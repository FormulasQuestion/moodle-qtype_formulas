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
 * @copyright  2022 Philipp Imhof
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
 * Unit tests for various functions
 *
 * @copyright  2022 Philipp Imhof
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

class functions_test extends \advanced_testcase {
    /**
     * Test 1: ncr() test.
     */
    public function test_ncr() {
        $testcases = array(
            array(ncr(0, 0), 1),
            array(ncr(1, 5), 0),
            array(ncr(5, 0), 1),
            array(ncr(5, 1), 5),
            array(ncr(5, 2), 10),
            array(ncr(5, 3), 10),
            array(ncr(5, 4), 5),
            array(ncr(5, 5), 1)
        );
        foreach ($testcases as $case) {
            $this->assertEquals($case[1], $case[0]);
        }
    }

    /**
     * Test 2: npr() test.
     */
    public function test_npr() {
        $testcases = array(
            array(npr(0, 0), 0),
            array(npr(1, 5), 0),
            array(npr(5, 0), 1),
            array(npr(5, 1), 5),
            array(npr(5, 2), 20),
            array(npr(5, 3), 60),
            array(npr(5, 4), 120),
            array(npr(5, 5), 120)
        );
        foreach ($testcases as $case) {
            $this->assertEquals($case[1], $case[0]);
        }
    }

    /**
     * Test 3: fact() test.
     */
    public function test_fact() {
        $testcases = array(
            array(fact(0), 1),
            array(fact(1), 1),
            array(fact(3), 6),
            array(fact(6), 720)
        );
        foreach ($testcases as $case) {
            $this->assertEquals($case[1], $case[0]);
        }
    }

    /**
     * Test 4: gcd() test.
     */
    public function test_gcd() {
        $testcases = array(
            array(gcd(0, 0), 0),
            array(gcd(13, 13), 13),
            array(gcd(1, 0), 1),
            array(gcd(3, 2), 1),
            array(gcd(6, 3), 3),
            array(gcd(12, 9), 3),
            array(gcd(2, 3), 1),
            array(gcd(3, 6), 3),
            array(gcd(9, 12), 3)
        );
        foreach ($testcases as $case) {
            $this->assertEquals($case[1], $case[0]);
        }
    }

    /**
     * Test 5: lcm() test.
     */
    public function test_lcm() {
        $testcases = array(
            array(lcm(0, 0), 0),
            array(gcd(13, 13), 13),
            array(lcm(1, 0), 0),
            array(lcm(1, 1), 1),
            array(lcm(3, 2), 6),
            array(lcm(6, 3), 6),
            array(lcm(12, 9), 36),
            array(lcm(2, 3), 6),
            array(lcm(3, 6), 6),
            array(lcm(9, 12), 36)
        );
        foreach ($testcases as $case) {
            $this->assertEquals($case[1], $case[0]);
        }
    }

    /**
     * Test 6: pick() test.
     */
    public function test_pick() {
        $testcases = array(
            array('p1=pick(3,[0,1,2,3,4,5]);', array(
                    'p1' => (object) array('type' => 'n', 'value' => 3),
                    )
            ),
            array('p1=pick(3.9,[0,1,2,3,4,5]);', array(
                    'p1' => (object) array('type' => 'n', 'value' => 3),
                    )
            ),
            array('p1=pick(10,[0,1,2,3,4,5]);', array(
                    'p1' => (object) array('type' => 'n', 'value' => 0),
                    )
            ),
            array('p1=pick(10.9,[0,1,2,3,4,5]);', array(
                    'p1' => (object) array('type' => 'n', 'value' => 0),
                    )
            ),
            array('p1=pick(3,0,1,2,3,4,5);', array(
                    'p1' => (object) array('type' => 'n', 'value' => 3),
                    )
            ),
            array('p1=pick(3.9,0,1,2,3,4,5);', array(
                    'p1' => (object) array('type' => 'n', 'value' => 3),
                    )
            ),
            array('p1=pick(10,0,1,2,3,4,5);', array(
                    'p1' => (object) array('type' => 'n', 'value' => 0),
                    )
            ),
            array('p1=pick(10.9,0,1,2,3,4,5);', array(
                    'p1' => (object) array('type' => 'n', 'value' => 0),
                    )
            ),
            array('p1=pick(3,["A","B","C","D","E","F"]);', array(
                    'p1' => (object) array('type' => 's', 'value' => "D"),
                    )
            ),
            array('p1=pick(3.9,["A","B","C","D","E","F"]);', array(
                    'p1' => (object) array('type' => 's', 'value' => "D"),
                    )
            ),
            array('p1=pick(10,["A","B","C","D","E","F"]);', array(
                    'p1' => (object) array('type' => 's', 'value' => "A"),
                    )
            ),
            array('p1=pick(10.9,["A","B","C","D","E","F"]);', array(
                    'p1' => (object) array('type' => 's', 'value' => "A"),
                    )
            ),
            array('p1=pick(3,"A","B","C","D","E","F");', array(
                    'p1' => (object) array('type' => 's', 'value' => "D"),
                    )
            ),
            array('p1=pick(3.9,"A","B","C","D","E","F");', array(
                    'p1' => (object) array('type' => 's', 'value' => "D"),
                    )
            ),
            array('p1=pick(10,"A","B","C","D","E","F");', array(
                    'p1' => (object) array('type' => 's', 'value' => "A"),
                    )
            ),
            array('p1=pick(10.9,"A","B","C","D","E","F");', array(
                    'p1' => (object) array('type' => 's', 'value' => "A"),
                    )
            ),
            array('p1=pick(3,[0,0],[1,1],[2,2],[3,3],[4,4],[5,5]);', array(
                'p1' => (object) array('type' => 'ln', 'value' => array(3, 3)),
                    )
            ),
            array('p1=pick(3.9,[0,0],[1,1],[2,2],[3,3],[4,4],[5,5]);', array(
                'p1' => (object) array('type' => 'ln', 'value' => array(3, 3)),
                    )
            ),
            array('p1=pick(10,[0,0],[1,1],[2,2],[3,3],[4,4],[5,5]);', array(
                'p1' => (object) array('type' => 'ln', 'value' => array(0, 0)),
                    )
            ),
            array('p1=pick(10.9,[0,0],[1,1],[2,2],[3,3],[4,4],[5,5]);', array(
                'p1' => (object) array('type' => 'ln', 'value' => array(0, 0)),
                    )
            ),
            array('p1=pick(3,["A","A"],["B","B"],["C","C"],["D","D"],["E","E"],["F","F"]);', array(
                'p1' => (object) array('type' => 'ls', 'value' => array("D", "D")),
                    )
            ),
            array('p1=pick(3.9,["A","A"],["B","B"],["C","C"],["D","D"],["E","E"],["F","F"]);', array(
                'p1' => (object) array('type' => 'ls', 'value' => array("D", "D")),
                    )
            ),
            array('p1=pick(10,["A","A"],["B","B"],["C","C"],["D","D"],["E","E"],["F","F"]);', array(
                'p1' => (object) array('type' => 'ls', 'value' => array("A", "A")),
                    )
            ),
            array('p1=pick(10.9,["A","A"],["B","B"],["C","C"],["D","D"],["E","E"],["F","F"]);', array(
                'p1' => (object) array('type' => 'ls', 'value' => array("A", "A")),
                    )
            )
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
            $this->assertNull($errmsg);
            $this->assertEquals($case[1], $result->all);
        }

        $testcases = array(
            array(
                'p1=pick("r",[2,3,5,7,11]);',
                '1: Wrong number or wrong type of parameters for the function pick()'
            ),
            array(
                'p1=pick(2,[2,3],[4,5],["a","b"]);',
                '1: Wrong number or wrong type of parameters for the function pick()'
            )
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
            $this->assertStringStartsWith($case[1], $errmsg);
        }

    }

    /**
     * Test 7: Sigfig function.
     * @copyright  2018 Jean-Michel Vedrine
     */
    public function test_sigfig() {
        $number = .012345;
        $this->assertSame(sigfig($number, 3), '0.0123');
        $this->assertSame(sigfig($number, 4), '0.01235');
        $this->assertSame(sigfig($number, 6), '0.0123450');
        $number = -.012345;
        $this->assertSame(sigfig($number, 3), '-0.0123');
        $this->assertSame(sigfig($number, 4), '-0.01235');
        $this->assertSame(sigfig($number, 6), '-0.0123450');
        $number = 123.45;
        $this->assertSame(sigfig($number, 2), '120');
        $this->assertSame(sigfig($number, 4), '123.5');
        $this->assertSame(sigfig($number, 6), '123.450');
        $number = -123.45;
        $this->assertSame(sigfig($number, 2), '-120');
        $this->assertSame(sigfig($number, 4), '-123.5');
        $this->assertSame(sigfig($number, 6), '-123.450');
        $number = .005;
        $this->assertSame(sigfig($number, 1), '0.005');
        $this->assertSame(sigfig($number, 2), '0.0050');
        $this->assertSame(sigfig($number, 3), '0.00500');
        $number = -.005;
        $this->assertSame(sigfig($number, 1), '-0.005');
        $this->assertSame(sigfig($number, 2), '-0.0050');
        $this->assertSame(sigfig($number, 3), '-0.00500');
    }
}
