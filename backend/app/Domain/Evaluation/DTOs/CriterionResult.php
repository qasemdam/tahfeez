<?php

namespace App\Domain\Evaluation\DTOs;

/**
 * One criterion's contribution. `normalized` is the criterion score
 * (0..100, or above 100 for uncapped memorization volume); `weighted` is
 * `normalized x weight / 100`. The aggregate renormalises by the included
 * weight, so excluded criteria do not distort the result.
 */
final readonly class CriterionResult
{
    public function __construct(
        public string $key,
        public string $weight,
        public ?string $rawValue,
        public ?string $normalized,
        public ?string $weighted,
        public bool $included,
        public bool $blocked,
        public string $explanation,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'weight' => $this->weight,
            'raw_value' => $this->rawValue,
            'normalized' => $this->normalized,
            'weighted' => $this->weighted,
            'included' => $this->included,
            'blocked' => $this->blocked,
            'explanation' => $this->explanation,
        ];
    }
}
