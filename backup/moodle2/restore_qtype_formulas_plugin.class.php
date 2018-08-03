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
 * @package    qtype_formulas
 * @copyright  2010 Hon Wai, Lau <lau65536@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * restore plugin class that provides the necessary information
 * needed to restore one formulas qtype plugin
 *
 * @copyright  2010 Hon Wai, Lau <lau65536@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class restore_qtype_formulas_plugin extends restore_qtype_plugin {

    /**
     * Returns the paths to be handled by the plugin at question level
     */
    protected function define_question_plugin_structure() {

        $paths = array();

        // This qtype uses don't question_answers, qtype_formulas_answers are differents.

        // Add own qtype stuff.
        $elename = 'formulas_answer';
        $elepath = $this->get_pathfor('/formulas_answers/formulas_answer'); // We used get_recommended_name() so this works.
        $paths[] = new restore_path_element($elename, $elepath);
        $elename = 'formulas';
        $elepath = $this->get_pathfor('/formulas'); // We used get_recommended_name() so this works.
        $paths[] = new restore_path_element($elename, $elepath);

        return $paths; // And we return the interesting paths.
    }

    /**
     * Process the qtype/formulas element
     */
    public function process_formulas($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;
        // Detect if the question is created or mapped.
        $oldquestionid   = $this->get_old_parentid('question');
        $newquestionid   = $this->get_new_parentid('question');
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
     * Process the qtype/formulasanswer element
     */
    public function process_formulas_answer($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;

        // Detect if the question is created or mapped.
        $oldquestionid   = $this->get_old_parentid('question');
        $newquestionid   = $this->get_new_parentid('question');
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
                $data->partindex = (int)$DB->get_field('qtype_formulas_answers',
                        'MAX(partindex) +1', array('questionid' => $newquestionid));
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
        return array(
            new restore_decode_content('qtype_formulas_options',
                    array('correctfeedback', 'partiallycorrectfeedback', 'incorrectfeedback'),
                    'qtype_formulas'),
            new restore_decode_content('qtype_formulas_answers', array('subqtext', 'feedback',
                    'partcorrectfb', 'partpartiallycorrectfb', 'partincorrectfb'),
                    'qtype_formulas_answers'),
        );
    }

}
