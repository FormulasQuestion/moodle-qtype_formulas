@qtype @qtype_formulas
Feature: Test duplicating a quiz containing a Formulas question
  As a teacher
  In order re-use my courses containing formulas questions
  I need to be able to backup and restore them

  Background:
    And the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1        | 0        |
    And the following "question categories" exist:
      | contextlevel | reference | name           |
      | Course       | C1        | Test questions |
    And the following "questions" exist:
      | questioncategory | qtype       | name             | template     |
      | Test questions   | formulas     | formulas-001    | test4        |
    And the following "activities" exist:
      | activity   | name      | course | idnumber |
      | quiz       | Test quiz | C1     | quiz1    |
    And quiz "Test quiz" contains the following questions:
      | formulas-001 | 1 |
    And I log in as "admin"
    And I am on "Course 1" course homepage

  @javascript
  Scenario: Backup and restore a course containing an formulas question
    When I backup "Course 1" course using this options:
      | Confirmation | Filename | test_backup.mbz |
    And I restore "test_backup.mbz" backup into a new course using this options:
      | Schema | Course name | Course 2 |
    And I navigate to "Question bank" in current page administration
    And I click on "Edit" "link" in the "formulas-001" "table_row"
    Then the following fields match these values:
      | Question name        | formulas-001                                                                  |
      | Question text        | This question shows different display methods of the answer and unit box.     |
      | Random variables     | v = {20:100:10}; dt = {2:6};                                                  |
      | Global variables     | s = v*dt;                                                                     |
      | id_answer_0          | v                                                                             |
      | id_answermark_0      | 2                                                                             |
      | id_postunit_0        | m/s                                                                           |
      | id_answer_1          | v                                                                             |
      | id_answermark_1      | 2                                                                             |
      | id_postunit_1        | m/s                                                                           |
      | id_answer_2          | v                                                                             |
      | id_answermark_2      | 2                                                                             |
      | id_postunit_2        |                                                                               |
      | id_answer_3          | v                                                                             |
      | id_answermark_3      | 2                                                                             |
      | id_postunit_3        |                                                                               |