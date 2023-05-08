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
 * Additional functions qtype_formulas
 *
 * @package    qtype_formulas
 * @copyright  2022 Philipp Imhof
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace qtype_formulas;
use Exception;

/*

TODO:
- diff (special function, cannot be used in evaluation context)

*/

class functions {
    /* function name => [min params, max params] */
    const FUNCTIONS = [
        'binomialcdf' => [3, 3],
        'binomialpdf' => [3, 3],
        'concat' => [2, INF],
        'fact' => [1, 1],
        'fill' => [2, 2],
        'fmod' => [2, 2],
        'fqversionnumber' => [0, 0],
        'gcd' => [2, 2],
        'inv' => [1, 1],
        'join' => [2, INF],
        'lcm' => [2, 2],
        'len' => [1, 1],
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
            if (!array_key_exists($what, $allfunctions)) {
                throw new Exception(("evaluation error: '$what' is not a legal first argument for the map() function"));
            }
            // Fetch the number of arguments for the given function name.
            $min = $allfunctions[$what][0];
            $max = $allfunctions[$what][1];
            if ($min < 1) {
                throw new Exception(("the function '$what' cannot be used with map(), because it accepts no arguments"));
            }
            if ($min > 2) {
                throw new Exception(("the function '$what' cannot be used with map(), because it expects more than two arguments"));
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
                throw new Exception("when using map() with the unary $type '$what', only one list is accepted");
            }
            if (!is_array($first)) {
                throw new Exception("when using map() with the unary $type '$what', the argument must be a list");
            }
        }

        // Check arguments for binary operators or functions: we expect (a) one scalar and one list or
        // (b) two lists of the same size.
        if ($usebinaryop || $usebinaryfunc) {
            $type = $usebinaryop ? 'operator' : 'function';
            if ($second === null) {
                throw new Exception("when using map() with the binary $type '$what', two arguments are expected");
            }
            if (is_scalar($first) && is_scalar($second)) {
                throw new Exception("when using map() with the binary $type '$what', at least one argument must be a list");
            }
            if (is_array($first) && is_array($second) && count($first) != count($second)) {
                throw new Exception("when using map() with two lists, they must both have the same size");
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
            throw new Exception('evaluation error in map(): ' . $e->getMessage());
        }

        return $result;
    }

    public static function inv($list): array {
        // First, we check that the array contains only numbers. If necessary,
        // floats will be converted to integers by truncation. Note: number tokens
        // always store their value as float, so we have to apply the conversion to
        // all numbers, because we cannot know whether they really are of type float or int.
        foreach ($list as $entry) {
            $value = $entry->value;
            if (!is_float($value)) {
                throw new Exception("inv() expects all elements of the list to be integers, found '{$entry->value}'");
            }
            $entry->value = intval($value);
        }

        // Now we check that the same number does not appear twice.
        $tmp = array_unique($list);
        if (count($tmp) !== count($list)) {
            throw new Exception('when using inv(), the list must not contain the same number multiple times');
        }
        // Finally, we make sure the numbers are consecutive from 0 to n-1 or from 1 to n with
        // n being the number of elements in the list. We can use min() and max(), because the
        // token has a __tostring() method and numeric strings are compared numerically.
        $min = min($list);
        $max = max($list);
        if ($min->value > 1 || $min->value < 0) {
            throw new Exception('when using inv(), the smallest number in the list must be 0 or 1');
        }
        if ($max->value - $min->value + 1 !== count($list)) {
            throw new Exception('when using inv(), the numbers in the list must be consecutive');
        }

        // Create array from minimum to maximum value and then use the given list as the sort order.
        $result = [];
        for ($i = $min->value; $i <= $max->value; $i++) {
            $result[] = new token(token::NUMBER, $i);
        }
        uksort($result, function($a, $b) use ($list) {
            return $list[$a] <=> $list[$b];
        });

        // Forget about the keys and re-index the sorted array from 0.
        return array_values($result);
    }

    public static function concat(...$arrays): array {
        $result = [];

        // Iterate over each array ...
        foreach ($arrays as $array) {
            if (!is_array($array)) {
                throw new Exception("concat() expects its arguments to be lists, found '$array'");
            }
            // ... and over each element of every array.
            foreach ($array as $element) {
                $result[] = $element;
            }
        }

        return $result;
    }

    public static function sort($tosort, $order = null): array {
        // The first argument must be an array.
        if (!is_array($tosort)) {
            throw new Exception('sort() expects it first argument to be a list');
        }

        // If we have one list only, we duplicate it.
        if ($order === null) {
            $order = $tosort;
        }

        // If two arguments are given, the second must be an array.
        if (!is_array($order)) {
            throw new Exception('when calling sort() with two arguments, they must both be lists');
        }

        // If we have two lists, they must have the same number of elements.
        if (count($tosort) !== count($order)) {
            throw new Exception('when calling sort() with two lists, they must have the same size');
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
     * wrapper for the poly() function which can be invoked in many different ways:
     * - list of numbers => polynomial with variable x
     * - number => force + sign if number > 0
     * - string, number => combine
     * - string, list of numbers => polynomial with variable from string
     * - list of strings, list of numbers => linear combination
     * - string, number, string => combine them and, if appropriate, force + sign
     * - string, list of numbers, string => polynomial (one var) using third argument as separator (e.g. &)
     * - list of strings, list of numbers, string => linear combination using third argument as separator
     * - list of numbers, string => polynomial with x using third argument as separator
     * will call the poly_formatter() accordingly
     */
    public static function poly(...$args) {
        $numargs = count($args);
        switch ($numargs) {
            case 1:
                $argument = $args[0];
                // For backwards compatibility: if called with just a list of numbers, use x as variable.
                if (is_array($argument)) {
                    return self::poly_formatter('x', $argument);
                }
                // If called with just a number, force the plus sign (if the number is positive) to be shown.
                // Basically, there is no other reason one would call this function with just one number.
                return self::poly_formatter('', $argument, '+');
            case 2:
                $first = $args[0];
                $second = $args[1];
                // If called with a string and one number, combine them.
                if (is_string($first) && is_float($second)) {
                    return self::poly_formatter([$first], [$second]);
                }
                // If called with a list of numbers and a string, use x as default variable for the polynomial and use the
                // third argument as a separator, e. g. for a usage in LaTeX matrices or array-like constructions.
                if (is_array($first) && is_string($second)) {
                    return self::poly_formatter('x', $first, '', $second);
                }
                // All other invocations with two arguments will automatically be handled correctly.
                return self::poly_formatter($args[0], $args[1]);
            case 3:
                $first = $args[0];
                $second = $args[1];
                $third = $args[2];
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
     * format a polynomial to be display with LaTeX / MathJax
     * can also be used to force the plus sign for a single number
     * can also be used for arbitrary linear combinations
     *
     * @param mixed $variables one variable (as a string) or a list of variables (array of strings)
     * @param mixed $coefficients one number or an array of numbers to be used as coefficients
     * @param string $forceplus symbol to be used for the normally invisible leading plus, optional
     * @param string $additionalseparator symbol to be used as separator between the terms, optional
     * @return string  the formatted string
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
            $variables = array();
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

    public static function sublist($list, $indices): array {
        if (!is_array($list) || !is_array($indices)) {
            throw new Exception('sublist() expects its arguments to be lists');
        }

        $result = [];
        foreach ($indices as $i) {
            $i = $i->value;
            if (!is_numeric($i)) {
                throw new Exception("sublist() expects the indices to be integers, found '$i'");
            }
            $i = intval($i);
            if ($i > count($list) - 1 || $i < 0) {
                throw new Exception("index $i out of range in sublist()");
            }
            $result[] = $list[$i];
        }
        return $result;
    }

    public static function sigfig($number, $precision): string {
        if (!is_numeric($number)) {
            throw new Exception('sigfig() expects its first argument to be a number');
        }
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

    public static function len($arg): int {
        if (is_array($arg)) {
            return count($arg);
        }
        if (is_string($arg)) {
            return strlen($arg);
        }
        throw new Exception('len() expects a list or a string');
    }

    public static function fill($count, $value): array {
        // If $count is invalid, it will be converted to 0 which will then lead to an error.
        $count = intval($count);
        if ($count < 1) {
            throw new Exception('fill() expects the first argument to be a positive integer');
        }
        return array_fill(0, $count, token::wrap($value));
    }

    public static function sum($array): float {
        $result = 0;
        foreach ($array as $token) {
            $value = $token->value;
            if (!is_numeric($value)) {
                throw new Exception('sum() expects a list of numbers');
            }
            $result += floatval($value);
        }
        return $result;
    }

    public static function str($value): string {
        if (!is_scalar($value)) {
            throw new Exception('str() expects a scalar argument, e.g. a number');
        }
        return strval($value);
    }

    public static function join($separator, ...$values): string {
        $result = [];
        array_walk_recursive($values, function($val, $idx) use (&$result) {
            $result[] = $val;
        });
        return implode($separator, $result);
    }

    public static function pick($index, ...$data) {
        // If the index is a float, it will be truncated. This is needed for
        // backward compatibility.
        $index = intval($index);

        $count = count($data);

        // The $data parameter will always be an array and will contain
        // - one single array for the pick(index, list) usage
        // - the various values for the pick(index, val1, val2, val3, ...) usage.
        if ($count === 1) {
            if (!is_array($data[0])) {
                throw new Exception("when called with two arguments, pick() expects the second parameter to be a list");
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

    public static function shuffle(array $ar): array {
        shuffle($ar);
        return $ar;
    }

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
     * Calculate the factorial n! of a number.
     *
     * @param int $n the number
     * @return int
     */
    public static function fact(int $n): int {
        $n = (int) $n;
        if ($n < 2) {
            return 1;
        }
        $result = 1;
        for ($i = $n; $i > 1; $i--) {
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
     * calculate standard normal cumulative distribution by approximation
     * using Simpson's rule, accurate to ~5 decimal places
     *
     * @param float $z value
     * @return float probability for a value of $z or less under standard normal distribution
     */
    public static function stdnormcdf(float $z): float {
        if ($z < 0) {
            return 1 - self::stdnormcdf(-$z);
        }
        $n = max(10, floor(10 * $z));
        $h = $z / $n;
        $res = self::stdnormpdf(0) + self::stdnormpdf($z);
        for ($i = 1; $i < $n; $i++) {
            $res += 2 * self::stdnormpdf($i * $h);
            $res += 4 * self::stdnormpdf(($i - 0.5) * $h);
        }
        $res += 4 * self::stdnormpdf(($n - 0.5) * $h);
        $res *= $h / 6;
        return $res + 0.5;
    }

    /**
     * calculate normal cumulative distribution by approximation
     * using Simpson's rule, accurate to ~5 decimal places
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
    public static function modpow(int $a, int $b, int $m): int {
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
     * calculate the multiplicative inverse of $a modulo $m using the
     * extended euclidean algorithm
     *
     * @param int $a the number whose inverse is to be found
     * @param int $m the modulus
     * @return int the result or 0 if the inverse does not exist
     */
    public static function modinv(int $a, int $m): int {
        $origm = $m;
        if (gcd($a, $m) != 1) {
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
    public static function fmod(float $x, float $m): float {
        if ($m === 0) {
            // FIXME: revise error message.
            throw new Exception(get_string('error_eval_numerical', 'qtype_formulas'));
        }
        return $x - $m * floor($x / $m);
    }

    /**
     * Calculate the probability of exactly $x successful outcomes for
     * $n trials under a binomial distribution with a probability of success
     * of $p.
     *
     * @param int $n number of trials
     * @param float $p probability of success for each trial
     * @param int $x number of successful outcomes
     *
     * @return float probability for exactly $x successful outcomes
     * @throws Exception
     */
    public static function binomialpdf(int $n, float $p, int $x): float {
        // Probability must be 0 <= p <= 1.
        if ($p < 0 || $p > 1) {
            // FIXME: revise error message.
            throw new Exception(get_string('error_eval_numerical', 'qtype_formulas'));
        }
        // Number of successful outcomes must be at least 0 and at most number of trials.
        if ($x < 0 || $x > $n) {
            // FIXME: revise error message.
            throw new Exception(get_string('error_eval_numerical', 'qtype_formulas'));
        }
        return self::ncr($n, $x) * $p ** $x * (1 - $p) ** ($n - $x);
    }

    /**
     * Calculate the probability of up to $x successful outcomes for
     * $n trials under a binomial distribution with a probability of success
     * of $p, known as the cumulative distribution function.
     *
     * @param int $n number of trials
     * @param float $p probability of success for each trial
     * @param int $x number of successful outcomes
     *
     * @return float probability for up to $x successful outcomes
     * @throws Exception
     */
    public static function binomialcdf(int $n, float $p, int $x): float {
        // Probability must be 0 <= p <= 1.
        if ($p < 0 || $p > 1) {
            // FIXME: revise error message.
            throw new Exception(get_string('error_eval_numerical', 'qtype_formulas'));
        }
        // Number of successful outcomes must be at least 0 and at most number of trials.
        if ($x < 0 || $x > $n) {
            // FIXME: revise error message.
            throw new Exception(get_string('error_eval_numerical', 'qtype_formulas'));
        }
        $res = 0;
        for ($i = 0; $i <= $x; $i++) {
            $res += self::binomialpdf($n, $p, $i);
        }
        return $res;
    }

    /**
     * Calculate the number of permutations when taking $r elements
     * from a set of $n elements.
     *
     * @param int $n the number of elements to choose from
     * @param int $r the number of elements to be rearranged
     * @return int
     */
    public static function npr(int $n, int $r): int {
        $n = (int)$n;
        $r = (int)$r;
        if ($r == 0 && $n == 0) {
            return 0;
        }
        return self::ncr($n, $r) * self::fact($r);
    }

    /**
     * Calculate the number of combination when taking $r elements
     * from a set of $n elements.
     *
     * @param int $n the number of elements to choose from
     * @param int $r the number of elements to be chosen
     * @return int
     */
    public static function ncr(int $n, int $r): int {
        $n = (int)$n;
        $r = (int)$r;
        if ($r > $n) {
            return 0;
        }
        if (($n - $r) < $r) {
            return self::ncr($n, ($n - $r));
        }
        $return = 1;
        for ($i = 0; $i < $r; $i++) {
            $return *= ($n - $i) / ($i + 1);
        }
        return $return;
    }

    /**
     * Calculate the greatest common divisor of two integers $a and $b
     * via the Euclidean algorithm.
     *
     * @param int $a first number
     * @param int $b second number
     * @return int
     */
    public static function gcd($a, $b) {
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
            $rest = (int) $a % $b;
            $a = $b;
            $b = $rest;
        } while ($rest > 0);
        return $a;
    }

    /**
     * Calculate the least (non-negative) common multiple of two integers $a and $b
     * via the Euclidean algorithm.
     *
     * @param int $a first number
     * @param int $b second number
     * @return int
     */
    public static function lcm($a, $b) {
        if ($a == 0 || $b == 0) {
            return 0;
        }
        return $a * $b / self::gcd($a, $b);
    }

    private static function abort_if_not_scalar($value, string $who = '', bool $enforcenumeric = true): void {
        $message = 'expected ';
        if ($who !== '') {
            $message = "$who expects ";
        }
        $message = $message . ($enforcenumeric ? 'a number' : 'a scalar value');

        if (!is_scalar($value)) {
            throw new Exception($message);
        }
        $isnumber = is_float($value) || is_int($value);
        if ($enforcenumeric && !$isnumber) {
            throw new Exception("$message, found '$value'");
        }
    }

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
                    throw new Exception('power 0^0 is not defined');
                }
                if ($first == 0 && $second < 0) {
                    throw new Exception('division by zero is not defined, so base cannot be zero for negative exponents');
                }
                if ($first < 0 && intval($second) != $second) {
                    throw new Exception('base cannot be negative with fractional exponent');
                }
                $output = $first ** $second;
                break;
            case '*':
                $output = $first * $second;
                break;
            case '/':
            case '%':
                if ($second == 0) {
                    throw new Exception('division by zero is not defined');
                }
                if ($op === '/') {
                    $output = $first / $second;
                } else {
                    $output = $first % $second;
                }
                break;
            case '+':
                // If at least one operand is a string, we use concatenation instead
                // of addition.
                if (is_string($first) || is_string($second)) {
                    self::abort_if_not_scalar($first, 'string concatenation', false);
                    self::abort_if_not_scalar($second, 'string concatenation', false);
                    $output = $first . $second;
                    break;
                }
                // In all other cases, addition must (currently) be numeric, so we abort
                // if the arguments are not numbers.
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
                    throw new Exception('bit shift operator should only be used with integers');
                }
                if ($first < 0) {
                    throw new Exception("bit shift by negative number $first is not allowed");
                }
                if ($op === '<<') {
                    $output = (int)$first << (int)$second;
                } else {
                    $output = (int)$first >> (int)$second;
                }
                break;
            case '&':
                if (intval($first) != $first || intval($second) != $second) {
                    throw new Exception('bitwise AND should only be used with integers');
                }
                $output = $first & $second;
                break;
            case '^':
                if (intval($first) != $first || intval($second) != $second) {
                    throw new Exception('bitwise XOR should only be used with integers');
                }
                $output = $first ^ $second;
                break;
            case '|':
                if (intval($first) != $first || intval($second) != $second) {
                    throw new Exception('bitwise OR should only be used with integers');
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
            throw new Exception('unknown evaluation error');
        }
        return $output;
    }
}
