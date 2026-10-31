<?php

namespace App\Domain\Evaluation\DTOs;

use App\Domain\Evaluation\Rules\EvaluationRuleSet;
use App\Domain\Evaluation\ValueObjects\EvaluationProfile;

/**
 * Everything the engine needs, with no Eloquent dependency.
 *
 * `actualPages` is the pre-computed new-memorization total for the period;
 * the engine never measures Quran amounts itself (QuranDataProvider stays
 * outside this domain).
 */
final readonly class EvaluationInput
{
    public function __construct(
        public string $requiredPages,
        public string $actualPages,
        public ?string $reviewPages = null,
        public ?RecitationInput $recitation = null,
        public ?AttendanceInput $attendance = null,
        public ?EvaluationProfile $profile = null,
        public ?EvaluationRuleSet $ruleSet = null,
    ) {}
}
