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
        // Test if function is accepted and parsed.
        $qv = new variables;
        $errmsg = null;
        try {
            $v = $qv->vstack_create();
            $result = $qv->evaluate_assignments($v, 'a=ncr(5, 4);');
        } catch (Exception $e) {
            $errmsg = $e->getMessage();
        }
        $this->assertNull($errmsg);

        // Test if function works correctly.
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
        // Test if function is accepted and parsed.
        $qv = new variables;
        $errmsg = null;
        try {
            $v = $qv->vstack_create();
            $result = $qv->evaluate_assignments($v, 'a=npr(5, 4);');
        } catch (Exception $e) {
            $errmsg = $e->getMessage();
        }
        $this->assertNull($errmsg);

        // Test if function works correctly.
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
        // Test if function is accepted and parsed.
        $qv = new variables;
        $errmsg = null;
        try {
            $v = $qv->vstack_create();
            $result = $qv->evaluate_assignments($v, 'a=fact(3);');
        } catch (Exception $e) {
            $errmsg = $e->getMessage();
        }
        $this->assertNull($errmsg);

        // Test if function works correctly.
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
        // Test if function is accepted and parsed.
        $qv = new variables;
        $errmsg = null;
        try {
            $v = $qv->vstack_create();
            $result = $qv->evaluate_assignments($v, 'a=gcd(3, 6);');
        } catch (Exception $e) {
            $errmsg = $e->getMessage();
        }
        $this->assertNull($errmsg);

        // Test if function works correctly.
        $testcases = array(
            array(gcd(0, 0), 0),
            array(gcd(13, 13), 13),
            array(gcd(1, 0), 1),
            array(gcd(10, 0), 10),
            array(gcd(0, 10), 10),
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
        // Test if function is accepted and parsed.
        $qv = new variables;
        $errmsg = null;
        try {
            $v = $qv->vstack_create();
            $result = $qv->evaluate_assignments($v, 'a=lcm(3, 6);');
        } catch (Exception $e) {
            $errmsg = $e->getMessage();
        }
        $this->assertNull($errmsg);

        // Test if function works correctly.
        $testcases = array(
            array(lcm(0, 0), 0),
            array(lcm(13, 13), 13),
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
        // Test if function is accepted and parsed.
        $qv = new variables;
        $errmsg = null;
        try {
            $v = $qv->vstack_create();
            $result = $qv->evaluate_assignments($v, 'a=sigfig(0.123, 1);');
        } catch (Exception $e) {
            $errmsg = $e->getMessage();
        }
        $this->assertNull($errmsg);

        // Test if function works correctly.
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

    /**
     * Test 8: modinv() test.
     */
    public function test_modinv() {
        // Test if function is accepted and parsed.
        $qv = new variables;
        $errmsg = null;
        try {
            $v = $qv->vstack_create();
            $result = $qv->evaluate_assignments($v, 'a=modinv(3, 7);');
        } catch (Exception $e) {
            $errmsg = $e->getMessage();
        }
        $this->assertNull($errmsg);

        // Test if function works correctly.
        $testcases = array(
            array(modinv(15, 3), 0),
            array(modinv(5, 1), 0),
            array(modinv(1, 7), 1),
            array(modinv(2, 7), 4),
            array(modinv(3, 7), 5),
            array(modinv(4, 7), 2),
            array(modinv(5, 7), 3),
            array(modinv(6, 7), 6)
        );
        foreach ($testcases as $case) {
            $this->assertEquals($case[1], $case[0]);
        }
    }

    /**
     * Test 9: modpow() test.
     */
    public function test_modpow() {
        // Test if function is accepted and parsed.
        $qv = new variables;
        $errmsg = null;
        try {
            $v = $qv->vstack_create();
            $result = $qv->evaluate_assignments($v, 'a=modpow(3, 10, 17);');
        } catch (Exception $e) {
            $errmsg = $e->getMessage();
        }
        $this->assertNull($errmsg);

        // Test if function works correctly.
        $testcases = array(
            array(modpow(15, 300, 19), 7),
            array(modpow(15, 18, 19), 1),
            array(modpow(1, 7, 13), 1),
            array(modpow(2, 7, 13), 11),
            array(modpow(3, 7, 13), 3),
            array(modpow(4, 7, 13), 4),
            array(modpow(5, 7, 13), 8),
            array(modpow(6, 7, 13), 7),
            array(modpow(7, 7, 13), 6),
            array(modpow(12, 7, 13), 12),
            array(modpow(12, 8, 13), 1)
        );
        foreach ($testcases as $case) {
            $this->assertEquals($case[1], $case[0]);
        }
    }

    /**
     * Test 10: stdnormpdf() test.
     */
    public function test_stdnormpdf() {
        // Test if function is accepted and parsed.
        $qv = new variables;
        $errmsg = null;
        try {
            $v = $qv->vstack_create();
            $result = $qv->evaluate_assignments($v, 'a=stdnormpdf(1);');
        } catch (Exception $e) {
            $errmsg = $e->getMessage();
        }
        $this->assertNull($errmsg);

        // Test if function works correctly.
        $testcases = array(
            array(stdnormpdf(0), 0.39894228),
            array(stdnormpdf(1), 0.24197072),
            array(stdnormpdf(2), 0.05399097),
            array(stdnormpdf(-1), 0.24197072),
            array(stdnormpdf(-2), 0.05399097),
            array(stdnormpdf(0.5), 0.35206533),
            array(stdnormpdf(-0.5), 0.35206533)
        );
        foreach ($testcases as $case) {
            $this->assertEqualsWithDelta($case[1], $case[0], 1e-7);
        }
    }

    /**
     * Test 11: stdnormcdf() test.
     */
    public function test_stdnormcdf() {
        // Test if function is accepted and parsed.
        $qv = new variables;
        $errmsg = null;
        try {
            $v = $qv->vstack_create();
            $result = $qv->evaluate_assignments($v, 'a=stdnormcdf(0);');
        } catch (Exception $e) {
            $errmsg = $e->getMessage();
        }
        $this->assertNull($errmsg);

        // Test if function works correctly.
        $testcases = array(
            array(stdnormcdf(0), 0.5),
            array(stdnormcdf(1), 0.84134),
            array(stdnormcdf(2), 0.97725),
            array(stdnormcdf(-1), 0.15866),
            array(stdnormcdf(-2), 0.02275),
            array(stdnormcdf(0.5), 0.69146),
            array(stdnormcdf(-0.5), 0.30854)
        );
        foreach ($testcases as $case) {
            $this->assertEqualsWithDelta($case[1], $case[0], .00001);
        }
    }

    /**
     * Test 12: normcdf() test.
     */
    public function test_normcdf() {
        // Test if function is accepted and parsed.
        $qv = new variables;
        $errmsg = null;
        try {
            $v = $qv->vstack_create();
            $result = $qv->evaluate_assignments($v, 'a=normcdf(1, 1, 1);');
        } catch (Exception $e) {
            $errmsg = $e->getMessage();
        }
        $this->assertNull($errmsg);

        // Test if function works correctly.
        $testcases = array(
            array(normcdf(1, 1, 5), 0.5),
            array(normcdf(3, 3, 5), 0.5),
            array(normcdf(7, 10, 30), 0.46017),
            array(normcdf(-8, 10, 30), 0.27425),
            array(normcdf(15, 5, 10), 0.84134),
            array(normcdf(-5, 5, 10), 0.15866)
        );
        foreach ($testcases as $case) {
            $this->assertEqualsWithDelta($case[1], $case[0], .00001);
        }
    }

    /**
     * binomialpdf() test.
     */
    public function test_binomialpdf() {
        // Test if function is accepted and parsed.
        $qv = new variables;
        $errmsg = null;
        try {
            $v = $qv->vstack_create();
            $qv->evaluate_assignments($v, 'a=binomialpdf(1, 1, 1);');
        } catch (Exception $e) {
            $errmsg = $e->getMessage();
        }
        $this->assertNull($errmsg);

        // Test if function works correctly.
        $testcases = [
            [binomialpdf(1, 0, 1), 0],
            [binomialpdf(1, 0, 0), 1],
            [binomialpdf(1, 1, 1), 1],
            [binomialpdf(1, 1, 0), 0],
            [binomialpdf(3, 0.5, 0), 0.125],
            [binomialpdf(3, 0.5, 1), 0.375],
            [binomialpdf(3, 0.5, 2), 0.375],
            [binomialpdf(3, 0.5, 3), 0.125],
        ];
        foreach ($testcases as $case) {
            $this->assertEqualsWithDelta($case[1], $case[0], 1e-6);
        }
    }

    /**
     * binomialcdf() test.
     */
    public function test_binomialcdf() {
        // Test if function is accepted and parsed.
        $qv = new variables;
        $errmsg = null;
        try {
            $v = $qv->vstack_create();
            $qv->evaluate_assignments($v, 'a=binomialcdf(1, 1, 1);');
        } catch (Exception $e) {
            $errmsg = $e->getMessage();
        }
        $this->assertNull($errmsg);

        // Test if function works correctly.
        $testcases = [
            [binomialcdf(1, 0, 1), 1],
            [binomialcdf(1, 0, 0), 1],
            [binomialcdf(1, 1, 1), 1],
            [binomialcdf(1, 1, 0), 0],
            [binomialcdf(3, 0.5, 0), 0.125],
            [binomialcdf(3, 0.5, 1), 0.5],
            [binomialcdf(3, 0.5, 2), 0.875],
            [binomialcdf(3, 0.5, 3), 1],
        ];
        foreach ($testcases as $case) {
            $this->assertEqualsWithDelta($case[1], $case[0], 1e-6);
        }
    }

    /**
     * Test number conversion functions decbin(), decoct(), octdec() and bindec()
     */
    public function test_number_conversions() {
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
            $this->assertNull($errmsg);
            $this->assertEquals($case[1], $result->all);
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
            $this->assertNotNull($errmsg);
        }
    }

    /**
     * Test: incovation of all documented trigonometric / hyberbolic functions
     */
    public function test_invocation_trigonometric() {
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
                $this->assertNull($errmsg);
            } else {
                $this->assertNotNull($errmsg);
            }
        }
    }

    /**
     * Test: incovation of all documented combinatorial functions
     */
    public function test_invocation_combinatorial() {
        $testcases = array(
            array(true, 'a=inv([0, 1, 2, 3]);'),
            array(false, 'a=inv();'),
            array(false, 'a=inv(1);'),
            array(false, 'a=inv(1, 2);'),
            array(false, 'a=inv([1, 4, 0]);'), // Not consecutive.
            array(false, 'a=inv([1, 2, 3]);'), // Lowest is not zero.
            array(false, 'a=inv([1, 2], 1);'),
            array(false, 'a=inv([1, 2], [3, 4]);'),
            array(true, 'a=ncr(5, 2);'),
            array(false, 'a=ncr();'),
            array(false, 'a=ncr(2);'),
            array(false, 'a=ncr(5, 2, 3);'),
            array(true, 'a=npr(5, 2);'),
            array(false, 'a=npr();'),
            array(false, 'a=npr(2);'),
            array(false, 'a=npr(5, 2, 3);'),
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
                $this->assertNull($errmsg);
            } else {
                $this->assertNotNull($errmsg);
            }
        }
    }

    /**
     * Test: incovation of all documented algebraic / other numerical functions
     */
    public function test_invocation_algebraic() {
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
            array(true, 'a=fmod(3, 2);'),
            array(false, 'a=fmod();'),
            array(false, 'a=fmod(0.5);'),
            array(false, 'a=fmod(1, 2, 3);'),
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
            array(true, 'a=poly("x", [1]);'),
            array(true, 'a=poly("x", [1, 2, 3, 4, 5, 6, 7, 8, 9, 10]);'),
            array(true, 'a=poly("x", 1);'),
            array(false, 'a=poly("x", 1, 2);'),
            array(false, 'a=poly("x");'),
            array(false, 'a=poly();'),
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
                $this->assertNull($errmsg);
            } else {
                $this->assertNotNull($errmsg);
            }
        }
    }

    /**
     * Test: incovation of all documented string/array functions
     */
    public function test_invocation_string_array() {
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
            array(true, 'a=map("+", [1, 2], [3, 4]);'),
            array(true, 'a=map("sigfig", [2.123, 3.568], 3);'),
            array(true, 'a=map("stdnormcdf", [2, 1]);'),
            array(true, 'a=map("abs", [-1, -2]);'),
            array(false, 'a=map("+", [1, 2]);'), // Binary operator needs two lists.
            array(false, 'a=map("abs", [-1, -2], [3, 4]);'),
            array(false, 'a=map("x", [-1, -2]);'),
            array(false, 'a=map();'),
            array(false, 'a=map("+");'),
            array(false, 'a=map("abs");'),
            array(false, 'a=map([1, 2, 3]);'),
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
                $this->assertNull($errmsg);
            } else {
                $this->assertNotNull($errmsg);
            }
        }
    }

    /**
     * Test poly() function
     */
    public function test_poly() {
        $testcases = array(
            // With just a number...
            array('p=poly(5);', '+5'),
            array('p=poly(1.5);', '+1.5'),
            array('p=poly(0);', '0'),
            array('p=poly(-1.5);', '-1.5'),
            array('p=poly(-5);', '-5'),
            // With one variable (or arbitrary string) and a number...
            array('p=poly("x", -5);', '-5x'),
            array('p=poly("x", 3);', '3x'),
            array('p=poly("x", 3.7);', '3.7x'),
            array('p=poly("x", 1);', 'x'),
            array('p=poly("x", -1);', '-x'),
            array('p=poly("x", -1.8);', '-1.8x'),
            array('p=poly("x", 3, "+");', '+3x'),
            array('p=poly("x^5", 3, "+");', '+3x^5'),
            array('p=poly("x", 0);', '0'),
            // Usage of other variables as coefficients...
            array('a=5; b=2; p=poly([a,b]);', '5x+2'),
            array('a=5; b=2; p=poly(a*b);', '+10'),
            // Usage of other functions in the list of coefficients...
            array('p=poly("x", [1, sqrt(3**2), 1]);', 'x^{2}+3x+1'),
            // With a variable and a list of numbers, with or without a separator...
            array('p=poly("x", [1, 1, 1]);', 'x^{2}+x+1'),
            array(
                'p=poly("x", [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15, 16, 17, 18, 19, 20]);',
                'x^{19}+2x^{18}+3x^{17}+4x^{16}+5x^{15}+6x^{14}+7x^{13}+8x^{12}+9x^{11}+10x^{10}+11x^{9}'
                .'+12x^{8}+13x^{7}+14x^{6}+15x^{5}+16x^{4}+17x^{3}+18x^{2}+19x+20'
            ),
            array('p=poly("x", [1.3, 1.5, 1.9]);', '1.3x^{2}+1.5x+1.9'),
            array('p=poly("x", [0, 0, 1]);', '1'),
            array('p=poly("y", [0, 0, 1]);', '1'),
            array('p=poly("x", [0, -1]);', '-1'),
            array('p=poly("y", [0, -1]);', '-1'),
            array('p=poly("y", [0, -2.8]);', '-2.8'),
            array('p=poly("x", [1, 0, 1]);', 'x^{2}+1'),
            array('p=poly("y", [1, 0, 1]);', 'y^{2}+1'),
            array('p=poly("x", [1, 2, 3]);', 'x^{2}+2x+3'),
            array('p=poly("x", [-1, -2, -3]);', '-x^{2}-2x-3'),
            array('p=poly("y", [-1, -2, -3]);', '-y^{2}-2y-3'),
            array('p=poly("y", [1, 1, 1]);', 'y^{2}+y+1'),
            array('p=poly("y", [1, 2, 3]);', 'y^{2}+2y+3'),
            array('p=poly("y", [2, -1], "&");', '2y&-1'),
            array('p=poly("z", [1, 1, 1], "&");', 'z^{2}&+z&+1'),
            array('p=poly("y", [-1, -2, -3], "&");', '-y^{2}&-2y&-3'),
            array('p=poly("x", [0, 0, 0]);', '0'),
            array('p=poly("x", [0, 0, 0], "&");', '&&0'),
            // With multiple variables and coefficients plus a separator...
            array('p=poly(["x","y","z"], [1, 1, 1], "&");', 'x&+y&+z'),
            array('p=poly(["x","y","z"], [2, 3, 4], "&");', '2x&+3y&+4z'),
            array('p=poly(["x","y","z"], [-2, -3, -4], "&");', '-2x&-3y&-4z'),
            array('p=poly(["x","y","z"], [-2.4, -3.1, -4.0], "&");', '-2.4x&-3.1y&-4z'),
            array('p=poly(["x","y","z"], [-1, -1, -1], "&");', '-x&-y&-z'),
            array('p=poly(["x","y","z"], [0, 0, 0], "&");', '&&0'),
            array('p=poly(["x","y","z"], [0, 0, 0]);', '0'),
            // With the default variable x, with or without a separator...
            array('p=poly([1, 1, 1]);', 'x^{2}+x+1'),
            array('p=poly([-1, -1, -1]);', '-x^{2}-x-1'),
            array('p=poly([0, 0, 1]);', '1'),
            array('p=poly([0, 0, 0]);', '0'),
            array('p=poly([0, -1]);', '-1'),
            array('p=poly([1, 0, 1]);', 'x^{2}+1'),
            array('p=poly([1, 2, 3]);', 'x^{2}+2x+3'),
            array('p=poly([-1, -2, -3]);', '-x^{2}-2x-3'),
            array('p=poly([-1, -2, -3], "&");', '-x^{2}&-2x&-3'),
            array('p=poly([0, 1], "&");', '&1'),
            array('p=poly([1, 0, 1], "&");', 'x^{2}&&+1'),
            array('p=poly([1, 0, 0, 1], "&");', 'x^{3}&&&+1'),
            // With a list of variables and coefficients...
            array('p=poly(["x", "y", "xy"], [-1, -2, -3]);', '-x-2y-3xy'),
            array('p=poly(["x", "y", "xy"], [1, 0, -3]);', 'x-3xy'),
            array('p=poly(["x", "y"], [1, 0, -3]);', 'x-3x'),
            array('p=poly(["x", "y"], [1, 1, 1, 1]);', 'x+y+x+y'),
            array('p=poly(["x", "y"], [1, 1, 1, 1], "&");', 'x&+y&+x&+y'),
            // With an empty string and a separator, we build a matrix row...
            array('p=poly("", [1, 1, 1, 1], "&");', '1&1&1&1'),
            array('p=poly("", [-1, 1, -1, 1], "&");', '-1&1&-1&1'),
            array('p=poly("", [1, -1, 1, -1], "&");', '1&-1&1&-1'),
            array('p=poly("", [0, 0, 0, 0], "&");', '0&0&0&0'),
            array('p=poly("", [1, 0, 2, 3], "&");', '1&0&2&3'),
            array('p=poly("", [0, 1, 0, -1], "&");', '0&1&0&-1'),
            // With double separators for e.g. equation systems...
            array('p=poly(["x", "y", "z"], [1, 1, 1], "&&");', 'x&+&y&+&z'),
            array('p=poly(["x", "y", "z"], [1, 2, 3], "&&");', 'x&+&2y&+&3z'),
            array('p=poly(["x", "y", "z"], [-1, -1, -1], "&&");', '-x&-&y&-&z'),
            array('p=poly(["x", "y", "z"], [-1, -2, -3], "&&");', '-x&-&2y&-&3z'),
            array('p=poly(["x", "y", "z"], [0, 1, 1], "&&");', '&&y&+&z'),
            array('p=poly(["x", "y", "z"], [1, 0, 1], "&&");', 'x&&&+&z'),
            array('p=poly(["x", "y", "z"], [1, 1, 0], "&&");', 'x&+&y&&'),
            array('p=poly(["x", "y", "z"], [0, 0, 1], "&&");', '&&&&z'),
            array('p=poly(["x", "y", "z"], [0, 0, -1], "&&");', '&&&-&z'),
            array('p=poly(["x", "y", "z"], [0, 0, 0], "&&");', '&&&&0'),
            // Separator with even length, but not doubled; no practical use...
            array('p=poly(["x", "y", "z"], [1, -2, 3], "&#");', 'x&#-2y&#+3z'),
            // Artificially making the lengh odd; no practical use...
            array('p=poly(["x", "y", "z"], [1, -2, 3], "&& ");', 'x&& -2y&& +3z'),
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
            $this->assertEquals($case[1], $result->all['p']->value);
        }
        $testcases = array(
            array('p=poly();', '1: A subexpression is empty.'),
            array('p=poly("x");', '1: Wrong number or wrong type of parameters'),
            array('p=poly("x", "x");', '1: Wrong number or wrong type of parameters'),
            array('p=poly(["x", "y"]);', '1: Wrong number or wrong type of parameters'),
            array('p=poly(["x", "y"], 1);', '1: Wrong number or wrong type of parameters'),

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
     * Test fqversionnumber() function
     */
    public function test_fqversionnumber() {
        $qv = new variables();
        $errmsg = null;
        try {
            $v = $qv->vstack_create();
            $result = $qv->evaluate_assignments($v, 'a=fqversionnumber();');
        } catch (Exception $e) {
            $errmsg = $e->getMessage();
        }
        $this->assertNull($errmsg);
        $this->assertEquals(get_config('qtype_formulas')->version, $result->all['a']->value);
    }

    public function test_fmod() {
        $testcases = array(
            array(fmod(35, 20), 15),
            array(fmod(-35, 20), 5),
            array(fmod(35, -20), -5),
            array(fmod(-35, -20), -15),
            array(fmod(12, 3), 0),
            array(fmod(5, 8), 5),
            array(fmod(5.7, 1.3), 0.5),
            array(fmod(0, 7.9), 0),
            array(fmod(2, 0.4), 0)
        );
        foreach ($testcases as $case) {
            $this->assertEquals($case[1], $case[0]);
        }
        $qv = new variables();
        $errmsg = null;
        try {
            $v = $qv->vstack_create();
            $qv->evaluate_assignments($v, 'a=fmod(4, 0);');
        } catch (Exception $e) {
            $errmsg = $e->getMessage();
        }
        $this->assertEquals('1: ' . get_string('error_eval_numerical', 'qtype_formulas'), $errmsg);
    }

}
