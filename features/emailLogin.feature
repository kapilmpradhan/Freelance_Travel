Feature: User Login Email
    Scenario: Login with the registered email and password
        Given I provide registered email "availableuser@email.com" and password "password@123" for login
        When I submit the login form
        Then I should get login response status 200
        And I should get tokens in response: 'accessToken' and 'refreshToken'

    Scenario: Login with the un-registered email and password
        Given I provide un-registered email "un-availableuser@email.com" and password "password@123" for login
        When I submit the login form
        Then I should get login response status 400
        And I should get error message of 'Invalid Credentials'
