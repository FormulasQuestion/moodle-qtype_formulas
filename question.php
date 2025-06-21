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
 * Question definition class for the Formulas question type.
 *
 * @copyright 2010-2011 Hon Wai, Lau; 2023 Philipp Imhof
 * @author Hon Wai, Lau <lau65536@gmail.com>
 * @author Philipp Imhof
 * @license https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @package qtype_formulas
 */




// TODO: rewrite input checker script for student answer and teacher's model answer / unit.

use qtype_formulas\answer_unit_conversion;
use qtype_formulas\local\answer_parser;
use qtype_formulas\local\evaluator;
use qtype_formulas\local\lexer;
use qtype_formulas\local\random_parser;
use qtype_formulas\local\parser;
use qtype_formulas\local\token;
use qtype_formulas\unit_conversion_rules;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/question/type/formulas/questiontype.php');
require_once($CFG->dirroot . '/question/type/formulas/answer_unit.php');
require_once($CFG->dirroot . '/question/type/formulas/conversion_rules.php');
require_once($CFG->dirroot . '/question/behaviour/adaptivemultipart/behaviour.php');

/**
 * Base class for the Formulas question type.
 *
 * @copyright 2010-2011 Hon Wai, Lau; 2023 Philipp Imhof
 * @author Hon Wai, Lau <lau65536@gmail.com>
 * @author Philipp Imhof
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class qtype_formulas_question extends question_graded_automatically_with_countback
        implements question_automatically_gradable_with_multiple_parts {

    /** @var int seed used to initialize the RNG; needed to restore an attempt state */
    public int $seed;

    /** @var ?evaluator evaluator class, this is where the evaluation stuff happens */
    public ?evaluator $evaluator = null;

    /** @var string $varsrandom definition text for random variables, as entered in the edit form */
    public string $varsrandom;

    /** @var string $varsglobal definition text for the question's global variables, as entered in the edit form */
    public string $varsglobal;

    /** @var qtype_formulas_part[] parts of the question */
    public $parts = [];

    /** @var string numbering (if any) of answers */
    public string $answernumbering;

    /** @var int number of parts in this question, used e.g. by the renderer */
    public int $numparts;

    /**
     * @var string[] strings (one more than $numparts) containing fragments from the question's main text
     *               that surround the parts' subtexts; used by the renderer
     */
    public array $textfragments;

    /** @var string $correctfeedback combined feedback for correct answer */
    public string $correctfeedback;

    /** @var int $correctfeedbackformat format of combined feedback for correct answer */
    public int $correctfeedbackformat;

    /** @var string $partiallycorrectfeedback combined feedback for partially correct answer */
    public string $partiallycorrectfeedback;

    /** @var int $partiallycorrectfeedbackformat format of combined feedback for partially correct answer */
    public int $partiallycorrectfeedbackformat;

    /** @var string $incorrectfeedback combined feedback for in correct answer */
    public string $incorrectfeedback;

    /** @var int $incorrectfeedbackformat format of combined feedback for incorrect answer */
    public int $incorrectfeedbackformat;

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
     * @param question_attempt_step $step the step of the {@see question_attempt()} being started
     * @param int $variant the variant requested, integer between 1 and {@see get_num_variants()} inclusive
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

        // For improved backwards-compatibility (allowing downgrade back to 5.x), we also store
        // the legacy qt vars '_randomsvars_text' (not a typo) and '_varsglobal' in the DB.
        $legacynote = "# Legacy entry for backwards compatibility only\r\n";
        $step->set_qt_var('_randomsvars_text', $legacynote . $this->evaluator->export_randomvars_for_step_data());
        $step->set_qt_var('_varsglobal', $legacynote . $this->varsglobal);

        // Set the question's $numparts property.
        $this->numparts = count($this->parts);

        // Finally, set up the parts' evaluators that evaluate the local variables.
        $this->initialize_part_evaluators();
    }

    /**
     * When reloading an in-progress {@see \question_attempt} from the database, restore the question's
     * state, i. e. make sure the random variables are instantiated with the same values again. For more
     * recent versions, we do this by restoring the seed. For legacy questions, the instantiated values
     * are stored in the database.
     *
     * @param question_attempt_step $step the step of the {@see \question_attempt} being loaded
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
            // because there is no randomness anymore. The data was created by the old
            // variables:vstack_get_serialization() function, so we know that every statement
            // ends with a semicolon and we can simply concatenate random and global vars definition.
            $randominstantiated = $step->get_qt_var('_randomsvars_text');
            $this->varsglobal = $step->get_qt_var('_varsglobal');
            $parser = new parser($randominstantiated . $this->varsglobal);
            $this->evaluator->evaluate($parser->get_statements());
        }

        // Set the question's $numparts property.
        $this->numparts = count($this->parts);

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

        // For the question summary, it seems useful to simplify the answer box placeholders.
        $summary = preg_replace(
            '/\{(_u|_\d+)(:(_[A-Za-z]|[A-Za-z]\w*)(:(MCE|MCES|MCS))?)?((\|[\w =#]*)*)\}/',
            '{\1}',
            $summary,
        );

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
        // Note: We do not globally normalize the answers, because that would split the answer from
        // a combined unit field into two separate fields, e.g. from 0_ into 0_0 and 0_1. This
        // will still work, because the form does not have the input fields 0_0 and 0_1, but it
        // seems strange to do that.

        // Call the corresponding function for each part and apply the union operator. Note that
        // the first argument takes precedence if a key exists in both arrays, so this will
        // replace all answers from $response that have been set in clear_from_response_if_wrong() and
        // keep all the others.
        foreach ($this->parts as $part) {
            $response = $part->clear_from_response_if_wrong($response) + $response;
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
            list('answer' => $answercorrect, 'unit' => $unitcorrect) = $part->grade($response);

            if ($answercorrect >= 0.999 && $unitcorrect == true) {
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
     * @param qtype_formulas_part|null $part model answer for every answer / unit box of each part
     * @return array model answer for every answer / unit box of each part
     */
    public function get_correct_response(?qtype_formulas_part $part = null): array {
        // If the caller has requested one specific part, just return that response.
        if (isset($part)) {
            return $part->get_correct_response();
        }

        // Otherwise, fetch them all.
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
        // If $args is not properly specified, we won't grant access.
        if (!isset($args[0])) {
            return false;
        }
        // The first (remaining) element in the $args array is the item ID. This is either the question ID
        // or the part ID.
        $itemid = $args[0];

        // Files from the part's question text should be shown if the part ID matches one of our parts.
        if ($component === 'qtype_formulas' && $filearea === 'answersubqtext') {
            foreach ($this->parts as $part) {
                if ($part->id == $itemid) {
                    return true;
                }
            }
            // If we did not find a matching part, we don't serve the file.
            return false;
        }

        // If the question is not finished, we don't serve files belong to any feedback field.
        $ownfeedbackareas = ['answerfeedback', 'partcorrectfb', 'partpartiallycorrectfb', 'partincorrectfb'];
        if ($component === 'qtype_formulas' && in_array($filearea, $ownfeedbackareas)) {
            // If the $itemid does not belong to our parts, we can leave.
            $validpart = false;
            foreach ($this->parts as $part) {
                if ($part->id == $itemid) {
                    $validpart = true;
                    break;
                }
            }
            if (!$validpart) {
                return false;
            }

            // If the question is not finished, check if we have a gradable response. If we do,
            // calculate the grade and proceed. Otherwise, do not grant access to feedback files.
            $state = $qa->get_state();
            if (!$state->is_finished()) {
                $response = $qa->get_last_qt_data();
                if (!$this->is_gradable_response($response)) {
                    return false;
                }
                // Response is gradable, so try to grade and get the corresponding state.
                list($ignored, $state) = $this->grade_response($response);
            }

            // Files from the answerfeedback area belong to the part's general feedback. It is showed
            // for all answers, if feedback is enabled in the display options.
            if ($filearea === 'answerfeedback') {
                return $options->generalfeedback;
            }

            // Fetching the feedback class, i. e. 'correct' or 'partiallycorrect' or 'incorrect'.
            $feedbackclass = $state->get_feedback_class();

            // Only show files from specific feedback area if the given answer matches the kind of
            // feedback and if specific feedback is enabled in the display options.
            return ($options->feedback && $filearea === "part{$feedbackclass}fb");
        }

        $combinedfeedbackareas = ['correctfeedback', 'partiallycorrectfeedback', 'incorrectfeedback'];
        if ($component === 'question' && in_array($filearea, $combinedfeedbackareas)) {
            return $this->check_combined_feedback_file_access($qa, $options, $filearea, $args);
        }

        if ($component === 'question' && $filearea === 'hint') {
            return $this->check_hint_file_access($qa, $options, $args);
        }

        return parent::check_file_access($qa, $options, $component, $filearea, $args, $forcedownload);
    }

    /**
     * Used by many of the behaviours to determine whether the student has provided enough of an answer
     * for the question to be graded automatically, or whether it must be considered aborted.
     *
     * @param array $response responses, as returned by {@see \question_attempt_step::get_qt_data()}
     * @return bool whether this response can be graded
     */
    public function is_gradable_response(array $response): bool {
        // Iterate over all parts. If at least one part is gradable, we can leave early.
        foreach ($this->parts as $part) {
            if ($part->is_gradable_response($response)) {
                return true;
            }
        }

        // Still here? Then the question is not gradable.
        return false;
    }

    /**
     * Used by many of the behaviours, to work out whether the student's response to the question is
     * complete. That is, whether the question attempt should move to the COMPLETE or INCOMPLETE state.
     *
     * @param array $response responses, as returned by {@see \question_attempt_step::get_qt_data()}
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
     * @param array $prevresponse previously recorded responses, as returned by {@see \question_attempt_step::get_qt_data()}
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
     * @param array $response student's response, as might be passed to {@see grade_response()}
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

    /**
     * Categorise the student's response according to the categories defined by get_possible_responses.
     *
     * @param array $response response, as might be passed to {@see grade_response()}
     * @return array subpartid => {@see \question_classified_response} objects;  empty array if no analysis is possible
     */
    public function classify_response(array $response) {
        // First, we normalize the student's answers.
        $response = $this->normalize_response($response);

        $classification = [];
        // Now, we do the classification for every part.
        foreach ($this->parts as $part) {
            // Unanswered parts can immediately be classified.
            if ($part->is_unanswered($response)) {
                $classification[$part->partindex] = question_classified_response::no_response();
                continue;
            }

            // If there is an answer, we check its correctness.
            list('answer' => $answergrade, 'unit' => $unitcorrect) = $part->grade($response);

            if ($part->postunit !== '') {
                // The unit can only be correct (1.0) or wrong (0.0).
                // The answer can be any float from 0.0 to 1.0 inclusive.
                if ($answergrade >= 0.999 && $unitcorrect) {
                    $classification[$part->partindex] = new question_classified_response(
                            'right', $part->summarise_response($response), 1);
                } else if ($unitcorrect) {
                    $classification[$part->partindex] = new question_classified_response(
                            'wrongvalue', $part->summarise_response($response), 0);
                } else if ($answergrade >= 0.999) {
                    $classification[$part->partindex] = new question_classified_response(
                            'wrongunit', $part->summarise_response($response), 1 - $part->unitpenalty);
                } else {
                    $classification[$part->partindex] = new question_classified_response(
                            'wrong', $part->summarise_response($response), 0);
                }
            } else {
                if ($answergrade >= .999) {
                    $classification[$part->partindex] = new question_classified_response(
                            'right', $part->summarise_response($response), $answergrade);
                } else {
                     $classification[$part->partindex] = new question_classified_response(
                            'wrong', $part->summarise_response($response), $answergrade);
                }
            }
        }
        return $classification;
    }

    /**
     * This method is called by the renderer when the question is in "invalid" state, i. e. if it
     * does not have a complete response (for immediate feedback or interactive mode) or if it has
     * an invalid part (in adaptive multipart mode).
     *
     * @param array $response student's response
     * @return string error message
     */
    public function get_validation_error(array $response): string {
        // If is_any_part_invalid() is true, that means no part is gradable, i. e. no fields
        // have been filled.
        if ($this->is_any_part_invalid($response)) {
            return get_string('allfieldsempty', 'qtype_formulas');
        }

        // If at least one part is gradable and yet the question is in "invalid" state, that means
        // that the behaviour expected all fields to be filled.
        return get_string('pleaseputananswer', 'qtype_formulas');
    }

    /**
     * Grade a response to the question, returning a fraction between get_min_fraction()
     * and 1.0, and the corresponding {@see \question_state} right, partial or wrong. This
     * method is used with immediate feedback, with adaptive mode and with interactive mode. It
     * is called after the studenet clicks "submit and finish" when deferred feedback is active.
     *
     * @param array $response responses, as returned by {@see \question_attempt_step::get_qt_data()}
     * @return array [0] => fraction (grade) and [1] => corresponding question state
     */
    public function grade_response(array $response) {
        $response = $this->normalize_response($response);

        $totalpossible = 0;
        $achievedmarks = 0;
        // Separately grade each part.
        foreach ($this->parts as $part) {
            // Count the total number of points for this part.
            $totalpossible += $part->answermark;

            $partsgrade = $part->grade($response);
            $fraction = $partsgrade['answer'];
            // If unit is wrong, make the necessary deduction.
            if ($partsgrade['unit'] === false) {
                $fraction = $fraction * (1 - $part->unitpenalty);
            }

            // Add the number of points achieved to the total.
            $achievedmarks += $part->answermark * $fraction;
        }

        // Finally, calculate the overall fraction of points received vs. possible points
        // and return the fraction together with the correct question state (i. e. correct,
        // partiall correct or wrong).
        $fraction = $achievedmarks / $totalpossible;
        return [$fraction, question_state::graded_state_for_fraction($fraction)];
    }

    /**
     * This method is called in multipart adaptive mode to grade the of the question
     * that can be graded. It returns the grade and penalty for each part, if (and only if)
     * the answer to that part has been changed since the last try. For parts that were
     * not retried, no grade or penalty should be returned.
     *
     * @param array $response current response (all fields)
     * @param array $lastgradedresponses array containing the (full) response given when each part registered
     *      an attempt for the last time; if there has been no try for a certain part, the corresponding key
     *      will be missing. Note that this is not the "history record" of all tries.
     * @param bool $finalsubmit true when the student clicks "submit all and finish"
     * @return array part name => qbehaviour_adaptivemultipart_part_result
     */
    public function grade_parts_that_can_be_graded(array $response, array $lastgradedresponses, $finalsubmit) {
        $partresults = [];

        foreach ($this->parts as $part) {
            // Check whether we already have an attempt for this part. If we don't, we create an
            // empty response.
            $lastresponse = [];
            if (array_key_exists($part->partindex, $lastgradedresponses)) {
                $lastresponse = $lastgradedresponses[$part->partindex];
            }

            // Check whether the response has been changed since the last attempt. If it has not,
            // we are done for this part.
            if ($part->is_same_response($lastresponse, $response)) {
                continue;
            }

            $partsgrade = $part->grade($response);
            $fraction = $partsgrade['answer'];
            // If unit is wrong, make the necessary deduction.
            if ($partsgrade['unit'] === false) {
                $fraction = $fraction * (1 - $part->unitpenalty);
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
     * deduction for an unchanged wrong answer that has already been counted before.
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
     * This is called by adaptive multipart behaviour in order to determine whether the question
     * state should be moved to question_state::$invalid; many behaviours mainly or exclusively
     * use !is_complete_response() for that. We will return true if *no* part is gradable,
     * because in that case it does not make sense to proceed. If at least one part has been
     * answered (at least partially), we say that no part is invalid, because that allows the student
     * to get feedback for the answered parts.
     *
     * @param array $response student's response
     * @return bool returning false
     */
    public function is_any_part_invalid(array $response): bool {
        // Iterate over all parts. If at least one part is gradable, we can leave early.
        foreach ($this->parts as $part) {
            if ($part->is_gradable_response($response)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Work out a final grade for this attempt, taking into account all the tries the student made.
     * This method is called in interactive mode when all tries are done or when the user hits
     * 'Submit and finish'.
     *
     * @param array $responses response for each try, each element (1 <= n <= $totaltries) is a response array
     * @param int $totaltries maximum number of tries allowed
     * @return float grade that should be awarded for this sequence of responses
     */
    public function compute_final_grade($responses, $totaltries): float {
        $obtainedgrade = 0;
        $maxgrade = 0;

        foreach ($this->parts as $part) {
            $maxgrade += $part->answermark;

            // We start with an empty last response.
            $lastresponse = [];
            $lastchange = 0;

            $partfraction = 0;

            foreach ($responses as $responseindex => $response) {
                // If the response has not changed, we have nothing to do.
                if ($part->is_same_response($lastresponse, $response)) {
                    continue;
                }

                $response = $this->normalize_response($response);

                // Otherwise, save this as the last response and store the index where
                // the response was changed for the last time.
                $lastresponse = $response;
                $lastchange = $responseindex;

                // Obtain the grade for the current response.
                $partgrade = $part->grade($response);

                $partfraction = $partgrade['answer'];
                // If unit is wrong, make the necessary deduction.
                if ($partgrade['unit'] === false) {
                    $partfraction = $partfraction * (1 - $part->unitpenalty);
                }
            }
            $obtainedgrade += $part->answermark * max(0,  $partfraction - $lastchange * $this->penalty);
        }

        return $obtainedgrade / $maxgrade;
    }

    /**
     * Set up an evaluator class for every part and have it evaluate the local variables.
     *
     * @return void
     */
    public function initialize_part_evaluators(): void {
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

    /**
     * Normalize student response for each part, i. e. split number and unit for combined answer
     * fields, trim answers and set missing answers to empty string to make sure all expected
     * response fields are set.
     *
     * @param array $response the student's response
     * @return array normalized response
     */
    public function normalize_response(array $response): array {
        $result = [];

        // Normalize the responses for each part.
        foreach ($this->parts as $part) {
            $result += $part->normalize_response($response);
        }

        // Set the 'normalized' key in order to mark the response as normalized; this is useful for
        // certain other functions, because it changes a combined field e.g. from 0_ to 0_0 and 0_1.
        $result['normalized'] = true;

        return $result;
    }
}

/**
 * Class to represent a question subpart, loaded from the question_answers table
 * in the database.
 *
 * @copyright  2012 Jean-Michel Védrine, 2023 Philipp Imhof
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class qtype_formulas_part {

    /** @var ?evaluator the part's evaluator class */
    public ?evaluator $evaluator = null;

    /** @var array store the evaluated model answer(s) */
    public array $evaluatedanswers = [];

    /** @var int the part's id */
    public int $id;

    /** @var int the parents question's id */
    public int $questionid;

    /** @var int the part's position among all parts of the question */
    public int $partindex;

    /** @var string the part's placeholder, e.g. #1 */
    public string $placeholder;

    /** @var float the maximum grade for this part */
    public float $answermark;

    /** @var int answer type (number, numerical, numerical formula, algebraic) */
    public int $answertype;

    /** @var int number of answer boxes (not including a possible unit box) for this part */
    public int $numbox;

    /** @var string definition of local variables */
    public string $vars1;

    /** @var string definition of grading variables */
    public string $vars2;

    /** @var string definition of the model answer(s) */
    public string $answer;

    /** @var int whether there are multiple possible answers */
    public int $answernotunique;

    /** @var string definition of the grading criterion */
    public string $correctness;

    /** @var float deduction for a wrong unit */
    public float $unitpenalty;

    /** @var string unit */
    public string $postunit;

    /** @var int the set of basic unit conversion rules to be used */
    public int $ruleid;

    /** @var string additional conversion rules for other accepted base units */
    public string $otherrule;

    /** @var string the part's text */
    public string $subqtext;

    /** @var int format constant (FORMAT_MOODLE, FORMAT_HTML, FORMAT_PLAIN or FORMAT_MARKDOWN) */
    public int $subqtextformat;

    /** @var string general feedback for the part */
    public string $feedback;

    /** @var int format constant (FORMAT_MOODLE, FORMAT_HTML, FORMAT_PLAIN or FORMAT_MARKDOWN) */
    public int $feedbackformat;

    /** @var string part's feedback for any correct response */
    public string $partcorrectfb;

    /** @var int format constant (FORMAT_MOODLE, FORMAT_HTML, FORMAT_PLAIN or FORMAT_MARKDOWN) */
    public int $partcorrectfbformat;

    /** @var string part's feedback for any partially correct response */
    public string $partpartiallycorrectfb;

    /** @var int format constant (FORMAT_MOODLE, FORMAT_HTML, FORMAT_PLAIN or FORMAT_MARKDOWN) */
    public int $partpartiallycorrectfbformat;

    /** @var string part's feedback for any incorrect response */
    public string $partincorrectfb;

    /** @var int format constant (FORMAT_MOODLE, FORMAT_HTML, FORMAT_PLAIN or FORMAT_MARKDOWN) */
    public int $partincorrectfbformat;

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
     *
     * @return bool
     */
    public function has_combined_unit_field(): bool {
        // In order to have a combined unit field, we must first assure that:
        // - there is a unit
        // - there is not more than one answer box
        // - the answer is not of the type algebraic formula.
        if (!$this->has_unit() || $this->numbox > 1 || $this->answertype == qtype_formulas::ANSWER_TYPE_ALGEBRAIC) {
            return false;
        }

        // Furthermore, there must be either a {_0}{_u} without whitespace in the part's text
        // (meaning the user explicitly wants a combined unit field) or no answer box placeholders
        // at all, neither for the answer nor for the unit.
        $combinedrequested = strpos($this->subqtext, '{_0}{_u}') !== false;
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
     * is called by the main question's {@see get_expected_data()} method.
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
     * - {_n:str:MCS} for *shuffled* radio buttons, str must be a variable name
     * - {_n:str:MCE} for a drop down field, MCE must be verbatim
     * - {_n:str:MCES} for a *shuffled* drop down field, MCE must be verbatim
     * Note: {_0:MCE} is valid and will lead to radio boxes based on the variable MCE.
     * Every answer box in the array will itself be an associative array with the
     * keys 'placeholder' (the entire placeholder), 'options' (the name of the variable containing
     * the options for the radio list or the dropdown) and 'dropdown' (true or false).
     * The method is declared static in order to allow its usage during form validation when
     * there is no actual question object.
     * TODO: allow {_n|50px} or {_n|10} to control size of the input field
     *
     * @param string $text string to be parsed
     * @return array
     */
    public static function scan_for_answer_boxes(string $text): array {
        // Match the text and store the matches.
        preg_match_all('/\{(_u|_\d+)(:(_[A-Za-z]|[A-Za-z]\w*)(:(MCE|MCS|MCES))?)?((\|[\w =#]*)*)\}/', $text, $matches);

        $boxes = [];

        // The array $matches[1] contains the matches of the first capturing group, i. e. _1 or _u.
        foreach ($matches[1] as $i => $match) {
            // Duplicates are not allowed.
            if (array_key_exists($match, $boxes)) {
                throw new Exception(get_string('error_answerbox_duplicate', 'qtype_formulas', $match));
            }
            // The array $matches[0] contains the entire pattern, e.g. {_1:var:MCE} or simply {_3}. This
            // text is later needed to replace the placeholder by the input element.
            // With $matches[3], we can access the name of the variable containing the options for the radio
            // boxes or the drop down list.
            // Finally, the array $matches[4] will contain ':MCE', ':MCES' or ':MCS' in case this has been
            // specified. Otherwise, there will be an empty string.
            // TODO: add option 'size' (for characters) or 'width' (for pixel width).
            $boxes[$match] = [
                'placeholder' => $matches[0][$i],
                'options' => $matches[3][$i],
                'dropdown' => (substr($matches[4][$i], 0, 4) === ':MCE'),
                'shuffle' => (substr($matches[4][$i], -1) === 'S'),
                'format' => self::parse_box_formatting_options(substr($matches[6][$i], 1)),
            ];
        }
        return $boxes;
    }

    /**
     * FIXME
     *
     * @param string $settings
     * @return array
     */
    protected static function parse_box_formatting_options(string $settings): array {
        $options = explode('|', $settings);

        $result = [];
        foreach ($options as $option) {
            if (strstr($option, '=') === false) {
                continue;
            }
            $namevalue = explode('=', $option);
            $result[$namevalue[0]] = $namevalue[1];
        }

        return $result;
    }

    /**
     * Produce a plain text summary of a response for the part.
     *
     * @param array $response a response, as might be passed to {@see grade_response()}.
     * @return string a plain text summary of that response, that could be used in reports.
     */
    public function summarise_response(array $response) {
        $summary = [];

        $isnormalized = array_key_exists('normalized', $response);

        // If the part has a combined unit field, we want to have the number and the unit
        // to appear together in the summary.
        if ($this->has_combined_unit_field()) {
            // If the answer is normalized, the combined field has already been split, so we
            // recombine both parts.
            if ($isnormalized) {
                return trim($response["{$this->partindex}_0"] . " " . $response["{$this->partindex}_1"]);
            }

            // Otherwise, we check whether the key 0_ or similar is present in the response. If it is,
            // we return that value.
            if (isset($response["{$this->partindex}_"])) {
                return $response["{$this->partindex}_"];
            }
        }

        // Iterate over all expected answer fields and if there is a corresponding
        // answer in $response, add its value to the summary array.
        foreach (array_keys($this->get_expected_data()) as $key) {
            if (array_key_exists($key, $response)) {
                $summary[] = $response[$key];
            }
        }

        // Transform the array to a comma-separated list for a nice summary.
        return implode(', ', $summary);
    }

    /**
     * Normalize student response for current part, i. e. split number and unit for combined answer
     * fields, trim answers and set missing answers to empty string to make sure all expected
     * response fields are set.
     *
     * @param array $response student's full response
     * @return array normalized response for this part only
     */
    public function normalize_response(array $response): array {
        $result = [];

        // There might be a combined field for number and unit which would be called i_.
        // We check this first. A combined field is only possible, if there is not more
        // than one answer, so we can safely use i_0 for the number and i_1 for the unit.
        $name = "{$this->partindex}_";
        if (isset($response[$name])) {
            $combined = trim($response[$name]);

            // We try to parse the student's response in order to find the position where
            // the unit presumably starts. If parsing fails (e. g. because there is a syntax
            // error), we consider the entire response to be the "number". It will later be graded
            // wrong anyway.
            try {
                $parser = new answer_parser($combined);
                $splitindex = $parser->find_start_of_units();
            } catch (Throwable $t) {
                // TODO: convert to non-capturing catch.
                $splitindex = PHP_INT_MAX;
            }

            $number = trim(substr($combined, 0, $splitindex));
            $unit = trim(substr($combined, $splitindex));

            $result["{$name}0"] = $number;
            $result["{$name}1"] = $unit;
            return $result;
        }

        // Otherwise, we iterate from 0 to numbox, inclusive (if there is a unit field) or exclusive.
        $count = $this->numbox;
        if ($this->has_unit()) {
            $count++;
        }
        for ($i = 0; $i < $count; $i++) {
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
     * i. e. if all fields have been filled. This method can be called before the
     * response is normalized, so we cannot be sure all array keys exist as we would
     * expect them.
     *
     * @param array $response
     * @return bool
     */
    public function is_complete_response(array $response): bool {
        // First, we check if there is a combined unit field. In that case, there will
        // be only one field to verify.
        if ($this->has_combined_unit_field()) {
            return !empty($response["{$this->partindex}_"]) && strlen($response["{$this->partindex}_"]) > 0;
        }

        // If we are still here, we do now check all "normal" fields. If one is empty,
        // we can return early.
        for ($i = 0; $i < $this->numbox; $i++) {
            if (!isset($response["{$this->partindex}_{$i}"]) || strlen($response["{$this->partindex}_{$i}"]) == 0) {
                return false;
            }
        }

        // Finally, we check whether there is a separate unit field and, if necessary,
        // make sure it is not empty.
        if ($this->has_separate_unit_field()) {
            return !empty($response["{$this->partindex}_{$this->numbox}"])
                && strlen($response["{$this->partindex}_{$this->numbox}"]) > 0;
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
        if (!array_key_exists('normalized', $response)) {
            $response = $this->normalize_response($response);
        }

        // Check all answer boxes (including a possible unit) of this part. If at least one is not empty,
        // the part has been answered. If there is a unit field, we will check this in the same way, even
        // if it should not actually be numeric. We don't need to care about that, because a wrong answer
        // is still an answer. Note that $response will contain *all* answers for *all* parts.
        $count = $this->numbox;
        if ($this->has_unit()) {
            $count++;
        }
        for ($i = 0; $i < $count; $i++) {
            // If the answer field is not empty or it is equivalent to zero, we consider
            // the part as answered and leave early.
            $tocheck = $response["{$this->partindex}_{$i}"];
            if (!empty($tocheck) || is_numeric($tocheck)) {
                return false;
            }
        }

        // Still here? Then no fields were filled.
        return true;
    }

    /**
     * Return the part's evaluated answers. The teacher has probably entered them using the
     * various (random, global and/or local) variables. This function calculates the numerical
     * value of all answers. If the answer type is 'algebraic formula', the answers are not
     * evaluated (this would not make sense), but the non-algebraic variables will be replaced
     * by their respective values, e.g. "a*x^2" could become "2*x^2" if the variable a is defined
     * to be 2.
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

        $parser = new parser($this->answer, $this->evaluator->export_variable_list());
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
     * This function takes an array of algebraic formulas and wraps them in quotes. This is
     * needed e.g. before we feed them to the parser for further processing.
     *
     * @param array $formulas the formulas to be wrapped in quotes
     * @return array array containing wrapped formulas
     */
    private static function wrap_algebraic_formulas_in_quotes(array $formulas): array {
        foreach ($formulas as &$formula) {
            // If the formula is aready wrapped in quotes (e. g. after an earlier call to this
            // function), there is nothing to do.
            if (preg_match('/^\"[^\"]*\"$/', $formula)) {
                continue;
            }

            $formula = '"' . $formula . '"';
        }
        // In case we later write to $formula, this would alter the last entry of the $formulas
        // array, so we'd better remove the reference to make sure this won't happen.
        unset($formula);

        return $formulas;
    }

    /**
     * Check whether algebraic formulas contain a PREFIX operator.
     *
     * @param array $formulas the formulas to check
     * @return bool
     */
    private static function contains_prefix_operator(array $formulas): bool {
        foreach ($formulas as $formula) {
            $lexer = new lexer($formula);

            foreach ($lexer->get_tokens() as $token) {
                if ($token->type === token::PREFIX) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Add several special variables to the question part's evaluator, namely
     * _a for the model answers (array)
     * _r for the student's response (array)
     * _d for the differences between _a and _r (array)
     * _0, _1 and so on for the student's individual answers
     * _err for the absolute error
     * _relerr for the relative error, if applicable
     *
     * @param array $studentanswers the student's response
     * @param float $conversionfactor unit conversion factor, if needed (1 otherwise)
     * @param bool $formodelanswer whether we are doing this to test model answers, i. e. PREFIX operator is allowed
     * @return void
     */
    public function add_special_variables(array $studentanswers, float $conversionfactor, bool $formodelanswer = false): void {
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
            // Students are not allowed to use the PREFIX operator. If they do, we drop out
            // here. Throwing an exception will make sure the grading function awards zero points.
            // Also, we disallow usage of the PREFIX in an algebraic formula's model answer, because
            // that would lead to bad feedback (showing the student a "correct" answer that they cannot
            // type in). In this case, we use a different error message.
            if (self::contains_prefix_operator($studentanswers)) {
                if ($formodelanswer) {
                    throw new Exception(get_string('error_model_answer_prefix', 'qtype_formulas'));
                }
                throw new Exception(get_string('error_prefix', 'qtype_formulas'));
            }
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

        $parser = new parser($command, $this->evaluator->export_variable_list());
        $this->evaluator->evaluate($parser->get_statements(), true);
    }

    /**
     * Grade the part and return its grade.
     *
     * @param array $response current response
     * @param bool $finalsubmit true when the student clicks "submit all and finish"
     * @return array 'answer' => grade (0...1) for this response, 'unit' => whether unit is correct (bool)
     */
    public function grade(array $response, bool $finalsubmit = false): array {
        $isalgebraic = $this->answertype == qtype_formulas::ANSWER_TYPE_ALGEBRAIC;

        // Normalize the student's response for this part, removing answers from other parts.
        $response = $this->normalize_response($response);

        // Store the unit as entered by the student and get rid of this part of the
        // array, leaving only the inputs from the number fields.
        $studentsunit = '';
        if ($this->has_unit()) {
            $studentsunit = trim($response["{$this->partindex}_{$this->numbox}"]);
            unset($response["{$this->partindex}_{$this->numbox}"]);
        }

        // For now, let's assume the unit is correct.
        $unitcorrect = true;

        // Check whether the student's unit is compatible, i. e. whether it can be converted to
        // the unit set by the teacher. If this is the case, we calculate the conversion factor.
        // Otherwise, we set $unitcorrect to false and let the conversion factor be 1, so the
        // result will not be "scaled".
        $conversionfactor = $this->is_compatible_unit($studentsunit);
        if ($conversionfactor === false) {
            $conversionfactor = 1;
            $unitcorrect = false;
        }

        // The response array does not contain the unit anymore. If we are dealing with algebraic
        // formulas, we must wrap the answers in quotes before we move on. Also, we reset the conversion
        // factor, because it is not needed for algebraic answers.
        if ($isalgebraic) {
                $response = self::wrap_algebraic_formulas_in_quotes($response);
            $conversionfactor = 1;
        }

        // Now we iterate over all student answers, feed them to the parser and evaluate them in order
        // to build an array containing the evaluated response.
        $evaluatedresponse = [];
        foreach ($response as $answer) {
            try {
                // Using the known variables upon initialisation allows the teacher to "block"
                // certain built-in functions for the student by overwriting them, e. g. by
                // defining "sin = 1" in the global variables.
                $parser = new answer_parser($answer, $this->evaluator->export_variable_list());

                // Check whether the answer is valid for the given answer type. If it is not,
                // we just throw an exception to make use of the catch block. Note that if the
                // student's answer was empty, it will fail in this check.
                if (!$parser->is_acceptable_for_answertype($this->answertype)) {
                    throw new Exception();
                }

                // Make sure the stack is empty, as there might be left-overs from a previous
                // failed evaluation, e.g. caused by an invalid answer.
                $this->evaluator->clear_stack();

                $evaluated = $this->evaluator->evaluate($parser->get_statements())[0];
                $evaluatedresponse[] = token::unpack($evaluated);
            } catch (Throwable $t) {
                // TODO: convert to non-capturing catch
                // If parsing, validity check or evaluation fails, we consider the answer as wrong.
                // The unit might be correct, but that won't matter.
                return ['answer' => 0, 'unit' => $unitcorrect];
            }
        }

        // Add correctness variables using the evaluated response.
        try {
            $this->add_special_variables($evaluatedresponse, $conversionfactor);
        } catch (Exception $e) {
            // TODO: convert to non-capturing catch
            // If the special variables cannot be evaluated, the answer will be considered as
            // wrong. Partial credit may be given for the unit. We do not carry on, because
            // evaluation of the grading criterion (and possibly grading variables) generally
            // depends on these special variables.
            return ['answer' => 0, 'unit' => $unitcorrect];
        }

        // Fetch and evaluate grading variables.
        $gradingparser = new parser($this->vars2);
        try {
            $this->evaluator->evaluate($gradingparser->get_statements());
        } catch (Exception $e) {
            // TODO: convert to non-capturing catch
            // If grading variables cannot be evaluated, the answer will be considered as
            // wrong. Partial credit may be given for the unit. Thus, we do not need to
            // carry on.
            return ['answer' => 0, 'unit' => $unitcorrect];
        }

        // Fetch and evaluate the grading criterion. If evaluation is not possible,
        // set grade to 0.
        $correctnessparser = new parser($this->correctness);
        try {
            $evaluatedgrading = $this->evaluator->evaluate($correctnessparser->get_statements())[0];
            $evaluatedgrading = $evaluatedgrading->value;
        } catch (Exception $e) {
            // TODO: convert to non-capturing catch.
            $evaluatedgrading = 0;
        }

        // Restrict the grade to the closed interval [0,1].
        $evaluatedgrading = min($evaluatedgrading, 1);
        $evaluatedgrading = max($evaluatedgrading, 0);

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
     * Return an array containing the correct answers for this question part. If $forfeedback
     * is set to true, multiple choice answers are translated from their list index to their
     * value (e.g. the text) to provide feedback to the student. Also, quotes are stripped
     * from algebraic formulas. Otherwise, the function returns the values as they are needed
     * to obtain full mark at this question.
     *
     * @param bool $forfeedback whether we request correct answers for student feedback
     * @return array list of correct answers
     */
    public function get_correct_response(bool $forfeedback = false): array {
        // Fetch the evaluated answers.
        $answers = $this->get_evaluated_answers();

        // Numeric answers should be localized, if that functionality is enabled.
        foreach ($answers as &$answer) {
            if (is_numeric($answer)) {
                $answer = qtype_formulas::format_float($answer);
            }
        }
        // Make sure we do not accidentally write to $answer later.
        unset($answer);

        // If we have a combined unit field, we return both the model answer plus the unit
        // in "i_". Combined fields are only possible for parts with one signle answer.
        if ($this->has_combined_unit_field()) {
            return ["{$this->partindex}_" => trim($answers[0] . ' ' . $this->postunit)];
        }

        // As algebraic formulas are not numbers, we must replace the decimal point separately.
        // Also, if the answer is requested for feedback, we must strip the quotes.
        // Strip quotes around algebraic formulas, if the answers are used for feedback.
        if ($this->answertype === qtype_formulas::ANSWER_TYPE_ALGEBRAIC) {
            if (get_config('qtype_formulas', 'allowdecimalcomma')) {
                $answers = str_replace('.', get_string('decsep', 'langconfig'), $answers);
            }

            if ($forfeedback) {
                $answers = str_replace('"', '', $answers);
            }
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

        if ($forfeedback) {
            $res = $this->translate_mc_answers_for_feedback($res);
        }

        return $res;
    }

    /**
     * When using multichoice options in a question (e.g. radio buttons or a dropdown list),
     * the answer will be stored as a number, e.g. 1 for the *second* option. However, when
     * giving feedback to the student, we want to show the actual *value* of the option and not
     * its number. This function makes sure the stored answer is translated accordingly.
     *
     * @param array $response the response given by the student
     * @return array updated response
     */
    public function translate_mc_answers_for_feedback(array $response): array {
        // First, we fetch all answer boxes.
        $boxes = self::scan_for_answer_boxes($this->subqtext);

        foreach ($boxes as $key => $box) {
            // If it is not a multiple choice answer, we have nothing to do.
            if ($box['options'] === '') {
                continue;
            }

            // Name of the array containing the choices.
            $source = $box['options'];

            // Student's choice.
            $userschoice = $response["{$this->partindex}$key"];

            // Fetch the value.
            $parser = new parser("{$source}[$userschoice]");
            try {
                $result = $this->evaluator->evaluate($parser->get_statements()[0]);
                $response["{$this->partindex}$key"] = $result->value;
            } catch (Exception $e) {
                // TODO: convert to non-capturing catch
                // If there was an error, we leave the value as it is. This should
                // not happen, because upon creation of the question, we check whether
                // the variable containing the choices exists.
                debugging('Could not translate multiple choice index back to its value. This should not happen. ' .
                    'Please file a bug report.');
            }
        }

        return $response;
    }


    /**
     * If the part is not correctly answered, we will set all answers to the empty string. Otherwise, we
     * just return an empty array. This function will be called by the main question (for every part) and
     * will be used to reset answers from wrong parts.
     *
     * @param array $response student's response
     * @return array either an empty array (if part is correct) or an array with all answers being the empty string
     */
    public function clear_from_response_if_wrong(array $response): array {
        // First, we have the response graded.
        list('answer' => $answercorrect, 'unit' => $unitcorrect) = $this->grade($response);

        // If the part's answer is correct (including the unit, if any), we return an empty array.
        // The caller of this function uses our values to overwrite the ones in the response, so
        // that's fine.
        if ($answercorrect >= 0.999 && $unitcorrect) {
            return [];
        }

        $result = [];
        foreach (array_keys($this->get_expected_data()) as $key) {
            $result[$key] = '';
        }
        return $result;
    }


}
