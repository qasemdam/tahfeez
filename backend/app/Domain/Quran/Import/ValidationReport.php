<?php

namespace App\Domain\Quran\Import;

/**
 * The outcome of the validation stage, check by check.
 *
 * Three statuses, because two were not enough:
 *
 *  - `passed`      every check ran and none failed.
 *  - `failed`      at least one check failed; the dataset is stored but unusable.
 *  - `incomplete`  nothing failed, but a *critical* check was skipped. The
 *                  dataset parsed and is structurally sound, but a guarantee the
 *                  project relies on could not be demonstrated, so it must not
 *                  be activated.
 *
 * `DatabaseQuranDataProvider` only reads `passed`, so an incomplete snapshot is
 * stored without ever being served.
 *
 * Skipped is never a pass. The reason is kept with the check.
 */
final readonly class ValidationReport
{
    public const PASSED = 'passed';

    public const FAILED = 'failed';

    public const INCOMPLETE = 'incomplete';

    public const SKIPPED = 'skipped';

    /**
     * @param  array<int, array{id: string, label: string, status: string, detail: string}>  $checks
     * @param  array<string, mixed>  $stats
     */
    public function __construct(public array $checks, public array $stats = []) {}

    /**
     * @param  array<int, array{id: string, label: string, status: string, detail: string}>  $checks
     * @param  array<string, mixed>  $stats
     */
    public static function fromChecks(array $checks, array $stats = []): self
    {
        return new self($checks, $stats);
    }

    public function status(): string
    {
        foreach ($this->checks as $check) {
            if ($check['status'] === self::FAILED) {
                return self::FAILED;
            }
        }

        foreach ($this->checks as $check) {
            if ($check['status'] === self::SKIPPED && $this->isCritical($check['id'])) {
                return self::INCOMPLETE;
            }
        }

        return self::PASSED;
    }

    public function passed(): bool
    {
        return $this->status() === self::PASSED;
    }

    /**
     * A skipped check only blocks activation when it is listed as critical in
     * config/quran.php. A non-critical skip (informational coverage) is recorded
     * and does not change the status.
     */
    private function isCritical(string $id): bool
    {
        return in_array($id, (array) config('quran.import.critical_checks', []), true);
    }

    /**
     * @return array<int, array{id: string, label: string, status: string, detail: string}>
     */
    public function failures(): array
    {
        return array_values(array_filter(
            $this->checks,
            static fn (array $check): bool => $check['status'] === self::FAILED,
        ));
    }

    /**
     * @return array<int, array{id: string, label: string, status: string, detail: string}>
     */
    public function skipped(): array
    {
        return array_values(array_filter(
            $this->checks,
            static fn (array $check): bool => $check['status'] === self::SKIPPED,
        ));
    }

    /**
     * @return array{passed: bool, status: string, checks: array<int, array{id: string, label: string, status: string, detail: string}>, stats: array<string, mixed>}
     */
    public function toArray(): array
    {
        return [
            'passed' => $this->passed(),
            'status' => $this->status(),
            'checks' => $this->checks,
            'stats' => $this->stats,
        ];
    }
}
