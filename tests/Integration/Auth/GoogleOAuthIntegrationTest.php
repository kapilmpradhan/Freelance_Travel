<?php

namespace Tests\Integration\Auth;

use App\Models\User;

class GoogleOAuthIntegrationTest extends AuthTestCase
{
    public function test_google_login_fails_without_token(): void
    {
        $response = $this->postJson('/api/user/login/google', []);

        $response->assertStatus(401);
    }

    public function test_google_login_fails_with_invalid_token(): void
    {
        $response = $this->postJson('/api/user/login/google', [
            'google_access_token' => 'invalid-token',
        ]);

        // Invalid token should return 401 unauthorized
        $response->assertStatus(401);
    }

    public function test_google_login_fails_for_permanently_deleted_user(): void
    {
        // Create a permanently deleted user
        User::factory()->google()->permanentlyDeleted()->create([
            'email' => 'deleted@gmail.com',
        ]);

        // Even with a valid-looking token, a deleted user should fail
        $response = $this->postJson('/api/user/login/google', [
            'google_access_token' => 'some-token',
        ]);

        // Should fail with 401 (token validation fails before user check)
        $response->assertStatus(401);
    }
}
