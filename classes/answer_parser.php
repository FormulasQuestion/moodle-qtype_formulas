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

/**
 * Parser for answer expressions for qtype_formulas
 *
 * @package    qtype_formulas
 * @copyright  2022 Philipp Imhof
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

 /* TODO: make validation functions with units */

class answer_parser extends parser {
    /**
     * FIXME Undocumented function
     *
     * @param string|array $tokenlist list of tokens as returned from the lexer or input string
     * @param array $knownvariables
     * @param bool $caretmeanspower whether ^ should be interpreted as exponentiation operator
     */
    public function __construct($tokenlist, array $knownvariables = [], bool $caretmeanspower = true) {
        // If the input is given as a string, run it through the lexer first.
        if (is_string($tokenlist)) {
            $lexer = new lexer($tokenlist);
            $tokenlist = $lexer->get_tokens();
        }

        // In the context of student answers, the caret (^) *always* means exponentiation (**) instead
        // of XOR. In model answers entered by the teacher, the caret *only* means exponentiation
        // for algebraic formulas, but not for the other answer types.
        if ($caretmeanspower) {
            foreach ($tokenlist as $token) {
                if ($token->type === token::OPERATOR && $token->value === '^') {
                    $token->value = '**';
                }
            }
        }

        // FIXME: stop at first semicolon, because answers must be single expressions?

        // FIXME: Filtering?

        // Once this is done, we can parse the expression normally.
        parent::__construct($tokenlist, $knownvariables);
    }

    /**
     * Check whether the given answer contains only valid tokens for the answer type NUMBER, i. e.
     * - just a number, possibly with a decimal point
     * - no operators, except unary + or - at start
     * - possibly followed by e/E (maybe followed by + or -) plus an integer
     *
     * @return boolean
     */
    public function is_valid_number(): bool {
        // The statement list must contain exactly one expression object.
        if (count($this->statements) !== 1) {
            return false;
        }

        $answertokens = $this->statements[0]->body;

        // The first element of the answer expression must be a token of type NUMBER.
        // Note: if the user has entered -5, this has now become [5, _].
        if ($answertokens[0]->type !== token::NUMBER) {
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
     * @return boolean
     */
    public function is_valid_numeric(): bool {
        // If it's a valid number expression, we have nothing to do.
        if ($this->is_valid_number()) {
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
            if ($token->type === token::FUNCTION || $token->typen === token::VARIABLE) {
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
     * @return boolean
     */
    public function is_valid_numerical_formula(): bool {
        if ($this->is_valid_number() || $this->is_valid_numeric()) {
            return true;
        }

        $answertokens = $this->statements[0]->body;

        // Iterate over all tokens. If we find a VARIABLE token, we can stop. If we find
        // a FUNCTION token, we check whether it is in the white list.
        foreach ($answertokens as $token) {
            if ($token->type === token::FUNCTION || $token->typen === token::VARIABLE) {
                return false;
            }
        }

        // Still here? Then it's all good.
        return true;
    }

    /**
     * Check whether the given answer contains only valid tokens for the answer type ALGEBRAIC, i. e.
     * - everything allowed for numerical formulas
     * - all functions and operators except assignment =
     * - variables (maybe only allow registered variables, would avoid student mistake "ab" instead of "a b" or "a*b")
     *
     * @return boolean
     */
    public function is_valid_algebraic(): bool {
        // Algebraic expressions MUST NOT contain the assignment operator =.
        if ($this->has_token_in_tokenlist(token::OPERATOR, '=')) {
            return false;
        }

        return true;
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
}
