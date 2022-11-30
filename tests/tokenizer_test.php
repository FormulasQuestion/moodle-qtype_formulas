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
 * qtype_formulas tokenizer tests
 *
 * @package    qtype_formulas
 * @category   test
 * @copyright  2022 Philipp Imhof
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace qtype_formulas;
use Exception;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/question/type/formulas/classes/parser.php');

class tokenizer_test extends \advanced_testcase {

    public function test_get_token_list_1() {
        $input = <<<EOF
        this = that * other_thing;
        s1 = 'single quoted string with a double quote " inside';
        # just a comment
        s2 = "double quoted string with a single quote ' inside";
        s3 = "string\nwith a newline";
        s4 = 'string with a real
newline';
        x = 2*x+z;
        f = 4g-e;
        test = (a == b ? c : d);
EOF;
        $output = array(
            new Token(Token::IDENTIFIER, 'this'),
            new Token(Token::OPERATOR, '='),
            new Token(Token::IDENTIFIER, 'that'),
            new Token(Token::OPERATOR, '*'),
            new Token(Token::IDENTIFIER, 'other_thing'),
            new Token(Token::INTERPUNCTION, ';'),
            new Token(Token::IDENTIFIER, 's1'),
            new Token(Token::OPERATOR, '='),
            new Token(Token::STRING, 'single quoted string with a double quote " inside'),
            new Token(Token::INTERPUNCTION, ';'),
            new Token(Token::IDENTIFIER, 's2'),
            new Token(Token::OPERATOR, '='),
            new Token(Token::STRING, 'double quoted string with a single quote \' inside'),
            new Token(Token::INTERPUNCTION, ';'),
            new Token(Token::IDENTIFIER, 's3'),
            new Token(Token::OPERATOR, '='),
            new Token(Token::STRING, "string\nwith a newline"),
            new Token(Token::INTERPUNCTION, ';'),
            new Token(Token::IDENTIFIER, 's4'),
            new Token(Token::OPERATOR, '='),
            new Token(Token::STRING, "string with a real\nnewline"),
            new Token(Token::INTERPUNCTION, ';'),
            new Token(Token::IDENTIFIER, 'x'),
            new Token(Token::OPERATOR, '='),
            new Token(Token::NUMBER, 2),
            new Token(Token::OPERATOR, '*'),
            new Token(Token::IDENTIFIER, 'x'),
            new Token(Token::OPERATOR, '+'),
            new Token(Token::IDENTIFIER, 'z'),
            new Token(Token::INTERPUNCTION, ';'),
            new Token(Token::IDENTIFIER, 'f'),
            new Token(Token::OPERATOR, '='),
            new Token(Token::NUMBER, 4),
            new Token(Token::IDENTIFIER, 'g'),
            new Token(Token::OPERATOR, '-'),
            new Token(Token::IDENTIFIER, 'e'),
            new Token(Token::INTERPUNCTION, ';'),
            new Token(Token::IDENTIFIER, 'test'),
            new Token(Token::OPERATOR, '='),
            new Token(Token::OPENING_PAREN, '('),
            new Token(Token::IDENTIFIER, 'a'),
            new Token(Token::OPERATOR, '=='),
            new Token(Token::IDENTIFIER, 'b'),
            new Token(Token::OPERATOR, '?'),
            new Token(Token::IDENTIFIER, 'c'),
            new Token(Token::OPERATOR, ':'),
            new Token(Token::IDENTIFIER, 'd'),
            new Token(Token::CLOSING_PAREN, ')'),
            new Token(Token::INTERPUNCTION, ';'),
        );

        $lexer = new Lexer($input);
        $this->assertEquals($output, $lexer->get_token_list());
    }

    public function test_get_token_list_2() {
        $input = <<<EOF
        s1 = 'single quoted string with an escaped quote \' inside';
        # just a comment
        s2 = "double quoted string with an escaped quote \" inside";
        a = b + c * d / e - f % g;
EOF;
        $output = array(
            new Token(Token::IDENTIFIER, 's1'),
            new Token(Token::OPERATOR, '='),
            new Token(Token::STRING, "single quoted string with an escaped quote ' inside"),
            new Token(Token::INTERPUNCTION, ';'),
            new Token(Token::IDENTIFIER, 's2'),
            new Token(Token::OPERATOR, '='),
            new Token(Token::STRING, 'double quoted string with an escaped quote " inside'),
            new Token(Token::INTERPUNCTION, ';'),
            new Token(Token::IDENTIFIER, 'a'),
            new Token(Token::OPERATOR, '='),
            new Token(Token::IDENTIFIER, 'b'),
            new Token(Token::OPERATOR, '+'),
            new Token(Token::IDENTIFIER, 'c'),
            new Token(Token::OPERATOR, '*'),
            new Token(Token::IDENTIFIER, 'd'),
            new Token(Token::OPERATOR, '/'),
            new Token(Token::IDENTIFIER, 'e'),
            new Token(Token::OPERATOR, '-'),
            new Token(Token::IDENTIFIER, 'f'),
            new Token(Token::OPERATOR, '%'),
            new Token(Token::IDENTIFIER, 'g'),
            new Token(Token::INTERPUNCTION, ';'),
        );

        $lexer = new Lexer($input);
        $this->assertEquals($output, $lexer->get_token_list());
    }

    public function test_get_token_list_3() {
        $input = <<<EOF
        a = sin(2);
        b = 3sqrt(5);
        c = 4x(a+b);
        d = (a+b)(c+d);
EOF;
        $output = array(
            new Token(Token::IDENTIFIER, 'a'),
            new Token(Token::OPERATOR, '='),
            new Token(Token::IDENTIFIER, 'sin'),
            new Token(Token::OPENING_PAREN, '('),
            new Token(Token::NUMBER, 2),
            new Token(Token::CLOSING_PAREN, ')'),
            new Token(Token::INTERPUNCTION, ';'),
            new Token(Token::IDENTIFIER, 'b'),
            new Token(Token::OPERATOR, '='),
            new Token(Token::NUMBER, 3),
            new Token(Token::IDENTIFIER, 'sqrt'),
            new Token(Token::OPENING_PAREN, '('),
            new Token(Token::NUMBER, 5),
            new Token(Token::CLOSING_PAREN, ')'),
            new Token(Token::INTERPUNCTION, ';'),
            new Token(Token::IDENTIFIER, 'c'),
            new Token(Token::OPERATOR, '='),
            new Token(Token::NUMBER, 4),
            new Token(Token::IDENTIFIER, 'x'),
            new Token(Token::OPENING_PAREN, '('),
            new Token(Token::IDENTIFIER, 'a'),
            new Token(Token::OPERATOR, '+'),
            new Token(Token::IDENTIFIER, 'b'),
            new Token(Token::CLOSING_PAREN, ')'),
            new Token(Token::INTERPUNCTION, ';'),
            new Token(Token::IDENTIFIER, 'd'),
            new Token(Token::OPERATOR, '='),
            new Token(Token::OPENING_PAREN, '('),
            new Token(Token::IDENTIFIER, 'a'),
            new Token(Token::OPERATOR, '+'),
            new Token(Token::IDENTIFIER, 'b'),
            new Token(Token::CLOSING_PAREN, ')'),
            new Token(Token::OPENING_PAREN, '('),
            new Token(Token::IDENTIFIER, 'c'),
            new Token(Token::OPERATOR, '+'),
            new Token(Token::IDENTIFIER, 'd'),
            new Token(Token::CLOSING_PAREN, ')'),
            new Token(Token::INTERPUNCTION, ';'),
        );

        $lexer = new Lexer($input);
        $this->assertEquals($output, $lexer->get_token_list());
    }

    public function test_get_token_list_4() {
        $input = <<<EOF
        a = sin(2E3);
        b = 3e-4sqrt(5e+2);
        c = 4ex(a+b);
EOF;
        $output = array(
            new Token(Token::IDENTIFIER, 'a'),
            new Token(Token::OPERATOR, '='),
            new Token(Token::IDENTIFIER, 'sin'),
            new Token(Token::OPENING_PAREN, '('),
            new Token(Token::NUMBER, 2000),
            new Token(Token::CLOSING_PAREN, ')'),
            new Token(Token::INTERPUNCTION, ';'),
            new Token(Token::IDENTIFIER, 'b'),
            new Token(Token::OPERATOR, '='),
            new Token(Token::NUMBER, .0003),
            new Token(Token::IDENTIFIER, 'sqrt'),
            new Token(Token::OPENING_PAREN, '('),
            new Token(Token::NUMBER, 500),
            new Token(Token::CLOSING_PAREN, ')'),
            new Token(Token::INTERPUNCTION, ';'),
            new Token(Token::IDENTIFIER, 'c'),
            new Token(Token::OPERATOR, '='),
            new Token(Token::NUMBER, 4),
            new Token(Token::IDENTIFIER, 'ex'),
            new Token(Token::OPENING_PAREN, '('),
            new Token(Token::IDENTIFIER, 'a'),
            new Token(Token::OPERATOR, '+'),
            new Token(Token::IDENTIFIER, 'b'),
            new Token(Token::CLOSING_PAREN, ')'),
            new Token(Token::INTERPUNCTION, ';'),
        );

        $lexer = new Lexer($input);
        $this->assertEquals($output, $lexer->get_token_list());
    }

    public function test_get_token_list_5() {
        $input = <<<EOF
        a = {1:10:2};
        b = [a, b, 'c', "d"];
        foo = [bar, [hello, world], [1, 2, 3], ["s", "t"]];
EOF;
        $output = array(
            new Token(Token::IDENTIFIER, 'a'),
            new Token(Token::OPERATOR, '='),
            new Token(Token::OPENING_PAREN, '{'),
            new Token(Token::NUMBER, 1),
            new Token(Token::OPERATOR, ':'),
            new Token(Token::NUMBER, 10),
            new Token(Token::OPERATOR, ':'),
            new Token(Token::NUMBER, 2),
            new Token(Token::CLOSING_PAREN, '}'),
            new Token(Token::INTERPUNCTION, ';'),
            new Token(Token::IDENTIFIER, 'b'),
            new Token(Token::OPERATOR, '='),
            new Token(Token::OPENING_PAREN, '['),
            new Token(Token::IDENTIFIER, 'a'),
            new Token(Token::INTERPUNCTION, ','),
            new Token(Token::IDENTIFIER, 'b'),
            new Token(Token::INTERPUNCTION, ','),
            new Token(Token::STRING, 'c'),
            new Token(Token::INTERPUNCTION, ','),
            new Token(Token::STRING, 'd'),
            new Token(Token::CLOSING_PAREN, ']'),
            new Token(Token::INTERPUNCTION, ';'),
            new Token(Token::IDENTIFIER, 'foo'),
            new Token(Token::OPERATOR, '='),
            new Token(Token::OPENING_PAREN, '['),
            new Token(Token::IDENTIFIER, 'bar'),
            new Token(Token::INTERPUNCTION, ','),
            new Token(Token::OPENING_PAREN, '['),
            new Token(Token::IDENTIFIER, 'hello'),
            new Token(Token::INTERPUNCTION, ','),
            new Token(Token::IDENTIFIER, 'world'),
            new Token(Token::CLOSING_PAREN, ']'),
            new Token(Token::INTERPUNCTION, ','),
            new Token(Token::OPENING_PAREN, '['),
            new Token(Token::NUMBER, 1),
            new Token(Token::INTERPUNCTION, ','),
            new Token(Token::NUMBER, 2),
            new Token(Token::INTERPUNCTION, ','),
            new Token(Token::NUMBER, 3),
            new Token(Token::CLOSING_PAREN, ']'),
            new Token(Token::INTERPUNCTION, ','),
            new Token(Token::OPENING_PAREN, '['),
            new Token(Token::STRING, 's'),
            new Token(Token::INTERPUNCTION, ','),
            new Token(Token::STRING, 't'),
            new Token(Token::CLOSING_PAREN, ']'),
            new Token(Token::CLOSING_PAREN, ']'),
            new Token(Token::INTERPUNCTION, ';'),
        );

        $lexer = new Lexer($input);
        $this->assertEquals($output, $lexer->get_token_list());
    }

    public function test_get_token_list_6() {
        $input = <<<EOF
        a = (     b == 1    ?   7 : 3);
        b = c[var > 1];
        c = thing     *    (x != 4);
EOF;
        $output = array(
            new Token(Token::IDENTIFIER, 'a'),
            new Token(Token::OPERATOR, '='),
            new Token(Token::OPENING_PAREN, '('),
            new Token(Token::IDENTIFIER, 'b'),
            new Token(Token::OPERATOR, '=='),
            new Token(Token::NUMBER, 1),
            new Token(Token::OPERATOR, '?'),
            new Token(Token::NUMBER, 7),
            new Token(Token::OPERATOR, ':'),
            new Token(Token::NUMBER, 3),
            new Token(Token::CLOSING_PAREN, ')'),
            new Token(Token::INTERPUNCTION, ';'),
            new Token(Token::IDENTIFIER, 'b'),
            new Token(Token::OPERATOR, '='),
            new Token(Token::IDENTIFIER, 'c'),
            new Token(Token::OPENING_PAREN, '['),
            new Token(Token::IDENTIFIER, 'var'),
            new Token(Token::OPERATOR, '>'),
            new Token(Token::NUMBER, '1'),
            new Token(Token::CLOSING_PAREN, ']'),
            new Token(Token::INTERPUNCTION, ';'),
            new Token(Token::IDENTIFIER, 'c'),
            new Token(Token::OPERATOR, '='),
            new Token(Token::IDENTIFIER, 'thing'),
            new Token(Token::OPERATOR, '*'),
            new Token(Token::OPENING_PAREN, '('),
            new Token(Token::IDENTIFIER, 'x'),
            new Token(Token::OPERATOR, '!='),
            new Token(Token::NUMBER, 4),
            new Token(Token::CLOSING_PAREN, ')'),
            new Token(Token::INTERPUNCTION, ';'),
        );

        $lexer = new Lexer($input);
        $this->assertEquals($output, $lexer->get_token_list());
    }

    public function test_read_identifier() {
        $testcases = array(
            array('input' => 'abc_', 'output' => 'abc_'),
            array('input' => 'a1', 'output' => 'a1'),
            array('input' => 'ab-', 'output' => 'ab'),
            array('input' => 'ABCabc', 'output' => 'ABCabc'),
            array('input' => 'ABC___abc', 'output' => 'ABC___abc'),
            array('input' => 'abc1*', 'output' => 'abc1'),
            array('input' => 'abc(x)', 'output' => 'abc'),
        );

        foreach ($testcases as $case) {
            $lexer = new Lexer($case['input']);
            $this->assertEquals($case['output'], $lexer->next_token()->value);
        }
    }

    public function test_read_parens() {
        $testcases = array(
            array('input' => ';', 'output' => ';'),
            array('input' => '(', 'output' => '('),
            array('input' => '[', 'output' => '['),
            array('input' => '{', 'output' => '{'),
            array('input' => '(a', 'output' => '('),
            array('input' => '[   asd', 'output' => '['),
            array('input' => ')', 'output' => ')'),
            array('input' => ']', 'output' => ']'),
            array('input' => '}', 'output' => '}'),
            array('input' => '}a', 'output' => '}'),
            array('input' => ']   asd', 'output' => ']'),
        );

        foreach ($testcases as $case) {
            $lexer = new Lexer($case['input']);
            $this->assertEquals($case['output'], $lexer->next_token()->value);
        }
    }

    public function test_read_operator() {
        $testcases = array(
            array('input' => '+', 'output' => '+'),
            array('input' => '+*', 'output' => '+'),
            array('input' => '+ ', 'output' => '+'),
            array('input' => '-', 'output' => '-'),
            array('input' => '*', 'output' => '*'),
            array('input' => '/', 'output' => '/'),
            array('input' => '%', 'output' => '%'),
            array('input' => '**', 'output' => '**'),
            array('input' => '***', 'output' => '**'),
            array('input' => '=', 'output' => '='),
            array('input' => '&', 'output' => '&'),
            array('input' => '|', 'output' => '|'),
            array('input' => '~', 'output' => '~'),
            array('input' => '^', 'output' => '^'),
            array('input' => '<<', 'output' => '<<'),
            array('input' => '>>', 'output' => '>>'),
            array('input' => '==', 'output' => '=='),
            array('input' => '===', 'output' => '=='),
            array('input' => '!=', 'output' => '!='),
            array('input' => '!+', 'output' => '!'),
            array('input' => '!==', 'output' => '!='),
            array('input' => '>', 'output' => '>'),
            array('input' => '<', 'output' => '<'),
            array('input' => '<=', 'output' => '<='),
            array('input' => '<==', 'output' => '<='),
            array('input' => '>=', 'output' => '>='),
            array('input' => '>==', 'output' => '>='),
            array('input' => '&&', 'output' => '&&'),
            array('input' => '&&&', 'output' => '&&'),
            array('input' => '||', 'output' => '||'),
            array('input' => '|*|', 'output' => '|'),
            array('input' => '|||', 'output' => '||'),
            array('input' => '!', 'output' => '!'),
            array('input' => '?', 'output' => '?'),
            array('input' => ':', 'output' => ':'),
        );

        foreach ($testcases as $case) {
            $lexer = new Lexer($case['input']);
            $this->assertEquals($case['output'], $lexer->next_token()->value);
        }
    }

    public function test_read_string() {
        $testcases = array(
            array('input' => "'foo'", 'output' => 'foo'),
            array('input' => '"foo"', 'output' => 'foo'),
            // Test a single quote string with a linebreak.
            array('input' => "'foo\nbar'", 'output' => "foo\nbar"),
            // Test usage of a double quote in a single quote string.
            array('input' => "'foo\"bar'", 'output' => "foo\"bar"),
            // Test usage of an escaped double quote in a double quote string.
            array('input' => '"foo\"bar"', 'output' => 'foo"bar'),
            // Test useage of a single quote in a double quote string.
            array('input' => '"foo\'bar"', 'output' => "foo'bar"),
            // Test usage of the escape sequence \n in a single quote string.
            array('input' => "'foo\\nbar'", 'output' => "foo\nbar"),
            // Test usage of the escape sequence \n in a double quote string.
            array('input' => '"foo\nbar"', 'output' => "foo\nbar"),
            // Test usage of the escape sequence \t in a single quote string.
            array('input' => "'foo\\tbar'", 'output' => "foo\tbar"),
            // Test usage of the escape sequence \t in a double quote string.
            array('input' => '"foo\tbar"', 'output' => "foo\tbar"),
            // Test usage of an unescaped backslash in a double quote string.
            array('input' => '"foo\bar"', 'output' => 'foo\bar'),
            // Test usage of an unescaped backslash in single quote string.
            array('input' => "'foo\\bar'", 'output' => 'foo\bar'),
            // Test usage of an escaped backslash in a double quote string.
            array('input' => '"foo\\\\bar"', 'output' => 'foo\\\\bar'),
            // Test usage of an escaped backslash in single quote string.
            array('input' => "'foo\\\\bar'", 'output' => 'foo\\\\bar'),

        );

        foreach ($testcases as $case) {
            $lexer = new Lexer($case['input']);
            $this->assertEquals($case['output'], $lexer->next_token()->value);
        }
    }

    /**
     * Test whether the read() function of the Tokenizer class correctly parses numbers.
     */
    public function test_read_number() {
        $testcases = array(
            array('input' => "\n123", 'output' => 123),
            array('input' => "\n\n123", 'output' => 123),
            array('input' => "321# testcomment", 'output' => 321),
            array('input' => '1a2b3c4d', 'output' => 1),
            array('input' => '5.a2.b3.c4.d', 'output' => 5),
            array('input' => '1234', 'output' => 1234),
            array('input' => '1234    ', 'output' => 1234),
            array('input' => ' 1234', 'output' => 1234),
            array('input' => ' 123 4', 'output' => 123),
            array('input' => '1234aaaaa', 'output' => 1234),
            array('input' => "    \n\n\t\r   1234", 'output' => 1234),
            array('input' => '.12', 'output' => 0.12),
            array('input' => '     .123', 'output' => 0.123),
            array('input' => '0.111', 'output' => 0.111),
            array('input' => '12.34', 'output' => 12.34),
            array('input' => '12.34e2', 'output' => 1234),
            array('input' => '1E3e2', 'output' => 1000),
            array('input' => '1E5', 'output' => 100000),
            array('input' => '1e4', 'output' => 10000),
            array('input' => '1e', 'output' => 1),
            array('input' => '1ex', 'output' => 1),
            array('input' => '12e ', 'output' => 12),
            array('input' => '1e-2', 'output' => 0.01),
            array('input' => '1e+2', 'output' => 100),
            array('input' => '1e-2+', 'output' => 0.01),
            array('input' => '1e+2-', 'output' => 100),
            array('input' => '12e+', 'output' => 12),
            array('input' => '15e-', 'output' => 15),
            array('input' => '17e-+', 'output' => 17),
            array('input' => '23e+-', 'output' => 23),
        );

        foreach ($testcases as $case) {
            $lexer = new Lexer($case['input']);
            $this->assertEquals($case['output'], $lexer->next_token()->value);
        }
    }

    /**
     * Test that the InputStream class indicates the correct position when die()ing.
     */
    public function test_die() {
        $input = "abcde\nfghij\nklmno";
        $reader = new InputStream($input);

        try {
            $reader->die('foo');

        } catch (Exception $e) {
            $this->assertEquals('1:0:foo', $e->getMessage());
        }

        for ($i = 0; $i < 5; $i++) {
            $reader->read();
        }
        try {
            $reader->die('bar');
        } catch (Exception $e) {
            $this->assertEquals('1:5:bar', $e->getMessage());
        }

        $reader->read();
        try {
            $reader->die('error');
        } catch (Exception $e) {
            $this->assertEquals('2:0:error', $e->getMessage());
        }

        $reader->read();
        try {
            $reader->die('other error');
        } catch (Exception $e) {
            $this->assertEquals('2:1:other error', $e->getMessage());
        }

        for ($i = 0; $i < 5; $i++) {
            $reader->read();
        }
        try {
            $reader->die('foo');
        } catch (Exception $e) {
            $this->assertEquals('3:0:foo', $e->getMessage());
        }

        $reader->read();
        try {
            $reader->die('x');
        } catch (Exception $e) {
            $this->assertEquals('3:1:x', $e->getMessage());
        }

        for ($i = 0; $i < 4; $i++) {
            $reader->read();
        }
        try {
            $reader->die('lastchar');
        } catch (Exception $e) {
            $this->assertEquals('3:5:lastchar', $e->getMessage());
        }

        $reader->read();
        try {
            $reader->die('shouldnotmovefarther');
        } catch (Exception $e) {
            $this->assertEquals('3:5:shouldnotmovefarther', $e->getMessage());
        }
    }

}
