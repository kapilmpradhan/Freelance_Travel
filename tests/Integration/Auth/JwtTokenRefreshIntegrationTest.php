<?php

namespace Tests\Integration\Auth;

use App\Models\User;
use App\Models\RefreshToken;
use Carbon\Carbon;

class JwtTokenRefreshIntegrationTest extends AuthTestCase
{
    public function test_can_refresh_access_token_with_valid_refresh_token(): void
    {
        $user = User::factory()->create();

        RefreshToken::create([
            'user_id' => $user->uuid,
            'token' => 'valid-refresh-token',
            'last_used_at' => now(),
            'user_agent' => 'Test Agent',
        ]);

        $response = $this->postJson('/api/user/token/access', [
            'user_id' => $user->uuid,
            'refresh_token' => 'valid-refresh-token',
        ]);

        $this->assertApiSuccess($response);
        $response->assertJsonStructure([
            'success',
            'data' => ['accessToken'],
        ]);
    }

    public function test_refresh_fails_with_invalid_refresh_token(): void
    {
        $user = User::factory()->create();

        $response = $this->postJson('/api/user/token/access', [
            'user_id' => $user->uuid,
            'refresh_token' => 'invalid-token',
        ]);

        $this->assertApiError($response);
        $response->assertJsonFragment(['title' => 'Invalid refresh token']);
    }

    public function test_refresh_fails_with_expired_refresh_token(): void
    {
        $user = User::factory()->create();

        RefreshToken::create([
            'user_id' => $user->uuid,
            'token' => 'expired-refresh-token',
            'last_used_at' => Carbon::now()->subWeeks(3),
            'user_agent' => 'Test Agent',
        ]);

        $response = $this->postJson('/api/user/token/access', [
            'user_id' => $user->uuid,
            'refresh_token' => 'expired-refresh-token',
        ]);

        $this->assertApiError($response);
        $response->assertJsonFragment(['title' => 'Invalid refresh token']);
    }

    public function test_refresh_fails_without_user_id(): void
    {
        $response = $this->postJson('/api/user/token/access', [
            'refresh_token' => 'some-token',
        ]);

        $this->assertApiError($response);
    }

    public function test_refresh_fails_without_refresh_token(): void
    {
        $user = User::factory()->create();

        $response = $this->postJson('/api/user/token/access', [
            'user_id' => $user->uuid,
        ]);

        $this->assertApiError($response);
    }
}
