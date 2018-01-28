<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

defined('MOODLE_INTERNAL') || die();

/**
 * @copyright Copyright (c) 2010-2011, Hon Wai, Lau. All rights reserved.
 * @author Hon Wai, Lau <lau65536@gmail.com>
 * @license New and Simplified BSD licenses, http://www.opensource.org/licenses/bsd-license.php
 */


/**
 * This class provides methods to check whether an input unit is convertible to a unit in a list.
 *
 * A unit is a combination of the 'base units' and its exponents. For the International System of Units
 * (SI), there are 7 base units and some derived units. In comparison, the 'base units' here represents
 * the unit that is not 'compound units', i.e. 'base units' is a string without space.
 * In order to compare whether two string represent the same unit, the method employed here is to
 * decompose both string into 'base units' and exponents and then compare one by one.
 *
 * In addition, different units can represent the same dimension linked by a conversion factor.
 * All those units are acceptable, so there is a need for a conversion method. To solve this problem,
 * for the same dimensional quantity, user can specify conversion rules between several 'base units'.
 * Also, user are allow to specify one (and only one) conversion rule between different 'compound units'
 * known as the $target variable in the check_convertibility().
 *
 * Example format of rules, for 'compound unit': "J = N m = kg m^2/s^2, W = J/s = V A, Pa = N m^(-2)"
 * For 'base unit': "1 m = 1e-3 km = 100 cm; 1 cm = 0.3937 inch; 1024 B = 1 KiB; 1024 KiB = 1 MiB"
 * The scale of a unit without a prefix is assumed to be 1. For convenience of using SI prefix, an
 * alternative rules format for 'base unit' is that a string with a unit and colon, then followed by
 * a list of SI prefix separated by a space, e.g. "W: M k m" equal to "W = 1e3 mW = 1e-3kW = 1e-6MW"
 */
class answer_unit_conversion {
    private $mapping;           // Mapping of the unit to the (dimension class, scale).
    private $additional_rules;  // Additional rules other than the default rules.
    private $default_mapping;   // Default mapping of a user selected rules, usually Common SI prefix.
    private $default_last_id;   // Dimension class id counter.
    private $default_id;        // Id of the default rule.
    private $default_rules;     // String of the default rule in a particular format.
    public static $unit_exclude_symbols = '][)(}{><0-9.,:;`~!@#^&*\/?|_=+ -';
    public static $prefix_scale_factors = array('d' => 1e-1, 'c' => 1e-2, 'da' => 1e1, 'h' => 1e2,
        'm' => 1e-3, 'u' => 1e-6, 'n' => 1e-9, 'p' => 1e-12, 'f' => 1e-15, 'a' => 1e-18, 'z' => 1e-21, 'y' => 1e-24,
        'k' => 1e3,  'M' => 1e6,  'G' => 1e9,  'T' => 1e12,  'P' => 1e15,  'E' => 1e18,  'Z' => 1e21,  'Y' => 1e24);
    // For convenience, u is used for micro-, rather than "mu", which has multiple similar UTF representations.

    // Initialize the internal conversion rule to empty. No exception raised.
    public function __construct() {
        $this->default_id = 0;
        $this->default_rules = '';
        $this->default_mapping = null;
        $this->mapping = null;
        $this->additional_rules = '';
    }


    /**
     * It assign default rules to this class. It will also reset the mapping. No exception raised.
     *
     * @param string $default_id id of the default rule. Use to avoid reinitialization same rule set
     * @param string $default_rules default rules
     */
    public function assign_default_rules($default_id, $default_rules) {
        if ($this->default_id == $default_id) {
            return;  // Do nothing if the rules are unchanged.
        }
        $this->default_id = $default_id;
        $this->default_rules = $default_rules;
        $this->default_mapping = null;
        $this->mapping = null;
        $this->additional_rules = '';   // Always remove the additional rule.
    }


    /**
     * Add the additional rule other than the default. Note the previous additional rule will be erased.
     *
     * @param string $additional_rules the additional rule string
     */
    public function assign_additional_rules($additional_rules) {
        $this->additional_rules = $additional_rules;
        $this->mapping = null;
    }


    /**
     * Parse all defined rules. It is designed to avoid unnecessary reparsing. Exception on parsing error
     */
    public function reparse_all_rules() {
        if ($this->default_mapping === null) {
            $tmp_mapping = array();
            $tmp_counter = 0;
            $this->parse_rules($tmp_mapping, $tmp_counter, $this->default_rules);
            $this->default_mapping = $tmp_mapping;
            $this->default_last_id = $tmp_counter;
        }
        if ($this->mapping === null) {
            $tmp_mapping = $this->default_mapping;
            $tmp_counter = $this->default_last_id;
            $this->parse_rules($tmp_mapping, $tmp_counter, $this->additional_rules);
            $this->mapping = $tmp_mapping;
        }
    }


    // Return the current unit mapping in this class.
    public function get_unit_mapping() {
        return $this->mapping;
    }


    // Return a dimension classes list for current mapping. Each class is an array of $unit to $scale mapping.
    public function get_dimension_list() {
        $dimension_list = array();
        foreach ($this->mapping as $unit => $class_scale) {
            list($class, $scale) = $class_scale;
            $dimension_list[$class][$unit] = $scale;
        }
        return $dimension_list;
    }


    /**
     * Check whether an input unit is equivalent, under conversion rules, to target units. May throw
     *
     * @param string $ipunit The input unit string
     * @param string $targets The list of unit separated by "=", such as "N = kg m/s^2"
     * @return object with three field:
     *   (1) convertible: true if the input unit is equivalent to the list of unit, otherwise false
     *   (2) cfactor: the number before ipunit has to multiply by this factor to convert a target unit.
     *     If the ipunit is not match to any one of target, the conversion factor is always set to 1
     *   (3) target: indicate the location of the matching in the $targets, if they are convertible
     */
    public function check_convertibility($ipunit, $targets) {
        $l1 = strlen(trim($ipunit)) == 0;
        $l2 = strlen(trim($targets)) == 0;
        if ($l1 && $l2) {
            // If both of them are empty, no unit check is required. i.e. they are equal.
            return (object) array('convertible' => true,  'cfactor' => 1, 'target' => 0);
        } else if (($l1 && !$l2) || (!$l1 && $l2)) {
            // If one of them is empty, they must not equal.
            return (object) array('convertible' => false, 'cfactor' => 1, 'target' => null);
        }
        // Parsing error for $ipunit is counted as not equal because it cannot match any $targets.
        $ip = $this->parse_unit($ipunit);
        if ($ip === null) {
            return (object) array('convertible' => false, 'cfactor' => 1, 'target' => null);
        }
        $this->reparse_all_rules();   // Reparse if the any rules have been updated.
        $targets_list = $this->parse_targets($targets);
        $res = $this->check_convertibility_parsed($ip, $targets_list);
        if ($res === null) {
            return (object) array('convertible' => false, 'cfactor' => 1, 'target' => null);
        } else {
            // For the input successfully converted to one of the unit in the $targets list.
            return (object) array('convertible' => true,  'cfactor' => $res[0], 'target' => $res[1]);
        }
    }


    /**
     * Parse the $targets into an array of target units. Throw on parsing error
     *
     * @param string $targets The "=" separated list of unit, such as "N = kg m/s^2"
     * @return an array of parsed unit, parsed by the parse_unit().
     */
    public function parse_targets($targets) {
        $targets_list = array();
        if (strlen(trim($targets)) == 0) {
            return $targets_list;
        }
        $units = explode('=', $targets);
        foreach ($units as $unit) {
            if (strlen(trim($unit) ) == 0) {
                throw new Exception('""');
            }
            $parsed_unit = $this->parse_unit($unit);
            if ($parsed_unit === null) {
                throw new Exception('"'.$unit.'"');
            }
            $targets_list[] = $parsed_unit;
        }
        return $targets_list;
    }


    /**
     * Check whether an parsed input unit $a is the same as one of the parsed unit in $target_units. No throw
     *
     * @param array $a the an array of (base unit => exponent) parsed by the parse_unit() function
     * @param array $targets_list an array of parsed units.
     * @return the array of (conversion factor, location in target list) if convertible, otherwise null
     */
    private function check_convertibility_parsed($a, $targets_list) {
        foreach ($targets_list as $i => $t) {   // Use exclusion method to check whether there is one match.
            if (count($a) != count($t)) {
                // If they have different number of base unit, skip.
                continue;
            }
            $cfactor = 1.;
            $is_all_matches = true;
            foreach ($a as $name => $exponent) {
                $unit_found = isset($t[$name]);
                if ($unit_found) {
                    $f = 1;
                    $e = $t[$name];         // Exponent of the target base unit.
                } else {     // If the base unit not match directly, try conversion.
                    list($f, $e) = $this->attempt_conversion($name, $t);
                    $unit_found = isset($f);
                }
                if (!$unit_found || abs($exponent - $e) > 0) {
                    $is_all_matches = false; // If unit is not found or the exponent of this dimension is wrong.
                    break;  // Stop further check.
                }
                $cfactor *= pow($f, $e);
            }
            if ($is_all_matches) {
                // All unit name and their dimension matches.
                return array($cfactor, $i);
            }
        }
        return null;   // None of the possible units match, so they are not the same.
    }


    /**
     * Attempt to convert the $test_unit_name to one of the unit in the $base_unit_array,
     * using any of the conversion rule added in this class earlier. No throw
     *
     * @param string $test_unit the name of the test unit
     * @param array $base_unit_array in the format of array(unit => exponent, ...)
     * @return array(conversion factor, unit exponent) if it can be converted, otherwise null.
     */
    private function attempt_conversion($test_unit_name, $base_unit_array) {
        $oclass = $this->mapping[$test_unit_name];
        if (!isset($oclass)) {
            return null;  // It does not exist in the mapping implies it is not convertible.
        }
        foreach ($base_unit_array as $u => $e) {
            $tclass = $this->mapping[$u];   // Try to match the dimension class of each base unit.
            if (isset($tclass) && $oclass[0] == $tclass[0]) {
                return array($oclass[1] / $tclass[1], $e);
            }
        }
        return null;
    }


    /**
     * Split the input into the number and unit. No exception
     *
     * @param string $input physical quantity with number and unit, assume 1 if number is missing
     * @return object with number and unit as the field name. null if input is empty
     */
    private function split_number_unit($input) {
        $input = trim($input);
        if (strlen($input) == 0) {
            return null;
        }
        $ex = explode(' ', $input, 2);
        $number = $ex[0];
        $unit = count($ex) > 1 ? $ex[1] : null;
        if (is_numeric($number)) {
            return (object) array('number' => floatval($number), 'unit' => $unit);
        } else {
            return (object) array('number' => 1, 'unit' => $input);
        }
    }


    /**
     * Parse the unit string into a simpler pair of base unit and its exponent. No exception
     *
     * @param string $unit_expression The input unit string
     * @param bool $no_divisor whether divisor '/' is acceptable. It is used to parse unit recursively
     * @return an array of the form (base unit name => exponent), null on error
     */
    public function parse_unit($unit_expression, $no_divisor=false) {
        if (strlen(trim($unit_expression)) == 0) {
            return array();
        }

        $pos = strpos($unit_expression, '/');
        if ($pos !== false) {
            if ($no_divisor || $pos == 0 || $pos >= strlen($unit_expression) - 1) {
                return null;  // Only one '/' is allowed.
            }
            $left = trim(substr($unit_expression, 0, $pos));
            $right = trim(substr($unit_expression, $pos + 1));
            if ($right[0] == '(' && $right[strlen($right) - 1] == ')') {
                $right = substr($right, 1, strlen($right) - 2);
            }
            $uleft = $this->parse_unit($left, true);
            $uright = $this->parse_unit($right, true);
            if ($uleft == null || $uright == null) {
                return null;    // If either part contains error.
            }
            foreach ($uright as $u => $exponent) {
                if (array_key_exists($u, $uleft)) {
                    return null;     // No duplication.
                }
                $uleft[$u] = -$exponent;   // Take opposite of the exponent.
            }
            return $uleft;
        }

        $unit = array();
        $unit_element_name = '([^'.self::$unit_exclude_symbols.']+)';
        $unit_expression = preg_replace('/\s*\^\s*/', '^', $unit_expression);
        $candidates = explode(' ', $unit_expression);
        foreach ($candidates as $candidate) {
            $ex = explode('^', $candidate);
            $name = $ex[0];     // There should be no space remaining.
            if (count($ex) > 1 && (strlen($name) == 0 || strlen($ex[1]) == 0)) {
                return null;
            }
            if (strlen($name) == 0) {
                continue;  // If it is an empty space.
            }
            if (!preg_match('/^'.$unit_element_name.'$/', $name)) {
                return null;
            }
            $exponent = null;
            if (count($ex) > 1) {
                if (!preg_match('/(.*)([0-9]+)(.*)/', $ex[1], $matches)) {
                    return null;     // Get the number of exponent.
                }
                if ($matches[1] == '' && $matches[3] == '') {
                    $exponent = intval($matches[2]);
                }
                if ($matches[1] == '-' && $matches[3] == '') {
                    $exponent = -intval($matches[2]);
                }
                if ($matches[1] == '(-' && $matches[3] == ')') {
                    $exponent = -intval($matches[2]);
                }
                if ($exponent == null) {
                    return null;    // No pattern matched.
                }
            } else {
                $exponent = 1;
            }
            if (array_key_exists($name, $unit)) {
                return null;     // No duplication.
            }
            $unit[$name] = $exponent;
        }
        return $unit;
    }


    /**
     * Parse rules into an mapping that will be used for fast lookup of unit. Exception on parsing error
     *
     * @param array $mapping an empty array, or array of unit => array(dimension class, conversion factor)
     * @param int $dim_id_count current number of dimension class. It will be incremented for new class
     * @param string $rules_string a comma separated list of rules
     */
    private function parse_rules(&$mapping, &$dim_id_count, $rules_string) {
        $rules = explode(';', $rules_string);
        foreach ($rules as $rule) {
            if (strlen(trim($rule)) > 0) {
                $unit_scales = array();
                $e = explode(':', $rule);
                if (count($e) > 3) {
                    throw new Exception('Syntax error of SI prefix');
                } else if (count($e) == 2) {
                    $unit_name = trim($e[0]);
                    if (preg_match('/['.self::$unit_exclude_symbols.']+/', $unit_name)) {
                        throw new Exception('"'.$unit_name.'" unit contains unaccepted character.');
                    }
                    $unit_scales[$unit_name] = 1.0;    // The original unit.
                    $si_prefixes = explode(' ', $e[1]);
                    foreach ($si_prefixes as $prefix) {
                        if (strlen($prefix) != 0) {
                            $f = self::$prefix_scale_factors[$prefix];
                            if (!isset($f)) {
                                throw new Exception('"'.$prefix.'" is not SI prefix.');
                            }
                            $unit_scales[$prefix.$unit_name] = $f;
                        }
                    }
                } else {
                    $data = explode('=', $rule);
                    foreach ($data as $d) {
                        $splitted = $this->split_number_unit($d);
                        if ($splitted === null || preg_match('/['.self::$unit_exclude_symbols.']+/', $splitted->unit)) {
                            throw new Exception('"'.$splitted->unit.'" unit contains unaccepted character.');
                        }
                        $unit_scales[trim($splitted->unit)] = 1. / floatval($splitted->number);
                    }
                }
                if (array_key_exists(key($unit_scales), $mapping)) {    // Is the first unit already defined?
                    $m = $mapping[key($unit_scales)];   // If yes, use the existing id of the same dimension class.
                    $dim_id = $m[0];  // This can automatically join all the previously defined unit scales.
                    $factor = $m[1] / current($unit_scales);    // Define the relative scale.
                } else { // Otherwise use a new id and define the relative scale to 1.
                    $dim_id = $dim_id_count++;
                    $factor = 1;
                }
                foreach ($unit_scales as $unit => $scale) {
                    // Join the new unit scale to old one, if any.
                    $mapping[$unit] = array($dim_id, $factor * $scale);
                }
            }
        }
    }

}
