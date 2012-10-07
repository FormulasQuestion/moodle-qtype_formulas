<?php
/* Each entry of $basic_unit_conversion_rules is a pair:
 *  - The first string is the name of the rule, which is used when editing the form
 *  - The second string is the actual rule that will be parsed and used as unit conversion
 *  - The array index is the unique id for the rule, which will be stored in the database
 * Note: the id 0 to 99 is reserved, please do not use to create you own rule
 */
global $basic_unit_conversion_rules;

$basic_unit_conversion_rules = array();
$basic_unit_conversion_rules[0] = array('None', '');
$basic_unit_conversion_rules[1] = array('Common SI unit','
m: k c d m u n p f;
s: m u n p f;
g: k m u n p f;
mol: m u n p;
N: k m u n p f;
A: m u n p f;
J: k M G T P m u n p f;
J = 6.24150947e+18 eV;
eV: k M G T P m u;
W: k M G T P m u n p f;
Pa: k M G T P;
Hz: k M G T P E;
C: k m u n p f;
V: k M G m u n p f;
ohm: m k M G T P;
F: m u n p f;
T: k m u n p;
H: k m u n p;
');
// $basic_unit_conversion_rules[100] = array(
//  $basic_unit_conversion_rules[1][0] + ' and your own conversion rules',
//  $basic_unit_conversion_rules[1][1] + '');
