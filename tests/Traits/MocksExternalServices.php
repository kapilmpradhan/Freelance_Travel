<?php

namespace Tests\Traits;

use App\Services\GoogleService;
use App\Services\AppleService;
use Mockery;

trait MocksExternalServices
{
    /**
     * Mock GoogleService to return successful token validation
     */
    protected function mockGoogleServiceSuccess(string $email = 'test@google.com', string $name = 'Test User'): void
    {
        $this->mock(GoogleService::class, function ($mock) use ($email, $name) {
            $mock->shouldReceive('googleTokenDetail')
                ->andReturn([
                    'aud' => config('vars.google_client_ids'),
                    'email' => $email,
                ]);

            $mock->shouldReceive('googleUserDetail')
                ->andReturn([
                    'name' => $name,
                    'email' => $email,
                ]);
        });
    }

    /**
     * Mock GoogleService to return failed token validation
     */
    protected function mockGoogleServiceFailure(): void
    {
        $this->mock(GoogleService::class, function ($mock) {
            $mock->shouldReceive('googleTokenDetail')
                ->andReturn(['aud' => 'invalid-client-id']);

            $mock->shouldReceive('googleUserDetail')
                ->andReturn(false);
        });
    }

    /**
     * Mock AppleService to return successful user info
     */
    protected function mockAppleServiceSuccess(string $email = 'test@apple.com'): void
    {
        Mockery::mock('alias:' . AppleService::class)
            ->shouldReceive('getUserInfo')
            ->andReturn([
                'success' => true,
                'data' => ['email' => $email]
            ]);
    }

    /**
     * Mock AppleService to return failure
     */
    protected function mockAppleServiceFailure(): void
    {
        Mockery::mock('alias:' . AppleService::class)
            ->shouldReceive('getUserInfo')
            ->andReturn([
                'success' => false,
                'errors' => ['Invalid token']
            ]);
    }
}
