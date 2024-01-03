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

/*

Methods to cover:

* save_question_options
* save_question
* delete_question
* split_question_text
* initialize_question_instance?
* check_placeholders
  - too long, format, duplicate, exactly once in qtext
* validate
* check_and_filter_parts
* check_variables_and_expressions
* reorder_parts


*/


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

    public function test_name() {
        self::assertEquals($this->qtype->name(), 'formulas');
    }

    public function test_can_analyse_responses() {
        self::assertTrue($this->qtype->can_analyse_responses());
    }

    public function test_reorder_parts_according_to_questiontext() {
        $questiontext = 'Main {#2} and {#1}.';

        $part1 = (object)['placeholder' => '#1'];
        $part2 = (object)['placeholder' => '#2'];
        $parts = [$part1, $part2];

        $orderedparts = $this->qtype->reorder_parts($questiontext, $parts);

        self::assertEquals([$part2, $part1], $orderedparts);
    }

    public function test_reorder_parts_no_placeholder_comes_last() {
        $questiontext = 'Main {#third} then {#fourth} and {#first}.';

        $part1 = (object)['placeholder' => '#first'];
        $part2 = (object)['placeholder' => ''];
        $part3 = (object)['placeholder' => '#third'];
        $part4 = (object)['placeholder' => '#fourth'];
        $parts = [$part1, $part2, $part3, $part4];

        $orderedparts = $this->qtype->reorder_parts($questiontext, $parts);

        self::assertEquals([$part3, $part4, $part1, $part2], $orderedparts);
    }

    public function test_reorder_multiple_parts_without_placeholder() {
        $questiontext = 'Main text without placeholders.';

        $part1 = (object)['placeholder' => ''];
        $part2 = (object)['placeholder' => ''];
        $part3 = (object)['placeholder' => ''];
        $part4 = (object)['placeholder' => ''];
        $parts = [$part1, $part2, $part3, $part4];

        $orderedparts = $this->qtype->reorder_parts($questiontext, $parts);

        self::assertEquals([$part1, $part2, $part3, $part4], $orderedparts);
    }



    public function test_check_placeholder0() {
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

    public function test_split_questiontext0() {
        $q = $this->get_test_formulas_question('testthreeparts');
        $expected = array(0 => '<p>Multiple parts : --',
                1 => '--',
                2 => '--',
                3 => '</p>');
        self::assertEquals($expected, $this->qtype->split_questiontext($q->questiontext, $q->parts));
    }

    public function test_split_questiontext1() {
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

    public function test_fetch_part_ids_for_question() {
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
    public function test_get_question_options() {
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
}
