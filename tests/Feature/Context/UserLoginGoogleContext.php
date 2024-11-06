<?php

namespace Tests\Feature\Context;

use Mockery;
use Behat\Behat\Context\Context;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\App;
use App\Services\GoogleService;

class UserLoginGoogleContext implements Context
{
    protected $accessToken;
    protected $response;
    protected $googleServiceMock;

    public function setUp(): void
    {
        // Create the mock for GoogleService using Mockery
        $this->googleServiceMock = Mockery::mock(GoogleService::class);

        // Set up the expectation for the googleUserDetail method
        $this->googleServiceMock->shouldReceive('googleUserDetail')
            ->with(['accessToken' => $this->accessToken])
            ->andReturn([
                'name' => 'Test User',
                'email'=> 'test@email.com',
            ]);

        // Bind the mock to the service container
        app()->instance(GoogleService::class, $this->googleServiceMock);
    }
    /**
     * @Given I have a valid Google access token
     */
    public function iHaveAValidGoogleAccessToken()
    {
        // Simulating a valid access token for testing
        $this->accessToken = 'valid-google-access-token';
    }

    /**
     * @When I post the Google login request
     */
    public function iPostTheGoogleLoginRequest()
    {
        // Simulate an HTTP POST request to the Google login endpoint
        $this->response = Http::post(env('APP_URL') . '/api/user/login/google/', ['google_access_token' => $this->accessToken]);
    }

    /**
     * @Then I should receive an access and refresh token
     */
    public function iShouldReceiveAnAccessAndRefreshToken()
    {
        // Assert that the response contains access and refresh tokens
        if (!isset($this->response['data']['accessToken']) || !isset($this->response['data']['refreshToken'])) {
            throw new \Exception('Access or refresh token not returned in response: ' . json_encode($this->response));
        }
    }

    /**
     * Clean up the mock after each scenario
     * @AfterScenario
     */
    public function tearDown(): void
    {
        // This ensures the mockery is properly cleaned up
        Mockery::close();
    }
}
