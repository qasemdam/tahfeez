<?php

namespace Tests\Feature\Quran;

use App\Domain\Quran\Import\ContentDigest;
use App\Domain\Quran\Import\DatasetPayload;
use App\Domain\Quran\Import\DatasetValidator;
use Tests\Support\Quran\SeedsSnapshots;
use Tests\TestCase;

/**
 * V-12 — the synthetic regression fixture (S6.4).
 *
 * It guards the canonical serialisation that content_digest depends on. It is
 * not a reference for the mushaf: V-10 stays skipped and critical, so a dataset
 * that passes V-12 is still `incomplete`.
 */
class GoldenRegressionTest extends TestCase
{
    use SeedsSnapshots;

    private function fixture(): array
    {
        $path = resource_path('quran/golden-regression.json');

        $this->assertFileExists($path);

        return json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
    }

    public function test_the_fixture_is_synthetic_and_says_so(): void
    {
        $fixture = $this->fixture();

        $this->assertTrue($fixture['synthetic']);
        $this->assertStringContainsString('NOT QURAN DATA', (string) $fixture['note']);
        $this->assertSame(ContentDigest::VERSION, $fixture['canonical_version']);
    }

    public function test_the_fixture_reproduces_its_expected_digest(): void
    {
        $fixture = $this->fixture();
        $payload = $fixture['payload'];

        $digest = ContentDigest::of(new DatasetPayload(
            edition: (string) $payload['edition'],
            sourceVersion: (string) $payload['source_version'],
            surahs: $payload['surahs'],
            pages: $payload['pages'],
            ayahs: $payload['ayahs'],
        ));

        $this->assertSame($fixture['expected_digest'], $digest);
    }

    public function test_v12_passes_and_v10_stays_skipped(): void
    {
        // Tighten the expected totals to the synthetic payload so the structural
        // checks do not drown the two checks under test.
        config(['quran.import.expected' => ['surahs' => 2, 'ayahs' => 3, 'pages' => 2]]);

        $tiny = $this->tinyPayload();

        $report = app(DatasetValidator::class)->validate(new DatasetPayload(
            edition: 'synthetic_regression',
            sourceVersion: 'test-v1',
            surahs: $tiny['surahs'],
            pages: $tiny['pages'],
            ayahs: $tiny['ayahs'],
        ));

        $checks = collect($report->checks)->keyBy('id');

        $this->assertSame('passed', $checks['V-12']['status']);
        $this->assertSame('skipped', $checks['V-10']['status']);

        // Everything else passes, yet the dataset is not activatable: V-10 is
        // skipped and critical.
        $this->assertSame('incomplete', $report->status());
        $this->assertFalse($report->passed());
    }

    public function test_a_changed_canonical_form_fails_v12(): void
    {
        $path = resource_path('quran/golden-regression.json');
        $original = (string) file_get_contents($path);

        try {
            $fixture = json_decode($original, true, 512, JSON_THROW_ON_ERROR);
            $fixture['expected_digest'] = str_repeat('0', 64);
            file_put_contents($path, json_encode($fixture, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));

            config(['quran.import.expected' => ['surahs' => 2, 'ayahs' => 3, 'pages' => 2]]);
            $tiny = $this->tinyPayload();

            $report = app(DatasetValidator::class)->validate(new DatasetPayload(
                edition: 'synthetic_regression',
                sourceVersion: 'test-v1',
                surahs: $tiny['surahs'],
                pages: $tiny['pages'],
                ayahs: $tiny['ayahs'],
            ));

            $checks = collect($report->checks)->keyBy('id');

            $this->assertSame('failed', $checks['V-12']['status']);
            $this->assertSame('failed', $report->status());
        } finally {
            file_put_contents($path, $original);
        }
    }
}
