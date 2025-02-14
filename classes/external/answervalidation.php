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
use qtype_formulas\local\answer_parser;
use qtype_formulas;
use qtype_formulas\local\evaluator;
use qtype_formulas\local\random_parser;
use qtype_formulas\local\token;
use qtype_formulas\local\variable;

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
     * @return bool whether or not the answer is valid
     */
    public static function validate_student_answer(string $answer, int $answertype, bool $withunit): bool {
        $params = self::validate_parameters(
            self::validate_student_answer_parameters(),
            ['answer' => $answer, 'answertype' => $answertype, 'withunit' => $withunit]
        );

        try {
            $parser = new answer_parser($params['answer']);
        } catch (Exception $e) {
            // If there was an exception already during the creation of the parser,
            // we can return false.
            return false;
        }

        // If we have a combined field, we split the unit from the number und parse again.
        if ($params['withunit']) {
            $splitindex = $parser->find_start_of_units();
            $number = trim(substr($answer, 0, $splitindex));
            $unit = trim(substr($answer, $splitindex));

            $parser = new answer_parser($number);
        }

        return $parser->is_acceptable_for_answertype($answertype);
    }

    /**
     * Description of the return value for the external function 'validate_student_answer'
     *
     * @return external_description
     */
    public static function validate_student_answer_returns() {
        return new \external_value(PARAM_BOOL, "whether or not the student's answer is syntactically valid", VALUE_REQUIRED);
    }
}
