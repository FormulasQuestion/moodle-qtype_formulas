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

namespace qtype_formulas\local;

use qtype_formulas\answer_unit_conversion;
use qtype_formulas\local\answer_parser;
use qtype_formulas\local\evaluator;
use qtype_formulas\local\lexer;
use qtype_formulas\local\parser;
use qtype_formulas\local\token;
use qtype_formulas\unit_conversion_rules;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/question/type/formulas/questiontype.php');
require_once($CFG->dirroot . '/question/type/formulas/answer_unit.php');
require_once($CFG->dirroot . '/question/type/formulas/conversion_rules.php');
require_once($CFG->dirroot . '/question/behaviour/adaptivemultipart/behaviour.php');

/**
 * Class to represent a question subpart, loaded from the question_answers table
 * in the database.
 *
 * @copyright  2012 Jean-Michel Védrine, 2023 Philipp Imhof
 * @license https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @package qtype_formulas
 */
class formulas_part {
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
        // at all, neither for the answer nor for the unit. As the placeholders may contain formatting
        // options, it makes sense to first simplify the part's question text by removing those. Note
        // that the regex is different from e. g. the one in scan_for_answer_boxes(), because there
        // MUST NOT be an :options-variable or a :MC/:MCE/:MCES/:MCS part.
        $simplifiedtext = preg_replace(
            '/\{(_u|_\d+)((\|[\w =#]*)*)\}/',
            '{\1}',
            $this->subqtext,
        );

        $combinedrequested = strpos($simplifiedtext, '{_0}{_u}') !== false;
        $noplaceholders = strpos($simplifiedtext, '{_0}') === false && strpos($this->subqtext, '{_u}') === false;
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
     * - {_n:str} or {_n:str:MC} for radio buttons, str must be a variable name
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
        preg_match_all('/\{(_u|_\d+)(:(_[A-Za-z]|[A-Za-z]\w*)(:(MC|MCE|MCS|MCES))?)?((\|[\w .=#]*)*)\}/', $text, $matches);

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
            // Finally, the array $matches[4] will contain ':MC', ':MCE', ':MCES' or ':MCS' in case this has been
            // specified. Otherwise, there will be an empty string.
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
     * Parse a string of format options, as used in the definition of a text box placeholder,
     * e. g. |w=10px|bgcol=yellow.
     *
     * @param string $settings format settings
     * @return array associative array 'optionname' => 'value', e. g. 'w' => '50px'
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
            // If the formula is aready wrapped in quotes, we throw an Exception, because that
            // should not happen. It will happen, if the student puts quotes around their response, but
            // we want that to be graded wrong. The exception will be caught and dealt with upstream,
            // so we do not need to be more precise.
            if (preg_match('/^\"[^\"]*\"$/', $formula)) {
                throw new Exception();
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
        $command = '_a = [' . implode(',', $modelanswers) . '];';

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
            try {
                $response = self::wrap_algebraic_formulas_in_quotes($response);
            } catch (Throwable $t) {
                // TODO: convert to non-capturing catch.
                return ['answer' => 0, 'unit' => $unitcorrect];
            }
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
        ['answer' => $answercorrect, 'unit' => $unitcorrect] = $this->grade($response);

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
