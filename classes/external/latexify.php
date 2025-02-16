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
use qtype_formulas\local\latexifier;

defined('MOODLE_INTERNAL') || die();

// TODO: in the future, this must be changed to $CFG->dirrot . '/lib/externallib.php'.
require_once($CFG->libdir . "/externallib.php");

/**
 * Class containing various methods to validate a student's answer to a Formulas question.
 */
class latexify extends \external_api {

    /**
     * Description of the parameters for the external function 'latexify'
     *
     * @return \external_function_parameters
     */
    public static function latexify_parameters() {
        return new \external_function_parameters([
            'input' => new \external_value(PARAM_RAW, "student's answer", VALUE_REQUIRED),
        ]);
    }

    /**
     * AJAX function to convert student's answer into LaTeX code for rendering with MathJax.
     *
     * @param string $input student's answer
     * @return string rendered code
     */
    public static function latexify(string $input): string {
        $params = self::validate_parameters(
            self::latexify_parameters(),
            ['input' => $input]
        );

        try {
            $parser = new answer_parser($params['input']);
        } catch (Exception $e) {
            // If there was an exception already during the creation of the parser,
            // we can return false.
            return false;
        }

        return latexifier::latexify($parser->get_statements()[0]->body);
    }

    /**
     * Description of the return value for the external function 'latexify'
     *
     * @return external_description
     */
    public static function latexify_returns() {
        return new \external_value(PARAM_RAW, "rendered LaTeX code", VALUE_REQUIRED);
    }
}
