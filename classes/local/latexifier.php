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

/**
 * Helper class to convert an expression from RPN notation into LaTeX.
 *
 * @package    qtype_formulas
 * @copyright  2025 Philipp Imhof
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class latexifier {

    public static function latexify(array $tokens): string {
        $stack = [];

        foreach ($tokens as $token) {
            // Contants and variables go straight to the stack.
            if (in_array($token->type, [token::CONSTANT, token::VARIABLE])) {
                $stack[] = ['content' => $token->value, 'precedence' => PHP_INT_MAX];
                continue;
            }
            // Numbers only need special treatment, if they are in scientific notation.
            if ($token->type === token::NUMBER) {
                if (is_null($token->metadata)) {
                    $stack[] = ['content' => $token->value, 'precedence' => PHP_INT_MAX];
                    continue;
                }
                $mantissa = $token->metadata['mantissa'];
                $exponent = $token->metadata['exponent'];
                $stack[] = [
                    'content' => $mantissa . '\cdot 10^{' . $exponent . '}',
                    'precedence' => shunting_yard::get_precedence('*'),
                ];
            }

            // Operators take arguments from the stack, stick them together and
            // build some output.
            if ($token->type === token::OPERATOR) {
                $op = $token->value;
                $second = array_pop($stack);
                // For unary operators... FIXME
                if (in_array($op, ['_', '!', '~'])) {
                    $new = self::translate_operator($op) . $second['content'];
                }
                // For binary operators... FIXME
                if (in_array($op, ['+', '-', '*', '/', '%', '**', '^', '==', '<=', '>=', '!='])) {
                    $first = array_pop($stack);
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

    private static function build_onearg_wrapping_function($function, $argument): string {
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

    private static function build_general_function(string $function, array $args): string {
        $arglist = '';
        foreach ($args as $arg) {
            $arglist .= $arg['content'] . ', ';
        }

        return self::translate_function($function) . '\left(' . substr($arglist, 0, -2) . '\right)';

    }

    private static function build_binary_part(string $operator, $first, $second): string {
        // Division is special, because the fraction command cannot just be inserted between the
        // two arguments. Also, the arguments never need parentheses.
        if ($operator === '/') {
            return self::build_frac($first, $second);
        }

        // Exponentiation is special, because the exponent has to be wrapped in braces, unless it
        // consists of only one character. Also, if the base is itself a power, it must be wrapped
        // in braces.
        if ($operator === '**') {
            if ($first['precedence'] == shunting_yard::get_precedence($operator)) {
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

    private static function wrap_in_parens(string $expression): string {
        return '\left(' . $expression . '\right)';
    }

    private static function translate_operator(string $operator): string {
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
     * FIXME
     * Note that we use \mathrm{} instead of \operatorname{}, because the latter is only
     * available, if the AMS extension is available and active, which we cannot control.
     *
     * @param string $funcname
     * @return string
     */
    private static function translate_function(string $funcname): string {
        switch ($funcname) {
            case 'acos':
            case 'asin':
            case 'atan':
                return '\\' . str_replace('a', 'arc', $funcname);
            case 'acosh':
            case 'asinh':
            case 'atanh':
                return '\mathrm{' . str_replace('a', 'ar', $funcname) . '}';
            case 'cos':
            case 'sin':
            case 'tan':
            case 'cosh':
            case 'sinh':
            case 'tanh':
            case 'ln':
            case 'exp':
                return '\\' . $funcname;
            case 'log10':
                return '\lg';
            case 'atan2':
                return '\mathrm{arctan2}';
            default:
                return '\mathrm{' . $funcname . '}';
        }
    }

    private static function build_frac($numerator, $denominator): string {
        return '\frac{' . $numerator['content'] . '}{' . $denominator['content'] . '}';
    }

}
