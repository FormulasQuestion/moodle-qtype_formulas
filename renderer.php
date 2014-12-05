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
 * Formulas question renderer class.
 *
 * @package    qtype_formulas
 * @copyright  2009 The Open University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Base class for generating the bits of output for formulas questions.
 *
 * @copyright  2009 The Open University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class qtype_formulas_renderer extends qtype_with_combined_feedback_renderer {
    /**
     * Generate the display of the formulation part of the question. This is the
     * area that contains the question text, and the controls for students to
     * input their answers. Some question types also embed bits of feedback, for
     * example ticks and crosses, in this area.
     *
     * @param question_attempt $qa the question attempt to display.
     * @param question_display_options $options controls what should and should not be displayed.
     * @return string HTML fragment.
     */
    public function formulation_and_controls(question_attempt $qa,
            question_display_options $options) {
        global $CFG;

        $question = $qa->get_question();

        $globalvars = $question->get_global_variables();

        // TODO: is this really necessary here ? If question is damaged it should have been detected before.
        if (count($question->textfragments) != $question->numpart + 1) {
            notify('Error: Question is damaged, number of text fragments and number of question parts are not equal.');
            return;
        }

        $result = html_writer::tag('script', 'var formulasbaseurl='
                .json_encode($CFG->wwwroot . '/question/type/' . $question->get_type_name()).';', array('type'=>'text/javascript'));
        $questiontext = '';
        foreach ($question->parts as $part) {
            $questiontext .= $question->formulas_format_text($globalvars, $question->textfragments[$part->partindex], FORMAT_HTML, $qa, 'question', 'questiontext', $question->id, false);
            $questiontext .= $this->part_formulation_and_controls($qa, $options, $part);
        }
        $questiontext .= $question->formulas_format_text($globalvars, $question->textfragments[$question->numpart], FORMAT_HTML, $qa, 'question', 'questiontext', $question->id, false);

        $result .= html_writer::tag('div', $questiontext, array('class' => 'qtext'));
        if ($qa->get_state() == question_state::$invalid) {
            $result .= html_writer::nonempty_tag('div',
                    $question->get_validation_error($qa->get_last_qt_data()),
                    array('class' => 'validationerror'));
        }
        return $result;
    }

    public function head_code(question_attempt $qa) {
        global $PAGE;

        $PAGE->requires->js('/question/type/formulas/script/formatcheck.js');
    }

    // Return the part text, controls, grading details and feedbacks.
    public function part_formulation_and_controls(question_attempt $qa,
            question_display_options $options, $part) {
        $question = $qa->get_question();
        $sub = $this->get_part_image_and_class($qa, $options, $part);
        $localvars = $question->get_local_variables($part);

        $output = $this->get_part_formulation($qa, $options, $part->partindex, $localvars, $sub);
        // Place for the right/wrong feeback image or appended at part's end.
        if (strpos($output, '{_m}') !== false) {
            $output = str_replace('{_m}', $sub->feedbackimage, $output);
        } else {
            $output .= $sub->feedbackimage;
        }

        $feedback = $this->part_feedback($qa, $options, $part);
        // We don't display the right answer if one of the part's coordinates is a MC or select question.
        // Because for that coordinate our result is not the right answer, but the index of the right answer,
        // And it would be very dfficult to calculate the right answer.
        // TODO: find a solution in that case. A popup (calculated in the part renderer) would work,
        // but would no be very accessible.
        if ($options->rightanswer && !$part->part_has_multichoice_coordinate()) {
            $feedback .= $this->part_correct_response($part->partindex, $qa);
        }
        $output .= html_writer::nonempty_tag('div', $feedback,
                array('class' => 'formulaspartoutcome'));
        return html_writer::tag('div', $output , array('class' => 'formulaspart'));
    }

    // Return class and image for the part feedback.
    public function get_part_image_and_class($qa, $options, $part) {
        $question = $qa->get_question();

        $sub = new StdClass;

        $response = $qa->get_last_qt_data();
        $question->rationalize_responses($response);
        $checkunit = new answer_unit_conversion;

        list( $sub->anscorr, $sub->unitcorr) = $question->grade_responses_individually($part, $response, $checkunit);
        $sub->fraction = $sub->anscorr * ($sub->unitcorr ? 1 : (1-$part->unitpenalty));

        // If using adaptivemultipart behaviour, adjust feedback display options for this part.
        if ($qa->get_behaviour_name() == 'adaptivemultipart') {
            $qa->get_behaviour()->adjust_display_options_for_part($part->partindex, $options);
        }

        // Get the class and image for the feedback.
        if ($options->correctness) {
            $sub->feedbackimage = $this->feedback_image($sub->fraction);
            $sub->feedbackclass = $this->feedback_class($sub->fraction);
            if ($part->unitpenalty >= 1) { // All boxes must be correct at the same time, so they are of the same color.
                $sub->unitfeedbackclass = $sub->feedbackclass;
                $sub->boxfeedbackclass = $sub->feedbackclass;
            } else {  // Show individual color, all four color combinations are possible.
                $sub->unitfeedbackclass = $this->feedback_class($sub->unitcorr);
                $sub->boxfeedbackclass = $this->feedback_class($sub->anscorr);
            }
        } else {  // There should be no feedback if options->correctness is not set for this part.
            $sub->feedbackimage = '';
            $sub->feedbackclass = '';
            $sub->unitfeedbackclass = '';
            $sub->boxfeedbackclass = '';
        }
        return $sub;
    }

    // Return the part's text with variables replaced by their values.
    public function get_part_formulation(question_attempt $qa, question_display_options $options, $i, $vars, $sub) {
        $question = $qa->get_question();
        $part = &$question->parts[$i];
        $localvars = $question->get_local_variables($part);

        // If using adaptivemultipart behaviour, adjust feedback display options for this part.
        if ($qa->get_behaviour_name() == 'adaptivemultipart') {
            $qa->get_behaviour()->adjust_display_options_for_part($part->partindex, $options);
        }

        $subqreplaced = $question->formulas_format_text($localvars, $part->subqtext,
                $part->subqtextformat, $qa, 'qtype_formulas', 'answersubqtext', $part->id, false);

        $types = array(0 => 'number', 10 => 'numeric', 100 => 'numerical_formula', 1000 => 'algebraic_formula');
        $gradingtype = ($part->answertype!=10 && $part->answertype!=100 && $part->answertype!=1000) ? 0 : $part->answertype;
        $gtype = $types[$gradingtype];

        // Get the set of defined placeholders and their options, also missing placeholders are appended at the end.
        $pattern = '\{(_[0-9u][0-9]*)(:[^{}:]+)?(:[^{}:]+)?\}';
        preg_match_all('/'.$pattern.'/', $subqreplaced, $matches);
        $boxes = array();
        foreach ($matches[1] as $j => $match) {
            if (!array_key_exists($match, $boxes)) {  // If there is duplication, it will be skipped.
                $boxes[$match] = (object)array('pattern' => $matches[0][$j], 'options' => $matches[2][$j], 'stype' => $matches[3][$j]);
            }
        }
        foreach (range(0, $part->numbox) as $j => $notused) {
            $placeholder = ($j == $part->numbox) ? "_u" : "_$j";
            if (!array_key_exists($placeholder, $boxes)) {
                $boxes[$placeholder] = (object)array('pattern' => "{".$placeholder."}", 'options' => '', 'stype' => '');
                $subqreplaced .= "{".$placeholder."}";  // Appended at the end.
            }
        }

        // If part has combined unit answer input.
        if ($part->part_has_combined_unit_field()) {
            $var_name =  "${i}_";
            $currentanswer = $qa->get_last_qt_var($var_name);
            $inputname = $qa->get_qt_field_name($var_name);
            $inputattributes = array(
                'type' => 'text',
                'name' => $inputname,
                'title' => get_string($gtype.($part->postunit=='' ? '' : '_unit'), 'qtype_formulas'),
                'value' => $currentanswer,
                'id' => $inputname,
                'class' => 'formulas_' . $gtype . '_unit ' . $sub->feedbackclass,
                'maxlength' => 128,
            );

            if ($options->readonly) {
                $inputattributes['readonly'] = 'readonly';
            }
            $input = html_writer::empty_tag('input', $inputattributes);
            $subqreplaced = str_replace("{_0}{_u}", $input, $subqreplaced);
        }

        // Get the set of string for each candidate input box {_0}, {_1}, ..., {_u}.
        $inputs = array();
        foreach (range(0, $part->numbox) as $j) {    // Replace the input box for each placeholder {_0}, {_1} ...
            $placeholder = ($j == $part->numbox) ? "_u" : "_$j";    // The last one is unit.
            $var_name =  "${i}_$j";
            $currentanswer = $qa->get_last_qt_var($var_name);
            $inputname = $qa->get_qt_field_name($var_name);
            $inputattributes = array(
                'name' => $inputname,
                'value' => $currentanswer,
                'id' => $inputname,
                'maxlength' => 128,
            );
            if ($options->readonly) {
                $inputattributes['readonly'] = 'readonly';
            }

            $stexts = null;
            if (strlen($boxes[$placeholder]->options) != 0) { // MC or check box.
                try {
                    $stexts = $question->qv->evaluate_general_expression($vars, substr($boxes[$placeholder]->options, 1));
                } catch (Exception $e) {
                    // The $stexts variable will be null if evaluation fails.
                }
            }
            // Coordinate as multichoice options.
            if ($stexts != null) {
                if ($boxes[$placeholder]->stype == ':SL') {
                } else {
                    if ($boxes[$placeholder]->stype == ':MCE') {
                        // Select menu.
                        if ($options->readonly) {
                            $inputattributes['disabled'] = 'disabled';
                        }
                        $choices =array();
                        foreach ($stexts->value as $x => $mctxt) {
                            $choices[$x] = $mctxt;
                        }
                        $select = html_writer::select($choices, $inputname,
                                $currentanswer, array('' => ''), $inputattributes);
                        $output = html_writer::start_tag('span', array('class' => 'formulaspart'));
                        $output .= html_writer::tag('label', get_string('answer'),
                                array('class' => 'subq accesshide', 'for' => $inputattributes['id']));
                        $output .= $select;
                        $output .= html_writer::end_tag('span');
                        $inputs[$placeholder] = $output;
                    } else {
                        // Multichoice single question.
                        $inputattributes['type'] = 'radio';
                        if ($options->readonly) {
                            $inputattributes['disabled'] = 'disabled';
                        }
                        $output = $this->all_choices_wrapper_start();
                        foreach ($stexts->value as $x => $mctxt) {
                            $inputattributes['id'] = $inputname.'_'.$x;
                            $inputattributes['value'] = $x;
                            $isselected = ($currentanswer != '' && $x==$currentanswer);
                            $class = 'r' . ($x % 2);
                            if ($isselected) {
                                $inputattributes['checked'] = 'checked';
                            } else {
                                unset($inputattributes['checked']);
                            }
                            if ($options->correctness && $isselected) {
                                $class .= ' ' . $sub->feedbackclass;
                            }
                            $output .= $this->choice_wrapper_start($class);
                            $output .= html_writer::empty_tag('input', $inputattributes);
                            $output .= html_writer::tag('label', $mctxt,
                                    array('for' => $inputattributes['id']));
                            $output .= $this->choice_wrapper_end();
                        }
                        $output .= $this->all_choices_wrapper_end();
                        $inputs[$placeholder] = $output;
                    }
                }
                continue;
            }

            // Coordinate as shortanswer question.
            $inputs[$placeholder] = '';
            $inputattributes['type'] = 'text';
            if ($options->readonly) {
                $inputattributes['readonly'] = 'readonly';
            }
            if ($j == $part->numbox) {
                // Check if it's an input for unit.
                if (strlen($part->postunit) > 0) {
                    $inputattributes['title'] = get_string('unit', 'qtype_formulas');
                    $inputattributes['class'] = 'formulas_unit '.$sub->unitfeedbackclass;

                    $inputs[$placeholder] = html_writer::empty_tag('input', $inputattributes);
                }
            } else {
                $inputattributes['title'] = get_string($gtype, 'qtype_formulas');
                $inputattributes['class'] = 'formulas_'.$gtype.' '.$sub->boxfeedbackclass;

                $inputs[$placeholder] = html_writer::empty_tag('input', $inputattributes);
            }
        }

        foreach ($inputs as $placeholder => $replacement) {
            $subqreplaced = preg_replace('/'.$boxes[$placeholder]->pattern.'/', $replacement, $subqreplaced, 1);
        }
        return $subqreplaced;
    }

    /**
     * @param string $class class attribute value.
     * @return string HTML to go before each choice.
     */
    protected function choice_wrapper_start($class) {
        return html_writer::start_tag('div', array('class' => $class));
    }

    /**
     * @return string HTML to go after each choice.
     */
    protected function choice_wrapper_end() {
        return html_writer::end_tag('div');
    }

    /**
     * @return string HTML to go before all the choices.
     */
    protected function all_choices_wrapper_start() {
        return html_writer::start_tag('div', array('class' => 'answer'));
    }

    /**
     * @return string HTML to go after all the choices.
     */
    protected function all_choices_wrapper_end() {
        return html_writer::end_tag('div');
    }
    /**
     * Correct response is provided by each question part.
     *
     * @param question_attempt $qa the question attempt to display.
     * @return string HTML fragment.
     */
    public function correct_response(question_attempt $qa) {
        return '';
    }

    /**
     * Generate an automatic description of the correct response for this part.
     *
     * @param int $i the part index.
     * @param question_attempt $qa the question attempt to display.
     * @return string HTML fragment.
     */
    public function part_correct_response($i, question_attempt $qa) {
        $question = $qa->get_question();

        $tmp = $question->get_correct_responses_individually($question->parts[$i]);
        if ($question->parts[$i]->part_has_combined_unit_field()) {
            $correctanswer = implode(' ', $tmp);
        } else {
            if (!$question->parts[$i]->part_has_separate_unit_field()) {
                unset($tmp["${i}_" .(count($tmp)-1)]);
            }
            $correctanswer = implode(', ', $tmp);
        }
        return html_writer::nonempty_tag('div', get_string('correctansweris', 'qtype_formulas', $correctanswer),
                    array('class' => 'formulaspartcorrectanswer'));
    }

    /**
     * We need to owerwrite this method to replace global variables by their value
     * @param question_attempt $qa the question attempt to display.
     * @return string HTML fragment.
     */
    protected function hint(question_attempt $qa, question_hint $hint) {
        $question = $qa->get_question();
        $globalvars = $question->get_global_variables();
        $hint->hint = $question->qv->substitute_variables_in_text($globalvars, $hint->hint);
        return html_writer::nonempty_tag('div',
                $qa->get_question()->format_hint($hint, $qa), array('class' => 'hint'));
    }

    protected function combined_feedback(question_attempt $qa) {
        $question = $qa->get_question();

        $state = $qa->get_state();

        if (!$state->is_finished()) {
            $response = $qa->get_last_qt_data();
            if (!$qa->get_question()->is_gradable_response($response)) {
                return '';
            }
            list($notused, $state) = $qa->get_question()->grade_response($response);
        }

        $feedback = '';
        $field = $state->get_feedback_class() . 'feedback';
        $format = $state->get_feedback_class() . 'feedbackformat';
        if ($question->$field) {
            $globalvars = $question->get_global_variables();
            $feedback .= $question->formulas_format_text($globalvars, $question->$field, $question->$format,
                    $qa, 'question', $field, $question->id, false);
        }

        return $feedback;
    }

    public function specific_feedback(question_attempt $qa) {
        return $this->combined_feedback($qa);
    }

    /**
     * @param int $i the part index.
     * @param question_attempt $qa the question attempt to display.
     * @param question_definition $question the question being displayed.
     * @param question_display_options $options controls what should and should not be displayed.
     * @return string nicely formatted feedback, for display.
     */
    protected function part_feedback(question_attempt $qa,
            question_display_options $options, $part) {
        $err = '';
        $feedback = '';
        $gradingdetails = '';

        $question = $qa->get_question();
        $state = $qa->get_state();
        // Only show feedback if response is wrong (will be corrected later for adaptive behaviour).
        $showfeedback = $options->feedback && $state == question_state::$gradedwrong;

        if ($qa->get_behaviour_name() == 'adaptivemultipart') {
            // This is rather a hack, but it will probably work.
            $renderer = $this->page->get_renderer('qbehaviour_adaptivemultipart');
            $details = $qa->get_behaviour()->get_part_mark_details($part->partindex);
            $fraction = $qa->get_last_behaviour_var('_fraction_' . $part->partindex);
            $gradingdetails = $renderer->render_adaptive_marks($details, $options);
            // Only show feedback if response is wrong.
            $showfeedback = $details->state == question_state::$gradedwrong;
        }

        if ($showfeedback) {
            $localvars = $question->get_local_variables($part);
            $feedbacktext = $question->formulas_format_text($localvars, $part->feedback, FORMAT_HTML, $qa, 'qtype_formulas', 'answerfeedback', $part->id, false);
            if ($feedbacktext) {
                $feedback = html_writer::tag('div', $feedbacktext , array('class' => 'feedback formulaslocalfeedback'));
            }
        }

        return html_writer::nonempty_tag('div', $err . $feedback . $gradingdetails,
                array('class' => 'formulaspartfeedback formulaspartfeedback-' . $part->partindex));
    }
}
