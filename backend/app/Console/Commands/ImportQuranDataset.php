<?php

namespace App\Console\Commands;

use App\Domain\Quran\Exceptions\QuranImportFailed;
use App\Domain\Quran\Import\ImportOptions;
use App\Domain\Quran\Import\QuranImporter;
use App\Domain\Quran\Import\ValidationReport;
use Illuminate\Console\Command;
use Throwable;

/**
 * Imports a Quran dataset as a new, inactive snapshot (Phase 7, S3).
 *
 * The command never activates what it writes: activation is a separate, explicit
 * decision once the snapshot has been reviewed.
 */
class ImportQuranDataset extends Command
{
    protected $signature = 'quran:import
                            {path : Path to the dataset file}
                            {--edition= : Edition identifier, e.g. madinah_1405h_hafs_uthmani (required)}
                            {--format=json : Source format (see config/quran.php)}
                            {--source=file : Source identifier stored on the snapshot}
                            {--source-version= : Version string as published by the source}
                            {--source-url= : Where the file came from}
                            {--notes= : Free-form note stored with the snapshot}';

    protected $description = 'Validate and import a Quran dataset as a new inactive snapshot';

    public function handle(QuranImporter $importer): int
    {
        $path = (string) $this->argument('path');
        $edition = trim((string) $this->option('edition'));

        if ($edition === '') {
            $this->components->error(
                'The --edition option is required: coordinates are edition-specific and must never be assumed.',
            );

            return self::FAILURE;
        }

        $options = new ImportOptions(
            edition: $edition,
            format: (string) $this->option('format'),
            source: (string) $this->option('source'),
            sourceVersion: $this->option('source-version') === null ? null : (string) $this->option('source-version'),
            sourceUrl: $this->option('source-url') === null ? null : (string) $this->option('source-url'),
            notes: $this->option('notes') === null ? null : (string) $this->option('notes'),
        );

        try {
            $result = $importer->import($path, $options);
        } catch (QuranImportFailed $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        } catch (Throwable $exception) {
            $this->components->error('Unexpected failure: '.$exception->getMessage());

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
        $this->components->twoColumnDetail('Checksum (sha256)', $result->checksum);
        $this->components->twoColumnDetail('Surahs / pages / ayahs', sprintf('%d / %d / %d', $result->surahs, $result->pages, $result->ayahs));
        $this->components->twoColumnDetail('Snapshot', $result->versionId === null ? 'not created' : '#'.$result->versionId);
        $this->components->twoColumnDetail('Status', $result->passed ? 'passed (inactive)' : 'failed');

        if (! $result->passed) {
            $this->newLine();
            $this->components->error($result->message);
            $this->line('  Failures: '.implode(', ', array_column($result->report->failures(), 'id')));

            return self::FAILURE;
        }

        $skipped = array_column($result->report->skipped(), 'id');

        if ($skipped !== []) {
            $this->newLine();
            $this->components->warn('Skipped checks: '.implode(', ', $skipped).' — see docs/quran-import.md.');
        }

        $this->newLine();
        $this->components->info($result->message);

        return self::SUCCESS;
    }
}
