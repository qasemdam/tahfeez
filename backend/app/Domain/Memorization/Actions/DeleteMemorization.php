<?php

namespace App\Domain\Memorization\Actions;

use App\Domain\Memorization\Models\Memorization;

/**
 * Soft-deletes a memorization record (BR-MEM, database.md §11). History is
 * retained; the row is hidden from default queries.
 */
class DeleteMemorization
{
    public function execute(Memorization $record): void
    {
        $record->delete();
    }
}
