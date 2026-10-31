<?php

namespace App\Domain\Review\Actions;

use App\Domain\Review\Models\Review;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Validation\ValidationException;

/**
 * Corrects grade, error counts, notes and (for MANUAL records) the amount.
 * The amount source, student, date, group and Quran range are immutable.
 */
class UpdateReview
{
    public function execute(Review $record, array $data): Review
    {
        if (array_key_exists('amount_pages', $data) && $data['amount_pages'] !== null) {
            if ($record->amount_source !== Review::SOURCE_MANUAL) {
                throw ValidationException::withMessages([
                    'amount_pages' => ['لا يمكن تعديل مقدار مراجعة محسوب يدويًا.'],
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
