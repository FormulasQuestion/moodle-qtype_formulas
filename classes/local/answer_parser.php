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

 /* FIXME-TODO: make validation function with units --> add unit tests, should be working, because unit/number are split before check */

class answer_parser extends parser {
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
    public function __construct($tokenlist, array $knownvariables = [], bool $caretmeanspower = true, bool $formodelanswer = false) {
        // If the input is given as a string, run it through the lexer first.
        if (is_string($tokenlist)) {
            $lexer = new lexer($tokenlist);
            $tokenlist = $lexer->get_tokens();
        }

        $precededbyprefix = false;
        foreach ($tokenlist as $token) {
            // In the context of student answers, the caret (^) *always* means exponentiation (**) instead
            // of XOR. In model answers entered by the teacher, the caret *only* means exponentiation
            // for algebraic formulas, but not for the other answer types.
            if ($caretmeanspower) {
                if ($token->type === token::OPERATOR && $token->value === '^') {
                    $token->value = '**';
                }
            }

            // Students are not allowed to use function names as variables, e.g. they cannot use a
            // variable 'sin'. This is important, because teachers have that option and the regular
            // parser will automatically consider 'sin' in the expression '3*sin x' as a variable,
            // due to the missing parens. We want to avoid that, because it would conceal a syntax
            // error. We make one exception: if the identifier has been labelled as a known variable,
            // the token will be considered as a variable. This allows the teacher to use e.g. 'exp'
            // as a unit name, if they want to.
            if ($token->type === token::IDENTIFIER) {
                if (in_array($token->value, $knownvariables) && !$precededbyprefix) {
                    $token->type = token::VARIABLE;
                } else if (array_key_exists($token->value, functions::FUNCTIONS + evaluator::PHPFUNCTIONS)) {
                    $token->type = token::FUNCTION;
                }
            }

            if (!$formodelanswer && $token->type === token::PREFIX) {
                $this->die(get_string('error_prefix', 'qtype_formulas'), $token);
            }

            $precededbyprefix = ($token->type === token::PREFIX);
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
    public function is_acceptable_for_answertype(int $type): bool {
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
            return $this->is_acceptable_algebraic_formula();
        }
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
     * - operators +, -, *, ** or ^
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
            // The PREFIX operator must not be used in numeric answers.
            if ($token->type === token::PREFIX) {
                return false;
            }

            // If we find a FUNCTION or VARIABLE token, we can stop, because those are not
            // allowed in the numeric answer type.
            if ($token->type === token::FUNCTION || $token->type === token::VARIABLE) {
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

        // Still here? Then it's all good.
        return true;
    }

    /**
     * Check whether the given answer contains only valid tokens for the answer type NUMERICAL_FORMULA, i. e.
     * - numerical expression
     * - plus functions: sin, cos, tan, asin, acos, atan, atan2, sinh, cosh, tanh, asinh, acosh, atanh
     * - plus functions: sqrt, exp, log, log10, ln
     * - plus functions: abs, ceil, floor
     * - plus functions: fact, ncr, npr
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
     * - all functions and operators except assignment =
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
        $functionwhitelist = [
            'sin', 'cos', 'tan', 'asin', 'acos', 'atan', 'atan2', 'sinh', 'cosh', 'tanh', 'asinh', 'acosh', 'atanh',
            'sqrt', 'exp', 'log', 'log10', 'ln', 'abs', 'ceil', 'floor', 'fact', 'ncr', 'npr'
        ];
        $operatorwhitelist = ['+', '_', '-', '/', '*', '**', '^', '%'];
        foreach ($answertokens as $token) {
            // Cut short, if it is a NUMBER token.
            if ($token->type === token::NUMBER) {
                continue;
            }
            // The PREFIX operator must not be used in numerical formulas.
            if ($fornumericalformula && $token->type === token::PREFIX) {
                return false;
            }
            if ($token->type === token::VARIABLE) {
                if ($fornumericalformula) {
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
            if ($token->type === token::VARIABLE) {
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
            if (in_array($token->type, [token::STRING, token::NUMBER, token::VARIABLE])) {
                $stack[] = $token->value;
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
                // the result on the stack. As we do not evaluate anything, we simply drop the top
                // element.
                array_pop($stack);
            }
            // For functions, the top element on the stack (always a number literal) will indicate
            // the number of arguments to consume. So we pop that element plus one less than what
            // it indicates, meaning we actually drop exactly the number of elements indicated
            // by that element.
            if ($token->type === token::FUNCTION) {
                $n = end($stack);
                $stack = array_slice($stack, 0, -$n);
            }
        }

        return (count($stack) === 1);
    }

}