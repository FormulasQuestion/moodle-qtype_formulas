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
 * qtype_formulas evaluation tests
 *
 * @package    qtype_formulas
 * @category   test
 * @copyright  2022 Philipp Imhof
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace qtype_formulas;

use Exception;
use Generator;
use Throwable;
use qtype_formulas;
use qtype_formulas\local\answer_parser;
use qtype_formulas\local\random_parser;
use qtype_formulas\local\parser;
use qtype_formulas\local\evaluator;
use qtype_formulas\local\expression;
use qtype_formulas\local\variable;
use qtype_formulas\local\token;

// TODO: reorder those tests later; some are unit tests for the functions and should go there.

/**
 * Unit tests for the evaluator class.
 *
 * @copyright  2024 Philipp Imhof
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @covers \qtype_formulas\local\evaluator
 */
final class evaluator_test extends \advanced_testcase {
    public static function setUpBeforeClass(): void {
        global $CFG;
        parent::setUpBeforeClass();

        require_once($CFG->dirroot . '/question/type/formulas/questiontype.php');
    }

    /**
     * Provide various invalid expressions involving the function diff().
     *
     * @return array
     */
    public static function provide_invalid_diff(): array {
        return [
            ['The first argument of diff() must be a list.', 'diff("", "");'],
            ['The second argument of diff() must be a list.', 'diff([1,2,3], 1);'],
            ['diff() expects two lists of the same size.', 'diff([1,2,3], [1,2]);'],
            ['When using diff(), the first list must contain only numbers or only strings.', 'diff([[1,2]], [1]);'],
            ['diff(): type mismatch for element #1 (zero-indexed) of the first list.', 'diff([1,"a"], [1,2]);'],
            ['diff(): type mismatch for element #1 (zero-indexed) of the first list.', 'diff(["a",1], ["a","b"]);'],
            ['diff(): type mismatch for element #0 (zero-indexed) of the second list.', 'diff([1,2], ["a",2]);'],
            ['diff(): type mismatch for element #1 (zero-indexed) of the second list.', 'diff(["a","b"], ["a",2]);'],
            ['The third argument of diff() can only be used when working with lists of strings.', 'diff([1,2,3], [4,5,6], 3);'],
        ];
    }

    /**
     * Test evaluation of various expressions that will yield a numeric result.
     *
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

    public function test_scope_of_for_loop_iterator(): void {
        $input = 'for (a:[1:10]) { x=1;} res=a;';
        $parser = new parser($input);
        $statements = $parser->get_statements();
        $evaluator = new evaluator();
        $e = null;
        try {
            $evaluator->evaluate($statements);
            $result = $evaluator->export_single_variable('res');
        } catch (Throwable $e) {
            $result = null;
        }
        self::assertNull($e);
        self::assertEquals(9, $result->value);
    }

    /**
     * Provide various boolean and logical expressions.
     *
     * @return array
     */
    public static function provide_boolean_and_logical(): array {
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

    /**
     * Provide various definitions and invocations of a for loop.
     *
     * @return array
     */
    public static function provide_for_loops(): array {
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
            'one statement with expression in range' => [
                22,
                'a = 0.5; b = 10; c = 1/4; res = 0; for (i:[a*2:b/2:c+c]) res = res + i',
            ],
            'for loop with two statements' => [258, 'b = 0; for (a:[1:23,5]) { x = {1,2}; b = b + a;}'],
            'for loop pre-stored range' => [45, 'r = [1:10]; b = 0; for (i:r) { b = b + i}'],
            'for loop pre-stored list' => [17, 'r = [1, 2, 5, 9]; b = 0; for (i:r) { b = b + i}'],
        ];
    }

    /**
     * Test evaluation related to arrays.
     *
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
                    return array_map(function ($nested) {
                        return $nested->value;
                    }, $el->value);
                } else {
                    return $el->value;
                }
            },
            $result->value
        );
        self::assertEqualsWithDelta($expected, $content, 1e-12);
    }

    /**
     * Provide various assignments involving sets.
     *
     * @return array
     */
    public static function provide_sets(): array {
        return [
            'basic' => [
                ['a' => new variable('a', [1, 2, 3, 4, 5], variable::ALGEBRAIC)],
                'a = {1,2,3,4,5}',
            ],
            'range without step' => [
                ['a' => new variable('a', [1, 2, 3, 4, 5, 6, 7, 8, 9, 10], variable::ALGEBRAIC)],
                'a = {1:10}',
            ],
            'range with step' => [
                ['a' => new variable('a', [1, 1.5, 2, 2.5, 3, 3.5, 4, 4.5], variable::ALGEBRAIC)],
                'a = {1:5:0.5}',
            ],
            'range with step, negatives' => [
                ['a' => new variable('a', [-1, -3, -5, -7, -9], variable::ALGEBRAIC)],
                'a = {-1:-10:-2}',
            ],
            'ranges and elements' => [
                ['a' => new variable('a', [1, 5, 5.2, 5.4, 5.6, 5.8, 100, 200, 250], variable::ALGEBRAIC)],
                'a = {1,5:6:0.2,100,200:300:50}',
            ],
            'multiple ranges' => [
                ['a' => new variable('a', [1, 3, 5, 7, 9, 15, 20, 25, 30, 35, 0, -1, -2, -3, -4], variable::ALGEBRAIC)],
                'a = {1:10:2,15:40:5,0:-5:-1}',
            ],
            'range with step, composed expressions' => [
                ['a' => new variable('a', [1 + sqrt(3), 1.2 + sqrt(3), 1.4 + sqrt(3), 1.6 + sqrt(3)], variable::ALGEBRAIC)],
                'a = {1+sqrt(3):4.5-cos(0):1/5}',
            ],
        ];
    }

    /**
     * Provide arrays written in various ways.
     *
     * @return array
     */
    public static function provide_arrays(): array {
        return [
            'basic' => [[1, 2, 3, 4, 5], '[1,2,3,4,5]'],
            'range without step' => [[1, 2, 3, 4, 5, 6, 7, 8, 9], '[1:10]'],
            'reversed range without step' => [[10, 9, 8, 7, 6, 5, 4, 3, 2], '[10:1]'],
            'range with step' => [[1, 3, 5, 7, 9], '[1:10:2]'],
            'ranges and elements' => [
                [1, 5, 5.1, 5.2, 5.3, 5.4, 5.5, 5.6, 5.7, 5.8, 5.9, 100, 200, 225, 250, 275],
                '[1,5:5.95:0.1,100,200:300:25]',
            ],
            'nested' => [
                [[1, 2, 3, 4, 5], [20, 24, 28], [40, 42, 44, 46, 48]],
                '[[1:6],[20:30:4],[40:50:2]]',
            ],
            'multiple ranges' => [
                [1, 2, 3, 4, 15, 30, 45, 60, 60.5, 61, 61.5, 62, 62.5, 0, -1, -2, -3, -4],
                '[1:5,15:50:15,60:63:0.5,0:-5:-1]',
            ],
            'range with step, negatives' => [
                [-1, -1.5, -2, -2.5, -3, -3.5, -4, -4.5],
                '[-1:-5:-0.5]'],
            'elements are expressions' => [
                [3, 4.7449230967779314, 0.2],
                '[1+sqrt(4),5*sin(5/4),1/5]',
            ],
            'range with step, composed expressions' => [
                [3, 3.2, 3.4, 3.6, 3.8, 4, 4.2, 4.4, 4.6],
                '[1+sqrt(4):5*sin(5/4):1/5]',
            ],
        ];
    }

    /**
     * Provide various ternary expressions.
     *
     * @return array
     */
    public static function provide_ternary_expressions(): array {
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
            'ternary with vars' => [1, 'a=1; b=2; a < b ? a : b'],
            'ternary with arrays' => [2, 'a=[1,2,3]; b=2; a[0] > a[b] ? a[1] : a[0] * a[1]'],
        ];
    }

    /**
     * Provide various expressions involving functions.
     *
     * @return array
     */
    public static function provide_expressions_with_functions(): array {
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
            'nested function' => [-0.35473297204849, 'sin(4) + exp(cos(4+5))'],
        ];
    }

    /**
     * Provide various simple expressions, i. e. expressions involving only operators but no functions.
     *
     * @return array
     */
    public static function provide_simple_expressions(): array {
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

    /**
     * Provide various assignments.
     *
     * @return array
     */
    public static function provide_valid_assignments(): array {
        return [
            'one number' => [
                ['a' => new variable('a', 1, variable::NUMERIC)],
                'a = 1;',
            ],
            'chain assignment' => [
                [
                    'a' => new variable('a', 1, variable::NUMERIC),
                    'b' => new variable('b', 1, variable::NUMERIC),
                ],
                'a = b = 1;',
            ],
            'boolean true should be 1' => [
                ['a' => new variable('a', 1, variable::NUMERIC)],
                'a = (5 == 5);',
            ],
            'boolean false should be 0' => [
                ['a' => new variable('a', 0, variable::NUMERIC)],
                'a = (5 == 4);',
            ],
            'inverse of boolean true should be 0' => [
                ['a' => new variable('a', 0, variable::NUMERIC)],
                'a = !(5 == 5);',
            ],
            'inverse of boolean false should be 1' => [
                ['a' => new variable('a', 1, variable::NUMERIC)],
                'a = !(5 == 4);',
            ],
            'two numbers' => [
                [
                    'a' => new variable('a', 1, variable::NUMERIC),
                    'b' => new variable('b', 4, variable::NUMERIC),
                ],
                'a = 1; b = 4;',
            ],
            'number with comment' => [
                ['a' => new variable('a', 1, variable::NUMERIC)],
                'a = 1; # This is a comment! So it will be skipped. ',
            ],
            'implicit multiplication of numbers' => [
                ['a' => new variable('a', 18, variable::NUMERIC)],
                'a=3 6;',
            ],
            'one expression' => [
                ['c' => new variable('c', 4.14, variable::NUMERIC)],
                'c = cos(0)+3.14;',
            ],
            'char from a string' => [
                [
                    's' => new variable('s', 'Hello!', variable::STRING),
                    'a' => new variable('a', 'H', variable::STRING),
                ],
                's = "Hello!"; a = s[0];',
            ],
            'string concatenation, direct' => [
                [
                    's' => new variable('s', 'Hello World!', variable::STRING),
                ],
                's = "Hello" + " World!";',
            ],
            'string concatenation, from variables' => [
                [
                    'a' => new variable('a', 'Hello', variable::STRING),
                    'b' => new variable('b', ' World!', variable::STRING),
                    's' => new variable('s', 'Hello World!', variable::STRING),
                ],
                'a = "Hello"; b = " World!"; s = a + b;',
            ],
            'string concatenation, mixed' => [
                [
                    'a' => new variable('a', 'Hello', variable::STRING),
                    's' => new variable('s', 'Hello World!', variable::STRING),
                ],
                'a = "Hello"; s = a + " World!";',
            ],
            'one string with double quotes' => [
                ['d' => new variable('d', 'Hello!', variable::STRING)],
                'd = "Hello!";',
            ],
            'one string with single quotes' => [
                ['d' => new variable('d', 'Hello!', variable::STRING)],
                "d = 'Hello!';",
            ],
            'list of numbers' => [
                ['e' => new variable('e', [1, 2, 3, 4], variable::LIST)],
                'e =[1,2,3,4];',
            ],
            'list of strings' => [
                ['f' => new variable('f', ['A', 'B', 'C'], variable::LIST)],
                'f =["A", "B", "C"];',
            ],
            'list with numbers and string' => [
                ['e' => new variable('e', [1, 2, 'A'], variable::LIST)],
                'e=[1,2,"A"];',
            ],
            'list with range of numbers and string' => [
                ['e' => new variable('e', [0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 'k'], variable::LIST)],
                'e=[0:10,"k"];',
            ],
            'empty list' => [
                ['e' => new variable('e', [], variable::LIST)],
                'e=[];',
            ],
            'large list (10000 entries) via fill' => [
                ['c' => new variable('e', array_fill(0, 10000, 'rr'), variable::LIST)],
                'c=fill(10000,"rr")',
            ],
            'list filled with count from expression' => [
                [
                    'a' => new variable('a', [1, 2, 3, 4], variable::LIST),
                    'c' => new variable('c', array_fill(0, 5, 'rr'), variable::LIST),
                ],
                'a=[1,2,3,4]; c=fill(len(a)+1,"rr")',
            ],
            'composed expression with vars' => [
                [
                    'a' => new variable('a', 1, variable::NUMERIC),
                    'b' => new variable('b', 4, variable::NUMERIC),
                    'c' => new variable('c', 4, variable::NUMERIC),
                    'g' => new variable('g', [1, 47 , 2 , 2.718281828459, 16], variable::LIST),
                ],
                'a = 1; b = 4; c = a*b; g= [1,2+45, cos(0)+1,exp(a),b*c];',
            ],
            'list with expressions + list element reference' => [
                [
                    'h' => new variable('h', [1, 5 , -0.7568024953079282, 5], variable::LIST),
                    'j' => new variable('j', 5, variable::NUMERIC),
                ],
                'h = [1,2+3,sin(4),5]; j=h[1];',
            ],
            'assign list element' => [
                ['e' => new variable('e', 2, variable::NUMERIC)],
                'e = [1,2,3,4][1];',
            ],
            'assign to list element' => [
                [
                    'e' => new variable('e', [1, 2 , 111 , 4], variable::LIST),
                ],
                'e = [1,2,3,4]; e[2]=111;',
            ],
            'assign string to list element with variable index' => [
                [
                    'a' => new variable('a', 0, variable::NUMERIC),
                    'e' => new variable('e', ['A', 2 , 3 , 4], variable::LIST),
                ],
                'e = [1,2,3,4]; a=1-1; e[a]="A";',
            ],
            'assign number to list element with variable index' => [
                [
                    'a' => new variable('a', 1, variable::NUMERIC),
                    'e' => new variable('e', [1, 111 , 3 , 4], variable::LIST),
                ],
                'e = [1,2,3,4]; a=1; e[a]=111;',
            ],
            'assign to list element with calculated variable as index' => [
                [
                    'a' => new variable('a', 0, variable::NUMERIC),
                    'e' => new variable('e', [111, 2 , 3 , 4], variable::LIST),
                ],
                'e = [1,2,3,4]; a=1-1; e[a]=111;',
            ],
            'assign only element from list of length 1' => [
                ['g' => new variable('g', 3, variable::NUMERIC)],
                'g = [3][0];',
            ],
            'assign from array where element is itself element from a list' => [
                [
                    'a' => new variable('a', [7, 8, 9], variable::LIST),
                    'g' => new variable('g', 8, variable::NUMERIC),
                ],
                'a = [7,8,9]; g = [a[1]][0];',
            ],
            'assign with ranges' => [
                [
                    'h' => new variable('h', [0, 1, 2, 3, 4, 5, 6, 7, 8, 9], variable::LIST),
                    'k' => new variable('k', [4, 5, 6, 7], variable::LIST),
                    'm' => new variable('m', [-20, -18.5, -17, -15.5, -14, -12.5, -11], variable::LIST),
                ],
                'h = [0:10]; k=[4:8:1]; m=[-20:-10:1.5];',
            ],
            'assign from list with negative index' => [
                [
                    'a' => new variable('a', [0, 1, 2, 3, 4, 5, 6, 7, 8, 9], variable::LIST),
                    'b' => new variable('b', 8, variable::NUMERIC),
                ],
                'a = [0:10]; b = a[-2];',
            ],
            'assign to list with negative index' => [
                [
                    'a' => new variable('a', [0, 1, 2, 3, 4, 5, 6, 99, 8, 15], variable::LIST),
                ],
                'a = [0:10]; a[-1] = 15; a[-3] = 99',
            ],
            'assign to list with index being a numeric string' => [
                [
                    'a' => new variable('a', [0, 1, 15, 3, 4, 5, 6, 99, 8, 9], variable::LIST),
                ],
                'a = [0:10]; a["2"] = 15; a["-3"] = 99',
            ],
            'assign from literal list with negative index' => [
                [
                    'a' => new variable('a', 8, variable::NUMERIC),
                ],
                'a = [0:10][-2]',
            ],
            'assign from string variable with negative index' => [
                [
                    's' => new variable('s', 'string', variable::STRING),
                    'c' => new variable('c', 'n', variable::STRING),
                ],
                's = "string"; c = s[-2];',
            ],
            'assign from string variable with index being numerical string' => [
                [
                    's' => new variable('s', 'string', variable::STRING),
                    'c' => new variable('c', 'n', variable::STRING),
                    'd' => new variable('d', 't', variable::STRING),
                ],
                's = "string"; c = s["-2"]; d = s["1"];',
            ],
            'assign from string literal with negative index' => [
                ['c' => new variable('c', 'n', variable::STRING)],
                'c = "string"[-2];',
            ],
            'assign lists and composed expressions' => [
                [
                    'a' => new variable('a', [1, 2, 3], variable::LIST),
                    's' => new variable('s', [2, 0, 1], variable::LIST),
                    'n' => new variable('n', [9, 3, 54], variable::LIST),
                ],
                'a = [1,2,3]; s=[2,0,1]; n=[3*a[s[0]], 3*a[s[1]], 3*a[s[2]]*9];',
            ],
            'assign with concatenation of lists' => [
                ['s' => new variable('s', [1, 2, 3, 'A', 'B'], variable::LIST)],
                's=concat([1,2,3], ["A","B"]);',
            ],
            'assign with exponentiation' => [
                ['a' => new variable('a', 6561, variable::NUMERIC)],
                'a=3**8;',
            ],
            'assign with fill()' => [
                [
                    'a' => new variable('a', 4, variable::NUMERIC),
                    'A' => new variable('A', [0, 0], variable::LIST),
                    'B' => new variable('B', ['Hello', 'Hello', 'Hello'], variable::LIST),
                    'C' => new variable('C', [4, 4, 4, 4], variable::LIST),
                ],
                'a=4; A = fill(2,0); B= fill ( 3,"Hello"); C=fill(a,4);',
            ],
            'assign with indirect fill()' => [
                [
                    'a' => new variable('a', [1, 2, 3, 4], variable::LIST),
                    'b' => new variable('b', 4, variable::NUMERIC),
                    'c' => new variable('c', ['rr', 'rr', 'rr', 'rr'], variable::LIST),
                ],
                'a=[1,2,3,4]; b=len(a); c=fill(len(a),"rr")',
            ],
            'assignment with sort() (numbers)' => [
                [
                    's' => new variable('s', [2, 3, 5, 7, 11], variable::LIST),
                ],
                's=sort([7,5,3,11,2]);',
            ],
            'assignment with sort() (strings)' => [
                [
                    's' => new variable('s', ['A1', 'A2', 'A10', 'A100'], variable::LIST),
                ],
                's=sort(["A1","A10","A2","A100"]);',
            ],
            'assignment with sort() (two args)' => [
                [
                    's' => new variable('s', [2, 3, 1], variable::LIST),
                ],
                's=sort([1,2,3], ["A10","A1","A2"]);',
            ],
            'assignment with sort() (numbers again)' => [
                [
                    's' => new variable('s', [1, 3, 5, 10], variable::LIST),
                ],
                's=sort([1,10,5,3]);',
            ],
            'assignment with sort() (numeric strings and letters)' => [
                [
                    's' => new variable('s', ['1', '2', '3', '4', 'A', 'B', 'C', 'a', 'b', 'c'], variable::LIST),
                ],
                's=sort(["4","3","A","a","B","2","1","b","c","C"]);',
            ],
            'assignment with sort() (letters)' => [
                [
                    's' => new variable('s', ['A', 'B', 'B', 'C'], variable::LIST),
                ],
                's=sort(["B","C","A","B"]);',
            ],
            'assignment with sort() (numeric strings and letters again)' => [
                [
                    's' => new variable('s', ['0', '1', '2', '3', 'A', 'B', 'C', 'a', 'b', 'c'], variable::LIST),
                ],
                's=sort(["B","3","1","0","A","C","c","b","2","a"]);',
            ],
            'assignment with sort() (strings), 2' => [
                [
                    's' => new variable('s', ['A1', 'A2', 'B'], variable::LIST),
                ],
                's=sort(["B","A2","A1"]);',
            ],
            'assignment with sort() (strings, two params)' => [
                [
                    's' => new variable('s', ['B', 'A', 'C'], variable::LIST),
                ],
                's=sort(["B","C","A"],[0,2,1]);',
            ],
            'assignment with sort() (strings, two params again)' => [
                [
                    's' => new variable('s', ['A1', 'B', 'A2'], variable::LIST),
                ],
                's=sort(["B","A2","A1"],[2,4,1]);',
            ],
            'assignment with sort() (both arguments are strings)' => [
                [
                    's' => new variable('s', ['C', 'A', 'B'], variable::LIST),
                ],
                's=sort(["A","B","C"],["A2","A10","A1"]);',
            ],
            'assignment with sort() (positive and negative numbers)' => [
                [
                    's' => new variable('s', [-4, -3, -2, -1, 0, 1, 2, 3, 4, 5], variable::LIST),
                ],
                's=sort([-3,-2,4,2,3,1,0,-1,-4,5]);',
            ],
            'assignment with sort() (positive and negative numbers as strings)' => [
                [
                    's' => new variable('s', ['-3', '-2', '-1', '0', '1', '2', '3', 'A', 'B', 'a', 'b'], variable::LIST),
                ],
                's=sort(["-3","-2","B","2","3","1","0","-1","b","a","A"]);',
            ],
            'assignment with sort(), one empty list' => [
                [
                    's' => new variable('s', [], variable::LIST),
                ],
                's=sort([]);',
            ],
            'assignment with sort(), two empty lists' => [
                [
                    's' => new variable('s', [], variable::LIST),
                ],
                's=sort([], []);',
            ],
            'assignment with sublist()' => [
                [
                    's' => new variable('s', ['B', 'D'], variable::LIST),
                ],
                's=sublist(["A","B","C","D"],[1,3]);',
            ],
            'assignment with sublist(), same element twice' => [
                [
                    's' => new variable('s', ['A', 'A', 'C', 'D'], variable::LIST),
                ],
                's=sublist(["A","B","C","D"],[0,0,2,3]);',
            ],
            'assignment with inv()' => [
                [
                    's' => new variable('s', [1, 3, 0, 2], variable::LIST),
                ],
                's=inv([2,0,3,1]);',
            ],
            'assignment with inv(inv())' => [
                [
                    's' => new variable('s', [2, 0, 3, 1], variable::LIST),
                ],
                's=inv(inv([2,0,3,1]));',
            ],
            'assignment with sublist() and inv()' => [
                [
                    'A' => new variable('A', ['A', 'B', 'C', 'D'], variable::LIST),
                    'B' => new variable('B', [2, 0, 3, 1], variable::LIST),
                    's' => new variable('s', ['A', 'B', 'C', 'D'], variable::LIST),
                ],
                'A=["A","B","C","D"]; B=[2,0,3,1]; s=sublist(sublist(A,B),inv(B));',
            ],
            'assignment with map() and "exp"' => [
                [
                    'a' => new variable('a', [1, 2, 3], variable::LIST),
                    'A' => new variable('A', [2.718281828459, 7.3890560989307, 20.085536923188], variable::LIST),
                ],
                'a=[1,2,3]; A=map("exp",a);',
            ],
            'assignment with map() and "+" with constant' => [
                [
                    'a' => new variable('a', [1, 2, 3], variable::LIST),
                    'A' => new variable('A', [3.3, 4.3, 5.3], variable::LIST),
                ],
                'a=[1,2,3]; A=map("+",a,2.3);',
            ],
            'assignment with map() and "+" with two arrays' => [
                [
                    'a' => new variable('a', [1, 2, 3], variable::LIST),
                    'b' => new variable('b', [4, 5, 6], variable::LIST),
                    'A' => new variable('A', [5, 7, 9], variable::LIST),
                ],
                'a=[1,2,3]; b=[4,5,6]; A=map("+",a,b);',
            ],
            'assignment with map() and "pow" with two arrays' => [
                [
                    'a' => new variable('a', [1, 2, 3], variable::LIST),
                    'b' => new variable('b', [4, 5, 6], variable::LIST),
                    'A' => new variable('A', [1, 32, 729], variable::LIST),
                ],
                'a=[1,2,3]; b=[4,5,6]; A=map("pow",a,b);',
            ],
            'assignment with sum()' => [
                [
                    'r' => new variable('r', 15, variable::NUMERIC),
                ],
                'r=sum([4,5,6]);',
            ],
            'assignment with sum(), fill() and operations' => [
                [
                    'r' => new variable('r', -4, variable::NUMERIC),
                ],
                'r=3+sum(fill(10,-1))+3;',
            ],
            'assignment with concat() and lists of numbers' => [
                [
                    's' => new variable('s', [1, 2, 3, 4, 5, 6, 7, 8], variable::LIST),
                ],
                's=concat([1,2,3], [4,5,6], [7,8]);',
            ],
            'assignment with concat() and lists of strings' => [
                [
                    's' => new variable('s', ['A', 'B', 'X', 'Y', 'Z', 'Hello'], variable::LIST),
                ],
                's=concat(["A","B"],["X","Y","Z"],["Hello"]);',
            ],
            'assignment with join() and list of numbers' => [
                [
                    's' => new variable('s', '1~2~3', variable::STRING),
                ],
                's=join("~", [1,2,3]);',
            ],
            'assignment with str()' => [
                [
                    's' => new variable('s', '45', variable::STRING),
                ],
                's=str(45);',
            ],
            'assignment with nested join() and list' => [
                [
                    'a' => new variable('a', [4, 5], variable::LIST),
                    's' => new variable('s', 'A,B,1,5,3,4+5+?,9', variable::STRING),
                ],
                'a=[4,5]; s = join(",","A","B", [ 1 , a  [1]], 3, [join("+",a,"?"),"9"]);',
            ],
            'assignment with references and sum() containing a range' => [
                [
                    'A' => new variable('A', 1, variable::NUMERIC),
                    'Z' => new variable('Z', 4, variable::NUMERIC),
                    'Y' => new variable('Y', 'Hello!', variable::STRING),
                    'X' => new variable('X', 31, variable::NUMERIC),
                ],
                'A = 1; Z = A + 3; Y = "Hello!"; X = sum([4:12:2]) + 3;',
            ],
            'implicit assignment via empty for loop index' => [
                [
                    'i' => new variable('i', 3, variable::NUMERIC),
                ],
                'for(i:[1,2,3]){ };',
            ],
            'implicit assignment via for loop index, other input format' => [
                [
                    'i' => new variable('i', 3, variable::NUMERIC),
                ],
                'for ( i : [1,2,3] ) {};',
            ],
            'assignment involving for loop with single statement and list from variable' => [
                [
                    'z' => new variable('z', 6, variable::NUMERIC),
                    'i' => new variable('i', 3, variable::NUMERIC),
                    'A' => new variable('A', [1, 2, 3], variable::LIST),
                ],
                'z = 0; A=[1,2,3]; for(i:A) z=z+i;',
            ],
            'assignment involving for loop with single statement in braces' => [
                [
                    'z' => new variable('z', 10, variable::NUMERIC),
                    'i' => new variable('i', 4, variable::NUMERIC),
                ],
                'z = 0; for(i: [0:5]){z = z + i;}',
            ],
            'assignment involving for loop iterating over list of strings' => [
                [
                    's' => new variable('s', 'ABC', variable::STRING),
                    'i' => new variable('i', 'C', variable::STRING),
                ],
                's = ""; for(i: ["A","B","C"]) { s=join("",s,[i]); }',
            ],
            'assignment involving nested for loops' => [
                [
                    'z' => new variable('z', 30, variable::NUMERIC),
                    'i' => new variable('i', 4, variable::NUMERIC),
                    'j' => new variable('j', 2, variable::NUMERIC),
                ],
                'z = 0; for(i: [0:5]) for(j: [0:3]) z=z+i;',
            ],
            'assignment involving nested for loops, 2' => [
                [
                    's' => new variable('s', [0], variable::LIST),
                ],
                's=diff([3*3+3],[3*4]);',
            ],
            'assignment with algebraic vars and diff()' => [
                [
                    'x' => new variable('x', [1, 2, 3, 4, 5, 6, 7, 8, 9], variable::ALGEBRAIC),
                    'y' => new variable('y', [1, 2, 3, 4, 5, 6, 7, 8, 9], variable::ALGEBRAIC),
                    's' => new variable('s', 0, variable::NUMERIC),
                ],
                'x={1:10}; y={1:10}; s=diff(["x*x+y*y"],["x^2+y^2"],50)[0];',
            ],
            'ternary with variables' => [
                [
                    'a' => new variable('a', 1, variable::NUMERIC),
                    'b' => new variable('b', 2, variable::NUMERIC),
                    'c' => new variable('c', 3, variable::NUMERIC),
                    'd' => new variable('d', 4, variable::NUMERIC),
                    'e' => new variable('e', 3, variable::NUMERIC),
                ],
                'a=1; b=2; c=3; d=4; e=(a==b ? b : c)',
            ],
        ];

    }

    /**
     * Provide assignments involving random variables.
     *
     * @return array
     */
    public static function provide_random_variables(): array {
        return [
            'three numbers' => [
                ['name' => 'x', 'count' => 3, 'min' => 1, 'max' => 3, 'shuffle' => false],
                'x = {1,2,3};',
            ],
            'three letters' => [
                ['name' => 'a', 'count' => 3, 'min' => 'A', 'max' => 'C', 'shuffle' => false],
                'a = {"A","B","C"}',
            ],
            'two lists with numbers' => [
                ['name' => 'a', 'count' => 2, 'min' => null, 'max' => null, 'shuffle' => false],
                'a = {[1,2], [3,4]}',
            ],
            'two lists with letters' => [
                ['name' => 'a', 'count' => 2, 'min' => null, 'max' => null, 'shuffle' => false],
                'a = {["A","B"],["C","D"]}',
            ],
            'three values, more whitespace' => [
                ['name' => 'x', 'count' => 3, 'min' => 1, 'max' => 3, 'shuffle' => false],
                'x = { 1 , 2 , 3 };',
            ],
            'three ranges' => [
                ['name' => 'x', 'count' => 16, 'min' => 1, 'max' => 9.5, 'shuffle' => false],
                'x = {1:3, 4:5:0.1 , 8:10:0.5 };',
            ],
            'values and ranges' => [
                ['name' => 'a', 'count' => 42, 'min' => 0, 'max' => 100, 'shuffle' => false],
                'a = {0, 1:3:0.1, 10:30, 100}',
            ],
            'shuffle with strings' => [
                ['name' => 'a', 'count' => 6, 'shuffle' => true],
                'a = shuffle (["A", "B", "C"])',
            ],
            'big shuffle' => [
                ['name' => 'a', 'count' => PHP_INT_MAX, 'shuffle' => true],
                'a = shuffle ([1:100])',
            ],
            'two vars and many combinations' => [
                ['name' => 'a', 'count' => PHP_INT_MAX, 'shuffle' => true],
                'a = shuffle([1:100]); b = shuffle([1:10]);',
            ],
            'two numeric lists of different length' => [
                ['name' => 'a', 'count' => 2, 'shuffle' => false],
                'a = {[1,2],[3,4,5]}',
            ],
            'list of numbers and strings, same length' => [
                ['name' => 'a', 'count' => 2, 'shuffle' => false],
                'a = {[1,2],["A","B"]}',
            ],
            'list of numbers and strings, different length' => [
                ['name' => 'a', 'count' => 2, 'shuffle' => false],
                'a = {[1,2],["A","B","C"]}',
            ],
        ];
    }

    /**
     * Test assignment and evaluation related to random variables.
     *
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
        // Setup and instantiate a random variable.
        $randomvars = 'a={1,2,4}';
        $randomparser = new random_parser($randomvars);
        $evaluator = new evaluator();
        $evaluator->evaluate($randomparser->get_statements());
        $evaluator->instantiate_random_variables();

        // Overwrite the random variable with a dependent global variable.
        $globalvars = 'a=2*a';
        $globalparser = new parser($globalvars);
        $evaluator->evaluate($globalparser->get_statements());

        // Variable a must be between 2, 4 or 8, as float.
        $a = $evaluator->export_single_variable('a')->value;
        self::assertContains($a, [2.0, 4.0, 8.0]);

        // Now re-instantiate the random variable until all values have been taken at least once,
        // but stop after 200 iterations.
        $two = false;
        $four = false;
        $eight = false;
        for ($i = 0; $i < 200; $i++) {
            if ($a == 2) {
                $two = true;
            }
            if ($a == 4) {
                $four = true;
            }
            if ($a == 8) {
                $eight = true;
            }
            if ($two && $four && $eight) {
                break;
            }
            $evaluator->instantiate_random_variables();
            $evaluator->evaluate($globalparser->get_statements());
            $a = $evaluator->export_single_variable('a')->value;
        }
        self::assertTrue($two);
        self::assertTrue($four);
        self::assertTrue($eight);
    }

    /**
     * Test treatment of invalid definitions for random variables.
     *
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

    /**
     * Provide invalid definitions of random variables.
     *
     * @return array
     */
    public static function provide_invalid_random_vars(): array {
        return [
            ['Evaluation error: range from 10 to 1 with step 1 will be empty.', 'a = {10:1:1}'],
            ['Setting individual list elements is not supported for random variables.', 'a[1] = {1,2,3}'],
            ['Syntax error: invalid use of separator token \',\'.', 'a = {1:10,}'],
            ["Syntax error: incomplete ternary operator or misplaced '?'.", 'a = {1:10?}'],
            ['Number expected, found algebraic variable.', 'a = {0, 1:3:0.1, 10:30, 100}*3'],
            ['Unknown variable: a', 'a = {1:3:0.1}; b={a,12,13};'],
        ];
    }

    /**
     * Test various assignments.
     *
     * @dataProvider provide_valid_assignments
     * @dataProvider provide_sets
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

    /**
     * Provide nested lists.
     *
     * @return array
     */
    public static function provide_nested_list_assignments(): array {
        return [
            'nested list' => [
                [
                    'e' => new variable('e', [[1, 2], [3, 4]], variable::LIST),
                ],
                'e=[[1,2],[3,4]];',
            ],
            'change list element to nested list' => [
                [
                    'e' => new variable('e', [[8, 9], 2, 3], variable::LIST),
                ],
                'e=[1,2,3]; e[0] = [8,9];',
            ],
            'nest list into list from variable' => [
                [
                    'a' => new variable('a', [9, 10], variable::LIST),
                    'e' => new variable('e', [[9, 10], 2, 3], variable::LIST),
                ],
                'a=[9,10]; e=[1,2,3]; e[0]=a;',
            ],
            'change list element in a nested list' => [
                [
                    'e' => new variable('e', [[1, 5], 2, 3], variable::LIST),
                ],
                'e=[[1,2],2,3]; e[0][1] = 5;',
            ],
        ];
    }

    /**
     * Test nested lists.
     *
     * @dataProvider provide_nested_list_assignments
     */
    public function test_assignment_of_nested_lists($expected, $input): void {
        $parser = new parser($input);
        $statements = $parser->get_statements();
        $evaluator = new evaluator();
        $evaluator->evaluate($statements);

        $variables = $evaluator->export_variable_list();
        foreach ($expected as $key => $variable) {
            self::assertContains($key, $variables);
            $stored = $evaluator->export_single_variable($key, true);
            self::assertEquals($variable->type, $stored->type);
            // If the value is a list or the variable is algebraic, the elements are tokens.
            // We will only compare the token values to the expected values. For scalar variables,
            // we can directly compare the values.
            if ($stored->type === token::LIST) {
                foreach ($stored->value as $i => $token) {
                    self::assertEquals($variable->value[$i], token::unpack($token));
                }
            } else {
                self::assertEquals($variable->value, $stored->value);
            }
        }
    }

    /**
     * Provide invalid expressions involving bitwise operators.
     *
     * @return array
     */
    public static function provide_invalid_bitwise_stuff(): array {
        return [
            ['Bit shift operator should only be used with integers.', '4.5 << 3'],
            ['Bit shift operator should only be used with integers.', '4.5 >> 3'],
            ['Bit shift operator should only be used with integers.', '8 << 1.5'],
            ['Bit shift operator should only be used with integers.', '8 >> 1.5'],
            ['Bit shift by negative number -3 is not allowed.', '8 >> -3'],
            ['Bit shift by negative number -3 is not allowed.', '8 << -3'],
            ['Bitwise AND should only be used with integers.', '8 & 1.5'],
            ['Bitwise AND should only be used with integers.', '8.5 & 3'],
            ['Bitwise OR should only be used with integers.', '8 | 1.5'],
            ['Bitwise OR should only be used with integers.', '8.5 | 3'],
            ['Bitwise XOR should only be used with integers.', '8 ^ 1.5'],
            ['Bitwise XOR should only be used with integers.', '8.5 ^ 3'],
        ];
    }

    /**
     * Provide invalid definitions of for loops.
     *
     * @return array
     */
    public static function provide_invalid_for_loops(): array {
        return [
            ['Syntax error: ( expected after for.', 'for a'],
            ['Syntax error: : expected.', 'for (a)'],
            ['Syntax error: : expected.', 'for (a=)'],
            ['Syntax error: ) expected.', 'for (a:[1:5],)'],
        ];
    }

    /**
     * Provide invalid definitions of ranges.
     *
     * @return array
     */
    public static function provide_invalid_ranges(): array {
        return [
            ['Syntax error: step size of a range cannot be zero.', 'a = [1:5:0]'],
            ['Syntax error: start and end of range must not be equal.', 'a = [5:5]'],
            ['Syntax error: start and end of range must not be equal.', 'a = [1.0:1]'],
            ['Syntax error in range definition.', 'a = [1:2:3:4]'],
        ];
    }

    /**
     * Provide invalid uses of the colon (range separator).
     *
     * @return array
     */
    public static function provide_invalid_colon(): array {
        return [
            ['Syntax error: invalid use of range separator \':\'.', 'a = (:5)'],
            ['Syntax error: invalid use of range separator \':\'.', 'a = {5:}'],
            ['Syntax error: invalid use of range separator \':\'.', 'a = {:5}'],
            ['Syntax error: invalid use of range separator \':\'.', 'a = [5:]'],
            ['Syntax error: invalid use of range separator \':\'.', 'a = [:5]'],
            ['Syntax error: invalid use of range separator \':\'.', 'a = [5::7]'],
            ['Syntax error: invalid use of range separator \':\'.', 'a = [3,:7]'],
            ['Syntax error: invalid use of range separator \':\'.', 'a = [3:,7]'],
            ['Syntax error: ternary operator missing middle part.', 'a = 5 ?: 3'],
            ['Syntax error: ranges can only be used in {} or [].', 'a = 5 : 3'],
        ];
    }

    /**
     * Provide various invalid assignments.
     *
     * @return array
     */
    public static function provide_invalid_assignments(): array {
        return [
            'assign list of strings to algebraic variable' => [
                'Algebraic variables can only be initialized with a list of numbers.',
                'x = {"a", "b"}',
            ],
            'assign mixed values to algebraic variable' => [
                'Algebraic variables can only be initialized with a list of numbers.',
                'x = {1, 2, "foo"}',
            ],
            'assign numeric string to algebraic variable' => [
                'Algebraic variables can only be initialized with a list of numbers.',
                'x = {"1", 2}',
            ],
            'assign nested list to algebraic variable' => [
                'Algebraic variables can only be initialized with a list of numbers.',
                'x = {1, 2, [1, 2]}',
            ],
            'trying to change char of string' => [
                'Individual chars of a string cannot be modified.',
                's = "foo"; s[1] = "x"',
            ],
            'assignment with invalid function' => [
                "Unknown function: 'idontexist'",
                'a = \idontexist(5)',
            ],
            'assignment to constant' => [
                'Left-hand side of assignment must be a variable.',
                'pi = 3',
            ],
            'assignment to constant, 2' => [
                'Left-hand side of assignment must be a variable.',
                'π = 3',
            ],
            'invalid use of prefix with number' => [
                'Syntax error: invalid use of prefix character \.',
                'a = \ 2',
            ],
            'invalid argument for unary operator' => [
                "Number expected, found 'foo'.",
                'a = -"foo"',
            ],
            'invalid argument for unary operator, indirect' => [
                "Number expected, found 'foo'.",
                's = "foo"; a = -s',
            ],
            'invalid use of prefix with paren' => [
                'Syntax error: invalid use of prefix character \.',
                'a = \ (3 + 1)',
            ],
            'assignment to invalid variable' => [
                '1:1:Invalid variable name: _a.',
                '_a=3;',
            ],
            'unknown char in expression' => [
                "1:4:Unexpected input: '«'",
                'a=3«6;',
            ],
            'not subscriptable' => [
                '1:8:Evaluation error: indexing is only possible with lists and strings.',
                'f=1; g=f[1];',
            ],
            'invalid index: array' => [
                'Evaluation error: only one index supported when accessing array elements.',
                'e=[1,2,3][4,5];',
            ],
            'invalid index: array (indirect)' => [
                'Evaluation error: only one index supported when accessing array elements.',
                'e=[1,2,3]; f=e[4,5]',
            ],
            'multiply array with number' => [
                '1:16:Number expected, found list.',
                'e=[1,2,3,4]; f=e*2;',
            ],
            'multiple indices for array' => [
                '1:18:Evaluation error: only one index supported when accessing array elements.',
                'e=[1,2,3][1][4,5,6][2];',
            ],
            'fill with count == 0' => [
                '1:3:fill() expects its first argument to be a positive integer.',
                'c=fill(0,"rr")',
            ],
            'undefined natrual logarithm' => [
                'ln() expects its argument to be a positive number.',
                'x=ln(-5)',
            ],
            'undefined natrual logarithm, 2' => [
                'ln() expects its argument to be a positive number.',
                'x=ln(0)',
            ],
            'closing parenthesis when not opened' => [
                "1:7:Unbalanced parenthesis, stray ')' found.",
                's=fill);',
            ],
            'opening parenthesis not closed' => [
                "1:7:Unbalanced parenthesis, '(' is never closed.",
                's=fill(10,"rr";',
            ],
            'invalid invocation of concat(), number' => [
                "1:3:concat() expects its arguments to be lists.",
                's=concat(0, [1,2,3], [5,6], 100);',
            ],
            'invalid for loop: no variable' => [
                '1:12:Syntax error: identifier expected.',
                'z = 0; for(: [0:5]) z=z+i;',
            ],
            'invalid for loop: no list' => [
                '1:14:Syntax error: [ or variable name expected.',
                'z = 0; for(i:) z=z+i;',
            ],
            'invalid for loop: no statement or brace' => [
                'Syntax error: { or statement expected.',
                'z = 0; for(i: [0:5]) ',
            ],
            'invalid for loop: missing colon in nested loop' => [
                '1:28:Syntax error: : expected.',
                'z = 0; for(i: [0:5]) for(j [0:3]) z=z+i;',
            ],
            'invalid invocation of diff(), mismatching lengths' => [
                '1:3:diff() expects two lists of the same size.',
                's=diff([3*3+3,0],[3*4]);',
            ],
            'algebraic variable used in calculation' => [
                "1:21:Algebraic variable 'b' cannot be used in this context.",
                'a = 7; b = {1:5}; 2*b',
            ],
            'invalid ternary, ? is last char before closing paren' => [
                "Syntax error: incomplete ternary operator or misplaced '?'.",
                'a = (5 ?)',
            ],
            'invalid ternary, ? is last char before closing brace' => [
                "Syntax error: incomplete ternary operator or misplaced '?'.",
                'a = {5 ?}',
            ],
            'invalid ternary, ? is last char before closing bracket' => [
                "Syntax error: incomplete ternary operator or misplaced '?'.",
                'a = [5 ?]',
            ],
            'invalid ternary, ? is last char before closing bracket, 2' => [
                'Evaluation error: not enough arguments for ternary operator: 2.',
                '(5 ? 4 :)',
            ],
            'invalid ternary, ? is last char before closing bracket, 3' => [
                'Evaluation error: not enough arguments for ternary operator.',
                'a = (5 ? 4 :)',
            ],
            'argument should be scalar, is list' => [
                'Evaluation error: numeric value expected, got list.',
                'a = [1, 2, 3] + 4',
            ],
            'argument should be scalar, is list, 2' => [
                'Scalar value expected, found list.',
                'a = "a" + [1, 2, 3]',
            ],
            'invalid 0^0' => [
                'Power 0^0 is not defined.',
                'a = 0 ** 0',
            ],
            'invalid power: 0 to negative power' => [
                'Division by zero is not defined, so base cannot be zero for negative exponents.',
                'a = 0 ** -1',
            ],
            'invalid power: negative base with fractional exponent' => [
                'Base cannot be negative with fractional exponent.',
                'a = (-1) ** 0.5',
            ],
            'array in algebraic variable' => [
                'Algebraic variables can only be initialized with a list of numbers.',
                'a = {[1:5],[20:25],[40:50:2]}',
            ],
            'string after string' => [
                'Syntax error: did you forget to put an operator?',
                'a = "foo" "bar"',
            ],
            'number after string' => [
                'Syntax error: did you forget to put an operator?',
                'a = "foo" 4',
            ],
            'string after number' => [
                'Syntax error: did you forget to put an operator?',
                'a = 4 "foo"',
            ],
        ];
    }

    /**
     * Provide invalid expressions trying to access list elements or chars of a string.
     *
     * @return array
     */
    public static function provide_invalid_indices(): array {
        return [
            ["index should be an integer, found 'foo'.", 's = "string"; a = s["foo"];'],
            ["index should be an integer, found 'foo'.", 'a = [1, 2, 3, 4]; b = a["foo"];'],
            ["index should be an integer, found '1.5'.", 's = "string"; a = s[1.5];'],
            ["index should be an integer, found '1.5'.", 'a = [1, 2, 3, 4]; b = a[1.5];'],
            ['index 4 out of range.', 'a = [1,2,3,4][4]'],
            ['index 4 out of range.', 'a = "abcd"[4]'],
            ['Syntax error: did you forget to put an operator?', 'a = 15[2]'],
            ['index 4 out of range.', 'a = "abcd"; b = a[4];'],
            ['index 4 out of range.', 'a = [1, 2, 3, 4]; b = a[4];'],
            ['indexing is only possible with lists and strings.', 'a = 15; b = a[2];'],
        ];
    }

    /**
     * Provide various invalid expressions.
     *
     * @return array
     */
    public static function provide_other_invalid_stuff(): array {
        return [
            ['1:7:Unexpected token: ,', 'a = 15,2'],
            ['1:9:Syntax error: sets cannot be nested.', 'a = {1, {2, 3}}'],
            ['1:9:Syntax error: sets cannot be used inside a list.', 'a = [1, {2, 3}]'],
            ['1:6:Invalid use of unary operator: !.', 'a = 1!2'],
            ['1:6:Invalid use of unary operator: ~.', 'a = 1~2'],
            [
                '1:8:Unknown error while applying operator **, result was (positive or negative) infinity or not a number (NAN).',
                'a = 99 ** 999',
            ],
        ];
    }

    /**
     * Test various invalid expressions.
     *
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
     * Provide various (valid and invalid) algebraic expressions.
     *
     * @return array
     */
    public static function provide_algebraic_expressions(): array {
        // Note: The test function will have the algebraic variable x, set to the value 4.
        return [
            ["'' is not a valid algebraic expression.", ''],
            ["'y=2' is not a valid algebraic expression.", 'y=2'],
            ["'4==x' is not a valid algebraic expression.", '4==x'],
            [0, '0'],
            [2, 'sqrt(4)'],
            [4, 'x'],
            [16, 'x^2'],
            [12, '3x'],
        ];
    }

    /**
     * Test evaluation of algebraic formulas.
     *
     * @dataProvider provide_algebraic_expressions
     */
    public function test_calculate_algebraic_expression($expected, $input): void {
        $error = '';
        $result = null;
        try {
            $parser = new parser('x={4};');
            $statements = $parser->get_statements();
            $evaluator = new evaluator();
            $evaluator->evaluate($statements);
            $result = $evaluator->calculate_algebraic_expression($input);
        } catch (Exception $e) {
            $error = $e->getMessage();
        }

        if (is_string($expected)) {
            self::assertStringEndsWith($expected, $error);
        } else {
            self::assertEmpty($error);
            self::assertEquals($expected, $result->value);
        }
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
            self::assertStringContainsString(
                'Stack should contain exactly one element after evaluation - did you forget a semicolon somewhere?',
                $e->getMessage()
            );
        }
        self::assertNotNull($e);
    }

    /**
     * Test splitting of number and unit as entered in a combined answer box.
     *
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

    public function test_strange_unit_split(): void {
        $input = '3exp^2';

        // Define 'exp' as a known variable.
        $parser = new answer_parser($input, ['exp']);
        $index = $parser->find_start_of_units();
        $number = substr($input, 0, $index);
        $unit = substr($input, $index);

        self::assertEquals('3', trim($number));
        self::assertEquals('exp^2', $unit);
    }

    /**
     * Provide combined responses with numbers and units to test splitting.
     *
     * @return array
     */
    public static function provide_numbers_and_units(): array {
        return [
            'missing unit' => [['123', ''], '123'],
            'missing number' => [['', 'm/s'], 'm/s'],
            'length 1' => [['100', 'm'], '100 m'],
            'length 2' => [['100', 'cm'], '100cm'],
            'length 3' => [['1.05', 'mm'], '1.05 mm'],
            'length 4' => [['-1.3', 'nm'], '-1.3 nm'],
            'area 1' => [['-7.5e-3', 'm^2'], '-7.5e-3 m^2'],
            'area 2' => [['6241509.47e6', 'MeV'], '6241509.47e6 MeV'],
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

            'old unit tests, 18' => [['', 'm/s'], 'm/s'],
            'old unit tests, 20' => [['sin(3)', 'kg m/s'], 'sin(3)kg m/s'],

            [['3.1e-10', 'kg m/s'], '3.1e-10kg m/s'],
            [['-3', 'kg m/s'], '-3kg m/s'],
            [['- 3', 'kg m/s'], '- 3kg m/s'],
            [['3', 'e'], '3e'],
            [['3e8', ''], '3e8'],
            [['3e8', 'e'], '3e8e'],

            [['sin(3)', 'kg m/s'], 'sin(3)kg m/s'],
            [['3*4*5', 'kg m/s'], '3*4*5 kg m/s'],

            [['3e8(4.e8+2)(.5e8/2)5', 'kg m/s'], '3e8(4.e8+2)(.5e8/2)5kg m/s'],
            [['3+exp(4+5)^sin(6+7)', 'kg m/s'], '3+exp(4+5)^sin(6+7)kg m/s'],
            [['3+exp(4+5)^-sin(6+7)', 'kg m/s'], '3+exp(4+5)^-sin(6+7)kg m/s'],

            [['3', 'e8'], '3 e8'],
            [['3', 'e 8'], '3e 8'],
            [['3e8', 'e8'], '3e8e8'],
            [['3e8', 'e8e8'], '3e8e8e8'],

            [['3 /', 's'], '3 /s'],
            [['3', 'm+s'], '3 m+s'],
            [['', 'a=b'], 'a=b'],

            [['3 4 5', 'm/s'], '3 4 5 m/s'],
            [['3+4 5+10^4', 'kg m/s'], '3+4 5+10^4kg m/s'],
            [['3+4 5+10^4', 'kg m/s'], '3+4 5+10^4kg m/s'],
            [['3 4 5', 'kg m/s'], '3 4 5 kg m/s'],
        ];
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
            self::assertEquals('Invalid variable context given, aborting import.', $e->getMessage());
        }
        self::assertNotNull($e);
    }

    /**
     * Provide answers of answer type number.
     *
     * @return array
     */
    public static function provide_numbers(): array {
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

    /**
     * Provide answers of answer type numeric.
     *
     * @return array
     */
    public static function provide_numeric_answers(): array {
        return [
            [7, '3+4'],
            [-1, '3-4'],
            [23, '3+4*5'],
            [35, '(3+4)*5'],
            [.75, '3/4'],
            [sqrt(2), '2**(1/2)'],
            [60, '3 4 5'],
            [3.004, '3+10*4/10^4'],
            [false, '3x'],
            [false, '3*x'],
            [false, '\\'],
            [false, '\sin(3)'],
            [false, 'sin(3)'],
            [false, '3+exp(4)'],
        ];
    }

    /**
     * Provide answers of answer type numerical formula.
     *
     * @return array
     */
    public static function provide_numerical_formulas(): array {
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
            [60, '3 4 5'],
            [6e24, '3e8 4.e8 .5e8'],
            [false, '3 e10'],
            [false, '3e 10'],
            [false, '3e8e8'],
            [false, '3e8e8e8'],
            [false, '\ 3'],
            [false, '\sin(3)'],
        ];
    }

    /**
     * Provide expressions involving e as a variable and as EE symbol.
     *
     * @return Generator
     */
    public static function provide_inputs_for_exponential_versus_e(): Generator {
        yield [
            'expected' => [
                '3e4' => 3e4,
                '3e4e4' => 'Unknown variable: e4',
                '3e4e4e4' => 'Unknown variable: e4e4',
            ],
            'vars' => '',
        ];
        yield [
            'expected' => [
                '3e4' => 3e4,
                '3e4e4' => 3e4 * 9,
                '3e4e4e4' => 'Unknown variable: e4e4',
            ],
            'vars' => 'e4 = 9;',
        ];
        yield [
            'expected' => [
                '3e4' => 3e4,
                '3e4e4' => 'Unknown variable: e4',
                '3e4e4e4' => 3e4 * 9,
            ],
            'vars' => 'e4e4 = 9;',
        ];
        yield [
            'expected' => [
                '3e4' => 3e4,
                '3e4e4' => 3e4 * 17,
                '3e4e4e4' => 3e4 * 9,
            ],
            'vars' => 'e4 = 17; e4e4 = 9;',
        ];
    }

    /**
     * Test correct interpretation of e as exponential (EE) or variable e.
     *
     * @dataProvider provide_inputs_for_exponential_versus_e
     */
    public function test_exponential_versus_variable_e_precedence($expected, $vars): void {
        // First step: prepare evaluator with the desired variable context.
        $parser = new parser($vars);
        $evaluator = new evaluator();
        $evaluator->evaluate($parser->get_statements());

        foreach ($expected as $input => $output) {
            // Make sure we start with a clean copy for every variant.
            $localevaluator = clone $evaluator;
            $parser = new parser($input);
            $error = null;
            try {
                $result = $localevaluator->evaluate($parser->get_statements())[0];
            } catch (Exception $e) {
                $error = $e->getMessage();
            }

            // If we expect an error message, check whether it is the right one.
            if (is_string($output)) {
                self::assertStringEndsWith($output, $error);
                continue;
            }

            // Otherwise, check the result *and* make sure there was no error.
            self::assertEquals($output, $result->value);
            self::assertNull($error);
        }
    }

    /**
     * Provide answers for answer type algebraic formula.
     *
     * @return array
     */
    public static function provide_algebraic_formulas(): array {
        return [
            [true, 'sin(a)-a+exp(b)'],
            [true, '- 3'],
            [true, '3e 10'],
            [true, 'sin(3)-3+exp(4)'],
            [true, '3e8 4.e8 .5e8'],
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
            // Note: the following is syntactically valid, but cannot be evaluated, because e10 is not a known variable.
            [false, '3 e10'],
            // Note: the following is syntactically valid, but cannot be evaluated, because e8 is not a known variable.
            [false, '3e8e8'],
            // Note: the following is syntactically valid, but cannot be evaluated, because e8e8 is not a known variable.
            [false, '3e8e8e8'],
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
     * Test evaluation of responses given as algebraic formulas.
     *
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
            // In such a case, is_acceptable_number() will fail, as expected.
            $parser = new answer_parser('');
        }

        // If we expect the expression to be valid, is must pass this first test.
        $isvalidsyntax = $parser->is_acceptable_for_answertype(qtype_formulas::ANSWER_TYPE_ALGEBRAIC);
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
     * Test evaluation of responses given as numerical formulas.
     *
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
            // In such a case, is_acceptable_number() will fail, as expected.
            if (!isset($parser)) {
                $parser = new answer_parser('');
            }
        }

        if ($expected === false) {
            self::assertFalse($parser->is_acceptable_for_answertype(qtype_formulas::ANSWER_TYPE_NUMERICAL_FORMULA));
            return;
        }

        self::assertTrue($parser->is_acceptable_for_answertype(qtype_formulas::ANSWER_TYPE_NUMERICAL_FORMULA));
        self::assertIsArray($result);
        self::assertEquals(1, count($result));
        self::assertEqualsWithDelta($expected, $result[0]->value, 1e-8);
    }

    /**
     * Test evaluation of responses given as numeric expressions.
     *
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
            // In such a case, is_acceptable_number() will fail, as expected.
            if (!isset($parser)) {
                $parser = new answer_parser('');
            }
        }

        if ($expected === false) {
            self::assertFalse($parser->is_acceptable_for_answertype(qtype_formulas::ANSWER_TYPE_NUMERIC));
            return;
        }

        self::assertTrue($parser->is_acceptable_for_answertype(qtype_formulas::ANSWER_TYPE_NUMERIC));
        self::assertIsArray($result);
        self::assertEquals(1, count($result));
        self::assertEqualsWithDelta($expected, $result[0]->value, 1e-8);
    }

    /**
     * Test evaluation of responses given as numbers.
     *
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
            // In such a case, is_acceptable_number() will fail, as expected.
            if (!isset($parser)) {
                $parser = new answer_parser('');
            }
        }

        if ($expected === false) {
            self::assertFalse($parser->is_acceptable_for_answertype(qtype_formulas::ANSWER_TYPE_NUMBER));
            return;
        }

        self::assertTrue($parser->is_acceptable_for_answertype(qtype_formulas::ANSWER_TYPE_NUMBER));
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
            self::assertStringEndsWith(
                'Evaluation error: empty stack - did you pass enough arguments for the function or operator?',
                $e->getMessage()
            );
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
            self::assertStringEndsWith("Undefined constant: 'foo'", $e->getMessage());
        }
        self::assertNotNull($e);

        // The function evaluator::evaluate() must be called with a for_loop, an expression
        // or a list thereof.
        $e = null;
        try {
            $evaluator->evaluate(['foo', 'bar']);
        } catch (Exception $e) {
            self::assertStringEndsWith('Bad invocation of evaluate_the_right_thing().', $e->getMessage());
        }
        self::assertNotNull($e);
        $e = null;
        try {
            $evaluator->evaluate('foo');
        } catch (Exception $e) {
            self::assertStringEndsWith('Bad invocation of evaluate().', $e->getMessage());
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
            self::assertStringEndsWith('Evaluation error: not enough arguments for ternary operator.', $e->getMessage());
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
            self::assertStringEndsWith('Evaluation error: not enough arguments for ternary operator.', $e->getMessage());
        }
        self::assertNotNull($e);

    }

}
