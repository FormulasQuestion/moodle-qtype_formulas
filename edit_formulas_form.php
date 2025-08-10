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
 * Defines the editing form for the formulas question type.
 *
 * @copyright 2010-2011 Hon Wai, Lau
 * @author Hon Wai, Lau <lau65536@gmail.com>
 * @license https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @package qtype_formulas
 */

use qtype_formulas\unit_conversion_rules;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/question/type/edit_question_form.php');
require_once($CFG->dirroot . '/question/type/multichoice/questiontype.php');

/**
 * coodinate question type editing form definition.
 */
class qtype_formulas_edit_form extends question_edit_form {

    /**
     * Add question-type specific form fields.
     *
     * @param MoodleQuickForm $mform the form being built.
     */
    protected function definition_inner($mform) {
        global $PAGE;
        $config = get_config('qtype_formulas');
        $PAGE->requires->js_call_amd('qtype_formulas/editform', 'init', [get_config('qtype_formulas')->defaultcorrectness]);
        $PAGE->requires->js_call_amd('qtype_formulas/answervalidation', 'init');
        $PAGE->requires->css('/question/type/formulas/styles.css');
        $PAGE->requires->css('/question/type/formulas/tabulator.css');
        // Hide the unused form fields.
        $mform->removeElement('defaultmark');
        $mform->addElement('hidden', 'defaultmark');
        $mform->setType('defaultmark', PARAM_RAW);

        $mform->addHelpButton('questiontext', 'questiontext', 'qtype_formulas');

        // Random and global variables and main question.
        $mform->insertElementBefore($mform->createElement('header', 'globalvarshdr', get_string('globalvarshdr', 'qtype_formulas'),
            ''), 'questiontext');
        $mform->insertElementBefore($mform->createElement('textarea', 'varsrandom', get_string('varsrandom', 'qtype_formulas'),
            ['cols' => 80, 'rows' => 1]) , 'questiontext');
        $mform->addHelpButton('varsrandom', 'varsrandom', 'qtype_formulas');
        $mform->insertElementBefore($mform->createElement('textarea', 'varsglobal', get_string('varsglobal', 'qtype_formulas'),
            ['cols' => 80, 'rows'  => 1]) , 'questiontext');
        $mform->addHelpButton('varsglobal', 'varsglobal', 'qtype_formulas');
        $mform->insertElementBefore($mform->createElement('header', 'mainq', get_string('mainq', 'qtype_formulas'),
            ''), 'questiontext');
        $numberingoptions = qtype_multichoice::get_numbering_styles();
        $mform->addElement('select', 'answernumbering',
                get_string('answernumbering', 'qtype_multichoice'), $numberingoptions);
        $mform->setDefault('answernumbering', get_config('qtype_multichoice', 'answernumbering'));

        // Part's answers.
        $this->add_per_answer_fields($mform, get_string('answerno', 'qtype_formulas', '{no}'),
            question_bank::fraction_options(), 1, 2);

        // Display options, flow options and global part's options.
        $mform->addElement('header', 'subqoptions', get_string('subqoptions', 'qtype_formulas'));
        $mform->addElement('text', 'globalunitpenalty',
            get_string('unitpenalty', 'qtype_formulas'),
            ['size' => 3]
        );
        $mform->addHelpButton('globalunitpenalty', 'unitpenalty', 'qtype_formulas');
        $mform->setDefault('globalunitpenalty', $config->defaultunitpenalty);
        $mform->setType('globalunitpenalty', PARAM_FLOAT);

        $conversionrules = new unit_conversion_rules();
        $allrules = $conversionrules->allrules();
        foreach ($allrules as $id => $entry) {
            $defaultrulechoice[$id] = $entry[0];
        }
        $mform->addElement('select', 'globalruleid', get_string('ruleid', 'qtype_formulas'), $defaultrulechoice);
        $mform->setDefault('globalruleid', 1);
        $mform->addHelpButton('globalruleid', 'ruleid', 'qtype_formulas');

        // Allow instantiate random variables and display the data for instantiated variables.
        $mform->addElement('header', 'checkvarshdr', get_string('checkvarshdr', 'qtype_formulas'));
        $numdatasetgroup = [];
        $numdatasetgroup[] = $mform->createElement('select', 'numdataset', '',
            [
                '1' => '1', '5' => '5', '10' => '10', '25' => '25', '50' => '50', '100' => '100', '250' => '250',
                '500' => '500', '1000' => '1000', '-1' => '*',
            ]
        );
        $numdatasetgroup[] = $mform->createElement('button', 'instantiatebtn', get_string('instantiate', 'qtype_formulas'));
        $mform->addElement('group', 'instantiationctrl', get_string('numdataset', 'qtype_formulas'), $numdatasetgroup, null, false);
        $mform->addElement('static', 'varsdata', get_string('varsdata', 'qtype_formulas'), '<div id="varsdata_display"></div>');
        $mform->addElement(
            'static', 'qtextpreview', '', '<div id="qtextpreview_display" class="filter_mathjaxloader_equation"></div>'
        );

        $this->add_combined_feedback_fields(true);
        $this->add_interactive_settings(true, true);
    }

    /**
     * Add the answer field for a particular part labelled by placeholder.
     *
     * @param MoodleQuickForm $mform the form being built
     * @param string $label label to use for each option
     * @param array $gradeoptions the possible grades for each answer.
     * @param array $repeatedoptions reference to array of repeated options to fill
     * @param array $answersoption reference to return the name of $question->options field holding an array of answers
     * @return array of form fields.
     */
    protected function get_per_answer_fields($mform, $label, $gradeoptions,
            &$repeatedoptions, &$answersoption) {
        $config = get_config('qtype_formulas');
        $repeated = [];
        $repeated[] = $mform->createElement('header', 'answerhdr', $label);
        // Part's mark.
        $repeated[] = $mform->createElement('text', 'answermark', get_string('answermark', 'qtype_formulas'),
            ['size' => 3]);
        $repeatedoptions['answermark']['helpbutton'] = ['answermark', 'qtype_formulas'];
        $repeatedoptions['answermark']['default'] = $config->defaultanswermark;
        $repeatedoptions['answermark']['type'] = PARAM_FLOAT;
        // Part's number of coordinates.
        $repeated[] = $mform->createElement('hidden', 'numbox', '', '');   // Exact value will be computed during validation.
        $repeatedoptions['numbox']['type'] = PARAM_INT;
        // Part's placeholder.
        $repeated[] = $mform->createElement('text', 'placeholder', get_string('placeholder', 'qtype_formulas'),
            ['size' => 20]);
        $repeatedoptions['placeholder']['helpbutton'] = ['placeholder', 'qtype_formulas'];
        $repeatedoptions['placeholder']['type'] = PARAM_RAW;
        // Part's text.
        $repeated[] = $mform->createElement('editor', 'subqtext', get_string('subqtext', 'qtype_formulas'),
            ['rows' => 3], $this->editoroptions);
        $repeatedoptions['subqtext']['helpbutton'] = ['subqtext', 'qtype_formulas'];
        // Part's answer type (0, 10, 100, 1000).
        $repeated[] = $mform->createElement('select', 'answertype', get_string('answertype', 'qtype_formulas'),
                [0 => get_string('number', 'qtype_formulas'), 10 => get_string('numeric', 'qtype_formulas'),
                        100 => get_string('numerical_formula', 'qtype_formulas'),
                        1000 => get_string('algebraic_formula', 'qtype_formulas')]);;
        $repeatedoptions['answertype']['default'] = $config->defaultanswertype;
        $repeatedoptions['answertype']['type'] = PARAM_INT;
        $repeatedoptions['answertype']['helpbutton'] = ['answertype', 'qtype_formulas'];
        // Part's answer.
        $repeated[] = $mform->createElement('text', 'answer', get_string('answer', 'qtype_formulas'),
            ['size' => 80]);
        $repeatedoptions['answer']['helpbutton'] = ['answer', 'qtype_formulas'];
        $repeatedoptions['answer']['type'] = PARAM_RAW_TRIMMED;
        // Whether the part allows leaving one or more fields empty.
        // FIXME: add some validation: if $EMPTY in model answers, this must be checked
        // FIXME: add validation: at least one answer must be ≠ $EMPTY
        $repeated[] = $mform->createElement(
            'advcheckbox',
            'emptyallowed',
            get_string('emptyallowed', 'qtype_formulas')
        );
        $repeatedoptions['emptyallowed']['helpbutton'] = ['emptyallowed', 'qtype_formulas'];
        // Whether the question has multiple answers.
        $repeated[] = $mform->createElement(
            'advcheckbox',
            'answernotunique',
            get_string('answernotunique', 'qtype_formulas')
        );
        $repeatedoptions['answernotunique']['helpbutton'] = ['answernotunique', 'qtype_formulas'];
        // Part's unit.
        $repeated[] = $mform->createElement('text', 'postunit', get_string('postunit', 'qtype_formulas'),
            ['size' => 60]);
        $repeatedoptions['postunit']['helpbutton'] = ['postunit', 'qtype_formulas'];
        $repeatedoptions['postunit']['type'] = PARAM_RAW;
        // Part's grading criteria.
        $gradinggroup = [];
        $gradinggroup[] = $mform->createElement('select', 'correctness_simple_type', null,
            [
                get_string('relerror', 'qtype_formulas'),
                get_string('abserror', 'qtype_formulas'),
            ], ['aria-label' => 'type'] // ARIA label needed as workaround for accessibility.
        );
        $gradinggroup[] = $mform->createElement(
            'select',
            'correctness_simple_comp',
            null,
            ['==', '<'],
            ['aria-label' => 'comparison'],
            false
        );
        $gradinggroup[] = $mform->createElement('text', 'correctness_simple_tol', null, ['aria-label' => 'tolerance']);
        $repeated[] = $mform->createElement(
            'group',
            'correctness_simple',
            get_string('correctness', 'qtype_formulas'),
            $gradinggroup,
            null,
            false
        );
        $repeated[] = $mform->createElement('text', 'correctness', get_string('correctness', 'qtype_formulas'),
        ['size' => 60]);
        $repeated[] = $mform->createElement(
            'checkbox',
            'correctness_simple_mode',
            get_string('correctnesssimple', 'qtype_formulas')
        );

        $repeatedoptions['correctness_simple_mode']['default'] = 0;
        $repeatedoptions['correctness']['hideif'] = ['correctness_simple_mode', 'checked'];
        $repeatedoptions['correctness']['default'] = $config->defaultcorrectness;
        $repeatedoptions['correctness']['helpbutton'] = ['correctness', 'qtype_formulas'];
        $repeatedoptions['correctness']['type'] = PARAM_RAW_TRIMMED;
        $repeatedoptions['correctness_simple']['hideif'] = ['correctness_simple_mode', 'notchecked'];
        $repeatedoptions['correctness_simple']['helpbutton'] = ['correctness', 'qtype_formulas'];
        $repeatedoptions['correctness_simple_tol']['type'] = PARAM_FLOAT;
        $repeatedoptions['correctness_simple_tol']['default'] = '0.01';

        // Part's local variables.
        $repeated[] = $mform->createElement('textarea', 'vars1', get_string('vars1', 'qtype_formulas'),
            ['cols' => 80, 'rows' => 1]);
        $repeatedoptions['vars1']['type'] = PARAM_RAW_TRIMMED;
        $repeatedoptions['vars1']['helpbutton'] = ['vars1', 'qtype_formulas'];
        $repeatedoptions['vars1']['advanced'] = true;
        // Part's grading variables.
        $repeated[] = $mform->createElement('textarea', 'vars2', get_string('vars2', 'qtype_formulas'),
            ['cols' => 80, 'rows' => 1]);
        $repeatedoptions['vars2']['type'] = PARAM_RAW_TRIMMED;
        $repeatedoptions['vars2']['helpbutton'] = ['vars2', 'qtype_formulas'];
        $repeatedoptions['vars2']['advanced'] = true;
        // Part's other rules.
        $repeated[] = $mform->createElement('textarea', 'otherrule', get_string('otherrule', 'qtype_formulas'),
            ['cols' => 80, 'rows' => 1]);
        $repeatedoptions['otherrule']['helpbutton'] = ['otherrule', 'qtype_formulas'];
        $repeatedoptions['otherrule']['type'] = PARAM_RAW_TRIMMED;
        $repeatedoptions['otherrule']['advanced'] = true;
        // Part's feedback.
        $repeated[] = $mform->createElement('editor', 'feedback', get_string('feedback', 'qtype_formulas'),
            ['rows' => 3], $this->editoroptions);
        $repeatedoptions['feedback']['helpbutton'] = ['feedback', 'qtype_formulas'];
        $repeatedoptions['feedback']['advanced'] = true;
        // Part's combined feedback.
        $repeated[] = $mform->createElement('editor', 'partcorrectfb', get_string('correctfeedback', 'qtype_formulas'),
            ['rows' => 3], $this->editoroptions);
        $repeatedoptions['partcorrectfb']['helpbutton'] = ['correctfeedback', 'qtype_formulas'];
        $repeatedoptions['partcorrectfb']['advanced'] = true;
        $repeated[] = $mform->createElement(
          'editor',
          'partpartiallycorrectfb',
          get_string('partiallycorrectfeedback', 'qtype_formulas'),
          ['rows' => 3],
          $this->editoroptions
        );
        $repeatedoptions['partpartiallycorrectfb']['helpbutton'] = ['partiallycorrectfeedback', 'qtype_formulas'];
        $repeatedoptions['partpartiallycorrectfb']['advanced'] = true;
        $repeated[] = $mform->createElement('editor', 'partincorrectfb', get_string('incorrectfeedback', 'qtype_formulas'),
            ['rows' => 3], $this->editoroptions);
        $repeatedoptions['partincorrectfb']['helpbutton'] = ['incorrectfeedback', 'qtype_formulas'];
        $repeatedoptions['partincorrectfb']['advanced'] = true;
        $answersoption = 'answers';
        return $repeated;
    }

    /**
     * Add a set of form fields, obtained from get_per_answer_fields, to the form,
     * one for each existing answer, with some blanks for some new ones.
     *
     * @param MoodleQuickForm $mform reference to the form being built.
     * @param array $label the label to use for each option.
     * @param array $gradeoptions the possible grades for each answer.
     * @param int $minoptions the minimum number of answer blanks to display.
     *      Default QUESTION_NUMANS_START.
     * @param int $addoptions the number of answer blanks to add. Default QUESTION_NUMANS_ADD.
     */
    protected function add_per_answer_fields(&$mform, $label, $gradeoptions,
            $minoptions = QUESTION_NUMANS_START, $addoptions = QUESTION_NUMANS_ADD) {
        $answersoption = '';
        $repeatedoptions = [];
        $repeated = $this->get_per_answer_fields($mform, $label, $gradeoptions,
                $repeatedoptions, $answersoption);

        // If we are editing an existing question and the user inadvertently cleared all parts,
        // we still want to show the fields for one part in the form. If we are creating a new
        // question, we show $minoptions part(s), the default is 3.
        if (isset($this->question->options)) {
            $repeatsatstart = max(1, count($this->question->options->$answersoption));
        } else {
            $repeatsatstart = $minoptions;
        }

        $this->repeat_elements($repeated, $repeatsatstart, $repeatedoptions,
                'noanswers', 'addanswers', $addoptions,
                $this->get_more_choices_string(), false);
    }

    /**
     * Language string to use for 'Add {no} more blanks'. Override from parent for
     * appropriate text.
     *
     * @return void
     */
    protected function get_more_choices_string() {
        return get_string('addmorepartsblanks', 'qtype_formulas');
    }

    /**
     * Perform any preprocessing needed on the data passed to {@see set_data()}
     * before it is used to initialise the form.
     *
     * @param object $question the data being passed to the form
     * @return object the modified data
     */
    protected function data_preprocessing($question) {
        $question = parent::data_preprocessing($question);
        $question = $this->data_preprocessing_combined_feedback($question, true);
        $question = $this->data_preprocessing_hints($question, true, true);
        if (isset($question->options)) {
            $defaultvalues = [];
            if (count($question->options->answers)) {
                $tags = qtype_formulas::PART_BASIC_FIELDS;
                foreach ($question->options->answers as $key => $answer) {

                    foreach ($tags as $tag) {
                        // FIXME: remove this later, when the DB is updated
                        if ($tag === 'emptyallowed') {
                            continue;
                        }
                        if ($tag === 'unitpenalty' || $tag === 'ruleid') {
                            $defaultvalues['global' . $tag] = $answer->$tag;
                        } else {
                            $defaultvalues[$tag.'['.$key.']'] = $answer->$tag;
                        }
                    }

                    $fields = ['subqtext', 'feedback'];
                    foreach ($fields as $field) {
                        $fieldformat = $field . 'format';
                        $itemid = file_get_submitted_draft_itemid($field . '[' . $key . ']');
                        $fieldtxt = file_prepare_draft_area($itemid, $this->context->id, 'qtype_formulas',
                                'answer' . $field, empty($answer->id) ? null : (int)$answer->id,
                                $this->fileoptions, $answer->$field);
                        $defaultvalues[$field . '[' . $key . ']'] = ['text' => $fieldtxt,
                            'format' => $answer->$fieldformat, 'itemid' => $itemid];
                    }
                    $fields = ['partcorrectfb', 'partpartiallycorrectfb', 'partincorrectfb'];
                    foreach ($fields as $field) {
                        $fieldformat = $field . 'format';
                        $itemid = file_get_submitted_draft_itemid($field . '[' . $key . ']');
                        $fieldtxt = file_prepare_draft_area($itemid, $this->context->id, 'qtype_formulas',
                                $field, empty($answer->id) ? null : (int)$answer->id,
                                $this->fileoptions, $answer->$field);
                        $defaultvalues[$field . '[' . $key . ']'] = ['text' => $fieldtxt,
                            'format' => $answer->$fieldformat, 'itemid' => $itemid];
                    }
                }
            }

            $question = (object)((array)$question + $defaultvalues);
        }
        return $question;
    }

    /**
     * Validating the data returning from the form. This checks for basic errors as well as specific
     * errors of the question type by evaluating one instantiation.
     *
     * @param array $fromform the form data
     * @param array $files array of uploaded files 'element_name' => tmp_file_path
     * @return array empty array if everything is OK, otherwise 'element_name' => 'error'
     */
    public function validation($fromform, $files) {
        $errors = parent::validation($fromform, $files);
        // Use the validation defined in the question type, check by instantiating one variable set.
        $data = (object)$fromform;
        $qtype = new qtype_formulas();
        $instantiationresult = $qtype->validate($data);
        if (isset($instantiationresult->errors)) {
            $errors = array_merge($errors, $instantiationresult->errors);
        }
        return $errors;
    }

    /**
     * Overriding abstract parent method to return the question type name.
     *
     * @return string
     */
    public function qtype() {
        return 'formulas';
    }
}
