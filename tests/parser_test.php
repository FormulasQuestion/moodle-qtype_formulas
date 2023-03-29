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
 * qtype_formulas parser tests
 *
 * @package    qtype_formulas
 * @category   test
 * @copyright  2022 Philipp Imhof
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace qtype_formulas;

class parser_test extends \advanced_testcase {
    /**
     * @dataProvider provide_simple_expressions
     */
    public function test_simple_expressions($expected, $input): void {
        $lexer = new lexer($input);
        $parser = new parser($lexer->get_tokens());
        $statement = $parser->get_statements()[0];
        self::assertEquals($expected, implode(',', $statement->body));
    }

    /**
     * @dataProvider provide_expressions_with_functions
     */
    public function test_expressions_with_functions($expected, $input): void {
        $lexer = new lexer($input);
        $parser = new parser($lexer->get_tokens());
        $statement = $parser->get_statements()[0];
        self::assertEquals($expected, implode(',', $statement->body));
    }

    /**
     * @dataProvider provide_ternary_expressions
     */
    public function test_ternary_expression($expected, $input): void {
        $lexer = new lexer($input);
        $parser = new parser($lexer->get_tokens());
        $statement = $parser->get_statements()[0];
        self::assertEquals($expected, implode(',', $statement->body));
    }

    /**
     * @dataProvider provide_assignments
     */
    public function test_assignments($expected, $input): void {
        $lexer = new lexer($input);
        $parser = new parser($lexer->get_tokens());
        $statement = $parser->get_statements()[0];
        self::assertEquals($expected, implode(',', $statement->body));
    }

    /**
     * @dataProvider provide_arrays
     */
    public function test_arrays($expected, $input): void {
        $lexer = new lexer($input);
        $parser = new parser($lexer->get_tokens());
        $statement = $parser->get_statements()[0];
        self::assertEquals($expected, implode(',', $statement->body));
    }

    /**
     * @dataProvider provide_sets
     */
    public function test_sets($expected, $input): void {
        $lexer = new lexer($input);
        $parser = new parser($lexer->get_tokens());
        $statement = $parser->get_statements()[0];
        self::assertEquals($expected, implode(',', $statement->body));
    }

    public function provide_sets(): array {
        return [
            'basic' => ['{,1,2,3,4,5,%%setbuild', '{1,2,3,4,5}'],
            'range without step' => ['{,1,10,2,%%rangebuild,%%setbuild', '{1:10}'],
            'range with step' => ['{,1,10,0.5,3,%%rangebuild,%%setbuild', '{1:10:0.5}'],
            'ranges and elements' => [
                '{,1,5,6,0.1,3,%%rangebuild,100,200,300,2,%%rangebuild,5,%%setbuild',
                '{1,5:6:0.1,100,200:300,5}'
            ],
            'array in set' => [
                '{,[,1,10,2,%%rangebuild,%%arraybuild,[,20,30,2,%%rangebuild,%%arraybuild,[,40,50,2,3,%%rangebuild,%%arraybuild,%%setbuild',
                '{[1:10],[20:30],[40:50:2]}'
            ],
            'multiple ranges' => ['{,1,10,2,%%rangebuild,15,50,5,3,%%rangebuild,60,70,0.5,3,%%rangebuild,100,110,2,%%rangebuild,0,10,_,1,_,3,%%rangebuild,%%setbuild', '{1:10,15:50:5,60:70:0.5,100:110,0:-10:-1}'],
            'range with step, negatives' => ['{,1,_,10,_,0.5,_,3,%%rangebuild,%%setbuild', '{-1:-10:-0.5}'],
            'range with step, composed expressions' => [
                '{,1,3,1,sqrt,+,10,5,1,sin,+,1,5,/,3,%%rangebuild,%%setbuild',
                '{1+sqrt(3):10+sin(5):1/5}'
            ],
        ];
    }
    public function provide_arrays(): array {
        return [
            'basic' => ['[,1,2,3,4,5,%%arraybuild', '[1,2,3,4,5]'],
            'range without step' => ['[,1,10,2,%%rangebuild,%%arraybuild', '[1:10]'],
            'range with step' => ['[,1,10,0.5,3,%%rangebuild,%%arraybuild', '[1:10:0.5]'],
            'ranges and elements' => [
                '[,1,5,6,0.1,3,%%rangebuild,100,200,300,2,%%rangebuild,5,%%arraybuild',
                '[1,5:6:0.1,100,200:300,5]'
            ],
            'nested' => [
                '[,[,1,10,2,%%rangebuild,%%arraybuild,[,20,30,2,%%rangebuild,%%arraybuild,[,40,50,2,3,%%rangebuild,%%arraybuild,%%arraybuild',
                '[[1:10],[20:30],[40:50:2]]'
            ],
            'multiple ranges' => [
                '[,1,10,2,%%rangebuild,15,50,5,3,%%rangebuild,60,70,0.5,3,%%rangebuild,100,110,2,%%rangebuild,0,10,_,1,_,3,%%rangebuild,%%arraybuild',
                '[1:10,15:50:5,60:70:0.5,100:110,0:-10:-1]'
            ],
            'range with step, negatives' => ['[,1,_,10,_,0.5,_,3,%%rangebuild,%%arraybuild', '[-1:-10:-0.5]'],
            'range with step, composed expressions' => [
                '[,1,3,1,sqrt,+,10,5,1,sin,+,1,5,/,3,%%rangebuild,%%arraybuild',
                '[1+sqrt(3):10+sin(5):1/5]'
            ],
        ];
    }

    public function provide_ternary_expressions(): array {
        return [
            'basic' => ['1,5,==,2,3,%%ternary', '1 == 5 ? 2 : 3'],
            'operations in condition, 1' => ['1,2,+,3,==,1,2,%%ternary', '1 + 2 == 3 ? 1 : 2'],
            'operations in condition, 2' => ['1,3,2,-,==,1,2,%%ternary', '1 == 3 - 2 ? 1 : 2'],
            'operations in true part' => ['1,1,2,+,2,%%ternary', '1 ? 1 + 2 : 2'],
            'operations in false part' => ['1,3,2,4,+,%%ternary', '1 ? 3 : 2 + 4'],
            'operations in all parts' => ['1,2,+,3,==,1,2,3,*,+,4,5,*,6,-,%%ternary', '1+2==3 ? 1+2*3 : 4*5-6'],
            'ternary in false part' => ['1,2,==,5,2,3,==,6,7,%%ternary,%%ternary', '1==2 ? 5 : 2==3 ? 6 : 7'],
        ];
    }

    public function provide_expressions_with_functions(): array {
        return [
            'one argument' => ['5.3,1,floor', 'floor(5.3)'],
            'two arguments' => ['10,5,2,ncr', 'ncr(10,5)'],
            'three arguments' => ['2,100,17,3,modpow', 'modpow(2,100,17)'],
            'several arguments' => ['-,a,b,c,4,join', 'join("-", a, b, c)'],
            'function in function' => ['2,1,sqrt,2,/,1,asin', 'asin(sqrt(2)/2)'],
            'operation in function' => ['1,2,3,*,+,4,5,**,-,3,2,round', 'round(1+2*3-4**5,3)'],
            'function with array' => ['[,1,2,3,%%arraybuild,1,sum', 'sum([1,2,3])'],
        ];
    }

    public function provide_simple_expressions(): array {
        return [
            'modulo' => ['1,2,3,%,+', '1+2%3'],
            'left-associativity bitwise left shift' => ['1,2,<<,3,<<', '1 << 2 << 3'],
            'left-associativity bitwise right shift' => ['1,2,>>,3,>>', '1 >> 2 >> 3'],
            'left-associativity bitwise left/right shift' => ['1,2,<<,3,>>', '1 << 2 >> 3'],
            'left-associativity bitwise right/left shift' => ['1,2,>>,3,<<', '1 >> 2 << 3'],
            'left-associativity bitwise and' => ['1,2,&,3,&', '1 & 2 & 3'],
            'left-associativity bitwise xor' => ['1,2,^,3,^', '1 ^ 2 ^ 3'],
            'left-associativity bitwise or' => ['1,2,|,3,|', '1 | 2 | 3'],
            'precedence among bitwise operators: and + xor, 1' => ['1,2,&,3,^', '1 & 2 ^ 3'],
            'precedence among bitwise operators: and + xor, 2' => ['1,2,3,&,^', '1 ^ 2 & 3'],
            'precedence among bitwise operators: and + or, 1' => ['1,2,&,3,|', '1 & 2 | 3'],
            'precedence among bitwise operators: and + or, 2' => ['1,2,3,&,|', '1 | 2 & 3'],
            'precedence among bitwise operators: xor + or, 1' => ['1,2,^,3,|', '1 ^ 2 | 3'],
            'precedence among bitwise operators: xor + or, 2' => ['1,2,3,^,|', '1 | 2 ^ 3'],
            'precedence among bitwise operators: all mixed, 1' => ['1,2,&,3,^,4,|', '1 & 2 ^ 3 | 4'],
            'precedence among bitwise operators: all mixed, 2' => ['1,2,^,3,4,&,|', '1 ^ 2 | 3 & 4'],
            'precedence among bitwise operators: all mixed, 3' => ['1,2,3,&,4,^,|', '1 | 2 & 3 ^ 4'],
            'unary bitwise negation' => ['2,~', '~2'],
            'unary bitwise negation in a sum, 1' => ['3,2,~,+', '3+~2'],
            'unary bitwise negation in a sum, 2' => ['3,~,2,~,+', '~3+~2'],
            'unary minus in multiplication' => ['1,2,_,*', '1*-2'],
            'unary minus in addition' => ['1,2,_,+', '1+-2'],
            'unary minus in parens' => ['2,3,_,*', '2*(-3)'],
            'multiplication before addition, 1' => ['1,2,3,*,+', '1+2*3'],
            'multiplication before addition, 2' => ['1,2,*,3,+', '1*2+3'],
            'implicit multiplication with parens' => ['1,2,+,3,4,+,*', '(1+2)(3+4)'],
            'sum in parens' => ['5,2,3,+,*', '5*(2+3)'],
            'power, with parens, 1' => ['3,4,**,5,**', '(3**4)**5'],
            'power, with parens, 2' => ['3,4,5,**,**', '3**(4**5)'],
            'power, right-associative' => ['3,4,5,**,**', '3**4**5'],
            'order of basic operations' => ['1,2,*,3,4,/,-,5,6,*,+,7,8,*,-,9,10,/,+', '1*2-3/4+5*6-7*8+9/10'],
        ];
    }

    public function provide_assignments(): array {
        return [
            'constant' => ['a,1,=', 'a = 1'],
            'arithmetic expression' => ['a,1,2,3,*,+,=', 'a = 1+2*3'],
            'arithmetic expression with ternary in parens' => [
                'a,5,b,1,==,3,4,%%ternary,2,*,+,=',
                'a = 5 + (b == 1 ? 3 : 4) * 2'
            ],
            'arithmetic expression with double ternary' => [
                'a,b,c,==,1,b,d,==,2,0,%%ternary,%%ternary,=',
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

    public function test_basic_operations() {
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
        $input = 'π + pi + pi()';
        $input = 'a = (b = 3) * 4; c = 5 * a(1 + b) * b(4 + a) + e;';
        $input = 'a = b[1]';

        $lexer = new lexer($input);
        //$parser = new parser($lexer->get_token_list(), true, ['b', 'c', 'd']);
        $parser = new parser($lexer->get_tokens());
        foreach ($parser->statements as $statement) {
            $output = $statement;
            //print_r($output);
            print_r(array_map(function($el) { return $el->value; }, $output->body));
        }
        //print_r($output);
    }

    public function test_for_loop() {
        $input = 'for (a:[1:23,5]) { a = 5; b = 3;}';

        $lexer = new lexer($input);
        $parser = new parser($lexer->get_tokens());
        print_r($parser->statements);
    }

    public function test_answer_expression() {
        $input = '2^3';

        $lexer = new lexer($input);
        $parser = new answer_parser($lexer->get_tokens());
        print_r($parser->statements);
    }

    public function test_parse_list() {
        //$input = '[1, 2, 3]';
        //$input = '[1, "a", 3]';
        //$input = '[1, ["x", "y"], 3]';
        //$input = '[[1,2]]';
        $input = 'a = [1, ["x", "y"], [3, 4], 5, [[1,2]],6]';

        $lexer = new lexer($input);
        $parser = new parser($lexer->get_tokens());
       // print_r($parser->statements);
    }
}
