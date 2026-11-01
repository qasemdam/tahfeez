<?php

namespace App\Domain\Evaluation\Actions;

use App\Domain\Evaluation\Models\WeeklyEvaluation;
use Illuminate\Validation\ValidationException;

/**
 * Approves a computed evaluation. The computed values and snapshots are never
 * touched; a correction/revision flow is deliberately out of scope.
 */
class ApproveWeeklyEvaluation
{
    public function execute(WeeklyEvaluation $evaluation, string $status): WeeklyEvaluation
    {
        if ($status !== WeeklyEvaluation::STATUS_APPROVED) {
            throw ValidationException::withMessages([
                'status' => ['الحالة المسموحة هي approved فقط.'],
            ]);
        }

        if ($evaluation->isApproved()) {
            return $evaluation;
        }

        $evaluation->status = WeeklyEvaluation::STATUS_APPROVED;
        $evaluation->save();

        return $evaluation;
    }
}
