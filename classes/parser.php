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
 * Parser for qtype_formulas
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
    const IDENTIFIER = 4;
    const OPERATOR = 8;
    const OPENING_PAREN = 16;
    const CLOSING_PAREN = 32;
    const INTERPUNCTION = 64;

    public $value;
    public $type;

    /**
     * Constructor
     *
     * @param integer $type the type of the token
     * @param mixed $value the value (e.g. name of identifier, string content, number value, operator)
     */
    public function __construct($type, $value) {
        $this->value = $value;
        $this->type = $type;
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
        $currenttoken = $this->next_token();
        $tokenlist = array();
        while ($currenttoken !== self::EOF) {
            $tokenlist[] = $currenttoken;
            $currenttoken = $this->next_token();
        }
        return $tokenlist;
    }

    /**
     * Read the next token from the input stream. Making the method public for easier testing.
     *
     * @return Token the token
     */
    public function next_token() {
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
        if (preg_match('/[A-Za-z]/', $currentchar)) {
            return $this->read_identifier();
        }
        // Operators always start with specific characters and may be up to two characters long.
        if (preg_match('/[-+*\/%=&|~^<>!?:]/', $currentchar)) {
            return $this->read_operator();
        }
        // Brackets, braces and parentheses are tokens on their own, they are always returned as an individual token.
        // We will have a separate category for opening and closing brackets.
        if (preg_match('/[\[{(]/', $currentchar)) {
            return new Token(Token::OPENING_PAREN, $this->inputstream->read());
        }
        if (preg_match('/[\]})]/', $currentchar)) {
            return new Token(Token::CLOSING_PAREN, $this->inputstream->read());
        }
        // Finally, there might be some interpunction like the , or ; character.
        if (preg_match('/[,;?:]/', $currentchar)) {
            return new Token(Token::INTERPUNCTION, $this->inputstream->read());
        }
        // If we are still here, that's not good at all.
        $this->inputstream->die('unknown input: "' . $currentchar . '"');
    }

    /**
     * Read a number token and return it as a float.
     *
     * @return Token the number token
     */
    private function read_number() {
        // Start by reading the first char. If we are here, that means it was a number or a decimal point.
        $currentchar = $this->inputstream->read();

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
        return new Token(Token::NUMBER, floatval($result));
    }

    /**
     * Read a string token
     *
     * @return Token the string token
     */
    private function read_string() {
        // Start by reading the opening delimiter, either a " or a ' character.
        $opener = $this->inputstream->read();

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
                return new Token(Token::STRING, $result);
            }
            $currentchar = $this->inputstream->read();
            $result .= $currentchar;
        }
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

        while ($currentchar !== InputStream::EOF) {
            $nextchar = $this->inputstream->peek();
            // Identifiers may contain letters, digits or underscores.
            if (!preg_match('/[A-Za-z0-9_]/', $nextchar)) {
                break;
            }
            $currentchar = $this->inputstream->read();
            $result .= $currentchar;
        }
        return new Token(Token::IDENTIFIER, $result);
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

        // Some chars might be the start of a two-character operator.
        $followedby = $this->inputstream->peek();
        if (preg_match('/[*=&|<>!]/', $followedby)) {
            // In most cases, two-character operators have the same character twice.
            // The only exceptions are !=, <= and >= where the second char is always the equal sign.
            if (($currentchar === $followedby)
                || ($followedby === '=' && preg_match('/[!<>]/', $currentchar))) {
                $result .= $this->inputstream->read();
            }
        }
        return new Token(Token::OPERATOR, $result);
    }

    /**
     * Read until the end of the line, because comments end with a newline character.
     *
     * @return void
     */
    private function consume_comment() {
        $currentchar = $this->inputstream->peek();
        while ($currentchar !== "\n") {
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

/**
 * Helper class to go through the input.
 */
class InputStream {
    const EOF = '';

    /** @var integer current position in the input */
    private $position = -1;

    /** @var integer the row (line number) of the current character */
    private $row = 1;

    /** @var integer the column number of the current character */
    private $column = 0;

    /** @var integer the length of the input */
    private $length = 0;

    /** @var string the raw input */
    private $input = '';

    /**
     * Constructor
     *
     * @param string $str the raw input
     */
    public function __construct($str) {
        $this->length = strlen($str);
        $this->input = $str;
    }

    /**
     * Return the next character of the stream, without consuming it. The optional
     * parameter allows to retrieve characters farther behind, if they exist.
     *
     * @param integer $skip skip a certain number of characters
     * @return string
     */
    public function peek($skip = 0) {
        if ($this->position < $this->length - $skip - 1) {
            return $this->input[$this->position + $skip + 1];
        }
        return self::EOF;
    }

    /**
     * Return the next character of the stream and move the position index one step forward.
     *
     * @return string
     */
    public function read() {
        $nextchar = $this->peek();
        if ($nextchar !== self::EOF) {
            $this->advance($nextchar === "\n");
        }
        return $nextchar;
    }

    /**
     * Advance the position index by one and keep row/column numbers in sync.
     *
     * @param boolean $newline
     * @return void
     */
    private function advance($newline) {
        $this->position++;
        $this->column++;
        if ($newline) {
            $this->row++;
            $this->column = 0;
        }
    }

    /**
     * Stop processing the input and indicate the position (row/column) where the error occurred.
     *
     * @param string $message error message
     * @return void
     * @throws Exception
     */
    public function die($message) {
        throw new \Exception($this->row . ':' . $this->column . ':' . $message);
    }
}


