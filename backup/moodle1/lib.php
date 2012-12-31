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
        $this->xmlwriter->begin_tag('formulasanswers');
        foreach ($answers as $answer) {
            $this->xmlwriter->begin_tag('formulasanswer', array('id' => $this->converter->get_nextid()));
            $this->xmlwriter->full_tag('placeholder', $answer['placeholder']);
            $this->xmlwriter->full_tag('answermark', $answer['answermark']);
            $this->xmlwriter->full_tag('answertype', $answer['answertype']);
            $this->xmlwriter->full_tag('numbox', $answer['numbox']);
            $this->xmlwriter->full_tag('vars1', $answer['vars1']);
            $this->xmlwriter->full_tag('answer', $answer['answer']);
            $this->xmlwriter->full_tag('vars2', $answer['vars2']);
            $this->xmlwriter->full_tag('correctness', $answer['correctness']);
            $this->xmlwriter->full_tag('unitpenalty', $answer['unitpenalty']);
            $this->xmlwriter->full_tag('postunit', $answer['postunit']);
            $this->xmlwriter->full_tag('ruleid', $answer['ruleid']);
            $this->xmlwriter->full_tag('otherrule', $answer['otherrule']);
            $this->xmlwriter->full_tag('subqtext', $answer['subqtext']);
            $this->xmlwriter->full_tag('subqtextformat', FORMAT_HTML);
            $this->xmlwriter->full_tag('feedback', $answer['feedback']);
            $this->xmlwriter->full_tag('feedbackformat', FORMAT_HTML);
            $this->xmlwriter->end_tag('formulasanswer');
        }
        $this->xmlwriter->end_tag('formulasanswers');

        // And finally the formulas options.
		$options = $data['formulas'][0];
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
		$this->xmlwriter->end_tag('formulas');
    }
}
