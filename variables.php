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

/**
 * The qtype_formulas_variables class is used to parse and evaluate variables.
 *
 * @copyright &copy; 2010-2011 Hon Wai, Lau
 * @author Hon Wai, Lau <lau65536@gmail.com>
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License version 3
 */

defined('MOODLE_INTERNAL') || die();

// Helper function to emulate the behaviour of the count() function
// before php 7.2.
// Needed because a string is passed as parameter in many places in the code.
function mycount($a) {
    if ($a === null) {
        return 0;
    } else {
        if ($a instanceof \Countable || is_array($a)) {
            return count($a);
        } else {
            return 1;
        }
    }
}

function fact($n) {
    $n = (int) $n;
    if ( $n < 2 ) {
        return 1;
    }
    $return = 1;
    for ($i = $n; $i > 1; $i--) {
        $return *= $i;
    }

    return $return;
}

function npr($n, $r) {
    $n = (int)$n;
    $r = (int)$r;
    if ($r > $n) {
        return 0;
    }
    if (($n - $r) < $r) {
        return npr($n, ($n - $r));
    }
    $return = 1;
    for ($i = 0; $i < $r; $i++) {
         $return *= ($n - $i);
    }
    return $return;
}

function ncr($n, $r) {
    $n = (int)$n;
    $r = (int)$r;
    if ($r > $n) {
        return 0;
    }
    if (($n - $r) < $r) {
        return ncr($n, ($n - $r));
    }
    $return = 1;
    for ($i = 0; $i < $r; $i++) {
         $return *= ($n - $i) / ($i + 1);
    }
    return $return;
}

function gcd($a, $b) {
    if ($a < 0) {
        $a = 0 - $a;
    }
    if ($b < 0 ) {
        $b = 0 - $b;
    }
    if ($a == 0 || $b == 0) {
        return 1;
    }
    if ($a == $b) {
        return $a;
    }
    do {
        $rest = (int) $a % $b;
        $a = $b;
        $b = $rest;
    } while ($rest > 0);
    return $a;
}

function lcm($a, $b) {
    return $a * $b / gcd($a, $b);
}

function sigfig($number, $precision) {
    if ($number == 0) {
        $decimalplaces = $precision - 1;
    } else if ($number < 0) {
        $decimalplaces = $precision - floor(log10($number * -1)) - 1;
    } else {
        $decimalplaces = $precision - floor(log10($number)) - 1;
    }
    $answer = ($decimalplaces > 0) ?
            number_format($number, $decimalplaces, '.', '') : number_format(round($number, $decimalplaces), 0, '.', '');
    return $answer;
}

/**
 * Class contains methods to parse variables text and evaluate variables. Results are stored in the $vstack
 * The functions can be roughly classified into 5 categories:
 *
 * - handle variable stack
 * - substitute number, string, function and variable name by placeholder, and the reverse functino
 * - parse and instantiate random variable
 * - evaluate assignments, general expression and numerical expression.
 * - evaluate algebraic formula
 */
class qtype_formulas_variables {
    private static $maxdataset = 2e9;      // It is the upper limit for the exhaustive enumeration.
    private static $listmaxsize = 1000;

    private function initialize_function_list() {
        $this->func_const = array_flip( array('pi'));
        $this->func_unary = array_flip( array('abs', 'acos', 'acosh', 'asin', 'asinh', 'atan', 'atanh', 'ceil'
            , 'cos', 'cosh' , 'deg2rad', 'exp', 'expm1', 'floor', 'is_finite', 'is_infinite', 'is_nan'
            , 'log10', 'log1p', 'rad2deg', 'sin', 'sinh', 'sqrt', 'tan', 'tanh', 'log', 'round', 'fact') );
        $this->func_binary = array_flip( array('log', 'round', 'atan2', 'fmod', 'pow', 'min', 'max', 'ncr', 'npr', 'gcd', 'lcm', 'sigfig') );
        $this->func_special = array_flip( array('fill', 'len', 'pick', 'sort', 'sublist', 'inv', 'map', 'sum', 'concat', 'join', 'str', 'diff', 'poly') );
        $this->func_all = array_merge($this->func_const, $this->func_unary, $this->func_binary, $this->func_special);
        $this->binary_op_map = array_flip( array('+', '-', '*', '/', '%', '>', '<', '==', '!=', '&&', '||', '&', '|', '<<', '>>', '^'));
        // $this->binary_op_reduce = array_flip( array('||', '&&', '==', '+', '*') );

        // Note that the implementation is exactly the same as the client so the behaviour should be the same.
        $this->func_algebraic = array_flip( array('sin', 'cos', 'tan', 'asin', 'acos', 'atan',
            'exp', 'log10', 'ln', 'sqrt', 'abs', 'ceil', 'floor', 'fact'));
        $this->constlist = array('pi' => '3.14159265358979323846');
        $this->evalreplacelist = array('ln' => 'log', 'log10' => '(1./log(10.))*log'); // Natural log and log with base 10, no log allowed to avoid ambiguity.
    }

    public function __construct() {
        $this->initialize_function_list();
    }

    /**
     * Data structure of the variables stack object, containing:
     * - all is an array with name (key) => data (value),
     *   - data is and object contains the type information and variable value.
     * - idcounter stores the largest id of temporary variables
     *
     * Note the basic type of the variables are:
     * n: number, s: string, ln: list of number, ls: list of string, a: algebraic variable
     *
     * Note also that the type used internally are:
     * f: function that can be used for algebraic formula, F: functions that will be used internally only
     * z(n, s, ln, ls): set of (number, string, list of number, list of string), zh(ln, ls): shuffle (list of number, list of string)
     * Note the type number 'n' has a "constantness" associated to it. The value is of type string if it is constant
     */

    // This function must be called to initial a variable stack, and the returned variable is required by most function.
    public function vstack_create() {
        return (object)array('idcounter' => 0, 'all' => array());
    }

    // Return a serialized string of vstack with type n, s, ln, ls. It can be reconstructed by calling evaluate_assignments().
    public function vstack_get_serialization(&$vstack) {
        $ctype = array_flip(explode(',', 'n,s,ln,ls'));
        $vstr = '';
        foreach ($vstack->all as $name => $data) {
            if (array_key_exists($data->type, $ctype)) {
                $values = $data->type[0] == 'l' ? $data->value : array($data->value);   // Convert all into arrays for homogeneous treatment.
                if ($data->type == 's' || $data->type == 'ls')  for ($i = 0; $i < mycount($values); $i++)
                    $values[$i] = '"'.$values[$i].'"';    // String has a quotation.
                $vstr .= $name . '=' . ($data->type[0] == 'l' ? ('['.implode(',', $values).']') : $values[0]) . ';';
            }
        }
        return $vstr;
    }

    // Return the size of sample space, or null if it is too large. The purpose of this number is to instantiate all random dataset.
    public function vstack_get_number_of_dataset(&$vstack) {
        $numdataset = 1;
        foreach ($vstack->all as $name => $data)  if ($data->type[0] == 'z' && $data->type != 'zh') {
            $numdataset *= $data->value->numelement;  // The 'shuffle' is not counted, as it always have large number of permutation...
            if ($numdataset > self::$maxdataset)  return null;
        }
        return $numdataset;
    }

    // Return whether there is shuffled data.
    public function vstack_get_has_shuffle(&$vstack) {
        foreach ($vstack->all as $name => $data)  if ($data->type[0] == 'zh')  return true;
        return false;
    }

    // Return the list of variables stored in the vstack.
    public function vstack_get_names(&$vstack) {
        return array_keys($vstack->all);
    }

    public function vstack_get_variable(&$vstack, $name) {
        return array_key_exists($name, $vstack->all) ? $vstack->all[$name] : null;
    }

    public function vstack_update_variable(&$vstack, $name, $index, $type, $value) {
        if ($index === null) {
            if ($type[0] == 'l') {  // Error check for list.
                if (!is_array($value))  throw new Exception('Unknown error. vstack_update_variable()');
                if (mycount($value) < 1 || mycount($value) > self::$listmaxsize)  throw new Exception(get_string('error_vars_array_size', 'qtype_formulas'));
                if (!is_numeric($value[0]) && !is_string($value[0]))  throw new Exception(get_string('error_vars_array_type', 'qtype_formulas'));
                if ($type[1] == 'n') {
                    for ($i = 0; $i < mycount($value); $i++) {
                        if (!is_numeric($value[$i]))  throw new Exception(get_string('error_vars_array_type', 'qtype_formulas'));
                        $value[$i] = floatval($value[$i]);
                    }
                } else {
                    for ($i = 0; $i < mycount($value); $i++)
                        if (!is_string($value[$i]))  throw new Exception(get_string('error_vars_array_type', 'qtype_formulas'));
                }
            }
            $vstack->all[$name] = (object)array('type' => $type, 'value' => $value);
        } else {
            $list = &$vstack->all[$name];
            if ($list->type[0] != 'l')  throw new Exception(get_string('error_vars_array_unsubscriptable', 'qtype_formulas'));
            $index = intval($index);
            if ($index < 0 || $index >= mycount($list->value))  throw new Exception(get_string('error_vars_array_index_out_of_range', 'qtype_formulas'));
            if ($list->type[1] != $type)  throw new Exception(get_string('error_vars_array_type', 'qtype_formulas'));
            $list->value[$index] = $type == 'n' ? floatval($value) : $value;
        }
    }

    private function vstack_mark_current_top(&$vstack) {
        return (object)array('idcounter' => $vstack->idcounter, 'sz' => mycount($vstack->all));
    }

    private function vstack_restore_previous_top(&$vstack, $previoustop) {
        $vstack->all = array_slice($vstack->all, 0, $previoustop->sz);
        $vstack->idcounter = $previoustop->idcounter;
    }

    private function vstack_add_temporary_variable(&$vstack, $type, $value) {
        $name = '@' . $vstack->idcounter;
        $this->vstack_update_variable($vstack, $name, null, $type, $value);
        $vstack->idcounter++;
        return $name;
    }

    private function vstack_clean_temporary(&$vstack) {
        $tmp = $this->vstack_create();
        foreach ($vstack->all as $name => $data)  if ($name[0] != '@')
            $tmp->all[$name] = $data;
        return $tmp;
    }

    /**
     * These functions replace the string, number, fixed range, function and variable name by placeholder (start with @)
     * Also, the reverse substitution function also available for different situation.
     * Note that string and fixed range are not treated as placeholder, so text with them cannot be fully recovered.
     */

    // Return the text with the variables, or evaluable expressions, substituted by their values.
    public function substitute_variables_in_text(&$vstack, $text) {
        $funcpattern = '/(\{=[^{}]+\}|\{([A-Za-z][A-Za-z0-9_]*)(\[([0-9]+)\])?\})/';
        $results = array();
        $ts = explode("\n`", $text);     // The ` is the separator, so split it first.
        foreach ($ts as $text) {
            $splitted = explode("\n`", preg_replace($funcpattern, "\n`$1\n`", $text));
            for ($i = 1; $i < mycount($splitted); $i += 2) {
                try {
                    $expr = substr($splitted[$i], $splitted[$i][1] == '=' ? 2 : 1 , -1);
                    $res = $this->evaluate_general_expression($vstack, $expr);
                    if ($res->type != 'n' && $res->type != 's')  throw new Exception();     // Skip for other type.
                    $splitted[$i] = $res->value;
                } catch (Exception $e) {
                    // Note that the expression will not be replaced if error occurs. Also, no error throw in any cases.
                }
            }
            $results[] = implode('', $splitted);
        }
        return implode("\n`", $results);
    }

    // Return the original string by substituting back the placeholders (given by variables in $vstack) in the input $text.
    private function substitute_placeholders_in_text(&$vstack, $text) {
        $splitted = explode('`', preg_replace('/(@[0-9]+)/', '`$1`', $text));
        for ($i = 1; $i < mycount($splitted); $i += 2)      // The length will always be odd, and the placeholder is stored in odd index.
            $splitted[$i] = $this->vstack_get_variable($vstack, $splitted[$i])->value;   // Substitute back the strings.
        return implode('', $splitted);
    }

    // If substitute_variables_by_placeholders() was used for $text, then this function forward the value of type 'v' to the actual variable value.
    private function substitute_vname_by_variables(&$vstack, $text) {
        $splitted = explode('`', preg_replace('/(@[0-9]+)/', '`$1`', $text));
        $appearedvars = array();     // Reuse the temporary variable if possible.
        for ($i = 1; $i < mycount($splitted); $i += 2) {    // The length will always be odd, and the numbers are stored in odd index.
            $data = $this->vstack_get_variable($vstack, $splitted[$i]);
            if ($data->type == 'v') {
                $tmp = $this->vstack_get_variable($vstack, $data->value);
                if ($tmp === null)  throw new Exception(get_string('error_vars_undefined', 'qtype_formulas', $data->value) . ' in substitute_vname_by_variables');
                if (!array_key_exists($data->value, $appearedvars))
                    $appearedvars[$data->value] = $this->vstack_add_temporary_variable($vstack, $tmp->type, $tmp->value);
                $splitted[$i] = $appearedvars[$data->value];
            }
        }
        return implode('', $splitted);
    }

    // Replace the strings in the $text.
    private function substitute_strings_by_placholders(&$vstack, $text) {
        $text = stripcslashes($text);
        $splitted = explode("\"", $text);
        if (mycount($splitted) % 2 == 0)  throw new Exception(get_string('error_vars_string', 'qtype_formulas'));
        foreach ($splitted as $i => &$s)  if ($i % 2 == 1) {
            if (strpos($s, '\'') !== false || strpos($s, "\n") !== false)
                throw new Exception(get_string('error_vars_string', 'qtype_formulas'));
            $s = $this->vstack_add_temporary_variable($vstack, 's', $s);
        }
        else if (strpos($s, '@') !== false || strpos($s, '`') !== false) // Characters @ and ` can't be used in the main text.
            throw new Exception(get_string('error_forbid_char', 'qtype_formulas'));
        return implode('', $splitted);
    }

    // Replace the fixed range of the form [a:b] in the $text by variables with new names in $tmpnames, and add it to the $vars.
    private function substitute_fixed_ranges_by_placeholders(&$vstack, $text) {
        $rangepattern = '/(\[[^\]]+:[^\]]+\])/';
        $splitted = explode('`', preg_replace($rangepattern, '`$1`', $text));
        for ($i = 1; $i < mycount($splitted); $i += 2) {    // The length will always be odd, and the numbers are stored in odd index.
            $res = $this->parse_fixed_range($vstack, substr($splitted[$i], 1, -1));
            if ($res === null)  throw new Exception(get_string('error_fixed_range', 'qtype_formulas'));
            $data = array();
            for ($z = $res->element[0]; $z < $res->element[1]; $z += $res->element[2]) {
                $data[] = $z;
                if (mycount($data) > self::$listmaxsize)  throw new Exception(get_string('error_vars_array_size', 'qtype_formulas'));
            }
            if (mycount($data) < 1)  throw new Exception(get_string('error_vars_array_size', 'qtype_formulas'));
            $splitted[$i] = $this->vstack_add_temporary_variable($vstack, 'ln', $data);
        }
        return implode('', $splitted);
    }

    // Return a string with all (positive) numbers substituted by placeholders. The information of placeholders is stored in v.
    private function substitute_numbers_by_placeholders(&$vstack, $text) {
        $numpattern = '/(^|[\]\[)(}{, ?:><=~!|&%^\/*+-])(([0-9]+\.?[0-9]*|[0-9]*\.?[0-9]+)([eE][-+]?[0-9]+)?)/';
        $splitted = explode('`', preg_replace($numpattern, '$1`$2`', $text));
        for ($i = 1; $i < mycount($splitted); $i += 2)      // The length will always be odd, and the numbers are stored in odd index.
            $splitted[$i] = $this->vstack_add_temporary_variable($vstack, 'n', $splitted[$i]);
        return implode('', $splitted);
    }

    // Return a string with all functions substituted by placeholders. The information of placeholders is stored in v.
    private function substitute_functions_by_placeholders(&$vstack, $text, $internal=false) {
        $funcpattern = '/([a-z][a-z0-9_]*)(\s*\()/';
        $funclists = $internal ? $this->func_all : $this->func_algebraic;
        $type = $internal ? 'F' : 'f';
        $splitted = explode('`', preg_replace($funcpattern, '`$1`$2', $text));
        for ($i = 1; $i < mycount($splitted); $i += 2) {    // The length will always be odd, and the variables are stored in odd index.
            if (!array_key_exists($splitted[$i], $funclists))  continue;
            $splitted[$i] = $this->vstack_add_temporary_variable($vstack, $type, $splitted[$i]);
        }
        return implode('', $splitted);
    }

    // Return a string with all variables substituted by placeholders. The information of placeholders is stored in v.
    private function substitute_constants_by_placeholders(&$vstack, $text, $preserve) {
        $varpattern = '/([A-Za-z][A-Za-z0-9_]*)/';
        $splitted = explode('`', preg_replace($varpattern, '`$1`', $text));
        for ($i = 1; $i < mycount($splitted); $i += 2) {    // The length will always be odd, and the variables are stored in odd index.
            if (!array_key_exists($splitted[$i], $this->constlist))  continue;
            $constnumber = $preserve ? $splitted[$i] : $this->constlist[$splitted[$i]];
            $splitted[$i] = $this->vstack_add_temporary_variable($vstack, 'n', $constnumber);
        }
        return implode('', $splitted);
    }

    // Return a string with all variables substituted by placeholders. The information of placeholders is stored in v.
    private function substitute_variables_by_placeholders(&$vstack, $text, $internal=false) {
        $varpattern = $internal ? '/([A-Za-z_][A-Za-z0-9_]*)/' : '/([A-Za-z][A-Za-z0-9_]*)/';
        $funclists = $internal ? $this->func_all : $this->func_algebraic;
        $splitted = explode('`', preg_replace($varpattern, '`$1`', $text));
        for ($i = 1; $i < mycount($splitted); $i += 2) {    // The length will always be odd, and the variables are stored in odd index.
            if (array_key_exists($splitted[$i], $funclists))  throw new Exception(get_string('error_vars_reserved', 'qtype_formulas', $splitted[$i]));
            $splitted[$i] = $this->vstack_add_temporary_variable($vstack, 'v', $splitted[$i]);
        }
        return implode('', $splitted);
    }

    // Parse the number or range in the format of start(:stop(:interval)). return null if error.
    private function parse_fixed_range(&$vstack, $expression) {
        $ex = explode(':', $expression);
        if (mycount($ex) > 3)  return null;
        $numpart = mycount($ex);
        for ($i = 0; $i < $numpart; $i++) {
            $ex[$i] = trim($ex[$i]);
            if (mycount($ex[$i]) == 0)  return null;
            $v = $ex[$i][0] == '-' ? trim(substr($ex[$i], 1)) : $ex[$i]; // Get the sign of the number.
            $num = $this->vstack_get_variable($vstack, $v);     // Num must be a constant number.
            if ($num === null || $num->type != 'n' || !is_string($num->value))  return null;
            $ex[$i] = strlen($ex[$i]) == strlen($v) ? floatval($num->value) : -floatval($num->value); // Multiply the sign back.
        }
        if (mycount($ex) == 1)  $ex = array($ex[0], $ex[0] + 0.5, 1.);
        if (mycount($ex) == 2)  $ex = array($ex[0], $ex[1], 1.);
        if ($ex[0] > $ex[1] || $ex[2] <= 0)  return null;
        return (object)array('numelement' => ceil( ($ex[1] - $ex[0]) / $ex[2] ), 'element' => $ex, 'numpart' => $numpart);
    }

    /**
     * There are two main forms of random variables, specified in the form 'variable = expression;'
     * The first form is declared as a set of either number, string, list of number and list of string.
     * One element will be drawn from the set when instantiating. Note that it allow a range format of numbers
     * Another one is the shuffling of a list of number or string.
     * e.g. A={1,2,3}; B={1, 3:5, 8:9:.1}; C={"A","B"}; D={[1,4],[1,9]}; F=shuffle([0:10]);
     */

    // Parse the random variables $assignments for later instantiation of a dataset. Throw on parsing error.
    public function parse_random_variables($text) {
        $vstack = $this->vstack_create();
        $text = $this->substitute_strings_by_placholders($vstack, $text);
        $text = $this->trim_comments($text);
        $text = $this->substitute_numbers_by_placeholders($vstack, $text);

        // Check whether variables or some reserved variables are used, throw on error.
        $tmpvars = clone $vstack;
        $tmptext = $text;
        $tmptext = $this->substitute_functions_by_placeholders($tmpvars, $tmptext, true);
        $tmptext = $this->substitute_variables_by_placeholders($tmpvars, $tmptext, true);

        $assignments = explode(';', $text);
        foreach ($assignments as $acounter => $assignment) {
            try {
                // Split into variable name and expression.
                $ex = explode('=', $assignment, 2);
                $name = trim($ex[0]);
                if (mycount($ex) == 1 && strlen($name) == 0) {
                    continue;   // If empty assignment.
                }
                if (mycount($ex) != 2) {
                    throw new Exception(get_string('error_syntax', 'qtype_formulas'));
                }
                if (!preg_match('/^[A-Za-z0-9_]+$/', $name)) {
                    throw new Exception(get_string('error_vars_name', 'qtype_formulas'));
                }
                $expression = trim($ex[1]);
                $expression = $this->substitute_fixed_ranges_by_placeholders($vstack, $expression);
                if (strlen($expression) == 0) {
                    throw new Exception(get_string('error_syntax', 'qtype_formulas'));
                }

                // Check whether the expression contains only the valid character set.
                $var = (object)array('numelement' => 0, 'elements' => array());
                if ($expression[0] == '{') {
                    $allowableoperatorchar = '-+*/:@0-9,\s}{\]\[';  // Restricted set, prevent too many calculations.
                    if (!preg_match('~^['.$allowableoperatorchar.']*$~', $expression))   // The result expression should only contains simple characters.
                        throw new Exception(get_string('error_forbid_char', 'qtype_formulas'));

                    $bracket = $this->get_expressions_in_bracket($expression, 0, '{');
                    if ($bracket === null) {
                        throw new Exception(get_string('error_vars_bracket_mismatch', 'qtype_formulas'));
                    }
                    if (!($bracket->openloc == 0 && $bracket->closeloc == strlen($expression) - 1)) {
                        throw new Exception(get_string('error_syntax', 'qtype_formulas'));
                    }

                    $type = null;
                    foreach ($bracket->expressions as $i => $ele) {
                        if ($i == 0 && strpos($ele, ':') !== false) {
                            $type = 'n';
                        }
                        if ($type != 'n') {
                            $result = $this->evaluate_general_expression_substituted_recursively($vstack, $ele);
                            if ($i == 0)  $type = $result->type;
                            if ($i > 0 && $result->type != $type)  throw new Exception(get_string('error_randvars_type', 'qtype_formulas'));
                            $element = $result->value;
                            $numelement = 1;
                        }
                        if ($type == 'n') { // Special handle for number, because it can be specified as a range.
                            $result = $this->parse_fixed_range($vstack, $ele);
                            if ($result === null)  throw new Exception(get_string('error_syntax', 'qtype_formulas'));
                            $element = $result->element;
                            $numelement = $result->numelement;
                        }
                        if ($i == 0) {
                            $listsize = $type[0] == 'l' ? mycount($element) : 1;
                        }
                        if ($i > 0) {
                            if (($type[0] == 'l' ? mycount($element) : 1) != $listsize) {
                                throw new Exception(get_string('error_randvars_type', 'qtype_formulas'));
                            }
                        }
                        $var->elements[] = $element;
                        $var->numelement += $numelement;
                    }
                    $type = 'z'.$type;
                } else if ( preg_match('~^shuffle\s*\(([-+*/@0-9,\s\[\]]+)\)$~', $expression, $matches) ) {
                    $result = $this->evaluate_general_expression_substituted_recursively($vstack, $matches[1]);
                    if ($result === null || $result->type[0] != 'l') {
                        throw new Exception(get_string('error_syntax', 'qtype_formulas'));
                    }
                    $type = 'zh'.$result->type;
                    $var->numelement = mycount($result->value);   // The actual number should be a!, but it will not be used anyway.
                    $var->elements = $result->value;
                } else {
                    throw new Exception(get_string('error_syntax', 'qtype_formulas'));
                }

                // There must be at least two elements to draw from, otherwise it is not a random variable.
                if ($var->numelement < 2) {
                    throw new Exception(get_string('error_randvars_set_size', 'qtype_formulas'));
                }
                $this->vstack_update_variable($vstack, $name, null, $type, $var);
            } catch (Exception $e) {    // Append the error message by the line info.
                throw new Exception(($acounter + 1).': '.$name.': '.$e->getMessage());
            }
        }
        return $this->vstack_clean_temporary($vstack);
    }

    // Instantiate a particular variables set given by datasetid (-1 for random). Another vstack of will be returned.
    public function instantiate_random_variables(&$vstack, $datasetid = -1) {
        $numdataset = $this->vstack_get_number_of_dataset($vstack);
        $datasetid = ($datasetid >= 0 && $datasetid < self::$maxdataset) ? $datasetid % $numdataset : -1;
        $newstack = $this->vstack_create(); // The instantiated result will be stored in another vstack.
        foreach ($vstack->all as $name => $data)  if ( $data->type[0] == 'z') {
            $v = &$data->value;
            if ( $data->type[1] == 'h') {
                $tmp = $v->elements;
                shuffle($tmp);
                $this->vstack_update_variable($newstack, $name, null, 'l'.$data->type[3], $tmp);
            } else {
                $id = ($datasetid >= 0) ? $datasetid % $v->numelement : mt_rand(0, $v->numelement - 1);
                $datasetid = ($datasetid >= 0) ? intval($datasetid / $v->numelement) : -1;
                if ( $data->type[1] == 'n' ) {  // If type is 'set_number', then pick up the correct element using following algorithm.
                    foreach ($v->elements as $elem) {
                        $sz = ceil( ($elem[1] - $elem[0]) / $elem[2] );
                        if ( $id < $sz) {
                            $this->vstack_update_variable($newstack, $name, null, 'n', $elem[0] + $id * $elem[2]);
                            break;
                        }
                        $id -= $sz;
                    }
                } else {
                    // Directly pick one element for type s,ln,ls.
                    $this->vstack_update_variable($newstack, $name, null, substr($data->type, 1), $v->elements[$id]);
                }
            }
        }
        return $newstack;
    }

    // This function can evaluate mathematical formula, manipulate lists of number and concatenate strings
    // The $vars contains variables evaluated previously and it will return the evaluated variables in $text.
    public function evaluate_assignments($vars, $text) {
        $vstack = clone $vars;
        $text = $this->substitute_strings_by_placholders($vstack, $text);
        $text = $this->trim_comments($text);
        $text = $this->substitute_numbers_by_placeholders($vstack, $text);
        $text = $this->substitute_fixed_ranges_by_placeholders($vstack, $text);
        $text = $this->substitute_functions_by_placeholders($vstack, $text, true);
        $text = $this->substitute_variables_by_placeholders($vstack, $text, true);
        $acounter = 0;
        try {
            $this->evaluate_assignments_substituted($vstack, $text, $acounter);
        } catch (Exception $e) {
            throw new Exception($acounter.': '.$e->getMessage());
        }
        return $this->vstack_clean_temporary($vstack);
    }

    // Return the evaluated general expression by calling evaluate_assignments().
    public function evaluate_general_expression($vars, $expression) {
        $vstack = clone $vars;
        $expression = $this->substitute_strings_by_placholders($vstack, $expression);
        $expression = $this->substitute_numbers_by_placeholders($vstack, $expression);
        $expression = $this->substitute_fixed_ranges_by_placeholders($vstack, $expression);
        $expression = $this->substitute_functions_by_placeholders($vstack, $expression, true);
        $expression = $this->substitute_variables_by_placeholders($vstack, $expression, true);
        $allowableoperatorchar = '-+/*%>:^\~<?=&|!,0-9\s)(\]\[' . '@';
        if (!preg_match('~^['.$allowableoperatorchar.']*$~', $expression))   // The result expression should only contains simple characters.
            throw new Exception(get_string('error_forbid_char', 'qtype_formulas'));
        $expression = $this->substitute_vname_by_variables($vstack, $expression);
        return $this->evaluate_general_expression_substituted_recursively($vstack, $expression);
    }

    // Parse and evaluate the substituted assignments one by one.
    private function evaluate_assignments_substituted(&$vstack, $subtext, &$acounter) {
        $cursor = 0;
        while ($cursor < strlen($subtext)) {
            $acounter++; // if ($acounter > 20000)  break; // Prevent infinite loop.

            $first = $this->get_next_variable($vstack, $subtext, $cursor);
            if ($first !== null && $first->var->type == 'v' && $first->var->value == 'for') {   // Handle the for loop.
                // Get the for loop header: the variable name and the expression.
                $header = $this->get_expressions_in_bracket($subtext, $first->endloc, '(');
                if ($header === null)  throw new Exception('Unknown error: for loop');
                $h = explode(':', implode('', $header->expressions), 2);
                if (mycount($h) == 1)  throw new Exception(get_string('error_forloop', 'qtype_formulas'));
                $loopvar = $this->vstack_get_variable($vstack, trim($h[0]));
                if ($loopvar === null || $loopvar->type != 'v' || $loopvar->value[0] == '_')  throw new Exception(get_string('error_forloop_var', 'qtype_formulas'));
                $expression = $this->substitute_vname_by_variables($vstack, $h[1]);
                $list = $this->evaluate_general_expression_substituted_recursively($vstack, $expression);
                if ($list->type[0] != 'l')  throw new Exception(get_string('error_forloop_expression', 'qtype_formulas'));

                // Get the assignments in the inner for loop.
                $isopen = strpos($subtext, '{', $header->closeloc);
                if ($isopen !== false)  // There must have no other text between the for loop and open bracket '{'.
                    $isopen = strlen(trim(substr($subtext, $header->closeloc + 1, max(0, $isopen - $header->closeloc - 2)))) == 0;
                if ($isopen === true) {
                    $bracket = $this->get_expressions_in_bracket($subtext, $header->closeloc, '{');
                    $innertext = implode('', $bracket->expressions);
                    $cursor = $bracket->closeloc + 1;
                } else {
                    $nextcursor = strpos($subtext, ';', $header->closeloc);
                    if ($nextcursor === false)  $nextcursor = strlen($subtext);    // If no end separator, use all text until the end.
                    $innertext = substr($subtext, $header->closeloc + 1, $nextcursor - $header->closeloc - 1);
                    $cursor = $nextcursor + 1;
                }

                // Loop over the assignments using loop counter one by one.
                $curacounter = $acounter + 1;
                foreach ($list->value as $e) {    // Call this function for the inner loop recursively.
                    $acounter = $curacounter;
                    $this->vstack_update_variable($vstack, $loopvar->value, null, $list->type[1], $e);
                    $this->evaluate_assignments_substituted($vstack, $innertext, $acounter);
                }
            } else {
                // Find the next assignment and then advance the cursor after the ';'.
                $nextcursor = strpos($subtext, ';', $cursor);
                if ($nextcursor === false)  $nextcursor = strlen($subtext);    // If no end separator, use all text until the end.
                $assignment = substr($subtext, $cursor, $nextcursor - $cursor);
                $cursor = $nextcursor + 1;

                // Check whether the assignment contains only the valid character set.
                $allowableoperatorchar = '-+/*%>:^\~<?=&|!,0-9\s)(}{\]\[' . '@';
                if (!preg_match('~^['.$allowableoperatorchar.']*$~', $assignment))   // The result expression should contains simple characters only.
                    throw new Exception(get_string('error_forbid_char', 'qtype_formulas'));

                // Split into variable name and expression.
                $ex = explode('=', $assignment, 2);
                $name = trim($ex[0]);
                if (mycount($ex) == 1 && strlen($name) == 0) {
                    continue;   // If empty assignment.
                }
                if (mycount($ex) != 2)
                    throw new Exception(get_string('error_syntax', 'qtype_formulas'));
                $expression = trim($ex[1]);
                // Check variable name format.
                $nameindex = $this->get_variable_name_index($vstack, $name);
                if ($nameindex === null)  throw new Exception(get_string('error_vars_name', 'qtype_formulas'));
                // Check whether all variables name are defined before and then replacing them by the value.
                $expression = $this->substitute_vname_by_variables($vstack, $expression);

                // Check for algebraic variable, it must be a simple assignment.
                $result = $this->parse_algebraic_variable($vstack, $expression);
                if ($result === null)   // If it is not an algebraic variable, try to evaluate it.
                    $result = $this->evaluate_general_expression_substituted_recursively($vstack, $expression);
                // Put the evaluated result into the variable name.
                $this->vstack_update_variable($vstack, $nameindex[0], $nameindex[1], $result->type, $result->value);
                // var_dump($name, $result->type, $result->value,'----------------------', $vstack)
            }
        }
    }

    // Evaluate expression with list operation, special function and numerical expression.
    private function evaluate_general_expression_substituted_recursively(&$vstack, $expression) {
        $expression = trim($expression);
        if (strlen($expression) == 0)     // Check whether expression is empty.
            throw new Exception(get_string('error_subexpression_empty', 'qtype_formulas'));
        $curtop = $this->vstack_mark_current_top($vstack);
        while (true) {
            $result = $this->vstack_get_variable($vstack, $expression);
            if ($result != null)  break;
            // Note that the square bracket and additional function needed to be handle recursively.
            $match = $this->handle_special_functions($vstack, $expression);
            if ($match)  continue;
            $match = $this->handle_square_bracket_syntax($vstack, $expression);
            if ($match)  continue;
            // Assume the expression is purely numerical and then evaluate.
            $nums = $this->evaluate_numerical_expression(array($vstack), $expression);
            $result = (object)array('type' => 'n', 'value' => $nums[0]);
            break;
        }
        $this->vstack_restore_previous_top($vstack, $curtop);
        return $result;
    }

    // Return the name and index (if any) on the left hand side of assignment. if error, return null.
    private function get_variable_name_index(&$vstack, $name) {
        if (!preg_match('/^(@[0-9]+)(\[(@[0-9]+)\])?$/', $name, $matches))  return null;
        $n = $this->vstack_get_variable($vstack, $matches[1]);
        if ($n->type != 'v' || $n->value[0] == '_')  return null;   // It must be a variable name and not prefixed by "_".
        if (!isset($matches[3]))
            return array($n->value, null);
        $idx = $this->vstack_get_variable($vstack, $matches[3]);
        if ($idx->type == 'v')  // If it is a variable, get its value.
            $idx = $this->vstack_get_variable($vstack, $idx->value);
        if ($idx->type == 'n')
            return array($n->value, $idx->value);
        else
            return null;
    }

    // Parse the algebraic variable, which is the same as the set of number for random variable.
    public function parse_algebraic_variable(&$vstack, $expression) {
        $expression = trim($expression);
        if (strlen($expression) == 0)  return null;
        if ($expression[0] != '{')  return null;
        $bracket = $this->get_expressions_in_bracket($expression, 0, '{');
        if ($bracket === null)  throw new Exception('Unknown error: parse_algebraic_variable()');
        if ($bracket->closeloc != strlen($expression) - 1)  throw new Exception(get_string('error_algebraic_var', 'qtype_formulas'));
        $numelement = 0;
        $elements = array();
        foreach ($bracket->expressions as $e) {
            $res = $this->parse_fixed_range($vstack, $e);
            if ($res === null)  throw new Exception(get_string('error_algebraic_var', 'qtype_formulas'));
            $numelement += $res->numelement;
            $elements[] = $res->element;
        }
        return (object)array('type' => 'zn', 'value' => (object)array('numelement' => $numelement, 'elements' => $elements));
    }

    // Handle the array by replacing it by variable, if necessary, evaluate subexpression by putting it in the $vstack.
    // @return boolean of whether this syntax is found or not.
    private function handle_square_bracket_syntax(&$vstack, &$expression) {
        $res = $this->get_expressions_in_bracket($expression, 0, '[');
        if ($res == null)  return false;
        if (mycount($res->expressions) < 1 || mycount($res->expressions) > self::$listmaxsize)
            throw new Exception(get_string('error_vars_array_size', 'qtype_formulas'));
        $list = array();
        foreach ($res->expressions as $e)
            $list[] = $this->evaluate_general_expression_substituted_recursively($vstack, $e);
        $data = $this->get_previous_variable($vstack, $expression, $res->openloc);
        if ($data !== null) {   // If the square bracket has a variable before it.
            if ($data->var->type != 'ln' && $data->var->type != 'ls')
                throw new Exception(get_string('error_vars_array_unsubscriptable', 'qtype_formulas'));
            if ($list[0]->type != 'n' || mycount($list) > 1)
                throw new Exception(get_string('error_vars_array_index_nonnumeric', 'qtype_formulas'));
            if ($list[0]->value < 0 || $list[0]->value >= mycount($data->var->value))
                throw new Exception(get_string('error_vars_array_index_out_of_range', 'qtype_formulas'));
            $this->replace_middle($vstack, $expression, $data->startloc, $res->closeloc + 1, $data->var->type[1], $data->var->value[$list[0]->value]);
            return true;
        }
        // Check the elements in the list is of the same type and then construct a new list.
        $elementtype = $list[0]->type;
        for ($i = 0; $i < mycount($list); $i++)  $list[$i] = $list[$i]->value;
        $this->replace_middle($vstack, $expression, $res->openloc, $res->closeloc + 1, $elementtype == 'n' ? 'ln' : 'ls', $list);
        return true;
    }

    // Handle the few function for the array of number or string
    // @return boolean of whether this syntax is found or not.
    private function handle_special_functions(&$vstack, &$expression) {
        $splitted = explode('`', preg_replace('/(@[0-9]+)/', '`$1`', $expression));
        $loc = 0;
        for ($i = 1; $i < mycount($splitted); $i += 2) {
            $data = $this->vstack_get_variable($vstack, $splitted[$i]);
            if ($data->type == 'F' && array_key_exists($data->value, $this->func_special)) {
                for ($j = 0; $j <= $i; $j++)  $loc += strlen($splitted[$j]);
                break;
            }
        }
        if ($loc === 0)  return false;
        $l = $loc - strlen($splitted[$i]);

        $bracket = $this->get_expressions_in_bracket($expression, $loc, '(');
        if ($bracket == null)  return false;
        $r = $bracket->closeloc + 1;
        $types = array();
        $values = array();
        foreach ($bracket->expressions as $e) {
            $tmp = $this->evaluate_general_expression_substituted_recursively($vstack, $e);
            $types[] = $tmp->type;
            $values[] = $tmp->value;
        }
        $sz = mycount($types);
        $typestr = implode(',', $types);

        switch ($data->value) {
            case 'fill':
                if (!($sz == 2 && ($typestr == 'n,n' || $typestr == 'n,s') && is_string($values[0])))  break;
                $N = intval($values[0]);  // Note that if $values[0]===string means that it is constant number.
                if ($N < 1 || $N > self::$listmaxsize)  throw new Exception(get_string('error_vars_array_size', 'qtype_formulas'));
                $this->replace_middle($vstack, $expression, $l, $r, 'l'.$types[1], array_fill(0, $N, $values[1]));
                return true;
            case 'len':
                if (!($sz == 1 && $typestr[0] == 'l'))  break;  // Note: type 'n' with strval is treated as constant.
                $this->replace_middle($vstack, $expression, $l, $r, 'n', strval(mycount($values[0])));
                return true;
            case 'pick':
                if (!($sz >= 2 && $types[0] == 'n'))  break;
                if ($sz == 2) {
                    if ($types[1][0] != 'l')  break;
                    $type = $types[1][1];
                    $pool = $values[1];
                } else {
                    $type = $types[1];
                    $pool = array($values[1]);
                    $allsametype = true;
                    for ($i = 2; $i < $sz; $i++) {
                        $allsametype = $allsametype && ($types[$i] == $type);
                        $pool[] = $values[$i];
                    }
                    if (!$allsametype)  break;
                }
                $v = intval($values[0] >= 0 && $values[0] < $sz ? $values[0] : 0);    // Always choose 0 if index out of range.
                $this->replace_middle($vstack, $expression, $l, $r, $type, $pool[$v]);
                return true;
            case 'sort':
                if (!($sz >= 1 && $sz <= 2 && $types[0][0] == 'l'))  break;
                if ($sz == 2)  if ($types[1][0] != 'l')  break;
                if ($sz == 1)  $values[1] = $values[0];
                if (mycount($values[0]) != mycount($values[1]))  break;
                $tmp = array_combine($values[1], $values[0]);
                ksort($tmp);
                $this->replace_middle($vstack, $expression, $l, $r, $types[0], array_values($tmp));
                return true;
            case 'sublist':
                if (!($sz == 2 && ($typestr == 'ln,ln' || $typestr == 'ls,ln')))  break;
                $sub = array();
                foreach ($values[1] as $idx) {
                    $idx = intval($idx);
                    if ($idx >= 0 && $idx < mycount($values[0]))  $sub[] = $values[0][$idx];
                    else throw new Exception(get_string('error_vars_array_index_out_of_range', 'qtype_formulas'));
                }
                $this->replace_middle($vstack, $expression, $l, $r, $types[0], $sub);
                return true;
            case 'inv':
                if (!($sz == 1 && $typestr == 'ln'))  break;
                $sub = $values[0];
                foreach ($values[0] as $i => $idx) {
                    $idx = intval($idx);
                    if ($idx >= 0 && $idx < mycount($values[0]))  $sub[$idx] = $i;
                    else throw new Exception(get_string('error_vars_array_index_out_of_range', 'qtype_formulas'));
                }
                $this->replace_middle($vstack, $expression, $l, $r, 'ln', $sub);
                return true;
            case 'map':
                if (!($sz >= 2 && $sz <= 3 && $types[0] == 's'))  break;
                if ($sz == 2) {   // Two parameters, unary operator.
                    if (!($typestr == 's,ln'))  break;
                    if (!array_key_exists($values[0], $this->func_unary))  break;
                    // The create_function function is deprecated since php 7.2.
                    // $value = array_map(create_function('$a', 'return floatval('.$values[0].'($a));'), $values[1]);
                    $value = array_map(function ($a) use ($values){return floatval($values[0]($a));}, $values[1]);
                } else {
                    if (!($typestr == 's,ln,n' || $typestr == 's,n,ln' || $typestr == 's,ln,ln'))  break;
                    if ($types[1] != 'ln')  $values[1] = array_fill(0, mycount($values[2]), $values[1]);
                    if ($types[2] != 'ln')  $values[2] = array_fill(0, mycount($values[1]), $values[2]);
                    if (array_key_exists($values[0], $this->binary_op_map))
                        // The create_function function is deprecated since php 7.2.
                        // $value = array_map(create_function('$a,$b', 'return floatval(($a)'.$values[0].'($b));'), $values[1], $values[2]);
                        $value = array_map(function ($a, $b) use ($values){return eval( 'return floatval(($a)'.$values[0].'($b));');}, $values[1], $values[2]);
                    else if (array_key_exists($values[0], $this->func_binary)) {
                        // The create_function function is deprecated since php 7.2.
                        // $value = array_map(create_function('$a,$b', 'return floatval('.$values[0].'($a,$b));'), $values[1], $values[2]);
                        $value = array_map(function ($a, $b) use ($values){return floatval($values[0]($a, $b));}, $values[1], $values[2]);
                    } else {
                        break;
                    }
                }
                $this->replace_middle($vstack, $expression, $l, $r, 'ln', $value);
                return true;
            case 'sum':
                if (!($sz == 1 && $typestr == 'ln')) {
                    break;
                }
                $sum = 0;
                foreach ($values[0] as $v) {
                    $sum += floatval($v);
                }
                $this->replace_middle($vstack, $expression, $l, $r, 'n', $sum);
                return true;
            case 'poly':
                // The poly function was contributed by PeTeL-Weizmann.
                if ($sz == 1 && $typestr == 'ln') {
                    $varName = 'x';
                    $vals = $values[0];
                } else if ($sz == 2 && $typestr == 's,ln') {
                    $varName = $values[0];
                    $vals = $values[1];
                } else {
                    break;
                }

                $pow = mycount($vals);
                $pp = '';
                foreach ($vals as $v) {
                    $pow--;
                    if ($v == 0) {
                        continue;
                    }
                    $ss = ($pp != '' && $v > 0) ? '+' : '';
                    if ($pow == 0) {
                        $pp .= "{$ss}{$v}";
                    } else {
                        if ($v == 1) {
                            $coff = "{$ss}";
                        } else if ($v == -1) {
                            $coff = "-";
                        } else {
                            $coff = "{$ss}{$v}";
                        }

                        if ($pow == 1) {
                            $pp .= "{$coff}{$varName}";
                        } else {
                            $pp .= "{$coff}{$varName}^{{$pow}}";
                        }
                    }
                }
                if ($pp == '') {
                    $pp = '0';
                }
                $this->replace_middle($vstack, $expression, $l, $r, 's', $pp);
                return true;
            case 'concat':
                if (!($sz >= 2 && ($types[0][0] == 'l'))) {
                    break;
                }
                $result = array();
                $haserror = false;
                foreach ($types as $i => $type) {
                    if ($type != $types[0]) {
                        $haserror = true;
                        break;
                    }
                    foreach ($values[$i] as $v) {
                        $result[] = $v;
                    }
                }
                if ($haserror)  break;
                $this->replace_middle($vstack, $expression, $l, $r, $types[0], $result);
                return true;
            case 'join':
                if (!($sz >= 2 && $types[0] == 's'))  break;
                $data = array();
                for ($i = 1; $i < $sz; $i++)
                    $data[] = $types[$i][0] == 'l' ? implode($values[0], $values[$i]) : $values[$i];
                $value = join($values[0], $data);
                $this->replace_middle($vstack, $expression, $l, $r, 's', $value);
                return true;
            case 'str':
                if (!($sz == 1 && $typestr == 'n'))  break;
                $this->replace_middle($vstack, $expression, $l, $r, 's', strval($values[0]));
                return true;
            case 'diff':
                if (!($typestr == 'ls,ls,n' || $typestr == 'ls,ls' || $typestr == 'ln,ln'))  break;
                if (mycount($values[0]) != mycount($values[1]))  break;
                if ($typestr == 'ln,ln')
                    $diff = $this->compute_numerical_formula_difference($values[0], $values[1], 1.0, 0);
                else
                    $diff = $this->compute_algebraic_formula_difference($vstack, $values[0], $values[1], $typestr == 'ls,ls' ? 100 : $values[2]);
                $this->replace_middle($vstack, $expression, $l, $r, 'ln', $diff);
                return true;
            default:
                return false;   // If no match, then the expression will be evaluated as a mathematical expression.
        }
        throw new Exception(get_string('error_func_param', 'qtype_formulas', $data->value));
    }

    /**
     * Evaluate the $expression with all variables given in the $vstacks. May throw error
     *
     * @param array $vstacks array of vstack data structure. Each vstack will be used one by one
     * @param string $expression The expression being evaluated
     * @param string $functype the function type, either 'F' for internal use, or 'f' for external use
     * @return The evaluated array of number, each number corresponds to one vstack
     */
    private function evaluate_numerical_expression($vstacks, $expression, $functype='F') {
        $splitted = explode('`', preg_replace('/(@[0-9]+)/', '`$1`', $expression));
        // Check and convert the vstacks into an array of array of numbers.
        $all = array_fill(0, mycount($vstacks), array());
        for ($i = 1; $i < mycount($splitted); $i += 2) {
            // $data = $this->vstack_get_variable($vstacks[0], $splitted[$i]);
            $data = $vstacks[0]->all[$splitted[$i]];    // For optimization, bypassing function call.
            if ($data === null || ($data->type != 'n' && $data->type != $functype)) {
                throw new Exception(get_string('error_eval_numerical', 'qtype_formulas'));
            }
            if ($data->type == $functype) {    // If it is a function, put it back into the expression.
                $splitted[$i] = $data->value;
            }
            if ($data->type == 'n') {   // If it is a number, store in $a for later evaluation.
                $all[0][$i] = floatval($data->value);
                for ($j = 1; $j < mycount($vstacks); $j++) {  // If it need to evaluate the same expression with different values.
                    // $tmp = $this->vstack_get_variable($vstacks[$j], $splitted[$i]);
                    $tmp = $vstacks[$j]->all[$splitted[$i]];    // For optimization, bypassing function call.
                    if ($tmp === null || $tmp->type != 'n') {
                        throw new Exception('Unexpected error! evaluate_numerical_expression(): Variables in all $vstack must be of the same type');
                    }
                    $all[$j][$i] = floatval($tmp->value);
                }
                $splitted[$i] = '$a['.$i.']';
            }
        }

        // Check for possible formula error for the substituted string, before directly calling eval().
        $replaced = $splitted;
        for ($i = 1; $i < mycount($replaced); $i += 2)  if ($replaced[$i][0] == '$')  $replaced[$i] = 1;  // Substitute a dummy value for testing.
        $res = $this->find_formula_errors(implode(' ', $replaced));
        if ($res)  throw new Exception($res);   // Forward the error.
        // Now, it should contains pure code of mathematical expression and all numerical variables are stored in $a.
        $results = array();
        foreach ($all as $a) {
            $res = null;
            // In PHP 7 eval() terminates the script if the evaluated code generate a fatal error.
            try {
                eval('$res = ' . implode(' ', $splitted) . ';');
            } catch (Throwable $t) {
                throw new Exception(get_string('error_eval_numerical', 'qtype_formulas'));
            }
            if (!isset($res))  throw new Exception(get_string('error_eval_numerical', 'qtype_formulas'));
            $results[] = floatval($res);    // Make sure it is a number, not other data type such as bool.
        }

        return $results;
    }

    // Return the list of expression inside the matching open and close bracket, otherwise null.
    // Changed to public so it can be tested from phpunit.
    public function get_expressions_in_bracket($text, $start, $open, $bset=array('(' => ')', '[' => ']', '{' => '}')) {
        $bflip = array_flip($bset);
        $ostack = array();  // Stack of open bracket.
        for ($i = $start; $i < strlen($text); $i++) {
            if ($text[$i] == $open) {
                $ostack[] = $open;
            }
            if (mycount($ostack) > 0) {
                break;     // When the first open bracket is found.
            }
        }
        if (mycount($ostack) == 0) {
            return null;
        }
        $firstopenloc = $i;
        $expressions = array();
        $ploc = $i + 1;
        for ($i = $i + 1; $i < strlen($text); $i++) {
            if (array_key_exists($text[$i], $bset))  $ostack[] = $text[$i];
            if ($text[$i] == ',' && mycount($ostack) == 1) {
                $expressions[] = substr($text, $ploc, $i - $ploc);
                $ploc = $i + 1;
            }
            if (array_key_exists($text[$i], $bflip))  if (array_pop($ostack) != $bflip[$text[$i]])  break;
            if (mycount($ostack) == 0) {
                $expressions[] = substr($text, $ploc, $i - $ploc);
                return (object)array('openloc' => $firstopenloc, 'closeloc' => $i, 'expressions' => $expressions);
            }
        }
        throw new Exception(get_string('error_vars_bracket_mismatch', 'qtype_formulas'));
    }

    // Get the variable immediately before the location $loc.
    private function get_previous_variable(&$vstack, $text, $loc) {
        if (!preg_match('/((@[0-9]+)\s*)$/', substr($text, 0, $loc), $m))  return null;
        $var = $this->vstack_get_variable($vstack, $m[2]);
        if ($var === null)  return null;
        return (object)array('startloc' => $loc - strlen($m[1]), 'var' => $var);
    }

    // Get the variable immediately at and after the location $loc (inclusive).
    private function get_next_variable(&$vstack, $text, $loc) {
        if (!preg_match('/^(\s*(@[0-9]+))/', substr($text, $loc), $m))  return null;
        $var = $this->vstack_get_variable($vstack, $m[2]);
        if ($var === null)  return null;
        return (object)array('startloc' => $loc + (strlen($m[1]) - strlen($m[2])), 'endloc' => $loc + strlen($m[1]), 'var' => $var);
    }

    // Replace the expression[left..right] by the variable with $value.
    private function replace_middle(&$vstack, &$expression, $left, $right, $type, $value) {
        $name = $this->vstack_add_temporary_variable($vstack, $type, $value);
        $expression = substr($expression, 0, max(0, $left)) . $name . substr($expression, $right);
    }

    // Remove the user comments, that is the string between # and the end of line.
    private function trim_comments($text) {
        return preg_replace('/'.chr(35).'.*$/m', "\n", $text);
    }

    // Return the information of the formula by substituting numbers, variables and functions.
    public function get_formula_information($vars, $text) {
        if (!preg_match('/^[A-Za-z0-9._ )(^\/*+-]*$/', $text))  return null;   // Formula can only contains these characters.
        $vstack = clone $vars;
        $sub = $text;
        $sub = $this->substitute_numbers_by_placeholders($vstack, $sub);
        $sub = $this->substitute_functions_by_placeholders($vstack, $sub);
        $sub = $this->substitute_constants_by_placeholders($vstack, $sub, false);
        $sub = $this->substitute_variables_by_placeholders($vstack, $sub);
        $vstack->lengths = array_fill_keys(explode(',', 'n,v,F,f,s,ln,ls,zn'), 0);
        foreach ($vstack->all as $data)  $vstack->lengths[$data->type]++;
        $vstack->original = $text;
        $vstack->sub = $sub;
        $vstack->remaining = preg_replace('/@[0-9]+/', '', $sub);
        return $vstack;
    }

    // Split the input into number/numeric/numerical formula and unit.
    public function split_formula_unit($text) {
        if (preg_match('/[`@]/', $text))  return array('', $text);   // Note: these symbols is reserved to split str.
        $vstack = $this->vstack_create();
        $sub = $text;
        $sub = $this->substitute_numbers_by_placeholders($vstack, $sub);
        $sub = $this->substitute_functions_by_placeholders($vstack, $sub);
        $sub = $this->substitute_constants_by_placeholders($vstack, $sub, true);
        // Split at the point that does not contain characters @ 0-9 + - * / ^ ( ) space.
        $spl = explode('`', preg_replace('/([^@0-9 )(^\/*+-])(.*)$/', '`$1$2', $sub));
        $num = $this->substitute_placeholders_in_text($vstack, $spl[0]);
        $unit = (!isset($spl[1])) ? '' : $this->substitute_placeholders_in_text($vstack, $spl[1]);
        return array($num, $unit);  // Don't trim them, otherwise the recombination may differ by a space.
    }

    // Translate the input formula $text into the corresponding evaluable mathematical formula in php.
    public function replace_evaluation_formula(&$vstack, $text) {
        $text = $this->insert_multiplication_for_juxtaposition($vstack, $text);
        $text = $this->replace_caret_by_power($vstack, $text);
        $text = preg_replace('/\s*([)(\/*+-])\s*/', '$1', $text);
        return $text;
    }

    // Replace the user input function in the vstack by another function.
    public function replace_vstack_variables($vstack, $replacementlist) {
        $res = clone $vstack;   // The $vstack->all will be used so it needs to clone deeply.
        foreach ($res->all as $name => $v)  if (is_string($v->value))
            $res->all[$name] = (object)array('type' => $v->type, 'value' =>
                array_key_exists($v->value, $replacementlist) ? $replacementlist[$v->value] : $v->value);
        return $res;
    }

    // Insert the multiplication symbol whenever juxtaposition occurs.
    public function insert_multiplication_for_juxtaposition($vstack, $text) {
        $splitted = explode('`', preg_replace('/(@[0-9]+)/', '`$1`', $text));
        for ($i = 3; $i < mycount($splitted); $i += 2) {    // The length will always be odd: placeholder in odd index, operators in even index.
            $op = trim($splitted[$i - 1]);    // The operator(s) between this and the previous variable.
            if ($this->vstack_get_variable($vstack, $splitted[$i - 2])->type == 'f')  continue;   // No need to add '*' if the left is function.
            if (strlen($op) == 0)  $op = ' * ';    // Add multiplication if no operator.
            else if ($op[0] == '(')  $op = ' * '.$op;
            else if ($op[strlen($op) - 1] == ')')  $op = $op.' * ';
            else $op = preg_replace('/^(\))(\s*)(\()/', '$1 * $3', $op);
            $splitted[$i - 1] = $op;
        }
        return implode('', $splitted);
    }

    // Replace the expression x^y by pow(x, y).
    public function replace_caret_by_power($vstack, $text) {
        while (true) {
            $loc = strrpos($text, '^');    // From right to left.
            if ($loc === false)  break;

            // Search for the expression of the exponent.
            $rloc = $loc;
            if ($rloc + 1 < strlen($text) && $text[$rloc + 1] == '-')  $rloc += 1;
            $r = $this->get_next_variable($vstack, $text, $rloc + 1);
            if ($r != null)  $rloc = $r->endloc - 1;
            if ($r == null || ($r != null && $r->var->type == 'f')) {
                $rtmp = $this->get_expressions_in_bracket($text, $rloc + 1, '(', array('(' => ')'));
                if ($rtmp == null || $rtmp->openloc != $rloc + 1)  throw new Exception('Expression expected');
                $rloc = $rtmp->closeloc;
            }

            // Search for the expression of the base.
            $lloc = $loc;
            $l = $this->get_previous_variable($vstack, $text, $loc);
            if ($l != null)
                $lloc = $l->startloc;
            else {
                $reverse = strrev($text);
                $ltmp = $this->get_expressions_in_bracket($reverse, strlen($text) - 1 - $loc + 1, ')', array(')' => '('));
                if ($ltmp == null || $ltmp->openloc != strlen($text) - 1 - $loc + 1)  throw new Exception('Expression expected');
                $lfunc = $this->get_previous_variable($vstack, $text, strlen($text) - 1 - $ltmp->closeloc);
                $lloc = ($lfunc == null || $lfunc->var->type != 'f') ? strlen($text) - 1 - $ltmp->closeloc : $lfunc->startloc;
            }

            // Replace the exponent notation by the pow function.
            $name = $this->vstack_add_temporary_variable($vstack, 'f', 'pow');
            $text = substr($text, 0, $lloc) . $name . '(' . substr($text, $lloc, $loc - $lloc) . ', '
                . substr($text, $loc + 1, $rloc - $loc) . ')' . substr($text, $rloc + 1);
        }
        return $text;
    }

    // Return the float value of number, numeric, or numerical formula, null when format incorrect.
    public function compute_numerical_formula_value($str, $gradingtype) {
        $info = $this->get_formula_information($this->vstack_create(), $str);
        if ($info === null)  return null;   // If the students' formula contains any disallowed characters.
        try {
            if ($gradingtype == 100) {        // For numerical formula format.
                if (preg_match('/^[ )(^\/*+-]*$/', $info->remaining) == false)  return null;
                if (!($info->lengths['v'] == 0))  return null;
                $info = $this->replace_vstack_variables($info, $this->evalreplacelist);
                $tmp = $this->replace_evaluation_formula($info, $info->sub);
                $nums = $this->evaluate_numerical_expression(array($info), $tmp, 'f');
                return $nums[0];
            } else if ($gradingtype == 10) {  // For numeric format.
                if (preg_match('/^[ )(^\/*+-]*$/', $info->remaining) == false)  return null;
                if (!($info->lengths['v'] == 0 && $info->lengths['f'] == 0))  return null;
                $info = $this->replace_vstack_variables($info, $this->evalreplacelist);
                $tmp = $this->replace_evaluation_formula($info, $info->sub);
                $nums = $this->evaluate_numerical_expression(array($info), $tmp, 'f');
                return $nums[0];
            } else {  // When $gradingtype != {10, 100, 1000}, for unknown type, all are treated as number.
                if (preg_match('/^[-+]?@0$/', $info->sub) == false)  return null;
                if (!($info->lengths['v'] == 0 && $info->lengths['f'] == 0 && $info->lengths['n'] == 1))  return null;
                return floatval($str);
            }
        } catch (Exception $e) {
            return null; // Any error means that the $str cannot be evaluated to a number.
        }
    }

    // Find the numerical value of students response $B and compute the difference between the modelanswer and students response.
    public function compute_numerical_formula_difference(&$A, &$B, $cfactor, $gradingtype) {
        $diffs = array();
        for ($i = 0; $i < mycount($B); $i++) {
            $value = $this->compute_numerical_formula_value($B[$i], $gradingtype);
            if ($value === null)  return null;  // If the coordinate cannot convert to a number.
            $B[$i] = $value * $cfactor;         // Rescale students' response to match unit of model answer.
            $diffs[$i] = abs($A[$i] - $B[$i]);  // Calculate the difference between students' response and model answer.
            if (is_nan($A[$i]))  $A[$i] = INF;
            if (is_nan($B[$i]))  $B[$i] = INF;
            if (is_nan($diffs[$i]))  $diffs[$i] = INF;
        }
        return $diffs;
    }

    // Compute the average L1-norm between $A and $B, evaluated at $N random points given by the random variables in $vars.
    public function compute_algebraic_formula_difference(&$vars, $A, $B, $N=100) {
        if ($N < 1)  $N = 100;
        $diffs = array();
        for ($idx = 0; $idx < mycount($A); $idx++) {
            if (!is_string($A[$idx]) || !is_string($B[$idx])) {
                return null;
            }
            $A[$idx] = trim($A[$idx]);
            $B[$idx] = trim($B[$idx]);
            if (strlen($A[$idx]) == 0 || strlen($B[$idx]) == 0) {
                return null;
            }
            $AsubB = 'abs('.$A[$idx].'-('.$B[$idx].'))';
            $info = $this->get_formula_information($vars, $AsubB);
            if ($info === null) {
                return null;
            }
            if (preg_match('/^[ )(^\/*+-]*$/', $info->remaining) == false) {
                return null;
            }
            $info = $this->replace_vstack_variables($info, $this->evalreplacelist);
            $d = $this->replace_evaluation_formula($info, $info->sub);
            $d = $this->substitute_vname_by_variables($info, $d);

            // Create a vstack contains purely the variables that appears in the formula.
            $splitted = explode('`', preg_replace('/(@[0-9]+)/', '`$1`', $d));
            $vstack = $this->vstack_create();
            for ($i = 1; $i < mycount($splitted); $i += 2) {
                $data = $this->vstack_get_variable($info, $splitted[$i]);
                if ($data === null || ($data->type != 'f' && $data->type != 'n' && $data->type != 'zn'))  return null;
                if ($data->type == 'f')     // If it is a function, put it back into the expression.
                    $splitted[$i] = $data->value;
                if ($data->type == 'n' || $data->type == 'zn')
                    $this->vstack_update_variable($vstack, $splitted[$i], null, $data->type, $data->value);  // Don't add other temp variable!
            }
            $newexpr = trim(implode('', $splitted));

            // Create the vstack for different realization of algebraic variable.
            $vstacks = array();
            for ($z = 0; $z < $N; $z++) {
                $vstacks[$z] = clone $vstack;
                $instantiation = $this->instantiate_random_variables($vstack);
                foreach ($instantiation->all as $name => $inst)
                    $this->vstack_update_variable($vstacks[$z], $name, null, 'n', $inst->value);
            }

            // Evaluate and find the root mean square of the difference over all instantiation.
            if (strlen($newexpr) == 0)  return null;
            $nums = $this->evaluate_numerical_expression($vstacks, $newexpr, 'f');
            for ($i = 0; $i < mycount($nums); $i++)  $nums[$i] = $nums[$i] * $nums[$i];
            $res = sqrt(array_sum($nums) / $N);    // It must be a positive integer, Nan or inf.
            if (is_nan($res))  $res = INF;
            $diffs[] = $res;
        }
        return $diffs;
    }

    // Substitute the variable with numeric value in the list of algebraic formulas, it is used to show correct answer with random numeric value.
    public function substitute_partial_formula(&$vars, $formulas) {
        $res = array();
        for ($idx = 0; $idx < mycount($formulas); $idx++) {
            if (!is_string($formulas[$idx]))  return null;  // Internal error for calling this function.
            $formulas[$idx] = trim($formulas[$idx]);
            $vstack = $this->get_formula_information($vars, $formulas[$idx]);
            if ($vstack === null || preg_match('/^[ )(^\/*+-]*$/', $vstack->remaining) == false)
                throw new Exception(get_string('error_forbid_char', 'qtype_formulas'));
            $vstack = $this->replace_vstack_variables($vstack, $this->evalreplacelist);

            // Replace the variable with numeric value by the number.
            $splitted = explode('`', preg_replace('/(@[0-9]+)/', '`$1`', $vstack->sub));
            for ($i = 1; $i < mycount($splitted); $i += 2) {
                $data = $this->vstack_get_variable($vstack, $splitted[$i]);
                if ($data->type == 'v') {
                    $tmp = $this->vstack_get_variable($vstack, $data->value);
                    if ($tmp === null)  throw new Exception(get_string('error_vars_undefined', 'qtype_formulas', $data->value) . ' in substitute_partial_formula');
                    if ($tmp->type == 'n')  $data = $tmp;
                }
                $splitted[$i] = $data->value;
            }
            $res[] = implode('', $splitted);
        }
        return $res;
    }

    /**
     * Check the validity of formula. From calculated question type. Modified.
     *
     * @param string $formula The input formula
     * @return false for possible valid formula, otherwise error message
     */
    public function find_formula_errors($formula) {
        // Validates the formula submitted from the question edit page.
        // Returns false if everything is alright.
        // Otherwise it constructs an error message
        // Strip away empty space and lowercase it.
        $formula = str_replace(' ', '', $formula);

        $safeoperatorchar = '-+/*%>:^\~<?=&|!'; /* */
        $operatorornumber = "[$safeoperatorchar.0-9eE]";

        while ( preg_match("~(^|[$safeoperatorchar,(])([a-z0-9_]*)\\(($operatorornumber+(,$operatorornumber+((,$operatorornumber+)+)?)?)?\\)~",
            $formula, $regs)) {
            for ($i = 0; $i < 6; $i++)  if (!isset($regs[$i]))  $regs[] = '';
            switch ($regs[2]) {
                // Simple parenthesis.
                case '':
                    if (strlen($regs[4]) != 0 || strlen($regs[3]) == 0) {
                        return get_string('illegalformulasyntax', 'qtype_formulas', $regs[0]);
                    }
                    break;

                // Zero argument functions.
                case 'pi':
                    if (strlen($regs[3]) != 0) {
                        return get_string('functiontakesnoargs', 'qtype_formulas', $regs[2]);
                    }
                    break;

                // Single argument functions (the most common case).
                case 'abs': case 'acos': case 'acosh': case 'asin': case 'asinh':
                        case 'atan': case 'atanh': case 'bindec': case 'ceil': case 'cos':
                        case 'cosh': case 'decbin': case 'decoct': case 'deg2rad':
                        case 'exp': case 'expm1': case 'floor': case 'is_finite':
                        case 'is_infinite': case 'is_nan': case 'log10': case 'log1p':
                        case 'octdec': case 'rad2deg': case 'sin': case 'sinh': case 'sqrt':
                        case 'tan': case 'tanh': case 'fact':
                    if (strlen($regs[4]) != 0 || strlen($regs[3]) == 0) {
                        return get_string('functiontakesonearg', 'qtype_formulas', $regs[2]);
                    }
                    break;

                // Functions that take one or two arguments.
                case 'log': case 'round':
                    if (strlen($regs[5]) != 0 || strlen($regs[3]) == 0) {
                        return get_string('functiontakesoneortwoargs', 'qtype_formulas', $regs[2]);
                    }
                    break;

                // Functions that must have two arguments.
                case 'atan2': case 'fmod': case 'pow': case 'ncr': case 'npr': case 'lcm': case 'gcd': case 'sigfig':
                    if (strlen($regs[5]) != 0 || strlen($regs[4]) == 0) {
                        return get_string('functiontakestwoargs', 'qtype_formulas', $regs[2]);
                    }
                    break;

                // Functions that take two or more arguments.
                case 'min': case 'max':
                    if (strlen($regs[4]) == 0) {
                        return get_string('functiontakesatleasttwo', 'qtype_formulas', $regs[2]);
                    }
                    break;

                default:
                    return get_string('unsupportedformulafunction', 'qtype_formulas', $regs[2]);
            }

            // Exchange the function call with '1' and then check for
            // another function call...
            if ($regs[1]) {
                // The function call is proceeded by an operator.
                $formula = str_replace($regs[0], $regs[1] . '1', $formula);
            } else {
                // The function call starts the formula.
                $formula = preg_replace("~^$regs[2]\\([^)]*\\)~", '1', $formula);
            }
        }

        if (preg_match("~[^$safeoperatorchar.0-9eE]+~", $formula, $regs)) {
            return get_string('illegalformulasyntax', 'qtype_formulas', $regs[0]);
        } else {
            // Formula just might be valid.
            return false;
        }

    }
}
