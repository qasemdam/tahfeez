<?php

namespace App\Domain\Recitation\Actions;

use App\Domain\Quran\Contracts\QuranDataProvider;
use App\Domain\Quran\Exceptions\QuranDataUnavailable;
use App\Domain\Recitation\Models\Recitation;
use App\Domain\Students\Models\Student;
use App\Domain\Tenancy\Models\Center;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Records a recitation. The provider validates the surah/ayah range and
 * resolves the page span; no amount is measured and no score is computed.
 *
 * Multiple recitations per student per day are allowed; idempotency is by
 * `client_uuid`.
 */
class RecordRecitation
{
    public function __construct(private readonly QuranDataProvider $quran) {}

    /**
     * @return array{0: Recitation, 1: bool} [record, created]
     */
    public function execute(User $actor, array $data): array
    {
        $student = Student::query()
            ->where('center_id', $actor->center_id)
            ->findOrFail((int) $data['student_id']);

        if (! $student->isActive()) {
            throw ValidationException::withMessages([
                'student_id' => ['لا يمكن تسجيل تسميع لطالب غير مفعّل.'],
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

        // Idempotency runs before the provider resolves the range: a retry must
        // answer with the record the first attempt produced, even if the Quran
        // dataset is not available at retry time.
        $uuid = (string) ($data['client_uuid'] ?? '');

        if ($uuid !== '') {
            $existing = $this->findByClientUuid($actor, $uuid);

            if ($existing !== null) {
                return $this->replay($actor, $existing, $student);
            }
        }

        $date = (string) $data['recitation_date'];
        $this->assertDateAllowed($student, $date, $actor->center);

        $surahId = (int) $data['surah_id'];
        $fromNumber = (int) $data['from_ayah'];
        $toNumber = (int) $data['to_ayah'];

        if ($fromNumber > $toNumber) {
            throw ValidationException::withMessages([
                'from_ayah' => ['بداية النطاق بعد نهايته.'],
            ]);
        }

        try {
            $start = $this->quran->ayahLocation($surahId, $fromNumber);
            $end = $this->quran->ayahLocation($surahId, $toNumber);
        } catch (QuranDataUnavailable $e) {
            throw ValidationException::withMessages(['surah_id' => [$e->getMessage()]]);
        }

        $fromPage = $start->pageStart;
        $toPage = $end->pageEnd;

        $this->assertPageMatches($data, 'from_page', $fromPage);
        $this->assertPageMatches($data, 'to_page', $toPage);

        $attributes = [
            'center_id' => $actor->center_id,
            'student_id' => $student->id,
            'group_id' => $group->id,
            'teacher_id' => $actor->id,
            'recitation_date' => $date,
            'surah_id' => $surahId,
            'from_ayah_id' => $start->ayahId,
            'to_ayah_id' => $end->ayahId,
            'from_ayah_number' => $fromNumber,
            'to_ayah_number' => $toNumber,
            'from_page' => $fromPage,
            'to_page' => $toPage,
            'grade' => $data['grade'] ?? null,
            'hifz_errors' => $data['hifz_errors'] ?? null,
            'tajweed_errors' => $data['tajweed_errors'] ?? null,
            'reading_errors' => $data['reading_errors'] ?? null,
            'notes' => $data['notes'] ?? null,
            'quran_dataset_version_id' => $this->quran->datasetVersionId(),
            'quran_dataset_version' => $this->quran->datasetVersion(),
            'client_uuid' => $uuid === '' ? null : $uuid,
        ];

        try {
            $record = Recitation::create($attributes);
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
     * @return array{0: Recitation, 1: bool}
     */
    private function replay(User $actor, Recitation $existing, Student $student): array
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
    private function findByClientUuid(User $actor, string $uuid): ?Recitation
    {
        return Recitation::withTrashed()
            ->where('center_id', $actor->center_id)
            ->where('client_uuid', $uuid)
            ->first();
    }

    /**
     * If the client sent a page, it must agree with the provider. The server
     * never stores a page span that contradicts the ayah range.
     */
    private function assertPageMatches(array $data, string $field, int $expected): void
    {
        if (! isset($data[$field])) {
            return;
        }

        if ((int) $data[$field] !== $expected) {
            throw ValidationException::withMessages([
                $field => ['نطاق الصفحات لا يطابق نطاق الآيات.'],
            ]);
        }
    }

    private function assertDateAllowed(Student $student, string $date, Center $center): void
    {
        $today = now($center->timezone)->toDateString();

        if ($date > $today) {
            throw ValidationException::withMessages([
                'recitation_date' => ['لا يمكن تسجيل تسميع بتاريخ مستقبلي.'],
            ]);
        }

        if ($student->enrolled_at !== null && $date < $student->enrolled_at->toDateString()) {
            throw ValidationException::withMessages([
                'recitation_date' => ['لا يمكن تسجيل تسميع قبل تاريخ تسجيل الطالب.'],
            ]);
        }
    }
}
