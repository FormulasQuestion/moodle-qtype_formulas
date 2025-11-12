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

use qtype_formulas\local\evaluator;
use qtype_formulas\local\formulas_part;
use qtype_formulas\local\random_parser;
use qtype_formulas\local\parser;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/question/type/formulas/classes/local/formulas_part.php');
require_once($CFG->dirroot . '/question/type/formulas/questiontype.php');
require_once($CFG->dirroot . '/question/type/formulas/answer_unit.php');
require_once($CFG->dirroot . '/question/type/formulas/conversion_rules.php');
require_once($CFG->dirroot . '/question/behaviour/adaptivemultipart/behaviour.php');

// phpcs:disable moodle.Files.LineLength.TooLong

/**
 * Base class for the Formulas question type.
 *
 * @copyright 2010-2011 Hon Wai, Lau; 2023 Philipp Imhof
 * @author Hon Wai, Lau <lau65536@gmail.com>
 * @author Philipp Imhof
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class qtype_formulas_question extends question_graded_automatically_with_countback implements question_automatically_gradable_with_multiple_parts {
    // phpcs:enable moodle.Files.LineLength.TooLong
    /** @var int seed used to initialize the RNG; needed to restore an attempt state */
    public int $seed;

    /** @var ?evaluator evaluator class, this is where the evaluation stuff happens */
    public ?evaluator $evaluator = null;

    /** @var string $varsrandom definition text for random variables, as entered in the edit form */
    public string $varsrandom;

    /** @var string $varsglobal definition text for the question's global variables, as entered in the edit form */
    public string $varsglobal;

    /** @var formulas_part[] parts of the question */
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
            '/\{(_u|_\d+)(:\s*([A-Za-z][A-Za-z_0-9]*)\s*(:(MC|MCE|MCS|MCES))?)?((\|[A-Za-z0-9_ .=#]*)*)\}/u',
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
            ['answer' => $answercorrect, 'unit' => $unitcorrect] = $part->grade($response);

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
     * @param formulas_part|null $part model answer for every answer / unit box of each part
     * @return array model answer for every answer / unit box of each part
     */
    public function get_correct_response(?formulas_part $part = null): array {
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
                [$ignored, $state] = $this->grade_response($response);
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
            ['answer' => $answergrade, 'unit' => $unitcorrect] = $part->grade($response);

            if ($part->postunit !== '') {
                // The unit can only be correct (1.0) or wrong (0.0).
                // The answer can be any float from 0.0 to 1.0 inclusive.
                if ($answergrade >= 0.999 && $unitcorrect) {
                    $classification[$part->partindex] = new question_classified_response(
                        'right',
                        $part->summarise_response($response),
                        1,
                    );
                } else if ($unitcorrect) {
                    $classification[$part->partindex] = new question_classified_response(
                        'wrongvalue',
                        $part->summarise_response($response),
                        0,
                    );
                } else if ($answergrade >= 0.999) {
                    $classification[$part->partindex] = new question_classified_response(
                        'wrongunit',
                        $part->summarise_response($response),
                        1 - $part->unitpenalty,
                    );
                } else {
                    $classification[$part->partindex] = new question_classified_response(
                        'wrong',
                        $part->summarise_response($response),
                        0,
                    );
                }
            } else {
                if ($answergrade >= .999) {
                    $classification[$part->partindex] = new question_classified_response(
                        'right',
                        $part->summarise_response($response),
                        $answergrade,
                    );
                } else {
                     $classification[$part->partindex] = new question_classified_response(
                         'wrong',
                         $part->summarise_response($response),
                         $answergrade,
                     );
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
                $part->partindex,
                $fraction,
                $this->penalty,
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
            $obtainedgrade += $part->answermark * max(0, $partfraction - $lastchange * $this->penalty);
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
