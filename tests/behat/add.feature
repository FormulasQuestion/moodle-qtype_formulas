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
    And I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I navigate to "Question bank" in current page administration

  Scenario: Create a Formulas question
    When I add a "Formulas" question filling the form with:
      | Question name        | formulas-001                              |
      | Question text        | Minimal formula question                  |
      | General feedback     | The correct answer is 1                   |
      | id_answer_0          | 1                                         |
      | id_answermark_0      | 1                                         |
    Then I should see "formulas-001"
