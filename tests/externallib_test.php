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

namespace qtype_formulas;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/webservice/tests/helpers.php');

/**
 * Unit test for the instantiation web service.
 *
 * @copyright  2024 Philipp Imhof
 * @package    qtype_formulas
 * @category   test
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @runTestsInSeparateProcesses
 * @covers \qtype_formulas\external\instantiation
 */
final class externallib_test extends \externallib_advanced_testcase {
    public function setUp(): void {
        global $CFG;
        require_once($CFG->dirroot . '/question/type/formulas/classes/external/instantiation.php');

        $this->resetAfterTest(true);
        parent::setUp();
    }

    /**
     * Data provider.
     *
     * @return array
     */
    public static function provide_random_global_vars(): array {
        return [
            [
                ['source' => '', 'message' => ''],
                ['randomvars' => '', 'globalvars' => ''],
            ],
            [
                ['source' => '', 'message' => ''],
                ['randomvars' => 'a={1,2};', 'globalvars' => ''],
            ],
            [
                ['source' => '', 'message' => ''],
                ['randomvars' => 'a={1,2};', 'globalvars' => 'a=4'],
            ],
            [
                ['source' => '', 'message' => ''],
                ['randomvars' => '', 'globalvars' => 'a=1'],
            ],
            [
                ['source' => '', 'message' => ''],
                ['randomvars' => '', 'globalvars' => 'a={1:5}'],
            ],
            [
                ['source' => '', 'message' => ''],
                ['randomvars' => '', 'globalvars' => 'a={1,2,3,4}'],
            ],
            [
                ['source' => '', 'message' => ''],
                ['randomvars' => 'a={1,2}', 'globalvars' => 'b=a'],
            ],
            [
                [
                    'source' => 'random',
                    'message' => 'Invalid definition of a random variable - you must provide a list of possible values.',
                ],
                ['randomvars' => 'a=1', 'globalvars' => ''],
            ],
            [
                ['source' => 'global', 'message' => 'Unknown variable: a'],
                ['randomvars' => '', 'globalvars' => 'b=a'],
            ],
            [
                ['source' => 'global', 'message' => "Syntax error: unexpected end of expression after '='."],
                ['randomvars' => '', 'globalvars' => 'a='],
            ],
            [
                ['source' => 'global', 'message' => "Syntax error: unexpected end of expression after '+'."],
                ['randomvars' => '', 'globalvars' => 'a=+'],
            ],
        ];
    }

    /**
     * Test for instantiation::check_random_global_vars().
     *
     * @param array $expected expected validation ('source' => string, 'message' => string)
     * @param array $input form input to validate ('randomvars' => string, 'globalvars' => string)
     * @dataProvider provide_random_global_vars
     */
    public function test_check_random_global_vars($expected, $input): void {
        $returnvalue = external\instantiation::check_random_global_vars(
            $input['randomvars'], $input['globalvars']
        );

        $returnvalue = \external_api::clean_returnvalue(
            external\instantiation::check_random_global_vars_returns(), $returnvalue
        );

        self::assertEquals($expected['source'], $returnvalue['source']);
        // As from PHP Unit 11.5, assertStringEndsWith() cannot be used to check for empty suffix anymore.
        if ($expected['message'] === '') {
            self::assertEmpty($returnvalue['message']);
        } else {
            self::assertStringEndsWith($expected['message'], $returnvalue['message']);
        }
    }

    /**
     * Data provider.
     *
     * @return array
     */
    public static function provide_random_global_local_vars(): array {
        return [
            [
                ['source' => '', 'message' => ''],
                ['randomvars' => '', 'globalvars' => '', 'localvars' => ''],
            ],
            [
                ['source' => '', 'message' => ''],
                ['randomvars' => '', 'globalvars' => '', 'localvars' => 'a=2'],
            ],
            [
                ['source' => '', 'message' => ''],
                ['randomvars' => '', 'globalvars' => '', 'localvars' => 'a={1:5}'],
            ],
            [
                ['source' => '', 'message' => ''],
                ['randomvars' => '', 'globalvars' => '', 'localvars' => 'a={1,2}'],
            ],
            [
                ['source' => 'local', 'message' => 'Unknown variable: b'],
                ['randomvars' => '', 'globalvars' => '', 'localvars' => 'a=b'],
            ],
            [
                ['source' => '', 'message' => ''],
                ['randomvars' => 'b={1,2}', 'globalvars' => '', 'localvars' => 'a=b'],
            ],
            [
                ['source' => '', 'message' => ''],
                ['randomvars' => '', 'globalvars' => 'b=1', 'localvars' => 'a=b'],
            ],
            [
                ['source' => '', 'message' => ''],
                ['randomvars' => '', 'globalvars' => 'b=1', 'localvars' => 'a={1,2,3}'],
            ],
            [
                ['source' => 'local', 'message' => "Syntax error: unexpected end of expression after '+'."],
                ['randomvars' => '', 'globalvars' => '', 'localvars' => 'a=+'],
            ],
            [
                ['source' => 'global', 'message' => "Syntax error: unexpected end of expression after '+'."],
                ['randomvars' => '', 'globalvars' => 'a=+', 'localvars' => 'b=2'],
            ],
            [
                ['source' => 'global', 'message' => "Syntax error: unexpected end of expression after '+'."],
                ['randomvars' => '', 'globalvars' => 'a=+', 'localvars' => 'b=2'],
            ],
            [
                ['source' => 'random', 'message' => "Syntax error: unexpected end of expression after '+'."],
                ['randomvars' => 'a=+', 'globalvars' => 'b=1', 'localvars' => 'c=2'],
            ],
            [
                ['source' => 'random', 'message' => "Syntax error: unexpected end of expression after '+'."],
                ['randomvars' => 'a=+', 'globalvars' => 'b=1', 'localvars' => 'c=+++'],
            ],
            [
                ['source' => 'random', 'message' => "Syntax error: unexpected end of expression after '+'."],
                ['randomvars' => 'a=+', 'globalvars' => '', 'localvars' => 'c=+++'],
            ],
            [
                ['source' => 'random', 'message' => "Syntax error: unexpected end of expression after '+'."],
                ['randomvars' => 'a=+', 'globalvars' => 'b=++', 'localvars' => 'c=+++'],
            ],
            [
                ['source' => 'local', 'message' => "Syntax error: unexpected end of expression after '='."],
                ['randomvars' => '', 'globalvars' => '', 'localvars' => 'a='],
            ],
        ];
    }

    /**
     * Test for instantiation::check_local_vars().
     *
     * @param array $expected expected validation ('source' => string, 'message' => string)
     * @param array $input form input to validate ('randomvars' => string, 'globalvars' => string, 'localvars' => string)
     * @dataProvider provide_random_global_local_vars
     */
    public function test_check_local_vars($expected, $input): void {
        $returnvalue = external\instantiation::check_local_vars(
            $input['randomvars'], $input['globalvars'], $input['localvars']
        );
        $returnvalue = \external_api::clean_returnvalue(external\instantiation::check_local_vars_returns(), $returnvalue);

        self::assertEquals($expected['source'], $returnvalue['source']);
        // As from PHP Unit 11.5, assertStringEndsWith() cannot be used to check for empty suffix anymore.
        if ($expected['message'] === '') {
            self::assertEmpty($returnvalue['message']);
        } else {
            self::assertStringEndsWith($expected['message'], $returnvalue['message']);
        }
    }

    /**
     * Data provider.
     *
     * @return array
     */
    public static function provide_question_texts(): array {
        return [
            [
                ['question' => '', 'parts' => []],
                ['questiontext' => '', 'parttexts' => [], 'globalvars' => '', 'partvars' => []],
            ],
            [
                [
                    'question' => "No preview available. Check your definition of random variables, " .
                        "global variables, parts' local variables and answers. Original error message: " .
                        "1:3:Syntax error: unexpected end of expression after '*'.",
                    'parts' => [],
                ],
                ['questiontext' => '', 'parttexts' => [], 'globalvars' => 'a=*', 'partvars' => []],
            ],
            [
                ['question' => 'Foo Bar 1', 'parts' => []],
                ['questiontext' => 'Foo Bar {a}', 'parttexts' => [], 'globalvars' => 'a=1', 'partvars' => []],
            ],
            [
                ['question' => 'Foo Bar {a}', 'parts' => []],
                ['questiontext' => 'Foo Bar {a}', 'parttexts' => [], 'globalvars' => 'a="{a}"', 'partvars' => []],
            ],
            [
                ['question' => 'Foo Bar {a}', 'parts' => []],
                ['questiontext' => 'Foo Bar {a}', 'parttexts' => [], 'globalvars' => '', 'partvars' => []],
            ],
            [
                ['question' => 'Foo Bar {a}', 'parts' => ['local 10']],
                [
                    'questiontext' => 'Foo Bar {a}',
                    'parttexts' => ['local {a}'],
                    'globalvars' => 'a={1,2,3}',
                    'partvars' => ['a=10'],
                ],
            ],
            [
                ['question' => '', 'parts' => ['Foo Bar 1']],
                ['questiontext' => '', 'parttexts' => ['Foo Bar {a}'], 'globalvars' => 'a=2', 'partvars' => ['a=1']],
            ],
            [
                ['question' => 'Main', 'parts' => ['Foo Bar 1', 'Part 2 6', '2']],
                [
                    'questiontext' => 'Main', 'parttexts' => ['Foo Bar {a}', 'Part 2 {=2*a}',
                    '{=sqrt(a)}'], 'globalvars' => 'a=4', 'partvars' => ['a=1', 'a=3', ''],
                ],
            ],
            [
                [
                    'question' => "No preview available. Check your definition of random variables, " .
                        "global variables, parts' local variables and answers. Original error message: " .
                        "1:4:Division by zero is not defined.",
                    'parts' => [],
                ],
                [
                    'questiontext' => 'foo', 'parttexts' => ['bar {a}'], 'globalvars' => '',
                    'partvars' => ['a=1/0'],
                ],
            ],
        ];
    }

    /**
     * Test for instantiation::render_question_text().
     *
     * @param array $expected expected output ('question' => string, 'parts' => string[])
     * @param array $input data ('questiontext' => string, 'parttexts' => string[], 'globalvars' => string, 'partvars' => string[])
     * @dataProvider provide_question_texts
     */
    public function test_render_question_text($expected, $input): void {
        $returnvalue = external\instantiation::render_question_text(
            $input['questiontext'], $input['parttexts'], $input['globalvars'], $input['partvars']
        );
        $returnvalue = \external_api::clean_returnvalue(external\instantiation::render_question_text_returns(), $returnvalue);

        self::assertEquals($expected['question'], $returnvalue['question']);
        foreach ($expected['parts'] as $i => $part) {
            self::assertEquals($part, $returnvalue['parts'][$i]);
        }
    }

    /**
     * Data provider.
     *
     * @return array
     */
    public static function provide_instantiation_data(): array {
        return [
            [
                ['status' => 'error', 'message' => "No answer has been defined for part 1."],
                [
                    'n' => 1, 'randomvars' => '', 'globalvars' => '',
                    'localvars' => [''], 'answers' => [''],
                ],
            ],
            [
                ['status' => 'ok', 'data' => [[
                    'randomvars' => [],
                    'globalvars' => [],
                    'parts' => [[['name' => '_0', 'value' => '1']]],
                ]]],
                [
                    'n' => 1, 'randomvars' => '', 'globalvars' => '',
                    'localvars' => [''], 'answers' => ['1'],
                ],
            ],
            [
                ['status' => 'ok', 'data' => [[
                    'randomvars' => [],
                    'globalvars' => [
                        ['name' => 'a', 'value' => '1'],
                        ['name' => 'b', 'value' => '2'],
                        ['name' => 'c', 'value' => '0'],
                    ],
                    'parts' => [[['name' => '_0', 'value' => '1']]],
                ]]],
                [
                    'n' => 1, 'randomvars' => '', 'globalvars' => 'a=1; b=2; c=(b<a);',
                    'localvars' => [''], 'answers' => ['1'],
                ],
            ],
            [
                ['status' => 'ok', 'data' => [[
                    'randomvars' => [],
                    'globalvars' => [
                        ['name' => 'a', 'value' => '3'],
                        ['name' => 'b', 'value' => '2'],
                        ['name' => 'c', 'value' => '6'],
                    ],
                    'parts' => [[['name' => '_0', 'value' => '6']]],
                ]]],
                [
                    'n' => -1, 'randomvars' => '', 'globalvars' => 'a=3; b=2; c=a*b',
                    'localvars' => [''], 'answers' => ['a*b'],
                ],
            ],
            [
                ['status' => 'ok', 'data' => [[
                    'randomvars' => [],
                    'globalvars' => [
                        ['name' => 'a', 'value' => '3'],
                        ['name' => 'b', 'value' => '2'],
                        ['name' => 'c', 'value' => '6'],
                    ],
                    'parts' => [[
                        ['name' => '_0', 'value' => '6'],
                        ['name' => '_1', 'value' => '6'],
                        ['name' => '_2', 'value' => '6'],
                    ]],
                ]]],
                [
                    'n' => 1, 'randomvars' => '', 'globalvars' => 'a=3; b=2; c=a*b',
                    'localvars' => [''], 'answers' => ['[a*b,c,6]'],
                ],
            ],
            [
                ['status' => 'ok', 'data' => [[
                    'randomvars' => [],
                    'globalvars' => [['name' => 'a', 'value' => '{a}']],
                    'parts' => [[['name' => '_0', 'value' => '1']]],
                ]]],
                [
                    'n' => 1, 'randomvars' => '', 'globalvars' => 'a={1,2,3}',
                    'localvars' => [''], 'answers' => ['1'],
                ],
            ],
            // For the next case, we define a "random" variable with no randomness.
            [
                ['status' => 'ok', 'data' => [[
                    'randomvars' => [['name' => 'a', 'value' => '1']],
                    'globalvars' => [['name' => 'a*', 'value' => '5']],
                    'parts' => [[['name' => '_0', 'value' => '1']]],
                ]]],
                [
                    'n' => 1, 'randomvars' => 'a={1,1}', 'globalvars' => 'a=5',
                    'localvars' => [''], 'answers' => ['1'],
                ],
            ],
            [
                ['status' => 'ok', 'data' => [[
                    'randomvars' => [],
                    'globalvars' => [['name' => 'a', 'value' => '{a}']],
                    'parts' => [[['name' => '_0', 'value' => '1']]],
                ]]],
                [
                    'n' => 1, 'randomvars' => '', 'globalvars' => 'a={1:10}',
                    'localvars' => [''], 'answers' => ['1'],
                ],
            ],
            [
                ['status' => 'ok', 'data' => [[
                    'randomvars' => [],
                    'globalvars' => [['name' => 'a', 'value' => '[1, 2]']],
                    'parts' => [[['name' => '_0', 'value' => '1']]],
                ]]],
                [
                    'n' => 1, 'randomvars' => '', 'globalvars' => 'a=[1,2]',
                    'localvars' => [''], 'answers' => ['1'],
                ],
            ],
            [
                ['status' => 'ok', 'data' => [[
                    'randomvars' => [],
                    'globalvars' => [['name' => 'a', 'value' => '{a}']],
                    'parts' => [[
                        ['name' => 'a*', 'value' => '{a}'],
                        ['name' => '_0', 'value' => '1'],
                    ]],
                ]]],
                [
                    'n' => 1, 'randomvars' => '', 'globalvars' => 'a={1:10}',
                    'localvars' => ['a={1,2,3}'], 'answers' => ['1'],
                ],
            ],
            [
                ['status' => 'error', 'message' => "Algebraic variable 'a' cannot be used in this context."],
                [
                    'n' => 1, 'randomvars' => '', 'globalvars' => '',
                    'localvars' => ['a={1,3}'], 'answers' => ['a'],
                ],
            ],
            [
                ['status' => 'error', 'message' => 'Division by zero is not defined.'],
                [
                    'n' => -1, 'randomvars' => '', 'globalvars' => '',
                    'localvars' => ['a=2'], 'answers' => ['1/(a-a)'],
                ],
            ],
            [
                ['status' => 'error', 'message' => "Algebraic variable 'a' cannot be used in this context."],
                [
                    'n' => 1, 'randomvars' => '', 'globalvars' => '',
                    'localvars' => ['a={1:5}'], 'answers' => ['a'],
                ],
            ],
            [
                ['status' => 'ok', 'data' => []],
                [
                    'n' => 0, 'randomvars' => '', 'globalvars' => '',
                    'localvars' => [], 'answers' => [],
                ],
            ],
            [
                ['status' => 'ok', 'data' => [['randomvars' => [], 'globalvars' => [], 'parts' => []]]],
                [
                    'n' => 1, 'randomvars' => '', 'globalvars' => '',
                    'localvars' => [], 'answers' => [],
                ],
            ],
            [
                ['status' => 'error', 'message' => "Syntax error: unexpected end of expression after '*'."],
                [
                    'n' => 1, 'randomvars' => 'a=*', 'globalvars' => '',
                    'localvars' => [], 'answers' => [],
                ],
            ],
            [
                ['status' => 'error', 'message' => 'Division by zero is not defined.'],
                [
                    'n' => 50, 'randomvars' => 'a={0, 1}', 'globalvars' => 'b = 1/a',
                    'localvars' => ['b = 1/a'], 'answers' => ['1/a'],
                ],
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
                    ], [
                        ['name' => '_0', 'value' => '1'],
                        ['name' => '_1', 'value' => '2'],
                    ], [
                        ['name' => '_0', 'value' => '1'],
                    ]],
                ]]],
                [
                    'n' => 1, 'randomvars' => '', 'globalvars' => 'a=1; b=2;',
                    'localvars' => ['a=3', '', ''], 'answers' => ['[1,a]', '[a,b]', 'a'],
                ],
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
                    'localvars' => [], 'answers' => [],
                ],
            ],
            [
                ['status' => 'ok', 'data' => [[
                    'randomvars' => [],
                    'globalvars' => [
                        ['name' => 'sin', 'value' => '1'],
                    ],
                    'parts' => [[['name' => '_0', 'value' => '2']]],
                ]]],
                [
                    'n' => 1, 'randomvars' => '', 'globalvars' => 'sin=1',
                    'localvars' => [], 'answers' => ['sin(2)'],
                ],
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
                    'localvars' => [], 'answers' => ['1'],
                ],
            ],
        ];
    }

    /**
     * Test for instantiation::instantiate().
     *
     * @param array $expected expected output ('status' => 'ok'/'error', 'data' => array or 'message' => string)
     * @param array $input expected input ('n' => int, 'randomvars' => string, 'globalvars' => string,
     *      'localvars' => string[], 'answers' => string[])
     * @dataProvider provide_instantiation_data
     */
    public function test_instantiate($expected, $input): void {
        $returnvalue = external\instantiation::instantiate(
            $input['n'], $input['randomvars'], $input['globalvars'], $input['localvars'], $input['answers']
        );
        $returnvalue = \external_api::clean_returnvalue(external\instantiation::instantiate_returns(), $returnvalue);

        self::assertEquals($expected['status'], $returnvalue['status']);
        if ($expected['status'] === 'error') {
            self::assertStringEndsWith($expected['message'], $returnvalue['message']);
        } else {
            self::assertEquals($expected['data'], $returnvalue['data']);
        }
    }
}
