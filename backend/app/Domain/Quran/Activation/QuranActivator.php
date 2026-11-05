<?php

namespace App\Domain\Quran\Activation;

use App\Domain\Quran\Import\ValidationReport;
use Illuminate\Support\Facades\DB;

/**
 * Switches the active Quran snapshot — explicitly, atomically, one at a time.
 *
 * The switch is a single transaction: the previous snapshot is deactivated and
 * the new one activated inside it, so no reader can observe "no active dataset"
 * between the two statements, and a failure anywhere leaves the previous
 * snapshot exactly as it was.
 *
 * Nothing else is touched. Snapshot rows keep their content, memorization,
 * review and recitation records are not read or written, and no evaluation is
 * recalculated: an amount already measured stays measured against the snapshot
 * recorded on it.
 *
 * Concurrency: PostgreSQL takes a transaction-scoped advisory lock, so two
 * activations cannot interleave, and the partial unique index on `is_active` is
 * the final guarantee if anything slips past it. SQLite serialises writers and
 * has no advisory locks; there the index is the only guarantee, which is enough
 * for tests but is not a claim about production concurrency.
 */
final class QuranActivator
{
    /**
     * Arbitrary but stable key for pg_advisory_xact_lock. Changing it would let
     * an old and a new deployment activate concurrently during a rolling deploy.
     */
    private const ADVISORY_LOCK_KEY = 918273645;

    public function activate(int $versionId): ActivationResult
    {
        return DB::transaction(function () use ($versionId): ActivationResult {
            $this->lockActivations();

            $version = DB::table('quran_dataset_versions')
                ->where('id', $versionId)
                ->lockForUpdate()
                ->first(['id', 'edition', 'source_version', 'validation_status', 'validation_report', 'is_active']);

            if ($version === null) {
                throw ActivationFailed::notFound($versionId);
            }

            if ((string) $version->validation_status !== ValidationReport::PASSED) {
                throw ActivationFailed::notPassed($versionId, (string) $version->validation_status);
            }

            $this->assertReportAgrees($version);

            $counts = $this->counts($versionId);
            $expected = $this->expected();

            $this->assertComplete($versionId, $counts, $expected);

            if ((bool) $version->is_active) {
                return new ActivationResult(
                    versionId: $versionId,
                    previousVersionId: null,
                    alreadyActive: true,
                    surahs: $counts['surahs'],
                    pages: $counts['pages'],
                    ayahs: $counts['ayahs'],
                    edition: (string) $version->edition,
                    sourceVersion: (string) $version->source_version,
                );
            }

            $previous = DB::table('quran_dataset_versions')
                ->where('is_active', true)
                ->lockForUpdate()
                ->pluck('id');

            // Deactivate first, then activate, both inside this transaction: the
            // unique index would reject the reverse order.
            DB::table('quran_dataset_versions')
                ->where('is_active', true)
                ->update(['is_active' => false, 'updated_at' => now()]);

            DB::table('quran_dataset_versions')
                ->where('id', $versionId)
                ->update(['is_active' => true, 'updated_at' => now()]);

            return new ActivationResult(
                versionId: $versionId,
                previousVersionId: $previous->first() === null ? null : (int) $previous->first(),
                alreadyActive: false,
                surahs: $counts['surahs'],
                pages: $counts['pages'],
                ayahs: $counts['ayahs'],
                edition: (string) $version->edition,
                sourceVersion: (string) $version->source_version,
            );
        });
    }

    private function lockActivations(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('SELECT pg_advisory_xact_lock('.self::ADVISORY_LOCK_KEY.')');
        }
    }

    /**
     * A row marked passed whose stored report says otherwise was not written by
     * the importer. Refuse it rather than trust the flag alone.
     */
    private function assertReportAgrees(object $version): void
    {
        $raw = $version->validation_report;

        if (! is_string($raw) || $raw === '') {
            return;
        }

        $report = json_decode($raw, true);

        if (! is_array($report)) {
            return;
        }

        if (($report['status'] ?? ValidationReport::PASSED) !== ValidationReport::PASSED) {
            throw ActivationFailed::reportDisagrees((int) $version->id);
        }

        if (($report['passed'] ?? true) === false) {
            throw ActivationFailed::reportDisagrees((int) $version->id);
        }
    }

    /**
     * @return array{surahs: int, pages: int, ayahs: int, orphans: int}
     */
    private function counts(int $versionId): array
    {
        $orphans = DB::table('quran_ayahs as a')
            ->leftJoin('quran_surahs as s', static function ($join): void {
                $join->on('s.dataset_version_id', '=', 'a.dataset_version_id')
                    ->on('s.id', '=', 'a.surah_id');
            })
            ->where('a.dataset_version_id', $versionId)
            ->whereNull('s.id')
            ->count();

        return [
            'surahs' => DB::table('quran_surahs')->where('dataset_version_id', $versionId)->count(),
            'pages' => DB::table('quran_pages')->where('dataset_version_id', $versionId)->count(),
            'ayahs' => DB::table('quran_ayahs')->where('dataset_version_id', $versionId)->count(),
            'orphans' => $orphans,
        ];
    }

    /**
     * @return array{surahs: int, pages: int, ayahs: int}
     */
    private function expected(): array
    {
        return [
            'surahs' => (int) config('quran.import.expected.surahs', 114),
            'pages' => (int) config('quran.import.expected.pages', 604),
            'ayahs' => (int) config('quran.import.expected.ayahs', 6236),
        ];
    }

    /**
     * A snapshot is only activatable if it is actually there: `passed` is a
     * statement about validation, not a substitute for the rows themselves.
     *
     * @param  array{surahs: int, pages: int, ayahs: int, orphans: int}  $counts
     * @param  array{surahs: int, pages: int, ayahs: int}  $expected
     */
    private function assertComplete(int $versionId, array $counts, array $expected): void
    {
        if ($counts['orphans'] > 0
            || $counts['surahs'] !== $expected['surahs']
            || $counts['pages'] !== $expected['pages']
            || $counts['ayahs'] !== $expected['ayahs']) {
            throw ActivationFailed::incompleteData($versionId, $counts, $expected);
        }
    }
}
