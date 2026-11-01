<?php

namespace App\Domain\Memorization\Actions;

use App\Domain\Memorization\Models\Memorization;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Validation\ValidationException;

/**
 * Corrects grade, error counts, notes and (for MANUAL records) the amount.
 *
 * The amount source, student, date, group and Quran range are immutable in
 * this slice: BR-MEM-02 forbids converting MANUAL into CALCULATED or back.
 */
class UpdateMemorization
{
    public function execute(Memorization $record, array $data): Memorization
    {
        if (array_key_exists('amount_pages', $data) && $data['amount_pages'] !== null) {
            if ($record->amount_source !== Memorization::SOURCE_MANUAL) {
                throw ValidationException::withMessages([
                    'amount_pages' => ['لا يمكن تعديل مقدار سجل محسوب يدويًا.'],
                ]);
            }

            $record->amount_pages = BigDecimal::of((string) $data['amount_pages'])
                ->toScale(4, RoundingMode::HALF_UP)
                ->__toString();
        }

        $record->fill(array_intersect_key($data, array_flip([
            'grade',
            'hifz_errors',
            'tajweed_errors',
            'reading_errors',
            'notes',
        ])));

        $record->save();

        return $record;
    }
}
