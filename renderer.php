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

    /** @var string */
    const UNIT_FIELD = 'u';

    /** @var string */
    const COMBINED_FIELD = '';

    /**
     * Generate the display of the formulation part of the question. This is the area that
     * contains the question text and the controls for students to input their answers.
     * Once the question is answered, it will contain the green tick or the red cross and
     * the part's general / combined feedback.
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
        // First, iterate over all parts, put the corresponding fragment of the main question text at the
        // right position, followed by the part's text, input and (if applicable) feedback elements.
        foreach ($question->parts as $part) {
            $questiontext .= $question->format_text(
                $question->textfragments[$part->partindex], $question->questiontextformat,
                $qa, 'question', 'questiontext', $question->id, false
            );
            $questiontext .= $this->part_formulation_and_controls($qa, $options, $part);
        }
        // All parts are done. We now append the final fragment of the main question text. Note that this fragment
        // might be empty.
        $questiontext .= $question->format_text(
            end($question->textfragments), $question->questiontextformat, $qa, 'question', 'questiontext', $question->id, false
        );

        // Pack everything in a <div> and, if the question is in an invalid state, append the appropriate error message
        // at the very end.
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
    public function head_code(question_attempt $qa): string {
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
     * @param question_display_options $options controls what should and should not be displayed
     * @param qtype_formulas_part $part question part
     * @return void
     */
    public function part_formulation_and_controls(question_attempt $qa, question_display_options $options,
            qtype_formulas_part $part): string {

        // The behaviour might change the display options per part, so it is safer to clone them here.
        $partoptions = clone $options;
        if ($qa->get_behaviour_name() === 'adaptivemultipart') {
            $qa->get_behaviour()->adjust_display_options_for_part($part->partindex, $partoptions);
        }

        // Fetch information about the outcome: grade, feedback symbol, CSS class to be used.
        $outcomedata = $this->get_part_feedback_class_and_symbol($qa, $partoptions, $part);

        // First of all, we take the part's question text and its input fields.
        $output = $this->get_part_formulation($qa, $partoptions, $part, $outcomedata);

        // If the user has requested the feedback symbol to be placed at a special position, we
        // do that now. Otherwise, we just append it after the part's text and input boxes.
        if (strpos($output, '{_m}') !== false) {
            $output = str_replace('{_m}', $outcomedata->feedbacksymbol, $output);
        } else {
            $output .= $outcomedata->feedbacksymbol;
        }

        // The part's feedback consists of the combined feedback (correct, partially correct, incorrect -- depending on the
        // outcome) and the general feedback which is given in all cases.
        $feedback = $this->part_combined_feedback($qa, $partoptions, $part, $outcomedata->fraction);
        $feedback .= $this->part_general_feedback($qa, $partoptions, $part);

        // If requested, the correct answer should be appended to the feedback.
        if ($partoptions->rightanswer) {
            $feedback .= $this->part_correct_response($part);
        }

        // Put all feedback into a <div> with the appropriate CSS class and append it to the output.
        $output .= html_writer::nonempty_tag('div', $feedback, ['class' => 'formulaspartoutcome outcome']);

        return html_writer::tag('div', $output , ['class' => 'formulaspart']);
    }

    /**
     * Return class and symbol for the part feedback.
     *
     * @param question_attempt $qa question attempt that will be displayed on the page
     * @param question_display_options $options controls what should and should not be displayed
     * @param qtype_formulas_part $part question part
     * @return stdClass
     */
    public function get_part_feedback_class_and_symbol(question_attempt $qa, question_display_options $options,
            qtype_formulas_part $part): stdClass {
        // Prepare a new object to hold the different elements.
        $result = new stdClass;

        // Fetch the last response data and grade it.
        $response = $qa->get_last_qt_data();
        list('answer' => $answergrade, 'unit' => $unitcorrect) = $part->grade($response);

        // The fraction will later be used to determine which feedback (correct, partially correct or incorrect)
        // to use. We have to take into account a possible deduction for a wrong unit.
        $result->fraction = $answergrade;
        if ($unitcorrect === false) {
            $result->fraction *= (1 - $part->unitpenalty);
        }

        // By default, we add no feedback at all...
        $result->feedbacksymbol = '';
        $result->feedbackclass = '';
        // ... unless correctness is requested in the display options.
        if ($options->correctness) {
            $result->feedbacksymbol = $this->feedback_image($result->fraction);
            $result->feedbackclass = $this->feedback_class($result->fraction);
        }
        return $result;
    }

    /**
     * Format given number according to numbering style, e. g. abc or 123.
     *
     * @param int $num number
     * @param string $style style to render the number in, acccording to {@see qtype_multichoice::get_numbering_styles()}
     * @return string number $num in the requested style
     */
    protected static function number_in_style(int $num, string $style): string {
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
     * Create a set of radio boxes for a multiple choice answer input.
     *
     * @param qtype_formulas_part $part question part
     * @param int|string $answerindex index of the answer (starting at 0) or special value for combined/separate unit field
     * @param question_attempt $qa question attempt that will be displayed on the page
     * @param array $answeroptions array of strings containing the answer options to choose from
     * @param bool $shuffle whether the options should be shuffled
     * @param question_display_options $displayoptions controls what should and should not be displayed
     * @param string $feedbackclass
     * @return string HTML fragment
     */
    protected function create_radio_mc_answer(qtype_formulas_part $part, $answerindex, question_attempt $qa,
            array $answeroptions, bool $shuffle, question_display_options $displayoptions, string $feedbackclass = ''): string {
        /** @var qype_formulas_question $question */
        $question = $qa->get_question();

        $variablename = "{$part->partindex}_{$answerindex}";
        $currentanswer = $qa->get_last_qt_var($variablename);
        $inputname = $qa->get_qt_field_name($variablename);

        $inputattributes['type'] = 'radio';
        $inputattributes['name'] = $inputname;
        if ($displayoptions->readonly) {
            $inputattributes['disabled'] = 'disabled';
        }

        // First, we open a <fieldset> around the entire group of options.
        $output = html_writer::start_tag('fieldset', ['class' => 'multichoice_answer']);

        // Inside the fieldset, we put the accessibility label, following the example of core's multichoice
        // question type, i. e. the label is inside a <span> with class 'sr-only', wrapped in a <legend>.
        $output .= html_writer::start_tag('legend', ['class' => 'sr-only']);
        $output .= html_writer::span(
            $this->generate_accessibility_label_text($answerindex, $part->numbox, $part->partindex, $question->numparts),
            'sr-only'
        );
        $output .= html_writer::end_tag('legend');

        // If needed, shuffle the options while maintaining the keys.
        if ($shuffle) {
            $keys = array_keys($answeroptions);
            shuffle($keys);

            $shuffledoptions = [];
            foreach ($keys as $key) {
                $shuffledoptions[$key] = $answeroptions[$key];
            }
            $answeroptions = $shuffledoptions;
        }

        // Iterate over all options.
        foreach ($answeroptions as $i => $optiontext) {
            $numbering = html_writer::span(self::number_in_style($i, $question->answernumbering), 'answernumber');
            $labeltext = $question->format_text(
                $numbering . $optiontext, $part->subqtextformat , $qa, 'qtype_formulas', 'answersubqtext', $part->id, false
            );

            $inputattributes['id'] = $inputname . '_' . $i;
            $inputattributes['value'] = $i;
            // Class ml-3 is Bootstrap's class for margin-left: 1rem; it used to be m-l-1.
            $label = $this->create_label_for_input($labeltext, $inputattributes['id'], ['class' => 'ml-3']);
            $inputattributes['aria-labelledby'] = $label['id'];

            // We must make sure $currentanswer is not null, because otherwise the first radio box
            // might be selected if there is no answer at all. It seems better to avoid strict equality,
            // because we might compare a string to a number.
            $isselected = ($i == $currentanswer && !is_null($currentanswer));

            // We do not reset the $inputattributes array on each iteration, so we have to add/remove the
            // attribute every time.
            if ($isselected) {
                $inputattributes['checked'] = 'checked';
            } else {
                unset($inputattributes['checked']);
            }

            // Each option (radio box element plus label) is wrapped in its own <div> element.
            $divclass = 'r' . ($i % 2);
            if ($displayoptions->correctness && $isselected) {
                $divclass .= ' ' . $feedbackclass;
            }
            $output .= html_writer::start_div($divclass);

            // Now add the <input> tag and its <label>.
            $output .= html_writer::empty_tag('input', $inputattributes);
            $output .= $label['html'];

            // Close the option's <div>.
            $output .= html_writer::end_div();
        }

        // Close the option group's <fieldset>.
        $output .= html_writer::end_tag('fieldset');

        return $output;
    }

    /**
     * Translate an array containing formatting options into a CSS format string, e. g. from
     * ['w' => '50px', 'bgcol' => 'yellow'] to 'width: 50px; background-color: yellow'. Note:
     * - colors can be defined in 3 or 6 digit hex RGB, in 4 or 8 digit hex RGBA or as CSS named color
     * - widths can be defined as a number followed by the units px, rem or em; if the unit is omitted, rem will be used
     * - alignment can be defined as left, right, center, start or end
     *
     * @param array $options associative array containing options (in our own denomination) and their settings
     * @return string
     */
    protected function get_css_properties(array $options): string {
        // Define some regex pattern.
        $hexcolor = '#([0-9A-F]{8}|[0-9A-F]{6}|[0-9A-F]{3}|[0-9A-F]{4})';
        $namedcolor = '[A-Z]+';
        // We accept floating point numbers with or without a leading integer part and integers.
        // Floating point numbers with a trailing decimal point do not work in all browsers.
        $length = '(\d+\.\d+|\d*\.\d+|\d+)(px|em|rem)?';
        $alignment = 'start|end|left|right|center';

        $styles = [];
        foreach ($options as $name => $value) {
            switch ($name) {
                case 'bgcol':
                    if (!preg_match("/^(($hexcolor)|($namedcolor))$/i", $value)) {
                        break;
                    }
                    $styles[] = "background-color: $value";
                    break;
                case 'txtcol':
                    if (!preg_match("/^(($hexcolor)|($namedcolor))$/i", $value)) {
                        break;
                    }
                    $styles[] = "color: $value";
                    break;
                case 'w':
                    if (!preg_match("/^($length)$/i", $value)) {
                        break;
                    }
                    // If no unit is given, append rem.
                    $styles[] = "width: $value" . (preg_match('/\d$/', $value) ? 'rem' : '');
                    break;
                case 'align':
                    if (!preg_match("/^($alignment)$/i", $value)) {
                        break;
                    }
                    $styles[] = "text-align: $value";
                    break;
            }
        }

        return implode(';', $styles);
    }

    /**
     * Create a <label> element for a given input control (e. g. a text field). Returns the
     * HTML and the label's ID.
     *
     * @param string $text the label's text
     * @param string $inputid ID of the input control for which the label is created
     * @param array $additionalattributes possibility to add custom attributes, attribute name => value
     * @return array 'id' => label's ID to be used in 'aria-labelledby' attribute, 'html' => HTML code
     */
    protected function create_label_for_input(string $text, string $inputid, array $additionalattributes = []): array {
        $labelid = 'lbl_' . str_replace(':', '__', $inputid);
        $attributes = [
            'class' => 'subq accesshide',
            'for' => $inputid,
            'id' => $labelid,
        ];

        // Merging the additional attributes with the default attributes; left array has precedence when
        // using the + operator.
        $attributes = $additionalattributes + $attributes;

        return [
            'id' => $labelid,
            'html' => html_writer::tag('label', $text, $attributes),
        ];
    }

    /**
     * Create a <select> field for a multiple choice answer input.
     *
     * @param qtype_formulas_part $part question part
     * @param int|string $answerindex index of the answer (starting at 0) or special value for combined/separate unit field
     * @param question_attempt $qa question attempt that will be displayed on the page
     * @param array $answeroptions array of strings containing the answer options to choose from
     * @param bool $shuffle whether the options should be shuffled
     * @param question_display_options $displayoptions controls what should and should not be displayed
     * @return string HTML fragment
     */
    protected function create_dropdown_mc_answer(qtype_formulas_part $part, $answerindex, question_attempt $qa,
            array $answeroptions, bool $shuffle, question_display_options $displayoptions): string {
        /** @var qype_formulas_question $question */
        $question = $qa->get_question();

        $variablename = "{$part->partindex}_{$answerindex}";
        $currentanswer = $qa->get_last_qt_var($variablename);
        $inputname = $qa->get_qt_field_name($variablename);

        $inputattributes['name'] = $inputname;
        $inputattributes['value'] = $currentanswer;
        $inputattributes['id'] = $inputname;
        $inputattributes['class'] = 'formulas-select';

        $label = $this->create_label_for_input(
            $this->generate_accessibility_label_text($answerindex, $part->numbox, $part->partindex, $question->numparts),
            $inputname
        );
        $inputattributes['aria-labelledby'] = $label['id'];

        if ($displayoptions->readonly) {
            $inputattributes['disabled'] = 'disabled';
        }

        // First, we open a <span> around the dropdown field and its accessibility label.
        $output = html_writer::start_tag('span', ['class' => 'formulas_menu']);
        $output .= $label['html'];

        // Iterate over all options.
        $entries = [];
        foreach ($answeroptions as $optiontext) {
            $entries[] = $question->format_text(
                $optiontext, $part->subqtextformat , $qa, 'qtype_formulas', 'answersubqtext', $part->id, false
            );
        }

        // If needed, shuffle the options while maintaining the keys.
        if ($shuffle) {
            $keys = array_keys($entries);
            shuffle($keys);

            $shuffledentries = [];
            foreach ($keys as $key) {
                $shuffledentries[$key] = $entries[$key];
            }
            $entries = $shuffledentries;
        }

        $output .= html_writer::select($entries, $inputname, $currentanswer, ['' => ''], $inputattributes);
        $output .= html_writer::end_tag('span');

        return $output;
    }

    /**
     * Generate the right label for the input control, depending on the number of answers in the given part
     * and the number of parts in the question. Special cases (combined field, unit field) are also taken
     * into account. Returns the appropriate string from the language file. Examples are "Answer field for
     * part X", "Answer field X for part Y" or "Answer and unit for part X".
     *
     * @param int|string $answerindex index of the answer (starting at 0) or special value for combined/separate unit field
     * @param int $totalanswers number of answers for the given part
     * @param int $partindex number of the part (starting at 0) in this question
     * @param int $totalparts number of parts in the question
     * @return string localized string
     */
    protected function generate_accessibility_label_text($answerindex, int $totalanswers, int $partindex,
            int $totalparts): string {

        // Some language strings need parameters.
        $labeldata = new stdClass();

        // The language strings start with 'answerunit' for a separate unit field, 'answercombinedunit' for
        // a combined field, 'answercoordinate' for an answer field when there are multiple answers in the
        // part or just 'answer' if there is a single field.
        $labelstring = 'answer';
        if ($answerindex === self::UNIT_FIELD) {
            $labelstring .= 'unit';
        } else if ($answerindex === self::COMBINED_FIELD) {
            $labelstring .= 'combinedunit';
        } else if ($totalanswers > 1) {
            $labelstring .= 'coordinate';
            $labeldata->numanswer = $answerindex + 1;
        }

        // The language strings end with 'multi' for multi-part questions or 'single' for single-part
        // questions.
        if ($totalparts > 1) {
            $labelstring .= 'multi';
            $labeldata->part = $partindex + 1;
        } else {
            $labelstring .= 'single';
        }

        return get_string($labelstring, 'qtype_formulas', $labeldata);
    }

    /**
     * Create an <input> field.
     *
     * @param qtype_formulas_part $part question part
     * @param int|string $answerindex index of the answer (starting at 0) or special value for combined/separate unit field
     * @param question_attempt $qa question attempt that will be displayed on the page
     * @param question_display_options $displayoptions controls what should and should not be displayed
     * @param array $formatoptions associative array 'optionname' => 'value', e. g. 'w' => '50px'
     * @param string $feedbackclass
     * @return string HTML fragment
     */
    protected function create_input_box(qtype_formulas_part $part, $answerindex,
            question_attempt $qa, question_display_options $displayoptions, array $formatoptions = [],
            string $feedbackclass = ''): string {
        /** @var qype_formulas_question $question */
        $question = $qa->get_question();

        // The variable name will be N_ for the (single) combined unit field of part N,
        // or N_M for answer #M in part #N. If #M is equal to the part's numbox (i. e. the
        // number of answers), it is a unit field; note that the fields are numbered starting
        // from 0, so with 3 answers, we have N_0, N_1, N_2 and only use N_3 if there is a
        // unit.
        $variablename = $part->partindex . '_';
        if ($answerindex === self::UNIT_FIELD) {
            $variablename .= $part->numbox;
        } else {
            $variablename .= ($answerindex === self::COMBINED_FIELD ? '' : $answerindex);
        }

        $currentanswer = $qa->get_last_qt_var($variablename);
        $inputname = $qa->get_qt_field_name($variablename);

        // Text fields will have a tooltip attached. The tooltip's content depends on the
        // answer type. Special tooltips exist for combined or separate unit fields.
        switch ($part->answertype) {
            case qtype_formulas::ANSWER_TYPE_NUMERIC:
                $titlestring = 'numeric';
                break;
            case qtype_formulas::ANSWER_TYPE_NUMERICAL_FORMULA:
                $titlestring = 'numerical_formula';
                break;
            case qtype_formulas::ANSWER_TYPE_ALGEBRAIC:
                $titlestring = 'algebraic_formula';
                break;
            case qtype_formulas::ANSWER_TYPE_NUMBER:
            default:
                $titlestring = 'number';
        }
        if ($answerindex === self::COMBINED_FIELD) {
            $titlestring .= '_unit';
        }
        if ($answerindex === self::UNIT_FIELD) {
            $titlestring = 'unit';
        }
        $title = get_string($titlestring, 'qtype_formulas');

        // Fetch the configured default width and use it, if the setting exists. Otherwise,
        // the plugin's default as defined in styles.css will be used. If the user did not
        // specify a unit, we use pixels (px). Note that this is different from the renderer
        // where rem is used in order to allow for the short syntax 'w=3' (3 chars wide).
        $defaultformat = [];
        $defaultwidth = get_config('qtype_formulas', "defaultwidth_{$titlestring}");
        // If the default width has not been set for the current answer box type, $defaultwidth will
        // be false and thus not numeric.
        if (is_numeric($defaultwidth)) {
            $defaultwidthunit = get_config('qtype_formulas', "defaultwidthunit");
            if (!in_array($defaultwidthunit, ['px', 'rem', 'em'])) {
                $defaultwidthunit = 'px';
            }
            $defaultformat = ['w' => $defaultwidth . $defaultwidthunit];
        }
        // Using the union operator the values from the left array will be kept.
        $formatoptions = $formatoptions + $defaultformat;

        $inputattributes = [
            'type' => 'text',
            'name' => $inputname,
            'value' => $currentanswer,
            'id' => $inputname,
            'style' => $this->get_css_properties($formatoptions),

            'data-answertype' => ($answerindex === self::UNIT_FIELD ? 'unit' : $part->answertype),
            'data-withunit' => ($answerindex === self::COMBINED_FIELD ? '1' : '0'),

            'data-toggle' => 'tooltip',
            'data-title' => $title,
            'data-custom-class' => 'qtype_formulas-tooltip',
            'title' => $title,
            'class' => "form-control formulas_{$titlestring} {$feedbackclass}",
            'maxlength' => 128,
        ];

        if ($displayoptions->readonly) {
            $inputattributes['readonly'] = 'readonly';
        }

        $label = $this->create_label_for_input(
            $this->generate_accessibility_label_text($answerindex, $part->numbox, $part->partindex, $question->numparts),
            $inputname
        );
        $inputattributes['aria-labelledby'] = $label['id'];

        $output = $label['html'];
        $output .= html_writer::empty_tag('input', $inputattributes);

        return $output;
    }

    /**
     * Return the part's text with variables replaced by their values.
     *
     * @param question_attempt $qa question attempt that will be displayed on the page
     * @param question_display_options $options controls what should and should not be displayed
     * @param qtype_formulas_part $part question part
     * @param stdClass $sub class and symbol for the part feedback
     * @return string HTML fragment
     */
    public function get_part_formulation(question_attempt $qa, question_display_options $options,
            qtype_formulas_part $part, stdClass $sub): string {
        /** @var qype_formulas_question $question */
        $question = $qa->get_question();

        // Clone the part's evaluator and remove special variables like _0 etc., because they must
        // not be substituted here; otherwise, we would lose input boxes.
        $evaluator = clone $part->evaluator;
        $evaluator->remove_special_vars();
        $text = $evaluator->substitute_variables_in_text($part->subqtext);

        $subqreplaced = $question->format_text(
            $text, $part->subqtextformat, $qa, 'qtype_formulas', 'answersubqtext', $part->id, false
        );

        // Get the set of defined placeholders and their options.
        $boxes = $part->scan_for_answer_boxes($subqreplaced);

        // Append missing placholders at the end of part. We do not put a space before the opening
        // or after the closing brace, in order to get {_0}{_u} for questions with one answer and
        // a unit. This makes sure that the question will receive a combined unit field.
        for ($i = 0; $i <= $part->numbox; $i++) {
            // If no unit has been set, we do not append the {_u} placeholder.
            if ($i == $part->numbox && empty($part->postunit)) {
                continue;
            }
            $placeholder = ($i == $part->numbox) ? '_u' : "_{$i}";
            // If the placeholder does not exist yet, we create it with default settings, i. e. no multi-choice
            // and no styling.
            if (!array_key_exists($placeholder, $boxes)) {
                $boxes[$placeholder] = [
                    'placeholder' => '{' . $placeholder . '}',
                    'options' => '',
                    'dropdown' => false,
                    'format' => [],
                ];
                $subqreplaced .= '{' . $placeholder . '}';
            }
        }

        // If part has combined unit answer input.
        if ($part->has_combined_unit_field()) {
            $combinedfieldhtml = $this->create_input_box(
                $part, self::COMBINED_FIELD, $qa, $options, $boxes[$placeholder]['format'], $sub->feedbackclass
            );
            return str_replace('{_0}{_u}', $combinedfieldhtml, $subqreplaced);
        }

        // Iterate over all boxes again, this time creating the appropriate input control and insert it
        // at the position indicated by the placeholder.
        for ($i = 0; $i <= $part->numbox; $i++) {
            // For normal answer fields, the placeholder is {_N} with N being the number of the
            // answer, starting from 0. The unit field, if there is one, comes last and has the
            // {_u} placeholder.
            if ($i < $part->numbox) {
                $answerindex = $i;
                $placeholder = "_$i";
            } else if (!empty($part->postunit)) {
                $answerindex = self::UNIT_FIELD;
                $placeholder = '_u';
            }

            // If the user has requested a multi-choice element, they must have specified an array
            // variable containing the options. We try to fetch that variable. If this fails, we
            // simply continue and build a text field instead.
            $optiontexts = null;
            if (!empty($boxes[$placeholder]['options'])) {
                try {
                    $optiontexts = $part->evaluator->export_single_variable($boxes[$placeholder]['options']);
                } catch (Exception $e) {
                    // TODO: use non-capturing catch.
                    unset($e);
                }
            }

            if ($optiontexts === null) {
                $inputfieldhtml = $this->create_input_box(
                    $part, $answerindex, $qa, $options, $boxes[$placeholder]['format'], $sub->feedbackclass
                );
            } else if ($boxes[$placeholder]['dropdown']) {
                $inputfieldhtml = $this->create_dropdown_mc_answer(
                    $part, $i, $qa, $optiontexts->value, $boxes[$placeholder]['shuffle'], $options
                );
            } else {
                $inputfieldhtml = $this->create_radio_mc_answer(
                    $part, $i, $qa, $optiontexts->value, $boxes[$placeholder]['shuffle'], $options, $sub->feedbackclass
                );
            }

            // The replacement text *might* contain a backslash and in the worst case this might
            // lead to an erroneous backreference, e. g. if the student's answer was \1. Thus,
            // we better use preg_replace_callback() instead of just preg_replace(), as this allows
            // us to ignore such unintentional backreferences.
            $subqreplaced = preg_replace_callback(
                '/' . preg_quote($boxes[$placeholder]['placeholder'], '/') . '/',
                function ($matches) use ($inputfieldhtml) {
                    return $inputfieldhtml;
                },
                $subqreplaced,
                1
            );
        }

        return $subqreplaced;
    }

    /**
     * Correct response for the question. This is not needed for the Formulas question, because
     * answers are relative to parts.
     *
     * @param question_attempt $qa question attempt that will be displayed on the page
     * @return string empty string
     */
    public function correct_response(question_attempt $qa) {
        return '';
    }

    /**
     * Generate an automatic description of the correct response for a given part.
     *
     * @param qtype_formulas_part $part question part
     * @return string HTML fragment
     */
    public function part_correct_response($part) {
        $answers = $part->get_correct_response(true);
        $answertext = implode('; ', $answers);

        $string = ($part->answernotunique ? 'correctansweris' : 'uniquecorrectansweris');
        return html_writer::nonempty_tag(
            'div', get_string($string, 'qtype_formulas', $answertext), ['class' => 'formulaspartcorrectanswer']
        );
    }

    /**
     * Generate a brief statement of how many sub-parts of this question the
     * student got right.
     *
     * @param question_attempt $qa question attempt that will be displayed on the page
     * @return string HTML fragment
     */
    protected function num_parts_correct(question_attempt $qa) {
        /** @var qtype_formulas_question $question */
        $question = $qa->get_question();
        $response = $qa->get_last_qt_data();
        if (!$question->is_gradable_response($response)) {
            return '';
        }

        $numright = $question->get_num_parts_right($response)[0];
        if ($numright === 1) {
            return get_string('yougotoneright', 'qtype_formulas');
        } else {
            return get_string('yougotnright', 'qtype_formulas', $numright);
        }
    }

    /**
     * We need to owerwrite this method to replace global variables by their value.
     *
     * @param question_attempt $qa question attempt that will be displayed on the page
     * @param question_hint $hint the hint to be shown
     * @return string HTML fragment
     */
    protected function hint(question_attempt $qa, question_hint $hint) {
        /** @var qtype_formulas_question $question */
        $question = $qa->get_question();
        $hint->hint = $question->evaluator->substitute_variables_in_text($hint->hint);

        return html_writer::nonempty_tag('div', $question->format_hint($hint, $qa), ['class' => 'hint']);
    }

    /**
     * Generate HTML fragment for the question's combined feedback.
     *
     * @param question_attempt $qa question attempt that will be displayed on the page
     * @return string HTML fragment
     */
    protected function combined_feedback(question_attempt $qa) {
        /** @var qtype_formulas_question $question */
        $question = $qa->get_question();

        $state = $qa->get_state();
        if (!$state->is_finished()) {
            $response = $qa->get_last_qt_data();
            if (!$question->is_gradable_response($response)) {
                return '';
            }
            $state = $question->grade_response($response)[1];
        }

        // The feedback will be in ->correctfeedback, ->partiallycorrectfeedback or ->incorrectfeedback,
        // with the corresponding ->...feedbackformat setting. We create the property names here to simplify
        // access.
        $fieldname = $state->get_feedback_class() . 'feedback';
        $formatname = $state->get_feedback_class() . 'feedbackformat';

        // If there is no feedback, we return an empty string.
        if (strlen(trim($question->$fieldname)) === 0) {
            return '';
        }

        // Otherwise, we return the appropriate feedback. The text is run through format_text() to have
        // variables replaced.
        return $question->format_text(
            $question->$fieldname, $question->$formatname, $qa, 'question', $fieldname, $question->id, false
        );
    }

    /**
     * Generate the specific feedback. This is feedback that varies according to
     * the response the student gave.
     *
     * @param question_attempt $qa question attempt that will be displayed on the page
     * @return string
     */
    public function specific_feedback(question_attempt $qa) {
        return $this->combined_feedback($qa);
    }

    /**
     * Gereate the part's general feedback. This is feedback is shown to all students.
     *
     * @param question_attempt $qa question attempt that will be displayed on the page
     * @param question_display_options $options controls what should and should not be displayed
     * @param qtype_formulas_part $part question part
     * @return string HTML fragment
     */
    protected function part_general_feedback(question_attempt $qa, question_display_options $options, qtype_formulas_part $part) {
        /** @var qtype_formulas_question $question */
        $question = $qa->get_question();
        $state = $qa->get_state();

        // If no feedback should be shown, we return an empty string.
        if (!$options->feedback) {
            return '';
        }

        // If we use the adaptive multipart behaviour, there will be some feedback about the grading,
        // e. g. the obtained marks for this submission and the attracted penalty.
        $gradingdetailsdiv = '';
        if ($qa->get_behaviour_name() == 'adaptivemultipart') {
            // This is rather a hack, but it will probably work.
            $renderer = $this->page->get_renderer('qbehaviour_adaptivemultipart');
            $details = $qa->get_behaviour()->get_part_mark_details($part->partindex);
            $gradingdetailsdiv = $renderer->render_adaptive_marks($details, $options);
            $state = $details->state;
        }
        // If the question is in a state that does not yet allow to give a feedback,
        // we return an empty string.
        if (empty($state->get_feedback_class())) {
            return '';
        }

        // If we have a general feedback, we substitute local / grading variables and
        // wrap it in a <div>.
        $feedbackdiv = '';
        if (strlen(trim($part->feedback)) !== 0) {
            $feedbacktext = $part->evaluator->substitute_variables_in_text($part->feedback);
            $feedbacktext = $question->format_text(
                $feedbacktext,
                FORMAT_HTML,
                $qa,
                'qtype_formulas',
                'answerfeedback',
                $part->id,
                false
            );
            $feedbackdiv = html_writer::tag('div', $feedbacktext , ['class' => 'feedback formulaslocalfeedback']);
        }

        // Append the grading details, if they exist. If the result is not empty, wrap in
        // a <div> and return.
        $feedbackdiv .= $gradingdetailsdiv;
        if (!empty($feedbackdiv)) {
            return html_writer::nonempty_tag(
                'div', $feedbackdiv, ['class' => 'formulaspartfeedback formulaspartfeedback-' . $part->partindex]
            );
        }

        // Still here? Then we return an empty string.
        return '';
    }

    /**
     * Generate HTML fragment for the part's combined feedback.
     *
     * @param question_attempt $qa question attempt that will be displayed on the page
     * @param question_display_options $options controls what should and should not be displayed
     * @param qtype_formulas_part $part question part
     * @param float $fraction the obtained grade
     * @return string HTML fragment
     */
    protected function part_combined_feedback(question_attempt $qa, question_display_options $options,
        qtype_formulas_part $part, float $fraction): string {
        $feedback = '';
        $showfeedback = false;
        /** @var qtype_formulas_question $question */
        $question = $qa->get_question();
        $state = $qa->get_state();
        $feedbackclass = $state->get_feedback_class();

        if ($qa->get_behaviour_name() == 'adaptivemultipart') {
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
                $part->$field = $part->evaluator->substitute_variables_in_text($part->$field);
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
