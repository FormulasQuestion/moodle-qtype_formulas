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
require_once($CFG->dirroot . '/question/type/formulas/classes/parser/parser.php');
require_once($CFG->dirroot . '/question/type/formulas/classes/parser/lexer.php');
require_once($CFG->dirroot . '/question/type/formulas/classes/parser/inputstream.php');
require_once($CFG->dirroot . '/question/type/formulas/classes/parser/shuntingyard.php');

class tokenizer_test extends \advanced_testcase {

    public function test_basic_operations() {
        //$input = 'for (i:1:4) { }';
        //$tokens = (new Lexer($input))->get_token_list();
        //print_r($tokens); return;

        /*$input = '1*2-3/4+5*6-7*8+9/10';
        $input = 'a(2)';
        $input = 'a b';
        $lexer = new Lexer($input);
        $tokens = $lexer->get_token_list();
        $parser = new Parser($tokens, true, ['a']);
        $output = ShuntingYard::shunting_yard($parser->statements[0]);
        print_r(array_map(function($el) { return $el->value; }, $output));
        die();*/

        $input = 'a = 1+2*3';
        $input = 'a = 1*(2+3)**2*4';
        $input = 'a = 1*2+3';
        $input = 'a = 5 = 3';
        $input = 'a = b = 7 + 1';
        $input = 'a = (b = 3) * 4; c = 5 * a(1 + b) * d(4 + a) + e;';
        $input = 'a = 4; b = !a';
        $input = 'a = 1*2-3/4+5*6-7*8+9/10';
        $input = 'a = !a b 2';

        $lexer = new Lexer($input);
        $parser = new Parser($lexer->get_token_list());
        foreach ($parser->statements as $statement) {
            $output = ShuntingYard::shunting_yard($statement);
            print_r(array_map(function($el) { return $el->value; }, $output));
        }
        //print_r($output);
    }

    public function test_parse_list() {
        //$input = '[1, 2, 3]';
        //$input = '[1, "a", 3]';
        //$input = '[1, ["x", "y"], 3]';
        //$input = '[[1,2]]';
        $input = '[1, ["x", "y"], [3, 4], 5, [[1,2]],6]';

        $lexer = new Lexer($input);
        $parser = new Parser($lexer->get_token_list());
        print_r($parser->get_statements());
    }

    public function test_get_token_list_1() {
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
        $output = array(
            new Token(Token::IDENTIFIER, 'this', 1, 1),
            new Token(Token::OPERATOR, '=', 1, 6),
            new Token(Token::IDENTIFIER, 'that', 1, 8),
            new Token(Token::OPERATOR, '*', 1, 13),
            new Token(Token::IDENTIFIER, 'other_thing', 1, 15),
            new Token(Token::END_OF_STATEMENT, ';', 1, 26),
            new Token(Token::IDENTIFIER, 's1', 2, 1),
            new Token(Token::OPERATOR, '=', 2, 4),
            new Token(Token::STRING, 'single quoted string with a double quote " inside', 2, 6),
            new Token(Token::END_OF_STATEMENT, ';', 2, 57),
            new Token(Token::IDENTIFIER, 's2', 4, 1),
            new Token(Token::OPERATOR, '=', 4, 4),
            new Token(Token::STRING, 'double quoted string with a single quote \' inside', 4, 6),
            new Token(Token::END_OF_STATEMENT, ';', 4, 57),
            new Token(Token::IDENTIFIER, 's3', 5, 1),
            new Token(Token::OPERATOR, '=', 5, 4),
            new Token(Token::STRING, "string\nwith a newline", 5, 6),
            new Token(Token::END_OF_STATEMENT, ';', 5, 30),
            new Token(Token::IDENTIFIER, '_s4', 6, 1),
            new Token(Token::OPERATOR, '=', 6, 5),
            new Token(Token::STRING, "string with a real\nnewline", 6, 7),
            new Token(Token::END_OF_STATEMENT, ';', 7, 9),
            new Token(Token::IDENTIFIER, 'x', 8, 1),
            new Token(Token::OPERATOR, '=', 8, 3),
            new Token(Token::NUMBER, 2, 8, 5),
            new Token(Token::OPERATOR, '*', 8, 6),
            new Token(Token::IDENTIFIER, 'x', 8, 7),
            new Token(Token::OPERATOR, '+', 8, 8),
            new Token(Token::IDENTIFIER, 'z', 8, 9),
            new Token(Token::END_OF_STATEMENT, ';', 8, 10),
            new Token(Token::IDENTIFIER, 'f', 9, 1),
            new Token(Token::OPERATOR, '=', 9, 3),
            new Token(Token::NUMBER, 4, 9, 5),
            new Token(Token::IDENTIFIER, 'g', 9, 6),
            new Token(Token::OPERATOR, '-', 9, 7),
            new Token(Token::IDENTIFIER, 'e', 9, 8),
            new Token(Token::END_OF_STATEMENT, ';', 9, 9),
            new Token(Token::IDENTIFIER, 'test', 10, 1),
            new Token(Token::OPERATOR, '=', 10, 6),
            new Token(Token::OPENING_PAREN, '(', 10, 8),
            new Token(Token::IDENTIFIER, 'a', 10, 9),
            new Token(Token::OPERATOR, '==', 10, 11),
            new Token(Token::IDENTIFIER, 'b', 10, 14),
            new Token(Token::OPERATOR, '?', 10, 16),
            new Token(Token::IDENTIFIER, 'c', 10, 18),
            new Token(Token::OPERATOR, ':', 10, 20),
            new Token(Token::IDENTIFIER, 'd', 10, 22),
            new Token(Token::CLOSING_PAREN, ')', 10, 23),
            new Token(Token::END_OF_STATEMENT, ';', 10, 24),
        );

        $lexer = new Lexer($input);
        $this->assertEquals($output, $lexer->get_token_list());
    }

    public function test_get_token_list_unicode() {
        $input = <<<EOF
s = 'string with äöüéç…';
t = join("", 'äöü', 'éçñ…');
EOF;
        $output = array(
            new Token(Token::IDENTIFIER, 's', 1, 1),
            new Token(Token::OPERATOR, '=', 1, 3),
            new Token(Token::STRING, 'string with äöüéç…', 1, 5),
            new Token(Token::END_OF_STATEMENT, ';', 1, 25),
            new Token(Token::IDENTIFIER, 't', 2, 1),
            new Token(Token::OPERATOR, '=', 2, 3),
            new Token(Token::IDENTIFIER, 'join', 2, 5),
            new Token(Token::OPENING_PAREN, '(', 2, 9),
            new Token(Token::STRING, '', 2, 10),
            new Token(Token::ARG_SEPARATOR, ',', 2, 12),
            new Token(Token::STRING, 'äöü', 2, 14),
            new Token(Token::ARG_SEPARATOR, ',', 2, 19),
            new Token(Token::STRING, 'éçñ…', 2, 21),
            new Token(Token::CLOSING_PAREN, ')', 2, 27),
            new Token(Token::END_OF_STATEMENT, ';', 2, 28),
        );

        $lexer = new Lexer($input);
        $this->assertEquals($output, $lexer->get_token_list());
    }

    public function test_get_token_list_2() {
        $input = <<<EOF
        s1 = 'single quoted string with an escaped quote \' inside';
        # just a comment
        s_2 = "double quoted string with an escaped quote \" inside";
        a = b + c * d / e - f % g;
EOF;
        // We are not testing the positions here.
        $output = array(
            new Token(Token::IDENTIFIER, 's1', 0, 0),
            new Token(Token::OPERATOR, '=', 0, 0),
            new Token(Token::STRING, "single quoted string with an escaped quote ' inside", 0, 0),
            new Token(Token::END_OF_STATEMENT, ';', 0, 0),
            new Token(Token::IDENTIFIER, 's_2', 0, 0),
            new Token(Token::OPERATOR, '=', 0, 0),
            new Token(Token::STRING, 'double quoted string with an escaped quote " inside', 0, 0),
            new Token(Token::END_OF_STATEMENT, ';', 0, 0),
            new Token(Token::IDENTIFIER, 'a', 0, 0),
            new Token(Token::OPERATOR, '=', 0, 0),
            new Token(Token::IDENTIFIER, 'b', 0, 0),
            new Token(Token::OPERATOR, '+', 0, 0),
            new Token(Token::IDENTIFIER, 'c', 0, 0),
            new Token(Token::OPERATOR, '*', 0, 0),
            new Token(Token::IDENTIFIER, 'd', 0, 0),
            new Token(Token::OPERATOR, '/', 0, 0),
            new Token(Token::IDENTIFIER, 'e', 0, 0),
            new Token(Token::OPERATOR, '-', 0, 0),
            new Token(Token::IDENTIFIER, 'f', 0, 0),
            new Token(Token::OPERATOR, '%', 0, 0),
            new Token(Token::IDENTIFIER, 'g', 0, 0),
            new Token(Token::END_OF_STATEMENT, ';', 0, 0),
        );

        $tokens = (new Lexer($input))->get_token_list();
        foreach ($tokens as $i => $token) {
            $this->assertEquals($output[$i]->type, $token->type);
            $this->assertEquals($output[$i]->value, $token->value);
        }
    }

    public function test_get_token_list_3() {
        $input = <<<'EOF'
        a = \sin(2);
        b = 3sqrt(5);
        c = 4x(a+b);
        d = (a+b)(c+d);
EOF;

        // We are not testing the positions here.
        $output = array(
            new Token(Token::IDENTIFIER, 'a', 0, 0),
            new Token(Token::OPERATOR, '=', 0, 0),
            new Token(Token::PREFIX, '\\', 0, 0),
            new Token(Token::IDENTIFIER, 'sin', 0, 0),
            new Token(Token::OPENING_PAREN, '(', 0, 0),
            new Token(Token::NUMBER, 2, 0, 0),
            new Token(Token::CLOSING_PAREN, ')', 0, 0),
            new Token(Token::END_OF_STATEMENT, ';', 0, 0),
            new Token(Token::IDENTIFIER, 'b', 0, 0),
            new Token(Token::OPERATOR, '=', 0, 0),
            new Token(Token::NUMBER, 3, 0, 0),
            new Token(Token::IDENTIFIER, 'sqrt', 0, 0),
            new Token(Token::OPENING_PAREN, '(', 0, 0),
            new Token(Token::NUMBER, 5, 0, 0),
            new Token(Token::CLOSING_PAREN, ')', 0, 0),
            new Token(Token::END_OF_STATEMENT, ';', 0, 0),
            new Token(Token::IDENTIFIER, 'c', 0, 0),
            new Token(Token::OPERATOR, '=', 0, 0),
            new Token(Token::NUMBER, 4, 0, 0),
            new Token(Token::IDENTIFIER, 'x', 0, 0),
            new Token(Token::OPENING_PAREN, '(', 0, 0),
            new Token(Token::IDENTIFIER, 'a', 0, 0),
            new Token(Token::OPERATOR, '+', 0, 0),
            new Token(Token::IDENTIFIER, 'b', 0, 0),
            new Token(Token::CLOSING_PAREN, ')', 0, 0),
            new Token(Token::END_OF_STATEMENT, ';', 0, 0),
            new Token(Token::IDENTIFIER, 'd', 0, 0),
            new Token(Token::OPERATOR, '=', 0, 0),
            new Token(Token::OPENING_PAREN, '(', 0, 0),
            new Token(Token::IDENTIFIER, 'a', 0, 0),
            new Token(Token::OPERATOR, '+', 0, 0),
            new Token(Token::IDENTIFIER, 'b', 0, 0),
            new Token(Token::CLOSING_PAREN, ')', 0, 0),
            new Token(Token::OPENING_PAREN, '(', 0, 0),
            new Token(Token::IDENTIFIER, 'c', 0, 0),
            new Token(Token::OPERATOR, '+', 0, 0),
            new Token(Token::IDENTIFIER, 'd', 0, 0),
            new Token(Token::CLOSING_PAREN, ')', 0, 0),
            new Token(Token::END_OF_STATEMENT, ';', 0, 0),
        );

        $tokens = (new Lexer($input))->get_token_list();
        foreach ($tokens as $i => $token) {
            $this->assertEquals($output[$i]->type, $token->type);
            $this->assertEquals($output[$i]->value, $token->value);
        }
    }

    public function test_get_token_list_4() {
        $input = <<<EOF
        a = sin(2E3);
        b = 3e-4sqrt(5e+2);
        c = 4ex(a+b);
EOF;

        // We are not testing the positions here.
        $output = array(
            new Token(Token::IDENTIFIER, 'a', 0, 0),
            new Token(Token::OPERATOR, '=', 0, 0),
            new Token(Token::IDENTIFIER, 'sin', 0, 0),
            new Token(Token::OPENING_PAREN, '(', 0, 0),
            new Token(Token::NUMBER, 2000, 0, 0),
            new Token(Token::CLOSING_PAREN, ')', 0, 0),
            new Token(Token::END_OF_STATEMENT, ';', 0, 0),
            new Token(Token::IDENTIFIER, 'b', 0, 0),
            new Token(Token::OPERATOR, '=', 0, 0),
            new Token(Token::NUMBER, .0003, 0, 0),
            new Token(Token::IDENTIFIER, 'sqrt', 0, 0),
            new Token(Token::OPENING_PAREN, '(', 0, 0),
            new Token(Token::NUMBER, 500, 0, 0),
            new Token(Token::CLOSING_PAREN, ')', 0, 0),
            new Token(Token::END_OF_STATEMENT, ';', 0, 0),
            new Token(Token::IDENTIFIER, 'c', 0, 0),
            new Token(Token::OPERATOR, '=', 0, 0),
            new Token(Token::NUMBER, 4, 0, 0),
            new Token(Token::IDENTIFIER, 'ex', 0, 0),
            new Token(Token::OPENING_PAREN, '(', 0, 0),
            new Token(Token::IDENTIFIER, 'a', 0, 0),
            new Token(Token::OPERATOR, '+', 0, 0),
            new Token(Token::IDENTIFIER, 'b', 0, 0),
            new Token(Token::CLOSING_PAREN, ')', 0, 0),
            new Token(Token::END_OF_STATEMENT, ';', 0, 0),
        );

        $tokens = (new Lexer($input))->get_token_list();
        foreach ($tokens as $i => $token) {
            $this->assertEquals($output[$i]->type, $token->type);
            $this->assertEquals($output[$i]->value, $token->value);
        }
    }

    public function test_get_token_list_5() {
        $input = <<<EOF
        a = {1:10:2};
        b = [a, b, 'c', "d"];
        foo = [bar, [hello, world], [1, 2, 3], ["s", "t"]];
EOF;

        // We are not testing the positions here.
        $output = array(
            new Token(Token::IDENTIFIER, 'a', 0, 0),
            new Token(Token::OPERATOR, '=', 0, 0),
            new Token(Token::OPENING_BRACE, '{', 0, 0),
            new Token(Token::NUMBER, 1, 0, 0),
            new Token(Token::OPERATOR, ':', 0, 0),
            new Token(Token::NUMBER, 10, 0, 0),
            new Token(Token::OPERATOR, ':', 0, 0),
            new Token(Token::NUMBER, 2, 0, 0),
            new Token(Token::CLOSING_BRACE, '}', 0, 0),
            new Token(Token::END_OF_STATEMENT, ';', 0, 0),
            new Token(Token::IDENTIFIER, 'b', 0, 0),
            new Token(Token::OPERATOR, '=', 0, 0),
            new Token(Token::OPENING_BRACKET, '[', 0, 0),
            new Token(Token::IDENTIFIER, 'a', 0, 0),
            new Token(Token::ARG_SEPARATOR, ',', 0, 0),
            new Token(Token::IDENTIFIER, 'b', 0, 0),
            new Token(Token::ARG_SEPARATOR, ',', 0, 0),
            new Token(Token::STRING, 'c', 0, 0),
            new Token(Token::ARG_SEPARATOR, ',', 0, 0),
            new Token(Token::STRING, 'd', 0, 0),
            new Token(Token::CLOSING_BRACKET, ']', 0, 0),
            new Token(Token::END_OF_STATEMENT, ';', 0, 0),
            new Token(Token::IDENTIFIER, 'foo', 0, 0),
            new Token(Token::OPERATOR, '=', 0, 0),
            new Token(Token::OPENING_BRACKET, '[', 0, 0),
            new Token(Token::IDENTIFIER, 'bar', 0, 0),
            new Token(Token::ARG_SEPARATOR, ',', 0, 0),
            new Token(Token::OPENING_BRACKET, '[', 0, 0),
            new Token(Token::IDENTIFIER, 'hello', 0, 0),
            new Token(Token::ARG_SEPARATOR, ',', 0, 0),
            new Token(Token::IDENTIFIER, 'world', 0, 0),
            new Token(Token::CLOSING_BRACKET, ']', 0, 0),
            new Token(Token::ARG_SEPARATOR, ',', 0, 0),
            new Token(Token::OPENING_BRACKET, '[', 0, 0),
            new Token(Token::NUMBER, 1, 0, 0),
            new Token(Token::ARG_SEPARATOR, ',', 0, 0),
            new Token(Token::NUMBER, 2, 0, 0),
            new Token(Token::ARG_SEPARATOR, ',', 0, 0),
            new Token(Token::NUMBER, 3, 0, 0),
            new Token(Token::CLOSING_BRACKET, ']', 0, 0),
            new Token(Token::ARG_SEPARATOR, ',', 0, 0),
            new Token(Token::OPENING_BRACKET, '[', 0, 0),
            new Token(Token::STRING, 's', 0, 0),
            new Token(Token::ARG_SEPARATOR, ',', 0, 0),
            new Token(Token::STRING, 't', 0, 0),
            new Token(Token::CLOSING_BRACKET, ']', 0, 0),
            new Token(Token::CLOSING_BRACKET, ']', 0, 0),
            new Token(Token::END_OF_STATEMENT, ';', 0, 0),
        );

        $tokens = (new Lexer($input))->get_token_list();
        foreach ($tokens as $i => $token) {
            $this->assertEquals($output[$i]->type, $token->type);
            $this->assertEquals($output[$i]->value, $token->value);
        }
    }

    public function test_get_token_list_6() {
        $input = <<<EOF
        a = (     b == 1    ?   7 : 3);
        b = c[var > 1];
        c = thing     *    (x != 4);
EOF;

        // We are not testing the positions here.
        $output = array(
            new Token(Token::IDENTIFIER, 'a', 0, 0),
            new Token(Token::OPERATOR, '=', 0, 0),
            new Token(Token::OPENING_PAREN, '(', 0, 0),
            new Token(Token::IDENTIFIER, 'b', 0, 0),
            new Token(Token::OPERATOR, '==', 0, 0),
            new Token(Token::NUMBER, 1, 0, 0),
            new Token(Token::OPERATOR, '?', 0, 0),
            new Token(Token::NUMBER, 7, 0, 0),
            new Token(Token::OPERATOR, ':', 0, 0),
            new Token(Token::NUMBER, 3, 0, 0),
            new Token(Token::CLOSING_PAREN, ')', 0, 0),
            new Token(Token::END_OF_STATEMENT, ';', 0, 0),
            new Token(Token::IDENTIFIER, 'b', 0, 0),
            new Token(Token::OPERATOR, '=', 0, 0),
            new Token(Token::IDENTIFIER, 'c', 0, 0),
            new Token(Token::OPENING_BRACKET, '[', 0, 0),
            new Token(Token::IDENTIFIER, 'var', 0, 0),
            new Token(Token::OPERATOR, '>', 0, 0),
            new Token(Token::NUMBER, '1', 0, 0),
            new Token(Token::CLOSING_BRACKET, ']', 0, 0),
            new Token(Token::END_OF_STATEMENT, ';', 0, 0),
            new Token(Token::IDENTIFIER, 'c', 0, 0),
            new Token(Token::OPERATOR, '=', 0, 0),
            new Token(Token::IDENTIFIER, 'thing', 0, 0),
            new Token(Token::OPERATOR, '*', 0, 0),
            new Token(Token::OPENING_PAREN, '(', 0, 0),
            new Token(Token::IDENTIFIER, 'x', 0, 0),
            new Token(Token::OPERATOR, '!=', 0, 0),
            new Token(Token::NUMBER, 4, 0, 0),
            new Token(Token::CLOSING_PAREN, ')', 0, 0),
            new Token(Token::END_OF_STATEMENT, ';', 0, 0),
        );

        $tokens = (new Lexer($input))->get_token_list();
        foreach ($tokens as $i => $token) {
            $this->assertEquals($output[$i]->type, $token->type);
            $this->assertEquals($output[$i]->value, $token->value);
        }
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
            $tokens = $lexer->get_token_list();
            $this->assertEquals($case['output'], $tokens[0]->value);
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
            $tokens = $lexer->get_token_list();
            $this->assertEquals($case['output'], $tokens[0]->value);
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
            $tokens = $lexer->get_token_list();
            $this->assertEquals($case['output'], $tokens[0]->value);
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
            $tokens = $lexer->get_token_list();
            $this->assertEquals($case['output'], $tokens[0]->value);
        }
        $testcases = array(
            array('input' => '"foo', 'output' => 'foo'),
            array('input' => "'foo", 'output' => 'foo'),
        );
        foreach ($testcases as $case) {
            $lexer = new Lexer($case['input']);
            try {
                $tokens = $lexer->get_token_list();
                $this->assertEquals($case['output'], $tokens[0]->value);
            } catch (Exception $e) {
                $this->assertEquals('1:4:unterminated string, started at row 1 and column 1', $e->getMessage());
            }
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
            // FIXME: input is invalid and must trigger error
            // array('input' => '5.a2.b3.c4.d', 'output' => 5),
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
            $tokens = $lexer->get_token_list();
            $this->assertEquals($case['output'], $tokens[0]->value);
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
