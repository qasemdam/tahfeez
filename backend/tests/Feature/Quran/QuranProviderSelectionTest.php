<?php

namespace Tests\Feature\Quran;

use App\Domain\Quran\Contracts\QuranDataProvider;
use App\Domain\Quran\Exceptions\QuranDataUnavailable;
use App\Infrastructure\Quran\DatabaseQuranDataProvider;
use App\Infrastructure\Quran\FixtureQuranDataProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Which implementation the container resolves.
 *
 * The default is `auto`: the fixture outside production, the database provider
 * in production. A hosted deployment must never present fixture data as if it
 * were canonical, and the database provider refuses to answer until a validated
 * snapshot is active — so `auto` cannot invent data either way.
 */
class QuranProviderSelectionTest extends TestCase
{
    use RefreshDatabase;

    private function resolveWith(string $provider, ?string $environment = null): QuranDataProvider
    {
        config(['quran.provider' => $provider]);

        $previous = null;

        if ($environment !== null) {
            $previous = $this->app['env'];
            $this->app['env'] = $environment;
        }

        $this->app->forgetScopedInstances();
        $resolved = $this->app->make(QuranDataProvider::class);

        if ($previous !== null) {
            $this->app['env'] = $previous;
        }

        return $resolved;
    }

    public function test_the_default_configuration_is_auto(): void
    {
        $this->assertSame('auto', config('quran.provider'));
    }

    public function test_auto_resolves_to_the_fixture_outside_production(): void
    {
        $this->assertInstanceOf(FixtureQuranDataProvider::class, $this->resolveWith('auto'));
    }

    public function test_auto_resolves_to_the_database_provider_in_production(): void
    {
        $this->assertInstanceOf(
            DatabaseQuranDataProvider::class,
            $this->resolveWith('auto', 'production'),
        );
    }

    public function test_an_explicit_choice_overrides_the_environment(): void
    {
        $this->assertInstanceOf(
            DatabaseQuranDataProvider::class,
            $this->resolveWith('database'),
        );

        $this->assertInstanceOf(
            FixtureQuranDataProvider::class,
            $this->resolveWith('fixture', 'production'),
        );
    }

    public function test_the_database_provider_does_not_answer_without_a_dataset(): void
    {
        // Resolving it is safe; asking it is not, and the failure is explicit.
        $this->assertInstanceOf(
            DatabaseQuranDataProvider::class,
            $this->resolveWith('database'),
        );

        $this->expectException(QuranDataUnavailable::class);

        $this->app->make(QuranDataProvider::class)->surahs();
    }
}
