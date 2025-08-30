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
 * Note: The "test_restore_xxx" tests have originally been written by Mark Johnson while fixing
 * MDL-83541. They have been taken from core's mod/quiz/tests/backup/repeated_restore_test.php
 * and they only work with Moodle versions 4.4.7 and higher, 4.5.3 and higher or 5.0 and higher.
 * They are integrated in here to (i) make sure that backups will still work even when we make
 * changes to our code, (ii) because we want to test multiple variants of our question and
 * (iii) some of them need to be tweaked because of our specific data structure.
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
use Exception;
use qtype_formulas;
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
     * Data provider.
     *
     * @return array
     */
    public static function provide_question_names(): array {
        return [
            ['testsinglenum'],
            ['testalgebraic'],
            ['testtwonums'],
            ['testsinglenumunit'],
            ['testsinglenumunitsep'],
            ['testzero'],
            ['testmce'],
            ['testmc'],
            ['testthreeparts'],
            ['testmethodsinparts'],
            ['testmcetwoparts'],
            ['testmctwoparts'],
            ['testtwoandtwo'],
            ['test4'],
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

    /**
     * Restore a quiz with questions of same stamp into the same course, but different answers.
     *
     * This test is based on the one written by Mark Johnson while fixing MDL-83541.
     *
     * @param string $questionname name of the test question (e. g. testsinglenum)
     * @dataProvider provide_question_names
     */
    public function test_restore_quiz_with_same_stamp_questions(string $questionname): void {
        global $CFG, $DB, $USER;
        $this->resetAfterTest();
        $this->setAdminUser();

        // The changes introduced while fixing MDL-83541 are only present in Moodle 4.4 and newer. It
        // does not make sense to perform this test with older versions.
        if ($CFG->branch < 404) {
            $this->markTestSkipped(
                'Not testing detection of duplicates while restoring in Moodle versions prior to 4.4.',
            );
        }

        // Create a course and a user with editing teacher capabilities.
        $generator = $this->getDataGenerator();
        $course1 = $generator->create_course();
        $teacher = $USER;
        $generator->enrol_user($teacher->id, $course1->id, 'editingteacher');
        $coursecontext = \context_course::instance($course1->id);
        /** @var \core_question_generator $questiongenerator */
        $questiongenerator = $this->getDataGenerator()->get_plugin_generator('core_question');

        // Create a question category.
        $cat = $questiongenerator->create_question_category(['contextid' => $coursecontext->id]);

        // Create 2 quizzes with 2 questions.
        $quiz1 = $this->create_test_quiz($course1);
        $question1 = $questiongenerator->create_question('formulas', $questionname, ['category' => $cat->id]);
        quiz_add_quiz_question($question1->id, $quiz1, 0);

        $question2 = $questiongenerator->create_question('formulas', $questionname, ['category' => $cat->id]);
        quiz_add_quiz_question($question2->id, $quiz1, 0);

        // Update question2 to have the same stamp as question1.
        $DB->set_field('question', 'stamp', $question1->stamp, ['id' => $question2->id]);

        // Change the answers of the question2 to be different to question1.
        $question2data = \question_bank::load_question_data($question2->id);
        foreach ($question2data->options->answers as $answer) {
            $newanswer = '999';
            if ($question2data->name === 'test-algebraic') {
                $newanswer = '"a*x^3"';
            }
            $DB->set_field('qtype_formulas_answers', 'answer', $newanswer, ['id' => $answer->id]);
        }

        // Backup quiz1.
        $bc = new backup_controller(backup::TYPE_1ACTIVITY, $quiz1->cmid, backup::FORMAT_MOODLE,
            backup::INTERACTIVE_NO, backup::MODE_IMPORT, $teacher->id);
        $backupid = $bc->get_backupid();
        $bc->execute_plan();
        $bc->destroy();

        // Restore the backup into the same course.
        $rc = new restore_controller($backupid, $course1->id, backup::INTERACTIVE_NO, backup::MODE_IMPORT,
            $teacher->id, backup::TARGET_CURRENT_ADDING);
        $rc->execute_precheck();
        $rc->execute_plan();
        $rc->destroy();

        // Verify that the newly-restored quiz uses the same question as quiz2.
        $modules = get_fast_modinfo($course1->id)->get_instances_of('quiz');
        $this->assertCount(2, $modules);
        $quiz2structure = \mod_quiz\question\bank\qbank_helper::get_question_structure(
            $quiz1->id,
            \context_module::instance($quiz1->cmid),
        );
        $quiz2 = end($modules);
        $quiz2structure = \mod_quiz\question\bank\qbank_helper::get_question_structure($quiz2->instance, $quiz2->context);
        $this->assertEquals($quiz2structure[1]->questionid, $quiz2structure[1]->questionid);
        $this->assertEquals($quiz2structure[2]->questionid, $quiz2structure[2]->questionid);
    }

    /**
     * Data provider.
     *
     * @return array
     */
    public static function provide_xml_keys_to_remove(): array {
        return [
            ['answernotunique'],
            ['partindex'],
        ];
    }

    /**
     * Restore a quiz with a question where a field is missing in the backup.
     *
     * @param string $which the XML key to remove from the backup
     *
     * @dataProvider provide_xml_keys_to_remove
     */
    public function test_restore_quiz_if_field_is_missing_in_backup(string $which): void {
        global $CFG, $USER;
        $this->resetAfterTest();
        $this->setAdminUser();

        // The changes introduced while fixing MDL-83541 are only present in Moodle 4.4 and newer. It
        // does not make sense to perform this test with older versions.
        if ($CFG->branch < 404) {
            $this->markTestSkipped(
                'Not testing detection of duplicates while restoring in Moodle versions prior to 4.4.',
            );
        }

        // Create a course and a user with editing teacher capabilities.
        $generator = $this->getDataGenerator();
        $course1 = $generator->create_course();
        $teacher = $USER;
        $generator->enrol_user($teacher->id, $course1->id, 'editingteacher');
        $coursecontext = \context_course::instance($course1->id);
        /** @var \core_question_generator $questiongenerator */
        $questiongenerator = $this->getDataGenerator()->get_plugin_generator('core_question');

        // Create a question category.
        $cat = $questiongenerator->create_question_category(['contextid' => $coursecontext->id]);

        // Create a quiz with a multipart Formulas question.
        $quiz = $this->create_test_quiz($course1);
        $question = $questiongenerator->create_question('formulas', 'testmethodsinparts', ['category' => $cat->id]);
        quiz_add_quiz_question($question->id, $quiz, 0);

        // Backup quiz1.
        $bc = new backup_controller(backup::TYPE_1ACTIVITY, $quiz->cmid, backup::FORMAT_MOODLE,
            backup::INTERACTIVE_NO, backup::MODE_IMPORT, $teacher->id);
        $backupid = $bc->get_backupid();
        $bc->execute_plan();
        $bc->destroy();

        // Delete requested entry from questions.xml file in the backup.
        $xmlfile = $bc->get_plan()->get_basepath() . '/questions.xml';
        $xml = file_get_contents($xmlfile);
        $xml = preg_replace("=<$which>[^<]+</$which>=", '', $xml);
        file_put_contents($xmlfile, $xml);

        // Restore the (modified) backup into the same course.
        $rc = new restore_controller($backupid, $course1->id, backup::INTERACTIVE_NO, backup::MODE_IMPORT,
            $teacher->id, backup::TARGET_CURRENT_ADDING);
        $rc->execute_precheck();
        $rc->execute_plan();
        $rc->destroy();

        // Verify that the newly-restored quiz uses the same question as quiz1.
        $modules = get_fast_modinfo($course1->id)->get_instances_of('quiz');
        $this->assertCount(2, $modules);
        $quizstructure = \mod_quiz\question\bank\qbank_helper::get_question_structure(
            $quiz->id,
            \context_module::instance($quiz->cmid),
        );
        $restoredquiz = end($modules);
        $restoredquizstructure = \mod_quiz\question\bank\qbank_helper::get_question_structure(
            $restoredquiz->instance,
            $restoredquiz->context,
        );
        $this->assertEquals($quizstructure[1]->questionid, $restoredquizstructure[1]->questionid);
    }

    public function test_restore_of_legacy_backup_with_missing_fields(): void {
        global $DB, $USER;
        $this->resetAfterTest();
        $this->setAdminUser();

        // Create a course and a user with editing teacher capabilities.
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $teacher = $USER;
        $generator->enrol_user($teacher->id, $course->id, 'editingteacher');
        $coursecontext = \context_course::instance($course->id);
        /** @var \core_question_generator $questiongenerator */
        $questiongenerator = $this->getDataGenerator()->get_plugin_generator('core_question');

        // Create a question category.
        $cat = $questiongenerator->create_question_category(['contextid' => $coursecontext->id]);

        // Create a quiz with a multipart Formulas question.
        $quiz = $this->create_test_quiz($course);
        $question = $questiongenerator->create_question('formulas', 'testmethodsinparts', ['category' => $cat->id]);
        quiz_add_quiz_question($question->id, $quiz, 0);

        // Backup quiz.
        $bc = new backup_controller(backup::TYPE_1ACTIVITY, $quiz->cmid, backup::FORMAT_MOODLE,
            backup::INTERACTIVE_NO, backup::MODE_IMPORT, $teacher->id);
        $backupid = $bc->get_backupid();
        $bc->execute_plan();
        $bc->destroy();

        // Delete requested entry from questions.xml file in the backup.
        $xmlfile = $bc->get_plan()->get_basepath() . '/questions.xml';
        $xml = file_get_contents($xmlfile);

        // List of fields that can be missing from older backups. We use the default
        // value that will be assigned in restore_qtype_formulas_plugin.class.php.
        $fieldstoremove = [
            'answernotunique' => '1',
            'shownumcorrect' => 0,
            'answernumbering' => 'none',
            'feedbackformat' => FORMAT_HTML,
            'partindex' => null,
            'correctfeedback' => '',
            'partiallycorrectfeedback' => '',
            'incorrectfeedback' => '',
            'partcorrectfb' => '',
            'partpartiallycorrectfb' => '',
            'partincorrectfb' => '',
        ];
        // Remove these fields from the backup file.
        foreach (array_keys($fieldstoremove) as $field) {
            $xml = preg_replace("#<$field( format=\"html\")?>[^<]+</$field>#", '', $xml);
        }
        file_put_contents($xmlfile, $xml);

        // Delete the current course to make sure there is no data.
        delete_course($course, false);

        // Create a new course and restore the backup.
        $newcourse = $generator->create_course();
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

        // Check whether the previously removed fields have been populated with their
        // respective default values.
        foreach ($fieldstoremove as $field => $defaultvalue) {
            // The fields 'shownumcorrect', 'answernumbering' and '...feedback' (combined
            // feedback) are stored at the options level.
            if ($field == 'shownumcorrect' || $field == 'answernumbering' || preg_match('/feedback$/', $field)) {
                self::assertEquals($defaultvalue, $questionrecord->options->$field, $field);
                if (strstr($field, 'num') === false) {
                    $format = $field . 'format';
                    self::assertEquals(FORMAT_HTML, $questionrecord->options->$format, $format);
                }
            } else {
                // All other fields are stored at the answers level. We check all parts. The partindex
                // will be assigned according to the appearance. For the other fields, we use the default
                // value. The combined feedback fields are stored as part....fb.
                foreach ($questionrecord->options->answers as $i => $answer) {
                    if ($field === 'partindex') {
                        self::assertEquals($i, $answer->$field, $field);
                    } else {
                        self::assertEquals($defaultvalue, $answer->$field, $field);
                    }
                    if (strstr($field, 'fb') !== false) {
                        $format = $field . 'format';
                        self::assertEquals(FORMAT_HTML, $answer->$format, $format);
                    }
                }
            }
        }
    }

    public function test_restore_of_backup_with_empty_formulas_section(): void {
        global $DB, $USER, $CFG;
        $this->resetAfterTest();
        $this->setAdminUser();

        // Create a course and a user with editing teacher capabilities.
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $teacher = $USER;
        $generator->enrol_user($teacher->id, $course->id, 'editingteacher');
        $coursecontext = \context_course::instance($course->id);
        /** @var \core_question_generator $questiongenerator */
        $questiongenerator = $this->getDataGenerator()->get_plugin_generator('core_question');

        // Create a question category.
        $cat = $questiongenerator->create_question_category(['contextid' => $coursecontext->id]);

        // Create a quiz with a multipart Formulas question.
        $quiz = $this->create_test_quiz($course);
        $question = $questiongenerator->create_question('formulas', 'testmethodsinparts', ['category' => $cat->id]);
        quiz_add_quiz_question($question->id, $quiz, 0);

        // Backup quiz.
        $bc = new backup_controller(backup::TYPE_1ACTIVITY, $quiz->cmid, backup::FORMAT_MOODLE,
            backup::INTERACTIVE_NO, backup::MODE_IMPORT, $teacher->id);
        $backupid = $bc->get_backupid();
        $bc->execute_plan();
        $bc->destroy();

        // Empty the <formulas> section inside the backup file.
        $xmlfile = $bc->get_plan()->get_basepath() . '/questions.xml';
        $xml = file_get_contents($xmlfile);
        $xml = preg_replace('#<formulas id="(\d+)">.*</formulas>#s', '<formulas id="\1"></formulas>', $xml);
        file_put_contents($xmlfile, $xml);

        // Delete the current course to make sure there is no data.
        delete_course($course, false);

        // Create a new course and restore the backup.
        $newcourse = $generator->create_course();
        $rc = new restore_controller(
            $backupid, $newcourse->id, backup::INTERACTIVE_NO, backup::MODE_IMPORT, $USER->id, backup::TARGET_NEW_COURSE
        );

        // We have corrupt input data, so there will be a warning about the missing array key. We don't want
        // that warning, but rather we want to provoke the real TypeError exception, so we suppress warnings.
        @$rc->execute_precheck();
        @$rc->execute_plan();
        $rc->destroy();

        // Fetch the quiz and question ID.
        $modules = get_fast_modinfo($newcourse->id)->get_instances_of('quiz');
        $quiz = reset($modules);
        $structure = \mod_quiz\question\bank\qbank_helper::get_question_structure($quiz->instance, $quiz->context);
        $question = reset($structure);

        // Fetch the question and its additional data from the DB. We just check the question has been
        // restored and the global vars are now empty. That should be enough to know that restoring of the
        // corrupted data worked.
        $questionrecord = $DB->get_record('question', ['id' => $question->questionid], '*', MUST_EXIST);
        $qtype = new qtype_formulas();
        $qtype->get_question_options($questionrecord);
        self::assertEquals('test-methodsinparts', $questionrecord->name);
        self::assertEquals('', $questionrecord->options->varsglobal);

        // With MDL-85721, the error reporting has changed for Moodle 4.5 and later. Instead of a notification,
        // the "Failed to load question options" error is now output via debugging as well.
        // is now output via debugging.
        if ($CFG->branch < 405) {
            self::assertDebuggingCalled("Formulas question ID {$questionrecord->id} was missing an options record. Using default.");

            // Notification we expect due to missing options.
            $this->expectOutputString('!! Failed to load question options from the table qtype_formulas_options' .
                                    ' for questionid ' . $questionrecord->id . ' !!' . "\n");
        } else {
            self::assertdebuggingcalledcount(2, [
                "Formulas question ID {$questionrecord->id} was missing an options record. Using default.",
                "Failed to load question options from the table qtype_formulas_options for questionid {$questionrecord->id}",
            ]);
        }
    }

    /**
     * Restore a quiz with duplicate questions (same stamp and questions) into the same course.
     * This is a contrived case, but this test serves as a control for the other tests in this class, proving
     * that the hashing process will match an identical question.
     *
     * This test is based on the one written by Mark Johnson while fixing MDL-83541.
     *
     * @param string $questionname name of the test question (e. g. testsinglenum)
     * @dataProvider provide_question_names
     */
    public function test_restore_quiz_with_duplicate_questions(string $questionname): void {
        global $CFG, $DB, $USER;
        $this->resetAfterTest();
        $this->setAdminUser();

        // The changes introduced while fixing MDL-83541 are only present in Moodle 4.4 and newer. It
        // does not make sense to perform this test with older versions.
        if ($CFG->branch < 404) {
            $this->markTestSkipped(
                'Not testing detection of duplicates while restoring in Moodle versions prior to 4.4.',
            );
        }

        // Create a course and a user with editing teacher capabilities.
        $generator = $this->getDataGenerator();
        $course1 = $generator->create_course();
        $teacher = $USER;
        $generator->enrol_user($teacher->id, $course1->id, 'editingteacher');
        $coursecontext = \context_course::instance($course1->id);
        /** @var \core_question_generator $questiongenerator */
        $questiongenerator = $this->getDataGenerator()->get_plugin_generator('core_question');

        // Create a question category.
        $cat = $questiongenerator->create_question_category(['contextid' => $coursecontext->id]);

        // Create a quiz with 2 identical but separate questions.
        $quiz1 = $this->create_test_quiz($course1);
        $question1 = $questiongenerator->create_question('formulas', $questionname, ['category' => $cat->id]);
        quiz_add_quiz_question($question1->id, $quiz1, 0);
        $question2 = $questiongenerator->create_question('formulas', $questionname, ['category' => $cat->id]);
        quiz_add_quiz_question($question2->id, $quiz1, 0);

        // Update question2 to have the same times and stamp as question1.
        $DB->update_record('question', [
            'id' => $question2->id,
            'stamp' => $question1->stamp,
            'timecreated' => $question1->timecreated,
            'timemodified' => $question1->timemodified,
        ]);

        // Backup quiz.
        $bc = new backup_controller(backup::TYPE_1ACTIVITY, $quiz1->cmid, backup::FORMAT_MOODLE,
            backup::INTERACTIVE_NO, backup::MODE_IMPORT, $teacher->id);
        $backupid = $bc->get_backupid();
        $bc->execute_plan();
        $bc->destroy();

        // Restore the backup into the same course.
        $rc = new restore_controller($backupid, $course1->id, backup::INTERACTIVE_NO, backup::MODE_IMPORT,
            $teacher->id, backup::TARGET_CURRENT_ADDING);
        $rc->execute_precheck();
        $rc->execute_plan();
        $rc->destroy();

        // Expect that the restored quiz will have the second question in both its slots
        // by virtue of identical stamp, version, and hash of question answer texts.
        $modules = get_fast_modinfo($course1->id)->get_instances_of('quiz');
        $this->assertCount(2, $modules);
        $quiz2 = end($modules);
        $quiz2structure = \mod_quiz\question\bank\qbank_helper::get_question_structure($quiz2->instance, $quiz2->context);
        $this->assertEquals($quiz2structure[1]->questionid, $quiz2structure[2]->questionid);
    }

    /**
     * Restore a quiz with questions that have the same stamp but different text.
     *
     * This test is based on the one written by Mark Johnson while fixing MDL-83541.
     *
     * @param string $questionname name of the test question (e. g. testsinglenum)
     * @dataProvider provide_question_names
     */
    public function test_restore_quiz_with_edited_questions(string $questionname): void {
        global $CFG, $DB, $USER;
        $this->resetAfterTest();
        $this->setAdminUser();

        // The changes introduced while fixing MDL-83541 are only present in Moodle 4.4 and newer. It
        // does not make sense to perform this test with older versions.
        if ($CFG->branch < 404) {
            $this->markTestSkipped(
                'Not testing detection of duplicates while restoring in Moodle versions prior to 4.4.',
            );
        }

        // Create a course and a user with editing teacher capabilities.
        $generator = $this->getDataGenerator();
        $course1 = $generator->create_course();
        $teacher = $USER;
        $generator->enrol_user($teacher->id, $course1->id, 'editingteacher');
        $coursecontext = \context_course::instance($course1->id);
        /** @var \core_question_generator $questiongenerator */
        $questiongenerator = $this->getDataGenerator()->get_plugin_generator('core_question');

        // Create a question category.
        $cat = $questiongenerator->create_question_category(['contextid' => $coursecontext->id]);

        // Create a quiz with 2 identical but separate questions.
        $quiz1 = $this->create_test_quiz($course1);
        $question1 = $questiongenerator->create_question('formulas', $questionname, ['category' => $cat->id]);
        quiz_add_quiz_question($question1->id, $quiz1);
        $question2 = $questiongenerator->create_question('formulas', $questionname, ['category' => $cat->id]);
        // Edit question 2 to have the same stamp and times as question1, but different text.
        $DB->update_record('question', [
            'id' => $question2->id,
            'questiontext' => 'edited',
            'stamp' => $question1->stamp,
            'timecreated' => $question1->timecreated,
            'timemodified' => $question1->timemodified,
        ]);
        quiz_add_quiz_question($question2->id, $quiz1);

        // Backup quiz.
        $bc = new backup_controller(backup::TYPE_1ACTIVITY, $quiz1->cmid, backup::FORMAT_MOODLE,
            backup::INTERACTIVE_NO, backup::MODE_IMPORT, $teacher->id);
        $backupid = $bc->get_backupid();
        $bc->execute_plan();
        $bc->destroy();

        // Restore the backup into the same course.
        $rc = new restore_controller($backupid, $course1->id, backup::INTERACTIVE_NO, backup::MODE_IMPORT,
            $teacher->id, backup::TARGET_CURRENT_ADDING);
        $rc->execute_precheck();
        $rc->execute_plan();
        $rc->destroy();

        // The quiz should contain both questions, as they have different text.
        $modules = get_fast_modinfo($course1->id)->get_instances_of('quiz');
        $this->assertCount(2, $modules);
        $quiz2 = end($modules);
        $quiz2structure = \mod_quiz\question\bank\qbank_helper::get_question_structure($quiz2->instance, $quiz2->context);
        $this->assertEquals($quiz2structure[1]->questionid, $question1->id);
        $this->assertEquals($quiz2structure[2]->questionid, $question2->id);
    }

    /**
     * Restore a course to another course having questions with the same stamp in a shared question bank context category.
     *
     * This test is based on the one written by Mark Johnson while fixing MDL-83541.
     *
     * @param string $questionname name of the test question (e. g. testsinglenum)
     * @dataProvider provide_question_names
     */
    public function test_restore_course_with_same_stamp_questions(string $questionname): void {
        global $CFG, $DB, $USER;
        $this->resetAfterTest();
        $this->setAdminUser();

        // The changes introduced while fixing MDL-83541 are only present in Moodle 4.4 and newer. It
        // does not make sense to perform this test with older versions.
        if ($CFG->branch < 404) {
            $this->markTestSkipped(
                'Not testing detection of duplicates while restoring in Moodle versions prior to 4.4.',
            );
        }

        // Create two courses and a user with editing teacher capabilities.
        $generator = $this->getDataGenerator();
        $course1 = $generator->create_course();
        $course2 = $generator->create_course();
        $teacher = $USER;
        $generator->enrol_user($teacher->id, $course1->id, 'editingteacher');
        $generator->enrol_user($teacher->id, $course2->id, 'editingteacher');
        /** @var \core_question_generator $questiongenerator */
        $questiongenerator = $this->getDataGenerator()->get_plugin_generator('core_question');

        // Create a question category.
        try {
            $qbank = $generator->get_plugin_generator('mod_qbank')->create_instance(['course' => $course2->id]);
            $context = \context_module::instance($qbank->cmid);
        } catch (Exception $e) {
            $context = \context_system::instance();
        }
        $cat = $questiongenerator->create_question_category(['contextid' => $context->id]);

        // Create quiz with question.
        $quiz1 = $this->create_test_quiz($course1);
        $question1 = $questiongenerator->create_question('formulas', $questionname, ['category' => $cat->id]);
        quiz_add_quiz_question($question1->id, $quiz1, 0);

        $quiz2 = $this->create_test_quiz($course1);
        $question2 = $questiongenerator->create_question('formulas', $questionname, ['category' => $cat->id]);
        quiz_add_quiz_question($question2->id, $quiz2, 0);

        // Update question2 to have the same stamp as question1.
        $DB->set_field('question', 'stamp', $question1->stamp, ['id' => $question2->id]);

        // Change the answers of the question2 to be different to question1.
        $question2data = \question_bank::load_question_data($question2->id);
        foreach ($question2data->options->answers as $answer) {
            $newanswer = '999';
            if ($question2data->name === 'test-algebraic') {
                $newanswer = '"a*x^3"';
            }
            $answer->answer = $newanswer;
            $DB->update_record('qtype_formulas_answers', $answer);
        }

        $course1q1structure = \mod_quiz\question\bank\qbank_helper::get_question_structure(
            $quiz1->id, \context_module::instance($quiz1->cmid)
        );
        $this->assertEquals($question1->id, $course1q1structure[1]->questionid);
        $course1q2structure = \mod_quiz\question\bank\qbank_helper::get_question_structure(
            $quiz2->id, \context_module::instance($quiz2->cmid)
        );
        $this->assertEquals($question2->id, $course1q2structure[1]->questionid);

        // Backup course1.
        $bc = new backup_controller(backup::TYPE_1COURSE, $course1->id, backup::FORMAT_MOODLE,
            backup::INTERACTIVE_NO, backup::MODE_IMPORT, $teacher->id);
        $backupid = $bc->get_backupid();
        $bc->execute_plan();
        $bc->destroy();

        // Restore the backup, adding to course2.
        $rc = new restore_controller($backupid, $course2->id, backup::INTERACTIVE_NO, backup::MODE_IMPORT,
            $teacher->id, backup::TARGET_CURRENT_ADDING);
        $rc->execute_precheck();
        $rc->execute_plan();
        $rc->destroy();

        // Verify that the newly-restored course's quizzes use the same questions as their counterparts of course1.
        $modules = get_fast_modinfo($course2->id)->get_instances_of('quiz');
        $course1q1structure = \mod_quiz\question\bank\qbank_helper::get_question_structure(
                $quiz1->id, \context_module::instance($quiz1->cmid));
        $course2quiz1 = array_shift($modules);
        $course2q1structure = \mod_quiz\question\bank\qbank_helper::get_question_structure(
                $course2quiz1->instance, $course2quiz1->context);
        $this->assertEquals($question1->id, $course1q1structure[1]->questionid);
        $this->assertEquals($question1->id, $course2q1structure[1]->questionid);

        $course1q2structure = \mod_quiz\question\bank\qbank_helper::get_question_structure(
                $quiz2->id, \context_module::instance($quiz2->cmid));
        $course2quiz2 = array_shift($modules);
        $course2q2structure = \mod_quiz\question\bank\qbank_helper::get_question_structure(
                $course2quiz2->instance, $course2quiz2->context);
        $this->assertEquals($question2->id, $course1q2structure[1]->questionid);
        $this->assertEquals($question2->id, $course2q2structure[1]->questionid);
    }

    /**
     * Restore a quiz with questions of same stamp into the same course, but different hints.
     *
     * This test is based on the one written by Mark Johnson while fixing MDL-83541.
     *
     * @param string $questionname name of the test question (e. g. testsinglenum)
     * @dataProvider provide_question_names
     */
    public function test_restore_quiz_with_same_stamp_questions_edited_hints(string $questionname): void {
        global $CFG, $DB, $USER;
        $this->resetAfterTest();
        $this->setAdminUser();

        // The changes introduced while fixing MDL-83541 are only present in Moodle 4.4 and newer. It
        // does not make sense to perform this test with older versions.
        if ($CFG->branch < 404) {
            $this->markTestSkipped(
                'Not testing detection of duplicates while restoring in Moodle versions prior to 4.4.',
            );
        }

        // Create a course and a user with editing teacher capabilities.
        $generator = $this->getDataGenerator();
        $course1 = $generator->create_course();
        $teacher = $USER;
        $generator->enrol_user($teacher->id, $course1->id, 'editingteacher');
        $coursecontext = \context_course::instance($course1->id);
        /** @var \core_question_generator $questiongenerator */
        $questiongenerator = $this->getDataGenerator()->get_plugin_generator('core_question');

        // Create a question category.
        $cat = $questiongenerator->create_question_category(['contextid' => $coursecontext->id]);

        // Create 2 questions.
        $quiz1 = $this->create_test_quiz($course1);
        $question1 = $questiongenerator->create_question('formulas', $questionname, ['category' => $cat->id]);
        quiz_add_quiz_question($question1->id, $quiz1, 0);

        $question2 = $questiongenerator->create_question('formulas', $questionname, ['category' => $cat->id]);
        quiz_add_quiz_question($question2->id, $quiz1, 0);

        // Update question2 to have the same stamp as question1.
        $DB->set_field('question', 'stamp', $question1->stamp, ['id' => $question2->id]);

        // Change the hints of the question2 to be different to question1.
        $hints = $DB->get_records('question_hints', ['questionid' => $question2->id]);
        if (empty($hints)) {
            $this->markTestSkipped(
                "Cannot test edited hints for qtype_formulas as test question {$questionname} does not use hints.",
            );
        }
        foreach ($hints as $hint) {
            $DB->set_field('question_hints', 'hint', "{$hint->hint} edited", ['id' => $hint->id]);
        }

        // Backup quiz1.
        $bc = new backup_controller(backup::TYPE_1ACTIVITY, $quiz1->cmid, backup::FORMAT_MOODLE,
            backup::INTERACTIVE_NO, backup::MODE_IMPORT, $teacher->id);
        $backupid = $bc->get_backupid();
        $bc->execute_plan();
        $bc->destroy();

        // Restore the backup into the same course.
        $rc = new restore_controller($backupid, $course1->id, backup::INTERACTIVE_NO, backup::MODE_IMPORT,
            $teacher->id, backup::TARGET_CURRENT_ADDING);
        $rc->execute_precheck();
        $rc->execute_plan();
        $rc->destroy();

        // Verify that the newly-restored quiz uses the same question as quiz2.
        $modules = get_fast_modinfo($course1->id)->get_instances_of('quiz');
        $this->assertCount(2, $modules);
        $quiz1structure = \mod_quiz\question\bank\qbank_helper::get_question_structure(
            $quiz1->id,
            \context_module::instance($quiz1->cmid),
        );
        $quiz2 = end($modules);
        $quiz2structure = \mod_quiz\question\bank\qbank_helper::get_question_structure($quiz2->instance, $quiz2->context);
        $this->assertEquals($quiz1structure[1]->questionid, $quiz2structure[1]->questionid);
        $this->assertEquals($quiz1structure[2]->questionid, $quiz2structure[2]->questionid);
    }

    /**
     * Data provider.
     *
     * @return array
     */
    public static function provide_edited_option_fields(): array {
        return [
            ['varsrandom', 'a={1,2,3};'],
            ['varsglobal', 'b=1;'],
            ['answernumbering', 'ABC'],
            ['shownumcorrect', '0'],
            ['correctfeedback', 'edited'],
            ['partiallycorrectfeedback', 'edited'],
            ['incorrectfeedback', 'edited'],

            ['answermark', '5'],
            ['answertype', '100'],
            ['vars1', 'c=5;'],
            ['vars2', 'd=9;'],
            ['correctness', '_err < 0.01'],
            ['answer', '17'],
            ['answernotunique', '0'],
            ['unitpenalty', '0.5'],
            ['postunit', 'm'],
            ['ruleid', '2'],
            ['otherrule', '60 s = 1 min'],
            ['subqtext', 'edited'],
            ['feedback', 'edited'],
            ['partcorrectfb', 'edited'],
            ['partpartiallycorrectfb', 'edited'],
            ['partincorrectfb', 'edited'],
        ];
    }

    /**
     * Restore a quiz with questions of same stamp into the same course, but different qtype-specific options.
     *
     * @param string $field The answer field to edit
     * @param string $value The value to set
     * @dataProvider provide_edited_option_fields
     */
    public function test_restore_quiz_with_same_stamp_questions_edited_options(string $field, string $value): void {
        global $CFG, $DB, $USER;
        $this->resetAfterTest();
        $this->setAdminUser();

        // The changes introduced while fixing MDL-83541 are only present in Moodle 4.4 and newer. It
        // does not make sense to perform this test with older versions.
        if ($CFG->branch < 404) {
            $this->markTestSkipped(
                'Not testing detection of duplicates while restoring in Moodle versions prior to 4.4.',
            );
        }

        // Create a course and a user with editing teacher capabilities.
        $generator = $this->getDataGenerator();
        $course1 = $generator->create_course();
        $teacher = $USER;
        $generator->enrol_user($teacher->id, $course1->id, 'editingteacher');
        $coursecontext = \context_course::instance($course1->id);
        /** @var \core_question_generator $questiongenerator */
        $questiongenerator = $this->getDataGenerator()->get_plugin_generator('core_question');

        // Create a question category.
        $cat = $questiongenerator->create_question_category(['contextid' => $coursecontext->id]);

        // A quiz with 2 questions.
        $quiz1 = $this->create_test_quiz($course1);
        $question1 = $questiongenerator->create_question('formulas', 'testsinglenum', ['category' => $cat->id]);
        quiz_add_quiz_question($question1->id, $quiz1, 0);

        $question2 = $questiongenerator->create_question('formulas', 'testsinglenum', ['category' => $cat->id]);
        quiz_add_quiz_question($question2->id, $quiz1, 0);

        // Update question2 to have the same stamp as question1.
        $DB->set_field('question', 'stamp', $question1->stamp, ['id' => $question2->id]);

        // Change the options of question2 to be different to question1.
        $optionfields = [
            'varsrandom', 'varsglobal', 'answernumbering', 'shownumcorrect',
            'correctfeedback', 'partiallycorrectfeedback', 'incorrectfeedback',
        ];
        if (in_array($field, $optionfields)) {
            $DB->set_field('qtype_formulas_options', $field, $value, ['questionid' => $question2->id]);
        } else {
            $DB->set_field('qtype_formulas_answers', $field, $value, ['questionid' => $question2->id]);
        }

        // Backup quiz.
        $bc = new backup_controller(backup::TYPE_1ACTIVITY, $quiz1->cmid, backup::FORMAT_MOODLE,
            backup::INTERACTIVE_NO, backup::MODE_IMPORT, $teacher->id);
        $backupid = $bc->get_backupid();
        $bc->execute_plan();
        $bc->destroy();

        // Restore the backup into the same course.
        $rc = new restore_controller($backupid, $course1->id, backup::INTERACTIVE_NO, backup::MODE_IMPORT,
            $teacher->id, backup::TARGET_CURRENT_ADDING);
        $rc->execute_precheck();
        $rc->execute_plan();
        $rc->destroy();

        // Verify that the newly-restored quiz questions match their quiz1 counterparts.
        $modules = get_fast_modinfo($course1->id)->get_instances_of('quiz');
        $this->assertCount(2, $modules);
        $quiz1structure = \mod_quiz\question\bank\qbank_helper::get_question_structure(
            $quiz1->id,
            \context_module::instance($quiz1->cmid),
        );
        $quiz2 = end($modules);
        $quiz2structure = \mod_quiz\question\bank\qbank_helper::get_question_structure($quiz2->instance, $quiz2->context);
        $this->assertEquals($quiz1structure[1]->questionid, $quiz2structure[1]->questionid);
        $this->assertEquals($quiz1structure[2]->questionid, $quiz2structure[2]->questionid);
    }
}
