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
 * Question definition class for the Formulas question type.
 *
 * @copyright 2010-2011 Hon Wai, Lau; 2023 Philipp Imhof
 * @author Hon Wai, Lau <lau65536@gmail.com>
 * @author Philipp Imhof
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @package qtype_formulas
 */

/*

question_definition
- get_type_name
- make_behaviour
- start_attempt
- apply_attempt_state
- get_question_summary
- get_num_variants
- get_variants_selection_seed
- get_min_fraction
- get_max_fraction
- clear_wrong_from_response
- get_num_parts_right
- get_renderer
- get_expected_data
- get_correct_response
- prepare_simulated_post_data
- get_student_response_values_for_simulation
- format_text
- html_to_text
- format_questiontext
- format_generalfeedback
- make_html_inline
- check_file_access
- get_question_definition_for_external_rendering

question_manually_gradable
- is_gradable_response
- is_complete_response
- is_same_response
- summarise_response
- un_summarise_response
- classify_response

question_with_responses
- classify_response
- is_gradable_response
- un_summarise_response

question_automatically_gradable
- get_validation_error
- grade_response
- get_hint
- get_right_answer_summary

question_graded_automatically
- get_right_answer_summary
- check_combined_feedback_file_access
- check_hint_file_access
- get_hint
- format_hint

question_automatically_gradable_with_countback
- compute_final_grade

question_graded_automatically_with_countback
- make_behaviour

question_automatically_gradable_with_multiple_parts
- grade_parts_that_can_be_graded
- get_parts_and_weights
- is_same_response_for_part
- is_any_part_invalid

*/

use qtype_formulas\answer_parser;
use qtype_formulas\answer_unit_conversion;
use qtype_formulas\evaluator;
use qtype_formulas\evaluator_test;
use qtype_formulas\random_parser;
use qtype_formulas\parser;
use qtype_formulas\token;
use qtype_formulas\unit_conversion_rules;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/question/type/formulas/questiontype.php');
require_once($CFG->dirroot . '/question/type/formulas/variables.php');
require_once($CFG->dirroot . '/question/type/formulas/answer_unit.php');
require_once($CFG->dirroot . '/question/type/formulas/conversion_rules.php');
require_once($CFG->dirroot . '/question/behaviour/adaptivemultipart/behaviour.php');

/**
 * Base class for the Formulas question type.
 *
 * @copyright 2010-2011 Hon Wai, Lau; 2023 Philipp Imhof
 * @author Hon Wai, Lau <lau65536@gmail.com>
 * @author Philipp Imhof
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class qtype_formulas_question extends question_graded_automatically_with_countback
        implements question_automatically_gradable_with_multiple_parts {

    /** @var seed used to initialize the RNG; needed to restore an attempt state */
    public int $seed;

    /** @var evaluator class, this is where the evaluation stuff happens */
    public ?evaluator $evaluator = null;

    /** @var definition text for random variables, as entered in the edit form */
    public string $varsrandom;

    /** @var definition text for the question's global variables, as entered in the edit form */
    public string $varsglobal;

    /** @var qtype_formulas_part[] parts of the question */
    public $parts = [];

    /** @var string numbering (if any) of answers */
    public string $answernumbering;

    /** @var array evaluated answers for each part, two dimensional array */
    public array $evaluatedanswers = [];

    /** @var int number of parts in this question, used e.g. by the renderer */
    public int $numparts;

    /**
     * @var array strings (one more than $numpart) containing fragments from the question's main text
     *            that surround the parts' subtexts; used by the renderer
     */
    public array $textfragments;

    // .......................

    /** These array may be used some day to store results ? */
    public $fractions = array();
    public $raw_grades = array();
    public $anscorrs = array();
    public $unitcorrs = array();

    public $localvars = array();

    /**
     * Create the appropriate behaviour for an attempt at this question.
     *
     * @param question_attempt $qa
     * @param string $preferredbehaviour
     * @return question_behaviour
     */
    public function make_behaviour(question_attempt $qa, $preferredbehaviour) {
        // If the requested behaviour is 'adaptive' or 'adaptiveopenpenalty', we have to change it
        // to 'adaptivemultipart'.
        if (in_array($preferredbehaviour, ['adaptive', 'adaptiveopenpenalty'])) {
            return question_engine::make_behaviour('adaptivemultipart', $qa, $preferredbehaviour);
        }

        // Otherwise, pass it on to the parent class.
        return parent::make_behaviour($qa, $preferredbehaviour);
    }

    /**
     * Start a new attempt at this question. This method initializes and instantiates the
     * random variables. Also, we will store the seed of the RNG in order to allow restoring
     * the question later on. Finally, we initialize the evaluators for every part, because
     * they need the global and random variables from the main question.
     *
     * @param question_attempt_step $step the step of the {@link question_attempt} being started
     * @param int $variant the variant requested, integer between 1 and {@link get_num_variants()} inclusive
     */
    public function start_attempt(question_attempt_step $step, $variant): void {
        // Take $variant as the seed, store it in the database (question_attempt_step_data)
        // and seed the PRNG with that value.
        $this->seed = $variant;
        $step->set_qt_var('_seed', $this->seed);

        // Create an empty evaluator, feed it with the random variables and instantiate
        // them.
        $this->evaluator = new evaluator();
        $randomparser = new random_parser($this->varsrandom);
        $this->evaluator->evaluate($randomparser->get_statements());
        $this->evaluator->instantiate_random_variables($this->seed);

        // Parse the definition of global variables and evaluate them, taking into account
        // the random variables.
        $globalparser = new parser($this->varsglobal, $randomparser->export_known_variables());
        $this->evaluator->evaluate($globalparser->get_statements());

        // Finally, set up the parts' evaluators that evaluate the local variables.
        $this->initialize_part_evaluators();
    }

    /**
     * When reloading an in-progress {@link question_attempt} from the database, restore the question's
     * state, i. e. make sure the random variables are instantiated with the same values again. For more
     * recent versions, we do this by restoring the seed. For legacy questions, the instantiated values
     * are stored in the database.
     *
     * @param question_attempt_step $step the step of the {@link question_attempt} being loaded
     */
    public function apply_attempt_state(question_attempt_step $step): void {
        // Create an empty evaluator.
        $this->evaluator = new evaluator();

        // For backwards compatibility, we must check whether the attempt stems from
        // a legacy version or not. Recent versions only store the seed that is used
        // to initialize the RNG.
        if ($step->has_qt_var('_seed')) {
            // Fetch the seed, set up the random variables and instantiate them with
            // the stored seed.
            $this->seed = $step->get_qt_var('_seed');
            $parser = new random_parser($this->varsrandom);
            $this->evaluator->evaluate($parser->get_statements());
            $this->evaluator->instantiate_random_variables($this->seed);

            // Parse the definition of global variables and evaluate them, taking into account
            // the random variables.
            $globalparser = new parser($this->varsglobal, $parser->export_known_variables());
            $this->evaluator->evaluate($globalparser->get_statements());
        } else {
            // Fetch the stored definition of the previously instantiated random variables
            // and send them to the evaluator. They will be evaluated as *global* variables,
            // because there is no randomness anymore.
            $randominstantiated = $step->get_qt_var('_randomsvars_text');
            $this->varsglobal = $step->get_qt_var('_varsglobal');
            $parser = new parser($randominstantiated . $this->varsglobal);
            $this->evaluator->evaluate($parser->get_statements());
        }

        // Set up the parts' evaluator classes and evaluate their local variables.
        $this->initialize_part_evaluators();

        parent::apply_attempt_state($step);
    }

    /**
     * Generate a brief plain-text summary of this question to be used e.g. in reports. The summary
     * will contain the question text and all parts' texts (at the right place) with all their variables
     * substituted.
     *
     * @return string a plain text summary of this question.
     */
    public function get_question_summary(): string {
        // First, we take the main question text and substitute all the placeholders.
        $questiontext = $this->evaluator->substitute_variables_in_text($this->questiontext);
        $summary = $this->html_to_text($questiontext, $this->questiontextformat);

        // For every part, we clone the current evaluator, so each part gets the same base of
        // instantiated random and global variables. Then we use the evaluator to prepare the part's
        // text.
        foreach ($this->parts as $part) {
            $subqtext = $part->evaluator->substitute_variables_in_text($part->subqtext);
            $chunk = $this->html_to_text($subqtext, $part->subqtextformat);
            // If the part has a placeholder, we insert the part's text at the position of the
            // placeholder. Otherwise, we simply append it.
            if ($part->placeholder !== '') {
                $summary = str_replace("{{$part->placeholder}}", $chunk, $summary);
            } else {
                $summary .= $chunk;
            }
        }
        return $summary;
    }

    /**
     * Return the number of variants that exist for this question. This depends on the definition of
     * random variables, so we have to pass through the question's evaluator class. If there is no
     * evaluator, we return PHP_INT_MAX.
     *
     * @return int number of variants or PHP_INT_MAX
     */
    public function get_num_variants(): int {
        // If the question data has not been analyzed yet, we let Moodle
        // define the seed freely.
        if ($this->evaluator === null) {
            return PHP_INT_MAX;
        }
        return $this->evaluator->get_number_of_variants();
    }

    /**
     * This function is called, if the question is attempted in interactive mode with multiple tries *and*
     * if it is setup to clear incorrect responses for the next try. In this case, we clear *all* answer boxes
     * (including a possibly existing unit field) for any part that is not fully correct.
     *
     * @param array $response student's response
     * @return array same array, but with *all* answers of wrong parts being empty
     */
    public function clear_wrong_from_response(array $response): array {
        // Normalize all student answers.
        $response = $this->normalize_response($response);

        // Prepare the unit conversion stuff. Doing it here and passing it as a parameter
        // avoids having the class recreated for every part.
        $checkunit = new answer_unit_conversion();

        // Call the corresponding function for each part and apply the union operator. Note that
        // the first argument takes precedence if a key exists in both arrays, so this will
        // replace all answers from $response that have been set in clear_from_response_if_wrong() and
        // keep all the others.
        foreach ($this->parts as $part) {
            $response = $part->clear_from_response_if_wrong($response, $checkunit) + $response;
        }

        return $response;
    }

    /**
     * Return the number of parts that have been correctly answered. The renderer will call this function
     * when the question is attempted in interactive mode with multiple tries *and* it is setup to show
     * the number of correct responses.
     *
     * @param array $response student's response
     * @return array array with [0] = number of correct parts and [1] = total number of parts
     */
    public function get_num_parts_right(array $response): array {
        // Normalize all student answers.
        $response = $this->normalize_response($response);

        $numcorrect = 0;
        foreach ($this->parts as $part) {
            // FIXME: needs refactoring once part grading is implemented
            list('answer' => $answercorrect, 'unit' => $unitcorrect) = $part->grade($response);

            if ($answercorrect * $unitcorrect >= .999) {
                $numcorrect++;
            }
        }
        return [$numcorrect, $this->numparts];
    }

    /**
     * Return the expected fields and data types for all answer boxes of the question. For every
     * answer box, we have one entry named "i_j" with i being the part's index and j being the
     * answer's index inside the part. Indices start at 0, so the first box of the first part
     * corresponds to 0_0, the third box of the second part is 1_2. If part *i* has *n* answer
     * boxes and a separate unit field, it will be named "i_n". For parts with a combined input
     * field for the answer and the unit (only possible for single answer parts), we use "i_".
     */
    public function get_expected_data(): array {
        $expected = [];
        foreach ($this->parts as $part) {
            $expected += $part->get_expected_data();
        }
        return $expected;
    }

    /**
     * Return the model answers as entered by the teacher. These answers should normally be sufficient
     * to get the maximum grade.
     *
     * @return array model answer for every answer / unit box of each part
     */
    public function get_correct_response(): array {
        $responses = [];
        foreach ($this->parts as $part) {
            $responses += $part->get_correct_response();
        }
        return $responses;
    }

    /**
     * Replace variables (if needed) and apply parent's format_text().
     *
     * @param string $text text to be output
     * @param int $format format (FORMAT_MOODLE, FORMAT_HTML, FORMAT_PLAIN or FORMAT_MARKDOWN)
     * @param question_attempt $qa question attempt
     * @param string $component component ID, used for rewriting file area URLs
     * @param string $filearea file area
     * @param int $itemid the item id
     * @param bool $clean whether HTML needs to be cleaned (generally not needed for parts of the question)
     * @return string text formatted for output by format_text
     */
    public function format_text($text, $format, $qa, $component, $filearea, $itemid, $clean = false): string {
        // Doing a quick check whether there *might be* placeholders in the text. If this
        // is positive, we run it through the evaluator, even if it might not be needed.
        if (strpos($text, '{') !== false) {
            $text = $this->evaluator->substitute_variables_in_text($text);
        }
        return parent::format_text($text, $format, $qa, $component, $filearea, $itemid, $clean);
    }

    /**
     * Checks whether the users is allowed to be served a particular file. Overriding the parent method
     * is needed for the additional file areas (part text and feedback per part).
     *
     * @param question_attempt $qa question attempt being displayed
     * @param question_display_options $options options controlling display of the question
     * @param string $component component ID, used for rewriting file area URLs
     * @param string $filearea file area
     * @param array $args remaining bits of the file path
     * @param bool $forcedownload whether the user must be forced to download the file
     * @return bool whether the user can access this file
     */
    public function check_file_access($qa, $options, $component, $filearea, $args, $forcedownload): bool {
        $ownareas = ['answersubqtext', 'answerfeedback', 'partcorrectfb', 'partpartiallycorrectfb', 'partincorrectfb'];
        $combinedfeedbackareas = ['correctfeedback', 'partiallycorrectfeedback', 'incorrectfeedback'];

        if ($component === 'qtype_formulas' && in_array($filearea, $ownareas)) {
            // If we have a matching part ID, return true.
            foreach ($this->parts as $part) {
                if ($part->id === $args[0]) {
                    return true;
                }
            }
            // All parts have been checked and no part ID matched, so no access should be granted.
            return false;
        } else if ($component === 'question' && in_array($filearea, $combinedfeedbackareas)) {
            return $this->check_combined_feedback_file_access($qa, $options, $filearea, $args);
        } else if ($component === 'question' && $filearea === 'hint') {
            return $this->check_hint_file_access($qa, $options, $args);
        } else {
            return parent::check_file_access($qa, $options, $component, $filearea, $args, $forcedownload);
        }
    }

    /**
     * Used by many of the behaviours to determine whether the student has provided enough of an answer
     * for the question to be graded automatically, or whether it must be considered aborted.
     *
     * @param array $response responses, as returned by {@link question_attempt_step::get_qt_data()}
     * @return bool whether this response can be graded
     */
    public function is_gradable_response(array $response): bool {
        // Iterate over all parts. If one is not gradable, we return early.
        foreach ($this->parts as $part) {
            if (!$part->is_gradable_response($response)) {
                return false;
            }
        }

        // Still here? Then the question is gradable.
        return true;
    }

    /**
     * Used by many of the behaviours, to work out whether the student's response to the question is
     * complete. That is, whether the question attempt should move to the COMPLETE or INCOMPLETE state.
     *
     * @param array $response responses, as returned by {@link question_attempt_step::get_qt_data()}
     * @return bool whether this response is a complete answer to this question
     */
    public function is_complete_response(array $response): bool {
        // Iterate over all parts. If one part is not complete, we can return early.
        foreach ($this->parts as $part) {
            if (!$part->is_complete_response($response)) {
                return false;
            }
        }

        // Still here? Then all parts have been fully answered.
        return true;
    }

    /**
     * Used by many of the behaviours to determine whether the student's response has changed. This
     * is normally used to determine that a new set of responses can safely be discarded.
     *
     * @param array $prevresponse previously recorded responses, as returned by {@link question_attempt_step::get_qt_data()}
     * @param array $newresponse new responses, in the same format
     * @return bool whether the two sets of responses are the same
     */
    public function is_same_response(array $prevresponse, array $newresponse) {
        // Check each part. If there is a difference in one part, we leave early.
        foreach ($this->parts as $part) {
            if (!$part->is_same_response($prevresponse, $newresponse)) {
                return false;
            }
        }

        // Still here? Then it's the same response.
        return true;
    }

    /**
     * Produce a plain text summary of a response to be used e. g. in reports.
     *
     * @param $response student's response, as might be passed to {@link grade_response()}
     * @return string plain text summary
     */
    public function summarise_response(array $response) {
        $summary = [];

        // Summarise each part's answers.
        foreach ($this->parts as $part) {
            $summary[] = $part->summarise_response($response);
        }
        return implode(', ', $summary);
    }

    /** FIXME: not treated yet
     * Categorise the student's response according to the categories defined by get_possible_responses.
     *
     * @param $response response, as might be passed to {@link grade_response()}
     * @return array subpartid => {@link question_classified_response} objects;  empty array if no analysis is possible
     */
    public function classify_response(array $response) {
        // First, we normalize the student's answers.
        $response = $this->normalize_response($response);

        // Prepare the unit checking stuff. It may be re-used across parts.
        $checkunit = new answer_unit_conversion();

        $classification = [];
        // Now, we do the classification for every part.
        foreach ($this->parts as $part) {
            // Unanswered parts can immediately be classified.
            if ($part->is_unanswered($response)) {
                $classification[$part->partindex] = question_classified_response::no_response();
                continue;
            }

            // If there is an answer, we check its correctness.
            // FIXME: refactor this part
            list($anscorr, $unitcorr)
                    = $this->grade_responses_individually($part, $response, $checkunit);


            // TODO: For questions with unit:
            // fully correct (unit + value)
            // correct unit, partially correct value (>= 50%)
            // correct unit, partially correct value (< 50%)
            // correct unit, wrong value
            // wrong unit, correct value
            // wrong unit, partially correct value
            // wrong
            // For questions without unit:
            // correct
            // partially correct (>= 50%)
            // partially correct (< 50%)
            // wrong
            // --> change questiontype.php:get_possible_responses()

            if ($part->postunit !== '') {
                // The unit can only be correct (1.0) or wrong (0.0).
                // The answer can be any float from 0.0 to 1.0 inclusive.
                if ($anscorr === 1.0 && $unitcorr === 1.0) {
                    $classification[$part->partindex] = new question_classified_response(
                            'right', $part->summarise_response($response), 1);
                } else if ($unitcorr === 1.0) {
                    $classification[$part->partindex] = new question_classified_response(
                            'wrongvalue', $part->summarise_response($response), 0);
                } else if ($anscorr === 1.0) {
                    $classification[$part->partindex] = new question_classified_response(
                            'wrongunit', $part->summarise_response($response), 1 - $part->unitpenalty);
                } else {
                    $classification[$part->partindex] = new question_classified_response(
                            'wrong', $part->summarise_response($response), 0);
                }
            } else {
                $fraction = $anscorr * ($unitcorr ? 1 : (1 - $part->unitpenalty));
                if ($fraction > .999) {
                    $classification[$part->partindex] = new question_classified_response(
                            'right', $part->summarise_response($response), $fraction);
                } else {
                     $classification[$part->partindex] = new question_classified_response(
                            'wrong', $part->summarise_response($response), $fraction);
                }
            }
        }
        return $classification;
    }

    /**
     * This method is called in cases where is_gradable_response() returns false. For our qtype, this
     * only happens when some part is unanswered, so we simply return a corresponding error message.
     *
     * @return string error message
     */
    public function get_validation_error(array $response): string {
        // If the response is complete, we return an empty string. This should not happen,
        // because the renderer should not call this method in such a case.
        if ($this->is_complete_response($response)) {
            return '';
        }

        return get_string('pleaseputananswer', 'qtype_formulas');
    }

    /** FIXME: not treated yet; used e.g. with immediate feedback, adaptive or interactive mode; called after "submit and finish" with deferred feedback
     * Grade a response to the question, returning a fraction between
     * get_min_fraction() and 1.0, and the corresponding {@link question_state}
     * right, partial or wrong.
     * @param array $response responses, as returned by
     *      {@link question_attempt_step::get_qt_data()}.
     * @return array (number, integer) the fraction, and the state.
     */
    public function grade_response(array $response) {
        $response = $this->normalize_response($response);

        $totalpossible = 0;
        $achievedmarks = 0;
        foreach ($this->parts as $part) {
            $totalpossible += $part->answermark;

            $partsgrade = $part->grade($response);
            if ($partsgrade['unit']) {
                $fraction = $partsgrade['answer'];
            } else {
                $fraction = $partsgrade['answer'] * (1 - $part->unitpenalty);
            }
            $achievedmarks += $part->answermark * $fraction;
        }

        $fraction = $achievedmarks / $totalpossible;
        return [$fraction, question_state::graded_state_for_fraction($fraction)];

        /*********************************** old stuff *******************/
        // We cant' rely on question defaultmark for restored questions.
        global $OUTPUT;

        $totalvalue = 0;
        try {
            $checkunit = new answer_unit_conversion; // Defined here for the possibility of reusing parsed default set.
            foreach ($this->parts as $part) {
                //list($this->anscorrs[$part->partindex], $this->unitcorrs[$part->partindex])
                //        = $this->grade_responses_individually($part, $response, $checkunit); // May throw exception.
                $this->fractions[$part->partindex] = $this->anscorrs[$part->partindex] * ($this->unitcorrs[$part->partindex]
                                                     ? 1
                                                     : (1 - $part->unitpenalty));
                $rawgrades[$part->partindex] = $part->answermark * $this->fractions[$part->partindex];
                $totalvalue += $part->answermark;
            }
        } catch (Exception $e) {
            $OUTPUT->notification(get_string('error_grading_error', 'qtype_formulas'), 'error');
            return false; // It should have no error when grading students question.
        }

        $fraction = array_sum($rawgrades) / $totalvalue;
        return array($fraction, question_state::graded_state_for_fraction($fraction));
    }

    /**
     * This method is called in multipart adaptive mode to grade the of the question
     * that can be graded. It returns the grade and penalty for each part, if (and only if)
     * the answer to that part has been changed since the last try. For parts that were
     * not retried, no grade or penalty should be returned.
     *
     * @param array $response current response (all fields)
     * @param array $lastgradedresponse response from the last attempt (by part, but every part contains all fields)
     * @param bool $finalsubmit true when the student clicks "submit all and finish"
     * @return array part name => qbehaviour_adaptivemultipart_part_result
     */
    public function grade_parts_that_can_be_graded(array $response, array $lastgradedresponse, $finalsubmit) {
        $partresults = [];
        $checkunit = new answer_unit_conversion();

        // Every entry from the $lastgradedresponse array contains the same fields (for the entire
        // question) and the values are all the same, so we just take the last array entry.
        $lastresponse = end($lastgradedresponse);
        if ($lastresponse === false) {
            $lastresponse = [];
        }

        foreach ($this->parts as $part) {
            // Check whether the response has been changed since the last attempt. If it has not,
            // we are done for this part.
            if ($part->is_same_response($lastresponse, $response)) {
                continue;
            }

            $partsgrade = $part->grade($response);
            if ($partsgrade['unit']) {
                $fraction = $partsgrade['answer'];
            } else {
                $fraction = $partsgrade['answer'] * (1 - $part->unitpenalty);
            }

            $partresults[$part->partindex] = new qbehaviour_adaptivemultipart_part_result(
                $part->partindex, $fraction, $this->penalty
            );
        }

        return $partresults;
    }

    /**
     * Get a list of all the parts of the question and the weight they have within
     * the question.
     *
     * @return array part identifier => weight
     */
    public function get_parts_and_weights() {
        // First, we calculate the sum of all marks.
        $sum = 0;
        foreach ($this->parts as $part) {
            $sum += $part->answermark;
        }

        // Now that the total is known, we calculate each part's weight.
        $weights = [];
        foreach ($this->parts as $part) {
            $weights[$part->partindex] = $part->answermark / $sum;
        }

        return $weights;
    }

    /**
     * Check whether two responses for a given part (and only for that part) are identical.
     * This is used when working with multiple tries in order to avoid getting a penalty
     * deduction for an unchanged wrong answer that has alreadyd been counted before.
     *
     * @param string $id part indentifier
     * @param array $prevresponse previously recorded responses (for entire question)
     * @param array $newresponse new responses (for entire question)
     * @return bool
     */
    public function is_same_response_for_part($id, array $prevresponse, array $newresponse): bool {
        return $this->parts[$id]->is_same_response($prevresponse, $newresponse);
    }

    /**
     * This is called by the behaviour in order to determine whether the question state should be moved
     * to question_state::$invalid. There is virtually no scenario where a Formulas question could become
     * invalid (in the sense that it could not be graded), so we always return false.
     *
     * @param array $response student's response
     * @return bool returning false
     */
    public function is_any_part_invalid(array $response): bool {
        // FIXME: mark part invalid if evaluation of answer fails, e.g. due to invalid tokens
        // like algebraic formula with assignment (=) or number with operators
        // in that case, we must probably get_validation_error() accordingly
        return false;
    }

    /** FIXME: not treated yet, called when last try using interactive mode with hints is done
     * Work out a final grade for this attempt, taking into account all the tries the student made.
     *
     * @param array $responses response for each try, each element (1 <= n <= $totaltries) is a response array
     * @param int $totaltries maximum number of tries allowed
     * @return float grade that should be awarded for this sequence of responses
     */
    public function compute_final_grade($responses, $totaltries): float {
        $fractionsum = 0;
        $fractionmax = 0;
        $checkunit = new answer_unit_conversion();

        foreach ($this->parts as $part) {
            $fractionmax += $part->answermark;
            $lastresponse = array();
            $lastchange = 0;
            $partfraction = 0;
            foreach ($responses as $responseindex => $response) {
                $response = $this->normalize_response($response);
                if ($part->is_same_response($lastresponse, $response)) {
                    continue;
                }
                $lastresponse = $response;
                $lastchange = $responseindex;
                list($anscorrs, $unitcorrs) = $this->grade_responses_individually($part, $response, $checkunit);
                $partfraction = $anscorrs * ($unitcorrs ? 1 : (1 - $part->unitpenalty));
            }
            $fractionsum += $part->answermark * max(0,  $partfraction - $lastchange * $this->penalty);
        }

        return $fractionsum / $fractionmax;
    }

    // Compute the correct response for the given question part.
    // FIXME: this should go to the part; not a mandatory method
    public function get_correct_responses_individually($part) {
        $res = $this->get_evaluated_answers()[$part->partindex];
        /*try {
            // If the answer is algebraic formulas (i.e. string), then replace the variable with numeric value by their number.
            $localvars = $this->get_local_variables($part);
            if (is_string($res[0])) {
                $res = $this->qv->substitute_partial_formula($localvars, $res);
            }
        } catch (Exception $e) {
            return null;
        }*/

        foreach (range(0, count($res) - 1) as $j) {
            $responses[$part->partindex."_$j"] = $res[$j]; // Coordinates.
        }
        $tmp = explode('=', $part->postunit, 2);
        $responses[$part->partindex."_".count($res)] = $tmp[0];  // Postunit.
        return $responses;
    }

    // Compute the correct response for the given question part.
    // Formatted for display.
    // FIXME: this should go to the part, used by the renderer (not mandatory)
    public function correct_response_formatted($part) {
        $tmp = $this->get_correct_responses_individually($part);
        // Get all part's answer boxes.
        $boxes = $part->scan_for_answer_boxes($part->subqtext);

        // Find all multichoice coordinates in the part.
        foreach ($boxes as $key => $box) {
            if (strlen($box['options']) != 0) { // It's a multichoice coordinate.
                // Calculate all the choices.
                try {
                    // Remove the : at the beginning of options and evaluate it.
                    // $stexts = $this->qv->evaluate_general_expression($localvars, substr($box->options, 1));
                    $stexts = (object)['value' => 'FIXME'];
                } catch (Exception $e) {
                    // The $stexts variable will be null if evaluation fails.
                    $stexts = null;
                }
                if ($stexts != null) {
                    // Replace index with calculated choice.
                     $tmp["{$part->partindex}". $key] = $stexts->value[$tmp["{$part->partindex}". $key]];
                }
            }

        }
        if ($part->has_combined_unit_field()) {
            $correctanswer = implode(' ', $tmp);
        } else {
            if (!$part->has_separate_unit_field()) {
                unset($tmp["{$part->partindex}_" . (count($tmp) - 1)]);
            }
            $correctanswer = implode(', ', $tmp);
        }
        return $correctanswer;
    }

    /**
     * Undocumented function
     * FIXME: not mandatory, own implementation
     *
     * @param [type] $response
     * @return void
     */
    public function add_special_variables($response) {
        foreach ($this->parts as $part) {
            // FIXME: conversion factor must be given later
            $part->add_special_variables($response, 1);
        }
    }

    // FIXME: this has to go to the part, own implementation
    // Check whether the format of the response is correct and evaluate the corresponding expression
    // @return difference between coordinate and model answer. null if format incorrect.
    // Note: $r will have evaluated value.
    public function compute_response_difference(&$vars, &$a, &$r, $cfactor, $gradingtype) {
        return 0;
        $res = (object)array('is_number' => true, 'diff' => null);
        if ($gradingtype != 10 && $gradingtype != 100 && $gradingtype != 1000) {
            $gradingtype = 0;   // Treat as number if grading type unknown.
        }
        $res->is_number = $gradingtype != 1000;    // 1000 is the algebraic answer type.

        // Note that the same format check has been performed on the client side by the javascript "formatcheck.js".
        try {
            if (!$res->is_number) {  // Unit has no meaning for algebraic format, so do nothing for it.
                $res->diff = $this->qv->compute_algebraic_formula_difference($vars, $a, $r);
            } else {
                $res->diff = $this->qv->compute_numerical_formula_difference($a, $r, $cfactor, $gradingtype);
            }
        } catch (Exception $e) { // @codingStandardsIgnoreLine
            // Any error will return null.
        }
        if ($res->diff === null) {
            return null;
        }
        return $res;
    }

    /**
     * Set up an evaluator class for every part and have it evaluate the local variables.
     *
     * @return void
     */
    public function initialize_part_evaluators() {
        // For every part, we clone the question's evaluator in order to have the
        // same set of (instantiated) random and global variables.
        foreach ($this->parts as $part) {
            $part->evaluator = clone $this->evaluator;

            // Parse and evaluate the local variables, if there are any. We do not need to
            // retrieve or store the result, because the vars will be set inside the evaluator.
            if (!empty($part->vars1)) {
                $parser = new parser($part->vars1);
                $part->evaluator->evaluate($parser->get_statements());
            }

            // Parse, evaluate and store the model answers. They will be returned as tokens,
            // so we need to "unpack" them. We always store the model answers as an array; if
            // there is only one answer, we wrap the value into an array.
            $part->get_evaluated_answers();
        }
    }

    // FIXME: this has to go to the part, not mandatory / own implementation
    // Grade response for part, and return a list with answer correctness and unit correctness.
    public function grade_responses_individually($part, $response, &$checkunit) {
        $response = $this->normalize_response($response);
        // Step 1: Split the student's responses to the part into coordinates and unit.
        $coordinates = array();
        $i = $part->partindex;
        foreach (range(0, $part->numbox - 1) as $j) {
            $coordinates[$j] = trim($response["{$i}_$j"]);
        }
        $postunit = trim($response["{$i}_{$part->numbox}"]);

        // Step 2: Use the unit system to check whether the unit in student responses is *convertible* to the true unit.
        $conversionrules = new unit_conversion_rules;
        $entry = $conversionrules->entry($part->ruleid);
        $checkunit->assign_default_rules($part->ruleid, $entry[1]);
        $checkunit->assign_additional_rules($part->otherrule);
        $checked = $checkunit->check_convertibility($postunit, $part->postunit);
        $cfactor = $checked->cfactor;
        $unitcorrect = $checked->convertible ? 1 : 0;  // Convertible is regarded as correct here.

        // Step 3: Unit is always correct if all coordinates are 0.
        // Note that numbers must be explicit zero, expression sin(0) is not acceptable.
        $is_origin = true;
        foreach ($coordinates as $c) {
            if (!is_numeric($c)) {
                $is_origin = false;
            }
            if ($is_origin == false) {
                break;    // Stop earlier when one of coordinates is not zero.
            }
            $is_origin = $is_origin && (floatval($c) == 0);
        }
        if ($is_origin) {
            $unitcorrect = 1;
        }

        // Step 4: If any coordinates is an empty string, it is considered as incorrect.
        foreach ($coordinates as $c) {
            if (strlen($c) == 0) {
                return array(0, $unitcorrect);   // Graded unit is still returned.
            }
        }

        // Step 5: Get the model answer, which is an array of numbers or strings.
        $modelanswers = $this->get_evaluated_answers()[$part->partindex];
        if (count($coordinates) != count($modelanswers)) {
            throw new Exception('Database record inconsistence: number of answers in part!');
        }

        // Step 6: Check the format of the student response and transform them into variables for grading later.
        //$vars = $this->get_local_variables($part);     // Contains both global and local variables.
        $vars = ['idcounter' => 0, 'all' => []];
        $gradingtype = $part->answertype;
        $dres = $this->compute_response_difference($vars, $modelanswers, $coordinates, $cfactor, $gradingtype);
        if ($dres === null) {
            return array(0, $unitcorrect); // If the answer cannot be evaluated under the grading type.
        }
        //$this->add_special_correctness_variables($vars, $modelanswers, $coordinates, $dres->diff, $dres->is_number);

        // Step 7: Evaluate the grading variables and grading criteria to determine whether the answer is correct.
        //$vars = $this->qv->evaluate_assignments($vars, $part->vars2);
        //$correctness = $this->qv->evaluate_general_expression($vars, $part->correctness);
        //if ($correctness->type != 'n') {
        //    throw new Exception(get_string('error_criterion', 'qtype_formulas'));
        //}

        // Step 8: Restrict the correctness value within 0 and 1 (inclusive). Also, all non-finite numbers are incorrect.
        //$answercorrect = is_finite($correctness->value) ? min(max((float) $correctness->value, 0.0), 1.0) : 0.0;
        $answercorrect = false;
        return [1.0, true];
        return array($answercorrect, $unitcorrect);
    }

    public function normalize_response(array $response): array {
        // If the response has already been normalized, we do not do it again.
        if (array_key_exists('normalized', $response)) {
            return $response;
        }

        $result = [];
        // Normalize the responses for each part.
        foreach ($this->parts as $part) {
            $result += $part->normalize_response($response);
        }

        // Set the 'normalized' key in order to avoid redoing the same job multiple times.
        $result['normalized'] = true;

        return $result;
    }

    /**
     * Fetch evaluated answers for each part and return the overview of all parts.
     * FIXME: not mandatory, own implementation, maybe remove this later
     * @param qtype_formulas_part $part
     * @return array
     */
    public function get_evaluated_answers(): array {
        // If we already know the evaluated answers for this part, we can simply return them.
        if (!empty($this->evaluatedanswers)) {
            return $this->evaluatedanswers;
        }

        // Still here? Then let's evaluate the answers.
        foreach ($this->parts as $part) {
            $this->evaluatedanswers[$part->partindex] = $part->get_evaluated_answers();
        }

        return $this->evaluatedanswers;
    }
}

/**
 * Class to represent a question subpart, loaded from the question_answers table
 * in the database.
 *
 * @copyright  2012 Jean-Michel Védrine, 2023 Philipp Imhof
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class qtype_formulas_part {

    /** @var ?evaluator the part's evaluator class */
    public ?evaluator $evaluator = null;

    /** @var array store the evaluated model answer(s) */
    public array $evaluatedanswers = [];

    /** @var int the part's id */
    public $id;

    /** @var int the part's position among all parts of the question */
    public $partindex;

    /** @var string the part's placeholder, e.g. #1 */
    public $placeholder;

    /** @var float the maximum grade for this part */
    public $answermark;

    /** @var int answer type (number, numerical, numerical formula, algebraic) */
    public $answertype;

    /** @var int number of answer boxes (not including a possible unit box) for this part */
    public $numbox;

    /** @var string definition of local variables */
    public $vars1;

    /** @var string definition of grading variables */
    public $vars2;

    /** @var string definition of the model answer(s) */
    public $answer;

    /** @var string definition of the grading criterion */
    public $correctness;

    /** @var float deduction for a wrong unit */
    public $unitpenalty;

    /** @var string unit */
    public $postunit;

    /** @var int the set of basic unit conversion rules to be used */
    public $ruleid;

    /** @var string additional conversion rules for other accepted base units */
    public $otherrule;

    /** @var string the part's text */
    public $subqtext;

    /** @var int format constant (FORMAT_MOODLE, FORMAT_HTML, FORMAT_PLAIN or FORMAT_MARKDOWN) */
    public $subqtextformat;

    /** @var string general feedback for the part */
    public $feedback;

    /** @var int format constant (FORMAT_MOODLE, FORMAT_HTML, FORMAT_PLAIN or FORMAT_MARKDOWN) */
    public $feedbackformat;

    /** @var string part's feedback for any correct response */
    public $partcorrectfb;

    /** @var int format constant (FORMAT_MOODLE, FORMAT_HTML, FORMAT_PLAIN or FORMAT_MARKDOWN) */
    public $partcorrectfbformat;

    /** @var string part's feedback for any partially correct response */
    public $partpartiallycorrectfb;

    /** @var int format constant (FORMAT_MOODLE, FORMAT_HTML, FORMAT_PLAIN or FORMAT_MARKDOWN) */
    public $partpartiallycorrectfbformat;

    /** @var string part's feedback for any incorrect response */
    public $partincorrectfb;

    /** @var int format constant (FORMAT_MOODLE, FORMAT_HTML, FORMAT_PLAIN or FORMAT_MARKDOWN) */
    public $partincorrectfbformat;

    /**
     * Constructor.
     */
    public function __construct() {
    }

    /**
     * Whether or not a unit field is used in this part.
     *
     * @return bool
     */
    public function has_unit(): bool {
        return $this->postunit !== '';
    }

    /**
     * Whether or not the part has a combined input field for the number and the unit.
     * TODO: implement test
     *
     * @return bool
     */
    public function has_combined_unit_field(): bool {
        // In order to have a combined unit field, we must first assure that:
        // - there is a unit
        // - there is not more than one answer box
        // - the answer is not of the type algebraic formula.
        if (!$this->has_unit() || $this->numbox > 1 || $this->answertype === qtype_formulas::ANSWER_TYPE_ALGEBRAIC) {
            return false;
        }

        // Furthermore, there must be either a {_0}{_u} without whitespace in the part's text
        // (meaning the user explicitly wants a combined unit field) or no answer box placeholders
        // at all, neither for the answer nor for the unit.
        $combinedrequested = strpos($this->subqtext, '{_0}{_u}');
        $noplaceholders = strpos($this->subqtext, '{_0}') === false && strpos($this->subqtext, '{_u}') === false;
        return $combinedrequested || $noplaceholders;
    }

    /**
     * Whether or not the part has a separate input field for the unit.
     *
     * @return bool
     */
    public function has_separate_unit_field(): bool {
        return $this->has_unit() && !$this->has_combined_unit_field();
    }

    /**
     * Check whether the previous response and the new response are the same for this part's fields.
     *
     * @param array $prevresponse previously recorded responses (for entire question)
     * @param array $newresponse new responses (for entire question)
     * @return bool
     */
    public function is_same_response(array $prevresponse, array $newresponse): bool {
        // Compare previous response and new response for every expected key.
        // If we have a difference at one point, we can return early.
        foreach (array_keys($this->get_expected_data()) as $key) {
            if (!question_utils::arrays_same_at_key_missing_is_blank($prevresponse, $newresponse, $key)) {
                return false;
            }
        }

        // Still here? That means they are all the same.
        return true;
    }

    /**
     * Return the expected fields and data types for all answer boxes this part. This function
     * is called by the main question's {@link get_expected_data()} method.
     *
     * @return array
     */
    public function get_expected_data(): array {
        // The combined unit field is only possible for parts with one
        // single answer box. If there are multiple input boxes, the
        // number and unit box will not be merged.
        if ($this->has_combined_unit_field()) {
            return ["{$this->partindex}_" => PARAM_RAW];
        }

        // First, we expect the answers, counting from 0 to numbox - 1.
        $expected = [];
        for ($i = 0; $i < $this->numbox; $i++) {
            $expected["{$this->partindex}_$i"] = PARAM_RAW;
        }

        // If there is a separate unit field, we add it to the list.
        if ($this->has_separate_unit_field()) {
            $expected["{$this->partindex}_{$this->numbox}"] = PARAM_RAW;
        }
        return $expected;
    }

    /**
     * Parse a string (i. e. the part's text) looking for answer box placeholders.
     * Answer box placeholders have one of the following forms:
     * - {_u} for the unit box
     * - {_n} for an answer box, n must be an integer
     * - {_n:str} for radio buttons, str must be a variable name
     * - {_n:str:MCE} for a drop down field, MCE must be verbatim
     * Note: {_0:MCE} is valid and will lead to radio boxes based on the variable MCE.
     * Every answer box in the array will itself be an associative array with the
     * keys 'placeholder' (the entire placeholder), 'options' (the name of the variable containing
     * the options for the radio list or the dropdown) and 'dropdown' (true or false).
     * TODO: implement test
     * TODO: allow {_n|50px} or {_n|10} to control size of the input field
     *
     * @param $text string to be parsed.
     * @return array.
     */
    public static function scan_for_answer_boxes(string $text, bool $failonduplicate = false): array {
        // Match the text and store the matches.
        preg_match_all('/\{(_u|_\d+)(:(_[A-Za-z]|[A-Za-z]\w*)(:(MCE))?)?\}/', $text, $matches);

        $boxes = [];

        // The array $matches[1] contains the matches of the first capturing group, i. e. _1 or _u.
        foreach ($matches[1] as $i => $match) {
            // Skip duplicates.
            if (array_key_exists($match, $boxes)) {
                if ($failonduplicate) {
                    throw new Exception("answer box placeholders must be unique, found second instance of $match");
                }
                continue;
            }
            // The array $matches[0] contains the entire pattern, e.g. {_1:vav:MCE} or simply {_3}. This
            // text is later needed to replace the placeholder by the input element.
            // With $matches[3], we can access the name of the variable containing the options for the radio
            // boxes or the drop down list.
            // Finally, the array $matches[4] will contain ':MCE' in case this has been specified. Otherwise,
            // there will be an empty string.
            // TODO: add option 'size' (for characters) or 'width' (for pixel width)
            $boxes[$match] = [
                'placeholder' => $matches[0][$i],
                'options' => $matches[3][$i],
                'dropdown' => ($matches[4][$i] === ':MCE')
            ];
        }
        return $boxes;
    }

    /**
     * Whether or not the part contains at least one answer with a drop down or
     * radio list.
     * TODO: implement test for this
     * FIXME: this function seems to be unused
     *
     * @return bool
     */
    public function has_multichoice_coordinate(): bool {
        // First, parse the part's text.
        $boxes = self::scan_for_answer_boxes($this->subqtext);

        // Check every answer box placeholder to see whether a variable name is
        // stored in the 'options' field.
        foreach ($boxes as $box) {
            if ($box['options'] !== '') {
                return true;
            }
        }

        // Still here? Then there's no multichoice answer.
        return false;
    }

    /**
     * Produce a plain text summary of a response for the part.
     * @param $response a response, as might be passed to {@link grade_response()}.
     * @return string a plain text summary of that response, that could be used in reports.
     */
    public function summarise_response(array $response) {
        $summary = [];

        // Iterate over all expected answer fields and if there is a corresponding
        // answer in $response, add its value to the summary array.
        foreach (array_keys($this->get_expected_data()) as $name) {
            if (array_key_exists($name, $response)) {
                $summary[] = $response[$name];
            }
        }

        // Transform the array to a comma-separated list for a nice summary.
        return implode(', ', $summary);
    }

    /**
     * Undocumented function
     *
     * @param array $response
     * @return array
     */
    public function normalize_response(array $response): array {
        $result = [];

        // There might be a combined field for number and unit which would be called i_.
        // We check this first. A combined field is only possible, if there is not more
        // than one answer, so we can safely use i_0 for the number and i_1 for the unit.
        $name = "{$this->partindex}_";
        if (isset($response[$name])) {
            $combined = trim($response[$name]);
            $parser = new answer_parser($combined);
            $splitindex = $parser->find_start_of_units();

            $number = trim(substr($combined, 0, $splitindex));
            $unit = trim(substr($combined, $splitindex));

            $result["{$name}0"] = $number;
            $result["{$name}1"] = $unit;
            return $result;
        }

        // Otherwise, we iterate from 0 to numbox inclusive, because the there might be a unit field.
        for ($i = 0; $i <= $this->numbox; $i++) {
            $name = "{$this->partindex}_$i";

            // If there is an answer, we strip white space from the start and end.
            // Missing answers should be empty strings.
            if (isset($response[$name])) {
                $result[$name] = trim($response[$name]);
            } else {
                $result[$name] = '';
            }

            // Restrict the answer's length to 128 characters. There is no real need
            // for this, but it was done in the first versions, so we'll keep it for
            // backwards compatibility.
            // TODO: get rid of this and maybe add option to input field to restrict length
            if (strlen($result[$name]) > 128) {
                $result[$name] = substr($result[$name], 0, 128);
            }
        }

        return $result;
    }

    /**
     * Determines whether the student has entered enough in order for this part to
     * be graded. We consider a part gradable, if it is not unanswered, i. e. if at
     * least some field has been filled.
     *
     * @param array $response
     * @return bool
     */
    public function is_gradable_response(array $response): bool {
        return !$this->is_unanswered($response);
    }

    /**
     * Determines whether the student has provided a complete answer to this part,
     * i. e. if all fields have been filled.
     *
     * @param array $response
     * @return bool
     */
    public function is_complete_response(array $response): bool {
        // First, we check if there is a combined unit field. In that case, there will
        // be only one field to verify.
        if ($this->has_combined_unit_field()) {
            return !empty($response["{$this->partindex}_"]);
        }

        // If we are still here, we do now check all "normal" fields. If one is empty,
        // we can return early.
        for ($i = 0; $i < $this->numbox; $i++) {
            if (empty($response["{$this->partindex}_{$i}"])) {
                return false;
            }
        }

        // Finally, we check whether there is a separate unit field and, if necessary,
        // make sure it is not empty.
        if ($this->has_separate_unit_field()) {
            return !empty($response["{$this->partindex}_{$this->numbox}"]);
        }

        // Still here? That means no expected field was missing and no fields were empty.
        return true;
    }

    /**
     * Determines whether the part (as a whole) is unanswered.
     *
     * @param array $response
     * @return bool
     */
    public function is_unanswered(array $response): bool {
        // If there is a combined number/unit answer, we know that there are no other
        // answers, so we just check this one.
        if ($this->has_combined_unit_field()) {
            return empty($response["{$this->partindex}_"]);
        }

        // Otherwise, we check all answer boxes (including unit, if it exists) of this part.
        // If at least one is not empty, the part has been answered.
        // Note that $response will contain *all* answers for *all* parts.
        for ($i = 0; $i <= $this->numbox; $i++) {
            if (!empty($response["{$this->partindex}_{$i}"])) {
                return false;
            }
        }

        // Still here? Then no fields were filled.
        return true;
    }

    /**
     * TODO: Undocumented function
     *
     * FIXME: for algebraic answers: replace non-algebraic variables by their numerical value
     *
     * @return array
     */
    public function get_evaluated_answers(): array {
        // If we already know the evaluated answers for this part, we can simply return them.
        if (!empty($this->evaluatedanswers)) {
            return $this->evaluatedanswers;
        }

        // Still here? Then let's evaluate the answers.
        $result = [];

        // Check whether the part uses the algebraic answer type.
        $isalgebraic = $this->answertype == qtype_formulas::ANSWER_TYPE_ALGEBRAIC;

        $parser = new parser($this->answer);
        $result = $this->evaluator->evaluate($parser->get_statements())[0];

        // The $result will now be a token with its value being either a literal (string, number)
        // or an array of literal tokens. If we have one single answer, we wrap it into an array
        // before continuing. Otherwise we convert the array of tokens into an array of literals.
        if ($result->type & token::ANY_LITERAL) {
            $this->evaluatedanswers = [$result->value];
        } else {
            $this->evaluatedanswers = array_map(function ($element) {
                return $element->value;
            }, $result->value);
        }

        // If the answer type is algebraic, substitute all non-algebraic variables by
        // their numerical value.
        if ($isalgebraic) {
            foreach ($this->evaluatedanswers as &$answer) {
                $answer = $this->evaluator->substitute_variables_in_algebraic_formula($answer);
            }
            // In case we later write to $answer, this would alter the last entry of the $modelanswers
            // array, so we'd better remove the reference to make sure this won't happend.
            unset($answer);
        }

        return $this->evaluatedanswers;
    }

    /**
     * FIXME: doc
     *
     * @param array $answers
     * @return void
     */
    private static function wrap_algebraic_formulas_in_quotes(array $formulas): array {
        foreach ($formulas as &$formula) {
            $formula = '"' . $formula . '"';
        }
        // In case we later write to $formula, this would alter the last entry of the $formulas
        // array, so we'd better remove the reference to make sure this won't happen.
        unset($formula);

        return $formulas;
    }

    /**
     * TODO: Undocumented function, clean up
     *
     * @param [type] $response (already evaluated, normal array indices)
     * @return void
     */
    public function add_special_variables($studentanswers, $conversionfactor) {
        $isalgebraic = $this->answertype == qtype_formulas::ANSWER_TYPE_ALGEBRAIC;

        // First, we set _a to the array of model answers. We can use the
        // evaluated answers. The function get_evaluated_answers() uses a cache.
        // Answers of type alebraic formula must be wrapped in quotes.
        $modelanswers = $this->get_evaluated_answers();
        if ($isalgebraic) {
            $modelanswers = self::wrap_algebraic_formulas_in_quotes($modelanswers);
        }
        $command = '_a = [' . implode(',', $modelanswers ). '];';

        // The variable _r will contain the student's answers, scaled according to the unit,
        // but not containing the unit. Also, the variables _0, _1, ... will contain the
        // individual answers.
        if ($isalgebraic) {
            $studentanswers = self::wrap_algebraic_formulas_in_quotes($studentanswers);
        }
        $ssqstudentanswer = 0;
        foreach ($studentanswers as $i => &$studentanswer) {
            // We only do the calculation if the answer type is not algebraic. For algebraic
            // answers, we don't do anything, because quotes have already been added.
            if (!$isalgebraic) {
                $studentanswer = $conversionfactor * $studentanswer;
                $ssqstudentanswer += $studentanswer ** 2;
            }
            $command .= "_{$i} = {$studentanswer};";
        }
        unset($studentanswer);
        $command .= '_r = [' . implode(',', $studentanswers) . '];';

        // The variable _d will contain the absolute differences between the model answer
        // and the student's response. Using the parser's diff() function will make sure
        // that algebraic answers are correctly evaluated.
        $command .= '_d = diff(_a, _r);';

        // Prepare the variable _err which is the root of the sum of squared differences.
        $command .= "_err = sqrt(sum(map('*', _d, _d)));";

        // Finally, calculate the relative error, unless the question uses an algebraic answer.
        if (!$isalgebraic) {
            // We calculate the sum of squares of all model answers.
            $ssqmodelanswer = 0;
            foreach ($this->get_evaluated_answers() as $answer) {
                $ssqmodelanswer += $answer ** 2;
            }
            // If the sum of squares is 0 (i.e. all answers are 0), then either the student
            // answers are all 0 as well, in which case we set the relative error to 0. Or
            // they are not, in which case we set the relative error to the greatest possible value.
            // Otherwise, the relative error is simply the absolute error divided by the root
            // of the sum of squares.
            if ($ssqmodelanswer == 0) {
                $command .= '_relerr = ' . ($ssqstudentanswer == 0 ? 0 : PHP_FLOAT_MAX);
            } else {
                $command .= "_relerr = _err / sqrt({$ssqmodelanswer})";
            }
        }

        // FIXME: parser should include known variables
        $parser = new parser($command);
        $this->evaluator->evaluate($parser->get_statements(), true);
    }

    /** FIXME: not finished yet
     * Grade the part and return its grade.
     *
     * @param array $response current response
     * @param bool $finalsubmit true when the student clicks "submit all and finish"
     * @return array (TODO doc)
     */
    public function grade(array $response, bool $finalsubmit = false): array {
        $isalgebraic = $this->answertype == qtype_formulas::ANSWER_TYPE_ALGEBRAIC;

        // Normalize the student's response for this part, removing answers from other parts.
        $response = $this->normalize_response($response);

        // Store the unit as entered by the student and get rid of this part of the
        // array.
        $studentsunit = trim($response["{$this->partindex}_{$this->numbox}"]);
        unset($response["{$this->partindex}_{$this->numbox}"]);

        // Now, only the "real" answers are remaining in the response array. We transform
        // everything into a list and feed it to the parser and evaluator in order to obtain
        // an array containing the evaluated answers.
        // FIXME: the student responses must be parsed according to the answer type, e. g.
        // answer type number MUST NOT contain operators etc.
        // algebraic answers must not be evaluated, but parsed in order to make sure they are valid
        // TODO: update comment
        $evaluatedresponse = $response;
        if (!$isalgebraic) {
            $parser = new answer_parser('[' . implode(',', $response) . ']');
            $evaluatedresponse = $this->evaluator->evaluate($parser->get_statements())[0];

            // Convert the array of tokens to an array of literals.
            $evaluatedresponse = array_map(function ($element) {
                return $element->value;
            }, $evaluatedresponse->value);
        }

        $conversionfactor = $this->is_compatible_unit($studentsunit);
        // If the units are not compatible, we set the conversion factor to 1.
        if ($conversionfactor === false) {
            $conversionfactor = 1;
            $unitcorrect = false;
        } else {
            $unitcorrect = true;
        }

        // Add correctness variables.
        try {
            $this->add_special_variables($evaluatedresponse, $conversionfactor);
        } catch (Exception $e) {
            // FIXME: deal with evaluation error, e.g. if _err cannot be computed, because of
            // invalid algebraic expression or syntax error in student input
            // -> return as wrong answer, no need to evaluate the rest.
        }

        // Fetch and evaluate grading variables.
        $gradingparser = new parser($this->vars2);
        try {
            $this->evaluator->evaluate($gradingparser->get_statements());
        } catch (Exception $e) {
            // If grading variables cannot be evaluated, the answer will be considered as
            // wrong. Partial credit may be given for the unit. Thus, we do not need to
            // carry on.
            return ['answer' => 0, 'unit' => $unitcorrect];
        }

        // Fetch and evaluate the grading criterion.
        $correctnessparser = new parser($this->correctness);
        try {
            $evaluatedgrading = $this->evaluator->evaluate($correctnessparser->get_statements())[0];
            $evaluatedgrading = $evaluatedgrading->value;
        } catch (Exception $e) {
            $evaluatedgrading = 0;
        }

        // Restrict the grade to the closed interval [0,1].
        $evaluatedgrading = min($evaluatedgrading, 1);
        $evaluatedgrading = max($evaluatedgrading, 0);

        // FIXME: not ready yet for answer type algebraic formula
        // in that case, also check that answer is string
        if ($this->answertype == 1000 && false) {
            throw new Exception(get_string('error_answertype_mistmatch', 'qtype_formulas'));
        }

        // ******** FIXME FIXME FIXME ***********
        // FIXME: legacy code used to set $unitcorrect = 1 if all answers == 0.0

        // if evaluation of grading crit is NaN  --> zero mark

        return ['answer' => $evaluatedgrading, 'unit' => $unitcorrect];
    }

    /**
     * Check whether the unit in the student's answer can be converted into the expected unit.
     * TODO: refactor this once the unit system has been rewritten
     *
     * @param string $studentsunit unit provided by the student
     * @return float|bool false if not compatible, conversion factor if compatible
     */
    private function is_compatible_unit(string $studentsunit) {
        $checkunit = new answer_unit_conversion();
        $conversionrules = new unit_conversion_rules();
        $entry = $conversionrules->entry($this->ruleid);
        $checkunit->assign_default_rules($this->ruleid, $entry[1]);
        $checkunit->assign_additional_rules($this->otherrule);

        $checked = $checkunit->check_convertibility($studentsunit, $this->postunit);
        if ($checked->convertible) {
            return $checked->cfactor;
        }

        return false;
    }

    /**
     * Return an array containing the correct answers for this question part like they are
     * shown e.g. in the feedback or on the review page of a question attempt.
     *
     * TODO: complete doc
     */
    public function get_correct_response(): array {
        // Fetch the evaluated answers.
        $answers = $this->get_evaluated_answers();

        // FIXME: deal with algebraic answer type (should be taken care of by get_evaluated_answers)

        // If we have a combined unit field, we return both the model answer plus the unit
        // in "i_". Combined fields are only possible for parts with one signle answer.
        if ($this->has_combined_unit_field()) {
            return ["{$this->partindex}_" => trim($answers[0] . ' ' . $this->postunit)];
        }

        // Otherwise, we build an array with all answers, according to our naming scheme.
        $res = [];
        for ($i = 0; $i < $this->numbox; $i++) {
            $res["{$this->partindex}_{$i}"] = $answers[$i];
        }

        // Finally, if we have a separate unit field, we add this as well.
        if ($this->has_separate_unit_field()) {
            $res["{$this->partindex}_{$this->numbox}"] = $this->postunit;
        }

        return $res;
    }


    /**
     * If the part is not correctly answered, we will set all answers to the empty string. Otherwise, we
     * just return an empty array. This function will be called by the main question (for every part) and
     * will be used to reset answers from wrong parts.
     *
     * @param array $response student's response
     * @param ?answer_unit_conversion $checkunit the unit checking toolkit in order to avoid reinitialisation for each part
     * @return array either an empty array (if part is correct) or an array with all answers being the empty string
     */
    public function clear_from_response_if_wrong(array $response, ?answer_unit_conversion $checkunit = null): array {
        $result = [];

        // If necessary, prepare unit checking stuff.
        if (empty($checkunit)) {
            $checkunit = new answer_unit_conversion();
        }

        // First, we have the response graded.
        // FIXME: this must be adapted once the grading is implemented in the part.
        list($answercorrect, $unitcorrect) = $this->grade($response);

        // If the grade is less than 1 (full mark), we reset all fields, including a possibly existing
        // combined answer+unit field.
        if ($answercorrect * $unitcorrect < 1) {
            for ($i = 0; $i <= $this->numbox; $i++) {
                if (array_key_exists("{$this->partindex}_{$i}", $response)) {
                    $result["{$this->partindex}_{$i}"] = '';
                }
            }
            if (array_key_exists("{$this->partindex}_", $response)) {
                $result["{$this->partindex}_"] = '';
            }
        }

        return $result;
    }
}
