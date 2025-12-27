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

use Exception;
use qtype_formulas;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/question/type/formulas/questiontype.php');


/**
 * Class for individual tokens
 *
 * @package    qtype_formulas
 * @copyright  2022 Philipp Imhof
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class token {
    /** @var int all literals (string or number) will have their 1-bit set */
    const ANY_LITERAL = 1;

    /** @var int used to designate a token storing a number */
    const NUMBER = 3;

    /** @var int used to designate a token storing a string literal */
    const STRING = 5;

    /** @var int used to designate the special token used for empty answers */
    const EMPTY = 7;

    /**
     * Parentheses are organised in groups, allowing for bitwise comparison.
     * examples: CLOSING_PAREN & ANY_PAREN = ANY_PAREN
     *           CLOSING_PAREN & ANY_CLOSING_PAREN = ANY_CLOSING_PAREN
     *           CLOSING_PAREN & OPEN_OR_CLOSE_PAREN = OPEN_OR_CLOSE_PAREN
     *           CLOSING_PAREN & CLOSING_BRACKET = ANY_PAREN | ANY_CLOSING_PAREN
     *           OPENING_* ^ CLOSING_COUNTER_PART = ANY_CLOSING_PAREN | ANY_OPENING_PAREN
     *
     *
     * @var int all parentheses have their 8-bit set
     **/
    const ANY_PAREN = 8;

    /** @var int all opening parentheses have their 16-bit set */
    const ANY_OPENING_PAREN = 16;

    /** @var int all closing parentheses have their 32-bit set */
    const ANY_CLOSING_PAREN = 32;

    /** @var int round opening or closing parens have their 64-bit set */
    const OPEN_OR_CLOSE_PAREN = 64;

    /** @var int opening or closing brackets have their 128-bit set */
    const OPEN_OR_CLOSE_BRACKET = 128;

    /** @var int opening or closing braces have their 256-bit set */
    const OPEN_OR_CLOSE_BRACE = 256;

    /** @var int an opening paren must be 8 (any paren) + 16 (opening) + 64 (round paren) = 88 */
    const OPENING_PAREN = 88;

    /** @var int a closing paren must be 8 (any paren) + 32 (closing) + 64 (round paren) = 104 */
    const CLOSING_PAREN = 104;

    /** @var int an opening bracket must be 8 (any paren) + 16 (opening) + 128 (bracket) = 152 */
    const OPENING_BRACKET = 152;

    /** @var int a closing bracket must be 8 (any paren) + 32 (closing) + 128 (bracket) = 168 */
    const CLOSING_BRACKET = 168;

    /** @var int an opening brace must be 8 (any paren) + 16 (opening) + 256 (brace) = 280 */
    const OPENING_BRACE = 280;

    /** @var int a closing brace must be 8 (any paren) + 32 (closing) + 256 (brace) = 296 */
    const CLOSING_BRACE = 296;

    /** @var int identifiers will have their 512-bit set */
    const IDENTIFIER = 512;

    /** @var int function tokens are 512 (identifier) + 1024 = 1536 */
    const FUNCTION = 1536;

    /** @var int variable tokens are 512 (identifier) + 2048 = 2560 */
    const VARIABLE = 2560;

    /** @var int used to designate a token storing the prefix operator */
    const PREFIX = 4096;

    /** @var int used to designate a token storing a constant */
    const CONSTANT = 8192;

    /** @var int used to designate a token storing an operator */
    const OPERATOR = 16384;

    /** @var int used to designate a token storing an argument separator (comma) */
    const ARG_SEPARATOR = 32768;

    /** @var int used to designate a token storing a range separator (colon) */
    const RANGE_SEPARATOR = 65536;

    /** @var int used to designate a token storing an end-of-statement marker (semicolon) */
    const END_OF_STATEMENT = 131072;

    /** @var int used to designate a token storing a reserved word (e. g. for) */
    const RESERVED_WORD = 262144;

    /** @var int used to designate a token storing a list */
    const LIST = 524288;

    /** @var int used to designate a token storing a set */
    const SET = 1048576;

    /** @var int used to designate a token storing a range */
    const RANGE = 1572864;

    /** @var int used to designate a token storing a start-of-group marker (opening brace) */
    const START_GROUP = 2097152;

    /** @var int used to designate a token storing an end-of-group marker (closing brace) */
    const END_GROUP = 4194304;

    /** @var int used to designate a token storing a unit */
    const UNIT = 8388608;

    /** @var mixed the token's content, will be the name for identifiers */
    public $value;

    /** @var mixed additional information, e. g. the form how a number was entered */
    public $metadata;

    /** @var int token type, e.g. number or string */
    public int $type;

    /** @var int row in which the token starts */
    public int $row;

    /** @var int column in which the token starts */
    public int $column;

    /**
     * Constructor.
     *
     * @param int $type the type of the token
     * @param mixed $value the value (e.g. name of identifier, string content, number value, operator)
     * @param int $row row where the token starts in the input stream
     * @param int $column column where the token starts in the input stream
     * @param mixed $metadata additional information (e.g. the form how a number was entered)
     */
    public function __construct(int $type, $value, int $row = -1, int $column = -1, $metadata = null) {
        $this->value = $value;
        $this->metadata = $metadata;
        $this->type = $type;
        $this->row = $row;
        $this->column = $column;
    }

    /**
     * Convert token to a string.
     *
     * @return string
     */
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

    /**
     * Wrap a given value (e. g. a number) into a token. If no specific type is requested, the
     * token type will be derived from the value.
     *
     * @param mixed $value value to be wrapped
     * @param int $type if desired, type of the resulting token (use pre-defined constants)
     * @param int $carry intermediate count, useful when recursively wrapping arrays
     * @return token
     */
    public static function wrap($value, $type = null, $carry = 0): token {
        // If the value is already a token, we do nothing.
        if ($value instanceof token) {
            return $value;
        }
        // If a NUMBER token is requested, we check whether the value is numeric. If
        // it is, we convert it to float. Otherwise, we throw an error.
        if ($type == self::NUMBER) {
            if (!is_numeric(($value))) {
                throw new Exception(get_string('error_wrapnumber', 'qtype_formulas'));
            }
            $value = floatval($value);
        }
        // If a STRING token is requested, we make sure the value is a string. If that is not
        // possible, throw an error.
        if ($type == self::STRING) {
            try {
                // We do not allow implicit conversion of array to string.
                if (gettype($value) === 'array') {
                    throw new Exception(get_string('error_wrapstring', 'qtype_formulas'));
                }
                $value = strval($value);
            } catch (Exception $e) {
                throw new Exception(get_string('error_wrapstring', 'qtype_formulas'));
            }
        }
        // If a specific type is requested, we return a token with that type.
        if ($type !== null) {
            return new token($type, $value);
        }
        // Otherwise, we choose the appropriate type ourselves.
        if (is_string($value)) {
            $type = self::STRING;
        } else if (is_float($value) || is_int($value)) {
            $type = self::NUMBER;
        } else if (is_array($value)) {
            $type = self::LIST;
            $count = $carry;
            // Values must be wrapped recursively.
            foreach ($value as &$val) {
                if (is_array($val)) {
                    $count += count($val);
                } else {
                    $count++;
                }

                if ($count > qtype_formulas::MAX_LIST_SIZE) {
                    throw new Exception(get_string('error_list_too_large', 'qtype_formulas', qtype_formulas::MAX_LIST_SIZE));
                }

                $val = self::wrap($val, null, $count);
            }
        } else if (is_bool($value)) {
            // Some PHP functions (e. g. is_nan and similar) will return a boolean value. For backwards
            // compatibility, we will convert this into a number with TRUE = 1 and FALSE = 0.
            $type = self::NUMBER;
            $value = ($value ? 1 : 0);
        } else if ($value instanceof lazylist) {
            $type = self::SET;
        } else {
            if (is_null($value)) {
                $value = 'null';
            }
            throw new Exception(get_string('error_tokenconversion', 'qtype_formulas', $value));
        }
        return new token($type, $value);
    }

    /**
     * Extract the value from a token.
     *
     * @param token $token
     * @return mixed
     */
    public static function unpack($token) {
        // For convenience, we also accept elementary types instead of tokens, e.g. literals.
        // In that case, we have nothing to do, we just return the value. Unless it is an
        // array, because those might contain tokens.
        if (!($token instanceof token)) {
            if (is_array($token)) {
                $result = [];
                foreach ($token as $value) {
                    $result[] = self::unpack($value);
                }
                return $result;
            }
            return $token;
        }
        // If the token value is a literal (number or string), return it directly.
        if (in_array($token->type, [self::NUMBER, self::STRING])) {
            return $token->value;
        }
        // If the token is the $EMPTY token, return the string '$EMPTY'.
        if ($token->type === self::EMPTY) {
            return '$EMPTY';
        }

        // If the token is a list or set, we have to unpack all elements separately and recursively.
        if (in_array($token->type, [self::LIST, self::SET])) {
            $result = [];
            foreach ($token->value as $value) {
                $result[] = self::unpack($value);
            }
        }
        return $result;
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

    /**
     * Recursively count the tokens inside this token. This is useful for nested lists only.
     *
     * @param token $token the token to be counted
     * @return int
     */
    public static function recursive_count(token $token): int {
        $count = 0;

        // Literals consist of one single token.
        if (in_array($token->type, [self::NUMBER, self::STRING])) {
            return 1;
        }

        // For lists, we recursively count all tokens.
        if ($token->type === self::LIST) {
            $elements = $token->value;
            foreach ($elements as $element) {
                $count += self::recursive_count($element);
            }
        }

        return $count;
    }
}
