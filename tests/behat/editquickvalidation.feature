@qtype @qtype_formulas
Feature: Test on-the-fly validation of variables while editing a question

  Background:
    Given the following "users" exist:
      | username |
      | teacher1 |
    And the following "courses" exist:
      | fullname | shortname |
      | Course 1 | C1        |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
    And the following "question categories" exist:
      | contextlevel | reference | name           |
      | Course       | C1        | Test questions |
    And the following "questions" exist:
      | questioncategory | qtype    | name | template      |
      | Test questions   | formulas | test | testsinglenum |
    And I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I navigate to "Question bank" in current page administration
    And I am on the "test" "core_question > edit" page logged in as teacher1

  @javascript
  Scenario: Validate random variables
    When I follow "Variables"
    And I set the field "Random variables" to "a=+"
    Then I should see "1: a: Syntax error."
    And I set the field "Random variables" to "a={1,2,3};"
    Then I should not see "1: a: Syntax error."

  @javascript
  Scenario: Validate global variables
    When I follow "Variables"
    And I set the field "Global variables" to "a=+"
    Then I should see "1: Some expressions cannot be evaluated numerically."
    And I set the field "Global variables" to "a=5;"
    Then I should not see "1: Some expressions cannot be evaluated numerically."
    And I set the field "Global variables" to "a=2*b"
    Then I should see "1: Variable 'b' has not been defined. in substitute_vname_by_variables"
    And I set the following fields to these values:
      | id_varsrandom | b={1,2,3}; |
      | id_varsglobal |            |
    And I set the field "Global variables" to "a=2*b"
    Then I should not see "1: Variable 'b' has not been defined. in substitute_vname_by_variables"

  @javascript
  Scenario: Validate local variables
    When I follow "Part 1"
    And I follow "Show more..."
    And I set the field "Local variables" to "a=+"
    Then I should see "1: Some expressions cannot be evaluated numerically."
    And I set the field "Local variables" to "a=5;"
    Then I should not see "1: Some expressions cannot be evaluated numerically."
    And I set the field "Local variables" to "a=2*b"
    Then I should see "1: Variable 'b' has not been defined. in substitute_vname_by_variables"
    And I set the following fields to these values:
      | id_varsrandom | b={1,2,3}; |
    And I set the field "Local variables" to "a=2*b"
    Then I should not see "1: Variable 'b' has not been defined. in substitute_vname_by_variables"
    And I set the field "Local variables" to "a=2*c"
    Then I should see "1: Variable 'c' has not been defined. in substitute_vname_by_variables"
    And I set the following fields to these values:
      | id_varsrandom | b={1,2,3}; |
      | id_varsglobal | c=4;       |
      | id_vars1_0    | a=2*c;     |
    Then I should not see "1: Variable 'c' has not been defined. in substitute_vname_by_variables"
