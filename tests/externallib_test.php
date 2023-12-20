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
 * qtype_formulas external functions unit tests
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

/**
 * @runTestsInSeparateProcesses
 */
class externallib_test extends \externallib_advanced_testcase {
    public function setUp(): void {
        global $CFG;
        require_once($CFG->dirroot . '/question/type/formulas/classes/external/instantiation.php');
    }

    public function provide_random_global_vars(): array {
        return [
            [
                ['source' => '', 'message' => ''],
                ['randomvars' => '', 'globalvars' => '']
            ],
            [
                ['source' => '', 'message' => ''],
                ['randomvars' => 'a={1,2};', 'globalvars' => '']
            ],
            [
                ['source' => '', 'message' => ''],
                ['randomvars' => '', 'globalvars' => 'a=1']
            ],
            [
                ['source' => '', 'message' => ''],
                ['randomvars' => '', 'globalvars' => 'a={1:5}']
            ],
            [
                ['source' => '', 'message' => ''],
                ['randomvars' => '', 'globalvars' => 'a={1,2,3,4}']
            ],
            [
                ['source' => '', 'message' => ''],
                ['randomvars' => 'a={1,2}', 'globalvars' => 'b=a']
            ],
            [
                ['source' => 'random', 'message' => 'invalid definition of a random variable - you must provide a list of possible values'],
                ['randomvars' => 'a=1', 'globalvars' => '']
            ],
            [
                ['source' => 'global', 'message' => 'unknown variable: a'],
                ['randomvars' => '', 'globalvars' => 'b=a']
            ],
            [
                ['source' => 'global', 'message' => "syntax error: unexpected end of expression after '='"],
                ['randomvars' => '', 'globalvars' => 'a=']
            ],
            [
                ['source' => 'global', 'message' => "syntax error: unexpected end of expression after '+'"],
                ['randomvars' => '', 'globalvars' => 'a=+']
            ],
        ];
    }

    /**
     * @dataProvider provide_random_global_vars
     */
    public function test_check_random_global_vars($expected, $input): void {
        $this->resetAfterTest(true);

        $returnvalue = external\instantiation::check_random_global_vars(
            $input['randomvars'], $input['globalvars']
        );

        $returnvalue = \external_api::clean_returnvalue(
            external\instantiation::check_random_global_vars_returns(), $returnvalue
        );

        $this->assertEquals($expected['source'], $returnvalue['source']);
        $this->assertStringEndsWith($expected['message'], $returnvalue['message']);
    }

    public function provide_random_global_local_vars(): array {
        return [
            [
                ['source' => '', 'message' => ''],
                ['randomvars' => '', 'globalvars' => '', 'localvars' => '']
            ],
            [
                ['source' => '', 'message' => ''],
                ['randomvars' => '', 'globalvars' => '', 'localvars' => 'a=2']
            ],
            [
                ['source' => '', 'message' => ''],
                ['randomvars' => '', 'globalvars' => '', 'localvars' => 'a={1:5}']
            ],
            [
                ['source' => '', 'message' => ''],
                ['randomvars' => '', 'globalvars' => '', 'localvars' => 'a={1,2}']
            ],
            [
                ['source' => 'local', 'message' => 'unknown variable: b'],
                ['randomvars' => '', 'globalvars' => '', 'localvars' => 'a=b']
            ],
            [
                ['source' => '', 'message' => ''],
                ['randomvars' => 'b={1,2}', 'globalvars' => '', 'localvars' => 'a=b']
            ],
            [
                ['source' => '', 'message' => ''],
                ['randomvars' => '', 'globalvars' => 'b=1', 'localvars' => 'a=b']
            ],
            [
                ['source' => 'local', 'message' => "syntax error: unexpected end of expression after '+'"],
                ['randomvars' => '', 'globalvars' => '', 'localvars' => 'a=+']
            ],
            [
                ['source' => 'global', 'message' => "syntax error: unexpected end of expression after '+'"],
                ['randomvars' => '', 'globalvars' => 'a=+', 'localvars' => 'b=2']
            ],
            [
                ['source' => 'global', 'message' => "syntax error: unexpected end of expression after '+'"],
                ['randomvars' => '', 'globalvars' => 'a=+', 'localvars' => 'b=2']
            ],
            [
                ['source' => 'random', 'message' => "syntax error: unexpected end of expression after '+'"],
                ['randomvars' => 'a=+', 'globalvars' => 'b=1', 'localvars' => 'c=2']
            ],
            [
                ['source' => 'random', 'message' => "syntax error: unexpected end of expression after '+'"],
                ['randomvars' => 'a=+', 'globalvars' => 'b=1', 'localvars' => 'c=+++']
            ],
            [
                ['source' => 'random', 'message' => "syntax error: unexpected end of expression after '+'"],
                ['randomvars' => 'a=+', 'globalvars' => '', 'localvars' => 'c=+++']
            ],
            [
                ['source' => 'random', 'message' => "syntax error: unexpected end of expression after '+'"],
                ['randomvars' => 'a=+', 'globalvars' => 'b=++', 'localvars' => 'c=+++']
            ],
            [
                ['source' => 'local', 'message' => "syntax error: unexpected end of expression after '='"],
                ['randomvars' => '', 'globalvars' => '', 'localvars' => 'a=']
            ],
        ];
    }

    /**
     * @dataProvider provide_random_global_local_vars
     */
    public function test_check_local_vars($expected, $input): void {
        $this->resetAfterTest(true);

        $returnvalue = external\instantiation::check_local_vars(
            $input['randomvars'], $input['globalvars'], $input['localvars']
        );
        $returnvalue = \external_api::clean_returnvalue(external\instantiation::check_local_vars_returns(), $returnvalue);

        $this->assertEquals($expected['source'], $returnvalue['source']);
        $this->assertStringEndsWith($expected['message'], $returnvalue['message']);
    }

    public function provide_question_texts(): array {
        return [
            [
                ['question' => '', 'parts' => []],
                ['questiontext' => '', 'parttexts' => [], 'globalvars' => '', 'partvars' => []]
            ],
            [
                [
                    'question' => "No preview available. Check your definition of random variables, " .
                        "global variables, parts' local variables and answers. Original error message: " .
                        "1:3:syntax error: unexpected end of expression after '*'",
                    'parts' => []
                ],
                ['questiontext' => '', 'parttexts' => [], 'globalvars' => 'a=*', 'partvars' => []]
            ],
            [
                ['question' => 'Foo Bar 1', 'parts' => []],
                ['questiontext' => 'Foo Bar {a}', 'parttexts' => [], 'globalvars' => 'a=1', 'partvars' => []]
            ],
            [
                ['question' => 'Foo Bar {a}', 'parts' => []],
                ['questiontext' => 'Foo Bar {a}', 'parttexts' => [], 'globalvars' => 'a="{a}"', 'partvars' => []]
            ],
            [
                ['question' => 'Foo Bar {a}', 'parts' => []],
                ['questiontext' => 'Foo Bar {a}', 'parttexts' => [], 'globalvars' => '', 'partvars' => []],
            ],
            [
                ['question' => 'Foo Bar {a}', 'parts' => ['local 10']],
                ['questiontext' => 'Foo Bar {a}', 'parttexts' => ['local {a}'], 'globalvars' => 'a={1,2,3}', 'partvars' => ['a=10']]
            ],
            [
                ['question' => '', 'parts' => ['Foo Bar 1']],
                ['questiontext' => '', 'parttexts' => ['Foo Bar {a}'], 'globalvars' => 'a=2', 'partvars' => ['a=1']]
            ],
            [
                ['question' => 'Main', 'parts' => ['Foo Bar 1', 'Part 2 6', '2']],
                [
                    'questiontext' => 'Main', 'parttexts' => ['Foo Bar {a}', 'Part 2 {=2*a}',
                    '{=sqrt(a)}'], 'globalvars' => 'a=4', 'partvars' => ['a=1', 'a=3', '']
                ]
            ],
        ];
    }

    /**
     * @dataProvider provide_question_texts
     */
    public function test_render_question_text($expected, $input): void {
        $this->resetAfterTest(true);

        $returnvalue = external\instantiation::render_question_text(
            $input['questiontext'], $input['parttexts'], $input['globalvars'], $input['partvars']
        );
        $returnvalue = \external_api::clean_returnvalue(external\instantiation::render_question_text_returns(), $returnvalue);

        $this->assertEquals($expected['question'], $returnvalue['question']);
        foreach ($expected['parts'] as $i => $part) {
            $this->assertEquals($part, $returnvalue['parts'][$i]);
        }
    }

    public function provide_instantiation_data(): array {
        return [
            [
                ['status' => 'ok', 'data' => [[
                    'randomvars' => [],
                    'globalvars' => [
                        ['name' => 'a', 'value' => '3'],
                        ['name' => 'b', 'value' => '2'],
                        ['name' => 'c', 'value' => '6']
                    ],
                    'parts' => [[['name' => '_0', 'value' => '6']]],
                ]]],
                [
                    'n' => 1, 'randomvars' => '', 'globalvars' => 'a=3; b=2; c=a*b',
                    'localvars' => [''], 'answers' => ['a*b']
                ]
            ],
            [
                ['status' => 'ok', 'data' => [[
                    'randomvars' => [],
                    'globalvars' => [
                        ['name' => 'a', 'value' => '3'],
                        ['name' => 'b', 'value' => '2'],
                        ['name' => 'c', 'value' => '6']
                    ],
                    'parts' => [[
                        ['name' => '_0', 'value' => '6'],
                        ['name' => '_1', 'value' => '6'],
                        ['name' => '_2', 'value' => '6']
                    ]],
                ]]],
                [
                    'n' => 1, 'randomvars' => '', 'globalvars' => 'a=3; b=2; c=a*b',
                    'localvars' => [''], 'answers' => ['[a*b,c,6]']
                ]
            ],
            [
                ['status' => 'ok', 'data' => [[
                    'randomvars' => [],
                    'globalvars' => [['name' => 'a', 'value' => '{a}']],
                    'parts' => [[['name' => '_0', 'value' => '1']]],
                ]]],
                [
                    'n' => 1, 'randomvars' => '', 'globalvars' => 'a={1,2,3}',
                    'localvars' => [''], 'answers' => ['1']
                ]
            ],
            [
                ['status' => 'ok', 'data' => [[
                    'randomvars' => [],
                    'globalvars' => [['name' => 'a', 'value' => '{a}']],
                    'parts' => [[['name' => '_0', 'value' => '1']]],
                ]]],
                [
                    'n' => 1, 'randomvars' => '', 'globalvars' => 'a={1:10}',
                    'localvars' => [''], 'answers' => ['1']
                ]
            ],
            [
                // TODO: old error was 'Invalid answer format: you cannot use an algebraic variable with this answer type'
                ['status' => 'error', 'message' => "algebraic variable 'a' cannot be used in this context"],
                [
                    'n' => 1, 'randomvars' => '', 'globalvars' => '',
                    'localvars' => ['a={1,3}'], 'answers' => ['a']
                ]
            ],
            [
                // TODO: old error was 'Invalid answer format: you cannot use an algebraic variable with this answer type'
                ['status' => 'error', 'message' => "algebraic variable 'a' cannot be used in this context"],
                [
                    'n' => 1, 'randomvars' => '', 'globalvars' => '',
                    'localvars' => ['a={1:5}'], 'answers' => ['a']
                ]
            ],
            [
                ['status' => 'ok', 'data' => []],
                [
                    'n' => 0, 'randomvars' => '', 'globalvars' => '',
                    'localvars' => [], 'answers' => []
                ]
            ],
            [
                ['status' => 'ok', 'data' => [['randomvars' => [], 'globalvars' => [], 'parts' => []]]],
                [
                    'n' => 1, 'randomvars' => '', 'globalvars' => '',
                    'localvars' => [], 'answers' => []
                ]
            ],
            [
                ['status' => 'error', 'message' => "syntax error: unexpected end of expression after '*'"],
                [
                    'n' => 1, 'randomvars' => 'a=*', 'globalvars' => '',
                    'localvars' => [], 'answers' => []
                ]
            ],
            [
                ['status' => 'ok', 'data' => [[
                    'randomvars' => [],
                    'globalvars' => [
                        ['name' => 'a', 'value' => '1'],
                        ['name' => 'b', 'value' => '2'],
                    ],
                    'parts' => [[
                        ['name' => 'a*', 'value' => '3'],
                        ['name' => '_0', 'value' => '1'],
                        ['name' => '_1', 'value' => '3'],
                    ],[
                        ['name' => '_0', 'value' => '1'],
                        ['name' => '_1', 'value' => '2'],
                    ],[
                        ['name' => '_0', 'value' => '1'],
                    ]],
                ]]],
                [
                    'n' => 1, 'randomvars' => '', 'globalvars' => 'a=1; b=2;',
                    'localvars' => ['a=3', '', ''], 'answers' => ['[1,a]', '[a,b]', 'a']
                ]
            ],
            [
                ['status' => 'ok', 'data' => array_fill(0, 2, [
                    'randomvars' => [],
                    'globalvars' => [
                        ['name' => 'a', 'value' => '1'],
                    ],
                    'parts' => [],
                ])],
                [
                    'n' => 2, 'randomvars' => '', 'globalvars' => 'a=1',
                    'localvars' => [], 'answers' => []
                ]
            ],
            [
                ['status' => 'ok', 'data' => array_fill(0, 10, [
                    'randomvars' => [],
                    'globalvars' => [
                        ['name' => 'a', 'value' => '1'],
                    ],
                    'parts' => [[['name' => '_0', 'value' => '1']]],
                ])],
                [
                    'n' => 10, 'randomvars' => '', 'globalvars' => 'a=1',
                    'localvars' => [], 'answers' => ['1']
                ]
            ],
        ];
    }

    /**
     * @dataProvider provide_instantiation_data
     */
    public function test_instantiate($expected, $input) {
        $this->resetAfterTest(true);

        $returnvalue = external\instantiation::instantiate(
            $input['n'], $input['randomvars'], $input['globalvars'], $input['localvars'], $input['answers']
        );
        $returnvalue = \external_api::clean_returnvalue(external\instantiation::instantiate_returns(), $returnvalue);

        $this->assertEquals($expected['status'], $returnvalue['status']);
        if ($expected['status'] === 'error') {
            $this->assertStringEndsWith($expected['message'], $returnvalue['message']);
        } else {
            $this->assertEquals($expected['data'], $returnvalue['data']);
        }
    }
}
