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
     * @param [type] $tokenlist
     * @param [type] $knownvariables
     */
    public function __construct(array $tokenlist, array $knownvariables = []) {
        // FIXME: add parameter for answer type (number, numeric, numerical formula, algebraic formula)

        // When parsing an answer expression, we have to replace all ^ operators (XOR) by
        // ** operators (exponentiation) in order to maintain backwards compatibility.
        foreach ($tokenlist as $token) {
            if ($token->type === token::OPERATOR && $token->value === '^') {
                $token->value = '**';
            }
        }

        // FIXME: add some filtering, according to answer type, e.g.
        // * number (no operators and stuff at all, except for unary -)
        // * numeric (allow +, -, *, /, ** or ^, parens and pi)
        // * numerical formula (allow some functions; cf. func_algebraic in legacy variables.php)
        // * algebraic formula (allow everything, but with ^ still being **)

        // Once this is done, we can parse the expression normally.
        parent::__construct($tokenlist);
    }
}
