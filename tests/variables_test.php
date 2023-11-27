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
     * Test 3.2: evaluate_assignments() test.
     * TODO: these are now allowed, but they must be tested separately
     */
    public function test_evaluate_assignments_2() {
        $testcases = array(
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
     * Test 7: Algebraic formula.
     */
    public function test_algebraic_formula() {
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
    }

}
