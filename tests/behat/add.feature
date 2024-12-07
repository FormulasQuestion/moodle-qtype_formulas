@qtype @qtype_formulas
Feature: Test creating a Formulas question
    As a teacher
    In order to test my students
    I need to be able to create a Formulas question

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email               |
      | teacher1 | T1        | Teacher1 | teacher1@moodle.com |
    And the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1        | 0        |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |

  Scenario: Create a Formulas question
    When I am on the "Course 1" "core_question > course question bank" page logged in as teacher1
    And I add a "Formulas" question filling the form with:
      | Question name    | formulas-001             |
      | Question text    | Minimal formula question |
      | General feedback | The correct answer is 1  |
      | id_answer_0      | 1                        |
      | id_answermark_0  | 1                        |
    Then I should see "formulas-001"
