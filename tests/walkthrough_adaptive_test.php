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

use question_state;
use test_question_maker;
use question_hint_with_parts;
use qtype_formulas;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once(dirname(__FILE__) . '/../../../engine/lib.php');
require_once($CFG->dirroot . '/question/engine/tests/helpers.php');
require_once(dirname(__FILE__) . '/test_base.php');
require_once($CFG->dirroot . '/question/type/formulas/tests/helper.php');


/**
 * Unit tests for the formulas question type.
 *
 * @package    qtype_formulas
 * @copyright  2012 Jean-Michel Vedrine
 * @copyright  2024 Philipp Imhof
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @covers \qtype_formulas
 * @covers \qtype_formulas_part
 * @covers \qtype_formulas_question
 */
final class walkthrough_adaptive_test extends walkthrough_test_base {
    /**
     * Create a question object of a certain type, as defined in the helper.php file.
     *
     * @param string|null $which the test question name
     * @return qtype_formulas_question
     */
    protected function get_test_formulas_question($which = null) {
        return test_question_maker::make_question('formulas', $which);
    }

    public function test_submit_empty_then_right(): void {
        // Create the formulas question 'testsinglenum'.
        $q = $this->get_test_formulas_question('testsinglenum');
        $this->start_attempt_at_question($q, 'adaptive', 1);

        // Check the initial state.
        $this->check_current_state(question_state::$todo);
        $this->check_current_mark(null);
        self::assertEquals(
            'adaptivemultipart',
            $this->quba->get_question_attempt($this->slot)->get_behaviour_name()
        );
        $this->render();
        $this->check_output_contains_text_input('0_0');
        $this->check_output_does_not_contain_text_input_with_class('0_0', 'correct');
        $this->check_output_does_not_contain_text_input_with_class('0_0', 'partiallycorrect');
        $this->check_output_does_not_contain_text_input_with_class('0_0', 'incorrect');
        $this->check_current_output(
            $this->get_contains_marked_out_of_summary(),
            $this->get_contains_submit_button_expectation(true),
            $this->get_does_not_contain_feedback_expectation(),
        );

        // Submit the empty form.
        $this->process_submission(['-submit' => 1]);

        // Verify.
        $this->check_current_state(question_state::$invalid);
        $this->check_current_mark(null);
        $this->render();
        $this->check_output_contains_text_input('0_0');
        $this->check_current_output(
            $this->get_contains_marked_out_of_summary(),
            $this->get_contains_submit_button_expectation(true),
            $this->get_contains_validation_error_expectation(),
        );

        // Submit the right answer.
        $this->process_submission(['0_0' => '5', '-submit' => 1]);

        // Verify.
        $this->check_current_state(question_state::$complete);
        $this->check_current_mark(1);
        $this->render();
        $this->check_output_contains_text_input('0_0', '5', true);
        $this->check_output_does_not_contain_stray_placeholders();
        $this->check_current_output(
            $this->get_contains_mark_summary(1),
            $this->get_contains_submit_button_expectation(true),
            $this->get_does_not_contain_validation_error_expectation(),
        );

        // Finish the attempt.
        $this->quba->finish_all_questions();

        // Verify.
        $this->check_current_state(question_state::$gradedright);
        $this->check_current_mark(1);
        $this->check_current_output(
            $this->get_contains_mark_summary(1),
            $this->get_contains_correct_expectation(),
            $this->get_does_not_contain_validation_error_expectation(),
        );
    }

    public function test_deferred_partially_answered(): void {
        // Create a multipart Formulas question.
        $q = $this->get_test_formulas_question('testthreeparts');
        $this->start_attempt_at_question($q, 'deferredfeedback', 3);

        // Check the initial state.
        $this->check_current_state(question_state::$todo);
        $this->check_current_mark(null);
        $this->render();
        $this->check_output_contains_text_input('0_0');
        $this->check_output_contains_text_input('1_0');
        $this->check_output_contains_text_input('2_0');
        $this->check_current_output(
            $this->get_contains_marked_out_of_summary(),
            $this->get_does_not_contain_submit_button_expectation(),
            $this->get_does_not_contain_feedback_expectation(),
        );

        // Submit the empty form. The question should be counted as "gave up" with no grade.
        // The feedback should be "incorrect".
        $this->finish();
        $this->check_current_state(question_state::$gaveup);
        $this->check_current_mark(null);
        $this->render();
        $this->check_output_contains_text_input('0_0', '', false);
        $this->check_output_contains_text_input('1_0', '', false);
        $this->check_output_contains_text_input('2_0', '', false);

        // Submit a partial answer, answering only the first part. The question should be
        // graded partially correct. The student's response should be shown in the text field.
        // All text fields should be disabled.
        $this->start_attempt_at_question($q, 'deferredfeedback', 3);
        $this->check_current_state(question_state::$todo);
        $this->process_submission(['0_0' => '5']);
        $this->finish();
        $this->check_current_state(question_state::$gradedpartial);
        $this->check_current_mark(1);
        $this->render();
        $this->check_output_contains_text_input('0_0', '5', false);
        $this->check_output_contains_text_input('1_0', '', false);
        $this->check_output_contains_text_input('2_0', '', false);
        $this->check_current_output(
            $this->get_contains_mark_summary(1),
            $this->get_contains_partcorrect_expectation(),
        );

        // Submit a partial answer, not answering the first part. The question should be
        // graded partially correct. The student's response should be shown in the text fields.
        // All text fields should be disabled.
        $this->start_attempt_at_question($q, 'deferredfeedback', 3);
        $this->check_current_state(question_state::$todo);
        $this->process_submission(['1_0' => '6', '2_0' => '7']);
        $this->finish();
        $this->check_current_state(question_state::$gradedpartial);
        $this->check_current_mark(2);
        $this->render();
        $this->check_output_contains_text_input('0_0', '', false);
        $this->check_output_contains_text_input('1_0', '6', false);
        $this->check_output_contains_text_input('2_0', '7', false);
        $this->check_current_output(
            $this->get_contains_mark_summary(2),
            $this->get_contains_partcorrect_expectation(),
        );

        // Submit a partial answer, answering only the second part, but wrong. The question should be
        // graded incorrect. The student's response should be shown in the text field.
        // All text fields should be disabled.
        $this->start_attempt_at_question($q, 'deferredfeedback', 3);
        $this->check_current_state(question_state::$todo);
        $this->process_submission(['1_0' => '5']);
        $this->finish();
        $this->check_current_state(question_state::$gradedwrong);
        $this->check_current_mark(0);
        $this->render();
        $this->check_output_contains_text_input('0_0', '', false);
        $this->check_output_contains_text_input('1_0', '5', false);
        $this->check_output_contains_text_input('2_0', '', false);
        $this->check_current_output(
            $this->get_contains_mark_summary(0),
            $this->get_contains_incorrect_expectation(),
        );

        // Finally, submit a full and correct answer. The question should be graded right.
        // The student's response should be shown in the text fields. All text fields should
        // be disabled.
        $this->start_attempt_at_question($q, 'deferredfeedback', 3);
        $this->check_current_state(question_state::$todo);
        $this->process_submission(['0_0' => '5', '1_0' => '6', '2_0' => '7']);
        $this->finish();
        $this->check_current_state(question_state::$gradedright);
        $this->check_current_mark(3);
        $this->render();
        $this->check_output_contains_text_input('0_0', '5', false);
        $this->check_output_contains_text_input('1_0', '6', false);
        $this->check_output_contains_text_input('2_0', '7', false);
        $this->check_current_output(
            $this->get_contains_mark_summary(3),
            $this->get_contains_correct_expectation(),
        );
    }

    public function test_immediate_partially_answered(): void {
        // Create a multipart Formulas question.
        $q = $this->get_test_formulas_question('testthreeparts');
        $this->start_attempt_at_question($q, 'immediatefeedback', 3);

        // Check the initial state.
        $this->check_current_state(question_state::$todo);
        $this->check_current_mark(null);
        $this->render();
        $this->check_output_contains_text_input('0_0');
        $this->check_output_contains_text_input('1_0');
        $this->check_output_contains_text_input('2_0');
        $this->check_current_output(
            $this->get_contains_marked_out_of_summary(),
            $this->get_contains_submit_button_expectation(),
            $this->get_does_not_contain_feedback_expectation(),
        );

        // Submit the empty form. The question should be makred as "incomplete" and an error
        // message should be shown.
        $this->process_submission(['-submit' => '1']);
        $this->check_current_state(question_state::$invalid);
        $this->check_current_mark(null);
        $this->render();
        $this->check_output_contains_text_input('0_0', '');
        $this->check_output_contains_text_input('1_0', '');
        $this->check_output_contains_text_input('2_0', '');
        $this->check_output_contains('All input fields are empty.');

        // Submit a partial answer, answering only the first part. The question should be
        // marked "incomplete". The student's response should be shown in the text field.
        // An error message should be shown. All fields should be enabled.
        $this->start_attempt_at_question($q, 'immediatefeedback', 3);
        $this->check_current_state(question_state::$todo);
        $this->process_submission(['0_0' => '5', '-submit' => '1']);
        $this->check_current_state(question_state::$invalid);
        $this->check_current_mark(null);
        $this->render();
        $this->check_output_contains_text_input('0_0', '5');
        $this->check_output_contains_text_input('1_0', '');
        $this->check_output_contains_text_input('2_0', '');
        $this->check_output_contains('Please put an answer in each input field.');

        // Submit a partial answer, not answering the first part. The question should be
        // graded partially correct. The student's response should be shown in the text fields.
        // All text fields should be disabled.
        $this->start_attempt_at_question($q, 'immediatefeedback', 3);
        $this->check_current_state(question_state::$todo);
        $this->process_submission(['1_0' => '6', '2_0' => '7', '-submit' => '1']);
        $this->check_current_state(question_state::$invalid);
        $this->check_current_mark(null);
        $this->render();
        $this->check_output_contains_text_input('0_0', '');
        $this->check_output_contains_text_input('1_0', '6');
        $this->check_output_contains_text_input('2_0', '7');
        $this->check_output_contains('Please put an answer in each input field.');

        // Finally, submit a full and correct answer. The question should be graded right.
        // The student's response should be shown in the text fields. All text fields should
        // be disabled.
        $this->start_attempt_at_question($q, 'immediatefeedback', 3);
        $this->check_current_state(question_state::$todo);
        $this->process_submission(['0_0' => '5', '1_0' => '6', '2_0' => '7', '-submit' => '1']);
        $this->check_current_state(question_state::$gradedright);
        $this->check_current_mark(3);
        $this->render();
        $this->check_output_contains_text_input('0_0', '5', false);
        $this->check_output_contains_text_input('1_0', '6', false);
        $this->check_output_contains_text_input('2_0', '7', false);
        $this->check_current_output(
            $this->get_contains_mark_summary(3),
            $this->get_contains_correct_expectation(),
        );
    }

    public function test_interactive_partially_answered(): void {
        // Create a multipart Formulas question.
        $q = $this->get_test_formulas_question('testthreeparts');
        $this->start_attempt_at_question($q, 'interactive', 3);

        // Check the initial state.
        $this->check_current_state(question_state::$todo);
        $this->check_current_mark(null);
        $this->render();
        $this->check_output_contains_text_input('0_0');
        $this->check_output_contains_text_input('1_0');
        $this->check_output_contains_text_input('2_0');
        $this->check_current_output(
            $this->get_contains_marked_out_of_summary(),
            $this->get_contains_submit_button_expectation(),
            $this->get_does_not_contain_feedback_expectation(),
        );

        // Submit the empty form. The question should be makred as "incomplete" and an error
        // message should be shown.
        $this->process_submission(['-submit' => '1']);
        $this->check_current_state(question_state::$invalid);
        $this->check_current_mark(null);
        $this->render();
        $this->check_output_contains_text_input('0_0', '');
        $this->check_output_contains_text_input('1_0', '');
        $this->check_output_contains_text_input('2_0', '');
        $this->check_output_contains('All input fields are empty.');

        // Submit a partial answer, answering only the first part. The question should be
        // marked "incomplete". The student's response should be shown in the text field.
        // An error message should be shown. All fields should be enabled.
        $this->start_attempt_at_question($q, 'interactive', 3);
        $this->check_current_state(question_state::$todo);
        $this->process_submission(['0_0' => '5', '-submit' => '1']);
        $this->check_current_state(question_state::$invalid);
        $this->check_current_mark(null);
        $this->render();
        $this->check_output_contains_text_input('0_0', '5');
        $this->check_output_contains_text_input('1_0', '');
        $this->check_output_contains_text_input('2_0', '');
        $this->check_output_contains('Please put an answer in each input field.');

        // Submit a partial answer, not answering the first part. The question should be
        // marked "incomplete". The student's response should be shown in the text fields.
        // An error message should be shown. All fields should be enabled.
        $this->start_attempt_at_question($q, 'interactive', 3);
        $this->check_current_state(question_state::$todo);
        $this->process_submission(['1_0' => '6', '2_0' => '7', '-submit' => '1']);
        $this->check_current_state(question_state::$invalid);
        $this->check_current_mark(null);
        $this->render();
        $this->check_output_contains_text_input('0_0', '');
        $this->check_output_contains_text_input('1_0', '6');
        $this->check_output_contains_text_input('2_0', '7');
        $this->check_output_contains('Please put an answer in each input field.');

        // Submit a complete, but only partialy correct answer. The question should be graded
        // partially right. The student's response should be shown in the text fields. All text
        // fields should be disabled.
        $this->start_attempt_at_question($q, 'interactive', 3);
        $this->check_current_state(question_state::$todo);
        $this->process_submission(['0_0' => '5', '1_0' => '1', '2_0' => '7', '-submit' => '1']);
        $this->check_current_state(question_state::$gradedpartial);
        $this->check_current_mark(2);
        $this->render();
        $this->check_output_contains_text_input('0_0', '5', false);
        $this->check_output_contains_text_input('1_0', '1', false);
        $this->check_output_contains_text_input('2_0', '7', false);
        $this->check_current_output(
            $this->get_contains_mark_summary(2),
            $this->get_contains_partcorrect_expectation(),
        );

        // Finally, submit a full and correct answer. The question should be graded right.
        // The student's response should be shown in the text fields. All text fields should
        // be disabled.
        $this->start_attempt_at_question($q, 'interactive', 3);
        $this->check_current_state(question_state::$todo);
        $this->process_submission(['0_0' => '5', '1_0' => '6', '2_0' => '7', '-submit' => '1']);
        $this->check_current_state(question_state::$gradedright);
        $this->check_current_mark(3);
        $this->render();
        $this->check_output_contains_text_input('0_0', '5', false);
        $this->check_output_contains_text_input('1_0', '6', false);
        $this->check_output_contains_text_input('2_0', '7', false);
        $this->check_current_output(
            $this->get_contains_mark_summary(3),
            $this->get_contains_correct_expectation(),
        );
    }

    public function test_adaptive_partially_answered(): void {
        // Create a multipart Formulas question.
        $q = $this->get_test_formulas_question('testthreeparts');
        $this->start_attempt_at_question($q, 'adaptive', 3);

        // Check the initial state.
        $this->check_current_state(question_state::$todo);
        $this->check_current_mark(null);
        $this->render();
        $this->check_output_contains_text_input('0_0');
        $this->check_output_contains_text_input('1_0');
        $this->check_output_contains_text_input('2_0');
        $this->check_current_output(
            $this->get_contains_marked_out_of_summary(),
            $this->get_contains_submit_button_expectation(),
            $this->get_does_not_contain_feedback_expectation(),
        );

        // Submit the empty form. The question should be counted as "invalid" with no grade.
        // An error message should be visible.
        $this->process_submission(['-submit' => '1']);
        $this->check_current_state(question_state::$invalid);
        $this->check_current_mark(null);
        $this->render();
        $this->check_output_contains('All input fields are empty.');
        $this->check_output_contains_text_input('0_0', '');
        $this->check_output_contains_text_input('1_0', '');
        $this->check_output_contains_text_input('2_0', '');

        // Submit a partial answer, answering only the first part. The question should remain
        // in "todo" state, but the student should have 1 point. The student's response should
        // be shown in the text field. All text fields should be enabled.
        $this->start_attempt_at_question($q, 'adaptive', 3);
        $this->check_current_state(question_state::$todo);
        $this->process_submission(['0_0' => '5', '-submit' => '1']);
        $this->check_current_state(question_state::$todo);
        $this->check_current_mark(1);
        $this->render();
        $this->check_output_contains_text_input('0_0', '5');
        $this->check_output_contains_text_input('1_0', '');
        $this->check_output_contains_text_input('2_0', '');
        $this->check_output_contains('Parts, but only parts, of your response are correct.');

        // Submit a partial answer, not answering the first part. The question should remain
        // in "todo" state, but the student should have 2 points. The student's response should
        // be shown in the text fields. All text fields should be enabled.
        $this->start_attempt_at_question($q, 'adaptive', 3);
        $this->check_current_state(question_state::$todo);
        $this->process_submission(['1_0' => '6', '2_0' => '7', '-submit' => 1]);
        $this->check_current_state(question_state::$todo);
        $this->check_current_mark(2);
        $this->render();
        $this->check_output_contains_text_input('0_0', '');
        $this->check_output_contains_text_input('1_0', '6');
        $this->check_output_contains_text_input('2_0', '7');
        $this->check_output_contains('Parts, but only parts, of your response are correct.');

        // Submit a partial answer, answering only the second part, but wrong. The question
        // should remain in "todo" state, but the student should have 0 points. The student's
        // response should be shown in the text field. All text fields should be enabled.
        $this->start_attempt_at_question($q, 'adaptive', 3);
        $this->check_current_state(question_state::$todo);
        $this->process_submission(['1_0' => '5', '-submit' => '1']);
        $this->check_current_state(question_state::$todo);
        $this->check_current_mark(0);
        $this->render();
        $this->check_output_contains_text_input('0_0', '');
        $this->check_output_contains_text_input('1_0', '5');
        $this->check_output_contains_text_input('2_0', '');
        $this->check_current_output(
            $this->get_contains_mark_summary(0),
            $this->get_contains_incorrect_expectation(),
        );

        // Finally, submit a full and correct answer. The question should be marked "complete".
        // The student's response should be shown in the text fields. All text fields should
        // be enabled. The student should have 3 points.
        $this->start_attempt_at_question($q, 'adaptive', 3);
        $this->check_current_state(question_state::$todo);
        $this->process_submission(['0_0' => '5', '1_0' => '6', '2_0' => '7', '-submit' => 1]);
        $this->check_current_state(question_state::$complete);
        $this->check_current_mark(3);
        $this->render();
        $this->check_output_contains_text_input('0_0', '5');
        $this->check_output_contains_text_input('1_0', '6');
        $this->check_output_contains_text_input('2_0', '7');
        $this->check_output_contains('Well done!');
    }

    public function test_submit_empty_then_right_with_combined_unit(): void {
        // Create the formulas question 'testsinglenumunit'.
        $q = $this->get_test_formulas_question('testsinglenumunit');
        $this->start_attempt_at_question($q, 'adaptive', 1);

        // Check the initial state.
        $this->check_current_state(question_state::$todo);
        $this->check_current_mark(null);
        self::assertEquals(
            'adaptivemultipart',
            $this->quba->get_question_attempt($this->slot)->get_behaviour_name()
        );
        $this->render();
        $this->check_output_contains_text_input('0_');
        $this->check_output_does_not_contain_text_input_with_class('0_', 'correct');
        $this->check_output_does_not_contain_text_input_with_class('0_', 'partiallycorrect');
        $this->check_output_does_not_contain_text_input_with_class('0_', 'incorrect');
        $this->check_current_output(
            $this->get_contains_marked_out_of_summary(),
            $this->get_contains_submit_button_expectation(true),
            $this->get_does_not_contain_feedback_expectation(),
        );

        // Submit the empty form.
        $this->process_submission(['-submit' => 1]);

        // Verify.
        $this->check_current_state(question_state::$invalid);
        $this->check_current_mark(null);
        $this->render();
        $this->check_output_contains_text_input('0_');
        $this->check_current_output(
            $this->get_contains_marked_out_of_summary(),
            $this->get_contains_submit_button_expectation(true),
            $this->get_contains_validation_error_expectation(),
        );

        // Submit the right answer.
        $this->process_submission(['0_' => '5 m/s', '-submit' => 1]);

        // Verify.
        $this->check_current_state(question_state::$complete);
        $this->check_current_mark(1);
        $this->render();
        $this->check_output_contains_text_input('0_', '5 m/s', true);
        $this->check_output_does_not_contain_stray_placeholders();
        $this->check_current_output(
            $this->get_contains_mark_summary(1),
            $this->get_contains_submit_button_expectation(true),
            $this->get_does_not_contain_validation_error_expectation(),
        );

        // Finish the attempt.
        $this->quba->finish_all_questions();

        // Verify.
        $this->check_current_state(question_state::$gradedright);
        $this->check_current_mark(1);
        $this->check_current_output(
            $this->get_contains_mark_summary(1),
            $this->get_contains_correct_expectation(),
            $this->get_does_not_contain_validation_error_expectation(),
        );
    }

    public function test_submit_empty_then_right_with_separate_unit(): void {
        // Create the formulas question 'testsinglenumunitsep'.
        $q = $this->get_test_formulas_question('testsinglenumunitsep');
        $this->start_attempt_at_question($q, 'adaptive', 1);

        // Check the initial state.
        $this->check_current_state(question_state::$todo);
        $this->check_current_mark(null);
        self::assertEquals(
            'adaptivemultipart',
            $this->quba->get_question_attempt($this->slot)->get_behaviour_name()
        );
        $this->render();
        $this->check_output_contains_text_input('0_0');
        $this->check_output_contains_text_input('0_1');
        $this->check_current_output(
            $this->get_contains_marked_out_of_summary(),
            $this->get_contains_submit_button_expectation(true),
            $this->get_does_not_contain_feedback_expectation(),
        );

        // Submit the empty form.
        $this->process_submission(['-submit' => 1]);

        // Verify.
        $this->check_current_state(question_state::$invalid);
        $this->check_current_mark(null);
        $this->render();
        $this->check_output_contains_text_input('0_0');
        $this->check_output_contains_text_input('0_1');
        $this->check_current_output(
            $this->get_contains_marked_out_of_summary(),
            $this->get_contains_submit_button_expectation(true),
            $this->get_contains_validation_error_expectation(),
        );

        // Submit the right answer.
        $this->process_submission(['0_0' => '5', '0_1' => 'm/s', '-submit' => 1]);

        // Verify.
        $this->check_current_state(question_state::$complete);
        $this->check_current_mark(1);
        $this->render();
        $this->check_output_contains_text_input('0_0', '5', true);
        $this->check_output_contains_text_input('0_1', 'm/s', true);
        $this->check_current_output(
            $this->get_contains_mark_summary(1),
            $this->get_contains_submit_button_expectation(true),
            $this->get_does_not_contain_validation_error_expectation(),
        );

        // Finish the attempt.
        $this->quba->finish_all_questions();

        // Verify.
        $this->check_current_state(question_state::$gradedright);
        $this->check_current_mark(1);
        $this->check_current_output(
            $this->get_contains_mark_summary(1),
            $this->get_contains_correct_expectation(),
            $this->get_does_not_contain_validation_error_expectation(),
        );
    }

    public function test_test0_submit_right_first_time(): void {
        // Create the formulas question 'test0'.
        $q = $this->get_test_formulas_question('testsinglenum');
        $this->start_attempt_at_question($q, 'adaptive', 1);

        // Check the initial state.
        $this->check_current_state(question_state::$todo);
        $this->check_current_mark(null);
        self::assertEquals('adaptivemultipart',
        $this->quba->get_question_attempt($this->slot)->get_behaviour_name());
        $this->render();
        $this->check_output_contains_text_input('0_0');
        $this->check_output_does_not_contain_text_input_with_class('0_0', 'correct');
        $this->check_output_does_not_contain_text_input_with_class('0_0', 'partiallycorrect');
        $this->check_output_does_not_contain_text_input_with_class('0_0', 'incorrect');
        $this->check_current_output(
            $this->get_contains_marked_out_of_summary(),
            $this->get_contains_submit_button_expectation(true),
            $this->get_does_not_contain_feedback_expectation()
        );

        // Submit the right answer.
        $this->process_submission(['0_0' => '5', '-submit' => 1]);

        // Verify.
        $this->check_current_state(question_state::$complete);
        $this->check_current_mark(1);
        $this->render();
        $this->check_output_contains_text_input('0_0', '5', true);
        $this->check_output_does_not_contain_stray_placeholders();
        $this->check_current_output(
            $this->get_contains_mark_summary(1),
            $this->get_contains_submit_button_expectation(true),
            $this->get_does_not_contain_validation_error_expectation()
        );

        // Finish the attempt.
        $this->quba->finish_all_questions();

        // Verify.
        $this->check_current_state(question_state::$gradedright);
        $this->check_current_mark(1);
        $this->check_current_output(
            $this->get_contains_mark_summary(1),
            $this->get_contains_correct_expectation(),
            $this->get_does_not_contain_validation_error_expectation()
        );
    }

    public function test_test0_submit_wrong_submit_right(): void {
        // Create the formulas question 'test0'.
        $q = $this->get_test_formulas_question('testsinglenum');
        $this->start_attempt_at_question($q, 'adaptive', 1);

        // Check the initial state.
        $this->check_current_state(question_state::$todo);
        self::assertEquals('adaptivemultipart',
        $this->quba->get_question_attempt($this->slot)->get_behaviour_name());
        $this->render();
        $this->check_output_contains_text_input('0_0');
        $this->check_current_output(
            $this->get_contains_marked_out_of_summary(),
            $this->get_contains_submit_button_expectation(true),
            $this->get_does_not_contain_feedback_expectation()
        );

        // Submit an incorrect answer.
        $this->process_submission(['0_0' => 'dont know', '-submit' => 1]);

        // Verify.
        $this->check_current_state(question_state::$todo);
        $this->check_current_mark(0);
        $this->check_current_output(
            $this->get_contains_mark_summary(0),
            $this->get_contains_submit_button_expectation(true),
            $this->get_does_not_contain_validation_error_expectation()
        );

        // Submit a correct answer.
        $this->process_submission(['0_0' => '5', '-submit' => 1]);

        // Verify.
        $this->check_current_state(question_state::$complete);
        $this->check_current_mark(0.7);
        $this->render();
        $this->check_output_contains_text_input('0_0', '5', true);
        $this->check_output_does_not_contain_stray_placeholders();
    }

    public function test_test0_submit_wrong_unit_then_right(): void {
        // Create and configure a question with an "odd" unit penalty in order to not
        // get the final grade right by chance. Adding two hints to allow a total of
        // three tries.
        $q = $this->get_test_formulas_question('testsinglenumunit');
        $q->parts[0]->unitpenalty = 0.55;

        $this->start_attempt_at_question($q, 'adaptive', 1);

        // Check the initial state.
        $this->check_current_state(question_state::$todo);
        self::assertEquals(
            'adaptivemultipart',
            $this->quba->get_question_attempt($this->slot)->get_behaviour_name()
        );
        $this->render();
        $this->check_output_contains_text_input('0_');
        $this->check_current_output(
            $this->get_contains_marked_out_of_summary(),
            $this->get_contains_submit_button_expectation(true),
        );

        // Submit an answer with a wrong unit.
        $this->process_submission(['0_' => '5 km/s', '-submit' => 1]);
        $this->check_current_state(question_state::$todo);
        $this->check_current_output(
            $this->get_contains_mark_summary(0),
            $this->get_contains_submit_button_expectation(true),
        );

        // Submit an answer with an incompatible unit.
        $this->process_submission(['0_' => '5 kg', '-submit' => 1]);
        $this->check_current_state(question_state::$todo);
        $this->check_current_output(
            $this->get_contains_mark_summary(1 - $q->parts[0]->unitpenalty - $q->penalty),
            $this->get_contains_submit_button_expectation(true),
        );

        // Submit a correct answer.
        $this->process_submission(['0_' => '5 m/s', '-submit' => 1]);
        // The last answer is correct, so the question should move to state "complete".
        $this->check_current_state(question_state::$complete);
        // Check the final grade: wrong, half-right in second try, finally right in third try.
        $this->check_current_mark(1 - 2 * $q->penalty);
    }

    public function test_test0_submit_wrong_unit_then_right_with_hints(): void {
        return;
        // Create and configure a question with an "odd" unit penalty in order to not
        // get the final grade right by chance. Adding two hints to allow a total of
        // three tries.
        $q = $this->get_test_formulas_question('testsinglenumunit');
        $q->parts[0]->unitpenalty = 0.55;
        $q->hints[] = new question_hint_with_parts(12, 'foo', FORMAT_HTML, false, false);
        $q->hints[] = new question_hint_with_parts(13, 'bar', FORMAT_HTML, false, false);

        $this->start_attempt_at_question($q, 'interactive', 1);

        // Check the initial state.
        $this->check_current_state(question_state::$todo);
            self::assertEquals('interactivecountback',
            $this->quba->get_question_attempt($this->slot)->get_behaviour_name()
        );
        $this->render();
        $this->check_output_contains_text_input('0_');
        $this->check_current_output(
            $this->get_contains_marked_out_of_summary(),
            $this->get_contains_submit_button_expectation(true),
        );

        // Submit an answer with a wrong unit.
        $this->process_submission(['0_' => '5 km/s', '-submit' => 1]);
        $this->check_current_state(question_state::$todo);
        $this->check_current_output(
            $this->get_contains_marked_out_of_summary(),
            $this->get_contains_try_again_button_expectation(true),
        );

        // Submit an answer with an incompatible unit.
        $this->process_submission(['-tryagain' => 1]);
        $this->process_submission(['0_' => '5 kg', '-submit' => 1]);
        $this->check_current_state(question_state::$todo);
        $this->check_current_output(
            $this->get_contains_marked_out_of_summary(),
            $this->get_contains_try_again_button_expectation(true),
        );

        // Submit a correct answer.
        $this->process_submission(['-tryagain' => 1]);
        $this->process_submission(['0_' => '5 m/s', '-submit' => 1]);
        // The last answer is correct, so the question should move to state "graded right".
        $this->check_current_state(question_state::$gradedright);
        // Check the final grade: wrong, half-right in second try, finally right in third try.
        $this->check_current_mark(1 - 2 * 0.3);
    }

    public function test_test0_submit_wrong_wrong_right(): void {
        // Here we test that the student is not penalized twice for the same error.
        // Create the formulas question 'test0'.
        $q = $this->get_test_formulas_question('testsinglenum');
        $this->start_attempt_at_question($q, 'adaptive', 1);

        // Check the initial state.
        $this->check_current_state(question_state::$todo);
        self::assertEquals('adaptivemultipart',
        $this->quba->get_question_attempt($this->slot)->get_behaviour_name());
        $this->render();
        $this->check_output_contains_text_input('0_0');
        $this->check_current_output(
            $this->get_contains_marked_out_of_summary(),
            $this->get_contains_submit_button_expectation(true),
            $this->get_does_not_contain_feedback_expectation()
        );

        // Submit an incorrect answer.
        $this->process_submission(['0_0' => 'dont know', '-submit' => 1]);

        // Verify.
        $this->check_current_state(question_state::$todo);
        $this->check_current_mark(0);
        $this->check_current_output(
            $this->get_contains_mark_summary(0),
            $this->get_contains_submit_button_expectation(true),
            $this->get_does_not_contain_validation_error_expectation()
        );

        // Submit another incorrect answer.
        $this->process_submission(['0_0' => 'still dont know', '-submit' => 1]);

        // Verify.
        $this->check_current_state(question_state::$todo);
        $this->check_current_mark(0);
        $this->check_current_output(
            $this->get_contains_mark_summary(0),
            $this->get_contains_submit_button_expectation(true),
            $this->get_does_not_contain_validation_error_expectation()
        );

        // Submit a correct answer.
        $this->process_submission(['0_0' => '5', '-submit' => 1]);

        // Verify.
        $this->check_current_state(question_state::$complete);
        $this->check_current_mark(0.4);
        $this->render();
        $this->check_output_contains_text_input('0_0', '5', true);
        $this->check_output_does_not_contain_stray_placeholders();
    }

    public function test_test0_submit_wrong_same_wrong_right(): void {
        // Here we test that the student is not penalized twice for the same error.
        // Create the formulas question 'test0'.
        $q = $this->get_test_formulas_question('testsinglenum');
        $this->start_attempt_at_question($q, 'adaptive', 1);

        // Check the initial state.
        $this->check_current_state(question_state::$todo);
        self::assertEquals('adaptivemultipart',
        $this->quba->get_question_attempt($this->slot)->get_behaviour_name());
        $this->render();
        $this->check_output_contains_text_input('0_0');
        $this->check_current_output(
            $this->get_contains_marked_out_of_summary(),
            $this->get_contains_submit_button_expectation(true),
            $this->get_does_not_contain_feedback_expectation()
        );

        // Submit an incorrect answer.
        $this->process_submission(['0_0' => 'dont know', '-submit' => 1]);

        // Verify.
        $this->check_current_state(question_state::$todo);
        $this->check_current_mark(0);
        $this->check_current_output(
            $this->get_contains_mark_summary(0),
            $this->get_contains_submit_button_expectation(true),
            $this->get_does_not_contain_validation_error_expectation()
        );

        // Submit again the same incorrect answer.
        $this->process_submission(['0_0' => 'dont know', '-submit' => 1]);

        // Verify.
        $this->check_current_state(question_state::$todo);
        $this->check_current_mark(0);
        $this->check_current_output(
            $this->get_contains_mark_summary(0),
            $this->get_contains_submit_button_expectation(true),
            $this->get_does_not_contain_validation_error_expectation()
        );

        // Submit a correct answer.
        $this->process_submission(['0_0' => '5', '-submit' => 1]);

        // Verify.
        $this->check_current_state(question_state::$complete);
        $this->check_current_mark(0.7);
        $this->render();
        $this->check_output_contains_text_input('0_0', '5', true);
        $this->check_output_does_not_contain_stray_placeholders();
    }

    public function test_student_using_overwritten_function(): void {
        // Create a question and tweak it a bit, by overwriting the sin() function in the global vars.
        $q = $this->get_test_formulas_question('testsinglenum');
        $q->varsglobal = 'sin = 3';
        $q->parts[0]->answertype = qtype_formulas::ANSWER_TYPE_NUMERICAL_FORMULA;
        $q->parts[0]->answer = '\sin(20)';

        // Start an attempt and submit the student answer "sin(20)". It should be incorrect,
        // because for the student, sin is no longer a function, but evaluates to 3.
        $this->start_attempt_at_question($q, 'immediatefeedback', 1);
        $this->check_current_state(question_state::$todo);
        $this->process_submission(['0_0' => 'sin(20)', '-submit' => 1]);
        $this->check_current_mark(0);
        $this->start_attempt_at_question($q, 'immediatefeedback', 1);
        $this->process_submission(['0_0' => '0.91294525072763', '-submit' => 1]);
        $this->check_current_mark(1);

        // Change the model answer by removing the prefix. The correct answer should
        // therefore be 60 now.
        $q = $this->get_test_formulas_question('testsinglenum');
        $q->varsglobal = 'sin = 3';
        $q->parts[0]->answer = 'sin(20)';
        $q->parts[0]->answertype = qtype_formulas::ANSWER_TYPE_NUMERICAL_FORMULA;
        $this->start_attempt_at_question($q, 'immediatefeedback', 1);
        $this->check_current_state(question_state::$todo);
        $this->process_submission(['0_0' => '60', '-submit' => 1]);
        $this->check_current_mark(1);
        $this->start_attempt_at_question($q, 'immediatefeedback', 1);
        $this->process_submission(['0_0' => '0.91294525072763', '-submit' => 1]);
        $this->check_current_mark(0);
        $this->start_attempt_at_question($q, 'immediatefeedback', 1);
        // The following must be wrong, because the student is not allowed to use the "variable"
        // sin in their response.
        $this->process_submission(['0_0' => 'sin(20)', '-submit' => 1]);
        $this->check_current_mark(0);

        // Change the question to algebraic formula.
        $q = $this->get_test_formulas_question('testsinglenum');
        $q->varsglobal = 'sin = 3; x = {-5:5}';
        $q->parts[0]->answertype = qtype_formulas::ANSWER_TYPE_ALGEBRAIC;
        $q->parts[0]->correctness = '_err < 0.01';
        $q->parts[0]->answer = '"3x"';

        // Start an attempt and submit the student answer "sin(x)". It should be wrong,
        // because students are not allowed to use variables with function names.
        $this->start_attempt_at_question($q, 'immediatefeedback', 1);
        $this->check_current_state(question_state::$todo);
        $this->process_submission(['0_0' => 'sin(x)', '-submit' => 1]);
        $this->check_current_mark(0);

        $q = $this->get_test_formulas_question('testsinglenum');
        $q->varsglobal = 'sin = 3; x = {-5:5}';
        $q->parts[0]->answertype = qtype_formulas::ANSWER_TYPE_ALGEBRAIC;
        $q->parts[0]->correctness = '_err < 0.01';
        // Using the PREFIX operator in the model answer is not possible via the edit form,
        // but for the unit test, the usual checks are bypassed.
        $q->parts[0]->answer = '"\sin(x)"';

        // The student's answer must now be wrong, because the teacher used the sine function,
        // where as the student only has access to the variable "sin".
        $this->start_attempt_at_question($q, 'immediatefeedback', 1);
        $this->process_submission(['0_0' => 'sin(x)', '-submit' => 1]);
        $this->check_current_mark(0);

        // Now the student should get full mark, because their response is equivalent to
        // the teacher's model answer, at least for the evaluation points given in this example.
        $this->start_attempt_at_question($q, 'immediatefeedback', 1);
        $this->process_submission(['0_0' => 'tan(x)*cos(x)', '-submit' => 1]);
        $this->check_current_mark(1);

        // The student is not allowed to use the PREFIX operator, so their answer must be wrong now.
        $this->start_attempt_at_question($q, 'immediatefeedback', 1);
        $this->process_submission(['0_0' => '\sin(x)', '-submit' => 1]);
        $this->check_current_mark(0);
    }

    public function test_deferred_partially_answered_with_empty_allowed(): void {
        // Create a question with one empty field.
        $q = $this->get_test_formulas_question('testnumandempty');
        $this->start_attempt_at_question($q, 'deferredfeedback', 2);

        // Check the initial state.
        $this->check_current_state(question_state::$todo);
        $this->check_current_mark(null);
        $this->render();
        $this->check_output_contains_text_input('0_0');
        $this->check_output_contains_text_input('0_1');
        $this->check_current_output(
            $this->get_contains_marked_out_of_summary(),
            $this->get_does_not_contain_submit_button_expectation(),
            $this->get_does_not_contain_feedback_expectation(),
        );

        // Submit the empty form. The question should be counted as "gave up" with no grade.
        // The feedback should be "incorrect".
        $this->finish();
        $this->check_current_state(question_state::$gaveup);
        $this->check_current_mark(null);
        $this->render();
        $this->check_output_contains_text_input('0_0', '', false);
        $this->check_output_contains_text_input('0_1', '', false);

        // Submit a partial answer, filling only the first field, but wrong. The question should be
        // graded wrong due to the grading criterion. The student's response should be shown in the
        // text field. All text fields should be disabled.
        $this->start_attempt_at_question($q, 'deferredfeedback', 2);
        $this->check_current_state(question_state::$todo);
        $this->process_submission(['0_0' => '5', '0_1' => '']);
        $this->finish();
        $this->check_current_state(question_state::$gradedwrong);
        $this->check_current_mark(0);
        $this->render();
        $this->check_output_contains_text_input('0_0', '5', false);
        $this->check_output_contains_text_input('0_1', '', false);
        $this->check_current_output(
            $this->get_contains_mark_summary(0),
            $this->get_contains_incorrect_expectation(),
        );

        // Submit an answer, filling only the first field, correct. The question should be
        // graded correct. The student's response should be shown in the text field.
        // All text fields should be disabled.
        $this->start_attempt_at_question($q, 'deferredfeedback', 2);
        $this->check_current_state(question_state::$todo);
        $this->process_submission(['0_0' => '1', '0_1' => '']);
        $this->finish();
        $this->check_current_state(question_state::$gradedright);
        $this->check_current_mark(2);
        $this->render();
        $this->check_output_contains_text_input('0_0', '1', false);
        $this->check_output_contains_text_input('0_1', '', false);
        $this->check_current_output(
            $this->get_contains_mark_summary(2),
            $this->get_contains_correct_expectation(),
        );

        // Submit an answer, filling only the second field, obviously wrong. The question should
        // be graded wrong. The student's response should be shown in the text field.
        // All text fields should be disabled.
        $this->start_attempt_at_question($q, 'deferredfeedback', 2);
        $this->check_current_state(question_state::$todo);
        $this->process_submission(['0_0' => '', '0_1' => '1']);
        $this->finish();
        $this->check_current_state(question_state::$gradedwrong);
        $this->check_current_mark(0);
        $this->render();
        $this->check_output_contains_text_input('0_0', '', false);
        $this->check_output_contains_text_input('0_1', '1', false);
        $this->check_current_output(
            $this->get_contains_mark_summary(0),
            $this->get_contains_incorrect_expectation(),
        );

        // Submit an answer, filling both fields. The question should be graded wrong.
        // The student's response should be shown in the text field.
        // All text fields should be disabled.
        $this->start_attempt_at_question($q, 'deferredfeedback', 2);
        $this->check_current_state(question_state::$todo);
        $this->process_submission(['0_0' => '1', '0_1' => '2']);
        $this->finish();
        $this->check_current_state(question_state::$gradedwrong);
        $this->check_current_mark(0);
        $this->render();
        $this->check_output_contains_text_input('0_0', '1', false);
        $this->check_output_contains_text_input('0_1', '2', false);
        $this->check_current_output(
            $this->get_contains_mark_summary(0),
            $this->get_contains_incorrect_expectation(),
        );
    }
}
