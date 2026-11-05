<?php

namespace App\Console\Commands;

use App\Domain\Quran\Activation\ActivationFailed;
use App\Domain\Quran\Activation\QuranActivator;
use Illuminate\Console\Command;
use Throwable;

/**
 * Makes one validated snapshot the active one (Phase 7, S4).
 *
 * The command changes nothing unless every condition holds; a refusal leaves the
 * previous snapshot active and the target untouched.
 */
class ActivateQuranDataset extends Command
{
    protected $signature = 'quran:activate
                            {version_id : The id of the dataset version to activate}';

    protected $description = 'Activate a validated Quran dataset snapshot';

    public function handle(QuranActivator $activator): int
    {
        $versionId = (int) $this->argument('version_id');

        if ($versionId < 1) {
            $this->components->error('A dataset version id is required.');

            return self::FAILURE;
        }

        try {
            $result = $activator->activate($versionId);
        } catch (ActivationFailed $exception) {
            $this->components->error($exception->getMessage());
            $this->line('  Nothing was changed: the previously active snapshot, if any, is still active.');

            return self::FAILURE;
        } catch (Throwable $exception) {
            // Same rule as the import: never echo a driver message.
            $this->components->error('Activation failed unexpectedly ('.class_basename($exception).'). Nothing was changed.');

            return self::FAILURE;
        }

        $this->newLine();
        $this->components->twoColumnDetail('Snapshot', '#'.$result->versionId);
        $this->components->twoColumnDetail('Edition', $result->edition);
        $this->components->twoColumnDetail('Source version', $result->sourceVersion);
        $this->components->twoColumnDetail(
            'Surahs / pages / ayahs',
            sprintf('%d / %d / %d', $result->surahs, $result->pages, $result->ayahs),
        );

        if ($result->alreadyActive) {
            $this->newLine();
            $this->components->info('This snapshot was already active. Nothing changed.');

            return self::SUCCESS;
        }

        $this->components->twoColumnDetail(
            'Previous snapshot',
            $result->previousVersionId === null ? 'none' : '#'.$result->previousVersionId.' (deactivated)',
        );

        $this->newLine();
        $this->components->info('Activated. The database provider serves this snapshot from the next request.');

        if ($result->previousVersionId !== null) {
            $this->line('  The previous snapshot keeps its data; amounts already measured against it are unchanged.');
        }

        return self::SUCCESS;
    }
}
