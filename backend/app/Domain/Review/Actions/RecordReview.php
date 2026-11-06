<?php

namespace App\Domain\Review\Actions;

use App\Domain\Quran\Actions\ResolveQuranAmount;
use App\Domain\Review\Models\Review;
use App\Domain\Students\Models\Student;
use App\Domain\Tenancy\Models\Center;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Records a review (Murajaʿah). The amount uses the same shared resolver as
 * memorization, so the measurement lives only in QuranDataProvider.
 *
 * Multiple reviews per student per day are allowed; idempotency is by
 * `client_uuid`, not by student+date.
 */
class RecordReview
{
    public function __construct(private readonly ResolveQuranAmount $amounts) {}

    /**
     * @return array{0: Review, 1: bool} [record, created]
     */
    public function execute(User $actor, array $data): array
    {
        $student = Student::query()
            ->where('center_id', $actor->center_id)
            ->findOrFail((int) $data['student_id']);

        if (! $student->isActive()) {
            throw ValidationException::withMessages([
                'student_id' => ['لا يمكن تسجيل مراجعة لطالب غير مفعّل.'],
            ]);
        }

        $membership = $student->activeGroupMembership()->with('group')->first();

        if ($membership === null || $membership->group === null) {
            throw ValidationException::withMessages([
                'student_id' => ['الطالب غير مرتبط بحلقة نشطة.'],
            ]);
        }

        $group = $membership->group;

        // A teacher may only record for a student in one of their groups.
        if ($actor->isTeacher() && $group->teacher_id !== $actor->id) {
            abort(404);
        }

        // Idempotency runs before any measurement: a retry must answer with the
        // record the first attempt produced, even if the Quran dataset is not
        // available at retry time.
        $uuid = (string) ($data['client_uuid'] ?? '');

        if ($uuid !== '') {
            $existing = $this->findByClientUuid($actor, $uuid);

            if ($existing !== null) {
                return $this->replay($actor, $existing, $student);
            }
        }

        $date = (string) $data['review_date'];
        $this->assertDateAllowed($student, $date, $actor->center);

        $source = (string) $data['amount_source'];
        $resolved = $this->amounts->execute($data, $source);

        $attributes = [
            'center_id' => $actor->center_id,
            'student_id' => $student->id,
            'group_id' => $group->id,
            'teacher_id' => $actor->id,
            'review_date' => $date,
            'surah_id' => $resolved['surah_id'],
            'from_ayah_id' => $resolved['from_ayah_id'],
            'to_ayah_id' => $resolved['to_ayah_id'],
            'from_ayah_number' => $resolved['from_ayah_number'],
            'to_ayah_number' => $resolved['to_ayah_number'],
            'from_page' => $resolved['from_page'],
            'to_page' => $resolved['to_page'],
            'amount_pages' => $resolved['amount_pages'],
            'amount_lines' => $resolved['amount_lines'],
            'amount_source' => $source,
            'quran_dataset_version_id' => $resolved['dataset_version_id'],
            'quran_dataset_version' => $resolved['dataset_version'],
            'grade' => $data['grade'] ?? null,
            'hifz_errors' => $data['hifz_errors'] ?? null,
            'tajweed_errors' => $data['tajweed_errors'] ?? null,
            'reading_errors' => $data['reading_errors'] ?? null,
            'notes' => $data['notes'] ?? null,
            'client_uuid' => $uuid === '' ? null : $uuid,
        ];

        try {
            $record = Review::create($attributes);
        } catch (QueryException $exception) {
            // Two concurrent retries can both miss the lookup above; the unique
            // index then rejects the loser. Whoever won is the answer.
            $winner = $uuid === '' ? null : $this->findByClientUuid($actor, $uuid);

            if ($winner === null) {
                throw $exception;
            }

            return $this->replay($actor, $winner, $student);
        }

        return [$record, true];
    }

    /**
     * A replay belongs to one student. Returning another student's record would
     * swallow the second write silently, so it is a validation error instead.
     *
     * @return array{0: Review, 1: bool}
     */
    private function replay(User $actor, Review $existing, Student $student): array
    {
        if ((int) $existing->student_id !== (int) $student->id) {
            throw ValidationException::withMessages([
                'client_uuid' => ['client_uuid مستخدم لسجل طالب آخر.'],
            ]);
        }

        // Handing back a record the actor may not read would leak it — and a
        // soft-deleted record is still a record.
        Gate::forUser($actor)->authorize('view', $existing);

        return [$existing, false];
    }

    /**
     * Includes soft-deleted rows on purpose: the operation did happen, so a
     * retry has to find it rather than collide with the unique index on
     * (center_id, client_uuid). The row is returned as-is; it is never restored.
     */
    private function findByClientUuid(User $actor, string $uuid): ?Review
    {
        return Review::withTrashed()
            ->where('center_id', $actor->center_id)
            ->where('client_uuid', $uuid)
            ->first();
    }

    private function assertDateAllowed(Student $student, string $date, Center $center): void
    {
        $today = now($center->timezone)->toDateString();

        if ($date > $today) {
            throw ValidationException::withMessages([
                'review_date' => ['لا يمكن تسجيل مراجعة بتاريخ مستقبلي.'],
            ]);
        }

        if ($student->enrolled_at !== null && $date < $student->enrolled_at->toDateString()) {
            throw ValidationException::withMessages([
                'review_date' => ['لا يمكن تسجيل مراجعة قبل تاريخ تسجيل الطالب.'],
            ]);
        }
    }
}
