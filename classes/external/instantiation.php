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
 * @copyright  2022 Philipp Imhof
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace qtype_formulas\external;

use Exception;
use qtype_formulas\local\evaluator;
use qtype_formulas\local\lazylist;
use qtype_formulas\local\parser;
use qtype_formulas\local\random_parser;
use qtype_formulas\local\token;
use qtype_formulas\local\variable;

defined('MOODLE_INTERNAL') || die();

// TODO: in the future, this must be changed to $CFG->dirrot . '/lib/externallib.php'.
require_once($CFG->libdir . "/externallib.php");

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
     * Remove variables that have just been copied from one evaluator to the next, unless
     * they have been changed. In that case, they should be kept and their name can be
     * marked to emphasize that change.
     *
     * @param array $base array of qtype_formulas\variable used as the base
     * @param array $new array of qtype_formulas\variable with updated/added variables
     * @param string $mark optional mark for overridden variables
     * @return array array of qtype_formulas\variable containing filtered variables
     */
    protected static function remove_duplicated_variables(array $base, array $new, $mark = '*'): array {
        $filtered = [];
        foreach ($base as $name => $variable) {
            // If the key exists in the subset, we might be overriding it.
            // This can be made clear by appending an asterisk to the variable name.
            $suffix = '';
            if (array_key_exists($name, $new)) {
                $suffix = $mark;
            }
            // If the timestamp of both variables is the same, we do not include it in the
            // subset, because there was no update.
            if ($suffix === $mark && $new[$name]->timestamp == $variable->timestamp) {
                continue;
            }
            $filtered[$name . $suffix] = $variable;
        }

        return $filtered;
    }

    /**
     * Convert the serialized variable context of an evaluator class into an array that
     * suits our needs. In case of an error, return an empty array.
     *
     * @param array $data serialized variable context
     * @return array
     */
    protected static function variable_context_to_array(array $data): array {
        // The data comes directly from the evaluator's export_variable_context() function, so
        // we don't have to expect an error.
        $context = unserialize($data['variables'], ['allowed_classes' => [variable::class, token::class, lazylist::class]]);

        $result = [];
        foreach ($context as $name => $var) {
            $result[$name] = $var;
        }
        return $result;
    }

    /**
     * Instiantiate one set of variables.
     *
     * @param array $context variable context containing the random variables
     * @param array $parsedglobalvars array of qtype_formulas\expression containing the parsed definition of global vars
     * @param array $parsedlocalvars array of qtype_formulas\expression containing the parsed definition of local vars
     * @param array $parsedanswers array of qtype_formulas\expression containing the parsed answers for each part
     * @return mixed associative array containing one data set or an error message, if instantiation failed
     */
    protected static function fetch_one_instance($context, $parsedglobalvars, $parsedlocalvars, $parsedanswers) {
        $noparts = count($parsedanswers);
        $evaluator = new evaluator($context);

        try {
            $evaluator->instantiate_random_variables(null);
            $randomvars = self::variable_context_to_array($evaluator->export_variable_context());

            $evaluator->evaluate($parsedglobalvars);
            $globalvars = self::variable_context_to_array($evaluator->export_variable_context());

            $localvars = [];
            $answers = [];
            for ($i = 0; $i < $noparts; $i++) {
                // Clone the global evaluator. We do not need to keep it beyond the evaluations for
                // the part.
                $partevaluator = clone $evaluator;
                // Only evaluate the local variable definitions if there are any.
                if (isset($parsedlocalvars[$i])) {
                    $partevaluator->evaluate($parsedlocalvars[$i]);
                }
                $localvars[$i] = self::variable_context_to_array($partevaluator->export_variable_context());
                // Finally, evaluate the answer(s). If the model answer was empty, we cannot move on. As we
                // are in a try-catch, we simply throw an exception with the appropriate error message.
                $evaluationresult = $partevaluator->evaluate($parsedanswers[$i]);
                if (empty($evaluationresult)) {
                    throw new Exception(get_string('error_answer_missing_in_part', 'qtype_formulas', $i + 1));
                }
                $answers[$i] = reset($evaluationresult);
            }
        } catch (Exception $e) {
            return $e->getMessage();
        }

        $row = ['randomvars' => [], 'globalvars' => [], 'parts' => []];
        foreach ($randomvars as $name => $variable) {
            $row['randomvars'][] = ['name' => $name, 'value' => self::stringify($variable->value)];
        }
        // Global variables might overwrite random variables. We mark those with a symbol.
        $filteredglobalvars = self::remove_duplicated_variables($globalvars, $randomvars);
        foreach ($filteredglobalvars as $name => $variable) {
            // If the variable has type SET, it is an algebraic variable. We only output its name
            // in curly braces to make that clear. For other variables, we put the value.
            if ($variable->type === variable::ALGEBRAIC) {
                $printname = str_replace('*', '', $name);
                $row['globalvars'][] = ['name' => $name, 'value' => "{{$printname}}"];
            } else {
                $row['globalvars'][] = ['name' => $name, 'value' => self::stringify($variable->value)];
            }
        }
        for ($i = 0; $i < $noparts; $i++) {
            $filteredlocalvars = self::remove_duplicated_variables($localvars[$i], $globalvars);
            $row['parts'][$i] = [];
            foreach ($filteredlocalvars as $name => $variable) {
                // If the variable has type SET, it is an algebraic variable. We only output its name
                // in curly braces to make that clear. For other variables, we put the value.
                if ($variable->type === variable::ALGEBRAIC) {
                    $printname = str_replace('*', '', $name);
                    $row['parts'][$i][] = ['name' => $name, 'value' => "{{$printname}}"];
                } else {
                    $row['parts'][$i][] = ['name' => $name, 'value' => self::stringify($variable->value)];
                }
            }
            // If the value is a scalar, that means the part has only one answer and it is _0.
            if (is_scalar($answers[$i]->value)) {
                $row['parts'][$i][] = ['name' => '_0', 'value' => $answers[$i]->value];
                continue;
            }
            // Otherwise, there are multiple answers from _0 to _n.
            foreach ($answers[$i]->value as $index => $token) {
                $row['parts'][$i][] = ['name' => "_{$index}", 'value' => self::stringify($token->value)];
            }
        }

        return $row;
    }

    /**
     * Description of the parameters for the external function 'instantiate'
     * @return \external_function_parameters
     */
    public static function instantiate_parameters() {
        return new \external_function_parameters([
            'n' => new \external_value(PARAM_INT, 'number of data sets', VALUE_DEFAULT, 1),
            'randomvars' => new \external_value(PARAM_RAW, 'random variables', VALUE_REQUIRED),
            'globalvars' => new \external_value(PARAM_RAW, 'global variables', VALUE_REQUIRED),
            'localvars' => new \external_multiple_structure(
                new \external_value(PARAM_RAW, 'local variables, per part', VALUE_REQUIRED)
            ),
            'answers' => new \external_multiple_structure(
                new \external_value(PARAM_RAW, 'answers, per part', VALUE_REQUIRED)
            ),
        ]);
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
        $params = self::validate_parameters(
            self::instantiate_parameters(),
            ['n' => $n, 'randomvars' => $randomvars, 'globalvars' => $globalvars, 'localvars' => $localvars, 'answers' => $answers]
        );

        // First, we check whether the variables can be parsed and we prepare an evaluator context
        // containing the random variables for (probably very very) slightly better performance.
        $noparts = count($params['answers']);
        try {
            $randomparser = new random_parser($params['randomvars']);
            $evaluator = new evaluator();
            $evaluator->evaluate($randomparser->get_statements());
            $randomcontext = $evaluator->export_variable_context();

            // When initializing the parser, use the known vars from the random parser.
            $globalparser = new parser($params['globalvars'], $randomparser->export_known_variables());
            $parsedglobalvars = $globalparser->get_statements();

            $parsedlocalvars = [];
            $parsedanswers = [];
            for ($i = 0; $i < $noparts; $i++) {
                // Get the known vars from the global parser and save them as a fallback.
                $knownvars = $globalparser->export_known_variables();
                if (!empty($params['localvars'][$i])) {
                    // For each part parser, use the known vars from the global parser.
                    $parser = new parser($params['localvars'][$i], $knownvars);
                    $parsedlocalvars[$i] = $parser->get_statements();

                    // If we are here, that means there are local variables. So we update the
                    // list of known vars.
                    $knownvars = $parser->export_known_variables();
                }

                // Initialize the answer parser using the known variables, either just the global ones
                // or global plus local vars.
                $parser = new parser($params['answers'][$i], $knownvars);
                $parsedanswers[$i] = $parser->get_statements();
            }
        } catch (Exception $e) {
            // If parsing failed, we leave now.
            return ['status' => 'error', 'message' => $e->getMessage()];
        }

        // If requested number is -1, we try to instantiate all possible combinations, but not more than 1000.
        $n = $params['n'];
        if ($n == -1) {
            $n = min(1000, $evaluator->get_number_of_variants());
        }

        // All clear, we can now start to generate instances.
        $data = [];
        for ($i = 0; $i < $n; $i++) {
            $result = self::fetch_one_instance($randomcontext, $parsedglobalvars, $parsedlocalvars, $parsedanswers);
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
        return new \external_single_structure([
            'status' => new \external_value(PARAM_TEXT, 'status', VALUE_REQUIRED),
            'message' => new \external_value(PARAM_TEXT, 'error message, if failed', VALUE_OPTIONAL),
            'data' => new \external_multiple_structure(
                new \external_single_structure([
                    'randomvars' => new \external_multiple_structure(
                        new \external_single_structure(
                            [
                                'name' => new \external_value(PARAM_TEXT, 'variable name', VALUE_REQUIRED),
                                'value' => new \external_value(PARAM_RAW, 'value', VALUE_REQUIRED),
                            ],
                            'description of each random variable',
                            VALUE_REQUIRED
                        ),
                        'list of random variables',
                        VALUE_REQUIRED
                    ),
                    'globalvars' => new \external_multiple_structure(
                        new \external_single_structure(
                            [
                                'name' => new \external_value(PARAM_TEXT, 'variable name', VALUE_REQUIRED),
                                'value' => new \external_value(PARAM_RAW, 'value', VALUE_REQUIRED),
                            ],
                            'description of each global variable',
                            VALUE_REQUIRED
                        ),
                        'list of global variables',
                        VALUE_REQUIRED
                    ),
                    'parts' => new \external_multiple_structure(
                        new \external_multiple_structure(
                            new \external_single_structure(
                                [
                                    'name' => new \external_value(PARAM_TEXT, 'variable name', VALUE_REQUIRED),
                                    'value' => new \external_value(PARAM_RAW, 'value', VALUE_REQUIRED),
                                ]
                            ),
                            'list of variables for the corresponding part',
                            VALUE_REQUIRED
                        ),
                        'list of parts',
                        VALUE_REQUIRED
                    ),
                ]
            ),
            'data, if successful',
            VALUE_OPTIONAL
        )]);
    }

    /**
     * Returns description of method parameters
     * @return \external_function_parameters
     */
    public static function check_random_global_vars_parameters() {
        return new \external_function_parameters([
            'randomvars' => new \external_value(PARAM_RAW, 'random variables', VALUE_DEFAULT, ''),
            'globalvars' => new \external_value(PARAM_RAW, 'global variables', VALUE_DEFAULT, ''),
        ]);
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
        $params = self::validate_parameters(
            self::check_random_global_vars_parameters(),
            ['randomvars' => $randomvars, 'globalvars' => $globalvars]
        );

        // Evaluation of global variables can fail, because there is an error in the random
        // variables. In order to know that, we need to have two separate try-catch constructions.
        try {
            $randomparser = new random_parser($params['randomvars']);
            $evaluator = new evaluator();
            $evaluator->evaluate($randomparser->get_statements());
        } catch (Exception $e) {
            return ['source' => 'random', 'message' => $e->getMessage()];
        }
        try {
            // Initialize the parser, taking into account the vars that are known after evaluation of
            // random variables assignments.
            $globalparser = new parser($params['globalvars'], $evaluator->export_variable_list());
            $evaluator->instantiate_random_variables();
            $evaluator->evaluate($globalparser->get_statements());
        } catch (Exception $e) {
            return ['source' => 'global', 'message' => $e->getMessage()];
        }

        return ['source' => '', 'message' => ''];
    }

    /**
     * Returns description of method result value
     * @return external_description
     */
    public static function check_random_global_vars_returns() {
        return new \external_single_structure([
            'source' => new \external_value(PARAM_RAW, 'source of the error or empty string'),
            'message' => new \external_value(PARAM_RAW, 'empty string or error message'),
        ]);
    }

    /**
     * Returns description of method parameters
     * @return \external_function_parameters
     */
    public static function check_local_vars_parameters() {
        return new \external_function_parameters([
            'randomvars' => new \external_value(PARAM_RAW, 'random variables', VALUE_DEFAULT, ''),
            'globalvars' => new \external_value(PARAM_RAW, 'global variables', VALUE_DEFAULT, ''),
            'localvars' => new \external_value(PARAM_RAW, 'local variables', VALUE_DEFAULT, ''),
        ]);
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
        $params = self::validate_parameters(
            self::check_local_vars_parameters(),
            ['randomvars' => $randomvars, 'globalvars' => $globalvars, 'localvars' => $localvars]
        );

        // Evaluation of global variables can fail, because there is an error in the random
        // variables. In order to know that, we need to have two separate try-catch constructions.
        try {
            $randomparser = new random_parser($params['randomvars']);
            $evaluator = new evaluator();
            $evaluator->evaluate($randomparser->get_statements());
        } catch (Exception $e) {
            return ['source' => 'random', 'message' => $e->getMessage()];
        }
        try {
            // Initialize the parser, taking into account the vars that are known after evaluation of
            // random variables assignments.
            $parser = new parser($params['globalvars'], $evaluator->export_variable_list());
            $evaluator->instantiate_random_variables();
            $evaluator->evaluate($parser->get_statements());
        } catch (Exception $e) {
            return ['source' => 'global', 'message' => $e->getMessage()];
        }
        try {
            // Initialize the local variable parser, taking into account all vars that have been created
            // by random or global vars assignments.
            $parser = new parser($params['localvars'], $evaluator->export_variable_list());
            $evaluator->evaluate($parser->get_statements());
        } catch (Exception $e) {
            return ['source' => 'local', 'message' => $e->getMessage()];
        }

        return ['source' => '', 'message' => ''];
    }

    /**
     * Returns description of method result value
     * @return external_description
     */
    public static function check_local_vars_returns() {
        return new \external_single_structure([
            'source' => new \external_value(PARAM_RAW, 'source of the error or empty string'),
            'message' => new \external_value(PARAM_RAW, 'empty string or error message'),
        ]);
    }

    /**
     * Returns description of method parameters
     * @return \external_function_parameters
     */
    public static function render_question_text_parameters() {
        return new \external_function_parameters([
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
            ),
        ]);
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
        $params = self::validate_parameters(
            self::render_question_text_parameters(),
            ['questiontext' => $questiontext, 'parttexts' => $parttexts, 'globalvars' => $globalvars, 'partvars' => $partvars]
        );

        $evaluator = new evaluator();

        // First prepare the main question text.
        try {
            // In this case, we do not start by parsing and evaluating random vars. Instead, the random vars
            // are already instantiated and are treated like normal global vars. Therefore, we do not need
            // to use known vars upon initialisation of the parser.
            $parser = new parser($params['globalvars']);
            $evaluator->evaluate($parser->get_statements());
            $renderedquestiontext = $evaluator->substitute_variables_in_text($params['questiontext']);
        } catch (Exception $e) {
            return [
                'question' => get_string('previewerror', 'qtype_formulas', $e->getMessage()),
                'parts' => [],
            ];
        }

        $renderedparttexts = [];
        foreach ($params['partvars'] as $i => $partvar) {
            try {
                $partevaluator = clone $evaluator;
                // Initialize the parser for each part's local variables, taking into account
                // the (global and instantiated random) variables known so far.
                $parser = new parser($partvar, $partevaluator->export_variable_list());
                $partevaluator->evaluate($parser->get_statements());

                $renderedparttexts[$i] = $partevaluator->substitute_variables_in_text($params['parttexts'][$i]);
            } catch (Exception $e) {
                return [
                    'question' => get_string('previewerror', 'qtype_formulas', $e->getMessage()),
                    'parts' => [],
                ];
            }
        }

        return ['question' => $renderedquestiontext, 'parts' => $renderedparttexts];
    }

    /**
     * Returns description of method result value
     * @return external_description
     */
    public static function render_question_text_returns() {
        return new \external_single_structure([
            'question' => new \external_value(PARAM_RAW, 'rendered question text', VALUE_REQUIRED),
            'parts' => new \external_multiple_structure(
                new \external_value(PARAM_RAW, 'rendered part text', VALUE_REQUIRED),
                'array of rendered part texts',
                VALUE_REQUIRED
            ),
        ]);
    }
}
