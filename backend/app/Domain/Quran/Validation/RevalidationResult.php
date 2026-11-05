<?php

namespace App\Domain\Quran\Validation;

use App\Domain\Quran\Import\ValidationReport;

/**
 * What a revalidation found (S6.2).
 *
 * It reports three independent comparisons, because they can disagree:
 *
 *  - the **checks** re-run against the stored rows;
 *  - the **digest** of those rows against the digest written at import;
 *  - the **status** the snapshot carries against the status the checks produce.
 *
 * A snapshot can be structurally fine and still have drifted from the report
 * that cleared it, which is exactly the case the digest exists to catch.
 */
final readonly class RevalidationResult
{
    /**
     * @param  array<string, mixed>  $storedStats
     * @param  array<string, mixed>  $computedStats
     */
    public function __construct(
        public int $versionId,
        public string $storedStatus,
        public string $recomputedStatus,
        public ValidationReport $report,
        public ?string $storedDigest,
        public string $computedDigest,
        public string $checksum,
        public array $storedStats,
        public array $computedStats,
    ) {}

    public function digestMatches(): bool
    {
        return $this->storedDigest !== null && hash_equals($this->storedDigest, $this->computedDigest);
    }

    /**
     * Null when the snapshot predates the stats or has none stored: nothing to
     * compare, which is not the same as a mismatch.
     */
    public function statsMatch(): ?bool
    {
        if ($this->storedStats === []) {
            return null;
        }

        return $this->storedStats == $this->computedStats;
    }

    public function statusMatches(): bool
    {
        return $this->storedStatus === $this->recomputedStatus;
    }

    /**
     * True only when the data is intact *and* the snapshot is activatable.
     * Today that second half cannot hold: V-10 is skipped and critical.
     */
    public function healthy(): bool
    {
        return $this->recomputedStatus === ValidationReport::PASSED
            && $this->statusMatches()
            && $this->digestMatches()
            && $this->statsMatch() !== false;
    }
}
