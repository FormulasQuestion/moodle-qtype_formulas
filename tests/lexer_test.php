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
use qtype_formulas\local\lexer;
use qtype_formulas\local\token;

/**
 * Tests for the lexer class.
 *
 * @package    qtype_formulas
 * @copyright  2022 Philipp Imhof
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @covers \qtype_formulas\local\lexer
 */
final class lexer_test extends \advanced_testcase {

    public function test_get_token_list_1(): void {
        $input = <<<EOF
this = that * other_thing;
s1 = 'single quoted string with a double quote " inside';
# just a comment
s2 = "double quoted string with a single quote ' inside";
s3 = "string\\nwith a newline";
_s4 = 'string with a real
newline';
x = 2*x+z;
f = 4g-e;
test = (a == b ? c : d);
EOF;
        $output = [
            new token(token::IDENTIFIER, 'this', 1, 1),
            new token(token::OPERATOR, '=', 1, 6),
            new token(token::IDENTIFIER, 'that', 1, 8),
            new token(token::OPERATOR, '*', 1, 13),
            new token(token::IDENTIFIER, 'other_thing', 1, 15),
            new token(token::END_OF_STATEMENT, ';', 1, 26),
            new token(token::IDENTIFIER, 's1', 2, 1),
            new token(token::OPERATOR, '=', 2, 4),
            new token(token::STRING, 'single quoted string with a double quote " inside', 2, 6),
            new token(token::END_OF_STATEMENT, ';', 2, 57),
            new token(token::IDENTIFIER, 's2', 4, 1),
            new token(token::OPERATOR, '=', 4, 4),
            new token(token::STRING, 'double quoted string with a single quote \' inside', 4, 6),
            new token(token::END_OF_STATEMENT, ';', 4, 57),
            new token(token::IDENTIFIER, 's3', 5, 1),
            new token(token::OPERATOR, '=', 5, 4),
            new token(token::STRING, "string\nwith a newline", 5, 6),
            new token(token::END_OF_STATEMENT, ';', 5, 30),
            new token(token::IDENTIFIER, '_s4', 6, 1),
            new token(token::OPERATOR, '=', 6, 5),
            new token(token::STRING, "string with a real\nnewline", 6, 7),
            new token(token::END_OF_STATEMENT, ';', 7, 9),
            new token(token::IDENTIFIER, 'x', 8, 1),
            new token(token::OPERATOR, '=', 8, 3),
            new token(token::NUMBER, 2, 8, 5),
            new token(token::OPERATOR, '*', 8, 6),
            new token(token::IDENTIFIER, 'x', 8, 7),
            new token(token::OPERATOR, '+', 8, 8),
            new token(token::IDENTIFIER, 'z', 8, 9),
            new token(token::END_OF_STATEMENT, ';', 8, 10),
            new token(token::IDENTIFIER, 'f', 9, 1),
            new token(token::OPERATOR, '=', 9, 3),
            new token(token::NUMBER, 4, 9, 5),
            new token(token::IDENTIFIER, 'g', 9, 6),
            new token(token::OPERATOR, '-', 9, 7),
            new token(token::IDENTIFIER, 'e', 9, 8),
            new token(token::END_OF_STATEMENT, ';', 9, 9),
            new token(token::IDENTIFIER, 'test', 10, 1),
            new token(token::OPERATOR, '=', 10, 6),
            new token(token::OPENING_PAREN, '(', 10, 8),
            new token(token::IDENTIFIER, 'a', 10, 9),
            new token(token::OPERATOR, '==', 10, 11),
            new token(token::IDENTIFIER, 'b', 10, 14),
            new token(token::OPERATOR, '?', 10, 16),
            new token(token::IDENTIFIER, 'c', 10, 18),
            new token(token::OPERATOR, ':', 10, 20),
            new token(token::IDENTIFIER, 'd', 10, 22),
            new token(token::CLOSING_PAREN, ')', 10, 23),
            new token(token::END_OF_STATEMENT, ';', 10, 24),
        ];

        $lexer = new lexer($input);
        self::assertEquals($output, $lexer->get_tokens());
    }

    public function test_get_token_list_unicode(): void {
        $input = <<<EOF
s = 'string with äöüéç…';
t = join("", 'äöü', 'éçñ…');
EOF;
        $output = [
            new token(token::IDENTIFIER, 's', 1, 1),
            new token(token::OPERATOR, '=', 1, 3),
            new token(token::STRING, 'string with äöüéç…', 1, 5),
            new token(token::END_OF_STATEMENT, ';', 1, 25),
            new token(token::IDENTIFIER, 't', 2, 1),
            new token(token::OPERATOR, '=', 2, 3),
            new token(token::IDENTIFIER, 'join', 2, 5),
            new token(token::OPENING_PAREN, '(', 2, 9),
            new token(token::STRING, '', 2, 10),
            new token(token::ARG_SEPARATOR, ',', 2, 12),
            new token(token::STRING, 'äöü', 2, 14),
            new token(token::ARG_SEPARATOR, ',', 2, 19),
            new token(token::STRING, 'éçñ…', 2, 21),
            new token(token::CLOSING_PAREN, ')', 2, 27),
            new token(token::END_OF_STATEMENT, ';', 2, 28),
        ];

        $lexer = new lexer($input);
        self::assertEquals($output, $lexer->get_tokens());
    }

    public function test_get_token_list_2(): void {
        $input = <<<EOF
        s1 = 'single quoted string with an escaped quote \' inside';
        # just a comment
        s_2 = "double quoted string with an escaped quote \" inside";
        a = b + c * d / e - f % g;
EOF;
        // We are not testing the positions anymore.
        $output = [
            new token(token::IDENTIFIER, 's1'),
            new token(token::OPERATOR, '='),
            new token(token::STRING, "single quoted string with an escaped quote ' inside"),
            new token(token::END_OF_STATEMENT, ';'),
            new token(token::IDENTIFIER, 's_2'),
            new token(token::OPERATOR, '='),
            new token(token::STRING, 'double quoted string with an escaped quote " inside'),
            new token(token::END_OF_STATEMENT, ';'),
            new token(token::IDENTIFIER, 'a'),
            new token(token::OPERATOR, '='),
            new token(token::IDENTIFIER, 'b'),
            new token(token::OPERATOR, '+'),
            new token(token::IDENTIFIER, 'c'),
            new token(token::OPERATOR, '*'),
            new token(token::IDENTIFIER, 'd'),
            new token(token::OPERATOR, '/'),
            new token(token::IDENTIFIER, 'e'),
            new token(token::OPERATOR, '-'),
            new token(token::IDENTIFIER, 'f'),
            new token(token::OPERATOR, '%'),
            new token(token::IDENTIFIER, 'g'),
            new token(token::END_OF_STATEMENT, ';'),
        ];

        $tokens = (new lexer($input))->get_tokens();
        foreach ($tokens as $i => $token) {
            self::assertEquals($output[$i]->type, $token->type);
            self::assertEquals($output[$i]->value, $token->value);
        }
    }

    public function test_get_token_list_3(): void {
        $input = <<<'EOF'
        a = \sin(2);
        b = 3sqrt(5);
        c = 4x(a+b);
        d = (a+b)(c+d);
EOF;

        // We are not testing the positions anymore.
        $output = [
            new token(token::IDENTIFIER, 'a'),
            new token(token::OPERATOR, '='),
            new token(token::PREFIX, '\\'),
            new token(token::IDENTIFIER, 'sin'),
            new token(token::OPENING_PAREN, '('),
            new token(token::NUMBER, 2),
            new token(token::CLOSING_PAREN, ')'),
            new token(token::END_OF_STATEMENT, ';'),
            new token(token::IDENTIFIER, 'b'),
            new token(token::OPERATOR, '='),
            new token(token::NUMBER, 3),
            new token(token::IDENTIFIER, 'sqrt'),
            new token(token::OPENING_PAREN, '('),
            new token(token::NUMBER, 5),
            new token(token::CLOSING_PAREN, ')'),
            new token(token::END_OF_STATEMENT, ';'),
            new token(token::IDENTIFIER, 'c'),
            new token(token::OPERATOR, '='),
            new token(token::NUMBER, 4),
            new token(token::IDENTIFIER, 'x'),
            new token(token::OPENING_PAREN, '('),
            new token(token::IDENTIFIER, 'a'),
            new token(token::OPERATOR, '+'),
            new token(token::IDENTIFIER, 'b'),
            new token(token::CLOSING_PAREN, ')'),
            new token(token::END_OF_STATEMENT, ';'),
            new token(token::IDENTIFIER, 'd'),
            new token(token::OPERATOR, '='),
            new token(token::OPENING_PAREN, '('),
            new token(token::IDENTIFIER, 'a'),
            new token(token::OPERATOR, '+'),
            new token(token::IDENTIFIER, 'b'),
            new token(token::CLOSING_PAREN, ')'),
            new token(token::OPENING_PAREN, '('),
            new token(token::IDENTIFIER, 'c'),
            new token(token::OPERATOR, '+'),
            new token(token::IDENTIFIER, 'd'),
            new token(token::CLOSING_PAREN, ')'),
            new token(token::END_OF_STATEMENT, ';'),
        ];

        $tokens = (new lexer($input))->get_tokens();
        foreach ($tokens as $i => $token) {
            self::assertEquals($output[$i]->type, $token->type);
            self::assertEquals($output[$i]->value, $token->value);
        }
    }

    public function test_get_token_list_4(): void {
        $input = <<<EOF
        a = sin(2E3);
        b = 3e-4sqrt(5e+2);
        c = 4ex(a+b);
EOF;

        // We are not testing the positions anymore.
        $output = [
            new token(token::IDENTIFIER, 'a'),
            new token(token::OPERATOR, '='),
            new token(token::IDENTIFIER, 'sin'),
            new token(token::OPENING_PAREN, '('),
            new token(token::NUMBER, 2000),
            new token(token::CLOSING_PAREN, ')'),
            new token(token::END_OF_STATEMENT, ';'),
            new token(token::IDENTIFIER, 'b'),
            new token(token::OPERATOR, '='),
            new token(token::NUMBER, .0003),
            new token(token::IDENTIFIER, 'sqrt'),
            new token(token::OPENING_PAREN, '('),
            new token(token::NUMBER, 500),
            new token(token::CLOSING_PAREN, ')'),
            new token(token::END_OF_STATEMENT, ';'),
            new token(token::IDENTIFIER, 'c'),
            new token(token::OPERATOR, '='),
            new token(token::NUMBER, 4),
            new token(token::IDENTIFIER, 'ex'),
            new token(token::OPENING_PAREN, '('),
            new token(token::IDENTIFIER, 'a'),
            new token(token::OPERATOR, '+'),
            new token(token::IDENTIFIER, 'b'),
            new token(token::CLOSING_PAREN, ')'),
            new token(token::END_OF_STATEMENT, ';'),
        ];

        $tokens = (new lexer($input))->get_tokens();
        foreach ($tokens as $i => $token) {
            self::assertEquals($output[$i]->type, $token->type);
            self::assertEquals($output[$i]->value, $token->value);
        }
    }

    public function test_get_token_list_5(): void {
        $input = <<<EOF
        a = {1:10:2};
        b = [a, b, 'c', "d"];
        foo = [bar, [hello, world], [1, 2, 3], ["s", "t"]];
EOF;

        // We are not testing the positions anymore.
        $output = [
            new token(token::IDENTIFIER, 'a'),
            new token(token::OPERATOR, '='),
            new token(token::OPENING_BRACE, '{'),
            new token(token::NUMBER, 1),
            new token(token::RANGE_SEPARATOR, ':'),
            new token(token::NUMBER, 10),
            new token(token::RANGE_SEPARATOR, ':'),
            new token(token::NUMBER, 2),
            new token(token::CLOSING_BRACE, '}'),
            new token(token::END_OF_STATEMENT, ';'),
            new token(token::IDENTIFIER, 'b'),
            new token(token::OPERATOR, '='),
            new token(token::OPENING_BRACKET, '['),
            new token(token::IDENTIFIER, 'a'),
            new token(token::ARG_SEPARATOR, ','),
            new token(token::IDENTIFIER, 'b'),
            new token(token::ARG_SEPARATOR, ','),
            new token(token::STRING, 'c'),
            new token(token::ARG_SEPARATOR, ','),
            new token(token::STRING, 'd'),
            new token(token::CLOSING_BRACKET, ']'),
            new token(token::END_OF_STATEMENT, ';'),
            new token(token::IDENTIFIER, 'foo'),
            new token(token::OPERATOR, '='),
            new token(token::OPENING_BRACKET, '['),
            new token(token::IDENTIFIER, 'bar'),
            new token(token::ARG_SEPARATOR, ','),
            new token(token::OPENING_BRACKET, '['),
            new token(token::IDENTIFIER, 'hello'),
            new token(token::ARG_SEPARATOR, ','),
            new token(token::IDENTIFIER, 'world'),
            new token(token::CLOSING_BRACKET, ']'),
            new token(token::ARG_SEPARATOR, ','),
            new token(token::OPENING_BRACKET, '['),
            new token(token::NUMBER, 1),
            new token(token::ARG_SEPARATOR, ','),
            new token(token::NUMBER, 2),
            new token(token::ARG_SEPARATOR, ','),
            new token(token::NUMBER, 3),
            new token(token::CLOSING_BRACKET, ']'),
            new token(token::ARG_SEPARATOR, ','),
            new token(token::OPENING_BRACKET, '['),
            new token(token::STRING, 's'),
            new token(token::ARG_SEPARATOR, ','),
            new token(token::STRING, 't'),
            new token(token::CLOSING_BRACKET, ']'),
            new token(token::CLOSING_BRACKET, ']'),
            new token(token::END_OF_STATEMENT, ';'),
        ];

        $tokens = (new lexer($input))->get_tokens();
        foreach ($tokens as $i => $token) {
            self::assertEquals($output[$i]->type, $token->type);
            self::assertEquals($output[$i]->value, $token->value);
        }
    }

    public function test_get_token_list_6(): void {
        $input = <<<EOF
        a = (     b == 1    ?   7 : 3);
        b = c[var > 1];
        c = thing     *    (x != 4);
EOF;

        // We are not testing the positions anymore.
        $output = [
            new token(token::IDENTIFIER, 'a'),
            new token(token::OPERATOR, '='),
            new token(token::OPENING_PAREN, '('),
            new token(token::IDENTIFIER, 'b'),
            new token(token::OPERATOR, '=='),
            new token(token::NUMBER, 1),
            new token(token::OPERATOR, '?'),
            new token(token::NUMBER, 7),
            new token(token::OPERATOR, ':'),
            new token(token::NUMBER, 3),
            new token(token::CLOSING_PAREN, ')'),
            new token(token::END_OF_STATEMENT, ';'),
            new token(token::IDENTIFIER, 'b'),
            new token(token::OPERATOR, '='),
            new token(token::IDENTIFIER, 'c'),
            new token(token::OPENING_BRACKET, '['),
            new token(token::IDENTIFIER, 'var'),
            new token(token::OPERATOR, '>'),
            new token(token::NUMBER, '1'),
            new token(token::CLOSING_BRACKET, ']'),
            new token(token::END_OF_STATEMENT, ';'),
            new token(token::IDENTIFIER, 'c'),
            new token(token::OPERATOR, '='),
            new token(token::IDENTIFIER, 'thing'),
            new token(token::OPERATOR, '*'),
            new token(token::OPENING_PAREN, '('),
            new token(token::IDENTIFIER, 'x'),
            new token(token::OPERATOR, '!='),
            new token(token::NUMBER, 4),
            new token(token::CLOSING_PAREN, ')'),
            new token(token::END_OF_STATEMENT, ';'),
        ];

        $tokens = (new lexer($input))->get_tokens();
        foreach ($tokens as $i => $token) {
            self::assertEquals($output[$i]->type, $token->type);
            self::assertEquals($output[$i]->value, $token->value);
        }
    }

    public function test_get_token_list_7(): void {
        $input = <<<EOF
        a = { x == y ? x + 1 : x, 5:10:0.5, 10, 12:15 }
EOF;

        // We are not testing the positions anymore.
        $output = [
            new token(token::IDENTIFIER, 'a'),
            new token(token::OPERATOR, '='),
            new token(token::OPENING_BRACE, '{'),
            new token(token::IDENTIFIER, 'x'),
            new token(token::OPERATOR, '=='),
            new token(token::IDENTIFIER, 'y'),
            new token(token::OPERATOR, '?'),
            new token(token::IDENTIFIER, 'x'),
            new token(token::OPERATOR, '+'),
            new token(token::NUMBER, 1),
            new token(token::OPERATOR, ':'),
            new token(token::IDENTIFIER, 'x'),
            new token(token::ARG_SEPARATOR, ','),
            new token(token::NUMBER, 5),
            new token(token::RANGE_SEPARATOR, ':'),
            new token(token::NUMBER, 10),
            new token(token::RANGE_SEPARATOR, ':'),
            new token(token::NUMBER, 0.5),
            new token(token::ARG_SEPARATOR, ','),
            new token(token::NUMBER, 10),
            new token(token::ARG_SEPARATOR, ','),
            new token(token::NUMBER, 12),
            new token(token::RANGE_SEPARATOR, ':'),
            new token(token::NUMBER, 15),
            new token(token::CLOSING_BRACE, '}'),
        ];

        $tokens = (new lexer($input))->get_tokens();
        foreach ($tokens as $i => $token) {
            self::assertEquals($output[$i]->type, $token->type);
            self::assertEquals($output[$i]->value, $token->value);
        }
    }

    public function test_pi(): void {
        $input = 'π + π(1 + 2) + pi + pi() + pi(2) + pi(1 + 2)';

        $output = [
            new token(token::CONSTANT, 'π'),
            new token(token::OPERATOR, '+'),
            new token(token::CONSTANT, 'π'),
            new token(token::OPENING_PAREN, '('),
            new token(token::NUMBER, '1'),
            new token(token::OPERATOR, '+'),
            new token(token::NUMBER, '2'),
            new token(token::CLOSING_PAREN, ')'),
            new token(token::OPERATOR, '+'),
            new token(token::CONSTANT, 'π'),
            new token(token::OPERATOR, '+'),
            new token(token::CONSTANT, 'π'),
            new token(token::OPERATOR, '+'),
            new token(token::CONSTANT, 'π'),
            new token(token::OPENING_PAREN, '('),
            new token(token::NUMBER, '2'),
            new token(token::CLOSING_PAREN, ')'),
            new token(token::OPERATOR, '+'),
            new token(token::CONSTANT, 'π'),
            new token(token::OPENING_PAREN, '('),
            new token(token::NUMBER, '1'),
            new token(token::OPERATOR, '+'),
            new token(token::NUMBER, '2'),
            new token(token::CLOSING_PAREN, ')'),
        ];

        $tokens = (new lexer($input))->get_tokens();
        foreach ($tokens as $i => $token) {
            self::assertEquals($output[$i]->type, $token->type);
            self::assertEquals($output[$i]->value, $token->value);
        }
    }

    /**
     * Data provider.
     *
     * @return array
     */
    public static function provide_identifiers(): array {
        return [
            ['abc_', 'abc_'],
            ['a1', 'a1'],
            ['ab', 'ab-'],
            ['ABCabc', 'ABCabc'],
            ['ABC__abc', 'ABC__abc'],
            ['abc1', 'abc1*'],
            ['abc', 'abc(x)'],
        ];
    }

    /**
     * Data provider.
     *
     * @return array
     */
    public static function provide_parens(): array {
        return [
            ['(', '('],
            ['[', '['],
            ['{', '{'],
            ['(', '(a'],
            ['[', '[    asd'],
            [')', ')'],
            [']', ']'],
            ['}', '}'],
            ['}', '}a'],
            [']', ']    asd'],
        ];
    }

    /**
     * Data provider.
     *
     * @return array
     */
    public static function provide_operators(): array {
        return [
            ['+', '+'],
            ['+', '+*'],
            ['+', '+ '],
            ['-', '-'],
            ['-', '-3'],
            ['*', '*'],
            ['*', '*-'],
            ['/', '/'],
            ['/', '/('],
            ['%', '%'],
            ['%', '%a'],
            ['**', '**'],
            ['**', '**5'],
            ['**', '***'],
            ['=', '='],
            ['=', '= '],
            ['&', '&'],
            ['&', '&2'],
            ['|', '|'],
            ['|', '|1'],
            ['~', '~'],
            ['~', '~a'],
            ['^', '^'],
            ['<<', '<<'],
            ['<<', '<<5'],
            ['>>', '>>'],
            ['==', '=='],
            ['==', '== a'],
            ['==', '==='],
            ['!=', '!='],
            ['!', '!+'],
            ['!=', '!=='],
            ['>', '>'],
            ['<', '<'],
            ['=', '=<'],
            ['<=', '<='],
            ['>=', '>='],
            ['=', '=>'],
            ['&&', '&&'],
            ['&&', '&&&'],
            ['||', '||'],
            ['|', '|*|'],
            ['||', '|||'],
            ['!', '!'],
            ['?', '?'],
            ['?', '?:'],
            ['?', '?2'],
            [':', ':'],
            [':', ':3'],
        ];
    }

    /**
     * Data provider.
     *
     * @return array
     */
    public static function provide_valid_strings(): array {
        return [
            ['foo', "'foo'"],
            ['foo', '"foo"'],
            // Test a single quote string with a linebreak.
            ["foo\nbar", "'foo\nbar'"],
            // Test usage of a double quote in a single quote string.
            ["foo\"bar", "'foo\"bar'"],
            // Test usage of an escaped double quote in a double quote string.
            ['foo"bar', '"foo\"bar"'],
            // Test useage of a single quote in a double quote string.
            ["foo'bar", '"foo\'bar"'],
            // Test usage of the escape sequence \n in a single quote string.
            ["foo\nbar", "'foo\\nbar'"],
            // Test usage of the escape sequence \n in a double quote string.
            ["foo\nbar", '"foo\nbar"'],
            // Test usage of the escape sequence \t in a single quote string.
            ["foo\tbar", "'foo\\tbar'"],
            // Test usage of the escape sequence \t in a double quote string.
            ["foo\tbar", '"foo\tbar"'],
            // Test usage of an unescaped backslash in a double quote string.
            ['foo\bar', '"foo\bar"'],
            // Test usage of an unescaped backslash in single quote string.
            ['foo\bar', "'foo\\bar'"],
            // Test usage of an escaped backslash in a double quote string.
            ['foo\\\\bar', '"foo\\\\bar"'],
            // Test usage of an escaped backslash in single quote string.
            ['foo\\\\bar', "'foo\\\\bar'"],
        ];
    }

    /**
     * Data provider.
     *
     * @return array
     */
    public static function provide_numbers(): array {
        return [
            [123, "\n123"],
            [123, "\n\n123"],
            [321, '321# testcomment'],
            [1, '1a2b3c4d'],
            [1234, '1234'],
            [1234, '1234    '],
            [1234, ' 1234'],
            [1234, ' 1234  '],
            [123, ' 123 4  '],
            [1234, '1234aaaa'],
            [1234, "    \n\n\t\r   1234"],
            [0.12, '.12'],
            [0.12, '0.12'],
            [0.123, '     .123'],
            [0.111, '0.111'],
            [12.34, '12.34'],
            [1234, '12.34e2'],
            [1000, '1E3e2'],
            [100000, '1E5'],
            [10000, '1e4'],
            [1, '1e'],
            [1, '1ex'],
            [12, '12e '],
            [0.01, '1e-2'],
            [100, '1e+2'],
            [0.01, '1e-2+'],
            [100, '1e+2-'],
            [12, '12e+'],
            [15, '15e-'],
            [17, '17e-+'],
            [23, '23e+-'],
        ];
    }

    /**
     * Test lexing of various inputs.
     *
     * @dataProvider provide_identifiers
     * @dataProvider provide_parens
     * @dataProvider provide_operators
     * @dataProvider provide_valid_strings
     * @dataProvider provide_numbers
     */
    public function test_various_inputs($expected, $input): void {
        $lexer = new lexer($input);
        $tokens = $lexer->get_tokens();
        self::assertEquals($expected, $tokens[0]->value);
    }

    /**
     * Data provider.
     *
     * @return array
     */
    public static function provide_invalid_strings(): array {
        return [
            ['1:4:Unterminated string, started at row 1, column 1.', '"foo'],
            ['1:4:Unterminated string, started at row 1, column 1.', "'foo"],
        ];
    }

    /**
     * Test interpretation of badly formatted strings.
     *
     * @dataProvider provide_invalid_strings
     */
    public function test_read_invalid_string($expected, $input): void {
        $e = null;
        try {
            new lexer($input);
        } catch (Exception $e) {
            self::assertEquals($expected, $e->getMessage());
        }
        self::assertNotNull($e);
    }

    /**
     * Test whether the read() function of the tokenizer class correctly parses special
     * cases involving numbers.
     */
    public function test_read_numbers_edge_cases(): void {
        // Invalid, because there is already a decimal point.
        $input = '5.127.3';
        $error = '';
        try {
            new lexer($input);
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
        self::assertEquals("1:6:Unexpected input: '.'", $error);

        // Invalid, because the exponent in scientific notation must be an integer.
        $input = '5.127e3.3';
        $error = '';
        try {
            new lexer($input);
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
        self::assertEquals("1:8:Unexpected input: '.'", $error);

        // Tokens: 5 (number), a2 (identifier), .b (invalid, unexpected .) -> fail.
        $input = '5.a2.b3.c4.d';
        $error = '';
        try {
            new lexer($input);
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
        self::assertEquals("1:5:Unexpected input: '.'", $error);

        // Tokens: 5 (number), a2 (identifier), 2000 (number) with metadata.
        $input = '5.a2 2.e3';
        $lexer = new lexer($input);
        $expectedtokens = [
            new token(token::NUMBER, 5.0, 1, 1),
            new token(token::IDENTIFIER, 'a2', 1, 3),
            new token(token::NUMBER, 2000.0, 1, 6, ['mantissa' => 2.0, 'exponent' => 3]),
        ];
        self::assertEquals($expectedtokens, $lexer->get_tokens());
    }

    public function test_lexing_for_loop(): void {
        $input = <<<EOF
        for (a:[1:b]) { x = x + a; }
EOF;

        // We are not testing the positions anymore.
        $output = [
            new token(token::RESERVED_WORD, 'for'),
            new token(token::OPENING_PAREN, '('),
            new token(token::IDENTIFIER, 'a'),
            new token(token::RANGE_SEPARATOR, ':'),
            new token(token::OPENING_BRACKET, '['),
            new token(token::NUMBER, '1'),
            new token(token::RANGE_SEPARATOR, ':'),
            new token(token::IDENTIFIER, 'b'),
            new token(token::CLOSING_BRACKET, ']'),
            new token(token::CLOSING_PAREN, ')'),
            new token(token::OPENING_BRACE, '{'),
            new token(token::IDENTIFIER, 'x'),
            new token(token::OPERATOR, '='),
            new token(token::IDENTIFIER, 'x'),
            new token(token::OPERATOR, '+'),
            new token(token::IDENTIFIER, 'a'),
            new token(token::END_OF_STATEMENT, ';'),
            new token(token::CLOSING_BRACE, '}'),
        ];

        $tokens = (new lexer($input))->get_tokens();
        foreach ($tokens as $i => $token) {
            self::assertEquals($output[$i]->type, $token->type);
            self::assertEquals($output[$i]->value, $token->value);
        }
    }
}
