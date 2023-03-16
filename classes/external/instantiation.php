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
 * qtype_formulas external file
 *
 * @package    qtype_formulas
 * @category   external
 * @copyright  2022 Philipp Imhof
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace qtype_formulas\external;
use qtype_formulas\variables;
use Exception;


defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . "/externallib.php");
require_once($CFG->dirroot . '/question/type/formulas/variables.php');

/**
 * Class containing various methods for validation of variable definitions and
 * instantiation of questions inside the edit form.
 */
class instantiation extends \external_api {

    /**
     * Convert array to string of form '[1, 2, 3, ... ]'.
     * If argument is a string or a number, return it unchanged.
     *
     * @param mixed $maybearray value
     * @return string converted value, if conversion is needed
     */
    protected static function stringify($maybearray) {
        if (gettype($maybearray) == 'array') {
            return '[' . implode(', ', $maybearray) . ']';
        }
        return $maybearray;
    }

    /**
     * Remove variables that have just been copied from one variable stack to the next,
     * unless they have been changed. In that case, they should be kept and their name can
     * be marked to emphasize that change.
     *
     * @param object $superset the variable stack used as a base
     * @param object $subset the derived variable stack
     * @param string $mark optional mark for overridden variables
     * @return object variable stack containing only new and overriden variables
     */
    protected static function remove_duplicated_variables($superset, $subset, $mark = '*') {
        $filtered = array();
        foreach ($superset as $name => $value) {
            // If the key exists in the subset, we might be overriding it.
            // This can be made clear by appending an asterisk to the variable name.
            $suffix = '';
            if (array_key_exists($name, $subset)) {
                $suffix = $mark;
            }
            // Same value, so no need to include the variable in the subset.
            if ($suffix == $mark && $subset[$name] == $value) {
                continue;
            }
            $filtered[$name . $suffix] = $value;
        }

        return $filtered;
    }

    /**
     * Instiantiate one set of variables.
     *
     * @param object $parsedrandomvars pre-parsed random variables (for performance reasons)
     * @param string $globalvars string defining the global variables
     * @param array $localvars array of strings, each one defining the corresponding part's local variables
     * @param array $answers array of strings, each one defining the corresponding part's answers
     * @return mixed associative array containing one data set or an error message, if instantiation failed
     */
    protected static function fetch_one_instance($parsedrandomvars, $globalvars, $localvars, $answers) {
        $vars = new variables();
        $noparts = count($answers);
        try {
            $instantiatedrandomvars = $vars->instantiate_random_variables($parsedrandomvars);
            $evaluatedglobalvars = $vars->evaluate_assignments($instantiatedrandomvars, $globalvars);
            $evaluatedlocalvars = array();
            $evaluatedanswers = array();
            for ($i = 0; $i < $noparts; $i++) {
                $evaluatedlocalvars[$i] = $vars->evaluate_assignments($evaluatedglobalvars, $localvars[$i]);
                $evaluatedanswers[$i] = $vars->evaluate_general_expression($evaluatedlocalvars[$i], $answers[$i]);
            }
        } catch (Exception $e) {
            return $e->getMessage();
        }

        $row = array('randomvars' => array(), 'globalvars' => array(), 'parts' => array());
        foreach ($instantiatedrandomvars->all as $name => $value) {
            $row['randomvars'][] = array('name' => $name, 'value' => self::stringify($value->value));
        }
        $filteredglobalvars = self::remove_duplicated_variables($evaluatedglobalvars->all, $instantiatedrandomvars->all);
        foreach ($filteredglobalvars as $name => $value) {
            if (is_object($value->value)) {
                $row['globalvars'][] = array('name' => $name, 'value' => "{{$name}}");
            } else {
                $row['globalvars'][] = array('name' => $name, 'value' => self::stringify($value->value));
            }
        }
        for ($i = 0; $i < $noparts; $i++) {
            $filteredlocalvars = self::remove_duplicated_variables($evaluatedlocalvars[$i]->all, $evaluatedglobalvars->all);
            $row['parts'][$i] = array();
            foreach ($filteredlocalvars as $name => $value) {
                if (is_object($value->value)) {
                    $row['parts'][$i][] = array('name' => $name, 'value' => "{{$name}}");
                } else {
                    $row['parts'][$i][] = array('name' => $name, 'value' => $value->value);
                }
            }
            // This should not happen.
            if (is_object(($evaluatedanswers[$i]->value))) {
                $row['parts'][$i][] = array('name' => '_0', 'value' => '!!!');
                break;
            }
            if (is_scalar($evaluatedanswers[$i]->value)) {
                $row['parts'][$i][] = array('name' => '_0', 'value' => $evaluatedanswers[$i]->value);
                continue;
            }
            foreach ($evaluatedanswers[$i]->value as $idx => $value) {
                $row['parts'][$i][] = array('name' => '_' . $idx, 'value' => $value);
            }
        }

        return $row;
    }

    /**
     * Description of the parameters for the external function 'instantiate'
     * @return external_function_parameters
     */
    public static function instantiate_parameters() {
        return new \external_function_parameters(
            array(
                'n' => new \external_value(PARAM_INT, 'number of data sets', VALUE_DEFAULT, 1),
                'randomvars' => new \external_value(PARAM_TEXT, 'random variables', VALUE_REQUIRED),
                'globalvars' => new \external_value(PARAM_TEXT, 'global variables', VALUE_REQUIRED),
                'localvars' => new \external_multiple_structure(
                    new \external_value(PARAM_TEXT, 'local variables, per part', VALUE_REQUIRED)
                ),
                'answers' => new \external_multiple_structure(
                    new \external_value(PARAM_TEXT, 'answers, per part', VALUE_REQUIRED)
                )
            )
        );
    }

    /**
     * AJAX function to instantiate a certain number of data sets.
     *
     * @param integer $n number of data sets to instantiate or -1 for "all"
     * @param string $randomvars string defining the random variables
     * @param string $globalvars string defining the global variables
     * @param array $localvars array of strings, each one defining the corresponding part's local variables
     * @param array $answers array of strings, each one defining the corresponding part's answers
     * @return mixed associative array containing the datasets, or an error message if instantiation failed
     */
    public static function instantiate($n, $randomvars, $globalvars, $localvars, $answers) {
        $params = self::validate_parameters(self::instantiate_parameters(),
                array(
                    'n' => $n,
                    'randomvars' => $randomvars,
                    'globalvars' => $globalvars,
                    'localvars' => $localvars,
                    'answers' => $answers
                )
            );

        // First, we check whether the variables can be parsed and interpreted.
        // We store the parsed random variables for better performance.
        $vars = new variables();
        $parsedrandomvars = null;
        $noparts = count($answers);
        try {
            $parsedrandomvars = $vars->parse_random_variables($randomvars);
            $instantiatedrandomvars = $vars->instantiate_random_variables($parsedrandomvars);
            $evaluatedglobalvars = $vars->evaluate_assignments($instantiatedrandomvars, $globalvars);
            $evaluatedlocalvars = array();
            $evaluatedanswers = array();
            for ($i = 0; $i < $noparts; $i++) {
                $evaluatedlocalvars[$i] = $vars->evaluate_assignments($evaluatedglobalvars, $localvars[$i]);
                $evaluatedanswers[$i] = $vars->evaluate_general_expression($evaluatedlocalvars[$i], $answers[$i]);
            }
        } catch (Exception $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }

        // If requested number is -1, we try to instantiate all possible combinations, but not more than 1000.
        if ($n == -1) {
            $n = min(1000, $vars->vstack_get_number_of_dataset_with_shuffle($parsedrandomvars));
        }

        // All clear, we can now generate instances.
        $data = array();
        for ($i = 0; $i < $n; $i++) {
            $result = self::fetch_one_instance($parsedrandomvars, $globalvars, $localvars, $answers);
            if (gettype($result) == 'string') {
                return ['status' => 'error', 'message' => $result];
            }
            $data[] = $result;
        }

        return ['status' => 'ok', 'data' => $data];
    }

    /**
     * Description of the return value for the external function 'instantiate'
     * @return external_description
     */
    public static function instantiate_returns() {
        return new \external_single_structure(array(
            'status' => new \external_value(PARAM_TEXT, 'status', VALUE_REQUIRED),
            'message' => new \external_value(PARAM_TEXT, 'error message, if failed', VALUE_OPTIONAL),
            'data' => new \external_multiple_structure(
                new \external_single_structure(array(
                    'randomvars' => new \external_multiple_structure(
                        new \external_single_structure(
                            array(
                                'name' => new \external_value(PARAM_TEXT, 'variable name', VALUE_REQUIRED),
                                'value' => new \external_value(PARAM_TEXT, 'value', VALUE_REQUIRED)
                            ),
                            'description of each random variable',
                            VALUE_REQUIRED),
                        'list of random variables',
                        VALUE_REQUIRED
                    ),
                    'globalvars' => new \external_multiple_structure(
                        new \external_single_structure(
                            array(
                                'name' => new \external_value(PARAM_TEXT, 'variable name', VALUE_REQUIRED),
                                'value' => new \external_value(PARAM_TEXT, 'value', VALUE_REQUIRED)
                            ),
                            'description of each global variable',
                            VALUE_REQUIRED
                        ),
                        'list of global variables',
                        VALUE_REQUIRED
                    ),
                    'parts' => new \external_multiple_structure(
                        new \external_multiple_structure(
                            new \external_single_structure(
                                array(
                                    'name' => new \external_value(PARAM_TEXT, 'variable name', VALUE_REQUIRED),
                                    'value' => new \external_value(PARAM_TEXT, 'value', VALUE_REQUIRED)
                                )
                            ),
                            'list of variables for the corresponding part',
                            VALUE_REQUIRED
                        ),
                        'list of parts',
                        VALUE_REQUIRED
                    ),
                )
            ),
            'data, if successful',
            VALUE_OPTIONAL
        )));
    }

    /**
     * Returns description of method parameters
     * @return external_function_parameters
     */
    public static function check_random_global_vars_parameters() {
        return new \external_function_parameters(
            array(
                'randomvars' => new \external_value(PARAM_RAW, 'random variables', VALUE_DEFAULT, ''),
                'globalvars' => new \external_value(PARAM_RAW, 'global variables', VALUE_DEFAULT, '')
            )
        );
    }

    /**
     * Try to parse and instantiate the given random vars (if any). If global vars are given,
     * the result from this first step will be used as a base to evaluate assignments of the
     * global vars.
     *
     * @param string $randomvars definition of random variables or empty string, if none
     * @param string $globalvars definition of global variables or empty string, if none
     * @return string error message (if any) or empty string (if definitions are valid)
     */
    public static function check_random_global_vars($randomvars, $globalvars) {
        $params = self::validate_parameters(self::check_random_global_vars_parameters(),
                array(
                    'randomvars' => $randomvars,
                    'globalvars' => $globalvars
                )
            );

        $vars = new variables();
        // Evaluation of global variables can fail, because there is an error in the random
        // variables. In order to know that, we need to have two separate try-catch constructions.
        try {
            $stack = $vars->parse_random_variables($randomvars);
        } catch (Exception $e) {
            return array(
                'source' => 'random',
                'message' => $e->getMessage()
            );
        }
        try {
            $vars->evaluate_assignments($vars->instantiate_random_variables($stack), $globalvars);
        } catch (Exception $e) {
            return array(
                'source' => 'global',
                'message' => $e->getMessage()
            );
        }

        return array('source' => '', 'message' => '');
    }

    /**
     * Returns description of method result value
     * @return external_description
     */
    public static function check_random_global_vars_returns() {
        return new \external_single_structure(array(
            'source' => new \external_value(PARAM_RAW, 'source of the error or empty string'),
            'message' => new \external_value(PARAM_RAW, 'empty string or error message')
        ));
    }

    /**
     * Returns description of method parameters
     * @return external_function_parameters
     */
    public static function check_local_vars_parameters() {
        return new \external_function_parameters(
            array(
                'randomvars' => new \external_value(PARAM_RAW, 'random variables', VALUE_DEFAULT, ''),
                'globalvars' => new \external_value(PARAM_RAW, 'global variables', VALUE_DEFAULT, ''),
                'localvars' => new \external_value(PARAM_RAW, 'local variables', VALUE_DEFAULT, '')
            )
        );
    }

    /**
     * Try to parse and evaluate the given local variables. In order to do so, the random and
     * global variables have to be considered as well, as local variables can be linked to them.
     *
     * @param string $randomvars definition of random variables or empty string, if none
     * @param string $globalvars definition of global variables or empty string, if none
     * @param string $localvars definition of part's local variables or empty string, if none
     * @return string error message (if any) or empty string (if definitions are valid)
     */
    public static function check_local_vars($randomvars, $globalvars, $localvars) {
        $params = self::validate_parameters(self::check_local_vars_parameters(),
                array(
                    'randomvars' => $randomvars,
                    'globalvars' => $globalvars,
                    'localvars' => $localvars
                )
            );

        $vars = new variables();
        // Evaluation of global variables can fail, because there is an error in the random
        // variables. In order to know that, we need to have three separate try-catch constructions.
        try {
            $stack = $vars->parse_random_variables($randomvars);
        } catch (Exception $e) {
            return array(
                'source' => 'random',
                'message' => $e->getMessage()
            );
        }
        try {
            $evaluatedglobalvars = $vars->evaluate_assignments($vars->instantiate_random_variables($stack), $globalvars);
        } catch (Exception $e) {
            return array(
                'source' => 'global',
                'message' => $e->getMessage()
            );
        }
        try {
            $vars->evaluate_assignments($evaluatedglobalvars, $localvars);
        } catch (Exception $e) {
            return array(
                'source' => 'local',
                'message' => $e->getMessage()
            );
            return $e->getMessage();
        }
        return array('source' => '', 'message' => '');
    }

    /**
     * Returns description of method result value
     * @return external_description
     */
    public static function check_local_vars_returns() {
        return new \external_single_structure(array(
            'source' => new \external_value(PARAM_RAW, 'source of the error or empty string'),
            'message' => new \external_value(PARAM_RAW, 'empty string or error message')
        ));
    }

    /**
     * Returns description of method parameters
     * @return external_function_parameters
     */
    public static function render_question_text_parameters() {
        return new \external_function_parameters(
            array(
                'questiontext' => new \external_value(PARAM_RAW, 'question text with placeholders', VALUE_REQUIRED),
                'parttexts' => new \external_multiple_structure(
                    new \external_value(PARAM_RAW, 'text for each part', VALUE_REQUIRED)
                ),
                'globalvars' => new \external_value(
                    PARAM_RAW,
                    'definition for global (and instantiated random) variables',
                    VALUE_DEFAULT,
                    ''
                ),
                'partvars' => new \external_multiple_structure(
                    new \external_value(PARAM_RAW, 'definition for part\'s local variables', VALUE_DEFAULT, '')
                )
            )
        );
    }

    /**
     * Replace variable placeholders in the question text and all parts' text, using global variables
     * and parts' local variables. This only makes sense after random variables have been instantiated,
     * so we expect the caller to include them as normal global variables. (That's what they are once they
     * have lost their randomness.)
     *
     * @param string $questiontext the question's text, possibly including place holders or calculations
     * @param array $parttexts array of parts' texts, possibly including place holders or calculations
     * @param string $globalvars string defining the global (and instantiated random) variables
     * @param array $partvars array of strings defining the parts' local variables
     * @return array associative array with the rendered question text and array of parts' texts
     */
    public static function render_question_text($questiontext, $parttexts, $globalvars, $partvars) {
        $vars = new variables();
        $stack = $vars->vstack_create();
        // First prepare the main question text.
        try {
            $evaluatedglobalvars = $vars->evaluate_assignments($stack, $globalvars);
        } catch (Exception $e) {
            return array(
                'question' => get_string('previewerror', 'qtype_formulas') . ' ' . $e->getMessage(),
                'parts' => array()
            );
        }
        $renderedquestion = $vars->substitute_variables_in_text($evaluatedglobalvars, $questiontext);

        $renderedparts = array();
        foreach ($partvars as $i => $partvar) {
            try {
                $evaluatedpartvars = $vars->evaluate_assignments($evaluatedglobalvars, $partvar);
                $renderedparts[$i] = $vars->substitute_variables_in_text($evaluatedpartvars, $parttexts[$i]);
            } catch (Exception $e) {
                $renderedparts[$i] = $e->getMessage();
            }
        }

        return array('question' => $renderedquestion, 'parts' => $renderedparts);
    }

    /**
     * Returns description of method result value
     * @return external_description
     */
    public static function render_question_text_returns() {
        return new \external_single_structure(array(
            'question' => new \external_value(PARAM_RAW, 'rendered question text', VALUE_REQUIRED),
            'parts' => new \external_multiple_structure(
                new \external_value(PARAM_RAW, 'rendered part text', VALUE_REQUIRED),
                'array of rendered part texts',
                VALUE_REQUIRED
            )
        ));
        return new \external_value(PARAM_RAW, 'question text with placeholders replaced by their values');
    }
}
