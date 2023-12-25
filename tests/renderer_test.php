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
 * Unit tests for the formulas question type renderer.
 *
 * @package    qtype_formulas
 * @copyright  2023 Philipp Imhof
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace qtype_formulas;

use question_state;
use test_question_maker;
use question_hint_with_parts;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once(dirname(__FILE__) . '/../../../engine/lib.php');
require_once($CFG->dirroot . '/question/engine/tests/helpers.php');
require_once(dirname(__FILE__) . '/test_base.php');
require_once($CFG->dirroot . '/question/type/formulas/tests/helper.php');


/**
 * Unit tests for the formulas question type.
 *
 * @copyright  2023 Jean-Michel Vedrine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class renderer_test extends walkthrough_test_base {
    /**
     * @return qtype_formulas_question the requested question object.
     */
    protected function get_test_formulas_question($which = null) {
        return test_question_maker::make_question('formulas', $which);
    }

    public function test_answer_not_unique() {
        // Create a basic question. By default, the answer is not unique.
        $q = $this->get_test_formulas_question('testsinglenum');
        $this->start_attempt_at_question($q, 'immediatefeedback', 1);

        // Submit wrong answer and check for 'One possible correct answer is: 5'.
        $this->process_submission(array('0_0' => '0', '-submit' => 1));
        $this->check_output_contains_lang_string('correctansweris', 'qtype_formulas', '5');

        // Set to unique answer and restart.
        $q->parts[0]->answernotunique = '0';
        $this->start_attempt_at_question($q, 'immediatefeedback', 1);

        // Submit wrong answer and check for 'The correct answer is: 5'.
        $this->process_submission(array('0_0' => '0', '-submit' => 1));
        $this->check_output_contains_lang_string('uniquecorrectansweris', 'qtype_formulas', '5');
    }

    public function test_render_question_with_combined_unit_field() {
        $q = $this->get_test_formulas_question('testsinglenumunit');
        $q->parts[0]->unitpenalty = 0.5;
        $this->start_attempt_at_question($q, 'immediatefeedback', 1);

        $this->render();
        $this->check_output_contains_text_input('0_', '', true);

        // Submit wrong answer.
        $this->process_submission(['0_' => '5', '-submit' => 1]);
        $this->check_current_state(question_state::$gradedpartial);
        $this->check_current_mark(0.5);
        $this->check_current_output(
                $this->get_contains_mark_summary(0.5),
                $this->get_contains_num_parts_correct(0)
        );
        $this->render();
        $this->check_output_contains_text_input('0_', '5', false);

        // Submit right answer.
        $this->start_attempt_at_question($q, 'immediatefeedback', 1);
        $this->process_submission(['0_' => '5 m/s', '-submit' => 1]);
        $this->check_current_state(question_state::$gradedright);
        $this->check_current_mark(1);
        $this->check_current_output(
                $this->get_contains_mark_summary(1),
        );
        $this->render();
        $this->check_output_contains_text_input('0_', '5 m/s', false);
    }

    public function test_render_question_with_separate_unit_field() {
        $q = $this->get_test_formulas_question('testsinglenumunitsep');
        $q->parts[0]->unitpenalty = 0.5;
        $this->start_attempt_at_question($q, 'immediatefeedback', 1);

        $this->render();
        $this->check_output_contains_text_input('0_0', '', true);
        $this->check_output_contains_text_input('0_1', '', true);

        // Submit incomplete answer.
        $this->process_submission(['0_0' => '5', '0_1' => '', '-submit' => 1]);
        $this->check_current_state(question_state::$invalid);
        $this->check_current_mark(null);
        $this->check_current_output(
                $this->get_contains_marked_out_of_summary(),
                $this->get_contains_validation_error_expectation()
        );
        $this->render();
        $this->check_output_contains_text_input('0_0', '5', true);
        $this->check_output_contains_text_input('0_1', '', true);

        // Submit wrong answer.
        $this->process_submission(['0_0' => '5', '0_1' => 'kg', '-submit' => 1]);
        $this->check_current_state(question_state::$gradedpartial);
        $this->check_current_mark(0.5);
        $this->check_current_output(
                $this->get_contains_mark_summary(0.5),
                $this->get_contains_num_parts_correct(0)
        );
        $this->render();
        $this->check_output_contains_text_input('0_0', '5', false);
        $this->check_output_contains_text_input('0_1', 'kg', false);

        // Submit right answer.
        $this->start_attempt_at_question($q, 'immediatefeedback', 1);
        $this->process_submission(['0_0' => '5', '0_1' => 'm/s', '-submit' => 1]);
        $this->check_current_state(question_state::$gradedright);
        $this->check_current_mark(1);
        $this->check_current_output(
                $this->get_contains_mark_summary(1),
        );
        $this->render();
        $this->check_output_contains_text_input('0_0', '5', false);
        $this->check_output_contains_text_input('0_1', 'm/s', false);
    }

    public function test_render_question_with_multiple_parts() {
        $q = $this->get_test_formulas_question('testmethodsinparts');
        $this->start_attempt_at_question($q, 'immediatefeedback', 8);

        $this->render();
        $this->check_output_contains_text_input('0_', '', true);
        $this->check_output_contains_text_input('1_0', '', true);
        $this->check_output_contains_text_input('1_1', '', true);
        $this->check_output_contains_text_input('2_0', '', true);
        $this->check_output_does_not_contain_text_input_with_class('2_1');
        $this->check_output_contains_text_input('3_0', '', true);
        $this->check_output_does_not_contain_text_input_with_class('3_1');

        // Submit partial answer.
        $this->process_submission(['0_' => '40 m/s', '1_0' => '40', '1_1' => 'm/s', '-submit' => 1]);
        $this->check_current_state(question_state::$invalid);
        $this->check_current_mark(null);
        $this->check_current_output(
                $this->get_contains_marked_out_of_summary(),
        );
        $this->render();
        $this->check_output_contains_text_input('0_', '40 m/s', true);
        $this->check_output_contains_text_input('1_0', '40', true);
        $this->check_output_contains_text_input('1_1', 'm/s', true);

        // Submit partially correct answer.
        $this->process_submission(['0_' => '40 m/s', '1_0' => '40', '1_1' => 'm/s', '2_0' => '20', '3_0' => '40', '-submit' => 1]);
        $this->check_current_state(question_state::$gradedpartial);
        $this->check_current_mark(6);
        $this->check_current_output(
                $this->get_contains_mark_summary(6),
                $this->get_contains_num_parts_correct(3)
        );
    }

    public function test_render_mc_question() {
        // Create a single part multiple choice (radio) question.
        $q = $this->get_test_formulas_question('testmc');
        $this->start_attempt_at_question($q, 'adaptive', 1);

        $this->render();
        $this->check_current_output(
                $this->get_contains_radio_expectation(['name' => $this->quba->get_field_prefix($this->slot) . '0_0'], true, false),
                $this->get_does_not_contain_specific_feedback_expectation()
        );

        // Submit wrong answer.
        $this->process_submission(array('0_0' => '0', '-submit' => 1));

        $this->check_current_state(question_state::$todo);
        $this->check_current_output(
                $this->get_contains_radio_expectation(['name' => $this->quba->get_field_prefix($this->slot) . '0_0'], true, true),
                $this->get_contains_num_parts_correct(0)
        );
        $this->check_current_mark(0);

        // Submit right answer.
        $this->process_submission(array('0_0' => '1', '-submit' => 1));
        $this->check_current_state(question_state::$complete);
        $this->check_current_output(
                $this->get_contains_radio_expectation(['name' => $this->quba->get_field_prefix($this->slot) . '0_0'], true, true),
                $this->get_contains_num_parts_correct(1)
        );
        $this->check_current_mark(0.7);
    }

    public function test_render_mce_question() {
        // Create a single part multiple choice (radio) question.
        $q = $this->get_test_formulas_question('testmce');
        $this->start_attempt_at_question($q, 'immediatefeedback', 1);

        $this->render();
        $this->check_output_contains_selectoptions(
                $this->get_contains_select_expectation('0_0', ['Dog', 'Cat', 'Bird', 'Fish'])
        );
        $this->check_current_output(
                $this->get_does_not_contain_specific_feedback_expectation()
        );

        // Submit wrong answer.
        $this->process_submission(array('0_0' => '0', '-submit' => 1));
        $this->check_current_state(question_state::$gradedwrong);
        $this->check_output_contains_selectoptions(
                $this->get_contains_select_expectation('0_0', ['Dog', 'Cat', 'Bird', 'Fish'], 0)
        );
        $this->check_current_mark(0);
        $this->check_output_contains_lang_string('correctansweris', 'qtype_formulas', 'Cat');

        // Restart question and submit right answer.
        $this->start_attempt_at_question($q, 'immediatefeedback', 1);
        $this->process_submission(array('0_0' => '1', '-submit' => 1));
        $this->check_current_state(question_state::$gradedright);
        $this->check_output_contains_selectoptions(
                $this->get_contains_select_expectation('0_0', ['Dog', 'Cat', 'Bird', 'Fish'], 1)
        );
        $this->check_current_mark(1);
        $this->check_output_contains_lang_string('correctansweris', 'qtype_formulas', 'Cat');
    }

    public function test_question_with_hint(): void {
        // Create a single part multiple choice (radio) question.
        $q = $this->get_test_formulas_question('testsinglenum');
        $q->varsglobal = 'a = 555; b = "foo";';
        $q->hints = [
                new question_hint_with_parts(0, 'Hint 1. {a} {b}', FORMAT_HTML, false, true),
                new question_hint_with_parts(0, 'Hint 2.', FORMAT_HTML, false, false),
        ];

        $this->start_attempt_at_question($q, 'interactive', 1);

        $this->render();
        $this->check_output_contains_text_input('0_0', '', true);
        $this->check_current_output(
                $this->get_no_hint_visible_expectation()
        );

        // Submit first wrong answer.
        $this->process_submission(['0_0' => '999', '-submit' => 1]);
        $this->check_current_state(question_state::$todo);
        $this->check_current_output(
                $this->get_contains_hint_expectation('Hint 1. 555 foo'),
                $this->get_tries_remaining_expectation(2),
                $this->get_contains_try_again_button_expectation(),
        );
        $this->render();
        $this->check_output_contains_text_input('0_0', '999', false);
        // The first hint is set to "clear wrong answers", so there should now be a hidden
        // field overriding the input from the text box.
        $this->check_output_contains_hidden_input('0_0', '');

        $this->process_submission(['0_0' => '', '-tryagain' => 1]);
        $this->render();
        $this->check_output_contains_text_input('0_0', '', true);

        // Submit second wrong answer.
        $this->process_submission(['0_0' => '123', '-submit' => 1]);
        $this->check_current_state(question_state::$todo);
        $this->check_current_output(
                $this->get_contains_hint_expectation('Hint 2.'),
                $this->get_tries_remaining_expectation(1),
                $this->get_contains_try_again_button_expectation(),
                // The second hint is NOT set to "clear wrong answers", so there should be no
                // hidden field in this case.
                $this->get_does_not_contain_hidden_expectation('0_0'),
        );
        $this->render();
        $this->check_output_contains_text_input('0_0', '123', false);
    }
}
