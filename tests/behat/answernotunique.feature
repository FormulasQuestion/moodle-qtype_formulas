@qtype @qtype_formulas
Feature: Test different feedback for questions with unique / non-unique answer

  Background:
    Given the following "users" exist:
      | username |
      | teacher1 |
    And the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1        | 0        |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
    And the following "question categories" exist:
      | contextlevel | reference | name           |
      | Course       | C1        | Test questions |
    And the following "questions" exist:
      | questioncategory | qtype    | name         | template      |
      | Test questions   | formulas | formulas-001 | testsinglenum |
    And I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I navigate to "Question bank" in current page administration

  Scenario: Question with multiple correct answers
    When I am on the "formulas-001" "core_question > preview" page logged in as teacher1
    And I set the following fields to these values:
      | behaviour | immediatefeedback |
    And I press "id_saverestart"
    And I set the field "Answer" to "1"
    And I press "Check"
    Then I should see "One possible correct answer is"

  @javascript
  Scenario: Question with one correct answers
    When I am on the "formulas-001" "core_question > edit" page logged in as teacher1
    And I set the field "Question name" to "Edited formulas-001"
    And I follow "Part 1"
    # Using the click step to test accessibility as well.
    And I click on "There are other correct answers." "checkbox"
    And I press "id_submitbutton"
    And I am on the "Edited formulas-001" "core_question > preview" page logged in as teacher1
    And I set the following fields to these values:
      | behaviour | immediatefeedback |
    And I press "id_saverestart"
    And I set the field "Answer" to "1"
    And I press "Check"
    Then I should see "The correct answer is"
