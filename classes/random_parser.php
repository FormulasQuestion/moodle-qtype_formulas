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

        // When parsing random variables, we change the assignment operator from '=' to 'r=' in order
        // for the evaluator to know it has to store them as random variables.
        foreach ($tokenlist as $token) {
            if ($token->type === token::OPERATOR && $token->value === '=') {
                $token->value = 'r=';
            }
        }

        // Once this is done, we can parse the expression normally.
        parent::__construct($tokenlist, $knownvariables);
    }
}
