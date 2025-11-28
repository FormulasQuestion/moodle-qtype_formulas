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

/**
 * Parser for answer expressions for qtype_formulas
 *
 * @package    qtype_formulas
 * @copyright  2022 Philipp Imhof
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class answer_parser extends parser {
    /** @var array list of operators that may exceptionally appear at the end of the input */
    protected $allowedoperatorsatend = ['%'];

    /**
     * Create a parser for student answers. This class does additional filtering (e. g. block
     * forbidden operators) and syntax checking according to the answer type. It also translates
     * the ^ symbol to the ** operator.
     *
     * @param string|array $tokenlist list of tokens as returned from the lexer or input string
     * @param array $knownvariables
     * @param bool $caretmeanspower whether ^ should be interpreted as exponentiation operator
     * @param bool $formodelanswer whether we are parsing a teacher's model answer (thus allowing \ prefix)
     */
    public function __construct(
        $tokenlist,
        array $knownvariables = [],
        bool $caretmeanspower = true,
        bool $formodelanswer = false
    ) {
        // If the input is given as a string, run it through the lexer first. Also, if we aren't parsing
        // a model answer (coming from the teacher), we replace all commas by points, because there is no
        // situation where the comma would be a valid character. Replacement is only done if the admin
        // settings allow the use of the decimal comma.
        if (is_string($tokenlist)) {
            if (!$formodelanswer && get_config('qtype_formulas', 'allowdecimalcomma')) {
                $tokenlist = str_replace(',', '.', $tokenlist);
            }
            $lexer = new lexer($tokenlist);
            $tokenlist = $lexer->get_tokens();
        }

        foreach ($tokenlist as $token) {
            // In the context of student answers, the caret (^) *always* means exponentiation (**) instead
            // of XOR. In model answers entered by the teacher, the caret *only* means exponentiation
            // for algebraic formulas, but not for the other answer types.
            if ($caretmeanspower) {
                if ($token->type === token::OPERATOR && $token->value === '^') {
                    $token->value = '**';
                }
            }

            // Students are not allowed to use the PREFIX operator.
            if (!$formodelanswer && $token->type === token::PREFIX) {
                $this->die(get_string('error_prefix', 'qtype_formulas'), $token);
            }

            // Answers must currently not contain the semicolon.
            if ($token->type === token::END_OF_STATEMENT) {
                $this->die(get_string('error_unexpectedtoken', 'qtype_formulas', ';'), $token);
            }
        }

        // If we only have one single token and it is an empty string, we set it to the $EMPTY token.
        $firsttoken = reset($tokenlist);
        if (count($tokenlist) === 1 && $firsttoken->value === '') {
            // FIXME: temporarily disabling this
            // $tokenlist[0] = new token(token::EMPTY, '$EMPTY', $firsttoken->row, $firsttoken->column);
        }

        // Once this is done, we can parse the expression normally.
        parent::__construct($tokenlist, $knownvariables);
    }

    /**
     * Perform the right check according to a given answer type.
     *
     * @param int $type the answer type, a constant from the qtype_formulas class
     * @return bool
     */
    public function is_acceptable_for_answertype(int $type, bool $acceptempty = false): bool {
        // An empty answer is never acceptable regardless of the answer type, unless empty fields
        // are explicitly allowed for a question's part.
        // FIXME: this can be removed later
        if (empty($this->tokenlist)) {
            return $acceptempty;
        }
        $firsttoken = reset($this->tokenlist);
        if (count($this->tokenlist) === 1 && $firsttoken->type === token::EMPTY) {
            return $acceptempty;
        }

        if ($type === qtype_formulas::ANSWER_TYPE_NUMBER) {
            return $this->is_acceptable_number();
        }

        if ($type === qtype_formulas::ANSWER_TYPE_NUMERIC) {
            return $this->is_acceptable_numeric();
        }

        if ($type === qtype_formulas::ANSWER_TYPE_NUMERICAL_FORMULA) {
            return $this->is_acceptable_numerical_formula();
        }

        if ($type === qtype_formulas::ANSWER_TYPE_ALGEBRAIC) {
            if (count($this->tokenlist) === 1 && $this->tokenlist[0]->value === '') {
                return $acceptempty;
            }
            return $this->is_acceptable_algebraic_formula();
        }

        // If an invalid answer type has been specified, we simply return false.
        return false;
    }

    /**
     * Check whether the given answer contains only valid tokens for the answer type NUMBER, i. e.
     * - just a number, possibly with a decimal point
     * - no operators, except unary + or - at start
     * - possibly followed by e/E (maybe followed by + or -) plus an integer
     *
     * @return bool
     */
    private function is_acceptable_number(): bool {
        // The statement list must contain exactly one expression object.
        if (count($this->statements) !== 1) {
            return false;
        }

        $answertokens = $this->statements[0]->body;

        // The first element of the answer expression must be a token of type NUMBER or
        // CONSTANT, e.g. pi or π; we currently do not have other named constants.
        // Note: if the user has entered -5, this has now become [5, _].
        if (!in_array($answertokens[0]->type, [token::NUMBER, token::CONSTANT])) {
            return false;
        }
        array_shift($answertokens);

        // If there are no tokens left, everything is fine.
        if (empty($answertokens)) {
            return true;
        }

        // We accept one more token: an unary minus sign (OPERATOR '_'). An unary plus sign
        // would be possible, but it would already have been dropped. For backwards compatibility,
        // we do not accept multiple unary minus signs.
        if (count($answertokens) > 1) {
            return false;
        }
        $token = $answertokens[0];
        return ($token->type === token::OPERATOR && $token->value === '_');
    }

    /**
     * Check whether the given answer contains only valid tokens for the answer type NUMERIC, i. e.
     * - numbers
     * - operators +, -, *, /, ** or ^
     * - round parens ( and )
     * - pi or pi() or π
     * - no functions
     * - no variables
     *
     * @return bool
     */
    private function is_acceptable_numeric(): bool {
        // If it's a valid number expression, we have nothing to do.
        if ($this->is_acceptable_number()) {
            return true;
        }

        // The statement list must contain exactly one expression object.
        if (count($this->statements) !== 1) {
            return false;
        }

        $answertokens = $this->statements[0]->body;

        // Iterate over all tokens.
        foreach ($answertokens as $token) {
            // If we find a FUNCTION or VARIABLE token, we can stop, because those are not
            // allowed in the numeric answer type.
            if ($token->type === token::FUNCTION || $token->type === token::VARIABLE) {
                return false;
            }
            // If we find a STRING literal, we can stop, because those are not
            // allowed in the numeric answer type.
            if ($token->type === token::STRING) {
                return false;
            }
            // If it is an OPERATOR, it has to be +, -, *, /, ^, ** or the unary minus _.
            $allowedoperators = ['+', '-', '*', '/', '^', '**', '_'];
            if ($token->type === token::OPERATOR && !in_array($token->value, $allowedoperators)) {
                return false;
            }
            $isparen = ($token->type & token::ANY_PAREN);
            // Only round parentheses are allowed.
            if ($isparen && !in_array($token->value, ['(', ')'])) {
                return false;
            }
        }

        // Still here? Then let's check the syntax.
        return $this->is_valid_syntax();
    }

    /**
     * Check whether the given answer contains only valid tokens for the answer type NUMERICAL_FORMULA, i. e.
     * - numerical expression
     * - plus functions: sin, cos, tan, asin, acos, atan (but not atan2), sinh, cosh, tanh, asinh, acosh, atanh
     * - plus functions: sqrt, exp, log10, ln, lb, lg (but not log)
     * - plus functions: abs, ceil, floor
     * - plus functions: fact
     * - no variables
     *
     * @return bool
     */
    private function is_acceptable_numerical_formula(): bool {
        if ($this->is_acceptable_number() || $this->is_acceptable_numeric()) {
            return true;
        }

        // Checking whether the expression is valid as an algebraic formula, but with variables
        // being disallowed. This also makes sure that there is one single statement.
        if (!$this->is_acceptable_algebraic_formula(true)) {
            return false;
        }

        // Still here? Then it's all good.
        return true;
    }

    /**
     * Check whether the given answer contains only valid tokens for the answer type ALGEBRAIC, i. e.
     * - everything allowed for numerical formulas
     * - variables (TODO: maybe only allow registered variables, would avoid student mistake "ab" instead of "a b" or "a*b")
     *
     * @param bool $fornumericalformula whether we disallow the usage of variables and the PREFIX operator
     * @return bool
     */
    private function is_acceptable_algebraic_formula(bool $fornumericalformula = false): bool {
        if ($this->is_acceptable_number() || $this->is_acceptable_numeric()) {
            return true;
        }

        // The statement list must contain exactly one expression object.
        if (count($this->statements) !== 1) {
            return false;
        }

        $answertokens = $this->statements[0]->body;

        // Iterate over all tokens. If we find a FUNCTION token, we check whether it is in the white list.
        // Note: We currently restrict the list of allowed functions to functions with only one argument.
        // That assures full backwards compatibility, without limiting our future possibilities w.r.t. the
        // usage of the comma as a decimal separator. We do not currently allow the 'log' function (which
        // would mean the natural logarithm), because it was not allowed in earlier versions, creates ambiguity
        // and would accept two arguments.
        $functionwhitelist = [
            'sin', 'cos', 'tan', 'asin', 'acos', 'atan', 'sinh', 'cosh', 'tanh', 'asinh', 'acosh', 'atanh',
            'sqrt', 'exp', 'log10', 'lb', 'ln', 'lg', 'abs', 'ceil', 'floor', 'fact',
        ];
        $operatorwhitelist = ['+', '_', '-', '/', '*', '**', '^'];
        foreach ($answertokens as $token) {
            // Cut short, if it is a NUMBER or CONSTANT token.
            if (in_array($token->type, [token::NUMBER, token::CONSTANT])) {
                continue;
            }
            // If we find a STRING literal and we are testing for a numerical formula, we can stop,
            // because those are not allowed in that case.
            if ($fornumericalformula && $token->type === token::STRING) {
                return false;
            }

            if ($token->type === token::VARIABLE) {
                if ($fornumericalformula) {
                    return false;
                }
                // If a student writes 'sin 30', the token 'sin' will be classified as a variable,
                // because it is not followed by parentheses. For all numerical answer types, this
                // will invalidate the answer. Hence, the student will see a warning and can correct
                // their answer to 'sin(30)', which is what they probably meant. However, in algebraic
                // formulas, students are allowed to use variables, so the expression is syntactically
                // valid and will be interpreted as 'sin*30' which is most certainly wrong. The
                // following check will make sure that students do not use function names as variables.
                if (self::is_valid_function_name($token->value)) {
                    return false;
                }
                /* TODO: maybe we should reject unknown variables, because that avoids mistakes
                         like student writing a(x+y) = ax + ay instead of a*x or a x.
                if (!$this->is_known_variable($token)) {
                    return false;
                }*/
            }
            if ($token->type === token::FUNCTION && !in_array($token->value, $functionwhitelist)) {
                return false;
            }
            if ($token->type === token::OPERATOR && !in_array($token->value, $operatorwhitelist)) {
                return false;
            }
        }

        // Still here? Then let's check the syntax.
        return $this->is_valid_syntax();
    }

    /**
     * This function determines the index where the numeric part ends and the unit part begins, e.g.
     * for the answer "1.5e3 m^2", that index would be 6.
     * We know that the student cannot (legally) use variables in their answers of type number, numeric
     * or numerical formula. Also, we know that units will be classified as variables. Thus, we can
     * walk through the list of tokens until we reach the first "variable" (actually a unit) and then
     * we know where the unit starts.
     *
     * @return int
     */
    public function find_start_of_units(): int {
        foreach ($this->tokenlist as $token) {
            $isvariable = $token->type === token::VARIABLE;
            // If the % sign is used, we consider it as a unit, because students are not allowed to
            // use the modulo operator.
            $ispercent = $token->type === token::OPERATOR && $token->value === '%';
            if ($isvariable || $ispercent) {
                return $token->column - 1;
            }
        }
        // Still here? That means there is no unit, so it starts very, very far away...
        return PHP_INT_MAX;
    }

    /**
     * Iterate over all tokens and check whether the expression is *syntactically* valid.
     * Note that this does not necessarily mean that the expression can be evaluated:
     * - sqrt(-3) is syntactically valid, but it cannot be calculated
     * - asin(x*y) is syntactically valid, but cannot be evaluated if abs(x*y) > 1
     * - a/(b-b) is syntactically valid, but it cannot be evaluated
     * - a-*b is syntactically invalid, because the operators cannot be chained that way
     *
     * @return bool
     */
    private function is_valid_syntax(): bool {
        $tokens = $this->statements[0]->body;

        // Iterate over all tokens. Push literals (strings, number) and variables on the stack.
        // Operators and functions will consume them, but not evaluate anything. In the end, there
        // should be only one single element on the stack.
        $stack = [];
        foreach ($tokens as $token) {
            if (self::could_be_argument($token)) {
                $stack[] = $token;
            }
            if ($token->type === token::OPERATOR) {
                // Check whether the operator is unary. We also include operators that are not
                // actually allowed in a student's answer. Unary operators would operate on
                // the last token on the stack, but as we do not evaluate anything, we just
                // drop them.
                if (in_array($token->value, ['_', '!', '~'])) {
                    continue;
                }
                // All other operators are binary, because the student cannot use the ternary
                // operator in their answer. Also, they are not allowed other than round parens,
                // so there can be no %%rangebuild or similar pseudo-operators in the queue.
                // A binary operator would pop the two top elements, do its magic and then push
                // the result on the stack. So we first check that the two top-most elements are
                // literals (string, number, constant, variable), before dropping them. Note that
                // we do not check whether the elements are valid input values for the operator,
                // e. g. we would accept two strings (or the number zero) for a division operator.
                $first = array_pop($stack);
                if (!self::could_be_argument($first)) {
                    return false;
                }
                // We do not pop the second argument, because we will later need to put
                // the "result" of the operation back onto the stack anyway.
                $second = end($stack);
                if ($second === false || !self::could_be_argument($second)) {
                    return false;
                }
                // Check has passed. We do not put the operator on the stack, because it has
                // been "consumed" by operating on the two arguments.
                continue;
            }
            // For functions, the top element on the stack (always a number literal) will indicate
            // the number of arguments to consume. So we pop that element plus one less than what
            // it indicates, meaning we actually drop exactly the number of elements indicated
            // by that element.
            if ($token->type === token::FUNCTION) {
                $n = end($stack)->value;
                // If the top element on the stack was not a number, there must have been a syntax
                // error. This should not happen anymore, but it does no harm to keep the fallback.
                if (!is_numeric($n)) {
                    return false;
                }
                $stack = array_slice($stack, 0, -$n);
            }
        }

        // The element must not be the empty string. As empty() returns true for the number 0, we
        // check whether the element is numeric. If it is, that's fine. Also, the stack must have
        // exactly one element.
        $countok = count($stack) === 1;
        $element = reset($stack);
        $value = $element instanceof token ? $element->value : null;
        $notemptystring = !empty($value) || is_numeric($value);
        return $countok && $notemptystring;
    }

    /**
     * Check whether a given token can be used as an argument to a function or an operator,
     * i. e. whether it is a literal (string, number, constant) or a variable that could
     * contain a literal.
     *
     * @param ?token $token the token to be checked, null is allowed
     * @return bool
     */
    private static function could_be_argument(?token $token): bool {
        // TODO: We can use the null-safe operator once we drop support for PHP 7.4.
        if ($token === null) {
            return false;
        }
        return in_array($token->type, [token::STRING, token::NUMBER, token::VARIABLE, token::CONSTANT]);
    }
}
