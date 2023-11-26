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
 * Unit tests for the formulas variables class.
 *
 * @package    qtype_formulas
 * @copyright  2018 Jean-Michel Vedrine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace qtype_formulas;
use qtype_formulas\variables;
use Exception;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/question/engine/tests/helpers.php');
require_once($CFG->dirroot . '/question/type/formulas/variables.php');


/**
 * Unit tests for the formulas question variables class.
 *
 * @copyright  2018 Jean-Michel Vedrine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

class variables_test extends \advanced_testcase {


    /**
     * Test 1: get_expressions_in_bracket() test.
     * TODO: can be removed, because it only tests an obsolete helper function
     * maybe the test can be used in other scenarios, e.g. evaluation
     */
    public function test_get_expressions_in_bracket() {
        $brackettest = array(
            array(true, '8+sum([1,2+2])', '(', 5, 13, array("[1,2+2]")),
            array(true, '8+[1,2+2,3+sin(3),sum([1,2,3])]', '[', 2, 30, array("1", "2+2", "3+sin(3)", "sum([1,2,3])")),
            array(true, 'a=0; for x in [1,2,3] { a=a+sum([1,x]); }', '{', 22, 40, array(" a=a+sum([1,x]); ")),
        );
    }

    /**
     * Test 3.2: evaluate_assignments() test.
     * TODO: these are now allowed
     */
    public function test_evaluate_assignments_2() {
        $testcases = array(
            array(
              false,
              'e=[1,2,3,4]; a=1-1; e[a]="A";',
              '3: Element in the same list must be of the same type, either number or string.'
            ),
            array(false, 'e=[1,2,"A"];', '1: Element in the same list must be of the same type, either number or string.'),
            array(false, 'e=[[1,2],[3,4]];', '1: Element in the same list must be of the same type, either number or string.'),
            array(false, 'e=[[[1,2],[3,4]]];', '1: Element in the same list must be of the same type, either number or string.'),
            array(
              false,
              'e=[1,2,3]; e[0] = [8,9];',
              '2: Element in the same list must be of the same type, either number or string.'
            ),
        );
    }

    /**
     * Test 3.3: evaluate_assignments() test.
     */
    public function test_evaluate_assignments_3() {
        $testcases = array(
            array(true, 'x = {1,2,3};', array(
                    'x' => (object) array('type' => 'zn', 'value' => (object) array(
                            'numelement' => 3,
                            'elements' => array(
                                    array(1, 1.5, 1),
                                    array(2, 2.5, 1),
                                    array(3, 3.5, 1),
                            ),
                        ),
                    ),
                ),
            ),
            array(true, 'x = { 1 , 2 , 3 };', array(
                    'x' => (object) array('type' => 'zn', 'value' => (object) array(
                            'numelement' => 3,
                            'elements' => array(
                                    array(1, 1.5, 1),
                                    array(2, 2.5, 1),
                                    array(3, 3.5, 1),
                            ),
                        ),
                    ),
                ),
            ),
            array(true, 'x = {1:3, 4:5:0.1 , 8:10:0.5 };', array(
                    'x' => (object) array('type' => 'zn', 'value' => (object) array(
                            'numelement' => 16,
                            'elements' => array(
                                    array(1, 3, 1),
                                    array(4, 5, 0.1),
                                    array(8, 10, 0.5),
                            ),
                        ),
                    ),
                ),
            ),
            array(true, 'x={1:10}; y={1:10}; s=diff(["x*x+y*y"],["x^2+y^2"],50);', array(
                    'x' => (object) array('type' => 'zn', 'value' => (object) array(
                            'numelement' => 9,
                            'elements' => array(array(1, 10, 1)),
                        ),
                     ),
                    'y' => (object) array('type' => 'zn', 'value' => (object) array(
                            'numelement' => 9,
                            'elements' => array(array(1, 10, 1)),
                        ),
                    ),
                    's' => (object) array('type' => 'ln', 'value' => array(0),
                    ),
                ),
            ),
            array(true, 'x={1:10}; y={1:10}; s=diff(["x*x+y*y"],["x+y^2"],50)[0];', ''),
        );
    }

    /**
     * Test 4: parse_random_variables(), instantiate_random_variables().
     */
    public function test_parse_random_variables() {
        $qv = new variables;
        $testcases = array(
            array(true, 'a = shuffle ( ["A","B", "C" ])', ''),
            array(true, 'a = {1,2,3}', array(
                    'a' => (object) array('type' => 'n', 'value' => 2),
                    )
            ),
            array(true, 'a = {[1,2], [3,4]}', array(
                    'a' => (object) array('type' => 'ln', 'value' => array(3, 4)),
                    )
            ),
            array(true, 'a = {"A","B","C"}', array(
                    'a' => (object) array('type' => 's', 'value' => "B"),
                    )
            ),
            array(true, 'a = {["A","B"],["C","D"]}', array(
                    'a' => (object) array('type' => 'ls', 'value' => array('C', 'D')),
                    )
            ),
            array(true, 'a = {0, 1:3:0.1, 10:30, 100}', array(
                    'a' => (object) array('type' => 'n', 'value' => 10),
                    )
            ),
            array(true, 'a = {1:3:0.1}; b={"A","B","C"};', array(
                    'a' => (object) array('type' => 'n', 'value' => 2),
                    'b' => (object) array('type' => 's', 'value' => "B"),
                    )
            ),
            array(false, 'a = {10:1:1}', '1: a: Syntax error.'),
            array(false, 'a = {1:10,}', '1: a: Uninitialized string offset'),
            array(false, 'a = {1:10?}', '1: a: Formula or expression contains forbidden characters or operators.'),
            array(false, 'a = {0, 1:3:0.1, 10:30, 100}*3', '1: a: Syntax error.'),
            array(false, 'a = {1:3:0.1}; b={a,12,13};', '2: b: Formula or expression contains forbidden characters or operators.'),
            array(false, 'a = {[1,2],[3,4,5]}', '1: a: All elements in the set must have exactly the same type and size.'),
            array(false, 'a = {[1,2],["A","B"]}', '1: a: All elements in the set must have exactly the same type and size.'),
            array(false, 'a = {[1,2],["A","B","C"]}', '1: a: All elements in the set must have exactly the same type and size.'),
        );
        foreach ($testcases as $idx => $testcase) {
            $errmsg = null;
            $var = (object)array('all' => null);
            try {
                $var = $qv->parse_random_variables($testcase[1]);
                // To predict the result we choose the dataset rather than having it at random.
                $dataset = (int) ($qv->vstack_get_number_of_dataset($var) / 2);
                $inst = $qv->instantiate_random_variables($var, $dataset);
                $serialized = $qv->vstack_get_serialization($inst);
            } catch (Exception $e) {
                $errmsg = $e->getMessage();
            }
            if ($testcase[0]) {
                // Test that no exception is thrown
                // and that correct result is returned.
                $this->assertNull($errmsg);
                $this->assertEquals(0, $inst->idcounter);
                if ($testcase[2] != '') {
                    // For now we don't test variables with shuffle.
                    $this->assertEquals($testcase[2], $inst->all);
                }
            } else {
                // Test that the correct exception message is returned.
                $this->assertStringStartsWith($testcase[2], $errmsg);
            }
        }
    }
    /**
     * Test 5: substitute_variables_in_text.
     */
    public function test_substitute_variables_in_text() {
        $qv = new variables;
        $vstack = $qv->vstack_create();
        $variablestring = 'a=1; b=[2,3,4];';
        $vstack = $qv->evaluate_assignments($vstack, $variablestring);
        $text = '{a}, {a }, { a}, {b}, {b[0]}, {b[0] }, { b[0]}, {b [0]}, ' .
                '{=a*100}, {=b[0]*b[1]}, {= b[1] * b[2] }, {=100+[4:8][1]} ';
        $newtext = $qv->substitute_variables_in_text($vstack, $text);
        $expected = '1, {a }, { a}, {b}, 2, {b[0] }, { b[0]}, {b [0]}, 100, 6, 12, 105 ';
        $this->assertEquals($expected, $newtext);
    }

    /**
     * Test 6.1: Numerical formula.
     */
    public function test_numerical_formula_1() {
        $qv = new variables;
        $testcases = array(
            array(true, 0, '3', 3),
            array(true, 0, '3.', 3),
            array(true, 0, '.3', 0.3),
            array(true, 0, '3.1', 3.1),
            array(true, 0, '3.1e-10', 3.1e-10),
            array(true, 0, '3.e10', 30000000000),
            array(true, 0, '.3e10', 3000000000),
            array(true, 0, '-3', -3),
            array(true, 0, '+3', 3),
            array(true, 0, '-3.e10', -30000000000),
            array(true, 0, '-.3e10', -3000000000),
            array(true, 0, 'pi', 0),
            array(false, 0, '- 3'),
            array(false, 0, '+ 3'),
            array(false, 0, '3 e10'),
            array(false, 0, '3e 10'),
            array(false, 0, '3e8e8'),
            array(false, 0, '3+10*4'),
            array(false, 0, '3+10^4'),
            array(false, 0, 'sin(3)'),
            array(false, 0, '3+exp(4)'),
            array(false, 0, '3*4*5'),
            array(false, 0, '3 4 5'),
            array(false, 0, 'a*b'),
            array(false, 0, '#')
        );
        foreach ($testcases as $idx => $testcase) {
            $result = $qv->compute_numerical_formula_value($testcase[2], $testcase[1]);
            $eval = $result !== null;
            $this->assertEquals($testcase[0], $eval);
            if ($testcase[0]) {
                $this->assertEquals($testcase[3], $result);
            }
        }
    }

    /**
     * Test 6.2: Numerical formula.
     */
    public function test_numerical_formula_2() {
        $qv = new variables;
        $testcases = array(
            // Numeric is basically a subset of 10al formula.
            array(true, 10, '3+10*4/10^4', 3.004),
            array(false, 10, 'sin(3)'),
            array(false, 10, '3+exp(4)'),

            // Numerical formula is basically a subset of algebraic formula, so test below together.
            array(true, 100, '3.1e-10', 3.1e-10),
            array(true, 100, '- 3', -3), // It is valid for this type.
            array(false, 100, '3 e10'),
            array(false, 100, '3e 10'),
            array(false, 100, '3e8e8'),
            array(false, 100, '3e8e8e8'),

            array(true, 100, '3+10*4/10^4', 3.004),
            array(true, 100, 'sin(3)-3+exp(4)', 51.739270041204),
            array(true, 100, '3*4*5', 60),
            array(true, 100, '3 4 5', 60),
            array(true, 100, '3e8 4.e8 .5e8', 6.0E+24),
            array(true, 100, '3e8(4.e8+2)(.5e8/2)5', 1.5000000075E+25),
            array(true, 100, '3e8(4.e8+2) (.5e8/2)5',  1.5000000075E+25),
            array(true, 100, '3e8 (4.e8+2)(.5e8/2) 5', 1.5000000075E+25),
            array(true, 100, '3e8 (4.e8+2) (.5e8/2) 5', 1.5000000075E+25),
            array(true, 100, '3(4.e8+2)3e8(.5e8/2)5', 4.5000000225E+25),
            array(true, 100, '3+4^9', 262147),
            array(true, 100, '3+(4+5)^9', 387420492),
            array(true, 100, '3+(4+5)^(6+7)', 2541865828332),
            array(true, 100, '3+sin(4+5)^(6+7)', 3.0000098920712),
            array(true, 100, '3+exp(4+5)^sin(6+7)', 46.881961305748),
            array(true, 100, '3+4^-(9)', 3.0000038146973),
            array(true, 100, '3+4^-9', 3.0000038146973),
            array(true, 100, '3+exp(4+5)^-sin(6+7)', 3.0227884071323),
            array(true, 100, '1+ln(3)', 2.0986122886681),
            array(true, 100, '1+log10(3)', 1.4771212547197),
            array(true, 100, 'pi', 3.1415926535898),
            array(false, 100, 'pi()'),
        );
        foreach ($testcases as $idx => $testcase) {
            $result = $qv->compute_numerical_formula_value($testcase[2], $testcase[1]);
            $eval = $result !== null;
            $this->assertEquals($testcase[0], $eval);
            if ($testcase[0]) {
                $this->assertEqualsWithDelta($testcase[3], $result, 1e-8);
            }
        }
    }

    /**
     * Test 7: Algebraic formula.
     */
    public function test_algebraic_formula() {
        $qv = new variables;
        $testcases = array(
            array(true, '- 3'),
            array(false, '3 e10'),
            array(true, '3e 10'),
            array(false, '3e8e8'),
            array(false, '3e8e8e8'),

            array(true, 'sin(3)-3+exp(4)'),
            array(true, '3e8 4.e8 .5e8'),
            array(true, '3e8(4.e8+2)(.5e8/2)5'),
            array(true, '3+exp(4+5)^sin(6+7)'),
            array(true, '3+4^-(9)'),

            array(true, 'sin(a)-a+exp(b)'),
            array(true, 'a*b*c'),
            array(true, 'a b c'),
            array(true, 'a(b+c)(x/y)d'),
            array(true, 'a(b+c) (x/y)d'),
            array(true, 'a (b+c)(x/y) d'),
            array(true, 'a (b+c) (x/y) d'),
            array(true, 'a(4.e8+2)3e8(.5e8/2)d'),
            array(true, 'pi'),
            array(true, 'a+x^y'),
            array(true, '3+x^-(y)'),
            array(true, '3+x^-y'),
            array(true, '3+(u+v)^x'),
            array(true, '3+(u+v)^(x+y)'),
            array(true, '3+sin(u+v)^(x+y)'),
            array(true, '3+exp(u+v)^sin(x+y)'),
            array(true, 'a+exp(a)(u+v)^sin(1+2)(b+c)'),
            array(true, 'a+exp(u+v)^-sin(x+y)'),
            array(true, 'a+b^c^d+f'),
            array(true, 'a+b^(c^d)+f'),
            array(true, 'a+(b^c)^d+f'),
            array(true, 'a+b^c^-d'),
            array(true, '1+ln(a)+log10(b)'),
            array(true, 'asin(w t)'),
            array(true, 'a sin(w t)+ b cos(w t)'),
            array(true, '2 (3) a sin(b)^c - (sin(x+y)+x^y)^-sin(z)c tan(z)(x^2)'),

            array(false, 'a-'),
            array(false, '*a'),
            array(true, 'a**b'),    // True since PHP > 5.6.
            array(false, 'a+^c+f'),
            array(false, 'a+b^^+f'),
            array(false, 'a+(b^c)^+f'),
            array(false, 'a+((b^c)^d+f'),
            array(false, 'a+(b^c+f'),
            array(false, 'a+b^c)+f'),
            array(false, 'a+b^(c+f'),
            array(false, 'a+b)^c+f'),
            array(false, 'pi()'),
            array(false, 'sin 3'),
            array(false, '1+sin*(3)+2'),
            array(false, '1+sin^(3)+2'),
            array(false, 'a sin w t'),
            array(false, '1==2?3:4'),
            array(false, 'a=b'),
            array(false, '3&4'),
            array(false, '3==4'),
            array(false, '3&&4'),
            array(false, '3!'),
            // @codingStandardsIgnoreLine
            array(false, '`'),
            array(false, '@'),
        );
        $v = $qv->vstack_create();
        $v = $qv->evaluate_assignments(
          $v,
          'a={1:10}; b={1:10}; c={1:10}; d={1:10}; e={1:10}; f={1:10}; t={1:10}; u={1:10}; v={1:10}; w={1:10}; ' .
          'x={1:10}; y={1:10}; z={1:10};'
        );
        foreach ($testcases as $idx => $testcase) {
            try {
                $result = $qv->compute_algebraic_formula_difference($v, array($testcase[1]), array($testcase[1]), 100);
            } catch (Exception $e) {
                $result = null;
            }
            $eval = $result !== null;
            $this->assertEquals($testcase[0], $eval);
        }

        $v = $qv->vstack_create();
        $v = $qv->evaluate_assignments($v, 'x={-10:11:1}; y={-10:-5, 6:11};');
        $result = $qv->compute_algebraic_formula_difference(
          $v,
          array('x', '1+x+y+3', '(1+sqrt(x))^2'), array('0', '2+x+y+2', '1+x'), 100
        );
        $this->assertEquals($result[1], 0);
        $this->assertEquals($result[2], INF);
        $result = $qv->compute_algebraic_formula_difference($v, array('x', '(x+y)^2'), array('0', 'x^2+2*x*y+y^2'), 100);
        $this->assertEquals($result[1], 0);
    }

    /**
     * Test 8: Split formula unit.
     */
    public function test_split_formula_unit() {
        $testcases = array(
            // Check for simple number and unit.
            array('#', array('', '#')), // FIXME: this is not valid and won't be accepted in new code

            // Numerical formula and unit.
            array('3.1e-10kg m/s', array('3.1e-10', 'kg m/s')),
            array('-3kg m/s', array('-3', 'kg m/s')),
            array('- 3kg m/s', array('- 3', 'kg m/s')),
            array('3e', array('3', 'e')),
            array('3e8', array('3e8', '')),
            array('3e8e', array('3e8', 'e')),
            array('3+4 5+10^4kg m/s', array('3+4 5+10^4', 'kg m/s')),
            array('sin(3)kg m/s', array('sin(3)', 'kg m/s')),
            array('3*4*5 kg m/s', array('3*4*5 ', 'kg m/s')),
            array('3 4 5 kg m/s', array('3 4 5 ', 'kg m/s')),
            array('3e8(4.e8+2)(.5e8/2)5kg m/s', array('3e8(4.e8+2)(.5e8/2)5', 'kg m/s')),
            array('3+exp(4+5)^sin(6+7)kg m/s', array('3+exp(4+5)^sin(6+7)', 'kg m/s')),
            array('3+exp(4+5)^-sin(6+7)kg m/s', array('3+exp(4+5)^-sin(6+7)', 'kg m/s')),
            array('3exp^2', array('3', 'exp^2')), // Note the unit is exp to the power 2.
            array('3 e8', array('3 ', 'e8')),
            array('3e 8', array('3', 'e 8')),
            array('3e8e8', array('3e8', 'e8')),
            array('3e8e8e8', array('3e8', 'e8e8')),
            array('3+exp(4+5).m/s', array('3+exp(4+5)', '.m/s')),
            array('3+(4.m/s', array('3+(4.', 'm/s')),
            array('3+4.)m/s', array('3+4.)', 'm/s')),
            array('3 m^', array('3 ', 'm^')),
            array('3 m/', array('3 ', 'm/')),
            array('3 /s', array('3 /', 's')),
            array('3 m+s', array('3 ', 'm+s')),
            array('1==2?3:4', array('1', '==2?3:4')),
            array('a=b', array('', 'a=b')),
            array('3&4', array('3', '&4')),
            array('3==4', array('3', '==4')),
            array('3&&4', array('3', '&&4')),
            array('3!', array('3', '!')),
            // @codingStandardsIgnoreLine
            array('`', array('', '`')),
            array('@', array('', '@')),
        );
        $v = $qv->vstack_create();
        $v = $qv->evaluate_assignments(
          $v,
          'a={1:10}; b={1:10}; c={1:10}; d={1:10}; e={1:10}; f={1:10}; t={1:10}; u={1:10}; ' .
          'v={1:10}; w={1:10}; x={1:10}; y={1:10}; z={1:10};'
        );
    }

}
