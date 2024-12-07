@qtype @qtype_formulas @javascript @_file_upload
Feature: Test importing Formulas questions
    As a teacher
    In order to reuse my Formulas questions
    I need to import them

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
    And I log in as "teacher1"
    And I am on "Course 1" course homepage

  Scenario: import formulas question.
    When I am on the "Course 1" "core_question > course question import" page
    And I set the field "id_format_xml" to "1"
    # Uploading needs JS enabled, because the code is written in a way that would
    # allow overwriting an existing file by pressing a button in a warning dialog.
    And I upload "question/type/formulas/tests/fixtures/qtype_sample_formulas.xml" file to "Import" filemanager
    And I press "id_submitbutton"
    Then I should see "Parsing questions from import file."
    And I should see "Importing 1 questions from file"
    And I should see "1. For a minimal question, you must define a subquestion with"
    And I press "Continue"
    And I should see "Formulas question 001"
