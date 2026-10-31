<?php

namespace App\Domain\Recitation\Actions;

use App\Domain\Quran\Contracts\QuranDataProvider;
use App\Domain\Quran\Exceptions\QuranDataUnavailable;
use App\Domain\Recitation\Models\Recitation;
use App\Domain\Students\Models\Student;
use App\Domain\Tenancy\Models\Center;
use App\Models\User;
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

        if (! empty($data['client_uuid'])) {
            $existing = Recitation::query()
                ->where('center_id', $actor->center_id)
                ->where('client_uuid', $data['client_uuid'])
                ->first();

            if ($existing !== null) {
                return [$existing, false];
            }
        }

        $record = Recitation::create([
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
            'client_uuid' => $data['client_uuid'] ?? null,
        ]);

        return [$record, true];
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
