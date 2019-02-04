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


defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/question/engine/tests/helpers.php');
require_once($CFG->dirroot . '/question/type/formulas/questiontype.php');
require_once($CFG->dirroot . '/question/type/edit_question_form.php');
require_once($CFG->dirroot . '/question/type/formulas/tests/helper.php');
require_once($CFG->dirroot . '/question/type/formulas/edit_formulas_form.php');


/**
 * Unit tests for question/type/formulas/questiontype.php.
 *
 * @copyright  2013 Jean-Michel Vedrine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class qtype_formulas_test extends advanced_testcase {

    protected $tolerance = 0.00000001;
    /** @var formulas instance of the question type class to test. */
    protected $qtype;

    /**
     * @return qtype_formulas_question the requested question object.
     */
    protected function get_test_formulas_question($which = null) {
        return test_question_maker::make_question('formulas', $which);
    }

    protected function setUp() {
        $this->qtype = new qtype_formulas();
    }

    protected function tearDown() {
        $this->qtype = null;
    }

    public function test_name() {
        $this->assertEquals($this->qtype->name(), 'formulas');
    }

    public function test_can_analyse_responses() {
        $this->assertTrue($this->qtype->can_analyse_responses());
    }

    public function test_reorder_answers0() {
        $questiontext = 'Main {#2} and {#1}.';
        $ans1 = new stdClass();
        $ans1->placeholder = '#1';
        $ans2 = new stdClass();
        $ans2->placeholder = '#2';
        $answers = array(0 => $ans1, 1 => $ans2);
        $this->assertEquals(array(0 => 1, 1 => 0), $this->qtype->reorder_answers($questiontext, $answers));
    }

    public function test_reorder_answers1() {
        $questiontext = 'Main {#third} then {#fourth} and {#first}.';
        $ans1 = new stdClass();
        $ans1->placeholder = '#first';
        $ans2 = new stdClass();
        $ans2->placeholder = '';
        $ans3 = new stdClass();
        $ans3->placeholder = '#third';
        $ans4 = new stdClass();
        $ans4->placeholder = '#fourth';
        $answers = array(0 => $ans1, 1 => $ans2, 2 => $ans3, 3 => $ans4);
        $this->assertEquals(array(0 => 2, 1 => 3, 2 => 0, 3 => 1), $this->qtype->reorder_answers($questiontext, $answers));
    }

    public function test_reorder_answers2() {
        $questiontext = 'Main text without placeholders.';
        $ans1 = new stdClass();
        $ans1->placeholder = '';
        $ans2 = new stdClass();
        $ans2->placeholder = '';
        $ans3 = new stdClass();
        $ans3->placeholder = '';
        $ans4 = new stdClass();
        $ans4->placeholder = '';
        $answers = array(0 => $ans1, 1 => $ans2, 2 => $ans3, 3 => $ans4);
        $this->assertEquals(array(0 => 0, 1 => 1, 2 => 2, 3 => 3), $this->qtype->reorder_answers($questiontext, $answers));
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
        $this->assertEquals($expected, $this->qtype->check_placeholder($questiontext, $answers));
    }

    public function test_split_questiontext0() {
        $q = $this->get_test_formulas_question('test1');
        $expected = array(0 => '<p>Multiple parts : --',
                1 => '--',
                2 => '--',
                3 => '</p>');
        $this->assertEquals($expected, $this->qtype->split_questiontext($q->questiontext, $q->parts));
    }

    public function test_split_questiontext1() {
        $q = $this->get_test_formulas_question('test4');
        $expected = array(0 => '<p>This question shows different display methods of the answer and unit box.</p>',
                1 => '',
                2 => '',
                3 => '',
                4 => '');
        $this->assertEquals($expected, $this->qtype->split_questiontext($q->questiontext, $q->parts));
    }

    /**
     * Test to make sure that loading of question options works, including in an error case.
     */
    public function test_get_question_options() {
        global $DB;

        $this->resetAfterTest(true);
        $this->setAdminUser();

        // Create a complete, in DB question to use.
        $questiondata = test_question_maker::get_question_data('formulas', 'test2');
        // echo "questiondata";
        // var_dump(count($questiondata->options->answers));
        $formdata = test_question_maker::get_question_form_data('formulas', 'test2');
        // var_dump((array)$formdata);
        $generator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $cat = $generator->create_question_category(array());

        $formdata->category = "{$cat->id},{$cat->contextid}";
        $formdata->id = 0;
        qtype_formulas_edit_form::mock_submit((array)$formdata);
        // echo "after mocksubmit";
        // var_dump($_POST);
        $form = qtype_formulas_test_helper::get_question_editing_form($cat, $questiondata);
        $form->id = 0;
        // var_dump($form);
        $this->assertTrue($form->is_validated());

        $fromform = $form->get_data();
        // var_dump($fromform); pas correct
        $returnedfromsave = $this->qtype->save_question($questiondata, $fromform);
        // var_dump($returnedfromsave);
        // Now get just the raw DB record.
        $question = $DB->get_record('question', ['id' => $returnedfromsave->id], '*', MUST_EXIST);
        // $testanswers = $DB->get_records('qtype_formulas_answers');
        // var_dump($testanswers);
        // Load it.
        $this->qtype->get_question_options($question);
        $this->assertDebuggingNotCalled();
        $this->assertInstanceOf(stdClass::class, $question->options);

        $options = $question->options;
        // var_dump($options);
        $this->assertEquals($question->id, $options->questionid);
        // $this->assertEquals(4, $options->numpart);

        $this->assertCount(4, $options->answers);

        // Now we are going to delete the options record.
        $DB->delete_records('qtype_formulas_options', ['questionid' => $question->id]);

        // Now see what happens.
        $question = $DB->get_record('question', ['id' => $returnedfromsave->id], '*', MUST_EXIST);
        $this->qtype->get_question_options($question);

        $this->assertDebuggingCalled('Formulas question ID '.$question->id.' was missing an options record. Using default.');
        $this->assertInstanceOf(stdClass::class, $question->options);
        $options = $question->options;
        $this->assertEquals($question->id, $options->questionid);
        $this->assertEquals(4, $options->numpart);
        $this->assertCount(4, $options->answers);

        $this->assertEquals(get_string('correctfeedbackdefault', 'question'), $options->correctfeedback);
        $this->assertEquals(FORMAT_HTML, $options->correctfeedbackformat);

        // And finally we try again with no answer either.
        $DB->delete_records('qtype_formulas_answers', ['questionid' => $question->id]);

        $question = $DB->get_record('question', ['id' => $returnedfromsave->id], '*', MUST_EXIST);
        $this->qtype->get_question_options($question);

        $this->assertDebuggingCalled('Formulas question ID '.$question->id.' was missing an options record. Using default.');
        $this->assertInstanceOf(stdClass::class, $question->options);
        $options = $question->options;
        $this->assertEquals($question->id, $options->questionid);
        $this->assertEquals(0, $options->numpart);
        $this->assertCount(0, $options->answers);
    }
}
