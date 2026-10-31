<?php

namespace App\Domain\Memorization\Actions;

use App\Domain\Memorization\Models\Memorization;
use App\Domain\Quran\Contracts\QuranDataProvider;
use App\Domain\Quran\Exceptions\QuranDataUnavailable;
use App\Domain\Students\Models\Student;
use App\Domain\Tenancy\Models\Center;
use App\Models\User;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Validation\ValidationException;

/**
 * Records new memorization in either mode:
 *  - MANUAL: the teacher supplies the page amount; the Quran range is optional.
 *  - CALCULATED: the provider measures the ayah range; the client never types
 *    the amount. A provider failure is a clear 422, never a guess.
 *
 * Multiple records per student per day are allowed (two sessions); idempotency
 * is by `client_uuid`, not by student+date.
 */
class RecordMemorization
{
    public function __construct(private readonly QuranDataProvider $quran) {}

    /**
     * @return array{0: Memorization, 1: bool} [record, created]
     */
    public function execute(User $actor, array $data): array
    {
        $student = Student::query()
            ->where('center_id', $actor->center_id)
            ->findOrFail((int) $data['student_id']);

        if (! $student->isActive()) {
            throw ValidationException::withMessages([
                'student_id' => ['لا يمكن تسجيل حفظ لطالب غير مفعّل.'],
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

        $date = (string) $data['memorization_date'];
        $this->assertDateAllowed($student, $date, $actor->center);

        $source = (string) $data['amount_source'];
        $resolved = $this->resolveAmount($data, $source);

        if (! empty($data['client_uuid'])) {
            $existing = Memorization::query()
                ->where('center_id', $actor->center_id)
                ->where('client_uuid', $data['client_uuid'])
                ->first();

            if ($existing !== null) {
                return [$existing, false];
            }
        }

        $record = Memorization::create([
            'center_id' => $actor->center_id,
            'student_id' => $student->id,
            'group_id' => $group->id,
            'teacher_id' => $actor->id,
            'memorization_date' => $date,
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

    /**
     * @return array<string, mixed>
     */
    private function resolveAmount(array $data, string $source): array
    {
        return $source === Memorization::SOURCE_MANUAL
            ? $this->resolveManual($data)
            : $this->resolveCalculated($data);
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveManual(array $data): array
    {
        if (! isset($data['amount_pages'])) {
            throw ValidationException::withMessages([
                'amount_pages' => ['المقدار مطلوب في الوضع اليدوي.'],
            ]);
        }

        $amount = BigDecimal::of((string) $data['amount_pages'])
            ->toScale(4, RoundingMode::HALF_UP)
            ->__toString();

        $surahId = isset($data['surah_id']) ? (int) $data['surah_id'] : null;
        $fromNumber = isset($data['from_ayah']) ? (int) $data['from_ayah'] : null;
        $toNumber = isset($data['to_ayah']) ? (int) $data['to_ayah'] : null;

        $fromId = null;
        $toId = null;
        $fromPage = isset($data['from_page']) ? (int) $data['from_page'] : null;
        $toPage = isset($data['to_page']) ? (int) $data['to_page'] : null;

        if ($surahId !== null || $fromNumber !== null || $toNumber !== null) {
            if ($surahId === null || $fromNumber === null || $toNumber === null) {
                throw ValidationException::withMessages([
                    'from_ayah' => ['أكمل نطاق الآيات أو اتركه فارغًا.'],
                ]);
            }

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

            $fromId = $start->ayahId;
            $toId = $end->ayahId;
            $fromPage ??= $start->pageStart;
            $toPage ??= $end->pageEnd;
        }

        return [
            'surah_id' => $surahId,
            'from_ayah_id' => $fromId,
            'to_ayah_id' => $toId,
            'from_ayah_number' => $fromNumber,
            'to_ayah_number' => $toNumber,
            'from_page' => $fromPage,
            'to_page' => $toPage,
            'amount_pages' => $amount,
            'amount_lines' => null,
            'dataset_version_id' => null,
            'dataset_version' => null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveCalculated(array $data): array
    {
        foreach (['surah_id', 'from_ayah', 'to_ayah'] as $field) {
            if (! isset($data[$field])) {
                throw ValidationException::withMessages([
                    $field => ['هذا الحقل مطلوب للحساب التلقائي.'],
                ]);
            }
        }

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
            $measurement = $this->quran->measure($start->ayahId, $end->ayahId);
        } catch (QuranDataUnavailable $e) {
            throw ValidationException::withMessages(['surah_id' => [$e->getMessage()]]);
        }

        return [
            'surah_id' => $surahId,
            'from_ayah_id' => $measurement->fromAyahId,
            'to_ayah_id' => $measurement->toAyahId,
            'from_ayah_number' => $fromNumber,
            'to_ayah_number' => $toNumber,
            'from_page' => $measurement->pageStart,
            'to_page' => $measurement->pageEnd,
            'amount_pages' => BigDecimal::of($measurement->amountPages)
                ->toScale(4, RoundingMode::HALF_UP)
                ->__toString(),
            'amount_lines' => $measurement->amountLines,
            'dataset_version_id' => $this->quran->datasetVersionId(),
            'dataset_version' => $this->quran->datasetVersion(),
        ];
    }

    private function assertDateAllowed(Student $student, string $date, Center $center): void
    {
        $today = now($center->timezone)->toDateString();

        if ($date > $today) {
            throw ValidationException::withMessages([
                'memorization_date' => ['لا يمكن تسجيل حفظ بتاريخ مستقبلي.'],
            ]);
        }

        if ($student->enrolled_at !== null && $date < $student->enrolled_at->toDateString()) {
            throw ValidationException::withMessages([
                'memorization_date' => ['لا يمكن تسجيل حفظ قبل تاريخ تسجيل الطالب.'],
            ]);
        }
    }
}
