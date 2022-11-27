# Changelog

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
