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

use Exception;
use qtype_formulas;

// TODO: test with global vars depending on instantiated random vars
// TODO: test visibility and last-value property of loop's iterator variable
// TODO: test calculate_algebraic_expression with empty string

class evaluator_test extends \advanced_testcase {

    public function provide_invalid_diff(): array {
        return [
            ['the first argument of diff() must be a list', 'diff("", "");'],
            ['the second argument of diff() must be a list', 'diff([1,2,3], 1);'],
            ['diff() expects two lists of the same size', 'diff([1,2,3], [1,2]);'],
            ['when using diff(), the first list must contain only numbers or only strings', 'diff([[1,2]], [1]);'],
            ['diff(): type mismatch for element #1 (zero-indexed) of the first list', 'diff([1,"a"], [1,2]);'],
            ['diff(): type mismatch for element #1 (zero-indexed) of the first list', 'diff(["a",1], ["a","b"]);'],
            ['diff(): type mismatch for element #0 (zero-indexed) of the second list', 'diff([1,2], ["a",2]);'],
            ['diff(): type mismatch for element #1 (zero-indexed) of the second list', 'diff(["a","b"], ["a",2]);'],
            ['diff(): the third argument can only be used with lists of strings', 'diff([1,2,3], [4,5,6], 3);'],
        ];
    }

    /**
     * @dataProvider provide_expressions_with_functions
     * @dataProvider provide_simple_expressions
     * @dataProvider provide_ternary_expressions
     * @dataProvider provide_for_loops
     * @dataProvider provide_boolean_and_logical
     */
    public function test_expressions_with_numeric_result($expected, $input): void {
        $parser = new parser($input);
        $statements = $parser->get_statements();
        $evaluator = new evaluator();
        $result = $evaluator->evaluate($statements);
        self::assertEqualsWithDelta($expected, end($result)->value, 1e-12);
    }

    public function provide_boolean_and_logical(): array {
        return [
            [1, '(0 == 0) || (1 == 1)'],
            [1, '(1 == 1) || (0 == 1)'],
            [1, '(0 == 1) || (1 == 1)'],
            [0, '(0 == 1) || (1 == 2)'],
            [1, '(0 == 0) && (1 == 1)'],
            [0, '(1 == 1) && (0 == 1)'],
            [0, '(0 == 1) && (1 == 1)'],
            [0, '(0 == 1) && (1 == 2)'],
            // Logical AND should take precedence over logical OR.
            [0, '(1 == 1) && (0 == 1) || (1 == 2)'],
            [1, '(0 == 0) && (1 == 0) || (1 == 1)'],
            [1, '(0 == 1) && (1 == 1) || (2 == 2)'],
        ];
    }

    public function provide_for_loops(): array {
        return [
            'one statement' => [45, 'res = 0; for (i:[1:10]) res = res + i'],
            'one statement with list' => [32, 'res = 0; for (i:[1, 2, 3, 5:9]) res = res + i'],
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
            'for loop with two statements' => [258, 'b = 0; for (a:[1:23,5]) { x = {1,2}; b = b + a;}'],
            'for loop pre-stored range' => [45, 'r = [1:10]; b = 0; for (i:r) { b = b + i}'],
            'for loop pre-stored list' => [17, 'r = [1, 2, 5, 9]; b = 0; for (i:r) { b = b + i}'],
        ];
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
            'basic true' => [2, '1 < 5 ? 2 : 3'],
            'basic false' => [3, '1 == 5 ? 2 : 3'],
            'basic true with string in condition' => [2, '"a" == "a" ? 2 : 3'],
            'basic false with string in condition' => [3, '"a" == "b" ? 2 : 3'],
            'basic true returning string, 1' => ['foo', '5 >= 1 ? "foo" : "bar"'],
            'basic true returning string, 2' => ['foo', '1 <= 5 ? "foo" : "bar"'],
            'basic false returning string' => ['bar', '1 > 5 ? "foo" : "bar"'],
            'basic true all strings' => ['foo', '"x" != "y" ? "foo" : "bar"'],
            'basic false all strings' => ['bar', '"x" == "y" ? "foo" : "bar"'],
            'operations in condition, 1' => [1, '1 + 2 == 3 ? 1 : 2'],
            'operations in condition, 2' => [1, '1 == 3 - 2 ? 1 : 2'],
            'operations in true part' => [3, '1 ? 1 + 2 : 2'],
            'operations in false part' => [3, '1 ? 3 : 2 + 4'],
            'operations in all parts' => [7, '1+2==3 ? 1+2*3 : 4*5-6'],
            'ternary in false part with parens' => [7, '1==2 ? 5 : (2==3 ? 6 : 7)'],
            'ternary in true part with parens' => [4, '1==1 ? (1 == 2 ? 3 : 4) : 5'],
            'ternary in false part without parens' => [7, '1==2 ? 5 : 2==3 ? 6 : 7'],
            'ternary in true part without parens' => [4, '1==1 ? 1 == 2 ? 3 : 4 : 5'],
            'ternary in both parts without parens, 1' => [4, '1==1 ? 1 > 2 ? 3 : 4 : 7 < 8 ? 12 : 15'],
            'ternary in both parts without parens, 2' => [12, '1>1 ? 1 == 2 ? 3 : 4 : 7 < 8 ? 12 : 15'],
            // TODO: test with variables
        ];
    }

    public function provide_expressions_with_functions(): array {
        return [
            'one argument, built-in' => [5, 'floor(5.3)'],
            'one argument, custom' => [5040, 'fact(7)'],
            'two arguments' => [252, 'ncr(10,5)'],
            'three arguments' => [16, 'modpow(2,100,17)'],
            'function in function' => [M_PI / 4, 'asin(sqrt(2)/2)'],
            'operation in function' => [-1.02, 'round((1+2*3-4**5)/1000,2)'],
            'several arguments' => ['foo-bar-test', 'a="foo"; b="bar"; c="test"; join("-", a, b, c)'],
            'function with array' => [6, 'sum([1,2,3])'],
            'natural logarithm' => [2.708050201102210065996, 'ln(15)'],
        ];
    }

    public function provide_simple_expressions(): array {
        return [
            'order of operations in power, 1' => [-16, '-4 ** 2'],
            'order of operations in power, 2' => [16, '(-4) ** 2'],
            'array access (valid), 1' => [5, '[1,5][1]'],
            'array access (valid), 2' => [1, '[1,5][0]'],
            'modulo' => [3, '1+2%3'],
            'bitshift left' => [32, '256 >> 3'],
            'bitshift right' => [80, '10 << 3'],
            'bitshift left with negative number' => [-32, '-256 >> 3'],
            'bitshift right with negative number' => [-80, '-10 << 3'],
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
    // TODO: change to variable::<TYPE>
    public function provide_valid_assignments(): array {
        return [
            'one number' => [
                ['a' => new variable('a', 1, token::NUMBER)],
                'a = 1;'
            ],
            'chain assignment' => [
                [
                    'a' => new variable('a', 1, token::NUMBER),
                    'b' => new variable('b', 1, token::NUMBER)
                ],
                'a = b = 1;'
            ],
            'boolean true should be 1' => [
                ['a' => new variable('a', 1, token::NUMBER)],
                'a = (5 == 5);'
            ],
            'boolean false should be 0' => [
                ['a' => new variable('a', 0, token::NUMBER)],
                'a = (5 == 4);'
            ],
            'inverse of boolean true should be 0' => [
                ['a' => new variable('a', 0, token::NUMBER)],
                'a = !(5 == 5);'
            ],
            'inverse of boolean false should be 1' => [
                ['a' => new variable('a', 1, token::NUMBER)],
                'a = !(5 == 4);'
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
            'char from a string' => [
                [
                    's' => new variable('s', 'Hello!', token::STRING),
                    'a' => new variable('a', 'H', token::STRING)
                ],
                's = "Hello!"; a = s[0];'
            ],
            'string concatenation, direct' => [
                [
                    's' => new variable('s', 'Hello World!', token::STRING),
                ],
                's = "Hello" + " World!";'
            ],
            'string concatenation, from variables' => [
                [
                    'a' => new variable('a', 'Hello', token::STRING),
                    'b' => new variable('b', ' World!', token::STRING),
                    's' => new variable('s', 'Hello World!', token::STRING),
                ],
                'a = "Hello"; b = " World!"; s = a + b;'
            ],
            'string concatenation, mixed' => [
                [
                    'a' => new variable('a', 'Hello', token::STRING),
                    's' => new variable('s', 'Hello World!', token::STRING),
                ],
                'a = "Hello"; s = a + " World!";'
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
            'list with numbers and string' => [
                ['e' => new variable('e', [1, 2, 'A'], token::LIST)],
                'e=[1,2,"A"];'
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
            'assign string to list element with variable index' => [
                [
                    'a' => new variable('a', 0, token::NUMBER),
                    'e' => new variable('e', ['A', 2 , 3 , 4], token::LIST),
                ],
                'e = [1,2,3,4]; a=1-1; e[a]="A";'
            ],
            'assign number to list element with variable index' => [
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
            'assign from list with negative index' => [
                [
                    'a' => new variable('a', [0, 1, 2, 3, 4, 5, 6, 7, 8, 9], token::LIST),
                    'b' => new variable('b', 8, token::NUMBER)
                ],
                'a = [0:10]; b = a[-2];'
            ],
            'assign to list with negative index' => [
                [
                    'a' => new variable('a', [0, 1, 2, 3, 4, 5, 6, 99, 8, 15], token::LIST),
                ],
                'a = [0:10]; a[-1] = 15; a[-3] = 99'
            ],
            'assign to list with index being a numeric string' => [
                [
                    'a' => new variable('a', [0, 1, 15, 3, 4, 5, 6, 99, 8, 9], token::LIST),
                ],
                'a = [0:10]; a["2"] = 15; a["-3"] = 99'
            ],
            'assign from literal list with negative index' => [
                [
                    'a' => new variable('a', 8, token::NUMBER)
                ],
                'a = [0:10][-2]'
            ],
            'assign from string variable with negative index' => [
                [
                    's' => new variable('s', 'string', token::STRING),
                    'c' => new variable('c', 'n', token::STRING)
                ],
                's = "string"; c = s[-2];'
            ],
            'assign from string variable with index being numerical string' => [
                [
                    's' => new variable('s', 'string', token::STRING),
                    'c' => new variable('c', 'n', token::STRING),
                    'd' => new variable('d', 't', token::STRING)
                ],
                's = "string"; c = s["-2"]; d = s["1"];'
            ],
            'assign from string literal with negative index' => [
                [
                    'c' => new variable('c', 'n', token::STRING)
                ],
                'c = "string"[-2];'
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
            'assignment with sort(), one empty list' => [
                [
                    's' => new variable('s', [], token::LIST),
                ],
                's=sort([]);'
            ],
            'assignment with sort(), two empty lists' => [
                [
                    's' => new variable('s', [], token::LIST),
                ],
                's=sort([], []);'
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
                    's' => new variable('s', 'ABC', variable::STRING),
                    'i' => new variable('i', 'C', variable::STRING),
                ],
                's = ""; for(i: ["A","B","C"]) { s=join("",s,[i]); }'
            ],
            'assignment involving nested for loops' => [
                [
                    'z' => new variable('z', 30, variable::NUMERIC),
                    'i' => new variable('i', 4, variable::NUMERIC),
                    'j' => new variable('j', 2, variable::NUMERIC),
                ],
                'z = 0; for(i: [0:5]) for(j: [0:3]) z=z+i;'
            ],
            'assignment involving nested for loops' => [
                [
                    's' => new variable('s', [0], token::LIST),
                ],
                's=diff([3*3+3],[3*4]);'
            ],
            'assignment with algebraic vars and diff()' => [
                [
                    'x' => new variable('x', [1, 2, 3, 4, 5, 6, 7, 8, 9], variable::ALGEBRAIC),
                    'y' => new variable('y', [1, 2, 3, 4, 5, 6, 7, 8, 9], variable::ALGEBRAIC),
                    's' => new variable('s', 0, variable::NUMERIC),
                ],
                'x={1:10}; y={1:10}; s=diff(["x*x+y*y"],["x^2+y^2"],50)[0];'
            ],
            'ternary with variables' => [
                [
                    'a' => new variable('a', 1, variable::NUMERIC),
                    'b' => new variable('b', 2, variable::NUMERIC),
                    'c' => new variable('c', 3, variable::NUMERIC),
                    'd' => new variable('d', 4, variable::NUMERIC),
                    'e' => new variable('e', 3, variable::NUMERIC),
                ],
                'a=1; b=2; c=3; d=4; e=(a==b ? b : c)'
            ],
        ];

    }

    public function provide_random_variables(): array {
        return [
            'three numbers' => [
                ['name' => 'x', 'count' => 3, 'min' => 1, 'max' => 3, 'shuffle' => false],
                'x = {1,2,3};'
            ],
            'three letters' => [
                ['name' => 'a', 'count' => 3, 'min' => 'A', 'max' => 'C', 'shuffle' => false],
                'a = {"A","B","C"}'
            ],
            'two lists with numbers' => [
                ['name' => 'a', 'count' => 2, 'min' => null, 'max' => null, 'shuffle' => false],
                'a = {[1,2], [3,4]}'
            ],
            'two lists with letters' => [
                ['name' => 'a', 'count' => 2, 'min' => null, 'max' => null, 'shuffle' => false],
                'a = {["A","B"],["C","D"]}'
            ],
            'three values, more whitespace' => [
                ['name' => 'x', 'count' => 3, 'min' => 1, 'max' => 3, 'shuffle' => false],
                'x = { 1 , 2 , 3 };'
            ],
            'three ranges' => [
                ['name' => 'x', 'count' => 16, 'min' => 1, 'max' => 9.5, 'shuffle' => false],
                'x = {1:3, 4:5:0.1 , 8:10:0.5 };'
            ],
            'values and ranges' => [
                ['name' => 'a', 'count' => 42, 'min' => 0, 'max' => 100, 'shuffle' => false],
                'a = {0, 1:3:0.1, 10:30, 100}'
            ],
            'shuffle with strings' => [
                ['name' => 'a', 'count' => 6, 'shuffle' => true],
                'a = shuffle (["A", "B", "C"])'
            ],
            'big shuffle' => [
                ['name' => 'a', 'count' => PHP_INT_MAX, 'shuffle' => true],
                'a = shuffle ([1:100])'
            ],
            'two vars and many combinations' => [
                ['name' => 'a', 'count' => PHP_INT_MAX, 'shuffle' => true],
                'a = shuffle([1:100]); b = shuffle([1:10]);'
            ],
        ];
    }

    // TODO: test for "randomness"

    /**
     * @dataProvider provide_random_variables
     */
    public function test_assignments_of_random_variables($expected, $input): void {
        $randomparser = new random_parser($input);
        $evaluator = new evaluator();
        $evaluator->evaluate($randomparser->get_statements());

        // First, we check whether the number of variants is correct. This indirectly
        // let's us verify that the random variable has been registered.
        $key = $expected['name'];
        self::assertEquals($expected['count'], $evaluator->get_number_of_variants());

        // Now, we instantiate the random variable. We check that a "normal" variable is
        // created.
        $evaluator->instantiate_random_variables();
        self::assertContains($key, $evaluator->export_variable_list());

        // FIXME: if not shuffle: check element is in reservoir; if shuffle: sort both lists and compare

        // If it is not a "shuffle" case and we have boundaries, we check that the instantiated
        // value is within those boundaries.
        // In some cases, >= and <= comparison does not make sense, e.g. when elements are lists.
        $stored = $evaluator->export_single_variable($key);
        if ($expected['shuffle'] === false) {
            if (isset($expected['min'])) {
                self::assertGreaterThanOrEqual($expected['min'], $stored->value);
            }
            if (isset($expected['max'])) {
                self::assertLessThanOrEqual($expected['max'], $stored->value);
            }
        }
    }

    public function test_reinstantiation_of_random_variables(): void {
        // TODO
        $randomvars = 'a={1:10}';
        $randomparser = new random_parser($randomvars);
        $evaluator = new evaluator();
        $evaluator->evaluate($randomparser->get_statements());
        $evaluator->instantiate_random_variables();

        $globalvars = 'a=2*a';
        $globalparser = new parser($globalvars);
        $evaluator->evaluate($globalparser->get_statements());

        $evaluator->instantiate_random_variables();
        $evaluator->evaluate($globalparser->get_statements());
    }

    /**
     * @dataProvider provide_invalid_random_vars
     */
    public function test_invalid_random_variables($expected, $input): void {
        $error = '';
        try {
            $randomparser = new random_parser($input);
            $evaluator = new evaluator();
            $evaluator->evaluate($randomparser->get_statements());
        } catch (Exception $e) {
            $error = $e->getMessage();
        }

        self::assertStringEndsWith($expected, $error);
    }

    public function test_algebraic_diff() {
        // Initialize the evaluator with global algebraic vars.
        $vars = 'x={-10:11:1}; y={-10:-5, 6:11};';
        $parser = new parser($vars);
        $statements = $parser->get_statements();
        $evaluator = new evaluator();
        $evaluator->evaluate($statements);

        $command = 'diff(["x", "1+x+y+3", "(1+sqrt(x))^2", "x*x+y*y"], ["0", "2+x+y+2", "1+x", "x+y^2"]);';
        $parser = new parser($command);
        $statements = $parser->get_statements();
        $result = $evaluator->evaluate($statements)[0]->value;

        // The first expression should have a difference greater than 0, but less than (or equal)
        // to 10. Even though it is *extremely* unlikely to obtain that maximum value, we'd rather not
        // take the risk and have a unit test that "randomly" fails.
        self::assertGreaterThan(0, $result[0]->value);
        self::assertLessThanOrEqual(10, $result[0]->value);

        // The second expression should have zero difference.
        self::assertEqualsWithDelta(0, $result[1]->value, 1e-8);

        // The third expression should be PHP_FLOAT_MAX, because sqrt(x) is not defined
        // for all values of x.
        self::assertEqualsWithDelta(PHP_FLOAT_MAX, $result[2]->value, 1e-8);

        // For the last expression, the difference is at least 0 (if x and y were to be chosen as 1 for
        // all evaluation points) and at most 72 (if we have the value 9 in all cases).
        self::assertGreaterThanOrEqual(0, $result[3]->value);
        self::assertLessThanOrEqual(72, $result[3]->value);
    }

    public function test_substitute_variables_in_text() {
        // Define, parse and evaluate some variables.
        $vars = 'a=1; b=[2,3,4]; c={1,2,3}; d=[[1,2],[3,4]]';
        $parser = new parser($vars);
        $statements = $parser->get_statements();
        $evaluator = new evaluator();
        $evaluator->evaluate($statements);

        // Our text contains placeholders with valid and invalid syntax (space) as well
        // as inline formulas, expressions with unkonwn variables and a formula that cannot
        // be evaluated due to a syntax error.
        $text = '{{a}}, {a}, {a }, { a}, {b}, {b[0]}, {b[0] }, { b[0]}, {b [0]}, ';
        $text .= '{=a*100}, {=b[0]*b[1]}, {= b[1] * b[2] }, {=100+[4:8][1]}, {xyz}, {=3+}';

        // Test without substitution of array b.
        $output = $evaluator->substitute_variables_in_text($text);
        $expected = '{1}, 1, {a }, { a}, {b}, 2, {b[0] }, { b[0]}, {b [0]}, 100, 6, 12, 105, {xyz}, {=3+}';
        self::assertEquals($expected, $output);

        // Test with substitution of array b as one would write it in PHP.
        $output = $evaluator->substitute_variables_in_text($text, false);
        $expected = '{1}, 1, {a }, { a}, [2, 3, 4], 2, {b[0] }, { b[0]}, {b [0]}, 100, 6, 12, 105, {xyz}, {=3+}';
        self::assertEquals($expected, $output);

        // Test with substitution of nested array.
        $text = '{d} {d[0]} {d[0][0]} {d[1][1]} {d[5][3]}';
        $expected = '{d} {d[0]} 1 4 {d[5][3]}';
        $output = $evaluator->substitute_variables_in_text($text);
        self::assertEquals($expected, $output);

        // And with substitution of arrays being activated.
        $expected = '[[1, 2], [3, 4]] [1, 2] 1 4 {d[5][3]}';
        $output = $evaluator->substitute_variables_in_text($text, false);
        self::assertEquals($expected, $output);

        // Test with algebraic variable. It should not be replaced.
        $text = '{c} {=c}';
        $expected = '{c} {=c}';
        $output = $evaluator->substitute_variables_in_text($text);
        self::assertEquals($expected, $output);
        $output = $evaluator->substitute_variables_in_text($text, false);
        self::assertEquals($expected, $output);

        // Test that expression with assignment is filtered out.
        $text = 'foo {=(a=2)} {=2*3}';
        $expected = 'foo {=(a=2)} 6';
        $output = $evaluator->substitute_variables_in_text($text, false);
        self::assertEquals($expected, $output);
    }

    public function test_substitute_variables_in_algebraic_formula() {
        // Define, parse and evaluate some variables.
        $vars = 'a=1; b=[2,3,4]; c={1,2,3}; x={1:10}; y={1:10}; k = [[1,2],[3,4]];';
        $parser = new parser($vars);
        $statements = $parser->get_statements();
        $evaluator = new evaluator();
        $evaluator->evaluate($statements);

        // Test with standard variable, a one-dimensional array and an unknown variable.
        $formula = 'a*x^2 + b[1]*y + b[2] + d';
        $output = $evaluator->substitute_variables_in_algebraic_formula($formula);
        $expected = '1 * x^2 + 3 * y + 4 + d';
        self::assertEquals($expected, $output);

        // Test with a two-dimensional-array.
        $formula = 'k[0][1]*x + k[1][0]*y + k[1][1] + k[3]';
        $output = $evaluator->substitute_variables_in_algebraic_formula($formula);
        $expected = '2 * x + 3 * y + 4 + k[3]';
        self::assertEquals($expected, $output);

        // Test with variable indices.
        $formula = 'k[0][a]*x + k[2a-a][a-a]*y+k[a][a] + k[a+a+a]';
        $output = $evaluator->substitute_variables_in_algebraic_formula($formula);
        $expected = '2 * x + 3 * y + 4 + k[1 + 1 + 1]';
        self::assertEquals($expected, $output);

        // Test with unary minus.
        $formula = '-x + x^a';
        $output = $evaluator->substitute_variables_in_algebraic_formula($formula);
        $expected = '-x + x^1';
        self::assertEquals($expected, $output);
    }

    public function provide_invalid_random_vars(): array {
        return [
            ['evaluation error: range from 10 to 1 with step 1 will be empty', 'a = {10:1:1}'],
            ['setting individual list elements is not supported for random variables', 'a[1] = {1,2,3}'],
            ['syntax error: invalid use of separator token (,)', 'a = {1:10,}'],
            ["syntax error: incomplete ternary operator or misplaced '?'", 'a = {1:10?}'],
            ['evaluation error: numeric value expected, got algebraic variable', 'a = {0, 1:3:0.1, 10:30, 100}*3'],
            ['unknown variable: a', 'a = {1:3:0.1}; b={a,12,13};'],
            // FIXME: the following are now valid
            ['', 'a = {[1,2],[3,4,5]}'],
            ['', 'a = {[1,2],["A","B"]}'],
            ['', 'a = {[1,2],["A","B","C"]}'],
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

        $variables = $evaluator->export_variable_list();
        foreach ($expected as $key => $variable) {
            self::assertContains($key, $variables);
            $stored = $evaluator->export_single_variable($key, true);
            //self::assertEquals($variable->name, $stored->name);
            self::assertEquals($variable->type, $stored->type);
            // If the value is a list or the variable is algebraic, the elements are tokens.
            // We will only compare the token values to the expected values. For scalar variables,
            // we can directly compare the values.
            if ($stored->type === token::LIST || $stored->type === variable::ALGEBRAIC) {
                foreach ($stored->value as $i => $token) {
                    self::assertEqualsWithDelta($variable->value[$i], $token->value, 1e-8);
                }
            } else {
                self::assertEqualsWithDelta($variable->value, $stored->value, 1e-8);
            }
        }
    }

    public function provide_invalid_bitwise_stuff(): array {
        return [
            ['bit shift operator should only be used with integers', '4.5 << 3'],
            ['bit shift operator should only be used with integers', '4.5 >> 3'],
            ['bit shift operator should only be used with integers', '8 << 1.5'],
            ['bit shift operator should only be used with integers', '8 >> 1.5'],
            ['bit shift by negative number -3 is not allowed', '8 >> -3'],
            ['bit shift by negative number -3 is not allowed', '8 << -3'],
            ['bitwise AND should only be used with integers', '8 & 1.5'],
            ['bitwise AND should only be used with integers', '8.5 & 3'],
            ['bitwise OR should only be used with integers', '8 | 1.5'],
            ['bitwise OR should only be used with integers', '8.5 | 3'],
            ['bitwise XOR should only be used with integers', '8 ^ 1.5'],
            ['bitwise XOR should only be used with integers', '8.5 ^ 3'],
        ];
    }

    public function provide_invalid_for_loops(): array {
        return [
            ['syntax error: ( expected after for', 'for a'],
            ['syntax error: : expected', 'for (a)'],
            ['syntax error: : expected', 'for (a=)'],
            ['syntax error: ) expected', 'for (a:[1:5],)'],
        ];
    }

    public function provide_invalid_ranges(): array {
        return [
            ['syntax error: step size of a range cannot be zero', 'a = [1:5:0]'],
            ['syntax error: start end end of range must not be equal', 'a = [5:5]'],
            ['syntax error: start end end of range must not be equal', 'a = [1.0:1]'],
            ['syntax error in range definition', 'a = [1:2:3:4]'],
        ];
    }

    public function provide_invalid_colon(): array {
        return [
            ['syntax error: invalid use of range separator (:)', 'a = (:5)'],
            ['syntax error: invalid use of range separator (:)', 'a = {5:}'],
            ['syntax error: invalid use of range separator (:)', 'a = {:5}'],
            ['syntax error: invalid use of range separator (:)', 'a = [5:]'],
            ['syntax error: invalid use of range separator (:)', 'a = [:5]'],
            ['syntax error: invalid use of range separator (:)', 'a = [5::7]'],
            ['syntax error: invalid use of range separator (:)', 'a = [3,:7]'],
            ['syntax error: invalid use of range separator (:)', 'a = [3:,7]'],
            ['syntax error: ternary operator missing middle part', 'a = 5 ?: 3'],
            ['syntax error: ranges can only be used in {} or []', 'a = 5 : 3'],
        ];
    }

    public function provide_invalid_assignments(): array {
        return [
            'assign list of strings to algebraic variable' => [
                'algebraic variables can only be initialized with a list of numbers',
                'x = {"a", "b"}'
            ],
            'assign mixed values to algebraic variable' => [
                'algebraic variables can only be initialized with a list of numbers',
                'x = {1, 2, "foo"}'
            ],
            'assign numeric string to algebraic variable' => [
                'algebraic variables can only be initialized with a list of numbers',
                'x = {"1", 2}'
            ],
            'assign nested list to algebraic variable' => [
                'algebraic variables can only be initialized with a list of numbers',
                'x = {1, 2, [1, 2]}'
            ],
            'trying to change char of string' => [
                'individual chars of a string cannot be modified',
                's = "foo"; s[1] = "x"'
            ],
            'assignment with invalid function' => [
                "unknown function: 'idontexist'",
                'a = \idontexist(5)'
            ],
            'assignment to constant' => [
                'left-hand side of assignment must be a variable',
                'pi = 3'
            ],
            'assignment to constant' => [
                'left-hand side of assignment must be a variable',
                'π = 3'
            ],
            'invalid use of prefix with number' => [
                'syntax error: invalid use of prefix character \\',
                'a = \ 2'
            ],
            'invalid argument for unary operator' => [
                "numeric value expected, got 'foo'",
                'a = -"foo"'
            ],
            'invalid argument for unary operator, indirect' => [
                "numeric value expected, got 'foo'",
                's = "foo"; a = -s'
            ],
            'invalid use of prefix with paren' => [
                'syntax error: invalid use of prefix character \\',
                'a = \ (3 + 1)'
            ],
            'assignment to invalid variable' => [
                '1:1:invalid variable name: _a',
                '_a=3;'
            ],
            'missing operator between numbers' => [
                '1:5:syntax error: did you forget to put an operator?',
                'a=3 6;'
            ],
            'unknown char in expression' => [
                "1:4:unexpected input: '«'",
                'a=3«6;'
            ],
            'not subscriptable' => [
                '1:8:evaluation error: indexing is only possible with arrays (lists) and strings',
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
            'xxxx' => [
                '', // FIXME: put this to valid assignments, it is no error anymore.
                'e=[0:10,"k"];'
            ],
            'multiple indices for array' => [
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
            'undefined natrual logarithm' => [
                'ln() expects its argument to be positive',
                'x=ln(-5)'
            ],
            'undefined natrual logarithm' => [
                'ln() expects its argument to be positive',
                'x=ln(0)'
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
            'algebraic variable used in calculation' => [
                "1:21:algebraic variable 'b' cannot be used in this context",
                'a = 7; b = {1:5}; 2*b'
            ],
            'invalid ternary, ? is last char before closing paren' => [
                "syntax error: incomplete ternary operator or misplaced '?'",
                'a = (5 ?)'
            ],
            'invalid ternary, ? is last char before closing brace' => [
                "syntax error: incomplete ternary operator or misplaced '?'",
                'a = {5 ?}'
            ],
            'invalid ternary, ? is last char before closing bracket' => [
                "syntax error: incomplete ternary operator or misplaced '?'",
                'a = [5 ?]'
            ],
            'invalid ternary, ? is last char before closing bracket' => [
                'evaluation error: not enough arguments for ternary operator: 2',
                '(5 ? 4 :)'
            ],
            'invalid ternary, ? is last char before closing bracket' => [
                'evaluation error: not enough arguments for ternary operator',
                'a = (5 ? 4 :)'
            ],
            'argument should be scalar, is list' => [
                'evaluation error: numeric value expected, got list',
                'a = [1, 2, 3] + 4'
            ],
            'argument should be scalar, is list' => [
                'evaluation error: scalar value expected, got list',
                'a = "a" + [1, 2, 3]'
            ],
            'invalid 0^0' => [
                'power 0^0 is not defined',
                'a = 0 ** 0'
            ],
            'invalid power: 0 to negative power' => [
                'division by zero is not defined, so base cannot be zero for negative exponents',
                'a = 0 ** -1'
            ],
            'invalid power: negative base with fractional exponent' => [
                'base cannot be negative with fractional exponent',
                'a = (-1) ** 0.5'
            ],
        ];
    }

    public function provide_invalid_indices(): array {
        return [
            ["expected numerical index, found 'foo'", 's = "string"; a = s["foo"];'],
            ["expected numerical index, found 'foo'", 'a = [1, 2, 3, 4]; b = a["foo"];'],
            ["index should be an integer, found '1.5'", 's = "string"; a = s[1.5];'],
            ["index should be an integer, found '1.5'", 'a = [1, 2, 3, 4]; b = a[1.5];'],
            ['index out of range: 4', 'a = [1,2,3,4][4]'],
            ['index out of range: 4', 'a = "abcd"[4]'],
            ['syntax error: did you forget to put an operator?', 'a = 15[2]'],
            ['index out of range: 4', 'a = "abcd"; b = a[4];'],
            ['index out of range: 4', 'a = [1, 2, 3, 4]; b = a[4];'],
            ['indexing is only possible with arrays (lists) and strings', 'a = 15; b = a[2];'],
        ];
    }

    public function provide_other_invalid_stuff(): array {
        return [
            ['1:7:unexpected token: ,', 'a = 15,2'],
            ['1:9:syntax error: sets cannot be nested', 'a = {1, {2, 3}}'],
            ['1:9:syntax error: sets cannot be used inside a list', 'a = [1, {2, 3}]'],
            ['1:6:invalid use of unary operator: !', 'a = 1!2'],
            ['1:6:invalid use of unary operator: ~', 'a = 1~2'],
        ];
    }

    /**
     * @dataProvider provide_invalid_assignments
     * @dataProvider provide_invalid_colon
     * @dataProvider provide_invalid_for_loops
     * @dataProvider provide_invalid_diff
     * @dataProvider provide_invalid_indices
     * @dataProvider provide_invalid_ranges
     * @dataProvider provide_other_invalid_stuff
     * @dataProvider provide_invalid_bitwise_stuff
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

        self::assertEqualsWithDelta($expected, $result->value, 1e-8);
    }

    public function provide_general_expressions(): array {
        return [
            [-0.35473297204849, 'sin(4) + exp(cos(4+5))'],

        ];
    }

    /**
     * Make sure that we properly detect if a malformed expression (that somehow slipped through all
     * syntax checks and made it to the evaluator) leaves tokens on the stack.
     *
     * @return void
     */
    public function test_expression_with_remaining_tokens(): void {
        $e = null;

        $expression = new expression([
            new token(token::NUMBER, 1),
            new token(token::NUMBER, 2),
            new token(token::NUMBER, 3),
            new token(token::OPERATOR, '+'),
        ]);

        $evaluator = new evaluator();

        try {
            $evaluator->evaluate($expression);
        } catch (Exception $e) {
            self::assertStringContainsString('stack should contain exactly one element after evaluation - did you forget a semicolon somewhere?', $e->getMessage());
        }
        self::assertNotNull($e);
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
            'length 2' => [['100', 'cm'], '100cm'],
            'length 3' => [['1.05', 'mm'], '1.05 mm'],
            'length 4' => [['-1.3', 'nm'], '-1.3 nm'],
            'area 1' => [['-7.5e-3', 'm^2'], '-7.5e-3 m^2', ],
            'area 2' => [['6241509.47e6', 'MeV'], '6241509.47e6 MeV', ],
            'speed' => [['1', 'km/s'], '1 km/s'],
            'combination 1' => [['1', 'm g/us'], '1 m g/us'],
            'combination 2' => [['1', 'kPa s^-2'], '1 kPa s^-2'],
            'combination 3' => [['1', 'm kg s^-2'], '1 m kg s^-2'],
            'numerical' => [['12 + 3 * 4/8', 'm^2'], '12 + 3 * 4/8 m^2'],
            'numerical formula' => [['12 * sqrt(3)', 'kg/s'], '12 * sqrt(3) kg/s'],

            [['.3', ''], '.3'],
            [['3.1', ''], '3.1'],
            [['3.1e-10', ''], '3.1e-10'],
            [['3', 'm'], '3m'],
            [['3', 'kg m/s'], '3kg m/s'],
            [['3.', 'm/s'], '3.m/s'],
            [['3.e-10', 'm/s'], '3.e-10m/s'],
            [['- 3', 'm/s'], '- 3m/s'],
            [['3', 'e10 m/s'], '3 e10 m/s'],
            [['3', 'e 10 m/s'], '3e 10 m/s'],
            [['3e8', 'e8 m/s'], '3e8e8 m/s'],
            [['3+10*4', 'm/s'], '3+10*4 m/s'],
            [['3+10^4', 'm/s'], '3+10^4 m/s'],
            [['sin(3)', 'm/s'], 'sin(3) m/s'],
            [['3+exp(4)', 'm/s'], '3+exp(4) m/s'],
            [['3*4*5', 'm/s'], '3*4*5 m/s'],

            // FIXME: The following is invalid, because 3 4 5 is not a number
            // 'old unit tests, 17' => [['3 4 5 ', 'm/s'], '3 4 5 m/s'],
            'old unit tests, 18' => [['', 'm/s'], 'm/s'],

            // FIXME: The following is invalid, because 3+4 5+10^4 is not a valid numerical expression
            // 'old unit tests, 19' => [['3+4 5+10^4', 'kg m/s'], '3+4 5+10^4kg m/s'],

            'old unit tests, 20' => [['sin(3)', 'kg m/s'], 'sin(3)kg m/s'],

            [['3.1e-10', 'kg m/s'], '3.1e-10kg m/s'],
            [['-3', 'kg m/s'], '-3kg m/s'],
            [['- 3', 'kg m/s'], '- 3kg m/s'],
            [['3', 'e'], '3e'],
            [['3e8', ''], '3e8'],
            [['3e8', 'e'], '3e8e'],

            // FIXME: The following is invalid, because 3+4 5+10^4 is not a number
            //[['3+4 5+10^4', 'kg m/s'], '3+4 5+10^4kg m/s'],
            [['sin(3)', 'kg m/s'], 'sin(3)kg m/s'],
            [['3*4*5', 'kg m/s'], '3*4*5 kg m/s'],

            // FIXME: The following is invalid, because 3 4 5 is not a number
            //'' => [['3 4 5 ', 'kg m/s'], '3 4 5 kg m/s'],

            [['3e8(4.e8+2)(.5e8/2)5', 'kg m/s'], '3e8(4.e8+2)(.5e8/2)5kg m/s'],
            [['3+exp(4+5)^sin(6+7)', 'kg m/s'], '3+exp(4+5)^sin(6+7)kg m/s'],
            [['3+exp(4+5)^-sin(6+7)', 'kg m/s'], '3+exp(4+5)^-sin(6+7)kg m/s'],

            // FIXME: the following is syntactically invalid. Maybe define exception
            // via knownvariables = [...] to allow 'exp 'as "unit"?
            // [['3', 'exp^2'], '3exp^2'],
            [['3', 'e8'], '3 e8'],
            [['3', 'e 8'], '3e 8'],
            [['3e8', 'e8'], '3e8e8'],
            [['3e8', 'e8e8'], '3e8e8e8'],

            // FIXME: The following is invalid, because exp(4+5).m/s is invalid syntax
            //[['3+exp(4+5)', '.m/s'], '3+exp(4+5).m/s'],

            // FIXME: the following are invalid (unbalanced parenthesis)
            // [['3+(4.', '.m/s'], '3+(4.m/s'],
            // [['3+4.)', '.m/s'], '3+4.)m/s'],

            // FIXME: the following are invalid, because m^ and m/ are not valid syntax
            //[['3', 'm^'], '3 m^'],
            //[['3', 'm/'], '3 m/'],

            [['3 /', 's'], '3 /s'],

            [['3', 'm+s'], '3 m+s'],

            // FIXME: this does not split in the same way anymore: '1==2?3:4' and ''
            // [['1', '==2?3:4'], '1==2?3:4'],

            [['', 'a=b'], 'a=b'],

            // FIXME: this does now split '3&4' and ''
            //[['3', '&4'], '3&4'],

            // FIXME: this does now split '3==4' and ''
            // [['3', '==4'], '3==4'],

            // FIXME: this does now split '3&&4' and ''
            //[['3', '&&4'], '3&&4'],

            // FIXME: the following is invalid, because 3! is not currently allowed
            //[['3', '!'], '3!'],

        ];
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

    public function test_export_import_variable_context(): void {
        // Prepare an evaluator with a few variables.
        $randomparser = new random_parser('r = {1,2,3,4}');
        $parser = new parser('a = 2; b = 3*a; c = "foo";');
        $evaluator = new evaluator();
        $evaluator->evaluate($randomparser->get_statements());
        $evaluator->evaluate($parser->get_statements());

        // Export the context and create a new evaluator based on the old one.
        $originalcontext = $evaluator->export_variable_context();
        $otherevaluator = new evaluator($originalcontext);

        // Verify the new evaluator contains the same variables with the same values.
        $originalvariables = $evaluator->export_variable_list();
        $newvariables = $otherevaluator->export_variable_list();
        foreach ($originalvariables as $varname) {
            self::assertContains($varname, $newvariables);
            // Exporting as "algebraic" variable, i. e. the variable as a whole and not just
            // its content.
            $original = $evaluator->export_single_variable($varname, true);
            $copy = $otherevaluator->export_single_variable($varname, true);
            self::assertEquals($copy->name, $original->name);
            self::assertEquals($copy->type, $original->type);
            self::assertEquals($copy->value, $original->value);
        }

        // Verify the new evaluator contains the same random variables.
        $othercontext = $otherevaluator->export_variable_context();
        self::assertEquals($othercontext['randomvariables'], $originalcontext['randomvariables']);
        // Make sure the list of random variables is not empty. There might be a bug that has them
        // both empty and we would not realize without that check.
        self::assertNotEquals('a:0:{}', $othercontext['randomvariables']);

        // Test importing a bad context.
        $e = null;
        try {
            new evaluator(['randomvariables' => 'foo', 'variables' => '']);
        } catch (Exception $e) {
            self::assertEquals('invalid variable context given, aborting import', $e->getMessage());
        }
        self::assertNotNull($e);
    }

    public function test_basic_operations() {
        $parser = new parser('1 >= 5 ? "a" : "b"');
        $evaluator = new evaluator();
        $evaluator->evaluate($parser->get_statements());

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
        $input = 'sublist(["A","B","C","D"],[1,3])';
        $input = 'concat([1,2,3], [4,5,6], [[1,2]], [7,8])';
        $input = 'poly("x", -5)';
        $input = 'shuffle([1,2,3,[4,5,6]])';
        $input = 'sin = 2; \sin(x)';
        $input = 'a = shuffle([1,2,3])';
        $input = 'inv([2, 7, 4, 9, 8, 3, 5, 0, 6, 1])';
        $input = 'inv([0,1,2,3,4])';
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
        $input = '1+ln(3)';
        $input = 'a*sin';
        $input = 'a=[1,2,3]; a[1]=5;';
        return;
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
        $context = $evaluator->export_variable_context();
        print_r($context);
        return;
        $evaluator = new evaluator();
        //$context = 'a:2:{s:1:"a";O:23:"qtype_formulas\variable":3:{s:4:"name";s:1:"a";s:4:"type";i:3;s:5:"value";d:3;}s:1:"b";O:23:"qtype_formulas\variable":3:{s:4:"name";s:1:"b";s:4:"type";i:3;s:5:"value";d:6;}}';
        //$evaluator->import_variable_context($context);
        print_r($result);
        var_dump($evaluator->export_variable_list());
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

    public function test_parse_list() {
        //$input = '[1, 2, 3]';
        //$input = '[1, "a", 3]';
        //$input = '[1, ["x", "y"], 3]';
        //$input = '[[1,2]]';
        $input = 'a = [1, ["x", "y"], [3, 4], 5, [[1,2]],6]';

        $parser = new parser($input);
        // print_r($parser->statements);
    }

    public function provide_numbers(): array {
        return [
            [3, '3'],
            [3, '3.'],
            [0.3, '.3'],
            [3.1, '3.1'],
            [3.1e-10, '3.1e-10'],
            [30000000000, '3.e10'],
            [3000000000, '.3e10'],
            [-3, '-3'],
            [3, '+3'],
            [-30000000000, '-3.e10'],
            [-3000000000, '-.3e10'],
            [M_PI, 'pi'],
            [M_PI, 'π'],
            [-M_PI, '-pi'],
            [-M_PI, '-π'],
            [-3, '- 3'],
            [3, '+ 3'],
            [false, '3 e10'],
            [false, '3e 10'],
            [false, '3e8e8'],
            [false, '3+10*4'],
            [false, '3+10^4'],
            [false, 'sin(3)'],
            [false, '3+exp(4)'],
            [false, '3*4*5'],
            [false, '3 4 5'],
            [false, 'a*b'],
            [false, '#'],
        ];
    }

    // TODO: add more cases
    public function provide_numeric_answers(): array {
        return [
            [3.004, '3+10*4/10^4'],
            [false, 'sin(3)'],
            [false, '3+exp(4)'],
        ];
    }

    public function provide_numerical_formulas(): array {
        return [
            [3.1e-10, '3.1e-10'],
            [-3, '- 3'],
            [3.004, '3+10*4/10^4'],
            [51.739270041204, 'sin(3)-3+exp(4)'],
            [60, '3*4*5'],
            [1.5000000075E+25, '3e8(4.e8+2)(.5e8/2)5'],
            [1.5000000075E+25, '3e8(4.e8+2) (.5e8/2)5'],
            [1.5000000075E+25, '3e8 (4.e8+2)(.5e8/2) 5'],
            [1.5000000075E+25, '3e8 (4.e8+2) (.5e8/2) 5'],
            [4.5000000225E+25, '3(4.e8+2)3e8(.5e8/2)5'],
            [262147, '3+4^9'],
            [387420492, '3+(4+5)^9'],
            [2541865828332, '3+(4+5)^(6+7)'],
            [3.0000098920712, '3+sin(4+5)^(6+7)'],
            [46.881961305748, '3+exp(4+5)^sin(6+7)'],
            [3.0000038146973, '3+4^-(9)'],
            [3.0000038146973, '3+4^-9'],
            [3.0227884071323, '3+exp(4+5)^-sin(6+7)'],
            [2.0986122886681, '1+ln(3)'],
            [1.4771212547197, '1+log10(3)'],
            [M_PI, 'pi'],
            [M_PI, 'pi()'],
            [false, '3 4 5'], // TODO doc: is no longer valid (no implicit multiplication of numbers)
            [false, '3 e10'],
            [false, '3e 10'],
            [false, '3e8e8'],
            [false, '3e8e8e8'],
            [false, '3e8 4.e8 .5e8'], // TODO doc: is no longer valid (no implicit multiplication of numbers)
        ];

    }

    public function provide_algebraic_formulas(): array {
        return [
            [true, 'sin(a)-a+exp(b)'],
            [true, '- 3'],
            [true, '3e 10'],
            [true, 'sin(3)-3+exp(4)'],
            [false, '3e8 4.e8 .5e8'], // TODO doc: is no longer valid (no implicit multiplication of numbers)
            [true, '3e8(4.e8+2)(.5e8/2)5'],
            [true, '3+exp(4+5)^sin(6+7)'],
            [true, '3+4^-(9)'],
            [true, 'sin(a)-a+exp(b)'],
            [true, 'a*b*c'],
            [true, 'a b c'],
            [true, 'a(b+c)(x/y)d'],
            [true, 'a(b+c) (x/y)d'],
            [true, 'a (b+c)(x/y) d'],
            [true, 'a (b+c) (x/y) d'],
            [true, 'a(4.e8+2)3e8(.5e8/2)d'],
            [true, 'pi'],
            [true, 'a+x^y'],
            [true, '3+x^-(y)'],
            [true, '3+x^-y'],
            [true, '3+(u+v)^x'],
            [true, '3+(u+v)^(x+y)'],
            [true, '3+sin(u+v)^(x+y)'],
            [true, '3+exp(u+v)^sin(x+y)'],
            [true, 'a+exp(a)(u+v)^sin(1+2)(b+c)'],
            [true, 'a+exp(u+v)^-sin(x+y)'],
            [true, 'a+b^c^d+f'],
            [true, 'a+b^(c^d)+f'],
            [true, 'a+(b^c)^d+f'],
            [true, 'a+b^c^-d'],
            [true, '1+ln(a)+log10(b)'],
            [true, 'asin(w t / 100)'],
            [true, 'a sin(w t)+ b cos(w t)'],
            [true, '2 (3) a sin(b)^c - (sin(x+y)+x^y)^-sin(z)c tan(z)(x^2)'],
            [true, 'a**b'],
            //[false, '3 e10'], // FIXME: this is valid: 3*e*10 (if e is known)
            //[false, '3e8e8'], // FIXME: this is valid: 3*e*8*e*8 (if e is known)
            //[false, '3e8e8e8'], // FIXME: this is valid: 3*e*8*e*8*e*8 (if e is known)
            [false, 'a/(b-b)'],
            [false, 'a-'],
            [false, '*a'],
            [false, 'a+^c+f'],
            [false, 'a+b^^+f'],
            [false, 'a+(b^c)^+f'],
            [false, 'a+((b^c)^d+f'],
            [false, 'a+(b^c+f'],
            [false, 'a+b^c)+f'],
            [false, 'a+b^(c+f'],
            [false, 'a+b)^c+f'],
            [false, 'pi()'],
            [false, 'sin 3'],
            [false, '1+sin*(3)+2'],
            [false, '1+sin^(3)+2'],
            [false, 'a sin w t'],
            [false, '1==2?3:4'],
            [false, 'a=b'],
            [false, '3&4'],
            [false, '3==4'],
            [false, '3&&4'],
            [false, '3!'],
            [false, '@'],
        ];
    }

    /**
     * @dataProvider provide_algebraic_formulas
     */
    public function test_algebraic_formulas($expected, $input): void {
        // Define a set of algebraic variables first and prepare the evaluator.
        $algebraicvars = 'a={1:10}; b={1:5}; c={1:5}; d={1:3}; e={1:10}; f={1:10};';
        $algebraicvars .= 't={1:10}; u={1:10}; v={1:10}; w={1:10}; x={1:10}; y={1:10}; z={1:10};';
        $parser = new parser($algebraicvars);
        $knownvars = $parser->export_known_variables();
        $evaluator = new evaluator();
        $evaluator->evaluate($parser->get_statements());

        try {
            $parser = new answer_parser($input, $knownvars);
        } catch (Exception $e) {
            // If there was an exception already during the creation of the parser,
            // it is not initialized yet. In that case, we create a new, empty parser.
            // In such a case, is_valid_number() will fail, as expected.
            $parser = new answer_parser('');
        }

        // If we expect the expression to be valid, is must pass this first test.
        $isvalidsyntax = $parser->is_valid_for_answertype(qtype_formulas::ANSWER_TYPE_ALGEBRAIC);
        if ($expected === true) {
            self::assertTrue($isvalidsyntax);
        }
        // If the syntax is not valid, that should have been expected. Note that some
        // expressions are expected to be invalid, but they will pass the first test,
        // because they are be *syntactically* valid.
        if (!$isvalidsyntax) {
            self::assertFalse($expected);
        }

        // If we expect the expression to be invalid, we do not continue, because it
        // should not be possible to evaluate it anyway.
        if ($expected === false) {
            return;
        }

        // Calculate the difference between the expression and a copy of itself. We speed up
        // the tests by evaluating only 20 data points at most.
        $command = "diff(['{$input}'], ['{$input}'], 20)";
        $parser = new parser($command, $knownvars);
        $differences = $evaluator->evaluate($parser->get_statements());
        $diff = $differences[0]->value;

        self::assertEqualsWithDelta(0, $diff[0]->value, 1e-8);
    }

    /**
     * @dataProvider provide_numerical_formulas
     */
    public function test_numerical_formulas($expected, $input): void {
        try {
            $parser = new answer_parser($input);
            $statements = $parser->get_statements();
            $evaluator = new evaluator();
            $result = $evaluator->evaluate($statements);
        } catch (Exception $e) {
            // If there was an exception already during the creation of the parser,
            // it is not initialized yet. In that case, we create a new, empty parser.
            // In such a case, is_valid_number() will fail, as expected.
            if (!isset($parser)) {
                $parser = new answer_parser('');
            }
        }

        if ($expected === false) {
            self::assertFalse($parser->is_valid_for_answertype(qtype_formulas::ANSWER_TYPE_NUMERICAL_FORMULA));
            return;
        }

        self::assertTrue($parser->is_valid_for_answertype(qtype_formulas::ANSWER_TYPE_NUMERICAL_FORMULA));
        self::assertIsArray($result);
        self::assertEquals(1, count($result));
        self::assertEqualsWithDelta($expected, $result[0]->value, 1e-8);
    }

    /**
     * @dataProvider provide_numeric_answers
     */
    public function test_numeric_answer($expected, $input): void {
        try {
            $parser = new answer_parser($input);
            $statements = $parser->get_statements();
            $evaluator = new evaluator();
            $result = $evaluator->evaluate($statements);
        } catch (Exception $e) {
            // If there was an exception already during the creation of the parser,
            // it is not initialized yet. In that case, we create a new, empty parser.
            // In such a case, is_valid_number() will fail, as expected.
            if (!isset($parser)) {
                $parser = new answer_parser('');
            }
        }

        if ($expected === false) {
            self::assertFalse($parser->is_valid_for_answertype(qtype_formulas::ANSWER_TYPE_NUMERIC));
            return;
        }

        self::assertTrue($parser->is_valid_for_answertype(qtype_formulas::ANSWER_TYPE_NUMERIC));
        self::assertIsArray($result);
        self::assertEquals(1, count($result));
        self::assertEqualsWithDelta($expected, $result[0]->value, 1e-8);
    }

    /**
     * @dataProvider provide_numbers
     */
    public function test_number($expected, $input): void {
        try {
            $parser = new answer_parser($input);
            $statements = $parser->get_statements();
            $evaluator = new evaluator();
            $result = $evaluator->evaluate($statements);
        } catch (Exception $e) {
            // If there was an exception already during the creation of the parser,
            // it is not initialized yet. In that case, we create a new, empty parser.
            // In such a case, is_valid_number() will fail, as expected.
            if (!isset($parser)) {
                $parser = new answer_parser('');
            }
        }

        if ($expected === false) {
            self::assertFalse($parser->is_valid_for_answertype(qtype_formulas::ANSWER_TYPE_NUMBER));
            return;
        }

        self::assertTrue($parser->is_valid_for_answertype(qtype_formulas::ANSWER_TYPE_NUMBER));
        self::assertIsArray($result);
        self::assertEquals(1, count($result));
        self::assertEqualsWithDelta($expected, $result[0]->value, 1e-8);
    }

    public function test_impossible_stuff(): void {
        $evaluator = new evaluator();

        // Using an operator with an empty stack.
        $statement = new expression([
            new token(token::OPERATOR, '+'),
        ]);
        $e = null;
        try {
            $evaluator->evaluate($statement);
        } catch (Exception $e) {
            self::assertStringEndsWith('evaluation error: empty stack - did you pass enough arguments for the function or operator?', $e->getMessage());
        }
        self::assertNotNull($e);

        // When trying to access an undefined constant, there should be an error.
        $statement = new expression([
            new token(token::CONSTANT, 'foo'),
        ]);
        $e = null;
        try {
            $evaluator->evaluate($statement);
        } catch (Exception $e) {
            self::assertStringEndsWith("undefined constant: 'foo'", $e->getMessage());
        }
        self::assertNotNull($e);

        // The function evaluator::evaluate() must be called with a for_loop, an expression
        // or a list thereof.
        $e = null;
        try {
            $evaluator->evaluate(['foo', 'bar']);
        } catch (Exception $e) {
            self::assertStringEndsWith('bad invocation of evaluate(), expected expression or for loop', $e->getMessage());
        }
        self::assertNotNull($e);
        $e = null;
        try {
            $evaluator->evaluate('foo');
        } catch (Exception $e) {
            self::assertStringEndsWith('bad invocation of evaluate(), expected an expression or a list of expressions', $e->getMessage());
        }
        self::assertNotNull($e);

        // When executing the ternary operator, we must have enough stuff (and the right stuff) on the stack.
        $statement = new expression([
            new token(token::OPERATOR, '?'),
            new token(token::OPERATOR, '%%ternary'),
        ]);
        $e = null;
        try {
            $evaluator->evaluate($statement);
        } catch (Exception $e) {
            self::assertStringEndsWith('evaluation error: not enough arguments for ternary operator', $e->getMessage());
        }
        self::assertNotNull($e);

        $statement = new expression([
            new token(token::NUMBER, 0),
            new token(token::NUMBER, 0),
            new token(token::NUMBER, 1),
            new token(token::OPERATOR, '?'),
            new token(token::NUMBER, 2),
            new token(token::OPERATOR, '%%ternary'),
        ]);
        $e = null;
        try {
            $evaluator->evaluate($statement);
        } catch (Exception $e) {
            self::assertStringEndsWith('evaluation error: not enough arguments for ternary operator', $e->getMessage());
        }
        self::assertNotNull($e);

    }

}
