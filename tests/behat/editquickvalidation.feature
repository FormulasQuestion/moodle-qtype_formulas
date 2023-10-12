@qtype @qtype_formulas @javascript
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

  Scenario: Validate random variables
    When I follow "Variables"
    And I set the field "Random variables" to "a=+"
    And I take focus off "id_varsrandom" "field"
    Then I should see "1: a: Syntax error."
    And I set the field "Random variables" to "a={1,2,3};"
    And I take focus off "id_varsrandom" "field"
    Then I should not see "1: a: Syntax error."

  Scenario: Validate global variables
    When I follow "Variables"
    And I set the field "Global variables" to "a=+"
    And I take focus off "id_varsglobal" "field"
    Then I should see "1: Some expressions cannot be evaluated numerically."
    And I set the field "Global variables" to "a=5;"
    And I take focus off "id_varsglobal" "field"
    Then I should not see "1: Some expressions cannot be evaluated numerically."
    And I set the field "Global variables" to "a=2*b"
    And I take focus off "id_varsglobal" "field"
    Then I should see "1: Variable 'b' has not been defined. in substitute_vname_by_variables"
    And I set the following fields to these values:
      | id_varsrandom | b={1,2,3}; |
      | id_varsglobal |            |
    And I set the field "Global variables" to "a=2*b"
    And I take focus off "id_varsglobal" "field"
    Then I should not see "1: Variable 'b' has not been defined. in substitute_vname_by_variables"

  Scenario: Validate global variables with prior error in random variables
    When I follow "Variables"
    And I set the following fields to these values:
      | id_varsglobal | b=1 |
      | id_varsrandom | a=+ |
    And I click on "id_varsglobal" "field"
    Then I should see "1: a: Syntax error." in the "#id_error_varsrandom" "css_element"
    And the focused element is "id_varsrandom" "field"

  Scenario: Validate local variables
    When I follow "Part 1"
    And I follow "Show more..."
    And I set the field "Local variables" to "a=+"
    And I take focus off "id_vars1_0" "field"
    Then I should see "1: Some expressions cannot be evaluated numerically."
    When I set the field "Local variables" to "a=5;"
    And I take focus off "id_vars1_0" "field"
    Then I should not see "1: Some expressions cannot be evaluated numerically."
    When I set the field "Local variables" to "a=2*b"
    And I take focus off "id_vars1_0" "field"
    Then I should see "1: Variable 'b' has not been defined. in substitute_vname_by_variables"
    When I set the following fields to these values:
      | id_varsrandom | b={1,2,3}; |
    And I set the field "Local variables" to "a=2*b"
    And I take focus off "id_vars1_0" "field"
    Then I should not see "1: Variable 'b' has not been defined. in substitute_vname_by_variables"
    When I set the field "Local variables" to "a=2*c"
    And I take focus off "id_vars1_0" "field"
    Then I should see "1: Variable 'c' has not been defined. in substitute_vname_by_variables"
    When I set the following fields to these values:
      | id_varsrandom | b={1,2,3}; |
      | id_varsglobal | c=4;       |
      | id_vars1_0    | a=2*c;     |
    And I take focus off "id_vars1_0" "field"
    Then I should not see "1: Variable 'c' has not been defined. in substitute_vname_by_variables"

  Scenario: Validate local variables with prior error in random or global variables
    When I follow "Variables"
    And I set the following fields to these values:
      | id_varsglobal | b=1 |
      | id_varsrandom | a=+ |
    And I take focus off "id_varsrandom" "field"
    And I set the field "id_vars1_0" to "a=2*c;"
    And I take focus off "id_vars1_0" "field"
    Then I should see "1: a: Syntax error." in the "#id_error_varsrandom" "css_element"
    And the focused element is "id_varsrandom" "field"
    When I set the following fields to these values:
      | id_varsrandom | a={1,2} |
      | id_varsglobal | b=++    |
    And I take focus off "id_varsglobal" "field"
    And I set the field "id_vars1_0" to "c=3*a;"
    And I take focus off "id_vars1_0" "field"
    Then I should see "1: Some expressions cannot be evaluated numerically." in the "#id_error_varsglobal" "css_element"
    And the focused element is "id_varsglobal" "field"
    # Last two steps verify that the field does not take back the focus, if it already had en error.
    And I click on "id_varsrandom" "field"
    And the focused element is "id_varsrandom" "field"
