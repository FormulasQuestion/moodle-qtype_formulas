@qtype @qtype_formulas @javascript
Feature: Test the notice when a caret is used in model answers

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
      | questioncategory | qtype    | name | template       |
      | Test questions   | formulas | test | testthreeparts |
    And I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I navigate to "Question bank" in current page administration
    And I am on the "test" "core_question > edit" page logged in as teacher1

  Scenario: Use caret in model answer and check for notice in two parts
    When I follow "Part 1"
    And I set the field "id_answer_0" to "1^"
    And I wait "2" seconds
    Then I should see "Note that ^ means XOR in model answers, except for algebraic formulas. For exponentiation, use ** instead."
    When I set the field "id_answer_0" to "5"
    And I wait "2" seconds
    Then I should not see "Note that ^ means XOR in model answers, except for algebraic formulas. For exponentiation, use ** instead."
    When I follow "Part 2"
    And I set the field "id_answer_1" to "1^"
    And I wait "2" seconds
    Then I should see "Note that ^ means XOR in model answers, except for algebraic formulas. For exponentiation, use ** instead."
