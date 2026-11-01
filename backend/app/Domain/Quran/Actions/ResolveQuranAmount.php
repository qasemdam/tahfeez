<?php

namespace App\Domain\Quran\Actions;

use App\Domain\Quran\Contracts\QuranDataProvider;
use App\Domain\Quran\Exceptions\QuranDataUnavailable;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Validation\ValidationException;

/**
 * Resolves an amount for a memorization or review record.
 *
 * The measurement itself lives only in `QuranDataProvider` (BR-MEM-06 /
 * BR-REV-03); this class orchestrates the two allowed sources:
 *  - MANUAL: the caller supplies the page amount; the range is optional.
 *  - CALCULATED: the provider measures the range; a failure is a clear 422.
 */
class ResolveQuranAmount
{
    public const SOURCE_MANUAL = 'MANUAL';

    public const SOURCE_CALCULATED = 'CALCULATED';

    public function __construct(private readonly QuranDataProvider $quran) {}

    /**
     * @return array{surah_id:?int, from_ayah_id:?int, to_ayah_id:?int, from_ayah_number:?int, to_ayah_number:?int, from_page:?int, to_page:?int, amount_pages:string, amount_lines:?int, dataset_version_id:?int, dataset_version:?string}
     */
    public function execute(array $data, string $source): array
    {
        return $source === self::SOURCE_MANUAL
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
}
