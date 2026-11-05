<?php

namespace App\Console\Commands;

use App\Domain\Quran\Exceptions\QuranDataUnavailable;
use App\Domain\Quran\Import\ValidationReport;
use App\Domain\Quran\Validation\RevalidationResult;
use App\Domain\Quran\Validation\SnapshotRevalidator;
use Illuminate\Console\Command;
use Throwable;

/**
 * Re-checks a stored snapshot without touching it (S6.2).
 *
 * Read-only by construction: it reads the version row, reads the rows, runs the
 * validator, and prints. It never writes `validation_status`,
 * `validation_report`, `content_digest` or `is_active`.
 */
class ValidateQuranDataset extends Command
{
    protected $signature = 'quran:validate
                            {version_id : The id of the dataset version to re-check}';

    protected $description = 'Re-validate a stored Quran dataset snapshot (read-only)';

    public function handle(SnapshotRevalidator $revalidator): int
    {
        $versionId = (int) $this->argument('version_id');

        if ($versionId < 1) {
            $this->components->error('A dataset version id is required.');

            return self::FAILURE;
        }

        try {
            $result = $revalidator->revalidate($versionId);
        } catch (QuranDataUnavailable $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        } catch (Throwable $exception) {
            $this->components->error('Revalidation failed unexpectedly ('.class_basename($exception).').');

            return self::FAILURE;
        }

        $this->newLine();
        $this->table(
            ['Check', 'Status', 'Rule', 'Detail'],
            array_map(static fn (array $check): array => [
                $check['id'],
                match ($check['status']) {
                    ValidationReport::PASSED => '<fg=green>passed</>',
                    ValidationReport::FAILED => '<fg=red>FAILED</>',
                    default => '<fg=yellow>skipped</>',
                },
                $check['label'],
                $check['detail'],
            ], $result->report->checks),
        );

        $this->newLine();
        $this->components->twoColumnDetail('Stored status', $result->storedStatus);
        $this->components->twoColumnDetail('Recomputed status', $this->colourStatus($result->recomputedStatus));
        $this->components->twoColumnDetail('Status agrees', $result->statusMatches() ? '<fg=green>yes</>' : '<fg=red>NO</>');
        $this->components->twoColumnDetail('File checksum', $result->checksum);
        $this->components->twoColumnDetail(
            'Content digest',
            $this->digestLine($result),
        );
        $this->components->twoColumnDetail('Counts and stats', $this->statsLine($result));

        if (! $result->statusMatches()) {
            $this->newLine();
            $this->components->warn(
                "The snapshot says [{$result->storedStatus}] but the stored rows validate as [{$result->recomputedStatus}].",
            );
        }

        if ($result->digestMatches() === false) {
            $this->newLine();
            $this->components->error(
                'The content digest does not match: the stored rows are not the rows that were imported.',
            );
        }

        if ($result->statsMatch() === false) {
            $this->newLine();
            $this->components->error('The stored statistics no longer describe the stored rows.');
        }

        $skipped = array_column($result->report->skipped(), 'id');

        if ($skipped !== []) {
            $this->newLine();
            $this->components->warn('Skipped checks: '.implode(', ', $skipped).' — see docs/quran-import.md.');
        }

        $this->newLine();

        if ($result->healthy()) {
            $this->components->info('The snapshot is intact and activatable.');

            return self::SUCCESS;
        }

        if ($result->report->failures() !== []) {
            $this->components->error(
                'The snapshot does not pass validation: '.implode(', ', array_column($result->report->failures(), 'id')),
            );

            return self::FAILURE;
        }

        $this->components->warn(
            'The snapshot is intact but not complete: a critical check could not run, so it cannot be activated.',
        );

        return self::FAILURE;
    }

    private function colourStatus(string $status): string
    {
        return match ($status) {
            ValidationReport::PASSED => '<fg=green>'.$status.'</>',
            ValidationReport::INCOMPLETE => '<fg=yellow>'.$status.'</>',
            default => '<fg=red>'.$status.'</>',
        };
    }

    private function digestLine(RevalidationResult $result): string
    {
        if ($result->storedDigest === null) {
            return '<fg=yellow>not stored</> (snapshot predates the digest) — computed '.substr($result->computedDigest, 0, 16).'…';
        }

        return $result->digestMatches()
            ? '<fg=green>matches</> '.substr($result->computedDigest, 0, 16).'…'
            : '<fg=red>MISMATCH</> stored '.substr($result->storedDigest, 0, 16).'… vs computed '.substr($result->computedDigest, 0, 16).'…';
    }

    private function statsLine(RevalidationResult $result): string
    {
        $match = $result->statsMatch();

        if ($match === null) {
            return '<fg=yellow>not stored</> — recomputed '.$this->statsSummary($result->computedStats);
        }

        return $match
            ? '<fg=green>match</> '.$this->statsSummary($result->computedStats)
            : '<fg=red>differ</> stored '.$this->statsSummary($result->storedStats).' / now '.$this->statsSummary($result->computedStats);
    }

    /**
     * @param  array<string, mixed>  $stats
     */
    private function statsSummary(array $stats): string
    {
        return sprintf(
            '%s surahs, %s pages, %s ayahs',
            $stats['surahs'] ?? '?',
            $stats['pages'] ?? '?',
            $stats['ayahs'] ?? '?',
        );
    }
}
