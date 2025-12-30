<?php

namespace Tests\Integration\Auth;

use App\Models\User;

class AppleOAuthIntegrationTest extends AuthTestCase
{
    /**
     * Helper to create a mock Apple JWT token
     */
    private function createMockAppleToken(string $email): string
    {
        $header = base64_encode(json_encode(['alg' => 'RS256', 'kid' => 'test']));
        $payload = base64_encode(json_encode([
            'iss' => 'https://appleid.apple.com',
            'aud' => config('services.apple.client_id'),
            'exp' => time() + 3600,
            'email' => $email,
        ]));
        $signature = base64_encode('mock-signature');

        return "{$header}.{$payload}.{$signature}";
    }

    public function test_new_user_can_login_with_apple(): void
    {
        $email = 'newuser@apple.com';
        $token = $this->createMockAppleToken($email);

        $response = $this->postJson('/api/user/login/apple', [
            'access_token' => $token,
            'first_name' => 'John',
            'last_name' => 'Doe',
        ]);

        $this->assertApiSuccess($response);
        $this->assertHasJwtTokens($response);

        $this->assertDatabaseHas('users', [
            'email' => $email,
            'first_name' => 'John',
            'last_name' => 'Doe',
            'sso_type' => 'apple',
        ]);
    }

    public function test_existing_user_can_login_with_apple(): void
    {
        $email = 'existing@apple.com';
        User::factory()->apple()->create(['email' => $email]);

        $token = $this->createMockAppleToken($email);

        $response = $this->postJson('/api/user/login/apple', [
            'access_token' => $token,
        ]);

        $this->assertApiSuccess($response);
        $this->assertHasJwtTokens($response);

        $this->assertDatabaseCount('users', 1);
    }

    public function test_apple_login_fails_without_token(): void
    {
        $response = $this->postJson('/api/user/login/apple', []);

        $this->assertApiError($response);
    }

    public function test_apple_login_fails_with_invalid_token_format(): void
    {
        $response = $this->postJson('/api/user/login/apple', [
            'access_token' => 'invalid-token-format',
        ]);

        $response->assertStatus(400);
        $response->assertJsonStructure(['error']);
    }

    public function test_apple_login_fails_for_permanently_deleted_user(): void
    {
        $email = 'deleted@apple.com';
        User::factory()->apple()->permanentlyDeleted()->create([
            'email' => $email,
        ]);

        $token = $this->createMockAppleToken($email);

        $response = $this->postJson('/api/user/login/apple', [
            'access_token' => $token,
        ]);

        $this->assertApiError($response);
    }
}
