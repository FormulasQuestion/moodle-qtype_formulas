@qtype @qtype_formulas @javascript
Feature: Display of tooltips

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
      | Test questions   | formulas | singlenumunit    | testsinglenumunit    |
      | Test questions   | formulas | singlenumunitsep | testsinglenumunitsep |
      | Test questions   | formulas | threeparts       | testthreeparts       |
      | Test questions   | formulas | algebraic        | testalgebraic        |
      | Test questions   | formulas | twoandtwo        | testtwoandtwo        |
    And the following "activities" exist:
      | activity | name   | course | idnumber |
      | quiz     | Quiz 1 | C1     | quiz1    |
      | quiz     | Quiz 2 | C1     | quiz2    |
      | quiz     | Quiz 3 | C1     | quiz3    |
      | quiz     | Quiz 4 | C1     | quiz4    |
      | quiz     | Quiz 5 | C1     | quiz5    |
    And quiz "Quiz 1" contains the following questions:
      | question  | page |
      | singlenum | 1    |
    And quiz "Quiz 2" contains the following questions:
      | question  | page |
      | algebraic | 1    |
    And quiz "Quiz 3" contains the following questions:
      | question      | page |
      | singlenumunit | 1    |
    And quiz "Quiz 4" contains the following questions:
      | question         | page |
      | singlenumunitsep | 1    |
    And quiz "Quiz 5" contains the following questions:
      | question  | page |
      | twoandtwo | 1    |
    And I log in as "student"
    And I am on "Course 1" course homepage

  Scenario: Try to answer a question with one part and one answer field
    When I follow "Quiz 1"
    And I press "Attempt quiz"
    And I set the field "Answer" to "5"
    Then I should see "Number"
    And I press tab
    Then I should not see "Number"
    And "div.tooltip-inner" "css_element" should not exist

  Scenario: Try to answer a question with an algebraic formula answer
    When I follow "Quiz 2"
    And I press "Attempt quiz"
    And I set the field "Answer" to "x"
    Then I should see "Algebraic formula"
    And I press tab
    Then I should not see "Algebraic formula"
    And "div.tooltip-inner" "css_element" should not exist

  Scenario: Try to answer a question with one combined answer+unit field
    When I follow "Quiz 3"
    And I press "Attempt quiz"
    And I set the field "Answer" to "5 m/s"
    Then I should see "Number and unit"
    And I press tab
    Then I should not see "Number and unit"
    And "div.tooltip-inner" "css_element" should not exist

  Scenario: Try to answer a question with one answer + one unit field
    When I follow "Quiz 4"
    And I press "Attempt quiz"
    And I set the field "Answer" to "5"
    Then I should see "Number" in the "div.tooltip-inner" "css_element"
    And I should not see "Unit" in the "div.tooltip-inner" "css_element"
    And I set the field "Unit" to "m/s"
    Then I should not see "Number" in the "div.tooltip-inner" "css_element"
    And I should see "Unit" in the "div.tooltip-inner" "css_element"
    And I should see "Unit"
    And I press tab
    Then "div.tooltip-inner" "css_element" should not exist

  Scenario: Try to answer a question with multiple input fields
    When I follow "Quiz 5"
    And I press "Attempt quiz"
    And I set the field "Answer field 1 for part 1" to "1"
    Then I should see "Number"
    And I press shift tab
    Then I should not see "Number"
    And "div.tooltip-inner" "css_element" should not exist
