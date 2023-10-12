@qtype @qtype_formulas
Feature: Preview a Formulas question
    As a teacher
    In order to check my Formulas questions will work for students
    I need to preview them

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
      | questioncategory | qtype    | name         | template           |
      | Test questions   | formulas | formulas-001 | testmethodsinparts |
    And the following "activities" exist:
      | activity | name   | course | idnumber |
      | quiz     | Quiz 1 | C1     | quiz1    |
    And quiz "Quiz 1" contains the following questions:
      | question     | page |
      | formulas-001 | 1    |

  Scenario: Preview a formulas question with correct answer
    When I am on the "formulas-001" "core_question > preview" page logged in as teacher1
    Then I should see "This question shows different display methods of the answer and unit box."
    And I should see "If a car travels 120 m in 3 s, what is the speed of the car"
    And I set the following fields to these values:
      | behaviour | immediatefeedback |
    And I press "id_saverestart"
    And I set the field "Answer and unit for part 1" to "40 m/s"
    And I set the field "Answer for part 2" to "40"
    And I set the field "Unit for part 2" to "m/s"
    And I set the field "Answer for part 3" to "40"
    And I set the field "Answer for part 4" to "40"
    And I press "Check"
    And I should see "Mark 8.00 out of 8.00"
    And I should see "This is the general feedback."
    And I should see "One possible correct answer is: 40 m/s"

  Scenario: Preview an formulas question with incorrect answer
    When I am on the "formulas-001" "core_question > preview" page logged in as teacher1
    Then I should see "This question shows different display methods of the answer and unit box."
    And I should see "If a car travels 120 m in 3 s, what is the speed of the car"
    And I set the following fields to these values:
      | behaviour | immediatefeedback |
    And I press "id_saverestart"
    And I set the field "Answer and unit for part 1" to "40 m/s"
    And I set the field "Answer for part 2" to "40"
    And I set the field "Unit for part 2" to "km"
    And I set the field "Answer for part 3" to "60"
    And I set the field "Answer for part 4" to "40"
    And I press "Check"
    And I should see "Mark 4.00 out of 8.00"
    And I should see "This is the general feedback."
    And I should see "One possible correct answer is: 40 m/s"
