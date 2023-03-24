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
 * Formulas Question Lexer class
 *
 * @package    qtype_formulas
 * @copyright  2022 Philipp Imhof
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

class lexer {
    const EOF = null;

    /** @var input_stream input stream */
    private $inputstream = null;

    /** @var token[] list of all tokens in the input stream */
    private $tokens = [];

    /** @var boolean whether we are in the middle of a ternary operator */
    private $pendingternary = false;

    /**
     * Constructor
     *
     * @param string $str the input to be tokenized
     */
    public function __construct(string $str) {
        $this->inputstream = new input_stream($str);
        $this->build_token_list();
    }

    /**
     * Return the list of all tokens.
     *
     * @return array
     */
    public function get_tokens(): array {
        return $this->tokens;
    }

    /**
     * Go through the entire input and fetch all tokens except comments and white space.
     * Store them in the corresponding variable, so that they can be retrieved.
     *
     * @return void
     */
    private function build_token_list(): void {
        $currenttoken = $this->read_next_token();
        $tokens = [];
        while ($currenttoken !== self::EOF) {
            $tokens[] = $currenttoken;
            $currenttoken = $this->read_next_token();
        }
        $this->tokens = $tokens;
    }

    /**
     * Find out what type of token is next and read it from the input stream by calling the
     * corresponding dedicated method.
     *
     * @return ?token the token or null, if we have reached the end of the input stream
     */
    private function read_next_token(): ?token {
        // Check the next char and quit if we are at the end of the stream.
        $currentchar = $this->inputstream->peek();
        if ($currentchar === input_stream::EOF) {
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
        if ($currentchar === input_stream::EOF) {
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
        // Unless we are in the middle of a ternary operator, we treat : as a RANGE_SEPARATOR.
        if ($currentchar === ':' && !$this->pendingternary) {
            return $this->read_single_char_token(token::RANGE_SEPARATOR);
        }
        // Operators always start with specific characters and may be up to two characters long.
        if (preg_match('/[-+*\/%=&|~^<>!?:]/', $currentchar)) {
            // After a ? operator, we expect a : to finish the ternary operator.
            // Note: In case of a syntax error, this flag might remain set even after the end
            // of a statement and we could therefore wrongfully interpret a : as an operator.
            // We don't mind, because bad syntax of a ternary operator will lead to a syntax error
            // anyway.
            if ($currentchar === '?') {
                $this->pendingternary = true;
            }
            // After a : operator, the ternary operator is no longer pending.
            if ($currentchar === ':') {
                $this->pendingternary = false;
            }
            return $this->read_operator();
        }
        // There are some single-character tokens...
        if (preg_match('/[]\[(){},;π\\\]/', $currentchar)) {
            $types = [
                '[' => token::OPENING_BRACKET,
                '(' => token::OPENING_PAREN,
                '{' => token::OPENING_BRACE,
                ']' => token::CLOSING_BRACKET,
                ')' => token::CLOSING_PAREN,
                '}' => token::CLOSING_BRACE,
                ',' => token::ARG_SEPARATOR,
                '\\' => token::PREFIX,
                ';' => token::END_OF_STATEMENT,
                'π' => token::CONSTANT
            ];
            return $this->read_single_char_token($types[$currentchar]);
        }
        // If we are still here, that's not good at all. We need to read the char (it is only peeked so far)
        // in order for the inputstream to be at the right position.
        $this->inputstream->read();
        $this->inputstream->die("unexpected input: '$currentchar'");
    }

    /**
     * Read a single-char token from the input stream, e.g. a parenthesis or a comma.
     *
     * @param int $type type to use when creating the new token
     * @return token
     */
    private function read_single_char_token(int $type): token {
        $char = $this->inputstream->read();
        $startingposition = $this->inputstream->get_position();
        return new token($type, $char, $startingposition['row'], $startingposition['column']);
    }

    /**
     * Read a number token from the input stream.
     *
     * @return token the number token
     */
    private function read_number(): token {
        // Start by reading the first char. If we are here, that means it was a number or a decimal point.
        $currentchar = $this->inputstream->read();

        // Save starting position of the number.
        $startingposition = $this->inputstream->get_position();

        // A number can only have one decimal point and one exponent (for scientific notation) at most.
        $hascomma = ($currentchar === '.');
        $hasexponent = false;

        // Save the first character.
        $result = $currentchar;
        while ($currentchar !== input_stream::EOF) {
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
        return new token(token::NUMBER, floatval($result), $startingposition['row'], $startingposition['column']);
    }

    /**
     * Read a string token from the input stream.
     *
     * @return token the string token
     */
    private function read_string(): token {
        // Start by reading the opening delimiter, either a " or a ' character.
        $opener = $this->inputstream->read();

        // Record position of the opening delimiter.
        $startingposition = $this->inputstream->get_position();

        $result = '';
        $currentchar = $this->inputstream->peek();
        while ($currentchar !== input_stream::EOF) {
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
                return new token(token::STRING, $result, $startingposition['row'], $startingposition['column']);
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
     * Read an identifier token (function name, variable name, reserved word or pre-defined constant like π)
     * from the input stream.
     *
     * @return token the identifier token
     */
    private function read_identifier(): token {
        // Start by reading the first char. If we are here, that means it was a number or a decimal point.
        $currentchar = $this->inputstream->read();
        $result = $currentchar;

        // Record position of the opening delimiter.
        $startingposition = $this->inputstream->get_position();

        while ($currentchar !== input_stream::EOF) {
            $nextchar = $this->inputstream->peek();
            // Identifiers may contain letters, digits or underscores.
            if (!preg_match('/[A-Za-z0-9_]/', $nextchar)) {
                break;
            }
            $currentchar = $this->inputstream->read();
            $result .= $currentchar;
        }
        if ($result === 'for') {
            $type = token::RESERVED_WORD;
        } else if (preg_match('/^(pi|PI|Pi)$/', $result)) {
            $type = token::CONSTANT;
            $result = 'π';
        } else {
            $type = token::IDENTIFIER;
        }
        return new token($type, $result, $startingposition['row'], $startingposition['column']);
    }

    /**
     * Read an operator token from the input stream.
     *
     * @return token the operator token
     */
    private function read_operator(): token {
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
        return new token(token::OPERATOR, $result, $startingposition['row'], $startingposition['column']);
    }

    /**
     * Read until the end of the line, because comments always extend until the end of the line.
     *
     * @return void
     */
    private function consume_comment(): void {
        $currentchar = $this->inputstream->peek();
        while ($currentchar !== "\n" && $currentchar !== input_stream::EOF) {
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
    private function consume_whitespace(): void {
        $currentchar = $this->inputstream->peek();
        while (preg_match('/\s/', $currentchar)) {
            $this->inputstream->read();
            $currentchar = $this->inputstream->peek();
        }
    }
}
