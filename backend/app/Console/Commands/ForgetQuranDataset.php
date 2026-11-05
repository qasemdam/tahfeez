<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Retires a dataset snapshot (S6.5).
 *
 * Accepted snapshots cannot be deleted — that is what 000700 is for — so this
 * command never drops a trigger to get around it. It picks the safe action for
 * the snapshot's state instead:
 *
 *  - `pending` / `failed`: nothing depends on them and no rows are served, so
 *    they are deleted outright (the triggers allow it);
 *  - `passed` / `incomplete`: archived with `archived_at`, rows untouched.
 *
 * Refusals, in order: the active snapshot, and any snapshot that historical
 * records point at. Neither is negotiable.
 */
class ForgetQuranDataset extends Command
{
    protected $signature = 'quran:forget
                            {version_id : The id of the dataset version to retire}
                            {--confirm= : Must repeat the version id}
                            {--force : Required; without it the command only reports what it would do}
                            {--reason= : Why the snapshot is being retired (recorded)}
                            {--actor= : Who is running it (defaults to the OS user)}';

    protected $description = 'Delete or archive a Quran dataset snapshot that nothing depends on';

    public function handle(): int
    {
        $versionId = (int) $this->argument('version_id');
        $confirm = (string) $this->option('confirm');

        if ($versionId < 1 || $confirm !== (string) $versionId) {
            $this->components->error('Pass the version id and repeat it in --confirm to make the target explicit.');

            return self::FAILURE;
        }

        $version = DB::table('quran_dataset_versions')
            ->where('id', $versionId)
            ->first(['id', 'validation_status', 'is_active', 'archived_at']);

        if ($version === null) {
            $this->components->error("Quran dataset version {$versionId} does not exist.");

            return self::FAILURE;
        }

        if ((bool) $version->is_active) {
            $this->components->error('This snapshot is active. Activate another one first; an active snapshot is never removed.');

            return self::FAILURE;
        }

        if ($version->archived_at !== null) {
            $this->components->info('This snapshot is already archived. Nothing to do.');

            return self::SUCCESS;
        }

        $references = $this->references($versionId);

        if ($references > 0) {
            $this->components->error(sprintf(
                'This snapshot is referenced by %d historical record(s). Historical data is never rewritten or orphaned.',
                $references,
            ));

            return self::FAILURE;
        }

        $mode = in_array((string) $version->validation_status, ['pending', 'failed'], true) ? 'delete' : 'archive';
        $actor = (string) ($this->option('actor') ?: (get_current_user() ?: 'cli'));
        $reason = (string) ($this->option('reason') ?: 'no reason given');

        if (! $this->option('force')) {
            $this->components->warn(sprintf(
                'Would %s version #%d (status: %s). Re-run with --force to apply.',
                $mode,
                $versionId,
                (string) $version->validation_status,
            ));

            return self::SUCCESS;
        }

        if ($mode === 'delete') {
            DB::table('quran_dataset_versions')->where('id', $versionId)->delete();
        } else {
            DB::table('quran_dataset_versions')->where('id', $versionId)->update([
                'archived_at' => now(),
                'updated_at' => now(),
                'notes' => trim(sprintf(
                    '%s | archived %s by %s: %s',
                    (string) DB::table('quran_dataset_versions')->where('id', $versionId)->value('notes'),
                    now()->toIso8601String(),
                    $actor,
                    $reason,
                )),
            ]);
        }

        Log::notice('quran.dataset.forget', [
            'version_id' => $versionId,
            'mode' => $mode,
            'actor' => $actor,
            'reason' => $reason,
        ]);

        $this->components->info(sprintf('Version #%d %s. %s', $versionId, $mode === 'delete' ? 'deleted' : 'archived', $reason));

        return self::SUCCESS;
    }

    /**
     * Historical rows that point at this snapshot. They are the reason a
     * snapshot cannot simply disappear: an amount measured against it would lose
     * the version it was measured against.
     */
    private function references(int $versionId): int
    {
        $total = 0;

        foreach (['memorizations', 'reviews', 'recitations'] as $table) {
            $total += DB::table($table)->where('quran_dataset_version_id', $versionId)->count();
        }

        return $total;
    }
}
