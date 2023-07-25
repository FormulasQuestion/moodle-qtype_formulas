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
use Exception;

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
                 OPENING_* ^ CLOSING_COUNTER_PART = ANY_CLOSING_PAREN | ANY_OPENING_PAREN
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
    const RANGE_SEPARATOR = 65536;
    const END_OF_STATEMENT = 131072;
    const RESERVED_WORD = 262144;
    const LIST = 524288;
    const SET = 1048576;
    const START_GROUP = 2097152;
    const END_GROUP = 4194304;

    /** @var mixed the token's content, will be the name for identifiers */
    public $value;

    /** @var int token type, e.g. number or string */
    public $type;

    /** @var int row in which the token starts */
    public $row;

    /** @var int column in which the token starts */
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

    public function __toString() {
        // Arrays are printed in their [...] form, sets are printed as {...}.
        if (gettype($this->value) === 'array') {
            $result = self::stringify_array($this->value);
            if ($this->type === self::SET) {
                return '{' . substr($result, 1, -1) . '}';
            }
            return $result;
        }

        // For everything else, we use PHP's string conversion.
        return strval($this->value);
    }

    public static function wrap($value, $type = null) {
        // If the value is already a token, we do nothing.
        if ($value instanceof token) {
            return $value;
        }
        // If a specific type is requested, we create a token with that type.
        if ($type !== null) {
            return new token($type, $value);
        }
        // Otherwise, we choose the appropriate type.
        if (is_string($value)) {
            $type = self::STRING;
        } else if (is_float($value) || is_int($value)) {
            $type = self::NUMBER;
        } else if (is_array($value)) {
            // FIXME: this probably needs some more treatment
            $type = self::LIST;
        } else {
            throw new Exception("the given value '$value' has an invalid data type an cannot be converted to a token");
        }
        return new token($type, $value);
    }

    /**
     * Recursively convert an array to a string.
     *
     * @param array $arr the array to be converted
     */
    public static function stringify_array($arr): string {
        $result = '[';
        foreach ($arr as $element) {
            if (gettype($element) === 'array') {
                $result .= self::stringify_array($element);
            } else {
                $result .= strval($element);
            }
            $result .= ', ';
        }
        $result .= ']';
        $result = str_replace(', ]', ']', $result);
        return $result;
    }

}
