@qtype @qtype_formulas
Feature: Test editing a Formulas question
    As a teacher
    In order to be able to update my Formulas question
    I need to edit them

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
      | questioncategory | qtype    | name                     | template       |
      | Test questions   | formulas | formulas-001 for editing | testthreeparts |
    And I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I navigate to "Question bank" in current page administration

  @javascript @_switch_window
  Scenario: Edit a Formulas question
    When I am on the "formulas-001 for editing" "core_question > edit" page logged in as teacher1
    And I set the field "Question name" to ""
    And I press "id_submitbutton"
    Then I should see "You must supply a value here."
    When I set the field "Question name" to "Edited formulas-001 name"
    And I press "id_submitbutton"
    Then I should see "Edited formulas-001 name"
    When I am on the "Edited formulas-001 name" "core_question > edit" page logged in as teacher1
    And I set the following fields to these values:
      | Question name    | Edited formulas-001 name2    |
      | Random variables | v = {40:120:10}; dt = {2:6}; |
    And I press "id_submitbutton"
    Then I should see "Edited formulas-001 name2"
    When I am on the "Edited formulas-001 name2" "core_question > preview" page logged in as teacher1
    Then I should see "Multiple parts : --"
    And I set the following fields to these values:
      | behaviour | immediatefeedback |
    And I press "id_saverestart"
    And I press "Check"
    And I should see "Please put an answer in each input field."
    And I press "Start again"
    And I set the field "Answer for part 1" to "1"
    And I set the field "Answer for part 2" to "6"
    And I set the field "Answer for part 3" to "7"
    And I press "Check"
    And I should see "Partially correct"
    And I press "Start again"
    And I set the field "Answer for part 1" to "5"
    And I set the field "Answer for part 2" to "6"
    And I set the field "Answer for part 3" to "7"
    And I press "Check"
    And I should see "Correct"
