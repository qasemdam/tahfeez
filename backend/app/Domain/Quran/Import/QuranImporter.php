<?php

namespace App\Domain\Quran\Import;

use App\Domain\Quran\Exceptions\QuranImportFailed;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Imports one dataset file as a new, inactive snapshot.
 *
 * Order matters and is not negotiable:
 *
 *   hash → duplicate guard → parse → re-hash → validate → write
 *
 * Validation happens before any child row is written, so a broken file never
 * leaves half a dataset behind. The snapshot row itself is written last for a
 * passing dataset (inside the transaction) and on its own for a failing one, so
 * a failed attempt is visible without ever being usable — the provider only
 * reads `validation_status = passed`.
 *
 * Nothing here activates the dataset. Activation is a separate, explicit step.
 */
final class QuranImporter
{
    private const CHUNK = 500;

    public function __construct(private DatasetValidator $validator) {}

    public function import(string $path, ImportOptions $options): ImportResult
    {
        if (! is_file($path) || ! is_readable($path)) {
            throw new QuranImportFailed("The file [{$path}] does not exist or is not readable.");
        }

        $checksum = $this->hash($path);

        $duplicate = DB::table('quran_dataset_versions')
            ->where('checksum_sha256', $checksum)
            ->where('validation_status', 'passed')
            ->first(['id', 'imported_at']);

        if ($duplicate !== null) {
            throw new QuranImportFailed(sprintf(
                'This file was already imported as version %d (checksum %s). Nothing was written.',
                (int) $duplicate->id,
                $checksum,
            ));
        }

        $payload = $this->source($options->format)->read($path);

        // V-11: the bytes must not change between the first hash and the write.
        $checksumAfterRead = $this->hash($path);

        $checksumCheck = $checksumAfterRead === $checksum
            ? ['id' => 'V-11', 'label' => 'checksum', 'status' => ValidationReport::PASSED, 'detail' => $checksum]
            : ['id' => 'V-11', 'label' => 'checksum', 'status' => ValidationReport::FAILED, 'detail' => 'the file changed while it was being read'];

        $report = ValidationReport::fromChecks([
            $checksumCheck,
            ...$this->validator->validate($payload)->checks,
        ]);

        if (! $report->passed()) {
            $versionId = $this->recordFailure($payload, $options, $checksum, $report);

            return new ImportResult(
                passed: false,
                report: $report,
                checksum: $checksum,
                versionId: $versionId,
                surahs: $payload->surahCount(),
                pages: $payload->pageCount(),
                ayahs: $payload->ayahCount(),
                message: 'Validation failed; the dataset was recorded as a failed snapshot and cannot be activated.',
            );
        }

        $versionId = $this->persist($payload, $options, $checksum, $report);

        return new ImportResult(
            passed: true,
            report: $report,
            checksum: $checksum,
            versionId: $versionId,
            surahs: $payload->surahCount(),
            pages: $payload->pageCount(),
            ayahs: $payload->ayahCount(),
            message: 'Imported as an inactive snapshot. Activate it explicitly when it has been reviewed.',
        );
    }

    private function hash(string $path): string
    {
        $hash = hash_file('sha256', $path);

        if ($hash === false) {
            throw new QuranImportFailed("Unable to hash [{$path}].");
        }

        return $hash;
    }

    private function source(string $format): QuranSource
    {
        /** @var array<string, class-string<QuranSource>> $sources */
        $sources = (array) config('quran.import.sources', []);
        $class = $sources[$format] ?? null;

        if ($class === null) {
            throw new QuranImportFailed(sprintf(
                'Unsupported source format [%s]. Supported: %s.',
                $format,
                implode(', ', array_keys($sources)),
            ));
        }

        return app($class);
    }

    private function persist(
        DatasetPayload $payload,
        ImportOptions $options,
        string $checksum,
        ValidationReport $report,
    ): int {
        try {
            return DB::transaction(function () use ($payload, $options, $checksum, $report): int {
                $versionId = $this->insertVersion($payload, $options, $checksum, $report, 'pending');

                $this->insertChildren($versionId, $payload);

                DB::table('quran_dataset_versions')
                    ->where('id', $versionId)
                    ->update([
                        'validation_status' => 'passed',
                        'validation_report' => json_encode($report->toArray(), JSON_UNESCAPED_UNICODE),
                        'updated_at' => now(),
                    ]);

                return $versionId;
            });
        } catch (Throwable $exception) {
            throw new QuranImportFailed(
                'The dataset could not be stored; nothing was written. '.$exception->getMessage(),
                0,
                $exception,
            );
        }
    }

    private function recordFailure(
        DatasetPayload $payload,
        ImportOptions $options,
        string $checksum,
        ValidationReport $report,
    ): int {
        return $this->insertVersion($payload, $options, $checksum, $report, 'failed');
    }

    private function insertVersion(
        DatasetPayload $payload,
        ImportOptions $options,
        string $checksum,
        ValidationReport $report,
        string $status,
    ): int {
        return (int) DB::table('quran_dataset_versions')->insertGetId([
            'source' => $options->source,
            'source_url' => $options->sourceUrl ?? '',
            'source_version' => $options->sourceVersion ?? $payload->sourceVersion,
            'edition' => $options->edition,
            'source_format' => $options->format,
            'downloaded_at' => now(),
            'imported_at' => now(),
            'checksum_sha256' => $checksum,
            'validation_status' => $status,
            'validation_report' => json_encode($report->toArray(), JSON_UNESCAPED_UNICODE),
            'is_active' => false,
            'notes' => $options->notes,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function insertChildren(int $versionId, DatasetPayload $payload): void
    {
        foreach (array_chunk($payload->surahs, self::CHUNK) as $chunk) {
            DB::table('quran_surahs')->insert($this->stamp($chunk, $versionId));
        }

        foreach (array_chunk($payload->pages, self::CHUNK) as $chunk) {
            DB::table('quran_pages')->insert($this->stamp($chunk, $versionId));
        }

        foreach (array_chunk($payload->ayahs, self::CHUNK) as $chunk) {
            DB::table('quran_ayahs')->insert($this->stamp($chunk, $versionId));
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array<string, mixed>>
     */
    private function stamp(array $rows, int $versionId): array
    {
        $now = now();

        return array_map(static fn (array $row): array => [
            ...$row,
            'dataset_version_id' => $versionId,
            'created_at' => $now,
            'updated_at' => $now,
        ], $rows);
    }
}
