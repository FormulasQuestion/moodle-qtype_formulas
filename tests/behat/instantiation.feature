@qtype @qtype_formulas
Feature: Test instantiation and inline preview while editing a question

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
      | questioncategory | qtype    | name | template      |
      | Test questions   | formulas | test | testsinglenum |
    And I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I navigate to "Question bank" in current page administration
    And I am on the "test" "core_question > edit" page logged in as teacher1

  @javascript
  Scenario: Instantiate and preview
    When I set the following fields to these values:
      | id_varsglobal   | a=3;                   |
      | id_questiontext | Showing {a} and {=2*a} |
      | id_subqtext_0   | Part 1 with {=a+b}     |
      | id_vars1_0      | b=5;                   |
      | id_numdataset   | 5                      |
    And I click on "Instantiate" "button"
    And I click on "div.tabulator-row-odd.tabulator-selectable" "css_element"
    Then I should see "Showing 3 and 6"
    And I should see "Part 1 with 8"
    When I set the following fields to these values:
      | id_subqtext_0 |  |
    And I click on "div.tabulator-row-even.tabulator-selectable" "css_element"
    Then I should see "Showing 3 and 6"
    And I should not see "Part 1 with 8"

  @javascript
  Scenario: Try to instantiate with invalid data
    When I set the following fields to these values:
      | id_varsglobal   | a=x+;                  |
      | id_questiontext | Showing {a} and {=2*a} |
      | id_subqtext_0   | Part 1 with {=a+b}     |
      | id_vars1_0      | b=5;                   |
      | id_numdataset   | 5                      |
    And I click on "Instantiate" "button"
    Then I should see "No preview available."
    When I set the following fields to these values:
      | id_varsglobal | a=3; |
    And I click on "Instantiate" "button"
    Then I should not see "No preview available."

