<?php

namespace App\Domain\Reports\Support;

use Brick\Math\BigDecimal;
use Illuminate\Support\Collection;

/**
 * Sums decimal amounts per student in PHP.
 *
 * A SQL `SUM()` over a decimal column returns a float on SQLite and would drift
 * (BR-EVL-02). Both reports share this so the arithmetic cannot diverge.
 */
final class AmountTotals
{
    /**
     * @param  Collection<int, mixed>  $rows  rows exposing `student_id` and `amount_pages`
     * @return array<int, array{pages: string, sessions: int}>
     */
    public static function byStudent(Collection $rows): array
    {
        $sums = [];
        $counts = [];

        foreach ($rows as $row) {
            $studentId = (int) $row->student_id;
            $sums[$studentId] = ($sums[$studentId] ?? BigDecimal::zero())
                ->plus(BigDecimal::of((string) $row->amount_pages));
            $counts[$studentId] = ($counts[$studentId] ?? 0) + 1;
        }

        $result = [];

        foreach ($sums as $studentId => $sum) {
            $result[$studentId] = [
                'pages' => (string) $sum->toScale(4),
                'sessions' => $counts[$studentId],
            ];
        }

        return $result;
    }
}
