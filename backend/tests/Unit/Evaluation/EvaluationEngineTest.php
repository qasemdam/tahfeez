<?php

namespace Tests\Unit\Evaluation;

use App\Domain\Evaluation\DTOs\AttendanceInput;
use App\Domain\Evaluation\DTOs\EvaluationInput;
use App\Domain\Evaluation\DTOs\RecitationInput;
use App\Domain\Evaluation\Rules\CriterionKey;
use App\Domain\Evaluation\Rules\EvaluationCriterion;
use App\Domain\Evaluation\Rules\EvaluationRuleSet;
use App\Domain\Evaluation\Rules\MissingDataPolicy;
use App\Domain\Evaluation\Services\EvaluationEngine;
use App\Domain\Evaluation\ValueObjects\EvaluationProfile;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class EvaluationEngineTest extends TestCase
{
    private EvaluationEngine $engine;

    protected function setUp(): void
    {
        $this->engine = new EvaluationEngine;
    }

    private function criterion(
        string $key,
        string $weight,
        MissingDataPolicy $policy = MissingDataPolicy::Block,
        array $params = [],
    ): EvaluationCriterion {
        return new EvaluationCriterion($key, $weight, $key, $policy, $params);
    }

    /**
     * @param  array<int, EvaluationCriterion>  $criteria
     */
    private function ruleSet(array $criteria, string $version = 'v1'): EvaluationRuleSet
    {
        return new EvaluationRuleSet($version, $criteria);
    }

    #[DataProvider('achievementRows')]
    public function test_achievement_baseline(string $actual, string $expected): void
    {
        $result = $this->engine->evaluate(new EvaluationInput('1', $actual));

        $this->assertSame($expected, $result->achievementPercentage);
        $this->assertNull($result->weightedScore);
        $this->assertTrue($result->isComplete);
        $this->assertSame([], $result->components);
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function achievementRows(): array
    {
        return [
            'zero' => ['0', '0.00'],
            'half' => ['0.5', '50.00'],
            'one' => ['1', '100.00'],
            'one and a half' => ['1.5', '150.00'],
            'two' => ['2', '200.00'],
        ];
    }

    public function test_an_overachiever_is_not_normalized_to_100(): void
    {
        $a = $this->engine->evaluate(new EvaluationInput('1', '1'));
        $b = $this->engine->evaluate(new EvaluationInput('1', '2'));

        $this->assertSame('100.00', $a->achievementPercentage);
        $this->assertSame('200.00', $b->achievementPercentage);
    }

    public function test_weighted_score_uses_the_configured_weights(): void
    {
        $ruleSet = $this->ruleSet([
            $this->criterion(
                CriterionKey::MEMORIZATION_PAGES,
                '60',
                MissingDataPolicy::Block,
                ['required_pages' => '1'],
            ),
            $this->criterion(
                CriterionKey::REVIEW_COMPLETION,
                '40',
                MissingDataPolicy::Block,
                ['target_pages' => '1'],
            ),
        ]);

        $result = $this->engine->evaluate(new EvaluationInput(
            requiredPages: '1',
            actualPages: '1',
            reviewPages: '0.5',
            ruleSet: $ruleSet,
        ));

        $this->assertSame('80.00', $result->weightedScore);
        $this->assertTrue($result->isComplete);
        $this->assertCount(2, $result->components);
        $this->assertSame(CriterionKey::MEMORIZATION_PAGES, $result->components[0]->key);
        $this->assertSame(CriterionKey::REVIEW_COMPLETION, $result->components[1]->key);
    }

    public function test_an_excluded_criterion_renormalizes_the_remaining_weights(): void
    {
        $ruleSet = $this->ruleSet([
            $this->criterion(
                CriterionKey::MEMORIZATION_PAGES,
                '60',
                MissingDataPolicy::Block,
                ['required_pages' => '1'],
            ),
            $this->criterion(
                CriterionKey::REVIEW_COMPLETION,
                '40',
                MissingDataPolicy::ExcludeAndRenormalize,
                ['target_pages' => '1'],
            ),
        ]);

        $result = $this->engine->evaluate(new EvaluationInput(
            requiredPages: '1',
            actualPages: '1',
            ruleSet: $ruleSet,
        ));

        $this->assertSame('100.00', $result->weightedScore);
        $this->assertFalse($result->components[1]->included);
        $this->assertFalse($result->components[1]->blocked);
    }

    public function test_missing_data_treat_as_zero_keeps_the_weight(): void
    {
        $ruleSet = $this->ruleSet([
            $this->criterion(
                CriterionKey::MEMORIZATION_PAGES,
                '60',
                MissingDataPolicy::Block,
                ['required_pages' => '1'],
            ),
            $this->criterion(
                CriterionKey::REVIEW_COMPLETION,
                '40',
                MissingDataPolicy::TreatAsZero,
                ['target_pages' => '1'],
            ),
        ]);

        $result = $this->engine->evaluate(new EvaluationInput(
            requiredPages: '1',
            actualPages: '1',
            ruleSet: $ruleSet,
        ));

        $this->assertSame('60.00', $result->weightedScore);
        $this->assertTrue($result->components[1]->included);
        $this->assertSame('0.000000', $result->components[1]->normalized);
    }

    public function test_missing_data_block_returns_incomplete(): void
    {
        $ruleSet = $this->ruleSet([
            $this->criterion(
                CriterionKey::MEMORIZATION_PAGES,
                '60',
                MissingDataPolicy::Block,
                ['required_pages' => '1'],
            ),
            $this->criterion(
                CriterionKey::RECITATION_QUALITY,
                '40',
                MissingDataPolicy::Block,
                ['aggregate' => 'mean'],
            ),
        ]);

        $result = $this->engine->evaluate(new EvaluationInput(
            requiredPages: '1',
            actualPages: '1',
            ruleSet: $ruleSet,
        ));

        $this->assertNull($result->weightedScore);
        $this->assertFalse($result->isComplete);
        $this->assertContains(CriterionKey::RECITATION_QUALITY, $result->blockedCriteria);
    }

    public function test_weights_must_total_exactly_100(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->engine->evaluate(new EvaluationInput(
            requiredPages: '1',
            actualPages: '1',
            ruleSet: $this->ruleSet([
                $this->criterion(CriterionKey::MEMORIZATION_PAGES, '60'),
                $this->criterion(CriterionKey::REVIEW_COMPLETION, '30'),
            ]),
        ));
    }

    public function test_a_weight_out_of_range_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->engine->evaluate(new EvaluationInput(
            requiredPages: '1',
            actualPages: '1',
            ruleSet: $this->ruleSet([
                $this->criterion(CriterionKey::MEMORIZATION_PAGES, '0'),
                $this->criterion(CriterionKey::REVIEW_COMPLETION, '100'),
            ]),
        ));
    }

    public function test_duplicate_criteria_are_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->engine->evaluate(new EvaluationInput(
            requiredPages: '1',
            actualPages: '1',
            ruleSet: $this->ruleSet([
                $this->criterion(CriterionKey::MEMORIZATION_PAGES, '50'),
                $this->criterion(CriterionKey::MEMORIZATION_PAGES, '50'),
            ]),
        ));
    }

    public function test_zero_required_pages_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->engine->evaluate(new EvaluationInput('0', '1'));
    }

    public function test_negative_actual_pages_are_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->engine->evaluate(new EvaluationInput('1', '-1'));
    }

    public function test_the_profile_version_is_carried_into_the_result(): void
    {
        $profile = new EvaluationProfile(2, '2026-01-01', '2', 9);

        $result = $this->engine->evaluate(new EvaluationInput(
            requiredPages: '1',
            actualPages: '2',
            profile: $profile,
        ));

        $this->assertSame('2', $result->requiredPages);
        $this->assertSame('100.00', $result->achievementPercentage);
        $this->assertSame(2, $result->profileVersion);
        $this->assertSame('2026-01-01', $result->profileEffectiveFrom);
        $this->assertSame(9, $result->templateVersionId);
    }

    public function test_the_engine_is_deterministic(): void
    {
        $input = new EvaluationInput(
            requiredPages: '1',
            actualPages: '1.5',
            reviewPages: '0.5',
            recitation: new RecitationInput(['90', '80']),
            ruleSet: $this->ruleSet([
                $this->criterion(CriterionKey::MEMORIZATION_PAGES, '40', MissingDataPolicy::Block, ['required_pages' => '1']),
                $this->criterion(CriterionKey::REVIEW_COMPLETION, '30', MissingDataPolicy::Block, ['target_pages' => '1']),
                $this->criterion(CriterionKey::RECITATION_QUALITY, '30', MissingDataPolicy::Block, ['aggregate' => 'mean']),
            ]),
        );

        $first = $this->engine->evaluate($input)->toArray();

        for ($i = 0; $i < 10; $i++) {
            $this->assertSame($first, $this->engine->evaluate($input)->toArray());
        }
    }

    public function test_memorization_and_review_are_separate_components(): void
    {
        $ruleSet = $this->ruleSet([
            $this->criterion(CriterionKey::MEMORIZATION_PAGES, '50', MissingDataPolicy::Block, ['required_pages' => '1']),
            $this->criterion(CriterionKey::REVIEW_COMPLETION, '50', MissingDataPolicy::Block, ['target_pages' => '1']),
        ]);

        $result = $this->engine->evaluate(new EvaluationInput(
            requiredPages: '1',
            actualPages: '1',
            reviewPages: '0.5',
            ruleSet: $ruleSet,
        ));

        $this->assertSame('100.000000', $result->components[0]->normalized);
        $this->assertSame('50.000000', $result->components[1]->normalized);
        $this->assertNotSame(
            $result->components[0]->normalized,
            $result->components[1]->normalized,
        );
    }

    public function test_attendance_is_opt_in(): void
    {
        $ruleSet = $this->ruleSet([
            $this->criterion(CriterionKey::MEMORIZATION_PAGES, '100', MissingDataPolicy::Block, ['required_pages' => '1']),
        ]);

        $attendance = new AttendanceInput(present: 1, unexcusedAbsence: 1);

        $without = $this->engine->evaluate(new EvaluationInput(
            requiredPages: '1',
            actualPages: '1',
            ruleSet: $ruleSet,
        ));

        $with = $this->engine->evaluate(new EvaluationInput(
            requiredPages: '1',
            actualPages: '1',
            attendance: $attendance,
            ruleSet: $ruleSet,
        ));

        $this->assertSame($without->toArray(), $with->toArray());
        $this->assertSame('100.00', $with->achievementPercentage);
    }

    public function test_attendance_contributes_when_the_rule_set_includes_it(): void
    {
        $baseParams = [
            'status_scores' => [
                'present' => '100',
                'late' => '80',
                'excused_absence' => '100',
                'unexcused_absence' => '0',
            ],
        ];

        $withAbsences = $this->ruleSet([
            $this->criterion(CriterionKey::ATTENDANCE, '100', MissingDataPolicy::Block, $baseParams + [
                'count_absences_in_denominator' => true,
            ]),
        ]);

        $withoutAbsences = $this->ruleSet([
            $this->criterion(CriterionKey::ATTENDANCE, '100', MissingDataPolicy::Block, $baseParams + [
                'count_absences_in_denominator' => false,
            ]),
        ]);

        $attendance = new AttendanceInput(present: 1, unexcusedAbsence: 1);

        $included = $this->engine->evaluate(new EvaluationInput('1', '1', null, null, $attendance, null, $withAbsences));
        $excluded = $this->engine->evaluate(new EvaluationInput('1', '1', null, null, $attendance, null, $withoutAbsences));

        $this->assertSame('50.00', $included->weightedScore);
        $this->assertSame('100.00', $excluded->weightedScore);
    }

    public function test_the_two_scores_are_never_mixed(): void
    {
        $ruleSet = $this->ruleSet([
            $this->criterion(CriterionKey::MEMORIZATION_PAGES, '100', MissingDataPolicy::Block, ['required_pages' => '1']),
        ]);

        $result = $this->engine->evaluate(new EvaluationInput(
            requiredPages: '1',
            actualPages: '2',
            ruleSet: $ruleSet,
        ));

        $this->assertSame('200.00', $result->achievementPercentage);
        $this->assertSame('100.00', $result->weightedScore);
        $this->assertNotSame($result->achievementPercentage, $result->weightedScore);
    }

    #[DataProvider('recitationAggregates')]
    public function test_recitation_quality_aggregates_scores(string $aggregate, string $expected): void
    {
        $ruleSet = $this->ruleSet([
            $this->criterion(CriterionKey::RECITATION_QUALITY, '100', MissingDataPolicy::Block, [
                'aggregate' => $aggregate,
            ]),
        ]);

        $result = $this->engine->evaluate(new EvaluationInput(
            requiredPages: '1',
            actualPages: '1',
            recitation: new RecitationInput(['100', '80']),
            ruleSet: $ruleSet,
        ));

        $this->assertSame($expected, $result->weightedScore);
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function recitationAggregates(): array
    {
        return [
            'mean' => ['mean', '90.00'],
            'min' => ['min', '80.00'],
            'latest' => ['latest', '80.00'],
        ];
    }

    public function test_error_rate_uses_the_configured_bands(): void
    {
        $ruleSet = $this->ruleSet([
            $this->criterion(CriterionKey::ERROR_RATE, '100', MissingDataPolicy::Block, [
                'weights_by_type' => ['hifz' => '1', 'tajweed' => '1', 'reading' => '1'],
                'bands' => [
                    ['max' => 1, 'score' => '100'],
                    ['max' => 3, 'score' => '80'],
                    ['max' => 999, 'score' => '0'],
                ],
            ]),
        ]);

        $result = $this->engine->evaluate(new EvaluationInput(
            requiredPages: '1',
            actualPages: '1',
            recitation: new RecitationInput([], hifzErrors: 2, tajweedErrors: 1),
            ruleSet: $ruleSet,
        ));

        $this->assertSame('80.00', $result->weightedScore);
    }

    public function test_a_recitation_score_outside_0_to_100_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->engine->evaluate(new EvaluationInput(
            requiredPages: '1',
            actualPages: '1',
            recitation: new RecitationInput(['150']),
        ));
    }

    public function test_an_unknown_calculator_blocks_the_result(): void
    {
        $ruleSet = $this->ruleSet([
            new EvaluationCriterion(
                CriterionKey::ATTENDANCE,
                '100',
                'unknown_calculator',
                MissingDataPolicy::Block,
            ),
        ]);

        $result = $this->engine->evaluate(new EvaluationInput(
            requiredPages: '1',
            actualPages: '1',
            ruleSet: $ruleSet,
        ));

        $this->assertNull($result->weightedScore);
        $this->assertFalse($result->isComplete);
        $this->assertContains(CriterionKey::ATTENDANCE, $result->blockedCriteria);
    }

    public function test_all_criteria_excluded_yields_no_weighted_score(): void
    {
        $ruleSet = $this->ruleSet([
            $this->criterion(CriterionKey::REVIEW_COMPLETION, '50', MissingDataPolicy::ExcludeAndRenormalize, ['target_pages' => '1']),
            $this->criterion(CriterionKey::RECITATION_QUALITY, '50', MissingDataPolicy::ExcludeAndRenormalize, ['aggregate' => 'mean']),
        ]);

        $result = $this->engine->evaluate(new EvaluationInput(
            requiredPages: '1',
            actualPages: '1',
            ruleSet: $ruleSet,
        ));

        $this->assertNull($result->weightedScore);
        $this->assertTrue($result->isComplete);
    }

    public function test_exact_decimal_arithmetic(): void
    {
        $result = $this->engine->evaluate(new EvaluationInput('0.1', '0.3'));

        $this->assertSame('300.00', $result->achievementPercentage);
    }
}
