@qtype @qtype_formulas
Feature: Make sure we do not leak information in adaptive mode

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
      | questioncategory | qtype    | name       | template       |
      | Test questions   | formulas | threeparts | testthreeparts |
    And the following "activities" exist:
      | activity | name   | course | idnumber | preferredbehaviour | reviewcorrectness | reviewmarks | reviewspecificfeedback | reviewgeneralfeedback |
      | quiz     | Quiz 1 | C1     | quiz1    | adaptive           | 65536             | 65536       | 65536                  | 65536                 |
    And quiz "Quiz 1" contains the following questions:
      | question   | page |
      | threeparts | 1    |
    And I log in as "student"
    And I am on "Course 1" course homepage
    And I follow "Quiz 1"
    And I press "Attempt quiz"

  Scenario: Try to leak number of correct parts via navigation alone
    When I set the field "Answer for part 1" to "5"
    And I set the field "Answer for part 2" to "6"
    And I set the field "Answer for part 3" to "7"
    And I press "Finish attempt"
    And I press "Return to attempt"
    Then ".formulaspartfeedback-0" "css_element" should not exist
    Then ".formulaspartfeedback-1" "css_element" should not exist
    Then ".formulaspartfeedback-2" "css_element" should not exist
    And ".numpartscorrect" "css_element" should not exist

  Scenario: Try to leak number of correct parts via partial submission and navigation
    When I set the field "Answer for part 1" to "5"
    And I press "Check"
    Then I should see "Marks for this submission" in the ".formulaspartfeedback-0" "css_element"
    And I should see "Part 1 correct feedback."
    And I should see "You have correctly answered 1 part of this question."
    When I set the field "Answer for part 2" to "6"
    When I press "Finish attempt"
    And I press "Return to attempt"
    Then I should see "Marks for this submission" in the ".formulaspartfeedback-0" "css_element"
    And I should see "Part 1 correct feedback."
    And ".formulaspartfeedback-1" "css_element" should not exist
    And ".numpartscorrect" "css_element" should not exist

  Scenario: Part feedback is not shown if answer has been modified since last check
    When I set the field "Answer for part 1" to "5"
    And I press "Check"
    Then I should see "Marks for this submission" in the ".formulaspartfeedback-0" "css_element"
    And I should see "Part 1 correct feedback."
    And I should see "You have correctly answered 1 part of this question."
    When I set the field "Answer for part 1" to "6"
    When I press "Finish attempt"
    And I press "Return to attempt"
    Then ".formulaspartfeedback-0" "css_element" should not exist
    And ".gradingdetails" "css_element" should not exist
    And ".numpartscorrect" "css_element" should not exist
