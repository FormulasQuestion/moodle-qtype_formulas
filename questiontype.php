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
 * Question type class for the Formulas question type.
 *
 * @copyright 2010-2011 Hon Wai, Lau; 2023 Philipp Imhof
 * @author Hon Wai, Lau <lau65536@gmail.com>
 * @author Philipp Imhof
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License version 3
 * @package qtype_formulas
 */

use qtype_formulas\answer_unit_conversion;
use qtype_formulas\unit_conversion_rules;
use qtype_formulas\evaluator;
use qtype_formulas\random_parser;
use qtype_formulas\answer_parser;
use qtype_formulas\parser;
use qtype_formulas\token;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/questionlib.php');
require_once($CFG->dirroot . '/question/engine/lib.php');
require_once($CFG->dirroot . '/question/type/formulas/answer_unit.php');
require_once($CFG->dirroot . '/question/type/formulas/conversion_rules.php');
require_once($CFG->dirroot . '/question/type/formulas/question.php');

/**
 * Question type class for the Formulas question type.
 *
 * @copyright 2010-2011 Hon Wai, Lau; 2023 Philipp Imhof
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License version 3
 */
class qtype_formulas extends question_type {
    const ANSWER_TYPE_NUMBER = 0;
    const ANSWER_TYPE_NUMERIC = 10;
    const ANSWER_TYPE_NUMERICAL_FORMULA = 100;
    const ANSWER_TYPE_ALGEBRAIC = 1000;

    /**
     * The following array contains some of the column names of the table qtype_formulas_answers,
     * the table that holds the parts (not just answers) of a question. These columns undergo similar
     * validation, so they are grouped in this array. Some columns are not listed here, namely
     * the texts (part's text, part's feedback) and their formatting option, because they are
     * validated separately:
     * - placeholder: the part's placeholder to be used in the main question text, e. g. #1
     * - answermark: grade awarded for this part, if answer is fully correct
     * - numbox: number of answers for this part, not including a possible unit field
     * - vars1: the part's local variables
     * - answer: the model answer(s) for this part
     * - vars2: the part's grading variables
     * - correctness: the part's grading criterion
     * - unitpenalty: deduction to be made for wrong units
     * - postunit: the unit in which the model answer has been entered
     * - ruleid: ruleset used for unit conversion
     * - otherrule: additional rules for unit conversion
     */
    const PART_BASIC_FIELDS = ['placeholder', 'answermark', 'answertype', 'numbox', 'vars1', 'answer', 'answernotunique', 'vars2',
        'correctness', 'unitpenalty', 'postunit', 'ruleid', 'otherrule'];

    /**
     * This function returns the "simple" additional fields defined in the qtype_formulas_options
     * table. It is called by Moodle's core in order to have those fields automatically saved
     * backed up and restored. The basic fields like id and questionid do not need to included.
     * Also, we do not include the more "complex" feedback fields (correct, partially correct, incorrect),
     * as they need special treatment, because they can contain references to uploaded files.
     *
     * @return string[]
     */
    public function extra_question_fields() {
        return ['qtype_formulas_options', 'varsrandom', 'varsglobal', 'shownumcorrect', 'answernumbering'];
    }

    /**
     * Fetch the ID for every part of a given question.
     * TODO: turn this into private method
     *
     * @param int $questionid
     * @return int[]
     */
    public function fetch_part_ids_for_question(int $questionid): array {
        global $DB;

        // Fetch the parts from the DB. The result will be an associative array with
        // the parts' IDs as keys.
        $parts = $DB->get_records('qtype_formulas_answers', ['questionid' => $questionid]);

        return array_keys($parts);
    }

    /**
     * Move all the files belonging to this question (and its parts) from one context to another.
     * TODO: oldid -> oldcontextid, newid -> newcontextid
     *
     * @param int $questionid the question being moved.
     * @param int $oldid the context it is moving from.
     * @param int $newid the context it is moving to.
     */
    public function move_files($questionid, $oldid, $newid): void {
        // Fetch the part IDs for every part of this question.
        $partids = $this->fetch_part_ids_for_question($questionid);

        // Move files for all areas and all parts.
        $fs = get_file_storage();
        $areas = ['answersubqtext', 'answerfeedback', 'partcorrectfb', 'partpartiallycorrectfb', 'partincorrectfb'];
        foreach ($areas as $area) {
            $fs->move_area_files_to_new_context($oldid, $newid, 'qtype_formulas', $area, $questionid);
            foreach ($partids as $partid) {
                $fs->move_area_files_to_new_context($oldid, $newid, 'qtype_formulas', $area, $partid);
            }
        }

        $this->move_files_in_combined_feedback($questionid, $oldid, $newid);
        $this->move_files_in_hints($questionid, $oldid, $newid);

        // The parent method will move files from the question text and the general feedback.
        parent::move_files($questionid, $oldid, $newid);
    }

    /**
     * Delete all the files belonging to this question (and its parts).
     *
     * @param int $questionid the question being deleted.
     * @param int $contextid the context the question is in.
     */
    protected function delete_files($questionid, $contextid): void {
        // Fetch the part IDs for every part of this question.
        $partids = $this->fetch_part_ids_for_question($questionid);

        // Delete files for all areas and all parts.
        $fs = get_file_storage();
        $areas = ['answersubqtext', 'answerfeedback', 'partcorrectfb', 'partpartiallycorrectfb', 'partincorrectfb'];
        foreach ($areas as $area) {
            $fs->delete_area_files($contextid, 'qtype_formulas', $area, $questionid);
            foreach ($partids as $partid) {
                $fs->delete_area_files($contextid, 'qtype_formulas', $area, $partid);
            }
        }

        $this->delete_files_in_combined_feedback($questionid, $contextid);
        $this->delete_files_in_hints($questionid, $contextid);

        // The parent method will delete files from the question text and the general feedback.
        parent::delete_files($questionid, $contextid);
    }

    /**
     * Loads the question type specific options for the question.
     * $question already contains the question's general data from the question table when
     * this function is called.
     *
     * This function loads any question type specific options for the
     * question from the database into the question object. This information
     * is placed in the $question->options field. A question type is
     * free, however, to decide on a internal structure of the options field.
     * @param object $question The question object for the question. This object
     *                         should be updated to include the question type
     *                         specific information (it is passed by reference).
     * @return bool            Indicates success or failure.
     */
    public function get_question_options($question): bool {
        global $DB;

        // Fetch options from the table qtype_formulas_options. The DB engine will automatically
        // return a standard class where the attribute names match the column names.
        $question->options = $DB->get_record('qtype_formulas_options', ['questionid' => $question->id]);

        // In case of a DB error (e. g. missing record), get_record() returns false. In that case, we
        // create default options.
        if ($question->options === false) {
            debugging(get_string('error_db_missing_options', 'qtype_formulas', $question->id), DEBUG_DEVELOPER);
            $question->options = (object)[
                'questionid' => $question->id,
                'varsrandom' => '',
                'varsglobal' => '',
                'correctfeedback' => get_string('correctfeedbackdefault', 'question'),
                'correctfeedbackformat' => FORMAT_HTML,
                'partiallycorrectfeedback' => get_string('partiallycorrectfeedbackdefault', 'question'),
                'partiallycorrectfeedbackformat' => FORMAT_HTML,
                'incorrectfeedback' => get_string('incorrectfeedbackdefault', 'question'),
                'incorrectfeedbackformat' => FORMAT_HTML,
                'shownumcorrect' => 0,
                'answernumbering' => 'none',
            ];
        }

        parent::get_question_options($question);

        // Fetch parts' data and remove existing array indices (starting from first part's id) in order
        // to have the array indices start from 0.
        $question->options->answers = $DB->get_records('qtype_formulas_answers', ['questionid' => $question->id], 'partindex ASC');
        $question->options->answers = array_values($question->options->answers);

        // Correctly set the number of parts for this question.
        $question->options->numparts = count($question->options->answers);

        return true;
    }

    /**
     * Helper function to save files that are embedded in e. g. part's text or
     * feedback, avoids to set 'qtype_formulas' for every invocation.
     *
     * @param array $array the data from the form (or from import). This will
     *      normally have come from the formslib editor element, so it will be an
     *      array with keys 'text', 'format' and 'itemid'. However, when we are
     *      importing, it will be an array with keys 'text', 'format' and 'files'
     * @param object $context the context the question is in.
     * @param string $filearea indentifies the file area questiontext,
     *      generalfeedback, answerfeedback, etc.
     * @param int $itemid identifies the file area. --> FIXME: part or question ID
     *
     * @return string the text for this field, after files have been processed.
     */
    protected function save_file_helper(array $array, object $context, string $filearea, int $itemid): string {
        return $this->import_or_save_files($array, $context, 'qtype_formulas', $filearea, $itemid);
    }

    /**
     * Saves question-type specific options
     *
     * This is called by {@link save_question()} to save the question-type specific data
     * @param object $formdata  This holds the information from the editing form,
     *      it is not a standard question object.
     * @return object $result->error or $result->notice
     * @throws Exception
     */
    public function save_question_options($formdata) {
        global $DB;

        // Fetch existing parts from the DB.
        $existingparts = $DB->get_records('qtype_formulas_answers', ['questionid' => $formdata->id], 'partindex ASC');

        // Validate the data from the edit form.
        $filtered = $this->validate($formdata);
        if (!empty($filtered->errors)) {
            return (object)['error' => get_string('error_damaged_question', 'qtype_formulas')];
        }

        // Order the parts according to how they appear in the question.
        $filtered->answers = $this->reorder_parts($formdata->questiontext, $filtered->answers);

        foreach ($filtered->answers as $i => $part) {
            $part->questionid = $formdata->id;
            $part->partindex = $i;

            // Try to take the first existing part.
            $parttoupdate = array_shift($existingparts);

            // If there is currently no part, we create an empty one, store it in the DB
            // and retrieve its ID.
            if (empty($parttoupdate)) {
                $parttoupdate = (object)[
                    'questionid' => $formdata->id,
                    'answermark' => 1,
                    'numbox' => 1,
                    'answer' => '',
                    'answernotunique' => 1,
                    'correctness' => '',
                    'ruleid' => 1,
                    'subqtext' => '',
                    'subqtextformat' => FORMAT_HTML,
                    'feedback' => '',
                    'feedbackformat' => FORMAT_HTML,
                    'partcorrectfb' => '',
                    'partcorrectfbformat' => FORMAT_HTML,
                    'partpartiallycorrectfb' => '',
                    'partpartiallycorrectfbformat' => FORMAT_HTML,
                    'partincorrectfb' => '',
                    'partincorrectfbformat' => FORMAT_HTML,
                ];

                try {
                    $parttoupdate->id = $DB->insert_record('qtype_formulas_answers', $parttoupdate);
                } catch (Exception $e) {
                    // TODO: change to non-capturing catch when dropping support for PHP 7.4.
                    return (object)['error' => get_string('error_db_write', 'qtype_formulas', 'qtype_formulas_answers')];
                }
            }

            // Finally, set the ID for the newpart.
            $part->id = $parttoupdate->id;

            // Now that we have the ID, we can deal with the text fields that might contain files,
            // i. e. the part's text and the feedbacks (general, correct, partially correct, incorrect).
            // We need the current question's context. Also, we must split up the form's text editor
            // data (text and format in one array) into separate text and format properties. Moodle does
            // its magic when saving the files, so we first do that and keep the modified text.
            // Note that we store the files with the question ID or the part ID, depending on the text
            // area where they belong.
            $context = $formdata->context;

            $tmp = $part->subqtext;
            $part->subqtext = $this->save_file_helper($tmp, $context, 'answersubqtext', $part->id);
            $part->subqtextformat = $tmp['format'];

            $tmp = $part->feedback;
            $part->feedback = $this->save_file_helper($tmp, $context, 'answerfeedback', $part->id);
            $part->feedbackformat = $tmp['format'];

            $tmp = $part->partcorrectfb;
            $part->partcorrectfb = $this->save_file_helper($tmp, $context, 'partcorrectfb', $part->id);
            $part->partcorrectfbformat = $tmp['format'];

            $tmp = $part->partpartiallycorrectfb;
            $part->partpartiallycorrectfb = $this->save_file_helper($tmp, $context, 'partpartiallycorrectfb', $part->id);
            $part->partpartiallycorrectfbformat = $tmp['format'];

            $tmp = $part->partincorrectfb;
            $part->partincorrectfb = $this->save_file_helper($tmp, $context, 'partincorrectfb', $part->id);
            $part->partincorrectfbformat = $tmp['format'];

            try {
                $DB->update_record('qtype_formulas_answers', $part);
            } catch (Exception $e) {
                // TODO: change to non-capturing catch when dropping support for PHP 7.4.
                return (object)['error' => get_string('error_db_write', 'qtype_formulas', 'qtype_formulas_answers')];
            }
        }

        $options = $DB->get_record('qtype_formulas_options', ['questionid' => $formdata->id]);

        // If there are no options yet (i. e. we are saving a new question) or if the fetch was not
        // successful, create new options with default values.
        if (empty($options) || $options === false) {
            $options = (object)[
                'questionid' => $formdata->id,
                'correctfeedback' => '',
                'partiallycorrectfeedback' => '',
                'incorrectfeedback' => '',
                'answernumbering' => 'none'
            ];

            try {
                $options->id = $DB->insert_record('qtype_formulas_options', $options);
            } catch (Exception $e) {
                return (object)['error' => get_string('error_db_write', 'qtype_formulas', 'qtype_formulas_options')];
            }
        }

        // Do all the magic for the question's combined feedback fields (correct, partially correct, incorrect).
        $options = $this->save_combined_feedback_helper($options, $formdata, $formdata->context, true);

        // Get the extra fields we have for our question type. Drop the first entry, because
        // it contains the table name.
        $extraquestionfields = $this->extra_question_fields();
        array_shift($extraquestionfields);

        // Assign the values from the form.
        foreach ($extraquestionfields as $extrafield) {
            if (isset($formdata->$extrafield)) {
                $options->$extrafield = $formdata->$extrafield;
            }
        }

        // Finally, update the existing (or just recently created) record with the values from the form.
        try {
            $DB->update_record('qtype_formulas_options', $options);
        } catch (Exception $e) {
            return (object)['error' => get_string('error_db_write', 'qtype_formulas', 'qtype_formulas_options')];
        }

        // Save the hints, if they exist.
        $this->save_hints($formdata, true);

        // If there are no existing parts left to be updated, we may leave.
        if (!$existingparts) {
            return;
        }

        // Still here? Then we must remove remaining parts and their files (if there are), because the
        // user seems to have deleted them in the form.
        $fs = get_file_storage();
        foreach ($existingparts as $leftover) {
            $areas = ['answersubqtext', 'answerfeedback', 'partcorrectfb', 'partpartiallycorrectfb', 'partincorrectfb'];
            foreach ($areas as $area) {
                $fs->delete_area_files($context->id, 'qtype_formulas', $area, $leftover->id);
            }
            try {
                $DB->delete_records('qtype_formulas_answers', array('id' => $leftover->id));
            } catch (Exception $e) {
                return (object)['error' => get_string('error_db_delete', 'qtype_formulas', 'qtype_formulas_answers')];
            }
        }
    }

    /**
     * Save a question. Overriding the parent method, because we have to calculate the
     * defaultmark and we need to propagate the global settings for unitpenalty and ruleid
     * to every part.
     *
     * @param object $question
     * @param object $formdata
     * @return object
     */
    public function save_question($question, $formdata) {
        // Question's default mark is the total of all non empty parts's marks.
        $formdata->defaultmark = 0;
        foreach (array_keys($formdata->answermark) as $key) {
            // Do nothing if the part has no mark or no answer.
            if (trim($formdata->answermark[$key]) === '' || trim($formdata->answer[$key]) === '') {
                continue;
            }
            $formdata->defaultmark += $formdata->answermark[$key];
        }

        // Add the global unitpenalty and ruleid to each part. Using the answertype field as
        // the counter reference, because it is always set.
        $count = count($formdata->answertype);
        $formdata->unitpenalty = array_fill(0, $count, $formdata->globalunitpenalty);
        $formdata->ruleid = array_fill(0, $count, $formdata->globalruleid);

        // Preparation work is done, let the parent method do the rest.
        return parent::save_question($question, $formdata);
    }

    /**
     * Create a question_hint. Overriding the parent method, because our
     * question type can have multiple parts.
     *
     * @param object $hint the DB row from the question hints table.
     * @return question_hint
     */
    protected function make_hint($hint) {
        return question_hint_with_parts::load_from_record($hint);
    }

    /**
     * Delete the question from the database, together with its options and parts.
     *
     * @param int $questionid
     * @param int $contextid
     * @return void
     */
    public function delete_question($questionid, $contextid) {
        global $DB;

        // First, we call the parent method. It will delete the question itself (from question)
        // and its options (from qtype_formulas_options).
        // Note: This will also trigger the delete_files() method which, in turn, needs the question's
        // parts to be available, so we MUST NOT remove the parts before this.
        parent::delete_question($questionid, $contextid);

        // Finally, remove the related parts from the qtype_formulas_answers table.
        $DB->delete_records('qtype_formulas_answers', ['questionid' => $questionid]);
    }

    /**
     * Split the main question text into fragments that will later enclose the various parts'
     * text. As an example, 'foo {#1} bar' will become 'foo ' and ' bar'. The function will
     * return one more fragment than the number of parts. The last fragment can be empty, e. g.
     * if we have a part with no placeholder. Such parts are placed at the very end, so there will
     * no fragment of the question's main text after them.
     *
     * @param string $questiontext main question tex
     * @param qtype_formulas_part[] $parts
     * @return string[] fragments (one more than the number of parts
     */
    public function split_questiontext(string $questiontext, array $parts): array {
        // Make sure the parts are ordered according to the position of their placeholders
        // in the main question text.
        $parts = $this->reorder_parts($questiontext, $parts);

        $fragments = [];
        foreach ($parts as $part) {
            // Since the parts are ordered, we know that parts with placeholders come first.
            // When we see the first part without a placeholder, we can add the remaining question
            // text to the fragments. We then set the question text to the empty string, in order
            // to add empty fragments for each subsequent part.
            if (empty($part->placeholder)) {
                $fragments[] = $questiontext;
                $questiontext = '';
                continue;
            }
            $pos = strpos($questiontext, "{{$part->placeholder}}");
            $fragments[] = substr($questiontext, 0, $pos);
            $questiontext = substr($questiontext, $pos + strlen($part->placeholder) + 2);
        }

        // Add the remainder of the question text after the last part; this might be an empty string.
        $fragments[] = $questiontext;

        return $fragments;
    }

    /**
     * Initialise instante of the qtype_formulas_question class and its parts which, in turn,
     * are instances of the qtype_formulas_part class.
     *
     * @param qtype_formulas_question $question instance of a Formulas question
     * @param object $questiondata question data as stored in the DB
     */
    protected function initialise_question_instance(question_definition $question, $questiondata) {
        // All the classical fields (e. g. category, context or id) are filled by the parent method.
        parent::initialise_question_instance($question, $questiondata);

        // First, copy some data for the main question.
        $question->varsrandom = $questiondata->options->varsrandom;
        $question->varsglobal = $questiondata->options->varsglobal;
        $question->answernumbering = $questiondata->options->answernumbering;
        $question->numparts = $questiondata->options->numparts;

        // The attribute $questiondata->options->answers stores all information for the parts. Despite
        // its name, it does not only contain the model answers, but also e.g. local or grading vars.
        foreach ($questiondata->options->answers as $partdata) {
            $questionpart = new qtype_formulas_part();

            // Copy the data fields fetched from the DB to the question part object.
            foreach ($partdata as $key => $value) {
                $questionpart->{$key} = $value;
            }

            // And finally store the populated part in the main question instance.
            $question->parts[$partdata->partindex] = $questionpart;
        }

        // Split the main question text into fragments that will later surround the parts' texts.
        $question->textfragments = $this->split_questiontext($question->questiontext, $question->parts);

        // The combined feedback will be initialised by the parent class, because we do not override
        // this method.
        $this->initialise_combined_feedback($question, $questiondata, true);
    }

    /**
     * Return all possible types of response. They are used e. g. in reports.
     *
     * @param object $questiondata question definition data
     * @return array possible responses for every part
     */
    public function get_possible_responses($questiondata) {
        $responses = [];

        $question = $this->make_question($questiondata);

        foreach ($question->parts as $part) {
            if ($part->postunit === '') {
                $responses[$part->partindex] = [
                    'wrong' => new question_possible_response(get_string('response_wrong', 'qtype_formulas'), 0),
                    'right' => new question_possible_response(get_string('response_right', 'qtype_formulas'), 1),
                    null => question_possible_response::no_response(),
                ];
            } else {
                $responses[$part->partindex] = [
                    'wrong' => new question_possible_response(get_string('response_wrong', 'qtype_formulas'), 0),
                    'right' => new question_possible_response(get_string('response_right', 'qtype_formulas'), 1),
                    'wrongvalue' => new question_possible_response(get_string('response_wrong_value', 'qtype_formulas'), 0),
                    'wrongunit' => new question_possible_response(
                        get_string('response_wrong_unit', 'qtype_formulas'), 1 - $part->unitpenalty
                    ),
                    null => question_possible_response::no_response(),
                ];
            }
        }

        return $responses;
    }

    /**
     * Imports the question from Moodle XML format. Overriding the parent function is necessary,
     * because a Formulas question contains subparts.
     *
     * @param array $xml structure containing the XML data
     * @param $question question object to fill
     * @param qformat_xml $format format class exporting the question
     * @param $extra extra information (not required for importing this question in this format)
     */
    public function import_from_xml($xml, $question, qformat_xml $format, $extra = null) {
        // Return if data type is not our own one.
        if (!isset($xml['@']['type']) || $xml['@']['type'] != $this->name()) {
            return false;
        }

        // Import the common question headers and set the corresponding field.
        $question = $format->import_headers($xml);
        $question->qtype = $this->name();
        $format->import_combined_feedback($question, $xml, true);
        $format->import_hints($question, $xml, true);

        $question->varsrandom = $format->getpath($xml, ['#', 'varsrandom', 0, '#', 'text', 0, '#'], '', true);
        $question->varsglobal = $format->getpath($xml, ['#', 'varsglobal', 0, '#', 'text', 0, '#'], '', true);
        $question->answernumbering = $format->getpath($xml, ['#', 'answernumbering', 0, '#', 'text', 0, '#'], 'none', true);

        // Loop over each answer block found in the XML.
        foreach ($xml['#']['answers'] as $i => $part) {
            $partindex = $format->getpath($part, ['#', 'partindex', 0 , '#' , 'text' , 0 , '#'], false);
            if ($partindex !== false) {
                $question->partindex[$i] = $partindex;
            }
            foreach (self::PART_BASIC_FIELDS as $field) {
                // Older questions do not have this field, so we do not want to issue an error message.
                // Also, for maximum backwards compatibility, we set the default value to 1. With this,
                // nothing changes for old questions.
                if ($field === 'answernotunique') {
                    $ifnotexists = '';
                    $default = '1';
                } else {
                    $ifnotexists = get_string('error_import_missing_field', 'qtype_formulas', $field);
                    $default = '0';
                }
                $question->{$field}[$i] = $format->getpath(
                    $part,
                    ['#', $field, 0 , '#' , 'text' , 0 , '#'],
                    $default,
                    false,
                    $ifnotexists
                );
            }

            $subqxml = $format->getpath($part, ['#', 'subqtext', 0], []);
            $question->subqtext[$i] = $format->import_text_with_files($subqxml,
                        [], '', $format->get_format($question->questiontextformat));

            $feedbackxml = $format->getpath($part, ['#', 'feedback', 0], []);
            $question->feedback[$i] = $format->import_text_with_files($feedbackxml,
                        [], '', $format->get_format($question->questiontextformat));

            $feedbackxml = $format->getpath($part, ['#', 'correctfeedback', 0], []);
            $question->partcorrectfb[$i] = $format->import_text_with_files($feedbackxml,
                        [], '', $format->get_format($question->questiontextformat));
            $feedbackxml = $format->getpath($part, ['#', 'partiallycorrectfeedback', 0], []);
            $question->partpartiallycorrectfb[$i] = $format->import_text_with_files($feedbackxml,
                        [], '', $format->get_format($question->questiontextformat));
            $feedbackxml = $format->getpath($part, ['#', 'incorrectfeedback', 0], []);
            $question->partincorrectfb[$i] = $format->import_text_with_files($feedbackxml,
                        [], '', $format->get_format($question->questiontextformat));
        }

        // Make the defaultmark consistent if not specified.
        $question->defaultmark = array_sum($question->answermark);

        return $question;
    }

    /**
     * Exports the question to Moodle XML format.
     *
     * @param object $question question to be exported into XML format
     * @param qformat_xml $format format class exporting the question
     * @param $extra extra information (not required for exporting this question in this format)
     * @return string containing the question data in XML format
     */
    public function export_to_xml($question, qformat_xml $format, $extra = null) {
        $output = '';
        $contextid = $question->contextid;
        $output .= $format->write_combined_feedback($question->options, $question->id, $question->contextid);

        // Get the extra fields we have for our question type. Drop the first entry, because
        // it contains the table name.
        $extraquestionfields = $this->extra_question_fields();
        array_shift($extraquestionfields);
        foreach ($extraquestionfields as $extrafield) {
            $output .= "<$extrafield>" . $format->writetext($question->options->$extrafield) . "</$extrafield>\n";
        }

        $fs = get_file_storage();
        foreach ($question->options->answers as $part) {
            $output .= "<answers>\n";
            $output .= " <partindex>\n  " . $format->writetext($part->partindex) . " </partindex>\n";

            foreach (self::PART_BASIC_FIELDS as $tag) {
                $output .= " <$tag>\n  " . $format->writetext($part->$tag) . " </$tag>\n";
            }

            $subqfiles = $fs->get_area_files($contextid, 'qtype_formulas', 'answersubqtext', $part->id);
            $subqtextformat = $format->get_format($part->subqtextformat);
            $output .= " <subqtext format=\"$subqtextformat\">\n";
            $output .= $format->writetext($part->subqtext);
            $output .= $format->write_files($subqfiles);
            $output .= " </subqtext>\n";

            $fbfiles = $fs->get_area_files($contextid, 'qtype_formulas', 'answerfeedback', $part->id);
            $feedbackformat = $format->get_format($part->feedbackformat);
            $output .= " <feedback format=\"$feedbackformat\">\n";
            $output .= $format->writetext($part->feedback);
            $output .= $format->write_files($fbfiles);
            $output .= " </feedback>\n";

            $fbfiles = $fs->get_area_files($contextid, 'qtype_formulas', 'partcorrectfb', $part->id);
            $feedbackformat = $format->get_format($part->partcorrectfbformat);
            $output .= " <correctfeedback format=\"$feedbackformat\">\n";
            $output .= $format->writetext($part->partcorrectfb);
            $output .= $format->write_files($fbfiles);
            $output .= " </correctfeedback>\n";

            $fbfiles = $fs->get_area_files($contextid, 'qtype_formulas', 'partpartiallycorrectfb', $part->id);
            $feedbackformat = $format->get_format($part->partpartiallycorrectfbformat);
            $output .= " <partiallycorrectfeedback format=\"$feedbackformat\">\n";
            $output .= $format->writetext($part->partpartiallycorrectfb);
            $output .= $format->write_files($fbfiles);
            $output .= " </partiallycorrectfeedback>\n";

            $fbfiles = $fs->get_area_files($contextid, 'qtype_formulas', 'partincorrectfb', $part->id);
            $feedbackformat = $format->get_format($part->partincorrectfbformat);
            $output .= " <incorrectfeedback format=\"$feedbackformat\">\n";
            $output .= $format->writetext($part->partincorrectfb);
            $output .= $format->write_files($fbfiles);
            $output .= " </incorrectfeedback>\n";

            $output .= "</answers>\n";
        }

        return $output;
    }

    /**
     * Check if part placeholders are correctly formatted and unique and if each
     * placeholder appears exactly once in the main question text.
     *
     * @param string $questiontext main question text
     * @param object[] $parts data relative to each part, coming from the edit form
     * @return array $errors possible error messages for each part's placeholder field
     */
    public function check_placeholders(string $questiontext, array $parts): array {
        // Store possible error messages for every part.
        $errors = [];

        // List of placeholders in order to spot duplicates.
        $knownplaceholders = [];

        foreach ($parts as $i => $part) {
            // No error if part's placeholder is empty.
            if (empty($part->placeholder)) {
                continue;
            }

            $errormsgs = [];

            // Maximal length for placeholders is limited to 40.
            if (strlen($part->placeholder) > 40) {
                $errormsgs[] = get_string('error_placeholder_too_long', 'qtype_formulas');
            }
            // Placeholders must start with # and contain only alphanumeric characters or underscores.
            if (!preg_match('/^#\w+$/', $part->placeholder) ) {
                $errormsgs[] = get_string('error_placeholder_format', 'qtype_formulas');
            }
            // Placeholders must be unique.
            if (in_array($part->placeholder, $knownplaceholders)) {
                $errormsgs[] = get_string('error_placeholder_sub_duplicate', 'qtype_formulas');
            }
            // Add this placeholder to the list of known values.
            $knownplaceholders[] = $part->placeholder;

            // Each placeholder must appear exactly once in the main question text.
            $count = substr_count($questiontext, "{{$part->placeholder}}");
            if ($count < 1) {
                $errormsgs[] = get_string('error_placeholder_missing', 'qtype_formulas');
            }
            if ($count > 1) {
                $errormsgs[] = get_string('error_placeholder_main_duplicate', 'qtype_formulas');
            }

            // Concatenate all error messages and store them, so they can be shown in the edit form.
            // The corresponding field's name is 'placeholder[...]', so we use that as the array key.
            if (!empty($errormsgs)) {
                $errors["placeholder[$i]"] = implode(' ', $errormsgs);
            }
        }

        // Return the errors. The array will be empty, if everything was fine.
        return $errors;
    }

    /**
     * For each part, check that all required fields have been filled and that they are valid.
     * Return the filtered data for all parts.
     *
     * @param object $data data from the edit form (or an import)
     * @return object stdClass with properties 'errors' (for errors) and 'parts' (array of stdClass, data for each part)
     */
    public function check_and_filter_parts(object $data): object {
        // This function is also called when importing a question.
        // The answers of imported questions already have their unitpenalty and ruleid set.
        $isfromimport = property_exists($data, 'unitpenalty') && property_exists($data, 'ruleid');

        $partdata = [];
        $errors = [];
        $hasoneanswer = false;

        foreach (array_keys($data->answermark) as $i) {

            // If the mark is not set or is zero, we consider that as no mark.
            $nomark = empty(trim($data->answermark[$i]));
            // For answers, zero is not nothing...
            $noanswer = empty(trim($data->answer[$i])) && !is_numeric($data->answer[$i]);
            if ($noanswer === false) {
                $hasoneanswer = true;
            }

            // FIXME: placement of "at least one answer is required"
            // --> only if no valid part can be found
            // --> if no answer and no answermark: answer
            // --> if answer but answermark == 0: answermark

            // FIXME: to review, probably keep current system
            // We consider a part as empty if none of the following fields have been set:
            // subqtext, answer, vars1, vars2, feedback (general feedback for part)
            //
            // maybe add the three other feedbacks + postunit + otherrules

            // Data from the editors are stored in an array with the keys text, format and itemid.
            $noparttext = empty(trim($data->subqtext[$i]['text']));
            $nogeneralfb = empty(trim($data->subqtext[$i]['text']));
            $nolocalvars = empty(trim($data->vars1[$i]));
            $emptypart = $noparttext && $nogeneralfb && $nolocalvars;

            if ($nomark && !$emptypart) {
                $errors["answermark[$i]"] = get_string('error_mark', 'qtype_formulas');
            }
            if ($noanswer && !$emptypart) {
                $errors["answer[$i]"] = get_string('error_answer_missing', 'qtype_formulas');
            }

            // No need to validate the remainder of this part if there is no answer or no mark.
            if ($noanswer || $nomark) {
                continue;
            }

            // The mark must be strictly positive.
            if (floatval($data->answermark[$i]) <= 0) {
                $errors["answermark[$i]"] = get_string('error_mark', 'qtype_formulas');
            }

            // The grading criterion must not be empty. Also, if there is no grading criterion, it does
            // not make sense to continue the validation.
            if (empty(trim($data->correctness[$i]))) {
                $errors["correctness[$i]"] = get_string('error_criterion_empty', 'qtype_formulas');
                continue;
            }

            // Create a stdClass for each part, start by setting the questionid property which is
            // common for all parts.
            $partdata[$i] = (object)['questionid' => $data->id];
            // Set the basic fields, e.g. mark, placeholder or definition of local variables.
            foreach (self::PART_BASIC_FIELDS as $field) {
                // In the edit form, the part's 'unitpenalty' and 'ruleid' are set via the global options
                // 'globalunitpenalty' and 'globalruleid'. When importing a question, these fields are
                // already present in each part, so they can be copied over like all the others.
                if ($isfromimport) {
                    $partdata[$i]->$field = trim($data->{$field}[$i]);
                } else {
                    if ($field === 'unitpenalty') {
                        $partdata[$i]->unitpenalty = trim($data->globalunitpenalty);
                    }
                    if ($field === 'ruleid') {
                        $partdata[$i]->ruleid = trim($data->globalruleid);
                    }
                }
            }

            // The various texts are stored as arrays with the keys 'text', 'format' and (if coming from
            // the edit form) 'itemid'. We can just copy that over.
            $partdata[$i]->subqtext = $data->subqtext[$i];
            $partdata[$i]->feedback = $data->feedback[$i];
            $partdata[$i]->partcorrectfb = $data->partcorrectfb[$i];
            $partdata[$i]->partpartiallycorrectfb = $data->partpartiallycorrectfb[$i];
            $partdata[$i]->partincorrectfb = $data->partincorrectfb[$i];
        }

        // If we do not have at least one valid part, output an error message. Attach
        // it to the field where the user can define the answer for the first part.
        // Note: we do only output that error, if there is no other error for the answermark
        // or answer field. Otherwise, we might be in a situation where the user sees
        // "At least one answer is required." below a filled answer field, simply because
        // e.g. all answermarks are set to 0.
        if (count($partdata) === 0 && $hasoneanswer === false) {
            if (empty($errors['answer[0]'])) {
                $errors['answer[0]'] = get_string('error_no_answer', 'qtype_formulas');
            }
        }

        return (object)['errors' => $errors, 'parts' => $partdata];
    }

    /**
     * Check the data from the edit form (or an XML import): parts, answer box placeholders,
     * part placeholders and definitions of variables and expressions. At the same time, calculate
     * the number of expected answers for every part.
     *
     * @param object $data
     * @return object
     */
    public function validate(object $data): object {
        // Collect all error messages in an associative array of the form 'fieldname' => 'error'.
        $errors = [];

        // The fields 'globalunitpenalty' and 'globalruleid' must be validated separately,
        // because they are defined at the question level, even though they affect the parts.
        // If we are importing a question, those fields will not be present, because the values
        // are already stored with the parts.
        // Note: we validate this first, because the fields will be referenced during validation
        // of the parts.
        $isfromimport = property_exists($data, 'unitpenalty') && property_exists($data, 'ruleid');
        if (!$isfromimport) {
            $errors += $this->validate_global_unit_fields($data);
        }

        // Check the parts. We get a stdClass with the properties 'errors' (a possibly empty array)
        // and 'parts' (an array of stdClass objects, one per part).
        $partcheckresult = $this->check_and_filter_parts($data);
        $errors += $partcheckresult->errors;

        // If the basic check failed, we abort and output the error message, because the errors
        // might cause other errors downstream.
        if (!empty($errors)) {
            return (object)array('errors' => $errors, 'answers' => null);
        }

        $parts = $partcheckresult->parts;

        // Make sure that answer box placeholders (if used) are unique for each part.
        // TODO: change to non-capturing catch when dropping support for PHP 7.4.
        foreach ($parts as $i => $part) {
            try {
                qtype_formulas_part::scan_for_answer_boxes($part->subqtext['text'], true);
            } catch (Exception $ingored) {
                $errors["subqtext[$i]"] = get_string('error_answerbox_duplicate', 'qtype_formulas');
            }
        }

        // Separately validate the part placeholders. If we are importing, the question text
        // will be a string. If the data comes from the edit from, it is in the editor's
        // array structure (text, format, itemid).
        $errors += $this->check_placeholders(
            is_array($data->questiontext) ? $data->questiontext['text'] : $data->questiontext,
            $parts
        );

        // Finally, check definition of variables (local, grading), various expressions
        // depending on those variables (model answers, correctness criterion) and unit
        // stuff. This check also allows us to calculate the number of answers for each part,
        // a value that we store as 'numbox'.
        $evaluationresult = $this->check_variables_and_expressions($data, $parts);
        $errors += $evaluationresult->errors;
        $parts = $evaluationresult->parts;

        // FIXME: add default options if no parts defined
        if (count($parts) === 0 && false) {
            $parts[0] =
            (object)[
                'answermark' => get_config('qtype_formulas')->defaultanswermark,
                'questionid' => $data->id,
                'varsrandom' => '',
                'varsglobal' => '',
                'correctfeedback' => get_string('correctfeedbackdefault', 'question'),
                'correctfeedbackformat' => FORMAT_HTML,
                'partiallycorrectfeedback' => get_string('partiallycorrectfeedbackdefault', 'question'),
                'partiallycorrectfeedbackformat' => FORMAT_HTML,
                'incorrectfeedback' => get_string('incorrectfeedbackdefault', 'question'),
                'incorrectfeedbackformat' => FORMAT_HTML,
                'shownumcorrect' => 0,
                'answernumbering' => 'none',
            ];
            print('****');
        }

        return (object)array('errors' => $errors, 'answers' => $parts);
    }

    /**
     * This function is called during the validation process to validate the special fields
     * 'globalunitpenalty' and 'globalruleid'. Both fields are used as a single option to set
     * the unit penalty and the unit conversion rules for all parts of a question.
     *
     * @param object $data form data to be validated
     * @return array array containing error messages or empty array if no error
     */
    private function validate_global_unit_fields(object $data): array {
        $errors = [];

        if ($data->globalunitpenalty < 0 || $data->globalunitpenalty > 1) {
            $errors['globalunitpenalty'] = get_string('error_unitpenalty', 'qtype_formulas');;
        }

        // If the globalruleid field is missing, that means the request or the form has
        // been modified by the user. In that case, we set the id to the invalid value -1
        // to simplify the code for the upcoming steps.
        if (!isset($data->globalruleid)) {
            $data->globalruleid = -1;
        }

        // Finally, check the global setting for the basic conversion rules. We only check this
        // once, because it is the same for all parts.
        $conversionrules = new unit_conversion_rules();
        $entry = $conversionrules->entry($data->globalruleid);
        if ($entry === null || $entry[1] === null) {
            $errors['globalruleid'] = get_string('error_ruleid', 'qtype_formulas');
        } else {
            $unitcheck = new answer_unit_conversion();
            $unitcheck->assign_default_rules($data->globalruleid, $entry[1]);
            try {
                $unitcheck->reparse_all_rules();
            } catch (Exception $e) {
                $errors['globalruleid'] = $e->getMessage();
            }
        }

        return $errors;
    }

    /**
     * Check definition of variables (local vars, grading vars), various expressions
     * like model answers or correctness criterion and unit stuff. At the same time,
     * calculate the number of answers boxes (to be stored in part->numbox) once the
     * model answers are evaluated. Possible errors are returned in the 'errors' property
     * of the return object. The updated part data (now containing the numbox value)
     * is in the 'parts' property, as an array of objects (one object per part).
     *
     * @param object $data
     * @param object[] $parts
     * @return object stdClass with 'errors' and 'parts'
     */
    public function check_variables_and_expressions(object $data, array $parts): object {
        // Collect all errors.
        $errors = [];

        // Check random variables. If there is an error, we do not continue, because
        // other variables or answers might depend on these definitions.
        $randomparser = new random_parser($data->varsrandom);
        $evaluator = new evaluator();
        try {
            $evaluator->evaluate($randomparser->get_statements());
            $evaluator->instantiate_random_variables();
        } catch (Exception $e) {
            $errors['varsrandom'] = $e->getMessage();
            return (object)['errors' => $errors, 'parts' => $parts];
        }

        // Check global variables. If there is an error, we do not continue, because
        // other variables or answers might depend on these definitions.
        try {
            $globalparser = new parser($data->varsglobal, $randomparser->export_known_variables());
            $evaluator->evaluate($globalparser->get_statements());
        } catch (Exception $e) {
            $errors['varsglobal'] = $e->getMessage();
            return $errors;
        }

        // Check local variables, model answers and grading criterion for each part.
        $numberofparts = count($parts);
        for ($i = 0; $i < $numberofparts; $i++) {
            $partevaluator = clone $evaluator;

            // Validate the local variables for this part. In case of an error, skip the
            // rest of the part, because there might be dependencies.
            $partparser = null;
            if (!empty($data->vars1[$i])) {
                try {
                    $partparser = new parser($data->vars1[$i], $globalparser->export_known_variables());
                    $partevaluator->evaluate($partparser->get_statements());
                } catch (Exception $e) {
                    $errors["vars1[$i]"] = $e->getMessage();
                    continue;
                }
            }

            $knownvars = [];
            // If there were no local variables, the partparser has not been initialized yet.
            // Otherwise, we export its known variables.
            if ($partparser !== null) {
                $knownvars = $partparser->export_known_variables();
            }

            // Check whether the part uses the algebraic answer type.
            $isalgebraic = $data->answertype[$i] == self::ANSWER_TYPE_ALGEBRAIC;

            // Try evaluating the model answers. If this fails, don't validate the rest of
            // this part, because there are dependencies.
            try {
                // If (and only if) the answer is algebraic, the answer parser should
                // interpret ^ as **.
                $answerparser = new answer_parser($data->answer[$i], $knownvars, $isalgebraic);
                $modelanswers = $partevaluator->evaluate($answerparser->get_statements())[0];
            } catch (Exception $e) {
                // If the answer type is algebraic, the model answer field must contain one string (with quotes)
                // or an array of strings. Thus, evaluation of the field's content as done above cannot fail,
                // unless that syntax constraint has not been respected by the user.
                if ($isalgebraic) {
                    $errors["answer[$i]"] = get_string('error_string_for_algebraic_formula', 'qtype_formulas');
                } else {
                    $errors["answer[$i]"] = $e->getMessage();
                }
                continue;
            }

            // Now that we know the model answers, we can set the $numbox property for the part,
            // i. e. the number of answer boxes that are to be shown. Also, we make sure that
            // $modelanswers becomes an array (possibly of one value) of literals.
            if (is_array($modelanswers->value)) {
                // The value can be an array, because the user entered an algebraic variable. That
                // is not accepted.
                if ($modelanswers->type === token::SET) {
                    $errors["answer[$i]"] = 'Invalid answer format: you cannot use an algebraic variable with this answer type';
                    continue;
                }
                $parts[$i]->numbox = count($modelanswers->value);
                $modelanswers = array_map(function ($element) {
                    return $element->value;
                }, $modelanswers->value);
            } else {
                $parts[$i]->numbox = 1;
                $modelanswers = [$modelanswers->value];
            }

            // If the answer type is algebraic and the user provided a valid numerical expression (possibly
            // containing non-algebraic variables), evaluation did not fail, so we still find ourselves with
            // invalid model answers. Furthermore, we must now try to do algebraic evaluation of each answer
            // to check for bad formulas.
            // Finally, if the user correctly specified strings, the quotes have been stripped, so we need to
            // add them again.
            if ($isalgebraic) {
                foreach ($modelanswers as $k => &$answer) {
                    // After the first problematic answer, we do not need to check the rest, so we break.
                    if (!is_string($answer)) {
                        $errors["answer[$i]"] = get_string('error_string_for_algebraic_formula', 'qtype_formulas');
                        break;
                    }

                    // Evaluating the string should give us a numeric value.
                    try {
                        $result = $partevaluator->calculate_algebraic_expression($answer);
                    } catch (Exception $e) {
                        $a = (object)[
                            // Answers are zero-indexed, but users normally count from 1.
                            'answerno' => $k + 1,
                            // The error message may contain line and column numbers, but they don't make
                            // sense in this context, so we'd rather remove them.
                            'message' => preg_replace('/([^:]+:)([^:]+:)/', '', $e->getMessage())
                        ];
                        $errors["answer[$i]"] = get_string('error_in_answer', 'qtype_formulas', $a);
                        break;
                    }

                    // Add quotes around the answer.
                    $answer = '"' . $answer . '"';
                }
                // In case we later write to $answer, this would alter the last entry of the $modelanswers
                // array, so we'd better remove the reference to make sure this won't happend.
                unset($answer);
                // If there was an error, we do not continue the validation.
                if (!empty($errors["answer[$i]"])) {
                    continue;
                }
            }

            // In order to prepare the grading variables, we need to have the special vars like
            // _a and _r or _0, _1, ... or _err and _relerr. We will simulate this part by copying
            // the model answers and thus setting _err and _relerr to 0.
            $command = '_a = [' . implode(',', $modelanswers) . '];';
            $command .= '_r = [' . implode(',', $modelanswers) . '];';
            for ($k = 0; $k < $parts[$i]->numbox; $k++) {
                $command .= "_{$k} = {$modelanswers[$k]};";
            }
            $command .= '_diff = [' . implode(',', array_fill(0, $parts[$i]->numbox, '0')) . '];';
            $command .= '_err = 0;';
            if (!$isalgebraic) {
                $command .= '_relerr = 0;';
            }
            $partparser = new parser($command, $knownvars);
            // Evaluate all that in God mode, because we set special variables.
            $partevaluator->evaluate($partparser->get_statements(), true);
            // Update the list of known variables.
            $knownvars = $partparser->export_known_variables();

            // Validate grading variables.
            if (!empty($data->vars2[$i])) {
                try {
                    $partparser = new parser($data->vars2[$i], $knownvars);
                    $partevaluator->evaluate($partparser->get_statements());
                } catch (Exception $e) {
                    $errors["vars2[$i]"] = $e->getMessage();
                    continue;
                }
            }
            // Update the list of known variables.
            $knownvars = $partparser->export_known_variables();

            // Check grading criterion.
            $grade = 0;
            try {
                $partparser = new parser($data->correctness[$i], $knownvars);
                $result = $partevaluator->evaluate($partparser->get_statements());
                $num = count($result);
                if ($num > 1) {
                    $errors["correctness[$i]"] = get_string('error_grading_single_expression', 'qtype_formulas', $num);
                }
                $grade = $result[0]->value;
            } catch (Exception $e) {
                $message = $e->getMessage();
                // If we are working with the answer type "algebraic formula" and there was an error
                // during evaluation of the grading criterion *and* the error message contains '_relerr',
                // we change the message, because it is save to assume that the teacher tried to use
                // relative error which is not supported with that answer type.
                if ($isalgebraic && strpos($message, '_relerr') !== false) {
                    $message = get_string('error_algebraic_relerr', 'qtype_formulas');
                }
                $errors["correctness[$i]"] = $message;
                continue;
            }

            // We used the model answers, so the grading criterion should always evaluate to 1 (or more).
            if ($grade < 0.999) {
                $errors["correctness[$i]"] = get_string('error_grading_not_1', 'qtype_formulas', $grade);
            }

            // Instantiate toolkit class for units.
            $unitcheck = new answer_unit_conversion();

            // If a unit has been provided, check whether it can be parsed.
            if (!empty($data->postunit[$i])) {
                try {
                    $unitcheck->parse_targets($data->postunit[$i]);
                } catch (Exception $e) {
                    $errors["postunit[$i]"] = get_string('error_unit', 'qtype_formulas') . $e->getMessage();
                }
            }

            // If provided by the user, check the additional conversion rules. We do validate those
            // rules even if the unit has not been set, because we would not want to have invalid stuff
            // in the database.
            if (!empty($data->otherrule[$i])) {
                try {
                    $unitcheck->assign_additional_rules($data->otherrule[$i]);
                    $unitcheck->reparse_all_rules();
                } catch (Exception $e) {
                    $errors["otherrule[$i]"] = get_string('error_rule', 'qtype_formulas') . $e->getMessage();
                }
            }
        }

        return (object)['errors' => $errors, 'parts' => $parts];
    }

    /**
     * Reorder the parts according to the order of placeholders in main question text.
     * Note: the check_placeholder() function should be called before.
     *
     * @param string $questiontext main question text, containing the placeholders
     * @param object[] $parts part data
     * @return object[] sorted parts
     */
    public function reorder_parts(string $questiontext, array $parts): array {
        // Scan question text for part placeholders; $matches[1] will contain a list of
        // the matches in the order of appearance.
        $matches = [];
        preg_match_all('/\{(#\w+)\}/', $questiontext, $matches);

        $ordered = [];

        // First, add the parts with a placeholder, ordered by their appearance.
        foreach ($parts as $part) {
            $newindex = array_search($part->placeholder, $matches[1]);
            if ($newindex !== false) {
                $ordered[$newindex] = $part;
            }
        }

        // Now, append all remaining parts that do not have a placeholder.
        foreach ($parts as $part) {
            if (empty($part->placeholder)) {
                $ordered[] = $part;
            }
        }

        // Sort the parts by their index and assign result.
        ksort($ordered);

        return $ordered;
    }
}
