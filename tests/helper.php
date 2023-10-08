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
 * Test helper code for the formulas question type.
 *
 * @package    qtype_formulas
 * @copyright  2012 Jean-Michel Védrine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Test helper class for the formulas question type.
 *
 * @copyright  2012 Jean-Michel Védrine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class qtype_formulas_test_helper extends question_test_helper {
    const DEFAULT_CORRECT_FEEDBACK          = '<p>Correct answer, well done.</p>';
    const DEFAULT_PARTIALLYCORRECT_FEEDBACK = '<p>Your answer is partially correct.</p>';
    const DEFAULT_INCORRECT_FEEDBACK        = '<p>Incorrect answer.</p>';

    public function get_test_questions() {
        return array(
            'testsinglenum', // Minimal formulas question : one part, not randomised (answer = 5),
            'testtwonums', // Minimal formulas question : one part, two numbers (2 and 3),
            'testsinglenumunit', // one part, one number with unit (answer: 5 m/s),
            'testsinglenumunitsep', // one part, one number plus separate unit (answer: 5 m/s),
            'testzero', // one part, not randomized, answer = 0 (to test problem with 0 as answer),
            'testmce', // One part not randomized drowdown multichoice answer.
            'testmc', // One part not randomized radiobutton multichoice answer.
            'testthreeparts', // 3 parts, not randomised. (answers = 5, 6, 7),
            'testmethodsinparts', // 4 parts, separated and combined unit field, not ramdomized,
            'testmcetwoparts', // 2 parts, each one with an MCE (dropdown) question
            'testmctwoparts', // 2 parts, each one with an MC (radio) question
            'testtwoandtwo', // 2 parts, each one with 2 numbers
            'test4', // 4 parts, separated and combined unit field, ramdomized.
        );
    }

    /**
     * Does the basical initialisation of a new formulas question that all the test
     * questions will need.
     * @return qtype_formulas_question the new question.
     */
    protected static function make_a_formulas_question() {
        question_bank::load_question_definition_classes('formulas');

        $q = new qtype_formulas_question();
        test_question_maker::initialise_a_question($q);
        $q->qtype = question_bank::get_qtype('formulas');
        $q->contextid = context_system::instance()->id;
        $q->varsrandom = '';
        $q->varsglobal = '';
        $q->shownumcorrect = 0;
        $q->answernumbering = 'abc';
        $q->qv = new qtype_formulas\variables();
        $q->penalty = 0.2; // The default.
        test_question_maker::set_standard_combined_feedback_fields($q);
        $q->numpart = 0;   // This is of course invalid but should be changed by all tests.
        $q->parts = array();
        $q->evaluatedanswer = array();
        $q->fractions = array();
        $q->anscorrs = array();
        $q->unitcorrs = array();
        return $q;
    }

    protected static function make_a_formulas_part() {
        question_bank::load_question_definition_classes('formulas');

        $p = new qtype_formulas_part();
        $p->id = 0;
        $p->placeholder = '';
        $p->answermark = 1;
        $p->answertype = 0;
        $p->numbox = 1;
        $p->vars1 = '';
        $p->vars2 = '';
        $p->answer = '1';
        $p->answernotunique = '1';
        $p->correctness = '_relerr < 0.01';
        $p->unitpenalty = 1;
        $p->postunit = '';
        $p->ruleid = 1;
        $p->otherrule = '';
        $p->subqtext = '';
        $p->subqtextformat = 1;
        $p->feedback = '';
        $p->feedbackformat = 1;
        $p->partcorrectfb = '';
        $p->partcorrectfbformat = 1;
        $p->partpartiallycorrectfb = '';
        $p->partpartiallycorrectfbformat = 1;
        $p->partincorrectfb = '';
        $p->partincorrectfbformat = 1;
        $p->partindex = 0;

        return $p;
    }

    /**
     * @return qtype_formulas_question question, single part, one number as answer, no unit
     */
    public static function make_formulas_question_testsinglenum() {
        $q = self::make_a_formulas_question();

        $q->name = 'test-0';
        $q->questiontext = '<p>This is a minimal question. The answer is 5.</p>';

        $q->penalty = 0.3; // Non-zero and not the default.
        $q->textfragments = array(0 => '<p>This is a minimal question. The answer is 5.</p>',
                                  1 => '');
        $q->numpart = 1;
        $q->defaultmark = 2;
        $p = self::make_a_formulas_part();
        $p->id = 14;
        $p->placeholder = '';
        $p->answermark = 2;
        $p->answer = '5';
        $p->answernotunique = '1';
        $p->subqtext = '';
        $p->partcorrectfb = 'Your answer is correct.';
        $p->partpartiallycorrectfb = 'Your answer is partially correct.';
        $p->partincorrectfb = 'Your answer is incorrect.';
        $q->parts[0] = $p;
        return $q;
    }

    /**
     * Gets the question form data for the singlenum formulas question
     * @return stdClass
     */
    public function get_formulas_question_form_data_testsinglenum() {
        $form = new stdClass();

        $form->name = 'test-0';
        $form->noanswers = 1;
        $form->answer = array('5');
        $form->answernotunique = array('1');
        $form->answermark = array(2);
        $form->answertype = array(0);
        $form->correctness = array('_relerr < 0.01');
        $form->numbox = array(1);
        $form->placeholder = array('');
        $form->vars1 = array('');
        $form->vars2 = array('');
        $form->ruleid = array(1);
        $form->unitpenalty = array(1);
        $form->postunit = array('');
        $form->otherrule = array('');
        $form->subqtext = array(
            array('text' => '', 'format' => FORMAT_HTML)
        );
        $form->feedback = array(
            array('text' => '', 'format' => FORMAT_HTML)
        );
        $form->partcorrectfb = array(
            array('text' => 'Your answer is correct.', 'format' => FORMAT_HTML)
        );
        $form->partpartiallycorrectfb = array(
            array('text' => 'Your answer is partially correct.', 'format' => FORMAT_HTML)
        );
        $form->partincorrectfb = array(
            array('text' => 'Your answer is incorrect.', 'format' => FORMAT_HTML)
        );
        $form->questiontext = array('text' => '<p>This is a minimal question. The answer is 5.</p>',
                'format' => FORMAT_HTML);
        $form->generalfeedback = array('text' => '', 'format' => FORMAT_HTML);
        $form->defaultmark = 2;
        $form->penalty = 0.3;
        $form->varsrandom = '';
        $form->varsglobal = '';
        $form->answernumbering = 'abc';
        $form->globalunitpenalty = 1;
        $form->globalruleid = 1;
        $form->correctfeedback = array('text' => '', 'format' => FORMAT_HTML);
        $form->partiallycorrectfeedback = array('text' => '', 'format' => FORMAT_HTML);
        $form->shownumcorrect = '0';
        $form->incorrectfeedback = array('text' => '', 'format' => FORMAT_HTML);
        return $form;
    }

    /**
     * @return qtype_formulas_question one part, one number with unit
     */
    public static function make_formulas_question_testsinglenumunit() {
        $q = self::make_a_formulas_question();

        $q->name = 'test-0';
        $q->questiontext = '<p>One part, one number plus unit, answer is 5 m/s</p>';

        $q->penalty = 0.3;
        $q->textfragments = array(0 => '<p>One part, one number plus unit, answer is 5 m/s</p>',
                                  1 => '');
        $q->numpart = 1;
        $q->defaultmark = 2;
        $p = self::make_a_formulas_part();
        $p->id = 14;
        $p->placeholder = '';
        $p->answermark = 2;
        $p->answer = '5';
        $p->answernotunique = '1';
        $p->postunit = 'm/s';
        $p->subqtext = '{_0}{_u}';
        $p->partcorrectfb = 'Your answer is correct.';
        $p->partpartiallycorrectfb = 'Your answer is partially correct.';
        $p->partincorrectfb = 'Your answer is incorrect.';
        $q->parts[0] = $p;
        return $q;
    }

    /**
     * Gets the question form data for the singlenum formulas question
     * @return stdClass
     */
    public function get_formulas_question_form_data_testsinglenumunit() {
        $form = new stdClass();

        $form->name = 'test-0';
        $form->noanswers = 1;
        $form->answer = array('5');
        $form->answernotunique = array('1');
        $form->answermark = array(2);
        $form->answertype = array(0);
        $form->correctness = array('_relerr < 0.01');
        $form->numbox = array(1);
        $form->placeholder = array('');
        $form->vars1 = array('');
        $form->vars2 = array('');
        $form->ruleid = array(1);
        $form->unitpenalty = array(1);
        $form->postunit = array('m/s');
        $form->otherrule = array('');
        $form->subqtext = array(
            array('text' => '{_0}{_u}', 'format' => FORMAT_HTML)
        );
        $form->feedback = array(
            array('text' => '', 'format' => FORMAT_HTML)
        );
        $form->partcorrectfb = array(
            array('text' => 'Your answer is correct.', 'format' => FORMAT_HTML)
        );
        $form->partpartiallycorrectfb = array(
            array('text' => 'Your answer is partially correct.', 'format' => FORMAT_HTML)
        );
        $form->partincorrectfb = array(
            array('text' => 'Your answer is incorrect.', 'format' => FORMAT_HTML)
        );
        $form->questiontext = array('text' => '<p>One part, one number plus unit, answer is 5 m/s</p>',
                'format' => FORMAT_HTML);
        $form->generalfeedback = array('text' => '', 'format' => FORMAT_HTML);
        $form->defaultmark = 2;
        $form->penalty = 0.3;
        $form->varsrandom = '';
        $form->varsglobal = '';
        $form->answernumbering = 'abc';
        $form->globalunitpenalty = 1;
        $form->globalruleid = 1;
        $form->correctfeedback = array('text' => '', 'format' => FORMAT_HTML);
        $form->partiallycorrectfeedback = array('text' => '', 'format' => FORMAT_HTML);
        $form->shownumcorrect = '0';
        $form->incorrectfeedback = array('text' => '', 'format' => FORMAT_HTML);
        return $form;
    }

    /**
     * @return qtype_formulas_question one part, one number plus unit
     */
    public static function make_formulas_question_testsinglenumunitsep() {
        $q = self::make_a_formulas_question();

        $q->name = 'test-0';
        $q->questiontext = '<p>One part, one number plus unit, answer is 5 m/s</p>';

        $q->penalty = 0.3;
        $q->textfragments = array(0 => '<p>One part, one number plus unit, answer is 5 m/s</p>',
                                  1 => '');
        $q->numpart = 1;
        $q->defaultmark = 2;
        $p = self::make_a_formulas_part();
        $p->id = 14;
        $p->placeholder = '';
        $p->answermark = 2;
        $p->answer = '5';
        $p->answernotunique = '1';
        $p->postunit = 'm/s';
        $p->subqtext = '{_0} {_u}';
        $p->partcorrectfb = 'Your answer is correct.';
        $p->partpartiallycorrectfb = 'Your answer is partially correct.';
        $p->partincorrectfb = 'Your answer is incorrect.';
        $q->parts[0] = $p;
        return $q;
    }

    /**
     * Gets the question form data for the singlenum formulas question
     * @return stdClass
     */
    public function get_formulas_question_form_data_testsinglenumunitsep() {
        $form = new stdClass();

        $form->name = 'test-0';
        $form->noanswers = 1;
        $form->answer = array('5');
        $form->answernotunique = array('1');
        $form->answermark = array(2);
        $form->answertype = array(0);
        $form->correctness = array('_relerr < 0.01');
        $form->numbox = array(1);
        $form->placeholder = array('');
        $form->vars1 = array('');
        $form->vars2 = array('');
        $form->ruleid = array(1);
        $form->unitpenalty = array(1);
        $form->postunit = array('m/s');
        $form->otherrule = array('');
        $form->subqtext = array(
            array('text' => '{_0} {_u}', 'format' => FORMAT_HTML)
        );
        $form->feedback = array(
            array('text' => '', 'format' => FORMAT_HTML)
        );
        $form->partcorrectfb = array(
            array('text' => 'Your answer is correct.', 'format' => FORMAT_HTML)
        );
        $form->partpartiallycorrectfb = array(
            array('text' => 'Your answer is partially correct.', 'format' => FORMAT_HTML)
        );
        $form->partincorrectfb = array(
            array('text' => 'Your answer is incorrect.', 'format' => FORMAT_HTML)
        );
        $form->questiontext = array('text' => '<p>One part, one number plus unit, answer is 5 m/s</p>',
                'format' => FORMAT_HTML);
        $form->generalfeedback = array('text' => '', 'format' => FORMAT_HTML);
        $form->defaultmark = 2;
        $form->penalty = 0.3;
        $form->varsrandom = '';
        $form->varsglobal = '';
        $form->answernumbering = 'abc';
        $form->globalunitpenalty = 1;
        $form->globalruleid = 1;
        $form->correctfeedback = array('text' => '', 'format' => FORMAT_HTML);
        $form->partiallycorrectfeedback = array('text' => '', 'format' => FORMAT_HTML);
        $form->shownumcorrect = '0';
        $form->incorrectfeedback = array('text' => '', 'format' => FORMAT_HTML);
        return $form;
    }

    /**
     * @return qtype_formulas_question question, single part, one number as answer, no unit
     */
    public static function make_formulas_question_testtwonums() {
        $q = self::make_a_formulas_question();

        $q->name = 'test-1';
        $q->questiontext = '<p>Question with two numbers. The answers are 2 and 3.</p>';

        $q->penalty = 0.3; // Non-zero and not the default.
        $q->textfragments = array(0 => '<p>Question with two numbers. The answers are 2 and 3.</p>',
                                  1 => '');
        $q->numpart = 1;
        $q->defaultmark = 2;
        $p = self::make_a_formulas_part();
        $p->id = 14;
        $p->placeholder = '';
        $p->answermark = 2;
        $p->answer = '[2, 3]';
        $p->answernotunique = '1';
        $p->subqtext = '';
        $p->partcorrectfb = 'Your answer is correct.';
        $p->partpartiallycorrectfb = 'Your answer is partially correct.';
        $p->partincorrectfb = 'Your answer is incorrect.';
        $q->parts[0] = $p;
        return $q;
    }

    /**
     * Gets the question form data for the twonums formulas question
     * @return stdClass
     */
    public function get_formulas_question_form_data_testtwonums() {
        $form = new stdClass();

        $form->name = 'test-1';
        $form->noanswers = 1;
        $form->answer = array('[2, 3]');
        $form->answernotunique = array('1');
        $form->answermark = array(2);
        $form->answertype = array(0);
        $form->correctness = array('_relerr < 0.01');
        $form->numbox = array(1, 1);
        $form->placeholder = array('');
        $form->vars1 = array('');
        $form->vars2 = array('');
        $form->ruleid = array(1);
        $form->unitpenalty = array(1);
        $form->postunit = array('');
        $form->otherrule = array('');
        $form->subqtext = array(
            array('text' => '', 'format' => FORMAT_HTML)
        );
        $form->feedback = array(
            array('text' => '', 'format' => FORMAT_HTML)
        );
        $form->partcorrectfb = array(
            array('text' => 'Your answer is correct.', 'format' => FORMAT_HTML)
        );
        $form->partpartiallycorrectfb = array(
            array('text' => 'Your answer is partially correct.', 'format' => FORMAT_HTML)
        );
        $form->partincorrectfb = array(
            array('text' => 'Your answer is incorrect.', 'format' => FORMAT_HTML)
        );
        $form->questiontext = array('text' => '<p>Question with two numbers. The answers are 2 and 3.</p>',
                'format' => FORMAT_HTML);
        $form->generalfeedback = array('text' => '', 'format' => FORMAT_HTML);
        $form->defaultmark = 2;
        $form->penalty = 0.3;
        $form->varsrandom = '';
        $form->varsglobal = '';
        $form->answernumbering = '';
        $form->globalunitpenalty = 1;
        $form->globalruleid = 1;
        $form->correctfeedback = array('text' => '', 'format' => FORMAT_HTML);
        $form->partiallycorrectfeedback = array('text' => '', 'format' => FORMAT_HTML);
        $form->shownumcorrect = '0';
        $form->incorrectfeedback = array('text' => '', 'format' => FORMAT_HTML);
        return $form;
    }

    /**
     * @return qtype_formulas_question with 3 parts.
     * this version is non randomized to ease testing
     */
    public static function make_formulas_question_testthreeparts() {
        $q = self::make_a_formulas_question();

        $q->name = 'test-1';
        $q->questiontext = '<p>Multiple parts : --{#1}--{#2}--{#3}</p>';
        $q->penalty = 0.3; // Non-zero and not the default.
        $q->textfragments = array(0 => '<p>Multiple parts : --',
                1 => '--',
                2 => '--',
                3 => '</p>');
        $q->numpart = 3;
        $q->defaultmark = 6;
        $p0 = self::make_a_formulas_part();
        $p0->placeholder = '#1';
        $p0->id = 14;
        $p0->answermark = 2;
        $p0->answer = '5';
        $p0->answernotunique = '1';
        $p0->subqtext = 'This is first part.';
        $p0->partcorrectfb = 'Part 1 correct feedback.';
        $p0->partpartiallycorrectfb = 'Part 1 partially correct feedback.';
        $p0->partincorrectfb = 'Part 1 incorrect feedback.';
        $q->parts[0] = $p0;
        $p1 = self::make_a_formulas_part();
        $p1->placeholder = '#2';
        $p1->id = 15;
        $p1->partindex = 1;
        $p1->answermark = 2;
        $p1->answer = '6';
        $p1->answernotunique = '1';
        $p1->subqtext = 'This is second part.';
        $p1->partcorrectfb = 'Part 2 correct feedback.';
        $p1->partpartiallycorrectfb = 'Part 2 partially correct feedback.';
        $p1->partincorrectfb = 'Part 2 incorrect feedback.';
        $q->parts[1] = $p1;
        $p2 = self::make_a_formulas_part();
        $p2->placeholder = '#3';
        $p2->id = 16;
        $p2->partindex = 2;
        $p2->answermark = 2;
        $p2->answer = '7';
        $p2->answernotunique = '1';
        $p2->subqtext = 'This is third part.';
        $p2->partcorrectfb = 'Part 3 correct feedback.';
        $p2->partpartiallycorrectfb = 'Part 3 partially correct feedback.';
        $p2->partincorrectfb = 'Part 3 incorrect feedback.';
        $q->parts[2] = $p2;

        return $q;
    }

    /**
     * Gets the question form data for the test1 formulas question
     * @return stdClass
     */
    public function get_formulas_question_form_data_testthreeparts() {
        $form = new stdClass();

        $form->name = 'test-1';
        $form->questiontext = array('text' => '<p>Multiple parts : --{#1}--{#2}--{#3}</p>',
                'format' => FORMAT_HTML);
        $form->generalfeedback = array('text' => '', 'format' => FORMAT_HTML);
        $form->defaultmark = 6;
        $form->penalty = 0.3;
        $form->varsrandom = '';
        $form->varsglobal = '';
        $form->answernumbering = 'abc';
        $form->noanswers = 3;
        $form->answer = array('5', '6', '7');
        $form->answernotunique = array('1', '1', '1');
        $form->answermark = array('2', '2', '2');
        $form->numbox = array(1, 1, 1);
        $form->placeholder = array('#1', '#2', '#3');
        $form->postunit = array('', '', '');
        $form->answertype = array(0, 0, 0);
        $form->vars1 = array('', '', '');
        $form->correctness = array('_relerr < 0.01', '_relerr < 0.01', '_relerr < 0.01');
        $form->vars2 = array('', '', '');
        $form->unitpenalty = array(1, 1, 1);
        $form->ruleid = array('1', '1', '1');
        $form->otherrule = array('', '', '');
        $form->globalunitpenalty = 1;
        $form->globalruleid = 1;
        $form->subqtext = array(
            array('text' => 'This is first part.', 'format' => FORMAT_HTML),
            array('text' => 'This is second part.', 'format' => FORMAT_HTML),
            array('text' => 'This is third part.', 'format' => FORMAT_HTML),
        );
        $form->feedback = array(
            array('text' => '', 'format' => FORMAT_HTML),
            array('text' => '', 'format' => FORMAT_HTML),
            array('text' => '', 'format' => FORMAT_HTML),
        );
        $form->partcorrectfb = array(
            array('text' => 'Part 1 correct feedback.', 'format' => FORMAT_HTML),
            array('text' => 'Part 2 correct feedback.', 'format' => FORMAT_HTML),
            array('text' => 'Part 3 correct feedback.', 'format' => FORMAT_HTML),
        );
        $form->partpartiallycorrectfb = array(
            array('text' => 'Part 1 partially correct feedback.', 'format' => FORMAT_HTML),
            array('text' => 'Part 2 partially correct feedback.', 'format' => FORMAT_HTML),
            array('text' => 'Part 3 partially correct feedback.', 'format' => FORMAT_HTML),
        );
        $form->partincorrectfb = array(
            array('text' => 'Part 1 incorrect feedback.', 'format' => FORMAT_HTML),
            array('text' => 'Part 2 incorrect feedback.', 'format' => FORMAT_HTML),
            array('text' => 'Part 3 incorrect feedback.', 'format' => FORMAT_HTML),
        );
        $form->correctfeedback = array('text' => '', 'format' => FORMAT_HTML);
        $form->partiallycorrectfeedback = array('text' => '', 'format' => FORMAT_HTML);
        $form->shownumcorrect = '0';
        $form->incorrectfeedback = array('text' => '', 'format' => FORMAT_HTML);
        $form->numhints = 2;
        $form->hint = array(
            array('text' => '', 'format' => FORMAT_HTML),
            array('text' => '', 'format' => FORMAT_HTML),
        );
        $form->hintclearwrong = array('0', '0');
        $form->hintshownumcorrect = array('0', '0');
        return $form;
    }

    /**
     * @return qtype_formulas_question the question from the test1.xml file.
     * Non randomized version for Behat tests.
     */
    public static function make_formulas_question_testmethodsinparts() {
        $q = self::make_a_formulas_question();

        $q->name = 'test-2';
        $q->questiontext = '<p>This question shows different display methods of the answer and unit box.</p>';
        $q->defaultmark = 8;
        $q->penalty = 0.3; // Non-zero and not the default.
        $q->numpart = 4;
        $q->textfragments = array(0 => '<p>This question shows different display methods of the answer and unit box.</p>',
                1 => '',
                2 => '',
                3 => '',
                4 => '',
                );
        $q->varsrandom = '';
        $q->varsglobal = 'v = 40;dt = 3;s = v*dt;';
        $p0 = self::make_a_formulas_part();
        $p0->id = 14;
        $p0->partindex = 0;
        $p0->answermark = 2;
        $p0->subqtext = '<p>If a car travels {s} m in {dt} s, what is the speed of the car? {_0}{_u}</p>';      // Combined unit.
        $p0->answer = 'v';
        $p0->answernotunique = '1';
        $p0->postunit = 'm/s';
        $q->parts[0] = $p0;
        $p1 = self::make_a_formulas_part();
        $p1->id = 15;
        $p1->partindex = 1;
        $p1->answermark = 2;
        $p1->subqtext = '<p>If a car travels {s} m in {dt} s, what is the speed of the car? {_0} {_u}</p>';     // Separated unit.
        $p1->answer = 'v';
        $p1->answernotunique = '1';
        $p1->postunit = 'm/s';
        $q->parts[1] = $p1;
        $p2 = self::make_a_formulas_part();
        $p2->id = 16;
        $p2->partindex = 2;
        $p2->answermark = 2;
        // As postunit is empty {_u} should be ignored.
        $p2->subqtext = '<p>If a car travels {s} m in {dt} s, what is the speed of the car? {_0} {_u}</p>';
        $p2->answer = 'v';
        $p2->answernotunique = '1';
        $p2->postunit = '';
        $q->parts[2] = $p2;
        $p3 = self::make_a_formulas_part();
        $p3->id = 17;
        $p3->partindex = 3;
        $p3->answermark = 2;
        // As postunit is empty {_u} should be ignored.
        $p3->subqtext = '<p>If a car travels {s} m in {dt} s, what is the speed of the car? speed = {_0}{_u}</p>';
        $p3->answer = 'v';
        $p3->answernotunique = '1';
        $p3->postunit = '';
        $q->parts[3] = $p3;

        return $q;
    }

    /**
     * Get the question data, as it would be loaded by get_question_options.
     * @return object
     */
    public static function get_formulas_question_data_testmethodsinparts() {
        global $USER;

        $qdata = new stdClass();
        test_question_maker::initialise_question_data($qdata);

        $qdata->qtype = 'formulas';
        $qdata->name = 'test-2';
        $qdata->questiontext = '<p>This question shows different display methods of the answer and unit box.</p>';
        $qdata->questiontextformat = FORMAT_HTML;
        $qdata->generalfeedback = '';
        $qdata->generalfeedbackformat = FORMAT_HTML;
        $qdata->defaultmark = 8;
        $qdata->length = 1;
        $qdata->penalty = 0.3;
        $qdata->hidden = 0;

        $qdata->options = new stdClass();
        $qdata->options->varsrandom = '';
        $qdata->options->varsglobal = 'v = 40;dt = 3;s = v*dt;';
        $qdata->options->answernumbering = 'abc';
        $qdata->options->shownumcorrect = 0;
        $qdata->options->correctfeedback =
                test_question_maker::STANDARD_OVERALL_CORRECT_FEEDBACK;
        $qdata->options->correctfeedbackformat = FORMAT_HTML;
        $qdata->options->partiallycorrectfeedback =
                test_question_maker::STANDARD_OVERALL_PARTIALLYCORRECT_FEEDBACK;
        $qdata->options->partiallycorrectfeedbackformat = FORMAT_HTML;
        $qdata->options->incorrectfeedback =
                test_question_maker::STANDARD_OVERALL_INCORRECT_FEEDBACK;
        $qdata->options->incorrectfeedbackformat = FORMAT_HTML;

        $qdata->options->answers = array(
            14 => (object) array(
                'id' => 14,
                'placeholder' => '',
                'answermark' => 2,
                'answertype' => 0,
                'numbox' => 1,
                'vars1' => '',
                'vars2' => '',
                'answer' => 'v',
                'answernotunique' => '1',
                'correctness' => '_relerr < 0.01',
                'unitpenalty' => 1,
                'postunit' => 'm/s',
                'ruleid' => 1,
                'otherrule' => '',
                'subqtext' => '<p>If a car travels {s} m in {dt} s, what is the speed of the car? {_0}{_u}</p>',
                'subqtextformat' => FORMAT_HTML,
                'feedback' => '',
                'feedbackformat' => FORMAT_HTML,
                'partcorrectfb' => '',
                'partcorrectfbformat' => FORMAT_HTML,
                'partpartiallycorrectfb' => '',
                'partpartiallycorrectfbformat' => FORMAT_HTML,
                'partincorrectfb' => '',
                'partincorrectfbformat' => FORMAT_HTML,
                'partindex' => 0,
            ),
            15 => (object) array(
                'id' => 15,
                'placeholder' => '',
                'answermark' => 2,
                'answertype' => 0,
                'numbox' => 1,
                'vars1' => '',
                'vars2' => '',
                'answer' => 'v',
                'answernotunique' => '1',
                'correctness' => '_relerr < 0.01',
                'unitpenalty' => 1,
                'postunit' => 'm/s',
                'ruleid' => 1,
                'otherrule' => '',
                'subqtext' => '<p>If a car travels {s} m in {dt} s, what is the speed of the car? {_0} {_u}</p>',
                'subqtextformat' => FORMAT_HTML,
                'feedback' => '',
                'feedbackformat' => FORMAT_HTML,
                'partcorrectfb' => '',
                'partcorrectfbformat' => FORMAT_HTML,
                'partpartiallycorrectfb' => '',
                'partpartiallycorrectfbformat' => FORMAT_HTML,
                'partincorrectfb' => '',
                'partincorrectfbformat' => FORMAT_HTML,
                'partindex' => 1,
            ),
            16 => (object) array(
                'id' => 16,
                'placeholder' => '',
                'answermark' => 2,
                'answertype' => 0,
                'numbox' => 1,
                'vars1' => '',
                'vars2' => '',
                'answer' => 'v',
                'answernotunique' => '1',
                'correctness' => '_relerr < 0.01',
                'unitpenalty' => 1,
                'postunit' => '',
                'ruleid' => 1,
                'otherrule' => '',
                'subqtext' => '<p>If a car travels {s} m in {dt} s, what is the speed of the car? {_0} {_u}</p>',
                'subqtextformat' => FORMAT_HTML,
                'feedback' => '',
                'feedbackformat' => FORMAT_HTML,
                'partcorrectfb' => '',
                'partcorrectfbformat' => FORMAT_HTML,
                'partpartiallycorrectfb' => '',
                'partpartiallycorrectfbformat' => FORMAT_HTML,
                'partincorrectfb' => '',
                'partincorrectfbformat' => FORMAT_HTML,
                'partindex' => 2,
            ),
            17 => (object) array(
                'id' => 17,
                'placeholder' => '',
                'answermark' => 2,
                'answertype' => 0,
                'numbox' => 1,
                'vars1' => '',
                'vars2' => '',
                'answer' => 'v',
                'answernotunique' => '1',
                'correctness' => '_relerr < 0.01',
                'unitpenalty' => 1,
                'postunit' => '',
                'ruleid' => 1,
                'otherrule' => '',
                'subqtext' => '<p>If a car travels {s} m in {dt} s, what is the speed of the car? speed = {_0}{_u}',
                'subqtextformat' => FORMAT_HTML,
                'feedback' => '',
                'feedbackformat' => FORMAT_HTML,
                'partcorrectfb' => '',
                'partcorrectfbformat' => FORMAT_HTML,
                'partpartiallycorrectfb' => '',
                'partpartiallycorrectfbformat' => FORMAT_HTML,
                'partincorrectfb' => '',
                'partincorrectfbformat' => FORMAT_HTML,
                'partindex' => 3,
            ),
        );

        $qdata->options->numpart = 4;

        $qdata->hints = array(
            1 => (object) array(
                'hint' => 'Hint 1.',
                'hintformat' => FORMAT_HTML,
                'shownumcorrect' => 1,
                'clearwrong' => 0,
                'options' => 0,
            ),
            2 => (object) array(
                'hint' => 'Hint 2.',
                'hintformat' => FORMAT_HTML,
                'shownumcorrect' => 1,
                'clearwrong' => 1,
                'options' => 1,
            ),
        );

        return $qdata;
    }

    /**
     * Gets the question form data for a formulas question
     * @return stdClass
     */
    public function get_formulas_question_form_data_testmethodsinparts() {
        $form = new stdClass();

        $form->name = 'test-2';
        $form->questiontext = array('text' => '<p>This question shows different display methods of the answer and unit box.</p>',
                'format' => FORMAT_HTML);
        $form->generalfeedback = array('text' => 'This is the general feedback.', 'format' => FORMAT_HTML);
        $form->defaultmark = 8;
        $form->penalty = 0.3;
        $form->varsrandom = '';
        $form->varsglobal = 'v = 40;dt = 3;s = v*dt;';
        $form->answernumbering = 'abc';
        $form->noanswers = 4;
        $form->answer = array(
            0 => 'v',
            1 => 'v',
            2 => 'v',
            3 => 'v',
        );
        $form->answernotunique = array(
            0 => '1',
            1 => '1',
            2 => '1',
            3 => '1',
        );
        $form->answermark = array(
            0 => 2,
            1 => 2,
            2 => 2,
            3 => 2,
        );
        $form->numbox = array(
            0 => 1,
            1 => 1,
            2 => 1,
            3 => 1,
        );
        $form->placeholder = array(
            0 => '',
            1 => '',
            2 => '',
            3 => '',
        );
        $form->postunit = array(
            0 => 'm/s',
            1 => 'm/s',
            2 => '',
            3 => '',
        );
        $form->answertype = array(
            0 => 0,
            1 => 0,
            2 => 0,
            3 => 0,
        );
        $form->vars1 = array(
            0 => '',
            1 => '',
            2 => '',
            3 => '',
        );
        $form->correctness = array(
            0 => '_relerr < 0.01',
            1 => '_relerr < 0.01',
            2 => '_relerr < 0.01',
            3 => '_relerr < 0.01'
        );
        $form->vars2 = array(
            0 => '',
            1 => '',
            2 => '',
            3 => '',
        );
        $form->unitpenalty = array(
            0 => '1.0',
            1 => '1.0',
            2 => '1.0',
            3 => '1.0',
        );
        $form->ruleid = array(
            0 => 1,
            1 => 1,
            2 => 1,
            3 => 1,
        );
        $form->otherrule = array(
            0 => '',
            1 => '',
            2 => '',
            3 => '',
        );
        $form->globalunitpenalty = 1;
        $form->globalruleid = 1;
        $form->subqtext = array(
            0 => array(
              'text' => '<p>If a car travels {s} m in {dt} s, what is the speed of the car? {_0}{_u}</p>',
              'format' => FORMAT_HTML
            ),
            1 => array(
              'text' => '<p>If a car travels {s} m in {dt} s, what is the speed of the car? {_0} {_u}</p>',
              'format' => FORMAT_HTML
            ),
            2 => array(
              'text' => '<p>If a car travels {s} m in {dt} s, what is the speed of the car? {_0} {_u}</p>',
              'format' => FORMAT_HTML
            ),
            3 => array(
              'text' => '<p>If a car travels {s} m in {dt} s, what is the speed of the car? speed = {_0}{_u}</p>',
              'format' => FORMAT_HTML
            ),
        );
        $form->feedback = array(
            0 => array('text' => '', 'format' => FORMAT_HTML),
            1 => array('text' => '', 'format' => FORMAT_HTML),
            2 => array('text' => '', 'format' => FORMAT_HTML),
            3 => array('text' => '', 'format' => FORMAT_HTML),
        );
        $form->partcorrectfb = array(
            0 => array('text' => '', 'format' => FORMAT_HTML),
            1 => array('text' => '', 'format' => FORMAT_HTML),
            2 => array('text' => '', 'format' => FORMAT_HTML),
            3 => array('text' => '', 'format' => FORMAT_HTML),
        );
        $form->partpartiallycorrectfb = array(
            0 => array('text' => '', 'format' => FORMAT_HTML),
            1 => array('text' => '', 'format' => FORMAT_HTML),
            2 => array('text' => '', 'format' => FORMAT_HTML),
            3 => array('text' => '', 'format' => FORMAT_HTML),
        );
        $form->partincorrectfb = array(
            0 => array('text' => '', 'format' => FORMAT_HTML),
            1 => array('text' => '', 'format' => FORMAT_HTML),
            2 => array('text' => '', 'format' => FORMAT_HTML),
            3 => array('text' => '', 'format' => FORMAT_HTML),
        );
        $form->correctfeedback = array('text' => '', 'format' => FORMAT_HTML);
        $form->partiallycorrectfeedback = array('text' => '', 'format' => FORMAT_HTML);
        $form->shownumcorrect = '0';
        $form->incorrectfeedback = array('text' => '', 'format' => FORMAT_HTML);
        $form->numhints = 2;
        $form->hint = array(
            array('text' => '', 'format' => FORMAT_HTML),
            array('text' => '', 'format' => FORMAT_HTML),
        );
        $form->hintclearwrong = array('0', '0');
        $form->hintshownumcorrect = array('0', '0');
        return $form;
    }

    /**
     * @return qtype_formulas_question the question with 0 as answer.
     */
    public static function make_formulas_question_testzero() {
        $q = self::make_a_formulas_question();

        $q->name = 'test-3';
        $q->questiontext = '<p>This question has 0 as answer to test problem when answer is equal to 0.</p>';

        $q->penalty = 0.3; // Non-zero and not the default.
        $q->textfragments = array(0 => '<p>This question has 0 as answer to test problem when answer is equal to 0.</p>',
                1 => '');
        $q->numpart = 1;
        $q->defaultmark = 2;
        $p = self::make_a_formulas_part();
        $p->id = 17;
        $p->answermark = 2;
        $p->answer = '0';
        $p->answernotunique = '1';
        $q->parts[0] = $p;

        return $q;
    }

    /**
     * Gets the question form data for the zero answer question
     * @return stdClass
     */
    public function get_formulas_question_form_data_testzero() {
        $form = new stdClass();

        $form->name = 'test-3';
        $form->noanswers = 1;
        $form->answer = array('0');
        $form->answernotunique = array('1');
        $form->answermark = array(2);
        $form->answertype = array(0);
        $form->correctness = array('_relerr < 0.01');
        $form->numbox = array(1);
        $form->placeholder = array('');
        $form->vars1 = array('');
        $form->vars2 = array('');
        $form->ruleid = array(1);
        $form->unitpenalty = array(1);
        $form->postunit = array('');
        $form->otherrule = array('');
        $form->subqtext = array(
            array('text' => '', 'format' => FORMAT_HTML)
        );
        $form->feedback = array(
            array('text' => '', 'format' => FORMAT_HTML)
        );
        $form->partcorrectfb = array(
            array('text' => 'Your answer is correct.', 'format' => FORMAT_HTML)
        );
        $form->partpartiallycorrectfb = array(
            array('text' => 'Your answer is partially correct.', 'format' => FORMAT_HTML)
        );
        $form->partincorrectfb = array(
            array('text' => 'Your answer is incorrect.', 'format' => FORMAT_HTML)
        );
        $form->questiontext = array('text' => '<p>This question has 0 as answer to test problem when answer is equal to 0.</p>',
                'format' => FORMAT_HTML);
        $form->generalfeedback = array('text' => '', 'format' => FORMAT_HTML);
        $form->defaultmark = 2;
        $form->penalty = 0.3;
        $form->varsrandom = '';
        $form->varsglobal = '';
        $form->answernumbering = '';
        $form->globalunitpenalty = 1;
        $form->globalruleid = 1;
        $form->correctfeedback = array('text' => '', 'format' => FORMAT_HTML);
        $form->partiallycorrectfeedback = array('text' => '', 'format' => FORMAT_HTML);
        $form->shownumcorrect = '0';
        $form->incorrectfeedback = array('text' => '', 'format' => FORMAT_HTML);
        return $form;
    }

    /**
     * @return qtype_formulas_question the question from the test1.xml file.
     * Randomized version.
     */
    public static function make_formulas_question_test4() {
        $q = self::make_a_formulas_question();

        $q->name = 'test-4';
        $q->questiontext = '<p>This question shows different display methods of the answer and unit box.</p>';
        $q->defaultmark = 8;
        $q->penalty = 0.3; // Non-zero and not the default.
        $q->numpart = 4;
        $q->textfragments = array(0 => '<p>This question shows different display methods of the answer and unit box.</p>',
                1 => '',
                2 => '',
                3 => '',
                4 => '',
                );
        $q->varsrandom = 'v = {20:100:10}; dt = {2:6};';
        $q->varsglobal = 's = v*dt;';
        $p0 = self::make_a_formulas_part();
        $p0->id = 18;
        $p0->partindex = 0;
        $p0->answermark = 2;
        $p0->subqtext = '<p>If a car travels {s} m in {dt} s, what is the speed of the car? {_0}{_u}</p>';      // Combined unit.
        $p0->answer = 'v';
        $p0->answernotunique = '1';
        $p0->postunit = 'm/s';
        $q->parts[0] = $p0;
        $p1 = self::make_a_formulas_part();
        $p1->id = 19;
        $p1->partindex = 1;
        $p1->answermark = 2;
        $p1->subqtext = '<p>If a car travels {s} m in {dt} s, what is the speed of the car? {_0} {_u}</p>';     // Separated unit.
        $p1->answer = 'v';
        $p1->answernotunique = '1';
        $p1->postunit = 'm/s';
        $q->parts[1] = $p1;
        $p2 = self::make_a_formulas_part();
        $p2->id = 20;
        $p2->partindex = 2;
        $p2->answermark = 2;
        $p2->answernotunique = '1';
        // As postunit is empty {_u} should be ignored.
        $p2->subqtext = '<p>If a car travels {s} m in {dt} s, what is the speed of the car? {_0} {_u}</p>';
        $p2->answer = 'v';
        $p2->postunit = '';
        $q->parts[2] = $p2;
        $p3 = self::make_a_formulas_part();
        $p3->id = 21;
        $p3->partindex = 3;
        $p3->answermark = 2;
        // As postunit is empty {_u} should be ignored.
        $p3->subqtext = '<p>If a car travels {s} m in {dt} s, what is the speed of the car? speed = {_0}{_u}</p>';
        $p3->answer = 'v';
        $p3->answernotunique = '1';
        $p3->postunit = '';
        $q->parts[3] = $p3;

        return $q;
    }

    /**
     * Gets the question form data for a formulas question
     * @return stdClass
     */
    public function get_formulas_question_form_data_test4() {
        $form = new stdClass();

        $form->name = 'test-4';
        $form->questiontext = array('text' => '<p>This question shows different display methods of the answer and unit box.</p>',
                'format' => FORMAT_HTML);
        $form->generalfeedback = array('text' => 'This is the general feedback.', 'format' => FORMAT_HTML);
        $form->defaultmark = 8;
        $form->penalty = 0.3;
        $form->varsrandom = 'v = {20:100:10}; dt = {2:6};';
        $form->varsglobal = 's = v*dt;';
        $form->answernumbering = 'abc';
        $form->noanswers = 4;
        $form->answer = array('v', 'v', 'v', 'v');
        $form->answernotunique = array('1', '1', '1', '1');
        $form->answermark = array('2', '2', '2', '2');
        $form->numbox = array(1, 1, 1, 1);
        $form->placeholder = array('', '', '', '');
        $form->postunit = array('m/s', 'm/s', '', '');
        $form->answertype = array(0, 0, 0, 0);
        $form->vars1 = array('', '', '', '');
        $form->correctness = array('_relerr < 0.01', '_relerr < 0.01', '_relerr < 0.01', '_relerr < 0.01');
        $form->vars2 = array('', '', '', '');
        $form->unitpenalty = array(1, 1, 1, 1);
        $form->ruleid = array('1', '1', '1', '1');
        $form->otherrule = array('', '', '', '');
        $form->globalunitpenalty = 1;
        $form->globalruleid = 1;
        $form->subqtext = array(
            array(
              'text' => '<p>If a car travels {s} m in {dt} s, what is the speed of the car? {_0}{_u}</p>',
              'format' => FORMAT_HTML
            ),
            array(
              'text' => '<p>If a car travels {s} m in {dt} s, what is the speed of the car? {_0} {_u}</p>',
              'format' => FORMAT_HTML
            ),
            array(
              'text' => '<p>If a car travels {s} m in {dt} s, what is the speed of the car? {_0} {_u}</p>',
              'format' => FORMAT_HTML
            ),
            array(
              'text' => '<p>If a car travels {s} m in {dt} s, what is the speed of the car? speed = {_0}{_u}</p>',
              'format' => FORMAT_HTML
            ),
        );
        $form->feedback = array(
            array('text' => '', 'format' => FORMAT_HTML),
            array('text' => '', 'format' => FORMAT_HTML),
            array('text' => '', 'format' => FORMAT_HTML),
            array('text' => '', 'format' => FORMAT_HTML),
        );
        $form->partcorrectfb = array(
            array('text' => '', 'format' => FORMAT_HTML),
            array('text' => '', 'format' => FORMAT_HTML),
            array('text' => '', 'format' => FORMAT_HTML),
            array('text' => '', 'format' => FORMAT_HTML),
        );
        $form->partpartiallycorrectfb = array(
            array('text' => '', 'format' => FORMAT_HTML),
            array('text' => '', 'format' => FORMAT_HTML),
            array('text' => '', 'format' => FORMAT_HTML),
            array('text' => '', 'format' => FORMAT_HTML),
        );
        $form->partincorrectfb = array(
            array('text' => '', 'format' => FORMAT_HTML),
            array('text' => '', 'format' => FORMAT_HTML),
            array('text' => '', 'format' => FORMAT_HTML),
            array('text' => '', 'format' => FORMAT_HTML),
        );
        $form->correctfeedback = array('text' => '', 'format' => FORMAT_HTML);
        $form->partiallycorrectfeedback = array('text' => '', 'format' => FORMAT_HTML);
        $form->shownumcorrect = '0';
        $form->incorrectfeedback = array('text' => '', 'format' => FORMAT_HTML);
        $form->numhints = 2;
        $form->hint = array(
            array('text' => '', 'format' => FORMAT_HTML),
            array('text' => '', 'format' => FORMAT_HTML),
        );
        $form->hintclearwrong = array('0', '0');
        $form->hintshownumcorrect = array('0', '0');
        return $form;
    }

    /**
     * @return qtype_formulas_question with a (radio button) multichoice answer.
     */
    public static function make_formulas_question_testmc() {
        $q = self::make_a_formulas_question();

        $q->name = 'test-5';
        $q->questiontext = '<p>This question has a multichoice answer.</p>';
        $q->varsglobal = 'mychoices=["Dog","Cat","Bird","Fish"];';
        $q->penalty = 0.3; // Non-zero and not the default.
        $q->textfragments = array(0 => '<p>This question has a multichoice answer.</p>',
                                  1 => '');
        $q->numpart = 1;
        $q->defaultmark = 2;
        $p = self::make_a_formulas_part();
        $p->id = 14;
        $p->answermark = 1;
        $p->answer = '1';
        $p->answernotunique = '1';
        $p->subqtext = '{_0:mychoices:MC}';
        $q->parts[0] = $p;

        return $q;
    }

    /**
     * Gets the question form data for the MC formulas question
     * @return stdClass
     */
    public function get_formulas_question_form_data_testmc() {
        $form = new stdClass();

        $form->name = 'test-5';
        $form->noanswers = 1;
        $form->answer = array('1');
        $form->answernotunique = array('1');
        $form->answermark = array(1);
        $form->answertype = array(0);
        $form->correctness = array('_relerr < 0.01');
        $form->numbox = array(1);
        $form->placeholder = array('');
        $form->vars1 = array('');
        $form->vars2 = array('');
        $form->ruleid = array(1);
        $form->unitpenalty = array(1);
        $form->postunit = array('');
        $form->otherrule = array('');
        $form->subqtext = array(
            array('text' => '{_0:mychoices:MC}', 'format' => FORMAT_HTML)
        );
        $form->feedback = array(
            array('text' => '', 'format' => FORMAT_HTML)
        );
        $form->partcorrectfb = array(
            array('text' => 'Your answer is correct.', 'format' => FORMAT_HTML)
        );
        $form->partpartiallycorrectfb = array(
            array('text' => 'Your answer is partially correct.', 'format' => FORMAT_HTML)
        );
        $form->partincorrectfb = array(
            array('text' => 'Your answer is incorrect.', 'format' => FORMAT_HTML)
        );
        $form->questiontext = array('text' => '<p>This question has a multichoice answer.</p>',
                'format' => FORMAT_HTML);
        $form->generalfeedback = array('text' => '', 'format' => FORMAT_HTML);
        $form->defaultmark = 2;
        $form->penalty = 0.3;
        $form->varsrandom = '';
        $form->varsglobal = 'mychoices=["Dog","Cat","Bird","Fish"];';
        $form->answernumbering = '';
        $form->globalunitpenalty = 1;
        $form->globalruleid = 1;
        $form->correctfeedback = array('text' => '', 'format' => FORMAT_HTML);
        $form->partiallycorrectfeedback = array('text' => '', 'format' => FORMAT_HTML);
        $form->shownumcorrect = '0';
        $form->incorrectfeedback = array('text' => '', 'format' => FORMAT_HTML);
        $form->numhints = 0;
        return $form;
    }

    /**
     * @return qtype_formulas_question with a dropdown multichoice answer.
     */
    public static function make_formulas_question_testmce() {
        $q = self::make_a_formulas_question();

        $q->name = 'test-5';
        $q->questiontext = '<p>This question has a multichoice answer.</p>';
        $q->varsglobal = 'mychoices=["Dog","Cat","Bird","Fish"];';
        $q->penalty = 0.3; // Non-zero and not the default.
        $q->textfragments = array(0 => '<p>This question has a multichoice answer.</p>',
                                  1 => '');
        $q->numpart = 1;
        $q->defaultmark = 2;
        $p = self::make_a_formulas_part();
        $p->id = 14;
        $p->answermark = 1;
        $p->answer = '1';
        $p->answernotunique = '1';
        $p->subqtext = '{_0:mychoices:MCE}';
        $q->parts[0] = $p;

        return $q;
    }

    /**
     * Gets the question form data for the MCE test formulas question
     * @return stdClass
     */
    public function get_formulas_question_form_data_testmce() {
        $form = new stdClass();

        $form->name = 'test-5';
        $form->noanswers = 1;
        $form->answer = array('1');
        $form->answernotunique = array('1');
        $form->answermark = array(1);
        $form->answertype = array(0);
        $form->correctness = array('_relerr < 0.01');
        $form->numbox = array(1);
        $form->placeholder = array('');
        $form->vars1 = array('');
        $form->vars2 = array('');
        $form->ruleid = array(1);
        $form->unitpenalty = array(1);
        $form->postunit = array('');
        $form->otherrule = array('');
        $form->subqtext = array(
            array('text' => '{_0:mychoices:MCE}', 'format' => FORMAT_HTML)
        );
        $form->feedback = array(
            array('text' => '', 'format' => FORMAT_HTML)
        );
        $form->partcorrectfb = array(
            array('text' => 'Your answer is correct.', 'format' => FORMAT_HTML)
        );
        $form->partpartiallycorrectfb = array(
            array('text' => 'Your answer is partially correct.', 'format' => FORMAT_HTML)
        );
        $form->partincorrectfb = array(
            array('text' => 'Your answer is incorrect.', 'format' => FORMAT_HTML)
        );
        $form->questiontext = array('text' => '<p>This question has a multichoice answer.</p>',
                'format' => FORMAT_HTML);
        $form->generalfeedback = array('text' => '', 'format' => FORMAT_HTML);
        $form->defaultmark = 2;
        $form->penalty = 0.3;
        $form->varsrandom = '';
        $form->varsglobal = 'mychoices=["Dog","Cat","Bird","Fish"];';
        $form->answernumbering = 'abc';
        $form->globalunitpenalty = 1;
        $form->globalruleid = 1;
        $form->correctfeedback = array('text' => '', 'format' => FORMAT_HTML);
        $form->partiallycorrectfeedback = array('text' => '', 'format' => FORMAT_HTML);
        $form->shownumcorrect = '0';
        $form->incorrectfeedback = array('text' => '', 'format' => FORMAT_HTML);
        $form->numhints = 0;
        return $form;
    }

    /**
     * @return qtype_formulas_question with two dropdown multichoice answers in separate parts.
     */
    public static function make_formulas_question_testmcetwoparts() {
        $q = self::make_a_formulas_question();

        $q->name = 'testmcetwoparts';
        $q->questiontext = '<p>This question has two parts with a multichoice answer in each of them.</p>';
        $q->varsglobal = 'choices1=["Dog","Cat","Bird","Fish"];';
        $q->varsglobal .= 'choices2=["Red","Blue","Green","Yellow"];';
        $q->penalty = 0.3; // Non-zero and not the default.
        $q->textfragments = array(0 => '<p>This question has two parts with a multichoice answer in each of them.</p>',
                                  1 => '{_0:choices1:MCE}',
                                  2 => '{_0:choices2:MCE}'
                            );
        $q->numpart = 1;
        $q->defaultmark = 2;
        $p1 = self::make_a_formulas_part();
        $p1->id = 14;
        $p1->answermark = 1;
        $p1->answer = '1';
        $p1->answernotunique = '1';
        $p1->subqtext = '{_0:choices1:MCE}';
        $q->parts[0] = $p1;
        $p2 = self::make_a_formulas_part();
        $p2->id = 15;
        $p2->answermark = 1;
        $p2->answer = '1';
        $p2->answernotunique = '1';
        $p2->subqtext = '{_0:choices2:MCE}';
        $q->parts[1] = $p2;

        return $q;
    }

    /**
     * Gets the question form data for the double MCE test formulas question
     * @return stdClass
     */
    public function get_formulas_question_form_data_testmcetwoparts() {
        $form = new stdClass();

        $form->name = 'testmcetwoparts';
        $form->noanswers = 2;
        $form->answer = array('1', '1');
        $form->answernotunique = array('1', '1');
        $form->answermark = array(1, 1);
        $form->answertype = array(0, 0);
        $form->correctness = array('_relerr < 0.01', '_relerr < 0.01');
        $form->numbox = array(1, 1);
        $form->placeholder = array('', '');
        $form->vars1 = array('', '');
        $form->vars2 = array('', '');
        $form->ruleid = array(1, 1);
        $form->unitpenalty = array(1, 1);
        $form->postunit = array('', '');
        $form->otherrule = array('', '');
        $form->subqtext = array(
            0 => array('text' => '{_0:choices1:MCE}', 'format' => FORMAT_HTML),
            1 => array('text' => '{_0:choices2:MCE}', 'format' => FORMAT_HTML)
        );
        $form->feedback = array(
            0 => array('text' => '', 'format' => FORMAT_HTML),
            1 => array('text' => '', 'format' => FORMAT_HTML)
        );
        $form->partcorrectfb = array(
            0 => array('text' => 'Your first answer is correct.', 'format' => FORMAT_HTML),
            1 => array('text' => 'Your second answer is correct.', 'format' => FORMAT_HTML)
        );
        $form->partpartiallycorrectfb = array(
            0 => array('text' => 'Your answer is partially correct.', 'format' => FORMAT_HTML),
            1 => array('text' => 'Your answer is partially correct.', 'format' => FORMAT_HTML)
        );
        $form->partincorrectfb = array(
            0 => array('text' => 'Your answer is incorrect.', 'format' => FORMAT_HTML),
            1 => array('text' => 'Your answer is incorrect.', 'format' => FORMAT_HTML)
        );
        $form->questiontext = array('text' => '<p>This question has a multichoice answer.</p>',
                'format' => FORMAT_HTML);
        $form->generalfeedback = array('text' => '', 'format' => FORMAT_HTML);
        $form->defaultmark = 2;
        $form->penalty = 0.3;
        $form->varsrandom = '';
        $form->varsglobal = 'choices1=["Dog","Cat","Bird","Fish"];';
        $form->varsglobal .= 'choices2=["Red","Blue","Green","Yellow"];';
        $form->answernumbering = '';
        $form->globalunitpenalty = 1;
        $form->globalruleid = 1;
        $form->correctfeedback = array('text' => '', 'format' => FORMAT_HTML);
        $form->partiallycorrectfeedback = array('text' => '', 'format' => FORMAT_HTML);
        $form->shownumcorrect = '0';
        $form->incorrectfeedback = array('text' => '', 'format' => FORMAT_HTML);
        $form->numhints = 0;
        return $form;
    }

    /**
     * @return qtype_formulas_question with two radio button multichoice answers in separate parts.
     */
    public static function make_formulas_question_testmctwoparts() {
        $q = self::make_a_formulas_question();

        $q->name = 'testmcetwoparts';
        $q->questiontext = '<p>This question has two parts with a multichoice answer in each of them.</p>';
        $q->varsglobal = 'choices1=["Dog","Cat","Bird","Fish"];';
        $q->varsglobal .= 'choices2=["Red","Blue","Green","Yellow"];';
        $q->penalty = 0.3; // Non-zero and not the default.
        $q->textfragments = array(0 => '<p>This question has two parts with a multichoice answer in each of them.</p>',
                                  1 => 'Part 1 -- {_0:choices1:MC}',
                                  2 => 'Part 2 -- {_0:choices2:MC}'
                            );
        $q->numpart = 1;
        $q->defaultmark = 2;
        $p1 = self::make_a_formulas_part();
        $p1->id = 14;
        $p1->answermark = 1;
        $p1->answer = '1';
        $p1->answernotunique = '1';
        $p1->subqtext = 'Part 1 -- {_0:choices1:MCE}';
        $q->parts[0] = $p1;
        $p2 = self::make_a_formulas_part();
        $p2->id = 15;
        $p2->answermark = 1;
        $p2->answer = '1';
        $p2->answernotunique = '1';
        $p2->subqtext = 'Part 2 -- {_0:choices2:MCE}';
        $q->parts[1] = $p2;

        return $q;
    }

    /**
     * Gets the question form data for the double MC test formulas question
     * @return stdClass
     */
    public function get_formulas_question_form_data_testmctwoparts() {
        $form = new stdClass();

        $form->name = 'testmcetwoparts';
        $form->noanswers = 2;
        $form->answer = array('1', '1');
        $form->answernotunique = array('1', '1');
        $form->answermark = array(1, 1);
        $form->answertype = array(0, 0);
        $form->correctness = array('_relerr < 0.01', '_relerr < 0.01');
        $form->numbox = array(1, 1);
        $form->placeholder = array('', '');
        $form->vars1 = array('', '');
        $form->vars2 = array('', '');
        $form->ruleid = array(1, 1);
        $form->unitpenalty = array(1, 1);
        $form->postunit = array('', '');
        $form->otherrule = array('', '');
        $form->subqtext = array(
            0 => array('text' => 'Part 1 -- {_0:choices1:MC}', 'format' => FORMAT_HTML),
            1 => array('text' => 'Part 2 -- {_0:choices2:MC}', 'format' => FORMAT_HTML)
        );
        $form->feedback = array(
            0 => array('text' => '', 'format' => FORMAT_HTML),
            1 => array('text' => '', 'format' => FORMAT_HTML)
        );
        $form->partcorrectfb = array(
            0 => array('text' => 'Your first answer is correct.', 'format' => FORMAT_HTML),
            1 => array('text' => 'Your second answer is correct.', 'format' => FORMAT_HTML)
        );
        $form->partpartiallycorrectfb = array(
            0 => array('text' => 'Your answer is partially correct.', 'format' => FORMAT_HTML),
            1 => array('text' => 'Your answer is partially correct.', 'format' => FORMAT_HTML)
        );
        $form->partincorrectfb = array(
            0 => array('text' => 'Your answer is incorrect.', 'format' => FORMAT_HTML),
            1 => array('text' => 'Your answer is incorrect.', 'format' => FORMAT_HTML)
        );
        $form->questiontext = array('text' => '<p>This question has two parts with a multichoice answer in each of them.</p>',
                'format' => FORMAT_HTML);
        $form->generalfeedback = array('text' => '', 'format' => FORMAT_HTML);
        $form->defaultmark = 2;
        $form->penalty = 0.3;
        $form->varsrandom = '';
        $form->varsglobal = 'choices1=["Dog","Cat","Bird","Fish"];';
        $form->varsglobal .= 'choices2=["Red","Blue","Green","Yellow"];';
        $form->answernumbering = '';
        $form->globalunitpenalty = 1;
        $form->globalruleid = 1;
        $form->correctfeedback = array('text' => '', 'format' => FORMAT_HTML);
        $form->partiallycorrectfeedback = array('text' => '', 'format' => FORMAT_HTML);
        $form->shownumcorrect = '0';
        $form->incorrectfeedback = array('text' => '', 'format' => FORMAT_HTML);
        $form->numhints = 0;
        return $form;
    }

    /**
     * @return qtype_formulas_question with two parts and two numbers in each of them.
     */
    public static function make_formulas_question_testtwoandtwo() {
        $q = self::make_a_formulas_question();

        $q->name = 'testtwoandtwo';
        $q->questiontext = '<p>This question has two parts with two numbers in each of them.</p>';
        $q->varsglobal = '';
        $q->penalty = 0.3; // Non-zero and not the default.
        $q->textfragments = array(0 => '<p>This question has two parts with two numbers in each of them.</p>',
                                  1 => 'Part 1 -- {_0} -- {_1}',
                                  2 => 'Part 2 -- {_0} -- {_1}'
                            );
        $q->numpart = 1;
        $q->defaultmark = 2;
        $p1 = self::make_a_formulas_part();
        $p1->id = 14;
        $p1->answermark = 1;
        $p1->answer = ['1', '2'];
        $p1->answernotunique = '1';
        $p1->subqtext = 'Part 1 -- {_0} -- {_1}';
        $q->parts[0] = $p1;
        $p2 = self::make_a_formulas_part();
        $p2->id = 15;
        $p2->answermark = 1;
        $p2->answer = ['3', '4'];
        $p2->answernotunique = '1';
        $p2->subqtext = 'Part 2 -- {_0} -- {_1}';
        $q->parts[1] = $p2;

        return $q;
    }

    /**
     * Gets the question form data for the 2x2 number test formulas question
     * @return stdClass
     */
    public function get_formulas_question_form_data_testtwoandtwo() {
        $form = new stdClass();

        $form->name = 'testtwoandtwo';
        $form->noanswers = 4;
        $form->answer = array(0 => '[1, 2]', 1 => '[3, 4]');
        $form->answernotunique = array('1', '1');
        $form->answermark = array(0 => 1, 1 => 1);
        $form->answertype = array(0, 0);
        $form->correctness = array('_relerr < 0.01', '_relerr < 0.01');
        $form->numbox = array(1, 1);
        $form->placeholder = array('', '');
        $form->vars1 = array('', '');
        $form->vars2 = array('', '');
        $form->ruleid = array(1, 1);
        $form->unitpenalty = array(1, 1);
        $form->postunit = array('', '');
        $form->otherrule = array('', '');
        $form->subqtext = array(
            0 => array('text' => 'Part 1 -- {_0} -- {_1}', 'format' => FORMAT_HTML),
            1 => array('text' => 'Part 2 -- {_0} -- {_1}', 'format' => FORMAT_HTML)
        );
        $form->feedback = array(
            0 => array('text' => '', 'format' => FORMAT_HTML),
            1 => array('text' => '', 'format' => FORMAT_HTML)
        );
        $form->partcorrectfb = array(
            0 => array('text' => 'Your answers in part 1 are correct.', 'format' => FORMAT_HTML),
            1 => array('text' => 'Your answers in part 2 are correct.', 'format' => FORMAT_HTML)
        );
        $form->partpartiallycorrectfb = array(
            0 => array('text' => 'Your answer is partially correct.', 'format' => FORMAT_HTML),
            1 => array('text' => 'Your answer is partially correct.', 'format' => FORMAT_HTML)
        );
        $form->partincorrectfb = array(
            0 => array('text' => 'Your answer is incorrect.', 'format' => FORMAT_HTML),
            1 => array('text' => 'Your answer is incorrect.', 'format' => FORMAT_HTML)
        );
        $form->questiontext = array('text' => '<p>This question has two parts with two numbers in each of them.</p>',
                'format' => FORMAT_HTML);
        $form->generalfeedback = array('text' => '', 'format' => FORMAT_HTML);
        $form->defaultmark = 2;
        $form->penalty = 0.3;
        $form->varsrandom = '';
        $form->varsglobal = '';
        $form->answernumbering = '';
        $form->globalunitpenalty = 1;
        $form->globalruleid = 1;
        $form->correctfeedback = array('text' => '', 'format' => FORMAT_HTML);
        $form->partiallycorrectfeedback = array('text' => '', 'format' => FORMAT_HTML);
        $form->shownumcorrect = '0';
        $form->incorrectfeedback = array('text' => '', 'format' => FORMAT_HTML);
        $form->numhints = 0;
        return $form;
    }
}
