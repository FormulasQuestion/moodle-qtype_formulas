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
 * Unit tests for (some of) question/type/formulas/questiontype.php.
 *
 * @package    qtype_formulas
 * @copyright  2013 Jean-Michel Vedrine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace qtype_formulas;

use context_system;
use stdClass;
use qtype_formulas_edit_form;
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
 * Unit tests for question/type/formulas/questiontype.php.
 *
 * @copyright  2013 Jean-Michel Vedrine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @group qtype_formulas
 */
class questiontype_test extends \advanced_testcase {

    /** @var formulas instance of the question type class to test. */
    protected $qtype;

    /**
     * @return qtype_formulas_question the requested question object.
     */
    protected function get_test_formulas_question($which = null) {
        return test_question_maker::make_question('formulas', $which);
    }

    protected function setUp():void {
        $this->qtype = new qtype_formulas();
    }

    protected function tearDown():void {
        $this->qtype = null;
    }

    public static function create_draft_file_for_user($user): \stored_file {
        global $CFG;

        $draftitemid = file_get_unused_draft_itemid();
        $usercontext = \context_user::instance($user->id);
        file_prepare_draft_area($draftitemid, $usercontext->id, null, null, null);

        $fs = get_file_storage();
        $fileinfo = array(
            'contextid' => $usercontext->id,
            'component' => 'user',
            'filearea' => 'draft',
            'itemid' => $draftitemid,
            'userid' => $user->id,
            'filepath' => '/',
            'filename' => 'icon.gif'
        );
        $file = $fs->create_file_from_pathname($fileinfo, $CFG->dirroot . '/question/type/formulas/tests/fixtures/icon.gif');

        return $file;
    }

    public function test_name(): void {
        self::assertEquals($this->qtype->name(), 'formulas');
    }

    public function test_can_analyse_responses(): void {
        self::assertTrue($this->qtype->can_analyse_responses());
    }

    public function test_reorder_parts_according_to_questiontext(): void {
        $questiontext = 'Main {#2} and {#1}.';

        $part1 = (object)['placeholder' => '#1'];
        $part2 = (object)['placeholder' => '#2'];
        $parts = [$part1, $part2];

        $orderedparts = $this->qtype->reorder_parts($questiontext, $parts);

        self::assertEquals([$part2, $part1], $orderedparts);
    }

    public function test_reorder_parts_no_placeholder_comes_last(): void {
        $questiontext = 'Main {#third} then {#fourth} and {#first}.';

        $part1 = (object)['placeholder' => '#first'];
        $part2 = (object)['placeholder' => ''];
        $part3 = (object)['placeholder' => '#third'];
        $part4 = (object)['placeholder' => '#fourth'];
        $parts = [$part1, $part2, $part3, $part4];

        $orderedparts = $this->qtype->reorder_parts($questiontext, $parts);

        self::assertEquals([$part3, $part4, $part1, $part2], $orderedparts);
    }

    public function test_reorder_multiple_parts_without_placeholder(): void {
        $questiontext = 'Main text without placeholders.';

        $part1 = (object)['placeholder' => ''];
        $part2 = (object)['placeholder' => ''];
        $part3 = (object)['placeholder' => ''];
        $part4 = (object)['placeholder' => ''];
        $parts = [$part1, $part2, $part3, $part4];

        $orderedparts = $this->qtype->reorder_parts($questiontext, $parts);

        self::assertEquals([$part1, $part2, $part3, $part4], $orderedparts);
    }

    public function test_check_placeholder0(): void {
        $questiontext = 'Main text {#4} with dulicated placeholders {#4}.';
        $ans0 = new stdClass();
        $ans0->placeholder = '#Thisisaverylongplaceholderandplaceholderarelimitedtofortycharacters';
        $ans1 = new stdClass();
        $ans1->placeholder = '#Spaces are forbidden in placeholders';
        $ans2 = new stdClass();
        $ans2->placeholder = '#3';
        $ans3 = new stdClass();
        $ans3->placeholder = '#3';
        $ans4 = new stdClass();
        $ans4->placeholder = '#4';
        $answers = array(0 => $ans0, 1 => $ans1, 2 => $ans2, 3 => $ans3, 4 => $ans4);
        $expected = array(
                'placeholder[0]' => 'The placeholder\'s length is limited to 40 characters.'
                        .' This placeholder is missing from the main question text.',
                'placeholder[1]' => 'Wrong placeholder\'s format or forbidden characters.'
                        .' This placeholder is missing from the main question text.',
                'placeholder[2]' => 'This placeholder is missing from the main question text.',
                'placeholder[3]' => 'This placeholder has already been defined in some other part.'
                        .' This placeholder is missing from the main question text.',
                'placeholder[4]' => 'Duplicated placeholder in the main question text.'
                );
        self::assertEquals($expected, $this->qtype->check_placeholders($questiontext, $answers));
    }

    public function test_split_questiontext0(): void {
        $q = $this->get_test_formulas_question('testthreeparts');
        $expected = array(0 => '<p>Multiple parts : --',
                1 => '--',
                2 => '--',
                3 => '</p>');
        self::assertEquals($expected, $this->qtype->split_questiontext($q->questiontext, $q->parts));
    }

    public function test_split_questiontext1(): void {
        $q = $this->get_test_formulas_question('test4');
        $expected = array(0 => '<p>This question shows different display methods of the answer and unit box.</p>',
                1 => '',
                2 => '',
                3 => '',
                4 => '');
        self::assertEquals($expected, $this->qtype->split_questiontext($q->questiontext, $q->parts));
    }

    public function provide_single_part_data_for_form_validation(): array {
        return [
            [[], [
                'answermark' => [0 => 1]]
            ],
        ];
    }

    public function provide_multipart_data_for_form_validation(): array {
        return [
            [['answermark[0]' => 'The answer mark must take a value larger than 0.'], [
                'answermark' => [0 => 0]]
            ],
        ];
    }

    /**
     * @dataProvider provide_multipart_data_for_form_validation
     */
    public function test_form_validation_multipart($expected, $input) {
        // TODO test: two parts, totally empty except for answer in one part

        self::resetAfterTest();
        self::setAdminUser();

        $questiondata = test_question_maker::get_question_data('formulas', 'testmethodsinparts');

        $generator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $category = $generator->create_question_category([]);
        $form = qtype_formulas_test_helper::get_question_editing_form($category, $questiondata);

        $formdata = test_question_maker::get_question_form_data('formulas', 'testmethodsinparts');
        $formdata->id = 0;
        $formdata->category = "{$category->id},{$category->contextid}";
        $formdata = array_replace_recursive((array)$formdata, $input);

        $errors = $form->validation($formdata, []);
        self::assertEquals($expected, $errors);

        qtype_formulas_edit_form::mock_submit($formdata);
        $form = qtype_formulas_test_helper::get_question_editing_form($category, $questiondata);
        self::assertEquals(count($expected) === 0, $form->is_validated());
    }

    /**
     * @dataProvider provide_single_part_data_for_form_validation
     */
    public function test_form_validation_single_part($expected, $input) {
        self::resetAfterTest();
        self::setAdminUser();

        $questiondata = test_question_maker::get_question_data('formulas', 'testsinglenum');

        $generator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $category = $generator->create_question_category([]);
        $form = qtype_formulas_test_helper::get_question_editing_form($category, $questiondata);

        $formdata = test_question_maker::get_question_form_data('formulas', 'testsinglenum');
        $formdata->id = 0;
        $formdata->category = "{$category->id},{$category->contextid}";
        $formdata = array_replace_recursive((array)$formdata, $input);

        $errors = $form->validation($formdata, []);
        self::assertEquals($expected, $errors);

        qtype_formulas_edit_form::mock_submit($formdata);
        $form = qtype_formulas_test_helper::get_question_editing_form($category, $questiondata);
        self::assertEquals(count($expected) === 0, $form->is_validated());
    }

    public function test_foo() {
        // FIXME: remove this once all others are done
        // test: two parts, totally empty except for answer in one part

        self::resetAfterTest();
        self::setAdminUser();

        $questiondata = test_question_maker::get_question_data('formulas', 'testmethodsinparts');

        $generator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $category = $generator->create_question_category([]);
        $form = qtype_formulas_test_helper::get_question_editing_form($category, $questiondata);

        $formdata = test_question_maker::get_question_form_data('formulas', 'testmethodsinparts');
        $formdata->id = 0;
        $formdata->category = "{$category->id},{$category->contextid}";
        //$formdata = array_replace_recursive((array)$formdata, []);
        $errors = $form->validation((array)$formdata, []);

        self::assertEquals([], $errors);

        /*$formdata = test_question_maker::get_question_form_data('formulas', 'testmethodsinparts');
        $formdata->id = 0;
        $formdata->category = "{$category->id},{$category->contextid}";
        $formdata->answermark[0] = 0;
        $errors = $form->validation((array)$formdata, []);

        $formdata = test_question_maker::get_question_form_data('formulas', 'testmethodsinparts');
        $formdata->id = 0;
        $formdata->category = "{$category->id},{$category->contextid}";
        $formdata->answermark[1] = 2;
        $errors = $form->validation((array)$formdata, []);*/

        $formdata = test_question_maker::get_question_form_data('formulas', 'testmethodsinparts');
        $formdata->id = 0;
        $formdata->category = "{$category->id},{$category->contextid}";
        $formdata->answermark[0] = 0;
        $formdata->answer[0] = '1';
        $formdata->feedback[0] = ['text' => '', 'format' => FORMAT_HTML];
        $formdata->subqtext[0] = ['text' => '', 'format' => FORMAT_HTML];
        $formdata->answermark[1] = 0;
        $formdata->answer[1] = '';
        $formdata->feedback[1] = ['text' => '', 'format' => FORMAT_HTML];
        $formdata->subqtext[1] = ['text' => '', 'format' => FORMAT_HTML];
        $formdata->answermark[2] = 0;
        $formdata->answer[2] = '';
        $formdata->feedback[2] = ['text' => '', 'format' => FORMAT_HTML];
        $formdata->subqtext[2] = ['text' => '', 'format' => FORMAT_HTML];
        $formdata->answermark[3] = 0;
        $formdata->answer[3] = '';
        $formdata->feedback[3] = ['text' => '', 'format' => FORMAT_HTML];
        $formdata->subqtext[3] = ['text' => '', 'format' => FORMAT_HTML];
        $errors = $form->validation((array)$formdata, []);


        /*qtype_formulas_edit_form::mock_submit((array)$formdata);
        $form = qtype_formulas_test_helper::get_question_editing_form($category, $questiondata);
        $fromform = $form->get_data();*/

        return;

        //$form->mock_submit((array)$formdata);
        //qtype_formulas_edit_form::mock_submit((array)$formdata);
        self::assertTrue($form->is_validated());

        qtype_formulas_edit_form::mock_submit((array)$formdata);
        $test = $form->get_data();

        return;


        $form = qtype_formulas_test_helper::get_question_editing_form($cat, $questiondata);
        self::assertTrue($form->is_validated());


        //$test = qtype_formulas_edit_form::mock_submit((array)$formdata);


        return;
        self::assertTrue($form->is_validated());
        return;


        $syscontext = context_system::instance();
        var_dump($syscontext);
        return;

        $form = new qtype_formulas_edit_form('', new qtype_formulas_question(), $cat->id, context_system::instance());
        $form->mock_submit($formdata);
        print_r($form->get_data());
        return;
        $form = qtype_formulas_test_helper::get_question_editing_form($cat, $questiondata);
        self::assertTrue($form->is_validated());

        $fromform = $form->get_data();
    }

    public function test_fetch_part_ids_for_question(): void {
        // TODO: remove this once method is private; it is indirectly tested
        // with the tests to move/delete files
        $this->resetAfterTest();
        $this->setAdminUser();

        $generator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $category = $generator->create_question_category([]);

        $questiondata = test_question_maker::get_question_data('formulas', 'testsinglenum');
        $formdata = test_question_maker::get_question_form_data('formulas', 'testsinglenum');
        $formdata->category = "{$category->id},{$category->contextid}";
        $formdata->id = 0;
        $saved = $this->qtype->save_question($questiondata, $formdata);
        self::assertCount(1, $this->qtype->fetch_part_ids_for_question($saved->id));

        $questiondata = test_question_maker::get_question_data('formulas', 'testmethodsinparts');
        $formdata = test_question_maker::get_question_form_data('formulas', 'testmethodsinparts');
        $formdata->category = "{$category->id},{$category->contextid}";
        $formdata->id = 0;
        $saved = $this->qtype->save_question($questiondata, $formdata);
        self::assertCount(4, $this->qtype->fetch_part_ids_for_question($saved->id));
    }

    /**
     * Test to make sure that loading of question options works, including in an error case.
     */
    public function test_get_question_options(): void {
        global $DB;

        $this->resetAfterTest(true);
        $this->setAdminUser();

        // Create a complete, in DB question to use.
        $questiondata = test_question_maker::get_question_data('formulas', 'testmethodsinparts');
        $formdata = test_question_maker::get_question_form_data('formulas', 'testmethodsinparts');
        $generator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $cat = $generator->create_question_category(array());

        $formdata->category = "{$cat->id},{$cat->contextid}";
        $formdata->id = 0;
        qtype_formulas_edit_form::mock_submit((array)$formdata);
        $form = qtype_formulas_test_helper::get_question_editing_form($cat, $questiondata);
        self::assertTrue($form->is_validated());

        $fromform = $form->get_data();
        $returnedfromsave = $this->qtype->save_question($questiondata, $fromform);
        // Now get just the raw DB record.
        $question = $DB->get_record('question', ['id' => $returnedfromsave->id], '*', MUST_EXIST);
        // Load it.
        $this->qtype->get_question_options($question);
        self::assertDebuggingNotCalled();
        self::assertInstanceOf(stdClass::class, $question->options);

        $options = $question->options;
        self::assertEquals($question->id, $options->questionid);
        self::assertEquals(4, $options->numparts);
        self::assertCount(4, $options->answers);

        // Now we are going to delete the options record.
        $DB->delete_records('qtype_formulas_options', ['questionid' => $question->id]);

        // Notifications we expect due to missing options.
        $this->expectOutputString('!! Failed to load question options from the table qtype_formulas_options' .
                                  ' for questionid ' . $question->id . ' !!' . "\n" .
                                  '!! Failed to load question options from the table qtype_formulas_options for '.
                                  'questionid ' . $question->id . ' !!' . "\n");

        // Now see what happens.
        $question = $DB->get_record('question', ['id' => $returnedfromsave->id], '*', MUST_EXIST);
        $this->qtype->get_question_options($question);

        self::assertDebuggingCalled('Formulas question ID '.$question->id.' was missing an options record. Using default.');
        self::assertInstanceOf(stdClass::class, $question->options);
        $options = $question->options;
        self::assertEquals($question->id, $options->questionid);
        self::assertEquals(4, $options->numparts);
        self::assertCount(4, $options->answers);

        self::assertEquals(get_string('correctfeedbackdefault', 'question'), $options->correctfeedback);
        self::assertEquals(FORMAT_HTML, $options->correctfeedbackformat);

        // And finally we try again with no answer either.
        $DB->delete_records('qtype_formulas_answers', ['questionid' => $question->id]);

        $question = $DB->get_record('question', ['id' => $returnedfromsave->id], '*', MUST_EXIST);
        $this->qtype->get_question_options($question);

        self::assertDebuggingCalled('Formulas question ID '.$question->id.' was missing an options record. Using default.');
        self::assertInstanceOf(stdClass::class, $question->options);
        $options = $question->options;
        self::assertEquals($question->id, $options->questionid);
        self::assertEquals(0, $options->numparts);
        self::assertCount(0, $options->answers);
    }

    public function provide_fileareas_for_deletion_and_moving(): array {
        return [
            ['subqtext', 'answersubqtext'],
            ['feedback', 'answerfeedback'],
            ['partcorrectfb', 'partcorrectfb'],
            ['partpartiallycorrectfb', 'partpartiallycorrectfb'],
            ['partincorrectfb', 'partincorrectfb'],
        ];
    }

    /**
     * @dataProvider provide_fileareas_for_deletion_and_moving
     */
    public function test_move_question_with_file_in_part($fieldname, $areaname): void {
        global $USER;

        // Login as admin user.
        $this->resetAfterTest(true);
        $this->setAdminUser();

        // Create two course categories.
        $coursecat1 = $this->getDataGenerator()->create_category();
        $coursecat2 = $this->getDataGenerator()->create_category();

        // Create a context and a question category in each course.
        $context1 = \context_coursecat::instance($coursecat1->id);
        $context2 = \context_coursecat::instance($coursecat2->id);
        $questiongenerator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $questioncat1 = $questiongenerator->create_question_category(['contextid' => $context1->id]);
        $questioncat2 = $questiongenerator->create_question_category(['contextid' => $context2->id]);

        // Prepare a draft file.
        $file = self::create_draft_file_for_user($USER);

        // Prepare the URL for the newly created draft file.
        $url = \moodle_url::make_draftfile_url(
                $file->get_itemid(),
                $file->get_filepath(),
                $file->get_filename(),
                false // Do not force download of the file.
        );

        // Create a basic question in the DB.
        $formdata = test_question_maker::get_question_form_data('formulas', 'testmethodsinparts');
        $formdata->category = "{$questioncat1->id},{$questioncat1->contextid}";
        $formdata->{$fieldname}[0] = [
            'text' => '<img src="' . $url . '">',
            'itemid' => $file->get_itemid(),
            'format' => FORMAT_HTML
        ];
        $q = $questiongenerator->create_question('formulas', 'testmethodsinparts', ['category' => $questioncat1->id]);
        $fs = get_file_storage();
        self::assertCount(2, $fs->get_area_files($file->get_contextid(), 'user', 'draft'));
        self::assertCount(0, $fs->get_area_files($context1->id, 'qtype_formulas', $areaname));

        // Store the modified question in the DB and verify the file has been moved to qtype_formulas' filearea 'answersubqtext'.
        \question_bank::get_qtype('formulas')->save_question($q, $formdata);
        self::assertCount(2, $fs->get_area_files($context1->id, 'qtype_formulas', $areaname));

        // Test moving the questions to another category.
        question_move_questions_to_category([$q->id], $questioncat2->id);
        self::assertCount(0, $fs->get_area_files($context1->id, 'qtype_formulas', $areaname));
        self::assertCount(2, $fs->get_area_files($context2->id, 'qtype_formulas', $areaname));

        // Remove the question.
        question_delete_question($q->id);
        self::assertCount(0, $fs->get_area_files($context2->id, 'qtype_formulas', $areaname));
    }

    public function provide_question_names(): array {
        return [
            ['testsinglenum'],
            ['testsinglenumunit'],
            ['testmethodsinparts'],
        ];
    }

    /**
     * @dataProvider provide_question_names
     */
    public function test_initialise_question_instance($questionname): void {
        $questiondata = test_question_maker::get_question_data('formulas', $questionname);

        $expected = \test_question_maker::make_question('formulas', $questionname);
        $expected->stamp = $questiondata->stamp;
        $expected->version = $questiondata->version;

        $q = $this->qtype->make_question($questiondata);

        $this->assertEquals($expected, $q);
    }

    public function test_save_question_removed_one_part(): void {
        global $USER, $DB, $CFG;

        // Login as admin user.
        $this->resetAfterTest(true);
        $this->setAdminUser();

        $context = context_system::instance();
        $questiongenerator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $questioncat = $questiongenerator->create_question_category(['contextid' => $context->id]);

        // Create a basic question with four parts in the DB.
        $q = $questiongenerator->create_question('formulas', 'testmethodsinparts', ['category' => $questioncat->id]);

        // Check we have all four parts in the DB.
        $fetchedquestion = $DB->get_record('question', ['id' => $q->id], '*', MUST_EXIST);
        $this->qtype->get_question_options($fetchedquestion);
        self::assertCount(4, $fetchedquestion->options->answers);

        // Prepare a draft file.
        $file = self::create_draft_file_for_user($USER);

        // Prepare the URL for the newly created draft file.
        $url = \moodle_url::make_draftfile_url(
                $file->get_itemid(),
                $file->get_filepath(),
                $file->get_filename(),
                false // Do not force download of the file.
        );

        // Prepare form data and add image to last part.
        $formdata = test_question_maker::get_question_form_data('formulas', 'testmethodsinparts');
        $formdata->category = "{$questioncat->id},{$questioncat->contextid}";
        $formdata->subqtext[3] = [
            'text' => '<img src="' . $url . '">',
            'itemid' => $file->get_itemid(),
            'format' => FORMAT_HTML
        ];

        // Save the modified question to the DB.
        $savedquestion = \question_bank::get_qtype('formulas')->save_question($q, $formdata);

        // Check we still have four parts in the DB and that the file has been stored.
        $this->qtype->get_question_options($savedquestion);
        self::assertCount(4, $savedquestion->options->answers);
        $partid = $savedquestion->options->answers[3]->id;
        $fs = get_file_storage();
        self::assertCount(2, $fs->get_area_files($context->id, 'qtype_formulas', 'answersubqtext', $partid));

        // Prepare form data and remove first part by deleting its answermark.
        $formdata = test_question_maker::get_question_form_data('formulas', 'testmethodsinparts');
        $formdata->category = "{$questioncat->id},{$questioncat->contextid}";
        array_shift($formdata->answermark);

        // Save the modified question to the DB.
        $modifiedquestion = \question_bank::get_qtype('formulas')->save_question($q, $formdata);

        // Check we now have only three parts in the DB and, for Moodle 3.11 and lower, that the file is gone.
        // For Moodle 4.0+, the file will remain in the DB, because all question versions are kept.
        $this->qtype->get_question_options($modifiedquestion);
        self::assertCount(3, $modifiedquestion->options->answers);
        if ($CFG->branch < 400) {
            self::assertCount(0, $fs->get_area_files($context->id, 'qtype_formulas', 'answersubqtext', $partid));
        }
    }

    public function provide_import_filenames(): array {
        global $CFG;

        return [
            [
                'The question is now set to have a unique answer.',
                $CFG->dirroot . '/question/type/formulas/tests/fixtures/qtype_sample_formulas_5.3.0.xml'
            ],
            [
                'For a minimal question, you must define a subquestion with (1) mark, (2) answer, (3) grading criteria',
                $CFG->dirroot . '/question/type/formulas/tests/fixtures/qtype_sample_formulas_5.2.0.xml'
            ],
        ];
    }
    /**
     * @dataProvider provide_import_filenames
     */
    public function test_import_from_xml($expected, $filename): void {
        global $CFG;

        // Login as admin user.
        $this->resetAfterTest(true);
        $this->setAdminUser();

        // Create a course and a question category.
        $course = $this->getDataGenerator()->create_course();
        $context = context_system::instance();
        $questiongenerator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $category = $questiongenerator->create_question_category(['contextid' => $context->id]);

        // Prepare the XML format class.
        require_once($CFG->dirroot . '/question/format/xml/format.php');
        $qformat = new \qformat_xml();
        $qformat->setCategory($category);
        if (class_exists('\core_question\local\bank\question_edit_contexts')) {
            $contexts = new \core_question\local\bank\question_edit_contexts($context);
        } else {
            $contexts = new \question_edit_contexts($context);
        }
        $qformat->setContexts($contexts);
        $qformat->setCourse($course);
        $qformat->setFilename($filename);
        $qformat->setMatchgrades(false);
        $qformat->setCatfromfile(false);
        $qformat->setContextfromfile(false);
        $qformat->setStoponerror(true);

        // Import our XML file.
        self::assertTrue($qformat->importpreprocess());
        self::assertTrue($qformat->importprocess());
        self::assertTrue($qformat->importpostprocess());

        // Importing generates output. Make sure the tests expects that.
        $this->expectOutputRegex('/\+\+ Importing 1 questions from file \+\+.*' . preg_quote($expected, '/') . '/s');
    }

    public function provide_question_names_for_export(): array {
        return [
            ['testsinglenum'],
            ['testsinglenumunit'],
            ['testmethodsinparts'],
        ];
    }

    /**
     * @dataProvider provide_question_names_for_export
     */
    public function test_export_and_reimport_xml($questionname): void {
        global $CFG, $DB;

        // Login as admin user.
        $this->resetAfterTest(true);
        $this->setAdminUser();

        // Create a course and a question category.
        $course = $this->getDataGenerator()->create_course();
        $context = context_system::instance();
        $questiongenerator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $category = $questiongenerator->create_question_category(['contextid' => $context->id]);

        // In Moodle 4.0+, the question MUST be stored in the DB to allow exporting it. For lower versions,
        // we just need the data.
        if ($CFG->branch >= 400) {
            $questiondata = $questiongenerator->create_question('formulas', 'testmethodsinparts', ['category' => $category->id]);
            $this->qtype->get_question_options($questiondata);
        } else {
            $questiondata = test_question_maker::get_question_data('formulas', $questionname);
        }

        // Prepare the XML format class.
        require_once($CFG->dirroot . '/question/format/xml/format.php');
        $qformat = new \qformat_xml();
        if (class_exists('\core_question\local\bank\question_edit_contexts')) {
            $contexts = new \core_question\local\bank\question_edit_contexts($context);
        } else {
            $contexts = new \question_edit_contexts($context);
        }
        $qformat->setContexts($contexts);
        $qformat->setCourse($course);
        $qformat->setCattofile(false);
        $qformat->setContexttofile(false);
        $qformat->setQuestions([$questiondata]);

        // Export the question and make sure it works. Store the XML output for later.
        self::assertTrue($qformat->exportpreprocess());
        $xmloutput = $qformat->exportprocess(false);
        $tempfile = tmpfile();
        fwrite($tempfile, $xmloutput);
        $xmlfilepath = stream_get_meta_data($tempfile)['uri'];

        // Remove the question and its parts.
        // Note: The method will do nothing is the question is already gone, so we do not have to
        // make separate branches for Moodle 4.0+ and for lower versions.
        question_delete_question($questiondata->id);

        // Reinitialize the XML format class.
        $qformat = new \qformat_xml();
        $context = context_system::instance();
        $questiongenerator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $category = $questiongenerator->create_question_category(['contextid' => $context->id]);
        $qformat->setCategory($category);
        $qformat->setContexts($contexts);
        $qformat->setCourse($course);
        $qformat->setFilename($xmlfilepath);
        $qformat->setMatchgrades(false);
        $qformat->setCatfromfile(false);
        $qformat->setContextfromfile(false);
        $qformat->setStoponerror(true);

        // Now, re-import the question from the generated XML.
        self::assertTrue($qformat->importpreprocess());
        self::assertTrue($qformat->importprocess());
        self::assertTrue($qformat->importpostprocess());

        // Importing will generate output. Make sure the test expects this.
        $message = preg_quote(strip_tags($questiondata->questiontext), '/');
        $this->expectOutputRegex('/\+\+ Importing 1 questions from file \+\+.*' . $message. '/s');

        // Load the question and its parts from the DB. We are supposed to be operating on a
        // clean DB, so we can filter for the qtype and still get only one match. This avoids
        // fiddling around with the currently unknown question ID.
        $importedquestion = $DB->get_record('question', ['qtype' => 'formulas'], '*', MUST_EXIST);
        $this->qtype->get_question_options($importedquestion);
        self::assertDebuggingNotCalled();

        // Verify that the basic fields for the question match. The created and modified time
        // might be off by a second or two, so it's better to check them separately and avoid random
        // failures of the test.
        $questionfields = ['name', 'questiontextformat', 'generalfeedbackformat', 'defaultmark', 'penalty'];
        foreach ($questionfields as $field) {
            self::assertEquals($questiondata->{$field}, $importedquestion->{$field});
        }
        self::assertEqualsWithDelta($questiondata->timecreated, $importedquestion->timecreated, 2);
        self::assertEqualsWithDelta($questiondata->timemodified, $importedquestion->timemodified, 2);

        // Check the question's text fields and their format.
        $textfields = ['questiontext', 'generalfeedback'];
        foreach ($textfields as $field) {
            self::assertEquals($questiondata->{$field}, $importedquestion->{$field});
            self::assertEquals($questiondata->{$field . 'format'}, $importedquestion->{$field . 'format'});
        }
        $combinedfeedbackfields = ['correctfeedback', 'partiallycorrectfeedback', 'incorrectfeedback'];
        foreach ($combinedfeedbackfields as $field) {
            self::assertEquals($questiondata->options->{$field}, $importedquestion->options->{$field});
            self::assertEquals($questiondata->options->{$field . 'format'}, $importedquestion->options->{$field . 'format'});
        }

        // Check the specific fields for our qtype.
        $extrafields = $this->qtype->extra_question_fields();
        array_shift($extrafields);
        foreach ($extrafields as $field) {
            self::assertEquals($questiondata->options->{$field}, $importedquestion->options->{$field});
        }

        // Make sure the number of parts match and check the basic fields for every part.
        $numparts = count($questiondata->options->answers);
        self::assertEquals($numparts, count($importedquestion->options->answers));
        $originalparts = array_values($questiondata->options->answers);
        $importedparts = array_values($importedquestion->options->answers);
        foreach ($this->qtype::PART_BASIC_FIELDS as $field) {
            for ($i = 0; $i < $numparts; $i++) {
                self::assertEquals($originalparts[$i]->{$field}, $importedparts[$i]->{$field});
            }
        }

        // Verify the parts' text fields and their formats.
        $parttextfields = ['subqtext', 'feedback', 'partcorrectfb', 'partpartiallycorrectfb', 'partincorrectfb'];
        foreach ($parttextfields as $field) {
            for ($i = 0; $i < $numparts; $i++) {
                self::assertEquals($originalparts[$i]->{$field}, $importedparts[$i]->{$field});
                self::assertEquals($originalparts[$i]->{$field . 'format'}, $importedparts[$i]->{$field . 'format'});
            }
        }
    }
}
