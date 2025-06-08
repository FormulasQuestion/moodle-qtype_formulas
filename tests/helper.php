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
 * Test helper class for the formulas question type.
 *
 * @package    qtype_formulas
 * @copyright  2012 Jean-Michel Védrine
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class qtype_formulas_test_helper extends question_test_helper {
    /** @var string */
    const DEFAULT_CORRECT_FEEDBACK = '<p>Correct answer, well done.</p>';

    /** @var string */
    const DEFAULT_PARTIALLYCORRECT_FEEDBACK = '<p>Your answer is partially correct.</p>';

    /** @var string */
    const DEFAULT_INCORRECT_FEEDBACK = '<p>Incorrect answer.</p>';

    /**
     * Return a list of possible test questions.
     *
     * @return array
     */
    public function get_test_questions(): array {
        return [
            // Minimal formulas question: one part, not randomised, answer = 5.
            'testsinglenum',
             // Formulas question with algebraic answer.
            'testalgebraic',
             // Minimal formulas question: one part, two numbers, answer = 2 and 3.
            'testtwonums',
            // Formulas question with one part, one number with unit (combined), answer = 5 m/s.
            'testsinglenumunit',
            // Formulas question with one part, one number with unit (separate), answer = 5 m/s.
            'testsinglenumunitsep',
            // Formulas question with one part, not randomized, answer = 0, used to test for problems with 0 as answer.
            'testzero',
            // Formulas question with one part, not randomized, drowdown multichoice answer.
            'testmce',
            // Formulas question with one part, not randomized, radio button multichoice answer.
            'testmc',
            // Formulas question with 3 parts, not randomised, answers = 5, 6, 7.
            'testthreeparts',
            // Formulas question with 4 parts, not randomised, separate and combined unit fields.
            'testmethodsinparts',
            // Formulas question with 2 parts, each with a drowdown multichoice answer.
            'testmcetwoparts',
            // Formulas question with 2 parts, each with a radio button multichoice answer.
            'testmctwoparts',
            // Formulas question with 2 parts, each one with 2 numbers.
            'testtwoandtwo',
            // Formulas question with 4 parts, randomised, separate and combined unit fields.
            'test4',
        ];
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
        $q->answernumbering = 'abc';
        $q->penalty = 0.2;
        $q->generalfeedback = '';
        test_question_maker::set_standard_combined_feedback_fields($q);
        $q->shownumcorrect = 1;
        $q->numparts = 0;   // This is of course invalid but should be changed by all tests.
        $q->parts = [];
        return $q;
    }

    /**
     * Create a qtype_formulas_part object for testing.
     *
     * @return qtype_formulas_part
     */
    public static function make_a_formulas_part(): qtype_formulas_part {
        question_bank::load_question_definition_classes('formulas');

        $p = new qtype_formulas_part();
        $p->id = 0;
        $p->placeholder = '';
        $p->answermark = 1;
        $p->answertype = '0';
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
        $p->subqtextformat = FORMAT_HTML;
        $p->feedback = '';
        $p->feedbackformat = FORMAT_HTML;
        $p->partcorrectfb = self::DEFAULT_CORRECT_FEEDBACK;
        $p->partcorrectfbformat = FORMAT_HTML;
        $p->partpartiallycorrectfb = self::DEFAULT_PARTIALLYCORRECT_FEEDBACK;
        $p->partpartiallycorrectfbformat = FORMAT_HTML;
        $p->partincorrectfb = self::DEFAULT_INCORRECT_FEEDBACK;
        $p->partincorrectfbformat = FORMAT_HTML;
        $p->partindex = 0;

        return $p;
    }

    /**
     * Create a single-part test question with answer type algebraic formula.
     *
     * @return qtype_formulas_question
     */
    public static function make_formulas_question_testalgebraic() {
        $q = self::make_a_formulas_question();

        $q->name = 'test-algebraic';
        $q->questiontext = '<p>This is a minimal question. The answer is "5*x^2".</p>';

        $q->penalty = 0.2;
        $q->textfragments = [0 => '<p>This is a minimal question. The answer is "5*x^2".</p>',
                                  1 => ''];
        $q->numparts = 1;
        $q->defaultmark = 1;
        $q->varsglobal = 'a=5;';
        $q->generalfeedback = '';
        $p = self::make_a_formulas_part();
        $p->id = 1;
        $p->placeholder = '';
        $p->answermark = 1;
        $p->vars1 = 'x={1:10}';
        $p->answer = '"a*x^2"';
        $p->answertype = strval(qtype_formulas::ANSWER_TYPE_ALGEBRAIC);
        $p->answernotunique = '1';
        $p->correctness = '_err < 0.01';
        $p->subqtext = '';
        $p->partcorrectfb = 'Your answer is correct.';
        $p->partpartiallycorrectfb = 'Your answer is partially correct.';
        $p->partincorrectfb = 'Your answer is incorrect.';
        $q->parts[0] = $p;
        return $q;
    }

    /**
     * Gets the question form data for the algebraic formulas question
     * @return stdClass
     */
    public function get_formulas_question_form_data_testalgebraic() {
        $form = new stdClass();

        $form->name = 'test-algebraic';
        $form->noanswers = 1;
        $form->answer = ['"a*x^2"'];
        $form->answernotunique = ['1'];
        $form->answermark = [1];
        $form->answertype = [strval(qtype_formulas::ANSWER_TYPE_ALGEBRAIC)];
        $form->correctness = ['_err < 0.01'];
        $form->numbox = [1];
        $form->placeholder = [''];
        $form->vars1 = ['x={1:10}'];
        $form->vars2 = [''];
        $form->postunit = [''];
        $form->otherrule = [''];
        $form->subqtext = [['text' => '', 'format' => FORMAT_HTML]];
        $form->feedback = [['text' => '', 'format' => FORMAT_HTML]];
        $form->partcorrectfb = [['text' => 'Your answer is correct.', 'format' => FORMAT_HTML]];
        $form->partpartiallycorrectfb = [['text' => 'Your answer is partially correct.', 'format' => FORMAT_HTML]];
        $form->partincorrectfb = [['text' => 'Your answer is incorrect.', 'format' => FORMAT_HTML]];
        $form->questiontext = [
            'text' => '<p>This is a minimal question. The answer is "5*x^2".</p>',
            'format' => FORMAT_HTML,
        ];
        $form->generalfeedback = ['text' => '', 'format' => FORMAT_HTML];
        $form->defaultmark = 2;
        $form->penalty = 0.2;
        $form->varsrandom = '';
        $form->varsglobal = 'a=5;';
        $form->answernumbering = 'abc';
        $form->globalunitpenalty = 1;
        $form->globalruleid = 1;
        $form->correctfeedback = ['text' => test_question_maker::STANDARD_OVERALL_CORRECT_FEEDBACK, 'format' => FORMAT_HTML];
        $form->partiallycorrectfeedback = [
            'text' => test_question_maker::STANDARD_OVERALL_PARTIALLYCORRECT_FEEDBACK,
            'format' => FORMAT_HTML,
        ];
        $form->incorrectfeedback = ['text' => test_question_maker::STANDARD_OVERALL_INCORRECT_FEEDBACK, 'format' => FORMAT_HTML];
        $form->shownumcorrect = '1';
        return $form;
    }

    /**
     * Create a single-part test question with answer type number.
     *
     * @return qtype_formulas_question
     */
    public static function make_formulas_question_testsinglenum() {
        $q = self::make_a_formulas_question();

        $q->name = 'test-singlenum';
        $q->questiontext = '<p>This is a minimal question. The answer is 5.</p>';

        $q->penalty = 0.3; // Non-zero and not the default.
        $q->textfragments = [0 => '<p>This is a minimal question. The answer is 5.</p>',
                                  1 => ''];
        $q->numparts = 1;
        $q->defaultmark = 2;
        $q->generalfeedback = '';
        $p = self::make_a_formulas_part();
        $p->questionid = $q->id;
        $p->id = 14;
        $p->placeholder = '';
        $p->answermark = 2;
        $p->answer = '5';
        $p->answernotunique = '1';
        $p->subqtext = '';
        $q->parts[0] = $p;

        $q->hints = [
            new question_hint_with_parts(101, 'Hint 1.', FORMAT_HTML, 1, 0),
            new question_hint_with_parts(102, 'Hint 2.', FORMAT_HTML, 1, 1),
        ];
        return $q;
    }

    /**
     * Gets the question form data for the singlenum formulas question
     *
     * @return stdClass
     */
    public function get_formulas_question_form_data_testsinglenum(): stdClass {
        $form = new stdClass();

        $form->name = 'test-singlenum';
        $form->noanswers = 1;
        $form->answer = ['5'];
        $form->answernotunique = ['1'];
        $form->answermark = [2];
        $form->answertype = ['0'];
        $form->correctness = ['_relerr < 0.01'];
        $form->numbox = [1];
        $form->placeholder = [''];
        $form->vars1 = [''];
        $form->vars2 = [''];
        $form->postunit = [''];
        $form->otherrule = [''];
        $form->subqtext = [
            ['text' => '', 'format' => FORMAT_HTML],
        ];
        $form->feedback = [
            ['text' => '', 'format' => FORMAT_HTML],
        ];
        $form->partcorrectfb = [
            ['text' => self::DEFAULT_CORRECT_FEEDBACK, 'format' => FORMAT_HTML],
        ];
        $form->partpartiallycorrectfb = [
            ['text' => self::DEFAULT_PARTIALLYCORRECT_FEEDBACK, 'format' => FORMAT_HTML],
        ];
        $form->partincorrectfb = [
            ['text' => self::DEFAULT_INCORRECT_FEEDBACK, 'format' => FORMAT_HTML],
        ];
        $form->questiontext = ['text' => '<p>This is a minimal question. The answer is 5.</p>',
                'format' => FORMAT_HTML];
        $form->generalfeedback = ['text' => '', 'format' => FORMAT_HTML];
        $form->defaultmark = 2;
        $form->penalty = 0.3;
        $form->varsrandom = '';
        $form->varsglobal = '';
        $form->answernumbering = 'abc';
        $form->globalunitpenalty = 1;
        $form->globalruleid = 1;
        $form->correctfeedback = ['text' => test_question_maker::STANDARD_OVERALL_CORRECT_FEEDBACK, 'format' => FORMAT_HTML];
        $form->partiallycorrectfeedback = [
            'text' => test_question_maker::STANDARD_OVERALL_PARTIALLYCORRECT_FEEDBACK,
            'format' => FORMAT_HTML,
        ];
        $form->incorrectfeedback = ['text' => test_question_maker::STANDARD_OVERALL_INCORRECT_FEEDBACK, 'format' => FORMAT_HTML];
        $form->shownumcorrect = '1';
        $form->hint = [
            ['text' => 'Hint 1.', 'format' => FORMAT_HTML],
            ['text' => 'Hint 2.', 'format' => FORMAT_HTML],
        ];
        $form->hintclearwrong = [0, 1];
        $form->hintshownumcorrect = [1, 1];
        return $form;
    }

    /**
     * Return question data for a single-part question with answer type number.
     *
     * @return stdClass
     */
    public static function get_formulas_question_data_testsinglenum(): stdClass {
        $qdata = new stdClass();
        test_question_maker::initialise_question_data($qdata);

        $qdata->qtype = 'formulas';
        $qdata->name = 'test-singlenum';
        $qdata->questiontext = '<p>This is a minimal question. The answer is 5.</p>';
        $qdata->generalfeedback = '';
        $qdata->defaultmark = 2;
        $qdata->penalty = 0.3;

        $qdata->options = new stdClass();
        $qdata->contextid = context_system::instance()->id;
        $qdata->options->varsrandom = '';
        $qdata->options->varsglobal = '';
        $qdata->options->answernumbering = 'abc';
        $qdata->options->shownumcorrect = 1;
        $qdata->options->correctfeedback =
                test_question_maker::STANDARD_OVERALL_CORRECT_FEEDBACK;
        $qdata->options->correctfeedbackformat = FORMAT_HTML;
        $qdata->options->partiallycorrectfeedback =
                test_question_maker::STANDARD_OVERALL_PARTIALLYCORRECT_FEEDBACK;
        $qdata->options->partiallycorrectfeedbackformat = FORMAT_HTML;
        $qdata->options->incorrectfeedback =
                test_question_maker::STANDARD_OVERALL_INCORRECT_FEEDBACK;
        $qdata->options->incorrectfeedbackformat = FORMAT_HTML;

        $qdata->options->answers = [
            14 => (object) [
                'id' => 14,
                'questionid' => $qdata->id,
                'placeholder' => '',
                'answermark' => 2,
                'answertype' => '0',
                'numbox' => 1,
                'vars1' => '',
                'vars2' => '',
                'answer' => '5',
                'answernotunique' => '1',
                'correctness' => '_relerr < 0.01',
                'unitpenalty' => 1,
                'postunit' => '',
                'ruleid' => 1,
                'otherrule' => '',
                'subqtext' => '',
                'subqtextformat' => FORMAT_HTML,
                'feedback' => '',
                'feedbackformat' => FORMAT_HTML,
                'partcorrectfb' => self::DEFAULT_CORRECT_FEEDBACK,
                'partcorrectfbformat' => FORMAT_HTML,
                'partpartiallycorrectfb' => self::DEFAULT_PARTIALLYCORRECT_FEEDBACK,
                'partpartiallycorrectfbformat' => FORMAT_HTML,
                'partincorrectfb' => self::DEFAULT_INCORRECT_FEEDBACK,
                'partincorrectfbformat' => FORMAT_HTML,
                'partindex' => 0,
            ],
        ];

        $qdata->options->numparts = 1;

        $qdata->hints = [
            (object) [
                'id' => '101',
                'hint' => 'Hint 1.',
                'hintformat' => FORMAT_HTML,
                'shownumcorrect' => 1,
                'clearwrong' => 0,
                'options' => 0,
            ],
            (object) [
                'id' => '102',
                'hint' => 'Hint 2.',
                'hintformat' => FORMAT_HTML,
                'shownumcorrect' => 1,
                'clearwrong' => 1,
                'options' => 1,
            ],
        ];

        return $qdata;
    }

    /**
     * Return Formulas question with one part, one number with unit (combined), answer = 5 m/s.
     *
     * @return qtype_formulas_question
     */
    public static function make_formulas_question_testsinglenumunit(): qtype_formulas_question {
        $q = self::make_a_formulas_question();

        $q->name = 'test-singlenumunit';
        $q->questiontext = '<p>One part, one number plus unit, answer is 5 m/s</p>';

        $q->penalty = 0.3;
        $q->textfragments = [0 => '<p>One part, one number plus unit, answer is 5 m/s</p>',
                                  1 => ''];
        $q->numparts = 1;
        $q->defaultmark = 2;
        $p = self::make_a_formulas_part();
        $p->id = 14;
        $p->questionid = $q->id;
        $p->placeholder = '';
        $p->answermark = 2;
        $p->answer = '5';
        $p->answernotunique = '1';
        $p->postunit = 'm/s';
        $p->subqtext = '{_0}{_u}';

        $q->parts[0] = $p;
        return $q;
    }

    /**
     * Gets the question form data for the singlenum formulas question
     * @return stdClass
     */
    public function get_formulas_question_form_data_testsinglenumunit() {
        $form = new stdClass();

        $form->name = 'test-singlenumunit';
        $form->noanswers = 1;
        $form->answer = ['5'];
        $form->answernotunique = ['1'];
        $form->answermark = [2];
        $form->answertype = ['0'];
        $form->correctness = ['_relerr < 0.01'];
        $form->numbox = [1];
        $form->placeholder = [''];
        $form->vars1 = [''];
        $form->vars2 = [''];
        $form->postunit = ['m/s'];
        $form->otherrule = [''];
        $form->subqtext = [
            ['text' => '{_0}{_u}', 'format' => FORMAT_HTML],
        ];
        $form->feedback = [
            ['text' => '', 'format' => FORMAT_HTML],
        ];
        $form->partcorrectfb = [
            ['text' => '<p>Correct answer, well done.</p>', 'format' => FORMAT_HTML],
        ];
        $form->partpartiallycorrectfb = [
            ['text' => '<p>Your answer is partially correct.</p>', 'format' => FORMAT_HTML],
        ];
        $form->partincorrectfb = [
            ['text' => '<p>Incorrect answer.</p>', 'format' => FORMAT_HTML],
        ];
        $form->questiontext = ['text' => '<p>One part, one number plus unit, answer is 5 m/s</p>',
                'format' => FORMAT_HTML];
        $form->generalfeedback = ['text' => '', 'format' => FORMAT_HTML];
        $form->defaultmark = 2;
        $form->penalty = 0.3;
        $form->varsrandom = '';
        $form->varsglobal = '';
        $form->answernumbering = 'abc';
        $form->globalunitpenalty = 1;
        $form->globalruleid = 1;
        $form->correctfeedback = ['text' => 'Well done!', 'format' => FORMAT_HTML];
        $form->partiallycorrectfeedback = [
            'text' => 'Parts, but only parts, of your response are correct.',
            'format' => FORMAT_HTML,
        ];
        $form->shownumcorrect = '0';
        $form->incorrectfeedback = ['text' => 'That is not right at all.', 'format' => FORMAT_HTML];
        return $form;
    }

    /**
     * Get question data for the testsinglenumunit question.
     *
     * @return stdClass
     */
    public static function get_formulas_question_data_testsinglenumunit(): stdClass {
        $qdata = new stdClass();
        test_question_maker::initialise_question_data($qdata);

        $qdata->qtype = 'formulas';
        $qdata->name = 'test-singlenumunit';
        $qdata->questiontext = '<p>One part, one number plus unit, answer is 5 m/s</p>';
        $qdata->generalfeedback = '';
        $qdata->defaultmark = 2;
        $qdata->penalty = 0.3;

        $qdata->options = new stdClass();
        $qdata->contextid = context_system::instance()->id;
        $qdata->options->varsrandom = '';
        $qdata->options->varsglobal = '';
        $qdata->options->answernumbering = 'abc';
        $qdata->options->shownumcorrect = 1;
        $qdata->options->correctfeedback =
                test_question_maker::STANDARD_OVERALL_CORRECT_FEEDBACK;
        $qdata->options->correctfeedbackformat = FORMAT_HTML;
        $qdata->options->partiallycorrectfeedback =
                test_question_maker::STANDARD_OVERALL_PARTIALLYCORRECT_FEEDBACK;
        $qdata->options->partiallycorrectfeedbackformat = FORMAT_HTML;
        $qdata->options->incorrectfeedback =
                test_question_maker::STANDARD_OVERALL_INCORRECT_FEEDBACK;
        $qdata->options->incorrectfeedbackformat = FORMAT_HTML;

        $qdata->options->answers = [
            14 => (object) [
                'id' => 14,
                'questionid' => $qdata->id,
                'placeholder' => '',
                'answermark' => 2,
                'answertype' => '0',
                'numbox' => 1,
                'vars1' => '',
                'vars2' => '',
                'answer' => '5',
                'answernotunique' => '1',
                'correctness' => '_relerr < 0.01',
                'unitpenalty' => 1,
                'postunit' => 'm/s',
                'ruleid' => 1,
                'otherrule' => '',
                'subqtext' => '{_0}{_u}',
                'subqtextformat' => FORMAT_HTML,
                'feedback' => '',
                'feedbackformat' => FORMAT_HTML,
                'partcorrectfb' => self::DEFAULT_CORRECT_FEEDBACK,
                'partcorrectfbformat' => FORMAT_HTML,
                'partpartiallycorrectfb' => self::DEFAULT_PARTIALLYCORRECT_FEEDBACK,
                'partpartiallycorrectfbformat' => FORMAT_HTML,
                'partincorrectfb' => self::DEFAULT_INCORRECT_FEEDBACK,
                'partincorrectfbformat' => FORMAT_HTML,
                'partindex' => 0,
            ],
        ];

        $qdata->options->numparts = 1;

        return $qdata;
    }

    /**
     * Return Formulas question with one part, one number with unit (separate), answer = 5 m/s.
     *
     * @return qtype_formulas_question
     */
    public static function make_formulas_question_testsinglenumunitsep(): qtype_formulas_question {
        $q = self::make_a_formulas_question();

        $q->name = 'test-singlenumunitsep';
        $q->questiontext = '<p>One part, one number plus unit, answer is 5 m/s</p>';

        $q->penalty = 0.3;
        $q->textfragments = [
            0 => '<p>One part, one number plus unit, answer is 5 m/s</p>',
            1 => '',
        ];
        $q->numparts = 1;
        $q->defaultmark = 2;

        $p = self::make_a_formulas_part();
        $p->id = 14;
        $p->questionid = $q->id;
        $p->placeholder = '';
        $p->answermark = 2;
        $p->answer = '5';
        $p->answernotunique = '1';
        $p->postunit = 'm/s';
        $p->subqtext = '{_0} {_u}';
        $p->partcorrectfb = self::DEFAULT_CORRECT_FEEDBACK;
        $p->partpartiallycorrectfb = self::DEFAULT_PARTIALLYCORRECT_FEEDBACK;
        $p->partincorrectfb = self::DEFAULT_INCORRECT_FEEDBACK;
        $q->parts[0] = $p;
        return $q;
    }

    /**
     * Gets the question form data for the singlenum formulas question
     * @return stdClass
     */
    public function get_formulas_question_form_data_testsinglenumunitsep() {
        $form = new stdClass();

        $form->name = 'test-singlenumunitsep';
        $form->noanswers = 1;
        $form->answer = ['5'];
        $form->answernotunique = ['1'];
        $form->answermark = [2];
        $form->answertype = ['0'];
        $form->correctness = ['_relerr < 0.01'];
        $form->numbox = [1];
        $form->placeholder = [''];
        $form->vars1 = [''];
        $form->vars2 = [''];
        $form->postunit = ['m/s'];
        $form->otherrule = [''];
        $form->subqtext = [
            ['text' => '{_0} {_u}', 'format' => FORMAT_HTML],
        ];
        $form->feedback = [
            ['text' => '', 'format' => FORMAT_HTML],
        ];
        $form->partcorrectfb = [
            ['text' => self::DEFAULT_CORRECT_FEEDBACK, 'format' => FORMAT_HTML],
        ];
        $form->partpartiallycorrectfb = [
            ['text' => self::DEFAULT_PARTIALLYCORRECT_FEEDBACK, 'format' => FORMAT_HTML],
        ];
        $form->partincorrectfb = [
            ['text' => self::DEFAULT_INCORRECT_FEEDBACK, 'format' => FORMAT_HTML],
        ];
        $form->questiontext = [
            'text' => '<p>One part, one number plus unit, answer is 5 m/s</p>',
            'format' => FORMAT_HTML,
        ];
        $form->generalfeedback = ['text' => '', 'format' => FORMAT_HTML];
        $form->defaultmark = 2;
        $form->penalty = 0.3;
        $form->varsrandom = '';
        $form->varsglobal = '';
        $form->answernumbering = 'abc';
        $form->globalunitpenalty = 1;
        $form->globalruleid = 1;
        $form->correctfeedback = ['text' => 'Well done!', 'format' => FORMAT_HTML];
        $form->partiallycorrectfeedback = [
            'text' => 'Parts, but only parts, of your response are correct.',
            'format' => FORMAT_HTML,
        ];
        $form->shownumcorrect = '0';
        $form->incorrectfeedback = ['text' => 'That is not right at all.', 'format' => FORMAT_HTML];
        return $form;
    }

    /**
     * Return question data for the testsinglenumunitsep question.
     *
     * @return stdClass
     */
    public static function get_formulas_question_data_testsinglenumunitsep(): stdClass {
        $qdata = new stdClass();
        test_question_maker::initialise_question_data($qdata);

        $qdata->qtype = 'formulas';
        $qdata->name = 'test-singlenumunitsep';
        $qdata->questiontext = '<p>One part, one number plus unit, answer is 5 m/s</p>';
        $qdata->generalfeedback = '';
        $qdata->defaultmark = 2;
        $qdata->penalty = 0.3;

        $qdata->options = new stdClass();
        $qdata->contextid = context_system::instance()->id;
        $qdata->options->varsrandom = '';
        $qdata->options->varsglobal = '';
        $qdata->options->answernumbering = 'abc';
        $qdata->options->shownumcorrect = 1;
        $qdata->options->correctfeedback = test_question_maker::STANDARD_OVERALL_CORRECT_FEEDBACK;
        $qdata->options->correctfeedbackformat = FORMAT_HTML;
        $qdata->options->partiallycorrectfeedback = test_question_maker::STANDARD_OVERALL_PARTIALLYCORRECT_FEEDBACK;
        $qdata->options->partiallycorrectfeedbackformat = FORMAT_HTML;
        $qdata->options->incorrectfeedback = test_question_maker::STANDARD_OVERALL_INCORRECT_FEEDBACK;
        $qdata->options->incorrectfeedbackformat = FORMAT_HTML;

        $qdata->options->answers = [
            14 => (object)[
                'id' => 14,
                'questionid' => $qdata->id,
                'placeholder' => '',
                'answermark' => 2,
                'answertype' => '0',
                'numbox' => 1,
                'vars1' => '',
                'vars2' => '',
                'answer' => '5',
                'answernotunique' => '1',
                'correctness' => '_relerr < 0.01',
                'unitpenalty' => 1,
                'postunit' => 'm/s',
                'ruleid' => 1,
                'otherrule' => '',
                'subqtext' => '{_0} {_u}',
                'subqtextformat' => FORMAT_HTML,
                'feedback' => '',
                'feedbackformat' => FORMAT_HTML,
                'partcorrectfb' => self::DEFAULT_CORRECT_FEEDBACK,
                'partcorrectfbformat' => FORMAT_HTML,
                'partpartiallycorrectfb' => self::DEFAULT_PARTIALLYCORRECT_FEEDBACK,
                'partpartiallycorrectfbformat' => FORMAT_HTML,
                'partincorrectfb' => self::DEFAULT_INCORRECT_FEEDBACK,
                'partincorrectfbformat' => FORMAT_HTML,
                'partindex' => 0,
            ],
        ];

        $qdata->options->numparts = 1;

        return $qdata;
    }

    /**
     * Return Formulas question: one part, two numbers, answer = 2 and 3.
     *
     * @return qtype_formulas_question
     */
    public static function make_formulas_question_testtwonums(): qtype_formulas_question {
        $q = self::make_a_formulas_question();

        $q->name = 'test-1';
        $q->questiontext = '<p>Question with two numbers. The answers are 2 and 3.</p>';

        $q->penalty = 0.3; // Non-zero and not the default.
        $q->textfragments = [
            0 => '<p>Question with two numbers. The answers are 2 and 3.</p>',
            1 => '',
        ];
        $q->numparts = 1;
        $q->defaultmark = 2;

        $p = self::make_a_formulas_part();
        $p->id = 14;
        $p->questionid = $q->id;
        $p->placeholder = '';
        $p->answermark = 2;
        $p->answer = '[2, 3]';
        $p->numbox = 2;
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
        $form->answer = ['[2, 3]'];
        $form->answernotunique = ['1'];
        $form->answermark = [2];
        $form->answertype = ['0'];
        $form->correctness = ['_relerr < 0.01'];
        $form->numbox = [2];
        $form->placeholder = [''];
        $form->vars1 = [''];
        $form->vars2 = [''];
        $form->postunit = [''];
        $form->otherrule = [''];
        $form->subqtext = [
            ['text' => '', 'format' => FORMAT_HTML],
        ];
        $form->feedback = [
            ['text' => '', 'format' => FORMAT_HTML],
        ];
        $form->partcorrectfb = [
            ['text' => 'Your answer is correct.', 'format' => FORMAT_HTML],
        ];
        $form->partpartiallycorrectfb = [
            ['text' => 'Your answer is partially correct.', 'format' => FORMAT_HTML],
        ];
        $form->partincorrectfb = [
            ['text' => 'Your answer is incorrect.', 'format' => FORMAT_HTML],
        ];
        $form->questiontext = ['text' => '<p>Question with two numbers. The answers are 2 and 3.</p>',
                'format' => FORMAT_HTML];
        $form->generalfeedback = ['text' => '', 'format' => FORMAT_HTML];
        $form->defaultmark = 2;
        $form->penalty = 0.3;
        $form->varsrandom = '';
        $form->varsglobal = '';
        $form->answernumbering = '';
        $form->globalunitpenalty = 1;
        $form->globalruleid = 1;
        $form->correctfeedback = ['text' => 'Well done!', 'format' => FORMAT_HTML];
        $form->partiallycorrectfeedback = [
            'text' => 'Parts, but only parts, of your response are correct.',
            'format' => FORMAT_HTML,
        ];
        $form->shownumcorrect = '0';
        $form->incorrectfeedback = ['text' => 'That is not right at all.', 'format' => FORMAT_HTML];
        return $form;
    }

    /**
     * Return Formulas question with 3 parts, not randomised, answers = 5, 6, 7.
     *
     * @return qtype_formulas_question with 3 parts.
     */
    public static function make_formulas_question_testthreeparts(): qtype_formulas_question {
        $q = self::make_a_formulas_question();

        $q->name = 'test-1';
        $q->questiontext = '<p>Multiple parts : --{#1}--{#2}--{#3}</p>';
        $q->penalty = 0.3; // Non-zero and not the default.
        $q->textfragments = [0 => '<p>Multiple parts : --',
                1 => '--',
                2 => '--',
                3 => '</p>'];
        $q->numparts = 3;
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
        $form->questiontext = ['text' => '<p>Multiple parts : --{#1}--{#2}--{#3}</p>',
                'format' => FORMAT_HTML];
        $form->generalfeedback = ['text' => '', 'format' => FORMAT_HTML];
        $form->defaultmark = 6;
        $form->penalty = 0.3;
        $form->varsrandom = '';
        $form->varsglobal = '';
        $form->answernumbering = 'abc';
        $form->noanswers = 3;
        $form->answer = ['5', '6', '7'];
        $form->answernotunique = ['1', '1', '1'];
        $form->answermark = ['2', '2', '2'];
        $form->numbox = [1, 1, 1];
        $form->placeholder = ['#1', '#2', '#3'];
        $form->postunit = ['', '', ''];
        $form->answertype = ['0', '0', '0'];
        $form->vars1 = ['', '', ''];
        $form->correctness = ['_relerr < 0.01', '_relerr < 0.01', '_relerr < 0.01'];
        $form->vars2 = ['', '', ''];
        $form->otherrule = ['', '', ''];
        $form->globalunitpenalty = 1;
        $form->globalruleid = 1;
        $form->subqtext = [
            ['text' => 'This is first part.', 'format' => FORMAT_HTML],
            ['text' => 'This is second part.', 'format' => FORMAT_HTML],
            ['text' => 'This is third part.', 'format' => FORMAT_HTML],
        ];
        $form->feedback = [
            ['text' => '', 'format' => FORMAT_HTML],
            ['text' => '', 'format' => FORMAT_HTML],
            ['text' => '', 'format' => FORMAT_HTML],
        ];
        $form->partcorrectfb = [
            ['text' => 'Part 1 correct feedback.', 'format' => FORMAT_HTML],
            ['text' => 'Part 2 correct feedback.', 'format' => FORMAT_HTML],
            ['text' => 'Part 3 correct feedback.', 'format' => FORMAT_HTML],
        ];
        $form->partpartiallycorrectfb = [
            ['text' => 'Part 1 partially correct feedback.', 'format' => FORMAT_HTML],
            ['text' => 'Part 2 partially correct feedback.', 'format' => FORMAT_HTML],
            ['text' => 'Part 3 partially correct feedback.', 'format' => FORMAT_HTML],
        ];
        $form->partincorrectfb = [
            ['text' => 'Part 1 incorrect feedback.', 'format' => FORMAT_HTML],
            ['text' => 'Part 2 incorrect feedback.', 'format' => FORMAT_HTML],
            ['text' => 'Part 3 incorrect feedback.', 'format' => FORMAT_HTML],
        ];
        $form->correctfeedback = ['text' => 'Well done!', 'format' => FORMAT_HTML];
        $form->partiallycorrectfeedback = [
            'text' => 'Parts, but only parts, of your response are correct.',
            'format' => FORMAT_HTML,
        ];
        $form->shownumcorrect = '0';
        $form->incorrectfeedback = ['text' => 'That is not right at all.', 'format' => FORMAT_HTML];
        $form->numhints = 2;
        $form->hint = [
            ['text' => '', 'format' => FORMAT_HTML],
            ['text' => '', 'format' => FORMAT_HTML],
        ];
        $form->hintclearwrong = ['0', '0'];
        $form->hintshownumcorrect = ['0', '0'];
        return $form;
    }

    /**
     * Return Formulas question with 4 parts, not randomised, separate and combined unit fields.
     *
     * @return qtype_formulas_question
     */
    public static function make_formulas_question_testmethodsinparts(): qtype_formulas_question {
        $q = self::make_a_formulas_question();

        $q->name = 'test-methodsinparts';
        $q->questiontext = '<p>This question shows different display methods of the answer and unit box.</p>';
        $q->defaultmark = 8;
        $q->penalty = 0.3; // Non-zero and not the default.
        $q->numparts = 4;
        $q->generalfeedback = 'This is the general feedback.';
        $q->textfragments = [0 => '<p>This question shows different display methods of the answer and unit box.</p>',
                1 => '',
                2 => '',
                3 => '',
                4 => '',
                ];
        $q->varsrandom = '';
        $q->varsglobal = 'v = 40;dt = 3;s = v*dt;';

        $p0 = self::make_a_formulas_part();
        $p0->questionid = $q->id;
        $p0->id = 14;
        $p0->partindex = 0;
        $p0->answermark = 2;
        $p0->subqtext = '<p>If a car travels {s} m in {dt} s, what is the speed of the car? {_0}{_u}</p>';      // Combined unit.
        $p0->answer = 'v';
        $p0->answernotunique = '1';
        $p0->postunit = 'm/s';
        $q->parts[0] = $p0;

        $p1 = self::make_a_formulas_part();
        $p1->questionid = $q->id;
        $p1->id = 15;
        $p1->partindex = 1;
        $p1->answermark = 2;
        $p1->subqtext = '<p>If a car travels {s} m in {dt} s, what is the speed of the car? {_0} {_u}</p>';     // Separated unit.
        $p1->answer = 'v';
        $p1->answernotunique = '1';
        $p1->postunit = 'm/s';
        $q->parts[1] = $p1;

        $p2 = self::make_a_formulas_part();
        $p2->questionid = $q->id;
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
        $p3->questionid = $q->id;
        $p3->id = 17;
        $p3->partindex = 3;
        $p3->answermark = 2;
        // As postunit is empty {_u} should be ignored.
        $p3->subqtext = '<p>If a car travels {s} m in {dt} s, what is the speed of the car? speed = {_0}{_u}</p>';
        $p3->answer = 'v';
        $p3->answernotunique = '1';
        $p3->postunit = '';
        $q->parts[3] = $p3;

        $q->hints = [
            new question_hint_with_parts(101, 'Hint 1.', FORMAT_HTML, 1, 0),
            new question_hint_with_parts(102, 'Hint 2.', FORMAT_HTML, 1, 1),
        ];

        return $q;
    }

    /**
     * Get the question data, as it would be loaded by get_question_options.
     * @return object
     */
    public static function get_formulas_question_data_testmethodsinparts() {
        $qdata = new stdClass();
        test_question_maker::initialise_question_data($qdata);

        $qdata->qtype = 'formulas';
        $qdata->name = 'test-methodsinparts';
        $qdata->questiontext = '<p>This question shows different display methods of the answer and unit box.</p>';
        $qdata->generalfeedback = 'This is the general feedback.';
        $qdata->defaultmark = 8;
        $qdata->penalty = 0.3;

        $qdata->options = new stdClass();
        $qdata->contextid = context_system::instance()->id;
        $qdata->options->varsrandom = '';
        $qdata->options->varsglobal = 'v = 40;dt = 3;s = v*dt;';
        $qdata->options->answernumbering = 'abc';
        $qdata->options->shownumcorrect = 1;
        $qdata->options->correctfeedback =
                test_question_maker::STANDARD_OVERALL_CORRECT_FEEDBACK;
        $qdata->options->correctfeedbackformat = FORMAT_HTML;
        $qdata->options->partiallycorrectfeedback =
                test_question_maker::STANDARD_OVERALL_PARTIALLYCORRECT_FEEDBACK;
        $qdata->options->partiallycorrectfeedbackformat = FORMAT_HTML;
        $qdata->options->incorrectfeedback =
                test_question_maker::STANDARD_OVERALL_INCORRECT_FEEDBACK;
        $qdata->options->incorrectfeedbackformat = FORMAT_HTML;

        $qdata->options->answers = [
            14 => (object) [
                'id' => 14,
                'questionid' => $qdata->id,
                'placeholder' => '',
                'answermark' => 2,
                'answertype' => '0',
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
                'partcorrectfb' => self::DEFAULT_CORRECT_FEEDBACK,
                'partcorrectfbformat' => FORMAT_HTML,
                'partpartiallycorrectfb' => self::DEFAULT_PARTIALLYCORRECT_FEEDBACK,
                'partpartiallycorrectfbformat' => FORMAT_HTML,
                'partincorrectfb' => self::DEFAULT_INCORRECT_FEEDBACK,
                'partincorrectfbformat' => FORMAT_HTML,
                'partindex' => 0,
            ],
            15 => (object) [
                'id' => 15,
                'questionid' => $qdata->id,
                'placeholder' => '',
                'answermark' => 2,
                'answertype' => '0',
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
                'partcorrectfb' => self::DEFAULT_CORRECT_FEEDBACK,
                'partcorrectfbformat' => FORMAT_HTML,
                'partpartiallycorrectfb' => self::DEFAULT_PARTIALLYCORRECT_FEEDBACK,
                'partpartiallycorrectfbformat' => FORMAT_HTML,
                'partincorrectfb' => self::DEFAULT_INCORRECT_FEEDBACK,
                'partincorrectfbformat' => FORMAT_HTML,
                'partindex' => 1,
            ],
            16 => (object) [
                'id' => 16,
                'questionid' => $qdata->id,
                'placeholder' => '',
                'answermark' => 2,
                'answertype' => '0',
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
                'partcorrectfb' => self::DEFAULT_CORRECT_FEEDBACK,
                'partcorrectfbformat' => FORMAT_HTML,
                'partpartiallycorrectfb' => self::DEFAULT_PARTIALLYCORRECT_FEEDBACK,
                'partpartiallycorrectfbformat' => FORMAT_HTML,
                'partincorrectfb' => self::DEFAULT_INCORRECT_FEEDBACK,
                'partincorrectfbformat' => FORMAT_HTML,
                'partindex' => 2,
            ],
            17 => (object) [
                'id' => 17,
                'questionid' => $qdata->id,
                'placeholder' => '',
                'answermark' => 2,
                'answertype' => '0',
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
                'subqtext' => '<p>If a car travels {s} m in {dt} s, what is the speed of the car? speed = {_0}{_u}</p>',
                'subqtextformat' => FORMAT_HTML,
                'feedback' => '',
                'feedbackformat' => FORMAT_HTML,
                'partcorrectfb' => self::DEFAULT_CORRECT_FEEDBACK,
                'partcorrectfbformat' => FORMAT_HTML,
                'partpartiallycorrectfb' => self::DEFAULT_PARTIALLYCORRECT_FEEDBACK,
                'partpartiallycorrectfbformat' => FORMAT_HTML,
                'partincorrectfb' => self::DEFAULT_INCORRECT_FEEDBACK,
                'partincorrectfbformat' => FORMAT_HTML,
                'partindex' => 3,
            ],
        ];

        $qdata->options->numparts = 4;

        $qdata->hints = [
            1 => (object) [
                'id' => 101,
                'hint' => 'Hint 1.',
                'hintformat' => FORMAT_HTML,
                'shownumcorrect' => 1,
                'clearwrong' => 0,
                'options' => 0,
            ],
            2 => (object) [
                'id' => 102,
                'hint' => 'Hint 2.',
                'hintformat' => FORMAT_HTML,
                'shownumcorrect' => 1,
                'clearwrong' => 1,
                'options' => 1,
            ],
        ];

        return $qdata;
    }

    /**
     * Gets the question form data for a formulas question
     * @return stdClass
     */
    public function get_formulas_question_form_data_testmethodsinparts() {
        $form = new stdClass();

        $form->name = 'test-methodsinparts';
        $form->questiontext = ['text' => '<p>This question shows different display methods of the answer and unit box.</p>',
                'format' => FORMAT_HTML];
        $form->generalfeedback = ['text' => 'This is the general feedback.', 'format' => FORMAT_HTML];
        $form->defaultmark = 8;
        $form->penalty = 0.3;
        $form->varsrandom = '';
        $form->varsglobal = 'v = 40;dt = 3;s = v*dt;';
        $form->answernumbering = 'abc';
        $form->noanswers = 4;
        $form->answer = [
            0 => 'v',
            1 => 'v',
            2 => 'v',
            3 => 'v',
        ];
        $form->answernotunique = [
            0 => '1',
            1 => '1',
            2 => '1',
            3 => '1',
        ];
        $form->answermark = [
            0 => 2,
            1 => 2,
            2 => 2,
            3 => 2,
        ];
        $form->numbox = [
            0 => 1,
            1 => 1,
            2 => 1,
            3 => 1,
        ];
        $form->placeholder = [
            0 => '',
            1 => '',
            2 => '',
            3 => '',
        ];
        $form->postunit = [
            0 => 'm/s',
            1 => 'm/s',
            2 => '',
            3 => '',
        ];
        $form->answertype = [
            0 => '0',
            1 => '0',
            2 => '0',
            3 => '0',
        ];
        $form->vars1 = [
            0 => '',
            1 => '',
            2 => '',
            3 => '',
        ];
        $form->correctness = [
            0 => '_relerr < 0.01',
            1 => '_relerr < 0.01',
            2 => '_relerr < 0.01',
            3 => '_relerr < 0.01',
        ];
        $form->vars2 = [
            0 => '',
            1 => '',
            2 => '',
            3 => '',
        ];
        $form->otherrule = [
            0 => '',
            1 => '',
            2 => '',
            3 => '',
        ];
        $form->globalunitpenalty = 1;
        $form->globalruleid = 1;
        $form->subqtext = [
            0 => [
              'text' => '<p>If a car travels {s} m in {dt} s, what is the speed of the car? {_0}{_u}</p>',
              'format' => FORMAT_HTML,
            ],
            1 => [
              'text' => '<p>If a car travels {s} m in {dt} s, what is the speed of the car? {_0} {_u}</p>',
              'format' => FORMAT_HTML,
            ],
            2 => [
              'text' => '<p>If a car travels {s} m in {dt} s, what is the speed of the car? {_0} {_u}</p>',
              'format' => FORMAT_HTML,
            ],
            3 => [
              'text' => '<p>If a car travels {s} m in {dt} s, what is the speed of the car? speed = {_0}{_u}</p>',
              'format' => FORMAT_HTML,
            ],
        ];
        $form->feedback = [
            0 => ['text' => '', 'format' => FORMAT_HTML],
            1 => ['text' => '', 'format' => FORMAT_HTML],
            2 => ['text' => '', 'format' => FORMAT_HTML],
            3 => ['text' => '', 'format' => FORMAT_HTML],
        ];
        $form->partcorrectfb = [
            0 => ['text' => self::DEFAULT_CORRECT_FEEDBACK, 'format' => FORMAT_HTML],
            1 => ['text' => self::DEFAULT_CORRECT_FEEDBACK, 'format' => FORMAT_HTML],
            2 => ['text' => self::DEFAULT_CORRECT_FEEDBACK, 'format' => FORMAT_HTML],
            3 => ['text' => self::DEFAULT_CORRECT_FEEDBACK, 'format' => FORMAT_HTML],
        ];
        $form->partpartiallycorrectfb = [
            0 => ['text' => self::DEFAULT_PARTIALLYCORRECT_FEEDBACK, 'format' => FORMAT_HTML],
            1 => ['text' => self::DEFAULT_PARTIALLYCORRECT_FEEDBACK, 'format' => FORMAT_HTML],
            2 => ['text' => self::DEFAULT_PARTIALLYCORRECT_FEEDBACK, 'format' => FORMAT_HTML],
            3 => ['text' => self::DEFAULT_PARTIALLYCORRECT_FEEDBACK, 'format' => FORMAT_HTML],
        ];
        $form->partincorrectfb = [
            0 => ['text' => self::DEFAULT_INCORRECT_FEEDBACK, 'format' => FORMAT_HTML],
            1 => ['text' => self::DEFAULT_INCORRECT_FEEDBACK, 'format' => FORMAT_HTML],
            2 => ['text' => self::DEFAULT_INCORRECT_FEEDBACK, 'format' => FORMAT_HTML],
            3 => ['text' => self::DEFAULT_INCORRECT_FEEDBACK, 'format' => FORMAT_HTML],
        ];
        $form->correctfeedback = ['text' => test_question_maker::STANDARD_OVERALL_CORRECT_FEEDBACK, 'format' => FORMAT_HTML];
        $form->partiallycorrectfeedback = [
            'text' => test_question_maker::STANDARD_OVERALL_PARTIALLYCORRECT_FEEDBACK,
            'format' => FORMAT_HTML,
        ];
        $form->incorrectfeedback = ['text' => test_question_maker::STANDARD_OVERALL_INCORRECT_FEEDBACK, 'format' => FORMAT_HTML];
        $form->shownumcorrect = '1';
        $form->numhints = 2;
        $form->hint = [
            ['text' => 'Hint 1.', 'format' => FORMAT_HTML],
            ['text' => 'Hint 2.', 'format' => FORMAT_HTML],
        ];
        $form->hintclearwrong = [0, 1];
        $form->hintshownumcorrect = [1, 1];
        return $form;
    }

    /**
     * Return Formulas question with one part, not randomized, answer = 0, used to test for problems with 0 as answer.
     *
     * @return qtype_formulas_question
     */
    public static function make_formulas_question_testzero(): qtype_formulas_question {
        $q = self::make_a_formulas_question();

        $q->name = 'test-3';
        $q->questiontext = '<p>This question has 0 as answer to test problem when answer is equal to 0.</p>';

        $q->penalty = 0.3; // Non-zero and not the default.
        $q->textfragments = [0 => '<p>This question has 0 as answer to test problem when answer is equal to 0.</p>',
                1 => ''];
        $q->numparts = 1;
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
        $form->answer = ['0'];
        $form->answernotunique = ['1'];
        $form->answermark = [2];
        $form->answertype = ['0'];
        $form->correctness = ['_relerr < 0.01'];
        $form->numbox = [1];
        $form->placeholder = [''];
        $form->vars1 = [''];
        $form->vars2 = [''];
        $form->postunit = [''];
        $form->otherrule = [''];
        $form->subqtext = [
            ['text' => '', 'format' => FORMAT_HTML],
        ];
        $form->feedback = [
            ['text' => '', 'format' => FORMAT_HTML],
        ];
        $form->partcorrectfb = [
            ['text' => self::DEFAULT_CORRECT_FEEDBACK, 'format' => FORMAT_HTML],
        ];
        $form->partpartiallycorrectfb = [
            ['text' => self::DEFAULT_PARTIALLYCORRECT_FEEDBACK, 'format' => FORMAT_HTML],
        ];
        $form->partincorrectfb = [
            ['text' => self::DEFAULT_INCORRECT_FEEDBACK, 'format' => FORMAT_HTML],
        ];
        $form->questiontext = ['text' => '<p>This question has 0 as answer to test problem when answer is equal to 0.</p>',
                'format' => FORMAT_HTML];
        $form->generalfeedback = ['text' => '', 'format' => FORMAT_HTML];
        $form->defaultmark = 2;
        $form->penalty = 0.3;
        $form->varsrandom = '';
        $form->varsglobal = '';
        $form->answernumbering = '';
        $form->globalunitpenalty = 1;
        $form->globalruleid = 1;
        $form->correctfeedback = ['text' => 'Well done!', 'format' => FORMAT_HTML];
        $form->partiallycorrectfeedback = [
            'text' => 'Parts, but only parts, of your response are correct.',
            'format' => FORMAT_HTML,
        ];
        $form->shownumcorrect = '0';
        $form->incorrectfeedback = ['text' => 'That is not right at all.', 'format' => FORMAT_HTML];
        return $form;
    }

    /**
     * Return Formulas question with 4 parts, randomised, separate and combined unit fields.
     *
     * @return qtype_formulas_question
     */
    public static function make_formulas_question_test4(): qtype_formulas_question {
        $q = self::make_a_formulas_question();

        $q->name = 'test-4';
        $q->questiontext = '<p>This question shows different display methods of the answer and unit box.</p>';
        $q->defaultmark = 8;
        $q->penalty = 0.3; // Non-zero and not the default.
        $q->numparts = 4;
        $q->generalfeedback = 'This is the general feedback.';
        $q->textfragments = [0 => '<p>This question shows different display methods of the answer and unit box.</p>',
                1 => '',
                2 => '',
                3 => '',
                4 => '',
                ];
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
        $form->questiontext = ['text' => '<p>This question shows different display methods of the answer and unit box.</p>',
                'format' => FORMAT_HTML];
        $form->generalfeedback = ['text' => 'This is the general feedback.', 'format' => FORMAT_HTML];
        $form->defaultmark = 8;
        $form->penalty = 0.3;
        $form->varsrandom = 'v = {20:100:10}; dt = {2:6};';
        $form->varsglobal = 's = v*dt;';
        $form->answernumbering = 'abc';
        $form->noanswers = 4;
        $form->answer = ['v', 'v', 'v', 'v'];
        $form->answernotunique = ['1', '1', '1', '1'];
        $form->answermark = ['2', '2', '2', '2'];
        $form->numbox = [1, 1, 1, 1];
        $form->placeholder = ['', '', '', ''];
        $form->postunit = ['m/s', 'm/s', '', ''];
        $form->answertype = ['0', '0', '0', '0'];
        $form->vars1 = ['', '', '', ''];
        $form->correctness = ['_relerr < 0.01', '_relerr < 0.01', '_relerr < 0.01', '_relerr < 0.01'];
        $form->vars2 = ['', '', '', ''];
        $form->otherrule = ['', '', '', ''];
        $form->globalunitpenalty = 1;
        $form->globalruleid = 1;
        $form->subqtext = [
            [
              'text' => '<p>If a car travels {s} m in {dt} s, what is the speed of the car? {_0}{_u}</p>',
              'format' => FORMAT_HTML,
            ],
            [
              'text' => '<p>If a car travels {s} m in {dt} s, what is the speed of the car? {_0} {_u}</p>',
              'format' => FORMAT_HTML,
            ],
            [
              'text' => '<p>If a car travels {s} m in {dt} s, what is the speed of the car? {_0} {_u}</p>',
              'format' => FORMAT_HTML,
            ],
            [
              'text' => '<p>If a car travels {s} m in {dt} s, what is the speed of the car? speed = {_0}{_u}</p>',
              'format' => FORMAT_HTML,
            ],
        ];
        $form->feedback = [
            ['text' => '', 'format' => FORMAT_HTML],
            ['text' => '', 'format' => FORMAT_HTML],
            ['text' => '', 'format' => FORMAT_HTML],
            ['text' => '', 'format' => FORMAT_HTML],
        ];
        $form->partcorrectfb = [
            ['text' => self::DEFAULT_CORRECT_FEEDBACK, 'format' => FORMAT_HTML],
            ['text' => self::DEFAULT_CORRECT_FEEDBACK, 'format' => FORMAT_HTML],
            ['text' => self::DEFAULT_CORRECT_FEEDBACK, 'format' => FORMAT_HTML],
            ['text' => self::DEFAULT_CORRECT_FEEDBACK, 'format' => FORMAT_HTML],
        ];
        $form->partpartiallycorrectfb = [
            ['text' => self::DEFAULT_PARTIALLYCORRECT_FEEDBACK, 'format' => FORMAT_HTML],
            ['text' => self::DEFAULT_PARTIALLYCORRECT_FEEDBACK, 'format' => FORMAT_HTML],
            ['text' => self::DEFAULT_PARTIALLYCORRECT_FEEDBACK, 'format' => FORMAT_HTML],
            ['text' => self::DEFAULT_PARTIALLYCORRECT_FEEDBACK, 'format' => FORMAT_HTML],
        ];
        $form->partincorrectfb = [
            ['text' => self::DEFAULT_INCORRECT_FEEDBACK, 'format' => FORMAT_HTML],
            ['text' => self::DEFAULT_INCORRECT_FEEDBACK, 'format' => FORMAT_HTML],
            ['text' => self::DEFAULT_INCORRECT_FEEDBACK, 'format' => FORMAT_HTML],
            ['text' => self::DEFAULT_INCORRECT_FEEDBACK, 'format' => FORMAT_HTML],
        ];
        $form->correctfeedback = ['text' => 'Well done!', 'format' => FORMAT_HTML];
        $form->partiallycorrectfeedback = [
            'text' => 'Parts, but only parts, of your response are correct.',
            'format' => FORMAT_HTML,
        ];
        $form->shownumcorrect = '0';
        $form->incorrectfeedback = ['text' => 'That is not right at all.', 'format' => FORMAT_HTML];
        $form->numhints = 2;
        $form->hint = [
            ['text' => '', 'format' => FORMAT_HTML],
            ['text' => '', 'format' => FORMAT_HTML],
        ];
        $form->hintclearwrong = ['0', '0'];
        $form->hintshownumcorrect = ['0', '0'];
        return $form;
    }

    /**
     * Return Formulas question with one part, not randomized, radio button multichoice answer.
     *
     * @return qtype_formulas_question
     */
    public static function make_formulas_question_testmc(): qtype_formulas_question {
        $q = self::make_a_formulas_question();

        $q->name = 'test-5';
        $q->questiontext = '<p>This question has a multichoice answer.</p>';
        $q->varsglobal = 'mychoices=["Dog","Cat","Bird","Fish"];';
        $q->penalty = 0.3; // Non-zero and not the default.
        $q->textfragments = [0 => '<p>This question has a multichoice answer.</p>',
                                  1 => ''];
        $q->numparts = 1;
        $q->defaultmark = 2;
        $p = self::make_a_formulas_part();
        $p->id = 14;
        $p->answermark = 2;
        $p->answer = '1';
        $p->answernotunique = '1';
        $p->subqtext = '{_0:mychoices}';
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
        $form->answer = ['1'];
        $form->answernotunique = ['1'];
        $form->answermark = [2];
        $form->answertype = ['0'];
        $form->correctness = ['_relerr < 0.01'];
        $form->numbox = [1];
        $form->placeholder = [''];
        $form->vars1 = [''];
        $form->vars2 = [''];
        $form->postunit = [''];
        $form->otherrule = [''];
        $form->subqtext = [
            ['text' => '{_0:mychoices}', 'format' => FORMAT_HTML],
        ];
        $form->feedback = [
            ['text' => '', 'format' => FORMAT_HTML],
        ];
        $form->partcorrectfb = [
            ['text' => self::DEFAULT_CORRECT_FEEDBACK, 'format' => FORMAT_HTML],
        ];
        $form->partpartiallycorrectfb = [
            ['text' => self::DEFAULT_PARTIALLYCORRECT_FEEDBACK, 'format' => FORMAT_HTML],
        ];
        $form->partincorrectfb = [
            ['text' => self::DEFAULT_INCORRECT_FEEDBACK, 'format' => FORMAT_HTML],
        ];
        $form->questiontext = ['text' => '<p>This question has a multichoice answer.</p>',
                'format' => FORMAT_HTML];
        $form->generalfeedback = ['text' => '', 'format' => FORMAT_HTML];
        $form->defaultmark = 2;
        $form->penalty = 0.3;
        $form->varsrandom = '';
        $form->varsglobal = 'mychoices=["Dog","Cat","Bird","Fish"];';
        $form->answernumbering = '';
        $form->globalunitpenalty = 1;
        $form->globalruleid = 1;
        $form->correctfeedback = ['text' => 'Well done!', 'format' => FORMAT_HTML];
        $form->partiallycorrectfeedback = [
            'text' => 'Parts, but only parts, of your response are correct.',
            'format' => FORMAT_HTML,
        ];
        $form->shownumcorrect = '0';
        $form->incorrectfeedback = ['text' => 'That is not right at all.', 'format' => FORMAT_HTML];
        $form->numhints = 0;
        return $form;
    }

    /**
     * Return Formulas question with one part, not randomized, drowdown multichoice answer.
     *
     * @return qtype_formulas_question
     */
    public static function make_formulas_question_testmce(): qtype_formulas_question {
        $q = self::make_a_formulas_question();

        $q->name = 'test-5';
        $q->questiontext = '<p>This question has a multichoice answer.</p>';
        $q->varsglobal = 'mychoices=["Dog","Cat","Bird","Fish"];';
        $q->penalty = 0.3; // Non-zero and not the default.
        $q->textfragments = [0 => '<p>This question has a multichoice answer.</p>',
                                  1 => ''];
        $q->numparts = 1;
        $q->defaultmark = 2;
        $p = self::make_a_formulas_part();
        $p->id = 14;
        $p->answermark = 2;
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
        $form->answer = ['1'];
        $form->answernotunique = ['1'];
        $form->answermark = [2];
        $form->answertype = ['0'];
        $form->correctness = ['_relerr < 0.01'];
        $form->numbox = [1];
        $form->placeholder = [''];
        $form->vars1 = [''];
        $form->vars2 = [''];
        $form->postunit = [''];
        $form->otherrule = [''];
        $form->subqtext = [
            ['text' => '{_0:mychoices:MCE}', 'format' => FORMAT_HTML],
        ];
        $form->feedback = [
            ['text' => '', 'format' => FORMAT_HTML],
        ];
        $form->partcorrectfb = [
            ['text' => self::DEFAULT_CORRECT_FEEDBACK, 'format' => FORMAT_HTML],
        ];
        $form->partpartiallycorrectfb = [
            ['text' => self::DEFAULT_PARTIALLYCORRECT_FEEDBACK, 'format' => FORMAT_HTML],
        ];
        $form->partincorrectfb = [
            ['text' => self::DEFAULT_INCORRECT_FEEDBACK, 'format' => FORMAT_HTML],
        ];
        $form->questiontext = ['text' => '<p>This question has a multichoice answer.</p>',
                'format' => FORMAT_HTML];
        $form->generalfeedback = ['text' => '', 'format' => FORMAT_HTML];
        $form->defaultmark = 2;
        $form->penalty = 0.3;
        $form->varsrandom = '';
        $form->varsglobal = 'mychoices=["Dog","Cat","Bird","Fish"];';
        $form->answernumbering = 'abc';
        $form->globalunitpenalty = 1;
        $form->globalruleid = 1;
        $form->correctfeedback = ['text' => 'Well done!', 'format' => FORMAT_HTML];
        $form->partiallycorrectfeedback = [
            'text' => 'Parts, but only parts, of your response are correct.',
            'format' => FORMAT_HTML,
        ];
        $form->shownumcorrect = '0';
        $form->incorrectfeedback = ['text' => 'That is not right at all.', 'format' => FORMAT_HTML];
        $form->numhints = 0;
        return $form;
    }

    /**
     * Return Formulas question with 2 parts, each with a drowdown multichoice answer.
     *
     * @return qtype_formulas_question
     */
    public static function make_formulas_question_testmcetwoparts(): qtype_formulas_question {
        $q = self::make_a_formulas_question();

        $q->name = 'testmcetwoparts';
        $q->questiontext = '<p>This question has two parts with a multichoice answer in each of them.</p>';
        $q->varsglobal = 'choices1=["Dog","Cat","Bird","Fish"];';
        $q->varsglobal .= 'choices2=["Red","Blue","Green","Yellow"];';
        $q->penalty = 0.3; // Non-zero and not the default.
        $q->textfragments = [0 => '<p>This question has two parts with a multichoice answer in each of them.</p>',
                                  1 => '{_0:choices1:MCE}',
                                  2 => '{_0:choices2:MCE}',
                            ];
        $q->numparts = 1;
        $q->defaultmark = 2;
        $p1 = self::make_a_formulas_part();
        $p1->id = 14;
        $p1->answermark = 1;
        $p1->answer = '1';
        $p1->answernotunique = '1';
        $p1->subqtext = '{_0:choices1:MCE}';
        $p1->partcorrectfb = 'Your first answer is correct.';
        $q->parts[0] = $p1;
        $p2 = self::make_a_formulas_part();
        $p2->id = 15;
        $p2->partindex = 1;
        $p2->answermark = 1;
        $p2->answer = '1';
        $p2->answernotunique = '1';
        $p2->subqtext = '{_0:choices2:MCE}';
        $p2->partcorrectfb = 'Your second answer is correct.';
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
        $form->answer = ['1', '1'];
        $form->answernotunique = ['1', '1'];
        $form->answermark = [1, 1];
        $form->answertype = ['0', '0'];
        $form->correctness = ['_relerr < 0.01', '_relerr < 0.01'];
        $form->numbox = [1, 1];
        $form->placeholder = ['', ''];
        $form->vars1 = ['', ''];
        $form->vars2 = ['', ''];
        $form->postunit = ['', ''];
        $form->otherrule = ['', ''];
        $form->subqtext = [
            0 => ['text' => '{_0:choices1:MCE}', 'format' => FORMAT_HTML],
            1 => ['text' => '{_0:choices2:MCE}', 'format' => FORMAT_HTML],
        ];
        $form->feedback = [
            0 => ['text' => '', 'format' => FORMAT_HTML],
            1 => ['text' => '', 'format' => FORMAT_HTML],
        ];
        $form->partcorrectfb = [
            0 => ['text' => 'Your first answer is correct.', 'format' => FORMAT_HTML],
            1 => ['text' => 'Your second answer is correct.', 'format' => FORMAT_HTML],
        ];
        $form->partpartiallycorrectfb = [
            0 => ['text' => self::DEFAULT_PARTIALLYCORRECT_FEEDBACK, 'format' => FORMAT_HTML],
            1 => ['text' => self::DEFAULT_PARTIALLYCORRECT_FEEDBACK, 'format' => FORMAT_HTML],
        ];
        $form->partincorrectfb = [
            0 => ['text' => self::DEFAULT_INCORRECT_FEEDBACK, 'format' => FORMAT_HTML],
            1 => ['text' => self::DEFAULT_INCORRECT_FEEDBACK, 'format' => FORMAT_HTML],
        ];
        $form->questiontext = ['text' => '<p>This question has two parts with a multichoice answer in each of them.</p>',
                'format' => FORMAT_HTML];
        $form->generalfeedback = ['text' => '', 'format' => FORMAT_HTML];
        $form->defaultmark = 2;
        $form->penalty = 0.3;
        $form->varsrandom = '';
        $form->varsglobal = 'choices1=["Dog","Cat","Bird","Fish"];';
        $form->varsglobal .= 'choices2=["Red","Blue","Green","Yellow"];';
        $form->answernumbering = '';
        $form->globalunitpenalty = 1;
        $form->globalruleid = 1;
        $form->correctfeedback = ['text' => 'Well done!', 'format' => FORMAT_HTML];
        $form->partiallycorrectfeedback = [
            'text' => 'Parts, but only parts, of your response are correct.',
            'format' => FORMAT_HTML,
        ];
        $form->shownumcorrect = '0';
        $form->incorrectfeedback = ['text' => 'That is not right at all.', 'format' => FORMAT_HTML];
        $form->numhints = 0;
        return $form;
    }

    /**
     * Formulas question with 2 parts, each with a radio button multichoice answer.
     *
     * @return qtype_formulas_question
     */
    public static function make_formulas_question_testmctwoparts(): qtype_formulas_question {
        $q = self::make_a_formulas_question();

        $q->name = 'testmcetwoparts';
        $q->questiontext = '<p>This question has two parts with a multichoice answer in each of them.</p>';
        $q->varsglobal = 'choices1=["Dog","Cat","Bird","Fish"];';
        $q->varsglobal .= 'choices2=["Red","Blue","Green","Yellow"];';
        $q->penalty = 0.3; // Non-zero and not the default.
        $q->textfragments = [0 => '<p>This question has two parts with a multichoice answer in each of them.</p>',
                                  1 => 'Part 1 -- {_0:choices1}',
                                  2 => 'Part 2 -- {_0:choices2}',
                            ];
        $q->numparts = 1;
        $q->defaultmark = 2;
        $p1 = self::make_a_formulas_part();
        $p1->id = 14;
        $p1->answermark = 1;
        $p1->answer = '1';
        $p1->answernotunique = '1';
        $p1->subqtext = 'Part 1 -- {_0:choices1}';
        $p1->partcorrectfb = 'Your first answer is correct.';
        $q->parts[0] = $p1;
        $p2 = self::make_a_formulas_part();
        $p2->id = 15;
        $p2->partindex = 1;
        $p2->answermark = 1;
        $p2->answer = '1';
        $p2->answernotunique = '1';
        $p2->subqtext = 'Part 2 -- {_0:choices2}';
        $p2->partcorrectfb = 'Your second answer is correct.';
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
        $form->answer = ['1', '1'];
        $form->answernotunique = ['1', '1'];
        $form->answermark = [1, 1];
        $form->answertype = ['0', '0'];
        $form->correctness = ['_relerr < 0.01', '_relerr < 0.01'];
        $form->numbox = [1, 1];
        $form->placeholder = ['', ''];
        $form->vars1 = ['', ''];
        $form->vars2 = ['', ''];
        $form->postunit = ['', ''];
        $form->otherrule = ['', ''];
        $form->subqtext = [
            0 => ['text' => 'Part 1 -- {_0:choices1}', 'format' => FORMAT_HTML],
            1 => ['text' => 'Part 2 -- {_0:choices2}', 'format' => FORMAT_HTML],
        ];
        $form->feedback = [
            0 => ['text' => '', 'format' => FORMAT_HTML],
            1 => ['text' => '', 'format' => FORMAT_HTML],
        ];
        $form->partcorrectfb = [
            0 => ['text' => 'Your first answer is correct.', 'format' => FORMAT_HTML],
            1 => ['text' => 'Your second answer is correct.', 'format' => FORMAT_HTML],
        ];
        $form->partpartiallycorrectfb = [
            0 => ['text' => self::DEFAULT_PARTIALLYCORRECT_FEEDBACK, 'format' => FORMAT_HTML],
            1 => ['text' => self::DEFAULT_PARTIALLYCORRECT_FEEDBACK, 'format' => FORMAT_HTML],
        ];
        $form->partincorrectfb = [
            0 => ['text' => self::DEFAULT_INCORRECT_FEEDBACK, 'format' => FORMAT_HTML],
            1 => ['text' => self::DEFAULT_INCORRECT_FEEDBACK, 'format' => FORMAT_HTML],
        ];
        $form->questiontext = ['text' => '<p>This question has two parts with a multichoice answer in each of them.</p>',
                'format' => FORMAT_HTML];
        $form->generalfeedback = ['text' => '', 'format' => FORMAT_HTML];
        $form->defaultmark = 2;
        $form->penalty = 0.3;
        $form->varsrandom = '';
        $form->varsglobal = 'choices1=["Dog","Cat","Bird","Fish"];';
        $form->varsglobal .= 'choices2=["Red","Blue","Green","Yellow"];';
        $form->answernumbering = '';
        $form->globalunitpenalty = 1;
        $form->globalruleid = 1;
        $form->correctfeedback = ['text' => 'Well done!', 'format' => FORMAT_HTML];
        $form->partiallycorrectfeedback = [
            'text' => 'Parts, but only parts, of your response are correct.',
            'format' => FORMAT_HTML,
        ];
        $form->shownumcorrect = '0';
        $form->incorrectfeedback = ['text' => 'That is not right at all.', 'format' => FORMAT_HTML];
        $form->numhints = 0;
        return $form;
    }

    /**
     * Return Formulas question with 2 parts, each one with 2 numbers.
     *
     * @return qtype_formulas_question
     */
    public static function make_formulas_question_testtwoandtwo(): qtype_formulas_question {
        $q = self::make_a_formulas_question();

        $q->name = 'testtwoandtwo';
        $q->questiontext = '<p>This question has two parts with two numbers in each of them.</p>';
        $q->penalty = 0.3; // Non-zero and not the default.
        $q->textfragments = [
            0 => '<p>This question has two parts with two numbers in each of them.</p>',
            1 => '',
            2 => '',
        ];
        $q->numparts = 2;
        $q->defaultmark = 2;

        $p1 = self::make_a_formulas_part();
        $p1->id = 14;
        $p1->questionid = $q->id;
        $p1->answermark = 1;
        $p1->answer = '[1, 2]';
        $p1->numbox = 2;
        $p1->answernotunique = '1';
        $p1->subqtext = 'Part 1 -- {_0} -- {_1}';
        $p1->partcorrectfb = 'Your answers in part 1 are correct.';
        $q->parts[0] = $p1;

        $p2 = self::make_a_formulas_part();
        $p2->id = 15;
        $p2->partindex = 1;
        $p2->questionid = $q->id;
        $p2->answermark = 1;
        $p2->answer = '[3, 4]';
        $p2->numbox = 2;
        $p2->answernotunique = '1';
        $p2->subqtext = 'Part 2 -- {_0} -- {_1}';
        $p2->partcorrectfb = 'Your answers in part 2 are correct.';
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
        $form->noanswers = 2;
        $form->answer = [0 => '[1, 2]', 1 => '[3, 4]'];
        $form->answernotunique = ['1', '1'];
        $form->answermark = [0 => 1, 1 => 1];
        $form->answertype = ['0', '0'];
        $form->correctness = ['_relerr < 0.01', '_relerr < 0.01'];
        $form->numbox = [2, 2];
        $form->placeholder = ['', ''];
        $form->vars1 = ['', ''];
        $form->vars2 = ['', ''];
        $form->postunit = ['', ''];
        $form->otherrule = ['', ''];
        $form->subqtext = [
            0 => ['text' => 'Part 1 -- {_0} -- {_1}', 'format' => FORMAT_HTML],
            1 => ['text' => 'Part 2 -- {_0} -- {_1}', 'format' => FORMAT_HTML],
        ];
        $form->feedback = [
            0 => ['text' => '', 'format' => FORMAT_HTML],
            1 => ['text' => '', 'format' => FORMAT_HTML],
        ];
        $form->partcorrectfb = [
            0 => ['text' => 'Your answers in part 1 are correct.', 'format' => FORMAT_HTML],
            1 => ['text' => 'Your answers in part 2 are correct.', 'format' => FORMAT_HTML],
        ];
        $form->partpartiallycorrectfb = [
            0 => ['text' => self::DEFAULT_PARTIALLYCORRECT_FEEDBACK, 'format' => FORMAT_HTML],
            1 => ['text' => self::DEFAULT_PARTIALLYCORRECT_FEEDBACK, 'format' => FORMAT_HTML],
        ];
        $form->partincorrectfb = [
            0 => ['text' => self::DEFAULT_INCORRECT_FEEDBACK, 'format' => FORMAT_HTML],
            1 => ['text' => self::DEFAULT_INCORRECT_FEEDBACK, 'format' => FORMAT_HTML],
        ];
        $form->questiontext = [
            'text' => '<p>This question has two parts with two numbers in each of them.</p>',
            'format' => FORMAT_HTML,
        ];
        $form->generalfeedback = ['text' => '', 'format' => FORMAT_HTML];
        $form->defaultmark = 2;
        $form->penalty = 0.3;
        $form->varsrandom = '';
        $form->varsglobal = '';
        $form->answernumbering = 'abc';
        $form->globalunitpenalty = 1;
        $form->globalruleid = 1;
        $form->correctfeedback = [
            'text' => test_question_maker::STANDARD_OVERALL_CORRECT_FEEDBACK,
            'format' => FORMAT_HTML,
        ];
        $form->partiallycorrectfeedback = [
            'text' => test_question_maker::STANDARD_OVERALL_PARTIALLYCORRECT_FEEDBACK,
            'format' => FORMAT_HTML,
        ];
        $form->incorrectfeedback = [
            'text' => test_question_maker::STANDARD_OVERALL_INCORRECT_FEEDBACK,
            'format' => FORMAT_HTML,
        ];
        $form->shownumcorrect = 1;

        return $form;
    }

    /**
     * Return question data for the testtwoandtwo Formulas question.
     *
     * @return stdClass
     */
    public static function get_formulas_question_data_testtwoandtwo(): stdClass {
        $qdata = new stdClass();
        test_question_maker::initialise_question_data($qdata);

        $qdata->qtype = 'formulas';
        $qdata->name = 'testtwoandtwo';
        $qdata->questiontext = '<p>This question has two parts with two numbers in each of them.</p>';
        $qdata->generalfeedback = '';
        $qdata->defaultmark = 2;
        $qdata->penalty = 0.3;

        $qdata->options = new stdClass();
        $qdata->contextid = context_system::instance()->id;
        $qdata->options->varsrandom = '';
        $qdata->options->varsglobal = '';
        $qdata->options->answernumbering = 'abc';
        $qdata->options->shownumcorrect = 1;
        $qdata->options->correctfeedback =
                test_question_maker::STANDARD_OVERALL_CORRECT_FEEDBACK;
        $qdata->options->correctfeedbackformat = FORMAT_HTML;
        $qdata->options->partiallycorrectfeedback =
                test_question_maker::STANDARD_OVERALL_PARTIALLYCORRECT_FEEDBACK;
        $qdata->options->partiallycorrectfeedbackformat = FORMAT_HTML;
        $qdata->options->incorrectfeedback =
                test_question_maker::STANDARD_OVERALL_INCORRECT_FEEDBACK;
        $qdata->options->incorrectfeedbackformat = FORMAT_HTML;

        $qdata->options->answers = [
            14 => (object)[
                'id' => 14,
                'questionid' => $qdata->id,
                'placeholder' => '',
                'answermark' => 1,
                'answertype' => '0',
                'numbox' => 2,
                'vars1' => '',
                'vars2' => '',
                'answer' => '[1, 2]',
                'answernotunique' => '1',
                'correctness' => '_relerr < 0.01',
                'unitpenalty' => 1,
                'postunit' => '',
                'ruleid' => 1,
                'otherrule' => '',
                'subqtext' => 'Part 1 -- {_0} -- {_1}',
                'subqtextformat' => FORMAT_HTML,
                'feedback' => '',
                'feedbackformat' => FORMAT_HTML,
                'partcorrectfb' => 'Your answers in part 1 are correct.',
                'partcorrectfbformat' => FORMAT_HTML,
                'partpartiallycorrectfb' => self::DEFAULT_PARTIALLYCORRECT_FEEDBACK,
                'partpartiallycorrectfbformat' => FORMAT_HTML,
                'partincorrectfb' => self::DEFAULT_INCORRECT_FEEDBACK,
                'partincorrectfbformat' => FORMAT_HTML,
                'partindex' => 0,
            ],
            15 => (object)[
                'id' => 15,
                'questionid' => $qdata->id,
                'placeholder' => '',
                'answermark' => 1,
                'answertype' => '0',
                'numbox' => 2,
                'vars1' => '',
                'vars2' => '',
                'answer' => '[3, 4]',
                'answernotunique' => '1',
                'correctness' => '_relerr < 0.01',
                'unitpenalty' => 1,
                'postunit' => '',
                'ruleid' => 1,
                'otherrule' => '',
                'subqtext' => 'Part 2 -- {_0} -- {_1}',
                'subqtextformat' => FORMAT_HTML,
                'feedback' => '',
                'feedbackformat' => FORMAT_HTML,
                'partcorrectfb' => 'Your answers in part 2 are correct.',
                'partcorrectfbformat' => FORMAT_HTML,
                'partpartiallycorrectfb' => self::DEFAULT_PARTIALLYCORRECT_FEEDBACK,
                'partpartiallycorrectfbformat' => FORMAT_HTML,
                'partincorrectfb' => self::DEFAULT_INCORRECT_FEEDBACK,
                'partincorrectfbformat' => FORMAT_HTML,
                'partindex' => 1,
            ],
        ];

        $qdata->options->numparts = 2;

        return $qdata;
    }

}
