<?php

namespace Tests\Integration\Auth;

use App\Models\User;

class EmailLoginIntegrationTest extends AuthTestCase
{
    public function test_user_can_login_with_valid_credentials(): void
    {
        User::factory()->create([
            'email' => 'test@example.com',
            'password' => bcrypt('password123'),
            'sso_type' => 'email',
        ]);

        $response = $this->postJson('/api/user/login/email', [
            'email' => 'test@example.com',
            'password' => 'password123',
        ]);

        $this->assertApiSuccess($response);
        $this->assertHasJwtTokens($response);
    }

    public function test_login_fails_with_wrong_password(): void
    {
        User::factory()->create([
            'email' => 'test@example.com',
            'password' => bcrypt('password123'),
        ]);

        $response = $this->postJson('/api/user/login/email', [
            'email' => 'test@example.com',
            'password' => 'wrongpassword',
        ]);

        $this->assertApiError($response);
        $response->assertJsonFragment(['title' => 'Invalid Credentials']);
    }

    public function test_login_fails_with_nonexistent_email(): void
    {
        $response = $this->postJson('/api/user/login/email', [
            'email' => 'nonexistent@example.com',
            'password' => 'password123',
        ]);

        $this->assertApiError($response);
        $response->assertJsonFragment(['title' => 'Invalid Credentials']);
    }

    public function test_login_creates_refresh_token(): void
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => bcrypt('password123'),
        ]);

        $this->postJson('/api/user/login/email', [
            'email' => 'test@example.com',
            'password' => 'password123',
        ]);

        $this->assertDatabaseHas('refresh_tokens', [
            'user_id' => $user->uuid,
        ]);
    }

    public function test_login_fails_for_permanently_deleted_user(): void
    {
        User::factory()->permanentlyDeleted()->create([
            'email' => 'deleted@example.com',
            'password' => bcrypt('password123'),
        ]);

        $response = $this->postJson('/api/user/login/email', [
            'email' => 'deleted@example.com',
            'password' => 'password123',
        ]);

        $this->assertApiError($response);
        $response->assertJsonFragment(['title' => 'This account was permanently deleted']);
    }

    public function test_login_fails_with_missing_email(): void
    {
        $response = $this->postJson('/api/user/login/email', [
            'password' => 'password123',
        ]);

        $this->assertApiError($response);
    }

    public function test_login_fails_with_missing_password(): void
    {
        $response = $this->postJson('/api/user/login/email', [
            'email' => 'test@example.com',
        ]);

        $this->assertApiError($response);
    }
}
