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
 * Unit tests for the OU multiple response question class.
 *
 * @package    qtype_formulas
 * @copyright 2012 Jean-Michel Védrine
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace qtype_formulas;
use qbehaviour_adaptivemultipart_part_result;
use question_attempt_step;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/question/engine/tests/helpers.php');
require_once($CFG->dirroot . '/question/type/formulas/tests/helper.php');

/**
 * Unit tests for (some of) question/type/formulas/question.php.
 *
 * @copyright  2008 The Open University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @group qtype_formulas
 */
class question_test extends \basic_testcase {

    /**
     * @return qtype_formulas_question the requested question object.
     */
    protected function get_test_formulas_question($which = null) {
        return \test_question_maker::make_question('formulas', $which);
    }

    public function test_get_expected_data_test0() {
        $q = $this->get_test_formulas_question('testsinglenum');
        $this->assertEquals(array('0_0' => PARAM_RAW), $q->get_expected_data());
    }

    public function test_get_expected_data_testthreeparts() {
        $q = $this->get_test_formulas_question('testthreeparts');
        $this->assertEquals(array('0_0' => PARAM_RAW,
                                  '1_0' => PARAM_RAW,
                                  '2_0' => PARAM_RAW),
                                  $q->get_expected_data());
    }

    public function test_get_expected_data_test2() {
        $q = $this->get_test_formulas_question('test4');
        $this->assertEquals(array('0_' => PARAM_RAW,
                                  '1_0' => PARAM_RAW,
                                  '1_1' => PARAM_RAW,
                                  '2_0' => PARAM_RAW,
                                  '3_0' => PARAM_RAW),
                                  $q->get_expected_data());
    }

    public function test_is_complete_response_test0() {
        $q = $this->get_test_formulas_question('testsinglenum');

        $this->assertFalse($q->is_complete_response(array()));
        $this->assertTrue($q->is_complete_response(array('0_0' => '0')));
        $this->assertTrue($q->is_complete_response(array('0_0' => 0)));
        $this->assertTrue($q->is_complete_response(array('0_0' => 'test')));
    }

    public function test_is_complete_response_threeparts() {
        $q = $this->get_test_formulas_question('testthreeparts');

        $this->assertFalse($q->is_complete_response(array()));
        $this->assertFalse($q->is_complete_response(array('0_0' => '1')));
        $this->assertFalse($q->is_complete_response(array('0_0' => '1', '1_0' => '1')));
        $this->assertTrue($q->is_complete_response(array('0_0' => '1', '1_0' => '1', '2_0' => '1')));
    }

    public function test_get_question_summary_test0() {
        $q = $this->get_test_formulas_question('testsinglenum');
        $q->start_attempt(new question_attempt_step(), 1);
        $this->assertEquals(
                "This is a minimal question. The answer is 5.\n",
                $q->get_question_summary());
    }

    public function test_get_question_summary_threeparts() {
        $q = $this->get_test_formulas_question('testthreeparts');
        $q->start_attempt(new question_attempt_step(), 1);
        $this->assertEquals("Multiple parts : --This is first part.--This is second part.--This is third part.\n",
                $q->get_question_summary());
    }

    public function test_get_question_summary_test2() {
        $q = $this->get_test_formulas_question('test4');
        $q->start_attempt(new question_attempt_step(), 1);

        $s = $q->evaluator->export_single_variable('s')->value;
        $dt = $q->evaluator->export_single_variable('dt')->value;

        $this->assertEquals("This question shows different display methods of the answer and unit box.\n"
                            . "If a car travels $s m in $dt s, what is the speed of the car? {_0}{_u}\n"
                            . "If a car travels $s m in $dt s, what is the speed of the car? {_0} {_u}\n"
                            . "If a car travels $s m in $dt s, what is the speed of the car? {_0} {_u}\n"
                            . "If a car travels $s m in $dt s, what is the speed of the car? speed = {_0}{_u}\n",
                                    $q->get_question_summary());
    }

    public function test_get_correct_response_test0() {
        $q = $this->get_test_formulas_question('testsinglenum');
        $q->start_attempt(new question_attempt_step(), 1);

        $this->assertEquals(array('0_0' => '5'), $q->get_correct_response());
    }

    public function test_get_correct_response_threeparts() {
        $q = $this->get_test_formulas_question('testthreeparts');
        $q->start_attempt(new question_attempt_step(), 1);

        $this->assertEquals(['0_0' => '5', '1_0' => '6', '2_0' => '7'], $q->get_correct_response());
        $this->assertEquals(['0_0' => '5'], $q->get_correct_response($q->parts[0]));
        $this->assertEquals(['1_0' => '6'], $q->get_correct_response($q->parts[1]));
        $this->assertEquals(['2_0' => '7'], $q->get_correct_response($q->parts[2]));
    }


    public function test_get_correct_response_test2() {
        $q = $this->get_test_formulas_question('test4');
        $q->start_attempt(new question_attempt_step(), 1);

        $v = $q->evaluator->export_single_variable('v')->value;

        $this->assertEquals(
            [
                '0_' => "{$v} m/s",
                '1_0' => $v,
                '1_1' => 'm/s',
                '2_0' => $v,
                '3_0' => $v
            ], $q->get_correct_response());
        $this->assertEquals(['0_' => "{$v} m/s"], $q->get_correct_response($q->parts[0]));
        $this->assertEquals(['1_0' => $v, '1_1' => 'm/s'], $q->get_correct_response($q->parts[1]));
        $this->assertEquals(['2_0' => $v], $q->get_correct_response($q->parts[2]));
        $this->assertEquals(['3_0' => $v], $q->get_correct_response($q->parts[3]));
    }

    public function test_get_correct_response_test3() {
        $q = $this->get_test_formulas_question('testmce');
        $q->start_attempt(new question_attempt_step(), 1);

        $this->assertEquals(['0_0' => 1], $q->get_correct_response());
        $this->assertEquals(['0_0' => 1], $q->get_correct_response($q->parts[0]));
        $this->assertEquals(['0_0' => 'Cat'], $q->parts[0]->get_correct_response(true));
    }

    public function test_get_is_same_response_for_part_test2() {
        $q = $this->get_test_formulas_question('testthreeparts');
        $q->start_attempt(new question_attempt_step(), 1);

        $this->assertTrue($q->is_same_response_for_part('1',
                array('1_0' => 'x'), array('1_0' => 'x')));
        $this->assertTrue($q->is_same_response_for_part('1',
                array('1_0' => 'x', '2_0' => 'x'),
                array('1_0' => 'x', '2_0' => 'y')));
        $this->assertFalse($q->is_same_response_for_part('1', array('1_0' => 'x'), array('1_0' => 'y')));
    }

    public function test_grade_parts_that_can_be_graded_test1() {
        // FIXME: changing this test, because it is flawed. test4 is a question with four parts
        // and different ways of entering a result with unit (combined field, separate field, ignoring unit)
        // and it is randomized; this should probably be 'testmethodsinparts', but the answers should
        // respect the expected format (e.g. 0_ for combined unit field)
        $q = $this->get_test_formulas_question('testmethodsinparts');
        $q->start_attempt(new question_attempt_step(), 1);

        // old: $response = ['0_0' => '5', '1_0' => '6', '2_0' => '8'];
        $response = ['0_' => '40 m/s', '1_0' => '30', '1_1' => 'm/s', '2_0' => '40', '3_0' => '50'];
        $lastgradedresponses = [
            // '0' => ['0_0' => '5', '1_0' => '', '2_0' => ''],
            // '1' => ['0_0' => '6', '1_0' => '6', '2_0' => '']
            '0' => ['0_' => '20 m/s', '1_0' => '0', '1_1' => 'm/s', '2_0' => '0', '3_0' => '40'],
            '1' => ['0_' => '30 m/s', '1_0' => '0', '1_1' => 'm/s', '2_0' => '40', '3_0' => '40'],
        ];
        $partscores = $q->grade_parts_that_can_be_graded($response, $lastgradedresponses, false);

        $expected = [
            // first part: right after wrong last response, penalty of 0.3, because it is a retry
            '0' => new qbehaviour_adaptivemultipart_part_result('0', 1, 0.3),
            // second part: wrong again, no points and a penalty of 0.3
            '1' => new qbehaviour_adaptivemultipart_part_result('1', 0, 0.3),
            // third part: not changed compared to last try, no entry
            // fourth part: was right at the first try, wrong now, so 0 points and a penalty
            '3' => new qbehaviour_adaptivemultipart_part_result('3', 0, 0.3),
        ];
        $this->assertEquals($expected, $partscores);
    }

    public function test_apply_attempt_state(): void {
        // Get a new randomized question and start a new attempt.
        $q = $this->get_test_formulas_question('test4');

        // The question is not initialized yet, so it should not know how many variants
        // it can offer.
        self::assertEquals(PHP_INT_MAX, $q->get_num_variants());

        $seed = 1;
        $q->start_attempt(new question_attempt_step(), $seed);

        // Verify the seed is stored in the question, the evaluator is set up and the variables
        // v, s and t do exist and that they are initialized.
        self::assertEquals($seed, $q->seed);
        self::assertNotNull($q->evaluator);
        $variables = $q->evaluator->export_variable_list();
        self::assertContains('v', $variables);
        self::assertContains('s', $variables);
        self::assertContains('dt', $variables);

        // Verify the number of parts is set and the parts' evaluators are set up.
        self::assertEquals(4, $q->numparts);
        self::assertNotNull($q->parts[0]->evaluator);
        self::assertNotNull($q->parts[1]->evaluator);
        self::assertNotNull($q->parts[2]->evaluator);
        self::assertNotNull($q->parts[3]->evaluator);

        // Store the values of the two random variables.
        $dt = $q->evaluator->export_single_variable('dt')->value;
        $v = $q->evaluator->export_single_variable('v')->value;

        // The question has the random variables "v = {20:100:10}; dt = {2:6};", so there must be
        // 8 * 4 = 32 variants.
        $variants = $q->get_num_variants();
        self::assertEquals(32, $variants);

        // Iterate over all variants until v and dt have changed at least once.
        $vchanged = false;
        $dtchanged = false;
        for ($i = $seed + 1; $i <= $variants; $i++) {
            $q->apply_attempt_state(new question_attempt_step(['_seed' => $i]));
            $vchanged = $vchanged || ($q->evaluator->export_single_variable('v')->value != $v);
            $dtchanged = $dtchanged || ($q->evaluator->export_single_variable('dt')->value != $dt);
            if ($vchanged && $dtchanged) {
                break;
            }
        }

        // Apply attempt step with original seed and verify both variables do have the original value
        // again.
        $q->apply_attempt_state(new question_attempt_step(['_seed' => $seed]));
        self::assertEquals($v, $q->evaluator->export_single_variable('v')->value);
        self::assertEquals($dt, $q->evaluator->export_single_variable('dt')->value);
    }

    public function test_apply_legacy_attempt_state(): void {
        // Get a new randomized question and start a new attempt.
        $q = $this->get_test_formulas_question('test4');
        $seed = 1;
        $q->start_attempt(new question_attempt_step(), $seed);

        // Verify the seed is stored in the question, the evaluator is set up and the variables
        // v, s and t do exist and that they are initialized.
        self::assertEquals($seed, $q->seed);
        self::assertNotNull($q->evaluator);
        $variables = $q->evaluator->export_variable_list();
        self::assertContains('v', $variables);
        self::assertContains('s', $variables);
        self::assertContains('dt', $variables);

        // Verify the number of parts is set and the parts' evaluators are set up.
        self::assertEquals(4, $q->numparts);
        self::assertNotNull($q->parts[0]->evaluator);
        self::assertNotNull($q->parts[1]->evaluator);
        self::assertNotNull($q->parts[2]->evaluator);
        self::assertNotNull($q->parts[3]->evaluator);

        // Store the values of the two random variables.
        $dt = $q->evaluator->export_single_variable('dt')->value;
        $v = $q->evaluator->export_single_variable('v')->value;

        // The question has the random variables "v = {20:100:10}; dt = {2:6};", so there must be
        // 8 * 4 = 32 variants.
        $variants = $q->get_num_variants();
        self::assertEquals(32, $variants);

        // Iterate over all variants until v and dt have changed at least once.
        $vchanged = false;
        $dtchanged = false;
        for ($i = $seed + 1; $i <= $variants; $i++) {
            $q->apply_attempt_state(new question_attempt_step(['_seed' => $i]));
            $vchanged = $vchanged || ($q->evaluator->export_single_variable('v')->value != $v);
            $dtchanged = $dtchanged || ($q->evaluator->export_single_variable('dt')->value != $dt);
            if ($vchanged && $dtchanged) {
                break;
            }
        }

        // Apply attempt step with original seed and verify both variables do have the original value
        // again.
        $q->apply_attempt_state(new question_attempt_step(['_varsglobal' => "v=$v;dt=$dt;"]));
        self::assertEquals($v, $q->evaluator->export_single_variable('v')->value);
        self::assertEquals($dt, $q->evaluator->export_single_variable('dt')->value);
    }

    public function test_grade_parts_that_can_be_graded_test2() {
        $q = $this->get_test_formulas_question('testthreeparts');
        $q->start_attempt(new question_attempt_step(), 1);

        $response = ['0_0' => '5', '1_0' => '6', '2_0' => '7'];
        $lastgradedresponses = [
            '0' => ['0_0' => '5', '1_0' => '', '2_0' => ''],
            '1' => ['0_0' => '6', '1_0' => '6', '2_0' => '']
        ];
        $partscores = $q->grade_parts_that_can_be_graded($response, $lastgradedresponses, false);

        $expected = [
            // FIXME: changed expected value, because new response differs from last registered
            // response for parts 0 and 2, so both should be graded; this is a change compared to
            // the old implementation; checking that with the authors of qbehaviour_adaptivemultipart
            '0' => new qbehaviour_adaptivemultipart_part_result('0', 1, 0.3),
            '2' => new qbehaviour_adaptivemultipart_part_result('2', 1, 0.3),
        ];
        $this->assertEquals($expected, $partscores);
    }

    public function test_grade_parts_that_can_be_graded_test3() {
        $q = $this->get_test_formulas_question('testthreeparts');
        $q->start_attempt(new question_attempt_step(), 1);

        $response = ['0_0' => '5', '1_0' => '6', '2_0' => '7'];
        $lastgradedresponses = [
            '0' => ['0_0' => '5', '1_0' => '4', '2_0' => ''],
            '1' => ['0_0' => '6', '1_0' => '6', '2_0' => ''],
            '2' => ['0_0' => '6', '1_0' => '6', '2_0' => '7']
        ];
        $partscores = $q->grade_parts_that_can_be_graded($response, $lastgradedresponses, false);

        // FIXME: changed expected value, because new response differs from last registered
        // response for part 0, so it should be graded; this is a change compared to
        // the old implementation; checking that with the authors of qbehaviour_adaptivemultipart
        $expected = [
            '0' => new qbehaviour_adaptivemultipart_part_result('0', 1, 0.3)
        ];
        $this->assertEquals($expected, $partscores);
    }

    public function test_with_invalidated_grading_vars() {
        $q = $this->get_test_formulas_question('testtwonums');

        // Set the grading vars to _0/_1 which will be invalid if the student
        // enters 0 as their second answer.
        $q->parts[0]->vars2 = 'test = _0/_1';
        $q->parts[0]->correctness = 'test';
        $q->parts[0]->numbox = 2;
        $q->start_attempt(new question_attempt_step(), 1);

        // The invalid grading criterion should not lead to an exception, but get
        // 0 marks.
        $response = ['0_0' => 1, '0_1' => 0];
        $partscores = $q->grade_parts_that_can_be_graded($response, [], false);
        $this->assertEquals(0, $partscores[0]->rawfraction);

        // This time the grading criterion can be evaluated.
        $response = ['0_0' => 1, '0_1' => 2];
        $partscores = $q->grade_parts_that_can_be_graded($response, [], false);
        $this->assertEquals(0.5, $partscores[0]->rawfraction);
    }

    public function test_with_invalidated_grading_criterion() {
        $q = $this->get_test_formulas_question('testtwonums');

        // Set the grading criterion to _0/_1 which will be invalid if the student
        // enters 0 as their second answer.
        $q->parts[0]->correctness = '_0/_1';
        $q->parts[0]->numbox = 2;
        $q->start_attempt(new question_attempt_step(), 1);

        // The invalid grading criterion should not lead to an exception, but get
        // 0 marks.
        $response = ['0_0' => 1, '0_1' => 0];
        $partscores = $q->grade_parts_that_can_be_graded($response, [], false);
        $this->assertEquals(0, $partscores[0]->rawfraction);

        // This time the grading criterion can be evaluated.
        $response = ['0_0' => 1, '0_1' => 2];
        $partscores = $q->grade_parts_that_can_be_graded($response, [], false);
        $this->assertEquals(0.5, $partscores[0]->rawfraction);
    }

    public function test_grade_parts_that_can_be_graded_test4() {
        $q = $this->get_test_formulas_question('testthreeparts');
        $q->start_attempt(new question_attempt_step(), 1);

        $response = array('0_0' => '5', '1_0' => '6', '2_0' => '7');
        $lastgradedresponses = array(
            '0'     => array('0_0' => '5', '1_0' => '', '2_0' => ''),
        );
        $partscores = $q->grade_parts_that_can_be_graded($response, $lastgradedresponses, false);

        $expected = array(
            '1' => new qbehaviour_adaptivemultipart_part_result('1', 1, 0.3),
            '2' => new qbehaviour_adaptivemultipart_part_result('2', 1, 0.3),
        );
        $this->assertEquals($expected, $partscores);
    }

    public function test_grade_parts_that_can_be_graded_test5() {
        $q = $this->get_test_formulas_question('testthreeparts');
        $q->start_attempt(new question_attempt_step(), 1);

        $response = array('0_0' => '5', '1_0' => '', '2_0' => '');
        $lastgradedresponses = array(
        );
        $partscores = $q->grade_parts_that_can_be_graded($response, $lastgradedresponses, false);

        $expected = array(
            '0' => new qbehaviour_adaptivemultipart_part_result('0', 1, 0.3),
        );
        $this->assertEquals($expected, $partscores);
    }

    public function test_get_parts_and_weights_test0() {
        $q = $this->get_test_formulas_question('testsinglenum');

        $this->assertEquals(array('0' => 1), $q->get_parts_and_weights());
    }

    public function test_get_parts_and_weights_threeparts() {
        $q = $this->get_test_formulas_question('testthreeparts');

        $this->assertEquals(array('0' => 1 / 3, '1' => 1 / 3, '2' => 1 / 3), $q->get_parts_and_weights());
    }

    public function test_get_parts_and_weights_test2() {
        $q = $this->get_test_formulas_question('test4');

        $this->assertEquals(array('0' => .25, '1' => .25, '2' => .25, '3' => .25), $q->get_parts_and_weights());
    }

    public function test_compute_final_grade_threeparts_1() {
        $q = $this->get_test_formulas_question('testthreeparts');
        $q->start_attempt(new question_attempt_step(), 1);

        $responses = array(0 => array('0_0' => '5', '1_0' => '7', '2_0' => '6'),
                           1 => array('0_0' => '5', '1_0' => '7', '2_0' => '7'),
                           2 => array('0_0' => '5', '1_0' => '6', '2_0' => '7')
                          );
        $finalgrade = $q->compute_final_grade($responses, 1);
        $this->assertEquals((2 + 2 * (1 - 2 * 0.3) + 2 * (1 - 0.3)) / 6, $finalgrade);

    }

    public function test_compute_final_grade_threeparts_2() {
        $q = $this->get_test_formulas_question('testthreeparts');
        $q->start_attempt(new question_attempt_step(), 1);

        $responses = array(0 => array('0_0' => '5', '1_0' => '7', '2_0' => '6'),
                           1 => array('0_0' => '5', '1_0' => '8', '2_0' => '6'),
                           2 => array('0_0' => '5', '1_0' => '6', '2_0' => '6')
                          );
        $finalgrade = $q->compute_final_grade($responses, 1);
        $this->assertEquals((2 + 2 * (1 - 2 * 0.3)) / 6, $finalgrade);
    }

    public function test_summarise_response_threeparts() {
        $q = $this->get_test_formulas_question('testthreeparts');
        $q->start_attempt(new question_attempt_step(), 1);

        $response = array('0_0' => '5', '1_0' => '6', '2_0' => '7');
        $summary0 = $q->parts[0]->summarise_response($response);
        $this->assertEquals('5', $summary0);
        $summary1 = $q->parts[1]->summarise_response($response);
        $this->assertEquals('6', $summary1);
        $summary2 = $q->parts[2]->summarise_response($response);
        $this->assertEquals('7', $summary2);
        $summary = $q->summarise_response($response);
        $this->assertEquals('5, 6, 7', $summary);
    }

    public function test_summarise_response_test1() {
        $q = $this->get_test_formulas_question('test4');
        $q->start_attempt(new question_attempt_step(), 1);

        $response = array('0_' => "30m/s", '1_0' => "20", '1_1' => 'm/s', '2_0' => "40", '3_0' => "50");
        $summary0 = $q->parts[0]->summarise_response($response);
        $this->assertEquals('30m/s', $summary0);
        $summary1 = $q->parts[1]->summarise_response($response);
        $this->assertEquals('20, m/s', $summary1);
        $summary2 = $q->parts[2]->summarise_response($response);
        $this->assertEquals('40', $summary2);
        $summary3 = $q->parts[3]->summarise_response($response);
        $this->assertEquals('50', $summary3);
        $summary = $q->summarise_response($response);
        $this->assertEquals('30m/s, 20, m/s, 40, 50', $summary);
    }

    public function test_is_complete_response_test3() {
        $q = $this->get_test_formulas_question('testzero');

        $this->assertFalse($q->is_complete_response(array()));
        $this->assertTrue($q->is_complete_response(array('0_0' => '0')));
        $this->assertTrue($q->is_complete_response(array('0_0' => 0)));
        $this->assertTrue($q->is_complete_response(array('0_0' => 'test')));
    }

    public function test_is_gradable_response_test3() {
        $q = $this->get_test_formulas_question('testzero');

        $this->assertFalse($q->is_gradable_response(array()));
        $this->assertTrue($q->is_gradable_response(array('0_0' => '0')));
        $this->assertTrue($q->is_gradable_response(array('0_0' => 0)));
        $this->assertTrue($q->is_gradable_response(array('0_0' => '0.0')));
        $this->assertTrue($q->is_gradable_response(array('0_0' => '5')));
        $this->assertTrue($q->is_gradable_response(array('0_0' => 5)));
    }
}
