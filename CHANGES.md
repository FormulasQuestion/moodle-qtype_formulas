# Changelog

### 6.1.2 (2025-08-20)

- improvement: allow :MC suffix for radio-box multi-choice
- improvement: better UX for on-the-fly validation of student answers
- bugfix: certain Unicode characters caused a parse error
- bugfix: fix problem with rendering of formatted combined unit field
- internal: update CI to use PostgreSQL 15

### 6.1.1 (2025-07-27)

- bugfix: allow assigning values to elements of shuffled lists

### 6.1.0 (2025-07-26)

- improvement: allow use of local decimal separator (comma) in student answers
- improvement: use local decimal separator when displaying numbers in feedback or question text
- improvement: simplifiy shuffling options in multi-choice answer fields using ":MCS" or ":MCES"
- improvement: implement basic formatting of input fields (width, color, alignment)
- improvement: add option to skip checking correctness of model answer when importing questions
- improvement: better error reporting if importing a question fails
- bugfix: ignore empty statements or subsequent semicolons in variable definitions
- bugfix: avoid undesired linebreak around dropdown fields with Moodle 5.0 and above
- bugfix: avoid re-activation of outdated MathJax preview
- bugfix: fix instantiation check for variables that contain HTML
- bugfix: allow setting individual elements in lists that are initialised with fill()
- bugfix: allow large reservoirs for random and algebraic variables
- bugfix: fix rendering of local variables in questions with answer type algebraic formula
- bugfix: flag quotes and strings in student answers as invalid
- internal: fix failing unit tests for Moodle 4.5+ after MDL-85721
- internal: improve language strings
- internal: improve layout of admin settings page
- internal: remove obsolete admin setting "usepopup"

Details: https://github.com/FormulasQuestion/moodle-qtype_formulas/compare/v6.0.4...v6.1.0

### 6.0.4 (2025-06-16)

- bugfix: avoid outdated MathJax preview being re-activated
- bugfix: solve problem with \n in strings used for MathJax rendering
- improvement: implement PHP-like escape sequences in strings
- internal: complete rewrite of renderer.php

### 6.0.3 (2025-06-06)

- bugfix: fix error message for partially answered questions in interactive mode
- bugfix: make sure partially answered questions are graded in deferred feedback mode
- bugfix: always use correct MathJax container to fetch preview width
- improvement: assure compatibility with MathJax v3
- improvement: silently discard useless PREFIX for better backwards compatibility
- internal: increased test coverage
- internal: update test_restore_course_with_same_stamp_questions after MDL-85556

### 6.0.2 (2025-05-06)

- bugfix: fix problem with subsequent comments in variable definitions

### 6.0.1 (2025-05-04)

- improvement: also accept numeric strings in places where numbers are expected

### 6.0.0 (2025-04-30)

Great care has been taken to make sure that this new version -- despite the numerous changes and
improvements -- is fully backwards compatible with prior versions, i. e. all questions created
in earlier versions will still work. Note, however, that questions including the new features
listed below can no longer be imported to Moodle systems using a 5.x.x version of the Formulas
question plugin.

We have done extensive testing. However, we recommend you not to update the plugin while you have
pending attempts at important exams.

- complete rewrite of the parsing / evaluation engine
- new feature: access to grading variables and student answers in part feedback
- new feature: allow use of strings in ternary expressions; no more obligation to use pick() for this
- new feature: allow string concatenation with + operator; no more obligation to use join() for this
- new feature: access individual chars of a string, as one can do with list elements
- new feature: allow negative indices to access chars or list elements "from the end"
- new feature: allow use of variables for the range delimiters and step size in for loop
- new feature: allow == comparison of strings
- new feature: possibility to use escaped quotes inside string
- new feature: allow to use single quote as string delimiter
- new feature: strings can include line breaks and hence span multiple lines
- new feature: mixed lists are now possible, i. e. lists including numbers and strings
- new feature: lists may now be nested
- new feature: allow use of ranges and elements side-by-side in a list, e. g. [1:10, 12]
- new feature: shuffle() can now be used in global/local variables as well
- new feature: allow usage of pi or π instead of pi() in expressions; pi() is still valid
- new feature: warn teacher when using ^ in model answer
- new feature: MathJax preview of student input and units
- new feature: students can now use lg() for common logarithm and lb() for binary logarithm
- new feature: precise error reporting, indicating what happened and where (if possible)

- improvement: allow better duplicate check during restore, following fix of MDL-83541
- improvement: show answer type in Bootstrap tooltip rather than own method
- improvement: variable instantiation check (in edit form) can handle empty model answers better now

- bugfix: no more loss of images when moving question between categories
- bugfix: no more inconsistency errors when reviewing old attempts

- internal: assure full compatibility with Moodle 5.0
- internal: added extensive automated tests to bring code coverage > 90%
- internal: no more use of eval() in the code
- internal: all Javascript is now in AMD modules
- internal: fixed all codesniffer errors and most warnings, except for legacy code

### 5.3.5 (2025-02-10)

- improvement: avoid possible precision problem with ncr()
- internal: drop support for upcoming Moodle 5.0 for the legacy branch

This is the final regular version for the 5.x branch. It is compatible with Moodle 3.9 to
Moodle 4.5. No updates are planned. Further development is done in the main branch, starting
with version 6.0.0. While it might still work with Moodle 5.0, no tests have been done to make
sure it does.

### 5.3.4.post0 (2024-10-07)
- internal: explicitly list Moodle 4.5 as supported in version.php

Please note: this is the last version with support for Moodle < 4.1 and PHP < 8.0.

### 5.3.4 (2024-10-07)
- assure compatibility with Moodle 4.5

Please note: this is the last version with support for Moodle < 4.1 and PHP < 8.0.

### 5.3.3 (2024-04-20)
- assure compatibility with Moodle 4.4 and PHP 8.3
- bugfix: solve problem with "Save and continue" on edit form and PHP 8.2
- bugfix: disable simple mode for grading criterion if it failed validation
- bugfix: solve problem with < char and instantiation check
- bugfix: correct some messages and texts
- internal: rename behat step to avoid conflicts with other plugins during tests
- internal: update CI
- internal: add code coverage to CI chain

Please note: this is (probably) the last version with support for Moodle < 4.1 and PHP < 8.0.

### 5.3.2 (2023-11-17)
- bugfix: also improve robustness against risky grading variables
- internal: additional tests

### 5.3.1 (2023-11-16)
- bugfix: make sure risky grading criterion cannot break question
- bugfix: make sure grading does not lead to invalid question state
- revert workaround from 5.2.2 (TinyMCE too small), as bug was fixed upstream
- internal: improvements to some tests
- internal: update GitHub actions

### 5.3.0 (2023-10-09)
- assure compatibility with Moodle 4.3 and PHP 8.2
- enhancement: different feedback for unique / non-unique correct answer
- enhancement: allow M (mega) prefix for unit Newton
- bugfix: remove wrongful warning triangle when using fact() in answer
- bugfix: nice formatting of preview for exponentiation, e.g. 4**3
- internal: update GitHub actions (moodle-plugin-ci v4, PHP 8.2, Moodle 4.3)
- internal: change mobile behat tests to work with updated labels in the app
- internal: add separate workflow for mobile behat (only PHP <8.2) with moodle-plugin-ci v3

### 5.2.2 (2023-08-09)
- bugfix: wrong sort order for negative numbers in sort()
- bugfix: some input fields in edit form too small with TinyMCE in Moodle 4.2+
- internal: fix in legacy code (indirect modification of overloaded property)
- internal: changes to behat tests for compatibility with 4.3

### 5.2.1 (2023-04-22)
- assure compatibility with Moodle 4.2
- internal: changes for compatibility with PHP 8.1
- internal: add PHP 8.1 to CI test matrix
- internal: added tests for units

### 5.2.0 (2023-03-17)
- new functions: binomialpdf() and binomialcdf()
- bugfix: gcd() now gives correct result even if one argument is 0
- internal: removed deprecated notify()

### 5.1.2 (2023-02-15)
- bugfix: internal functions (e.g. sigfig) working with map() again

### 5.1.1 (2023-01-30)
- bugfix: fmod() now works like in other scientific calculators
- bugfix: sort() now uses natural sorting and does not lose values anymore
- bugfix: instantiation check could fail in certain cases
- internal: some cleanup, update of package.json

Please note: future releases will no longer support Internet Explorer.

### 5.1.0 (2022-11-23)
- added support for Moodle 4.1
- new functions for number conversion (decimal <-> octal/binary)
- extended functionality for existing poly() function, see documentation
- direct validation of variable definitions when editing/creating a question
- improved check of variable instantiation and inline preview
- internal: added more tests
- internal: code cleanup and refactoring

### 5.0.1 (2022-10-16)
- bugfix: custom functions are now working again

### 5.0.0 (2022-10-15) - YANKED
- new feature: support for Moodle App (thanks to Jakob Heinemann)
- new functions for statistics: stdnormpdf(), stdnormcdf(), normcdf()
- new functions for number theory: modpow(), modinv()
- bugfix: pick() now working correctly with lists (arrays)
- bugfix: npr() now returns correct even for n-r < r
- bugfix: formatcheck.js now working again
- various changes related to acceptance and unit tests
- code cleanup
