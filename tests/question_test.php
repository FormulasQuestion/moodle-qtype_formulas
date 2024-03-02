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

// FIXME: use get_possible_responses in test_classify


/**
 * Unit tests for the OU multiple response question class.
 *
 * @package    qtype_formulas
 * @copyright 2012 Jean-Michel Védrine
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace qtype_formulas;

use Exception;
use qbehaviour_adaptivemultipart_part_result;
use qtype_formulas_part;
use qtype_formulas_question;
use question_attempt;
use question_attempt_step;
use question_display_options;
use question_hint_with_parts;
use question_possible_response;
use question_usage_by_activity;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/question/engine/tests/helpers.php');
require_once($CFG->dirroot . '/question/type/formulas/tests/helper.php');
require_once($CFG->dirroot . '/question/type/formulas/question.php');

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

    public function test_check_file_access_general() {
        // Prepare a question.
        $question = $this->get_test_formulas_question('testsinglenum');
        $question->id = 42;
        $question->parts[0]->id = 1;

        // Prepare and start a question attempt.
        $quba = new question_usage_by_activity('qtype_formulas', \context_system::instance());
        $qa = new question_attempt($question, $quba->get_id());
        $qa->start('immediatefeedback', 1);

        // Prepare default display options.
        $options = new question_display_options();

        // Step 1: access to files in the part's text should always be granted, as long as
        // the $itemid ($args[0]) matches the part's ID.
        $component = 'qtype_formulas';
        $area = 'answersubqtext';
        $args = [$question->parts[0]->id, 'foo.jpg'];
        self::assertTrue($question->check_file_access($qa, $options, $component, $area, $args, false));
        $args = [$question->parts[0]->id + 1, 'foo.jpg'];
        self::assertFalse($question->check_file_access($qa, $options, $component, $area, $args, false));
        // If no ID is given, we should not have access.
        self::assertFalse($question->check_file_access($qa, $options, $component, $area, [], false));


        // Step 2: access to files in the question text should always be granted, as long as
        // the $itemid ($args[0]) matches the question's ID.
        $component = 'question';
        $area = 'questiontext';
        $args = [$question->id, 'foo.jpg'];
        self::assertTrue($question->check_file_access($qa, $options, $component, $area, $args, false));
        $args = [$question->id + 1, 'foo.jpg'];
        self::assertFalse($question->check_file_access($qa, $options, $component, $area, $args, false));

        // Step 3: access to the general feedback fields (part or question) should not be granted if
        // the question is not finished.
        $component = 'qtype_formulas';
        $area = 'answerfeedback';
        $args = [$question->parts[0]->id, 'foo.jpg'];
        self::assertFalse($question->check_file_access($qa, $options, $component, $area, $args, false));

        // Step 4: sending a wrong answer and thus finishing the question. Files from the area belonging
        // to the general feedback ('answerfeedback') should be served, unless generalfeedback is hidden
        // in the display options. However, hiding combined feedback should not change the outcome.
        $qa->process_action(['0_0' => '4', '-submit' => 1]);
        $args = [$question->parts[0]->id + 1, 'foo.jpg'];
        self::assertFalse($question->check_file_access($qa, $options, $component, $area, $args, false));
        $args = [$question->parts[0]->id, 'foo.jpg'];
        self::assertTrue($question->check_file_access($qa, $options, $component, $area, $args, false));
        $options->feedback = $options::HIDDEN;
        self::assertTrue($question->check_file_access($qa, $options, $component, $area, $args, false));
        $options->generalfeedback = $options::HIDDEN;
        self::assertFalse($question->check_file_access($qa, $options, $component, $area, $args, false));

        // Step 5: restarting the question and sending the right answer. The general feedback should still
        // be served.
        $qa = new question_attempt($question, $quba->get_id());
        $qa->start('immediatefeedback', 1);
        $qa->process_action(['0_0' => '5', '-submit' => 1]);
        $options->generalfeedback = $options::VISIBLE;
        $args = [$question->parts[0]->id, 'foo.jpg'];
        self::assertTrue($question->check_file_access($qa, $options, $component, $area, $args, false));
    }

    public function test_check_file_access_hints() {
        // Prepare a question.
        $question = $this->get_test_formulas_question('testsinglenum');
        $question->id = 42;
        $question->parts[0]->id = 1;

        // Add two hints.
        $question->hints[] = new question_hint_with_parts(12, 'foo', FORMAT_HTML, false, false);
        $question->hints[] = new question_hint_with_parts(13, 'bar', FORMAT_HTML, false, false);

        // Prepare and start a question attempt.
        $quba = new question_usage_by_activity('qtype_formulas', \context_system::instance());
        $qa = new question_attempt($question, $quba->get_id());
        $qa->start('interactive', 1);

        // Prepare default display options.
        $options = new question_display_options();

        // Step 1: no answer has been submitted, so we should not have access to files from the
        // 'hint' area.
        $component = 'question';
        $area = 'hint';
        $args = [$question->hints[0]->id, 'foo.jpg'];
        if (PHP_MAJOR_VERSION >= 8) {
            $message = 'Attempt to read property "id" on null';
        } else {
            $message = "Trying to get property 'id' of non-object";
        }
        try {
            self::assertFalse($question->check_file_access($qa, $options, $component, $area, $args, false));
        } catch (\Exception $e) {
            self::assertStringContainsString($message, $e->getMessage());
        }
        $args = [$question->hints[1]->id, 'foo.jpg'];
        try {
            self::assertFalse($question->check_file_access($qa, $options, $component, $area, $args, false));
        } catch (\Exception $e) {
            self::assertStringContainsString($message, $e->getMessage());
        }

        // Step 2: send a wrong answer. We should now have access to the first hint, but not the second.
        $qa->process_action(['0_0' => '4', '-submit' => 1]);
        $args = [$question->hints[0]->id, 'foo.jpg'];
        self::assertTrue($question->check_file_access($qa, $options, $component, $area, $args, false));
        $args = [$question->hints[1]->id, 'foo.jpg'];
        self::assertFalse($question->check_file_access($qa, $options, $component, $area, $args, false));

        // Step 3: send another wrong answer. We should now have access to the second hint, but not the first.
        $qa->process_action(['0_0' => '4', '-tryagain' => 1]);
        $qa->process_action(['0_0' => '3', '-submit' => 1]);
        $args = [$question->hints[0]->id, 'foo.jpg'];
        self::assertFalse($question->check_file_access($qa, $options, $component, $area, $args, false));
        $args = [$question->hints[1]->id, 'foo.jpg'];
        self::assertTrue($question->check_file_access($qa, $options, $component, $area, $args, false));
    }

    public function test_check_file_access_question_combinedfeedback() {
        // Prepare a question.
        $question = $this->get_test_formulas_question('testsinglenum');
        $question->id = 42;
        $question->parts[0]->id = 1;

        // Prepare default display options.
        $options = new question_display_options();

        // Prepare and start a question attempt.
        $quba = new question_usage_by_activity('qtype_formulas', \context_system::instance());
        $qa = new question_attempt($question, $quba->get_id());
        $qa->start('immediatefeedback', 1);

        // Step 1: access to combined feedback fields should not be granted, because question is
        // not finished.
        $component = 'question';
        $args = [$question->id, 'foo.jpg'];
        $area = 'correctfeedback';
        self::assertFalse($question->check_file_access($qa, $options, $component, $area, $args, false));
        $area = 'partiallycorrectfeedback';
        self::assertFalse($question->check_file_access($qa, $options, $component, $area, $args, false));
        $area = 'incorrectfeedback';
        self::assertFalse($question->check_file_access($qa, $options, $component, $area, $args, false));

        // Step 2: sending a wrong answer. Access should be granted to the file area that belongs to
        // the incorrect feedback, but only for this question.
        $qa->process_action(['0_0' => '4', '-submit' => 1]);
        $area = 'correctfeedback';
        self::assertFalse($question->check_file_access($qa, $options, $component, $area, $args, false));
        $area = 'partiallycorrectfeedback';
        self::assertFalse($question->check_file_access($qa, $options, $component, $area, $args, false));
        $area = 'incorrectfeedback';
        self::assertTrue($question->check_file_access($qa, $options, $component, $area, $args, false));
        $args = [$question->id + 1, 'foo.jpg'];
        self::assertFalse($question->check_file_access($qa, $options, $component, $area, $args, false));

        // Step 3: restarting and sending the correct answer. Access should be granted to the file area
        // that belongs to the correct feedback, but only for this part.
        $qa = new question_attempt($question, $quba->get_id());
        $qa->start('immediatefeedback', 1);
        $qa->process_action(['0_0' => '5', '-submit' => 1]);
        $args = [$question->id, 'foo.jpg'];
        $area = 'partiallycorrectfeedback';
        self::assertFalse($question->check_file_access($qa, $options, $component, $area, $args, false));
        $area = 'incorrectfeedback';
        self::assertFalse($question->check_file_access($qa, $options, $component, $area, $args, false));
        $area = 'correctfeedback';
        self::assertTrue($question->check_file_access($qa, $options, $component, $area, $args, false));
        $args = [$question->id + 1, 'foo.jpg'];
        self::assertFalse($question->check_file_access($qa, $options, $component, $area, $args, false));

        // Step 4: access to the previously good area should no longer be granted, if we set feedback
        // to invisible in the display options. However, hiding general feedback only should not change
        // the access.
        $args = [$question->id, 'foo.jpg'];
        $options->generalfeedback = $options::HIDDEN;
        self::assertTrue($question->check_file_access($qa, $options, $component, $area, $args, false));
        $options->feedback = $options::HIDDEN;
        self::assertFalse($question->check_file_access($qa, $options, $component, $area, $args, false));
    }

    public function test_check_file_access_partcombinedfeedback() {
        // Prepare a question.
        $question = $this->get_test_formulas_question('testsinglenum');
        $question->id = 42;
        $question->parts[0]->id = 1;

        // Prepare default display options.
        $options = new question_display_options();

        // Prepare and start a question attempt.
        $quba = new question_usage_by_activity('qtype_formulas', \context_system::instance());
        $qa = new question_attempt($question, $quba->get_id());
        $qa->start('immediatefeedback', 1);

        // Step 1: access to combined feedback fields should not be granted, because question is
        // not finished.
        $component = 'qtype_formulas';
        $args = [$question->parts[0]->id, 'foo.jpg'];
        $area = 'partcorrectfb';
        self::assertFalse($question->check_file_access($qa, $options, $component, $area, $args, false));
        $area = 'partpartiallycorrectfb';
        self::assertFalse($question->check_file_access($qa, $options, $component, $area, $args, false));
        $area = 'partincorrectfb';
        self::assertFalse($question->check_file_access($qa, $options, $component, $area, $args, false));

        // Step 2: sending a wrong answer. Access should be granted to the file area that belongs to
        // the incorrect feedback, but only for this part.
        $qa->process_action(['0_0' => '4', '-submit' => 1]);
        $area = 'partcorrectfb';
        self::assertFalse($question->check_file_access($qa, $options, $component, $area, $args, false));
        $area = 'partpartiallycorrectfb';
        self::assertFalse($question->check_file_access($qa, $options, $component, $area, $args, false));
        $area = 'partincorrectfb';
        self::assertTrue($question->check_file_access($qa, $options, $component, $area, $args, false));
        $args = [$question->parts[0]->id + 1, 'foo.jpg'];
        self::assertFalse($question->check_file_access($qa, $options, $component, $area, $args, false));

        // Step 3: restarting and sending the correct answer. Access should be granted to the file area
        // that belongs to the correct feedback, but only for this part.
        $qa = new question_attempt($question, $quba->get_id());
        $qa->start('immediatefeedback', 1);
        $qa->process_action(['0_0' => '5', '-submit' => 1]);
        $args = [$question->parts[0]->id, 'foo.jpg'];
        $area = 'partpartiallycorrectfb';
        self::assertFalse($question->check_file_access($qa, $options, $component, $area, $args, false));
        $area = 'partincorrectfb';
        self::assertFalse($question->check_file_access($qa, $options, $component, $area, $args, false));
        $area = 'partcorrectfb';
        self::assertTrue($question->check_file_access($qa, $options, $component, $area, $args, false));
        $args = [$question->parts[0]->id + 1, 'foo.jpg'];
        self::assertFalse($question->check_file_access($qa, $options, $component, $area, $args, false));

        // Step 4: access to the previously good area should no longer be granted, if we set feedback
        // to invisible in the display options. However, hiding general feedback only should not change
        // the access.
        $args = [$question->parts[0]->id, 'foo.jpg'];
        $options->generalfeedback = $options::HIDDEN;
        self::assertTrue($question->check_file_access($qa, $options, $component, $area, $args, false));
        $options->feedback = $options::HIDDEN;
        self::assertFalse($question->check_file_access($qa, $options, $component, $area, $args, false));
    }

    public function test_check_file_access_partcombinedfeedback_adaptive() {
        // Prepare a question.
        $question = $this->get_test_formulas_question('testsinglenum');
        $question->id = 42;
        $question->parts[0]->id = 1;

        // Prepare default display options.
        $options = new question_display_options();

        // Prepare and start a question attempt.
        $quba = new question_usage_by_activity('qtype_formulas', \context_system::instance());
        $qa = new question_attempt($question, $quba->get_id());
        $qa->start('adaptive', 1);
        self::assertEquals('adaptivemultipart', $qa->get_behaviour_name());

        // Step 1: access to combined feedback fields should not be granted, because question is
        // not finished and not gradable.
        $component = 'qtype_formulas';
        $args = [$question->parts[0]->id, 'foo.jpg'];
        $area = 'partcorrectfb';
        self::assertFalse($question->check_file_access($qa, $options, $component, $area, $args, false));
        $area = 'partpartiallycorrectfb';
        self::assertFalse($question->check_file_access($qa, $options, $component, $area, $args, false));
        $area = 'partincorrectfb';
        self::assertFalse($question->check_file_access($qa, $options, $component, $area, $args, false));

        // Step 2: sending a wrong answer. Access should be granted to the file area that belongs to
        // the incorrect feedback, but only for this part.
        $qa->process_action(['0_0' => '4', '-submit' => 1]);
        $area = 'partcorrectfb';
        self::assertFalse($question->check_file_access($qa, $options, $component, $area, $args, false));
        $area = 'partpartiallycorrectfb';
        self::assertFalse($question->check_file_access($qa, $options, $component, $area, $args, false));
        $area = 'partincorrectfb';
        self::assertTrue($question->check_file_access($qa, $options, $component, $area, $args, false));
        $args = [$question->parts[0]->id + 1, 'foo.jpg'];
        self::assertFalse($question->check_file_access($qa, $options, $component, $area, $args, false));

        // Step 3: sending the correct answer. Access should be granted to the file area
        // that belongs to the correct feedback, but only for this part.
        $qa->process_action(['0_0' => '5', '-submit' => 1]);
        $args = [$question->parts[0]->id, 'foo.jpg'];
        $area = 'partpartiallycorrectfb';
        self::assertFalse($question->check_file_access($qa, $options, $component, $area, $args, false));
        $area = 'partincorrectfb';
        self::assertFalse($question->check_file_access($qa, $options, $component, $area, $args, false));
        $area = 'partcorrectfb';
        self::assertTrue($question->check_file_access($qa, $options, $component, $area, $args, false));
        $args = [$question->parts[0]->id + 1, 'foo.jpg'];
        self::assertFalse($question->check_file_access($qa, $options, $component, $area, $args, false));

        // Step 4: access to the previously good area should no longer be granted, if we set feedback
        // to invisible in the display options. However, hiding general feedback only should not change
        // the access.
        $args = [$question->parts[0]->id, 'foo.jpg'];
        $options->generalfeedback = $options::HIDDEN;
        self::assertTrue($question->check_file_access($qa, $options, $component, $area, $args, false));
        $options->feedback = $options::HIDDEN;
        self::assertFalse($question->check_file_access($qa, $options, $component, $area, $args, false));
    }

    public function test_get_expected_data_test0() {
        $q = $this->get_test_formulas_question('testsinglenum');
        self::assertEquals(array('0_0' => PARAM_RAW), $q->get_expected_data());
    }

    public function test_get_expected_data_testthreeparts() {
        $q = $this->get_test_formulas_question('testthreeparts');
        self::assertEquals(array('0_0' => PARAM_RAW,
                                  '1_0' => PARAM_RAW,
                                  '2_0' => PARAM_RAW),
                                  $q->get_expected_data());
    }

    public function test_get_expected_data_test2() {
        $q = $this->get_test_formulas_question('test4');
        self::assertEquals(array('0_' => PARAM_RAW,
                                  '1_0' => PARAM_RAW,
                                  '1_1' => PARAM_RAW,
                                  '2_0' => PARAM_RAW,
                                  '3_0' => PARAM_RAW),
                                  $q->get_expected_data());
    }

    public function test_is_complete_response_test0() {
        $q = $this->get_test_formulas_question('testsinglenum');

        self::assertFalse($q->is_complete_response(array()));
        self::assertTrue($q->is_complete_response(array('0_0' => '0')));
        self::assertTrue($q->is_complete_response(array('0_0' => 0)));
        self::assertTrue($q->is_complete_response(array('0_0' => 'test')));
    }

    public function test_is_complete_response_threeparts() {
        $q = $this->get_test_formulas_question('testthreeparts');

        self::assertFalse($q->is_complete_response(array()));
        self::assertFalse($q->is_complete_response(array('0_0' => '1')));
        self::assertFalse($q->is_complete_response(array('0_0' => '1', '1_0' => '1')));
        self::assertTrue($q->is_complete_response(array('0_0' => '1', '1_0' => '1', '2_0' => '1')));
    }

    public function test_get_question_summary_test0() {
        $q = $this->get_test_formulas_question('testsinglenum');
        $q->start_attempt(new question_attempt_step(), 1);
        self::assertEquals(
                "This is a minimal question. The answer is 5.\n",
                $q->get_question_summary());
    }

    public function test_get_question_summary_threeparts() {
        $q = $this->get_test_formulas_question('testthreeparts');
        $q->start_attempt(new question_attempt_step(), 1);
        self::assertEquals("Multiple parts : --This is first part.--This is second part.--This is third part.\n",
                $q->get_question_summary());
    }

    public function test_get_question_summary_test2() {
        $q = $this->get_test_formulas_question('test4');
        $q->start_attempt(new question_attempt_step(), 1);

        $s = $q->evaluator->export_single_variable('s')->value;
        $dt = $q->evaluator->export_single_variable('dt')->value;

        self::assertEquals("This question shows different display methods of the answer and unit box.\n"
                            . "If a car travels $s m in $dt s, what is the speed of the car? {_0}{_u}\n"
                            . "If a car travels $s m in $dt s, what is the speed of the car? {_0} {_u}\n"
                            . "If a car travels $s m in $dt s, what is the speed of the car? {_0} {_u}\n"
                            . "If a car travels $s m in $dt s, what is the speed of the car? speed = {_0}{_u}\n",
                                    $q->get_question_summary());
    }

    public function test_get_correct_response_test0() {
        $q = $this->get_test_formulas_question('testsinglenum');
        $q->start_attempt(new question_attempt_step(), 1);

        self::assertEquals(array('0_0' => '5'), $q->get_correct_response());
    }

    public function test_get_correct_response_threeparts() {
        $q = $this->get_test_formulas_question('testthreeparts');
        $q->start_attempt(new question_attempt_step(), 1);

        self::assertEquals(['0_0' => '5', '1_0' => '6', '2_0' => '7'], $q->get_correct_response());
        self::assertEquals(['0_0' => '5'], $q->get_correct_response($q->parts[0]));
        self::assertEquals(['1_0' => '6'], $q->get_correct_response($q->parts[1]));
        self::assertEquals(['2_0' => '7'], $q->get_correct_response($q->parts[2]));
    }


    public function test_get_correct_response_test2() {
        $q = $this->get_test_formulas_question('test4');
        $q->start_attempt(new question_attempt_step(), 1);

        $v = $q->evaluator->export_single_variable('v')->value;

        self::assertEquals(
            [
                '0_' => "{$v} m/s",
                '1_0' => $v,
                '1_1' => 'm/s',
                '2_0' => $v,
                '3_0' => $v
            ], $q->get_correct_response());
        self::assertEquals(['0_' => "{$v} m/s"], $q->get_correct_response($q->parts[0]));
        self::assertEquals(['1_0' => $v, '1_1' => 'm/s'], $q->get_correct_response($q->parts[1]));
        self::assertEquals(['2_0' => $v], $q->get_correct_response($q->parts[2]));
        self::assertEquals(['3_0' => $v], $q->get_correct_response($q->parts[3]));
    }

    public function test_get_correct_response_test3() {
        $q = $this->get_test_formulas_question('testmce');
        $q->start_attempt(new question_attempt_step(), 1);

        self::assertEquals(['0_0' => 1], $q->get_correct_response());
        self::assertEquals(['0_0' => 1], $q->get_correct_response($q->parts[0]));
        self::assertEquals(['0_0' => 'Cat'], $q->parts[0]->get_correct_response(true));
    }

    public function test_get_is_same_response_for_part_test2() {
        $q = $this->get_test_formulas_question('testthreeparts');
        $q->start_attempt(new question_attempt_step(), 1);

        self::assertTrue($q->is_same_response_for_part('1',
                array('1_0' => 'x'), array('1_0' => 'x')));
        self::assertTrue($q->is_same_response_for_part('1',
                array('1_0' => 'x', '2_0' => 'x'),
                array('1_0' => 'x', '2_0' => 'y')));
        self::assertFalse($q->is_same_response_for_part('1', array('1_0' => 'x'), array('1_0' => 'y')));
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
        self::assertEquals(0, $partscores[0]->rawfraction);

        // This time the grading criterion can be evaluated.
        $response = ['0_0' => 1, '0_1' => 2];
        $partscores = $q->grade_parts_that_can_be_graded($response, [], false);
        self::assertEquals(0.5, $partscores[0]->rawfraction);
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
        self::assertEquals(0, $partscores[0]->rawfraction);

        // This time the grading criterion can be evaluated.
        $response = ['0_0' => 1, '0_1' => 2];
        $partscores = $q->grade_parts_that_can_be_graded($response, [], false);
        self::assertEquals(0.5, $partscores[0]->rawfraction);
    }

    public function test_grade_parts_that_can_be_graded_test1() {
        // Question with three parts, answers being 5, 6 and 7.
        $q = $this->get_test_formulas_question('testthreeparts');
        $q->start_attempt(new question_attempt_step(), 1);

        $response = ['0_0' => '5', '1_0' => '6', '2_0' => '8'];
        // The $lastgradedresponses array contains one entry for every part that has registered at
        // least one try; if a part has never been ansered, there will be no entry for it. If there
        // have been multiple tries, only the last graded try is kept. In particular, this array is
        // not the *history* of all tries.
        // With the following values, this means that the first part (part #0) has been tried at least
        // once and on its last try, there has been no answer to the two other parts. The last time
        // the second part (part #1) has been answered, the response was wrong for part #0, right
        // for part #1 and empty for the last part (part #2). The last part (part #2) has never been
        // attempted.
        $lastgradedresponses = [
            '0' => ['0_0' => '5', '1_0' => '', '2_0' => ''],
            '1' => ['0_0' => '6', '1_0' => '6', '2_0' => ''],
        ];
        $partscores = $q->grade_parts_that_can_be_graded($response, $lastgradedresponses, false);

        // The current $response is '5' for the first part, which is the same as in the last try for
        // that part. It is '6' for the second part, which is also unchanged. We have the answer '8'
        // for the last part and as there has not been an answer for that part so far, this is a new
        // answer. We should thus have a grading for part #2 only.
        $expected = [
            '2' => new qbehaviour_adaptivemultipart_part_result('2', 0, 0.3),
        ];
        $this->assertEquals($expected, $partscores);
    }

    public function test_grade_parts_that_can_be_graded_test2() {
        $q = $this->get_test_formulas_question('testthreeparts');
        $q->start_attempt(new question_attempt_step(), 1);

        $response = ['0_0' => '5', '1_0' => '6', '2_0' => '7'];
        $lastgradedresponses = [
            '0' => ['0_0' => '5', '1_0' => '', '2_0' => ''],
            '1' => ['0_0' => '6', '1_0' => '6', '2_0' => ''],
        ];
        $partscores = $q->grade_parts_that_can_be_graded($response, $lastgradedresponses, false);

        // The current $response is correct for all three parts. However, at the last registered attempt,
        // parts #0 and #1 were already correct, so this does not count as a new attempt. We should get
        // a grading result for part #2 only.
        $expected = [
            '2' => new qbehaviour_adaptivemultipart_part_result('2', 1, 0.3),
        ];
        self::assertEquals($expected, $partscores);
    }

    public function test_grade_parts_that_can_be_graded_test3() {
        $q = $this->get_test_formulas_question('testthreeparts');
        $q->start_attempt(new question_attempt_step(), 1);

        $response = ['0_0' => '5', '1_0' => '6', '2_0' => '7'];
        $lastgradedresponses = [
            '0' => ['0_0' => '5', '1_0' => '4', '2_0' => ''],
            '1' => ['0_0' => '6', '1_0' => '6', '2_0' => ''],
            '2' => ['0_0' => '6', '1_0' => '6', '2_0' => '7'],
        ];
        $partscores = $q->grade_parts_that_can_be_graded($response, $lastgradedresponses, false);

        // The current $response is correct for all three parts. However, every part has already been
        // correctly answered at its last registered attempt, so we should get no grading at all.
        $expected = [];
        self::assertEquals($expected, $partscores);
    }

    public function test_grade_parts_that_can_be_graded_test4() {
        $q = $this->get_test_formulas_question('testthreeparts');
        $q->start_attempt(new question_attempt_step(), 1);

        $response = ['0_0' => '5', '1_0' => '6', '2_0' => '7'];
        $lastgradedresponses = [
            '0' => ['0_0' => '5', '1_0' => '', '2_0' => ''],
        ];
        $partscores = $q->grade_parts_that_can_be_graded($response, $lastgradedresponses, false);

        // Parts #1 and #2 have never been attempted. The last registered attempt for part #0 was correct.
        // The current $response is correct for all parts. So we expect a full-mark grading result for
        // parts #1 and #2 only.
        $expected = [
            '1' => new qbehaviour_adaptivemultipart_part_result('1', 1, 0.3),
            '2' => new qbehaviour_adaptivemultipart_part_result('2', 1, 0.3),
        ];
        self::assertEquals($expected, $partscores);
    }

    public function test_grade_parts_that_can_be_graded_test5() {
        $q = $this->get_test_formulas_question('testthreeparts');
        $q->start_attempt(new question_attempt_step(), 1);

        $response = ['0_0' => '5', '1_0' => '', '2_0' => ''];
        $lastgradedresponses = [];
        $partscores = $q->grade_parts_that_can_be_graded($response, $lastgradedresponses, false);

        // There have been no previously registered attempts. The new $response is correct for
        // part #0 and empty for the other parts. Thus, we expect a full-mark grading result for
        // part #0 only.
        $expected = [
            '0' => new qbehaviour_adaptivemultipart_part_result('0', 1, 0.3),
        ];
        self::assertEquals($expected, $partscores);
    }

    public function test_grade_parts_that_can_be_graded_test6() {
        $q = $this->get_test_formulas_question('testmethodsinparts');
        $q->start_attempt(new question_attempt_step(), 1);

        $response = ['0_' => '40 m/s', '1_0' => '30', '1_1' => 'm/s', '2_0' => '40', '3_0' => '50'];
        $lastgradedresponses = [
            '0' => ['0_' => '20 m/s', '1_0' => '0', '1_1' => 'm/s', '2_0' => '0', '3_0' => '40'],
            '1' => ['0_' => '30 m/s', '1_0' => '0', '1_1' => 'm/s', '2_0' => '40', '3_0' => '40'],
        ];
        $partscores = $q->grade_parts_that_can_be_graded($response, $lastgradedresponses, false);

        // The latest $response is correct for parts #0 and #2; it has the wrong value but correct unit
        // for part #1.
        // We have no previously registered attempts for parts #2 and #3, so they should be graded.
        // Parts #0 and #1 have been answered wrong in their last respective attempt, so they should
        // be graded.
        $expected = [
            '0' => new qbehaviour_adaptivemultipart_part_result('0', 1, 0.3),
            '1' => new qbehaviour_adaptivemultipart_part_result('1', 0, 0.3),
            '2' => new qbehaviour_adaptivemultipart_part_result('2', 1, 0.3),
            '3' => new qbehaviour_adaptivemultipart_part_result('3', 0, 0.3),
        ];
        self::assertEquals($expected, $partscores);
    }

    public function test_get_parts_and_weights_test0() {
        $q = $this->get_test_formulas_question('testsinglenum');

        self::assertEquals(array('0' => 1), $q->get_parts_and_weights());
    }

    public function test_get_parts_and_weights_threeparts() {
        $q = $this->get_test_formulas_question('testthreeparts');

        self::assertEquals(array('0' => 1 / 3, '1' => 1 / 3, '2' => 1 / 3), $q->get_parts_and_weights());
    }

    public function test_get_parts_and_weights_test2() {
        $q = $this->get_test_formulas_question('test4');

        self::assertEquals(array('0' => .25, '1' => .25, '2' => .25, '3' => .25), $q->get_parts_and_weights());
    }

    public function test_compute_final_grade_threeparts_1() {
        $q = $this->get_test_formulas_question('testthreeparts');
        $q->start_attempt(new question_attempt_step(), 1);

        $responses = array(0 => array('0_0' => '5', '1_0' => '7', '2_0' => '6'),
                           1 => array('0_0' => '5', '1_0' => '7', '2_0' => '7'),
                           2 => array('0_0' => '5', '1_0' => '6', '2_0' => '7')
                          );
        $finalgrade = $q->compute_final_grade($responses, 3);
        self::assertEquals((2 + 2 * (1 - 2 * 0.3) + 2 * (1 - 0.3)) / 6, $finalgrade);

    }

    public function test_compute_final_grade_threeparts_2() {
        $q = $this->get_test_formulas_question('testthreeparts');
        $q->start_attempt(new question_attempt_step(), 1);

        $responses = array(0 => array('0_0' => '5', '1_0' => '7', '2_0' => '6'),
                           1 => array('0_0' => '5', '1_0' => '8', '2_0' => '6'),
                           2 => array('0_0' => '5', '1_0' => '6', '2_0' => '6')
                          );
        $finalgrade = $q->compute_final_grade($responses, 3);
        self::assertEquals((2 + 2 * (1 - 2 * 0.3)) / 6, $finalgrade);
    }

    public function test_compute_final_grade_with_unit() {
        $q = $this->get_test_formulas_question('testsinglenumunit');
        $q->start_attempt(new question_attempt_step(), 1);

        $responses = [
            0 => ['0_' => '5 km/s'],
            1 => ['0_' => '5 kg'],
            2 => ['0_' => '5 m/s'],
        ];
        $finalgrade = $q->compute_final_grade($responses, 3);
        self::assertEquals(1 - 2 * 0.3, $finalgrade);
    }

    public function test_compute_final_grade_with_zero() {
        $q = $this->get_test_formulas_question('testzero');
        $q->start_attempt(new question_attempt_step(), 1);

        $responses = [
            0 => ['0_0' => '1'],
            1 => ['0_0' => ''],
            2 => ['0_0' => '0'],
        ];
        $finalgrade = $q->compute_final_grade($responses, 3);
        self::assertEquals(1 - 2 * 0.3, $finalgrade);
    }

    public function test_summarise_response_threeparts() {
        $q = $this->get_test_formulas_question('testthreeparts');
        $q->start_attempt(new question_attempt_step(), 1);

        $response = array('0_0' => '5', '1_0' => '6', '2_0' => '7');
        $summary0 = $q->parts[0]->summarise_response($response);
        self::assertEquals('5', $summary0);
        $summary1 = $q->parts[1]->summarise_response($response);
        self::assertEquals('6', $summary1);
        $summary2 = $q->parts[2]->summarise_response($response);
        self::assertEquals('7', $summary2);
        $summary = $q->summarise_response($response);
        self::assertEquals('5, 6, 7', $summary);
    }

    public function test_summarise_response_test1() {
        $q = $this->get_test_formulas_question('test4');
        $q->start_attempt(new question_attempt_step(), 1);

        $response = array('0_' => "30m/s", '1_0' => "20", '1_1' => 'm/s', '2_0' => "40", '3_0' => "50");
        $summary0 = $q->parts[0]->summarise_response($response);
        self::assertEquals('30m/s', $summary0);
        $summary1 = $q->parts[1]->summarise_response($response);
        self::assertEquals('20, m/s', $summary1);
        $summary2 = $q->parts[2]->summarise_response($response);
        self::assertEquals('40', $summary2);
        $summary3 = $q->parts[3]->summarise_response($response);
        self::assertEquals('50', $summary3);
        $summary = $q->summarise_response($response);
        self::assertEquals('30m/s, 20, m/s, 40, 50', $summary);
    }

    public function test_is_complete_response_test3() {
        $q = $this->get_test_formulas_question('testzero');

        self::assertFalse($q->is_complete_response(array()));
        self::assertTrue($q->is_complete_response(array('0_0' => '0')));
        self::assertTrue($q->is_complete_response(array('0_0' => 0)));
        self::assertTrue($q->is_complete_response(array('0_0' => 'test')));
    }

    public function test_is_gradable_response_test3() {
        $q = $this->get_test_formulas_question('testzero');

        self::assertFalse($q->is_gradable_response(array()));
        self::assertTrue($q->is_gradable_response(array('0_0' => '0')));
        self::assertTrue($q->is_gradable_response(array('0_0' => 0)));
        self::assertTrue($q->is_gradable_response(array('0_0' => '0.0')));
        self::assertTrue($q->is_gradable_response(array('0_0' => '5')));
        self::assertTrue($q->is_gradable_response(array('0_0' => 5)));
    }

    public function provide_answer_box_texts(): array {
        return [
            [[], ''],
            [[], '{ _0}'],
            [[], '{_ 0}'],
            [[], '{_0 }'],
            [[], '{_0::}'],
            [[], '{_0::MCE}'],
            [[], '{_0:foo:}'],
            [[], '{_0:foo }'],
            [[], '{ _0:foo}'],
            [[], '{ _u}'],
            [[], '{_ u}'],
            [[], '{_u }'],
            [[], '{_a}'],
            [[
                '_0' => ['placeholder' => '{_0}', 'options' => '', 'dropdown' => false]
            ], '{_0}'],
            [[
                '_0' => ['placeholder' => '{_0}', 'options' => '', 'dropdown' => false]
            ], '{_0} {_1:}'],
            [[
                '_0' => ['placeholder' => '{_0:foo}', 'options' => 'foo', 'dropdown' => false]
            ], '{_0:foo}'],
            [[
                '_0' => ['placeholder' => '{_0:MCE}', 'options' => 'MCE', 'dropdown' => false]
            ], '{_0:MCE}'],
            [[
                '_0' => ['placeholder' => '{_0:foo:MCE}', 'options' => 'foo', 'dropdown' => true]
            ], '{_0:foo:MCE}'],
            [[
                '_0' => ['placeholder' => '{_0}', 'options' => '', 'dropdown' => false],
                '_u' => ['placeholder' => '{_u}', 'options' => '', 'dropdown' => false],
            ], '{_0}{_u}'],
            [[
                '_0' => ['placeholder' => '{_0:foo}', 'options' => 'foo', 'dropdown' => false],
                '_u' => ['placeholder' => '{_u}', 'options' => '', 'dropdown' => false],
            ], '{_0:foo} {_u}'],
            [[
                '_0' => ['placeholder' => '{_0:MCE}', 'options' => 'MCE', 'dropdown' => false],
                '_u' => ['placeholder' => '{_u}', 'options' => '', 'dropdown' => false],
            ], '{_0:MCE} {_u}'],
            [[
                '_0' => ['placeholder' => '{_0:foo:MCE}', 'options' => 'foo', 'dropdown' => true],
                '_u' => ['placeholder' => '{_u}', 'options' => '', 'dropdown' => false],
            ], '{_0:foo:MCE} {_u}'],
        ];
    }

    /**
     * @dataProvider provide_answer_box_texts
     */
    public function test_scan_for_answer_boxes($expected, $input) {
        $boxes = qtype_formulas_part::scan_for_answer_boxes($input);
        self::assertSame($expected, $boxes);
    }

    public function provide_answer_box_texts_invalid(): array {
        return [
            ['_0', '{_0} {_0}'],
            ['_0', '{_0:foo} {_0}'],
            ['_0', '{_0:foo} {_0:bar}'],
            ['_0', '{_0:foo:MCE} {_0}'],
            ['_0', '{_0:foo:MCE} {_0:foo}'],
            ['_u', '{_0}{_u} {_u}'],
        ];
    }

    /**
     * @dataProvider provide_answer_box_texts_invalid
     */
    public function test_scan_for_answer_boxes_invalid($expected, $input) {
        $e = null;
        try {
            qtype_formulas_part::scan_for_answer_boxes($input);
        } catch (Exception $e) {
            self::assertStringContainsString(get_string('error_answerbox_duplicate', 'qtype_formulas', $expected), $e->getMessage());
        }
        self::assertNotNull($e);
    }

    public function provide_responses_for_combined_question(): array {
        return [
            [['id' => null, 'fraction' => null], ''],
            [['id' => 'right', 'fraction' => 1], '5 m/s'],
            [['id' => 'wrongvalue', 'fraction' => 0], '15 m/s'],
            [['id' => 'wrongunit', 'fraction' => 0.5], '5'],
            [['id' => 'wrong', 'fraction' => 0], '99 kg'],
        ];
    }

    /**
     * @dataProvider provide_responses_for_combined_question
     */
    public function test_classify_response_combined_field($expected, $input) {
        // Prepare a question.
        $question = $this->get_test_formulas_question('testsinglenumunit');
        $question->parts[0]->unitpenalty = 0.5;

        // Prepare and start a question attempt.
        $quba = new question_usage_by_activity('qtype_formulas', \context_system::instance());
        $qa = new question_attempt($question, $quba->get_id());
        $qa->start('immediatefeedback', 1);

        $qdata = \test_question_maker::get_question_data('formulas', 'testsinglenumunit');
        $possibleresponses = \question_bank::get_qtype('formulas')->get_possible_responses($qdata);


        $response = ['0_' => $input, '-submit' => 1];
        $qa->process_action($response);
        $classification = $question->classify_response($response)[0];

        // If we send an empty response, we will get a special classification with its own response summary.
        // Check that the classification is in the list of possibleresponses.
        if ($expected['id'] === null) {
            $input = get_string('noresponse', 'question');
            // We cannot use 'null' as key for assertArrayHasKey, so we use the empty string.
            self::assertArrayHasKey('', $possibleresponses[0]);
        } else {
            self::assertArrayHasKey($expected['id'], $possibleresponses[0]);
        }

        self::assertEquals($expected['id'], $classification->responseclassid);
        self::assertEquals($expected['fraction'], $classification->fraction);
        self::assertEquals($input, $classification->response);
    }

    public function provide_responses_for_question_with_separate_unit(): array {
        return [
            [['id' => null, 'fraction' => null], ['', '']],
            [['id' => 'right', 'fraction' => 1], ['5', 'm/s']],
            [['id' => 'wrongvalue', 'fraction' => 0], ['15', 'm/s']],
            [['id' => 'wrongunit', 'fraction' => 0.5], ['5', '']],
            [['id' => 'wrong', 'fraction' => 0], ['99', 'kg']],
            [['id' => 'wrong', 'fraction' => 0], ['15', '']],
            [['id' => 'wrong', 'fraction' => 0], ['', 'kg']],
        ];
    }

    /**
     * @dataProvider provide_responses_for_question_with_separate_unit
     */
    public function test_classify_response_separate_unit_field($expected, $input) {
        // Prepare a question.
        $question = $this->get_test_formulas_question('testsinglenumunitsep');
        $question->parts[0]->unitpenalty = 0.5;

        // Prepare and start a question attempt.
        $quba = new question_usage_by_activity('qtype_formulas', \context_system::instance());
        $qa = new question_attempt($question, $quba->get_id());
        $qa->start('immediatefeedback', 1);

        $response = ['0_0' => $input[0], '0_1' => $input[1], '-submit' => 1];
        $qa->process_action($response);
        $classification = $question->classify_response($response)[0];

        // If we send an empty response, we will get a special classification with its own response summary.
        if ($expected['id'] === null) {
            $summary = '[No response]';
        } else {
            $summary = "{$input[0]}, {$input[1]}";
        }

        self::assertEquals($expected['id'], $classification->responseclassid);
        self::assertEquals($expected['fraction'], $classification->fraction);
        self::assertEquals($summary, $classification->response);
    }

    public function provide_responses_for_threepart_question(): array {
        return [
            [['id' => [null, null, null], 'fraction' => [null, null, null]], ['', '', '']],
            [['id' => ['right', 'right', 'right'], 'fraction' => [1, 1, 1]], ['5', '6', '7']],
            [['id' => ['wrong', 'right', 'right'], 'fraction' => [0, 1, 1]], ['1', '6', '7']],
            [['id' => ['wrong', null, 'right'], 'fraction' => [0, null, 1]], ['1', '', '7']],
            [['id' => ['right', 'wrong', null], 'fraction' => [1, 0, null]], ['5', 'x', '']],
        ];
    }

    /**
     * @dataProvider provide_responses_for_threepart_question
     */
    public function test_classify_response_three_parts_without_unit($expected, $input) {
        // Prepare a question.
        $question = $this->get_test_formulas_question('testthreeparts');

        // Prepare and start a question attempt.
        $quba = new question_usage_by_activity('qtype_formulas', \context_system::instance());
        $qa = new question_attempt($question, $quba->get_id());
        $qa->start('immediatefeedback', 1);

        $response = [
            '0_0' => $input[0],
            '1_0' => $input[1],
            '2_0' => $input[2],
            '-submit' => 1
        ];
        $qa->process_action($response);
        $classification = $question->classify_response($response);

        for ($i = 0; $i < 3; $i++) {
            // If we send an empty response, we will get a special classification with its own response summary.
            if ($expected['id'][$i] === null) {
                $input[$i] = '[No response]';
            }

            self::assertEquals($expected['id'][$i], $classification[$i]->responseclassid);
            self::assertEquals($expected['fraction'][$i], $classification[$i]->fraction);
            self::assertEquals($input[$i], $classification[$i]->response);
        }
    }

    // TODO
    public function test_student_using_overwritten_function() {
        // something like this:
        // global vars: sin = 3, x = {-5:5}
        // answer numerical formula \sin(20) (prefix needed)
        // student answers sin(20) should be incorrect (because sin == 3 for student)
        // answer algebraic formula "3x"
        // student answers "sin(x)" should be correct
    }
}
