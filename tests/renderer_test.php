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

use question_hint_with_parts;
use question_state;
use test_question_maker;
use qtype_formulas;
use qtype_formulas_test_helper;
use Generator;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/question/engine/lib.php');
require_once($CFG->dirroot . '/question/engine/tests/helpers.php');
require_once($CFG->dirroot . '/question/type/formulas/tests/test_base.php');
require_once($CFG->dirroot . '/question/type/formulas/tests/helper.php');

/**
 * Unit tests for the formulas question type.
 *
 * @package    qtype_formulas
 * @copyright  2024 Philipp Imhof
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @covers     \qtype_formulas_renderer
 * @covers     \qtype_formulas_question
 * @covers     \qtype_formulas_part
 * @covers     \qtype_formulas
 */
final class renderer_test extends walkthrough_test_base {
    /**
     * Create a question object of a certain type, as defined in the helper.php file.
     *
     * @param string|null $which the test question name
     * @return qtype_formulas_question
     */
    protected function get_test_formulas_question($which = null) {
        return test_question_maker::make_question('formulas', $which);
    }

    public function test_usage_of_local_and_grading_vars_in_feedback(): void {
        // Create a basic question, setting global, local and grading vars, as well as
        // a custom feedback.
        $q = $this->get_test_formulas_question('testsinglenumunit');
        $q->varsglobal = 'x = 1; y = 9;';
        $q->parts[0]->vars1 = 'y = 2;';
        $q->parts[0]->vars2 = 'z = _0';
        $q->parts[0]->feedback = 'general {x} -- {y} -- {z}';
        $q->parts[0]->partincorrectfb = 'incorrect {z} -- {y} -- {x}';
        $q->parts[0]->partcorrectfb = 'correct {z} -- {y} -- {x}';
        $q->parts[0]->partpartiallycorrectfb = 'partially correct {z} -- {y} -- {x}';
        $q->parts[0]->unitpenalty = 0.5;

        // Start attempt and submit wrong answer.
        $this->start_attempt_at_question($q, 'immediatefeedback', 1);
        $this->process_submission(['0_' => '99', '-submit' => 1]);
        $this->check_output_contains('general 1 -- 2 -- 99');
        $this->check_output_contains('incorrect 99 -- 2 -- 1');

        // Start attempt and submit correct answer.
        $this->start_attempt_at_question($q, 'immediatefeedback', 1);
        $this->process_submission(['0_' => '5 m/s', '-submit' => 1]);
        $this->check_output_contains('general 1 -- 2 -- 5');
        $this->check_output_contains('correct 5 -- 2 -- 1');

        // Start attempt and submit partially correct answer.
        $this->start_attempt_at_question($q, 'immediatefeedback', 1);
        $this->process_submission(['0_' => '5 m', '-submit' => 1]);
        $this->check_output_contains('general 1 -- 2 -- 5');
        $this->check_output_contains('partially correct 5 -- 2 -- 1');
    }

    public function test_answer_not_unique(): void {
        // Create a basic question. By default, the answer is not unique.
        $q = $this->get_test_formulas_question('testsinglenum');
        $this->start_attempt_at_question($q, 'immediatefeedback', 1);

        // Submit wrong answer and check for 'One possible correct answer is: 5'.
        $this->process_submission(['0_0' => '0', '-submit' => 1]);
        $this->check_output_contains_lang_string('correctansweris', 'qtype_formulas', '5');

        // Set to unique answer and restart.
        $q->parts[0]->answernotunique = '0';
        $this->start_attempt_at_question($q, 'immediatefeedback', 1);

        // Submit wrong answer and check for 'The correct answer is: 5'.
        $this->process_submission(['0_0' => '0', '-submit' => 1]);
        $this->check_output_contains_lang_string('uniquecorrectansweris', 'qtype_formulas', '5');
    }

    public function test_show_response_with_backslash(): void {
        $q = $this->get_test_formulas_question('testsinglenum');
        $this->start_attempt_at_question($q, 'immediatefeedback', 1);

        $this->render();
        $this->check_current_state(question_state::$todo);
        $this->check_output_contains_text_input('0_0', '', true);

        // Submit wrong answer. The \1 must not be gobbled.
        $this->process_submission(['0_0' => '\1', '-submit' => 1]);
        $this->render();
        $this->check_current_state(question_state::$gradedwrong);
        $this->check_output_contains(qtype_formulas_test_helper::DEFAULT_INCORRECT_FEEDBACK);
        $this->check_output_contains_text_input('0_0', '\1', false);
    }

    public function test_adaptive_grading_details_are_shown(): void {
        // Create a simple test question with no feedback strings at all.
        $q = $this->get_test_formulas_question('testsinglenum');
        $q->parts[0]->feedback = '';
        $q->parts[0]->partcorrectfb = '';
        $q->parts[0]->partpartiallycorrectfb = '';
        $q->parts[0]->partincorrectfb = '';

        // Initially, there should be no feedback.
        $this->start_attempt_at_question($q, 'adaptive', 1);
        $this->render();
        $this->check_current_output(
            $this->get_does_not_contain_div_with_class_expectation('formulaspartfeedback-0'),
            $this->get_does_not_contain_div_with_class_expectation('formulaslocalfeedback'),
            $this->get_does_not_contain_div_with_class_expectation('gradingdetails'),
        );

        // After submitting an answer, the grading details should be shown, but there
        // should be no empty <div> for the part's (inexistent) feedback.
        $this->process_submission(['0_0' => '0', '-submit' => 1]);
        $this->render();
        $this->check_current_output(
            $this->get_does_not_contain_div_with_class_expectation('formulaslocalfeedback'),
            $this->get_contains_div_with_class_expectation('gradingdetails'),
        );
        $this->check_output_contains('Marks for this submission');
        $this->check_output_contains('This submission attracted a penalty of');

        // If there is a general feedback, it should be there now.
        $q->parts[0]->feedback = 'foo bar feedback';
        $this->start_attempt_at_question($q, 'adaptive', 1);
        $this->process_submission(['0_0' => '0', '-submit' => 1]);
        $this->render();
        $this->check_current_output(
            $this->get_contains_div_with_class_expectation('formulaslocalfeedback'),
            $this->get_contains_div_with_class_expectation('gradingdetails'),
        );
        $this->check_output_contains('foo bar feedback');
        $this->check_output_contains('Marks for this submission');
        $this->check_output_contains('This submission attracted a penalty of');
    }

    public function test_render_question_with_combined_unit_field(): void {
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

    public function test_render_question_with_algebraic_answer(): void {
        $q = $this->get_test_formulas_question('testalgebraic');
        $this->start_attempt_at_question($q, 'immediatefeedback', 1);

        $this->render();
        $this->check_output_contains_text_input('0_0', '', true);

        // Submit wrong answer.
        $this->process_submission(['0_0' => '5', '-submit' => 1]);
        $this->check_current_state(question_state::$gradedwrong);
        $this->check_current_mark(0);
        $this->check_current_output(
                $this->get_contains_mark_summary(0),
        );
        $this->render();
        $this->check_output_contains_text_input('0_0', '5', false);
        $this->check_output_contains_lang_string('correctansweris', 'qtype_formulas', '5 * x^2');
        $this->check_output_does_not_contain('a*x^2');

        // Submit right answer.
        $this->start_attempt_at_question($q, 'immediatefeedback', 1);
        $this->process_submission(['0_0' => '5*x^2', '-submit' => 1]);
        $this->check_current_state(question_state::$gradedright);
        $this->check_current_mark(1);
        $this->check_current_output(
                $this->get_contains_mark_summary(1),
        );
        $this->render();
        $this->check_output_contains_text_input('0_0', '5*x^2', false);

        // Submit different right answer.
        $this->start_attempt_at_question($q, 'immediatefeedback', 1);
        $this->process_submission(['0_0' => '5*x*x', '-submit' => 1]);
        $this->check_current_state(question_state::$gradedright);
        $this->check_current_mark(1);
        $this->check_current_output(
                $this->get_contains_mark_summary(1),
        );
        $this->render();
        $this->check_output_contains_text_input('0_0', '5*x*x', false);

        // Test with the correct solution being stored in a string variable.
        $q = $this->get_test_formulas_question('testalgebraic');
        $q->varsglobal .= 'sol = "5*x^2"';
        $this->start_attempt_at_question($q, 'immediatefeedback', 1);

        $this->process_submission(['0_0' => 'sol', '-submit' => 1]);
        $this->check_current_state(question_state::$gradedwrong);
        $this->check_current_mark(0);
        $this->check_current_output(
                $this->get_contains_mark_summary(0),
        );
        $this->render();
        $this->check_output_contains_text_input('0_0', 'sol', false);

        // Test with the unit: there should not be a combined unit field.
        $q = $this->get_test_formulas_question('testalgebraic');
        $q->parts[0]->postunit = 'm';
        $this->start_attempt_at_question($q, 'immediatefeedback', 1);
        $this->render();
        $this->check_output_contains_text_input('0_0', '', true);
        $this->check_output_contains_text_input('0_1', '', true);
        $this->check_output_does_not_contain_text_input_with_class('0_');
    }

    public function test_render_question_with_separate_unit_field(): void {
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

    public function test_render_question_with_multiple_parts(): void {
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
        // phpcs:ignore Universal.Arrays.DuplicateArrayKey.Found
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
        // phpcs:ignore Universal.Arrays.DuplicateArrayKey.Found
        $this->process_submission(['0_' => '40 m/s', '1_0' => '40', '1_1' => 'm/s', '2_0' => '20', '3_0' => '40', '-submit' => 1]);
        $this->check_current_state(question_state::$gradedpartial);
        $this->check_current_mark(6);
        $this->check_current_output(
                $this->get_contains_mark_summary(6),
                $this->get_contains_num_parts_correct(3)
        );
    }

    public function test_render_shuffled_mc(): void {
        // Create a single part multiple choice (radio) question. Activate shuffling of the options
        // and disable numbering of the answers, as that makes it easier to test.
        $q = $this->get_test_formulas_question('testmc');
        $q->answernumbering = '';
        $q->parts[0]->subqtext = '{_0:mychoices:MCS}';

        $this->start_attempt_at_question($q, 'immediatefeedback', 1);

        // Count how many times each option appears first. Re-render the question until all options have been
        // on the first position at least once. In case something is off, stop after 100 tries at latest.
        $countfirst = ['Dog' => 0, 'Cat' => 0, 'Bird' => 0, 'Fish' => 0];
        $allwerefirst = false;
        $safety = 0;
        while (!$allwerefirst && $safety < 100) {
            $this->render();
            $fieldset = preg_replace('=^(.*)<fieldset[^>]+>(.+)</fieldset>(.*)$=', '\\2', $this->currentoutput);
            $answers = str_replace('Answer', '', strip_tags($fieldset));

            foreach ($countfirst as $option => &$count) {
                if (strstr($answers, $option, true) === '') {
                    $count++;
                }
                $allwerefirst = (array_product($countfirst) > 0);
            }
            $safety++;
        }

        // Make sure we're not here just because of the safety switch.
        self::assertTrue($allwerefirst);
    }

    public function test_render_shuffled_mce(): void {
        // Create a single part multiple choice (dropdown) question. Activate shuffling of the options
        // and disable numbering of the answers, as that makes it easier to test.
        $q = $this->get_test_formulas_question('testmce');
        $q->answernumbering = '';
        $q->parts[0]->subqtext = '{_0:mychoices:MCES}';

        $this->start_attempt_at_question($q, 'immediatefeedback', 1);

        // Count how many times each option appears first. Re-render the question until all options have been
        // on the first position at least once. In case something is off, stop after 100 tries at latest.
        $countfirst = ['Dog' => 0, 'Cat' => 0, 'Bird' => 0, 'Fish' => 0];
        $allwerefirst = false;
        $safety = 0;
        while (!$allwerefirst && $safety < 100) {
            $this->render();
            $select = preg_replace('=^(.*)<select[^>]+>(.+)</select>(.*)$=', '\\2', $this->currentoutput);
            $answers = strip_tags($select);

            foreach ($countfirst as $option => &$count) {
                if (strstr($answers, $option, true) === '') {
                    $count++;
                }
                $allwerefirst = (array_product($countfirst) > 0);
            }
            $safety++;
        }

        // Make sure we're not here just because of the safety switch.
        self::assertTrue($allwerefirst);
    }

    public function test_render_mc_question(): void {
        // Create a single part multiple choice (radio) question.
        $q = $this->get_test_formulas_question('testmc');
        $this->start_attempt_at_question($q, 'adaptive', 1);

        $this->render();
        $this->check_current_output(
                $this->get_contains_radio_expectation(['name' => $this->quba->get_field_prefix($this->slot) . '0_0'], true, false),
                $this->get_does_not_contain_specific_feedback_expectation()
        );

        // Submit wrong answer.
        $this->process_submission(['0_0' => '0', '-submit' => 1]);

        $this->check_current_state(question_state::$todo);
        $this->check_current_output(
                $this->get_contains_radio_expectation(['name' => $this->quba->get_field_prefix($this->slot) . '0_0'], true, true),
                $this->get_contains_num_parts_correct(0)
        );
        $this->check_current_mark(0);

        // Submit right answer.
        $this->process_submission(['0_0' => '1', '-submit' => 1]);
        $this->check_current_state(question_state::$complete);
        $this->check_current_output(
                $this->get_contains_radio_expectation(['name' => $this->quba->get_field_prefix($this->slot) . '0_0'], true, true),
                $this->get_contains_num_parts_correct(1)
        );
        $this->check_current_mark(0.7);

        // Restart with immediate feedback to check the radio box is disabled when showing the feedback.
        $this->start_attempt_at_question($q, 'immediatefeedback', 1);
        $this->process_submission(['0_0' => '1', '-submit' => 1]);
        $this->check_current_output(
            $this->get_contains_radio_expectation(['id' => $this->quba->get_field_prefix($this->slot) . '0_0_0'], false, false),
            $this->get_contains_radio_expectation(['id' => $this->quba->get_field_prefix($this->slot) . '0_0_1'], false, true),
            $this->get_contains_radio_expectation(['id' => $this->quba->get_field_prefix($this->slot) . '0_0_2'], false, false),
            $this->get_contains_radio_expectation(['id' => $this->quba->get_field_prefix($this->slot) . '0_0_3'], false, false),
        );
    }

    public function test_render_mce_question(): void {
        // Create a single part multiple choice (dropdown) question.
        $q = $this->get_test_formulas_question('testmce');
        $this->start_attempt_at_question($q, 'immediatefeedback', 1);

        $this->render();
        // Using check_current_output to make sure that the <select> is actually there. Using
        // check_output_contains_selectoptions only makes sure that the options are there
        // *among* existing <select>s; if no <select> is there, the options do not need to exist.
        $this->check_current_output(
            new \question_contains_tag_with_attribute('select', 'name', $this->quba->get_field_prefix($this->slot) . '0_0'),
            $this->get_does_not_contain_specific_feedback_expectation(),
            new \question_contains_tag_with_contents('label', 'Answer'),
            new \question_contains_tag_with_attribute('label', 'class', 'subq accesshide'),
        );
        $this->check_output_contains_selectoptions(
            $this->get_contains_select_expectation('0_0', ['Dog', 'Cat', 'Bird', 'Fish'], 0)
        );

        // Submit wrong answer.
        $this->process_submission(['0_0' => '0', '-submit' => 1]);
        $this->check_current_state(question_state::$gradedwrong);
        $this->check_output_contains_selectoptions(
            $this->get_contains_select_expectation('0_0', ['Dog', 'Cat', 'Bird', 'Fish'], 0)
        );
        $this->check_current_mark(0);
        $this->check_output_contains_lang_string('correctansweris', 'qtype_formulas', 'Cat');

        // Restart question and submit right answer.
        $this->start_attempt_at_question($q, 'immediatefeedback', 1);
        $this->process_submission(['0_0' => '1', '-submit' => 1]);
        $this->check_current_state(question_state::$gradedright);
        $this->check_output_contains_selectoptions(
            $this->get_contains_select_expectation('0_0', ['Dog', 'Cat', 'Bird', 'Fish'], 1, false)
        );
        $this->check_current_mark(1);
        $this->check_output_contains_lang_string('correctansweris', 'qtype_formulas', 'Cat');
    }

    public function test_render_mc_question_with_missing_options(): void {
        // Create a single part multiple choice (dropdown) question.
        $q = $this->get_test_formulas_question('testmce');
        $q->parts[0]->subqtext = '{_0:xxxx}';
        $this->start_attempt_at_question($q, 'immediatefeedback', 1);

        $this->render();
        // The options are not available, so a text box should be rendered instead.
        $this->check_output_contains_text_input('0_0', '', true);
        $this->check_current_output(
            new \question_contains_tag_with_contents('label', 'Answer'),
        );

        // Create a single part multiple choice (radio) question.
        $q = $this->get_test_formulas_question('testmc');
        $q->parts[0]->subqtext = '{_0:xxxx}';
        $this->start_attempt_at_question($q, 'immediatefeedback', 1);

        $this->render();
        // The options are not available, so a text box should be rendered instead.
        $this->check_output_contains_text_input('0_0', '', true);
        $this->check_current_output(
            new \question_contains_tag_with_contents('label', 'Answer'),
        );
    }

    public function test_render_mce_accessibility_labels(): void {
        // Create a single part multiple choice (dropdown) question.
        $q = $this->get_test_formulas_question('testmce');
        $this->start_attempt_at_question($q, 'immediatefeedback', 1);
        $this->check_current_output(
            new \question_contains_tag_with_contents('label', 'Answer'),
        );

        // Create a single part multiple choice (dropdown) question with an additional field.
        $q = $this->get_test_formulas_question('testmce');
        $q->parts[0]->numbox = 2;
        $q->parts[0]->answer = '[1, 1]';
        $this->start_attempt_at_question($q, 'immediatefeedback', 1);
        $this->check_current_output(
            new \question_contains_tag_with_contents('label', 'Answer field 1'),
            new \question_contains_tag_with_contents('label', 'Answer field 2'),
        );

        // Create a two-part multiple choice (dropdown) question with an additional field in each part.
        $q = $this->get_test_formulas_question('testmcetwoparts');
        $q->parts[0]->numbox = 2;
        $q->parts[0]->answer = '[1, 1]';
        $q->parts[1]->numbox = 2;
        $q->parts[1]->answer = '[1, 1]';

        $this->start_attempt_at_question($q, 'immediatefeedback', 1);
        $this->check_current_output(
            new \question_contains_tag_with_contents('label', 'Answer field 1 for part 1'),
            new \question_contains_tag_with_contents('label', 'Answer field 2 for part 1'),
            new \question_contains_tag_with_contents('label', 'Answer field 1 for part 2'),
            new \question_contains_tag_with_contents('label', 'Answer field 2 for part 2'),
        );
    }

    public function test_textbox_tooltip_title(): void {
        // Create a simple test question.
        $q = $this->get_test_formulas_question('testsinglenum');
        $this->start_attempt_at_question($q, 'immediatefeedback', 1);
        $this->check_current_output(
            new \question_contains_tag_with_attribute('input', 'data-title', 'Number'),
        );

        // Change answer type to numeric.
        $q->parts[0]->answertype = qtype_formulas::ANSWER_TYPE_NUMERIC;
        $this->start_attempt_at_question($q, 'immediatefeedback', 1);
        $this->check_current_output(
            new \question_contains_tag_with_attribute('input', 'data-title', 'Numeric'),
        );

        // Change answer type to numerical formula.
        $q->parts[0]->answertype = qtype_formulas::ANSWER_TYPE_NUMERICAL_FORMULA;
        $this->start_attempt_at_question($q, 'immediatefeedback', 1);
        $this->check_current_output(
            new \question_contains_tag_with_attribute('input', 'data-title', 'Numerical formula'),
        );

        // Change answer type to algebraic formula.
        $q->parts[0]->answertype = qtype_formulas::ANSWER_TYPE_ALGEBRAIC;
        $this->start_attempt_at_question($q, 'immediatefeedback', 1);
        $this->check_current_output(
            new \question_contains_tag_with_attribute('input', 'data-title', 'Algebraic formula'),
        );

        // Create a simple test question with a combined field.
        $q = $this->get_test_formulas_question('testsinglenumunit');
        $this->start_attempt_at_question($q, 'immediatefeedback', 1);
        $this->check_current_output(
            new \question_contains_tag_with_attribute('input', 'data-title', 'Number and unit'),
        );

        // Create a simple test question with a separate unit field.
        $q = $this->get_test_formulas_question('testsinglenumunitsep');
        $this->start_attempt_at_question($q, 'immediatefeedback', 1);
        $this->check_current_output(
            new \question_contains_tag_with_attribute('input', 'data-title', 'Number'),
            new \question_contains_tag_with_attribute('input', 'data-title', 'Unit'),
        );
    }

    public function test_render_mc_accessibility_labels(): void {
        // Create a single part multiple choice (radio) question.
        $q = $this->get_test_formulas_question('testmc');
        $this->start_attempt_at_question($q, 'immediatefeedback', 1);
        $this->check_current_output(
            new \question_contains_tag_with_attribute('legend', 'class', 'sr-only'),
            new \question_contains_tag_with_attribute('span', 'class', 'sr-only'),
            new \question_contains_tag_with_contents('span', 'Answer'),
        );

        // Create a single part multiple choice (radio) question with an additional field.
        $q = $this->get_test_formulas_question('testmc');
        $q->parts[0]->numbox = 2;
        $q->parts[0]->answer = '[1, 1]';
        $this->start_attempt_at_question($q, 'immediatefeedback', 1);
        $this->check_current_output(
            new \question_contains_tag_with_attribute('legend', 'class', 'sr-only'),
            new \question_contains_tag_with_contents('span', 'Answer field 1'),
            new \question_contains_tag_with_contents('label', 'Answer field 2'),
        );

        // Create a two-part multiple choice (radio) question with an additional field in each part.
        $q = $this->get_test_formulas_question('testmctwoparts');
        $q->parts[0]->numbox = 2;
        $q->parts[0]->answer = '[1, 1]';
        $q->parts[1]->numbox = 2;
        $q->parts[1]->answer = '[1, 1]';

        $this->start_attempt_at_question($q, 'immediatefeedback', 1);
        $this->check_current_output(
            new \question_contains_tag_with_attribute('legend', 'class', 'sr-only'),
            new \question_contains_tag_with_contents('span', 'Answer field 1 for part 1'),
            new \question_contains_tag_with_contents('label', 'Answer field 2 for part 1'),
            new \question_contains_tag_with_contents('span', 'Answer field 1 for part 2'),
            new \question_contains_tag_with_contents('label', 'Answer field 2 for part 2'),
        );
    }

    public function test_render_textbox_accessibility_labels(): void {
        // Create a multi-part question with a combined and a separate unit field.
        $q = $this->get_test_formulas_question('testmethodsinparts');
        $this->start_attempt_at_question($q, 'immediatefeedback', 1);
        $this->check_current_output(
            new \question_contains_tag_with_contents('label', 'Answer and unit for part 1'),
            new \question_contains_tag_with_contents('label', 'Answer for part 2'),
            new \question_contains_tag_with_contents('label', 'Unit for part 2'),
            new \question_contains_tag_with_contents('label', 'Answer for part 3'),
            new \question_contains_tag_with_contents('label', 'Answer for part 4'),
        );

        // Create a multi-part question with a combined and a separate unit field.
        $q = $this->get_test_formulas_question('testtwoandtwo');
        $this->start_attempt_at_question($q, 'immediatefeedback', 1);
        $this->check_current_output(
            new \question_contains_tag_with_contents('label', 'Answer field 1 for part 1'),
            new \question_contains_tag_with_contents('label', 'Answer field 2 for part 1'),
            new \question_contains_tag_with_contents('label', 'Answer field 1 for part 2'),
            new \question_contains_tag_with_contents('label', 'Answer field 2 for part 2'),
        );
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

    public function test_clear_wrong_with_combined_unit(): void {
        // Create a multipart question with combined, separate and no unit field.
        $q = $this->get_test_formulas_question('testmethodsinparts');
        // Create two hints all set to clear wrong answers.
        $q->hints = [
                new question_hint_with_parts(0, 'Hint 1.', FORMAT_HTML, false, true),
                new question_hint_with_parts(0, 'Hint 2.', FORMAT_HTML, false, true),
        ];

        $this->start_attempt_at_question($q, 'interactive', 4);

        $this->render();
        $this->check_output_contains_text_input('0_', '', true);
        $this->check_output_contains_text_input('1_0', '', true);
        $this->check_output_contains_text_input('1_1', '', true);
        $this->check_output_contains_text_input('2_0', '', true);
        $this->check_output_contains_text_input('3_0', '', true);
        $this->check_current_output(
                $this->get_no_hint_visible_expectation()
        );

        // Submit wrong answer in first and third part, right answer for the others.
        // First part's answer is not only wrong, but invalid, i. e. it leads to an error
        // when trying to split the number and the unit.
        // phpcs:ignore Universal.Arrays.DuplicateArrayKey.Found
        $this->process_submission(['0_' => '(', '1_0' => '40', '1_1' => 'm/s', '2_0' => '1', '3_0' => '40', '-submit' => 1]);
        $this->check_current_state(question_state::$todo);
        $this->check_current_output(
                $this->get_contains_hint_expectation('Hint 1.'),
                $this->get_tries_remaining_expectation(2),
                $this->get_contains_try_again_button_expectation(),
        );
        $this->render();
        $this->check_output_contains_text_input('0_', '(', false);
        $this->check_output_contains_text_input('1_0', '40', false);
        $this->check_output_contains_text_input('1_1', 'm/s', false);
        $this->check_output_contains_text_input('2_0', '1', false);
        $this->check_output_contains_text_input('3_0', '40', false);
        // The first hint is set to "clear wrong answers", so there should now be hidden fields
        // overriding the input from the text boxes.
        $this->check_output_contains_hidden_input('0_', '');
        $this->check_output_contains_hidden_input('2_0', '');

        // Try again. The wrong fields should now be cleared, the others still filled.
        // phpcs:ignore Universal.Arrays.DuplicateArrayKey.Found
        $this->process_submission(['0_' => '', '1_0' => '40', '1_1' => 'm/s', '2_0' => '', '3_0' => '40', '-tryagain' => 1]);
        $this->render();
        $this->check_output_contains_text_input('0_', '', true);
        $this->check_output_contains_text_input('1_0', '40', true);
        $this->check_output_contains_text_input('1_1', 'm/s', true);
        $this->check_output_contains_text_input('2_0', '', true);
        $this->check_output_contains_text_input('3_0', '40', true);

        // Submit wrong answer in first and second part, right answer for the others.
        // phpcs:ignore Universal.Arrays.DuplicateArrayKey.Found
        $this->process_submission(['0_' => '2', '1_0' => '2', '1_1' => 'kg', '2_0' => '40', '3_0' => '40', '-submit' => 1]);
        $this->check_current_state(question_state::$todo);
        $this->check_current_output(
                $this->get_contains_hint_expectation('Hint 2.'),
                $this->get_tries_remaining_expectation(1),
                $this->get_contains_try_again_button_expectation(),
        );
        $this->render();
        $this->check_output_contains_text_input('0_', '2', false);
        $this->check_output_contains_text_input('1_0', '2', false);
        $this->check_output_contains_text_input('1_1', 'kg', false);
        $this->check_output_contains_text_input('2_0', '40', false);
        $this->check_output_contains_text_input('3_0', '40', false);
        // The first hint is set to "clear wrong answers", so there should now be hidden fields
        // overriding the input from the text boxes.
        $this->check_output_contains_hidden_input('0_', '');
        $this->check_output_contains_hidden_input('1_0', '');
        $this->check_output_contains_hidden_input('1_1', '');

        // Try again. The wrong fields should now be cleared, the others still filled.
        // phpcs:ignore Universal.Arrays.DuplicateArrayKey.Found
        $this->process_submission(['0_' => '', '1_0' => '', '1_1' => '', '2_0' => '40', '3_0' => '40', '-tryagain' => 1]);
        $this->render();
        $this->check_output_contains_text_input('0_', '', true);
        $this->check_output_contains_text_input('1_0', '', true);
        $this->check_output_contains_text_input('1_1', '', true);
        $this->check_output_contains_text_input('2_0', '40', true);
        $this->check_output_contains_text_input('3_0', '40', true);

        // Submit right answer.
        // phpcs:ignore Universal.Arrays.DuplicateArrayKey.Found
        $this->process_submission(['0_' => '40 m/s', '1_0' => '40', '1_1' => 'm/s', '2_0' => '40', '3_0' => '40', '-submit' => 1]);
        $this->check_current_state(question_state::$gradedright);
        $this->check_current_output(
                $this->get_no_hint_visible_expectation(),
                $this->get_does_not_contain_try_again_button_expectation(),
        );
        $this->render();
        $this->check_output_contains_text_input('0_', '40 m/s', false);
        $this->check_output_contains_text_input('1_0', '40', false);
        $this->check_output_contains_text_input('1_1', 'm/s', false);
        $this->check_output_contains_text_input('2_0', '40', false);
        $this->check_output_contains_text_input('3_0', '40', false);

        $this->check_current_mark(2.5);
    }


    /**
     * Data provider.
     *
     * @return Generator
     */
    public static function provide_responses_for_feedback_test(): Generator {
        yield [
            qtype_formulas_test_helper::DEFAULT_CORRECT_FEEDBACK,
            ['behaviour' => 'immediatefeedback', 'question' => 'testsinglenumunit', 'response' => ['0_' => '5 m/s']],
        ];
        yield [
            qtype_formulas_test_helper::DEFAULT_PARTIALLYCORRECT_FEEDBACK,
            ['behaviour' => 'immediatefeedback', 'question' => 'testsinglenumunit', 'response' => ['0_' => '5']],
        ];
        yield [
            qtype_formulas_test_helper::DEFAULT_INCORRECT_FEEDBACK,
            ['behaviour' => 'immediatefeedback', 'question' => 'testsinglenumunit', 'response' => ['0_' => '1']],
        ];
        // The following is considered incorrect, because it is converted to 5000 m/s.
        yield [
            qtype_formulas_test_helper::DEFAULT_INCORRECT_FEEDBACK,
            ['behaviour' => 'immediatefeedback', 'question' => 'testsinglenumunit', 'response' => ['0_' => '5 km/s']],
        ];
        // The following is considered partially correct, because the unit is wrong (hour is not supported).
        yield [
            qtype_formulas_test_helper::DEFAULT_PARTIALLYCORRECT_FEEDBACK,
            ['behaviour' => 'immediatefeedback', 'question' => 'testsinglenumunit', 'response' => ['0_' => '5 km/h']],
        ];
        yield [
            qtype_formulas_test_helper::DEFAULT_CORRECT_FEEDBACK,
            ['behaviour' => 'adaptive', 'question' => 'testsinglenumunit', 'response' => ['0_' => '5 m/s']],
        ];
        yield [
            qtype_formulas_test_helper::DEFAULT_PARTIALLYCORRECT_FEEDBACK,
            ['behaviour' => 'adaptive', 'question' => 'testsinglenumunit', 'response' => ['0_' => '5']],
        ];
        yield [
            qtype_formulas_test_helper::DEFAULT_INCORRECT_FEEDBACK,
            ['behaviour' => 'adaptive', 'question' => 'testsinglenumunit', 'response' => ['0_' => '1']],
        ];
        yield [
            qtype_formulas_test_helper::DEFAULT_CORRECT_FEEDBACK,
            ['behaviour' => 'interactive', 'question' => 'testsinglenumunit', 'response' => ['0_' => '5 m/s']],
        ];
        yield [
            qtype_formulas_test_helper::DEFAULT_PARTIALLYCORRECT_FEEDBACK,
            ['behaviour' => 'interactive', 'question' => 'testsinglenumunit', 'response' => ['0_' => '5']],
        ];
        yield [
            qtype_formulas_test_helper::DEFAULT_INCORRECT_FEEDBACK,
            ['behaviour' => 'interactive', 'question' => 'testsinglenumunit', 'response' => ['0_' => '1']],
        ];
    }

    /**
     * Test general and combined feedback for part.
     *
     * @param string $expectedfeedback the feedback that should be shown
     * @param array $input input data (behaviour, question name, simulated student response)
     * @dataProvider provide_responses_for_feedback_test
     */
    public function test_part_feedback($expectedfeedback, $input): void {
        // Prepare feedback strings.
        $generalfeedback = 'Part general feedback';
        $feedbacks = [
            qtype_formulas_test_helper::DEFAULT_CORRECT_FEEDBACK,
            qtype_formulas_test_helper::DEFAULT_INCORRECT_FEEDBACK,
            qtype_formulas_test_helper::DEFAULT_PARTIALLYCORRECT_FEEDBACK,
        ];

        // Create the requested question.
        $q = $this->get_test_formulas_question($input['question']);
        $q->parts[0]->unitpenalty = 0.6;
        $q->parts[0]->feedback = $generalfeedback;

        // Start question, check that there is no feedback yet.
        $this->start_attempt_at_question($q, $input['behaviour'], 1);
        $this->check_output_does_not_contain($generalfeedback);
        foreach ($feedbacks as $feedback) {
            $this->check_output_does_not_contain($feedback);
        }

        // Submit answer.
        $this->process_submission($input['response'] + ['-submit' => 1]);

        // Verify the feedback.
        $this->check_output_contains('Part general feedback');
        foreach ($feedbacks as $feedback) {
            if ($feedback === $expectedfeedback) {
                $this->check_output_contains($feedback);
            } else {
                $this->check_output_does_not_contain($feedback);
            }
        }
    }
}
