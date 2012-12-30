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
 * Moodle formulas question type class.
 *
 * @copyright &copy; 2010-2011 Hon Wai, Lau
 * @author Hon Wai, Lau <lau65536@gmail.com>
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License version 3
 * @package questionbank
 * @subpackage questiontypes
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/questionlib.php');
require_once($CFG->dirroot . '/question/engine/lib.php');
require_once("$CFG->dirroot/question/type/formulas/variables.php");
require_once("$CFG->dirroot/question/type/formulas/answer_unit.php");
require_once("$CFG->dirroot/question/type/formulas/conversion_rules.php");
require_once($CFG->dirroot . '/question/type/formulas/question.php');

/**
 * The formulas question class
 *
 * TODO give an overview of how the class works here.
 */
class qtype_formulas extends question_type {
    private $qv;


    public function __construct() {
        $this->qv = new qtype_formulas_variables();
    }

    /**
     * column names of qtype_formulas_answers table (apart from id and questionid)
     * WARNING qtype_formulas_answers is NOT an extension of answers table
     * so we can't use extra_answer_fields here.
     * subqtext, subqtextformat, feedback and feedbackformat are not included as their handling is special
     * @return array.
     */
    public function subquestion_answer_tags() {
        return array('placeholder', 'answermark', 'answertype', 'numbox', 'vars1', 'answer', 'vars2', 'correctness'
            , 'unitpenalty', 'postunit', 'ruleid', 'otherrule');
    }

    /**
     * If you use extra_question_fields, overload this function to return question id field name
     *  in case you table use another name for this column
     */
    public function questionid_column_name() {
        return 'questionid';
    }

    /**
     * If your question type has a table that extends the question table, and
     * you want the base class to automatically save, backup and restore the extra fields,
     * override this method to return an array wherer the first element is the table name,
     * and the subsequent entries are the column names (apart from id and questionid).
     *
     * @return mixed array as above, or null to tell the base class to do nothing.
     */
    public function extra_question_fields() {
        return array('qtype_formulas', 'varsrandom', 'varsglobal');
    }

    /**
     * Move all the files belonging to this question from one context to another.
     * @param int $questionid the question being moved.
     * @param int $oldcontextid the context it is moving from.
     * @param int $newcontextid the context it is moving to.
     */
    public function move_files($questionid, $oldcontextid, $newcontextid) {
        $fs = get_file_storage();

        parent::move_files($questionid, $oldcontextid, $newcontextid);
        $fs->move_area_files_to_new_context($oldcontextid,
            $newcontextid, 'qtype_formulas', 'answersubqtext', $questionid);
        $fs->move_area_files_to_new_context($oldcontextid,
            $newcontextid, 'qtype_formulas', 'answerfeedback', $questionid);
        $this->move_files_in_combined_feedback($questionid, $oldcontextid, $newcontextid);
        $this->move_files_in_hints($questionid, $oldcontextid, $newcontextid);
    }

    /**
     * Delete all the files belonging to this question.
     * @param int $questionid the question being deleted.
     * @param int $contextid the context the question is in.
     */
    protected function delete_files($questionid, $contextid) {
        $fs = get_file_storage();

        parent::delete_files($questionid, $contextid);
        $this->delete_files_in_combined_feedback($questionid, $contextid);
        $this->delete_files_in_hints($questionid, $contextid);
        $fs->delete_area_files($contextid, 'question', 'correctfeedback', $questionid);
        $fs->delete_area_files($contextid, 'question', 'partiallycorrectfeedback', $questionid);
        $fs->delete_area_files($contextid, 'question', 'incorrectfeedback', $questionid);
        $fs->delete_area_files($contextid, 'qtype_formulas', 'answersubqtext', $questionid);
        $fs->delete_area_files($contextid, 'qtype_formulas', 'answerfeedback', $questionid);
    }

    /**
     * Loads the question type specific options for the question.
     *
     * This function loads any question type specific options for the
     * question from the database into the question object. This information
     * is placed in the $question->options field. A question type is
     * free, however, to decide on a internal structure of the options field.
     * @return bool            Indicates success or failure.
     * @param object $question The question object for the question. This object
     *                         should be updated to include the question type
     *                         specific information (it is passed by reference).
     */
    public function get_question_options($question) {
        global $DB;
        $question->options = $DB->get_record('qtype_formulas',
                array('questionid' => $question->id), '*', MUST_EXIST);

        parent::get_question_options($question);
        $question->options->answers = $DB->get_records('qtype_formulas_answers', array('questionid' => $question->id), 'id ASC');
        $question->options->numpart = count($question->options->answers);
        $question->options->answers = array_values($question->options->answers);
        foreach ($question->options->answers as $idx => $part) {
            $part->location = $idx;     // It is useful when we only pass the parameter $part, the location stores which part is it.
        }
        return true;
    }

    /**
     * Saves question-type specific options
     *
     * This is called by {@link save_question()} to save the question-type specific data
     * @return object $result->error or $result->noticeyesno or $result->notice
     * @param object $question  This holds the information from the editing form,
     *      it is not a standard question object.
     */
    public function save_question_options($question) {
        global $DB;

        $context = $question->context;
        $result = new stdClass();

        $oldanswers = $DB->get_records('qtype_formulas_answers', array('questionid' => $question->id), 'id ASC');
        try {
            $filtered = $this->validate($question); // Data from the web input interface should be validated.
            if (count($filtered->errors) > 0) {  // There may be errors from import or restore.
                throw new Exception('Format error! Probably import/restore files have been damaged.');
            }
            $ss = $this->create_subquestion_structure($question->questiontext, $filtered->answers);
            // Reorder the answer, so that the ordering of answers is the same as the placeholders ordering in questiontext.
            foreach ($ss->answerorders as $newloc) {
                $newanswers[] = $filtered->answers[$newloc];
            }

            $idcount = 0;
            foreach ($newanswers as $i => $ans) {
                // Subqtext and feedback are now arrays so can't save them like that.
                $subqtextarr = $ans->subqtext;
                $ans->subqtext = $subqtextarr['text'];
                $ans->subqtextformat = $subqtextarr['format'];
                $feedbackarr = $ans->feedback;
                $ans->feedback = $feedbackarr['text'];
                $ans->feedbackformat = $feedbackarr['format'];
                // Update an existing answer if possible.
                $answer = array_shift($oldanswers);
                if (!$answer) {
                    $answer = new stdClass();
                    $answer->questionid = $question->id;
                    $answer->answermark = 1;
                    $answer->numbox = 1;
                    $answer->answer = '';
                    $answer->correctness = '';
                    $answer->ruleid = 1;
                    $answer->trialmarkseq = '';
                    $answer->subqtextformat = 0;
                    $answer->feedbackformat = 0;
                    $ans->id = $DB->insert_record('qtype_formulas_answers', $answer);
                } else {
                    $ans->id = $answer->id;
                }
                $ans->subqtext = $this->import_or_save_files($subqtextarr, $context, 'qtype_formulas', 'answersubqtext', $ans->id);
                $ans->feedback = $this->import_or_save_files($feedbackarr, $context, 'qtype_formulas', 'answerfeedback', $ans->id);
                $DB->update_record('qtype_formulas_answers', $ans);
            }

            // Delete any left over old answer records.
            $fs = get_file_storage();
            foreach ($oldanswers as $oldanswer) {
                $fs->delete_area_files($context->id, 'qtype_formulas', 'answersubqtext', $oldanswer->id);
                $fs->delete_area_files($context->id, 'qtype_formulas', 'answerfeedback', $oldanswer->id);
                $DB->delete_records('qtype_formulas_answers', array('id' => $oldanswer->id));
            }
        } catch (Exception $e) {
            return (object)array('error' => $e->getMessage());
        }
        // Save the question options.
        $options = $DB->get_record('qtype_formulas', array('questionid' => $question->id));
        if (!$options) {
            $options = new stdClass();
            $options->questionid = $question->id;
            $options->correctfeedback = '';
            $options->partiallycorrectfeedback = '';
            $options->incorrectfeedback = '';
            $options->id = $DB->insert_record('qtype_formulas', $options);
        }
        $extraquestionfields = $this->extra_question_fields();
        array_shift($extraquestionfields);
        foreach ($extraquestionfields as $extra) {
            if (!isset($question->$extra)) {
                $result = new stdClass();
                $result->error = "No data for field $extra when saving " .
                    ' formulas options question id ' . $question->id;
                return $result;
            }
            $options->$extra = $question->$extra;
        }
        $options = $this->save_combined_feedback_helper($options, $question, $context, true);
        $DB->update_record('qtype_formulas', $options);

        $this->save_hints($question, true);
        return true;
    }


    // Override the parent save_question in order to change the defaultmark.
    public function save_question($question, $form) {
        $form->defaultmark = array_sum($form->answermark); // Question's default mark is the total of all subquestion's marks.
        $question = parent::save_question($question, $form);
        return $question;
    }

    protected function make_hint($hint) {
        return question_hint_with_parts::load_from_record($hint);
    }

    // Delete question from the question-type specific tables with $questionid.
    public function delete_question($questionid, $contextid) {
        global $DB;
        // The qtype_formulas records are deleted in parent as extra_question_fields records.
        // But we need to take care of qtype_formulas_answers as they are not real answers.
        $DB->delete_records('qtype_formulas_answers', array('questionid' => $questionid));
        parent::delete_question($questionid, $contextid);
    }

    /**
     * Initialise the question_definition fields.
     * @param question_definition $question the question_definition we are creating.
     * @param object $questiondata the question data loaded from the database.
     */
    protected function initialise_question_instance(question_definition $question, $questiondata) {
        parent::initialise_question_instance($question, $questiondata);
        $question->parts = array();
        if (!empty($questiondata->options->answers)) {
            foreach ($questiondata->options->answers as $i => $ans) {
                $part = new qtype_formulas_part();
                foreach ($ans as $key => $value) {
                    $part->{$key} = $value;
                }
                $question->parts[$i] = $part;
            }
        }
        $question->varsrandom = $questiondata->options->varsrandom;
        $question->varsglobal = $questiondata->options->varsglobal;
        $question->qv = new qtype_formulas_variables();
        $question->numpart = count($question->parts);
        if ($question->numpart != 0) {
            $question->fractions = array_fill(0, $question->numpart, 0);
            $question->anscorrs = array_fill(0, $question->numpart, 0);
            $question->unitcorrs = array_fill(0, $question->numpart, 0);
        }
        $this->initialise_combined_feedback($question, $questiondata, true);
    }


    /**
     * Imports the question from Moodle XML format.
     *
     * @param $data structure containing the XML data
     * @param $question question object to fill: ignored by this function (assumed to be null)
     * @param $format format class exporting the question
     * @param $extra extra information (not required for importing this question in this format)
     */
    public function import_from_xml($data, $question, qformat_xml $format, $extra=null) {
        // Return if data type is not our own one.
        $nodeqtype = $data['@']['type'];
        if ($nodeqtype != $this->name()) {
            return false;
        }

        // Import the common question headers and set the corresponding field,
        // Unfortunately we can't use the parent method because it will try to import answers,
        // and fails as formulas "answers" are not real answers but subquestions !!
        $qo = $format->import_headers($data);
        $qo->qtype = $this->name();
        $format->import_combined_feedback($qo, $data, true);
        $format->import_hints($qo, $data, true);

        $extras = $this->extra_question_fields();
        array_shift($extras);
        foreach ($extras as $extra) {
            $qo->$extra = $format->getpath($data, array('#', $extra, 0, '#', 'text', 0, '#'), '', true);
        }

        // Loop over each answer block found in the XML.
        $tags = $this->subquestion_answer_tags();
        $answers = $data['#']['answers'];
        foreach ($answers as $answer) {
            foreach ($tags as $tag) {
                $qotag = &$qo->$tag;
                $qotag[] = $format->getpath($answer, array('#', $tag, 0 , '#' , 'text' , 0 , '#'), '0', false, 'error');
            }

            $subqtexttext = $format->getpath($answer, array('#', 'subqtext', 0, '#', 'text', 0, '#'), '', true);
            $subqtextformat = $format->trans_format($format->getpath($answer,
                    array('#', 'subqtext', 0, '@', 'format'), $format->get_format($qo->questiontextformat)));
            $subqtextfiles = array();
            $files = $format->getpath($answer, array('#', 'subqtext', 0, '#', 'file'), array());
            foreach ($files as $file) {
                $data = new stdclass;
                $data->content = $file['#'];
                $data->name = $file['@']['name'];
                $data->encoding = $file['@']['encoding'];
                $subqtextfiles[] = $data;
            }
            $qo->subqtext[] = array('text'=>$subqtexttext, 'format'=>$subqtextformat, 'files'=>$subqtextfiles);

            $fbtext = $format->getpath($answer, array('#', 'feedback', 0, '#', 'text', 0, '#'), '', true);
            $fbformat = $format->trans_format($format->getpath($answer, array('#', 'feedback', 0, '@', 'format'),
                    $format->get_format($qo->questiontextformat)));
            $fbfiles = array();
            $files = $format->getpath($answer, array('#', 'feedback', 0, '#', 'file'), array());
            foreach ($files as $file) {
                $data = new stdclass;
                $data->content = $file['#'];
                $data->name = $file['@']['name'];
                $data->encoding = $file['@']['encoding'];
                $fbfiles[] = $data;
            }
            $qo->feedback[] = array('text'=>$fbtext, 'format'=>$fbformat, 'files'=>$fbfiles);
        }
        $qo->defaultmark = array_sum($qo->answermark); // Make the defaultmark consistent if not specified.

        return $qo;
    }


    /**
     * Exports the question to Moodle XML format.
     *
     * @param $question question to be exported into XML format
     * @param $format format class exporting the question
     * @param $extra extra information (not required for exporting this question in this format)
     * @return text string containing the question data in XML format
     */
    public function export_to_xml($question, qformat_xml $format, $extra=null) {
        $expout = '';
        $fs = get_file_storage();
        $contextid = $question->contextid;
        $expout .= $format->write_combined_feedback($question->options,
                                                    $question->id,
                                                    $question->contextid);
        $extraquestionfields = $this->extra_question_fields();
        array_shift($extraquestionfields);
        foreach ($extraquestionfields as $extra) {
            $expout .= "<$extra>".$format->writetext($question->options->$extra)."</$extra>\n";
        }

        $tags = $this->subquestion_answer_tags();
        foreach ($question->options->answers as $answer) {
            $expout .= "<answers>\n";
            foreach ($tags as $tag) {
                $expout .= " <$tag>\n  ".$format->writetext($answer->$tag)." </$tag>\n";
            }

            $subqfiles = $fs->get_area_files($contextid, 'qtype_formulas', 'answersubqtext', $answer->id);
            $subqtextformat = $format->get_format($answer->subqtextformat);
            $expout .= " <subqtext format=\"$subqtextformat\">\n";
            $expout .= $format->writetext($answer->subqtext);
            $expout .= $format->write_files($subqfiles);
            $expout .= " </subqtext>\n";

            $fbfiles = $fs->get_area_files($contextid, 'qtype_formulas', 'answerfeedback', $answer->id);
            $feedbackformat = $format->get_format($answer->feedbackformat);
            $expout .= " <feedback format=\"$feedbackformat\">\n";
            $expout .= $format->writetext($answer->feedback);
            $expout .= $format->write_files($fbfiles);
            $expout .= " </feedback>\n";

            $expout .= "</answers>\n";
        }
        return $expout;
    }

    // Check whether the placeholder in the $answers is correct and compatible with $questiontext.
    public function check_placeholder($questiontext, $answers) {
        if (is_array($questiontext)) {
            $text = $questiontext['text'];
        } else {
            $text = $questiontext;
        }
        $placeholder_format = '#\w+';
        $placeholders = array();
        foreach ($answers as $idx => $answer) {
            if ( strlen($answer->placeholder) == 0 ) {
                continue; // No error for empty placeholder.
            }
            $errstr = '';
            if ( strlen($answer->placeholder) >= 40 ) {
                $errstr .= get_string('error_placeholder_too_long', 'qtype_formulas');
            }
            if ( !preg_match('/^'.$placeholder_format.'$/', $answer->placeholder) ) {
                $errstr .= get_string('error_placeholder_format', 'qtype_formulas');
            }
            if ( array_key_exists($answer->placeholder, $placeholders) ) {
                $errstr .= get_string('error_placeholder_sub_duplicate', 'qtype_formulas');
            }
            $placeholders[$answer->placeholder] = true;
            $count = substr_count($text, '{'.$answer->placeholder.'}');
            if ($count<1) {
                $errstr .= get_string('error_placeholder_missing', 'qtype_formulas');
            }
            if ($count>1) {
                $errstr .= get_string('error_placeholder_main_duplicate', 'qtype_formulas');
            }
            if (strlen($errstr) != 0) {
                $errors["placeholder[$idx]"] = $errstr;
            }
        }
        return isset($errors) ? $errors : array();
    }

    /**
     * Check that all required fields have been filled and return the filtered classes of the answers.
     *
     * @param $form all the input form data
     * @return an object with a field 'answers' containing valid answers. Otherwise, the 'errors' field will be set
     */
    public function check_and_filter_answers($form) {
        $tags = $this->subquestion_answer_tags();
        $res = (object)array('answers' => array());
        foreach ($form->answermark as $i => $a) {
            if (strlen(trim($form->answermark[$i])) == 0) {
                continue;   // If no mark, then skip this answer.
            }
            if (floatval($form->answermark[$i]) <= 0) {
                $res->errors["answermark[$i]"] = get_string('error_mark', 'qtype_formulas');
            }
            $skip = false;
            if (strlen(trim($form->answer[$i])) == 0) {
                $res->errors["answer[$i]"] = get_string('error_answer_missing', 'qtype_formulas');
                $skip = true;
            }
            if (strlen(trim($form->correctness[$i])) == 0) {
                $res->errors["correctness[$i]"] = get_string('error_criterion', 'qtype_formulas');
                $skip = true;
            }
            if ($skip) {
                continue;   // If no answer or correctness conditions, it cannot check other parts, so skip.
            }
            $res->answers[$i] = (object)array('questionid' => $form->id);   // Create an object of answer with the id.
            foreach ($tags as $tag) {
                $res->answers[$i]->{$tag} = trim($form->{$tag}[$i]);
            }

            $subqtext = array();
            $subqtext['text'] = $form->subqtext[$i]['text'];
            $subqtext['format'] = $form->subqtext[$i]['format'];
            if (isset($form->subqtext[$i]['itemid'])) {
                $subqtext['itemid'] = $form->subqtext[$i]['itemid'];
            } else if (isset($form->subqtext[$i]['files'])) {
                $subqtext['files'] = $form->subqtext[$i]['files'];
            }
            $res->answers[$i]->subqtext = $subqtext;

            $fb = array();
            $fb['text'] = $form->feedback[$i]['text'];
            $fb['format'] = $form->feedback[$i]['format'];
            if (isset($form->feedback[$i]['itemid'])) {
                $fb['itemid'] = $form->feedback[$i]['itemid'];
            } else if (isset($form->feedback[$i]['files'])) {
                $fb['files'] = $form->feedback[$i]['files'];
            }
            $res->answers[$i]->feedback = $fb;
        }
        if (count($res->answers) == 0) {
            $res->errors["answermark[0]"] = get_string('error_no_answer', 'qtype_formulas');
        }
        return $res;
    }

    // It checks basic errors as well as formula errors by evaluating one instantiation.
    public function validate($form) {
        $errors = array();

        $answerschecked = $this->check_and_filter_answers($form);
        if (isset($answerschecked->errors)) {
            $errors = array_merge($errors, $answerschecked->errors);
        }
        $validanswers = $answerschecked->answers;

        foreach ($validanswers as $idx => $part) {
            if ($part->unitpenalty < 0 || $part->unitpenalty > 1) {
                $errors["unitpenalty[$idx]"] = get_string('error_unitpenalty', 'qtype_formulas');
            }
            try {
                $pattern = '\{(_[0-9u][0-9]*)(:[^{}]+)?\}';
                preg_match_all('/'.$pattern.'/', $part->subqtext['text'], $matches);
                $boxes = array();
                foreach ($matches[1] as $j => $match) {
                    if (array_key_exists($match, $boxes)) {
                        throw new Exception(get_string('error_answerbox_duplicate', 'qtype_formulas'));
                    } else {
                        $boxes[$match] = 1;
                    }
                }
            } catch (Exception $e) {
                $errors["subqtext[$idx]"] = $e->getMessage();
            }
        }

        $placeholdererrors = $this->check_placeholder(is_string($form->questiontext) ? $form->questiontext : $form->questiontext['text'],
                $validanswers);
        $errors = array_merge($errors, $placeholdererrors);

        $instantiationerrors = $this->validate_instantiation($form, $validanswers);
        $errors = array_merge($errors, $instantiationerrors);

        return (object)array('errors' => $errors, 'answers' => $validanswers);
    }


    // Validating the data from the client, and return errors.
    // If no errors, the $validanswers should be appended by numbox variables.
    public function validate_instantiation($form, &$validanswers) {
        global $basic_unit_conversion_rules;

        $errors = array();

        // Create a formulas question so we can use its methods for validation.
        $qo = new qtype_formulas_question;
        foreach ($form as $key => $value) {
            $qo->$key = $value;
        }
        $tags = $this->subquestion_answer_tags();
        /* $qo->id = $form->id;
        $qo->questiontext = $form->questiontext;
        $qo->qtype = $form->qtype;
        $qo->generalfeedback = $form->generalfeedback;
        $qo->defaultmark = $form->defaultmark;
        foreach ($tags as $tag) {
            $qo->$tag = $form->$tag;
        }
        $qo->subqtext = $form->subqtext;
        $qo->feedback = $form->feedback;  */
        $qo->options = new stdClass();
        $extraquestionfields = $this->extra_question_fields();
        array_shift($extraquestionfields);
        foreach ($extraquestionfields as $field) {
            if (isset($form->{$field})) {
//                $qo->{$field} = $form->{$field};
                $qo->options->{$field} = $form->{$field};
            }
        }

        if (count($form->answer)) {
            foreach ($form->answer as $key => $answer) {
                $ans = new stdClass();
                foreach ($tags as $tag) {
                    $ans->{$tag} = $form->{$tag}[$key];
                }
                $ans->subqtext =$form->subqtext[$key];
                $ans->feedback = $form->feedback[$key];
                $qo->options->answers[] = $ans;
            }
        }
        $qo->parts = array();
        if (!empty($qo->options->answers)) {
            foreach ($qo->options->answers as $i => $ans) {
                $ans->location = $i;
                $ans->subqtextformat = $ans->subqtext['format'];
                $ans->subqtext = $ans->subqtext['text'];
                $ans->feedbackformat = $ans->feedback['format'];
                $ans->feedback = $ans->feedback['text'];

                $qo->parts[$i] = new qtype_formulas_part();
                foreach ($ans as $key => $value) {
                    $qo->parts[$i]->$key = $value;
                    // TODO verify if part id is set (but do we actually need it here?).
                }
            }
        }
        $qo->qv = new qtype_formulas_variables();
        $qo->options->numpart = count($qo->options->answers);
        $qo->numpart = $qo->options->numpart;
/*        $qo->fractions = array_fill(0, $qo->numpart, 0);
        $qo->anscorrs = array_fill(0, $qo->numpart, 0);
        $qo->unitcorrs = array_fill(0, $qo->numpart, 0);   */

        try {
            $vstack = $qo->qv->parse_random_variables($qo->varsrandom);
            $qo->randomsvars = $qo->qv->instantiate_random_variables($vstack); // Instantiate a set of random variables.
        } catch (Exception $e) {
            $errors["varsrandom"] = $e->getMessage();
            return $errors;
        }

        try {
            $qo->globalvars = $qo->qv->evaluate_assignments($qo->randomsvars, $qo->varsglobal);
        } catch (Exception $e) {
            $errors["varsglobal"] = get_string('error_validation_eval', 'qtype_formulas') . $e->getMessage();
            return $errors;
        }

        // Attempt to compute answers to see if they are wrong or not.
        foreach ($validanswers as $idx => $ans) {
            $ans->location = $idx;
            $unitcheck = new answer_unit_conversion;

            try {
                $unitcheck->parse_targets($ans->postunit);
            } catch (Exception $e) {
                $errors["postunit[$idx]"] = get_string('error_unit', 'qtype_formulas') . $e->getMessage();
            }

            try {
                $unitcheck->assign_additional_rules($ans->otherrule);
                $unitcheck->reparse_all_rules();
            } catch (Exception $e) {
                $errors["otherrule[$idx]"] = get_string('error_rule', 'qtype_formulas') . $e->getMessage();
            }

            try {
                $entry = $basic_unit_conversion_rules[$ans->ruleid];
                if ($entry === null || $entry[1] === null) {
                    throw new Exception(get_string('error_ruleid', 'qtype_formulas'));
                }
                $unitcheck->assign_default_rules($ans->ruleid, $entry[1]);
                $unitcheck->reparse_all_rules();
            } catch (Exception $e) {
                $errors["ruleid[$idx]"] = $e->getMessage();
            }

            try {
                $vars = $qo->qv->evaluate_assignments($qo->globalvars, $ans->vars1);
            } catch (Exception $e) {
                $errors["vars1[$idx]"] = get_string('error_validation_eval', 'qtype_formulas') . $e->getMessage();
                continue;
            }

            try {
                $modelanswers = $qo->get_evaluated_answer($ans);
                $cloneanswers = $modelanswers;
                // Set the number of 'coordinates' which is used to display all answer boxes.
                $ans->numbox = count($modelanswers);
                $gradingtype = $ans->answertype;
            } catch (Exception $e) {
                $errors["answer[$idx]"] = $e->getMessage();
                continue;
            }

            try {
                $dres = $qo->compute_response_difference($vars, $modelanswers, $cloneanswers, 1, $gradingtype);
                if ($dres === null) {
                    throw new Exception();
                }
            } catch (Exception $e) {
                $errors["answer[$idx]"] = get_string('error_validation_eval', 'qtype_formulas') . $e->getMessage();
                continue;
            }

            try {
                $qo->add_special_correctness_variables($vars, $modelanswers, $cloneanswers, $dres->diff, $dres->is_number);
                $qo->qv->evaluate_assignments($vars, $ans->vars2);
            } catch (Exception $e) {
                $errors["vars2[$idx]"] = get_string('error_validation_eval', 'qtype_formulas') . $e->getMessage();
                continue;
            }

            try {
                $responses = $qo->get_correct_responses_individually($ans);
                $correctness = $qo->grade_responses_individually($ans, $responses, $unitcheck);
            } catch (Exception $e) {
                $errors["correctness[$idx]"] = get_string('error_validation_eval', 'qtype_formulas') . $e->getMessage();
                continue;
            }

        }
        return $errors;
    }

    /**
     * Split and reorder the main question by the placeholders. The check_placeholder() should be called before
     *
     * @param string $questiontext The input question text containing a set of placeholder
     * @param array $answers Array of answers, containing the placeholder name  (must not empty)
     * @return  object with fields answerorders, pretexts and posttext.
     * This method is used both in save_question_options and in the renderer.
     * In save_question_options only answerorders is used,
     * in the renderer only pretexts and posttext are used.
     */
    public static function create_subquestion_structure($questiontext, $answers) {
        $locations = array();   // Store the (scaled) location of the *named* placeholder in the main text.
        foreach ($answers as $idx => $answer) {
            if (strlen($answer->placeholder) != 0) {
                $locations[] = 1000*strpos($questiontext, '{'.$answer->placeholder.'}') + $idx; // Store the pair (location, idx).
            }
        }
        sort($locations);       // Performs stable sort of location and answerorder pair.

        $ss = new stdClass();
        foreach ($locations as $i => $location) {
            $answerorder = $location%1000;
            $ss->answerorders[] = $answerorder; // Store the new location of the placeholder in the main text.
            list($ss->pretexts[$i], $questiontext) = explode('{'.$answers[$answerorder]->placeholder.'}', $questiontext);
        }
        foreach ($answers as $idx => $answer) {
            if (strlen($answer->placeholder) == 0) { // Add the empty placeholder at the end.
                $ss->answerorders[] = $idx;
                $ss->pretexts[] = $questiontext;
                $questiontext = '';
            }
        }
        $ss->posttext = $questiontext;  // Add the post-question text, if any.

        return $ss;
    }
}

