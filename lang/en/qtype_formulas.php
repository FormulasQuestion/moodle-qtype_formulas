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
 * The language strings for the formulas question type.
 *
 * @package    qtype_formulas
 * @copyright  2010 Hon Wai, Lau <lau65536@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
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
$string['checkvarshdr'] = 'Check variables instantiation';
$string['commonsiunit'] = 'Common SI units';
$string['correctansweris'] = 'One possible correct answer is: {$a}';
$string['correctfeedback'] = 'For any correct response';
$string['correctfeedback_help'] = 'This feedback will be show to students that get the maximum mark at this part. It can include global and local variables that will be replaced by their values.';
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
$string['error_algebraic_relerr'] = 'relative error (_relerr) cannot be used with answer type algebraic formula';
$string['error_algvar_numbers'] = 'algebraic variables can only be initialized with a list of numbers';
$string['error_answer_missing'] = 'No answer has been defined.';
$string['error_answerbox_duplicate'] = 'Answer box placeholders must be unique, found second instance of {$a}.';
$string['error_bitshift_integer'] = 'bit shift operator should only be used with integers';
$string['error_bitshift_negative'] = 'bit shift by negative number {$a} is not allowed';
$string['error_bitwand_integer'] = 'bitwise AND should only be used with integers';
$string['error_bitwor_integer'] = 'bitwise OR should only be used with integers';
$string['error_bitwxor_integer'] = 'bitwise XOR should only be used with integers';
$string['error_cannotusealgebraic'] = 'algebraic variable \'{$a}\' cannot be used in this context';
$string['error_criterion_empty'] = 'The grading criterion must not be empty.';
$string['error_damaged_question'] = 'Invalid data. The Formulas question might have been damaged, e. g. during a failed import or restore process.';
$string['error_db_delete'] = 'Could not delete record from the database, table {$a}.';
$string['error_db_missing_options'] = 'Formulas question ID {$a} was missing an options record. Using default.';
$string['error_db_read'] = 'Could not read from to the database, table {$a}.';
$string['error_db_write'] = 'Could not write to the database, table {$a}.';
$string['error_diff_binary_function_needslist'] = 'when using map() with the binary function \'{$a}\', at least one argument must be a list';
$string['error_diff_binary_function_two'] = 'when using map() with the binary function \'{$a}\', two arguments are expected';
$string['error_diff_binary_operator_needslist'] = 'when using map() with the binary operator \'{$a}\', at least one argument must be a list';
$string['error_diff_binary_operator_two'] = 'when using map() with the binary operator \'{$a}\', two arguments are expected';
$string['error_diff_binary_samesize'] = 'when using map() with two lists, they must both have the same size';
$string['error_diff_first_invalid'] = '\'{$a}\' is not a legal first argument for the map() function';
$string['error_diff_first'] = 'the first argument of diff() must be a list';
$string['error_diff_firstlist_content'] = 'when using diff(), the first list must contain only numbers or only strings';
$string['error_diff_firstlist_mismatch'] = 'diff(): type mismatch for element #{$a} (zero-indexed) of the first list';
$string['error_diff_function_more_args'] = 'the function \'{$a}\' cannot be used with map(), because it expects more than two arguments';
$string['error_diff_function_no_args'] = 'the function \'{$a}\' cannot be used with map(), because it accepts no arguments';
$string['error_diff_samesize'] = 'diff() expects two lists of the same size';
$string['error_diff_second'] = 'the second argument of diff() must be a list';
$string['error_diff_secondlist_mismatch'] = 'diff(): type mismatch for element #{$a} (zero-indexed) of the second list';
$string['error_diff_third'] = 'the third argument of diff() can only be used when working with lists of strings';
$string['error_diff_unary_function'] = 'when using map() with the unary function \'{$a}\', only one list is accepted';
$string['error_diff_unary_needslist'] = 'when using map() with \'{$a}\', the argument must be a list';
$string['error_diff_unary_operator'] = 'when using map() with the unary operator \'{$a}\', only one list is accepted';
$string['error_distribution_outcomes'] = '{$a} expects the number of successful outcomes to be a non-negative integer';
$string['error_distribution_tries'] = '{$a} expects the number of tries to be a non-negative integer';
$string['error_divzero'] = 'division by zero is not defined';
$string['error_emptyrange'] = 'evaluation error: range from {$a->start} to {$a->end} with step {$a->step} will be empty';
$string['error_emptystack'] = 'evaluation error: empty stack - did you pass enough arguments for the function or operator?';
$string['error_evaluate_invocation'] = 'bad invocation of {$a}';
$string['error_evaluation_unknown'] = 'unknown evaluation error';
$string['error_expectbraceorstatement'] = 'syntax error: { or statement expected';
$string['error_expectbracketorvarname'] = 'syntax error: [ or variable name expected';
$string['error_expectclosingparen'] = 'syntax error: ) expected';
$string['error_expected_intindex'] = 'evaluation error: index should be an integer, found \'{$a}\'';
$string['error_expected_number'] = 'number expeced';
$string['error_expected_number_found'] = 'number expected, found {$a->found}';
$string['error_expected_number_found_algebraicvar'] = 'number expected, found algebraic variable';
$string['error_expected_number_found_list'] = 'number expected, found list';
$string['error_expected_scalar'] = 'scalar value expected';
$string['error_expected_scalar_found'] = 'scalar value expected, found {$a->found}';
$string['error_expected_scalar_found_algebraicvar'] = 'scalar value expected, found algebraic variable';
$string['error_expected_scalar_found_list'] = 'scalar value expected, found list';
$string['error_expects_number'] = '{$a->who} expects a number';
$string['error_expects_number_found'] = '{$a->who} expects a number, found {$a->found}';
$string['error_expects_scalar'] = '{$a->who} expects a scalar value';
$string['error_expects_scalar_found'] = '{$a->who} expects a scalar value, found {$a->found}';
$string['error_fact_toolarge'] = 'cannot compute {$a}! on this platform, the result is bigger than PHP_MAX_INT';
$string['error_for_expectcolon'] = 'syntax error: : expected';
$string['error_for_expectidentifier'] = 'syntax error: identifier expected';
$string['error_for_expectparen'] = 'syntax error: ( expected after for';
$string['error_forgotoperator'] = 'syntax error: did you forget to put an operator?';
$string['error_func_all_lists'] = '{$a} expects its arguments to be lists';
$string['error_func_argcount'] = 'invalid number of arguments for function \'{$a->function}\': {$a->count} given';
$string['error_func_first_int'] = '{$a} expects its first argument to be an integer';
$string['error_func_first_list'] = '{$a} expects its first argument to be a list';
$string['error_func_first_nnegint'] = '{$a} expects its first argument to be a non-negative integer';
$string['error_func_first_number'] = '{$a} expects its first argument to be a number';
$string['error_func_first_nzeroint'] = '{$a} expects its first argument to be a non-zero integer';
$string['error_func_first_posint'] = '{$a} expects its first argument to be a positive integer';
$string['error_func_nan'] = 'result of function \'{$a}\' was not a number';
$string['error_func_nnegint'] = '{$a} expects its argument to be a non-negative integer';
$string['error_func_param'] = 'Wrong number or wrong type of parameters for the function {$a}()';
$string['error_func_paren'] = 'syntax error: function must be followed by opening parenthesis';
$string['error_func_positive'] = '{$a} expects its argument to be a positive number';
$string['error_func_second_int'] = '{$a} expects its second argument to be an integer';
$string['error_func_second_nnegint'] = '{$a} expects its second argument to be a non-negative integer';
$string['error_func_second_nzeronum'] = '{$a} expects its second argument to be a non-zero number';
$string['error_func_second_posint'] = '{$a} expects its second argument to be a positive integer';
$string['error_func_third_posint'] = '{$a} expects its third argument to be a positive integer';
$string['error_grading_not_one'] = 'The grading criterion should evaluate to 1 for correct answers. Found {$a} instead.';
$string['error_grading_single_expression'] = 'The grading criterion should be one single expression. Found {$a} statements instead.';
$string['error_import_missing_field'] = 'Import error. Missing field: {$a} ';
$string['error_in_answer'] = 'error in answer #{$a->answerno}: {$a->message}';
$string['error_indexoutofrange'] = 'evaluation error: index out of range: {$a}';
$string['error_inv_consec'] = 'when using inv(), the numbers in the list must be consecutive';
$string['error_inv_integers'] = 'inv() expects all elements of the list to be integers; floats will be truncated';
$string['error_inv_list'] = 'inv() expects a list';
$string['error_inv_nodup'] = 'when using inv(), the list must not contain the same number multiple times';
$string['error_inv_smallest'] = 'when using inv(), the smallest number in the list must be 0 or 1';
$string['error_invalidalgebraic'] = '\'{$a}\' is not a valid algebraic expression';
$string['error_invalidargsep'] = 'syntax error: invalid use of separator token (,)';
$string['error_invalidcontext'] = 'invalid variable context given, aborting import';
$string['error_invalidrandvardef'] = 'invalid definition of a random variable - you must provide a list of possible values';
$string['error_invalidrangesep'] = 'syntax error: invalid use of range separator (:)';
$string['error_invalidunary'] = 'invalid use of unary operator: {$a}';
$string['error_invalidvarname'] = 'invalid variable name: {$a}';
$string['error_len_argument'] = 'len() expects a list or a string';
$string['error_map_unknown'] = 'evaluation error in map(): {$a}';
$string['error_mark'] = 'The answer mark must take a value larger than 0.';
$string['error_model_answer_no_content'] = 'Invalid answer: the model answer contains no evaluable symbols.';
$string['error_model_answer_prefix'] = 'Invalid answer: Please do not use the prefix operator \ in model answers with this answer type.';
$string['error_no_answer'] = 'At least one answer is required.';
$string['error_notindexable'] = 'evaluation error: indexing is only possible with lists and strings';
$string['error_number_for_numeric_answertypes'] = 'Invalid answer format: this answer type expects one number or a list of numbers.';
$string['error_onlyoneindex'] = 'evaluation error: only one index supported when accessing array elements';
$string['error_parenmismatch'] = 'mismatched parentheses, \'{$a->closer}\' is closing \'{$a->opener}\' from row {$a->row} and column {$a->column}';
$string['error_parennotclosed'] = 'unbalanced parenthesis, \'{$a}\' is never closed';
$string['error_pick_two'] = 'when called with two arguments, pick() expects the second parameter to be a list';
$string['error_placeholder_format'] = 'Wrong placeholder\'s format or forbidden characters.';
$string['error_placeholder_main_duplicate'] = 'Duplicated placeholder in the main question text.';
$string['error_placeholder_missing'] = 'This placeholder is missing from the main question text.';
$string['error_placeholder_sub_duplicate'] = 'This placeholder has already been defined in some other part.';
$string['error_placeholder_too_long'] = 'The placeholder\'s length is limited to 40 characters.';
$string['error_poly_one'] = 'when calling poly() with one argument, it must be a number or a list of numbers';
$string['error_poly_string'] = 'when calling poly() with a string, the second argument must be a number or a list of numbers';
$string['error_poly_stringlist'] = 'when calling poly() with a list of strings, the second argument must be a list of numbers';
$string['error_poly_two'] = 'when calling poly() with two arguments, the first must be a string or a list of strings';
$string['error_power_negbase_expfrac'] = 'base cannot be negative with fractional exponent';
$string['error_power_negbase_expzero'] = 'division by zero is not defined, so base cannot be zero for negative exponents';
$string['error_power_zerozero'] = 'power 0^0 is not defined';
$string['error_prefix'] = 'syntax error: invalid use of prefix character \\';
$string['error_probability'] = '{$a} expects the probability to be at least 0 and not more than 1';
$string['error_question_damaged'] = 'Error: Question is damaged, number of text fragments and number of question parts are not equal.';
$string['error_rangesyntax'] = 'syntax error in range definition';
$string['error_rangeusage'] = 'syntax error: ranges can only be used in {} or []';
$string['error_rule'] = 'Rule parsing error! ';
$string['error_ruleid'] = 'No such rule exists in the file with the id/name.';
$string['error_samestartend'] = 'syntax error: start and end of range must not be equal';
$string['error_setindividual_randvar'] = 'setting individual list elements is not supported for random variables';
$string['error_setindividual_string'] = 'individual chars of a string cannot be modified';
$string['error_setinlist'] = 'syntax error: sets cannot be used inside a list';
$string['error_setnested'] = 'syntax error: sets cannot be nested';
$string['error_sort_samesize'] = 'when calling sort() with two lists, they must have the same size';
$string['error_sort_twolists'] = 'when calling sort() with two arguments, they must both be lists';
$string['error_stacksize'] = 'stack should contain exactly one element after evaluation - did you forget a semicolon somewhere?';
$string['error_stepzero'] = 'syntax error: step size of a range cannot be zero';
$string['error_str_argument'] = 'str() expects a scalar argument, e. g. a number';
$string['error_strayparen'] = 'unbalanced parenthesis, stray \'{$a}\' found';
$string['error_string_for_algebraic_formula'] = 'Invalid answer format: the answer type "algebraic formula" expects one single string wrapped in quotes or a list of strings, each wrapped in quotes.';
$string['error_sublist_indices'] = 'sublist() expects the indices to be integers, found \'{$a}\'';
$string['error_sublist_lists'] = 'sublist() expects its arguments to be lists';
$string['error_sublist_outofrange'] = 'index {$a} out of range in sublist()';
$string['error_sum_argument'] = 'sum() expects a list of numbers';
$string['error_ternary_incomplete'] = 'syntax error: incomplete ternary operator or misplaced \'?\'';
$string['error_ternary_missmiddle'] = 'syntax error: ternary operator missing middle part';
$string['error_ternary_notenough'] = 'evaluation error: not enough arguments for ternary operator';
$string['error_tokenconversion'] = 'the given value \'{$a}\' has an invalid data type and cannot be converted to a token';
$string['error_undefinedconstant'] = 'undefined constant: \'{$a}\'';
$string['error_unexpectedend'] = 'syntax error: unexpected end of expression after \'{$a}\'';
$string['error_unexpectedinput'] = 'unexpected input: \'{$a}\'';
$string['error_unexpectedtoken'] = 'unexpected token: {$a}';
$string['error_unit'] = 'Unit parsing error! ';
$string['error_unitpenalty'] = 'The penalty must be a number between 0 and 1.';
$string['error_unknownfunction'] = 'unknown function: \'{$a}\'';
$string['error_unknownvarname'] = 'unknown variable: {$a}';
$string['error_unterminatedstring'] = 'unterminated string, started at row {$a->row}, column {$a->column}';
$string['error_variablelhs'] = 'left-hand side of assignment must be a variable';
$string['error_wrapnumber'] = 'cannot wrap a non-numeric value into a NUMBER token';
$string['error_wrapstring'] = 'cannot wrap the given value into a STRING token';
$string['feedback'] = 'Part general feedback';
$string['feedback_help'] = 'This part feedback will be show to all students. It can include global and local variables that will be replaced by their values.';
$string['globalvarshdr'] = 'Variables';
$string['incorrectfeedback'] = 'For any incorrect response';
$string['incorrectfeedback_help'] = 'This feedback will be show to students that don\'t get any mark at this part. It can include global and local variables that will be replaced by their values.';
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
$string['partiallycorrectfeedback_help'] = 'This feedback will be show to students that don\'t get the maximum mark at this part. It can include global and local variables that will be replaced by their values.';
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
$string['previewerror'] = 'No preview available. Check your definition of random variables, global variables, parts\' local variables and answers. Original error message:';
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
$string['settingusepopup'] = 'Use tooltips';
$string['settingusepopup_desc'] = 'Display correct answer and feedback in a tooltip';
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
