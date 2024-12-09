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
 * Unit tests for the Formulas question plugin's evaluator class,
 * focused on the invocation and implementation of functions.
 *
 * @package    qtype_formulas
 * @copyright  2022 Philipp Imhof
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace qtype_formulas;

use qtype_formulas\variables;
use qtype_formulas\parser;
use qtype_formulas\evaluator;
use Exception;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/question/engine/tests/helpers.php');

/**
 * Unit tests for various functions
 *
 * @copyright  2022 Philipp Imhof
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

class functions_test extends \advanced_testcase {

    public function provide_cases_for_assure_numeric(): array {
        $message = 'error message';

        return [
            // Test with a positive real integer.
            [1.0, ['value' => 1, 'message' => $message, 'conditions' => functions::NONE]],
            [1, ['value' => 1, 'message' => $message, 'conditions' => functions::INTEGER]],
            [1.0, ['value' => 1, 'message' => $message, 'conditions' => functions::NON_NEGATIVE]],
            [1, ['value' => 1, 'message' => $message, 'conditions' => functions::NON_NEGATIVE | functions::INTEGER]],
            [1.0, ['value' => 1, 'message' => $message, 'conditions' => functions::NON_ZERO]],
            [1, ['value' => 1, 'message' => $message, 'conditions' => functions::NON_ZERO | functions::INTEGER]],
            [$message, ['value' => 1, 'message' => $message, 'conditions' => functions::NEGATIVE]],
            [$message, ['value' => 1, 'message' => $message, 'conditions' => functions::NEGATIVE | functions::INTEGER]],
            [1.0, ['value' => 1, 'message' => $message, 'conditions' => functions::POSITIVE]],
            [1, ['value' => 1, 'message' => $message, 'conditions' => functions::POSITIVE | functions::INTEGER]],
            // Test with zero.
            [0.0, ['value' => 0, 'message' => $message, 'conditions' => functions::NONE]],
            [0, ['value' => 0, 'message' => $message, 'conditions' => functions::INTEGER]],
            [0.0, ['value' => 0, 'message' => $message, 'conditions' => functions::NON_NEGATIVE]],
            [0, ['value' => 0, 'message' => $message, 'conditions' => functions::NON_NEGATIVE | functions::INTEGER]],
            [$message, ['value' => 0, 'message' => $message, 'conditions' => functions::NON_ZERO]],
            [$message, ['value' => 0, 'message' => $message, 'conditions' => functions::NON_ZERO | functions::INTEGER]],
            [$message, ['value' => 0, 'message' => $message, 'conditions' => functions::NEGATIVE]],
            [$message, ['value' => 0, 'message' => $message, 'conditions' => functions::NEGATIVE | functions::INTEGER]],
            [$message, ['value' => 0, 'message' => $message, 'conditions' => functions::POSITIVE]],
            [$message, ['value' => 0, 'message' => $message, 'conditions' => functions::POSITIVE | functions::INTEGER]],
            // Test with a negative real integer.
            [-1.0, ['value' => -1, 'message' => $message, 'conditions' => functions::NONE]],
            [-1, ['value' => -1, 'message' => $message, 'conditions' => functions::INTEGER]],
            [$message, ['value' => -1, 'message' => $message, 'conditions' => functions::NON_NEGATIVE]],
            [$message, ['value' => -1, 'message' => $message, 'conditions' => functions::NON_NEGATIVE | functions::INTEGER]],
            [-1.0, ['value' => -1, 'message' => $message, 'conditions' => functions::NON_ZERO]],
            [-1, ['value' => -1, 'message' => $message, 'conditions' => functions::NON_ZERO | functions::INTEGER]],
            [-1.0, ['value' => -1, 'message' => $message, 'conditions' => functions::NEGATIVE]],
            [-1, ['value' => -1, 'message' => $message, 'conditions' => functions::NEGATIVE | functions::INTEGER]],
            [$message, ['value' => -1, 'message' => $message, 'conditions' => functions::POSITIVE]],
            [$message, ['value' => -1, 'message' => $message, 'conditions' => functions::POSITIVE | functions::INTEGER]],
            // Test with an integer-valued float.
            [1.0, ['value' => 1.0, 'message' => $message, 'conditions' => functions::NONE]],
            [1, ['value' => 1.0, 'message' => $message, 'conditions' => functions::INTEGER]],
            [1.0, ['value' => 1.0, 'message' => $message, 'conditions' => functions::NON_NEGATIVE]],
            [1, ['value' => 1.0, 'message' => $message, 'conditions' => functions::NON_NEGATIVE | functions::INTEGER]],
            [1.0, ['value' => 1.0, 'message' => $message, 'conditions' => functions::NON_ZERO]],
            [1, ['value' => 1.0, 'message' => $message, 'conditions' => functions::NON_ZERO | functions::INTEGER]],
            [$message, ['value' => 1.0, 'message' => $message, 'conditions' => functions::NEGATIVE]],
            [$message, ['value' => 1.0, 'message' => $message, 'conditions' => functions::NEGATIVE | functions::INTEGER]],
            [1.0, ['value' => 1.0, 'message' => $message, 'conditions' => functions::POSITIVE]],
            [1, ['value' => 1.0, 'message' => $message, 'conditions' => functions::POSITIVE | functions::INTEGER]],
            // Test with a non-integer-valued float.
            [1.5, ['value' => 1.5, 'message' => $message, 'conditions' => functions::NONE]],
            [$message, ['value' => 1.5, 'message' => $message, 'conditions' => functions::INTEGER]],
            [1.5, ['value' => 1.5, 'message' => $message, 'conditions' => functions::NON_NEGATIVE]],
            [$message, ['value' => 1.5, 'message' => $message, 'conditions' => functions::NON_NEGATIVE | functions::INTEGER]],
            [1.5, ['value' => 1.5, 'message' => $message, 'conditions' => functions::NON_ZERO]],
            [$message, ['value' => 1.5, 'message' => $message, 'conditions' => functions::NON_ZERO | functions::INTEGER]],
            [$message, ['value' => 1.5, 'message' => $message, 'conditions' => functions::NEGATIVE]],
            [$message, ['value' => 1.5, 'message' => $message, 'conditions' => functions::NEGATIVE | functions::INTEGER]],
            [1.5, ['value' => 1.5, 'message' => $message, 'conditions' => functions::POSITIVE]],
            [$message, ['value' => 1.5, 'message' => $message, 'conditions' => functions::POSITIVE | functions::INTEGER]],
            // Test with non-numeric input or empty string.
            [$message, ['value' => 'a', 'message' => $message, 'conditions' => functions::NONE]],
            [$message, ['value' => [1], 'message' => $message, 'conditions' => functions::NONE]],
            [$message, ['value' => [1, 2], 'message' => $message, 'conditions' => functions::NONE]],
            [$message, ['value' => [], 'message' => $message, 'conditions' => functions::NONE]],
            [$message, ['value' => ['a', 'b'], 'message' => $message, 'conditions' => functions::NONE]],
            [$message, ['value' => '', 'message' => $message, 'conditions' => functions::NONE]],
            // Test with numeric string.
            [1.0, ['value' => ' 1', 'message' => $message, 'conditions' => functions::NONE]],
            [1, ['value' => '1 ', 'message' => $message, 'conditions' => functions::INTEGER]],
            [1.0, ['value' => '1.0 ', 'message' => $message, 'conditions' => functions::NON_NEGATIVE]],
            [1, ['value' => ' 1.0', 'message' => $message, 'conditions' => functions::NON_NEGATIVE | functions::INTEGER]],
            [-1.0, ['value' => '-1', 'message' => $message, 'conditions' => functions::NON_ZERO]],
            [-1, ['value' => ' -1', 'message' => $message, 'conditions' => functions::NON_ZERO | functions::INTEGER]],
            [-1.0, ['value' => '-1 ', 'message' => $message, 'conditions' => functions::NEGATIVE]],
            [$message, ['value' => '0', 'message' => $message, 'conditions' => functions::NEGATIVE | functions::INTEGER]],
            [$message, ['value' => ' 0', 'message' => $message, 'conditions' => functions::POSITIVE]],
            [$message, ['value' => '0 ', 'message' => $message, 'conditions' => functions::POSITIVE | functions::INTEGER]],
        ];
    }

    /**
     * @dataProvider provide_cases_for_assure_numeric
     */
    public function test_assure_numeric($expected, $input): void {
        $result = NAN;
        try {
            $result = functions::assure_numeric($input['value'], $input['message'], $input['conditions']);
        } catch (Exception $e) {
            self::assertEquals($expected, $e->getMessage());
            return;
        }

        self::assertEquals($expected, $result);
        self::assertEquals(gettype($expected), gettype($result));
    }

    public function provide_normcdf(): array {
        return [
            [0.841344746068543, 'normcdf(12, 5, 7)'],
            [0.990184671371355, 'normcdf(135, 100, 15)'],
            [0.246921118121914, 'normcdf(10, 23, 19)'],
            [0.022750131948179, 'normcdf(-4, 2, 3)'],
        ];
    }

    public function provide_stdnormcdf(): array {
        return [
            [2.866515718791946e-7, 'stdnormcdf(-5)'],
            [0.00134989803163009452665, 'stdnormcdf(-3)'],
            [0.022750131948179207200, 'stdnormcdf(-2)'],
            [0.158655253931457051415, 'stdnormcdf(-1)'],
            [0.3085375387259868963623, 'stdnormcdf(-0.5)'],
            [0.5, 'stdnormcdf(0)'],
            [0.6914624612740131036377, 'stdnormcdf(0.5)'],
            [0.841344746068542948585, 'stdnormcdf(1)'],
            [0.9772498680518207927997, 'stdnormcdf(2)'],
            [0.99865010196836990547335, 'stdnormcdf(3)'],
            [0.9999997133484281208060883, 'stdnormcdf(5)'],
            [0.9999999999999999999999999, 'stdnormcdf(10)'],
            [0.9999999999999999999999999, 'stdnormcdf(20)'],
        ];
    }

    public function provide_stdnormpdf(): array {
        return [
            [0.398942280401432677939946, 'stdnormpdf(0)'],
            [0.241970724519143349797830, 'stdnormpdf(1)'],
            [0.241970724519143349797830, 'stdnormpdf(-1)'],
            [0.0539909665131880519505642, 'stdnormpdf(2)'],
            [0.0539909665131880519505642, 'stdnormpdf(-2)'],
            [0.3520653267642994777746804, 'stdnormpdf(0.5)'],
            [0.3520653267642994777746804, 'stdnormpdf(-0.5)'],
        ];
    }

    public function provide_ncr(): array {
        return [
            [1, 'ncr(0, 0)'],
            [0, 'ncr(1, 5)'],
            [1, 'ncr(5, 0)'],
            [5, 'ncr(5, 1)'],
            [10, 'ncr(5, 2)'],
            [10, 'ncr(5, 3)'],
            [5, 'ncr(5, 4)'],
            [1, 'ncr(5, 5)'],
            [0, 'ncr(5, 6)'],
            [0, 'ncr(-1, 2)'],
            [0, 'ncr(3, -4)'],
            ['ncr() expects its first argument to be an integer', 'ncr(10.5, 3)'],
            ['ncr() expects its second argument to be an integer', 'ncr(12, 3.5)'],
        ];
    }

    public function provide_npr(): array {
        return [
            [1, 'npr(0, 0)'],
            [0, 'npr(1, 5)'],
            [1, 'npr(5, 0)'],
            [5, 'npr(5, 1)'],
            [20, 'npr(5, 2)'],
            [60, 'npr(5, 3)'],
            [120, 'npr(5, 4)'],
            [120, 'npr(5, 5)'],
            [0, 'npr(5, 6)'],
            ['npr() expects its first argument to be a non-negative integer', 'npr(-1, 2)'],
            ['npr() expects its second argument to be a non-negative integer', 'npr(3, -4)'],
            ['npr() expects its first argument to be a non-negative integer', 'npr(10.5, 3)'],
            ['npr() expects its second argument to be a non-negative integer', 'npr(12, 3.5)'],
        ];
    }

    public function provide_fact(): array {
        return [
            [1, 'fact(0)'],
            [1, 'fact(1)'],
            [2, 'fact(2)'],
            [6, 'fact(3)'],
            [720, 'fact(6)'],
            ['the factorial function expects its first argument to be a non-negative integer', 'fact(-2)'],
            ['the factorial function expects its first argument to be a non-negative integer', 'fact(2.5)'],
            ['cannot compute 250! on this platform, the result is bigger than PHP_MAX_INT', 'fact(250)'],
        ];
    }

    public function provide_binomialpdf(): array {
        return [
            [0, 'binomialpdf(1, 0, 1)'],
            [1, 'binomialpdf(1, 0, 0)'],
            [1, 'binomialpdf(1, 1, 1)'],
            [0, 'binomialpdf(1, 1, 0)'],
            [0, 'binomialpdf(10, 1, 20)'],
            [0.125, 'binomialpdf(3, 0.5, 0)'],
            [0.375, 'binomialpdf(3, 0.5, 1)'],
            [0.375, 'binomialpdf(3, 0.5, 2)'],
            [0.125, 'binomialpdf(3, 0.5, 3)'],
            ['binomialpdf() expects the probability to be at least 0 and not more than 1', 'binomialpdf(10, 3, 5)'],
            ['binomialpdf() expects the probability to be at least 0 and not more than 1', 'binomialpdf(10, -2, 5)'],
        ];
    }

    public function provide_binomialcdf(): array {
        return [
            [1, 'binomialcdf(1, 0, 1)'],
            [1, 'binomialcdf(1, 0, 0)'],
            [1, 'binomialcdf(1, 1, 1)'],
            [0, 'binomialcdf(1, 1, 0)'],
            [1, 'binomialcdf(10, 0.3, 10)'],
            [1, 'binomialcdf(10, 0.3, 20)'],
            [0.125, 'binomialcdf(3, 0.5, 0)'],
            [0.5, 'binomialcdf(3, 0.5, 1)'],
            [0.875, 'binomialcdf(3, 0.5, 2)'],
            [1, 'binomialcdf(3, 0.5, 3)'],
            ['binomialcdf() expects the probability to be at least 0 and not more than 1', 'binomialcdf(10, 3, 5)'],
            ['binomialcdf() expects the probability to be at least 0 and not more than 1', 'binomialcdf(10, -2, 5)'],
        ];
    }

    public function provide_inv(): array {
        return [
            [[0, 1, 2, 3], 'a=inv([0, 1, 2, 3]);'],
            [[1, 2, 3, 4], 'a=inv([1, 2, 3, 4]);'],
            ["invalid number of arguments for function 'inv': 0 given", 'a=inv();'],
            ["invalid number of arguments for function 'inv': 2 given", 'a=inv([1, 2, 3], [4, 5, 6]);'],
            ['inv() expects a list', 'a=inv(1);'],
            ['when using inv(), the numbers in the list must be consecutive', 'a=inv([1, 4, 0]);'],
            ['when using inv(), the smallest number in the list must be 0 or 1', 'a=inv([2, 3, 4]);'],
            ['when using inv(), the list must not contain the same number multiple times', 'a=inv([0, 1, 2, 1]);'],
        ];
    }

    /**
     * @dataProvider provide_ncr
     * @dataProvider provide_npr
     * @dataProvider provide_fact
     * @dataProvider provide_stdnormpdf
     * @dataProvider provide_stdnormcdf
     * @dataProvider provide_normcdf
     * @dataProvider provide_binomialpdf
     * @dataProvider provide_binomialcdf
     *
     */
    public function test_combinatorics($expected, $input) {
        $parser = new parser($input);
        $statements = $parser->get_statements();
        $evaluator = new evaluator();
        try {
            $result = $evaluator->evaluate($statements);
        } catch (Exception $e) {
            self::assertStringEndsWith($expected, $e->getMessage());
            return;
        }

        self::assertEqualsWithDelta($expected, end($result)->value, 1e-12);
    }

    public function provide_len_inputs(): array {
        return [
            [0, 'len([])'],
            [1, 'len([[]])'],
            [3, 'len("foo")'],
            [4, 'len([1, 2, 3, 4])'],
            [4, 'len(["1", "2", "3", "4"])'],
            [2, 'len([["1", "2"], ["3", "4"]])'],
            ["invalid number of arguments for function 'len': 0 given", 'len()'],
            ['len() expects a list or a string', 'len(3)'],
        ];
    }

    /**
     * @dataProvider provide_len_inputs
     */
    public function test_len($expected, $input) {
        $parser = new parser($input);
        $statements = $parser->get_statements();
        $evaluator = new evaluator();
        try {
            $result = $evaluator->evaluate($statements);
        } catch (Exception $e) {
            self::assertStringEndsWith($expected, $e->getMessage());
            return;
        }

        self::assertEqualsWithDelta($expected, end($result)->value, 1e-12);
    }

    public function provide_sum_inputs(): array {
        return [
            [0, 'sum([])'],
            [10, 'sum([1, 2, 3, 4])'],
            [10, 'sum(["1", "2", "3", "4"])'],
            ["invalid number of arguments for function 'sum': 0 given", 'sum()'],
            ['sum() expects a list of numbers', 'sum("a")'],
            ['sum() expects a list of numbers', 'sum(3)'],
            ['sum() expects a list of numbers', 'sum(["a", "b"])'],
        ];
    }

    /**
     * @dataProvider provide_sum_inputs
     */
    public function test_sum($expected, $input) {
        $parser = new parser($input);
        $statements = $parser->get_statements();
        $evaluator = new evaluator();
        try {
            $result = $evaluator->evaluate($statements);
        } catch (Exception $e) {
            self::assertStringEndsWith($expected, $e->getMessage());
            return;
        }

        self::assertEqualsWithDelta($expected, end($result)->value, 1e-12);
    }

    public function provide_gcd_inputs(): array {
        return [
            [0, 'gcd(0, 0)'],
            [13, 'gcd(13, 13)'],
            [1, 'gcd(1, 0)'],
            [10, 'gcd(10, 0)'],
            [10, 'gcd(0, 10)'],
            [1, 'gcd(3, 2)'],
            [3, 'gcd(6, 3)'],
            [3, 'gcd(12, 9)'],
            [1, 'gcd(2, 3)'],
            [3, 'gcd(3, 6)'],
            [3, 'gcd(9, 12)'],
            [3, 'gcd(-9, 12)'],
            [3, 'gcd(9, -12)'],
            [2, 'gcd(-10, -12)'],
            ['gcd() expects its first argument to be an integer', 'gcd(1.5, 3)'],
            ['gcd() expects its second argument to be an integer', 'gcd(9, 4.5)'],
        ];
    }

    public function provide_lcm_inputs(): array {
        return [
            [0, 'lcm(0, 0)'],
            [13, 'lcm(13, 13)'],
            [0, 'lcm(1, 0)'],
            [0, 'lcm(10, 0)'],
            [0, 'lcm(0, 10)'],
            [6, 'lcm(3, 2)'],
            [6, 'lcm(6, 3)'],
            [36, 'lcm(12, 9)'],
            [6, 'lcm(2, 3)'],
            [6, 'lcm(3, 6)'],
            [36, 'lcm(9, 12)'],
            [36, 'lcm(-9, 12)'],
            [36, 'lcm(9, -12)'],
            [60, 'lcm(-10, -12)'],
            ['lcm() expects its first argument to be an integer', 'lcm(1.5, 3)'],
            ['lcm() expects its second argument to be an integer', 'lcm(9, 4.5)'],
        ];
    }

    /**
     * @dataProvider provide_gcd_inputs
     * @dataProvider provide_lcm_inputs
     */
    public function test_gcd_lcm($expected, $input): void {
        $parser = new parser($input);
        $statements = $parser->get_statements();
        $evaluator = new evaluator();
        try {
            $result = $evaluator->evaluate($statements);
        } catch (Exception $e) {
            self::assertStringEndsWith($expected, $e->getMessage());
            return;
        }

        self::assertEqualsWithDelta($expected, end($result)->value, 1e-12);
    }


    public function provide_pick_inputs(): array {
        return [
            [3, 'pick(3,[0,1,2,3,4,5])'],
            [3, 'pick(3.9,[0,1,2,3,4,5])'],
            [0, 'pick(10,[0,1,2,3,4,5])'],
            [0, 'pick(10.9,[0,1,2,3,4,5])'],
            [3, 'pick(3,0,1,2,3,4,5)'],
            [3, 'pick(3.9,0,1,2,3,4,5)'],
            [0, 'pick(10,0,1,2,3,4,5)'],
            [0, 'pick(10.9,0,1,2,3,4,5)'],
            ['D', 'pick(3,["A","B","C","D","E","F"])'],
            ['D', 'pick(3.9,["A","B","C","D","E","F"])'],
            ['A', 'pick(10.9,["A","B","C","D","E","F"])'],
            ['A', 'pick(10.9,["A","B","C","D","E","F"])'],
            ['D', 'pick(3,"A","B","C","D","E","F")'],
            ['D', 'pick(3.9,"A","B","C","D","E","F")'],
            ['A', 'pick(10,"A","B","C","D","E","F")'],
            ['A', 'pick(10.9,"A","B","C","D","E","F")'],
            [[3, 3], 'pick(3,[0,0],[1,1],[2,2],[3,3],[4,4],[5,5])'],
            [[3, 3], 'pick(3.9,[0,0],[1,1],[2,2],[3,3],[4,4],[5,5])'],
            [[0, 0], 'pick(10,[0,0],[1,1],[2,2],[3,3],[4,4],[5,5])'],
            [[0, 0], 'pick(10.9,[0,0],[1,1],[2,2],[3,3],[4,4],[5,5])'],
            [['D', 'D'], 'pick(3,["A","A"],["B","B"],["C","C"],["D","D"],["E","E"],["F","F"])'],
            [['D', 'D'], 'pick(3.9,["A","A"],["B","B"],["C","C"],["D","D"],["E","E"],["F","F"])'],
            [['A', 'A'], 'pick(10,["A","A"],["B","B"],["C","C"],["D","D"],["E","E"],["F","F"])'],
            [['A', 'A'], 'pick(10.9,["A","A"],["B","B"],["C","C"],["D","D"],["E","E"],["F","F"])'],
            ['pick() expects its first argument to be a number', 'pick("r",[2,3,5,7,11])'],
            ['when called with two arguments, pick() expects the second parameter to be a list', 'pick(2,3)'],
            // The next line was not allowed in older versions.
            [['a', 'b'], 'pick(2,[2,3],[4,5],["a","b"])'],
        ];
    }

    /**
     * @dataProvider provide_pick_inputs
     */
    public function test_pick($expected, $input): void {
        $parser = new parser($input);
        $statements = $parser->get_statements();
        $evaluator = new evaluator();
        try {
            $result = $evaluator->evaluate($statements)[0];
        } catch (Exception $e) {
            self::assertStringEndsWith($expected, $e->getMessage());
            return;
        }

        if ($result->type === token::LIST) {
            foreach ($result->value as $i => $token) {
                self::assertEqualsWithDelta($expected[$i], $token->value, 1e-12);
            }
        } else {
            self::assertEqualsWithDelta($expected, $result->value, 1e-8);
        }
    }

    public function provide_sigfig_expressions(): array {
        return [
            ['0.01', 'sigfig(.012345, 1)'],
            ['0.02', 'sigfig(.019, 1)'],
            ['20', 'sigfig(17.1, 1)'],
            ['-0.01', 'sigfig(-.012345, 1)'],
            ['-0.02', 'sigfig(-.019, 1)'],
            ['-20', 'sigfig(-17.1, 1)'],

            ['0.0123', 'sigfig(.012345, 3)'],
            ['0.01235', 'sigfig(.012345, 4)'],
            ['0.0123450', 'sigfig(.012345, 6)'],
            ['-0.0123', 'sigfig(-.012345, 3)'],
            ['-0.01235', 'sigfig(-.012345, 4)'],
            ['-0.0123450', 'sigfig(-.012345, 6)'],

            ['120', 'sigfig(123.45, 2)'],
            ['123.5', 'sigfig(123.45, 4)'],
            ['123.450', 'sigfig(123.45, 6)'],
            ['-120', 'sigfig(-123.45, 2)'],
            ['-123.5', 'sigfig(-123.45, 4)'],
            ['-123.450', 'sigfig(-123.45, 6)'],

            ['0.005', 'sigfig(.005, 1)'],
            ['0.0050', 'sigfig(.005, 2)'],
            ['0.00500', 'sigfig(.005, 3)'],
            ['-0.005', 'sigfig(-.005, 1)'],
            ['-0.0050', 'sigfig(-.005, 2)'],
            ['-0.00500', 'sigfig(-.005, 3)'],
        ];
    }

    /**
     * Test number conversion functions decbin(), decoct(), octdec() and bindec()
     */
    public function test_number_conversions() {
        // FIXME: short-circuit the test in order to remove variables.php
        // cases have yet to be ported to make use of the new evaluator
        self::assertTrue(true);
        return;
        // Check valid invocations.
        $testcases = array(
            array('a=decbin(1);', array('a' => (object) array('type' => 'n', 'value' => 1))),
            array('a=decbin(0);', array('a' => (object) array('type' => 'n', 'value' => 0))),
            array('a=decbin(3);', array('a' => (object) array('type' => 'n', 'value' => 11))),
            array('a=decbin(10);', array('a' => (object) array('type' => 'n', 'value' => 1010))),
            array('a=decbin(15);', array('a' => (object) array('type' => 'n', 'value' => 1111))),
            array('a=decoct(1);', array('a' => (object) array('type' => 'n', 'value' => 1))),
            array('a=decoct(0);', array('a' => (object) array('type' => 'n', 'value' => 0))),
            array('a=decoct(3);', array('a' => (object) array('type' => 'n', 'value' => 3))),
            array('a=decoct(10);', array('a' => (object) array('type' => 'n', 'value' => 12))),
            array('a=decoct(15);', array('a' => (object) array('type' => 'n', 'value' => 17))),
            array('a=octdec(1);', array('a' => (object) array('type' => 'n', 'value' => 1))),
            array('a=octdec(0);', array('a' => (object) array('type' => 'n', 'value' => 0))),
            array('a=octdec(3);', array('a' => (object) array('type' => 'n', 'value' => 3))),
            array('a=octdec(12);', array('a' => (object) array('type' => 'n', 'value' => 10))),
            array('a=octdec(17);', array('a' => (object) array('type' => 'n', 'value' => 15))),
            array('a=bindec(1);', array('a' => (object) array('type' => 'n', 'value' => 1))),
            array('a=bindec(0);', array('a' => (object) array('type' => 'n', 'value' => 0))),
            array('a=bindec(11);', array('a' => (object) array('type' => 'n', 'value' => 3))),
            array('a=bindec(1010);', array('a' => (object) array('type' => 'n', 'value' => 10))),
            array('a=bindec(1111);', array('a' => (object) array('type' => 'n', 'value' => 15)))
        );
        foreach ($testcases as $case) {
            $qv = new variables;
            $errmsg = null;
            try {
                $v = $qv->vstack_create();
                $result = $qv->evaluate_assignments($v, $case[0]);
            } catch (Exception $e) {
                $errmsg = $e->getMessage();
            }
            self::assertNull($errmsg);
            self::assertEquals($case[1], $result->all);
        }
        // Check invalid invocations.
        $testcases = array(
            array('a=decbin();'),
            array('a=decbin(1, 2);'),
            array('a=decbin("3");'),
            array('a=decbin("a");'),
            array('a=decoct();'),
            array('a=decoct(1, 2);'),
            array('a=decoct("3");'),
            array('a=decoct("a");'),
            array('a=octdec();'),
            array('a=octdec(1, 2);'),
            array('a=octdec("3");'),
            array('a=octdec("a");'),
            array('a=bindec();'),
            array('a=bindec(1, 2);'),
            array('a=bindec("3");'),
            array('a=bindec("a");')
        );
        foreach ($testcases as $case) {
            $qv = new variables;
            $errmsg = null;
            try {
                $v = $qv->vstack_create();
                $result = $qv->evaluate_assignments($v, $case[0]);
            } catch (Exception $e) {
                $errmsg = $e->getMessage();
            }
            self::assertNotNull($errmsg);
        }
    }

    /**
     * Test: incovation of all documented trigonometric / hyberbolic functions
     */
    public function test_invocation_trigonometric() {
        // FIXME: short-circuit the test in order to remove variables.php
        // cases have yet to be ported to make use of the new evaluator
        self::assertTrue(true);
        return;

        $testcases = array(
            array(true, 'a=acos(0.5);'),
            array(false, 'a=acos();'),
            array(false, 'a=acos(1, 2);'),
            array(true, 'a=asin(0.5);'),
            array(false, 'a=asin();'),
            array(false, 'a=asin(1, 2);'),
            array(true, 'a=atan(0.5);'),
            array(false, 'a=atan();'),
            array(false, 'a=atan(1, 2);'),
            array(true, 'a=atan2(1, 2);'),
            array(false, 'a=atan2(1, 2, 3);'),
            array(false, 'a=atan2(1);'),
            array(false, 'a=atan2();'),
            array(true, 'a=cos(0.5);'),
            array(false, 'a=cos();'),
            array(false, 'a=cos(1, 2);'),
            array(true, 'a=sin(0.5);'),
            array(false, 'a=sin();'),
            array(false, 'a=sin(1, 2);'),
            array(true, 'a=tan(0.5);'),
            array(false, 'a=tan();'),
            array(false, 'a=tan(1, 2);'),
            array(true, 'a=acosh(0.5);'),
            array(false, 'a=acosh();'),
            array(false, 'a=acosh(1, 2);'),
            array(true, 'a=asinh(0.5);'),
            array(false, 'a=asinh();'),
            array(false, 'a=asinh(1, 2);'),
            array(true, 'a=atanh(0.5);'),
            array(false, 'a=atanh();'),
            array(false, 'a=atanh(1, 2);'),
            array(true, 'a=cosh(0.5);'),
            array(false, 'a=cosh();'),
            array(false, 'a=cosh(1, 2);'),
            array(true, 'a=sinh(0.5);'),
            array(false, 'a=sinh();'),
            array(false, 'a=sinh(1, 2);'),
            array(true, 'a=tanh(0.5);'),
            array(false, 'a=tanh();'),
            array(false, 'a=tanh(1, 2);'),
        );
        $qv = new variables;
        foreach ($testcases as $case) {
            $errmsg = null;
            try {
                $v = $qv->vstack_create();
                $qv->evaluate_assignments($v, $case[1]);
            } catch (Exception $e) {
                $errmsg = $e->getMessage();
            }
            if ($case[0]) {
                self::assertNull($errmsg);
            } else {
                self::assertNotNull($errmsg);
            }
        }
    }

    /**
     * Test: incovation of all documented algebraic / other numerical functions
     */
    public function test_invocation_algebraic() {
        // FIXME: short-circuit the test in order to remove variables.php
        // cases have yet to be ported to make use of the new evaluator
        self::assertTrue(true);
        return;

        $testcases = array(
            array(true, 'a=abs(-3);'),
            array(false, 'a=abs();'),
            array(false, 'a=abs(1, 2);'),
            array(true, 'a=ceil(0.5);'),
            array(false, 'a=ceil();'),
            array(false, 'a=ceil(1, 2);'),
            array(true, 'a=deg2rad(0.5);'),
            array(false, 'a=deg2rad();'),
            array(false, 'a=deg2rad(1, 2);'),
            array(true, 'a=exp(0.5);'),
            array(false, 'a=exp();'),
            array(false, 'a=exp(1, 2);'),
            array(true, 'a=expm1(0.5);'),
            array(false, 'a=expm1();'),
            array(false, 'a=expm1(1, 2);'),
            array(true, 'a=fact(3);'),
            array(false, 'a=fact();'),
            array(false, 'a=fact(1, 2);'),
            array(true, 'a=floor(0.5);'),
            array(false, 'a=floor();'),
            array(false, 'a=floor(1, 2);'),
            array(true, 'a=gcd(3, 2);'),
            array(false, 'a=gcd();'),
            array(false, 'a=gcd(0.5);'),
            array(false, 'a=gcd(1, 2, 3);'),
            array(true, 'a=is_finite(0.5);'),
            array(false, 'a=is_finite();'),
            array(false, 'a=is_finite(1, 2);'),
            array(true, 'a=is_infinite(0.5);'),
            array(false, 'a=is_infinite();'),
            array(false, 'a=is_infinite(1, 2);'),
            array(true, 'a=is_nan(0.5);'),
            array(false, 'a=is_nan();'),
            array(false, 'a=is_nan(1, 2);'),
            array(true, 'a=lcm(3, 2);'),
            array(false, 'a=lcm();'),
            array(false, 'a=lcm(0.5);'),
            array(false, 'a=lcm(1, 2, 3);'),
            array(true, 'a=log(0.5, 2);'),
            array(true, 'a=log(0.5);'),
            array(false, 'a=log();'),
            array(false, 'a=log(0.5, 2, 3);'),
            array(true, 'a=log10(0.5);'),
            array(false, 'a=log10();'),
            array(false, 'a=log10(1, 2);'),
            array(true, 'a=log1p(0.5);'),
            array(false, 'a=log1p();'),
            array(false, 'a=log1p(1, 2);'),
            array(true, 'a=max(1, 2);'),
            array(true, 'a=max(1, 2, 3);'),
            array(true, 'a=max(1, 2, 3, 4);'),
            array(true, 'a=max(1, 2, 3, 4, 5, 6, 7, 8, 9, 10);'),
            array(false, 'a=max();'),
            array(false, 'a=max(1);'),
            array(true, 'a=min(1, 2);'),
            array(true, 'a=min(1, 2, 3);'),
            array(true, 'a=min(1, 2, 3, 4);'),
            array(true, 'a=min(1, 2, 3, 4, 5, 6, 7, 8, 9, 10);'),
            array(false, 'a=min();'),
            array(false, 'a=min(1);'),
            array(true, 'a=modpow(1, 2, 3);'),
            array(false, 'a=modpow();'),
            array(false, 'a=modpow(1);'),
            array(false, 'a=modpow(1, 2);'),
            array(false, 'a=modpow(1, 2, 3, 4);'),
            array(true, 'a=pi();'),
            array(false, 'a=pi(1);'),
            array(true, 'a=pow(1, 2);'),
            array(false, 'a=pow();'),
            array(false, 'a=pow(1);'),
            array(false, 'a=pow(1, 2, 3);'),
            array(true, 'a=rad2deg(0.5);'),
            array(false, 'a=rad2deg();'),
            array(false, 'a=rad2deg(1, 2);'),
            array(true, 'a=round(0.123, 2);'),
            array(true, 'a=round(0.123);'),
            array(false, 'a=round();'),
            array(false, 'a=round(0.123, 2, 3);'),
            array(true, 'a=sigfig(0.5, 1);'),
            array(false, 'a=sigfig();'),
            array(false, 'a=sigfig(1);'),
            array(false, 'a=sigfig(1, 2, 3);'),
            array(true, 'a=sqrt(0.5);'),
            array(false, 'a=sqrt();'),
            array(false, 'a=sqrt(1, 2);'),
            array(false, 'a=sqrt(1, 2);'),
        );
        $qv = new variables;
        foreach ($testcases as $case) {
            $errmsg = null;
            try {
                $v = $qv->vstack_create();
                $qv->evaluate_assignments($v, $case[1]);
            } catch (Exception $e) {
                $errmsg = $e->getMessage();
            }
            if ($case[0]) {
                self::assertNull($errmsg);
            } else {
                self::assertNotNull($errmsg);
            }
        }
    }

    public function provide_sort(): array {
        return [
            ['sort() expects its first argument to be a list', 'sort(5, [1,2,3])'],
            ['sort() expects its first argument to be a list', 'sort("a", [1,2,3])'],
            ['when calling sort() with two arguments, they must both be lists', 'sort([1,2,3], 2)'],
            ['when calling sort() with two arguments, they must both be lists', 'sort([1,2,3], "a")'],
            ['when calling sort() with two lists, they must have the same size', 'sort([1,2,3], [1,2])'],
        ];
    }

    /**
     * Test: incovation of all documented string/array functions
     */
    public function test_invocation_string_array() {
        // FIXME: short-circuit the test in order to remove variables.php
        // cases have yet to be ported to make use of the new evaluator
        self::assertTrue(true);
        return;

        $testcases = array(
            array(true, 'a=concat([1, 2], [2, 4]);'),
            array(true, 'a=concat([1, 2], [2, 4], [3, 5], [5, 6]);'),
            array(true, 'a=concat([1], [2], [3], [4], [5], [6]);'),
            array(true, 'a=concat(["1"], ["2"], ["3"], ["4"], ["5"], ["6"]);'),
            array(false, 'a=concat();'),
            array(false, 'a=concat([1, 2]);'),
            array(false, 'a=concat(1);'),
            array(false, 'a=concat(1, 2);'),
            array(true, 'a=diff([1, 2], [2, 4]);'),
            array(false, 'a=diff();'),
            array(false, 'a=diff([1, 2]);'),
            array(false, 'a=diff([1, 2], [2, 4], [3, 5]);'),
            array(false, 'a=diff(1);'),
            array(false, 'a=diff(1, 2);'),
            array(true, 'a=fill(3, "x");'),
            array(true, 'a=fill(3, 0);'),
            array(false, 'a=fill(0);'),
            array(false, 'a=fill(3, 3, 3);'),
            array(true, 'a=join(" ", ["a", "b"]);'),
            array(true, 'a=join(" ", ["a", 1]);'),
            array(true, 'a=join(" ", "a", "b", "c");'),
            array(true, 'a=join(" ", 1, 2, 3, 4);'),
            array(false, 'a=join();'),
            array(false, 'a=join(3);'),
            array(false, 'a=join(["a", "b"]);'),
            array(true, 'a=len([1, 2, 3]);'),
            array(true, 'a=len(["1", "2", "3"]);'),
            array(true, 'a=len(["1", 2, "3"]);'),
            array(false, 'a=len();'),
            array(false, 'a=len(1);'),
            array(true, 'a=sort([1, 2, 3]);'),
            array(true, 'a=sort(["1", "2", "3"]);'),
            array(true, 'a=sort(["1", 2, "3"]);'),
            array(false, 'a=sort();'),
            array(false, 'a=sort(1);'),
            array(false, 'a=sort(1, 2);'),
            array(true, 'a=str(1);'),
            array(false, 'a=str();'),
            array(false, 'a=str("1");'),
            array(false, 'a=str(1, 2);'),
            array(false, 'a=str([1, 2]);'),
            array(true, 'a=sublist([1, 2, 3], [1, 1, 1, 1]);'),
            array(false, 'a=sublist();'),
            array(false, 'a=sublist(1);'),
            array(false, 'a=sublist(1, 2);'),
            array(false, 'a=sublist(1, 2, 3);'),
            array(false, 'a=sublist([1, 2, 3]);'),
            array(false, 'a=sublist([1, 2, 3], [5]);'), // Index is out of range.
            array(false, 'a=sublist([1, 2, 3], [1, 1, 1, 1], [1, 2, 3]);'),
            array(true, 'a=sum([1, 2, 3]);'),
            array(false, 'a=sum(["1", "2", "3"]);'),
            array(false, 'a=sum();'),
            array(false, 'a=sum(1);'),
            array(false, 'a=sum(1, 2);'),
            array(false, 'a=sum([1, 2, 3], [1, 2, 3]);'),
        );
        $qv = new variables;
        foreach ($testcases as $case) {
            $errmsg = null;
            try {
                $v = $qv->vstack_create();
                $qv->evaluate_assignments($v, $case[1]);
            } catch (Exception $e) {
                $errmsg = $e->getMessage();
            }
            if ($case[0]) {
                self::assertNull($errmsg);
            } else {
                self::assertNotNull($errmsg);
            }
        }
    }

    public function provide_poly_inputs(): array {
        return [
            // With just a number...
            ['+5', 'poly(5)'],
            ['+1.5', 'poly(1.5)'],
            ['0', 'poly(0)'],
            ['-1.5', 'poly(-1.5)'],
            ['-5', 'poly(-5)'],
            // With one variable (or arbitrary string) and a number...
            ['-5x', 'poly("x", -5)'],
            ['3x', 'poly("x", 3)'],
            ['3.7x', 'poly("x", 3.7)'],
            ['x', 'poly("x", 1)'],
            ['-x', 'poly("x", -1)'],
            ['-1.8x', 'poly("x", -1.8)'],
            ['+3x', 'poly("x", 3, "+")'],
            ['+3x^5', 'poly("x^5", 3, "+")'],
            ['0', 'poly("x", 0)'],
            // Invalid invocation with two arguments and the first not a string...
            ['when calling poly() with two arguments, the first must be a string or a list of strings', 'poly(1, [1, 2, 3])'],
            // Usage of other variables as coefficients...
            ['5x+2', 'a=5; b=2; p=poly([a,b]);'],
            ['+10', 'a=5; b=2; p=poly(a*b);'],
            // Invalid usage with algebraic variable as coefficient...
            ["algebraic variable 'a' cannot be used in this context", 'a={1,2,3}; p=poly("x", a);'],
            // Usage of other functions in the list of coefficients...
            ['x^{2}+3x+1', 'poly("x", [1, sqrt(3**2), 1])'],
            // With a variable and a list of numbers, with or without a separator...
            ['x^{2}+x+1', 'poly("x", [1, 1, 1])'],
            [
                'x^{19}+2x^{18}+3x^{17}+4x^{16}+5x^{15}+6x^{14}+7x^{13}+8x^{12}+9x^{11}+10x^{10}+11x^{9}'
                . '+12x^{8}+13x^{7}+14x^{6}+15x^{5}+16x^{4}+17x^{3}+18x^{2}+19x+20',
                'poly("x", [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15, 16, 17, 18, 19, 20])'
            ],
            ['1.3x^{2}+1.5x+1.9', 'poly("x", [1.3, 1.5, 1.9])'],
            ['1', 'poly("x", [0, 0, 1])'],
            ['1', 'poly("y", [0, 0, 1])'],
            ['-1', 'poly("x", [0, -1])'],
            ['-1', 'poly("y", [0, -1])'],
            ['-2.8', 'poly("y", [0, -2.8])'],
            ['x^{2}+1', 'poly("x", [1, 0, 1])'],
            ['y^{2}+1', 'poly("y", [1, 0, 1])'],
            ['x^{2}+2x+3', 'poly("x", [1, 2, 3])'],
            ['-x^{2}-2x-3', 'poly("x", [-1, -2, -3])'],
            ['-y^{2}-2y-3', 'poly("y", [-1, -2, -3])'],
            ['y^{2}+y+1', 'poly("y", [1, 1, 1])'],
            ['y^{2}+2y+3', 'poly("y", [1, 2, 3])'],
            ['2y&-1', 'poly("y", [2, -1], "&")'],
            ['z^{2}&+z&+1', 'poly("z", [1, 1, 1], "&")'],
            ['-y^{2}&-2y&-3', 'poly("y", [-1, -2, -3], "&")'],
            ['0', 'poly("x", [0, 0, 0])'],
            ['&&0', 'poly("x", [0, 0, 0], "&")'],
            // With multiple variables and coefficients plus a separator...
            ['x&+y&+z', 'poly(["x","y","z"], [1, 1, 1], "&")'],
            ['2x&+3y&+4z', 'poly(["x","y","z"], [2, 3, 4], "&")'],
            ['-2x&-3y&-4z', 'poly(["x","y","z"], [-2, -3, -4], "&")'],
            ['-2.4x&-3.1y&-4z', 'poly(["x","y","z"], [-2.4, -3.1, -4.0], "&")'],
            ['-x&-y&-z', 'poly(["x","y","z"], [-1, -1, -1], "&")'],
            ['&&0', 'poly(["x","y","z"], [0, 0, 0], "&")'],
            ['0', 'poly(["x","y","z"], [0, 0, 0])'],
            // With the default variable x, with or without a separator...
            ['x^{2}+x+1', 'poly([1, 1, 1])'],
            ['-x^{2}-x-1', 'poly([-1, -1, -1])'],
            ['1', 'poly([0, 0, 1])'],
            ['0', 'poly([0, 0, 0])'],
            ['-1', 'poly([0, -1])'],
            ['x^{2}+1', 'poly([1, 0, 1])'],
            ['x^{2}+2x+3', 'poly([1, 2, 3])'],
            ['-x^{2}-2x-3', 'poly([-1, -2, -3])'],
            ['-x^{2}&-2x&-3', 'poly([-1, -2, -3], "&")'],
            ['&1', 'poly([0, 1], "&")'],
            ['x^{2}&&+1', 'poly([1, 0, 1], "&")'],
            ['x^{3}&&&+1', 'poly([1, 0, 0, 1], "&")'],
            // With a list of variables and coefficients...
            ['-x-2y-3xy', 'poly(["x", "y", "xy"], [-1, -2, -3])'],
            ['x-3xy', 'poly(["x", "y", "xy"], [1, 0, -3])'],
            ['x-3x', 'poly(["x", "y"], [1, 0, -3])'],
            ['x+y+x+y', 'poly(["x", "y"], [1, 1, 1, 1])'],
            ['x&+y&+x&+y', 'poly(["x", "y"], [1, 1, 1, 1], "&")'],
            // With an empty string and a separator, we build a matrix row...
            ['1&1&1&1', 'poly("", [1, 1, 1, 1], "&")'],
            ['-1&1&-1&1', 'poly("", [-1, 1, -1, 1], "&")'],
            ['1&-1&1&-1', 'poly("", [1, -1, 1, -1], "&")'],
            ['0&0&0&0', 'poly("", [0, 0, 0, 0], "&")'],
            ['1&0&2&3', 'poly("", [1, 0, 2, 3], "&")'],
            ['0&1&0&-1', 'poly("", [0, 1, 0, -1], "&")'],
            // With double separators for e.g. equation systems...
            ['x&+&y&+&z', 'poly(["x", "y", "z"], [1, 1, 1], "&&")'],
            ['x&+&2y&+&3z', 'poly(["x", "y", "z"], [1, 2, 3], "&&")'],
            ['-x&-&y&-&z', 'poly(["x", "y", "z"], [-1, -1, -1], "&&")'],
            ['-x&-&2y&-&3z', 'poly(["x", "y", "z"], [-1, -2, -3], "&&")'],
            ['&&y&+&z', 'poly(["x", "y", "z"], [0, 1, 1], "&&")'],
            ['x&&&+&z', 'poly(["x", "y", "z"], [1, 0, 1], "&&")'],
            ['x&+&y&&', 'poly(["x", "y", "z"], [1, 1, 0], "&&")'],
            ['&&&&z', 'poly(["x", "y", "z"], [0, 0, 1], "&&")'],
            ['&&&-&z', 'poly(["x", "y", "z"], [0, 0, -1], "&&")'],
            ['&&&&0', 'poly(["x", "y", "z"], [0, 0, 0], "&&")'],
            // Separator with even length, but not doubled; no practical use...
            ['x&#-2y&#+3z', 'poly(["x", "y", "z"], [1, -2, 3], "&#")'],
            // Artificially making the lengh odd; no practical use...
            ['x&& -2y&& +3z', 'poly(["x", "y", "z"], [1, -2, 3], "&& ")'],
            // Invalid invocations...
            ["invalid number of arguments for function 'poly': 0 given", 'poly()'],
            ['when calling poly() with one argument, it must be a number or a list of numbers', 'poly("x")'],
            ['when calling poly() with one argument, it must be a number or a list of numbers', 'poly(["x", "y"])'],
            ['when calling poly() with a list of strings, the second argument must be a list of numbers', 'poly(["x", "y"], 1)'],
            ['when calling poly() with a string, the second argument must be a number or a list of numbers', 'poly("x", "y")'],
        ];
    }

    /**
     * @dataProvider provide_poly_inputs
     */
    public function test_poly($expected, $input): void {
        $parser = new parser($input);
        $statements = $parser->get_statements();
        $evaluator = new evaluator();
        try {
            $result = $evaluator->evaluate($statements);
        } catch (Exception $e) {
            self::assertStringEndsWith($expected, $e->getMessage());
            return;
        }

        self::assertEquals($expected, end($result)->value);
    }

    public function provide_various_function_calls(): array {
        return [
            [get_config('qtype_formulas')->version, 'fqversionnumber()'],
            ['str() expects a scalar argument, e. g. a number', 's = str([])'],
            ['str() expects a scalar argument, e. g. a number', 's = str([1, 2, 3])'],
        ];
    }

    public function provide_sublist(): array {
        return [
            [[1, 1, 1, 2, 3, 2, 1, 3], 'sublist([1, 2, 3], [0, 0, 0, 1, 2, 1, 0, 2])'],
            [[1, 3, 2], 'sublist([1, 2, 3], [0.0, 2.0, 1.0])'],
            [[[1, 2], "a", 1, "a"], 'sublist([1, "a", [1, 2]], [2, 1, 0, 1])'],
            [[], 'sublist([1, 2, 3], [])'],
            ['sublist() expects its arguments to be lists', 'sublist([1, 2, 3], 3)'],
            ['sublist() expects its arguments to be lists', 'sublist([1, 2, 3], "foo")'],
            ['sublist() expects its arguments to be lists', 'sublist(1, [1, 2, 3])'],
            ['sublist() expects its arguments to be lists', 'sublist("foo", [1, 2, 3])'],
            ["sublist() expects the indices to be integers, found '1.5'", 'sublist([1, 2, 3], [1.5])'],
            ["sublist() expects the indices to be integers, found 'foo'", 'sublist([1, 2, 3], ["foo"])'],
            ['index 3 out of range in sublist()', 'sublist([1, 2, 3], [3])'],
        ];
    }

    public function provide_map(): array {
        return [
            [[1, 0, 1, 0], 'map("==", [1, 2, 1, 3], [1, 1, 1, 1])'],
            [[4, 6], 'map("+", [1, 2], [3, 4])'],
            [[2, 10], 'map("-", [5, 6], [3, -4])'],
            [[6, 7, 8], 'map("+", [1, 2, 3], 5)'],
            ['* expects a number', 'map("*", [[1, 2], [3, 4]], "foo")'],
            ["* expects a number, found foo", 'map("*", ["foo", "bar"], "s")'],
            [[11, 12, 13], 'map("+", 10, [1, 2, 3])'],
            [['week 1', 'week 2', 'week 3'], 'map("+", "week ", [1, 2, 3])'],
            [[1, -2], 'map("-", [-1, 2])'],
            [['2.12', '3.57'], 'map("sigfig", [2.123, 3.568], 3)'],
            [[6, 15], 'map("sum", [[1,2,3],[4,5,6]])'],
            [[0.977249868051821, 0.841344746068543], 'map("stdnormcdf", [2, 1])'],
            [[1, 2], 'map("abs", [-1, -2])'],
            [[5, 10, 3], 'map("max", [5, 0, 3], [3, 10, -1])'],
            [[5, 3], 'map("max", [[5, 0, 3], [1, 2, 3]])'],
            ["invalid number of arguments for function 'map': 1 given", 'map("+")'],
            ["invalid number of arguments for function 'map': 0 given", 'map()'],
            ["when using map() with the unary function 'abs', only one list is accepted", 'map("abs", [-1, -2], [3, 4])'],
            ["invalid number of arguments for function 'map': 1 given", 'map("abs")'],
            ["invalid number of arguments for function 'map': 1 given", 'map([1, 2, 3])'],
            ["when using map() with the binary operator '+', two arguments are expected", 'map("+", [1, 2])'],
            ["when using map() with the binary operator '+', at least one argument must be a list", 'map("+", 3, 4)'],
            ['when using map() with two lists, they must both have the same size', 'map("+", [1, 2, 3], [4, 5])'],
            ["when using map() with '-', the argument must be a list", 'map("-", 2)'],
            ["'x' is not a legal first argument for the map() function", 'map("x", [-1, -2])'],
            ["the function 'fqversionnumber' cannot be used with map(), because it accepts no arguments", 'map("fqversionnumber", [1, 2, 3])'],
            ["the function 'modpow' cannot be used with map(), because it expects more than two arguments", 'map("modpow", [1, 2, 3], [3, 4, 5])'],
        ];
    }

    public function provide_modular_function_calls(): array {
        return [
            [15, 'fmod(35,20)'],
            [5, 'fmod(-35, 20)'],
            [-5, 'fmod(35, -20)'],
            [-15, 'fmod(-35, -20)'],
            [0, 'fmod(12, 3)'],
            [5, 'fmod(5, 8)'],
            [0.5, 'fmod(5.7, 1.3)'],
            [0, 'fmod(0, 7.9)'],
            [0, 'fmod(2, 0.4)'],
            ["invalid number of arguments for function 'fmod': 0 given", 'fmod()'],
            ["invalid number of arguments for function 'fmod': 1 given", 'fmod(3)'],
            ["invalid number of arguments for function 'fmod': 3 given", 'fmod(3, 2, 1)'],
            ['fmod() expects its first argument to be a number', 'fmod("a", "b")'],
            ['fmod() expects its second argument to be a non-zero number', 'fmod(3, "b")'],
            ['fmod() expects its first argument to be a number', 'fmod("a", 3)'],
            ['fmod() expects its second argument to be a non-zero number', 'fmod(4, 0)'],

            [0, 'modinv(15, 3)'],
            [0, 'modinv(5, 1)'],
            [1, 'modinv(1, 7)'],
            [4, 'modinv(2, 7)'],
            [5, 'modinv(3, 7)'],
            [2, 'modinv(4, 7)'],
            [3, 'modinv(5, 7)'],
            [6, 'modinv(6, 7)'],
            [3, 'modinv(-3, 5)'],
            ['modinv() expects its second argument to be a positive integer', 'modinv(8, -3)'],
            ['modinv() expects its second argument to be a positive integer', 'modinv(8, 0)'],
            ['modinv() expects its first argument to be a non-zero integer', 'modinv(0, 13)'],

            [7, 'modpow(15, 300, 19)'],
            [1, 'modpow(15, 18, 19)'],
            [1, 'modpow(1, 7, 13)'],
            [11, 'modpow(2, 7, 13)'],
            [3, 'modpow(3, 7, 13)'],
            [4, 'modpow(4, 7, 13)'],
            [8, 'modpow(5, 7, 13)'],
            [7, 'modpow(6, 7, 13)'],
            [6, 'modpow(7, 7, 13)'],
            [12, 'modpow(12, 7, 13)'],
            [1, 'modpow(12, 8, 13)'],
            [8, 'modpow(3, 10, 17)'],
            [1, 'modpow(12, 0, 17)'],
            // TODO: add invalid calls
        ];
    }

    /**
     * @dataProvider provide_sigfig_expressions
     */
    public function test_functions_returning_string($expected, $input): void {
        $parser = new parser($input);
        $statements = $parser->get_statements();
        $evaluator = new evaluator();
        $result = $evaluator->evaluate($statements)[0];

        self::assertSame($expected, token::unpack($result));
    }

    /**
     * @dataProvider provide_various_function_calls
     * @dataProvider provide_modular_function_calls
     * @dataProvider provide_map
     * @dataProvider provide_sublist
     * @dataProvider provide_inv
     * @dataProvider provide_sort
     */
    public function test_function_calls($expected, $input): void {
        $parser = new parser($input);
        $statements = $parser->get_statements();
        $evaluator = new evaluator();
        try {
            $result = $evaluator->evaluate($statements)[0];
        } catch (Exception $e) {
            self::assertStringEndsWith($expected, $e->getMessage());
            return;
        }

        $unpackedresult = token::unpack($result);
        self::assertEqualsWithDelta($expected, $unpackedresult, 1e-8);
    }

    public function test_shuffle(): void {
        $parser = new parser('shuffle([1, 2, 3])');
        $statements = $parser->get_statements();
        $evaluator = new evaluator();

        // First of all, the evaluation must be successful.
        $e = null;
        try {
            $result = $evaluator->evaluate($statements)[0];
        } catch (Exception $e) {
            ;
        }
        self::assertNull($e);

        // Second, we check that the resulting array is one of the possible permutations.
        $all = [[1, 2, 3], [1, 3, 2], [2, 1, 3], [2, 3, 1], [3, 1, 2], [3, 2, 1]];
        $unpackedresult = token::unpack($result);
        self::assertTrue(in_array($unpackedresult, $all));

        // Third, we shuffle the array a certain number of times until the first element has changed
        // at least once. That's enough to know that some shuffling has happened.
        for ($i = 0; $i < 20; $i++) {
            if ($unpackedresult[0] != 1) {
                break;
            }
            $result = $evaluator->evaluate($statements)[0];
            $unpackedresult = token::unpack($result);
        }
        self::assertNotEquals(1, $unpackedresult[0]);
    }

    public function test_rshuffle(): void {
        $parser = new parser('rshuffle([[1, 2, 3],[4, 5, 6]])');
        $statements = $parser->get_statements();
        $evaluator = new evaluator();

        // First of all, the evaluation must be successful.
        $e = null;
        try {
            $result = $evaluator->evaluate($statements)[0];
        } catch (Exception $e) {
            ;
        }
        self::assertNull($e);

        // Second, we check that the resulting array is one of the possible permutations.
        $first = [[1, 2, 3], [1, 3, 2], [2, 1, 3], [2, 3, 1], [3, 1, 2], [3, 2, 1]];
        $second = [[4, 5, 6], [4, 6, 5], [5, 4, 6], [5, 6, 4], [6, 4, 5], [6, 5, 4]];
        $unpackedresult = token::unpack($result);
        if ($unpackedresult[0][0] < 4) {
            self::assertTrue(in_array($unpackedresult[0], $first));
            self::assertTrue(in_array($unpackedresult[1], $second));
        } else {
            self::assertTrue(in_array($unpackedresult[1], $first));
            self::assertTrue(in_array($unpackedresult[0], $second));
        }

        // Third, we shuffle the array a certain number of times until the first array
        // and the first element of both arrays has changed at least once. That's enough
        // to know that some shuffling has happened.
        $firstok = false;
        $secondok = false;
        for ($i = 0; $i < 20; $i++) {
            if ($unpackedresult[0][0] == 5 || $unpackedresult[0][0] == 6) {
                $secondok = true;
            }
            if ($unpackedresult[1][0] == 2 || $unpackedresult[1][0] == 3) {
                $firstok = true;
            }
            if ($firstok && $secondok) {
                break;
            }
            $result = $evaluator->evaluate($statements)[0];
            $unpackedresult = token::unpack($result);
        }
        self::assertTrue($firstok);
        self::assertTrue($secondok);
    }

    public function test_is_numeric_array(): void {
        // If no array is given, result should be false.
        self::assertFalse(functions::is_numeric_array('foo'));
        self::assertFalse(functions::is_numeric_array(3));

        // If empty arrays are not allowed, result should be false.
        self::assertFalse(functions::is_numeric_array([], false));
        // Otherwise, an empty array is fine, because it does not contain non-numeric stuff.
        self::assertTrue(functions::is_numeric_array([]));

        // Check various arrays.
        self::assertTrue(functions::is_numeric_array([1, 2, 3]));
        self::assertTrue(functions::is_numeric_array([1, '2', '3.0']));
        self::assertTrue(functions::is_numeric_array([1.0, -2, -3.1]));
        self::assertFalse(functions::is_numeric_array([1, 'foo']));
        self::assertFalse(functions::is_numeric_array([[1, 2], [3, 4]]));
    }
}
