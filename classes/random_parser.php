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
 * Parser for random variables for qtype_formulas
 *
 * @package    qtype_formulas
 * @copyright  2022 Philipp Imhof
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


class random_parser extends parser {
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

        // We scan all tokens in order to make a few modifications that are specific
        // to random variables.
        foreach ($tokenlist as $token) {
            // When parsing random variables, we change the assignment operator from '=' to 'r=' in order
            // for the evaluator to know it has to store them as random variables.
            if ($token->type === token::OPERATOR && $token->value === '=') {
                $token->value = 'r=';
            }

            // In legacy code, arrays in random variables always had to be used together with
            // the shuffle() function, e.g. ar = shuffle([1,2,3]) and shuffle() could *only* be
            // used to define random variables. We keep this syntax, but allow defining shuffled
            // arrays without actually writing the function, because if the user did not want the
            // array to be shuffled, they would define it in the global section rather than the
            // random section. Therefore, we silently drop the function while parsing.
            if ($token->type === token::IDENTIFIER && $token->value === 'shuffle') {
                $token->value = '';
                $token->type = token::FUNCTION;
            }
        }

        // Once this is done, we can parse the expression normally.
        parent::__construct($tokenlist, $knownvariables);
    }
}
