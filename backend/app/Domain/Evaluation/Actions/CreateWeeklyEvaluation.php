<?php

namespace App\Domain\Evaluation\Actions;

use App\Domain\Attendance\Models\Attendance;
use App\Domain\Evaluation\DTOs\AttendanceInput;
use App\Domain\Evaluation\DTOs\EvaluationInput;
use App\Domain\Evaluation\DTOs\RecitationInput;
use App\Domain\Evaluation\Models\EvaluationProfile;
use App\Domain\Evaluation\Models\EvaluationProfileVersion;
use App\Domain\Evaluation\Models\WeeklyEvaluation;
use App\Domain\Evaluation\Rules\EvaluationCriterion;
use App\Domain\Evaluation\Rules\EvaluationRuleSet;
use App\Domain\Evaluation\Rules\MissingDataPolicy;
use App\Domain\Evaluation\Services\EvaluationEngine;
use App\Domain\Evaluation\Services\WeekPeriodResolver;
use App\Domain\Evaluation\Support\RequiredPages;
use App\Domain\Evaluation\ValueObjects\EvaluationProfile as EvaluationProfileSnapshot;
use App\Domain\Memorization\Models\Memorization;
use App\Domain\Recitation\Models\Recitation;
use App\Domain\Review\Models\Review;
use App\Domain\Students\Models\Student;
use App\Models\User;
use Brick\Math\BigDecimal;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/**
 * Orchestrates a weekly evaluation:
 * resolve the week → gather the week's records → run the pure engine →
 * snapshot the inputs and the result → persist once per student/week.
 *
 * It never re-runs the engine for an existing week and never reads the current
 * profile/center setting when returning a historical evaluation.
 */
class CreateWeeklyEvaluation
{
    public function __construct(
        private readonly EvaluationEngine $engine,
        private readonly WeekPeriodResolver $weeks,
    ) {}

    /**
     * @return array{0: WeeklyEvaluation, 1: bool} [evaluation, created]
     */
    public function execute(User $actor, array $data): array
    {
        $student = Student::query()
            ->where('center_id', $actor->center_id)
            ->findOrFail((int) $data['student_id']);

        if (! $student->isActive()) {
            throw ValidationException::withMessages([
                'student_id' => ['لا يمكن تقييم طالب غير مفعّل.'],
            ]);
        }

        $membership = $student->activeGroupMembership()->with('group')->first();

        if ($membership === null || $membership->group === null) {
            throw ValidationException::withMessages([
                'student_id' => ['الطالب غير مرتبط بحلقة نشطة.'],
            ]);
        }

        $group = $membership->group;

        // A teacher may only compute for a student in one of their own groups —
        // the same structural scope as memorization/review/recitation. An
        // out-of-scope student is 404, never 403 (BR-TEN-02 / BR-AUT-02).
        if ($actor->isTeacher() && $group->teacher_id !== $actor->id) {
            abort(404);
        }

        $center = $actor->center;

        $referenceDate = (string) ($data['week_date'] ?? now($center->timezone)->toDateString());

        $period = $this->weeks->resolve(
            new DateTimeImmutable($referenceDate, new DateTimeZone($center->timezone)),
            $center->weekly_evaluation_start_day,
        );

        // Idempotency: a retried operation and a duplicate student/week both
        // return the existing historical row, never a recomputation.
        if (! empty($data['client_uuid'])) {
            $existing = WeeklyEvaluation::query()
                ->where('center_id', $center->id)
                ->where('client_uuid', $data['client_uuid'])
                ->first();

            if ($existing !== null) {
                // The uuid identifies one operation. Handing back another
                // student's row would leak it across the actor's scope.
                if ($existing->student_id !== $student->id) {
                    throw ValidationException::withMessages([
                        'client_uuid' => ['client_uuid مستخدم لسجل طالب آخر.'],
                    ]);
                }

                return [$existing, false];
            }
        }

        $existing = WeeklyEvaluation::query()
            ->where('center_id', $center->id)
            ->where('student_id', $student->id)
            ->where('week_key', $period->key)
            ->first();

        if ($existing !== null) {
            return [$existing, false];
        }

        $profileVersion = $this->resolveProfileVersion($student, $period->start, $data['profile_version_id'] ?? null);

        $requiredPages = $profileVersion?->required_pages ?? RequiredPages::DEFAULT;
        $ruleSetJson = $profileVersion?->rule_set;
        $ruleSet = $this->hydrateRuleSet($ruleSetJson);
        $gradeScores = is_array($ruleSetJson) ? ($ruleSetJson['recitation_grade_scores'] ?? []) : [];

        $memorizationPages = $this->sumDecimal(
            Memorization::query()
                ->where('center_id', $center->id)
                ->where('student_id', $student->id)
                ->whereBetween('memorization_date', [$period->start, $period->end])
                ->pluck('amount_pages')
        );

        $reviewPages = $this->reviewPages($center->id, $student->id, $period->start, $period->end);
        $recitationInput = $this->recitationInput($center->id, $student->id, $period->start, $period->end, $gradeScores);
        $attendanceInput = $this->attendanceInput($center->id, $student->id, $period->start, $period->end);

        $engineProfile = $profileVersion !== null
            ? new EvaluationProfileSnapshot(
                version: $profileVersion->version_number,
                effectiveFrom: $profileVersion->effective_from->toDateString(),
                requiredPages: (string) $profileVersion->required_pages,
                templateVersionId: null,
            )
            : null;

        $input = new EvaluationInput(
            requiredPages: $requiredPages,
            actualPages: $memorizationPages,
            reviewPages: $reviewPages,
            recitation: $recitationInput,
            attendance: $attendanceInput,
            profile: $engineProfile,
            ruleSet: $ruleSet,
        );

        try {
            $result = $this->engine->evaluate($input);
        } catch (InvalidArgumentException $e) {
            throw ValidationException::withMessages([
                'profile_version_id' => [$e->getMessage()],
            ]);
        }

        $rulesSnapshot = [
            'profile_version_id' => $profileVersion?->id,
            'profile_version' => $profileVersion?->version_number,
            'profile_effective_from' => $profileVersion?->effective_from?->toDateString(),
            'template_version_id' => null,
            'required_pages' => $requiredPages,
            'weekly_evaluation_start_day' => $center->weekly_evaluation_start_day->value,
            'week_key' => $period->key,
            'week_start_date' => $period->start,
            'week_end_date' => $period->end,
            'timezone' => $center->timezone,
            'rule_set' => $ruleSetJson,
            'recitation_grade_scores' => $gradeScores,
            'inputs' => [
                'memorization_pages' => $memorizationPages,
                'review_pages' => $reviewPages,
                'recitation_scores' => $recitationInput?->scores ?? [],
                'recitation_errors' => [
                    'hifz' => $recitationInput?->hifzErrors ?? 0,
                    'tajweed' => $recitationInput?->tajweedErrors ?? 0,
                    'reading' => $recitationInput?->readingErrors ?? 0,
                ],
                'attendance' => $attendanceInput !== null ? [
                    'present' => $attendanceInput->present,
                    'late' => $attendanceInput->late,
                    'excused_absence' => $attendanceInput->excusedAbsence,
                    'unexcused_absence' => $attendanceInput->unexcusedAbsence,
                ] : null,
            ],
        ];

        try {
            $evaluation = DB::transaction(static fn (): WeeklyEvaluation => WeeklyEvaluation::create([
                'center_id' => $center->id,
                'student_id' => $student->id,
                'group_id' => $group->id,
                'teacher_id' => $group->teacher_id,
                'week_key' => $period->key,
                'week_start_date' => $period->start,
                'week_end_date' => $period->end,
                'weekly_evaluation_start_day' => $center->weekly_evaluation_start_day->value,
                'evaluation_profile_version_id' => $profileVersion?->id,
                'evaluation_profile_version' => $profileVersion?->version_number,
                'template_version_id' => null,
                'required_pages' => $requiredPages,
                'actual_pages' => $memorizationPages,
                'achievement_percentage' => $result->achievementPercentage,
                'weighted_score' => $result->weightedScore,
                'is_complete' => $result->isComplete,
                'status' => WeeklyEvaluation::STATUS_COMPUTED,
                'rules_snapshot' => $rulesSnapshot,
                'result_snapshot' => $result->toArray(),
                'client_uuid' => $data['client_uuid'] ?? null,
            ]));
        } catch (QueryException $e) {
            $winner = WeeklyEvaluation::query()
                ->where('center_id', $center->id)
                ->where('student_id', $student->id)
                ->where('week_key', $period->key)
                ->first();

            if ($winner !== null) {
                return [$winner, false];
            }

            throw $e;
        }

        return [$evaluation, true];
    }

    private function resolveProfileVersion(
        Student $student,
        string $weekStart,
        ?int $requestedId,
    ): ?EvaluationProfileVersion {
        $profile = EvaluationProfile::query()
            ->where('center_id', $student->center_id)
            ->where('student_id', $student->id)
            ->first();

        if ($profile === null) {
            return null;
        }

        if ($requestedId !== null) {
            $version = $profile->versions()->whereKey($requestedId)->firstOrFail();

            if ($version->effective_from->toDateString() > $weekStart) {
                throw ValidationException::withMessages([
                    'profile_version_id' => ['هذه النسخة غير سارية في بداية الأسبوع.'],
                ]);
            }

            return $version;
        }

        return $profile->versions()
            ->where('effective_from', '<=', $weekStart)
            ->orderByDesc('effective_from')
            ->orderByDesc('version_number')
            ->first();
    }

    /**
     * @param  array<string, mixed>|null  $json
     */
    private function hydrateRuleSet(?array $json): ?EvaluationRuleSet
    {
        if ($json === null || ! isset($json['criteria']) || ! is_array($json['criteria'])) {
            return null;
        }

        try {
            $criteria = [];

            foreach ($json['criteria'] as $row) {
                $criteria[] = new EvaluationCriterion(
                    key: (string) $row['key'],
                    weight: (string) $row['weight'],
                    calculator: (string) ($row['calculator'] ?? $row['key']),
                    missingDataPolicy: MissingDataPolicy::from((string) $row['missing_data_policy']),
                    params: (array) ($row['params'] ?? []),
                );
            }

            return new EvaluationRuleSet((string) ($json['version'] ?? 'v1'), $criteria);
        } catch (\Throwable $e) {
            throw ValidationException::withMessages([
                'profile_version_id' => ['Rule set غير صالح: '.$e->getMessage()],
            ]);
        }
    }

    private function reviewPages(int $centerId, int $studentId, string $start, string $end): ?string
    {
        $values = Review::query()
            ->where('center_id', $centerId)
            ->where('student_id', $studentId)
            ->whereBetween('review_date', [$start, $end])
            ->pluck('amount_pages');

        return $values->isEmpty() ? null : $this->sumDecimal($values);
    }

    /**
     * @param  array<string, mixed>  $gradeScores
     */
    private function recitationInput(
        int $centerId,
        int $studentId,
        string $start,
        string $end,
        array $gradeScores,
    ): ?RecitationInput {
        $recitations = Recitation::query()
            ->where('center_id', $centerId)
            ->where('student_id', $studentId)
            ->whereBetween('recitation_date', [$start, $end])
            ->orderBy('recitation_date')
            ->orderBy('id')
            ->get();

        if ($recitations->isEmpty()) {
            return null;
        }

        $scores = [];
        $hifz = 0;
        $tajweed = 0;
        $reading = 0;

        foreach ($recitations as $recitation) {
            $hifz += (int) $recitation->hifz_errors;
            $tajweed += (int) $recitation->tajweed_errors;
            $reading += (int) $recitation->reading_errors;

            if ($recitation->grade !== null && isset($gradeScores[$recitation->grade])) {
                $scores[] = (string) $gradeScores[$recitation->grade];
            }
        }

        return new RecitationInput($scores, $hifz, $tajweed, $reading);
    }

    private function attendanceInput(int $centerId, int $studentId, string $start, string $end): ?AttendanceInput
    {
        $counts = Attendance::query()
            ->where('center_id', $centerId)
            ->where('student_id', $studentId)
            ->whereBetween('attendance_date', [$start, $end])
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        if ($counts->isEmpty()) {
            return null;
        }

        return new AttendanceInput(
            present: (int) ($counts['present'] ?? 0),
            late: (int) ($counts['late'] ?? 0),
            excusedAbsence: (int) ($counts['excused_absence'] ?? 0),
            unexcusedAbsence: (int) ($counts['unexcused_absence'] ?? 0),
        );
    }

    /**
     * @param  Collection<int, mixed>  $values
     */
    private function sumDecimal(Collection $values): string
    {
        $sum = BigDecimal::zero();

        foreach ($values as $value) {
            $sum = $sum->plus(BigDecimal::of((string) $value));
        }

        return $sum->toScale(4)->__toString();
    }
}
