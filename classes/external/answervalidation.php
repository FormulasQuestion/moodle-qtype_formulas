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
 * qtype_formulas external file
 *
 * @package    qtype_formulas
 * @category   external
 * @copyright  2025 Philipp Imhof
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace qtype_formulas\external;

use Exception;
use qtype_formulas;
use qtype_formulas\answer_unit_conversion;
use qtype_formulas\local\answer_parser;
use qtype_formulas\local\latexifier;

defined('MOODLE_INTERNAL') || die();

// TODO: in the future, this must be changed to $CFG->dirrot . '/lib/externallib.php'.
require_once($CFG->libdir . "/externallib.php");

require_once($CFG->dirroot . '/question/type/formulas/questiontype.php');

/**
 * Class containing various methods to validate a student's answer to a Formulas question.
 */
class answervalidation extends \external_api {

    /**
     * Description of the parameters for the external function 'validate_student_answer'
     *
     * @return \external_function_parameters
     */
    public static function validate_student_answer_parameters() {
        return new \external_function_parameters([
            'answer' => new \external_value(PARAM_RAW, "student's answer", VALUE_REQUIRED),
            'answertype' => new \external_value(PARAM_INT, 'answer type, e. g. number or numeric', VALUE_REQUIRED),
            'withunit' => new \external_value(PARAM_BOOL, 'whether or not the field also contains a unit', VALUE_REQUIRED),
        ]);
    }

    /**
     * AJAX function to validate a student's answer to a Formulas question.
     *
     * @param string $answer student's answer
     * @param int $answertype answer type, as defined in qtype_formulas
     * @param bool $withunit whether or not the field also contains a unit (combined answer field)
     * @return array array with status (error or success) and detail (error message or TeX code)
     */
    public static function validate_student_answer(string $answer, int $answertype, bool $withunit): array {
        $params = self::validate_parameters(
            self::validate_student_answer_parameters(),
            ['answer' => $answer, 'answertype' => $answertype, 'withunit' => $withunit]
        );

        // Try to parse the answer. If this fails, it does not make sense to continue.
        try {
            $parser = new answer_parser($params['answer']);
        } catch (Exception $e) {
            return ['status' => 'error', 'detail' => $e->getMessage()];
        }

        // If we have a combined field, we split the unit from the number. This is needed, because the
        // answer types that allow for a combined field do not accept tokens like 'm' or 'cm' (they read
        // them as variables). Also, the unit string must be validated separately.
        if ($params['withunit']) {
            $splitindex = $parser->find_start_of_units();
            $number = trim(substr($params['answer'], 0, $splitindex));
            $unit = trim(substr($params['answer'], $splitindex));
        } else {
            $number = $params['answer'];
            $unit = '';
        }

        // Now, check whether the number part is acceptable for the given answertype. If it is, we also
        // translate it to LaTeX code. Re-parsing just the number part must still be in a try-catch, because
        // it could fail now that the unit part is gone, e. g. if the response was '1+m', that would have
        // parsed fine (sum of number 1 and variable m), but after splitting the "number" part would be
        // just '1+' which results in a parse error (unexpected end of expression).
        $numberpartlatex = '';
        if (strlen($number) > 0) {
            try {
                $parser = new answer_parser($number);
                // As we are in a try-catch block anyway, we can just throw an Exception if the answer
                // is not acceptable.
                if (!$parser->is_acceptable_for_answertype($answertype)) {
                    $answertypestrings = [
                        qtype_formulas::ANSWER_TYPE_NUMBER => 'number',
                        qtype_formulas::ANSWER_TYPE_NUMERIC => 'numeric',
                        qtype_formulas::ANSWER_TYPE_NUMERICAL_FORMULA => 'numerical_formula',
                        qtype_formulas::ANSWER_TYPE_ALGEBRAIC => 'algebraic_formula',
                    ];
                    $message = get_string(
                        'answernotacceptable',
                        'qtype_formulas',
                        get_string($answertypestrings[$answertype], 'qtype_formulas'),
                    );
                    throw new Exception($message);
                }
            } catch (Exception $e) {
                return ['status' => 'error', 'detail' => $e->getMessage()];
            }
            $numberpartlatex = latexifier::latexify($parser->get_statements()[0]->body);
        }

        // Finally, check the unit part and, if it is valid, translate to LaTeX.
        list('status' => $checkresult, 'detail' => $unitpartlatex) = self::validate_unit($unit);
        if ($checkresult === 'error') {
            return ['status' => 'error', 'detail' => get_string('error_unit', 'qtype_formulas')];
        }

        // By using array_filter without a callback, it simply removes empty entries from the array.
        // This way, we only get the \quad space if we really have a number *and* a unit.
        $latex = implode(' \quad ', array_filter([$numberpartlatex, $unitpartlatex]));
        return ['status' => 'success', 'detail' => $latex];
    }

    /**
     * Description of the return value for the external function 'validate_student_answer'
     *
     * @return external_description
     */
    public static function validate_student_answer_returns() {
        return new \external_single_structure([
            'status' => new \external_value(PARAM_RAW, "result of validation, i. e. 'error' or 'success'", VALUE_REQUIRED),
            'detail' => new \external_value(
                PARAM_RAW,
                "error message in case of failed validation, TeX code otherwise",
                VALUE_REQUIRED,
            ),
        ]);
    }

    /**
     * Description of the parameters for the external function 'validate_unit'
     *
     * @return \external_function_parameters
     */
    public static function validate_unit_parameters() {
        return new \external_function_parameters([
            'unit' => new \external_value(PARAM_RAW, "unit string", VALUE_REQUIRED),
        ]);
    }

    /**
     * AJAX function to validate a unit for the Formulas question plugin.
     *
     * @param string $unit unit string
     * @return array array with status (error or success) and detail (error message or TeX code)
     */
    public static function validate_unit(string $unit): array {
        $params = self::validate_parameters(self::validate_unit_parameters(), ['unit' => $unit]);

        $unitconverter = new answer_unit_conversion();
        $unitcheck = $unitconverter->parse_unit($params['unit']);
        if ($unitcheck === null) {
            return ['status' => 'error', 'detail' => get_string('error_unit', 'qtype_formulas')];
        }

        return ['status' => 'success', 'detail' => latexifier::latexify_unit($unitcheck)];
    }

    /**
     * Description of the return value for the external function 'validate_unit'
     *
     * @return external_description
     */
    public static function validate_unit_returns() {
        return new \external_single_structure([
            'status' => new \external_value(PARAM_RAW, "result of validation, i. e. 'error' or 'success'", VALUE_REQUIRED),
            'detail' => new \external_value(
                PARAM_RAW,
                "error message in case of failed validation, TeX code otherwise",
                VALUE_REQUIRED,
            ),
        ]);
    }
}
