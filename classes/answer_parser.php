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


class answer_parser extends parser {
    /**
     * FIXME Undocumented function
     *
     * @param [type] $tokenlist list of tokens as returned from the lexer or input string
     * @param [type] $knownvariables
     */
    public function __construct($tokenlist, array $knownvariables = []) {
        // If the input is given as a string, run it through the lexer first.
        if (is_string($tokenlist)) {
            $lexer = new lexer($tokenlist);
            $tokenlist = $lexer->get_tokens();
        }

        // When parsing an answer expression, we have to replace all ^ operators (XOR) by
        // ** operators (exponentiation) in order to maintain backwards compatibility.
        foreach ($tokenlist as $token) {
            if ($token->type === token::OPERATOR && $token->value === '^') {
                $token->value = '**';
            }
        }

        // TODO/FIXME: add some filtering, according to answer type, e.g.
        // * number, no operators and stuff at all, except for unary +/- (at start) and e (scientific notation with +/-)
        // * numeric, allow +, -, *, /, ** or ^, parens and pi, but no functions
        // * numerical formula, allow some functions; cf. func_algebraic in legacy variables.php
        // * algebraic formula, allow everything, but with ^ still being **

        // Once this is done, we can parse the expression normally.
        parent::__construct($tokenlist, $knownvariables);
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
        // Still here? That means there is no unit, so it starts very, very
        // far away...
        return PHP_INT_MAX;
    }

}
