<?php
/**
 * For course/quiz backup
 *
 * @copyright &copy; 2010 Hon Wai, Lau
 * @author Hon Wai, Lau <lau65536@gmail.com>
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License version 3
 * @package questionbank
 * @subpackage questiontypes
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Provides the information to backup formulas questions
 */
class backup_qtype_formulas_plugin extends backup_qtype_plugin {

    /**
     * Returns the qtype information to attach to question element
     */
    protected function define_question_plugin_structure() {

        // Define the virtual plugin element with the condition to fulfill
        $plugin = $this->get_plugin_element(null, '../../qtype', 'formulas');

        // Create one standard named plugin element (the visible container)
        $pluginwrapper = new backup_nested_element($this->get_recommended_name());

        // connect the visible container ASAP
        $plugin->add_child($pluginwrapper);
        
        // WARNING This qtype don't uses standard question_answers, qtype_formulas_answers are differents

        // Now create the qtype own structures
        
        $formulas = new backup_nested_element('formulas', array('id'), array(
            'varsrandom', 'varsglobal', 'showperanswermark',
            'correctfeedback', 'correctfeedbackformat',
            'partiallycorrectfeedback', 'partiallycorrectfeedbackformat',
            'incorrectfeedback', 'incorrectfeedbackformat', 'shownumcorrect'));
        
        $formulasanswers = new backup_nested_element('formulasanswers');
        $formulasanswer = new backup_nested_element('formulasanswer', array('id'), array(
            'placeholder', 'answermark', 'answertype', 'numbox', 'vars1', 'answer', 'vars2', 'correctness',
            'unitpenalty','postunit', 'ruleid', 'otherrule', 'subqtext', 'subqtextformat', 'feedback', 'feedbackformat'));

        // don't need to annotate ids nor files
        // Now the own qtype tree
        // Order is important because we need to know formulas_answers ids
        // to fill the formulas answerids field at restore
        $pluginwrapper->add_child($formulasanswers);
        $formulasanswers->add_child($formulasanswer);
        $pluginwrapper->add_child($formulas);

        // set source to populate the data
        $formulasanswer->set_source_table('qtype_formulas_answers', array('questionid' => backup::VAR_PARENTID));
        $formulas->set_source_table('qtype_formulas', array('questionid' => backup::VAR_PARENTID));

        // don't need to annotate ids nor files

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
            'incorrectfeedback' => 'question_created');
    }
}
