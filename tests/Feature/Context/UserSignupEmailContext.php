<?php

namespace Tests\Feature\Context;

use Behat\Behat\Context\Context;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Http;

class UserSignupEmailContext implements Context
{
    use RefreshDatabase;

    private $data;
    private $response;

    public function __construct()
    {
        // Bootstrap the Laravel application
        $app = require __DIR__ . '/../../../bootstrap/app.php';
        $app->make(Kernel::class)->bootstrap();
    }

    /**
     * @Given I provide email :email and password :password
     */
    public function iProvideEmailAndPassword($email, $password)
    {
        // Store the user data for later use
        $this->data = [
            'email' => $email,
            'password' => $password,
        ];
    }

    /**
     * @When I submit the signup form
     */
    public function iSubmitTheSignupForm()
    {
        // Simulate an HTTP request to the signup endpoint with the provided data
        $this->response = Http::post(env('APP_URL') . '/api/user/signup/email/', $this->data);
    }

    /**
     * @Then I should get response status :responseStatus
     */
    public function iShouldGetResponseStatus($responseStatus)
    {
        // Check that the response has a validation error
        if ($this->response->status() !== (int)$responseStatus) {
            throw new \Exception('Expected status ' . $responseStatus . ' but got ' . $this->response->status());
        }
    }

    /**
     * @Then the email :arg1 will be available in the database
     */
    public function theEmailWillBeAvailableInTheDatabase($email)
    {
        // Check that the user was created in the database
        if (!DB::table('users')->where('email', $email)->exists()) {
            throw new \Exception('User with email ' . $email . 'does not exists in the database.');
        }
    }

    /**
     * @Then the email :email will not be available in the database
     */
    public function theEmailWillNotBeAvailableInTheDatabase($email)
    {
        // Check that the user was created in the database
        if (DB::table('users')->where('email', $email)->exists()) {
            throw new \Exception('User with email ' . $email . ' exists in the database.');
        }
    }
}
