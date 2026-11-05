<?php

namespace App\Domain\Quran\Activation;

use RuntimeException;

/**
 * Activation was refused.
 *
 * Every reason is a refusal to change state, not a partial activation: the
 * previous snapshot stays active and the target is untouched.
 */
final class ActivationFailed extends RuntimeException
{
    public static function notFound(int $versionId): self
    {
        return new self("Quran dataset version {$versionId} does not exist.");
    }

    public static function notPassed(int $versionId, string $status): self
    {
        return new self(
            "Quran dataset version {$versionId} has status [{$status}]; only a snapshot that passed validation can be activated.",
        );
    }

    public static function reportDisagrees(int $versionId): self
    {
        return new self(
            "Quran dataset version {$versionId} is marked passed but its stored validation report says otherwise.",
        );
    }

    public static function archived(int $versionId): self
    {
        return new self("Quran dataset version {$versionId} is archived and cannot be activated.");
    }

    public static function digestMissing(int $versionId): self
    {
        return new self(
            "Quran dataset version {$versionId} has no content digest, so its rows cannot be verified against the import.",
        );
    }

    public static function digestMismatch(int $versionId): self
    {
        return new self(
            "Quran dataset version {$versionId} does not match its content digest: the stored rows are not the rows that were validated.",
        );
    }

    /**
     * @param  array{surahs: int, pages: int, ayahs: int, orphans: int}  $counts
     * @param  array{surahs: int, pages: int, ayahs: int}  $expected
     */
    public static function incompleteData(int $versionId, array $counts, array $expected): self
    {
        return new self(sprintf(
            'Quran dataset version %d is not complete: %d/%d surahs, %d/%d pages, %d/%d ayahs, %d orphan ayahs.',
            $versionId,
            $counts['surahs'],
            $expected['surahs'],
            $counts['pages'],
            $expected['pages'],
            $counts['ayahs'],
            $expected['ayahs'],
            $counts['orphans'],
        ));
    }
}
