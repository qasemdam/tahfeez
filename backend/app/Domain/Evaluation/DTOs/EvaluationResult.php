<?php

namespace App\Domain\Evaluation\DTOs;

/**
 * The engine's output. The two numbers are deliberately separate
 * (BR-EVL-15): `achievementPercentage` is uncapped, `weightedScore` is
 * 0..100 or null when no rule set was supplied / the result is blocked.
 */
final readonly class EvaluationResult
{
    /**
     * @param  array<int, CriterionResult>  $components
     * @param  array<int, string>  $blockedCriteria
     */
    public function __construct(
        public string $requiredPages,
        public string $actualPages,
        public string $achievementPercentage,
        public ?string $weightedScore,
        public bool $isComplete,
        public array $components,
        public array $blockedCriteria,
        public ?int $profileVersion,
        public ?string $profileEffectiveFrom,
        public ?int $templateVersionId,
        public ?string $ruleSetVersion,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'required_pages' => $this->requiredPages,
            'actual_pages' => $this->actualPages,
            'achievement_percentage' => $this->achievementPercentage,
            'weighted_score' => $this->weightedScore,
            'is_complete' => $this->isComplete,
            'blocked_criteria' => $this->blockedCriteria,
            'profile_version' => $this->profileVersion,
            'profile_effective_from' => $this->profileEffectiveFrom,
            'template_version_id' => $this->templateVersionId,
            'rule_set_version' => $this->ruleSetVersion,
            'components' => array_map(
                static fn (CriterionResult $component): array => $component->toArray(),
                $this->components,
            ),
        ];
    }
}
