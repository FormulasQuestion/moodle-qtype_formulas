# Changelog

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
