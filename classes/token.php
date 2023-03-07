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
 * Class for individual tokens
 *
 * @package    qtype_formulas
 * @copyright  2022 Philipp Imhof
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class token {
    // Literals (string or number) will have their 1-bit set.
    const ANY_LITERAL = 1;
    const NUMBER = 3;
    const STRING = 5;

    /* Parentheses are organised in groups, allowing for bitwise comparison.
       We set the 8-bit for any parenthesis plus the 16-bit for opening and the 32-bit for closing parens.
       examples: CLOSING_PAREN & ANY_PAREN = ANY_PAREN
                 CLOSING_PAREN & ANY_CLOSING_PAREN = ANY_CLOSING_PAREN
                 CLOSING_PAREN & OPEN_OR_CLOSE_PAREN = OPEN_OR_CLOSE_PAREN
                 CLOSING_PAREN & CLOSING_BRACKET = ANY_PAREN | ANY_CLOSING_PAREN
                 CLOSING_PAREN & ANY_OPENING_PAREN =
    */
    const ANY_PAREN = 8;
    const ANY_OPENING_PAREN = 16;
    const ANY_CLOSING_PAREN = 32;
    const OPEN_OR_CLOSE_PAREN = 64;
    const OPEN_OR_CLOSE_BRACKET = 128;
    const OPEN_OR_CLOSE_BRACE = 256;
    const OPENING_PAREN = 88;
    const CLOSING_PAREN = 104;
    const OPENING_BRACKET = 152;
    const CLOSING_BRACKET = 168;
    const OPENING_BRACE = 280;
    const CLOSING_BRACE = 296;

    // Identifiers will have their 512-bit set.
    const IDENTIFIER = 512;
    const FUNCTION = 1536;
    const VARIABLE = 2560;

    // Other types.
    const PREFIX = 4096;
    const CONSTANT = 8192;
    const OPERATOR = 16384;
    const ARG_SEPARATOR = 32768;
    const END_OF_STATEMENT = 65536;
    const RESERVED_WORD = 131072;
    const LIST = 262144;
    const SET = 524288;

    /** @var mixed the token's content */
    public $value;

    /** @var integer token type, e.g. number or string */
    public $type;

    /** @var integer row in which the token starts */
    public $row;

    /** @var integer column in which the token starts */
    public $column;

    /**
     * Constructor
     *
     * @param int $type the type of the token
     * @param mixed $value the value (e.g. name of identifier, string content, number value, operator)
     * @param int $row row where the token starts in the input stream
     * @param int $column column where the token starts in the input stream
     */
    public function __construct(int $type, $value, int $row = -1, int $column = -1) {
        $this->value = $value;
        $this->type = $type;
        $this->row = $row;
        $this->column = $column;
    }
}
