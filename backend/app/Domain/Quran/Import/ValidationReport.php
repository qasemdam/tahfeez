<?php

namespace App\Domain\Quran\Import;

/**
 * The outcome of the validation stage, check by check.
 *
 * A check is `passed`, `failed` or `skipped`. Skipped is not a pass: it means
 * the check could not run in this environment (no golden fixture, no profile for
 * the edition) and is recorded so a reviewer sees the gap instead of assuming
 * coverage.
 */
final readonly class ValidationReport
{
    public const PASSED = 'passed';

    public const FAILED = 'failed';

    public const SKIPPED = 'skipped';

    /**
     * @param  array<int, array{id: string, label: string, status: string, detail: string}>  $checks
     */
    public function __construct(public array $checks) {}

    /**
     * @param  array<int, array{id: string, label: string, status: string, detail: string}>  $checks
     */
    public static function fromChecks(array $checks): self
    {
        return new self($checks);
    }

    public function passed(): bool
    {
        foreach ($this->checks as $check) {
            if ($check['status'] === self::FAILED) {
                return false;
            }
        }

        return true;
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
     * @return array{passed: bool, checks: array<int, array{id: string, label: string, status: string, detail: string}>}
     */
    public function toArray(): array
    {
        return [
            'passed' => $this->passed(),
            'checks' => $this->checks,
        ];
    }
}
