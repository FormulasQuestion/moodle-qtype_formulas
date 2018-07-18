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
 * Moodle formulas question definition class.
 *
 * @package    qtype_formulas
 * @copyright  2010-2011 Hon Wai, Lau
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/question/type/formulas/questiontype.php');
require_once($CFG->dirroot . '/question/type/formulas/variables.php');
require_once($CFG->dirroot . '/question/type/formulas/answer_unit.php');
require_once($CFG->dirroot . '/question/type/formulas/conversion_rules.php');
require_once($CFG->dirroot . '/question/behaviour/adaptivemultipart/behaviour.php');

/**
 * Base class for formulas questions.
 *
 * @copyright  2010-2011 Hon Wai, Lau
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class qtype_formulas_question extends question_graded_automatically_with_countback
        implements question_automatically_gradable_with_multiple_parts {
    /**
     * @var int: number of formulas_parts for the question.
     */
    public $numpart;
    /**
     * @var array of qtype_formulas_part, the $numpart parts of the question.
     */
    public $parts = array();
    /**
     * @var array of strings, one longer than $numpart, which is achieved by
     * indexing from 0. The bits of question text that go between the parts.
     */
    public $textfragments;
    /** These array may be used some day to store results ? */
    public $evaluatedanswer = array();
    public $fractions = array();
    public $anscorrs = array();
    public $unitcorrs = array();

    public $localvars = array();
    public $varsrandom;
    /** global variables serialized as string (as saved in database) */
    public $varsglobal;
    /** qtype_formulas_variables */
    public $qv;
    /** instancied random variables  */
    public $randomsvars;
    /** instancied random variables serialized as string (as saved in database) */
    public $randomsvarstext;

    public function make_behaviour(question_attempt $qa, $preferredbehaviour) {
        if ($preferredbehaviour == 'adaptive' || $preferredbehaviour == 'adaptivenopenalty') {
            return question_engine::make_behaviour('adaptivemultipart', $qa, $preferredbehaviour);
        }
        return parent::make_behaviour($qa, $preferredbehaviour);
    }
    /**
     * What data may be included in the form submission when a student enter a response.
     * The number before the _ is the part index (starting at 0),
     * and the number after the _ is the coordinate index (starting at 0).
     * For instance "2_3" is anwer for fourth coordinate of third part.
     * When there is a separated unit response for part index i, it is called "i_n"
     * where n is the number of coordinates for part i.
     * Sor for instance if part 2 has 3 coordinates and a separate unit response,
     * we will have responses names 2_0, 2_1, 2_2, 2_3 (last one is for unit)
     * When there is a combined answer&unit field for part index i, it is simply called "i_"
     * So for instance if part index 2 has a combined answer&unit response, its name will be "2_"
     * and will be equivalent to separate anser and unit response "2_0" and "2_1".
     */
    public function get_expected_data() {
        $expected = array();
        foreach ($this->parts as $part) {
            $expected += $part->part_get_expected_data();
        }
        return $expected;
    }

    /**
     * Start a new attempt at this question, storing any information that will
     * be needed later in the step.
     *
     * This is where the question can do any initialisation required on a
     * per-attempt basis. For example, this is where the multiple choice
     * question type randomly shuffles the choices (if that option is set).
     *
     * Any information about how the question has been set up for this attempt
     * should be stored in the $step, by calling $step->set_qt_var(...).
     *
     * @param question_attempt_step The first step of the {@link question_attempt}
     *      being started. Can be used to store state.
     * @param int $variant which variant of this question to start. Will be between
     *      1 and {@link get_num_variants()} inclusive.
     */
    public function start_attempt(question_attempt_step $step, $variant) {
        try {
            $vstack = $this->qv->parse_random_variables($this->varsrandom);
            $this->randomsvars = $this->qv->instantiate_random_variables($vstack);
            $this->randomsvarstext = $this->qv->vstack_get_serialization($this->randomsvars);
            $step->set_qt_var('_randomsvars_text', $this->randomsvarstext);
            $step->set_qt_var('_varsglobal', $this->varsglobal);

            return true;    // Success.
        } catch (Exception $e) {
            return false;   // Fail.
        }

    }

    /**
     * When an in-progress {@link question_attempt} is re-loaded from the
     * database, this method is called so that the question can re-initialise
     * its internal state as needed by this attempt.
     *
     * For example, the multiple choice question type needs to set the order
     * of the choices to the order that was set up when start_attempt was called
     * originally. All the information required to do this should be in the
     * $step object, which is the first step of the question_attempt being loaded.
     *
     * @param question_attempt_step The first step of the {@link question_attempt}
     *      being loaded.
     */
    public function apply_attempt_state(question_attempt_step $step) {
        $this->randomsvarstext = $step->get_qt_var('_randomsvars_text');
        $this->varsglobal = $step->get_qt_var('_varsglobal');
        $this->randomsvars = $this->qv->evaluate_assignments($this->qv->vstack_create(), $this->randomsvarstext);

        parent::apply_attempt_state($step);
    }

    /**
     * Replace variables with their values
     * and apply format_text() to some text.
     *
     * @param $vars
     * @param string $text some content that needs to be output.
     * @param int $format the FORMAT_... constant.
     * @param question_attempt $qa the question attempt.
     * @param string $component used for rewriting file area URLs.
     * @param string $filearea used for rewriting file area URLs.
     * @param bool $clean Whether the HTML needs to be cleaned. Generally,
     *      parts of the question do not need to be cleaned, and student input does.
     * @return string the text formatted for output by format_text.
     */
    public function formulas_format_text($vars, $text, $format, $qa, $component, $filearea, $itemid,
            $clean = false) {
        return $this->format_text($this->qv->substitute_variables_in_text($vars, $text),
                 $format, $qa, $component, $filearea, $itemid, $clean);
    }

    /**
     * This has to be a formulas-specific method
     * so that global variables are replaced by their values.
     */
    public function format_generalfeedback($qa) {
        $globalvars = $this->get_global_variables();
        return $this->formulas_format_text($globalvars, $this->generalfeedback, $this->generalfeedbackformat,
                $qa, 'question', 'generalfeedback', $this->id, false);
    }

    /**
     * Generate a brief, plain-text, summary of this question. This is used by
     * various reports. This should show the particular variant of the question
     * as presented to students. For example, the calculated quetsion type would
     * fill in the particular numbers that were presented to the student.
     * This method will return null if such a summary is not possible, or
     * inappropriate.
     * @return string|null a plain text summary of this question.
     */
    public function get_question_summary() {
        $globalvars = $this->get_global_variables();
        $qtext = $this->qv->substitute_variables_in_text($globalvars, $this->questiontext);
        $summary = $this->html_to_text($qtext, $this->questiontextformat);
        foreach ($this->parts as $part) {
            $localvars = $this->get_local_variables($part);
            $subtext = $this->qv->substitute_variables_in_text($localvars, $part->subqtext);
            $answerbit = $this->html_to_text($subtext, $part->subqtextformat);
            if ($part->placeholder != '') {
                $summary = str_replace('{' . $part->placeholder . '}', $answerbit, $summary);
            } else {
                $summary .= $answerbit;
            }
        }
        return $summary;
    }

    /**
     * Given a response, rest the parts that are wrong.
     * @param array $response a response
     * @return array a cleaned up response with the wrong bits reset.
     */
    public function clear_wrong_from_response(array $response) {
        $this->rationalize_responses($response);
        $checkunit = new answer_unit_conversion;
        foreach ($this->parts as $part) {
            list( $answercorrect, $unitcorrect) = $this->grade_responses_individually($part, $response, $checkunit);
            if ($answercorrect * $unitcorrect < 1.0) {
                foreach (range(0, $part->numbox) as $j) {
                    if (array_key_exists($part->partindex . "_$j", $response)) {
                        $response[$part->partindex . "_$j"] = '';
                    }
                }
                if (array_key_exists($part->partindex . "_", $response)) {
                    $response[$part->partindex . "_"] = '';
                }
            }
        }
        return $response;
    }

     /**
      * Return the number of parts of the question
      */
    public function get_number_of_parts() {
        return $this->numpart;
    }

    /**
     * Return the number of subparts of this response that are right.
     * @param array $response a response
     * @return array with two elements, the number of correct subparts, and
     * the total number of subparts.
     */
    public function get_num_parts_right(array $response) {
        $this->rationalize_responses($response);      // May throw if subqtext have changed.
        $checkunit = new answer_unit_conversion;
        $c = 0;
        foreach ($this->parts as $part) {
            list( $answercorrect, $unitcorrect) = $this->grade_responses_individually($part, $response, $checkunit);
            if ($answercorrect * $unitcorrect >= .999) {
                $c++;
            }
        }
        return array($c, $this->numpart);
    }

    /**
     * What data would need to be submitted to get this question correct.
     * If there is more than one correct answer, this method should just
     * return one possibility.
     *
     * @return array parameter name => value.
     */
    public function get_correct_response() {
        $responses = array();
        foreach ($this->parts as $part) {
            $tmp = $this->get_correct_responses_individually($part);
            if ($tmp === null) {
                return array(); // TODO : really examine what to return in that case empty array or null ?
            }
            if ($part->part_has_combined_unit_field()) {
                $tmp[$part->partindex . "_"] = $tmp[$part->partindex . "_0"] . $tmp[$part->partindex . "_1"];
                unset($tmp[$part->partindex . "_0"], $tmp[$part->partindex . "_1"]);
            } else if (!$part->part_has_separate_unit_field()) {
                unset($tmp[$part->partindex . "_" . $part->numbox]);
            }
            $responses = array_merge($responses, $tmp);
        }
        return $responses;
    }

    /**
     * Used by many of the behaviours, to work out whether the student's
     * response to the question is complete. That is, whether the question attempt
     * should move to the COMPLETE or INCOMPLETE state.
     *
     * @param array $response responses, as returned by
     *      {@link question_attempt_step::get_qt_data()}.
     * @return bool whether this response is a complete answer to this question.
     */
    public function is_complete_response(array $response) {
        // TODO add tests to verify it works in all cases : combined and separate unit field, no unit field.
        $complete = true;
        foreach ($this->parts as $part) {
            if ($part->part_has_combined_unit_field()) {
                $complete = $complete && array_key_exists($part->partindex . "_", $response)
                        && $response[$part->partindex . "_"] !== '';
            } else {
                foreach (range(0, $part->numbox - 1) as $j) {
                    $complete = $complete && array_key_exists($part->partindex . "_$j", $response)
                            && $response[$part->partindex . "_$j"] !== '';
                }
                if ($part->part_has_separate_unit_field()) {
                    $complete = $complete && array_key_exists($part->partindex . "_" . $part->numbox, $response)
                            && $response[$part->partindex . "_" . $part->numbox] != '';
                }
            }
        }
        return $complete;
    }

    /**
     * Use by many of the behaviours to determine whether the student's
     * response has changed. This is normally used to determine that a new set
     * of responses can safely be discarded.
     *
     * @param array $prevresponse the responses previously recorded for this question,
     *      as returned by {@link question_attempt_step::get_qt_data()}
     * @param array $newresponse the new responses, in the same format.
     * @return bool whether the two sets of responses are the same - that is
     *      whether the new set of responses can safely be discarded.
     */
    public function is_same_response(array $prevresponse, array $newresponse) {
        foreach ($this->get_expected_data() as $name => $notused) {
            if (!question_utils::arrays_same_at_key_missing_is_blank(
                    $prevresponse, $newresponse, $name)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Are two responses the same insofar as a certain part is concerned. This is
     * used so we do not penalise the same mistake twice.
     * This is in fact just a wrapper for the part method because it is needed by
     * adaptive multipart behaviour.
     * @param string $part a part indentifier. Whether the two responses are the same
     *      for the given part.
     * @param array $prevresponse the responses previously recorded for this question,
     *      as returned by {@link question_attempt_step::get_qt_data()}
     * @param array $newresponse the new responses, in the same format.
     * @return bool whether the two sets of responses are the same for the given
     *      part.
     */
    public function is_same_response_for_part($i, array $prevresponse, array $newresponse) {
        return $this->parts[$i]->part_is_same_response($prevresponse, $newresponse);
    }

    /**
     * Produce a plain text summary of a response.
     * @param $response a response, as might be passed to {@link grade_response()}.
     * @return string a plain text summary of that response, that could be used in reports.
     */
    public function summarise_response(array $response) {
        $summary = array();
        foreach ($this->parts as $part) {
            $summary[] = $part->part_summarise_response($response);
        }
        $summary = implode(', ', $summary);
        return $summary;

    }

    /**
     * Categorise the student's response according to the categories defined by
     * get_possible_responses.
     * @param $response a response, as might be passed to {@link grade_response()}.
     * @return array subpartid => {@link question_classified_response} objects.
     *      returns an empty array if no analysis is possible.
     */
    public function classify_response(array $response) {
        $this->rationalize_responses($response);
        $classification = array();

        foreach ($this->parts as $part) {
            if ($part->part_is_unanswered($response)) {
                $classification[$part->partindex] = question_classified_response::no_response();
                continue;
            }
            $checkunit = new answer_unit_conversion;
            list($anscorr, $unitcorr)
                    = $this->grade_responses_individually($part, $response, $checkunit);

            if ($part->postunit != '') {
                if ($anscorr == 1 && $unitcorr == 1) {
                    $classification[$part->partindex] = new question_classified_response(
                            'right', $part->part_summarise_response($response), 1);
                }
                if ($anscorr == 0 && $unitcorr == 1) {
                    $classification[$part->partindex] = new question_classified_response(
                            'wrongvalue', $part->part_summarise_response($response), 0);
                }
                if ($anscorr == 1 && $unitcorr == 0) {
                    $classification[$part->partindex] = new question_classified_response(
                            'wrongunit', $part->part_summarise_response($response), 1 - $part->unitpenalty);
                }
                if ($anscorr == 0 && $unitcorr == 0) {
                    $classification[$part->partindex] = new question_classified_response(
                            'wrong', $part->part_summarise_response($response), 0);
                }
            } else {
                $fraction = $anscorr * ($unitcorr ? 1 : (1 - $part->unitpenalty));
                if ($fraction > .999) {
                    $classification[$part->partindex] = new question_classified_response(
                            'right', $part->part_summarise_response($response), $fraction);
                } else {
                     $classification[$part->partindex] = new question_classified_response(
                            'wrong', $part->part_summarise_response($response), $fraction);
                }
            }

        }
        return $classification;
    }

    /**
     * Use by many of the behaviours to determine whether the student
     * has provided enough of an answer for the question to be graded automatically,
     * or whether it must be considered aborted.
     *
     * @param array $response responses, as returned by
     *      {@link question_attempt_step::get_qt_data()}.
     * @return bool whether this response can be graded.
     */
    public function is_gradable_response(array $response) {
        // TODO is an unit alone enought to be gradable ? If I read Tim comment correctly, I think yes,
        // but in fact it depends on $part->unitpenalty.
        // TODO if student response is invalid decide what to do.
        foreach ($this->parts as $part) {
            foreach (range(0, $part->numbox) as $j) {
                if (array_key_exists($part->partindex . "_$j", $response) &&
                        ($response[$part->partindex . "_$j"] || $response[$part->partindex . "_$j"] === '0'
                                || $response[$part->partindex . "_$j"] === 0)) {
                    return true;
                }
            }
            if (array_key_exists($part->partindex . '_', $response) &&
                    ($response[$part->partindex . '_'] || $response[$part->partindex . '_'] === '0'
                            || $response[$part->partindex . '_'] === 0)) {
                return true;
            }
        }
        return false;
    }

    /**
     * In situations where is_gradable_response() returns false, this method
     * should generate a description of what the problem is.
     * @return string the message.
     */
    public function get_validation_error(array $response) {
        if ($this->is_complete_response($response)) {
            return '';
        }
        return get_string('pleaseputananswer', 'qtype_formulas');
    }

    /**
     * Grade a response to the question, returning a fraction between
     * get_min_fraction() and 1.0, and the corresponding {@link question_state}
     * right, partial or wrong.
     * @param array $response responses, as returned by
     *      {@link question_attempt_step::get_qt_data()}.
     * @return array (number, integer) the fraction, and the state.
     */
    public function grade_response(array $response) {
        // We cant' rely on question defaultmark for restored questions.
        $totalvalue = 0;
        try {
            $this->rationalize_responses($response);      // May throw if subqtext have changed.
            $checkunit = new answer_unit_conversion; // Defined here for the possibility of reusing parsed default set.
            foreach ($this->parts as $part) {
                list($this->anscorrs[$part->partindex], $this->unitcorrs[$part->partindex])
                        = $this->grade_responses_individually($part, $response, $checkunit);
                $this->fractions[$part->partindex] = $this->anscorrs[$part->partindex] * ($this->unitcorrs[$part->partindex] ? 1 : (1 - $part->unitpenalty));
                $this->raw_grades[$part->partindex] = $part->answermark * $this->fractions[$part->partindex];
                $totalvalue += $part->answermark;
            }
        } catch (Exception $e) {
            notify('Grading error! Probably result of incorrect import file or database corruption.');
            return false; // It should have no error when grading students question.
        }

        $fraction = array_sum($this->raw_grades) / $totalvalue;
        return array($fraction, question_state::graded_state_for_fraction($fraction));
    }

    // Compute the correct response for the given question part.
    public function get_correct_responses_individually($part) {
        try {
            $res = $this->get_evaluated_answer($part);
            // If the answer is algebraic formulas (i.e. string), then replace the variable with numeric value by their number.
            $localvars = $this->get_local_variables($part);
            if (is_string($res[0])) {
                $res = $this->qv->substitute_partial_formula($localvars, $res);
            }
        } catch (Exception $e) {
            return null;
        }

        foreach (range(0, count($res) - 1) as $j) {
            $responses[$part->partindex."_$j"] = $res[$j]; // Coordinates.
        }
        $tmp = explode('=', $part->postunit, 2);
        $responses[$part->partindex."_".count($res)] = $tmp[0];  // Postunit.
        return $responses;
    }

    // Compute the correct response for the given question part.
    // Formatted for display.
    public function correct_response_formatted($part) {
        $localvars = $this->get_local_variables($part);
        $tmp = $this->get_correct_responses_individually($part);
        // Get all part's answer boxes.
        $boxes = $part->part_answer_boxes($part->subqtext);

        // Find all multichoice coordinates in the part.
        foreach ($boxes as $key => $box) {
            if (strlen($box->options) != 0) { // It's a multichoice coordinate.
                // Calculate all the choices.
                try {
                    // Remove the : at the beginning of options and evaluate it.
                    $stexts = $this->qv->evaluate_general_expression($localvars, substr($box->options, 1));
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
        if ($part->part_has_combined_unit_field()) {
            $correctanswer = implode(' ', $tmp);
        } else {
            if (!$part->part_has_separate_unit_field()) {
                unset($tmp["{$part->partindex}_" . (count($tmp) - 1)]);
            }
            $correctanswer = implode(', ', $tmp);
        }
        return $correctanswer;
    }

    // Add the set of special variables that may be useful to check the correctness of the user input.
    public function add_special_correctness_variables(&$vars, $_a, $_r, $diff, $is_number) {
        // Calculate other special variables.
        $sum0 = $sum1 = $sum2 = 0;
        foreach ($_r as $idx => $coord) {
            $sum2 += $diff[$idx] * $diff[$idx];
        }
        $t = is_string($_r[0]) ? 's' : 'n';
        // Add the special variables to the variable pool for later grading.
        foreach ($_r as $idx => $coord) {
            $this->qv->vstack_update_variable($vars, '_'.$idx, null, $t, $coord);  // Individual scaled response.
        }
        $this->qv->vstack_update_variable($vars, '_r', null, 'l'.$t, $_r); // Array of scaled responses.
        $this->qv->vstack_update_variable($vars, '_a', null, 'l'.$t, $_a); // Array of model answers.
        $this->qv->vstack_update_variable($vars, '_d', null, 'ln', $diff); // Array of difference between responses and model answers.
        $this->qv->vstack_update_variable($vars, '_err', null, 'n', sqrt($sum2));   // Error in Euclidean space, L-2 norm, sqrt(sum(map("pow",_diff,2))).

        // Calculate the relative error. We only define relative error for number or numerical expression.
        if ($is_number) {
            $norm_sqr = 0;
            foreach ($_a as $idx => $coord) {
                $norm_sqr += $coord * $coord;
            }
            $relerr = $norm_sqr != 0 ? sqrt($sum2 / $norm_sqr) : ($sum2 == 0 ? 0 : 1e30); // If the model answer is zero, the answer from student must also match exactly.
            $this->qv->vstack_update_variable($vars, '_relerr', null, 'n', $relerr);
        }
    }

    // Check whether the format of the response is correct and evaluate the corresponding expression
    // @return difference between coordinate and model answer. null if format incorrect. Note: $r will have evaluated value.
    public function compute_response_difference(&$vars, &$a, &$r, $cfactor, $gradingtype) {
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
        } catch (Exception $e) {
            // Any error will return null.
        }
        if ($res->diff === null) {
            return null;
        }
        return $res;
    }

    // Grade response for part, and return a list with answer correctness and unit correctness.
    public function grade_responses_individually($part, $response, &$checkunit) {
        // Step 1: Split the student's responses to the part into coordinates and unit.
        $coordinates = array();
        $i = $part->partindex;
        foreach (range(0, $part->numbox - 1) as $j) {
            $coordinates[$j] = trim($response["${i}_$j"]);
        }
        $postunit = trim($response["${i}_{$part->numbox}"]);

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
        $modelanswers = $this->get_evaluated_answer($part);
        if (count($coordinates) != count($modelanswers)) {
            throw new Exception('Database record inconsistence: number of answers in part!');
        }

        // Step 6: Check the format of the student response and transform them into variables for grading later.
        $vars = $this->get_local_variables($part);     // Contains both global and local variables.
        $gradingtype = $part->answertype;
        $dres = $this->compute_response_difference($vars, $modelanswers, $coordinates, $cfactor, $gradingtype);
        if ($dres === null) {
            return array(0, $unitcorrect); // If the answer cannot be evaluated under the grading type.
        }
        $this->add_special_correctness_variables($vars, $modelanswers, $coordinates, $dres->diff, $dres->is_number);

        // Step 7: Evaluate the grading variables and grading criteria to determine whether the answer is correct.
        $vars = $this->qv->evaluate_assignments($vars, $part->vars2);
        $correctness = $this->qv->evaluate_general_expression($vars, $part->correctness);
        if ($correctness->type != 'n') {
            throw new Exception(get_string('error_criterion', 'qtype_formulas'));
        }

        // Step 8: Restrict the correctness value within 0 and 1 (inclusive). Also, all non-finite numbers are incorrect.
        $answercorrect = is_finite($correctness->value) ? min(max((float) $correctness->value, 0.0), 1.0) : 0.0;
        return array($answercorrect, $unitcorrect);
    }

    // Fill all 'missing' responses by default value and remove unwanted characters.
    public function rationalize_responses_for_part($part, array &$response) {
        foreach (range(0, $part->numbox) as $j) {
            $name = $part->partindex . "_$j";
            $response[$name] = isset($response[$name]) ? trim($response[$name]) : '';   // Replace all missing responses with an empty string.
            if (strlen($response[$name]) > 128) {
                $response[$name] = substr($response[$name], 0, 128);    // Restrict length to 128.
            }
        }
        if (isset($response[$part->partindex . "_"])) {   // For a combined answer box, always parse it into a number and unit, "i_0" and "i_1".
                $response[$part->partindex . "_"] = (string) substr(trim($response[$part->partindex . "_"]), 0, 128);
                $tmp = $this->qv->split_formula_unit($response[$part->partindex . "_"]);
                $response[$part->partindex . "_0"] = $tmp[0]; // It will be checked later if tmp[0] is a number.
                $response[$part->partindex . "_1"] = isset($tmp[1]) ? $tmp[1] : '';
        }   // The else case may occur if there is no submission for answer "i_", in which case "i_0" and "i_1" were already rationalized.
    }

    public function rationalize_responses(array &$response) {
        foreach ($this->parts as $part) {
            $this->rationalize_responses_for_part($part, $response);
        }
    }


    // Return the variable type and data in the global variable text defined in the formula question. May throw error.
    public function get_global_variables() {
        // TODO I don't understand why this is needed because it has been done in apply_attempt_state.
        $this->randomsvars = $this->qv->evaluate_assignments($this->qv->vstack_create(), $this->randomsvarstext);
        if (!isset($this->globalvars)) {
            // Perform lazy evaluation, when global variables don't already exist.
            $this->globalvars = $this->qv->evaluate_assignments($this->randomsvars, $this->varsglobal);
        }
        return $this->globalvars;
    }


    // Return the variable type and data in the local variable defined in the $part. May throw error.
    public function get_local_variables($part) {
        if (!isset($this->localvars[$part->partindex])) {
            // Perform lazy evaluation, when local variables don't already exist.
            $this->localvars[$part->partindex] = $this->qv->evaluate_assignments($this->get_global_variables(), $part->vars1);
        }
        return $this->localvars[$part->partindex];
    }

    /**
     * Grade those parts of the question that can be graded, and return the grades and penalties.
     * @param array $response the current response being processed. Response variable name => value.
     * @param array $lastgradedresponses array part name => $response array from the last
     *      time this part registered a try. If a particular part has not yet registered a
     *      try, then there will not be an entry in the array for it.
     * @param bool $finalsubmit set to true when the student click submit all and finish,
     *      since the question is ending, we make a final attempt to award the student as much
     *      credit as possible for what they did.
     * @return array part name => qbehaviour_adaptivemultipart_part_result. There should
     *      only be entries in this array for those parts of the question where this
     *      sumbission counts as a new try at that part.
     */
    public function grade_parts_that_can_be_graded(array $response, array $lastgradedresponses, $finalsubmit) {
        $partresults = array();
        $checkunit = new answer_unit_conversion;

        foreach ($this->parts as $part) {
            $name = (string) $part->partindex;
            if (array_key_exists($name, $lastgradedresponses)) {
                // There is a response for this part in the last graded responses array.
                $lastresponse = $lastgradedresponses[$name];
            } else {
                // No response in last graded responses array.
                $lastresponse = array();
            }

            if ($part->part_is_same_response($lastresponse, $response)) {
                // Response for that part has not changed.
                continue;
            }

            // In that case we need to grade the new response.
            $this->rationalize_responses_for_part($part, $response);
            list($anscorr, $unitcorr) = $this->grade_responses_individually($part, $response, $checkunit);
            $fraction = $anscorr * ($unitcorr ? 1 : (1 - $part->unitpenalty));
            $partresults[$name] = new qbehaviour_adaptivemultipart_part_result(
                    $name, $fraction, $this->penalty);
        }
        return $partresults;
    }

    /**
     * Get a list of all the parts of the question, and the weight they have within
     * the question.
     * @return array part identifier => weight. The sum of all the weights should be 1.
     */
    public function get_parts_and_weights() {
        $weights = array();
        foreach ($this->parts as $part) {
            $weights[$part->partindex] = $part->answermark;
        }
        $totalvalue = array_sum($weights);
        foreach ($weights as &$w) {
            $w /= $totalvalue;
        }
        return $weights;
    }

    /**
     * @param array $response the current response being processed. Response variable name => value.
     * @return bool true if any part of the response is invalid.
     */
    public function is_any_part_invalid(array $response) {
        // TODO find in what case a formulas part is to be considered as invalid.
        return false;
    }

    // Return the evaluated answer array (number will be converted to array). Throw on error.
    public function get_evaluated_answer($part) {
        if (!isset($this->evaluatedanswer[$part->partindex])) {   // Perform lazy evaluation.
            $vstack = $this->get_local_variables($part);
            $res = $this->qv->evaluate_general_expression($vstack, $part->answer);
            $this->evaluatedanswer[$part->partindex] = $res->type[0] == 'l' ? $res->value : array($res->value); // Convert to numbers array.
            $a = $res->type[strlen($res->type) - 1];
            if (($part->answertype == 1000 ? $a != 's' : $a != 'n')) {
                throw new Exception(get_string('error_answertype_mistmatch', 'qtype_formulas'));
            }
        }   // Perform the evaluation only when the local variable does not exist before.
        return $this->evaluatedanswer[$part->partindex]; // No type information needed, it returns numbers or strings array.
    }

    public function check_file_access($qa, $options, $component, $filearea, $args, $forcedownload) {
        $itemid = reset($args);
        if ($component == 'qtype_formulas' && ($filearea == 'answersubqtext' || $filearea == 'answerfeedback'
                || $filearea == 'partcorrectfb' || $filearea == 'partpartiallycorrectfb' || $filearea == 'partincorrectfb')) {
            // Check if answer id exists.
            for ($i = 0; $i < $this->numpart; $i++) {
                if ($this->parts[$i]->id == $itemid) {
                    return true;
                }
            }
            return false;
        } else if ($component == 'question' && in_array($filearea,
                array('correctfeedback', 'partiallycorrectfeedback', 'incorrectfeedback'))) {
            return $this->check_combined_feedback_file_access($qa, $options, $filearea, $args);
        } else if ($component == 'question' && $filearea == 'hint') {
            return $this->check_hint_file_access($qa, $options, $args);

        } else {
            return parent::check_file_access($qa, $options, $component, $filearea,
                    $args, $forcedownload);
        }

    }

    /**
     * Work out a final grade for this attempt, taking into account all the
     * tries the student made.
     * @param array $responses the response for each try. Each element of this
     * array is a response array, as would be passed to {@link grade_response()}.
     * There may be between 1 and $totaltries responses.
     * @param int $totaltries The maximum number of tries allowed.
     * @return numeric the fraction that should be awarded for this
     * sequence of response.
     */
    public function compute_final_grade($responses, $totaltries) {
        $fractionsum = 0;
        $fractionmax = 0;
        $checkunit = new answer_unit_conversion;

        foreach ($this->parts as $part) {
            $fractionmax += $part->answermark;
            $lastresponse = array();
            $lastchange = 0;
            $partfraction = 0;
            foreach ($responses as $responseindex => $response) {
                if ($part->part_is_same_response($lastresponse, $response)) {
                    continue;
                }
                $lastresponse = $response;
                $lastchange = $responseindex;
                $this->rationalize_responses($response);
                list($anscorrs, $unitcorrs) = $this->grade_responses_individually($part, $response, $checkunit);
                $partfraction = $anscorrs * ($unitcorrs ? 1 : (1 - $part->unitpenalty));
            }
            $fractionsum += $part->answermark * max(0,  $partfraction - $lastchange * $this->penalty);
        }

        return $fractionsum / $fractionmax;
    }
}

/**
 * Class to represent a question subpart, loaded from the question_answers table
 * in the database.
 *
 * @copyright  2012 Jean-Michel Védrine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class qtype_formulas_part {
    /** @var integer the answer id. */
    public $id;
    public $partindex;
    public $placeholder;
    public $answermark;
    public $answertype;
    public $numbox;
    public $vars1;
    public $vars2;
    public $answer;
    public $correctness;
    public $unitpenalty;
    public $postunit;
    public $ruleid;
    public $otherrule;
    public $subqtext;
    public $subqtextformat;
    public $feedback;
    public $feedbackformat;
    public $partcorrectfb;
    public $partcorrectfbformat;
    public $partpartiallycorrectfb;
    public $partpartiallycorrectfbformat;
    public $partincorrectfb;
    public $partincorrectfbformat;


    /**
     * Constructor.
     */
    public function __construct() {
    }

    public function part_has_unit() {
        return strlen($this->postunit) != 0;
    }

    public function part_has_separate_unit_field() {
        return strlen($this->postunit) != 0 && $this->part_has_combined_unit_field() == false;
    }

    public function part_has_combined_unit_field() {
        return strlen($this->postunit) != 0 && $this->numbox == 1 && $this->answertype != 1000
                && (strpos($this->subqtext, "{_0}{_u}") !== false || (strpos($this->subqtext, "{_0}") === false && strpos($this->subqtext, "{_u}") === false));
    }

    /**
     * Are two responses the same insofar as this part is concerned. This is
     * used so we do not penalise the same mistake twice.
     *
     * @param array $prevresponse the responses previously recorded for this question,
     *      as returned by {@link question_attempt_step::get_qt_data()}
     * @param array $newresponse the new responses, in the same format.
     * @return bool whether the two sets of responses are the same for the given
     *      part.
     */
    public function part_is_same_response(array $prevresponse, array $newresponse) {
        foreach ($this->part_get_expected_data() as $name => $type) {
            if (!question_utils::arrays_same_at_key_missing_is_blank($prevresponse, $newresponse, $name)) {
                return false;
            }
        }
        return true;
    }

    public function part_get_expected_data() {
        $expected = array();
        $i = $this->partindex;
        if ($this->part_has_combined_unit_field()) {
                $expected["${i}_"] = PARAM_RAW;
        } else {
            foreach (range(0, $this->numbox - 1) as $j) {
                $expected["${i}_$j"] = PARAM_RAW;
            }
            if ($this->part_has_separate_unit_field()) {
                $expected["${i}_{$this->numbox}"] = PARAM_RAW;
            }
        }
        return $expected;
    }
    /**
     * Parse a string with placeholders and return
     * the corresponding array of answer boxes.
     * Each box is an object with 3 strings properties
     * pattern, options and stype.
     * pattern is the placeholder as _0, _1, ..., _u
     * options is empty except for multichoice answers
     * where it is the name of a variable containing the list of choices
     * stype is empty for radio buttons or :MCE for drop down
     * select menu.
     *
     * @param $text string to be parsed.
     * @return array.
     */

    public function part_answer_boxes($text) {
        $pattern = '\{(_[0-9u][0-9]*)(:[^{}:]+)?(:[^{}:]+)?\}';
        preg_match_all('/'.$pattern.'/', $text, $matches);
        $boxes = array();
        foreach ($matches[1] as $j => $match) {
            if (!array_key_exists($match, $boxes)) {  // If there is duplication, it will be skipped.
                $boxes[$match] = (object)array('pattern' => $matches[0][$j], 'options' => $matches[2][$j], 'stype' => $matches[3][$j]);
            }
        }
        return $boxes;
    }

    public function part_has_multichoice_coordinate() {
        $boxes = $this->part_answer_boxes($this->subqtext);
        foreach ($boxes as $box) {
            if (strlen($box->options) != 0) { // Multichoice.
                return true;
            }
        }
        return false;
    }

    /**
     * Produce a plain text summary of a response for the part.
     * @param $response a response, as might be passed to {@link grade_response()}.
     * @return string a plain text summary of that response, that could be used in reports.
     */
    public function part_summarise_response(array $response) {
        $summary = array();
        foreach ($this->part_get_expected_data() as $name => $type) {
            if (array_key_exists($name, $response)) {
                $summary [] = $response[$name];
            } else {
                    $summary [] = '';
            }
        }
        $summary = implode(', ', $summary);
        return $summary;
    }

    public function part_is_gradable_response(array $response) {
        // TODO and after that use in is_gradable_response.

    }

    public function part_is_complete_response(array $response) {
        // TODO and after that use it in is_complete_response.

    }

    public function part_is_unanswered(array$response) {
        $i = $this->partindex;
        if (array_key_exists("${i}_", $response) && $response["${i}_"] != '') {
            return false;
        }
        foreach (range(0, $this->numbox) as $j) {
            if (array_key_exists("${i}_$j", $response) && $response["${i}_$j"] != '') {
                    return false;
            }
        }
        return true;
    }
}
