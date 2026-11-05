<?php

namespace App\Domain\Reports\Export;

/**
 * Which report an export was built from.
 *
 * It only drives presentation (file name, page orientation); the figures
 * always come from the report query objects.
 */
enum ReportKind: string
{
    case Weekly = 'weekly';
    case Monthly = 'monthly';
    case Dashboard = 'dashboard';

    /**
     * Per-student reports carry too many columns for a portrait page.
     */
    public function landscape(): bool
    {
        return $this !== self::Dashboard;
    }
}
