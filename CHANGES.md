# Changelog

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
