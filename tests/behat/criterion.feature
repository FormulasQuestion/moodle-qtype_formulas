@qtype @qtype_formulas
Feature: Test setting the grading criterion in different modes

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
  Scenario: Set a simple grading criterion
    When I follow "Part 1"
    Then the following fields match these values:
      | correctness_simple_mode[0] | 1              |
      | correctness_simple_tol[0]  | 0.01           |
      | correctness_simple_comp[0] | &lt;           |
      | correctness_simple_type[0] | Relative error |
    When I set the following fields to these values:
      | correctness_simple_type[0] | Absolute error |
      | correctness_simple_comp[0] | ==             |
      | correctness_simple_tol[0]  | 0.02           |
    And I press "Save changes"
    And I wait until the page is ready
    And I am on the "test" "core_question > edit" page logged in as teacher1
    Then the following fields match these values:
      | correctness_simple_mode[0] | 1              |
      | correctness_simple_type[0] | Absolute error |
      | correctness_simple_comp[0] | ==             |
      | correctness_simple_tol[0]  | 0.02           |

  @javascript
  Scenario: Set an expert grading criterion
    When I follow "Part 1"
    And I click on "Simplified mode" "checkbox"
    And I wait "1" seconds
    Then the following fields match these values:
      | correctness[0] | _relerr < 0.01 |
    When I set the field "Grading criterion*" to ""
    And I press "Save changes"
    And I wait until the page is ready
    Then I should see "The grading criterion must be evaluated to a single number."
    And the following fields match these values:
      | correctness_simple_mode[0] |  |
    And the "Simplified mode" "checkbox" should be enabled
    When I set the field "Grading criterion*" to "a"
    And I press "Save changes"
    And I wait until the page is ready
    Then I should see "Try evalution error! Variable 'a' has not been defined."
    And the following fields match these values:
      | correctness_simple_mode[0] |  |
    And the "Simplified mode" "checkbox" should be disabled
    When I set the field "Grading criterion*" to "_err == 0 && 1 == 1"
    And I press "Save changes"
    And I wait until the page is ready
    And I am on the "test" "core_question > edit" page logged in as teacher1
    Then the following fields match these values:
      | correctness[0] | _err == 0 && 1 == 1 |
    And the "Simplified mode" "checkbox" should be disabled

  @javascript
  Scenario: Switch from easy to expert
    When I follow "Part 1"
    And I click on "Simplified mode" "checkbox"
    And I wait "1" seconds
    Then the following fields match these values:
      | correctness[0] | _relerr < 0.01 |
    And the "Simplified mode" "checkbox" should be enabled
    When I click on "Simplified mode" "checkbox"
    And I set the following fields to these values:
      | correctness_simple_tol[0]  | 0              |
      | correctness_simple_comp[0] | ==             |
      | correctness_simple_type[0] | Absolute error |
    And I click on "Simplified mode" "checkbox"
    And I wait "1" seconds
    Then the following fields match these values:
      | correctness[0] | _err == 0 |

  @javascript
  Scenario: Switch from expert to easy
    When I follow "Part 1"
    And I click on "Simplified mode" "checkbox"
    And I wait "1" seconds
    And I set the field "Grading criterion*" to "foo bar"
    Then the "Simplified mode" "checkbox" should be disabled
    And I set the field "Grading criterion*" to "_err == 0"
    Then the "Simplified mode" "checkbox" should be enabled
    And I click on "Simplified mode" "checkbox"
    And I wait "1" seconds
    Then the following fields match these values:
      | correctness_simple_mode[0] | 1              |
      | correctness_simple_type[0] | Absolute error |
      | correctness_simple_comp[0] | ==             |
      | correctness_simple_tol[0]  | 0              |
    When I click on "Simplified mode" "checkbox"
    And I wait "1" seconds
    And I set the field "Grading criterion*" to ""
    Then the "Simplified mode" "checkbox" should be enabled
    When I click on "Simplified mode" "checkbox"
    Then the following fields match these values:
      | correctness_simple_mode[0] | 1              |
      | correctness_simple_type[0] | Relative error |
      | correctness_simple_comp[0] | &lt;           |
      | correctness_simple_tol[0]  | 0.01           |
