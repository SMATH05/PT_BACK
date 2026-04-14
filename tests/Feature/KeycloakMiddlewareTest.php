<?php

namespace Tests\Feature;

use App\Services\KeycloakTokenVerifier;
use Mockery;
use Tests\TestCase;

class KeycloakMiddlewareTest extends TestCase
{
    public function test_auth_me_requires_a_bearer_token(): void
    {
        $response = $this->getJson('/api/auth/me');

        $response
            ->assertUnauthorized()
            ->assertJson([
                'message' => 'Unauthorized',
                'error' => 'Missing bearer token.',
            ]);
    }

    public function test_auth_me_returns_claims_from_a_valid_keycloak_token(): void
    {
        $claims = [
            'sub' => 'user-123',
            'preferred_username' => 'dev.user',
            'name' => 'Dev User',
            'email' => 'dev@example.com',
            'realm_access' => [
                'roles' => ['developer'],
            ],
        ];

        $mock = Mockery::mock(KeycloakTokenVerifier::class);
        $mock->shouldReceive('verify')->once()->with('valid-token')->andReturn($claims);
        $this->instance(KeycloakTokenVerifier::class, $mock);

        $response = $this
            ->withHeader('Authorization', 'Bearer valid-token')
            ->getJson('/api/auth/me');

        $response
            ->assertOk()
            ->assertJsonPath('user.id', 'user-123')
            ->assertJsonPath('user.username', 'dev.user')
            ->assertJsonPath('user.email', 'dev@example.com')
            ->assertJsonPath('user.roles.0', 'developer')
            ->assertJsonPath('claims.sub', 'user-123');
    }

    public function test_auth_me_rejects_invalid_tokens(): void
    {
        $mock = Mockery::mock(KeycloakTokenVerifier::class);
        $mock->shouldReceive('verify')->once()->with('bad-token')->andThrow(new \RuntimeException('The token signature is invalid.'));
        $this->instance(KeycloakTokenVerifier::class, $mock);

        $response = $this
            ->withHeader('Authorization', 'Bearer bad-token')
            ->getJson('/api/auth/me');

        $response
            ->assertUnauthorized()
            ->assertJson([
                'message' => 'Unauthorized',
                'error' => 'The token signature is invalid.',
            ]);
    }
}
