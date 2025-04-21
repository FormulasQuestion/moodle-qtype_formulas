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

        try {
            $parser = new answer_parser($params['answer']);
        } catch (Exception $e) {
            return ['status' => 'error', 'detail' => $e->getMessage()];
        }

        if ($params['withunit'] === false) {
            if ($parser->is_acceptable_for_answertype($answertype)) {
                return ['status' => 'success', 'detail' => latexifier::latexify($parser->get_statements()[0]->body)];
            }

            return ['status' => 'error', 'detail' => 'FIXME answer not acceptable for answer type'];
        }

        // If we have a combined field, we split the unit from the number und parse again. This is needed,
        // because the answer types (all answer types that can have a combined field) would not otherwise
        // accept those tokens. Also, we want to validate the unit string and this must be done separately.
        $splitindex = $parser->find_start_of_units();
        $number = trim(substr($params['answer'], 0, $splitindex));
        $unit = trim(substr($params['answer'], $splitindex));

        try {
            $parser = new answer_parser($number);
        } catch (Exception $e) {
            return ['status' => 'error', 'detail' => $e->getMessage()];
        }

        $unitconverter = new answer_unit_conversion();
        $unitcheck = $unitconverter->parse_unit($unit);
        if ($unitcheck === null) {
            return ['status' => 'error', 'detail' => get_string('error_unit', 'qtype_formulas')];
        }

        $numberpart = latexifier::latexify($parser->get_statements()[0]->body);
        $unitpart = latexifier::latexify_unit($unitcheck);

        return ['status' => 'success', 'detail' => "$numberpart \\quad $unitpart"];
    }

    /**
     * Description of the return value for the external function 'validate_student_answer'
     *
     * @return external_description
     */
    public static function validate_student_answer_returns() {
        return new \external_single_structure([
            'status' => new \external_value(PARAM_RAW, "result of validation, i. e. 'error' or 'success'", VALUE_REQUIRED),
            'detail' => new \external_value(PARAM_RAW, "error message in case of failed validation, TeX code otherwise", VALUE_REQUIRED),

        ]);
    }
}
