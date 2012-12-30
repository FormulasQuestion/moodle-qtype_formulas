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
 * @package    qtype
 * @subpackage formulas
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

        $ss = qtype_formulas::create_subquestion_structure($question->questiontext, $question->parts);
        if (count($ss->pretexts) != $question->numpart) {
            notify("Error: The number of subquestions and number of answer are not the same.");
            return;
        }

        $result = html_writer::tag('script', 'var formulasbaseurl='.json_encode($CFG->wwwroot . '/question/type/' . $question->get_type_name()).';', array('type'=>'text/javascript'));
        $questiontext = '';
        foreach ($question->parts as $i => $part) {
            $pretext = $question->formulas_format_text($globalvars, $ss->pretexts[$i], FORMAT_HTML, $qa, 'question', 'questiontext', $question->id, false);
            $subtext = $this->part_formulation_and_controls($qa, $options, $i);
            $questiontext .= $pretext . $subtext;
        }
        $questiontext .= $question->formulas_format_text($globalvars, $ss->posttext, FORMAT_HTML, $qa, 'question', 'questiontext', $question->id, false);

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

        $PAGE->requires->js('/question/type/formulas/script/quiz.js');
        $PAGE->requires->js('/question/type/formulas/script/formatcheck.js');
        $PAGE->requires->js('/question/type/formulas/overlib/overlib.js', true);
        $PAGE->requires->js('/question/type/formulas/overlib/overlib_cssstyle.js', true);
    }

    // Return the part text, controls, grading details and feedbacks.
    public function part_formulation_and_controls($qa, $options, $i) {
        $question = $qa->get_question();
        $sub = $this->get_subquestion_all_options($qa, $options, $i);
        $part = $question->parts[$i];
        $localvars = $question->get_local_variables($part);

        $subqreplaced = $this->get_subquestion_formulation($qa, $options, $i, $localvars, $sub);
        $subqreplaced .= $sub->feedbackimage;

        $subqreplaced .= $this->part_feedback($i, $qa, $question, $options);
        return html_writer::tag('div', $subqreplaced , array('class' => 'formulas_part'));
    }

    // Return the object containing all options that affect the display of part i.
    public function get_subquestion_all_options($qa, $options, $i) {
        $question = $qa->get_question();
        $part = $question->parts[$i];

        $sub = new StdClass;
        $sub->readonlyattribute = $options->readonly ? 'readonly="readonly"' : '';
        $sub->showanswers = $options->correctness && $options->readonly;

        $response = $qa->get_last_qt_data();
        $question->rationalize_responses($response);      // May throw if the subqtext changed.
        $checkunit = new answer_unit_conversion;

        list( $sub->anscorr, $sub->unitcorr) = $question->grade_responses_individually($part, $response, $checkunit);
        $sub->fraction = $sub->anscorr * $sub->unitcorr ? 1 : (1-$part->unitpenalty);

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
        } else {  // There should be no feedback if options->feedback is not set.
            $sub->feedbackimage = '';
            $sub->feedbackclass = '';
            $sub->unitfeedbackclass = '';
            $sub->boxfeedbackclass = '';
        }
        return $sub;
    }

    // Return the subquestion text with variables replaced by their values.
    public function get_subquestion_formulation(question_attempt $qa, question_display_options $options, $i, $vars, $sub) {
        $question = $qa->get_question();
        $part = &$question->parts[$i];
        $localvars = $question->get_local_variables($part);
        $subqreplaced = $question->formulas_format_text($localvars, $part->subqtext,
                $part->subqtextformat, $qa, 'qtype_formulas', 'answersubqtext', $part->id, false);
        $A = $sub->showanswers ? $question->get_correct_responses_individually($part) : null;

        $types = array(0 => 'number', 10 => 'numeric', 100 => 'numerical_formula', 1000 => 'algebraic_formula');
        $gradingtype = ($part->answertype!=10 && $part->answertype!=100 && $part->answertype!=1000) ? 0 : $part->answertype;
        $gtype = $types[$gradingtype];

        // Get the set of defined placeholders and their options, also missing placeholders are appended at the end.
        // Options are no more used as they were not documented anywhere but pattern left as is for compatibility.
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

        // If {_0} and {_u} are adjacent to each other and there is only one number in the answer, "concatenate" them together in a combined input box.
        if ($part->numbox == 1 && (strlen($part->postunit) != 0) && strpos($subqreplaced, "{_0}{_u}") !== false && $gradingtype != 1000) {
            $popup = $this->get_answers_popup($j, (isset($A) ? $A["${i}_0"].$A["${i}_1"] : ''));
            $inputbox = '<input type="text" maxlength="128" class="formulas_'.$gtype.'_unit '.$sub->feedbackclass.'" ';
            $inputbox .= $options->readonly ? 'readonly="readonly"' : '';
            $inputbox .= ' title="'
                .get_string($gtype.($part->postunit=='' ? '' : '_unit'), 'qtype_formulas').'"'
                .' name="'.$qa->get_qt_field_name("${i}_").'"'
                .' value="'. $qa->get_last_qt_var("${i}_") .'" '.$popup.'/>';
                // .' value="'. s($qa->get_last_qt_var("${i}_0").$qa->get_last_qt_var("${i}_1"), true) .'" '.$popup.'/>';
            $subqreplaced = str_replace("{_0}{_u}", $inputbox, $subqreplaced);
        }

        // Get the set of string for each candidate input box {_0}, {_1}, ..., {_u}.
        $inputboxes = array();
        foreach (range(0, $part->numbox) as $j) {    // Replace the input box for each placeholder {_0}, {_1} ...
            $placeholder = ($j == $part->numbox) ? "_u" : "_$j";    // The last one is unit.
            $var_name =  "${i}_$j";
            $name = $qa->get_qt_field_name($var_name);
            $response = $qa->get_last_qt_var($var_name);

            $popup = $this->get_answers_popup($j, (isset($A) ? $A[$var_name] : ''));
            $inputboxes[$placeholder] = '';
            if ($j == $part->numbox) {
                // Check if it's an unit placeholder.
                $inputboxes[$placeholder] = (strlen($part->postunit) == 0) ? '' :
                        '<input type="text" maxlength="128" class="'.'formulas_unit '.$sub->unitfeedbackclass.'" '.$sub->readonlyattribute.' title="'
                        .get_string('unit', 'qtype_formulas').'"'.' name="'.$name.'" value="'.$response.'" '.$popup.'/>';
            } else {
                $inputboxes[$placeholder] = '<input type="text" maxlength="128" class="'.'formulas_'.$gtype.' '.$sub->boxfeedbackclass.'" '.$sub->readonlyattribute.' title="'
                        .get_string($gtype, 'qtype_formulas').'"'.' name="'.$name.'" value="'.$response.'" '.$popup.'/>';
            }
        }

        foreach ($inputboxes as $placeholder => $replacement) {
            $subqreplaced = preg_replace('/'.$boxes[$placeholder]->pattern.'/', $replacement, $subqreplaced, 1);
        }
        return $subqreplaced;
    }

    // Return the popup with correct answer for the input field.
    public function get_answers_popup($i, $answer) {
        if ($answer === '') {
            return '';  // No popup if no answer.
        }
        $strfeedbackwrapped = s(get_string('modelanswer', 'qtype_formulas'));
        $answer = s(str_replace(array("\\", "'"), array("\\\\", "\\'"), $answer));
        $code = "var a='$answer'; try{ a=this.formulas.common.fn[this.formulas.self.func](this.formulas.common.fn,a); } catch(e) {} ";
        return " onmouseover=\"$code return overlib(a, MOUSEOFF, CAPTION, '$strfeedbackwrapped', FGCOLOR, '#FFFFFF');\" ".
            " onmouseout=\"return nd();\" ";
    }


    /**
     * Generate an automatic description of the correct response to this question.
     * Not all question types can do this. If it is not possible, this method
     * should just return an empty string.
     *
     * @param question_attempt $qa the question attempt to display.
     * @return string HTML fragment.
     */
    public function correct_response(question_attempt $qa) {
        $question = $qa->get_question();
        $correctanswer = array();
        foreach ($question->parts as $i => $part) {
            $tmp = $question->get_correct_responses_individually($part);
            if ($part->part_has_combined_unit_field()) {
                $correctanswer[] = implode(' ', $tmp);
            } else {
                if (!$part->part_has_separate_unit_field()) {
                    unset($tmp["${i}_" .(count($tmp)-1)]);
                }
                $correctanswer[] = implode(', ', $tmp);
            }
        }
        $correctanswer = implode(' | ', $correctanswer);
        return get_string('correctansweris', 'qtype_formulas', $correctanswer);
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
    protected function part_feedback($i, question_attempt $qa,
            question_definition $question,
            question_display_options $options) {
        $err = '';
        $feedback = '';
        $gradingdetails = '';

        $state = $qa->get_state();
        // Only show feedback if response is wrong (will be corrected later for adaptive behaviour).
        $showfeedback = $options->feedback && $state == question_state::$gradedwrong;

        if ($qa->get_behaviour_name() == 'adaptivemultipart') {
            // This is rather a hack, but it will probably work.
            $renderer = $this->page->get_renderer('qbehaviour_adaptivemultipart');
            $details = $qa->get_behaviour()->get_part_mark_details("$i");
            $fraction = $qa->get_last_behaviour_var("_fraction_{$i}");
            $gradingdetails = $renderer->render_adaptive_marks($details, $options);
            $showfeedback = $details->state == question_state::$gradedwrong;
        }

        if ($showfeedback) {
            $part = $question->parts[$i];
            $localvars = $question->get_local_variables($part);
            $feedbacktext = $question->formulas_format_text($localvars, $part->feedback, FORMAT_HTML, $qa, 'qtype_formulas', 'answerfeedback', $i, false);
            if ($feedbacktext) {
                $feedback = html_writer::tag('div', $feedbacktext , array('class' => 'feedback formulas_local_feedback'));
            }
        }
        return html_writer::nonempty_tag('div',
                $err . $feedback . $gradingdetails,
                array('class' => 'formulaspartfeedback formulaspartfeedback-' . $i));
    }
}
