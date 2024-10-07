Feature: Google Login

  Scenario: User logs in with a valid Google access token
    Given I have a valid Google access token
    When I post the Google login request
    Then I should receive an access and refresh token
