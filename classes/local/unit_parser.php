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
 * Parser for units for qtype_formulas
 *
 * @package    qtype_formulas
 * @copyright  2025 Philipp Imhof
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class unit_parser extends parser {

    /** @var array list of used units */
    private array $unitlist = [];

    /**
     * Create a unit parser class and have it parse a given input. The input can be given as a string, in
     * which case it will first be sent to the lexer. If that step has already been made, the constructor
     * also accepts a list of tokens.
     *
     * @param string|array $tokenlist list of tokens as returned from the lexer or input string
     */
    public function __construct($tokenlist) {
        // If the input is given as a string, run it through the lexer first.
        if (is_string($tokenlist)) {
            $lexer = new lexer($tokenlist);
            $tokenlist = $lexer->get_tokens();
        }
        $this->tokenlist = $tokenlist;

        // The unit_parser might have been called on an empty input. If this is the case, we store an
        // empty statement and stop here.
        if (empty($this->tokenlist)) {
            $this->statements[] = [];
            return;
        }

        // Check for unbalanced / mismatched parentheses.
        $this->check_parens();

        // Perform basic syntax check, including classification of IDENTIFIER tokens
        // to UNIT tokens.
        $this->check_syntax();

        // Run the tokens through an adapted shunting yard algorithm to bring them into
        // RPN notation.
        $this->statements[] = shunting_yard::unit_infix_to_rpn($this->tokenlist);
    }

    /**
     * Check if the unit expression respects all syntax constraints, i. e. only valid operators (division,
     * multiplication, exponentiation), valid use of parentheses, only one division operator. We only allow
     * stuff that can be converted into the legacy syntax, because we still rely on the original unit
     * conversion code for now.     *
     */
    protected function check_syntax(): void {
        // Whether we have already seen a slash or a unit and whether we are in an exponent.
        $seenslash = false;
        $seenunit = false;
        $inexponent = false;
        foreach ($this->tokenlist as $token) {
            // The use of functions is not permitted in units, so all identifiers will be classified
            // as UNIT tokens.
            if ($token->type === token::IDENTIFIER) {
                // If inside an exponent, only numbers (and maybe the unary minus) are allowed.
                if ($inexponent) {
                    $this->die(get_string('error_unexpectedtoken', 'qtype_formulas', $token->value), $token);
                }
                // The same unit must not be used more than once.
                if ($this->has_unit_been_used($token)) {
                    $this->die('Unit already used: ' . $token->value, $token);
                }
                $this->unitlist[] = $token->value;
                $token->type = token::UNIT;
                $seenunit = true;
                continue;
            }

            // Do various syntax checks for operators. We do them separately in order to allow
            // for more specific error messages, if needed.
            if ($token->type === token::OPERATOR) {
                // We can only accept an operator if there has been at least one unit before.
                if (!$seenunit) {
                    $this->die(get_string('error_unexpectedtoken', 'qtype_formulas', $token->value), $token);
                }
                // The only operators allowed are exponentiation, multiplication, division and the unary minus.
                // Note that the caret (^) always means exponentiation in the context of units.
                if (!in_array($token->value, ['^', '**', '/', '*', '-'])) {
                    $this->die(get_string('error_unexpectedtoken', 'qtype_formulas', $token->value), $token);
                }
                // The unary minus is only allowed inside an exponent.
                if ($token->value === '-' && !$inexponent) {
                    $this->die(get_string('error_unexpectedtoken', 'qtype_formulas', $token->value), $token);
                }
                // Only the unary minus is allowed inside an exponent.
                if ($inexponent && $token->value !== '-') {
                    $this->die(get_string('error_unexpectedtoken', 'qtype_formulas', $token->value), $token);
                }
                if ($token->value === '^' || $token->value === '**') {
                    $inexponent = true;
                }
                if ($token->value === '/') {
                    if ($seenslash) {
                        $this->die(get_string('error_unexpectedtoken', 'qtype_formulas', $token->value), $token);
                    }
                    $seenslash = true;
                }
                continue;
            }

            // Numbers can only be used as exponents and exponents must always be integers.
            if ($token->type === token::NUMBER) {
                if (!$inexponent) {
                    $this->die(get_string('error_unexpectedtoken', 'qtype_formulas', $token->value), $token);
                }
                if (intval($token->value) != $token->value) {
                    $this->die(get_string('error_integerexpected', 'qtype_formulas', $token->value), $token);
                }
                // Only one number is allowed in an exponent, so after the number the
                // exponent must be finished.
                $inexponent = false;
                continue;
            }

            // Parentheses are allowed, but we don't have to do anything with them now.
            if (in_array($token->type, [token::OPENING_PAREN, token::CLOSING_PAREN])) {
                continue;
            }

            // All other tokens are not allowed.
            $this->die(get_string('error_unexpectedtoken', 'qtype_formulas', $token->value), $token);
        }

        // The last token must be a number, a unit or a closing parenthesis.
        $finaltoken = end($this->tokenlist);
        if (!in_array($finaltoken->type, [token::UNIT, token::NUMBER, token::CLOSING_PAREN])) {
            $this->die(get_string('error_unexpectedtoken', 'qtype_formulas', $token->value), $token);
        }
    }

    /**
     * Check whether a given unit has already been used. As we currently still use the original code for
     * unit conversion stuff, we stick to the same behaviour. Hence, we do not take into account the prefixes,
     * i. e. km and m will be considered as different units.
     *
     * @param token $token token containing the unit
     * @return bool
     */
    protected function has_unit_been_used(token $token): bool {
        return in_array($token->value, $this->unitlist);
    }

    /**
     * Check whether all parentheses are balanced and whether only round parens are used.
     * Otherweise, stop all further processing and output an error message.
     *
     * @return void
     */
    protected function check_parens(): void {
        $parenstack = [];
        foreach ($this->tokenlist as $token) {
            $type = $token->type;
            // We only allow round parens.
            if (($token->type & token::ANY_PAREN) && !($token->type & token::OPEN_OR_CLOSE_PAREN)) {
                $this->die(get_string('error_unexpectedtoken', 'qtype_formulas', $token->value), $token);
            }
            if ($type === token::OPENING_PAREN) {
                $parenstack[] = $token;
            }
            if ($type === token::CLOSING_PAREN) {
                $top = end($parenstack);
                // If stack is empty, we have a stray closing paren.
                if (!($top instanceof token)) {
                    $this->die(get_string('error_strayparen', 'qtype_formulas', $token->value), $token);
                }
                array_pop($parenstack);
            }
        }
        // If the stack of parentheses is not empty now, we have an unmatched opening parenthesis.
        if (!empty($parenstack)) {
            $unmatched = end($parenstack);
            $this->die(get_string('error_parennotclosed', 'qtype_formulas', $unmatched->value), $unmatched);
        }
    }

    /**
     * Translate the given input into a string that can be understood by the legacy unit parser, i. e.
     * following all syntax rules. This allows keeping the old unit conversion system in place until
     * we are ready to eventually replace it.
     *
     * @return string
     */
    public function get_legacy_unit_string(): string {
        $stack = [];

        foreach ($this->statements[0] as $token) {
            // Write numbers and units to the stack.
            if (in_array($token->type, [token::UNIT, token::NUMBER])) {
                $value = $token->value;
                if (is_numeric($value) && $value < 0) {
                    $value = '(' . strval($value) . ')';
                }
                $stack[] = $value;
            }

            // Operators take arguments from stack and stick them together in the appropriate way.
            if ($token->type === token::OPERATOR) {
                $op = $token->value;
                if ($op === '**') {
                    $op = '^';
                }
                if ($op === '*') {
                    $op = ' ';
                }
                $second = array_pop($stack);
                $first = array_pop($stack);
                // With the new syntax, it is possible to write e.g. (m/s^2)*kg. In older versions,
                // everything coming after the / operator will be considered a part of the denominator,
                // so the only way to get the kg into the numerator is to reorder the units and
                // write them as kg*m/s^2. Long story short: if there is a division, it must come last.
                // Note that the syntax currently does not allow more than one /, so we do not need
                // a more sophisticated solution.
                if (strpos($first, '/') !== false) {
                    list($second, $first) = [$first, $second];
                }
                // Legacy syntax allowed parens around the entire denominator, so we do that unless the
                // denominator is just one unit.
                if ($op === '/' && !preg_match('/^[A-Za-z]+$/', $second)) {
                    $second = '(' . $second . ')';
                }
                $stack[] = $first . $op . $second;
            }
        }

        return implode('', $stack);
    }
}
