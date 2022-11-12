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
 * COMPONENT External functions unit tests
 *
 * @package    qtype_formulas
 * @category   external
 * @copyright  2022 Philipp Imhof
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace qtype_formulas;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/webservice/tests/helpers.php');
require_once($CFG->dirroot . '/question/type/formulas/classes/external/instantiation.php');

class externallib_test extends \externallib_advanced_testcase {

    /**
     * Test
     */
    public function test_check_random_global_vars() {
        $this->resetAfterTest(true);

        $testcases = array(
            array('randomvars' => '', 'globalvars' => '', 'return' => ''),
            array('randomvars' => 'a={1,2};', 'globalvars' => '', 'return' => ''),
            array('randomvars' => '', 'globalvars' => 'a=1', 'return' => ''),
            array('randomvars' => 'a={1,2}', 'globalvars' => 'b=a', 'return' => ''),
            array('randomvars' => 'a=1', 'globalvars' => '', 'return' => '1: a: Syntax error.'),
            array('randomvars' => '', 'globalvars' => 'b=a', 'return' => '1: Variable \'a\' has not been defined. in substitute_vname_by_variables'),
            array('randomvars' => '', 'globalvars' => 'a=', 'return' => '1: A subexpression is empty.'),
            array('randomvars' => '', 'globalvars' => 'a=+', 'return' => '1: Some expressions cannot be evaluated numerically.')
        );

        foreach ($testcases as $case) {
            $returnvalue = external\instantiation::check_random_global_vars(
                $case['randomvars'], $case['globalvars']
            );
            $returnvalue = \external_api::clean_returnvalue(external\instantiation::check_random_global_vars_returns(), $returnvalue);
            $this->assertEquals($case['return'], $returnvalue);
        }
    }

    public function test_check_local_vars() {
        $this->resetAfterTest(true);

        $testcases = array(
            array('randomvars' => '', 'globalvars' => '', 'localvars' => '', 'return' => ''),
            array('randomvars' => '', 'globalvars' => '', 'localvars' => 'a=2', 'return' => ''),
            array('randomvars' => '', 'globalvars' => '', 'localvars' => 'a=b', 'return' => '1: Variable \'b\' has not been defined. in substitute_vname_by_variables'),
            array('randomvars' => 'b={1,2}', 'globalvars' => '', 'localvars' => 'a=b', 'return' => ''),
            array('randomvars' => '', 'globalvars' => 'b=1', 'localvars' => 'a=b', 'return' => ''),
            array('randomvars' => '', 'globalvars' => '', 'localvars' => 'a=+', 'return' => '1: Some expressions cannot be evaluated numerically.'),
            array('randomvars' => '', 'globalvars' => '', 'localvars' => 'a=', 'return' => '1: A subexpression is empty.'),
        );

        foreach ($testcases as $case) {
            $returnvalue = external\instantiation::check_local_vars(
                $case['randomvars'], $case['globalvars'], $case['localvars']
            );
            $returnvalue = \external_api::clean_returnvalue(external\instantiation::check_local_vars_returns(), $returnvalue);
            $this->assertEquals($case['return'], $returnvalue);
        }
    }

    public function test_render_question_text() {
        $this->resetAfterTest(true);

        $testcases = array(
            array(
                'questiontext' => '',
                'parttexts' => array(),
                'globalvars' => '',
                'partvars' => array(),
                'return' => array(
                    'question' => '',
                    'parts' => array()
                )
            ),
            array(
                'questiontext' => '',
                'parttexts' => array(),
                'globalvars' => 'a=*',
                'partvars' => array(),
                'return' => array(
                    'question' => 'No preview available. Check your definition of random variables, ' .
                        'global variables, parts\' local variables and answers. Original error message: ' .
                        '1: Some expressions cannot be evaluated numerically.',
                    'parts' => array()
                )
            ),
            array(
                'questiontext' => 'Foo Bar {a}',
                'parttexts' => array(),
                'globalvars' => 'a=1',
                'partvars' => array(),
                'return' => array(
                    'question' => 'Foo Bar 1',
                    'parts' => array()
                )
            ),
            array(
                'questiontext' => 'Foo Bar {a}',
                'parttexts' => array(),
                'globalvars' => '',
                'partvars' => array(),
                'return' => array(
                    'question' => 'Foo Bar {a}',
                    'parts' => array()
                )
            ),
            array(
                'questiontext' => 'Foo Bar {a}',
                'parttexts' => array(''),
                'globalvars' => '',
                'partvars' => array('a=1'),
                'return' => array(
                    'question' => 'Foo Bar {a}',
                    'parts' => array('')
                )
            ),
            array(
                'questiontext' => '',
                'parttexts' => array('Foo Bar {a}'),
                'globalvars' => 'a=2',
                'partvars' => array('a=1'),
                'return' => array(
                    'question' => '',
                    'parts' => array('Foo Bar 1')
                )
            ),
            array(
                'questiontext' => 'Main',
                'parttexts' => array('Foo Bar {a}', 'Part 2 {=2*a}', '{=sqrt(a)}'),
                'globalvars' => 'a=4',
                'partvars' => array('a=1', 'a=3', ''),
                'return' => array(
                    'question' => 'Main',
                    'parts' => array('Foo Bar 1', 'Part 2 6', '2')
                )
            ),
        );

        foreach ($testcases as $case) {
            $returnvalue = external\instantiation::render_question_text(
                $case['questiontext'], $case['parttexts'], $case['globalvars'], $case['partvars']
            );
            $returnvalue = \external_api::clean_returnvalue(external\instantiation::render_question_text_returns(), $returnvalue);
            $this->assertEquals($case['return'], $returnvalue);
        }
    }

    public function test_instantiate() {
        $this->resetAfterTest(true);

        $testcases = array(
            array(
                'n' => 0,
                'randomvars' => '',
                'globalvars' => '',
                'localvars' => array(),
                'answers' => array(),
                'return' => array(
                    'status' => 'ok',
                    'data' => array()
                )
            ),
            array(
                'n' => 1,
                'randomvars' => '',
                'globalvars' => '',
                'localvars' => array(),
                'answers' => array(),
                'return' => array(
                    'status' => 'ok',
                    'data' => array(
                        array(
                            'randomvars' => array(),
                            'globalvars' => array(),
                            'parts' => array()
                        )
                    )
                )
            ),
            array(
                'n' => 1,
                'randomvars' => 'a=*',
                'globalvars' => '',
                'localvars' => array(),
                'answers' => array(),
                'return' => array(
                    'status' => 'error',
                    'message' => '1: a: Syntax error.'
                )
            ),
            array(
                'n' => 1,
                'randomvars' => '',
                'globalvars' => 'a=1; b=2;',
                'localvars' => array('a=3', '', ''),
                'answers' => array('[1,a]', '[a,b]', 'a'),
                'return' => array(
                    'status' => 'ok',
                    'data' => array(
                        array(
                            'randomvars' => array(),
                            'globalvars' => array(
                                array('name' => 'a', 'value' => 1),
                                array('name' => 'b', 'value' => 2)
                            ),
                            'parts' => array(
                                array(
                                    array('name' => 'a*', 'value' => 3),
                                    array('name' => '_0', 'value' => 1),
                                    array('name' => '_1', 'value' => 3)
                                ),
                                array(
                                    array('name' => '_0', 'value' => 1),
                                    array('name' => '_1', 'value' => 2)
                                ),
                                array(
                                    array('name' => '_0', 'value' => 1)
                                )
                            )
                        )
                    )
                )
            ),
            array(
                'n' => 2,
                'randomvars' => '',
                'globalvars' => 'a=1',
                'localvars' => array(),
                'answers' => array(),
                'return' => array(
                    'status' => 'ok',
                    'data' => array(
                        array(
                            'randomvars' => array(),
                            'globalvars' => array(array('name' => 'a', 'value' => 1)),
                            'parts' => array()
                        ),
                        array(
                            'randomvars' => array(),
                            'globalvars' => array(array('name' => 'a', 'value' => 1)),
                            'parts' => array()
                        )
                    )
                )
            ),
        );

        foreach ($testcases as $case) {
            $returnvalue = external\instantiation::instantiate(
                $case['n'], $case['randomvars'], $case['globalvars'], $case['localvars'], $case['answers']
            );
            $returnvalue = \external_api::clean_returnvalue(external\instantiation::instantiate_returns(), $returnvalue);
            $this->assertEquals($case['return'], $returnvalue);
        }
    }
}