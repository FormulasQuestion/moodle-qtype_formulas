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
 * The language strings for the formulas question type.
 *
 * @package    qtype_formulas
 * @copyright  2010 Hon Wai, Lau <lau65536@gmail.com>
 * @copyright  2024 Philipp E. Imhof
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['abserror'] = 'Absolute error';
$string['addmorepartsblanks'] = 'Blanks for {no} more parts';
$string['algebraic_formula'] = 'Algebraic formula';
$string['allfieldsempty'] = 'All input fields are empty.';
$string['answer'] = 'Answer*';
$string['answer_help'] = '**Required**.
Must be a numer / list of numbers, or a string / list of strings depending on the answer type chosen.
Please note that the number of elements in the list defines the number of answer fields for this part.

<pre class="prettyprint">123<br>[1, 0, 0, 1]<br>a<br>[1, a, b]<br>"exp(-a t)"<br>["vx t","vy t - 0.5 a t^2"]</pre>';
$string['answercombinedunitmulti'] = 'Answer and unit for part {$a->part}';
$string['answercombinedunitsingle'] = 'Answer and unit';
$string['answercoordinatemulti'] = 'Answer field {$a->numanswer} for part {$a->part}';
$string['answercoordinatesingle'] = 'Answer field {$a->numanswer}';
$string['answermark'] = 'Part\'s mark*';
$string['answermark_help'] = '**Required**.
The mark for the answer of this part, which should be a number greater than 0.
The default mark of the whole question is the sum of all its parts marks.

Note: If this part\'s mark field is left blank and there is no answer, the part will be deleted when the question is saved.';
$string['answermulti'] = 'Answer for part {$a->part}';
$string['answerno'] = 'Part {$a}';
$string['answernotacceptable'] = 'This answer is not valid for the given answer type: {$a}.';
$string['answernotunique'] = 'There are other correct answers.';
$string['answernotunique_help'] = 'If this option is checked, the student will see "One correct answer is: ..." instead of "The correct answer is: ..." when reviewing their attempt.';
$string['answersingle'] = 'Answer';
$string['answertype'] = 'Answer type';
$string['answertype_help'] = 'There are four answer types.

Number, numeric and numerical formula answers require number or a list of numbers as answer.

Algebraic formula answers require a string or list of strings as answer.

Different answer types will impose different restrictions while inputting answers, so students will
need to know how to input them. The format check in the question code will also tell them when they
type if something is wrong. Please read the documentation for details.';
$string['answertype_link'] = 'https://dynamiccourseware.org/';
$string['answerunitmulti'] = 'Unit for part {$a->part}';
$string['answerunitsingle'] = 'Unit';
$string['caretwarning'] = 'Note that ^ means XOR in model answers, except for algebraic formulas. For exponentiation, use ** instead.';
$string['checkvarshdr'] = 'Check variables instantiation';
$string['commonsiunit'] = 'Common SI units';
$string['correctansweris'] = 'One possible correct answer is: {$a}';
$string['correctfeedback'] = 'For any correct response';
$string['correctfeedback_help'] = 'This feedback will be shown to students that get the maximum mark at this part. It can include global and local variables that will be replaced by their values.';
$string['correctness'] = 'Grading criterion*';
$string['correctness_help'] = '**Required**. You can choose either relative error or absolute error with error range. Relative error cannot be used for algebraic answer type.

For the precise definition of the relative error and absolute error when there is more than one answer field, see documentation.';
$string['correctness_link'] = 'https://dynamiccourseware.org/';
$string['correctnessexpert'] = 'Expert';
$string['correctnesssimple'] = 'Simplified mode';
$string['defaultanswermark'] = 'Default part\'s mark';
$string['defaultanswermark_desc'] = 'Default part\'s mark for new question\'s parts';
$string['defaultanswertype'] = 'Default answer type';
$string['defaultanswertype_desc'] = 'Default answer type for new question\'s parts';
$string['defaultcorrectness'] = 'Default grading criteria';
$string['defaultcorrectness_desc'] = 'Default grading criteria for new question\'s parts';
$string['defaultunitpenalty'] = 'Default unit penalty';
$string['defaultunitpenalty_desc'] = 'Default penalty for wrong unit (0-1)';
$string['defaultwidth_algebraic_formula'] = 'Answer type "Algebraic formula"';
$string['defaultwidth_algebraic_formula_desc'] = 'Default width of the input field for answer type "Algebraic formula"';
$string['defaultwidth_number'] = 'Answer type "Number"';
$string['defaultwidth_number_desc'] = 'Default width of the input field for answer type "Number"';
$string['defaultwidth_number_unit'] = 'Combined field "Number"';
$string['defaultwidth_number_unit_desc'] = 'Default width of the combined input field for answer type "Number"';
$string['defaultwidth_numeric'] = 'Answer type "Numeric"';
$string['defaultwidth_numeric_desc'] = 'Default width of the input field for answer type "Numeric"';
$string['defaultwidth_numeric_unit'] = 'Combined field "Numeric"';
$string['defaultwidth_numeric_unit_desc'] = 'Default width of the combined input field for answer type "Numeric"';
$string['defaultwidth_numerical_formula'] = 'Answer type "Numerical formula"';
$string['defaultwidth_numerical_formula_desc'] = 'Default width of the input field for answer type "Numerical formula"';
$string['defaultwidth_numerical_formula_unit'] = 'Combined field "Numerical formula"';
$string['defaultwidth_numerical_formula_unit_desc'] = 'Default width of the combined input field for answer type "Numerical formula"';
$string['defaultwidth_unit'] = 'Separate unit field';
$string['defaultwidth_unit_desc'] = 'Default width of the separate unit field';
$string['defaultwidthunit'] = 'Unit of length';
$string['defaultwidthunit_desc'] = 'Unit of length used for the default width settings below. The units "em" or "rem" correspond approximately to the width of one digit.';
$string['error_algebraic_relerr'] = 'Relative error (_relerr) cannot be used with answer type algebraic formula.';
$string['error_algvar_numbers'] = 'Algebraic variables can only be initialized with a list of numbers.';
$string['error_answer_missing'] = 'No answer has been defined.';
$string['error_answer_missing_in_part'] = 'No answer has been defined for part {$a}.';
$string['error_answerbox_duplicate'] = 'Answer box placeholders must be unique, found second instance of {$a}.';
$string['error_bitshift_integer'] = 'Bit shift operator should only be used with integers.';
$string['error_bitshift_negative'] = 'Bit shift by negative number {$a} is not allowed.';
$string['error_bitwand_integer'] = 'Bitwise AND should only be used with integers.';
$string['error_bitwor_integer'] = 'Bitwise OR should only be used with integers.';
$string['error_bitwxor_integer'] = 'Bitwise XOR should only be used with integers.';
$string['error_cannotusealgebraic'] = 'Algebraic variable \'{$a}\' cannot be used in this context.';
$string['error_criterion_empty'] = 'The grading criterion must not be empty.';
$string['error_db_delete'] = 'Could not delete record from the database, table {$a}.';
$string['error_db_missing_options'] = 'Formulas question ID {$a} was missing an options record. Using default.';
$string['error_db_read'] = 'Could not read from to the database, table {$a}.';
$string['error_db_write'] = 'Could not write to the database, table {$a}.';
$string['error_diff_binary_function_needslist'] = 'When using map() with the binary function \'{$a}\', at least one argument must be a list.';
$string['error_diff_binary_function_two'] = 'When using map() with the binary function \'{$a}\', two arguments are expected.';
$string['error_diff_binary_operator_needslist'] = 'When using map() with the binary operator \'{$a}\', at least one argument must be a list.';
$string['error_diff_binary_operator_two'] = 'When using map() with the binary operator \'{$a}\', two arguments are expected.';
$string['error_diff_binary_samesize'] = 'When using map() with two lists, they must both have the same size.';
$string['error_diff_first'] = 'The first argument of diff() must be a list.';
$string['error_diff_first_invalid'] = '\'{$a}\' is not a legal first argument for the map() function.';
$string['error_diff_firstlist_content'] = 'When using diff(), the first list must contain only numbers or only strings.';
$string['error_diff_firstlist_mismatch'] = 'diff(): type mismatch for element #{$a} (zero-indexed) of the first list.';
$string['error_diff_function_more_args'] = 'The function \'{$a}\' cannot be used with map(), because it expects more than two arguments.';
$string['error_diff_function_no_args'] = 'The function \'{$a}\' cannot be used with map(), because it accepts no arguments.';
$string['error_diff_samesize'] = 'diff() expects two lists of the same size.';
$string['error_diff_second'] = 'The second argument of diff() must be a list.';
$string['error_diff_secondlist_mismatch'] = 'diff(): type mismatch for element #{$a} (zero-indexed) of the second list.';
$string['error_diff_third'] = 'The third argument of diff() can only be used when working with lists of strings.';
$string['error_diff_unary_function'] = 'When using map() with the unary function \'{$a}\', only one list is accepted.';
$string['error_diff_unary_needslist'] = 'When using map() with \'{$a}\', the argument must be a list.';
$string['error_diff_unary_operator'] = 'When using map() with the unary operator \'{$a}\', only one list is accepted.';
$string['error_distribution_outcomes'] = '{$a} expects the number of successful outcomes to be a non-negative integer.';
$string['error_distribution_tries'] = '{$a} expects the number of tries to be a non-negative integer.';
$string['error_divzero'] = 'Division by zero is not defined.';
$string['error_emptyrange'] = 'Evaluation error: range from {$a->start} to {$a->end} with step {$a->step} will be empty.';
$string['error_emptystack'] = 'Evaluation error: empty stack - did you pass enough arguments for the function or operator?';
$string['error_evaluate_invocation'] = 'Bad invocation of {$a}.';
$string['error_evaluation_unknown_nan_inf'] = 'Unknown error while applying operator {$a}, result was (positive or negative) infinity or not a number (NAN).';
$string['error_expectbraceorstatement'] = 'Syntax error: { or statement expected.';
$string['error_expectbracketorvarname'] = 'Syntax error: [ or variable name expected.';
$string['error_expectclosingparen'] = 'Syntax error: ) expected.';
$string['error_expected_intindex'] = 'Evaluation error: index should be an integer, found \'{$a}\'.';
$string['error_expected_number'] = 'Number expected.';
$string['error_expected_number_found'] = 'Number expected, found {$a->found}.';
$string['error_expected_number_found_algebraicvar'] = 'Number expected, found algebraic variable.';
$string['error_expected_number_found_list'] = 'Number expected, found list.';
$string['error_expected_scalar'] = 'Scalar value expected.';
$string['error_expected_scalar_found'] = 'Scalar value expected, found {$a->found}.';
$string['error_expected_scalar_found_algebraicvar'] = 'Scalar value expected, found algebraic variable.';
$string['error_expected_scalar_found_list'] = 'Scalar value expected, found list.';
$string['error_expects_number'] = '{$a->who} expects a number.';
$string['error_expects_number_found'] = '{$a->who} expects a number, found {$a->found}.';
$string['error_expects_scalar'] = '{$a->who} expects a scalar value.';
$string['error_expects_scalar_found'] = '{$a->who} expects a scalar value, found {$a->found}.';
$string['error_fact_toolarge'] = 'Cannot compute {$a}! on this platform, the result is bigger than PHP_MAX_INT.';
$string['error_for_expectcolon'] = 'Syntax error: : expected.';
$string['error_for_expectidentifier'] = 'Syntax error: identifier expected.';
$string['error_for_expectparen'] = 'Syntax error: ( expected after for.';
$string['error_forgotoperator'] = 'Syntax error: did you forget to put an operator?';
$string['error_func_all_lists'] = '{$a} expects its arguments to be lists.';
$string['error_func_argcount'] = 'Invalid number of arguments for function \'{$a->function}\': {$a->count} given.';
$string['error_func_first_int'] = '{$a} expects its first argument to be an integer.';
$string['error_func_first_list'] = '{$a} expects its first argument to be a list.';
$string['error_func_first_nnegint'] = '{$a} expects its first argument to be a non-negative integer.';
$string['error_func_first_number'] = '{$a} expects its first argument to be a number.';
$string['error_func_first_nzeroint'] = '{$a} expects its first argument to be a non-zero integer.';
$string['error_func_first_posint'] = '{$a} expects its first argument to be a positive integer.';
$string['error_func_nan'] = 'Result of function \'{$a}\' was not a number.';
$string['error_func_nnegint'] = '{$a} expects its argument to be a non-negative integer.';
$string['error_func_paren'] = 'Syntax error: function must be followed by opening parenthesis.';
$string['error_func_positive'] = '{$a} expects its argument to be a positive number.';
$string['error_func_second_int'] = '{$a} expects its second argument to be an integer.';
$string['error_func_second_nnegint'] = '{$a} expects its second argument to be a non-negative integer.';
$string['error_func_second_nzeronum'] = '{$a} expects its second argument to be a non-zero number.';
$string['error_func_second_posint'] = '{$a} expects its second argument to be a positive integer.';
$string['error_func_third_posint'] = '{$a} expects its third argument to be a positive integer.';
$string['error_grading_not_one'] = 'The grading criterion should evaluate to 1 for correct answers. Found {$a} instead.';
$string['error_grading_single_expression'] = 'The grading criterion should be one single expression. Found {$a} statements instead.';
$string['error_import_missing_field'] = 'Import error. Missing field: {$a} ';
$string['error_in_answer'] = 'Error in answer #{$a->answerno}: {$a->message}';
$string['error_indexoutofrange'] = 'Evaluation error: index {$a} out of range.';
$string['error_inv_consec'] = 'When using inv(), the numbers in the list must be consecutive.';
$string['error_inv_integers'] = 'inv() expects all elements of the list to be integers; floats will be truncated.';
$string['error_inv_list'] = 'inv() expects a list.';
$string['error_inv_nodup'] = 'When using inv(), the list must not contain the same number multiple times.';
$string['error_inv_smallest'] = 'When using inv(), the smallest number in the list must be 0 or 1.';
$string['error_invalidalgebraic'] = '\'{$a}\' is not a valid algebraic expression.';
$string['error_invalidargsep'] = 'Syntax error: invalid use of separator token \',\'.';
$string['error_invalidcodepoint'] = 'Invalid UTF-8 codepoint escape sequence.';
$string['error_invalidcodepoint_toolarge'] = 'Invalid UTF-8 codepoint escape sequence: Codepoint larger than 0x10FFFF.';
$string['error_invalidcontext'] = 'Invalid variable context given, aborting import.';
$string['error_invalidrandvardef'] = 'Invalid definition of a random variable - you must provide a list of possible values.';
$string['error_invalidrangesep'] = 'Syntax error: invalid use of range separator \':\'.';
$string['error_invalidunary'] = 'Invalid use of unary operator: {$a}.';
$string['error_invalidvarname'] = 'Invalid variable name: {$a}.';
$string['error_len_argument'] = 'len() expects a list or a string.';
$string['error_list_too_large'] = 'List must not contain more than {$a} elements.';
$string['error_map_unknown'] = 'Evaluation error in map(): {$a}';
$string['error_mark'] = 'The answer mark must take a value larger than 0.';
$string['error_model_answer_no_content'] = 'Invalid answer: the model answer contains no evaluable symbols.';
$string['error_model_answer_prefix'] = 'Invalid answer: Please do not use the prefix operator \ in model answers with this answer type.';
$string['error_no_answer'] = 'At least one answer is required.';
$string['error_notindexable'] = 'Evaluation error: indexing is only possible with lists and strings.';
$string['error_number_for_numeric_answertypes'] = 'Invalid answer format: this answer type expects one number or a list of numbers.';
$string['error_onlyoneindex'] = 'Evaluation error: only one index supported when accessing array elements.';
$string['error_parenmismatch'] = 'Mismatched parentheses, \'{$a->closer}\' is closing \'{$a->opener}\' from row {$a->row} and column {$a->column}.';
$string['error_parennotclosed'] = 'Unbalanced parenthesis, \'{$a}\' is never closed.';
$string['error_pick_two'] = 'When called with two arguments, pick() expects the second parameter to be a list.';
$string['error_placeholder_format'] = 'Wrong placeholder\'s format or forbidden characters.';
$string['error_placeholder_main_duplicate'] = 'Duplicated placeholder in the main question text.';
$string['error_placeholder_missing'] = 'This placeholder is missing from the main question text.';
$string['error_placeholder_sub_duplicate'] = 'This placeholder has already been defined in some other part.';
$string['error_placeholder_too_long'] = 'The placeholder\'s length is limited to 40 characters.';
$string['error_poly_one'] = 'When calling poly() with one argument, it must be a number or a list of numbers.';
$string['error_poly_string'] = 'When calling poly() with a string, the second argument must be a number or a list of numbers.';
$string['error_poly_stringlist'] = 'When calling poly() with a list of strings, the second argument must be a list of numbers.';
$string['error_poly_two'] = 'When calling poly() with two arguments, the first must be a string or a list of strings.';
$string['error_power_negbase_expfrac'] = 'Base cannot be negative with fractional exponent.';
$string['error_power_negbase_expzero'] = 'Division by zero is not defined, so base cannot be zero for negative exponents.';
$string['error_power_zerozero'] = 'Power 0^0 is not defined.';
$string['error_prefix'] = 'Syntax error: invalid use of prefix character \\.';
$string['error_probability'] = '{$a} expects the probability to be at least 0 and not more than 1.';
$string['error_question_damaged'] = 'Error: question is damaged, number of text fragments and number of question parts are not equal.';
$string['error_rangesyntax'] = 'Syntax error in range definition.';
$string['error_rangeusage'] = 'Syntax error: ranges can only be used in {} or [].';
$string['error_rule'] = 'Rule parsing error. ';
$string['error_ruleid'] = 'No such rule exists in the file with the id/name.';
$string['error_samestartend'] = 'Syntax error: start and end of range must not be equal.';
$string['error_setindividual_algebraicvar'] = 'Setting individual elements is not supported for algebraic variables.';
$string['error_setindividual_randvar'] = 'Setting individual elements is not supported for random variables.';
$string['error_setindividual_string'] = 'Individual chars of a string cannot be modified.';
$string['error_setinlist'] = 'Syntax error: sets cannot be used inside a list.';
$string['error_setnested'] = 'Syntax error: sets cannot be nested.';
$string['error_sort_samesize'] = 'When calling sort() with two lists, they must have the same size.';
$string['error_sort_twolists'] = 'When calling sort() with two arguments, they must both be lists.';
$string['error_stacksize'] = 'Stack should contain exactly one element after evaluation - did you forget a semicolon somewhere?';
$string['error_stepzero'] = 'Syntax error: step size of a range cannot be zero.';
$string['error_str_argument'] = 'str() expects a scalar argument, e. g. a number.';
$string['error_strayparen'] = 'Unbalanced parenthesis, stray \'{$a}\' found.';
$string['error_string_for_algebraic_formula'] = 'Invalid answer format: the answer type "algebraic formula" expects one single string wrapped in quotes or a list of strings, each wrapped in quotes.';
$string['error_sublist_indices'] = 'sublist() expects the indices to be integers, found \'{$a}\'.';
$string['error_sublist_lists'] = 'sublist() expects its arguments to be lists.';
$string['error_sublist_outofrange'] = 'Index {$a} out of range in sublist().';
$string['error_sum_argument'] = 'sum() expects a list of numbers.';
$string['error_ternary_incomplete'] = 'Syntax error: incomplete ternary operator or misplaced \'?\'.';
$string['error_ternary_missmiddle'] = 'Syntax error: ternary operator missing middle part.';
$string['error_ternary_notenough'] = 'Evaluation error: not enough arguments for ternary operator.';
$string['error_tokenconversion'] = 'The given value \'{$a}\' has an invalid data type and cannot be converted to a token.';
$string['error_undefinedconstant'] = 'Undefined constant: \'{$a}\'';
$string['error_unexpectedend'] = 'Syntax error: unexpected end of expression after \'{$a}\'.';
$string['error_unexpectedinput'] = 'Unexpected input: \'{$a}\'';
$string['error_unexpectedtoken'] = 'Unexpected token: {$a}';
$string['error_unit'] = 'Unit parsing error';
$string['error_unitpenalty'] = 'The penalty must be a number between 0 and 1.';
$string['error_unknownfunction'] = 'Unknown function: \'{$a}\'';
$string['error_unknownvarname'] = 'Unknown variable: {$a}';
$string['error_unterminatedstring'] = 'Unterminated string, started at row {$a->row}, column {$a->column}.';
$string['error_variablelhs'] = 'Left-hand side of assignment must be a variable.';
$string['error_wrapnumber'] = 'Cannot wrap a non-numeric value into a NUMBER token.';
$string['error_wrapstring'] = 'Cannot wrap the given value into a STRING token.';
$string['feedback'] = 'Part general feedback';
$string['feedback_help'] = 'This part feedback will be shown to all students. It can include global and local variables that will be replaced by their values.';
$string['globalvarshdr'] = 'Variables';
$string['incorrectfeedback'] = 'For any incorrect response';
$string['incorrectfeedback_help'] = 'This feedback will be shown to students that don\'t get any mark at this part. It can include global and local variables that will be replaced by their values.';
$string['instantiate'] = 'Instantiate';
$string['mainq'] = 'Main question';
$string['modelanswer'] = 'Model answer';
$string['none'] = 'None';
$string['number'] = 'Number';
$string['number_unit'] = 'Number and unit';
$string['numdataset'] = 'Number of data sets';
$string['numeric'] = 'Numeric';
$string['numeric_unit'] = 'Numeric and unit';
$string['numerical_formula'] = 'Numerical formula';
$string['numerical_formula_unit'] = 'Numerical formula and unit';
$string['otherrule'] = 'Other rules';
$string['otherrule_help'] = 'Here the question author can define additional conversion rules for other accepted base units. See documentation for the advanced usages.';
$string['partiallycorrectfeedback'] = 'For any partially correct response';
$string['partiallycorrectfeedback_help'] = 'This feedback will be shown to students that don\'t get the maximum mark at this part. It can include global and local variables that will be replaced by their values.';
$string['placeholder'] = 'Placeholder name';
$string['placeholder_help'] = 'A placeholder is a used to identify the location in the main question
text that will be replaced by the content of the part. It is a
string of alphanumeric characters prefixed by \'**#**\', such as #1, #2a, #2b and #A.

If this field is left empty, the part will be appended at the end of the main question text.';
$string['pleaseputananswer'] = 'Please put an answer in each input field.';
$string['pluginname'] = 'Formulas';
$string['pluginname_help'] = 'To start using this question, please go to <a href="https://dynamiccourseware.org/">dynamiccourseware.org</a>.

 For possible questions, please go to <a href="https://dynamiccourseware.org/">dynamiccourseware.org</a>.

 For the options in the editing form below, please go to <a href="https://dynamiccourseware.org/">dynamiccourseware.org</a>.

 For the full documentation, please go to <a href="https://dynamiccourseware.org/">dynamiccourseware.org</a>.';
$string['pluginname_link'] = 'question/type/formulas';
$string['pluginnameadding'] = 'Adding a Formulas question';
$string['pluginnameediting'] = 'Editing a Formulas question';
$string['pluginnamesummary'] = 'Question type with random values and multiple answers. The answer fields can be placed anywhere so that we can create questions involving various structures such as vectors, polynomials and matrices. Other features such as unit checking and multiple parts questions are also integrated tightly and easy to use.';
$string['postunit'] = 'Unit';
$string['postunit_help'] = 'You can specify the unit here. This question type is specially designed for SI units, so an
empty space represents the product of different base units and <tt>^</tt> is used for exponents. Also, <tt>/</tt> can be used
for inverse exponent. Any permutation of the base units are treated the same.

Students are required to use the same input format. Examples:
<pre class="prettyprint">1 m<br>0.1 m^2<br>20 m s^(-1)<br>400 kg m/s<br>100 kW</pre>';
$string['previewerror'] = 'No preview available. Check your definition of random variables, global variables, parts\' local variables and answers. Original error message: {$a}';
$string['privacy:metadata'] = 'The Formulas question type plugin does not store any personal data.';
$string['qtextpreview'] = 'Preview';
$string['questiontext'] = 'Question text';
$string['questiontext_help'] = 'In addition to the normal question text, you can also use global variables and placeholders here.

Global variables will be replaced by their values and placeholders will be replaced by parts.

A simple example with variables <tt>A, B, C</tt> and placeholders <tt>#1, #2, #3</tt> is:

<pre class="prettyprint">What is the result of {A} + {B}?<br>{#1}<br>What is the result of {A} - {B}?<br>{#2}<br>What is the result of {C} / {B}?<br>{#3}</pre>';
$string['relerror'] = 'Relative error';
$string['response_right'] = 'Right';
$string['response_wrong'] = 'Wrong';
$string['response_wrong_unit'] = 'Right value, wrong unit';
$string['response_wrong_value'] = 'Wrong value, right unit';
$string['ruleid'] = 'Basic conversion rules';
$string['ruleid_help'] = 'This question type has a built-in unit conversion system and has basic conversion rules.

The basic one is the "Common SI units" rules that will convert standard units such as unit for length,
say, km, m, cm and mm. This option has no effect if no unit has been used.';
$string['settingallowdecimalcomma'] = 'Localised decimal separator';
$string['settingallowdecimalcomma_desc'] = 'Allow all students to use the comma as decimal separator in answers.<br>If activated, numbers will be displayed according to the locale settings.';
$string['settingdebouncedelay'] = 'Delay before on-the-fly validation';
$string['settingdebouncedelay_desc'] = 'The timespan between the last modification of an answer field and the on-the-fly validation.';
$string['settinglenientimport'] = 'Lenient check on import';
$string['settinglenientimport_desc'] = 'When importing a question, do not check whether the provided model answers would receive full marks. <br>Note: You should only activate this setting temporarily.';
$string['settings_heading_general'] = 'General preferences';
$string['settings_heading_general_desc'] = '';
$string['settings_heading_width'] = 'Default widths';
$string['settings_heading_width_desc'] = 'Default width of input fields for the various answer types. For fields that are left empty, the settings from the plugin\'s style file will be used. Please use this settings carefully. Making the fields too small can make it difficult for your students to type their answer. Note that the exclamation mark icon shown for invalid answers takes up approximately 12 pixels.';
$string['subqoptions'] = 'Unit settings';
$string['subqtext'] = 'Part\'s text';
$string['subqtext_help'] = 'Part text and placement of answer fields can be specified here. The placeholders that can be used to specify answer fields places are:

<pre class="prettyprint">{_0}<br>{_1}<br>{_2}<br>...<br>{_u}</pre>

The <tt>{_0}, {_1}, {_2}</tt> are the different input fields for values and <tt>{_u}</tt> is the input field for the unit.

All missing fields are automatically appended at the end of the part\'s text. A special case is that if <tt>{_0}</tt> and <tt>{_u}</tt> are specified consecutively with no space,
and there is only one answer field and unit, i. e. <tt>{_0}{_u}</tt>, they will be combined into a single long input answer field for both answer and unit.';
$string['uniquecorrectansweris'] = 'The correct answer is: {$a}';
$string['unit'] = 'Unit';
$string['unitpenalty'] = 'Deduction for wrong unit (0-1)*';
$string['unitpenalty_help'] = 'This option specifies the mark you want to penalize the student for a wrong unit.

It takes value between 0 to 1. If it takes value 1, the unit and answer must both be correct in order
to get a mark, the unit and answer are treated as one entity. On the other hand, if set to 0,  students
can get full mark for the correct answer alone, any random string can be entered for the unit and it will
have no effect to the grading.

Therefore, it is recommended to use value 1 whenever the answer has no associated unit.';
$string['vars1'] = 'Local variables';
$string['vars1_help'] = 'You can define variables here in the same way as global variables are defined at the question level. Variables defined here can be used in the part\'s answer or feedback
and their scope of visibility is limited to the part.';
$string['vars2'] = 'Grading variables';
$string['vars2_help'] = 'All local variables and the student\'s responses can be used here. See documentation for advanced usages.';
$string['vars2_link'] = 'https://dynamiccourseware.org/';
$string['varsdata'] = 'Instantiated data sets';
$string['varsglobal'] = 'Global variables';
$string['varsglobal_help'] = 'Formulas can be specified here to manipulate the instantiated random variables
(all random variables are available here). The full list of mathematical
functions and operators is given in the documentation.

<pre class="prettyprint">a = 1.11111;<br>b = exp(3);<br>c = A + a + b;<br>d = sin(1.5*pi) + c;<br>e = round(a, 0);<br>f = [0,1,2,3][A];<br>g = ["zero","one","two","three"][A];<br>distance = sqrt(a**2 + b**2);</pre>';
$string['varsglobal_link'] = 'https://dynamiccourseware.org/';
$string['varsrandom'] = 'Random variables';
$string['varsrandom_help'] = 'New random values are generated for these variables at the beginning of each attempt.  It can
be done by defining a set of elements to choose from:

<pre class="prettyprint">A = {1,2,3};<br>C = {[1,-1], [2,-2], [3,-3]};<br>E = {10:100:10, 100, 1000};</pre>

The elements can be numbers, strings or lists of them. At the start of a new attempt, one element will be drawn from the set and assigned to the variable
on the left.  Also, for a set of number, you can use the range notation like 10:100:10 (see example E above.). </p>';
$string['varsrandom_link'] = 'https://dynamiccourseware.org/';
$string['varsstatistics'] = 'Statistics';
$string['yougotnright'] = 'You have correctly answered {$a} parts of this question.';
$string['yougotoneright'] = 'You have correctly answered 1 part of this question.';

// phpcs:disable moodle.Files.LangFilesOrdering.IncorrectOrder
// phpcs:ignore moodle.Files.LangFilesOrdering.UnexpectedComment
// Strings from the 5.3 branch that we keep in this file in order for them to remain in AMOS.
$string['choiceno'] = 'No';
$string['choiceyes'] = 'Yes';
$string['error_algebraic_var'] = 'Syntax error of defining algebraic variable.';
$string['error_answertype_mistmatch'] = 'Answer type mismatch: Numerical answer type requires number and algebraic answer type requires string';
$string['error_criterion'] = 'The grading criterion must be evaluated to a single number.';
$string['error_damaged_question'] = 'Invalid data. The Formulas question might have been damaged, e. g. during a failed import or restore process.';
$string['error_eval_numerical'] = 'Some expressions cannot be evaluated numerically.';
$string['error_fixed_range'] = 'Syntax error of a fixed range.';
$string['error_forbid_char'] = 'Formula or expression contains forbidden characters or operators.';
$string['error_forloop'] = 'Syntax error of the for loop.';
$string['error_forloop_expression'] = 'Expression of the for loop must be a list.';
$string['error_forloop_var'] = 'Variable of the for loop has some errors.';
$string['error_func_param'] = 'Wrong number or wrong type of parameters for the function {$a}()';
$string['error_grading_error'] = 'Grading error. Probably result of incorrect import file or database corruption.';
$string['error_randvars_set_size'] = 'The number of generable elements in the set must be larger than 1.';
$string['error_randvars_type'] = 'All elements in the set must have exactly the same type and size.';
$string['error_subexpression_empty'] = 'A subexpression is empty.';
$string['error_syntax'] = 'Syntax error.';
$string['error_validation_eval'] = 'Try evaluation error. ';
$string['error_vars_array_index_nonnumeric'] = 'Non-numeric value cannot be used as list index.';
$string['error_vars_array_index_out_of_range'] = 'List index out of range.';
$string['error_vars_array_size'] = 'Size of list must be within 1 to 1000.';
$string['error_vars_array_type'] = 'Element in the same list must be of the same type, either number or string.';
$string['error_vars_array_unsubscriptable'] = 'Variable is unsubscriptable.';
$string['error_vars_bracket_mismatch'] = 'Bracket mismatch.';
$string['error_vars_end_separator'] = 'Missing an assignment separator at the end.';
$string['error_vars_name'] = 'The syntax of the variable name is incorrect.';
$string['error_vars_reserved'] = 'Function {$a}() is reserved and cannot be used as variable.';
$string['error_vars_string'] = 'Error. Either a string without closing double quote, or use of non-accepted character such as \'.';
$string['error_vars_undefined'] = 'Variable \'{$a}\' has not been defined.';
$string['functiontakesatleasttwo'] = 'The function {$a} must have at least two arguments';
$string['functiontakesnoargs'] = 'The function {$a} does not take any arguments';
$string['functiontakesonearg'] = 'The function {$a} must have exactly one argument';
$string['functiontakesoneortwoargs'] = 'The function {$a} must have either one or two arguments';
$string['functiontakesthreeargs'] = 'The function {$a} must have exactly three arguments';
$string['functiontakestwoargs'] = 'The function {$a} must have exactly two arguments';
$string['illegalformulasyntax'] = 'Illegal formula syntax starting with \'{$a}\'';
$string['renew'] = 'Update';
$string['settingusepopup'] = 'Use tooltips';
$string['settingusepopup_desc'] = 'Display correct answer and feedback in a tooltip';
$string['unsupportedformulafunction'] = 'The function {$a} is not supported';
