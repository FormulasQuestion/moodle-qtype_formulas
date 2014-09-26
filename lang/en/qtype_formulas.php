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
 *
 * @package    qtype_formulas
 * @copyright  2010 Hon Wai, Lau <lau65536@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
/**
 * The language strings for the formulas question type.
 */

// All the texts that will be seen by students in the quiz interface.

$string['unit'] = 'Unit';
$string['number'] = 'Number';
$string['number_unit'] = 'Number and unit';
$string['numeric'] = 'Numeric';
$string['numeric_unit'] = 'Numeric and unit';
$string['numerical_formula'] = 'Numerical formula';
$string['numerical_formula_unit'] = 'Numerical formula and unit';
$string['algebraic_formula'] = 'Algebraic formula';
$string['yougotnright'] = 'You have correctly answered {$a->num} parts of this question.';
$string['pleaseputananswer'] = 'Please put an answer in each input field.';

$string['modelanswer'] = 'Model answer';

// Texts that will be shown in the question editing interface.
$string['addmorepartsblanks'] = 'Blanks for {no} more parts';
$string['pluginname'] = 'Formulas';
$string['pluginnameadding'] = 'Adding a formulas question';
$string['pluginnameediting'] = 'Editing a formulas question';
$string['pluginname_help'] = 'To start using this question, please read the <a href="http://code.google.com/p/moodle-coordinate-question/wiki/Tutorial">Tutorial</a>. <br><br>'
    . 'For possible questions, please download and import the <a href="http://code.google.com/p/moodle-coordinate-question/downloads/list">Example</a>, or see the <a href="http://code.google.com/p/moodle-coordinate-question/wiki/ScreenShots">Screenshots</a>. <br>'
    . 'For the options in the editing form below, please read <a href="http://code.google.com/p/moodle-coordinate-question/wiki/QuestionOptions">Question Options</a> (<a href="type/formulas/lang/en/help/formulas/questionoptions.html">Local copy</a>) <br>'
    . 'For the full documentation, please read <a href="http://code.google.com/p/moodle-coordinate-question/wiki/Documentation">Documentation</a> (<a href="type/formulas/lang/en/help/formulas/formulas.html">Local copy</a>) <br>';
$string['pluginnamesummary'] = 'Question type with random values and multiple answers.'
    . 'The answer fields can be placed anywhere so that we can create questions involving various structures such as vectors, polynomials and matrix.'
    . 'Other features such as unit checking and multiple parts questions are also integrated tightly and easy to use.';
$string['pluginname_link'] = 'question/type/formulas';
$string['questiontext'] = 'Question text';
$string['questiontext_help'] = '<p>In addition to the normal question text, you can also use global variables and placeholders here.</p>
<p>Global variables will be replaced by their values and placeholders will be replaced by parts. A simple example with variables <tt> A, B, C </tt> and placeholders <tt> #1, #2, #3 </tt> is:</p>
<pre class="prettyprint">What is the result of {A} + {B}?<br>{#1}<br>What is the result of {A} - {B}?<br>{#2}<br>What is the result of {C} / {B}?<br>{#3}</pre>';

// The language string for the global variables.
$string['globalvarshdr'] = 'Variables';
$string['varsrandom'] = 'Random variables';
$string['varsrandom_help'] = '<p>New random values are generated for these variables at the beginning of each attempt.  It can
be done by defining a set of elements to choose from: </p><pre class="prettyprint">A = {1,2,3};<br>C = {[1,-1], [2,-2], [3,-3]};<br>E = {10:100:10, 100, 1000};</pre><p>The
 elements can be numbers, strings or lists of them. At the start of a new attempt, one element will be drawn from the set and assigned to the variable
on the left.  Also, for a set of number, you can use the range
notation like 10:100:10 (e.g. E). </p>';
$string['varsrandom_link'] = 'http://code.google.com/p/moodle-coordinate-question/wiki/Documentation#Random_variables';
$string['varsglobal'] = 'Global variables';
$string['varsglobal_help'] = '<p>Formulas can be specified here to manipulate the instantiated random variables
(all random variables are available here). The full list of mathematical
functions and operators is given in the documentation.</p>
<pre class="prettyprint">a = 1.11111;<br>b = exp(3);<br>c = A + a + b;<br>d = sin(1.5*pi()) + c;<br>e = round(a, 0);<br>f = [0,1,2,3][A];<br>g = ["zero","one","two","three"][A];<br>distance = sqrt(a*a + b*b);</pre>';

// The language string for the display and flow options and common question parts settings.
$string['mainq'] = 'Main question';
$string['subqoptions'] = 'Extra options';
$string['choiceyes'] = 'Yes';
$string['choiceno'] = 'No';

// The language string for the questions parts.
$string['answerno'] = 'Part {$a}';
$string['placeholder'] = 'Placeholder name';
$string['placeholder_help'] = '<p>A placeholder is a used to identify the location in the main question
text that will be replaced by the content of the part. It is a
string of alphanumeric characters prefixed by \'<strong>#</strong>\', such as #1, #2a, #2b and #A. </p><p>If
 this field is left empty, the part
will be appended at the end of the main question text.</p>';
$string['answermark'] = 'Default answer mark*';
$string['answermark_help'] = '<p>Required.
 The mark for the answer of this part, which should be a number
greater than 0. The default mark of the whole question is the sum of all its parts
 default marks. </p><p>Note: If this answer mark field is left blank, the part will be deleted when the question is successfully saved.</p>';
$string['answertype'] = 'Answer type';
$string['answertype_help'] = '<p>There
 are four answer types. Number, numeric and numerical formula answers
requires number or a list of numbers as answer. Algebraic formula answers requires a string or
list of strings as answer. </p><p>Different answer types will
impose different restrictions while inputting answers, so students will
need to know how to input them. The format check in the question code will
also tell them when they type if something is wrong. Please read the
documentation for details.</p>';
$string['answertype_link'] = 'http://code.google.com/p/moodle-coordinate-question/wiki/Documentation#Answer_type';
$string['vars1'] = 'Local variables';
$string['vars1_help'] ='<p>You can define variables here in the same way as global variables are defined at the question level. Variables defined here can be used in the part\'s ansver or feedback
and their scope of visibility is limited to the part.</p>';
$string['answer'] = 'Answer*';
$string['answer_help'] = '<p>Required.
 must be a list of numbers, or a list of strings depending on the answer
type chosen. When there is only one answer, a number or string can be
entered directly. Please note that the number of elements in the list
defines the number of answer fileds for this part.  </p><pre class="prettyprint">123<br>[1, 0, 0, 1]<br>a<br>[1, a, b]<br>"exp(-a t)"<br>["vx t","vy t - 0.5 a t^2"]</pre>';
$string['vars2'] = 'Grading variables';
$string['vars2_help'] = '<p>All local variables and the student\'s responses can be used here. See documentation for advanced usages. </p>';
$string['vars2_link'] = 'http://code.google.com/p/moodle-coordinate-question/wiki/Documentation#Grading_variables';
$string['correctness'] = 'Grading criteria*';
$string['correctness_help'] = '<p>Required. You can choose either relative error or absolute error with error
range. Relative error cannot be used for algebraic
answer type. </p><p>For the precise definition of the relative error and absolute error when there is more than one answer field, see documentation. </p>';
$string['correctness_link'] = 'http://code.google.com/p/moodle-coordinate-question/wiki/Documentation#Manual_grading_criteria';
$string['postunit'] = 'Unit';
$string['postunit_help'] = '<p>You
 can specify the unit here. This question type is specially designed for SI unit, so an
empty space represents the \'product\' of different \'base unit\' and <tt> ^ </tt> is used for exponents.
Also, <tt> / </tt> can be used for inverse exponent. Any permutation of the base unit are treated the same. </p>
<p>Students are required to use the same input format. For example, </p>
<pre class="prettyprint">1 m<br>0.1 m^2<br>20 m s^(-1)<br>400 kg m/s<br>100 kW</pre>';
$string['unitpenalty'] = 'Deduction for wrong unit (0-1)*';
$string['unitpenalty_help'] = '<p>This option specify the mark you want to penalize the student for a wrong unit. </p><p>It
 takes value between 0 to 1. If it takes value 1, the unit and answer
must be correct at the same time in order to get mark. That is the unit
and answer are treated as one entity. On the other hand, if it takes
value 0, students can get full mark for only correct answer, all random
string will have no effect at the end of answer. Therefore, it is
recommended to use value 1 whenever the answer has no associated unit. </p>';
$string['ruleid'] = 'Basic conversion rules';
$string['ruleid_help'] = '<p>This question type has a build-in unit conversion system and has basic conversion rules.</p>
<p>The basic one is the "Common SI unit" rules that will convert standard units
 such as unit for length, say, km, m, cm and mm. This option has no
effect if no unit has been used. </p>';
$string['otherrule'] = 'Other rules';
$string['otherrule_help'] = '<p>Here the question\' author can define additional  conversion rules for other accepted base units. See documentation for the advanced usages.</p>';
$string['subqtext'] = 'Part\'s text';
$string['subqtext_help'] = '<p>Part text and answer fields places can be specified here. The placeholders that can be used to specifie answer fields places are: </p><pre class="prettyprint">{_0}<br>{_1}<br>{_2}<br>...<br>{_u}</pre><p>The <tt> {_0}, {_1}, {_2} </tt> are the input fields for coordinates and <tt> {_u} </tt> is the input field for unit. </p><p>All missing fields are automatically appended at the end of the part\'s text. A special case is that if <tt> {_0}, {_u} </tt> are specified consecutively, and there is only one coordinate and unit, i.e. <tt> {_0}{_u} </tt>, they will be combined into a single long input answer field for both answer and unit. </p>
';
$string['feedback'] = 'Feedback';
$string['feedback_help'] = 'This part feedback will be show to students that don\'t get the maximum mark at this part. It can include global and locals variables that will be replaced by their values';
$string['globaloptions'] = '[Global] - ';

// The language string for the variables instantiation and preview.
$string['checkvarshdr'] = 'Check variables instantiation';
$string['numdataset'] = 'Number of dataset';
$string['qtextpreview'] = 'Preview using dataset';
$string['varsstatistics'] = 'Statistics';
$string['varsdata'] = 'Instantiated dataset';

// Errors message used by editing form's validation.
$string['error_no_answer'] = 'At least one answer is required.';
$string['error_mark'] = 'The answer mark must take a value larger than 0.';
$string['error_placeholder_too_long'] = 'The placeholder\'s length is limited to 40 characters.';
$string['error_placeholder_format'] = 'Wrong placeholder\'s format or forbidden characters.';
$string['error_placeholder_missing'] = 'This placeholder is missing from the main question text.';
$string['error_placeholder_main_duplicate'] = 'Duplicated placeholder in the main question text.';
$string['error_placeholder_sub_duplicate'] = 'This placeholder has already been defined in some other part.';
$string['error_answerbox_duplicate'] = 'Each answer field placeholder can only be used once in a part.';
$string['error_answertype_mistmatch'] = 'Answer type mismatch: Numerical answer type requires number and algebraic answer type requires string';
$string['error_answer_missing'] = 'No answer has been defined.';
$string['error_criterion'] = 'The grading criterion must be evaluated to a single number.';
$string['error_forbid_char'] = 'Formula or expression contains forbidden characters or operators.';
$string['error_unit'] = 'Unit parsing error! ';
$string['error_ruleid'] = 'No such rule exists in the file with the id/name.';
$string['error_rule'] = 'Rule parsing error! ';
$string['error_unitpenalty'] = 'The penalty must be a number between 0 and 1.';
$string['error_validation_eval'] = 'Try evalution error! ';
$string['error_syntax'] = 'Syntax error.';     // Generic syntax error.
$string['error_vars_name'] = 'The syntax of the variable name is incorrect.';
$string['error_vars_string'] = 'Error! Either a string without closing quotation, or use of non-accepted character such as \'.';
$string['error_vars_end_separator'] = 'Missing an assignment separator at the end.';
$string['error_vars_array_size'] = 'Size of list must be within 1 to 1000.';
$string['error_vars_array_type'] = 'Element in the same list must be of the same type, either number or string.';
$string['error_vars_array_index_nonnumeric'] = 'Non-numeric value cannot be used as list index.';
$string['error_vars_array_unsubscriptable'] = 'Variable is unsubscriptable.';
$string['error_vars_array_index_out_of_range'] = 'List index out of range !!!';
$string['error_vars_reserved'] = 'Function {$a}() is reserved and cannot be used as variable.';
$string['error_vars_undefined'] = 'Variable \'{$a}\' has not been defined.';
$string['error_vars_bracket_mismatch'] = 'Bracket mismatch.';
$string['error_forloop'] = 'Syntax error of the for loop.';
$string['error_forloop_var'] = 'Variable of the for loop has some errors.';
$string['error_forloop_expression'] = 'Expression of the for loop must be a list.';
$string['error_randvars_type'] = 'All elements in the set must have exactly the same type and size.';
$string['error_randvars_set_size'] = 'The number of generable elements in the set must be larger than 1.';
$string['error_fixed_range'] = 'Syntax error of a fixed range.';
$string['error_algebraic_var'] = 'Syntax error of defining algebraic variable.';
$string['error_func_param'] = 'Wrong number or wrong type of parameters for the function $a()';
$string['error_subexpression_empty'] = 'A subexpression is empty.';
$string['error_eval_numerical'] = 'Some expressions cannot be evaluated numerically.';

// The language strings for the renderer.
$string['correctansweris'] = 'One possible correct answer is: {$a}';

// String that were "borrowed" from quiz and are now in calculated plugin.
$string['illegalformulasyntax'] = 'Illegal formula syntax starting with \'{$a}\'';
$string['functiontakesnoargs'] = 'The function {$a} does not take any arguments';
$string['functiontakesonearg'] = 'The function {$a} must have exactly one argument';
$string['functiontakesoneortwoargs'] = 'The function {$a} must have either one or two arguments';
$string['functiontakestwoargs'] = 'The function {$a} must have exactly two arguments';
$string['functiontakesatleasttwo'] = 'The function {$a} must have at least two arguments';
$string['unsupportedformulafunction'] = 'The function {$a} is not supported';

// Strings for question settings.
$string['settingusepopup'] = 'Use popups for correct answer and feedback';
$string['settingusepopup_desc'] = 'Display correct answer and feedback in a tooltip';
$string['defaultanswertype'] = 'Default answer type';
$string['defaultanswertype_desc'] = 'Default answer type for new question\'s parts';
$string['defaultcorrectness'] = 'Default grading criteria';
$string['defaultcorrectness_desc'] = 'Default grading criteria for new question\'s parts';
$string['defaultanswermark'] = 'Default part\'s mark';
$string['defaultanswermark_desc'] = 'Default part\'s mark for new question\'s parts';
$string['defaultunitpenalty'] ='Default unit penalty';
$string['defaultunitpenalty_desc'] ='Default penalty for wrong unit (0-1)';
