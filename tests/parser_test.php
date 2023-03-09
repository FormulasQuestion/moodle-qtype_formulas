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
use Exception;

class parser_test extends \advanced_testcase {

    /**
     * @dataProvider provide_simple_expressions
     */
    public function test_simple_expressions($expected, $input): void {
        $lexer = new lexer($input);
        $parser = new parser($lexer->get_tokens(), true);
        $statement = shunting_yard::infix_to_rpn($parser->get_statements()[0]);
        self::assertEquals($expected, implode(',', $statement));
    }

    /**
     * @dataProvider provide_expressions_with_functions
     */
    public function test_expressions_with_functions($expected, $input): void {
        $lexer = new lexer($input);
        $parser = new parser($lexer->get_tokens(), true);
        $statement = shunting_yard::infix_to_rpn($parser->get_statements()[0]);
        self::assertEquals($expected, implode(',', $statement));
    }

    /**
     * @dataProvider provide_ternary_expressions
     */
    public function test_ternary_expression($expected, $input): void {
        $lexer = new lexer($input);
        $parser = new parser($lexer->get_tokens(), true);
        $statement = shunting_yard::infix_to_rpn($parser->get_statements()[0]);
        self::assertEquals($expected, implode(',', $statement));
    }

    /**
     * @dataProvider provide_assignments
     */
    public function test_assignments($expected, $input): void {
        $lexer = new lexer($input);
        $parser = new parser($lexer->get_tokens());
        $statement = shunting_yard::infix_to_rpn($parser->get_statements()[0]);
        self::assertEquals($expected, implode(',', $statement));
    }

    public function provide_ternary_expressions(): array {
        return [
            'basic' => ['1,5,==,?,2,:,3,%%ternary', '1 == 5 ? 2 : 3'],
            'operations in condition, 1' => ['1,2,+,3,==,?,1,:,2,%%ternary', '1 + 2 == 3 ? 1 : 2'],
            'operations in condition, 2' => ['1,3,2,-,==,?,1,:,2,%%ternary', '1 == 3 - 2 ? 1 : 2'],
            'operations in true part' => ['1,?,1,2,+,:,2,%%ternary', '1 ? 1 + 2 : 2'],
            'operations in false part' => ['1,?,3,:,2,4,+,%%ternary', '1 ? 3 : 2 + 4'],
            'operations in all parts' => ['1,2,+,3,==,?,1,2,3,*,+,:,4,5,*,6,-,%%ternary', '1+2==3 ? 1+2*3 : 4*5-6'],
            'ternary in false part' => ['1,2,==,?,5,:,2,3,==,?,6,:,7,%%ternary,%%ternary', '1==2 ? 5 : 2==3 ? 6 : 7'],
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
            'function with array' => ['1,2,3,3,%%arraybuild,1,sum', 'sum([1,2,3])'],
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
            'arithmetic expression with ternary in parens' => ['a,5,b,1,==,?,3,:,4,%%ternary,2,*,+,=', 'a = 5 + (b == 1 ? 3 : 4) * 2'],
            'arithmetic expression with double ternary' => ['a,b,c,==,?,1,:,b,d,==,?,2,:,0,%%ternary,%%ternary,=', 'a = b == c ? 1 : b == d ? 2 : 0'],
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
        $input = 'a = (b = 3) * 4; c = 5 * a(1 + b) * b(4 + a) + e;';
        $input = 'a = "foo" 5';
        $input = 'a = 5 5';
        $input = 'a = 5 == 3 ? 1 + 2 : 2*(0 + 3)';
        $input = 'a = sin(2)';
        $input = 'a = a[1][2]; b = [1, 2, [3, "four", 5], 6, [7]]';
        $input = 'a = \sin(2)';
        $input = '[1, ["x", "y"], [3, 4], 5, [[1,2]],6]';
        $input = 'a = [1:-5:-1]';
        $input = 'a = sin(sin(4),cos(5))';

        $lexer = new lexer($input);
        //$parser = new parser($lexer->get_token_list(), true, ['b', 'c', 'd']);
        $parser = new parser($lexer->get_tokens());
        foreach ($parser->statements as $statement) {
            $output = shunting_yard::infix_to_rpn($statement);
            //print_r(array_map(function($el) { return $el->value; }, $output));
        }
        //print_r($output);
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
