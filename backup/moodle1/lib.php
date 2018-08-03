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
 * @copyright  2012 Jean-Michel Vedrine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Formulas question type conversion handler
 */
class moodle1_qtype_formulas_handler extends moodle1_qtype_handler {

    /**
     * @return array
     */
    public function get_question_subpaths() {
        return array(
            'FORMULAS',
            'FORMULAS/ANSWERS'

        );
    }

    /**
     * Appends the formulas specific information to the question
     */
    public function process_question(array $data, array $raw) {
        // Convert and write the formulas answers first.
        // We can't use write_answers  for that task.
        // Because formulas answers aren't standard answers.
        if (isset($data['formulas'][0]['answers'])) {
            $answers   = $data['formulas'][0]['answers'];
        } else {
            $answers   = array();
        }
        $anscount = 0;
        $this->xmlwriter->begin_tag('formulas_answers');
        foreach ($answers as $answer) {
            // Create an artificial 'id' attribute (is not included in moodle.xml).
            $answer['id'] = $this->converter->get_nextid();
            // Add missing fields.
            $answer['partindex'] = $anscount;
            $answer['subqtextformat'] = FORMAT_HTML;
            $answer['feedbackformat'] = FORMAT_HTML;
            // Add part's combined feedback.
            $answer['partcorrectfb'] = '';
            $answer['partcorrectfbformat'] = FORMAT_HTML;
            $answer['partpartiallycorrectfb'] = '';
            $answer['partpartiallycorrectfbformat'] = FORMAT_HTML;
            $answer['partincorrectfb'] = '';
            $answer['partincorrectfbformat'] = FORMAT_HTML;

            // Migrate images in answers subqtext and feedback fields.
            // Uncomment the 2 following lines once MDL-33424 is closed.
            $answer['subqtext'] = $this->migrate_files($answer['subqtext'], 'qtype_formulas', 'answersubqtext', $answer['id']);
            $answer['feedback'] = $this->migrate_files($answer['feedback'], 'qtype_formulas', 'answerfeedback', $answer['id']);

            $this->xmlwriter->begin_tag('formulas_answer', array('id' => $answer['id']));
            foreach (array(
                'partindex', 'placeholder', 'answermark', 'answertype',
                'numbox', 'vars1', 'answer', 'vars2', 'correctness', 'unitpenalty',
                'postunit', 'ruleid', 'otherrule', 'subqtext', 'subqtextformat',
                'feedback', 'feedbackformat', 'partcorrectfb', 'partcorrectfbformat',
                'partpartiallycorrectfb', 'partpartiallycorrectfbformat',
                'partincorrectfb', 'partincorrectfbformat'
            ) as $fieldname) {
                if (!array_key_exists($fieldname, $answer)) {
                    throw new moodle1_convert_exception('missing_formulas_answer_field', $fieldname);
                }
                $this->xmlwriter->full_tag($fieldname, $answer[$fieldname]);
            }
            $this->xmlwriter->end_tag('formulas_answer');
            ++$anscount;
        }
        $this->xmlwriter->end_tag('formulas_answers');

        // And finally the formulas options.
        $options = $data['formulas'][0];
        if (!isset($options)) {
            // This should never happen, but it can do if the 1.9 site contained
            // corrupt data.
            $options = array(
                'varsrandom'  => '',
                'varsglobal' => ''
            );
        }
        $this->xmlwriter->begin_tag('formulas', array('id' => $this->converter->get_nextid()));
        $this->xmlwriter->full_tag('varsrandom', $options['varsrandom']);
        $this->xmlwriter->full_tag('varsglobal', $options['varsglobal']);
        $this->xmlwriter->full_tag('correctfeedback', '');
        $this->xmlwriter->full_tag('correctfeedbackformat', FORMAT_HTML);
        $this->xmlwriter->full_tag('partiallycorrectfeedback', '');
        $this->xmlwriter->full_tag('partiallycorrectfeedbackformat', FORMAT_HTML);
        $this->xmlwriter->full_tag('incorrectfeedback', '');
        $this->xmlwriter->full_tag('incorrectfeedbackformat', FORMAT_HTML);
        $this->xmlwriter->full_tag('shownumcorrect', 0);
        $this->xmlwriter->full_tag('answernumbering', 'none');
        $this->xmlwriter->end_tag('formulas');
    }
}
