<?php

namespace App\Domain\Recitation\Actions;

use App\Domain\Recitation\Models\Recitation;

/**
 * Corrects grade, error counts and notes. The student, date, group, author and
 * Quran range are immutable in this slice.
 */
class UpdateRecitation
{
    public function execute(Recitation $record, array $data): Recitation
    {
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
