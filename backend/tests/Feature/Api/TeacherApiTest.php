<?php

namespace Tests\Feature\Api;

use App\Domain\Tenancy\Models\Center;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeacherApiTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, string>
     */
    private function auth(User $user): array
    {
        [, $plain] = $user->issueToken('phpunit');

        return ['Authorization' => 'Bearer '.$plain];
    }

    public function test_a_manager_sees_only_teachers_of_their_center(): void
    {
        $center = Center::factory()->create();
        $manager = User::factory()->manager()->for($center)->create();
        User::factory()->teacher()->for($center)->create(['name' => 'أستاذ أحمد']);
        User::factory()->manager()->for($center)->create();

        // Another center's teacher must not appear.
        $other = Center::factory()->create();
        User::factory()->teacher()->for($other)->create(['name' => 'أستاذ آخر']);

        $this->withHeaders($this->auth($manager))
            ->getJson('/api/v1/teachers')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'أستاذ أحمد');
    }

    public function test_a_teacher_cannot_list_teachers(): void
    {
        $center = Center::factory()->create();
        $teacher = User::factory()->teacher()->for($center)->create();

        $this->withHeaders($this->auth($teacher))
            ->getJson('/api/v1/teachers')
            ->assertForbidden();
    }
}
