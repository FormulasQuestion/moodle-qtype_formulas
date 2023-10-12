@qtype @qtype_formulas @javascript
Feature: Validation of input

  Background:
    Given the following "users" exist:
      | username |
      | student  |
    And the following "courses" exist:
      | fullname | shortname |
      | Course 1 | C1        |
    And the following "course enrolments" exist:
      | user    | course | role    |
      | student | C1     | student |
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
    Given I log in as "student"
    And I am on the "Quiz 1" "mod_quiz > View" page logged in as "student"
    And I press "Attempt quiz"

  Scenario: Check validation of input works
    When I set the field "Answer for part 2" to "1+"
    # First make sure the script has been loaded and parsed
    Then "" "qtype_formulas > Invisible Validation Warning Symbol" should exist
    # and the internal unit tests have passed
    And "" "qtype_formulas > Validation Unit Tests Error Indicator" should not exist
    # Now wrong input should give an error
    And "" "qtype_formulas > Validation Warning Symbol" should exist
    When I set the field "Answer for part 2" to "1"
    # Finally, the error should be gone
    Then "" "qtype_formulas > Validation Warning Symbol" should not exist
