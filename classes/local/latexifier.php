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

use qtype_formulas;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/question/type/formulas/questiontype.php');

/**
 * Helper class to convert an expression from RPN notation or a unit expression into LaTeX.
 *
 * @package    qtype_formulas
 * @copyright  2025 Philipp Imhof
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class latexifier {
    /**
     * Transform a unit expression as returned from answer_unit_conversion::parse_unit() into LaTeX
     * code.
     *
     * @param array $units array containing the unit symbol (e. g. m or s) and the exponent
     * @return string LaTeX code
     */
    public static function latexify_unit(array $units): string {
        $numeratorunits = [];
        $denominatorunits = [];

        // Iterate over all units and store them in the numerator or denominator, according to their
        // exponent.
        foreach ($units as $unit => $exponent) {
            $target = ($exponent > 0 ? 'numeratorunits' : 'denominatorunits');
            ${$target}[] = '\mathrm{' . $unit . '}^{' . abs($exponent) . '}';
        }

        // Generate the numerator and the denominator as products of units.
        $numerator = join('\cdot', $numeratorunits);
        $denominator = join('\cdot', $denominatorunits);

        // Remove exponent 1 everywhere, if needed.
        $numerator = str_replace('^{1}', '', $numerator);
        $denominator = str_replace('^{1}', '', $denominator);

        // If we do not have a denominator, just return the numerator.
        if (empty($denominator)) {
            return $numerator;
        }

        // If we do not have a numerator, put 1 over the denominator.
        if (empty($numerator)) {
            return '\frac{1}{' . $denominator . '}';
        }

        // Otherwise, return a fraction with numerator and denominator.
        return '\frac{' . $numerator . '}{' . $denominator . '}';
    }

    /**
     * Transform an expression in RPN notation as returned from the answer_parser into LaTeX
     * code.
     *
     * @param array $tokens the tokens in RPN notation, as return from answer_parser
     * @return string LaTeX code
     */
    public static function latexify(array $tokens): string {
        $stack = [];

        foreach ($tokens as $token) {
            // Contants and variables go straight to the stack.
            if (in_array($token->type, [token::CONSTANT, token::VARIABLE])) {
                $stack[] = ['content' => $token->value, 'precedence' => PHP_INT_MAX];
                continue;
            }
            // Numbers only need special treatment, if they are in scientific notation.
            // Also, we make sure that the user's decimal separator is used.
            if ($token->type === token::NUMBER) {
                if (is_null($token->metadata)) {
                    $content = qtype_formulas::format_float($token->value, true);
                    $precedence = PHP_INT_MAX;
                } else {
                    $mantissa = $token->metadata['mantissa'];
                    $exponent = $token->metadata['exponent'];
                    $content = qtype_formulas::format_float($mantissa, true) . '\cdot 10^{' . $exponent . '}';
                    $precedence = shunting_yard::get_precedence('*');
                }

                $stack[] = ['content' => $content, 'precedence' => $precedence];
            }

            // Operators take arguments from the stack, stick them together and
            // build some output.
            if ($token->type === token::OPERATOR) {
                $op = $token->value;
                $second = array_pop($stack);
                // Unary operators must be translated and can then be prepended their argument.
                if (in_array($op, ['_', '!', '~'])) {
                    $new = self::translate_operator($op) . $second['content'];
                }
                // For binary operators, we must first fetch the other argument and then send
                // everything to a dedicated function to build the next expression.
                if (in_array($op, ['+', '-', '*', '/', '%', '**', '^', '==', '<=', '>=', '!='])) {
                    $first = array_pop($stack);
                    // The stack should not be empty, but it might be in case of certain syntax errors.
                    // In that case, we want to avoid dropping out with an error message, because that
                    // could block the student from continuing a quiz. Instead, we create an empty token
                    // and will (probably) finish by returning some bad output following the principle
                    // "garbe in, garbage out".
                    if ($first === null) {
                        $first = ['content' => '', 'precedence' => PHP_INT_MAX];
                    }
                    $new = self::build_binary_part($op, $first, $second);
                }
                $stack[] = ['content' => $new, 'precedence' => shunting_yard::get_precedence($op)];
            }

            if ($token->type === token::FUNCTION) {
                $new = '';
                $numargstoken = array_pop($stack);
                $numargs = $numargstoken['content'];
                $funcname = $token->value;
                // The log() function can be used with two arguments. If there is only one argument,
                // it is considered to be the natural logarithm. If we have two arguments, we
                // "abuse" the onearg method.
                if ($funcname === 'log') {
                    if ($numargs == 1) {
                        $funcname = 'ln';
                    } else {
                        $base = array_pop($stack);
                        $arg = array_pop($stack);
                        $new = self::build_onearg_wrapping_function(
                            'log_{' . $base['content'] . '}',
                            $arg,
                        );
                    }
                }
                // The pow() function is just a legacy form for the exponentiation operator.
                if ($funcname === 'pow') {
                    $second = array_pop($stack);
                    $first = array_pop($stack);
                    $new = self::build_binary_part('**', $first, $second);
                }
                // The ncr() function must be written as a binomial coefficient.
                if ($funcname === 'ncr') {
                    $second = array_pop($stack);
                    $first = array_pop($stack);
                    $new = '\binom{' . $first['content'] . '}{' . $second['content'] . '}';
                }
                // The fact(), abs(), ceil() and floor() function are written by putting their single
                // argument between delimiters. Also, we use the same method for sqrt(), because it
                // will be \sqrt{...} instead of \sqrt(...) in LaTeX.
                if (in_array($funcname, ['sqrt', 'fact', 'abs', 'ceil', 'floor'])) {
                    $arg = array_pop($stack);
                    $new = self::build_onearg_wrapping_function($funcname, $arg);
                }
                // If we still haven't built the new string, we use a general method for functions.
                if (empty($new)) {
                    $args = [];
                    for ($i = 0; $i < $numargs; $i++) {
                        $args[] = array_pop($stack);
                        $args = array_reverse($args);
                    }
                    $new = self::build_general_function($funcname, $args);
                }
                $stack[] = ['content' => $new, 'precedence' => PHP_INT_MAX];
            }
        }

        $output = '';
        foreach ($stack as $part) {
            $output .= $part['content'] . ' ';
        }

        return substr($output, 0, -1);
    }

    /**
     * Generate LaTeX code for functions that "enclose" their single argument, e. g. abs(x) which
     * becomes |x|.
     *
     * @param string $function function name
     * @param array $argument argument, associative array with 'content' and 'precedence'
     * @return string LaTeX code
     */
    protected static function build_onearg_wrapping_function(string $function, array $argument): string {
        // This function can be "abused" to build the logarithm, so we check that first.
        if (substr($function, 0, 3) === 'log') {
            $ldelim = '\\' . $function . '\left(';
            $rdelim = '\right)';
        }
        switch ($function) {
            case 'sqrt':
                $ldelim = '\sqrt{';
                $rdelim = '}';
                break;
            case 'fact':
                if ($argument['precedence'] < PHP_INT_MAX) {
                    $argument['content'] = '\left(' . $argument['content'] . '\right)';
                }
                $ldelim = '';
                $rdelim = '!';
                break;
            case 'abs':
                $ldelim = '\left|';
                $rdelim = '\right|';
                break;
            case 'ceil':
                $ldelim = '\lceil';
                $rdelim = '\rceil';
                break;
            case 'floor':
                $ldelim = '\lfloor';
                $rdelim = '\rfloor';
                break;
        }

        return $ldelim . ' ' . $argument['content'] . ' ' . $rdelim;
    }

    /**
     * Generate LaTeX code for a general function like sin, cos and the like.
     *
     * @param string $function function name
     * @param array $args arguments for the function
     * @return string LaTeX code
     */
    protected static function build_general_function(string $function, array $args): string {
        $arglist = '';
        foreach ($args as $arg) {
            $arglist .= $arg['content'] . ', ';
        }

        return self::translate_function($function) . '\left(' . substr($arglist, 0, -2) . '\right)';
    }

    /**
     * Build a binary expression, e. g. 1 + 2.
     *
     * @param string $operator the operator
     * @param array $first first argument, associative array with 'content' and 'precedence'
     * @param array $second second argument, associative array with 'content' and 'precedence'
     * @return string
     */
    protected static function build_binary_part(string $operator, array $first, array $second): string {
        // Division is special, because the fraction command cannot just be inserted between the
        // two arguments. Also, the arguments never need parentheses.
        if ($operator === '/') {
            return self::build_frac($first, $second);
        }

        // Exponentiation is special, because the exponent has to be wrapped in braces, unless it
        // consists of only one character. Also, if the base is itself a power, it must be wrapped
        // in braces.
        if ($operator === '**') {
            if ($first['precedence'] <= shunting_yard::get_precedence($operator)) {
                $first['content'] = '\left(' . $first['content'] . '\right)';
            }
            $second['content'] = '{' . $second['content'] . '}';
            return $first['content'] . self::translate_operator($operator) . $second['content'];
        }

        // If operator precedence is greater than precedence of argument, we need to wrap it in
        // parens.
        if ($first['precedence'] < shunting_yard::get_precedence($operator)) {
            $first['content'] = self::wrap_in_parens($first['content']);
        }
        if ($second['precedence'] < shunting_yard::get_precedence($operator)) {
            $second['content'] = self::wrap_in_parens($second['content']);
        }

        return $first['content'] . self::translate_operator($operator) . $second['content'];
    }

    /**
     * Wrap an expression in parentheses.
     *
     * @param string $expression expression to be wrapped
     * @return string LaTeX code
     */
    protected static function wrap_in_parens(string $expression): string {
        return '\left(' . $expression . '\right)';
    }

    /**
     * Translate operators into the corresponding LaTeX macro.
     *
     * @param string $operator operator symbol, e. g. '*'
     * @return string LaTeX code
     */
    protected static function translate_operator(string $operator): string {
        switch ($operator) {
            case '_':
                return '-';
            case '*':
                return '\cdot ';
            case '**':
                return '^';
            case '%':
                return '\bmod ';
            case '>=':
                return '\geq ';
            case '<=':
                return '\leq ';
            case '!=':
                return '\neq ';
            case '==':
                return '=';
            default:
                return $operator;
        }
    }

    /**
     * Convert a function name into its LaTeX equivalent, e. g. sqrt to \sqrt or asin to \arcsin.
     * The \operatorname command is avaible with Moodle's default settings. Using \mathrm instead is
     * not a good choice, because spacing will be wrong in certain cases.
     *
     * @param string $funcname name of the function
     * @return string LaTeX equivalent of the function
     */
    protected static function translate_function(string $funcname): string {
        switch ($funcname) {
            case 'acos':
            case 'asin':
            case 'atan':
                return '\arc' . substr($funcname, 1);
            case 'acosh':
            case 'asinh':
            case 'atanh':
                return '\operatorname{ar' . substr($funcname, 1) . '}';
            case 'cos':
            case 'sin':
            case 'tan':
            case 'cosh':
            case 'sinh':
            case 'tanh':
            case 'ln':
            case 'lg':
            case 'exp':
                return '\\' . $funcname;
            case 'log10':
                return '\lg';
            case 'atan2':
                return '\operatorname{arctan2}';
            default:
                return '\operatorname{' . $funcname . '}';
        }
    }

    /**
     * Generate LaTeX code for a fraction.
     *
     * @param array $numerator numerator, associative array with keys 'content' and 'precendence'
     * @param array $denominator denominator, associative array with keys 'content' and 'precendence'
     * @return string
     */
    protected static function build_frac(array $numerator, array $denominator): string {
        return '\frac{' . $numerator['content'] . '}{' . $denominator['content'] . '}';
    }
}
