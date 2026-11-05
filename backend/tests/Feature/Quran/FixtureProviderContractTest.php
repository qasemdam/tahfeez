<?php

namespace Tests\Feature\Quran;

use App\Infrastructure\Quran\FixtureQuranDataProvider;
use Tests\Support\Quran\QuranProviderContract;
use Tests\TestCase;

/**
 * The development fixture must satisfy the same contract the database provider
 * does, so switching implementations cannot change measurement semantics.
 */
class FixtureProviderContractTest extends TestCase
{
    use QuranProviderContract;

    public function test_it_satisfies_the_provider_contract(): void
    {
        $this->assertProviderContract(new FixtureQuranDataProvider);
    }

    public function test_it_rejects_a_reversed_range(): void
    {
        $this->assertRejectsReversedRange(new FixtureQuranDataProvider);
    }
}
