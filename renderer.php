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

/**
 * Formulas question renderer class.
 *
 * @package    qtype_formulas
 * @copyright  2009 The Open University
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


/**
 * Base class for generating the bits of output for formulas questions.
 *
 * @copyright  2009 The Open University
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class qtype_formulas_renderer extends qtype_with_combined_feedback_renderer {

    /**
     * Generate the display of the formulation part of the question. This is the
     * area that contains the question text, and the controls for students to
     * input their answers. Once the question is answered, it will contain the green tick
     * or the red cross and the part's general / combined feedback.
     *
     * @param question_attempt $qa the question attempt to display.
     * @param question_display_options $options controls what should and should not be displayed.
     * @return ?string HTML fragment.
     */
    public function formulation_and_controls(question_attempt $qa, question_display_options $options): ?string {
        // First, fetch the instantiated question from the attempt.
        /** @var qtype_formulas_question $question */
        $question = $qa->get_question();

        if (count($question->textfragments) !== $question->numparts + 1) {
            $this->output->notification(get_string('error_question_damaged', 'qtype_formulas'), 'error');
            return null;
        }

        $questiontext = '';
        foreach ($question->parts as $part) {
            $questiontext .= $question->format_text(
                $question->textfragments[$part->partindex],
                $question->questiontextformat,
                $qa,
                'question',
                'questiontext',
                $question->id,
                false
            );
            $questiontext .= $this->part_formulation_and_controls($qa, $options, $part);
        }
        $questiontext .= $question->format_text(
            $question->textfragments[$question->numparts],
            $question->questiontextformat,
            $qa,
            'question',
            'questiontext',
            $question->id,
            false
        );

        $result = html_writer::tag('div', $questiontext, ['class' => 'qtext']);
        if ($qa->get_state() == question_state::$invalid) {
            $result .= html_writer::nonempty_tag(
                'div',
                $question->get_validation_error($qa->get_last_qt_data()),
                ['class' => 'validationerror']
            );
        }
        return $result;
    }

    /**
     * Return HTML that needs to be included in the page's <head> when this
     * question is used.
     *
     * @param question_attempt $qa question attempt that will be displayed on the page
     * @return string HTML fragment
     */
    public function head_code(question_attempt $qa) {
        global $CFG;
        $this->page->requires->js_call_amd('qtype_formulas/answervalidation', 'init');

        // Include backwards-compatibility layer for Bootstrap 4 data attributes, if available.
        // We may safely assume that if the uncompiled version is there, the minified one exists as well.
        if (file_exists($CFG->dirroot . '/theme/boost/amd/src/bs4-compat.js')) {
            $this->page->requires->js_call_amd('theme_boost/bs4-compat', 'init');
        }

        return '';
    }

    /**
     * Return the part text, controls, grading details and feedbacks.
     *
     * @param question_attempt $qa question attempt that will be displayed on the page
     * @param question_display_options $options
     * @param qtype_formulas_part $part
     * @return void
     */
    public function part_formulation_and_controls(question_attempt $qa, question_display_options $options,
            qtype_formulas_part $part) {

        $partoptions = clone $options;
        // If using adaptivemultipart behaviour, adjust feedback display options for this part.
        if ($qa->get_behaviour_name() === 'adaptivemultipart') {
            $qa->get_behaviour()->adjust_display_options_for_part($part->partindex, $partoptions);
        }
        $sub = $this->get_part_image_and_class($qa, $partoptions, $part);

        $output = $this->get_part_formulation(
            $qa,
            $partoptions,
            $part->partindex,
            $sub
        );
        // Place for the right/wrong feeback image or appended at part's end.
        // TODO: this is not documented anywhere.
        if (strpos($output, '{_m}') !== false) {
            $output = str_replace('{_m}', $sub->feedbackimage, $output);
        } else {
            $output .= $sub->feedbackimage;
        }

        $feedback = $this->part_combined_feedback($qa, $partoptions, $part, $sub->fraction);
        $feedback .= $this->part_general_feedback($qa, $partoptions, $part);
        // If one of the part's coordinates is a MC or select question, the correct answer
        // stored in the database is not the right answer, but the index of the right answer,
        // so in that case, we need to calculate the right answer.
        if ($partoptions->rightanswer) {
            $feedback .= $this->part_correct_response($part->partindex, $qa);
        }
        $output .= html_writer::nonempty_tag(
            'div',
            $feedback,
            ['class' => 'formulaspartoutcome']
        );
        return html_writer::tag('div', $output , ['class' => 'formulaspart']);
    }

    /**
     * Return class and image for the part feedback.
     *
     * @param question_attempt $qa
     * @param question_display_options $options
     * @param qtype_formulas_part $part
     * @return object
     */
    public function get_part_image_and_class($qa, $options, $part) {
        $question = $qa->get_question();

        $sub = new StdClass;

        $response = $qa->get_last_qt_data();
        $response = $question->normalize_response($response);

        list('answer' => $answergrade, 'unit' => $unitcorrect) = $part->grade($response);

        $sub->fraction = $answergrade;
        if ($unitcorrect === false) {
            $sub->fraction *= (1 - $part->unitpenalty);
        }

        // Get the class and image for the feedback.
        if ($options->correctness) {
            $sub->feedbackimage = $this->feedback_image($sub->fraction);
            $sub->feedbackclass = $this->feedback_class($sub->fraction);
            if ($part->unitpenalty >= 1) { // All boxes must be correct at the same time, so they are of the same color.
                $sub->unitfeedbackclass = $sub->feedbackclass;
                $sub->boxfeedbackclass = $sub->feedbackclass;
            } else {  // Show individual color, all four color combinations are possible.
                $sub->unitfeedbackclass = $this->feedback_class($unitcorrect);
                $sub->boxfeedbackclass = $this->feedback_class($answergrade);
            }
        } else {  // There should be no feedback if options->correctness is not set for this part.
            $sub->feedbackimage = '';
            $sub->feedbackclass = '';
            $sub->unitfeedbackclass = '';
            $sub->boxfeedbackclass = '';
        }
        return $sub;
    }

    /**
     * Format given number according to numbering style, e. g. abc or 123.
     *
     * @param int $num number
     * @param string $style style to render the number in, acccording to {@see qtype_multichoice::get_numbering_styles()}
     * @return string number $num in the requested style
     */
    protected function number_in_style($num, $style) {
        switch ($style) {
            case 'abc':
                $number = chr(ord('a') + $num);
                break;
            case 'ABCD':
                $number = chr(ord('A') + $num);
                break;
            case '123':
                $number = $num + 1;
                break;
            case 'iii':
                $number = question_utils::int_to_roman($num + 1);
                break;
            case 'IIII':
                $number = strtoupper(question_utils::int_to_roman($num + 1));
                break;
            case 'none':
                return '';
            default:
                // Default similar to none for compatibility with old questions.
                return '';
        }
        return $number . '. ';
    }

    /**
     * Return the part's text with variables replaced by their values.
     *
     * @param question_attempt $qa
     * @param question_display_options $options
     * @param int $i part index
     * @param object $sub class and image for the part feedback
     * @return string
     */
    public function get_part_formulation(question_attempt $qa, question_display_options $options, $i, $sub) {
        /** @var qype_formulas_question $question */
        $question = $qa->get_question();
        $part = &$question->parts[$i];

        // Clone the part's evaluator and remove special variables like _0 etc., because they must
        // not be substituted here; otherwise, we would lose input boxes.
        $evaluator = clone $part->evaluator;
        $evaluator->remove_special_vars();
        $text = $evaluator->substitute_variables_in_text($part->subqtext);

        $subqreplaced = $question->format_text($text,
                $part->subqtextformat, $qa, 'qtype_formulas', 'answersubqtext', $part->id, false);
        $types = [0 => 'number', 10 => 'numeric', 100 => 'numerical_formula', 1000 => 'algebraic_formula'];
        $gradingtype = ($part->answertype != 10 && $part->answertype != 100 && $part->answertype != 1000) ? 0 : $part->answertype;
        $gtype = $types[$gradingtype];

        // Get the set of defined placeholders and their options.
        $boxes = $part->scan_for_answer_boxes($subqreplaced);
        // Append missing placholders at the end of part.
        foreach (range(0, $part->numbox) as $j) {
            $placeholder = ($j == $part->numbox) ? "_u" : "_$j";
            if (!array_key_exists($placeholder, $boxes)) {
                $boxes[$placeholder] = ['placeholder' => "{".$placeholder."}", 'options' => '', 'dropdown' => false];
                $subqreplaced .= "{".$placeholder."}";  // Appended at the end.
            }
        }

        // If part has combined unit answer input.
        if ($part->has_combined_unit_field()) {
            $variablename = "{$i}_";
            $currentanswer = $qa->get_last_qt_var($variablename);
            $inputname = $qa->get_qt_field_name($variablename);
            $title = get_string($gtype . ($part->postunit == '' ? '' : '_unit'), 'qtype_formulas');
            $inputattributes = [
                'type' => 'text',
                'data-answertype' => $part->answertype,
                'data-withunit' => '1',
                'name' => $inputname,
                'data-toggle' => 'tooltip',
                'data-title' => $title,
                'title' => $title,
                'value' => $currentanswer,
                'id' => $inputname,
                'class' => 'form-control formulas_' . $gtype . '_unit ' . $sub->feedbackclass,
                'maxlength' => 128,
                'aria-labelledby' => 'lbl_' . str_replace(':', '__', $inputname),
            ];

            if ($options->readonly) {
                $inputattributes['readonly'] = 'readonly';
            }
            // Create a meaningful label for accessibility.
            $a = new stdClass();
            $a->part = $i + 1;
            $a->numanswer = '';
            if ($question->numparts == 1) {
                $label = get_string('answercombinedunitsingle', 'qtype_formulas', $a);
            } else {
                $label = get_string('answercombinedunitmulti', 'qtype_formulas', $a);
            }
            $input = html_writer::tag(
                'label',
                $label,
                [
                    'class' => 'subq accesshide',
                    'for' => $inputattributes['id'],
                    'id' => 'lbl_' . str_replace(':', '__', $inputattributes['id']),
                ]
            );
            $input .= html_writer::empty_tag('input', $inputattributes);
            $subqreplaced = str_replace("{_0}{_u}", $input, $subqreplaced);
        }

        // Get the set of string for each candidate input box {_0}, {_1}, ..., {_u}.
        $inputs = [];
        foreach (range(0, $part->numbox) as $j) {    // Replace the input box for each placeholder {_0}, {_1} ...
            $placeholder = ($j == $part->numbox) ? "_u" : "_$j";    // The last one is unit.
            $variablename = "{$i}_$j";
            $currentanswer = $qa->get_last_qt_var($variablename);
            $inputname = $qa->get_qt_field_name($variablename);
            $title = get_string($placeholder == '_u' ? 'unit' : $gtype, 'qtype_formulas');
            $inputattributes = [
                'name' => $inputname,
                'value' => $currentanswer,
                'id' => $inputname,
                'data-toggle' => 'tooltip',
                'data-title' => $title,
                'title' => $title,
                'maxlength' => 128,
                'aria-labelledby' => 'lbl_' . str_replace(':', '__', $inputname),
            ];
            if ($options->readonly) {
                $inputattributes['readonly'] = 'readonly';
            }

            $stexts = null;
            if (strlen($boxes[$placeholder]['options']) != 0) { // Then it's a multichoice answer..
                try {
                    $stexts = $part->evaluator->export_single_variable($boxes[$placeholder]['options']);
                } catch (Exception $e) {
                    // TODO: use non-capturing catch.
                    unset($e);
                }
            }
            // Coordinate as multichoice options.
            if ($stexts != null) {
                if ($boxes[$placeholder]['dropdown']) {
                    // Select menu.
                    if ($options->readonly) {
                        $inputattributes['disabled'] = 'disabled';
                    }
                    $choices = [];
                    foreach ($stexts->value as $x => $mctxt) {
                        $choices[$x] = $question->format_text($mctxt, $part->subqtextformat , $qa,
                                'qtype_formulas', 'answersubqtext', $part->id, false);
                    }
                    unset($inputattributes['data-toggle']);
                    unset($inputattributes['data-title']);
                    $select = html_writer::select($choices, $inputname,
                            $currentanswer, ['' => ''], $inputattributes);
                    $output = html_writer::start_tag('span', ['class' => 'formulas_menu']);
                    $a = new stdClass();
                    $a->numanswer = $j + 1;
                    $a->part = $i + 1;
                    if (count($question->parts) > 1) {
                        $labeltext = get_string('answercoordinatemulti', 'qtype_formulas', $a);
                    } else {
                        $labeltext = get_string('answercoordinatesingle', 'qtype_formulas', $a);
                    }
                    $output .= html_writer::tag(
                        'label',
                        $labeltext,
                        [
                            'class' => 'subq accesshide',
                            'for' => $inputattributes['id'],
                            'id' => 'lbl_' . str_replace(':', '__', $inputattributes['id']),
                        ]
                    );
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
                        $mctxt = html_writer::span($this->number_in_style($x, $question->answernumbering), 'answernumber')
                                . $question->format_text($mctxt, $part->subqtextformat , $qa,
                                'qtype_formulas', 'answersubqtext', $part->id, false);
                        $inputattributes['id'] = $inputname.'_'.$x;
                        $inputattributes['value'] = $x;
                        $inputattributes['aria-labelledby'] = 'lbl_' . str_replace(':', '__', $inputattributes['id']);
                        $isselected = ($currentanswer != '' && $x == $currentanswer);
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
                        unset($inputattributes['data-toggle']);
                        unset($inputattributes['data-title']);
                        $output .= html_writer::empty_tag('input', $inputattributes);
                        $output .= html_writer::tag(
                            'label',
                            $mctxt,
                            [
                                'for' => $inputattributes['id'],
                                'class' => 'm-l-1',
                                'id' => 'lbl_' . str_replace(':', '__', $inputattributes['id']),
                            ]
                        );
                        $output .= $this->choice_wrapper_end();
                    }
                    $output .= $this->all_choices_wrapper_end();
                    $inputs[$placeholder] = $output;
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
                    $inputattributes['class'] = 'form-control formulas_unit '.$sub->unitfeedbackclass;
                    $inputattributes['data-title'] = get_string('unit', 'qtype_formulas');
                    $inputattributes['data-toggle'] = 'tooltip';
                    $inputattributes['data-answertype'] = 'unit';
                    $a = new stdClass();
                    $a->part = $i + 1;
                    $a->numanswer = $j + 1;
                    if ($question->numparts == 1) {
                        $label = get_string('answerunitsingle', 'qtype_formulas', $a);
                    } else {
                        $label = get_string('answerunitmulti', 'qtype_formulas', $a);
                    }
                    $inputs[$placeholder] = html_writer::tag(
                        'label',
                        $label,
                        [
                            'class' => 'subq accesshide',
                            'for' => $inputattributes['id'],
                            'id' => 'lbl_' . str_replace(':', '__', $inputattributes['id']),
                        ]
                    );
                    $inputs[$placeholder] .= html_writer::empty_tag('input', $inputattributes);
                }
            } else {
                $inputattributes['title'] = get_string($gtype, 'qtype_formulas');
                $inputattributes['class'] = 'form-control formulas_'.$gtype.' '.$sub->boxfeedbackclass;
                $inputattributes['data-toggle'] = 'tooltip';
                $inputattributes['data-title'] = get_string($gtype, 'qtype_formulas');
                $inputattributes['aria-labelledby'] = 'lbl_' . str_replace(':', '__', $inputattributes['id']);
                $inputattributes['data-answertype'] = $part->answertype;
                $inputattributes['data-withunit'] = '0';
                $a = new stdClass();
                $a->part = $i + 1;
                $a->numanswer = $j + 1;
                if ($part->numbox == 1) {
                    if ($question->numparts == 1) {
                        $label = get_string('answersingle', 'qtype_formulas', $a);
                    } else {
                        $label = get_string('answermulti', 'qtype_formulas', $a);
                    }
                } else {
                    if ($question->numparts == 1) {
                        $label = get_string('answercoordinatesingle', 'qtype_formulas', $a);
                    } else {
                        $label = get_string('answercoordinatemulti', 'qtype_formulas', $a);
                    }
                }
                $inputs[$placeholder] = html_writer::tag(
                    'label',
                    $label,
                    [
                        'class' => 'subq accesshide',
                        'for' => $inputattributes['id'],
                        'id' => 'lbl_' . str_replace(':', '__', $inputattributes['id']),
                    ]
                );
                $inputs[$placeholder] .= html_writer::empty_tag('input', $inputattributes);
            }
        }

        foreach ($inputs as $placeholder => $replacement) {
            $subqreplaced = preg_replace('/'.$boxes[$placeholder]['placeholder'].'/', $replacement, $subqreplaced, 1);
        }
        return $subqreplaced;
    }

    /**
     * Generate HTML code to be included before each choice in multiple choice questions.
     *
     * @param string $class class attribute value
     * @return string
     */
    protected function choice_wrapper_start($class) {
        return html_writer::start_tag('div', ['class' => $class]);
    }

    /**
     * Generate HTML code to be included after each choice in multiple choice questions.
     *
     * @return string
     */
    protected function choice_wrapper_end() {
        return html_writer::end_tag('div');
    }

    /**
     * Generate HTML code to be included before all choices in multiple choice questions.
     *
     * @return string
     */
    protected function all_choices_wrapper_start() {
        return html_writer::start_tag('div', ['class' => 'multichoice_answer']);
    }

    /**
     * Generate HTML code to be included after all choices in multiple choice questions.
     *
     * @return string
     */
    protected function all_choices_wrapper_end() {
        return html_writer::end_tag('div');
    }

    /**
     * Correct response for the question. This is not needed for the Formulas question, because
     * answers are relative to parts.
     *
     * @param question_attempt $qa the question attempt to display
     * @return string empty string
     */
    public function correct_response(question_attempt $qa) {
        return '';
    }

    /**
     * Generate an automatic description of the correct response for a given part.
     *
     * @param int $i part index
     * @param question_attempt $qa question attempt to display
     * @return string HTML fragment
     */
    public function part_correct_response($i, question_attempt $qa) {
        /** @var qtype_formulas_question $question */
        $question = $qa->get_question();
        $answers = $question->parts[$i]->get_correct_response(true);
        $answertext = implode(', ', $answers);

        if ($question->parts[$i]->answernotunique) {
            $string = 'correctansweris';
        } else {
            $string = 'uniquecorrectansweris';
        }
        return html_writer::nonempty_tag('div', get_string($string, 'qtype_formulas', $answertext),
                    ['class' => 'formulaspartcorrectanswer']);
    }

    /**
     * Generate a brief statement of how many sub-parts of this question the
     * student got right.
     * @param question_attempt $qa the question attempt to display.
     * @return string HTML fragment.
     */
    protected function num_parts_correct(question_attempt $qa) {
        $response = $qa->get_last_qt_data();
        if (!$qa->get_question()->is_gradable_response($response)) {
            return '';
        }
        $numright = $qa->get_question()->get_num_parts_right($response);
        if ($numright[0] === 1) {
            return get_string('yougotoneright', 'qtype_formulas');
        } else {
            return get_string('yougotnright', 'qtype_formulas', $numright[0]);
        }
    }

    /**
     * We need to owerwrite this method to replace global variables by their value
     *
     * @param question_attempt $qa the question attempt to display
     * @param question_hint $hint the hint to be shown
     * @return string HTML fragment
     */
    protected function hint(question_attempt $qa, question_hint $hint) {
        /** @var qtype_formulas_question $question */
        $question = $qa->get_question();
        $hint->hint = $question->evaluator->substitute_variables_in_text($hint->hint);

        return html_writer::nonempty_tag('div', $qa->get_question()->format_hint($hint, $qa), ['class' => 'hint']);
    }

    /**
     * Generate HTML fragment for the question's combined feedback.
     *
     * @param question_attempt $qa question attempt being displayed
     * @return string
     */
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
            $feedback .= $question->format_text($question->$field, $question->$format,
                    $qa, 'question', $field, $question->id, false);
        }

        return $feedback;
    }

    /**
     * Generate the specific feedback. This is feedback that varies according to
     * the response the student gave.
     *
     * @param question_attempt $qa question attempt being displayed
     * @return string
     */
    public function specific_feedback(question_attempt $qa) {
        return $this->combined_feedback($qa);
    }

    /**
     * Gereate the part's general feedback. This is feedback is shown to all students.
     *
     * @param question_attempt $qa question attempt being displayed
     * @param question_display_options $options controls what should and should not be displayed
     * @param qtype_formulas_part $part the question part
     * @return string HTML fragment
     */
    protected function part_general_feedback(question_attempt $qa, question_display_options $options, qtype_formulas_part $part) {
        if ($part->feedback == '') {
            return '';
        }

        $feedback = '';
        $gradingdetails = '';
        $question = $qa->get_question();
        $state = $qa->get_state();

        if ($qa->get_behaviour_name() == 'adaptivemultipart') {
            // This is rather a hack, but it will probably work.
            $renderer = $this->page->get_renderer('qbehaviour_adaptivemultipart');
            $details = $qa->get_behaviour()->get_part_mark_details($part->partindex);
            $gradingdetails = $renderer->render_adaptive_marks($details, $options);
            $state = $details->state;
        }
        $showfeedback = $options->feedback && $state->get_feedback_class() != '';
        if ($showfeedback) {
            // Clone the part's evaluator and substitute local / grading vars first.
            $evaluator = clone $part->evaluator;
            $feedbacktext = $evaluator->substitute_variables_in_text($part->feedback);

            $feedbacktext = $question->format_text(
              $feedbacktext,
              FORMAT_HTML,
              $qa,
              'qtype_formulas',
              'answerfeedback',
              $part->id,
              false
            );
            $feedback = html_writer::tag('div', $feedbacktext , ['class' => 'feedback formulaslocalfeedback']);
            return html_writer::nonempty_tag('div', $feedback . $gradingdetails,
                    ['class' => 'formulaspartfeedback formulaspartfeedback-' . $part->partindex]);
        }
        return '';
    }

    /**
     * Generate HTML fragment for the part's combined feedback.
     *
     * @param question_attempt $qa question attempt being displayed
     * @param question_display_options $options controls what should and should not be displayed
     * @param qtype_formulas_part $part the question part
     * @param float $fraction the obtained grade
     * @return string HTML fragment
     */
    protected function part_combined_feedback(
        question_attempt $qa,
        question_display_options $options,
        qtype_formulas_part $part,
        float $fraction
    ) {
        $feedback = '';
        $showfeedback = false;
        $gradingdetails = '';
        $question = $qa->get_question();
        $state = $qa->get_state();
        $feedbackclass = $state->get_feedback_class();

        if ($qa->get_behaviour_name() == 'adaptivemultipart') {
            // This is rather a hack, but it will probably work.
            $renderer = $this->page->get_renderer('qbehaviour_adaptivemultipart');
            $details = $qa->get_behaviour()->get_part_mark_details($part->partindex);
            $feedbackclass = $details->state->get_feedback_class();
        } else {
            $state = question_state::graded_state_for_fraction($fraction);
            $feedbackclass = $state->get_feedback_class();
        }
        if ($feedbackclass != '') {
            $showfeedback = $options->feedback;
            $field = 'part' . $feedbackclass . 'fb';
            $format = 'part' . $feedbackclass . 'fbformat';
            if ($part->$field) {
                // Clone the part's evaluator and substitute local / grading vars first.
                $evaluator = clone $part->evaluator;
                $part->$field = $evaluator->substitute_variables_in_text($part->$field);
                $feedback = $question->format_text($part->$field, $part->$format,
                        $qa, 'qtype_formulas', $field, $part->id, false);
            }
        }
        if ($showfeedback && $feedback) {
                $feedback = html_writer::tag('div', $feedback , ['class' => 'feedback formulaslocalfeedback']);
                return html_writer::nonempty_tag('div', $feedback,
                        ['class' => 'formulaspartfeedback formulaspartfeedback-' . $part->partindex]);
        }
        return '';
    }
}
