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
 * Restore plugin class that provides the necessary information
 * needed to restore one formulas qtype plugin
 *
 * @package    qtype_formulas
 * @copyright  2010 Hon Wai, Lau <lau65536@gmail.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class restore_qtype_formulas_plugin extends restore_qtype_plugin {

    /**
     * The Formulas question type uses some custom fields that have to be backed up, but are not
     * accounted for in the default routines. This function defines the necessary XML paths.
     *
     * @return array
     */
    protected function define_question_plugin_structure() {
        $paths = [];

        // Upon initial creation, the names have been derived from get_recommended_name(). These
        // paths MUST NOT be changed, because that would break compatibility with existing backups.
        // Those paths will be created as subpaths inside the <plugin_qtype_formulas_question>
        // section. For every question, we will have <formulas_answers></formulas_answers> to enclose
        // all parts. For each part, there will then be a <formulas_answer id="..."></formulas_answer>
        // section containing the various data fields, e. g. <placeholder> or <numbox> etc. These
        // are the fields stored in the qtype_formulas_answers table.
        $paths[] = new restore_path_element('formulas_answer', $this->get_pathfor('/formulas_answers/formulas_answer'));

        // Additionally, there will be <formulas id="..."></formulas> containing the custom data
        // fields at the question level, e. g. <varsrandom>...</varsrandom> etc. These are the fields
        // stored in the qtype_formulas_options table.
        $paths[] = new restore_path_element('formulas', $this->get_pathfor('/formulas'));

        return $paths;
    }

    /**
     * This function processes the <formulas> XML element for the backup, i. e. the part where the
     * specific question level data like varsrandom or varsglobal are backed up. That's the data stored
     * in the qtype_formulas_options table.
     *
     * @param array $data data from the XML element <formulas>
     */
    public function process_formulas($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;

        // Detect if the question is created or mapped.
        $oldquestionid = $this->get_old_parentid('question');
        $newquestionid = $this->get_new_parentid('question');
        $questioncreated = $this->get_mappingid('question_created', $oldquestionid) ? true : false;

        // If the question has been created by restore, we need to create its qtype_formulas_options too.
        if ($questioncreated) {
            // Some 2.0 backups are missing the combined feedback.
            if (!isset($data->correctfeedback)) {
                $data->correctfeedback = '';
                $data->correctfeedbackformat = FORMAT_HTML;
            }
            if (!isset($data->partiallycorrectfeedback)) {
                $data->partiallycorrectfeedback = '';
                $data->partiallycorrectfeedbackformat = FORMAT_HTML;
            }
            if (!isset($data->incorrectfeedback)) {
                $data->incorrectfeedback = '';
                $data->incorrectfeedbackformat = FORMAT_HTML;
            }
            if (!isset($data->shownumcorrect)) {
                $data->shownumcorrect = 0;
            }
            if (!isset($data->answernumbering)) {
                $data->answernumbering = 'none';
            }
            // Adjust some columns.
            $data->questionid = $newquestionid;
            // Insert record.
            $newitemid = $DB->insert_record('qtype_formulas_options', $data);
            // Create mapping (needed for decoding links).
            $this->set_mapping('qtype_formulas_options', $oldid, $newitemid);
        }
    }

    /**
     * This function processes the <formulas_answer> XML element for the backup, i. e. the part where
     * the specific part level data like answertype or subqtext are backed up. That's the data stored
     * in the qtype_formulas_answers table.
     *
     * @param array $data data from the <formulas_answer> XML element
     */
    public function process_formulas_answer($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;

        // Detect if the question is created or mapped.
        $oldquestionid = $this->get_old_parentid('question');
        $newquestionid = $this->get_new_parentid('question');
        $questioncreated = $this->get_mappingid('question_created', $oldquestionid) ? true : false;

        // If the question has been created by restore, we need to create its qtype_formulas_answers too.
        if ($questioncreated) {
            // Adjust some columns.
            $data->questionid = $newquestionid;
            // Some 2.0 backups are missing the feedbackformat.
            if (!isset($data->feedbackformat)) {
                $data->feedbackformat = FORMAT_HTML;
            }
            // All 2.0 backups are missing the part's index.
            if (!isset($data->partindex)) {
                $data->partindex = (int)$DB->get_field(
                    'qtype_formulas_answers',
                    'MAX(partindex) + 1',
                    ['questionid' => $newquestionid],
                );
            }
            // Old backups are missing the part's combined feedback.
            if (!isset($data->partcorrectfb)) {
                $data->partcorrectfb = '';
                $data->partcorrectfbformat = FORMAT_HTML;
            }
            if (!isset($data->partpartiallycorrectfb)) {
                $data->partpartiallycorrectfb = '';
                $data->partpartiallycorrectfbformat = FORMAT_HTML;
            }
            if (!isset($data->partincorrectfb)) {
                $data->partincorrectfb = '';
                $data->partincorrectfbformat = FORMAT_HTML;
            }
            // Older backups might not yet have the answernotunique field.
            if (!isset($data->answernotunique)) {
                $data->answernotunique = '1';
            }
            // Backups prior to 6.2 do not yet have the emptyallowed field. In that case, we set it
            // to false. It should default to true for *new* questions only.
            if (!isset($data->emptyallowed)) {
                $data->emptyallowed = '0';
            }
            // Insert record.
            $newitemid = $DB->insert_record('qtype_formulas_answers', $data);
            // Create mapping.
            $this->set_mapping('qtype_formulas_answers', $oldid, $newitemid);
        }
    }

    /**
     * Return the contents of this qtype to be processed by the links decoder
     */
    public static function define_decode_contents() {
        return [
            new restore_decode_content(
                'qtype_formulas_options',
                ['correctfeedback', 'partiallycorrectfeedback', 'incorrectfeedback'],
                'qtype_formulas',
            ),
            new restore_decode_content(
                'qtype_formulas_answers',
                ['subqtext', 'feedback', 'partcorrectfb', 'partpartiallycorrectfb', 'partincorrectfb'],
                'qtype_formulas_answers',
            ),
        ];
    }

    /**
     * Convert the backup structure of the Formulas question type into a structure matching its
     * question data. This data will then be used to produce an identity hash for comparison with
     * questions in the database. We have to override the parent function, because we use a special
     * structure during backup.
     *
     * @param array $backupdata
     * @return stdClass
     */
    public static function convert_backup_to_questiondata(array $backupdata): stdClass {
        $questiondata = parent::convert_backup_to_questiondata($backupdata);

        // As our parts are backed up in a separate XML key rather than just "answers", the parent
        // function did not add them to the questiondata. Old backups may lack the "answernotunique"
        // key, in which case we add it here with the default value. Also, backups might miss the
        // "partindex" key. In that case, we add the key and order the parts according to their appearance
        // in the file.
        foreach ($backupdata['plugin_qtype_formulas_question']['formulas_answers']['formulas_answer'] as $i => $answer) {
            if (!key_exists('answernotunique', $answer)) {
                $answer['answernotunique'] = '1';
            }
            if (!key_exists('emptyallowed', $answer)) {
                $answer['emptyallowed'] = '0';
            }
            if (!key_exists('partindex', $answer)) {
                $answer['partindex'] = $i;
            }
            $questiondata->options->answers[] = (object) $answer;
        }

        // Also, we must make sure that the specific options like varsrandom etc. are added to
        // the questiondata object.
        $questiondata->options = (object) array_merge(
            (array) $questiondata->options,
            $backupdata['plugin_qtype_formulas_question']['formulas'][0],
        );

        return $questiondata;
    }

    /**
     * Return a list of paths to fields to be removed from questiondata before creating an identity hash.
     * We have to remove the id and questionid property from all answers (parts) as well as the numparts
     * field, because it is automatically calculated rather than stored in the database.
     *
     * @return array
     */
    protected function define_excluded_identity_hash_fields(): array {
        return [
            '/options/answers/id',
            '/options/answers/questionid',
            '/options/numparts',
        ];
    }
}
