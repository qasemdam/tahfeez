<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_returns_a_token_and_the_user(): void
    {
        $user = User::factory()->manager()->create();

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'password',
            'device_name' => 'phpunit',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.user.id', $user->id)
            ->assertJsonPath('data.user.role', User::ROLE_MANAGER)
            ->assertJsonStructure([
                'data' => [
                    'token',
                    'expires_at',
                    'user' => ['id', 'name', 'email', 'role', 'center_id', 'center_name'],
                ],
            ]);

        $plain = $response->json('data.token');
        $this->assertNotEmpty($plain);

        // The plaintext is never persisted; only its SHA-256 hash is (BR-SEC-03).
        $this->assertDatabaseHas('api_tokens', [
            'user_id' => $user->id,
            'name' => 'phpunit',
            'token_hash' => hash('sha256', $plain),
        ]);
        $this->assertDatabaseMissing('api_tokens', ['token_hash' => $plain]);
    }

    public function test_login_rejects_a_wrong_password(): void
    {
        $user = User::factory()->create();

        $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ])->assertStatus(422)->assertJsonValidationErrors('email');
    }

    public function test_an_inactive_user_cannot_log_in(): void
    {
        $user = User::factory()->inactive()->create();

        $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertStatus(422)->assertJsonValidationErrors('email');
    }

    public function test_me_requires_a_token(): void
    {
        $this->getJson('/api/v1/auth/me')->assertUnauthorized();
    }

    public function test_me_returns_the_authenticated_user(): void
    {
        $user = User::factory()->teacher()->create();
        [, $plain] = $user->issueToken('phpunit');

        $this->withHeader('Authorization', 'Bearer '.$plain)
            ->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonPath('data.center_id', $user->center_id);
    }

    public function test_logout_revokes_the_token(): void
    {
        $user = User::factory()->create();
        [, $plain] = $user->issueToken('phpunit');

        $this->withHeader('Authorization', 'Bearer '.$plain)
            ->postJson('/api/v1/auth/logout')
            ->assertOk();

        $this->assertDatabaseCount('api_tokens', 0);

        // The revoked token no longer authenticates.
        $this->withHeader('Authorization', 'Bearer '.$plain)
            ->getJson('/api/v1/auth/me')
            ->assertUnauthorized();
    }
}
