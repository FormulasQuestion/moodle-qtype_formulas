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
use Throwable, Exception;

/**
 * Evaluator for qtype_formulas
 *
 * @package    qtype_formulas
 * @copyright  2022 Philipp Imhof
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class evaluator {
    /** @var array list function name => [min params, max params] */
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
        'log' => [1, 2],
        'max' => [1, INF],
        'min' => [1, INF],
        'octdec' => [1, 1],
        'pow' => [2, 2],
        'rad2deg' => [1, 1],
        'round' => [1, 2],
        'sin' => [1, 1],
        'sinh' => [1, 1],
        'sqrt' => [1, 1],
        'tan' => [1, 1],
        'tanh' => [1, 1],
    ];

    /** @var array $variables array holding all variables */
    private array $variables = [];

    /** @var array $randomvariables array holding all (uninstantiated) random variables */
    private array $randomvariables = [];

    /** @var array $constants array holding all predefined constants, i. e. pi */
    private array $constants = [
        'π' => M_PI,
    ];

    /** @var array $stack the operand stack */
    private array $stack = [];

    /**
     * PRNG seed. This is used, because we want the same variable to be resolved to the same
     * value when evaluating any given expression.
     *
     * @var int $seed
     */
    private int $seed = 0;

    /** @var bool $godmode whether we are allowed to modify reserved variables, e.g. _a or _0 */
    private bool $godmode = false;

    /** @var bool $algebraicmode whether algebraic variables are replaced by a random value from their reservoir */
    private bool $algebraicmode = false;

    /**
     * Create an evaluator class. This class does all evaluations for expressions that have
     * been parsed by a parser or answer_parser.
     *
     * @param array $context serialized variable context from another evaluator class
     */
    public function __construct(array $context = []) {
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
        $arraypattern = '[_A-Za-z]\w*(\[\d+\])+';
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
                // Evaluation will fail e.g. if it is an algebraic variable or if there is an
                // error in the expression. In those cases, the placeholder will simply not
                // be replaced.
                $results = $this->evaluate($parser->get_statements());
                $result = end($results);
                // If the users does not want to substitute lists (arrays), well ... we don't.
                if ($skiplists && in_array($result->type, [token::LIST, token::SET])) {
                    continue;
                }
                $text = str_replace("{{$match}}", strval($result), $text);
            } catch (Exception $e) {
                // TODO: use non-capturing exception when we drop support for old PHP.
                unset($e);
            }
        }

        return $text;
    }

    /**
     * Remove the special variables like _a or _0, _1, ... from the evaluator.
     *
     * @return void
     */
    public function remove_special_vars(): void {
        foreach ($this->variables as $name => $variable) {
            $isreserved = in_array($name, ['_err', '_relerr', '_a', '_r', '_d', '_u']);
            $isanswer = preg_match('/^_\d+$/', $name);

            if ($isreserved || $isanswer) {
                unset($this->variables[$name]);
            }
        }
    }

    /**
     * Reinitialize the evaluator by clearing the stack and, if requested, setting the
     * variables and random variables to a certain state.
     *
     * @param array $context associative array containing the random and normal variables
     * @return void
     */
    public function reinitialize(array $context = []): void {
        $this->clear_stack();

        // If a context is given, we initialize our variables accordingly.
        if (key_exists('randomvariables', $context) && key_exists('variables', $context)) {
            $this->import_variable_context($context);
        }
    }

    /**
     * Clear the stack.
     *
     * @return void
     */
    public function clear_stack(): void {
        $this->stack = [];
    }

    /**
     * Export all random variables and variables. The function returns an associative array
     * with the keys 'randomvariables' and 'variables'. Each key will hold the serialized
     * string of the corresponding variables.
     *
     * @return array
     */
    public function export_variable_context(): array {
        return [
            'randomvariables' => serialize($this->randomvariables),
            'variables' => serialize($this->variables),
        ];
    }

    /**
     * Build a string that can be used to redefine the instantiated random variables with
     * the same values, but as global values. This is how Formulas question prior to version 6.x
     * used to store their state. We implement this for maximum backwards compatibility, i. e.
     * in order to allow switching back to a 5.x version.
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
     * Export the names of all known variables. This can be used to pass to a new parser,
     * in order to help it classify identifiers as functions or variables.
     *
     * @return array
     */
    public function export_variable_list(): array {
        return array_keys($this->variables);
    }

    /**
     * Export the variable with the given name. Depending on the second parameter, the function
     * returns a token (the variable's content) or a variable (the variable's actual definition).
     *
     * @param string $varname name of the variable
     * @param bool $exportasvariable whether to export as an instance of variable, otherwise just export the content
     * @return token|variable
     */
    public function export_single_variable(string $varname, bool $exportasvariable = false) {
        if ($exportasvariable) {
            return $this->variables[$varname];
        }
        $result = $this->get_variable_value(token::wrap($varname));
        return $result;
    }

    /**
     * Calculate the number of possible variants according to the defined random variables.
     *
     * @return int
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

    /**
     * Instantiate random variables, i. e. assigning a fixed value to them and make them available
     * as regular global variables.
     *
     * @param int|null $seed initialization seed for the PRNG
     * @return void
     */
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
     * @param array $data serialized context for randomvariables and variables
     * @param bool $overwrite whether to overwrite existing data with incoming context
     * @return void
     */
    public function import_variable_context(array $data, bool $overwrite = true) {
        // If the data is invalid, unserialize() will issue an E_NOTICE. We suppress that,
        // because we have our own error message.
        $randomvariables = @unserialize($data['randomvariables'], ['allowed_classes' => [random_variable::class, token::class]]);
        $variables = @unserialize($data['variables'], ['allowed_classes' => [variable::class, token::class]]);
        if ($randomvariables === false || $variables === false) {
            throw new Exception(get_string('error_invalidcontext', 'qtype_formulas'));
        }
        foreach ($variables as $name => $var) {
            // New variables are added.
            // Existing variables are only overwritten, if $overwrite is true.
            $notknownyet = !array_key_exists($name, $this->variables);
            if ($notknownyet || $overwrite) {
                $this->variables[$name] = $var;
            }
        }
        foreach ($randomvariables as $name => $var) {
            // New variables are added.
            // Existing variables are only overwritten, if $overwrite is true.
            $notknownyet = !array_key_exists($name, $this->randomvariables);
            if ($notknownyet || $overwrite) {
                $this->randomvariables[$name] = $var;
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
        // Note that _m is not a reserved name in itself, but the placeholder {_m} is accepted
        // by the renderer to mark the position of the feedback image. Allowing that variable
        // could lead to conflicts, so we do not allow it.
        $isreserved = in_array($basename, ['_err', '_relerr', '_a', '_r', '_d', '_u', '_m']);
        $isanswer = preg_match('/^_\d+$/', $basename);
        // We will -- at least for the moment -- block all variables starting with an underscore,
        // because we might one day need some internal variables or the like.
        $underscore = strpos($basename, '_') === 0;
        if ($underscore && $this->godmode === false) {
            $this->die(get_string('error_invalidvarname', 'qtype_formulas', $basename), $value);
        }

        // If there are no indices, we set the variable as requested.
        if ($basename === $vartoken->value) {
            // If we are assigning to a random variable, we create a new instance and
            // return the value of the first instantiation.
            if ($israndomvar) {
                $useshuffle = $value->type === variable::LIST;
                if (is_scalar($value->value)) {
                    $this->die(get_string('error_invalidrandvardef', 'qtype_formulas'), $value);
                }
                $randomvar = new random_variable($basename, $value->value, $useshuffle);
                $this->randomvariables[$basename] = $randomvar;
                return token::wrap($randomvar->reservoir);
            }

            // Otherwise we return the stored value. If the data is a SET, the variable is an
            // algebraic variable.
            if ($value->type === token::SET) {
                // Algebraic variables only accept a list of numbers; they must not contain
                // strings or nested lists.
                foreach ($value->value as $entry) {
                    if ($entry->type != token::NUMBER) {
                        $this->die(get_string('error_algvar_numbers', 'qtype_formulas'), $value);
                    }
                }

                $value->type = variable::ALGEBRAIC;
            }
            $var = new variable($basename, $value->value, $value->type, microtime(true));
            $this->variables[$basename] = $var;
            return token::wrap($var->value);
        }

        // If there is an index and we are setting a random variable, we throw an error.
        if ($israndomvar) {
            $this->die(get_string('error_setindividual_randvar', 'qtype_formulas'), $value);
        }

        // If there is an index, but the variable is a string, we throw an error. Setting
        // characters of a string in this way is not allowed.
        if ($this->variables[$basename]->type === variable::STRING) {
            $this->die(get_string('error_setindividual_string', 'qtype_formulas'), $value);
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
     * Make sure the index is valid, i. e. an integer (as a number or string) and not out
     * of range. If needed, translate a negative index (count from end) to a 0-indexed value.
     *
     * @param mixed $arrayorstring array or string that should be indexed
     * @param mixed $index the index
     * @param ?token $anchor anchor token used in case of error (may be the array or the index)
     * @return int
     */
    private function validate_array_or_string_index($arrayorstring, $index, ?token $anchor = null): int {
        // Check if the index is a number. If it is not, try to convert it.
        // If conversion fails, throw an error.
        if (!is_numeric($index)) {
            $this->die(get_string('error_expected_intindex', 'qtype_formulas', $index), $anchor);
        }
        $index = floatval($index);

        // If the index is not a whole number, throw an error. A whole number in float
        // representation is fine, though.
        if ($index - intval($index) != 0) {
            $this->die(get_string('error_expected_intindex', 'qtype_formulas', $index), $anchor);
        }
        $index = intval($index);

        // Fetch the length of the array or string.
        if (is_string($arrayorstring)) {
            $len = strlen($arrayorstring);
        } else if (is_array($arrayorstring)) {
            $len = count($arrayorstring);
        } else {
            $this->die(get_string('error_notindexable', 'qtype_formulas'), $anchor);
        }

        // Negative indices can be used to count "from the end". For strings, this is
        // directly supported in PHP, but not for arrays. So for the sake of simplicity,
        // we do our own preprocessing.
        if ($index < 0) {
            $index = $index + $len;
        }
        // Now check if the index is out of range. We use the original value from the token.
        if ($index > $len - 1 || $index < 0) {
            $this->die(get_string('error_indexoutofrange', 'qtype_formulas', $index), $anchor);
        }

        return $index;
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
            $this->die(get_string('error_unknownvarname', 'qtype_formulas', $name), $variable);
        }
        $result = $this->variables[$name];

        // If we access the variable as a whole, we return a new token
        // created from the stored value and type.
        if (count($parts) === 0) {
            $type = $result->type;
            // In algebraic mode, an algebraic variable will resolve to a random value
            // from its reservoir.
            if ($type === variable::ALGEBRAIC) {
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
                    $this->die(get_string('error_cannotusealgebraic', 'qtype_formulas', $name), $variable);
                }
            } else {
                $value = $result->value;
            }
            return new token($type, $value, $variable->row, $variable->column);
        }

        // If we do have indices, we access them one by one. The ] at the end of each
        // part must be stripped.
        foreach ($parts as $part) {
            // Validate the index and, if necessary, convert a negative index to the corresponding
            // positive value.
            $index = $this->validate_array_or_string_index($result->value, substr($part, 0, -1), $variable);
            $result = $result->value[$index];
        }

        // When accessing an array, the elements are already stored as tokens, so we return them
        // as they are. This allows the receiver to change values inside the array, because
        // objects are passed by reference.
        // For strings, we must create a new token, because we only get a character.
        if (is_string($result)) {
            return new token(token::STRING, $result, $variable->row, $variable->column);
        }
        return $result;
    }

    /**
     * Stop evaluating and indicate the human readable position (row/column) where the error occurred.
     *
     * @param string $message error message
     * @param token $offendingtoken the token where the error occurred
     * @throws Exception
     */
    private function die(string $message, token $offendingtoken) {
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
            throw new Exception(get_string('error_emptystack', 'qtype_formulas'));
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
        // Parse the expression. It will parsed by the answer parser, i. e. the ^ operator
        // will mean exponentiation rather than XOR, as per the documented behaviour.
        // As the expression might contain a PREFIX operator (from a model answer), we
        // set the fourth parameter of the constructor to TRUE.
        // Note that this step will also throw an error, if the expression is empty.
        $parser = new answer_parser($expression, $this->export_variable_list(), true, true);
        if (!$parser->is_acceptable_for_answertype(qtype_formulas::ANSWER_TYPE_ALGEBRAIC)) {
            throw new Exception(get_string('error_invalidalgebraic', 'qtype_formulas', $expression));
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
        // Evaluation might fail. In that case, it is important to assure that the old stack
        // is re-established and that algebraic mode is turned off.
        try {
            $result = $this->evaluate($parser->get_statements()[0]);
        } catch (Exception $e) {
            ;
        } finally {
            $this->stack = $oldstack;
            $this->algebraicmode = false;
            // If we have an exception, we throw it again to pass the error upstream.
            if (isset($e)) {
                throw $e;
            }
        }

        return $result;
    }

    /**
     * For a given list of tokens, find the index of the closing bracket that marks the end of
     * the index definition, i. e. the part that says what element of the array should be accessed.
     *
     * @param array $tokens
     * @return int
     */
    private function find_end_of_array_access(array $tokens): int {
        $count = count($tokens);

        // If we don't have at least four tokens (variable, opening bracket, index, closing bracket)
        // or if the first token after the variable name is not an opening bracket, we can return
        // immediately.
        if ($count < 4 || $tokens[1]->type !== token::OPENING_BRACKET) {
            return 1;
        }

        for ($i = 1; $i < $count - 1; $i++) {
            $token = $tokens[$i];

            // As long as we are not at the closing bracket, we just keep advancing.
            if ($token->type !== token::CLOSING_BRACKET) {
                continue;
            }
            // We found a closing bracket. Now let's see whether the next token is
            // an opening bracket again. If it is, we have to keep searching for the end.
            if ($tokens[$i + 1]->type === token::OPENING_BRACKET) {
                continue;
            }
            // If it is not, we can return.
            return $i + 1;
        }

        // We have not found the closing bracket, so the end is ... at the end.
        return $count;
    }

    /**
     * Takes a string representation of an algebraic formula, e.g. "a*x^2 + b" and
     * replaces the non-algebraic variables by their numerical value. Returns the resulting
     * string.
     *
     * @param string $formula the algebraic formula
     * @return string
     */
    public function substitute_variables_in_algebraic_formula(string $formula): string {
        // We do not use the answer parser, because we do not actually evaluate the formula,
        // and if it is needed for later output (e.g. "the correct answer is ..."), there is
        // no need to replace ^ by **.
        $parser = new parser($formula, $this->export_variable_list());
        $tokens = $parser->get_tokens();
        $count = count($tokens);

        // Will will iterate over all tokens and build an output string bit by bit.
        $output = '';
        for ($i = 0; $i < $count; $i++) {
            $token = $tokens[$i];
            // The unary minus must be translated back to '-'.
            if ($token->type === token::OPERATOR && $token->value === '_') {
                $output .= '-';
                continue;
            }
            // For a nicer output, we add a space before and after the +, -, * and / operator.
            if ($token->type === token::OPERATOR && in_array($token->value, ['+', '-', '*', '/'])) {
                $output .= " {$token->value} ";
                continue;
            }
            // If the token is not a VARIABLE, it can be shipped out.
            if ($tokens[$i]->type !== token::VARIABLE) {
                $output .= $tokens[$i]->value;
                continue;
            }

            // If we are still here, we have a variable name, possibly followed by an opening bracket.
            // As it is not allowed to build lists in an algebraic formula, such a bracket could only
            // mean we are accessing an array element. We try to find out whether there is one and,
            // if needed, how far that "subexpression" goes.
            $numberoftokens = $this->find_end_of_array_access(array_slice($tokens, $i));
            $subexpression = implode('', array_slice($tokens, $i, $numberoftokens));
            $result = $this->substitute_variables_in_text("{=$subexpression}");

            // If there was an error, e.g. invalid array index, there will have been no substitution.
            // In that case, we only send the variable token to the output and keep on working, because
            // there might be nested variables to substitute.
            if ($result === "{=$subexpression}") {
                $output .= $token->value;
                continue;
            }

            // If we are still here, the subexpression has been replaced. We append it to the output
            // and remove all tokens until the end of that subexpression from the queue.
            $output .= $result;
            array_splice($tokens, $i + 1, $numberoftokens - 1);
            $count = $count - $numberoftokens + 1;
        }

        return $output;
    }

    /**
     * The diff() function calculates absolute differences between numerical or algebraic
     * expressions.
     *
     * @param array $first first list
     * @param array $second second list
     * @param int|null $n number of points where algebraic expressions will be evaluated
     * @return array
     */
    public function diff($first, $second, ?int $n = null) {
        // First, we check that $first and $second are lists of the same size.
        if (!is_array($first)) {
            throw new Exception(get_string('error_diff_first', 'qtype_formulas'));
        }
        if (!is_array($second)) {
            throw new Exception(get_string('error_diff_second', 'qtype_formulas'));
        }
        $count = count($first);
        if (count($second) !== $count) {
            throw new Exception(get_string('error_diff_samesize', 'qtype_formulas'));
        }

        // Now make sure the lists do contain one single data type (only numbers or only strings).
        // This is needed for the diff() function, because strings are evaluated as algebraic
        // formulas, i. e. in a completely different way. Also, both lists must have the same data
        // type.
        $type = $first[0]->type;
        if (!in_array($type, [token::NUMBER, token::STRING])) {
            throw new Exception(get_string('error_diff_firstlist_content', 'qtype_formulas'));
        }
        for ($i = 0; $i < $count; $i++) {
            if ($first[$i]->type !== $type) {
                throw new Exception(get_string('error_diff_firstlist_mismatch', 'qtype_formulas', $i));
            }
            if ($second[$i]->type !== $type) {
                throw new Exception(get_string('error_diff_secondlist_mismatch', 'qtype_formulas', $i));
            }
        }

        // If we are working with numbers, we can directly calculate the differences and return.
        if ($type === token::NUMBER) {
            // The user should not specify a third argument when working with numbers.
            if ($n !== null) {
                throw new Exception(get_string('error_diff_third', 'qtype_formulas'));
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
                    // Note: index is $i, because every $j step adds to the $i-th difference.
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

    /**
     * Evaluate the given thing, e. g. an expression or a for loop.
     *
     * @param expression|for_loop $input
     * @param bool $godmode whether one should be allowed to modify reserved variables like e.g. _a or _0
     * @return token|void
     */
    private function evaluate_the_right_thing($input, bool $godmode = false) {
        if ($input instanceof expression) {
            return $this->evaluate_single_expression($input, $godmode);
        }
        if ($input instanceof for_loop) {
            return $this->evaluate_for_loop($input);
        }
        throw new Exception(get_string('error_evaluate_invocation', 'qtype_formulas', 'evaluate_the_right_thing()'));
    }

    /**
     * Evaluate a single expression or an array of expressions.
     *
     * @param expression|for_loop|array $input
     * @param bool $godmode whether to run the evaluation in god mode
     * @return token|array
     */
    public function evaluate($input, bool $godmode = false) {
        if (($input instanceof expression) || ($input instanceof for_loop)) {
            return $this->evaluate_the_right_thing($input, $godmode);
        }
        if (!is_array($input)) {
            throw new Exception(get_string('error_evaluate_invocation', 'qtype_formulas', 'evaluate()'));
        }
        $result = [];
        foreach ($input as $single) {
            $result[] = $this->evaluate_the_right_thing($single, $godmode);
        }
        return $result;
    }

    /**
     * Evaluate a for loop.
     *
     * @param for_loop $loop
     * @return void
     */
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

    /**
     * Evaluate an expression, e. g. an assignment, a function call or a calculation.
     *
     * @param expression $expression
     * @param bool $godmode
     * @return token
     */
    private function evaluate_single_expression(expression $expression, bool $godmode = false): token {
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
            throw new Exception(get_string('error_stacksize', 'qtype_formulas'));
        }
        // If the stack only contains one single variable token, return its content.
        // Otherwise, return the token.
        return $this->pop_real_value();
    }

    /**
     * Fetch an element from a list or a char from a string. The index and the list or string will
     * be taken from the stack.
     *
     * @return token the desired list element or char
     */
    private function fetch_array_element_or_char(): token {
        $indextoken = $this->pop_real_value();
        $index = $indextoken->value;
        $nexttoken = array_pop($this->stack);

        // Make sure there is only one index.
        if ($nexttoken->type !== token::OPENING_BRACKET) {
            $this->die(get_string('error_onlyoneindex', 'qtype_formulas'), $indextoken);
        }

        // Fetch the array or string from the stack.
        $arraytoken = array_pop($this->stack);

        // If it is a variable, we do lazy evaluation: just append the index and wait. It might be used
        // as a left-hand side in an assignment. If it is not, it will be resolved later. Also, if
        // the index is invalid, that will lead to an error later on.
        if ($arraytoken->type === token::VARIABLE) {
            $name = $arraytoken->value . "[$index]";
            return new token(token::VARIABLE, $name, $arraytoken->row, $arraytoken->column);
        }

        // Before accessing the array or string, we validate the index and, if necessary,
        // we translate a negative index to the corresponding positive value.
        $array = $arraytoken->value;
        $index = $this->validate_array_or_string_index($array, $index, $nexttoken);
        $element = $array[$index];

        // If we are accessing a string's char, we create a new string token.
        if ($arraytoken->type === token::STRING) {
            return new token(token::STRING, $element, $arraytoken->row, $arraytoken->column + $index);
        }
        // Otherwise, the element is already wrapped in a token.
        return $element;
    }

    /**
     * Build a list of (NUMBER) tokens based on a range definition. The lower and upper limit
     * and, if present, the step will be taken from the stack.
     *
     * @return array
     */
    private function build_range(): array {
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
        if ($step == 0) {
            $this->die(get_string('error_stepzero', 'qtype_formulas'), $steptoken);
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
            $this->die(get_string('error_samestartend', 'qtype_formulas'), $endtoken);
        }

        if (($end - $start) * $step < 0) {
            if ($parts === 3) {
                $a = (object)['start' => $start, 'end' => $end, 'step' => $step];
                $this->die(get_string('error_emptyrange', 'qtype_formulas', $a), $steptoken);
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

    /**
     * Create a SET or LIST token based on elements on the stack.
     *
     * @param string $type whether to build a SET or a LIST
     * @return token
     */
    private function build_set_or_array(string $type): token {
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

    /**
     * Whether a given OPERATOR token is an unary operator.
     *
     * @param token $token
     * @return bool
     */
    private function is_unary_operator(token $token): bool {
        return in_array($token->value, ['_', '!', '~']);
    }

    /**
     * Whether a given OPERATOR token expects its argument(s) to be numbers.
     *
     * @param token $token
     * @return bool
     */
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
     * @param bool $enforcenumeric whether the value must be numeric in addition to being scalar
     * @return void
     * @throws Exception
     */
    private function abort_if_not_scalar(token $token, bool $enforcenumeric = true): void {
        $found = '';
        $a = (object)[];
        if ($token->type !== token::NUMBER) {
            if ($token->type === token::SET) {
                $found = '_algebraicvar';
                $value = "algebraic variable";
            } else if ($token->type === token::LIST) {
                $found = '_list';
                $value = "list";
            } else if ($enforcenumeric) {
                // Let's be lenient if the token is not a NUMBER, but its value is numeric.
                if (is_numeric($token->value)) {
                    return;
                }
                $a->found = "'{$token->value}'";
            } else if ($token->type === token::STRING) {
                return;
            }
            $expected = ($enforcenumeric ? 'number' : 'scalar');

            $this->die(get_string("error_expected_{$expected}_found{$found}", 'qtype_formulas', $a), $token);
        }
    }

    /**
     * Whether a given OPERATOR token is a binary operator.
     *
     * @param token $token
     * @return bool
     */
    public static function is_binary_operator(token $token): bool {
        $binaryoperators = ['=', '**', '*', '/', '%', '+', '-', '<<', '>>', '&', '^',
            '|', '&&', '||', '<', '>', '==', '>=', '<=', '!='];

        return in_array($token->value, $binaryoperators);
    }

    /**
     * Assign a value to a variable. The value and the variable name are taken from the stack.
     *
     * @param boolean $israndomvar
     * @return token the assigned value
     */
    private function execute_assignment($israndomvar = false): token {
        $what = $this->pop_real_value();
        $destination = array_pop($this->stack);

        // When storing a value in a variable, the row and column should be
        // set to the row and column of the variable token.
        $what->row = $destination->row;
        $what->column = $destination->column;

        // The destination must be a variable token.
        if ($destination->type !== token::VARIABLE) {
            $this->die(get_string('error_variablelhs', 'qtype_formulas'), $destination);
        }
        return $this->set_variable_to_value($destination, $what, $israndomvar);
    }

    /**
     * Evaluate a ternary expression, taking the arguments from the stack.
     *
     * @param token $optoken token that led to this function being called, for better error reporting
     * @return token evaluation result
     */
    private function execute_ternary_operator(token $optoken) {
        // For good error reporting, we first check, whether there are enough arguments on
        // the stack. We subtract one, because there is a sentinel token.
        if (count($this->stack) - 1 < 3) {
            $this->die(get_string('error_ternary_notenough', 'qtype_formulas'), $optoken);
        }
        $else = array_pop($this->stack);
        $then = array_pop($this->stack);
        // The user might not have provided enough arguments for the ternary operator (missing 'else'
        // part), but there might be other elements on the stack from earlier operations (or a LHS variable
        // for an upcoming assignment). In that case, the intended 'then' token has been popped as
        // the 'else' part and we have now read the '%%ternary-sentinel' pseudo-token.
        if ($then->type === token::OPERATOR && $then->value === '%%ternary-sentinel') {
            $this->die(get_string('error_ternary_notenough', 'qtype_formulas'), $then);
        }
        // If everything is OK, we should now arrive at the '%%ternary-sentinel' pseudo-token. Let's see...
        $pseudotoken = array_pop($this->stack);
        if ($pseudotoken->type !== token::OPERATOR && $pseudotoken->value !== '%%ternary-sentinel') {
            $this->die(get_string('error_ternary_notenough', 'qtype_formulas'), $then);
        }

        $condition = $this->pop_real_value();
        return ($condition->value ? $then : $else);
    }

    /**
     * Apply an unary operator to the token that is currently on top of the stack.
     *
     * @param token $token operator token
     * @return token result
     */
    private function execute_unary_operator($token) {
        $input = $this->pop_real_value();

        // Check if the input is numeric. Boolean values are internally treated as 1 and 0 for
        // backwards compatibility.
        if ($this->needs_numeric_input($token)) {
            $this->abort_if_not_scalar($input);
        }

        $result = functions::apply_unary_operator($token->value, $input->value);
        return token::wrap($result);
    }

    /**
     * Apply a binary operator to the two elements currently on top of the stack.
     *
     * @param token $optoken operator token
     * @return token result
     */
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

    /**
     * Check whether the number of parameters is valid for a given function.
     *
     * @param token $function FUNCTION token containing the function name
     * @param int $count number of arguments
     * @return bool
     */
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
        $this->die(get_string('error_unknownfunction', 'qtype_formulas', $funcname), $function);
    }

    /**
     * Lookup the value of a constant and return its value.
     *
     * @param token $token CONSTANT token containing the constant's name
     * @return token value of the requested constant
     */
    private function resolve_constant($token): token {
        if (array_key_exists($token->value, $this->constants)) {
            return new token(token::NUMBER, $this->constants[$token->value], $token->row, $token->column);
        }
        $this->die(get_string('error_undefinedconstant', 'qtype_formulas', $token->value), $token);
    }

    /**
     * Execute a given function, taking the needed argument(s) from the stack.
     *
     * @param token $token FUNCTION token containing the function's name.
     * @return token result
     */
    private function execute_function(token $token): token {
        $funcname = $token->value;

        // Fetch the number of params from the stack. Keep the token in case of an error.
        $numparamstoken = array_pop($this->stack);
        $numparams = $numparamstoken->value;

        // Check if the number of params is valid for the given function. If it is not,
        // die with an error message.
        if (!$this->is_valid_num_of_params($token, $numparams)) {
            $a = (object)['function' => $funcname, 'count' => $numparams];
            $this->die(get_string('error_func_argcount', 'qtype_formulas', $a), $token);
        }

        // Fetch the params from the stack and reverse their order, because the stack is LIFO.
        $params = [];
        for ($i = 0; $i < $numparams; $i++) {
            $params[] = $this->pop_real_value()->value;
        }
        $params = array_reverse($params);

        // If something goes wrong, e. g. wrong type of parameter, functions will throw a TypeError (built-in)
        // or an Exception (custom functions). We catch the exception and build a nice error message.
        try {
            // If we have our own implementation, execute that one. Otherwise, use PHP's built-in function.
            // The special function diff() is defined in the evaluator, so it needs special treatment.
            $isown = array_key_exists($funcname, functions::FUNCTIONS);
            $prefix = '';
            if ($funcname === 'diff') {
                $prefix = self::class . '::';
            } else if ($isown) {
                $prefix = functions::class . '::';
            }
            $result = call_user_func_array($prefix . $funcname, $params);
            // Our own funtions should deal with all sorts of errors and invalid arguments. However,
            // the PHP built-in functions will sometimes return NAN or ±INF, e.g. for sqrt(-2) or log(0).
            // We will check for those return values and output a special error message.
            // Note that for PHP the values NAN, INF and -INF are all numeric, but not finite.
            if (is_numeric($result) && !is_finite($result)) {
                throw new Exception(get_string('error_func_nan', 'qtype_formulas', $funcname));
            }
        } catch (Throwable $e) {
            $this->die($e->getMessage(), $token);
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
