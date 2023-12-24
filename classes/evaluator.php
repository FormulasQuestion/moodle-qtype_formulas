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
    public array $variables = [];
    public array $randomvariables = [];

    private array $constants = [
        'π' => M_PI,
    ];

    private array $stack = [];

    /*
     * @var int seed used when picking a random value for algebraic variables
     *
     * This is used, because we want the same variable to be resolved to the same
     * value when evaluating any given expression.
     */
    private int $seed = 0;

    /* if true, it is possible to assign values to reserved variables */
    private bool $godmode = false;

    /* if true, algebraic variables are replaced by a random value among their reservoir */
    private bool $algebraicmode = false;

    /**
     * FIXME Undocumented function
     *
     */
    public function __construct(?string $context = null) {
        $this->reinitialize($context);
    }

    /**
     * Substitute placeholders like {a} or {=a*b} in a text by evaluating the corresponding
     * expressions in the current evaluator.
     *
     * @param string $text the text to be formatted
     * @param bool $skiplists whether lists should be skipped, otherwise they are printed as [1, 2, 3]
     * @return string
     */
    public function substitute_variables_in_text(string $text, bool $skiplists = true): string {
        // We have three sorts of placeholders: "naked" variables like {a},
        // variables with a numerical index like {a[1]} or more complex
        // expressions like {=a+b} or {=a[b]}.
        $varpattern = '[_A-Za-z]\w*';
        $arraypattern = '[_A-Za-z]\w*\[\d+\]';
        $expressionpattern = '=[^}]+';

        $matches = [];
        preg_match_all("/\{($varpattern|$arraypattern|$expressionpattern)\}/", $text, $matches);

        // We have the variable names or expressions in $matches[1]. Let's first filter out the
        // duplicates.
        $matches = array_unique($matches[1]);

        foreach ($matches as $match) {
            $input = $match;
            // For expressions, we have to remove the = sign.
            if ($input[0] === '=') {
                $input = substr($input, 1);
            }
            // We could resolve variables like {a} or {b[1]} directly and it would probably be faster
            // to do so, but the code is much simpler if we just feed everything to the evaluator.
            // If there is an evaluation error, we simply do not replace do placeholder.
            try {
                $parser = new parser($input);
                // Before evaluating an expression, we want to make sure it does not contain
                // an assignment operator, because that could overwrite values in the evaluator's
                // variable context.
                if ($input !== $match && $parser->has_token_in_tokenlist(token::OPERATOR, '=')) {
                    continue;
                }
                $results = $this->evaluate($parser->get_statements());
                $result = end($results);
                // If the users does not want to substitute lists (arrays), well ... we don't.
                if ($skiplists && in_array($result->type, [token::LIST, token::SET])) {
                    continue;
                }
                $text = str_replace("{{$match}}", strval($result), $text);
            } catch (Exception $e) {
                // TODO: use non-capturing exception when we drop support for old PHP
                unset($e);
            }
        }

        return $text;
    }

    public function remove_special_vars(): void {
        foreach ($this->variables as $name => $variable) {
            $isreserved = in_array($name, ['_err', '_relerr', '_a', '_r', '_d', '_u']);
            $isanswer = preg_match('/^_\d+$/', $name);

            if ($isreserved || $isanswer) {
                unset($this->variables[$name]);
            }
        }
    }

    public function reinitialize(?string $context = null) {
        $this->clear_stack();

        // If a context is given, we initialize our variables accordingly.
        if (is_string($context)) {
            $this->import_variable_context($context);
        }
    }

    public function clear_stack(): void {
        $this->stack = [];
    }

    public function export_variable_context(): string {
        return serialize($this->variables);
    }

    public function export_variable_list(): array {
        return array_keys($this->variables);
    }

    public function export_single_variable(string $varname) {
        $result = $this->get_variable_value(token::wrap($varname));
        return $result;
    }
    /**
     * FIXME: doc
     *
     * @return string
     */
    public function export_randomvars_for_step_data(): string {
        $result = '';
        foreach ($this->randomvariables as $var) {
            $result .= $var->get_instantiated_definition();
        }
        return $result;
    }

    /**
     * Undocumented function
     *
     * @return integer
     */
    public function get_number_of_variants(): int {
        $result = 1;
        foreach ($this->randomvariables as $var) {
            $num = $var->how_many();
            if ($num > PHP_INT_MAX / $result) {
                return PHP_INT_MAX;
            }
            $result = $result * $num;
        }
        return $result;
    }

    public function instantiate_random_variables(?int $seed = null): void {
        if (isset($seed)) {
            mt_srand($seed);
        }
        foreach ($this->randomvariables as $var) {
            $value = $var->instantiate();
            $this->set_variable_to_value(token::wrap($var->name, token::VARIABLE), $value);
        }
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
     * @param token $vartoken
     * @param token $value
     * @param bool $israndomvar
     * @return token
     */
    private function set_variable_to_value(token $vartoken, token $value, $israndomvar = false): token {
        // Get the "basename" of the variable, e.g. foo in case of foo[1][2].
        $basename = $vartoken->value;
        if (strpos($basename, '[') !== false) {
            $basename = strstr($basename, '[', true);
        }

        // Some variables are reserved and cannot be used as left-hand side in an assignment,
        // unless the evaluator is currently in god mode.
        $isreserved = in_array($basename, ['_err', '_relerr', '_a', '_r', '_d', '_u']);
        $isanswer = preg_match('/^_\d+$/', $basename);
        if (!$this->godmode && ($isreserved || $isanswer)) {
            $this->die("you cannot assign values to the special variable '$basename'", $value);
        }

        // If there are no indices, we set the variable as requested.
        if ($basename === $vartoken->value) {
            // If we are assigning to a random variable, we create a new instance and
            // return the value of the first instantiation.
            if ($israndomvar) {
                $useshuffle = $value->type === variable::LIST;
                if (is_scalar($value->value)) {
                    $this->die('invalid definition of a random variable - you must provide a list of possible values', $value);
                }
                $randomvar = new random_variable($basename, $value->value, $useshuffle);
                $this->randomvariables[$basename] = $randomvar;
                return token::wrap($randomvar->reservoir);
            }

            // Otherwise we return the stored value.
            $var = new variable($basename, $value->value, $value->type, microtime(true));
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
        // Update timestamp for the base variable.
        $this->variables[$basename]->timestamp = microtime(true);

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
        // created from the stored value and type.
        if (count($parts) === 0) {
            $type = $result->type;
            // In algebraic mode, an algebraic variable will resolve to a random value
            // from its reservoir.
            if ($type === token::SET) {
                if ($this->algebraicmode) {
                    // We re-seed the random generator with a preset value and the CRC32 of the
                    // variable's name. The preset will be changed by the calculate_algebraic_expression()
                    // function. This makes sure that while evaluating one single expression, we will
                    // get the same value for the same variable. Adding the variable name into the seed
                    // gives the chance to not have the same value for different variables with the
                    // same reservoir, even though this is not guaranteed, especially if the reservoir is
                    // small.
                    mt_srand($this->seed + crc32($name));

                    $randomindex = mt_rand(0, count($result->value) - 1);
                    $randomelement = $result->value[$randomindex];
                    $value = $randomelement->value;
                    $type = $randomelement->type;
                } else {
                    // If we are not in algebraic mode, it does not make sense to get the value of an algebraic
                    // variable.
                    $this->die("algebraic variable '$name' cannot be used in this context", $variable);
                }
            } else {
                $value = $result->value;
            }
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
        if (empty($this->stack)) {
            throw new Exception('evaluation error: empty stack - did you pass enough arguments for the function or operator?');
        }
        $token = array_pop($this->stack);
        if ($token->type === token::VARIABLE) {
            return $this->get_variable_value($token);
        }
        return $token;
    }

    /**
     * Take an algebraic expression, resolve its variables and calculate its value. For each
     * algebraic variable, a random value among its possible values will be taken.
     *
     * @param string $expression algebraic expression
     * @return token
     */
    public function calculate_algebraic_expression(string $expression): token {
        // If the string is empty, throw an error.
        if (strlen(trim($expression)) === 0) {
            throw new Exception('cannot evaluate an empty formula');
        }

        // Parse the expression. It will parsed by the answer parser, i. e. the ^ operator
        // will mean exponentiation rather than XOR, as per the documented behaviour.
        $parser = new answer_parser($expression, $this->export_variable_list());
        if (!$parser->is_valid_algebraic_formula()) {
            throw new Exception("'$expression' is not a valid algebraic expression");
        }

        // Setting the evaluator's seed to the current time. If the function is called several
        // times in short intervals, we want to make sure the seed still changes.
        $lastseed = $this->seed;
        $this->seed = time();
        if ($lastseed >= $this->seed) {
            $this->seed = $lastseed + 1;
            $lastseed = $this->seed;
        }

        // Now evaluate the expression and return the result. By saving the stack and restoring
        // it afterwards, we create an empty substack for this evaluation only.
        $this->algebraicmode = true;
        $oldstack = $this->stack;
        $this->clear_stack();
        $result = $this->evaluate($parser->get_statements()[0]);
        $this->stack = $oldstack;
        $this->algebraicmode = false;

        return $result;
    }

    /**
     * Takes a string representation of an algebraic formula, e.g. "a*x^2 + b" and
     * replace the non-algebraic variables by their numerical value. Return the resulting
     * string.
     *
     * @return string
     */
    public function substitute_variables_in_algebraic_formula(string $formula): string {
        // We do not use the answer parser, because we do not actually evaluate the formula,
        // and if it is needed for later output (e.g. "the correct answer is ..."), there is
        // no need to replace ^ by **.
        $parser = new parser($formula, $this->export_variable_list());

        $tokens = $parser->get_tokens();
        foreach ($tokens as &$token) {
            // If we have a VARIABLE token, we fetch its value and check whether it is
            // an algebraic variable (i. e. the value is of type SET) or not. We will only
            // replace literals by their value.
            if ($token->type === token::VARIABLE) {
                $value = $this->get_variable_value($token);
                if ($value->type === token::SET) {
                    continue;
                }
                // Note: the value of a variable is always stored as a token.
                $token = $value;
            }
        }
        unset($token);

        return implode('', $tokens);
    }

    /**
     * The diff() function calculates absolute differences between numerical or algebraic
     * expressions.
     *
     * @param array $first first list
     * @param array $second second list
     * @param int $n number of points where algebraic expressions will be evaluated
     * @return array
     */
    public function diff(array $first, array $second, ?int $n = null) {
        // FIXME: maybe allow invocation with num/num or string/string/n for convenience.

        // First, we check that $first and $second are lists of the same size.
        if (!is_array($first)) {
            throw new Exception("the first argument of diff() must be a list");
        }
        if (!is_array($second)) {
            throw new Exception("the second argument of diff() must be a list");
        }
        $count = count($first);
        if (count($second) !== $count) {
            throw new Exception("diff() expects two lists of the same size");
        }

        // Now make sure the lists do contain one single data type (only numbers or only strings).
        // This is needed for the diff() function, because strings are evaluated as algebraic
        // formulas, i. e. in a completely different way. Also, both lists must have the same data
        // type.
        $type = $first[0]->type;
        if (!in_array($type, [token::NUMBER, token::STRING])) {
            throw new Exception("when using diff(), the first list must contain only numbers or only strings");
        }
        for ($i = 0; $i < $count; $i++) {
            if ($first[$i]->type !== $type) {
                throw new Exception("diff(): type mismatch for element #{$i} (zero-indexed) of the first list");
            }
            if ($second[$i]->type !== $type) {
                throw new Exception("diff(): type mismatch for element #{$i} (zero-indexed) of the second list");
            }
        }

        // If we are working with numbers, we can directly calculate the differences and return.
        if ($type === token::NUMBER) {
            // The user should not specify a third argument when working with numbers.
            if ($n !== null) {
                throw new Exception("diff(): the third argument can only be used with lists of strings");
            }

            $result = [];
            for ($i = 0; $i < $count; $i++) {
                $diff = abs($first[$i]->value - $second[$i]->value);
                $result[$i] = token::wrap($diff);
            }
            return $result;
        }

        // If the user did not specify $n, we set it to 100, for backwards compatibility.
        if ($n === null) {
            $n = 100;
        }

        $result = [];
        // Iterate over all strings and calculate the root mean square difference between the two expressions.
        for ($i = 0; $i < $count; $i++) {
            $result[$i] = 0;
            $expression = "({$first[$i]}) - ({$second[$i]})";

            // Flag that we will set to TRUE if a difference cannot be evaluated. This
            // is to make sure that the difference will be PHP_FLOAT_MAX and not
            // sqrt(PHP_FLOAT_MAX) divided by $n.
            $cannotevaluate = false;
            for ($j = 0; $j < $n; $j++) {
                try {
                    $difference = $this->calculate_algebraic_expression($expression);
                } catch (Exception $e) {
                    // If evaluation failed, there is no need to evaluate any further. Instead,
                    // we set the $cannotevaluate flag and will later set the result to
                    // PHP_FLOAT_MAX. By choosing PHP_FLOAT_MAX rather than INF, we make sure
                    // that the result is still a float.
                    $cannotevaluate = true;
                    $result[$i] = PHP_FLOAT_MAX;
                    break;
                }
                $result[$i] += $difference->value ** 2;
            }
            $result[$i] = token::wrap(sqrt($result[$i] / $n), token::NUMBER);
            if ($cannotevaluate) {
                $result[$i] = token::wrap(PHP_FLOAT_MAX, token::NUMBER);
            }
        }

        return $result;
    }

    private function evaluate_the_right_thing($input, bool $godmode = false) {
        if ($input instanceof expression) {
            return $this->evaluate_single_expression($input, $godmode);
        }
        if ($input instanceof for_loop) {
            if ($godmode) {
                throw new Exception('for loops cannot be evaluated in god mode');
            }
            return $this->evaluate_for_loop($input);
        }
        throw new Exception('bad invocation of evaluate(), expected expression or for loop');
    }

    /**
     * Evaluate a single expression or an array of expressions.
     *
     * @param expression|array $input
     * @param bool $godmode whether to run the evaluation in god mode
     * @return token|array
     */
    public function evaluate($input, bool $godmode = false) {
        if (($input instanceof expression) || ($input instanceof for_loop)) {
            return $this->evaluate_the_right_thing($input, $godmode);
        }
        if (!is_array($input)) {
            throw new Exception('bad invocation of evaluate(), expected an expression or a list of expressions');
        }
        $result = [];
        foreach ($input as $single) {
            $result[] = $this->evaluate_the_right_thing($single, $godmode);
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

    private function evaluate_single_expression(expression $expression, bool $godmode = false) {
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
                    $this->godmode = $godmode;
                    $this->stack[] = $this->execute_assignment($israndomvar);
                    $this->godmode = false;
                } else if ($this->is_binary_operator($token)) {
                    $this->stack[] = $this->execute_binary_operator($token);
                }
                // The %%ternary-sentinel pseudo-token goes on the stack where it will
                // help detect ternary expressions with too few arguments.
                if ($value === '%%ternary-sentinel') {
                    $this->stack[] = $token;
                }
                // When executing the ternary operator, we pass it the operator token
                // in order to have best possible error reporting.
                if ($value === '%%ternary') {
                    $this->stack[] = $this->execute_ternary_operator($token);
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
            throw new Exception('stack should contain exactly one element after evaluation - did you forget a semicolon somewhere?');
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
            $elements[] = $this->pop_real_value();
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
                $value = "algebraic variable";
            } else if ($token->type === token::LIST) {
                $value = "list";
            } else if ($enforcenumeric) {
                $value = "'{$token->value}'";
            } else if ($token->type === token::STRING) {
                return;
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

        // When storing a value in a variable, the row and column should be
        // set to the row and column of the variable token.
        $what->row = $destination->row;
        $what->column = $destination->column;

        // The destination must be a variable token.
        if ($destination->type !== token::VARIABLE) {
            $this->die('left-hand side of assignment must be a variable', $destination);
        }
        return $this->set_variable_to_value($destination, $what, $israndomvar);
    }

    private function execute_ternary_operator(token $optoken) {
        // For good error reporting, we first check, whether there are enough arguments on
        // the stack. We subtract one, because there is a sentinel token.
        if (count($this->stack) - 1 < 3) {
            $this->die('evaluation error: not enough arguments for ternary operator', $optoken);
        }
        $else = array_pop($this->stack);
        $then = array_pop($this->stack);
        // The user might not have provided enough arguments for the ternary operator (missing 'else'
        // part), but there might be other elements on the stack from earlier operations (or a LHS variable
        // for an upcoming assignment). In that case, the intended 'then' token has been popped as
        // the 'else' part and we have now read the '%%stopternary' pseudo-token.
        if ($then->type === token::OPERATOR && $then->value === '%%ternary-sentinel') {
            $this->die('evaluation error: not enough arguments for ternary operator', $then);
        }
        // If everything is OK, we should now arrive at the '%%ternary-sentinel' pseudo-token. Let's see...
        $pseudotoken = array_pop($this->stack);
        if ($pseudotoken->type !== token::OPERATOR && $pseudotoken->value !== '%%ternary-sentinel') {
            $this->die('evaluation error: not enough arguments for ternary operator', $then);
        }

        $condition = $this->pop_real_value();
        return ($condition->value ? $then : $else);
    }

    private function execute_unary_operator($token) {
        $input = $this->pop_real_value();

        // Check if the input is numeric. Boolean values are internally treated as 1 and 0 for
        // backwards compatibility. The function apply_unary_operator() will do its own check,
        // but we can have a better error message, if we do it here.
        if ($this->needs_numeric_input($token)) {
            $this->abort_if_not_scalar($input);
        }

        try {
            $result = functions::apply_unary_operator($token->value, $input->value);
        } catch (Exception $e) {
            $this->die($e->getMessage(), $token);
        }
        return token::wrap($result);
    }

    private function execute_binary_operator($optoken) {
        // The stack is LIFO, so we pop the second operand first.
        $secondtoken = $this->pop_real_value();
        $firsttoken = $this->pop_real_value();

        // Abort with nice error message, if arguments should be numeric but are not.
        if ($this->needs_numeric_input($optoken)) {
            $this->abort_if_not_scalar($firsttoken);
            $this->abort_if_not_scalar($secondtoken);
        }

        $first = $firsttoken->value;
        $second = $secondtoken->value;

        // For + (string concatenation or addition) we check the arguments here, even if another
        // check is done in functions::apply_binary_operator(), because this allows for better
        // error reporting.
        if ($optoken->value === '+') {
            // If at least one operand is a string, both values must be scalar, but
            // not necessarily numeric; we use concatenation instead of addition.
            // In all other cases, addition must (currently) be numeric, so we abort
            // if the arguments are not numbers.
            $acceptstring = is_string($first) || is_string($second);
            $this->abort_if_not_scalar($firsttoken, !$acceptstring);
            $this->abort_if_not_scalar($secondtoken, !$acceptstring);
        }

        try {
            $result = functions::apply_binary_operator($optoken->value, $first, $second);
        } catch (Exception $e) {
            $this->die($e->getMessage(), $optoken);
        }
        return token::wrap($result);
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
            $params[] = $this->pop_real_value()->value;
        }
        $params = array_reverse($params);

        // FIXME: return correct type according to function
        // -> own functions should have possibility to return token; in this case, use it
        // -> if function returns value (PHP function or simple own function), wrap it into token
        // If something goes wrong, e. g. wrong type of parameter, functions will throw a TypeError (built-in)
        // or an Exception (custom functions). We catch the exception and build a nice error message.
        try {
            // If we have our own implementation, execute that one. Otherwise, use PHP's built-in function.
            // The special function diff() is defined in the evaluator, so it needs special treatment.
            $prefix = '';
            if ($funcname === 'diff') {
                $prefix = self::class . '::';
            } else if (array_key_exists($funcname, functions::FUNCTIONS)) {
                $prefix = functions::class . '::';
            }
            $result = call_user_func_array($prefix . $funcname, $params);
        } catch (Throwable $e) {
            // FIXME: maybe change message and remove "evaluation error"
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
