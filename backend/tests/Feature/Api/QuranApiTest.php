<?php

namespace Tests\Feature\Api;

use App\Domain\Tenancy\Models\Center;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * GET /api/v1/quran/surahs against the database provider.
 *
 * The endpoint itself is unchanged since Phase 1; what S5 adds is that it now
 * answers from the active snapshot, and that a missing snapshot is a 503 instead
 * of an unhandled 500.
 *
 * The snapshots here are small and synthetic: the endpoint reads surah rows only,
 * so there is no reason to seed 6,236 ayahs to test it.
 */
class QuranApiTest extends TestCase
{
    use RefreshDatabase;

    private function auth(User $user): array
    {
        [, $plain] = $user->issueToken('phpunit');

        return ['Authorization' => 'Bearer '.$plain];
    }

    private function manager(): User
    {
        $center = Center::factory()->create();

        return User::factory()->manager()->for($center)->create();
    }

    /**
     * @param  array<int, array{id: int, name_ar: string, ayah_count: int, page_start: int, page_end: int}>  $surahs
     * @param  array<string, mixed>  $overrides
     */
    private function snapshot(array $surahs, array $overrides = []): int
    {
        $status = $overrides['validation_status'] ?? 'passed';

        $id = (int) DB::table('quran_dataset_versions')->insertGetId([
            'source' => 'synthetic-test',
            'source_url' => '',
            'source_version' => 'test-v1',
            'edition' => 'madinah_1405h_hafs_uthmani',
            'source_format' => 'json',
            'downloaded_at' => now(),
            'imported_at' => now(),
            'checksum_sha256' => str_repeat('d', 64),
            'validation_status' => $status,
            'validation_report' => json_encode(['passed' => $status === 'passed', 'status' => $status, 'checks' => [], 'stats' => []]),
            'is_active' => $overrides['is_active'] ?? false,
            'notes' => null,
            'created_at' => now(),
            'updated_at' => now(),
            ...$overrides,
        ]);

        foreach ($surahs as $surah) {
            DB::table('quran_surahs')->insert([
                'dataset_version_id' => $id,
                'id' => $surah['id'],
                'name_ar' => $surah['name_ar'],
                'name_en' => 'Surah '.$surah['id'],
                'ayah_count' => $surah['ayah_count'],
                'page_start' => $surah['page_start'],
                'page_end' => $surah['page_end'],
            ]);
        }

        return $id;
    }

    /**
     * @return array<int, array{id: int, name_ar: string, ayah_count: int, page_start: int, page_end: int}>
     */
    private function threeSurahs(): array
    {
        return [
            ['id' => 2, 'name_ar' => 'سورة اختبار ثانية', 'ayah_count' => 286, 'page_start' => 2, 'page_end' => 49],
            ['id' => 1, 'name_ar' => 'سورة اختبار أولى', 'ayah_count' => 7, 'page_start' => 1, 'page_end' => 1],
            ['id' => 114, 'name_ar' => 'سورة اختبار أخيرة', 'ayah_count' => 6, 'page_start' => 604, 'page_end' => 604],
        ];
    }

    private function useDatabaseProvider(): void
    {
        config(['quran.provider' => 'database']);
        $this->app->forgetScopedInstances();
    }

    public function test_a_guest_cannot_read_surahs(): void
    {
        $this->getJson('/api/v1/quran/surahs')->assertUnauthorized();
    }

    public function test_surahs_are_served_from_the_active_snapshot(): void
    {
        $this->useDatabaseProvider();
        $this->snapshot($this->threeSurahs(), ['is_active' => true]);

        $this->withHeaders($this->auth($this->manager()))
            ->getJson('/api/v1/quran/surahs')
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('data.0.id', 1)
            ->assertJsonPath('data.0.name_ar', 'سورة اختبار أولى');
    }

    public function test_surahs_come_back_in_mushaf_order(): void
    {
        $this->useDatabaseProvider();
        $this->snapshot($this->threeSurahs(), ['is_active' => true]);

        $ids = $this->withHeaders($this->auth($this->manager()))
            ->getJson('/api/v1/quran/surahs')
            ->assertOk()
            ->json('data');

        $this->assertSame([1, 2, 114], array_column($ids, 'id'));
    }

    public function test_rows_from_an_inactive_snapshot_are_not_served(): void
    {
        $this->useDatabaseProvider();

        $this->snapshot([
            ['id' => 9, 'name_ar' => 'سورة غير نشطة', 'ayah_count' => 10, 'page_start' => 10, 'page_end' => 10],
        ], ['is_active' => false, 'checksum_sha256' => str_repeat('a', 64)]);

        $active = $this->snapshot([
            ['id' => 1, 'name_ar' => 'سورة نشطة', 'ayah_count' => 7, 'page_start' => 1, 'page_end' => 1],
        ], ['is_active' => true, 'checksum_sha256' => str_repeat('b', 64)]);

        $data = $this->withHeaders($this->auth($this->manager()))
            ->getJson('/api/v1/quran/surahs')
            ->assertOk()
            ->json('data');

        $this->assertSame([1], array_column($data, 'id'));
        $this->assertSame($active, DB::table('quran_dataset_versions')->where('is_active', true)->value('id'));
    }

    public function test_without_an_active_snapshot_the_api_answers_503(): void
    {
        $this->useDatabaseProvider();
        $this->snapshot($this->threeSurahs(), ['is_active' => false]);

        $response = $this->withHeaders($this->auth($this->manager()))
            ->getJson('/api/v1/quran/surahs')
            ->assertStatus(503)
            ->assertJsonPath('type', 'https://tahfeez.app/errors/quran-data-unavailable')
            ->assertJsonPath('title', 'Quran data unavailable')
            ->assertJsonPath('status', 503);

        $this->assertSame('No active Quran dataset is available.', $response->json('detail'));
    }

    public function test_an_incomplete_snapshot_is_refused(): void
    {
        $this->useDatabaseProvider();
        $this->snapshot($this->threeSurahs(), ['is_active' => true, 'validation_status' => 'incomplete']);

        $this->withHeaders($this->auth($this->manager()))
            ->getJson('/api/v1/quran/surahs')
            ->assertStatus(503);
    }

    public function test_a_failed_snapshot_is_refused(): void
    {
        $this->useDatabaseProvider();
        $this->snapshot($this->threeSurahs(), ['is_active' => true, 'validation_status' => 'failed']);

        $this->withHeaders($this->auth($this->manager()))
            ->getJson('/api/v1/quran/surahs')
            ->assertStatus(503);
    }

    public function test_production_never_falls_back_to_the_fixture(): void
    {
        // `auto` resolves to the database provider in production; with no
        // snapshot it must answer 503 rather than serve fixture surahs.
        config(['quran.provider' => 'auto']);
        $this->app['env'] = 'production';
        $this->app->forgetScopedInstances();

        try {
            $response = $this->withHeaders($this->auth($this->manager()))
                ->getJson('/api/v1/quran/surahs')
                ->assertStatus(503);

            $this->assertStringNotContainsString('الفاتحة', (string) $response->getContent());
        } finally {
            $this->app['env'] = 'testing';
        }
    }

    public function test_the_response_shape_is_what_the_client_expects(): void
    {
        $this->useDatabaseProvider();
        $this->snapshot($this->threeSurahs(), ['is_active' => true]);

        $row = $this->withHeaders($this->auth($this->manager()))
            ->getJson('/api/v1/quran/surahs')
            ->assertOk()
            ->json('data.0');

        $this->assertSame(
            ['id', 'name_ar', 'ayah_count', 'page_start', 'page_end'],
            array_keys($row),
        );
        $this->assertIsInt($row['id']);
        $this->assertIsString($row['name_ar']);
        $this->assertIsInt($row['ayah_count']);
        $this->assertIsInt($row['page_start']);
        $this->assertIsInt($row['page_end']);
    }

    public function test_an_error_does_not_leak_internal_details(): void
    {
        $this->useDatabaseProvider();

        $body = (string) $this->withHeaders($this->auth($this->manager()))
            ->getJson('/api/v1/quran/surahs')
            ->assertStatus(503)
            ->getContent();

        foreach (['SQLSTATE', 'select ', 'quran_dataset_versions', 'sqlite', 'Exception'] as $needle) {
            $this->assertStringNotContainsStringIgnoringCase($needle, $body);
        }
    }

    public function test_the_fixture_still_serves_local_and_test_environments(): void
    {
        // The default binding outside production is unchanged: four fixture surahs.
        config(['quran.provider' => 'auto']);
        $this->app->forgetScopedInstances();

        $this->withHeaders($this->auth($this->manager()))
            ->getJson('/api/v1/quran/surahs')
            ->assertOk()
            ->assertJsonCount(4, 'data');
    }
}
