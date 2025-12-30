<?php

namespace Tests\Integration\Auth;

use App\Models\User;
use App\Jobs\SendProfileEmailOtp;
use Illuminate\Support\Facades\Queue;

class EmailSignupIntegrationTest extends AuthTestCase
{
    public function test_user_can_signup_with_valid_email_and_password(): void
    {
        Queue::fake();

        $response = $this->postJson('/api/user/signup/email', [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john.doe@example.com',
            'password' => 'password123',
        ]);

        $this->assertApiSuccess($response);

        $this->assertDatabaseHas('users', [
            'email' => 'john.doe@example.com',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'sso_type' => 'email',
            'is_email_verified' => false,
        ]);

        Queue::assertPushed(SendProfileEmailOtp::class);
    }

    public function test_signup_fails_with_duplicate_email(): void
    {
        User::factory()->verified()->create(['email' => 'existing@example.com']);

        $response = $this->postJson('/api/user/signup/email', [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'existing@example.com',
            'password' => 'password123',
        ]);

        $this->assertApiError($response);
    }

    public function test_signup_fails_with_invalid_email(): void
    {
        $response = $this->postJson('/api/user/signup/email', [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'invalid-email',
            'password' => 'password123',
        ]);

        $this->assertApiError($response);
    }

    public function test_signup_fails_with_short_password(): void
    {
        $response = $this->postJson('/api/user/signup/email', [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john@example.com',
            'password' => 'short',
        ]);

        $this->assertApiError($response);
    }

    public function test_signup_fails_without_required_fields(): void
    {
        $response = $this->postJson('/api/user/signup/email', []);

        $this->assertApiError($response);
    }
}
