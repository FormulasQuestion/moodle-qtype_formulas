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

/**
 * Formulas Question Lexer class
 *
 * @package    qtype_formulas
 * @copyright  2022 Philipp Imhof
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class lexer {
    /** @var null */
    const EOF = null;

    /** @var ?input_stream input stream */
    private ?input_stream $inputstream = null;

    /** @var token[] list of all tokens in the input stream */
    private array $tokens = [];

    /** @var int level of nested ternary operators */
    private int $pendingternary = 0;

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
        if ($currentchar === ':' && $this->pendingternary < 1) {
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
                $this->pendingternary++;
            }
            // After a : operator, the ternary operator is no longer pending. In case of *nested*
            // ternary operators, we descend one level.
            if ($currentchar === ':') {
                $this->pendingternary--;
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
                'π' => token::CONSTANT,
            ];
            return $this->read_single_char_token($types[$currentchar]);
        }
        // If we are still here, that's not good at all. We need to read the char (it is only peeked
        // so far) in order for the input stream to be at the right position.
        $this->inputstream->read();
        $this->inputstream->die(get_string('error_unexpectedinput', 'qtype_formulas', $currentchar));
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
                    $this->inputstream->read();
                    $this->inputstream->die(get_string('error_unexpectedinput', 'qtype_formulas', $nextchar));
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
        // If the number is written in scientific notation (e. g. 1.25e3 or 5e-4), we store that information
        // separately in the token's metadata.
        $metadata = null;
        if ($hasexponent) {
            $split = explode('e', strtolower($result), 2);
            $metadata = [
                'mantissa' => floatval($split[0]),
                'exponent' => intval($split[1]),
            ];
        };
        return new token(token::NUMBER, floatval($result), $startingposition['row'], $startingposition['column'], $metadata);
    }

    /**
     * Read various escape sequences in strings.
     *
     * @param bool $doublequote whether the string is delimited by double quotes
     * @return string
     */
    private function read_escape_sequence(bool $doublequote = true): string {
        // Consume the backslash and look at the character immediately following.
        $this->inputstream->read();
        $afterbackslash = $this->inputstream->peek();

        // If the backslash is followed by another backslash, also consume the second and
        // return a backslash.
        if ($afterbackslash === '\\') {
            $this->inputstream->read();
            return '\\';
        }

        // If the string is delimited by single quotes, we simply return the backslash, because
        // all other escape sequences are treated literally. Note that this function
        // is not called if the backslash was used to escape the string's opening delimiter.
        if (!$doublequote) {
            return '\\';
        }

        // In strings delimited by double quotes, some escape sequences have a special meaning.
        // We return them here. The character following the backslash has to be consumed.
        switch ($afterbackslash) {
            case 'n':
                $this->inputstream->read();
                return "\n";
            case 'r':
                $this->inputstream->read();
                return "\r";
            case 't':
                $this->inputstream->read();
                return "\t";
            case 'v':
                $this->inputstream->read();
                return "\v";
            case 'e':
                $this->inputstream->read();
                return "\e";
            case 'f':
                $this->inputstream->read();
                return "\f";
            case '$':
                $this->inputstream->read();
                return "\$";
        }

        // The backslash can be followed by an octal number, i. e. one, two or three digits from 0
        // up to and including 7. In this case, we return the character. If it's more than 3 digits,
        // the remaining digits are not considered, but appended after the escape sequence.
        if (preg_match('/[0-7]/', $afterbackslash)) {
            $octal = 0;
            $digits = 0;
            $possiblenextdigit = $this->inputstream->peek();
            while (preg_match('/[0-7]/', $possiblenextdigit) && $digits < 3) {
                $digits++;
                $octal = 8 * $octal + intval($this->inputstream->read());
                $possiblenextdigit = $this->inputstream->peek();
            }
            return chr($octal);
        }

        // The backslash can be followed by x in order to have a hexadecimal escale sequence.
        // In this case, there must be one or two hexadecimal digits after the x; if it's more,
        // that is not an error, but the digits will simply not be part of the escape sequence.
        if ($afterbackslash === 'x') {
            $hex = null;
            $digits = 0;
            $afterx = $this->inputstream->peek(1);
            while (preg_match('/[0-9A-F]/i', $afterx) && $digits < 2) {
                $digits++;
                $hex = 16 * $hex + hexdec($afterx);
                $this->inputstream->read();
                $afterx = $this->inputstream->peek(1);
            }
            // If there was no hexadecimal digit after the x, we must simply return \x verbatim.
            // Note that the x character must be consumed.
            if ($hex === null) {
                $this->inputstream->read();
                return '\x';
            }
            // Consume the last digit.
            $this->inputstream->read();
            return chr($hex);
        }

        // Finally, the backslash can be use to reference a unicode codepoint. The codepoint must be
        // wrapped in curly braces and must be given as a hexadecimal number, not larger than 0x10FFFF.
        // A missing or an invalid codepoint shall trigger an error message, mimicking PHP's behaviour.
        if ($afterbackslash === 'u') {
            $afteru = $this->inputstream->peek(1);
            // If the u is not followed by an opening brace, we just return the backslash. The u
            // and all the rest will be read separately.
            if ($afteru != '{') {
                return '\\';
            }
            // So there was an opening brace, let's consume the u character.
            $this->inputstream->read();

            // Read all digits and calculate the codepoint's value.
            $possibledigit = $this->inputstream->peek(1);
            $codepoint = null;
            while (preg_match('/[0-9A-Fa-f]/', $possibledigit)) {
                $codepoint = 16 * $codepoint + hexdec($possibledigit);
                $this->inputstream->read();
                $possibledigit = $this->inputstream->peek(1);
            }
            // If the character following the last digit is not a closing curly brace, that is a
            // syntax error.
            if ($possibledigit != '}' || $codepoint === null) {
                $this->inputstream->die(get_string('error_invalidcodepoint', 'qtype_formulas'));
            }
            // Make sure the codepoint is not too large.
            if ($codepoint > 0x10FFFF) {
                $this->inputstream->die(get_string('error_invalidcodepoint_toolarge', 'qtype_formulas'));
            }
            // Consume the last digit and the curly brace and return the (probably multi-byte) character.
            $this->inputstream->read();
            $this->inputstream->read();
            return mb_chr($codepoint);
        }

        // No escape sequence found? Then just return the backslash.
        return '\\';
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
            // Also, we can have \n for newline or \t for tabulator. Furthermore, it is possible
            // to write \\ for the backslash. However, escaping is not mandatory, so it is
            // perfectly valid to have 2 \ 3 which would mean two-backslash-three.
            if ($nextchar == '\\') {
                $followedby = $this->inputstream->peek(1);
                if ($followedby === $opener) {
                    // Consume the backslash. The quote will be appended later.
                    $this->inputstream->read();
                } else {
                    $result .= $this->read_escape_sequence($opener === '"');
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
        $a = (object)$startingposition;
        $this->inputstream->die(get_string('error_unterminatedstring', 'qtype_formulas', $a));
    }

    /**
     * Read an identifier token (function name, variable name, reserved word or pre-defined constant
     * like π) from the input stream.
     *
     * @return token the identifier token
     */
    private function read_identifier(): token {
        // Start by reading the first char. If we are here, that means it was a letter or an underscore.
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
        } else if ($result === 'pi') {
            $type = token::CONSTANT;
            $result = 'π';
            // If we have the legacy syntax pi(), we drop the two parens.
            $next = $this->inputstream->peek();
            $nextbutone = $this->inputstream->peek(1);
            if ($next === '(' && $nextbutone === ')') {
                $this->inputstream->read();
                $this->inputstream->read();
            }
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
        // Start by reading the first char.
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

        // If the next char is a # again, the comment seems to continue on the next line,
        // so we go for another round.
        if ($this->inputstream->peek() === '#') {
            $this->consume_comment();
        }
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
