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
use Throwable, Exception;

/**
 * Evaluator for qtype_formulas
 *
 * @package    qtype_formulas
 * @copyright  2022 Philipp Imhof
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


/*

TODO:

 * set value for individual array element / make %%arrayindex aware of variables vs. literals

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

    // FIXME: temporarily, for testing debugging
    public $variables = [];
    private $randomvariables = [];

    private $constants = [
        'π' => M_PI,
    ];

    private array $stack = [];

    /**
     * FIXME Undocumented function
     *
     */
    public function __construct(?string $context = null) {
        $this->reinitialize($context);
    }

    public function reinitialize(?string $context = null) {
        $this->clear_stack();

        // If a context is given, we initialize our variables accordingly.
        if (is_string($context)) {
            $this->import_variable_context($context);
        }
    }

    private function clear_stack(): void {
        $this->stack = [];
    }

    public function export_variable_context(): string {
        return serialize($this->variables);
    }

    /**
     * Import an existing variable context, e.g. from another evaluator class.
     * If the same variable exists in our context and the incoming context, the
     * incoming context will overwrite our data. This can be avoided by setting
     * the optional parameter to false.
     *
     * @param string $data serialized variable context exported from another evaluator
     * @param boolean $overwrite whether to overwrite existing data with incoming context
     * @return void
     */
    public function import_variable_context(string $data, bool $overwrite = true) {
        $incoming = unserialize($data, ['allowed_classes' => [variable::class, token::class]]);
        if ($incoming === false) {
            throw new Exception('invalid variable context given, aborting import');
        }
        foreach ($incoming as $name => $var) {
            // New variables are added.
            // Existing variables are only overwritten, if $overwrite is true.
            $notknownyet = !array_key_exists($name, $this->variables);
            if ($notknownyet || $overwrite) {
                $this->variables[$name] = $var;
            }
        }
    }

    /**
     * Set the variable defined in $token to the value $value and correctly set
     * it's $type attribute.
     *
     * @param token $variable
     * @param token $value
     * @param bool $israndomvar
     * @return void
     */
    private function set_variable_to_value(token $vartoken, token $value, $israndomvar = false): token {
        // Get the "basename" of the variable, e.g. foo in case of foo[1][2].
        $basename = $vartoken->value;
        if (strpos($basename, '[') !== false) {
            $basename = strstr($basename, '[', true);
        }

        // Some variables are reserved and cannot be used as left-hand side in an assignment.
        $isreserved = in_array($basename, ['_err', '_relerr', '_a', '_r', '_d', '_u']);
        $isanswer = preg_match('/^_\d+$/', $basename);
        if ($isreserved || $isanswer) {
            $this->die("you cannot assign values to the special variable '$basename'", $value);
        }

        // If there are no indices, we set the variable as requested.
        if ($basename === $vartoken->value) {
            // If we are assigning to a random variable, we create a new instance and
            // return the value of the first instantiation.
            if ($israndomvar) {
                $useshuffle = $value->type === variable::LIST;
                $randomvar = new random_variable($basename, $value->value, $useshuffle);
                $this->randomvariables[$basename] = $randomvar;
                return token::wrap($randomvar->value);
            }

            // Otherwise we return the stored value.
            $var = new variable($basename, $value->value, $value->type);
            $this->variables[$basename] = $var;
            return token::wrap($var->value);
        }

        // If there is an index and we are setting a random variable, we throw an error.
        if ($israndomvar) {
            $this->die('setting individual list elements is not supported for random variables', $value);
        }

        // If there is an index, but the variable is a string, we throw an error. Setting
        // characters of a string in this way is not allowed.
        if ($this->variables[$basename]->type === variable::STRING) {
            $this->die('individual chars of a string cannot be modified', $value);
        }

        // Otherwise, we try to get the variable's value. The function will
        // - resolve indices correctly
        // - throw an error, if the variable does not exist
        // so we can just rely on that.
        $current = $this->get_variable_value($vartoken);

        // Array elements are stored as tokens rather than just values (because
        // each element can have a different type). That means, we received an
        // object or rather a reference to an object. Thus, if we change the value and
        // type attribute of that token object, it will automatically be changed
        // inside the array itself.
        $current->value = $value->value;
        $current->type = $value->type;

        // Finally, we return what has been stored.
        return $current;
    }

    /**
     * Get the value token that is stored in a variable. If the token is a literal
     * (number, string, array, set), just return the value directly.
     *
     * @param token $variable
     * @return token
     */
    private function get_variable_value(token $variable): token {
        // The raw name may contain indices, e.g. a[1][2]. We split at the [ and
        // take the first chunk as the true variable name. If there are no brackets,
        // there will be only one chunk and everything is fine.
        $rawname = $variable->value;
        $parts = explode('[', $rawname);
        $name = array_shift($parts);
        if (!array_key_exists($name, $this->variables)) {
            $this->die("unknown variable: $name", $variable);
        }
        $result = $this->variables[$name];

        // If we access the variable as a whole, we return a new token
        // created from the stored value and tye.
        if (count($parts) === 0) {
            $value = $result->value;
            $type = $result->type;
            return new token($type, $value, $variable->row, $variable->column);
        }

        // If we do have indices, we access them one by one. The ] at the end of each
        // part must be stripped.
        foreach ($parts as $part) {
            $result = $result->value[substr($part, 0, -1)];
        }

        // When accessing an array, the elements are already stored as tokens, so we return them
        // as they are. This allows the receiver to change values inside the array, because
        // objects are passed by reference.
        // For strings, we must create a new token, because we only get a character.
        if (is_string($result)) {
            return new token(token::STRING, $result, $variable->row, $variable->column);
        }
        return $result;

        // FIXME: if type is SET, this is either an algebraic variable or an uninstantiated random variable
    }

    /**
     * Stop evaluating and indicate the human readable position (row/column) where the error occurred.
     *
     * @param string $message error message
     * @throws Exception
     */
    private function die(string $message, token $offendingtoken): never {
        throw new Exception($offendingtoken->row . ':' . $offendingtoken->column . ':' . $message);
    }

    /**
     * Pop top element from the stack. If the token is a literal (number, string, list etc.), return it
     * directly. If it is a variable, resolve it and return its content.
     *
     * @return token
     */
    private function pop_real_value(): token {
        $token = array_pop($this->stack);
        if ($token->type === token::VARIABLE) {
            return $this->get_variable_value($token);
        }
        return $token;
    }

    private function evaluate_the_right_thing($input) {
        if ($input instanceof expression) {
            return $this->evaluate_single_expression($input);
        }
        if ($input instanceof for_loop) {
            return $this->evaluate_for_loop($input);
        }
        throw new Exception('bad invocation of evaluate(), expected expression or for loop');
    }

    /**
     * Evaluate a single expression or an array of expressions.
     *
     * @param expression|array $input
     * @return token|array
     */
    public function evaluate($input) {
        if (($input instanceof expression) || ($input instanceof for_loop)) {
            return $this->evaluate_the_right_thing($input);
        }
        if (!is_array($input)) {
            throw new Exception('bad invocation of evaluate(), expected an expression or a list of expressions');
        }
        $result = [];
        foreach ($input as $single) {
            $result[] = $this->evaluate_the_right_thing($single);
        }
        return $result;
    }

    private function evaluate_for_loop(for_loop $loop) {
        $rangetoken = $this->evaluate_single_expression($loop->range);
        $range = $rangetoken->value;
        $result = null;
        foreach ($range as $iterationvalue) {
            $this->set_variable_to_value($loop->variable, $iterationvalue);
            $result = $this->evaluate($loop->body);
        }
        $this->clear_stack();
        return end($result);
    }

    private function evaluate_single_expression(expression $expression) {
        foreach ($expression->body as $token) {
            $type = $token->type;
            $value = $token->value;

            $isliteral = ($type & token::ANY_LITERAL);
            $isopening = ($type === token::OPENING_BRACE || $type === token::OPENING_BRACKET);
            $isvariable = ($type === token::VARIABLE);

            // Many tokens go directly to the stack.
            if ($isliteral || $isopening || $isvariable) {
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
                // The = operator is binary, but we treat it separately.
                if ($value === '=' || $value === 'r=') {
                    $israndomvar = ($value === 'r=');
                    $this->stack[] = $this->execute_assignment($israndomvar);
                } else if ($this->is_binary_operator($token)) {
                    $this->stack[] = $this->execute_binary_operator($token);
                }
                if ($value === '%%ternary') {
                    $this->stack[] = $this->execute_ternary_operator();
                }
                if ($value === '%%arrayindex') {
                    $this->stack[] = $this->fetch_array_element_or_char();
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
        // If the stack contains more than one element, there must have been a problem somewhere.
        if (count($this->stack) !== 1) {
            throw new Exception("stack should contain exactly one element after evaluation");
        }
        // If the stack only contains one single variable token, return its content.
        // Otherwise, return the token.
        return $this->pop_real_value();
    }

    private function fetch_array_element_or_char(): token {
        $indextoken = $this->pop_real_value();
        $index = $indextoken->value;
        $nexttoken = array_pop($this->stack);

        // Check if the index is a number. If it is not, try to convert it.
        // If conversion fails, throw an error.
        if ($indextoken->type !== token::NUMBER) {
            if (!is_numeric($index)) {
                $this->die("evaluation error: expected numerical index, found '{$index}'", $indextoken);
            }
            $index = floatval($index);
        }

        // If the index is not a whole number, throw an error. A whole number in float
        // representation is fine, though.
        if (abs($index - intval($index)) > 1e-6) {
            $this->die("evaluation error: index should be an integer, found '{$index}'", $indextoken);
        }
        $index = intval($index);

        // Make sure there is only one index.
        if ($nexttoken->type !== token::OPENING_BRACKET) {
            $this->die('evaluation error: only one index supported when accessing array elements', $indextoken);
        }

        // Fetch the array or string from the stack.
        $arraytoken = array_pop($this->stack);
        // If it is a variable, we do lazy evaluation: just append the index and wait. It might be used
        // as a left-hand side in an assignment. If it is not, it will be resolved later.
        if ($arraytoken->type === token::VARIABLE) {
            $name = $arraytoken->value . "[$index]";
            return new token(token::VARIABLE, $name, $arraytoken->row, $arraytoken->column);
        }
        if (!in_array($arraytoken->type, [token::LIST, token::STRING])) {
            $this->die('evaluation error: indexing is only possible with arrays (lists) and strings', $nexttoken);
        }
        $array = $arraytoken->value;

        // Fetch the length of the array or string.
        if ($arraytoken->type === token::STRING) {
            $len = strlen($array);
        } else {
            $len = count($array);
        }
        // Negative indices can be used to count "from the end". For strings, this is
        // directly supported in PHP, but not for arrays. So for the sake of simplicity,
        // we do our own preprocessing.
        if ($index < 0) {
            $index = $index + $len;
        }
        // Now check if the index is out of range. We use the original value from the token.
        if ($index > $len - 1 || $index < 0) {
            $this->die("evaluation error: index out of range: {$indextoken->value}", $indextoken);
        }

        $element = $array[$index];
        // If we are accessing a string's char, we create a new string token.
        if ($arraytoken->type === token::STRING) {
            return new token(token::STRING, $element, $arraytoken->row, $arraytoken->column + $index);
        }
        // Otherwise, the element is already wrapped in a token.
        return $element;
    }

    private function build_range() {
        // Pop the number of parts. We generated it ourselves, so we know it will be 2 or 3.
        $parts = array_pop($this->stack)->value;

        $step = 1;
        // If we have 3 parts, extract the step size. Conserve the token in case of an error.
        if ($parts === 3) {
            $steptoken = $this->pop_real_value();
            // Abort with nice error message, if step is not numeric.
            $this->abort_if_not_scalar($steptoken);
            $step = $steptoken->value;
        }

        // Step must not be zero.
        if ($step === 0) {
            $this->die('syntax error: step size of a range cannot be zero', $steptoken);
        }

        // Fetch start and end of the range. Conserve token for the end value, in case of an error.
        $endtoken = $this->pop_real_value();
        $end = $endtoken->value;
        $starttoken = $this->pop_real_value();
        $start = $starttoken->value;

        // Abort with nice error message, if start or end is not numeric.
        $this->abort_if_not_scalar($starttoken);
        $this->abort_if_not_scalar($endtoken);

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

    private function is_unary_operator(token $token): bool {
        return in_array($token->value, ['_', '!', '~']);
    }

    private function needs_numeric_input(token $token): bool {
        $operators = ['_', '~', '**', '*', '/', '%', '-', '<<', '>>', '&', '^', '|', '&&', '||'];
        return in_array($token->value, $operators);
    }

    /**
     * In many cases, operators need a numeric or at least a scalar operand to work properly.
     * This function does the necessary check and prepares a human-friendly error message
     * if the conditions are not met.
     *
     * @param token $token the token to check
     * @param boolean $enforcenumeric whether the value must be numeric in addition to being scalar
     * @return void
     * @throws Exception
     */
    private function abort_if_not_scalar(token $token, bool $enforcenumeric = true): void {
        if ($token->type !== token::NUMBER) {
            if ($token->type === token::SET) {
                $value = "'{...}'";
            } else if ($token->type === token::LIST) {
                $value = "'[...]'";
            } else if ($enforcenumeric) {
                $value = "'{$token->value}'";
            }
            $expected = ($enforcenumeric ? 'numeric' : 'scalar');
            $this->die("evaluation error: $expected value expected, got $value", $token);
        }
    }

    private function is_binary_operator(token $token): bool {
        $binaryoperators = ['=', '**', '*', '/', '%', '+', '-', '<<', '>>', '&', '^',
            '|', '&&', '||', '<', '>', '==', '>=', '<=', '!='];

        return in_array($token->value, $binaryoperators);
    }

    private function execute_assignment($israndomvar = false): token {
        $what = $this->pop_real_value();
        $destination = array_pop($this->stack);

        // The destination must be a variable token.
        if ($destination->type !== token::VARIABLE) {
            $this->die('left-hand side of assignment must be a variable', $destination);
        }
        return $this->set_variable_to_value($destination, $what, $israndomvar);
    }

    private function execute_ternary_operator() {
        $else = array_pop($this->stack);
        $then = array_pop($this->stack);
        $condition = array_pop($this->stack);
        return ($condition->value ? $then : $else);
    }

    private function execute_unary_operator($token) {
        $input = $this->pop_real_value();
        // Check if the input is numeric. Boolean values are internally treated as 1 and 0 for
        // backwards compatibility.
        if ($this->needs_numeric_input($token)) {
            $this->abort_if_not_scalar($input);
        }
        $value = $input->value;
        $output = null;
        switch ($token->value) {
            case '_':
                $output = (-1) * $value;
                break;
            case '!':
                $output = ($value ? 0 : 1);
                break;
            case '~':
                $output = ~ $value;
                break;
        }
        return new token(token::NUMBER, $output, $token->row, $token->column);
    }

    private function execute_binary_operator($optoken) {
        $firsttoken = $this->pop_real_value();
        $secondtoken = $this->pop_real_value();

        // Abort with nice error message, if arguments should be numeric but are not.
        if ($this->needs_numeric_input($optoken)) {
            $this->abort_if_not_scalar($firsttoken);
            $this->abort_if_not_scalar($secondtoken);
        }

        $first = $firsttoken->value;
        $second = $secondtoken->value;

        $output = null;
        // Many results will be numeric, so we set this as the default here.
        $outtype = token::NUMBER;
        switch ($optoken->value) {
            case '**':
                // Only check for equality, because 0.0 == 0 but not 0.0 === 0.
                if ($first == 0 && $second == 0) {
                    $this->die('power 0^0 is not defined', $optoken);
                }
                if ($first < 0 && $second == 0) {
                    $this->die('division by zero is not defined, so base cannot be zero for negative exponents', $optoken);
                }
                if ($second < 0 && intval($first) != $first) {
                    $this->die('base cannot be negative with fractional exponent', $optoken);
                }
                $output = $second ** $first;
                break;
            case '*':
                $output = $first * $second;
                break;
            case '/':
            case '%':
                if ($first == 0) {
                    $this->die('division by zero is not defined', $optoken);
                }
                if ($optoken->value === '/') {
                    $output = $second / $first;
                } else {
                    $output = $second % $first;
                }
                break;
            case '+':
                // If at least one operand is a string, we use concatenation instead
                // of addition.
                if (is_string($first) || is_string($second)) {
                    $this->abort_if_not_scalar($firsttoken, false);
                    $this->abort_if_not_scalar($secondtoken, false);
                    $output = $second . $first;
                    $outtype = token::STRING;
                    break;
                }
                // In all other cases, addition must (currently) be numeric, so we abort
                // if the arguments are not numbers.
                $this->abort_if_not_scalar($firsttoken);
                $this->abort_if_not_scalar($secondtoken);
                $output = $second + $first;
                break;
            case '-':
                $output = $second - $first;
                break;
            case '<<':
            case '>>':
                if (intval($first) != $first || intval($second) != $second) {
                    $this->die('bit shift operator should only be used with integers', $optoken);
                }
                if ($first < 0) {
                    $this->die("bit shift by negative number $first is not allowed", $optoken);
                }
                if ($optoken->value === '<<') {
                    $output = (int)$second << (int)$first;
                } else {
                    $output = (int)$second >> (int)$first;
                }
                break;
            case '&':
                if (intval($first) != $first || intval($second) != $second) {
                    $this->die('bitwise AND should only be used with integers', $optoken);
                }
                $output = $second & $first;
                break;
            case '^':
                if (intval($first) != $first || intval($second) != $second) {
                    $this->die('bitwise XOR should only be used with integers', $optoken);
                }
                $output = $second ^ $first;
                break;
            case '|':
                if (intval($first) != $first || intval($second) != $second) {
                    $this->die('bitwise OR should only be used with integers', $optoken);
                }
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
        // One last safety check: numeric results must not be NAN or INF.
        // This should never be triggered.
        if (is_numeric($output) && (is_nan($output) || is_infinite($output))) {
            $this->die('evaluation error', $optoken);
        }
        return new token($outtype, $output, $optoken->row, $optoken->column);
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

        // FIXME: return correct type according to function
        // -> own functions should have possibility to return token; in this case, use it
        // -> if function returns value (PHP function or simple own function), wrap it into token
        // If something goes wrong, e. g. wrong type of parameter, functions will throw a TypeError (built-in)
        // or an Exception (custom functions). We catch the exception and build a nice error message.
        try {
            // If we have our own implementation, execute that one. Otherwise, use PHP's built-in function.
            $prefix = '';
            if (array_key_exists($funcname, functions::FUNCTIONS)) {
                $prefix = functions::class . '::';
            }
            $result = call_user_func_array($prefix . $funcname, $params);
        } catch (Throwable $e) {
            $this->die('evaluation error: ' . $e->getMessage(), $token);
        }

        // Some of our own functions may return a token. In those cases, we reset
        // the row and column value, because they are no longer accurate. Once that
        // is done, we return the token.
        if ($result instanceof token) {
            $result->row = -1;
            $result->column = -1;
            return $result;
        }

        // Most of the time, the return value will not be a token. In those cases,
        // we have to wrap it up before returning.
        return token::wrap($result);
    }
}
