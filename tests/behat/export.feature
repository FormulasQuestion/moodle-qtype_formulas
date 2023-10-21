@qtype @qtype_formulas @_file_upload
Feature: Test exporting Formulas questions
    As a teacher
    In order to be able to reuse my Formulas questions
    I need to export them

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
      | questioncategory | qtype    | name         | template           |
      | Test questions   | formulas | formulas-001 | testmethodsinparts |
    And I log in as "teacher1"
    And I am on "Course 1" course homepage

  Scenario: Export a Formulas question
    When I am on the "Course 1" "core_question > course question export" page
    And I set the field "id_format_xml" to "1"
    And I press "Export questions to file"
    Then following "click here" should download between "5400" and "5600" bytes
    # If the download step is the last in the scenario then we can sometimes run
    # into the situation where the download page causes a http redirect but behat
    # has already conducted its reset (generating an error). By putting a logout
    # step we avoid behat doing the reset until we are off that page.
    And I log out
