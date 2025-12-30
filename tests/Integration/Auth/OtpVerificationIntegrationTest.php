<?php

namespace Tests\Integration\Auth;

use App\Models\User;
use App\Models\Otp;
use App\Jobs\SendForgotPasswordOtp;
use Illuminate\Support\Facades\Queue;

class OtpVerificationIntegrationTest extends AuthTestCase
{
    public function test_can_send_otp_for_forgot_password(): void
    {
        Queue::fake();

        User::factory()->create([
            'email' => 'test@example.com',
            'sso_type' => 'email',
        ]);

        $response = $this->postJson('/api/user/login/email/forgot-password', [
            'email' => 'test@example.com',
        ]);

        $this->assertApiSuccess($response);
        Queue::assertPushed(SendForgotPasswordOtp::class);
    }

    public function test_send_otp_fails_for_nonexistent_email(): void
    {
        $response = $this->postJson('/api/user/login/email/forgot-password', [
            'email' => 'nonexistent@example.com',
        ]);

        $this->assertApiError($response);
        $response->assertJsonFragment(['title' => 'Email not registered']);
    }

    public function test_send_otp_fails_for_sso_user(): void
    {
        User::factory()->google()->create([
            'email' => 'google@example.com',
        ]);

        $response = $this->postJson('/api/user/login/email/forgot-password', [
            'email' => 'google@example.com',
        ]);

        $this->assertApiError($response);
        $response->assertJsonFragment(['title' => 'SSO registered email cannot perform this action.']);
    }

    public function test_can_verify_valid_otp(): void
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'is_email_verified' => false,
        ]);

        $this->createOtpForUser($user, '12345');

        $response = $this->postJson('/api/user/email/otp/verify', [
            'email' => 'test@example.com',
            'otp' => '12345',
        ]);

        $this->assertApiSuccess($response);

        $user->refresh();
        $this->assertTrue($user->is_email_verified);
    }

    public function test_verify_otp_fails_with_invalid_otp(): void
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
        ]);

        $this->createOtpForUser($user, '12345');

        $response = $this->postJson('/api/user/email/otp/verify', [
            'email' => 'test@example.com',
            'otp' => '99999',
        ]);

        $this->assertApiError($response);
        $response->assertJsonFragment(['title' => 'Invalid OTP']);
    }

    public function test_verify_otp_with_already_verified_otp(): void
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
        ]);

        Otp::create([
            'user_id' => $user->uuid,
            'otp' => '12345',
            'is_verified' => true,
        ]);

        $response = $this->postJson('/api/user/email/otp/verify', [
            'email' => 'test@example.com',
            'otp' => '12345',
        ]);

        $this->assertApiError($response);
        $response->assertJsonFragment(['title' => 'Invalid OTP']);
    }
}
