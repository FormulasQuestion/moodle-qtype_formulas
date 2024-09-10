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
 * Unit tests for the formulas variables class.
 *
 * @package    qtype_formulas
 * @copyright  2018 Jean-Michel Vedrine
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace qtype_formulas;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/question/engine/tests/helpers.php');

/**
 * Unit tests for the formulas question variables class.
 *
 * @copyright  2018 Jean-Michel Vedrine
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
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
     * Test 4: parse_random_variables(), instantiate_random_variables().
     */
    public function test_parse_random_variables() {
        // TODO: current unit test is not configured to test simultaneous creation of two random vars.
        $testcases = array(
            array(true, 'a = {1:3:0.1}; b={"A","B","C"};', array(
                    'a' => (object) array('type' => 'n', 'value' => 2),
                    'b' => (object) array('type' => 's', 'value' => "B"),
                    )
            ),
        );
    }

    /**
     * Test 8: Split formula unit.
     */
    public function test_split_formula_unit() {
        $testcases = array(
            // FIXME: these are not valid and won't be accepted in new code
            array('#', array('', '#')),
            // @codingStandardsIgnoreLine
            array('`', array('', '`')),
            array('@', array('', '@')),
        );
    }

}
