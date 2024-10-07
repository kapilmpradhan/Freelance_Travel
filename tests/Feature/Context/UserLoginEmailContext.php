<?php

namespace Tests\Feature\Context;

use Behat\Behat\Context\Context;
use Illuminate\Support\Facades\Http;

class UserLoginEmailContext implements Context
{
    private $data;
    private $response;

    /**
     * @Given I provide registered email :email and password :password for login
     */
    public function iProvideRegisteredEmailAndPasswordForLogin($email, $password)
    {
        // Store the user data for later use
        $this->data = [
            'email' => $email,
            'password' => $password,
        ];

        // Create a user with this credential
        Http::post(env('APP_URL') . '/api/user/signup/email/', $this->data);
    }

    /**
     * @Given I provide un-registered email :email and password :password for login
     */
    public function iProvideUnRegisteredEmailAndPasswordForLogin($email, $password)
    {
        // Store the user data for later use
        $this->data = [
            'email' => $email,
            'password' => $password,
        ];
    }

    /**
     * @When I submit the login form
     */
    public function iSubmitTheLoginForm()
    {
        // Simulate an HTTP request to the login endpoint with the provided data
        $this->response = Http::post(env('APP_URL') . '/api/user/login/email/', $this->data);
    }

    /**
     * @Then I should get login response status :responseStatus
     */
    public function iShouldGetLoginResponseStatus($responseStatus)
    {
        // Check the response status
        if ($this->response->status() !== (int)$responseStatus) {
            throw new \Exception('Expected status ' . $responseStatus . ' but got ' . $this->response->status());
        }
    }

    /**
     * @Then I should get error message of :errorMessage
     */
    public function iShouldGetErrorMessageOf($errorMessage)
    {
        if ($errorMessage !== 'Invalid Credentials') {
            throw new \Exception('Expected error messsage \'Invalid Credentials \' but got \'' . $errorMessage . '\'');
        }
    }

    /**
     * @Then I should get tokens in response: :accessToken and :refreshToken
     */
    public function iShouldGetTokensInResponse($accessToken, $refreshToken)
    {
        if (!isset($this->response['data'][$accessToken]) or !isset($this->response['data'][$refreshToken])) {
            throw new \Exception('Expected accessToken and refreshToken in response but missing.'. $this->response);
        }
    }
}
