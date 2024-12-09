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

/**
 * qtype_formulas parser tests
 *
 * @package    qtype_formulas
 * @category   test
 * @copyright  2022 Philipp Imhof
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


 // TODO: most of these tests should only be made in the evaluator; we should only test specific
 // parser features here, e.g. error detection, but conversion from input to token list is
 // purely an implementation thing; the user only cares about results
// has_token_in_tokenlist

namespace qtype_formulas;

use Exception;

class parser_test extends \advanced_testcase {


    public function test_has_token_in_tokenlist(): void {
        $input = 'sin = 5';
        $parser = new parser($input);
        self::assertTrue($parser->has_token_in_tokenlist(token::VARIABLE, 'sin'));
        self::assertFalse($parser->has_token_in_tokenlist(token::FUNCTION, 'sin'));

        $input = 'tan = 5; y = \tan(12)';
        $parser = new parser($input);
        self::assertTrue($parser->has_token_in_tokenlist(token::VARIABLE, 'tan'));
        self::assertTrue($parser->has_token_in_tokenlist(token::FUNCTION, 'tan'));

        $input = 'a = 5.0 - (3**4); b = [1:10:2]; c = sin(2.5*x);';
        $parser = new parser($input);
        self::assertTrue($parser->has_token_in_tokenlist(token::VARIABLE, 'a'));
        self::assertTrue($parser->has_token_in_tokenlist(token::VARIABLE, 'b'));
        self::assertTrue($parser->has_token_in_tokenlist(token::VARIABLE, 'c'));
        self::assertTrue($parser->has_token_in_tokenlist(token::VARIABLE, 'x'));
        self::assertTrue($parser->has_token_in_tokenlist(token::FUNCTION, 'sin'));
        self::assertTrue($parser->has_token_in_tokenlist(token::NUMBER, 5.0));
        self::assertTrue($parser->has_token_in_tokenlist(token::NUMBER, 5));
        self::assertTrue($parser->has_token_in_tokenlist(token::NUMBER, 4));
        self::assertTrue($parser->has_token_in_tokenlist(token::NUMBER, 3));
        self::assertTrue($parser->has_token_in_tokenlist(token::NUMBER, 2.5));
        self::assertTrue($parser->has_token_in_tokenlist(token::NUMBER, 2));
        self::assertTrue($parser->has_token_in_tokenlist(token::NUMBER, 1));
        self::assertTrue($parser->has_token_in_tokenlist(token::NUMBER, 10));
        self::assertTrue($parser->has_token_in_tokenlist(token::OPERATOR, '='));
        self::assertTrue($parser->has_token_in_tokenlist(token::OPERATOR, '-'));
        self::assertTrue($parser->has_token_in_tokenlist(token::OPERATOR, '*'));
        self::assertTrue($parser->has_token_in_tokenlist(token::OPERATOR, '**'));
        self::assertTrue($parser->has_token_in_tokenlist(token::OPENING_BRACKET, '['));
        self::assertTrue($parser->has_token_in_tokenlist(token::CLOSING_BRACKET, ']'));
        self::assertTrue($parser->has_token_in_tokenlist(token::OPENING_PAREN, '('));
        self::assertTrue($parser->has_token_in_tokenlist(token::CLOSING_PAREN, ')'));
        self::assertTrue($parser->has_token_in_tokenlist(token::END_OF_STATEMENT, ';'));
    }

    /**
     * @dataProvider provide_assignments
     */
    public function test_assignments($expected, $input): void {
        $parser = new parser($input);
        $statement = $parser->get_statements()[0];
        self::assertEquals($expected, implode(',', $statement->body));
    }

    public function provide_assignments(): array {
        return [
            'arithmetic expression' => ['a,1,2,3,*,+,=', 'a = 1+2*3'],
            'arithmetic expression with ternary in parens' => [
                'a,5,b,1,==,%%ternary-sentinel,3,4,%%ternary,2,*,+,=',
                'a = 5 + (b == 1 ? 3 : 4) * 2'
            ],
            'arithmetic expression with double ternary' => [
                'a,b,c,==,%%ternary-sentinel,1,b,d,==,%%ternary-sentinel,2,0,%%ternary,%%ternary,=',
                'a = b == c ? 1 : b == d ? 2 : 0'
            ],
            'arithmetic expression with paren and power' => ['a,3,4,**,5,**,=', 'a = (3**4)**5'],
            'double assignment' => ['a,b,7,=,=', 'a = b = 7'],
            'assignment with implicit multiplication and functions' => ['a,2,3,b,*,1,sin,*,3,_,b,+,*,=', 'a = 2 sin(3b)(-3+b)'],
            'bitwise, 1' => ['a,1,2,3,~,&,|,=', 'a = 1 | 2 & ~3'],
            'bitwise, 2' => ['a,1,2,3,~,&,5,~,^,|,=', 'a = 1 | 2 & ~3 ^ ~5'],
            'double assignment with expression' => ['a,b,7.3,1,floor,15,2,**,+,=,=', 'a = b = floor(7.3)+15**2'],
        ];
    }

    // FIXME: rewrite with data provider and all possible combinations
    public function test_invalid_parens() {
        $input = 'sin(2*x]';
        try {
            $parser = new parser($input);
        } catch (Exception $e) {
            self::assertStringEndsWith("mismatched parentheses, ']' is closing '(' from row 1 and column 4", $e->getMessage());
        }
    }

    public function test_basic_operations() {
        // FIXME: remove or create real use
        self::assertTrue(true);
        return;
        $input = 'a = 5 = 3';
        $input = 'a = b = 7 + 1';
        $input = 'a = 4; b = !a';
        $input = 'a = !a b 2';
        $input = 'a = arctan(1,2,3) + sin(2,3) + cos(1) + pi()';
        $input = 'a = arctan(sin(2,cos(pi())),4)';
        $input = 'a = "foo" 5';
        $input = 'a = 5 5';
        $input = 'a = 5 == 3 ? 1 + 2 : 2*(0 + 3)';
        $input = 'a = sin(2)';
        $input = 'a = a[1][2]; b = [1, 2, [3, "four", 5], 6, [7]]';
        $input = 'a = \sin(2)';
        $input = 'a = 1?a:';
        $input = '[1, ["x", "y"], [3, 4], 5, [[1,2]],6]';
        $input = 'a = sin(sin(4),cos(5))';
        $input = 'a = {1,-5,-3,2}';
        $input = 'a = [1:-5:-1]';
        $input = 'a = {1:-5:-1,9}';
        $input = 'a = 5:4';
        $input = 'a = {5:10:2,20,30:40:.5}';
        $input = 'a = [5:10:2,20,30:40:.5]';
        $input = '{3:5:0.5,10:15:0.5,1:3:4}';
        $input = '{[1,2], [3,4]}';
        $input = '5~3';
        $input = '{{1,2}}';
        $input = '[{1,2}]';
        $input = 'a = (b = 3) * 4; c = 5 * a(1 + b) * b(4 + a) + e;';
        $input = 'a = b[1]';
        $input = 'π + pi + pi() + pi(2+3)';
        $input = '"foo"[1]';
        $input = '3 4';

        //$parser = new parser($lexer->get_token_list(), true, ['b', 'c', 'd']);
        $parser = new parser($input);
        foreach ($parser->get_statements() as $statement) {
            $output = $statement;
            print_r($output);
            print_r(array_map(function($el) { return $el->value; }, $output->body));
        }
        //print_r($output);
    }

    public function test_parse_list() {
        // FIXME - TODO --> implement new test for lists
        //$input = '[1, 2, 3]';
        //$input = '[1, "a", 3]';
        //$input = '[1, ["x", "y"], 3]';
        //$input = '[[1,2]]';
        $input = 'a = [1, ["x", "y"], [3, 4], 5, [[1,2]],6]';
        $input = '-123.541e-13; 4';

        $parser = new answer_parser($input);
        // print_r($parser->statements);
    }

    public function provide_impossible_things(): array {
        return [
            ['1:99:unexpected token: ;', new token(token::END_OF_STATEMENT, ';', 1, 99)],
            ['1:99:unexpected token: foo', new token(token::IDENTIFIER, 'foo', 1, 99)],
            ['1:99:unexpected token: invalid', new token(-1, 'invalid', 1, 99)],
        ];
    }

    /**
     * @dataProvider provide_impossible_things
     */
    public function test_impossible_stuff($expected, $input): void {
        $parser = new parser('a = 3');
        $tokens = $parser->get_tokens();
        $tokens[] = $input;

        $e = null;
        try {
            shunting_yard::infix_to_rpn($tokens);
        } catch (Exception $e) {
            self::assertStringEndsWith($expected, $e->getMessage());
        }
        self::assertNotNull($e);
    }
}
