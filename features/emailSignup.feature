Feature: User Signup Email

  Scenario: Signup with valid email
    Given I provide email "test@email.com" and password "password@123"
    When I submit the signup form
    Then I should get response status 200
    And the email "test@email.com" will be available in the database

  Scenario: Signup with invalid email
    Given I provide email "test" and password "password@123"
    When I submit the signup form
    Then I should get response status 400
    Then I should get error message 'Validation Error.'
    And the email "test" will not be available in the database
