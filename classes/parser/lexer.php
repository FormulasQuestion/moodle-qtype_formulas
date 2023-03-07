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

/**
 * Lexer for qtype_formulas
 *
 * @package    qtype_formulas
 * @copyright  2022 Philipp Imhof
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace qtype_formulas;

defined('MOODLE_INTERNAL') || die();

/**
 * Class for individual tokens
 *
 * @package    qtype_formulas
 * @copyright  2022 Philipp Imhof
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class Token {
    const NUMBER = 1;
    const STRING = 2;
    const LIST = 4;
    const SET = 8;
    const PREFIX = 16;
    const IDENTIFIER = 32;
    const FUNCTION = 64;
    const VARIABLE = 128;
    const CONSTANT = 129;
    const OPERATOR = 256;
    const OPENING_PAREN = 512;
    const CLOSING_PAREN = 1024;
    const OPENING_BRACKET = 513;
    const CLOSING_BRACKET = 1025;
    const OPENING_BRACE = 514;
    const CLOSING_BRACE = 1026;
    const ARG_SEPARATOR = 2048;
    const END_OF_STATEMENT = 4096;
    const RESERVED_WORD = 8192;

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
     * @param integer $type the type of the token
     * @param mixed $value the value (e.g. name of identifier, string content, number value, operator)
     */
    public function __construct($type, $value, $row = -1, $column = -1) {
        $this->value = $value;
        $this->type = $type;
        $this->row = $row;
        $this->column = $column;
    }
}

/**
 * Formulas Question Lexer class
 *
 * @package    qtype_formulas
 * @copyright  2022 Philipp Imhof
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class Lexer {
    const EOF = InputStream::EOF;

    /** @var InputStream input stream */
    private $inputstream = null;

    /**
     * Constructor
     *
     * @param string $str the input to be tokenized
     */
    public function __construct($str) {
        $this->inputstream = new InputStream($str);
    }

    /**
     * Go through the entire input and fetch all non-comment tokens.
     *
     * @return array list of tokens
     */
    public function get_token_list() {
        $currenttoken = $this->read_next_token();
        $tokenlist = [];
        while ($currenttoken !== self::EOF) {
            $tokenlist[] = $currenttoken;
            $currenttoken = $this->read_next_token();
        }

        return $tokenlist;
    }

    /**
     * Read the next token from the input stream. Making the method public for easier testing.
     *
     * @return Token the token
     */
    private function read_next_token() {
        // Check the next char and quit if we are at the end of the stream.
        $currentchar = $this->inputstream->peek();
        if ($currentchar === InputStream::EOF) {
            return self::EOF;
        }
        // Skip all white space.
        $this->consume_whitespace();
        $currentchar = $this->inputstream->peek();
        // If we have a # character, this is the start of a comment.
        if ($currentchar === '#') {
            $this->consume_comment();
            $currentchar = $this->inputstream->peek();
        }
        // If there is nothing after stripping whitespace and comments, we may quit.
        if ($currentchar === InputStream::EOF) {
            return self::EOF;
        }
        // If we have a " or ' character, this is the start of a string.
        if ($currentchar === '"' || $currentchar === "'") {
            return $this->read_string();
        }
        // If we are at the start of a number, return that number as the next token.
        if (preg_match('/[0-9]/', $currentchar)) {
            return $this->read_number();
        }
        // The decimal point counts as the start of a number, iff it is followed by a digit.
        if ($currentchar === '.' && preg_match('/[0-9]/', $this->inputstream->peek(1))) {
            return $this->read_number();
        }
        // A letter indicates the start of an identifier, i. e. a variable or function name.
        if (preg_match('/[_A-Za-z]/', $currentchar)) {
            return $this->read_identifier();
        }
        // Operators always start with specific characters and may be up to two characters long.
        if (preg_match('/[-+*\/%=&|~^<>!?:]/', $currentchar)) {
            return $this->read_operator();
        }
        // There are some single-character tokens...
        if (preg_match('/[]\[(){},;π\\\]/', $currentchar)) {
            $types = [
                '[' => Token::OPENING_BRACKET,
                '(' => Token::OPENING_PAREN,
                '{' => Token::OPENING_BRACE,
                ']' => Token::CLOSING_BRACKET,
                ')' => Token::CLOSING_PAREN,
                '}' => Token::CLOSING_BRACE,
                ',' => Token::ARG_SEPARATOR,
                '\\' => Token::PREFIX,
                ';' => Token::END_OF_STATEMENT,
                'π' => Token::CONSTANT
            ];
            return $this->read_single_char_token($types[$currentchar]);
        }
        // If we are still here, that's not good at all. We need to read the char (it is only peeked so far)
        // in order for the inputstream to be at the right position.
        $this->inputstream->read();
        $this->inputstream->die("unexpected input: '$currentchar'");
    }

    /**
     * FIXME Undocumented function
     *
     * @param [type] $type
     * @return void
     */
    private function read_single_char_token($type) {
        $char = $this->inputstream->read();
        $startingposition = $this->inputstream->get_position();
        return new Token($type, $char, $startingposition['row'], $startingposition['column']);
    }

    /**
     * Read a number token and return it as a float.
     *
     * @return Token the number token
     */
    private function read_number() {
        // Start by reading the first char. If we are here, that means it was a number or a decimal point.
        $currentchar = $this->inputstream->read();

        // Save starting position of the number.
        $startingposition = $this->inputstream->get_position();

        // A number can only have one decimal point and one exponent (for scientific notation) at most.
        $hascomma = ($currentchar === '.');
        $hasexponent = false;

        // Save the first character.
        $result = $currentchar;
        while ($currentchar !== InputStream::EOF) {
            // Look at the next char and decide what to do.
            $nextchar = $this->inputstream->peek();
            if ($nextchar === '.') {
                // A decimal point is only valid, if we don't have one yet and if we are in the mantissa.
                if ($hascomma || $hasexponent) {
                    break;
                }
                // Keep track that we do now have a decimal point in the number.
                $hascomma = true;
            } else if ($nextchar === 'e' || $nextchar === 'E') {
                // An exponent is only valid, if we don't have one yet.
                if ($hasexponent) {
                    break;
                }
                // Also, an exponent must be followed either by a digit or by a plus/minus sign *and* a digit.
                // If it is not, it might be the start of an identifier or a syntax error, but that's not the question.
                $followedby = $this->inputstream->peek(1);
                if (preg_match('/[0-9]/', $followedby)) {
                    $hasexponent = true;
                } else if (preg_match('/[-+]/', $followedby) && preg_match('/[0-9]/', $this->inputstream->peek(2))) {
                    $hasexponent = true;
                    // In this particular case, we will store two characters. The first one (e or E) must be
                    // read now, the second one (+ or -) will follow at the end of the loop.
                    $currentchar = $this->inputstream->read();
                    $result .= $currentchar;
                } else {
                    // We had an e or E, but it is not the start of an exponent, so we drop out. The e or E
                    // must be part of the next token.
                    break;
                }
            } else if (!preg_match('/[0-9]/', $nextchar)) {
                // We have covered all special cases. So, if the character is not a digit, we must stop here.
                break;
            }
            $currentchar = $this->inputstream->read();
            $result .= $currentchar;
        }
        return new Token(Token::NUMBER, floatval($result), $startingposition['row'], $startingposition['column']);
    }

    /**
     * Read a string token
     *
     * @return Token the string token
     */
    private function read_string() {
        // Start by reading the opening delimiter, either a " or a ' character.
        $opener = $this->inputstream->read();

        // Record position of the opening delimiter.
        $startingposition = $this->inputstream->get_position();

        $result = '';
        $currentchar = $this->inputstream->peek();
        while ($currentchar !== InputStream::EOF) {
            $nextchar = $this->inputstream->peek();
            // A backslash could be used to escape the opening/closing delimiter inside the string.
            if ($nextchar == '\\') {
                $followedby = $this->inputstream->peek(1);
                if ($followedby === $opener) {
                    // Consume the backslash. The quote will be appended later.
                    $this->inputstream->read();
                } else if ($followedby === 't' || $followedby === 'n') {
                    $this->inputstream->read();
                    $currentchar = $this->inputstream->read();
                    $result .= ($followedby === 't' ? "\t" : "\n");
                    continue;
                }
            } else if ($nextchar === $opener) {
                $this->inputstream->read();
                return new Token(Token::STRING, $result, $startingposition['row'], $startingposition['column']);
            }
            $currentchar = $this->inputstream->read();
            $result .= $currentchar;
        }
        // Still here? That means the string has not been closed.
        $this->inputstream->die(
            "unterminated string, started at row {$startingposition['row']}, column {$startingposition['column']}"
        );
    }

    /**
     * Read an identifier token (function name, variable name).
     *
     * @return Token the identifier token
     */
    private function read_identifier() {
        // Start by reading the first char. If we are here, that means it was a number or a decimal point.
        $currentchar = $this->inputstream->read();
        $result = $currentchar;

        // Record position of the opening delimiter.
        $startingposition = $this->inputstream->get_position();

        while ($currentchar !== InputStream::EOF) {
            $nextchar = $this->inputstream->peek();
            // Identifiers may contain letters, digits or underscores.
            if (!preg_match('/[A-Za-z0-9_]/', $nextchar)) {
                break;
            }
            $currentchar = $this->inputstream->read();
            $result .= $currentchar;
        }
        if ($result === 'for') {
            $type = Token::RESERVED_WORD;
        } else if (preg_match('/^(pi|PI|Pi)$/', $result)) {
            $type = Token::CONSTANT;
            $result = 'π';
        } else {
            $type = Token::IDENTIFIER;
        }
        return new Token($type, $result, $startingposition['row'], $startingposition['column']);
    }

    /**
     * Read an operator token
     *
     * @return Token the operator token
     */
    private function read_operator() {
        // Start by reading the first char. If we are here, that means it was a number or a decimal point.
        $currentchar = $this->inputstream->read();
        $result = $currentchar;

        // Record position of the opening delimiter.
        $startingposition = $this->inputstream->get_position();

        // Some chars might be the start of a two-character operator. Those are:
        // ** << >> == != >= <= && ||
        // Let's look at the following character...
        $followedby = $this->inputstream->peek();
        if (preg_match('/[*=&|<>]/', $followedby)) {
            // In most cases, two-character operators have the same character twice.
            // The only exceptions are !=, <= and >= where the second char is always the equal sign.
            if (($currentchar === $followedby)
                || ($followedby === '=' && preg_match('/[!<>]/', $currentchar))) {
                $result .= $this->inputstream->read();
            }
        }
        return new Token(Token::OPERATOR, $result, $startingposition['row'], $startingposition['column']);
    }

    /**
     * Read until the end of the line, because comments end with a newline character.
     *
     * @return void
     */
    private function consume_comment() {
        $currentchar = $this->inputstream->peek();
        while ($currentchar !== "\n" && $currentchar !== InputStream::EOF) {
            $currentchar = $this->inputstream->read();
        }
        // Eat up all white space following the comment.
        $this->consume_whitespace();
    }

    /**
     * Eat up all white space until the start of the next token.
     *
     * @return void
     */
    private function consume_whitespace() {
        $currentchar = $this->inputstream->peek();
        while (preg_match('/\s/', $currentchar)) {
            $this->inputstream->read();
            $currentchar = $this->inputstream->peek();
        }
    }
}
