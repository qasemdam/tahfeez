<?php

namespace App\Domain\Recitation\Actions;

use App\Domain\Recitation\Models\Recitation;

/**
 * Soft-deletes a recitation record. History is retained; the row is hidden
 * from default queries.
 */
class DeleteRecitation
{
    public function execute(Recitation $record): void
    {
        $record->delete();
    }
}
