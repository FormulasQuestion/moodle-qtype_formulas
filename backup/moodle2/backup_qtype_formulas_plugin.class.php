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
 * Provides the information to backup formulas questions
 *
 * @copyright  2010 Hon Wai, Lau <lau65536@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class backup_qtype_formulas_plugin extends backup_qtype_plugin {

    /**
     * Returns the qtype information to attach to question element
     */
    protected function define_question_plugin_structure() {

        // Define the virtual plugin element with the condition to fulfill.
        $plugin = $this->get_plugin_element(null, '../../qtype', 'formulas');

        // Create one standard named plugin element (the visible container).
        $pluginwrapper = new backup_nested_element($this->get_recommended_name());

        // Connect the visible container ASAP.
        $plugin->add_child($pluginwrapper);

        // WARNING This qtype don't uses standard question_answers, qtype_formulas_answers are differents.

        // Now create the qtype own structures.

        $formulas = new backup_nested_element('formulas', array('id'), array(
            'varsrandom', 'varsglobal',
            'correctfeedback', 'correctfeedbackformat',
            'partiallycorrectfeedback', 'partiallycorrectfeedbackformat',
            'incorrectfeedback', 'incorrectfeedbackformat', 'shownumcorrect',
            'answernumbering'));

        $formulasanswers = new backup_nested_element('formulas_answers');
        $formulasanswer = new backup_nested_element('formulas_answer', array('id'), array(
            'placeholder', 'answermark', 'answertype', 'numbox', 'vars1', 'answer', 'vars2', 'correctness',
            'unitpenalty', 'postunit', 'ruleid', 'otherrule', 'subqtext', 'subqtextformat', 'feedback', 'feedbackformat',
            'partcorrectfb', 'partcorrectfbformat',
            'partpartiallycorrectfb', 'partpartiallycorrectfbformat',
            'partincorrectfb', 'partincorrectfbformat'));

        // Don't need to annotate ids nor files.
        // Now the own qtype tree.
        // Order is important because we need to know formulas_answers ids,
        // to fill the formulas answerids field at restore.
        $pluginwrapper->add_child($formulasanswers);
        $formulasanswers->add_child($formulasanswer);
        $pluginwrapper->add_child($formulas);

        // Set source to populate the data.
        $formulasanswer->set_source_sql('
                SELECT *
                FROM {qtype_formulas_answers}
                WHERE questionid = :questionid
                ORDER BY partindex',
                array('questionid' => backup::VAR_PARENTID));

        $formulas->set_source_table('qtype_formulas_options', array('questionid' => backup::VAR_PARENTID));

        // Don't need to annotate ids nor files.

        return $plugin;
    }

    /**
     * Returns one array with filearea => mappingname elements for the qtype
     *
     * Used by {@link get_components_and_fileareas} to know about all the qtype
     * files to be processed both in backup and restore.
     */
    public static function get_qtype_fileareas() {
        return array(
            'answersubqtext' => 'qtype_formulas_answers',
            'answerfeedback' => 'qtype_formulas_answers',
            'correctfeedback' => 'question_created',
            'partiallycorrectfeedback' => 'question_created',
            'incorrectfeedback' => 'question_created',
            'partcorrectfb' => 'qtype_formulas_answers',
            'partpartiallycorrectfb' => 'qtype_formulas_answers',
            'partincorrectfb' => 'qtype_formulas_answers');
    }
}
