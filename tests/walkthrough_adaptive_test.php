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
 * Unit tests for the formulas question type.
 *
 * @package    qtype_formulas
 * @copyright  2012 Jean-Michel Védrine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once(dirname(__FILE__) . '/../../../engine/lib.php');
require_once($CFG->dirroot . '/question/engine/tests/helpers.php');
require_once(dirname(__FILE__) . '/test_base.php');
require_once($CFG->dirroot . '/question/type/formulas/tests/helper.php');


/**
 * Unit tests for the formulas question type.
 *
 * @copyright  2012 Jean-Michel Vedrine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class qtype_formulas_walkthrough_adaptive_test extends qtype_formulas_walkthrough_test_base {
    /**
     * @return qtype_formulas_question the requested question object.
     */
    protected function get_test_formulas_question($which = null) {
        return test_question_maker::make_question('formulas', $which);
    }

    public function test_test0_submit_right_first_time() {
        // Create the formulas question 'test0'.
        $q = $this->get_test_formulas_question('test0');
        $this->start_attempt_at_question($q, 'adaptive', 1);

        // Check the initial state.
        $this->check_current_state(question_state::$todo);
        $this->check_current_mark(null);
        $this->assertEquals('adaptivemultipart',
                $this->quba->get_question_attempt($this->slot)->get_behaviour_name());
        $this->render();
        $this->check_output_contains_text_input('0_0');
        $this->check_output_does_not_contain_text_input_with_class('0_0', 'correct');
        $this->check_output_does_not_contain_text_input_with_class('0_0', 'partiallycorrect');
        $this->check_output_does_not_contain_text_input_with_class('0_0', 'incorrect');
        $this->check_current_output(
                $this->get_contains_marked_out_of_summary(),
                $this->get_contains_submit_button_expectation(true),
                $this->get_does_not_contain_feedback_expectation());

        // Submit the right answer.
        $this->process_submission(array('0_0' => '5', '-submit' => 1));

        // Verify.
        $this->check_current_state(question_state::$complete);
        $this->check_current_mark(1);
        $this->render();
        $this->check_output_contains_text_input('0_0', '5', true);
        $this->check_output_does_not_contain_stray_placeholders();
        $this->check_current_output(
                $this->get_contains_mark_summary(1),
                $this->get_contains_submit_button_expectation(true),
                $this->get_does_not_contain_validation_error_expectation());

        // Finish the attempt.
        $this->quba->finish_all_questions();

        // Verify.
        $this->check_current_state(question_state::$gradedright);
        $this->check_current_mark(1);
        $this->check_current_output(
                $this->get_contains_mark_summary(1),
                $this->get_contains_correct_expectation(),
                $this->get_does_not_contain_validation_error_expectation());
    }

    public function test_test0_submit_wrong_submit_right() {
        // Create the formulas question 'test0'.
        $q = $this->get_test_formulas_question('test0');
        $this->start_attempt_at_question($q, 'adaptive', 1);

        // Check the initial state.
        $this->check_current_state(question_state::$todo);
        $this->assertEquals('adaptivemultipart',
                $this->quba->get_question_attempt($this->slot)->get_behaviour_name());
        $this->render();
        $this->check_output_contains_text_input('0_0');
        $this->check_current_output(
                $this->get_contains_marked_out_of_summary(),
                $this->get_contains_submit_button_expectation(true),
                $this->get_does_not_contain_feedback_expectation());

        // Submit an incorrect answer.
        $this->process_submission(array('0_0' => 'dont know', '-submit' => 1));

        // Verify.
        $this->check_current_state(question_state::$todo);
        $this->check_current_mark(0);
        $this->check_current_output(
                $this->get_contains_mark_summary(0),
                $this->get_contains_submit_button_expectation(true),
                $this->get_does_not_contain_validation_error_expectation());

        // Submit a correct answer.
        $this->process_submission(array('0_0' => '5', '-submit' => 1));

        // Verify.
        $this->check_current_state(question_state::$complete);
        $this->check_current_mark(0.7);
        $this->render();
        $this->check_output_contains_text_input('0_0', '5', true);
        $this->check_output_does_not_contain_stray_placeholders();
    }

    public function test_test0_submit_wrong_wrong_right() {
        // Here we test that the student is not penalized twice for the same error.
        // Create the formulas question 'test0'.
        $q = $this->get_test_formulas_question('test0');
        $this->start_attempt_at_question($q, 'adaptive', 1);

        // Check the initial state.
        $this->check_current_state(question_state::$todo);
        $this->assertEquals('adaptivemultipart',
                $this->quba->get_question_attempt($this->slot)->get_behaviour_name());
        $this->render();
        $this->check_output_contains_text_input('0_0');
        $this->check_current_output(
                $this->get_contains_marked_out_of_summary(),
                $this->get_contains_submit_button_expectation(true),
                $this->get_does_not_contain_feedback_expectation());

        // Submit an incorrect answer.
        $this->process_submission(array('0_0' => 'dont know', '-submit' => 1));

        // Verify.
        $this->check_current_state(question_state::$todo);
        $this->check_current_mark(0);
        $this->check_current_output(
                $this->get_contains_mark_summary(0),
                $this->get_contains_submit_button_expectation(true),
                $this->get_does_not_contain_validation_error_expectation());

        // Submit another incorrect answer.
        $this->process_submission(array('0_0' => 'still dont know', '-submit' => 1));

        // Verify.
        $this->check_current_state(question_state::$todo);
        $this->check_current_mark(0);
        $this->check_current_output(
                $this->get_contains_mark_summary(0),
                $this->get_contains_submit_button_expectation(true),
                $this->get_does_not_contain_validation_error_expectation());

        // Submit a correct answer.
        $this->process_submission(array('0_0' => '5', '-submit' => 1));

        // Verify.
        $this->check_current_state(question_state::$complete);
        $this->check_current_mark(0.4);
        $this->render();
        $this->check_output_contains_text_input('0_0', '5', true);
        $this->check_output_does_not_contain_stray_placeholders();
    }

    public function test_test0_submit_wrong_same_wrong_right() {
        // Here we test that the student is not penalized twice for the same error.
        // Create the formulas question 'test0'.
        $q = $this->get_test_formulas_question('test0');
        $this->start_attempt_at_question($q, 'adaptive', 1);

        // Check the initial state.
        $this->check_current_state(question_state::$todo);
        $this->assertEquals('adaptivemultipart',
                $this->quba->get_question_attempt($this->slot)->get_behaviour_name());
        $this->render();
        $this->check_output_contains_text_input('0_0');
        $this->check_current_output(
                $this->get_contains_marked_out_of_summary(),
                $this->get_contains_submit_button_expectation(true),
                $this->get_does_not_contain_feedback_expectation());

        // Submit an incorrect answer.
        $this->process_submission(array('0_0' => 'dont know', '-submit' => 1));

        // Verify.
        $this->check_current_state(question_state::$todo);
        $this->check_current_mark(0);
        $this->check_current_output(
                $this->get_contains_mark_summary(0),
                $this->get_contains_submit_button_expectation(true),
                $this->get_does_not_contain_validation_error_expectation());

        // Submit again the same incorrect answer.
        $this->process_submission(array('0_0' => 'dont know', '-submit' => 1));

        // Verify.
        $this->check_current_state(question_state::$todo);
        $this->check_current_mark(0);
        $this->check_current_output(
                $this->get_contains_mark_summary(0),
                $this->get_contains_submit_button_expectation(true),
                $this->get_does_not_contain_validation_error_expectation());

        // Submit a correct answer.
        $this->process_submission(array('0_0' => '5', '-submit' => 1));

        // Verify.
        $this->check_current_state(question_state::$complete);
        $this->check_current_mark(0.7);
        $this->render();
        $this->check_output_contains_text_input('0_0', '5', true);
        $this->check_output_does_not_contain_stray_placeholders();
    }
}