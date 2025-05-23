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

namespace qtype_formulas;

use Exception;
use Generator;
use qtype_formulas\local\parser;
use qtype_formulas\local\shunting_yard;
use qtype_formulas\local\token;

/**
 * Unit tests for the parser class.
 *
 * @package    qtype_formulas
 * @category   test
 * @copyright  2022 Philipp Imhof
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @covers \qtype_formulas\local\parser
 * @covers \qtype_formulas\local\shunting_yard
 */
final class parser_test extends \advanced_testcase {

    public function test_has_token_in_tokenlist(): void {
        $input = 'sin = 5';
        $parser = new parser($input);
        // Checking without specifying the value, just looking for the type.
        self::assertTrue($parser->has_token_in_tokenlist(token::VARIABLE, null));
        self::assertFalse($parser->has_token_in_tokenlist(token::FUNCTION, null));
        self::assertFalse($parser->has_token_in_tokenlist(token::OPENING_PAREN, null));
        // Checking with specifying the content.
        self::assertTrue($parser->has_token_in_tokenlist(token::VARIABLE, 'sin'));
        self::assertFalse($parser->has_token_in_tokenlist(token::FUNCTION, 'sin'));
        self::assertFalse($parser->has_token_in_tokenlist(token::FUNCTION, 'cos'));

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
     * Test various assignments.
     *
     * @param string $expected string representation of expected sequence of tokens after parsing
     * @param string $input simulated input to be parsed
     * @dataProvider provide_assignments
     */
    public function test_assignments($expected, $input): void {
        $parser = new parser($input);
        $statement = $parser->get_statements()[0];
        self::assertEquals($expected, implode(',', $statement->body));
    }

    /**
     * Data provider.
     *
     * @return array
     */
    public static function provide_assignments(): array {
        return [
            'arithmetic expression' => ['a,1,2,3,*,+,=', 'a = 1+2*3'],
            'arithmetic expression with ternary in parens' => [
                'a,5,b,1,==,%%ternary-sentinel,3,4,%%ternary,2,*,+,=',
                'a = 5 + (b == 1 ? 3 : 4) * 2',
            ],
            'arithmetic expression with double ternary' => [
                'a,b,c,==,%%ternary-sentinel,1,b,d,==,%%ternary-sentinel,2,0,%%ternary,%%ternary,=',
                'a = b == c ? 1 : b == d ? 2 : 0',
            ],
            'arithmetic expression with paren and power' => ['a,3,4,**,5,**,=', 'a = (3**4)**5'],
            'double assignment' => ['a,b,7,=,=', 'a = b = 7'],
            'assignment with implicit multiplication and functions' => ['a,2,3,b,*,1,sin,*,3,_,b,+,*,=', 'a = 2 sin(3b)(-3+b)'],
            'bitwise, 1' => ['a,1,2,3,~,&,|,=', 'a = 1 | 2 & ~3'],
            'bitwise, 2' => ['a,1,2,3,~,&,5,~,^,|,=', 'a = 1 | 2 & ~3 ^ ~5'],
            'double assignment with expression' => ['a,b,7.3,1,floor,15,2,**,+,=,=', 'a = b = floor(7.3)+15**2'],
        ];
    }

    /**
     * Data provider.
     *
     * @return array
     */
    public static function provide_paren_expressions(): Generator {
        yield [
            "1:1:Unbalanced parenthesis, '(' is never closed.",
            '(',
        ];
        yield [
            "1:3:Unbalanced parenthesis, '[' is never closed.",
            'a=[',
        ];
        yield [
            "1:5:Unbalanced parenthesis, '{' is never closed.",
            'var={',
        ];
        yield [
            "1:1:Unbalanced parenthesis, stray ')' found.",
            ')',
        ];
        yield [
            "1:4:Unbalanced parenthesis, stray ']' found.",
            'a=5]',
        ];
        yield [
            "1:6:Unbalanced parenthesis, stray '}' found.",
            'var=2}',
        ];
        yield [
            "Mismatched parentheses, ']' is closing '(' from row 1 and column 4.",
            'sin(2*x]',
        ];
        yield [
            "Mismatched parentheses, '}' is closing '(' from row 1 and column 4.",
            'sin(2x}',
        ];
        yield [
            "Mismatched parentheses, ')' is closing '[' from row 1 and column 3.",
            'a=[1,2)',
        ];
        yield [
            "Mismatched parentheses, '}' is closing '[' from row 1 and column 3.",
            'a=[1,2}',
        ];
        yield [
            "Mismatched parentheses, ')' is closing '{' from row 1 and column 3.",
            'a={1,2)',
        ];
        yield [
            "Mismatched parentheses, ']' is closing '{' from row 1 and column 3.",
            'a={1,2]',
        ];
        yield [
            "1:1:Unbalanced parenthesis, '(' is never closed.",
            '(2*(3+4)',
        ];
        yield [
            "1:3:Unbalanced parenthesis, '[' is never closed.",
            'a=[[1,2],[3,4]',
        ];
        yield [
            "1:5:Unbalanced parenthesis, '{' is never closed.",
            'var={{1,2}',
        ];
        yield [
            "1:5:Mismatched parentheses, ')' is closing '[' from row 1 and column 3.",
            '(a[2)]',
        ];
        yield [
            "1:7:Mismatched parentheses, ']' is closing '(' from row 1 and column 5.",
            '[sin(2])',
        ];
    }

    /**
     * Test various expressions involving parentheses.
     *
     * @param string $expected expected error message
     * @param string $input simulated input
     * @dataProvider provide_paren_expressions
     */
    public function test_invalid_parens($expected, $input): void {
        $e = null;
        try {
            $parser = new parser($input);
        } catch (Exception $e) {
            self::assertStringEndsWith($expected, $e->getMessage());
        }
        self::assertNotNull($e);
    }

    /**
     * Test various expressions involving sets.
     *
     * @param string $expected string representation of expected sequence of tokens after parsing
     * @param string $input simulated input to be parsed
     * @dataProvider provide_sets
     */
    public function test_sets($expected, $input): void {
        $parser = new parser($input);
        $statement = $parser->get_statements()[0];
        self::assertEquals($expected, implode(',', $statement->body));
    }

    /**
     * Data provider.
     *
     * @return array
     */
    public static function provide_sets(): array {
        return [
            'basic' => ['{,1,2,3,4,5,%%setbuild', '{1,2,3,4,5}'],
            'range without step' => ['{,1,10,2,%%rangebuild,%%setbuild', '{1:10}'],
            'range with step' => ['{,1,10,0.5,3,%%rangebuild,%%setbuild', '{1:10:0.5}'],
            'ranges and elements' => [
                '{,1,5,6,0.1,3,%%rangebuild,100,200,300,2,%%rangebuild,5,%%setbuild',
                '{1,5:6:0.1,100,200:300,5}',
            ],
            'array in set' => [
                // phpcs:ignore moodle.Files.LineLength.TooLong
                '{,[,1,10,2,%%rangebuild,%%arraybuild,[,20,30,2,%%rangebuild,%%arraybuild,[,40,50,2,3,%%rangebuild,%%arraybuild,%%setbuild',
                '{[1:10],[20:30],[40:50:2]}',
            ],
            'multiple ranges' => [
                // phpcs:ignore moodle.Files.LineLength.TooLong
                '{,1,10,2,%%rangebuild,15,50,5,3,%%rangebuild,60,70,0.5,3,%%rangebuild,100,110,2,%%rangebuild,0,10,_,1,_,3,%%rangebuild,%%setbuild',
                '{1:10,15:50:5,60:70:0.5,100:110,0:-10:-1}',
            ],
            'range with step, negatives' => ['{,1,_,10,_,0.5,_,3,%%rangebuild,%%setbuild', '{-1:-10:-0.5}'],
            'range with step, composed expressions' => [
                '{,1,3,1,sqrt,+,10,5,1,sin,+,1,5,/,3,%%rangebuild,%%setbuild',
                '{1+sqrt(3):10+sin(5):1/5}',
            ],
        ];
    }

    /**
     * Data provider.
     *
     * @return array
     */
    public static function provide_impossible_things(): array {
        return [
            ['1:99:Unexpected token: ;', new token(token::END_OF_STATEMENT, ';', 1, 99)],
            ['1:99:Unexpected token: foo', new token(token::IDENTIFIER, 'foo', 1, 99)],
            ['1:99:Unexpected token: invalid', new token(-1, 'invalid', 1, 99)],
        ];
    }

    /**
     * Test dealing with things that cannot normally be seen.
     *
     * @param string $expected expected error message
     * @param token $input token, will be appended to some standard input
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
