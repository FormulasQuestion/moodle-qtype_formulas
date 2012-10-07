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
		global $CFG, $PAGE;
	    $PAGE->requires->js('/question/type/formulas/script/quiz.js');
        $PAGE->requires->js('/question/type/formulas/script/formatcheck.js');
        $PAGE->requires->js('/question/type/formulas/overlib/overlib.js', true);
        $PAGE->requires->js('/question/type/formulas/overlib/overlib_cssstyle.js', true);

        $question = $qa->get_question();
 
        try {
            $globalvars = $question->get_global_variables();
            foreach ($question->parts as $i => $part) {
                $question->get_local_variables($part);
            }
        } catch (Exception $e) {
            notify("Error: Question evaluation failure, probably the question is changed and is not checked.");
            return;
        }
        $ss = qtype_formulas::create_subquestion_structure($question->questiontext, $question->parts);
        if (count($ss->pretexts) != $question->numpart) {
            notify("Error: The number of subquestions and number of answer are not the same.");
            return;
        }
        $result = html_writer::tag('script', 'var formulasbaseurl='.json_encode($CFG->wwwroot . '/question/type/' . $question->get_type_name()).';', array('type'=>'text/javascript'));
//        $result .= '<br /> begin 1 <br />';
		$questiontext = '';
        foreach ($question->parts as $i => $part) {
// DEBUG
//            echo "i=$i and part=";var_dump($part);
//            echo "ss=";var_dump($ss);
            $pretext = $question->format_text($question->qv->substitute_variables_in_text($globalvars, $ss->pretexts[$i]), FORMAT_HTML, $qa, 'question', 'questiontext', $question->id, false);
//            var_dump($question);
            $subtext = $this->subquestion_formulation_and_controls($qa, $options, $i);
//            $questiontext .= '<br />begin 2<br />';
			$questiontext .= $pretext . $subtext;
//            $questiontext .= '<br />end 2<br />';
        }
//        $questiontext .= '<br />begin 3<br />';
        $questiontext .= $question->format_text($question->qv->substitute_variables_in_text($globalvars, $ss->posttext), FORMAT_HTML, $qa, 'question', 'questiontext', $question->id, false);
//        $questiontext .= '<br />end 3<br />';
		$result .= html_writer::tag('div', $questiontext, array('class' => 'qtext'));
        if ($qa->get_state() == question_state::$invalid) {
            $result .= html_writer::nonempty_tag('div',
                    $question->get_validation_error($qa->get_last_qt_data()),
                    array('class' => 'validationerror'));
        }
//        $result .= '<br /> end 1<br />';echo "get_expected_data";var_dump($question->get_expected_data());
//          $result .= '<br />' . $question->get_question_summary() . '<br />';
//          var_dump($question->randomsvars);
//            var_dump($qa->get_step_iterator());
        return $result;
    }
	
	/// Return the string of the subquestion text, controls, grading details and feedbacks
    function subquestion_formulation_and_controls($qa, $options, $i) {
		$question = $qa->get_question();
        $sub = $this->get_subquestion_all_options($qa, $options, $i);
        $part = $question->parts[$i];
        $localvars = $question->get_local_variables($part);
        $feedbacktext = '';
        $feedback = '';
        $mark = '';
        $gradinginfo = '';
//        echo " subquestion_formulation_and_controls for part $i ";
//        var_dump($sub);
//        echo "options = ";
//        var_dump($options);
 /*       if ($sub->showfeedback) {
            $feedbacktext = get_string('partiallycorrect','quiz');
            if ($sub->fraction == 1)  $feedbacktext = get_string('correct','quiz');
            if ($sub->fraction == 0)  $feedbacktext = get_string('incorrect','quiz');
            $feedbacktext = html_writer::tag('span', ' ' . $feedbacktext, array('class' => 'formulas_grade '.$sub->feedbackclass));
            $feedback = html_writer::tag('div', $question->format_text($question->qv->substitute_variables_in_text($localvars, $part->feedback), FORMAT_HTML, $qa, 'qtype_formulas', 'answerfeedback', $part->id, false), array('class' => 'feedback formulas_local_feedback'));
        }
        if ($sub->showmark) {
            $csub = clone $sub;
            // la note est dans highestmark  et le barème dans maxmark
            // attention au cas où il n'y a pas encore eu de réponse envoyée !
            foreach ($csub as $key => $s)  if (is_numeric($s))  $csub->$key = round($csub->$key, $options->markdp);
            $mark = html_writer::tag('span', get_string($csub->firsttrial ? 'localmarknotgraded' : 'localmark','qtype_formulas',$csub), array('class' => 'formulas_grade'));   // TODO wrong to correct
            $gradinginfo = !$csub->thirdplustrial ? '' : html_writer::tag('span', get_string('gradinginfo','qtype_formulas',$csub), array('class' => 'formulas_grade '.$csub->feedbackclass));
        }*/
        $subqreplaced = $this->get_subquestion_formulation($qa, $options, $i, $localvars, $sub);
 /*       if (strpos($subqreplaced, '{_m}') !== false) {
            $subqreplaced = str_replace('{_m}', $mark, $subqreplaced);
            $mark = '';    // if the mark placeholder is specified, there is no need to add another one next to submit button
        }
        $subqreplaced .= html_writer::tag('div', $feedback .  $mark . $sub->feedbackimage . $feedbacktext . $gradinginfo , array('class' => 'formulas_submit'));*/
            $result = new stdclass;
            $result->errors = null;
            echo "before";
            $subqreplaced .= $this->part_feedback($i, $qa, $question, $result, $options);
        // adaptive multipart behaviour vars
 /*       $subqreplaced .= "<div> _penalty_{$i} = " .$qa->get_last_behaviour_var("_penalty_{$i}");
        $subqreplaced .= " _fraction_{$i} = " .$qa->get_last_behaviour_var("_fraction_{$i}");
        $subqreplaced .= " _rawfraction_{$i} = " .$qa->get_last_behaviour_var("_rawfraction_{$i}");
        $subqreplaced .= " _tries_{$i} = " .$qa->get_last_behaviour_var("_tries_{$i}");
        // interactive behaviour var
        $subqreplaced .= " _triesleft = " .$qa->get_last_behaviour_var("_triesleft");
        $subqreplaced .= "</div>"; */
        return html_writer::tag('div', $subqreplaced , array('class' => 'formulas_subpart'));
    }

    // return an object that contains all control options, grading details and state dependent strings for the subquestion $i
    function get_subquestion_info($qa, $i, $nested=false) {
        $question = $qa->get_question();

        $sub = new StdClass;

        // get the possible max grade of the current and future submit
        // warning this only works in adaptive behaviour !
        $part = $question->parts[$i];
        $sub->prevtrial = $qa->get_last_behaviour_var("_tries_{$i}");
        $sub->curtrial = $sub->prevtrial+1;
//        echo "part=$i";var_dump($sub);
        $fracs = array();
        $n = 3;
 /*       for ($z = 0; $z < $n; $z++) {
            $tmf = $part->part_get_trial_mark_fraction($sub->prevtrial+$z);
            $sub->maxtrial = $tmf[1];
            if ($sub->prevtrial+$z <= $sub->maxtrial || $sub->maxtrial < 0)  $fracs[] = $tmf[0];
        } */
        
//        $sub->remaintrial = ($sub->maxtrial > 0 ? $sub->maxtrial - $sub->curtrial : -2);
//        $sub->prevmaxfrac = $fracs[0];
        $sub->prevmaxfrac = $qa->get_last_behaviour_var("_fraction_{$i}");
        $sub->prevmaxpercent = round($sub->prevmaxfrac * 100, 1).'%';
        $sub->curmaxfrac = $sub->prevmaxfrac + $question->penalty;
        $sub->curmaxpercent = round($sub->curmaxfrac * 100, 1).'%';
        $sub->nextmaxfrac = $sub->curmaxfrac + $question->penalty;
        $sub->nextmaxpercent = round($sub->nextmaxfrac * 100, 1).'%';
        
        // get information of current status and the grading of the last submit
        $sub->highestmark = $qa->get_last_behaviour_var("_rawfraction_{$i}") * $part->answermark;

        $sub->maxmark = $part->answermark;

        $response = $qa->get_last_qt_data();
        $question->rationalize_responses($response);      // may throw if the subqtext changed
        $checkunit = new answer_unit_conversion;
        
		list( $sub->anscorr, $sub->unitcorr) = $question->grade_responses_individually($part, $response, $checkunit);
        $sub->fraction = $sub->anscorr * $sub->unitcorr ? 1 : (1-$part->unitpenalty);
        $sub->highestmark = $part->answermark * $sub->fraction;
        $question->maxgrade = $question->defaultmark; // TODO understand what this rescaling means ?
        $sub->maxmark = $part->answermark*($question->maxgrade/$question->defaultmark); // rescale
        $sub->rawmark = $sub->maxmark * $sub->fraction;
        $sub->curmark = $sub->rawmark * $sub->prevmaxfrac;   // only used in commented string
        $sub->gradingtype = ($part->answertype!=10 && $part->answertype!=100 && $part->answertype!=1000) ? 0 : $part->answertype;
        $sub->alreadycorrect = $sub->fraction >= 1;
 //       $sub->nofurthertrial = $sub->alreadycorrect || ($sub->maxtrial > 0 && $sub->curtrial > $sub->maxtrial);
        $sub->nofurthertrial = $sub->alreadycorrect;
        $sub->firsttrial = $sub->curtrial <= 1;
        $sub->thirdplustrial = $sub->curtrial >= 3;
        $sub->gradedtogether = $part->unitpenalty >= 1; // whether the answers and unit are treated as one answer       
        
        // get the information of the previous subquestion
        if ($nested)  return $sub;
        $sub->previous = ($i > 0) ? $this->get_subquestion_info($qa, $i-1, true) : null;
//        var_dump($sub);
        return $sub;

    }
    
    /// return the object containing all options that affect the display of the subquestion $i
    function get_subquestion_all_options($qa, $options, $i) {
        $question = $qa->get_question();
        $sub = $this->get_subquestion_info($qa, $i, false);

        // disable if the whole question is readonly, or no more trials, or it has already correct, for this subquestion
        $sub->readonly = $options->readonly || $sub->nofurthertrial;
        $sub->readonlyattribute = $sub->readonly ? 'readonly="readonly"' : '';

        $sub->showmark = $question->showperanswermark;
        $sub->showanswers = $options->correctness && $sub->readonly;
        $sub->showfeedback = $options->feedback;
//        $sub->showfeedback = $options->feedback && !$sub->firsttrial; // TODO wrong only work in adaptive to correct
//        $sub->showfeedback = $options->correctness;

        // get the class and image for the feedback.
        if ($sub->showfeedback) {
            $sub->feedbackimage = $this->feedback_image($sub->fraction);
            $sub->feedbackclass = $this->feedback_class($sub->fraction);
            if ($sub->gradedtogether) { // all boxes must be correct at the same time, so they are of the same color
                $sub->unitfeedbackclass = $sub->feedbackclass;
                $sub->boxfeedbackclass = $sub->feedbackclass;
            }
            else {  // show individual color, all four color combinations are possible
                $sub->unitfeedbackclass = $this->feedback_class($sub->unitcorr);
                $sub->boxfeedbackclass = $this->feedback_class($sub->anscorr);
            }
        }
        else {  // There should be no feedback if showfeedback is not set
            $sub->feedbackimage = '';
            $sub->feedbackclass = '';
            $sub->unitfeedbackclass = '';
            $sub->boxfeedbackclass = '';
        }
//        echo "part=$i ";var_dump($sub);
		return $sub;
    }

	/// return the subquestion text with variables replaced by their values
    function get_subquestion_formulation(question_attempt $qa, question_display_options $options, $i, $vars, $sub) {
		$question = $qa->get_question();
        $part = &$question->parts[$i];
        $localvars = $question->get_local_variables($part);
        $subqreplaced = $question->format_text($question->qv->substitute_variables_in_text($localvars, $part->subqtext),
                $part->subqtextformat, $qa, 'qtype_formulas', 'answersubqtext', $part->id, false);
        $A = $sub->showanswers ? $question->get_correct_responses_individually($part) : null;
        $types = array(0 => 'number', 10 => 'numeric', 100 => 'numerical_formula', 1000 => 'algebraic_formula');
        $gtype = $types[$sub->gradingtype];
        
        // get the set of defined placeholder and its options, also missing placeholder are appended at the end
        $pattern = '\{(_[0-9u][0-9]*)(:[^{}:]+)?(:[^{}:]+)?\}';
        preg_match_all('/'.$pattern.'/', $subqreplaced, $matches);
        $boxes = array();
        foreach ($matches[1] as $j => $match)  if (!array_key_exists($match, $boxes))   // if there is duplication, it will be skipped
            $boxes[$match] = (object)array('pattern' => $matches[0][$j], 'options' => $matches[2][$j], 'stype' => $matches[3][$j]);
        foreach (range(0, $part->numbox) as $j => $notused) {
            $placeholder = ($j == $part->numbox) ? "_u" : "_$j";
            if (!array_key_exists($placeholder,$boxes)) {
                $boxes[$placeholder] = (object)array('pattern' => "{".$placeholder."}", 'options' => '', 'stype' => '');
                $subqreplaced .= "{".$placeholder."}";  // appended at the end
            }
        }
        
        // if {_0} and {_u} are adjacent to each other and there is only one number in the answer, "concatenate" them together into one input box
        if ($part->numbox == 1 && (strlen($part->postunit) != 0) && strpos($subqreplaced, "{_0}{_u}") !== false && $sub->gradingtype != 1000) {
            $popup = $this->get_answers_popup($j, (isset($A) ? $A["${i}_0"].$A["${i}_1"] : ''));
			$inputbox = '<input type="text" maxlength="128" class="formulas_'.$gtype.'_unit '.$sub->feedbackclass.'" '.$sub->readonlyattribute.' title="'
                .get_string($gtype.($part->postunit=='' ? '' : '_unit'),'qtype_formulas').'"'
                .' name="'.$qa->get_qt_field_name("${i}_").'"'
                .' value="'. $qa->get_last_qt_var("${i}_") .'" '.$popup.'/>';
//                .' value="'. s($qa->get_last_qt_var("${i}_0").$qa->get_last_qt_var("${i}_1"), true) .'" '.$popup.'/>';
            $subqreplaced = str_replace("{_0}{_u}", $inputbox, $subqreplaced);
        }
        
        // get the set of string for each candidate input box {_0}, {_1}, ..., {_u}
        $inputboxes = array();
        foreach (range(0,$part->numbox) as $j) {    // replace the input box for each placeholder {_0}, {_1} ...
            $placeholder = ($j == $part->numbox) ? "_u" : "_$j";    // the last one is unit
            $var_name =  "${i}_$j";
            $name = $qa->get_qt_field_name($var_name);
            $response = $qa->get_last_qt_var($var_name);
            
            $stexts = null;
            if (strlen($boxes[$placeholder]->options) != 0)  try { // MC or check box
                $stexts = $this->qv->evaluate_general_expression($vars, substr($boxes[$placeholder]->options,1));
            } catch(Exception $e) {}    // $stexts will be null if evaluation fails
            
            if ($stexts != null) {
                if ($boxes[$placeholder]->stype == ':SL') {
                }
                else {
                    $popup = $this->get_answers_popup($j, (isset($A) ? $stexts->value[$A[$var_name]] : ''));
                    if ($boxes[$placeholder]->stype == ':MCE') {
                        $mc = '<option value="" '.(''==$response?' selected="selected" ':'').'>'.'</option>';
                        foreach ($stexts->value as $x => $mctxt)
                            $mc .= '<option value="'.$x.'" '.((string)$x==$response?' selected="selected" ':'').'>'.$mctxt.'</option>';
                        $inputboxes[$placeholder] = '<select name="'.$name.'" '.$sub->readonlyattribute.' '.$popup.'>' . $mc . '</select>';
                    }
                    else {
                        $mc = '';
                        foreach ($stexts->value as $x => $mctxt) {
                            $mc .= '<tr class="r'.($x%2).'"><td class="c0 control">';
                            $mc .= '<input id="'.$name.'_'.$x.'" name="'.$name.'" value="'.$x.'" type="radio" '.$sub->readonlyattribute.' '.((string)$x==$response?' checked="checked" ':'').'>';
                            $mc .= '</td><td class="c1 text "><label for="'.$name.'_'.$x.'">'.$mctxt.'</label></td>';
                            $mc .= '</tr>';
                        }
                        $inputboxes[$placeholder] = '<table '.$popup.'><tbody>' . $mc . '</tbody></table>';
                    }
                }
                continue;
            }
            
            // Normal answer box with input text
            $popup = $this->get_answers_popup($j, (isset($A) ? $A[$var_name] : ''));
            $inputboxes[$placeholder] = '';
            if ($j == $part->numbox)    // check whether it is a unit placeholder
                $inputboxes[$placeholder] = (strlen($part->postunit) == 0) ? '' :
                    '<input type="text" maxlength="128" class="'.'formulas_unit '.$sub->unitfeedbackclass.'" '.$sub->readonlyattribute.' title="'
                    .get_string('unit','qtype_formulas').'"'.' name="'.$name.'" value="'.$response.'" '.$popup.'/>';
            else
                $inputboxes[$placeholder] = '<input type="text" maxlength="128" class="'.'formulas_'.$gtype.' '.$sub->boxfeedbackclass.'" '.$sub->readonlyattribute.' title="'
                    .get_string($gtype,'qtype_formulas').'"'.' name="'.$name.'" value="'.$response.'" '.$popup.'/>';
        }
        
        // sequential replacement has the issue that the string such as {_0},... cannot be used in the MC, minor issue
        foreach ($inputboxes as $placeholder => $replacement)
            $subqreplaced = preg_replace('/'.$boxes[$placeholder]->pattern.'/', $replacement, $subqreplaced, 1);
        return $subqreplaced;
    }
  
    /// return the popup correct answer for the input field
    function get_answers_popup($i, $answer) {
        if ($answer === '')  return '';  // no popup if no answer
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
        foreach ($question->parts as $i=>$part) {
            $tmp = $question->get_correct_responses_individually($part);
            if ($part->part_has_combined_unit_field()) {
                $correctanswer[] = implode(' ', $tmp);
            } else {
                if (!$part->part_has_separate_unit_field())
                    unset($tmp["${i}_" .(count($tmp)-1)]);
                $correctanswer[] = implode(', ', $tmp);
            }
        }
        $correctanswer = implode(' | ', $correctanswer);
        return get_string('correctansweris', 'qtype_formulas', $correctanswer);
    }
	
	protected function num_parts_correct(question_attempt $qa) {
        $a = new stdClass();
        list($a->num, $a->outof) = $qa->get_question()->get_num_parts_right(
                $qa->get_last_qt_data());
        if (is_null($a->outof)) {
            return '';
        } else {
            return get_string('yougotnright', 'qtype_formulas', $a);
        }
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
            $vfeedback = $question->qv->substitute_variables_in_text($globalvars, $question->$field);
            $feedback .= $question->format_text($vfeedback, $question->$format,
                    $qa, 'question', $field, $question->id);
        }

        return $feedback;
    }

    public function specific_feedback(question_attempt $qa) {
        return $this->combined_feedback($qa);
    }
    
        /**
     * @param string $i the part index.
     * @param question_attempt $qa the question attempt to display.
     * @param question_definition $question the question being displayed.
     * @param stack_potentialresponse_tree_state $result the results to display.
     * @param question_display_options $options controls what should and should not be displayed.
     * @return string nicely formatted feedback, for display.
     */
    protected function part_feedback($i, question_attempt $qa,
            question_definition $question, $result,
            question_display_options $options) {
        $err = '';
        if ($result->errors) {
            $err = $result->errors;
        }

        $gradingdetails = '';
        echo " part_feedback for part $i ";
//        var_dump($qa->get_behaviour_name());
        if (!$result->errors && ($qa->get_behaviour_name() == 'adaptivemultipart')) {
        echo " adaptivemultipart behaviour ";
            // This is rather a hack, but it will probably work.
            $renderer = $this->page->get_renderer('qbehaviour_adaptivemultipart');
            $details = $qa->get_behaviour()->get_part_mark_details("$i");
 echo "part_mark_details=";
 var_dump($details);
            $gradingdetails = $renderer->render_adaptive_marks($details, $options);
        echo " gradingdetails =";
        var_dump($gradingdetails);
        }
//        var_dump($qa);
        $feedback = '';
        if ($options->feedback) {
            $part = $question->parts[$i];
            $localvars = $question->get_local_variables($part);
            $feedbacktext = $question->format_text($question->qv->substitute_variables_in_text($localvars, $part->feedback), FORMAT_HTML, $qa, 'qtype_formulas', 'answerfeedback', $i, false);
            if ($feedbacktext) {
                $feedback = html_writer::tag('div', $feedbacktext , array('class' => 'feedback formulas_local_feedback'));
            }
        }
        return html_writer::nonempty_tag('div',
                $err . $feedback . $gradingdetails,
                array('class' => 'formulaspartfeedback formulaspartfeedback-' . $i));
    }
}
