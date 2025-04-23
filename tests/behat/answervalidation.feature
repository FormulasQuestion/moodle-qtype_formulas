@qtype @qtype_formulas @javascript
Feature: Validation of student responses

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

  Scenario: Check validation of input works for answer fields
    When I set the field "Answer for part 2" to "1+"
    Then "" "qtype_formulas > Formulas field with warning" should exist
    When I set the field "Answer for part 2" to "1"
    And I press tab
    And I wait "1" seconds
    Then "" "qtype_formulas > Formulas field with warning" should not exist

  Scenario: Check validation of input works for unit fields
    When I set the field "Unit for part 2" to "m + m"
    Then "" "qtype_formulas > Formulas field with warning" should exist
    When I set the field "Unit for part 2" to "m/s"
    And I press tab
    And I wait "1" seconds
    Then "" "qtype_formulas > Formulas field with warning" should not exist

  Scenario: Check validation of input works for combined fields
    When I set the field "Answer and unit for part 1" to "1 + m"
    Then "" "qtype_formulas > Formulas field with warning" should exist
    When I set the field "Answer and unit for part 1" to "1.5e5 m/s"
    And I press tab
    And I wait "1" seconds
    Then "" "qtype_formulas > Formulas field with warning" should not exist

  Scenario: Check rendering of MathJax works in answer field
    # A number in scientific notation should be rendered.
    When I set the field "Answer for part 2" to "1e5"
    And I wait "1" seconds
    Then "" "qtype_formulas > MathJax display" should be visible
    # A simple number should not be rendered and the existing preview should be removed.
    When I set the field "Answer for part 2" to "15.0"
    And I wait "1" seconds
    Then "" "qtype_formulas > MathJax display" should not be visible
    # Putting the rendering again to see it disappear.
    When I set the field "Answer for part 2" to "1e5"
    And I wait "1" seconds
    Then "" "qtype_formulas > MathJax display" should be visible
    # Invalid content of the field means the existing preview should be removed.
    When I set the field "Answer for part 2" to "xxx"
    And I wait "1" seconds
    Then "" "qtype_formulas > MathJax display" should not be visible
    # Make sure there is some preview.
    When I set the field "Answer for part 2" to "123e4"
    And I wait "1" seconds
    Then "" "qtype_formulas > MathJax display" should be visible
    # And see it is removed when focus is lost.
    When I press tab
    And I wait "1" seconds
    Then "" "qtype_formulas > MathJax display" should not be visible
    When I press tab
    # When coming back into the field, the preview must be shown again.
    When I press shift tab
    And I wait "1" seconds
    Then "" "qtype_formulas > MathJax display" should be visible

  Scenario: Check rendering of MathJax works in unit field
    # A compound unit should be rendered.
    When I set the field "Unit for part 2" to "m/s"
    And I wait "1" seconds
    Then "" "qtype_formulas > MathJax display" should be visible
    # A simple unit not be rendered and the existing preview should be removed.
    When I set the field "Unit for part 2" to "m"
    And I wait "1" seconds
    Then "" "qtype_formulas > MathJax display" should not be visible
    # Putting the rendering again to see it disappear.
    When I set the field "Unit for part 2" to "m/s"
    And I wait "1" seconds
    Then "" "qtype_formulas > MathJax display" should be visible
    # Invalid content of the field means the existing preview should be removed.
    When I set the field "Unit for part 2" to "1+2"
    And I wait "1" seconds
    Then "" "qtype_formulas > MathJax display" should not be visible
    # Make sure there is some preview.
    When I set the field "Unit for part 2" to "m kg / s^2"
    And I wait "1" seconds
    Then "" "qtype_formulas > MathJax display" should be visible
    # And see it is removed when focus is lost.
    When I press tab
    And I wait "1" seconds
    Then "" "qtype_formulas > MathJax display" should not be visible
    When I press tab
    # When coming back into the field, the preview must be shown again.
    When I press shift tab
    And I wait "1" seconds
    Then "" "qtype_formulas > MathJax display" should be visible

  Scenario: Check rendering of MathJax works in combined field
    # A number plus an unit should be rendered.
    When I set the field "Answer and unit for part 1" to "10 m/s"
    And I wait "1" seconds
    Then "" "qtype_formulas > MathJax display" should be visible
    # A simple number not be rendered and the existing preview should be removed.
    When I set the field "Answer and unit for part 1" to "100"
    And I wait "1" seconds
    Then "" "qtype_formulas > MathJax display" should not be visible
    # Putting the rendering again to see it disappear.
    When I set the field "Answer and unit for part 1" to "5 m/s"
    And I wait "1" seconds
    Then "" "qtype_formulas > MathJax display" should be visible
    # Invalid content of the field means the existing preview should be removed.
    When I set the field "Answer and unit for part 1" to "1+2 m+s"
    And I wait "1" seconds
    Then "" "qtype_formulas > MathJax display" should not be visible
    # Make sure there is some preview.
    When I set the field "Answer and unit for part 1" to "5 m s^-1"
    And I wait "1" seconds
    Then "" "qtype_formulas > MathJax display" should be visible
    # And see it is removed when focus is lost.
    When I press tab
    And I wait "1" seconds
    Then "" "qtype_formulas > MathJax display" should not be visible
    When I press tab
    # When coming back into the field, the preview must be shown again.
    When I press shift tab
    And I wait "1" seconds
    Then "" "qtype_formulas > MathJax display" should be visible