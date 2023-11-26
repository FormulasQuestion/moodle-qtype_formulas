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

use \Exception;

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

    public function test_diff(): void {
        $parser = new parser('a = 4; b={1,2}; c = "a" + b; d = diff([a, 1], [5, 6])');
        $parser = new parser('x = {1:10}; y = {1:10}; d = diff(["x"], ["y"])');
        $statements = $parser->get_statements();
        $evaluator = new evaluator();
        $result = $evaluator->evaluate($statements);
        print_r(end($result));
    }

    public function test_algebra(): void {
        return;
        $parser = new parser('a = 7; b = {1:5}; 2*b');
        $statements = $parser->get_statements();
        $evaluator = new evaluator();
        $result = $evaluator->evaluate($statements);
        $evaluator->calculate_algebraic_expression_at_random_point('2*a');
        print_r($result);
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

    // TODO: reorder those tests later; some are unit tests for the functions and should go there
    public function provide_valid_assignments(): array {
        return [
            'one number' => [
                ['a' => new variable('a', 1, token::NUMBER)],
                'a = 1;'
            ],
            'two numbers' => [
                [
                    'a' => new variable('a', 1, token::NUMBER),
                    'b' => new variable('b', 4, token::NUMBER)
                ],
                'a = 1; b = 4;'
            ],
            'number with comment' => [
                ['a' => new variable('a', 1, token::NUMBER)],
                'a = 1; # This is a comment! So it will be skipped. '
            ],
            'one expression' => [
                ['c' => new variable('c', 4.14, token::NUMBER)],
                'c = cos(0)+3.14;'
            ],
            'one string with double quotes' => [
                ['d' => new variable('d', 'Hello!', token::STRING)],
                'd = "Hello!";'
            ],
            'one string with single quotes' => [
                ['d' => new variable('d', 'Hello!', token::STRING)],
                "d = 'Hello!';"
            ],
            'list of numbers' => [
                ['e' => new variable('e', [1, 2, 3, 4], token::LIST)],
                'e =[1,2,3,4];'
            ],
            'list of strings' => [
                ['f' => new variable('f', ['A', 'B', 'C'], token::LIST)],
                'f =["A", "B", "C"];'
            ],
            'composed expression with vars' => [
                [
                    'a' => new variable('a', 1, token::NUMBER),
                    'b' => new variable('b', 4, token::NUMBER),
                    'c' => new variable('c', 4, token::NUMBER),
                    'g' => new variable('g', [1, 47 , 2 , 2.718281828459, 16], token::LIST),
                ],
                'a = 1; b = 4; c = a*b; g= [1,2+45, cos(0)+1,exp(a),b*c];'
            ],
            'list with expressions + list element reference' => [
                [
                    'h' => new variable('h', [1, 5 , -0.7568024953079282, 5], token::LIST),
                    'j' => new variable('j', 5, token::NUMBER),
                ],
                'h = [1,2+3,sin(4),5]; j=h[1];'
            ],
            'assign list element' => [
                ['e' => new variable('e', 2, token::NUMBER)],
                'e = [1,2,3,4][1];'
            ],
            'assign to list element' => [
                [
                    'e' => new variable('e', [1, 2 , 111 , 4], token::LIST),
                ],
                'e = [1,2,3,4]; e[2]=111;'
            ],
            'assign to list element with variable as index' => [
                [
                    'a' => new variable('a', 1, token::NUMBER),
                    'e' => new variable('e', [1, 111 , 3 , 4], token::LIST),
                ],
                'e = [1,2,3,4]; a=1; e[a]=111;'
            ],
            'assign to list element with calculated variable as index' => [
                [
                    'a' => new variable('a', 0, token::NUMBER),
                    'e' => new variable('e', [111, 2 , 3 , 4], token::LIST),
                ],
                'e = [1,2,3,4]; a=1-1; e[a]=111;'
            ],
            'assign only element from list of length 1' => [
                ['g' => new variable('g', 3, token::NUMBER)],
                'g = [3][0];'
            ],
            'assign from array where element is itself element from a list' => [
                [
                    'a' => new variable('a', [7, 8, 9], token::LIST),
                    'g' => new variable('g', 8, token::NUMBER),
                ],
                'a = [7,8,9]; g = [a[1]][0];'
            ],
            'assign with ranges' => [
                [
                    'h' => new variable('h', [0, 1, 2, 3, 4, 5, 6, 7, 8, 9], token::LIST),
                    'k' => new variable('k', [4, 5, 6, 7], token::LIST),
                    'm' => new variable('m', [-20, -18.5, -17, -15.5, -14, -12.5, -11], token::LIST),
                ],
                'h = [0:10]; k=[4:8:1]; m=[-20:-10:1.5];'
            ],
            'assign lists and composed expressions' => [
                [
                    'a' => new variable('a', [1, 2, 3], token::LIST),
                    's' => new variable('s', [2, 0, 1], token::LIST),
                    'n' => new variable('n', [9, 3, 54], token::LIST),
                ],
                'a = [1,2,3]; s=[2,0,1]; n=[3*a[s[0]], 3*a[s[1]], 3*a[s[2]]*9];'
            ],
            'assign with exponentiation' => [
                ['a' => new variable('a', 6561, token::NUMBER)],
                'a=3**8;'
            ],
            'assign with fill()' => [
                [
                    'a' => new variable('a', 4, token::NUMBER),
                    'A' => new variable('A', [0, 0], token::LIST),
                    'B' => new variable('B', ['Hello', 'Hello', 'Hello'], token::LIST),
                    'C' => new variable('C', [4, 4, 4, 4], token::LIST),
                ],
                'a=4; A = fill(2,0); B= fill ( 3,"Hello"); C=fill(a,4);'
            ],
            'assign with indirect fill()' => [
                [
                    'a' => new variable('a', [1, 2, 3, 4], token::LIST),
                    'b' => new variable('b', 4, token::NUMBER),
                    'c' => new variable('c', ['rr', 'rr', 'rr', 'rr'], token::LIST),
                ],
                'a=[1,2,3,4]; b=len(a); c=fill(len(a),"rr")'
            ],
            'assignment with sort() (numbers)' => [
                [
                    's' => new variable('s', [2, 3, 5, 7, 11], token::LIST),
                ],
                's=sort([7,5,3,11,2]);'
            ],
            'assignment with sort() (strings)' => [
                [
                    's' => new variable('s', ['A1', 'A2', 'A10', 'A100'], token::LIST),
                ],
                's=sort(["A1","A10","A2","A100"]);'
            ],
            'assignment with sort() (two args)' => [
                [
                    's' => new variable('s', [2, 3, 1], token::LIST),
                ],
                's=sort([1,2,3], ["A10","A1","A2"]);'
            ],
            'assignment with sort() (numbers again)' => [
                [
                    's' => new variable('s', [1, 3, 5, 10], token::LIST),
                ],
                's=sort([1,10,5,3]);'
            ],
            'assignment with sort() (numeric strings and letters)' => [
                [
                    's' => new variable('s', ['1', '2', '3', '4', 'A', 'B', 'C', 'a', 'b', 'c'], token::LIST),
                ],
                's=sort(["4","3","A","a","B","2","1","b","c","C"]);'
            ],
            'assignment with sort() (letters)' => [
                [
                    's' => new variable('s', ['A', 'B', 'B', 'C'], token::LIST),
                ],
                's=sort(["B","C","A","B"]);'
            ],
            'assignment with sort() (numeric strings and letters again)' => [
                [
                    's' => new variable('s', ['0', '1', '2', '3', 'A', 'B', 'C', 'a', 'b', 'c'], token::LIST),
                ],
                's=sort(["B","3","1","0","A","C","c","b","2","a"]);'
            ],
            'assignment with sort() (strings)' => [
                [
                    's' => new variable('s', ['A1', 'A2', 'B'], token::LIST),
                ],
                's=sort(["B","A2","A1"]);'
            ],
            'assignment with sort() (strings, two params)' => [
                [
                    's' => new variable('s', ['B', 'A', 'C'], token::LIST),
                ],
                's=sort(["B","C","A"],[0,2,1]);'
            ],
            'assignment with sort() (strings, two params again)' => [
                [
                    's' => new variable('s', ['A1', 'B', 'A2'], token::LIST),
                ],
                's=sort(["B","A2","A1"],[2,4,1]);'
            ],
            'assignment with sort() (both arguments are strings)' => [
                [
                    's' => new variable('s', ['C', 'A', 'B'], token::LIST),
                ],
                's=sort(["A","B","C"],["A2","A10","A1"]);'
            ],
            'assignment with sort() (positive and negative numbers)' => [
                [
                    's' => new variable('s', [-4, -3, -2, -1, 0, 1, 2, 3, 4, 5], token::LIST),
                ],
                's=sort([-3,-2,4,2,3,1,0,-1,-4,5]);'
            ],
            'assignment with sort() (positive and negative numbers as strings)' => [
                [
                    's' => new variable('s', ['-3', '-2', '-1', '0', '1', '2', '3', 'A', 'B', 'a', 'b'], token::LIST),
                ],
                's=sort(["-3","-2","B","2","3","1","0","-1","b","a","A"]);'
            ],
            'assignment with sublist()' => [
                [
                    's' => new variable('s', ['B', 'D'], token::LIST),
                ],
                's=sublist(["A","B","C","D"],[1,3]);'
            ],
            'assignment with sublist(), same element twice' => [
                [
                    's' => new variable('s', ['A', 'A', 'C', 'D'], token::LIST),
                ],
                's=sublist(["A","B","C","D"],[0,0,2,3]);'
            ],
            'assignment with inv()' => [
                [
                    's' => new variable('s', [1, 3, 0, 2], token::LIST),
                ],
                's=inv([2,0,3,1]);'
            ],
            'assignment with inv(inv())' => [
                [
                    's' => new variable('s', [2, 0, 3, 1], token::LIST),
                ],
                's=inv(inv([2,0,3,1]));'
            ],
            'assignment with sublist() and inv()' => [
                [
                    'A' => new variable('A', ['A', 'B', 'C', 'D'], token::LIST),
                    'B' => new variable('B', [2, 0, 3, 1], token::LIST),
                    's' => new variable('s', ['A', 'B', 'C', 'D'], token::LIST),
                ],
                'A=["A","B","C","D"]; B=[2,0,3,1]; s=sublist(sublist(A,B),inv(B));'
            ],
            'assignment with map() and "exp"' => [
                [
                    'a' => new variable('a', [1, 2, 3], token::LIST),
                    'A' => new variable('A', [2.718281828459, 7.3890560989307, 20.085536923188], token::LIST),
                ],
                'a=[1,2,3]; A=map("exp",a);'
            ],
            'assignment with map() and "+" with constant' => [
                [
                    'a' => new variable('a', [1, 2, 3], token::LIST),
                    'A' => new variable('A', [3.3, 4.3, 5.3], token::LIST),
                ],
                'a=[1,2,3]; A=map("+",a,2.3);'
            ],
            'assignment with map() and "+" with two arrays' => [
                [
                    'a' => new variable('a', [1, 2, 3], token::LIST),
                    'b' => new variable('b', [4, 5, 6], token::LIST),
                    'A' => new variable('A', [5, 7, 9], token::LIST),
                ],
                'a=[1,2,3]; b=[4,5,6]; A=map("+",a,b);'
            ],
            'assignment with map() and "pow" with two arrays' => [
                [
                    'a' => new variable('a', [1, 2, 3], token::LIST),
                    'b' => new variable('b', [4, 5, 6], token::LIST),
                    'A' => new variable('A', [1, 32, 729], token::LIST),
                ],
                'a=[1,2,3]; b=[4,5,6]; A=map("pow",a,b);'
            ],
            'assignment with sum()' => [
                [
                    'r' => new variable('r', 15, token::NUMBER),
                ],
                'r=sum([4,5,6]);'
            ],
            'assignment with sum(), fill() and operations' => [
                [
                    'r' => new variable('r', -4, token::NUMBER),
                ],
                'r=3+sum(fill(10,-1))+3;'
            ],
            'assignment with concat() and lists of numbers' => [
                [
                    's' => new variable('s', [1, 2, 3, 4, 5, 6, 7, 8], token::LIST),
                ],
                's=concat([1,2,3], [4,5,6], [7,8]);'
            ],
            'assignment with concat() and lists of strings' => [
                [
                    's' => new variable('s', ['A', 'B', 'X', 'Y', 'Z', 'Hello'], token::LIST),
                ],
                's=concat(["A","B"],["X","Y","Z"],["Hello"]);'
            ],
            'assignment with join() and list of numbers' => [
                [
                    's' => new variable('s', '1~2~3', token::STRING),
                ],
                's=join("~", [1,2,3]);'
            ],
            'assignment with str()' => [
                [
                    's' => new variable('s', '45', token::STRING),
                ],
                's=str(45);'
            ],
            'assignment with nested join() and list' => [
                [
                    'a' => new variable('a', [4, 5], token::LIST),
                    's' => new variable('s', 'A,B,1,5,3,4+5+?,9', token::STRING),
                ],
                'a=[4,5]; s = join(",","A","B", [ 1 , a  [1]], 3, [join("+",a,"?"),"9"]);'
            ],
            'assignment with references and sum() containing a range' => [
                [
                    'A' => new variable('A', 1, token::NUMBER),
                    'Z' => new variable('Z', 4, token::NUMBER),
                    'Y' => new variable('Y', 'Hello!', token::STRING),
                    'X' => new variable('X', 31, token::NUMBER),
                ],
                'A = 1; Z = A + 3; Y = "Hello!"; X = sum([4:12:2]) + 3;'
            ],
            'implicit assignment via empty for loop index' => [
                [
                    'i' => new variable('i', 3, token::NUMBER),
                ],
                'for(i:[1,2,3]){ };'
            ],
            'implicit assignment via for loop index, other input format' => [
                [
                    'i' => new variable('i', 3, token::NUMBER),
                ],
                'for ( i : [1,2,3] ) {};'
            ],
            'assignment involving for loop with single statement and list from variable' => [
                [
                    'z' => new variable('z', 6, token::NUMBER),
                    'i' => new variable('i', 3, token::NUMBER),
                    'A' => new variable('A', [1, 2, 3], token::LIST),
                ],
                'z = 0; A=[1,2,3]; for(i:A) z=z+i;'
            ],
            'assignment involving for loop with single statement in braces' => [
                [
                    'z' => new variable('z', 10, token::NUMBER),
                    'i' => new variable('i', 4, token::NUMBER),
                ],
                'z = 0; for(i: [0:5]){z = z + i;}'
            ],
            'assignment involving for loop iterating over list of strings' => [
                [
                    's' => new variable('s', 'ABC', token::STRING),
                    'i' => new variable('i', 'C', token::STRING),
                ],
                's = ""; for(i: ["A","B","C"]) { s=join("",s,[i]); }'
            ],
            'assignment involving nested for loops' => [
                [
                    'z' => new variable('z', 30, token::NUMBER),
                    'i' => new variable('i', 4, token::NUMBER),
                    'j' => new variable('j', 2, token::NUMBER),
                ],
                'z = 0; for(i: [0:5]) for(j: [0:3]) z=z+i;'
            ],
            'assignment involving nested for loops' => [
                [
                    's' => new variable('s', [0], token::LIST),
                ],
                's=diff([3*3+3],[3*4]);'
            ],
        ];

    }

    /**
     * @dataProvider provide_valid_assignments
     */
    public function test_assignments($expected, $input): void {
        $parser = new parser($input);
        $statements = $parser->get_statements();
        $evaluator = new evaluator();
        $evaluator->evaluate($statements);

        foreach ($expected as $key => $variable) {
            self::assertArrayHasKey($key, $evaluator->variables);
            $stored = $evaluator->variables[$key];
            self::assertEquals($variable->name, $stored->name);
            self::assertEquals($variable->type, $stored->type);
            // If the value is a list, its elements are tokens. We will only compare the
            // token values to the expected values. For scalar variables, we can directly
            // compare the values.
            if ($stored->type === token::LIST) {
                foreach ($stored->value as $i => $token) {
                    self::assertEqualsWithDelta($variable->value[$i], $token->value, 1e-8);
                }
            } else {
                self::assertEqualsWithDelta($variable->value, $stored->value, 1e-8);
            }
        }
    }

    public function provide_invalid_assignments(): array {
        return [
            'missing operator between numbers' => [
                '1:5:syntax error: did you forget to put an operator?',
                'a=3 6;'
            ],
            'unknown char in expression' => [
                "1:4:unexpected input: '«'",
                'a=3«6;'
            ],
            'not subscriptable' => [
                'Trying to access array offset on value of type float',
                'f=1; g=f[1];'
            ],
            'assignment of empty list' => [
                '', // FIXME: put this to valid assignments, it is no error anymore.
                'e=[];'
            ],
            'invalid index: array' => [
                'evaluation error: only one index supported when accessing array elements',
                'e=[1,2,3][4,5];'
            ],
            'invalid index: array (indirect)' => [
                'evaluation error: only one index supported when accessing array elements',
                'e=[1,2,3]; f=e[4,5]'
            ],
            'multiply array with number' => [
                '1:16:evaluation error: numeric value expected, got list',
                'e=[1,2,3,4]; f=e*2;'
            ],
            'multiply array with number' => [
                '', // FIXME: put this to valid assignments, it is no error anymore.
                'e=[0:10,"k"];'
            ],
            'multiply array with number' => [
                '1:18:evaluation error: only one index supported when accessing array elements',
                'e=[1,2,3][1][4,5,6][2];'
            ],
            'fill with count == 0' => [
                '1:3:evaluation error: fill() expects the first argument to be a positive integer',
                'c=fill(0,"rr")'
            ],
            'fill with count == 10000' => [
                '', // FIXME: this is not an error anymore
                'c=fill(10000,"rr")'
            ],
            'closing parenthesis when not opened' => [
                "1:7:unbalanced parentheses, stray ')' found",
                's=fill);'
            ],
            'opening parenthesis not closed' => [
                "1:7:unbalanced parenthesis, '(' is never closed",
                's=fill(10,"rr";'
            ],
            'xxx' => [
                '', // FIXME: move to valid tests, no error anymore
                'a=[1,2,3,4]; c=fill(len(a)+1,"rr")'
            ],
            'invalid invocation of concat(), number' => [
                "1:3:evaluation error: concat() expects its arguments to be lists, found '0'",
                's=concat(0, [1,2,3], [5,6], 100);'
            ],
            'invalid invocation of concat()' => [
                '', // FIXME: no error anymore, because lists can contain different types
                's=concat([1,2,3], ["A","B"]);'
            ],
            'invalid for loop: no variable' => [
                '1:12:syntax error: identifier expected',
                'z = 0; for(: [0:5]) z=z+i;'
            ],
            'invalid for loop: no list' => [
                '1:14:syntax error: [ or variable name expected',
                'z = 0; for(i:) z=z+i;'
            ],
            'invalid for loop: no statement or brace' => [
                'syntax error: { or statement expected',
                'z = 0; for(i: [0:5]) '
            ],
            'invalid for loop: missing colon in nested loop' => [
                '1:28:syntax error: : expected',
                'z = 0; for(i: [0:5]) for(j [0:3]) z=z+i;'
            ],
            'invalid for loop: missing colon in nested loop' => [
                '', // FIXME: this is no error anymore
                'z = 0; for(i: [0:5]) z=z+i; b=[1,"b"];'
            ],
            'invalid invocation of diff(), mismatching lengths' => [
                '1:3:evaluation error: diff() expects two lists of the same size',
                's=diff([3*3+3,0],[3*4]);'
            ],
            'algebraic variable with strings instead of numbers' => [
                '', // FIXME: this is no error anymore; should it be?
                'x = {"A", "B"};'
            ],


        ];

    }


    /**
     * @dataProvider provide_invalid_assignments
     */
    public function test_invalid_stuff($expected, $input): void {
        $error = '';
        try {
            $parser = new parser($input);
            $statements = $parser->get_statements();
            $evaluator = new evaluator();
            $evaluator->evaluate($statements);
        } catch (Exception $e) {
            $error = $e->getMessage();
        }

        self::assertStringEndsWith($expected, $error);
    }

    /**
     * @dataProvider provide_general_expressions
     */
    public function test_general_expressions($expected, $input): void {
        $parser = new parser($input);
        $statement = $parser->get_statements()[0];
        $evaluator = new evaluator();
        $result = $evaluator->evaluate($statement);

        $this->assertEqualsWithDelta($expected, $result->value, 1e-8);
    }

    public function provide_general_expressions(): array {
        return [
            [-0.35473297204849, 'sin(4) + exp(cos(4+5))'],

        ];
    }

    /**
     * @dataProvider provide_numbers_and_units
     */
    public function test_unit_split($expected, $input): void {
        $parser = new answer_parser($input);
        $index = $parser->find_start_of_units();
        $number = substr($input, 0, $index);
        $unit = substr($input, $index);

        self::assertEquals($expected[0], trim($number));
        self::assertEquals($expected[1], $unit);
    }


    public function provide_numbers_and_units(): array {
        return [
            'missing unit' => [['123', ''], '123'],
            'missing number' => [['', 'm/s'], 'm/s'],
            'length 1' => [['100', 'm'], '100 m'],
            'length 1' => [['100', 'cm'], '100cm'],
            'length 2' => [['1.05', 'mm'], '1.05 mm'],
            'length 3' => [['-1.3', 'nm'], '-1.3 nm'],
            'area' => [['-7.5e-3', 'm^2'], '-7.5e-3 m^2', ],
            'area' => [['6241509.47e6', 'MeV'], '6241509.47e6 MeV', ],
            'speed' => [['1', 'km/s'], '1 km/s'],
            'combination 1' => [['1', 'm g/us'], '1 m g/us'],
            'combination 2' => [['1', 'kPa s^-2'], '1 kPa s^-2'],
            'combination 3' => [['1', 'm kg s^-2'], '1 m kg s^-2'],
            'numerical' => [['12 + 3 * 4/8', 'm^2'], '12 + 3 * 4/8 m^2'],
            'numerical formula' => [['12 * sqrt(3)', 'kg/s'], '12 * sqrt(3) kg/s'],

            'old unit tests, 1' => [['.3', ''], '.3'],
            'old unit tests, 2' => [['3.1', ''], '3.1'],
            'old unit tests, 3' => [['3.1e-10', ''], '3.1e-10'],
            'old unit tests, 4' => [['3', 'm'], '3m'],
            'old unit tests, 5' => [['3', 'kg m/s'], '3kg m/s'],
            'old unit tests, 6' => [['3.', 'm/s'], '3.m/s'],
            'old unit tests, 7' => [['3.e-10', 'm/s'], '3.e-10m/s'],
            'old unit tests, 8' => [['- 3', 'm/s'], '- 3m/s'],
            'old unit tests, 9' => [['3', 'e10 m/s'], '3 e10 m/s'],
            'old unit tests, 10' => [['3', 'e 10 m/s'], '3e 10 m/s'],
            'old unit tests, 11' => [['3e8', 'e8 m/s'], '3e8e8 m/s'],
            'old unit tests, 12' => [['3+10*4', 'm/s'], '3+10*4 m/s'],
            'old unit tests, 13' => [['3+10^4', 'm/s'], '3+10^4 m/s'],
            'old unit tests, 14' => [['sin(3)', 'm/s'], 'sin(3) m/s'],
            'old unit tests, 15' => [['3+exp(4)', 'm/s'], '3+exp(4) m/s'],
            'old unit tests, 16' => [['3*4*5', 'm/s'], '3*4*5 m/s'],

            // FIXME: The following is invalid, because 3 4 5 is not a number
            // 'old unit tests, 17' => [['3 4 5 ', 'm/s'], '3 4 5 m/s'],
            'old unit tests, 18' => [['', 'm/s'], 'm/s'],

            // FIXME: The following is invalid, because 3+4 5+10^4 is not a valid numerical expression
            // 'old unit tests, 19' => [['3+4 5+10^4', 'kg m/s'], '3+4 5+10^4kg m/s'],

            'old unit tests, 20' => [['sin(3)', 'kg m/s'], 'sin(3)kg m/s'],
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

    public function test_pick() {
        $testcases = [
            ['pick(3,[0,1,2,3,4,5]);', 3],
            ['pick(3.9,[0,1,2,3,4,5]);', 3],
            ['pick(10,[0,1,2,3,4,5]);', 0],
            ['pick(10.9,[0,1,2,3,4,5]);', 0],
            ['pick(3,[0,1,2,3,4,5]);', 3],
            ['pick(3.9,0,1,2,3,4,5);', 3],
            ['pick(10,0,1,2,3,4,5);', 0],
            ['pick(3,["A","B","C","D","E","F"]);', 'D'],
            ['pick(3.9,["A","B","C","D","E","F"]);', 'D'],
            ['pick(10,["A","B","C","D","E","F"]);', 'A'],
            ['pick(3,"A","B","C","D","E","F");', 'D'],
            ['pick(3.9,"A","B","C","D","E","F");', 'D'],
            ['pick(10,"A","B","C","D","E","F");', 'A'],
            ['pick(10.9,"A","B","C","D","E","F");', 'A'],
            ['pick(3,[0,0],[1,1],[2,2],[3,3],[4,4],[5,5]);', [3, 3]],
            ['pick(3.9,[0,0],[1,1],[2,2],[3,3],[4,4],[5,5]);', [3, 3]],
            ['pick(10,[0,0],[1,1],[2,2],[3,3],[4,4],[5,5]);', [0, 0]],
            ['pick(10.9,[0,0],[1,1],[2,2],[3,3],[4,4],[5,5]);', [0, 0]],
            ['pick(3,["A","A"],["B","B"],["C","C"],["D","D"],["E","E"],["F","F"]);', ['D', 'D']],
            ['pick(3.9,["A","A"],["B","B"],["C","C"],["D","D"],["E","E"],["F","F"]);', ['D', 'D']],
            ['pick(10,["A","A"],["B","B"],["C","C"],["D","D"],["E","E"],["F","F"]);', ['A', 'A']],
            ['pick(10.9,["A","A"],["B","B"],["C","C"],["D","D"],["E","E"],["F","F"]);', ['A', 'A']],
        ];

        foreach ($testcases as $case) {
            $parser = new parser($case[0]);
            $statements = $parser->get_statements();
            $evaluator = new evaluator();
            $result = $evaluator->evaluate($statements);
            $value = end($result)->value;
            if (is_array($value)) {
                $value = array_map(function ($el) { return $el->value; }, $value);
            }
            self::assertEqualsWithDelta($case[1], $value, 1e-12);
        }
    }

    public function test_sigfig() {
        $testcases = [
            ['sigfig(.012345, 3)', '0.0123'],
            ['sigfig(.012345, 4)', '0.01235'],
            ['sigfig(.012345, 6)', '0.0123450'],
            ['sigfig(-.012345, 3)', '-0.0123'],
            ['sigfig(-.012345, 4)', '-0.01235'],
            ['sigfig(-.012345, 6)', '-0.0123450'],
            ['sigfig(123.45, 2)', '120'],
            ['sigfig(123.45, 4)', '123.5'],
            ['sigfig(123.45, 6)', '123.450'],
            ['sigfig(-123.45, 2)', '-120'],
            ['sigfig(-123.45, 4)', '-123.5'],
            ['sigfig(-123.45, 6)', '-123.450'],
            ['sigfig(.005, 1)', '0.005'],
            ['sigfig(.005, 2)', '0.0050'],
            ['sigfig(.005, 3)', '0.00500'],
            ['sigfig(-.005, 1)', '-0.005'],
            ['sigfig(-.005, 2)', '-0.0050'],
            ['sigfig(-.005, 3)', '-0.00500'],
        ];

        foreach ($testcases as $case) {
            $parser = new parser($case[0]);
            $statements = $parser->get_statements();
            $evaluator = new evaluator();
            $result = $evaluator->evaluate($statements);
            //var_dump(end($result));
            $value = end($result)->value;
            self::assertEqualsWithDelta($case[1], $value, 1e-12);
        }
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
        $input = 'a = [[1,2],[3,4],[5,6]]; a[1][0];';
        $input = 'a = [[1,2],[2,3]]; a[1] = 9; a[1]';
        $input = 'a = "foo"; a[1] = "x"';
        $input = 'a = [1,2]; a';
        $input = '_err = 1';
        $input = 'p=pick(3.9,[0,1,2,3,4,5]);';
        $input = 'fqversionnumber()';
        $input = 'join("x", 8, 7, [5,9])';
        $input = 'sum([1,2,3, "4", [1]])';
        $input = 'fill("3", "a")';
        $input = 'sort([3,12,5], [3,5,0])';
        $input = 'sublist(["A","B","C","D"],[1,3])';
        $input = 'concat([1,2,3], [4,5,6], [[1,2]], [7,8])';
        $input = 'poly("x", -5)';
        $input = 'shuffle([1,2,3,[4,5,6]])';
        $input = 'sin = 2; \sin(x)';
        $input = 'a = shuffle([1,2,3])';
        $input = 'inv([2, 7, 4, 9, 8, 3, 5, 0, 6, 1])';
        $input = 'inv([0,1,2,3,4])';
        $input = 'sort([1,-2,-3,2,"A","B"],["A","1","-1","-2","20","-5"])';
        $input = 'sort([1,-2,-3,2,"A","B"])';
        $input = 'sort([-20,3,1,5,2,4,-1,-4,-20,0])';
        $input = 'map("ncr", 10, [1,2,3])';
        $input = 'map("map", "-", [[1,2],[3,4]])';
        $input = 'a = 2; sin(a)';
        $input = 'fact(6)';
        $input = 'a = {1,2,3}; b=shuffle([4,5,6,8,9,10]);';
        $input = 'a = [1,2]; a';
        $input = 'a = 1; [a,2]; a';
        $input = 'a = 5; fill((a-1)/2, "a")';
        $input = 'wrong = 0; yes = "yes"; no = "no"; (wrong ? yes : no)';
        $input = 'a = 5; x={1:10};';
        //$input = "a = [1,2,3];\nb = 1 \n     + 3\n# comment\n     + a";

        //$parser = new random_parser($input);
        $parser = new parser($input);
        $statements = $parser->get_statements();
        $evaluator = new evaluator();
        $result = $evaluator->evaluate($statements);
        print($evaluator->substitute_variables_in_algebraic_formula("a*x^2"));
        //print_r($result);
        return;
        //print("how many? " . $evaluator->get_number_of_variants() . "\n");
        $evaluator->instantiate_random_variables();
        //print_r($result);
        var_dump(end($result));
        //die($evaluator->export_randomvars_for_step_data());
        $context = $evaluator->export_variable_context();
        print_r($context);
        return;
        $evaluator = new evaluator();
        //$context = 'a:2:{s:1:"a";O:23:"qtype_formulas\variable":3:{s:4:"name";s:1:"a";s:4:"type";i:3;s:5:"value";d:3;}s:1:"b";O:23:"qtype_formulas\variable":3:{s:4:"name";s:1:"b";s:4:"type";i:3;s:5:"value";d:6;}}';
        //$evaluator->import_variable_context($context);
        print_r($result);
        var_dump($evaluator->variables);
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
        $input = '2^3';
        $input = '1.5e3 m^2';

        $parser = new answer_parser($input);
        print('unit starts at index: ' . $parser->find_start_of_units());
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
