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
 * Unit tests for backup and restore.
 *
 * @package    qtype_formulas
 * @copyright  2025 Philipp Imhof
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace qtype_formulas;

use backup;
use backup_controller;
use context_course;
use core_question_generator;
use qtype_formulas;
use qtype_formulas_question;
use restore_controller;
use test_question_maker;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');
require_once($CFG->dirroot . '/mod/quiz/locallib.php');
require_once($CFG->dirroot . '/mod/quiz/tests/quiz_question_helper_test_trait.php');
require_once($CFG->dirroot . '/question/engine/tests/helpers.php');
require_once($CFG->dirroot . '/question/type/formulas/questiontype.php');
require_once($CFG->dirroot . '/question/type/formulas/tests/helper.php');

/**
 * Unit tests for backup and restore.
 *
 * @copyright  2025 Philipp Imhof
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @covers     \restore_qtype_formulas_plugin
 * @covers     \backup_qtype_formulas_plugin
 * @covers     \qtype_formulas
 * @covers     \qtype_formulas_question
 * @covers     \qtype_formulas_part
 */
final class backup_restore_test extends \advanced_testcase {
    use \quiz_question_helper_test_trait;

    /**
     * Create a question object of a certain type, as defined in the helper.php file.
     *
     * @return qtype_formulas_question
     */
    protected function get_test_formulas_question($which = null) {
        return test_question_maker::make_question('formulas', $which);
    }

    /**
     * Data provider.
     *
     * @return array
     */
    public static function provide_question_names(): array {
        return [
            ['testsinglenum'],
            ['testsinglenumunit'],
            ['testsinglenumunitsep'],
            ['testtwonums'],
            ['testthreeparts'],
            ['test4'],
            ['testmethodsinparts'],
            ['testalgebraic'],
        ];
    }

    /**
     * Backup and restore a question and check whether the data matches.
     *
     * @param string $questionname name of the test question (e. g. testsinglenum)
     * @dataProvider provide_question_names
     */
    public function test_backup_and_restore(string $questionname): void {
        global $USER, $DB;

        // Login as admin user.
        $this->resetAfterTest(true);
        $this->setAdminUser();

        // Create a course and a quiz with a Formulas question.
        $generator = $this->getDataGenerator();
        /** @var \core_question_generator $questiongenerator */
        $questiongenerator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $course = $generator->create_course();
        $context = context_course::instance($course->id);
        $cat = $questiongenerator->create_question_category(['contextid' => $context->id]);
        $question = $questiongenerator->create_question('formulas', $questionname, ['category' => $cat->id]);
        quiz_add_quiz_question($question->id, $this->create_test_quiz($course));

        // Backup course. By using MODE_IMPORT, we avoid the backup being zipped.
        $bc = new backup_controller(
            backup::TYPE_1COURSE, $course->id, backup::FORMAT_MOODLE, backup::INTERACTIVE_NO, backup::MODE_IMPORT, $USER->id
        );
        $backupid = $bc->get_backupid();
        $bc->execute_plan();
        $bc->destroy();

        // Delete the current course to make sure there is no data.
        delete_course($course, false);

        // Create a new course and restore the backup.
        $newcourse = $generator->create_course();
        $context = context_course::instance($newcourse->id);
        $rc = new restore_controller(
            $backupid, $newcourse->id, backup::INTERACTIVE_NO, backup::MODE_IMPORT, $USER->id, backup::TARGET_NEW_COURSE
        );
        $rc->execute_precheck();
        $rc->execute_plan();
        $rc->destroy();

        // Fetch the quiz and question ID.
        $modules = get_fast_modinfo($newcourse->id)->get_instances_of('quiz');
        $quiz = reset($modules);
        $structure = \mod_quiz\question\bank\qbank_helper::get_question_structure($quiz->instance, $quiz->context);
        $question = reset($structure);

        // Fetch the question and its additional data (random vars, global vars, parts) from the DB.
        $questionrecord = $DB->get_record('question', ['id' => $question->questionid], '*', MUST_EXIST);
        $qtype = new qtype_formulas();
        $qtype->get_question_options($questionrecord);

        // Fetch the reference data for the given question.
        $referencedata = test_question_maker::make_question('formulas', $questionname);

        // First, we check the data for the main question, i. e. basic fields plus custom data for the
        // Formulas question like varsrandom or varsglobal.
        $basicfields = [
            'name',
            'questiontext',
            'questiontextformat',
            'generalfeedback',
            'generalfeedbackformat',
            'defaultmark',
            'penalty',
        ];
        $questionfields = $basicfields + array_slice($qtype->extra_question_fields(), 1);
        foreach ($questionfields as $field) {
            self::assertEquals($referencedata->$field, $questionrecord->$field, $field);
        }

        // Next, we check the fields for each part.
        $partfields = qtype_formulas::PART_BASIC_FIELDS + ['subqtext', 'subqtextformat', 'feedback', 'feedbackformat'];
        foreach ($partfields as $field) {
            foreach ($questionrecord->options->answers as $i => $part) {
                self::assertEquals($referencedata->parts[$i]->$field, $part->$field, $field);
            }
        }

        // Finally, we check the combined feedback fields for the parts and the main question.
        $feedbackfields = ['correctfeedback', 'partiallycorrectfeedback', 'incorrectfeedback'];
        foreach ($feedbackfields as $field) {
            self::assertEquals($referencedata->$field, $questionrecord->options->$field, $field);
            $fieldformat = $field . 'format';
            self::assertEquals($referencedata->$fieldformat, $questionrecord->options->$fieldformat, $fieldformat);
            $origfield = $field;
            foreach ($questionrecord->options->answers as $i => $part) {
                $field = 'part' . str_replace('feedback', 'fb', $origfield);
                self::assertEquals($referencedata->parts[$i]->$field, $part->$field, $field);
                $fieldformat = $field . 'format';
                self::assertEquals($referencedata->parts[$i]->$fieldformat, $part->$fieldformat);
            }
        }

        // Finally, compare the hints.
        $hintfields = ['hint', 'hintformat', 'shownumcorrect', 'clearwrong'];
        $keys = array_keys($questionrecord->hints);
        foreach ($referencedata->hints as $i => $hint) {
            foreach ($hintfields as $field) {
                self::assertEquals($hint->$field, $questionrecord->hints[$keys[$i]]->$field, $field);
            }
        }
    }
}
