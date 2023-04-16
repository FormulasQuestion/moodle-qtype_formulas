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
 * qtype_formulas evaluation tests
 *
 * @package    qtype_formulas
 * @category   test
 * @copyright  2022 Philipp Imhof
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace qtype_formulas;

class evaluator_test extends \advanced_testcase {

    /**
     * @dataProvider provide_expressions_with_functions
     * @dataProvider provide_simple_expressions
     * @dataProvider provide_ternary_expressions
     * @dataProvider provide_for_loops
     */
    public function test_expressions_with_numeric_result($expected, $input): void {
        $parser = new parser($input);
        $statements = $parser->get_statements();
        $evaluator = new evaluator();
        $result = $evaluator->evaluate($statements);
        self::assertEqualsWithDelta($expected, end($result)->value, 1e-12);
    }

    public function provide_for_loops(): array {
        return [
            'one statement' => [45, 'res = 0; for (i:[1:10]) res = res + i'],
            'one statement in braces, without semicolon' => [45, 'res = 0; for (i:[1:10]) {res = res + i}'],
            'one statement in braces, with semicolon' => [45, 'res = 0; for (i:[1:10]) {res = res + i;}'],
            'two statements' => [90, 'res = 0; for (i:[1:10]) {i = i * 2; res = res + i;}'],
            'nested without braces' => [810, 'res = 0; for (a:[1:10]) for (b:[1:10]) res = res + a + b'],
            'nested without braces inner' => [810, 'res = 0; for (a:[1:10]) for (b:[1:10]) { res = res + a + b }'],
            'nested with braces outer' => [810, 'res = 0; for (a:[1:10]) { for (b:[1:10]) res = res + a + b }'],
            'nested with braces' => [810, 'res = 0; for (a:[1:10]) { for (b:[1:10]) { res = res + a + b } }'],
            'one statement with variable range' => [10, 'a = 1; b = 5; res = 0; for (i:[a:b]) res = res + i'],
            'one statement with variable range and step' => [22, 'a = 1; b = 5; c = 0.5; res = 0; for (i:[a:b:c]) res = res + i'],
            'one statement with expression in range' => [22, 'a = 0.5; b = 10; c = 1/4; res = 0; for (i:[a*2:b/2:c+c]) res = res + i'],
        ];
    }

    /**
     * @dataProvider provide_assignments
     */
    public function test_assignments($expected, $input): void {
        // TODO
        $parser = new parser($input);
        $statement = $parser->get_statements()[0];
        self::assertEquals($expected, implode(',', $statement->body));
    }

    /**
     * @dataProvider provide_arrays
     */
    public function test_arrays($expected, $input): void {
        $parser = new parser($input);
        $statement = $parser->get_statements()[0];
        $evaluator = new evaluator();
        $result = $evaluator->evaluate($statement);
        // The test data can contain nested arrays. For easier testing, we need to extract
        // the values for elements and nested elements.
        $content = array_map(
            function ($el) {
                if (is_array($el->value)) {
                    return array_map(function ($nested) { return $nested->value; }, $el->value);
                } else {
                    return $el->value;
                }
            },
            $result->value
        );
        self::assertEqualsWithDelta($expected, $content, 1e-12);
    }

    /**
     * @dataProvider provide_sets
     */
    public function test_sets($expected, $input): void {
        // TODO
        $parser = new parser($input);
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
            'basic' => [[1, 2, 3, 4, 5], '[1,2,3,4,5]'],
            'range without step' => [[1, 2, 3, 4, 5, 6, 7, 8, 9], '[1:10]'],
            'reversed range without step' => [[10, 9, 8, 7, 6, 5, 4, 3, 2], '[10:1]'],
            'range with step' => [[1, 3, 5, 7, 9], '[1:10:2]'],
            'ranges and elements' => [
                [1, 5, 5.1, 5.2, 5.3, 5.4, 5.5, 5.6, 5.7, 5.8, 5.9, 100, 200, 225, 250, 275],
                '[1,5:5.95:0.1,100,200:300:25]'
            ],
            'nested' => [
                [[1, 2, 3, 4, 5], [20, 24, 28], [40, 42, 44, 46, 48]],
                '[[1:6],[20:30:4],[40:50:2]]'
            ],
            'multiple ranges' => [
                [1, 2, 3, 4, 15, 30, 45, 60, 60.5, 61, 61.5, 62, 62.5, 0, -1, -2, -3, -4],
                '[1:5,15:50:15,60:63:0.5,0:-5:-1]'
            ],
            'range with step, negatives' => [
                [-1, -1.5, -2, -2.5, -3, -3.5, -4, -4.5],
                '[-1:-5:-0.5]'],
            'elements are expressions' => [
                [3, 4.7449230967779314, 0.2],
                '[1+sqrt(4),5*sin(5/4),1/5]'
            ],
            'range with step, composed expressions' => [
                [3, 3.2, 3.4, 3.6, 3.8, 4, 4.2, 4.4, 4.6],
                '[1+sqrt(4):5*sin(5/4):1/5]'
            ],
        ];
    }

    public function provide_ternary_expressions(): array {
        return [
            'basic' => [3, '1 == 5 ? 2 : 3'],
            'operations in condition, 1' => [1, '1 + 2 == 3 ? 1 : 2'],
            'operations in condition, 2' => [1, '1 == 3 - 2 ? 1 : 2'],
            'operations in true part' => [3, '1 ? 1 + 2 : 2'],
            'operations in false part' => [3, '1 ? 3 : 2 + 4'],
            'operations in all parts' => [7, '1+2==3 ? 1+2*3 : 4*5-6'],
            'ternary in false part' => [7, '1==2 ? 5 : 2==3 ? 6 : 7'],
        ];
    }

    public function provide_expressions_with_functions(): array {
        return [
            'one argument, built-in' => [5, 'floor(5.3)'],
            'one argument, custom' => [5040, 'fact(7)'],
            'two arguments' => [252, 'ncr(10,5)'],
            'three arguments' => [16, 'modpow(2,100,17)'],
            //'several arguments' => ['-,a,b,c,4,join', 'join("-", a, b, c)'],
            'function in function' => [M_PI / 4, 'asin(sqrt(2)/2)'],
            'operation in function' => [-1.02, 'round((1+2*3-4**5)/1000,2)'],
            //'function with array' => [6, 'sum([1,2,3])'],
        ];
    }

    public function provide_simple_expressions(): array {
        return [
            'array access (valid)' => [5, '[1,5][1]'],
            'array access (valid)' => [1, '[1,5][0]'],
            'modulo' => [3, '1+2%3'],
            'left-associativity bitwise left shift' => [32, '1 << 2 << 3'],
            'left-associativity bitwise right shift' => [0, '1 >> 2 >> 3'],
            'left-associativity bitwise left/right shift' => [0, '1 << 2 >> 3'],
            'left-associativity bitwise right/left shift' => [0, '1 >> 2 << 3'],
            'left-associativity bitwise and' => [0, '1 & 2 & 3'],
            'left-associativity bitwise xor' => [0, '1 ^ 2 ^ 3'],
            'left-associativity bitwise or' => [3, '1 | 2 | 3'],
            'precedence among bitwise operators: and + xor, 1' => [3, '1 & 2 ^ 3'],
            'precedence among bitwise operators: and + xor, 2' => [3, '1 ^ 2 & 3'],
            'precedence among bitwise operators: and + or, 1' => [3, '1 & 2 | 3'],
            'precedence among bitwise operators: and + or, 2' => [3, '1 | 2 & 3'],
            'precedence among bitwise operators: xor + or, 1' => [3, '1 ^ 2 | 3'],
            'precedence among bitwise operators: xor + or, 2' => [1, '1 | 2 ^ 3'],
            'precedence among bitwise operators: all mixed, 1' => [7, '1 & 2 ^ 3 | 4'],
            'precedence among bitwise operators: all mixed, 2' => [3, '1 ^ 2 | 3 & 4'],
            'precedence among bitwise operators: all mixed, 3' => [7, '1 | 2 & 3 ^ 4'],
            'unary bitwise negation' => [-3, '~2'],
            'unary bitwise negation in a sum, 1' => [0, '3+~2'],
            'unary bitwise negation in a sum, 2' => [-7, '~3+~2'],
            'unary minus in multiplication' => [-2, '1*-2'],
            'unary minus in addition' => [-1, '1+-2'],
            'unary minus in parens' => [-6, '2*(-3)'],
            'multiplication before addition, 1' => [7, '1+2*3'],
            'multiplication before addition, 2' => [5, '1*2+3'],
            'implicit multiplication with parens' => [21, '(1+2)(3+4)'],
            'sum in parens' => [25, '5*(2+3)'],
            'power, with parens, 1' => [3486784401, '(3**4)**5'],
            'power, with parens, 2' => [43046721, '3**(4**2)'],
            'power, right-associative' => [43046721, '3**4**2'],
            'order of basic operations' => [-23.85, '1*2-3/4+5*6-7*8+9/10'],
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

    public function test_random_vars() {
        return;
        $input = 'a = shuffle([1,2,3])';
        $input = 'a = {1,-5,-3,2}';

        $parser = new parser($input);
        $statements = $parser->get_statements();
        $evaluator = new random_evaluator();
        //$context = 'a:2:{s:1:"a";O:23:"qtype_formulas\variable":3:{s:4:"name";s:1:"a";s:4:"type";i:3;s:5:"value";d:3;}s:1:"b";O:23:"qtype_formulas\variable":3:{s:4:"name";s:1:"b";s:4:"type";i:3;s:5:"value";d:6;}}';
        //$evaluator->import_variable_context($context);
        foreach ($statements as $st) {
            $result = $evaluator->evaluate($st);
            print_r($result);
        }
        // $context = $evaluator->export_variable_context();
        // print_r($context);
        $evaluator->instantiate_random_variables();
        return;
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
        $input = '"abc"[2]';
        $input = '0**0';
        $input = '[1,2,"3"][2]';
        $input = 'fqversionnumber()';
        $input = 'c=2*a + b';
        $input = 'a="foo"; b="foox"; a == b';
        $input = 'a = {1,2,3}; 2*a;';
        $input = 'a = [1,2,3]; "2"+a';
        $input = 'a = "fooo"; 2*a';
        $input = 'a = 2; b = 3; c = 4; d = a * b; d';

        $parser = new parser($input);
        $statements = $parser->get_statements();
        $evaluator = new evaluator();
        //$context = 'a:2:{s:1:"a";O:23:"qtype_formulas\variable":3:{s:4:"name";s:1:"a";s:4:"type";i:3;s:5:"value";d:3;}s:1:"b";O:23:"qtype_formulas\variable":3:{s:4:"name";s:1:"b";s:4:"type";i:3;s:5:"value";d:6;}}';
        //$evaluator->import_variable_context($context);
        $result = $evaluator->evaluate($statements);
        print_r($result);
        $context = $evaluator->export_variable_context();
        print_r($context);
        return;

        //$parser = new parser($lexer->get_token_list(), true, ['b', 'c', 'd']);
        $parser = new parser($input);
        foreach ($parser->statements as $statement) {
            $output = $statement;
            print_r($output);
            //print_r(array_map(function($el) { return $el->value; }, $output));
        }
        //print_r($output);
    }

    public function test_for_loop() {
        $input = 'b = 0; for (a:[1:23,5]) { x = {1,2}; b = b + a;}';

        $parser = new parser($input);
        $statements = $parser->get_statements();
        $evaluator = new evaluator();
        $result = $evaluator->evaluate($statements);
        print_r($result);
    }

    public function test_answer_expression() {
        return;
        $input = '2^3';

        $parser = new answer_parser($input);
        print_r($parser->statements);
    }

    public function test_parse_list() {
        //$input = '[1, 2, 3]';
        //$input = '[1, "a", 3]';
        //$input = '[1, ["x", "y"], 3]';
        //$input = '[[1,2]]';
        $input = 'a = [1, ["x", "y"], [3, 4], 5, [[1,2]],6]';

        $parser = new parser($input);
       // print_r($parser->statements);
    }
}
