<?php

namespace Tests\Integration\Auth;

use Tests\TestCase;
use Tests\Traits\MocksExternalServices;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\User;
use App\Models\Otp;
use App\Models\RefreshToken;

abstract class AuthTestCase extends TestCase
{
    use RefreshDatabase;
    use MocksExternalServices;

    protected function setUp(): void
    {
        parent::setUp();

        // Bind a default platform for testing
        $this->app->instance('platform', 'default');
    }

    /**
     * Create an OTP for a user
     */
    protected function createOtpForUser(User $user, string $otp = '12345'): Otp
    {
        return Otp::create([
            'user_id' => $user->uuid,
            'otp' => $otp,
        ]);
    }

    /**
     * Create a refresh token for a user
     */
    protected function createRefreshTokenForUser(User $user): RefreshToken
    {
        return RefreshToken::create([
            'user_id' => $user->uuid,
            'token' => 'test-refresh-token-' . uniqid(),
            'last_used_at' => now(),
            'user_agent' => 'PHPUnit Test Agent',
        ]);
    }

    /**
     * Assert response has JWT tokens
     */
    protected function assertHasJwtTokens($response): void
    {
        $response->assertJsonStructure([
            'success',
            'data' => [
                'accessToken',
                'refreshToken',
            ],
        ]);
    }

    /**
     * Assert response is a successful API response
     */
    protected function assertApiSuccess($response, int $code = 200): void
    {
        $response->assertStatus($code);
        $response->assertJson(['success' => true]);
    }

    /**
     * Assert response is an error API response
     */
    protected function assertApiError($response, int $code = 400): void
    {
        $response->assertStatus($code);
        $response->assertJson(['success' => false]);
    }
}
