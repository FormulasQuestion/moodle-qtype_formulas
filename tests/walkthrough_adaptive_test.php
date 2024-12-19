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

namespace qtype_formulas;
use question_state;
use test_question_maker;
use question_hint_with_parts;
use qtype_formulas_test_helper;
use Generator;

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
class walkthrough_adaptive_test extends walkthrough_test_base {
    /**
    * @return qtype_formulas_question the requested question object.
    */
    protected function get_test_formulas_question($which = null) {
        return test_question_maker::make_question('formulas', $which);
    }

    public function test_submit_empty_then_right() {
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

    public function test_submit_empty_then_right_with_combined_unit() {
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

    public function test_submit_empty_then_right_with_separate_unit() {
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

    public function test_test0_submit_right_first_time() {
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
    * @dataProvider provide_responses_for_feedback_test
    */
    public function test_part_feedback($expectedfeedback, $input) {
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

    public function test_test0_submit_wrong_submit_right() {
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
        $this->process_submission(array('0_0' => 'dont know', '-submit' => 1));

        // Verify.
        $this->check_current_state(question_state::$todo);
        $this->check_current_mark(0);
        $this->check_current_output(
            $this->get_contains_mark_summary(0),
            $this->get_contains_submit_button_expectation(true),
            $this->get_does_not_contain_validation_error_expectation()
        );

        // Submit a correct answer.
        $this->process_submission(array('0_0' => '5', '-submit' => 1));

        // Verify.
        $this->check_current_state(question_state::$complete);
        $this->check_current_mark(0.7);
        $this->render();
        $this->check_output_contains_text_input('0_0', '5', true);
        $this->check_output_does_not_contain_stray_placeholders();
    }

    public function test_test0_submit_wrong_unit_then_right() {
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

    public function test_test0_submit_wrong_unit_then_right_with_hints() {
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

    public function test_test0_submit_wrong_wrong_right() {
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
        $this->process_submission(array('0_0' => 'dont know', '-submit' => 1));

        // Verify.
        $this->check_current_state(question_state::$todo);
        $this->check_current_mark(0);
        $this->check_current_output(
            $this->get_contains_mark_summary(0),
            $this->get_contains_submit_button_expectation(true),
            $this->get_does_not_contain_validation_error_expectation()
        );

        // Submit another incorrect answer.
        $this->process_submission(array('0_0' => 'still dont know', '-submit' => 1));

        // Verify.
        $this->check_current_state(question_state::$todo);
        $this->check_current_mark(0);
        $this->check_current_output(
            $this->get_contains_mark_summary(0),
            $this->get_contains_submit_button_expectation(true),
            $this->get_does_not_contain_validation_error_expectation()
        );

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
        $this->process_submission(array('0_0' => 'dont know', '-submit' => 1));

        // Verify.
        $this->check_current_state(question_state::$todo);
        $this->check_current_mark(0);
        $this->check_current_output(
            $this->get_contains_mark_summary(0),
            $this->get_contains_submit_button_expectation(true),
            $this->get_does_not_contain_validation_error_expectation()
        );

        // Submit again the same incorrect answer.
        $this->process_submission(array('0_0' => 'dont know', '-submit' => 1));

        // Verify.
        $this->check_current_state(question_state::$todo);
        $this->check_current_mark(0);
        $this->check_current_output(
            $this->get_contains_mark_summary(0),
            $this->get_contains_submit_button_expectation(true),
            $this->get_does_not_contain_validation_error_expectation()
        );

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
