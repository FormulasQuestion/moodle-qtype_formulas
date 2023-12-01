@qtype @qtype_formulas
Feature: Usage in quiz

  Background:
    Given the following "users" exist:
      | username |
      | student  |
    And the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1        | 0        |
    And the following "course enrolments" exist:
      | user    | course | role    |
      | student | C1     | student |
    And the following "question categories" exist:
      | contextlevel | reference | name           |
      | Course       | C1        | Test questions |
    And the following "questions" exist:
      | questioncategory | qtype    | name             | template             |
      | Test questions   | formulas | singlenum        | testsinglenum        |
      | Test questions   | formulas | twonums          | testtwonums          |
      | Test questions   | formulas | singlenumunit    | testsinglenumunit    |
      | Test questions   | formulas | singlenumunitsep | testsinglenumunitsep |
      | Test questions   | formulas | threeparts       | testthreeparts       |
      | Test questions   | formulas | mc               | testmc               |
      | Test questions   | formulas | mce              | testmce              |
      | Test questions   | formulas | mcetwoparts      | testmcetwoparts      |
      | Test questions   | formulas | mctwoparts       | testmctwoparts       |
      | Test questions   | formulas | twoandtwo        | testtwoandtwo        |
    And the following "activities" exist:
      | activity | name    | course | idnumber |
      | quiz     | Quiz 1  | C1     | quiz1    |
      | quiz     | Quiz 2  | C1     | quiz2    |
      | quiz     | Quiz 3  | C1     | quiz3    |
      | quiz     | Quiz 4  | C1     | quiz4    |
      | quiz     | Quiz 5  | C1     | quiz5    |
      | quiz     | Quiz 6  | C1     | quiz6    |
      | quiz     | Quiz 7  | C1     | quiz7    |
      | quiz     | Quiz 8  | C1     | quiz8    |
      | quiz     | Quiz 9  | C1     | quiz9    |
      | quiz     | Quiz 10 | C1     | quiz9    |
    And quiz "Quiz 1" contains the following questions:
      | question  | page |
      | singlenum | 1    |
    And quiz "Quiz 2" contains the following questions:
      | question | page |
      | twonums  | 1    |
    And quiz "Quiz 3" contains the following questions:
      | question      | page |
      | singlenumunit | 1    |
    And quiz "Quiz 4" contains the following questions:
      | question         | page |
      | singlenumunitsep | 1    |
    And quiz "Quiz 5" contains the following questions:
      | question   | page |
      | threeparts | 1    |
    And quiz "Quiz 6" contains the following questions:
      | question | page |
      | mc       | 1    |
    And quiz "Quiz 7" contains the following questions:
      | question | page |
      | mce      | 1    |
    And quiz "Quiz 8" contains the following questions:
      | question    | page |
      | mcetwoparts | 1    |
    And quiz "Quiz 9" contains the following questions:
      | question   | page |
      | mctwoparts | 1    |
    And quiz "Quiz 10" contains the following questions:
      | question  | page |
      | twoandtwo | 1    |
    And I log in as "student"
    And I am on "Course 1" course homepage

  Scenario: Try to answer a question with one part and one answer field
    When I follow "Quiz 1"
    And I press "Attempt quiz"
    And I set the field "Answer" to "5"
    And I press "Finish attempt"
    And I press "Submit all and finish"
    # And I click on "Submit all and finish" "button" in the "Confirmation" "dialogue"
    # And I click on "Submit all and finish" "button" in the "Submit all your answers and finish?" "dialogue"
    # And I confirm the quiz submission in the modal dialog
    Then I should see "Your answer is correct."

  Scenario: Try to answer a question with one part and two answer fields
    When I follow "Quiz 2"
    And I press "Attempt quiz"
    And I set the field "Answer field 1" to "2"
    And I set the field "Answer field 2" to "3"
    And I press "Finish attempt"
    And I press "Submit all and finish"
    # And I confirm the quiz submission in the modal dialog
    Then I should see "Your answer is correct."

  Scenario: Try to answer a question with one combined answer+unit field
    When I follow "Quiz 3"
    And I press "Attempt quiz"
    And I set the field "Answer" to "5 m/s"
    And I press "Finish attempt"
    And I press "Submit all and finish"
    # And I confirm the quiz submission in the modal dialog
    Then I should see "Your answer is correct."

  Scenario: Try to answer a question with one answer + one unit field
    When I follow "Quiz 4"
    And I press "Attempt quiz"
    And I set the field "Answer" to "5"
    And I set the field "Unit" to "m/s"
    And I press "Finish attempt"
    And I press "Submit all and finish"
    # And I confirm the quiz submission in the modal dialog
    Then I should see "Your answer is correct."

  Scenario: Try to answer a question with three parts and one answer field each
    When I follow "Quiz 5"
    And I press "Attempt quiz"
    And I set the field "Answer for part 1" to "5"
    And I set the field "Answer for part 2" to "6"
    And I set the field "Answer for part 3" to "7"
    And I press "Finish attempt"
    And I press "Submit all and finish"
    # And I confirm the quiz submission in the modal dialog
    Then I should see "Part 1 correct feedback."
    And I should see "Part 2 correct feedback."
    And I should see "Part 3 correct feedback."

  @javascript
  # Using javascript, because we also want to check accessibility, so
  # we need to click on the radio button's label.
  Scenario: Try to answer a radiobutton multiple choice formula question
    When I follow "Quiz 6"
    And I press "Attempt quiz"
    And I click on "Cat" "radio"
    And I press "Finish attempt"
    And I press "Submit all and finish"
    And I confirm the quiz submission in the modal dialog for the formulas plugin
    Then I should see "Your answer is correct."

  Scenario: Try to answer a drowdown multiple choice formula question
    When I follow "Quiz 7"
    And I press "Attempt quiz"
    And I set the field "Answer" to "Cat"
    And I press "Finish attempt"
    And I press "Submit all and finish"
    # And I confirm the quiz submission in the modal dialog
    Then I should see "Your answer is correct."

  Scenario: Try to answer a question with two parts, one drowdown multiple choice in each of them
    When I follow "Quiz 8"
    And I press "Attempt quiz"
    And I set the field "Answer field 1 for part 1" to "Cat"
    And I set the field "Answer field 1 for part 2" to "Blue"
    And I press "Finish attempt"
    And I press "Submit all and finish"
    # And I confirm the quiz submission in the modal dialog
    Then I should see "Your first answer is correct."
    And I should see "Your second answer is correct."

  @javascript
  # Using javascript, because we also want to check accessibility, so
  # we need to click on the radio button's label.
  Scenario: Try to answer a question with two parts, one radio multiple choice in each of them
    When I follow "Quiz 9"
    And I press "Attempt quiz"
    And I click on "Cat" "radio"
    And I click on "Blue" "radio"
    And I press "Finish attempt"
    And I press "Submit all and finish"
    And I confirm the quiz submission in the modal dialog for the formulas plugin
    Then I should see "Your first answer is correct."
    And I should see "Your second answer is correct."

  Scenario: Try to answer a question with two parts, two numbers in each of them
    When I follow "Quiz 10"
    And I press "Attempt quiz"
    And I set the field "Answer field 1 for part 1" to "1"
    And I set the field "Answer field 2 for part 1" to "2"
    And I set the field "Answer field 1 for part 2" to "3"
    And I set the field "Answer field 2 for part 2" to "4"
    And I press "Finish attempt"
    And I press "Submit all and finish"
    # And I confirm the quiz submission in the modal dialog
    Then I should see "Your answers in part 1 are correct."
    And I should see "Your answers in part 2 are correct."
