<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

namespace qtype_formulas\local;
use Exception;

// TODO: add function randint.
// TODO: add some string functions, e.g. upper/lower case, repeat char.

/**
 * Additional functions qtype_formulas
 *
 * @package    qtype_formulas
 * @copyright  2022 Philipp Imhof
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class functions {
    /** @var int */
    const NONE = 0;

    /** @var int */
    const INTEGER = 1;

    /** @var int */
    const NON_NEGATIVE = 2;

    /** @var int */
    const NON_ZERO = 4;

    /** @var int */
    const NEGATIVE = 8;

    /** @var int */
    const POSITIVE = 16;

    /**
     * List of all functions exported by this class.
     *
     * The function name (as is) is used as the array key. The array value
     * is another array of two numbers, i. e. the minimum and the maximum number
     * of parameters arguments supported by this function. If there is no
     * maximum, INF is used.
     *
     * Examples:
     * - function foo() with no arguments: 'foo' => [0, 0]
     * - function bar() with at least 1 argument: 'bar' => [1, INF]
     * - function baz() with 2 or 3 arguments: 'baz' => [2, 3]
     *
     * @var array
     */
    const FUNCTIONS = [
        'binomialcdf' => [3, 3],
        'binomialpdf' => [3, 3],
        'concat' => [2, INF],
        // Note: The special function diff() is defined in the evaluator class.
        'diff' => [2, 3],
        'fact' => [1, 1],
        'fill' => [2, 2],
        'fmod' => [2, 2],
        'fqversionnumber' => [0, 0],
        'gcd' => [2, 2],
        'inv' => [1, 1],
        'join' => [2, INF],
        'lb' => [1, 1],
        'lcm' => [2, 2],
        'len' => [1, 1],
        'lg' => [1, 1],
        'ln' => [1, 1],
        'map' => [2, 3],
        'modinv' => [2, 2],
        'modpow' => [3, 3],
        'ncr' => [2, 2],
        'normcdf' => [3, 3],
        'npr' => [2, 2],
        'pick' => [2, INF],
        'poly' => [1, 3],
        'rshuffle' => [1, 1],
        'shuffle' => [1, 1],
        'sigfig' => [2, 2],
        'sort' => [1, 2],
        'stdnormcdf' => [1, 1],
        'stdnormpdf' => [1, 1],
        'str' => [1, 1],
        'sublist' => [2, 2],
        'sum' => [1, 1],
    ];

    /**
     * Return the plugin's version number. This is intended for users without
     * administration access who want to check whether their installation offers
     * a certain feature or is affected by a certain bug.
     *
     * @return string
     */
    public static function fqversionnumber(): string {
        return get_config('qtype_formulas')->version;
    }

    /**
     * Apply an unary operator or function to one array or a binary operator or function
     * to two arrays and return the result. When working with binary operators or functions,
     * one of the two arrays may be a constant and will be inflated to a list of the
     * correct size.
     *
     * Examples:
     * - map("+", [1, 2, 3], 1) -> [2, 3, 4]
     * - map("+", [1, 2, 3], [4, 5, 6]) -> [5, 7, 9]
     * - map("sqrt", [1, 4, 9]) -> [1, 2, 3]
     *
     * @param string $what operator or function to be applied
     * @param mixed $first list or constant (number, string)
     * @param mixed $second list of the same size or constant
     * @return array
     */
    public static function map(string $what, $first, $second = null): array {
        // List of allowed binary operators, i. e. all but the assignment.
        $binaryops = ['**', '*', '/', '%', '+', '-', '<<', '>>', '&', '^',
            '|', '&&', '||', '<', '>', '==', '>=', '<=', '!='];

        // List of allowed unary operators.
        $unaryops = ['_', '!', '~'];

        // List of all functions.
        $allfunctions = self::FUNCTIONS + evaluator::PHPFUNCTIONS;

        // If the operator is '-', we first check the parameters to find out whether
        // it is subtraction (encoded as '-') or negation (encoded as '_').
        if ($what === '-' && $second === null) {
            $what = '_';
        }

        // In order to perform the necessary pre-checks, we have to determine what operation
        // type is requested: binary operation, unary operation, function with one argument
        // or function with two arguments.
        $usebinaryop = in_array($what, $binaryops);
        $useunaryop = in_array($what, $unaryops);
        $useunaryfunc = false;
        $usebinaryfunc = false;

        // If $what is neither a valid operator nor a function, throw an error.
        if (!$usebinaryop && !$useunaryop) {
            if (!array_key_exists($what, $allfunctions) || $what === 'diff') {
                self::die('error_diff_first_invalid', $what);
            }
            // Fetch the number of arguments for the given function name.
            $min = $allfunctions[$what][0];
            $max = $allfunctions[$what][1];
            if ($max < 1) {
                self::die('error_diff_function_no_args', $what);
            }
            if ($min > 2) {
                self::die('error_diff_function_more_args', $what);
            }
            // Some functions are clearly unary.
            if ($min <= 1 && $max === 1) {
                $useunaryfunc = true;
                $usebinaryfunc = false;
            }
            // Other functions are clearly binary.
            if ($min === 2 && $max >= 2) {
                $useunaryfunc = false;
                $usebinaryfunc = true;
            }
            // If the function can be unary or binary, we have to check the arguments.
            if ($min <= 1 && $max >= 2) {
                $useunaryfunc = ($second === null);
                $usebinaryfunc = !$useunaryfunc;
            }
        }

        // Check arguments for unary operators or functions: we need exactly one list.
        if ($useunaryop || $useunaryfunc) {
            $type = $useunaryop ? 'operator' : 'function';
            if ($second !== null) {
                self::die("error_diff_unary_$type", $what);
            }
            if (!is_array($first)) {
                // The unary minus is internally represented as '_', but it should be shown as '-' in
                // an error message.
                if ($what === '_') {
                    $what = '-';
                }
                self::die('error_diff_unary_needslist', $what);
            }
        }

        // Check arguments for binary operators or functions: we expect (a) one scalar and one list or
        // (b) two lists of the same size.
        if ($usebinaryop || $usebinaryfunc) {
            $type = $usebinaryop ? 'operator' : 'function';
            if ($second === null) {
                self::die("error_diff_binary_{$type}_two", $what);
            }
            if (is_scalar($first) && is_scalar($second)) {
                self::die("error_diff_binary_{$type}_needslist", $what);
            }
            if (is_array($first) && is_array($second) && count($first) != count($second)) {
                self::die('error_diff_binary_samesize');
            }
            // We do now know that we are using a binary operator or function and that we have at least one list.
            // If the other argument is a scalar, we blow it up to an array of the same size as the other list.
            if (is_scalar($first)) {
                $first = array_fill(0, count($second), token::wrap($first));
            }
            if (is_scalar($second)) {
                $second = array_fill(0, count($first), token::wrap($second));
            }
        }

        // Now we are all set to apply the operator or execute the function. We are going to use
        // our own for loop instead of PHP's array_walk() or array_map(). For better error reporting,
        // we use a try-catch construct.
        $result = [];
        try {
            $count = count($first);
            for ($i = 0; $i < $count; $i++) {
                if ($useunaryop) {
                    $tmp = self::apply_unary_operator($what, $first[$i]->value);
                } else if ($usebinaryop) {
                    $tmp = self::apply_binary_operator($what, $first[$i]->value, $second[$i]->value);
                } else if ($useunaryfunc || $usebinaryfunc) {
                    // For function calls, we distinguish between our own functions and PHP's built-in functions.
                    $prefix = '';
                    if (array_key_exists($what, self::FUNCTIONS)) {
                        $prefix = self::class . '::';
                    }
                    // The params must be wrapped in an array. There is at least one parameter ...
                    $params = [$first[$i]->value];
                    // ... and there's a second one for binary functions.
                    if ($usebinaryfunc) {
                        $params[] = $second[$i]->value;
                    }
                    $tmp = call_user_func_array($prefix . $what, $params);
                }
                $result[] = token::wrap($tmp);
            }
        } catch (Exception $e) {
            self::die('error_map_unknown', $e->getMessage());
        }

        return $result;
    }

    /**
     * Given a permutation, find its inverse.
     *
     * Example:
     * - The permutation [2, 0, 1] would transform ABC to CAB.
     * - Its inverse is [1, 2, 0] which transforms CAB to ABC again.
     *
     * @param array $list list of consecutive integers, starting at 0
     * @return array inverse permutation
     */
    public static function inv($list): array {
        // First, we check that the argument is actually a list.
        if (!is_array($list)) {
            self::die('error_inv_list');
        }
        // Now, we check that the array contains only numbers. If necessary,
        // floats will be converted to integers by truncation. Note: number tokens
        // always store their value as float, so we have to apply the conversion to
        // all numbers, because we cannot know whether they really are of type float or int.
        foreach ($list as $entry) {
            $value = $entry->value;
            // Not setting INTEGER as condition, because we actually do accept floats and truncate them.
            self::assure_numeric($value, get_string('error_inv_integers', 'qtype_formulas'));
            $entry->value = intval($value);
        }

        // Now we check that the same number does not appear twice.
        $tmp = array_unique($list);
        if (count($tmp) !== count($list)) {
            self::die('error_inv_nodup');
        }
        // Finally, we make sure the numbers are consecutive from 0 to n-1 or from 1 to n with
        // n being the number of elements in the list. We can use min() and max(), because the
        // token has a __tostring() method and numeric strings are compared numerically.
        $min = min($list);
        $max = max($list);
        if ($min->value > 1 || $min->value < 0) {
            self::die('error_inv_smallest');
        }
        if ($max->value - $min->value + 1 !== count($list)) {
            self::die('error_inv_consec');
        }

        // Create array from minimum to maximum value and then use the given list as the sort order.
        // Note: number tokens should have their value stored as floats.
        $result = [];
        for ($i = $min->value; $i <= $max->value; $i++) {
            $result[] = new token(token::NUMBER, floatval($i));
        }
        uksort($result, function($a, $b) use ($list) {
            return $list[$a] <=> $list[$b];
        });

        // Forget about the keys and re-index the sorted array from 0.
        return array_values($result);
    }

    /**
     * Concatenate multiple lists into one.
     *
     * @param array ...$arrays two or more lists
     * @return array concetanation of all given lists
     */
    public static function concat(...$arrays): array {
        $result = [];

        // Iterate over each array ...
        foreach ($arrays as $array) {
            if (!is_array($array)) {
                self::die('error_func_all_lists', 'concat()');
            }
            // ... and over each element of every array.
            foreach ($array as $element) {
                $result[] = $element;
            }
        }

        return $result;
    }

    /**
     * Sort a given list using natural sort order. Optionally, a second list may be given
     * to indicate the sort order.
     *
     * Examples:
     * - sort([1,10,5,3]) --> [1, 3, 5, 10]
     * - sort([-3,-2,4,2,3,1,0,-1,-4,5]) --> [-4, -3, -2, -1, 0, 1, 2, 3, 4, 5]
     * - sort(["A1","A10","A2","A100"]) --> ['A1', 'A2', 'A10', 'A100']
     * - sort(["B","A2","A1"]) --> ['A1', 'A2', 'B']
     * - sort(["B","C","A"],[0,2,1]) --> ['B', 'A', 'C']
     * - sort(["-3","-2","B","2","3","1","0","-1","b","a","A"]) --> ['-3', '-2', '-1', '0', '1', '2', '3', 'A', 'B', 'a', 'b']
     * - sort(["B","3","1","0","A","C","c","b","2","a"]) --> ['0', '1', '2', '3', 'A', 'B', 'C', 'a', 'b', 'c']
     * - sort(["B","A2","A1"],[2,4,1]) --> ['A1', 'B', 'A2']
     * - sort([1,2,3], ["A10","A1","A2"]) --> [2, 3, 1]
     *
     * @param array $tosort list to be sorted
     * @param ?array $order sort order
     * @return array sorted list
     */
    public static function sort($tosort, $order = null): array {
        // The first argument must be an array.
        if (!is_array($tosort)) {
            self::die('error_func_first_list', 'sort()');
        }

        // If we have one list only, we duplicate it.
        if ($order === null) {
            $order = $tosort;
        }

        // If two arguments are given, the second must be an array.
        if (!is_array($order)) {
            self::die('error_sort_twolists');
        }

        // If we have two lists, they must have the same number of elements.
        if (count($tosort) !== count($order)) {
            self::die('error_sort_samesize');
        }

        // Now sort the first array, using the second as the sort order.
        $tmp = $tosort;
        uksort($tmp, function($a, $b) use ($order) {
            $first = $order[$a]->value;
            $second = $order[$b]->value;
            // If both elements are numeric, we compare their numerical value.
            if (is_numeric($first) && is_numeric($second)) {
                return floatval($first) <=> floatval($second);
            }
            // Otherwise, we use natural sorting.
            return strnatcmp($first, $second);
        });
        return array_values($tmp);
    }

    /**
     * Wrapper for the poly() function which can be invoked in many different ways:
     * - (1) list of numbers => polynomial with variable x
     * - (1) number => force + sign if number > 0
     * - (2) string, number => combine
     * - (2) string, list of numbers => polynomial with variable from string
     * - (2) list of strings, list of numbers => linear combination
     * - (2) list of numbers, string => polynomial with x using second argument as separator (e.g. &)
     * - (3) string, number, string => combine them and, if appropriate, force + sign
     * - (3) string, list of numbers, string => polynomial (one var) using third argument as separator (e.g. &)
     * - (3) list of strings, list of numbers, string => linear combination using third argument as separator
     * This will call the poly_formatter() function accordingly.
     *
     * @param mixed ...$args arguments
     * @return string the formatted string
     * @throws Exception
     */
    public static function poly(...$args) {
        $numargs = count($args);
        switch ($numargs) {
            case 1:
                $argument = token::unpack($args[0]);
                // For backwards compatibility: if called with just a list of numbers, use x as variable.
                if (self::is_numeric_array($argument)) {
                    return self::poly_formatter('x', $argument);
                }
                // If called with just a number, force the plus sign (if the number is positive) to be shown.
                // Basically, there is no other reason one would call this function with just one number.
                if (is_numeric($argument)) {
                    return self::poly_formatter('', $argument, '+');
                }
                // If the single argument is neither an array, nor a number or numeric string,
                // we throw an error.
                self::die('error_poly_one');
            case 2:
                $first = token::unpack($args[0]);
                $second = token::unpack($args[1]);
                // If the first argument is a string, we distinguish to cases: (a) the second is a number
                // and (b) the second is a list of numbers.
                if (is_string($first)) {
                    // If we have a string and a number, we wrap them in arrays and build a linear combination.
                    if (is_float($second)) {
                        return self::poly_formatter([$first], [$second]);
                    }
                    // If it is a string and a list of numbers, we build a polynomial.
                    if (self::is_numeric_array($second)) {
                        return self::poly_formatter($first, $second);
                    }
                    self::die('error_poly_string');
                }
                // If called with a list of numbers and a string, use x as default variable for the polynomial and use the
                // third argument as a separator, e. g. for a usage in LaTeX matrices or array-like constructions.
                if (self::is_numeric_array($first) && is_string($second)) {
                    return self::poly_formatter('x', $first, '', $second);
                }
                // If called with a list of strings, the next argument must be a list of numbers.
                if (is_array($first)) {
                    if (self::is_numeric_array($second)) {
                        return self::poly_formatter($first, $second);
                    }
                    self::die('error_poly_stringlist');
                }
                // Any other invocations with two arguments is invalid.
                self::die('error_poly_two');
            case 3:
                $first = token::unpack($args[0]);
                $second = token::unpack($args[1]);
                $third = token::unpack($args[2]);
                // If called with a string, a number and another string, combine them while using the third argument
                // to e. g. force a "+" on positive numbers.
                if (is_string($first) && is_float($second) && is_string($third)) {
                    return self::poly_formatter([$first], [$second], $third);
                }
                // If called with a string (or list of strings), a list of numbers and another string, combine them
                // while using the third argument as a separator, e. g. for a usage in LaTeX matrices or array-like constructions.
                return self::poly_formatter($first, $second, '', $third);
        }
    }

    /**
     * Format a polynomial to be display with LaTeX / MathJax. The function can also be
     * used to force the plus sign for a single number or to format arbitrary linear combinations.
     *
     * This function will be called by the public poly() function.
     *
     * @param mixed $variables one variable (as a string) or a list of variables (array of strings)
     * @param mixed $coefficients one number or an array of numbers to be used as coefficients
     * @param string $forceplus symbol to be used for the normally invisible leading plus, optional
     * @param string $additionalseparator symbol to be used as separator between the terms, optional
     * @return string the formatted string
     */
    private static function poly_formatter($variables, $coefficients = null, $forceplus = '', $additionalseparator = '') {
        // If no variable is given and there is just one single number, simply force the plus sign
        // on positive numbers.
        if ($variables === '' && is_numeric($coefficients)) {
            if ($coefficients > 0) {
                return $forceplus . $coefficients;
            }
            return $coefficients;
        }

        $numberofterms = count($coefficients);
        // By default, we think that a final coefficient == 1 is not to be shown, because it is a true coefficient
        // and not a constant term. Also, terms with coefficient == zero should generally be completely omitted.
        $constantone = false;
        $omitzero = true;

        // If the variable is left empty, but there is a list of coefficients, we build an empty array
        // of the same size as the number of coefficients. This can be used to pretty-print matrix rows.
        // In that case, the numbers 1 and 0 should never be omitted.
        if ($variables === '') {
            $variables = array_fill(0, $numberofterms, '');
            $constantone = true;
            $omitzero = false;
        }

        // If there is just one variable, we blow it up to an array of the correct size and descending exponents.
        if (gettype($variables) === 'string' && $variables !== '') {
            // As we have just one variable, we are building a standard polynomial where the last coefficient
            // is not a real coefficient, but a constant term that has to be printed.
            $constantone = true;
            $tmp = $variables;
            $variables = [];
            for ($i = 0; $i < $numberofterms; $i++) {
                if ($i == $numberofterms - 2) {
                    $variables[$i] = $tmp;
                } else if ($i == $numberofterms - 1) {
                    $variables[$i] = '';
                } else {
                    $variables[$i] = $tmp . '^{' . ($numberofterms - 1 - $i) . '}';
                }
            }
        }
        // If the list of variables is shorter than the list of coefficients, just start over again.
        if (count($variables) < $numberofterms) {
            $numberofvars = count($variables);
            for ($i = count($variables); $i < $numberofterms; $i++) {
                $variables[$i] = $variables[$i % $numberofvars];
            }
        }

        // If the separator is "doubled", e.g. &&, we put one half before and one half after the
        // operator. By default, we have the entire separator before the operator. Also, we do not
        // change anything if we are building a matrix row, because there are no operators, just signs.
        $separatorlength = strlen($additionalseparator);
        $separatorbefore = $additionalseparator;
        $separatorafter = '';
        if ($separatorlength > 0 && $separatorlength % 2 === 0 && $omitzero) {
            $tmpbefore = substr($additionalseparator, 0, $separatorlength / 2);
            $tmpafter = substr($additionalseparator, $separatorlength / 2);
            // If the separator just has even length, but is not "doubled", we don't touch it.
            if ($tmpbefore === $tmpafter) {
                $separatorbefore = $tmpbefore;
                $separatorafter = $tmpafter;
            }
        }

        $result = '';
        // First term should not have a leading plus sign, unless user wants to force it.
        foreach ($coefficients as $i => $coef) {
            $thisseparatorbefore = ($i == 0 ? '' : $separatorbefore);
            $thisseparatorafter = ($i == 0 ? '' : $separatorafter);
            // Terms with coefficient == 0 are generally not shown. But if we use a separator, it must be printed anyway.
            if ($coef == 0) {
                if ($i > 0) {
                    $result .= $thisseparatorbefore . $thisseparatorafter;
                }
                if ($omitzero) {
                    continue;
                }
            }
            // Put a + or - sign according to value of coefficient and replace the coefficient
            // by its absolute value, as we don't need the sign anymore after this step.
            // If the coefficient is 0 and we force its output, do it now. However, do not put a sign,
            // as the only documented usage of this is for matrix rows and the like.
            if ($coef < 0) {
                $result .= $thisseparatorbefore . '-' . $thisseparatorafter;
                $coef = abs($coef);
            } else if ($coef > 0) {
                // If $omitzero is false, we are building a matrix row, so we don't put plus signs.
                $result .= $thisseparatorbefore . ($omitzero ? '+' : '') . $thisseparatorafter;
            }
            // Put the coefficient. If the coefficient is +1 or -1, we don't put the number,
            // unless we're at the last term. The sign is already there, so we use the absolute value.
            // Never omit 1's if building a matrix row.
            if ($coef == 1) {
                $coef = (!$omitzero || ($i == $numberofterms - 1 && $constantone) ? '1' : '');
            }
            $result .= $coef . $variables[$i];
        }
        // If the resulting string is empty (or empty with just alignment separators), add a zero at the end.
        if ($result === '' || $result === str_repeat($additionalseparator, $numberofterms - 1)) {
            $result .= '0';
        }
        // Strip leading + and replace by $forceplus (which will be '' or '+' most of the time).
        if ($result[0] == '+') {
            $result = $forceplus . substr($result, 1);
        }
        // If we have nothing but separators before the leading +, replace that + by $forceplus.
        if ($separatorbefore !== '' && preg_match("/^($separatorbefore+)\+/", $result)) {
            $result = preg_replace("/^($separatorbefore+)\+/", "\\1$forceplus", $result);
        }
        return $result;
    }

    /**
     * Given a list $list, return the elements at the positions defined by the list $indices,
     * e. g. sublist([1, 2, 3], [0, 0, 2, 2, 1, 1]) yields [1, 1, 3, 3, 2, 2].
     *
     * @param mixed $list
     * @param mixed $indices
     * @return array
     */
    public static function sublist($list, $indices): array {
        if (!is_array($list) || !is_array($indices)) {
            self::die('error_func_all_lists', 'sublist()');
        }

        $result = [];
        foreach ($indices as $i) {
            $i = $i->value;
            $i = self::assure_numeric($i, get_string('error_sublist_indices', 'qtype_formulas', $i), self::INTEGER);
            if ($i > count($list) - 1 || $i < 0) {
                self::die('error_sublist_outofrange', $i);
            }
            $result[] = $list[$i];
        }
        return $result;
    }

    /**
     * Round a given number to an indicated number of significant figures. The function
     * returns a string in order to allow trailing zeroes.
     *
     * @param mixed $number
     * @param mixed $precision
     * @return string
     */
    public static function sigfig($number, $precision): string {
        self::assure_numeric($number, get_string('error_func_first_number', 'qtype_formulas', 'sigfig()'));
        self::assure_numeric(
            $precision,
            get_string('error_func_second_posint', 'qtype_formulas', 'sigfig()'),
            self::POSITIVE | self::INTEGER
        );
        $number = floatval($number);
        $precision = intval($precision);

        // First, we calculate how many digits we have before the decimal point.
        $digitsbefore = 1;
        if ($number != 0) {
            $digitsbefore = floor(log10(abs($number))) + 1;
        }
        // Now, we determine the number of decimals (digits after the point). This
        // number might be negative, e.g. if we want to have 12345 with 3 significant
        // figures. Or it might be zero, e.g. if 12345 must be brought to 5 significant
        // figures.
        $digitsafter = $precision - $digitsbefore;

        // We round the number as desired. This might add zeroes, e.g. 12345 will become
        // 12300 when rounded to -2 digits.
        $number = round($number, $digitsafter);

        // We only request decimals if $digitsafter is greater than zero.
        $digitsafter = max(0, $digitsafter);

        return number_format($number, $digitsafter, '.', '');
    }

    /**
     * Return the number of elements in a list or the length of a string.
     *
     * @param array|string $arg list or string
     * @return int number of elements or length
     */
    public static function len($arg): int {
        if (is_array($arg)) {
            return count($arg);
        }
        if (is_string($arg)) {
            return strlen($arg);
        }
        self::die('error_len_argument');
    }

    /**
     * Create an array of a given size, filled with a given value.
     *
     * Examples:
     * - fill(5, 1) -> [1, 1, 1, 1, 1]
     * - fill(3, "a") -> ["a", "a", "a"]
     * - fill(4, [1, 2]) -> [[1, 2], [1, 2], [1, 2], [1, 2]]
     *
     * @param int $count number of elements
     * @param mixed $value value to use
     * @return array
     */
    public static function fill($count, $value): array {
        // If $count is invalid, it will be converted to 0 which will then lead to an error.
        $count = intval($count);
        if ($count < 1) {
            self::die('error_func_first_posint', 'fill()');
        }
        return array_fill(0, $count, token::wrap($value));
    }

    /**
     * Calculate the sum of all elements in an array.
     *
     * @param array $array list of numbers
     * @return float sum
     */
    public static function sum($array): float {
        if (!is_array($array)) {
            self::die('error_sum_argument');
        }

        $result = 0;
        foreach ($array as $token) {
            $value = $token->value;
            if (!is_numeric($value)) {
                self::die('error_sum_argument');
            }
            $result += floatval($value);
        }
        return $result;
    }

    /**
     * Convert number to string.
     *
     * @param float $value number
     * @return string
     */
    public static function str($value): string {
        if (!is_scalar($value)) {
            self::die('error_str_argument');
        }
        return strval($value);
    }

    /**
     * Concatenate the given strings, separating them by the given separator, e. g.
     * join('-', 'a', 'b') gives 'a-b'. The strings to be joined can also be passed as
     * a list, e. g. join('-', ['a', 'b']).
     *
     * @param string $separator
     * @param string ...$values
     * @return string
     */
    public static function join($separator, ...$values): string {
        $result = [];
        // Using array_walk_recursive() makes it easy to accept a list of strings as the second
        // argument instead of giving all strings individually.
        array_walk_recursive($values, function($val) use (&$result) {
            $result[] = $val;
        });
        return implode($separator, $result);
    }

    /**
     * Return the n-th element of a list or multiple arguments. If the index is out of range,
     * the function will always return the *first* element in order to maintain backwards
     * compatibility.
     *
     * @param mixed $index
     * @param mixed ...$data
     * @return void
     */
    public static function pick($index, ...$data) {
        // The index must be a number (or a numeric string). We do not enforce it to be integer.
        // If it is not, it will be truncated for backwards compatibility.
        self::assure_numeric($index, get_string('error_func_first_number', 'qtype_formulas', 'pick()'));
        $index = intval($index);

        $count = count($data);

        // The $data parameter will always be an array and will contain
        // - one single array for the pick(index, list) usage
        // - the various values for the pick(index, val1, val2, val3, ...) usage.
        if ($count === 1) {
            if (!is_array($data[0])) {
                self::die('error_pick_two');
            }
            // We set $data to the given array and update the count.
            $data = $data[0];
            $count = count($data);
        }

        // For backwards compatibility, we always take the first element if the index is
        // out of range. Indexing "from the end" is not allowed.
        if ($index > $count - 1 || $index < 0) {
            $index = 0;
        }

        // We can either return a a token or a value and let the caller wrap it into a token.
        return $data[$index];
    }

    /**
     * Shuffle the elements of an array.
     *
     * @param array $ar
     * @return array
     */
    public static function shuffle(array $ar): array {
        shuffle($ar);
        return $ar;
    }

    /**
     * Recursively shuffle a given array.
     *
     * @param array $ar
     * @return array
     */
    public static function rshuffle(array $ar): array {
        // First, we shuffle the array.
        shuffle($ar);

        // Now, we iterate over all elements and check whether they are nested arrays.
        // If they are, we shuffle them recursively.
        foreach ($ar as $element) {
            if (is_array($element->value)) {
                $element->value = self::shuffle($element->value);
            }
        }

        return $ar;
    }

    /**
     * Calculate the factorial n! of a non-negative integer.
     * Note: technically, the function accepts a float, because in some
     * PHP versions, if one passes a float to a function that expectes an int,
     * the float will be converted. We'd rather detect that and print an error.
     *
     * @param float $n the number
     * @return int
     */
    public static function fact(float $n): int {
        $n = self::assure_numeric(
            $n,
            get_string('error_func_nnegint', 'qtype_formulas', 'fact()'),
            self::NON_NEGATIVE | self::INTEGER
        );
        if ($n < 2) {
            return 1;
        }
        $result = 1;
        for ($i = 1; $i <= $n; $i++) {
            if ($result > PHP_INT_MAX / $i) {
                self::die('error_fact_toolarge', $n);
            }
            $result *= $i;
        }
        return $result;
    }

    /**
     * calculate standard normal probability density
     *
     * @param float $z value
     * @return float standard normal density of $z
     */
    public static function stdnormpdf(float $z): float {
        return 1 / (sqrt(2) * M_SQRTPI) * exp(-.5 * $z ** 2);
    }

    /**
     * Calculate standard normal cumulative distribution by approximation,
     * accurate at least to 1e-12. The approximation uses the complementary
     * error function and some magic numbers that can be found in Wikipedia:
     * https://en.wikipedia.org/wiki/Error_function
     *
     * @param float $z value
     * @return float probability for a value of $z or less under standard normal distribution
     */
    public static function stdnormcdf(float $z): float {
        // We use the relationship Phi(z) = (1 + erf(z/sqrt(2))) / 2 with
        // erf() being the error function. Instead of erf(), we will approximate
        // the complementary error function erfc() and use erf(x) = 1 - erfc(x).
        // The approximation formula for erfc is valid for $z >= 0. For $z < 0,
        // we can use the identity erfc(x) = 2 - erfc(-x), so we store the sign
        // and transform $z |-> abs($z) / sqrt(2).
        $sign = $z >= 0 ? 1 : -1;
        $z = abs($z) / sqrt(2);

        // Magic coefficients from Wikipedia.
        $p = [
            [0, 0, 0.56418958354775629],
            [1, 2.71078540045147805, 5.80755613130301624],
            [1, 3.47469513777439592, 12.07402036406381411],
            [1, 4.00561509202259545, 9.30596659485887898],
            [1, 5.16722705817812584, 9.12661617673673262],
            [1, 5.95908795446633271, 9.19435612886969243],
        ];
        $q = [
            [0, 1, 2.06955023132914151],
            [1, 3.47954057099518960, 12.06166887286239555],
            [1, 3.72068443960225092, 8.44319781003968454],
            [1, 3.90225704029924078, 6.36161630953880464],
            [1, 4.03296893109262491, 5.13578530585681539],
            [1, 4.11240942957450885, 4.48640329523408675],
        ];

        // Calculate approximation of erfc()...
        $erfc = exp(-$z ** 2);
        for ($i = 0; $i < count($p); $i++) {
            $erfc *= ($p[$i][0] * $z ** 2 + $p[$i][1] * $z + $p[$i][2]) / ($q[$i][0] * $z ** 2 + $q[$i][1] * $z + $q[$i][2]);
        }
        // If needed, transform for negative input.
        if ($sign === -1) {
            $erfc = 2 - $erfc;
        }
        // We need (1 + erf) / 2 with erf = 1 - erfc.
        return (2 - $erfc) / 2;
    }

    /**
     * Calculate normal cumulative distribution based on stdnormcdf(). The
     * approxmation is accurate at least to 1e-12.
     *
     * @param float $x value
     * @param float $mu mean
     * @param float $sigma standard deviation
     * @return float probability for a value of $x or less
     */
    public static function normcdf(float $x, float $mu, float $sigma): float {
        return self::stdnormcdf(($x - $mu) / $sigma);
    }

    /**
     * raise $a to the $b-th power modulo $m using efficient
     * square and multiply
     *
     * @param int $a base
     * @param int $b exponent
     * @param int $m modulus
     * @return int
     */
    public static function modpow($a, $b, $m): int {
        $a = self::assure_numeric(
            $a,
            get_string('error_func_first_int', 'qtype_formulas', 'modpow()'),
            self::INTEGER
        );
        $b = self::assure_numeric(
            $b,
            get_string('error_func_second_int', 'qtype_formulas', 'modpow()'),
            self::INTEGER
        );
        $m = self::assure_numeric(
            $m,
            get_string('error_func_third_posint', 'qtype_formulas', 'modpow()'),
            self::INTEGER | self::POSITIVE
        );

        $bin = decbin($b);
        $res = $a;
        if ($b == 0) {
            return 1;
        }
        for ($i = 1; $i < strlen($bin); $i++) {
            if ($bin[$i] == "0") {
                $res = ($res * $res) % $m;
            } else {
                $res = ($res * $res) % $m;
                $res = ($res * $a) % $m;
            }
        }
        return $res;
    }

    /**
     * Calculate the multiplicative inverse of $a modulo $m using the
     * extended euclidean algorithm.
     *
     * @param int $a the number whose inverse is to be found
     * @param int $m the modulus
     * @return int the result or 0 if the inverse does not exist
     */
    public static function modinv(int $a, int $m): int {
        $a = self::assure_numeric(
            $a,
            get_string('error_func_first_nzeroint', 'qtype_formulas', 'modinv()'),
            self::INTEGER | self::NON_ZERO
        );
        $m = self::assure_numeric(
            $m,
            get_string('error_func_second_posint', 'qtype_formulas', 'modinv()'),
            self::INTEGER | self::POSITIVE
        );

        $origm = $m;
        if (self::gcd($a, $m) != 1) {
            // Inverse does not exist.
            return 0;
        }
        list($s, $t, $lasts, $lastt) = [1, 0, 0, 1];
        while ($m != 0) {
            $q = floor($a / $m);
            list($a, $m) = [$m, $a - $q * $m];
            list($s, $lasts) = [$lasts, $s - $q * $lasts];
            list($t, $lastt) = [$lastt, $t - $q * $lastt];
        }
        return $s < 0 ? $s + $origm : $s;
    }

    /**
     * Calculate the floating point remainder of the division of
     * the arguments, i. e. x - m * floor(x / m). There is no
     * canonical definition for this function; some calculators
     * use flooring (round down to nearest integer) and others
     * use truncation (round to nearest integer, but towards zero).
     * This implementation gives the same results as e. g. Wolfram Alpha.
     *
     * @param float $x the dividend
     * @param float $m the modulus
     * @return float remainder of $x modulo $m
     * @throws Exception
     */
    public static function fmod($x, $m): float {
        self::assure_numeric($x, get_string('error_func_first_number', 'qtype_formulas', 'fmod()'));
        self::assure_numeric($m, get_string('error_func_second_nzeronum', 'qtype_formulas', 'fmod()'), self::NON_ZERO);
        return $x - $m * floor($x / $m);
    }

    /**
     * Calculate the probability of exactly $x successful outcomes for
     * $n trials under a binomial distribution with a probability of success
     * of $p.
     *
     * @param float $n number of trials
     * @param float $p probability of success for each trial
     * @param float $x number of successful outcomes
     *
     * @return float probability for exactly $x successful outcomes
     * @throws Exception
     */
    public static function binomialpdf(float $n, float $p, float $x): float {
        // Probability must be 0 <= p <= 1.
        if ($p < 0 || $p > 1) {
            self::die('error_probability', 'binomialpdf()');
        }
        // Number of tries must be at least 0.
        $n = self::assure_numeric(
            $n,
            get_string('error_distribution_tries', 'qtype_formulas', 'binomialpdf()'),
            self::NON_NEGATIVE | self::INTEGER
        );
        // Number of successful outcomes must be at least 0.
        $x = self::assure_numeric(
            $x,
            get_string('error_distribution_outcomes', 'qtype_formulas', 'binomialpdf'),
            self::NON_NEGATIVE | self::INTEGER
        );
        // If the number of successful outcomes is greater than the number of trials, the probability
        // is zero.
        if ($x > $n) {
            return 0;
        }
        return self::ncr($n, $x) * $p ** $x * (1 - $p) ** ($n - $x);
    }

    /**
     * Calculate the probability of up to $x successful outcomes for
     * $n trials under a binomial distribution with a probability of success
     * of $p, known as the cumulative distribution function. Parameters are float
     * instead of int to allow for better error reporting.
     *
     * @param float $n number of trials
     * @param float $p probability of success for each trial
     * @param float $x number of successful outcomes
     * @return float probability for up to $x successful outcomes
     * @throws Exception
     */
    public static function binomialcdf(float $n, float $p, float $x): float {
        // Probability must be 0 <= p <= 1.
        if ($p < 0 || $p > 1) {
            self::die('error_probability', 'binomialcdf()');
        }
        // Number of tries must be at least 0.
        $n = self::assure_numeric(
            $n,
            get_string('error_distribution_tries', 'qtype_formulas', 'binomialcdf()'),
            self::NON_NEGATIVE | self::INTEGER
        );
        // Number of successful outcomes must be at least 0.
        $x = self::assure_numeric(
            $x,
            get_string('error_distribution_outcomes', 'qtype_formulas', 'binomialcdf'),
            self::NON_NEGATIVE | self::INTEGER
        );
        // The probability for *up to* $n or more successful outcomes is 1.
        if ($x >= $n) {
            return 1;
        }
        $res = 0;
        for ($i = 0; $i <= $x; $i++) {
            $res += self::binomialpdf($n, $p, $i);
        }
        return $res;
    }

    /**
     * Calculate the common logarithm of a number.
     *
     * @param float $x number
     * @return float
     */
    public static function lg(float $x): float {
        if ($x <= 0) {
            self::die('error_func_positive', 'lg()');
        }
        return log10($x);
    }

    /**
     * Calculate the binary logarithm of a number.
     *
     * @param float $x number
     * @return float
     */
    public static function lb(float $x): float {
        if ($x <= 0) {
            self::die('error_func_positive', 'lb()');
        }
        return log($x, 2);
    }

    /**
     * Calculate the natural logarithm of a number.
     *
     * @param float $x number
     * @return float
     */
    public static function ln(float $x): float {
        if ($x <= 0) {
            self::die('error_func_positive', 'ln()');
        }
        return log($x);
    }

    /**
     * Calculate the number of permutations when taking $r elements
     * from a set of $n elements. The arguments must be integers.
     * Note: technically, the function accepts floats, because in some
     * PHP versions, if one passes a float to a function that expectes an int,
     * the float will be converted. We'd rather detect that and print an error.
     *
     * @param float $n the number of elements to choose from
     * @param float $r the number of elements to be chosen
     * @return int
     */
    public static function npr(float $n, float $r): int {
        $n = self::assure_numeric(
            $n,
            get_string('error_func_first_nnegint', 'qtype_formulas', 'npr()'),
            self::NON_NEGATIVE | self::INTEGER
        );
        $r = self::assure_numeric(
            $r,
            get_string('error_func_second_nnegint', 'qtype_formulas', 'npr()'),
            self::NON_NEGATIVE | self::INTEGER
        );

        return self::ncr($n, $r) * self::fact($r);
    }

    /**
     * Calculate the number of combination when taking $r elements
     * from a set of $n elements. The arguments must be integers.
     * Note: technically, the function accepts floats, because in some
     * PHP versions, if one passes a float to a function that expectes an int,
     * the float will be converted. We'd rather detect that and print an error.
     *
     * @param float $n the number of elements to choose from
     * @param float $r the number of elements to be chosen
     * @return int
     */
    public static function ncr(float $n, float $r): int {
        $n = self::assure_numeric($n, get_string('error_func_first_int', 'qtype_formulas', 'ncr()'), self::INTEGER);
        $r = self::assure_numeric($r, get_string('error_func_second_int', 'qtype_formulas', 'ncr()'), self::INTEGER);

        // The binomial coefficient is calculated for 0 <= r < n. For all
        // other cases, the result is zero.
        if ($r < 0 || $n < 0 || $n < $r) {
            return 0;
        }
        // Take the shortest path.
        if (($n - $r) < $r) {
            return self::ncr($n, ($n - $r));
        }
        $numerator = 1;
        $denominator = 1;
        for ($i = 1; $i <= $r; $i++) {
            $numerator *= ($n - $i + 1);
            $denominator *= $i;
        }
        return intdiv($numerator, $denominator);
    }

    /**
     * Calculate the greatest common divisor of two integers $a and $b
     * via the Euclidean algorithm. The arguments must be integers.
     * Note: technically, the function accepts floats, because in some
     * PHP versions, if one passes a float to a function that expectes an int,
     * the float will be converted. We'd rather detect that and print an error.
     *
     * @param float $a first number
     * @param float $b second number
     * @return int
     */
    public static function gcd(float $a, float $b): int {
        $a = self::assure_numeric($a, get_string('error_func_first_int', 'qtype_formulas', 'gcd()'), self::INTEGER);
        $b = self::assure_numeric($b, get_string('error_func_second_int', 'qtype_formulas', 'gcd()'), self::INTEGER);

        if ($a < 0) {
            $a = abs($a);
        }
        if ($b < 0) {
            $b = abs($b);
        }
        if ($a == 0 && $b == 0) {
            return 0;
        }
        if ($a == 0 || $b == 0) {
            return $a + $b;
        }
        if ($a == $b) {
            return $a;
        }
        do {
            $remainder = (int) $a % $b;
            $a = $b;
            $b = $remainder;
        } while ($remainder > 0);
        return $a;
    }

    /**
     * Calculate the least (non-negative) common multiple of two integers $a and $b
     * via the Euclidean algorithm. The arguments must be integers.
     * Note: technically, the function accepts floats, because in some
     * PHP versions, if one passes a float to a function that expectes an int,
     * the float will be converted. We'd rather detect that and print an error.
     *
     * @param float $a first number
     * @param float $b second number
     * @return int
     */
    public static function lcm(float $a, float $b): int {
        $a = self::assure_numeric($a, get_string('error_func_first_int', 'qtype_formulas', 'lcm()'), self::INTEGER);
        $b = self::assure_numeric($b, get_string('error_func_second_int', 'qtype_formulas', 'lcm()'), self::INTEGER);

        if ($a == 0 || $b == 0) {
            return 0;
        }
        return abs($a * $b) / self::gcd($a, $b);
    }

    /**
     * In many cases, operators need a numeric or at least a scalar operand to work properly.
     * This function does the necessary check and prepares a human-friendly error message
     * if the conditions are not met.
     *
     * @param mixed $value the value to check
     * @param string $who the operator or function we perform the check for
     * @param bool $enforcenumeric whether the value must be numeric in addition to being scalar
     * @return void
     * @throws Exception
     */
    private static function abort_if_not_scalar($value, string $who = '', bool $enforcenumeric = true): void {
        $a = (object)[];
        $variant = 'expected';
        if ($who !== '') {
            $a->who = $who;
            $variant = 'expects';
        }
        $expectation = ($enforcenumeric ? 'number' : 'scalar');

        if (!is_scalar($value)) {
            self::die("error_{$variant}_{$expectation}", $a);
        }
        $isnumber = is_numeric($value);
        if ($enforcenumeric && !$isnumber) {
            $a->found = $value;
            self::die("error_{$variant}_{$expectation}_found", $a);
        }
    }

    /**
     * Apply an unary operator to a given argument.
     *
     * @param string $op operator, e.g. - or !
     * @param mixed $input argument
     * @return mixed
     */
    public static function apply_unary_operator($op, $input) {
        // Abort with nice error message, if argument should be numeric but is not.
        if ($op === '_' || $op === '~') {
            self::abort_if_not_scalar($input, $op);
        }

        $output = null;
        switch ($op) {
            // If we already know that an unary operator was requested, we accept - instead of _
            // for negation.
            case '-':
            case '_':
                $output = (-1) * $input;
                break;
            case '!':
                $output = ($input ? 0 : 1);
                break;
            case '~':
                $output = ~ $input;
                break;
        }
        return $output;
    }

    /**
     * Apply a binary operator to two given arguments.
     *
     * @param string $op operator, e.g. + or **
     * @param mixed $first first argument
     * @param mixed $second second argument
     * @return mixed
     */
    public static function apply_binary_operator($op, $first, $second) {
        // Binary operators that need numeric input. Note: + is not here, because it
        // can be used to concatenate strings.
        $neednumeric = ['**', '*', '/', '%', '-', '<<', '>>', '&', '^', '|', '&&', '||'];

        // Abort with nice error message, if arguments should be numeric but are not.
        if (in_array($op, $neednumeric)) {
            self::abort_if_not_scalar($first, $op);
            self::abort_if_not_scalar($second, $op);
        }

        $output = null;
        // Many results will be numeric, so we set this as the default here.
        switch ($op) {
            case '**':
                // Only check for equality, because 0.0 == 0 but not 0.0 === 0.
                if ($first == 0 && $second == 0) {
                    self::die('error_power_zerozero');
                }
                if ($first == 0 && $second < 0) {
                    self::die('error_power_negbase_expzero');
                }
                if ($first < 0 && intval($second) != $second) {
                    self::die('error_power_negbase_expfrac');
                }
                $output = $first ** $second;
                break;
            case '*':
                $output = $first * $second;
                break;
            case '/':
            case '%':
                if ($second == 0) {
                    self::die('error_divzero');
                }
                if ($op === '/') {
                    $output = $first / $second;
                } else {
                    $output = $first % $second;
                }
                break;
            case '+':
                // If at least one operand is a string, we use concatenation instead
                // of addition, *UNLESS* both strings are numeric, in which case
                // we follow PHP's type juggling and add those numbers. The user
                // will have to use join() in such cases.
                $bothnumeric = is_numeric($first) && is_numeric($second);
                if (is_string($first) || is_string($second) && !$bothnumeric) {
                    self::abort_if_not_scalar($first, '+', false);
                    self::abort_if_not_scalar($second, '+', false);
                    $output = $first . $second;
                    break;
                }
                // In all other cases, addition must (currently) be numeric, so we abort
                // if the arguments are not numbers or numeric strings.
                self::abort_if_not_scalar($first, '+');
                self::abort_if_not_scalar($second, '+');
                $output = $first + $second;
                break;
            case '-':
                $output = $first - $second;
                break;
            case '<<':
            case '>>':
                if (intval($first) != $first || intval($second) != $second) {
                    self::die('error_bitshift_integer');
                }
                if ($second < 0) {
                    self::die('error_bitshift_negative', $second);
                }
                if ($op === '<<') {
                    $output = (int)$first << (int)$second;
                } else {
                    $output = (int)$first >> (int)$second;
                }
                break;
            case '&':
                if (intval($first) != $first || intval($second) != $second) {
                    self::die('error_bitwand_integer');
                }
                $output = $first & $second;
                break;
            case '^':
                if (intval($first) != $first || intval($second) != $second) {
                    self::die('error_bitwxor_integer');
                }
                $output = $first ^ $second;
                break;
            case '|':
                if (intval($first) != $first || intval($second) != $second) {
                    self::die('error_bitwor_integer');
                }
                $output = $first | $second;
                break;
            case '&&':
                $output = ($first && $second ? 1 : 0);
                break;
            case '||':
                $output = ($first || $second ? 1 : 0);
                break;
            case '<':
                $output = ($first < $second ? 1 : 0);
                break;
            case '>':
                $output = ($first > $second ? 1 : 0);
                break;
            case '==':
                $output = ($first == $second ? 1 : 0);
                break;
            case '>=':
                $output = ($first >= $second ? 1 : 0);
                break;
            case '<=':
                $output = ($first <= $second ? 1 : 0);
                break;
            case '!=':
                $output = ($first != $second ? 1 : 0);
                break;
        }
        // One last safety check: numeric results must not be NAN or INF.
        // This should never be triggered.
        if (is_numeric($output) && (is_nan($output) || is_infinite($output))) {
            self::die('error_evaluation_unknown_nan_inf', $op);
        }
        return $output;
    }

    /**
     * Check whether a given value is numeric and, if desired, meets other criteria like
     * being non-negative or integer etc. If the conditions are not met, the function will
     * throw an Exception. If the value is valid, the function will return a float or int,
     * depending on the given conditions.
     *
     * @param mixed $n
     * @param string $message
     * @param int $additionalcondition
     * @throws Exception
     * @return int|float
     */
    public static function assure_numeric($n, string $message = '', int $additionalcondition = self::NONE) {
        // For compatibility with PHP 7.4: check if it is a string. If it is, remove trailing
        // space before trying to convert to number.
        if (is_string($n)) {
            $n = trim($n);
        }
        if (is_numeric($n) === false) {
            throw new Exception($message);
        }
        if ($additionalcondition & self::NON_NEGATIVE) {
            if ($n < 0) {
                throw new Exception($message);
            }
        }
        if ($additionalcondition & self::NEGATIVE) {
            if ($n >= 0) {
                throw new Exception($message);
            }
        }
        if ($additionalcondition & self::POSITIVE) {
            if ($n <= 0) {
                throw new Exception($message);
            }
        }
        if ($additionalcondition & self::NON_ZERO) {
            if ($n == 0) {
                throw new Exception($message);
            }
        }
        if ($additionalcondition & self::INTEGER) {
            if ($n - intval($n) != 0) {
                throw new Exception($message);
            }
            return intval($n);
        }
        return floatval($n);
    }

    /**
     * Check whether a given array contains only numbers.
     *
     * @param mixed $ar
     * @param bool $acceptempty
     * @return bool
     */
    public static function is_numeric_array($ar, bool $acceptempty = true): bool {
        if (!is_array($ar)) {
            return false;
        }
        if (!$acceptempty && count($ar) === 0) {
            return false;
        }
        foreach ($ar as $element) {
            if (!is_numeric($element)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Throw an Exception, fetching the localized string $identifier from the language file
     * via Moodle's get_string() function.
     *
     * @param string $identifier identifier for the localized string
     * @param string|object|array $a additional (third) parameter passed to get_string
     * @throws Exception
     */
    private static function die(string $identifier, $a = null): void {
        throw new Exception(get_string($identifier, 'qtype_formulas', $a));
    }

}
