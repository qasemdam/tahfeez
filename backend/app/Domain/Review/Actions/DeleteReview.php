<?php

namespace App\Domain\Review\Actions;

use App\Domain\Review\Models\Review;

/**
 * Soft-deletes a review record. History is retained; the row is hidden from
 * default queries.
 */
class DeleteReview
{
    public function execute(Review $record): void
    {
        $record->delete();
    }
}
