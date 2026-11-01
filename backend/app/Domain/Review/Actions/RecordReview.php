<?php

namespace App\Domain\Review\Actions;

use App\Domain\Quran\Actions\ResolveQuranAmount;
use App\Domain\Review\Models\Review;
use App\Domain\Students\Models\Student;
use App\Domain\Tenancy\Models\Center;
use App\Models\User;
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

        $date = (string) $data['review_date'];
        $this->assertDateAllowed($student, $date, $actor->center);

        $source = (string) $data['amount_source'];
        $resolved = $this->amounts->execute($data, $source);

        if (! empty($data['client_uuid'])) {
            $existing = Review::query()
                ->where('center_id', $actor->center_id)
                ->where('client_uuid', $data['client_uuid'])
                ->first();

            if ($existing !== null) {
                return [$existing, false];
            }
        }

        $record = Review::create([
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
            'client_uuid' => $data['client_uuid'] ?? null,
        ]);

        return [$record, true];
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
