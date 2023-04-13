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

namespace qtype_formulas;
use Throwable;

/**
 * Evaluator for qtype_formulas
 *
 * @package    qtype_formulas
 * @copyright  2022 Philipp Imhof
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


/*

TODO:

*/

class evaluator {
    /* function name => [min params, max params] */
    const PHPFUNCTIONS = [
        'abs' => [1, 1],
        'acos' => [1, 1],
        'acosh' => [1, 1],
        'asin' => [1, 1],
        'asinh' => [1, 1],
        'atan2' => [2, 2],
        'atan' => [1, 1],
        'atanh' => [1, 1],
        'base_convert' => [3, 3],
        'bindec' => [1, 1],
        'ceil' => [1, 1],
        'cos' => [1, 1],
        'cosh' => [1, 1],
        'decbin' => [1, 1],
        'dechex' => [1, 1],
        'decoct' => [1, 1],
        'deg2rad' => [1, 1],
        'exp' => [1, 1],
        'expm1' => [1, 1],
        'fdiv' => [2, 2],
        'floor' => [1, 1],
        'fmod' => [2, 2], // FIXME: own implementation!
        'hexdec' => [1, 1],
        'hypot' => [2, 2],
        'intdiv' => [2, 2],
        'is_finite' => [1, 1],
        'is_infinite' => [1, 1],
        'is_nan' => [1, 1],
        'log10' => [1, 1],
        'log1p' => [1, 1],
        'log' => [1, 1],
        'max' => [1, INF],
        'min' => [1, INF],
        'octdec' => [1, 1],
        'pow' => [2, 2],
        'rad2deg' => [1, 1],
        'round' => [2, 2],
        'sin' => [1, 1],
        'sinh' => [1, 1],
        'sqrt' => [1, 1],
        'tan' => [1, 1],
        'tanh' => [1, 1],
    ];

    private $variableslist = [];

    private $constants = [
        'π' => M_PI,
    ];

    private array $stack = [];

    /**
     * FIXME Undocumented function
     *
     */
    public function __construct() {

    }

    /**
     * Stop evaluating and indicate the human readable position (row/column) where the error occurred.
     *
     * @param string $message error message
     * @throws Exception
     */
    private function die(string $message, token $offendingtoken): never {
        throw new \Exception($offendingtoken->row . ':' . $offendingtoken->column . ':' . $message);
    }

    private function at_least_on_stack(int $n): bool {
        return count($this->stack) >= $n;
    }

    public function evaluate(expression $expression) {
        foreach ($expression->body as $token) {
            $type = $token->type;
            $value = $token->value;

            $isliteral = ($type & token::ANY_LITERAL);
            $isopening = ($type === token::OPENING_BRACE || $type === token::OPENING_BRACKET);

            // Many tokens go directly to the stack.
            if ($isliteral || $isopening) {
                $this->stack[] = $token;
                continue;
            }

            // Constants are resolved and sent to the stack.
            if ($type === token::CONSTANT) {
                $this->stack[] = $this->resolve_constant($token);
                continue;
            }



            if ($type === token::OPERATOR) {
                if ($this->is_unary_operator($token)) {
                    $this->stack[] = $this->execute_unary_operator($token);
                }
                if ($this->is_binary_operator($token)) {
                    $this->stack[] = $this->execute_binary_operator($token);
                }
                if ($value === '%%ternary') {
                    $this->stack[] = $this->execute_ternary_operator();
                }
                if ($value === '%%arrayindex') {
                    $this->stack[] = $this->fetch_array_element();
                }
                if ($value === '%%setbuild' || $value === '%%arraybuild') {
                    $this->stack[] = $this->build_set_or_array($value);
                }
                if ($value === '%%rangebuild') {
                    $elements = $this->build_range();
                    array_push($this->stack, ...$elements);
                }
            }

            if ($type === token::FUNCTION) {
                $this->stack[] = $this->execute_function($token);
            }

        }
        return $this->stack;
    }

    private function fetch_array_element() {
        $indextoken = array_pop($this->stack);
        $nexttoken = array_pop($this->stack);

        // FIXME: if index is not numeric, try to convert to number
        if ($indextoken->type !== token::NUMBER) {
            $this->die("evaluation error: expected numerical index, found '{$indextoken->value}'", $indextoken);
        }

        // FIXME: check if index is int, otherwise truncate

        if ($nexttoken->type !== token::OPENING_BRACKET) {
            $this->die('evaluation error: only one index supported when accessing array elements', $indextoken);
        }

        $arraytoken = array_pop($this->stack);

        if (!in_array($arraytoken->type, [token::LIST, token::STRING])) {
            // FIXME: change error message?
            $this->die('evaluation error: trying to access array offset on a scalar value', $nexttoken);
        }

        // FIXME: check if out of range
        $element = $arraytoken->value[intval($indextoken->value)];
        // different return value for array or string access
        return $element;
    }

    private function build_range() {
        // Pop the number of parts. We generated it ourselves, so we know it will be 2 or 3.
        $parts = array_pop($this->stack)->value;

        $step = 1;
        // If we have 3 parts, extract the step size. Conserve the token in case of an error.
        if ($parts === 3) {
            $steptoken = array_pop($this->stack);
            $step = $steptoken->value;
        }

        // Step must not be zero.
        if ($step === 0) {
            $this->die('syntax error: step size of a range cannot be zero', $steptoken);
        }

        // Fetch start and end of the range. Conserve token for the end value, in case of an error.
        $endtoken = array_pop($this->stack);
        $end = $endtoken->value;
        $start = array_pop($this->stack)->value;

        if ($start === $end) {
            $this->die('syntax error: start end end of range must not be equal', $endtoken);
        }

        if (($end - $start) * $step < 0) {
            if ($parts === 3) {
                $this->die("evaluation error: range from $start to $end with step $step will be empty", $steptoken);
            }
            $step = -$step;
        }

        $result = [];
        $numofsteps = ($end - $start) / $step;
        // Choosing multiplication of step instead of repeated addition for better numerical accuracy.
        for ($i = 0; $i < $numofsteps; $i++) {
            $result[] = new token(token::NUMBER, $start + $i * $step);
        }
        return $result;
    }

    private function build_set_or_array(string $type) {
        // FIXME: remove type / conserve only value for elements ?
        if ($type === '%%setbuild') {
            $delimitertype = token::OPENING_BRACE;
            $outputtype = token::SET;
        } else {
            $delimitertype = token::OPENING_BRACKET;
            $outputtype = token::LIST;
        }
        $elements = [];
        $head = end($this->stack);
        while ($head !== false) {
            if ($head->type === $delimitertype) {
                array_pop($this->stack);
                break;
            }
            $elements[] = array_pop($this->stack);
            $head = end($this->stack);
        }
        // Return reversed list, because the stack ist LIFO.
        return new token($outputtype, array_reverse($elements));
    }

    private function is_unary_operator($token) {
        return in_array($token->value, ['_', '!', '~']);
    }

    private function needs_numeric_input($token) {
        $operators = ['**', '*', '/', '%', '-', '<<', '>>', '&', '^', '|', '&&', '||'];
        return in_array($token->value, $operators);
    }

    private function is_binary_operator($token) {
        $binaryoperators = ['=', '**', '*', '/', '%', '+', '-', '<<', '>>', '&', '^',
            '|', '&&', '||', '<', '>', '==', '>=', '<=', '!='];

        return in_array($token->value, $binaryoperators);
    }

    private function execute_ternary_operator() {
        $else = array_pop($this->stack);
        $then = array_pop($this->stack);
        $condition = array_pop($this->stack);
        return ($condition->value ? $then : $else);
    }

    private function execute_unary_operator($token) {
        $input = array_pop($this->stack);
        // Check if the input is numeric. Boolean values are internally treated as 1 and 0 for
        // backwards compatibility.
        if ($input->type !== token::NUMBER) {
            $this->die("evaluation error: numerical value expected, got '{$input->value}'", $input);
        }
        $value = $input->value;
        $output = null;
        switch ($token->value) {
            case '_':
                $output = (-1) * $value;
                break;
            case '!':
                $output = ($value ? 1 : 0);
                break;
            case '~':
                $output = ~ $value;
                break;
        }
        return new token(token::NUMBER, $output, $token->row, $token->column);
    }

    private function execute_binary_operator($token) {
        $first = array_pop($this->stack);
        $second = array_pop($this->stack);

        if ($this->needs_numeric_input($token)) {
            if ($first->type !== token::NUMBER) {
                $this->die("evaluation error: numerical value expected, got '{$first->value}'", $first);
            }
            if ($second->type !== token::NUMBER) {
                $this->die("evaluation error: numerical value expected, got '{$second->value}'", $second);
            }
        }

        $first = $first->value;
        $second = $second->value;

        $output = null;
        // Many results will be numeric, so we set this as the default here.
        $outtype = token::NUMBER;
        switch ($token->value) {
            case '=':
                $output = $this->assign_value($second, $first);
                // FIXME: set $outtype according to type of $second
                break;
            case '**':
                // FIXME checks:
                // - negative exponent -> base ≠ 0
                // - 0 ** 0
                // - negative base -> exponent must be integer
                $output = $second ** $first;
                break;
            case '*':
                $output = $first * $second;
                break;
            case '/':
                // FIXME check: $second ≠ 0
                $output = $second / $first;
                break;
            case '%':
                // FIXME check: $second ≠ 0
                $output = $second % $first;
                break;
            case '+':
                // FIXME: with strings -> concatenation
                // if string -> set $outtype
                $output = $second + $first;
                break;
            case '-':
                $output = $second - $first;
                break;
            case '<<':
                // FIXME: checks
                // - args must be integer (truncate if necessary)
                // - second must be non-negative
                $output = $second << $first;
                break;
            case '>>':
                // FIXME: checks as for <<
                $output = $second >> $first;
                break;
            case '&':
                // FIXME: checks
                $output = $second & $first;
                break;
            case '^':
                // FIXME: checks
                $output = $second ^ $first;
                break;
            case '|':
                // FIXME: checks
                $output = $second | $first;
                break;
            case '&&':
                $output = ($second && $first ? 1 : 0);
                break;
            case '||':
                $output = ($second || $first ? 1 : 0);
                break;
            case '<':
                $output = ($second < $first ? 1 : 0);
                break;
            case '>':
                $output = ($second > $first ? 1 : 0);
                break;
            case '==':
                $output = ($second == $first ? 1 : 0);
                break;
            case '>=':
                $output = ($second >= $first ? 1 : 0);
                break;
            case '<=':
                $output = ($second <= $first ? 1 : 0);
                break;
            case '!=':
                $output = ($second != $first ? 1 : 0);
                break;
        }
        return new token($outtype, $output, $token->row, $token->column);
    }

    private function assign_value($var, $value) {
        // FIXME
        return $value;
    }

    private function is_valid_num_of_params(token $function, int $count): bool {
        $funcname = $function->value;
        $min = INF;
        $max = -INF;
        // Union gives precedence to first array, so we are able to override a
        // built-in function.
        $allfunctions = functions::FUNCTIONS + self::PHPFUNCTIONS;
        if (array_key_exists($funcname, $allfunctions)) {
            $min = $allfunctions[$funcname][0];
            $max = $allfunctions[$funcname][1];
            return $count >= $min && $count <= $max;
        }
        // Still here? That means the function is unknown.
        $this->die("unknown function: '$funcname'", $function);
    }

    private function resolve_constant($token) {
        if (array_key_exists($token->value, $this->constants)) {
            return new token(token::NUMBER, $this->constants[$token->value], $token->row, $token->column);
        }
        $this->die("undefined constant: '{$token->value}'", $token);
    }

    public function execute_function(token $token) {
        $funcname = $token->value;
        // Fetch the number of params from the stack. Keep the token in case of an error.
        $numparamstoken = array_pop($this->stack);
        $numparams = $numparamstoken->value;

        // Check if the number of params is valid for the given function. If it is not,
        // die with an error message.
        if (!$this->is_valid_num_of_params($token, $numparams)) {
            $this->die("invalid number of arguments for function '$funcname': $numparams given", $numparamstoken);
        }

        // Fetch the params from the stack and reverse their order, because the stack is LIFO.
        $params = [];
        for ($i = 0; $i < $numparams; $i++) {
            $params[] = array_pop($this->stack)->value;
        }
        $params = array_reverse($params);

        // FIXME: return correct type
        // If something goes wrong, e. g. wrong type of parameter, functions will throw a TypeError (built-in)
        // or an Exception (custom functions). We catch the exception and build a nice error message.
        try {
            // If we have our own implementation, execute that one and quit.
            // Otherwise, call PHP's built-in function.
            if (array_key_exists($funcname, functions::FUNCTIONS)) {
                return new token(token::NUMBER, call_user_func_array(__NAMESPACE__ . '\functions::' . $funcname, $params));
            }
            if (array_key_exists($funcname, self::PHPFUNCTIONS)) {
                return new token(token::NUMBER, call_user_func_array($funcname, $params));
            }
        } catch (Throwable $e) {
            $this->die('evaluation error: ' . $e->getMessage(), $token);
        }
    }

    private function is_known_variable(token $token): bool {
        return in_array($token->value, $this->variableslist);
    }

    private function register_variable(token $token): void {
        // Do not register a variable twice.
        if ($this->is_known_variable($token)) {
            return;
        }
        $this->variableslist[] = $token->value;
    }

}
