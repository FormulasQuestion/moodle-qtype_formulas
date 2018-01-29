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
 * Tests of the upgrade to the new Moodle question engine for attempts at
 * truefalse questions.
 *
 * @package    qtype_formulas
 * @copyright  2013 Jean-Michel Vedrine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/question/engine/upgrade/tests/helper.php');


/**
 * Testing the upgrade of formulas question attempts.
 *
 * @copyright  2009 The Open University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class qtype_formulas_attempt_upgrader_test extends question_attempt_upgrader_test_base {
    public function test_formulas_deferredfeedback_qsession2() {
        $quiz = (object) array(
            'id' => '1',
            'course' => '2',
            'name' => 'essai quiz deffered',
            'intro' => '<p>essai de quiz</p>',
            'introformat' => '1',
            'timeopen' => '0',
            'timeclose' => '0',
            'attempts' => '0',
            'attemptonlast' => '0',
            'grademethod' => '1',
            'decimalpoints' => '2',
            'questiondecimalpoints' => '-1',
            'review' => '4459503',
            'questionsperpage' => '1',
            'shufflequestions' => '0',
            'shuffleanswers' => '0',
            'questions' => '4,0',
            'sumgrades' => '8.00000',
            'grade' => '100.00000',
            'timecreated' => '0',
            'timemodified' => '1357402681',
            'timelimit' => '0',
            'password' => '',
            'subnet' => '',
            'popup' => '0',
            'delay1' => '0',
            'delay2' => '0',
            'showuserpicture' => '0',
            'showblocks' => '0',
            'preferredbehaviour' => 'deferredfeedback',
        );
        $attempt = (object) array(
            'id' => '2',
            'uniqueid' => '2',
            'quiz' => '1',
            'userid' => '3',
            'attempt' => '1',
            'sumgrades' => '4.00000',
            'timestart' => '1357402852',
            'timefinish' => '1357402889',
            'timemodified' => '1357402889',
            'layout' => '4,0',
            'preview' => '0',
            'needsupgradetonewqe' => 1,
        );
        $question = (object) array(
            'id' => '4',
            'category' => '3',
            'parent' => '0',
            'name' => '1.4: Placeholder of answer and unit',
            'questiontext' => '<i><b> Placeholder of answer  and unit  </b>'
                    . '</i>: This question shows different display methods of the answer and unit box.
 <br>
 <br>',
            'questiontextformat' => '1',
            'generalfeedback' => '',
            'generalfeedbackformat' => '0',
            'penalty' => '0.1000000',
            'qtype' => 'formulas',
            'length' => '1',
            'stamp' => 'localhost+130105161625+q6E1Qu',
            'version' => 'localhost+130105161625+04tX0w',
            'hidden' => '0',
            'timecreated' => '1357402585',
            'timemodified' => '1357402585',
            'createdby' => '2',
            'modifiedby' => '2',
            'maxmark' => '8.0000000',
            'options' => (object) array(
                'varsrandom' => 'v = {20:100:10};
 dt = {2:6};',
                'varsglobal' => 's = v*dt;',
                'peranswersubmit' => '1',
                'showperanswermark' => '1',
                'answers' => array(
                    0 => (object) array(
                        'id' => '6',
                        'questionid' => '4',
                        'placeholder' => '',
                        'answermark' => '2',
                        'answertype' => '0',
                        'numbox' => '1',
                        'vars1' => '',
                        'answer' => 'v',
                        'vars2' => '',
                        'correctness' => '_relerr < 0.01',
                        'unitpenalty' => '1',
                        'postunit' => 'm/s',
                        'ruleid' => '1',
                        'otherrule' => '',
                        'trialmarkseq' => '1, 0.8,',
                        'subqtext' => 'If a car travel {s} m in {dt} s, what is the speed of the car?<br />{_0}{_u}<br />',
                        'subqtextformat' => '0',
                        'feedback' => '',
                        'feedbackformat' => '0',
                        'partindex' => 0,
                        'fraction' => 1,
                    ),
                    1 => (object) array(
                        'id' => '7',
                        'questionid' => '4',
                        'placeholder' => '',
                        'answermark' => '2',
                        'answertype' => '0',
                        'numbox' => '1',
                        'vars1' => '',
                        'answer' => 'v',
                        'vars2' => '',
                        'correctness' => '_relerr < 0.01',
                        'unitpenalty' => '1',
                        'postunit' => 'm/s',
                        'ruleid' => '1',
                        'otherrule' => '',
                        'trialmarkseq' => '1, 0.8,',
                        'subqtext' => 'If a car travel {s} m in {dt} s, what is the speed of the car?<br /> {_0} {_u}<br />',
                        'subqtextformat' => '0',
                        'feedback' => '',
                        'feedbackformat' => '0',
                        'partindex' => 1,
                        'fraction' => 1,
                    ),
                    2 => (object) array(
                        'id' => '8',
                        'questionid' => '4',
                        'placeholder' => '',
                        'answermark' => '2',
                        'answertype' => '0',
                        'numbox' => '1',
                        'vars1' => '',
                        'answer' => 'v',
                        'vars2' => '',
                        'correctness' => '_relerr < 0.01',
                        'unitpenalty' => '1',
                        'postunit' => '',
                        'ruleid' => '1',
                        'otherrule' => '',
                        'trialmarkseq' => '1, 0.8,',
                        'subqtext' => 'If a car travel {s} m in {dt} s, what is the speed of the car?<br /> {_0} m/s<br />',
                        'subqtextformat' => '0',
                        'feedback' => '',
                        'feedbackformat' => '0',
                        'partindex' => 2,
                        'fraction' => 1,
                    ),
                    3 => (object) array(
                        'id' => '9',
                        'questionid' => '4',
                        'placeholder' => '',
                        'answermark' => '2',
                        'answertype' => '0',
                        'numbox' => '1',
                        'vars1' => '',
                        'answer' => 'v',
                        'vars2' => '',
                        'correctness' => '_relerr < 0.01',
                        'unitpenalty' => '1',
                        'postunit' => '',
                        'ruleid' => '1',
                        'otherrule' => '',
                        'trialmarkseq' => '1, 0.8,',
                        'subqtext' => 'If a car travel {s} m in {dt} s, what is the speed of the car?<br />speed = {_0} m/s<br />',
                        'subqtextformat' => '0',
                        'feedback' => '',
                        'feedbackformat' => '0',
                        'partindex' => 3,
                        'fraction' => 1,
                    ),
                ),
                'numpart' => 4,
            ),
            'defaultmark' => '8.0000000',
        );
        $qsession = (object) array(
            'id' => '2',
            'attemptid' => '2',
            'questionid' => '4',
            'newest' => '4',
            'newgraded' => '4',
            'sumpenalty' => '0.0000000',
            'manualcomment' => '',
            'manualcommentformat' => '1',
            'flagged' => '0',
        );
        $qstates = array(
            2 => (object) array(
                'id' => '2',
                'attempt' => '2',
                'question' => '4',
                'seq_number' => '0',
                'answer' => '


        v=30;dt=3;
        ',
                'timestamp' => '1357402852',
                'event' => '0',
                'grade' => '0.0000000',
                'raw_grade' => '0.0000000',
                'penalty' => '0.0000000',
            ),
            3 => (object) array(
                'id' => '3',
                'attempt' => '2',
                'question' => '4',
                'seq_number' => '1',
                'answer' => '0=1,2,1,1
        0_0=30
        0_1=m/s
        1=1,0,0,1
        1_0=40
        1_1=m/s
        2=1,2,1,1
        2_0=30
        3=1,0,0,1
        3_0=40

        -1

        v=30;dt=3;
        ',
                'timestamp' => '1357402884',
                'event' => '2',
                'grade' => '0.0000000',
                'raw_grade' => '4.0000000',
                'penalty' => '0.0000000',
            ),
            4 => (object) array(
                'id' => '4',
                'attempt' => '2',
                'question' => '4',
                'seq_number' => '2',
                'answer' => '0=1,2,1,1
        0_0=30
        0_1=m/s
        1=1,0,0,1
        1_0=40
        1_1=m/s
        2=1,2,1,1
        2_0=30
        3=1,0,0,1
        3_0=40

        -1

        v=30;dt=3;
        ',
                'timestamp' => '1357402884',
                'event' => '6',
                'grade' => '4.0000000',
                'raw_grade' => '4.0000000',
                'penalty' => '0.0000000',
            ),
        );

        $qa = $this->updater->convert_question_attempt($quiz, $attempt, $question, $qsession, $qstates);

        $expectedqa = (object) array(
            'behaviour' => 'deferredfeedback',
            'questionid' => '4',
            'variant' => 1,
            'maxmark' => '8.0000000',
            'minfraction' => 0,
            'maxfraction' => 1,
            'flagged' => 0,
            'questionsummary' => '_ PLACEHOLDER OF ANSWER AND UNIT _:'
                    . 'This question shows different display methods of the answer and unit box.'
                    . 'If a car travel {s} m in {dt} s, what is the speed of the car?
            {_0}{_u}If a car travel {s} m in {dt} s, what is the speed of the car?
            {_0} {_u}If a car travel {s} m in {dt} s, what is the speed of the car?
            {_0} m/sIf a car travel {s} m in {dt} s, what is the speed of the car?
            speed = {_0} m/s (v=30;dt=3;)',
            'rightanswer' => '',
            'responsesummary' => '30m/s,40,m/s,30,40',
            'timemodified' => 1357402884,
            'steps' => array(
                0 => (object) array(
                    'sequencenumber' => 0,
                    'state' => 'todo',
                    'fraction' => null,
                    'timecreated' => 1357402852,
                    'userid' => 3,
                    'data' => array('_randomsvars_text' => 'v=30;dt=3;',
                            '_varsglobal' => 's = v*dt;'),
                ),
                1 => (object) array(
                    'sequencenumber' => 1,
                    'state' => 'complete',
                    'fraction' => null,
                    'timecreated' => 1357402884,
                    'userid' => 3,
                    'data' => array(
                '0_' => '30m/s',
                '1_0' => '40',
                '1_1' => 'm/s',
                '2_0' => '30',
                '3_0' => '40'),
                ),
                2 => (object) array(
                    'sequencenumber' => 2,
                    'state' => 'gradedpartial',
                    'fraction' => 0.5,
                    'timecreated' => 1357402884,
                    'userid' => 3,
                    'data' => array('-finish' => '1',
                    '0_' => '30m/s',
                '1_0' => '40',
                '1_1' => 'm/s',
                '2_0' => '30',
                '3_0' => '40'),
                ),
            ),
        );

        $this->compare_qas($expectedqa, $qa);
    }
}
