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
 * Unit tests for (some of) question/type/formulas/edit_formulas_form.php.
 *
 * @package    qtype_formulas
 * @copyright  2025 Philipp Imhof
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace qtype_formulas;

use qtype_formulas;
use qtype_formulas_question;
use qtype_formulas_test_helper;
use test_question_maker;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/question/engine/tests/helpers.php');
require_once($CFG->dirroot . '/question/type/formulas/questiontype.php');
require_once($CFG->dirroot . '/question/type/formulas/tests/helper.php');
require_once($CFG->dirroot . '/question/type/formulas/edit_formulas_form.php');

/**
 * Unit tests for question/type/formulas/edit_formulas_form.php.
 *
 * @copyright  2025 Philipp Imhof
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @covers     \qtype_formulas_edit_form
 */
final class edit_formulas_form_test extends \advanced_testcase {
    /** @var formulas instance of the question type class to test. */
    protected $qtype;

    /**
     * Create a question object of a certain type, as defined in the helper.php file.
     *
     * @param string|null $which the test question name
     * @return qtype_formulas_question
     */
    protected function get_test_formulas_question($which = null) {
        return test_question_maker::make_question('formulas', $which);
    }

    protected function setUp(): void {
        $this->qtype = new qtype_formulas();

        parent::setUp();
    }

    protected function tearDown(): void {
        $this->qtype = null;

        parent::tearDown();
    }

    public function test_data_preprocessing(): void {
        global $DB, $USER, $PAGE;
        $this->resetAfterTest();
        $this->setAdminUser();

        // Create a course and a user with editing teacher capabilities.
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $teacher = $USER;
        $generator->enrol_user($teacher->id, $course->id, 'editingteacher');
        $coursecontext = \context_course::instance($course->id);
        $contexts = new \core_question\local\bank\question_edit_contexts($coursecontext);
        /** @var \core_question_generator $questiongenerator */
        $questiongenerator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $category = $questiongenerator->create_question_category(['contextid' => $coursecontext->id]);

        $question = $questiongenerator->create_question('formulas', 'testmethodsinparts', ['category' => $category->id]);
        $questionrecord = $DB->get_record('question', ['id' => $question->id], '*', MUST_EXIST);
        $this->qtype->get_question_options($questionrecord);

        $questionrecord->formoptions = new \stdClass();
        $questionrecord->formoptions->canedit = true;
        $questionrecord->formoptions->canmove = true;
        $questionrecord->formoptions->cansaveasnew = true;
        $questionrecord->formoptions->repeatelements = true;
        $questionrecord->beingcopied = false;

        $PAGE->set_url('/question/bank/editquestion/question.php');
        $form = $this->qtype->create_editing_form('question.php', $questionrecord, $category, $contexts, true);

        // Use reflection to access protected method.
        $method = new \ReflectionMethod($form, 'data_preprocessing');
        $method->setAccessible(true);
        $processedquestion = $method->invoke($form, $questionrecord);

        $helper = new qtype_formulas_test_helper();
        $formdata = $helper->get_formulas_question_form_data_testmethodsinparts();

        // First, we want to make sure that the ruleid and unitpenalty values are moved from the parts (where they)
        // are stored in the DB, to the global form fields.
        $globalfields = ['globalruleid', 'globalunitpenalty'];
        foreach ($globalfields as $field) {
            // For backwards compatibility with PHPUnit 9.5, used in Moodle 4.1 and 4.2.
            if (method_exists(__CLASS__, 'assertObjectHasProperty')) {
                self::assertObjectHasProperty($field, $processedquestion);
            } else {
                self::assertObjectHasAttribute($field, $processedquestion);
            }
            self::assertEquals($formdata->$field, $processedquestion->$field);
        }

        // Now, check the per-part fields, with exception of the unitpenalty and ruleid mentioned above.
        $numparts = count($questionrecord->options->answers);
        foreach ($this->qtype::PART_BASIC_FIELDS as $field) {
            if ($field === 'unitpenalty' || $field === 'ruleid') {
                continue;
            }
            for ($i = 0; $i < $numparts; $i++) {
                $formfieldname = $field . "[{$i}]";
                self::assertEquals($formdata->{$field}[$i], $processedquestion->$formfieldname);
            }
        }

        // Finally, check the textual fields, i. e. subqtext, general feedback and combined feedback.
        // They all have a text, a format and an item ID. For the item ID, we just check it is there.
        $textfields = ['subqtext', 'feedback', 'partcorrectfb', 'partpartiallycorrectfb', 'partincorrectfb'];
        foreach ($textfields as $field) {
            for ($i = 0; $i < $numparts; $i++) {
                $formfieldname = $field . "[{$i}]";
                self::assertEquals($formdata->{$field}[$i]['text'], $processedquestion->{$formfieldname}['text']);
                self::assertEquals($formdata->{$field}[$i]['format'], $processedquestion->{$formfieldname}['format']);
                self::assertArrayHasKey('itemid', $processedquestion->$formfieldname);
            }
        }
    }
}
