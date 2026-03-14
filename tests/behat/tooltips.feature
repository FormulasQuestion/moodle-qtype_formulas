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
    Then I should see "Number" in the "" "qtype_formulas > tooltip"
    And I press tab
    Then I should not see "Number"
    And "" "qtype_formulas > tooltip" should not be visible

  Scenario: Try to answer a question with an algebraic formula answer
    When I follow "Quiz 2"
    And I press "Attempt quiz"
    And I set the field "Answer" to "x"
    Then I should see "Algebraic formula" in the "" "qtype_formulas > tooltip"
    And I press tab
    Then I should not see "Algebraic formula"
    And "" "qtype_formulas > tooltip" should not be visible

  Scenario: Try to answer a question with one combined answer+unit field
    When I follow "Quiz 3"
    And I press "Attempt quiz"
    And I set the field "Answer" to "5 m/s"
    Then I should see "Number and unit" in the "" "qtype_formulas > tooltip"
    And I press tab
    Then I should not see "Number and unit"
    And "" "qtype_formulas > tooltip" should not be visible

  Scenario: Try to answer a question with one answer + one unit field
    When I follow "Quiz 4"
    And I press "Attempt quiz"
    And I set the field "Answer" to "5"
    Then I should see "Number" in the "" "qtype_formulas > tooltip"
    And I should not see "Unit" in the "//*[not(self::label)]" "xpath_element"
    # We must reload the page in order to remove the hidden tooltip from the DOM,
    # because later search for our div will only return the first match and the
    # unit's tooltip would be the second.
    When I reload the page
    And I set the field "Unit" to "m/s"
    Then I should not see "Number"
    And I should see "Unit" in the "" "qtype_formulas > tooltip"
    When I press tab
    Then "" "qtype_formulas > tooltip" should not be visible

  Scenario: Try to answer a question with multiple input fields
    When I follow "Quiz 5"
    And I press "Attempt quiz"
    And I set the field "Answer field 1 for part 1" to "1"
    Then I should see "Number" in the "" "qtype_formulas > tooltip"
    And I press shift tab
    Then I should not see "Number"
    And "" "qtype_formulas > tooltip" should not be visible

  Scenario: Tooltip for Number is not shown if disabled
    When the following config values are set as admin:
      | shownumbertooltip | 0 | qtype_formulas |
    And I follow "Quiz 1"
    And I press "Attempt quiz"
    And I set the field "Answer" to "1"
    Then I should not see "Number"
    And "" "qtype_formulas > tooltip" should not be visible

  Scenario: Setting does not affect tooltip for combined field with answer type "Number"
    When the following config values are set as admin:
      | shownumbertooltip | 0 | qtype_formulas |
    And I follow "Quiz 3"
    And I press "Attempt quiz"
    And I set the field "Answer" to "5 m/s"
    Then I should see "Number and unit" in the "" "qtype_formulas > tooltip"
    And I press the escape key
    Then I should not see "Number and unit"
    And "" "qtype_formulas > tooltip" should not be visible

  Scenario: Setting does not affect tooltip for other answer types
    When the following config values are set as admin:
      | shownumbertooltip | 0 | qtype_formulas |
    And I follow "Quiz 2"
    And I press "Attempt quiz"
    And I set the field "Answer" to "x"
    Then I should see "Algebraic formula" in the "" "qtype_formulas > tooltip"
    And I press the escape key
    Then I should not see "Number and unit"
    And "" "qtype_formulas > tooltip" should not be visible
