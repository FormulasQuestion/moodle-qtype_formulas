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
 * Check whether the format of input numbers, formulas and units are incorrect
 *
 * @copyright &copy; 2010-2011 Hon Wai, Lau
 * @author Hon Wai, Lau <lau65536@gmail.com>
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License version 3
 */

// This function will be called to initialize the format check for input boxes, after the page is fully loaded.
function formulas_format_check() {
    var use_format_check = true;    // If it is set to false, no format check will be used and initialized.
    var show_hinting_type = null;   // Show the type hinting under the input box, such as 'Number', 'Unit'. null means use the individual setting in variable types below.
    var show_interpretation = null;   // Show the interpretation of the formula under the input box. null means use the individual setting in variable types below.
    var show_warning = null;   // Show the warning sign if the input is wrong/not interpretable. null means use the individual setting in variable types below.
    var unittest_fail_show_icon = true; // Show an icon at the low right corner if the format check testing fails
    var unittest_fail_show_details = false;  // Show the detail test case that it fails.

    // The following variable can fine control the information for each type in addition to the variable defined above
    // So, if the type name is commented out here, the corresponding format checking will be disabled
    // For each type record, it contains the input type name (say 'unit')
    // and whether to show the type name (true/false), interpretation (true/false) and warning sign (true/false) respectively
    var types = [
        ['unit', true, true, true],                 // For the unit input box.
        ['number', true, true, true],               // For the number input box.
        ['number_unit', true, true, true],          // Input box with number and unit together.
        ['numeric', true, true, true],              // Allow the combination of numbers and operators + - * / ^ ( ).
        ['numeric_unit', true, true, true],         // Input box with numeric and unit together.
        ['numerical_formula', true, true, true],    // Allow the combination of numbers, operators and functions.
        ['numerical_formula_unit', true, true, true],   // Input box with numerical formula and unit together.
        ['algebraic_formula', true, true, true],    // Allow the combination of numbers, operators, functions and variables.
        ['editing_unit', true, true, true]         // Used for the unit in editing interface.
    ];

    // The list of constant that will be replaced by the corresponding number.
    var constlist = {'pi': '3.14159265358979323846'};

    // The list of allowable (single parameter) function and their evaluation replacement.
    var funclist = {'sin': 1, 'cos': 1, 'tan': 1, 'asin': 1, 'acos': 1, 'atan': 1, 'exp': 1,
        'log10': 1, 'ln': 1, 'sqrt': 1, 'abs': 1, 'ceil': 1, 'floor': 1, 'fact':1};

    // The replacement list used when preform trial evaluation, note it is not the same as dispreplacelist
    var evalreplacelist = {'ln': 'log', 'log10': '(1./log(10.))*log'}; // Natural log and log with base 10, no log allowed to avoid ambiguity.

    // The replacement list used when the formula is displayed on the screen.
    var dispreplacelist = {'log10': 'log<sub>10</sub>', '3.14159265358979323846': '<i>π</i>'};

    // Perform the action specified by the above global variables.
    if (!use_format_check)  return;
    for (var i=0; i<types.length; i++) {
        if (show_hinting_type != null)  types[i][1] = show_hinting_type;
        if (show_interpretation != null)  types[i][2] = show_interpretation;
        if (show_warning != null) types[i][3] = show_warning;
    }

    // Define the set of function that will be used to check the input and display the warning sign.
    var fn = {
        // When blur, hide the information box attached to the input field
        stop : function(common, self) {
            if (!common.ready || !common.pass)  return;
            self.info.style.display = 'none';
            common.fn.update(common, self);
        },

        // Start to show the dynamic checking information when the input field is focused.
        monitor : function(common, self) {
            if (!common.ready || !common.pass)  return;
            self.info.style.display = self.show_info ? 'block' : 'none';
            common.fn.update(common, self);
        },

        // Check the input value and display the updated information.
        update : function(common, self) {
            // Restrict the length of input to 128 characters, extra characters will be stripped away.
            if (self.input.value.length > 128)  self.input.value = self.input.value.substr(0,128);
            if (!common.ready || !common.pass)  return;
            self.cur_value = common.fn.trim(self.input.value);
            if (self.last_value != null && self.last_value == self.cur_value)  return;
            self.last_value = self.cur_value;
            self.answered = self.input.value == '' ? false : true;

            // Call the corresponding function for this particular input box if the value is not empty.
            var info = self.answered ? common.fn[self.func](common.fn, self.cur_value) : '';
            self.correct = (info != null);  // the function returns a string if correct, otherwise null
            info = (info != null) ? common.fn.trim(info) : '';

            // Update the information shown on the screen.
            self.warning.style.display = (self.show_sign && self.answered && !self.correct) ? 'block' : 'none';
            if (self.correct) {
                self.interpretation.innerHTML = info;
                self.interpretation.style.display = (self.show_interpretation && self.cur_value != info) ? 'block' : 'none';
                self.interpretation.className = 'formulas_input_info_interpretation';
            }
            else  // Dim the text color and keep the background text unchanged, until the next correct.
                self.interpretation.className = 'formulas_input_info_interpretation_incorrect';
        },

        check_unit : function(fn, value) {
            var base_units = null;
            try {
                base_units = fn.parse_unit(fn, value);
            } catch(e) {}
            if (base_units == null)  return null;
            return fn.format_unit(base_units);
        },

        check_number : function(fn, value) {
            var v = fn.get_formula_information(fn, value);
            if (v == null)  return null;
            if (/^[-+]?@0$/.test(v.sub) == false)  return null;  // It must be a positive number, with an optional '-' sign.
            if (!((v.lengths['n'] == 1) && (v.lengths['f'] == 0) && (v.lengths['v'] == 0)))  return null;
            return fn.format_number(value);
        },

        check_number_unit : function(fn, value) {
            var splitted = fn.split_formula_unit(fn, value);
            var n = fn.check_number(fn, splitted[0]);
            var u = fn.check_unit(fn, splitted[1]);
            if (n == null || u == null)  return null; // both part must correct simultaneously
            return n + ' ' + u;
        },

        check_numeric : function(fn, value) {
            var v = fn.get_formula_information(fn, value);
            if (v == null)  return null;
            if (/^[ )(^\/*+-]*$/.test(v.remaining) == false)  return null;  // it must contain only the operators +-*/^()
            if (!((v.lengths['f'] == 0) && (v.lengths['v'] == 0)))  return null;
            if (fn.test_evaluation(fn, v, v.sub) == null)  return null;
            var n = fn.check_number(fn, value);    // If it is a simple number, show a better format.
            return n != null ? n : fn.replace_display_formula(fn, v, v.sub);
        },

        check_numeric_unit : function(fn, value) {
            var splitted = fn.split_formula_unit(fn, value);
            var n = fn.check_numeric(fn, splitted[0]);
            var u = fn.check_unit(fn, splitted[1]);
            if (n == null || u == null)  return null; // Both part must be correct simultaneously.
            return n + ' ' + u;
        },

        check_numerical_formula : function(fn, value) {
            var v = fn.get_formula_information(fn, value);
            if (v == null)  return null;
            if (/^[ )(^\/*+-]*$/.test(v.remaining) == false)  return null;  // It must contain only the operators +-*/^().
            if (!((v.lengths['v'] == 0)))  return null;
            if (fn.test_evaluation(fn, v, v.sub) == null)  return null;
            var n = fn.check_number(fn, value);    // If it is a simple number, show a better format.
            return n != null ? n : fn.replace_display_formula(fn, v, v.sub);
        },

        check_numerical_formula_unit : function(fn, value) {
            var splitted = fn.split_formula_unit(fn, value);
            var n = fn.check_numerical_formula(fn, splitted[0]);
            var u = fn.check_unit(fn, splitted[1]);
            if (n == null || u == null)  return null; // Both part must be correct simultaneously.
            return n + ' ' + u;
        },

        check_algebraic_formula : function(fn, value) {
            var v = fn.get_formula_information(fn, value);
            if (v == null)  return null;
            if (/^[ )(^\/*+-]*$/.test(v.remaining) == false)  return null;  // It must only contain the  +-*/^() operators.
            // Replace all variables symbols by a number before the evaluation test.
            var variables = {};  // replace all variables in v.all by 1 and then attempt the evaluation.
            for (var key in v.all)  variables[key] = v.all[key];    // clone the v.all
            for (var key in v.all)  if (v.all[key]!=null && v.all[key].type=='v')  v.all[key] = {'type': 'n', 'value': 1.};
            if (fn.test_evaluation(fn, v, v.sub) == null)  return null;
            for (var key in v.all)  v.all[key] = variables[key];    // Put the variables back, it will be used in the display formula.
            return fn.replace_display_formula(fn, v, v.sub);
        },

        check_editing_unit : function(fn, value) {
            var unit_lists = value.split('=');
            var correct = true;
            var formatted = [];
            for (var i=0; i<unit_lists.length; i++)  try {
                var base_units = fn.parse_unit(common.fn, unit_lists[i]);
                correct = correct && (base_units != null) && (fn.trim(unit_lists[i]).length != 0);
                formatted.push(fn.format_unit(base_units));
            } catch(e) {
                correct = false;
            }
            if (!correct) {
                return null;
            }
            return formatted.join(' = ');
        },

        // test whether the input string is evaluable, and return the evaluated value
        test_evaluation: function(fn, vstack, formula) {
            try {
                var v = fn.replace_vstack_variables(fn, vstack, evalreplacelist);
                var expr = fn.replace_evaluation_formula(fn, v, formula);
                var expr = fn.substitute_placeholders_in_text(fn, v, expr);
                var res;
                eval('with(Math) { res = ' + expr + '; }');
                return res;     // Return the evaluated result, even it is NaN or infinity.
            }
            catch (e) { return null; }  // Return null for any error such as syntax error.
        },

        // This function must be called to initial a variable stack
        vstack_create : function() {
            return {idcounter: 0, all: {}};
        },

        // return the variable with name in the vstack
        vstack_get_variable : function(vstack, name) {
            return (name in vstack.all ? vstack.all[name] : null);
        },

        // Add a temporary variable in the vstack
        vstack_add_temporary_variable : function(vstack, type, value) {
            var name = '@' + vstack.idcounter;
            vstack.all[name] = {'type': type, 'value': value};
            vstack.idcounter++;
            return name;
        },

        // return the original string by substituting back the placeholders (given by variables in $vstack) in the input $text.
        substitute_placeholders_in_text : function(fn, vstack, text) {
            var splitted = text.replace(/(@[0-9]+)/g, '`$1`').split('`');
            for (var i=1; i<splitted.length; i+=2)      // The length will always be odd, and the placeholder is stored in odd index
                splitted[i] = fn.vstack_get_variable(vstack, splitted[i]).value;   // substitute back the strings
            return splitted.join('');
        },

        // return a string with all (positive) numbers substituted by placeholders. The information of placeholders is stored in v.
        substitute_numbers_by_placeholders : function(fn, vstack, text) {
            var numPattern = /(^|[\]\[)(}{, ?:><=~!|&%^\/*+-])(([0-9]+\.?[0-9]*|[0-9]*\.?[0-9]+)([eE][-+]?[0-9]+)?)/g;
            var splitted = text.replace(numPattern, '$1`$2`').split('`');
            for (var i=1; i<splitted.length; i+=2)      // The length will always be odd, and the numbers are stored in odd index
                splitted[i] = fn.vstack_add_temporary_variable(vstack, 'n', splitted[i]);
            return splitted.join('');
        },

        // return a string with all functions substituted by placeholders. The information of placeholders is stored in v.
        substitute_functions_by_placeholders : function(fn, vstack, text) {
            var funcPattern = /([a-z][a-z0-9_]*)(\s*\()/g;
            var splitted = text.replace(funcPattern, '`$1`$2').split('`');
            for (var i=1; i<splitted.length; i+=2) {    // The length will always be odd, and the variables are stored in odd index
                if (!(splitted[i] in funclist))  continue;
                splitted[i] = fn.vstack_add_temporary_variable(vstack, 'f', splitted[i]);
            }
            return splitted.join('');
        },

        // return a string with all constants substituted by placeholders. The information of placeholders is stored in v.
        substitute_constants_by_placeholders : function(fn, vstack, text, preserve) {
            var varPattern = /([A-Za-z][A-Za-z0-9_]*)/g;
            var splitted = text.replace(varPattern, '`$1`').split('`');
            for (var i=1; i<splitted.length; i+=2) {    // The length will always be odd, and the variables are stored in odd index
                if (!(splitted[i] in constlist))  continue;
                var constnumber = preserve ? splitted[i] : constlist[splitted[i]];
                splitted[i] = fn.vstack_add_temporary_variable(vstack, 'n', constnumber);   // it is a number!
            }
            return splitted.join('');
        },

        // return a string with all variables substituted by placeholders. The information of placeholders is stored in v.
        substitute_variables_by_placeholders : function(fn, vstack, text) {
            var varPattern = /([A-Za-z][A-Za-z0-9_]*)/g;
            var splitted = text.replace(varPattern, '`$1`').split('`');
            for (var i=1; i<splitted.length; i+=2) {    // The length will always be odd, and the variables are stored in odd index
                if (splitted[i] in funclist)  continue;
                splitted[i] = fn.vstack_add_temporary_variable(vstack, 'v', splitted[i]);
            }
            return splitted.join('');
        },

        // return the information of the formula by substituting numbers, variables and functions.
        get_formula_information : function(fn, str) {
            if (/^[A-Za-z0-9._ )(^\/*+-]*$/.test(str) == false)  return null;   // formula can only contains these characters
            var v = fn.vstack_create();
            var sub = str;
            var sub = fn.substitute_numbers_by_placeholders(fn, v, sub);
            var sub = fn.substitute_functions_by_placeholders(fn, v, sub);
            var sub = fn.substitute_constants_by_placeholders(fn, v, sub, false);
            var sub = fn.substitute_variables_by_placeholders(fn, v, sub);
            v.lengths = {'n': 0, 'v': 0, 'f': 0};
            for (var data in v.all)  v.lengths[v.all[data].type]++;
            v.original = str;
            v.sub = sub;
            v.remaining = v.sub.replace(/@[0-9]+/g, '');    // remove all placeholders, so operators should remain
            return v;
        },

        // split the input into number/numeric/numerical formula and unit.
        split_formula_unit : function(fn, str) {
            if (/[`@]/.test(str) == true)  return ['',str];   // Note: these symbols will be used to split str
            var v = fn.vstack_create();
            var sub = str;
            var sub = fn.substitute_numbers_by_placeholders(fn, v, sub);
            var sub = fn.substitute_functions_by_placeholders(fn, v, sub);
            var sub = fn.substitute_constants_by_placeholders(fn, v, sub, true);
            // Split at the point that does not contain characters @ 0-9 + - * / ^ ( ) space
            var loc = sub.search(/([^@0-9 )(^\/*+-])/);
            var num = fn.substitute_placeholders_in_text(fn, v, (loc == -1) ? sub : sub.substr(0,loc));
            var unit = (loc == -1) ? '' : fn.substitute_placeholders_in_text(fn, v, sub.substr(loc));
            return [fn.trim(num), fn.trim(unit)];
        },

        // replace the user input function in the vstack by another function
        replace_vstack_variables : function(fn, vstack, replacementlist) {
            var res = {idcounter: vstack.idcounter, all: {}};   // the vstack.all will be used so it needs to clone deeply
            for (var key in vstack.all)  res.all[key] = {'type': vstack.all[key].type, 'value': vstack.all[key].value};
            for (var key in res.all) {
                var tmp = res.all[key];
                if (tmp.value in replacementlist)  tmp.value = replacementlist[tmp.value];
            }
            return res;
        },

        // insert the multiplication symbol whenever juxtaposition occurs
        insert_multiplication_for_juxtaposition : function(fn, vstack, text) {
            var splitted = text.replace(/(@[0-9]+)/g, '`$1`').split('`');
            for (var i=3; i<splitted.length; i+=2) {    // The length will always be odd: placeholder in odd index, operators in even index
                var op = fn.trim(splitted[i-1]);    // the operator(s) between this and the previous variable
                if (fn.vstack_get_variable(vstack,splitted[i-2]).type == 'f')  continue;   // no need to add '*' if the left is function
                if (op.length==0)  op = ' * ';    // add multiplication if no operator
                else if (op[0]=='(')  op = ' * '+op;
                else if (op[op.length-1]==')')  op = op+' * ';
                else op = op.replace(/^(\))(\s*)(\()/g, '$1 * $3');
                splitted[i-1] = op;
            }
            return splitted.join('');
        },

        // replace the expression x^y by pow(x,y)
        replace_caret_by_power : function(fn, vstack, text) {
            while (true){
                var loc = text.lastIndexOf('^');    // from right to left
                if (loc < 0)  break;

                // search for the expression of the exponent
                var rloc = loc;
                if (rloc+1 < text.length && text[rloc+1] == '-')  rloc += 1;
                var r = fn.get_next_variable(fn, vstack, text, rloc+1);
                if (r != null)  rloc = r.endloc-1;
                if (r == null || (r != null && r.variable.type == 'f')) {
                    var rtmp = fn.get_expressions_in_bracket(text, rloc+1, '(', {'(': ')'});
                    if (rtmp == null || rtmp.openloc != rloc+1)  throw 'Expression expected';
                    rloc = rtmp.closeloc;
                }

                // search for the expression of the base
                var lloc = loc;
                var l = fn.get_previous_variable(fn, vstack, text, loc);
                if (l != null)
                    lloc = l.startloc;
                else {
                    var reverse = text.split('').reverse().join('');
                    var ltmp = fn.get_expressions_in_bracket(reverse, text.length-1-loc+1, ')', {')': '('});
                    if (ltmp == null || ltmp.openloc != text.length-1-loc+1)  throw 'Expression expected';
                    var lfunc = fn.get_previous_variable(fn, vstack, text, text.length-1-ltmp.closeloc);
                    lloc = (lfunc==null || lfunc.variable.type!='f') ? text.length-1-ltmp.closeloc : lfunc.startloc;
                }

                // replace the exponent notation by the pow function
                var name = fn.vstack_add_temporary_variable(vstack, 'f', 'pow');
                text = text.substr(0,lloc) + name + '(' + text.substr(lloc,loc-lloc) + ', '
                    + text.substr(loc+1, rloc-loc) + ')' + text.substr(rloc+1);
            }
            return text;
        },

        // translate the input text into the corresponding evaluable mathematical formula in javascript.
        replace_evaluation_formula : function(fn, vstack, text) {
            text = fn.insert_multiplication_for_juxtaposition(fn, vstack, text);
            text = fn.replace_caret_by_power(fn, vstack, text);
            text = text.replace(/\s*([)(\/*+-])\s*/g, '$1');
            return text;
        },

        // translate the input s into the corresponding displayable HTML.
        replace_display_formula : function(fn, vstack, text) {
            var vstack = fn.replace_vstack_variables(fn, vstack, dispreplacelist);  // the vstack will be cloned
            text = fn.replace_evaluation_formula(fn, vstack, text);

            // change the multiplication '*' to a better looking symbol
            var splitted = text.replace(/(@[0-9]+)/g, '`$1`').split('`');
            for (var i=3; i<splitted.length; i+=2) {    // The length will always be odd: placeholder in odd index, operators in even index
                if (fn.trim(splitted[i-1]) == '*') {
                    var left = fn.vstack_get_variable(vstack, splitted[i-2]);
                    var right = fn.vstack_get_variable(vstack, splitted[i]);
                    splitted[i-1] = (left.type == 'n' && right.type == 'n') ? '\u00D7' : '\u00b7';
                }
                splitted[i-1] = splitted[i-1].replace('*', '\u00b7');
            }
            text = splitted.join('');

            // set all variable to italic and then substitute back to the formula
            for (var key in vstack.all)  if (vstack.all[key]!=null) {
                if (vstack.all[key].type=='v')  vstack.all[key].value = '<i>'+vstack.all[key].value+'</i>';
                if (vstack.all[key].type=='n')  vstack.all[key].value = fn.format_number(vstack.all[key].value, true);
            }
            text = fn.substitute_placeholders_in_text(fn, vstack, text);

            // replace the pow(a,b) into the form superscript format: a<sup>b</sup>
            while (true){
                var loc = text.lastIndexOf('pow');  // from right to left
                if (loc < 0)  break;
                var b = fn.get_expressions_in_bracket(text, loc+1, '(', {'(': ')'});
                var base = fn.trim(b.expressions[0]);
                if (base[0]!='(' && base.indexOf('(')>=0)  base = '('+base+')';  // probably a function, add brackets for less ambiguous
                var expo = fn.trim(b.expressions[1]);
                if (expo[0]=='(' && expo[expo.length-1]==')')  expo = expo.substr(1,expo.length-2); // remove extra brackets
                text = text.substr(0,loc) + base + '<sup>' + expo + '</sup>' + text.substr(b.closeloc+1);
            }
            return text;
        },

        // return the list of expression inside the open '(' and close ')' bracket, otherwise null
        get_expressions_in_bracket : function(text, start, open, bset) {
            var bflip = {};
            for (var b in bset)  bflip[bset[b]] = b;
            var ostack = [];  // stack of open bracket
            for (var i=start; i<text.length; i++) {
                if (text[i] == open)  ostack.push(open);
                if (ostack.length > 0)  break;     // when the first open bracket is found
            }
            if (ostack.length == 0)  { return null; }
            var firstopenloc = i;
            var expressions = [];
            var ploc = i+1;
            for (var i=ploc; i<text.length; i++) {
                if (text[i] in bset)  ostack.push(text[i]);
                if (text[i] == ',' && ostack.length == 1) {
                    expressions.push( text.substr(ploc, i - ploc) );
                    ploc = i+1;
                }
                if (text[i] in bflip)  if (ostack.pop() != bflip[text[i]])  break;
                if (ostack.length == 0) {
                    expressions.push( text.substr(ploc, i - ploc) );
                    return {'openloc': firstopenloc, 'closeloc': i, 'expressions': expressions};
                }
            }
            throw 'error_bracket_mismatch';
        },

        // get the variable immediately before the location $loc
        get_previous_variable : function(fn, vstack, text, loc) {
            var m = /((@[0-9]+)\s*)$/.exec(text.substr(0,loc));
            if (m == null)  return null;
            var v = fn.vstack_get_variable(vstack, m[2]);
            if (v == null)  return null;
            return {'startloc': (loc-m[1].length), 'variable': v};
        },

        // get the variable immediately at and after the location $loc (inclusive)
        get_next_variable : function(fn, vstack, text, loc) {
            var m = /^(\s*(@[0-9]+))/.exec(text.substr(loc));
            if (m == null)  return null;
            var v = fn.vstack_get_variable(vstack, m[2]);
            if (v == null)  return null;
            return {'startloc': (loc+(m[1].length-m[2].length)), 'endloc': (loc+m[1].length), 'variable': v};
        },

        // return an array of [base unit, exponent] if the input is a unit, otherwise null.
        parse_unit : function(fn, str, no_divisor) {
            if ((fn.trim(str)).length == 0)  return [];

            var pos = str.indexOf('/');
            if (pos >= 0) {
                if (no_divisor!=null || pos==0 || pos>=str.length-1)  return null;
                //alert([no_divisor,no_divisor!=null || pos==0 || pos>=str.length-1, no_divisor!=null, pos==0 || pos>=str.length-1]);
                var left = fn.trim(str.substr(0, pos));
                var right = fn.trim(str.substr(pos+1));
                if (right[0] == '(' && right[right.length-1] == ')')  right = right.substr(1, right.length-2);
                var uleft = fn.parse_unit(fn, left, true);
                var uright = fn.parse_unit(fn, right, true);
                if (uleft==null || uright==null)  return null;  // if either part contains error
                var unit = uleft;
                var unit_set = {};
                for (var i=0; i<uleft.length; i++)
                    unit_set[uleft[i][0]] = uleft[i][1];
                for (var i=0; i<uright.length; i++) {
                    if (uright[i][0] in unit_set)  return null;     // no duplication
                    var exponent = -uright[i][1];   // take negation of the exponent
                    unit_set[uright[i][0]] = exponent;
                    unit.push([uright[i][0], exponent]);
                }
                return uleft;
            }

            var unit = [];
            var unit_set = {};
            var candidates = str.replace(/\s*\^\s*/,'^').split(' ');
            for (var i=0; i<candidates.length; i++) {
                var candidate = candidates[i];
                var ex = candidate.split('^');
                var name = ex[0];      // there should be no space remaining
                if (ex.length > 1 && (name.length == 0 || ex[1].length == 0))  return null;
                if (name.length == 0)  continue;    // if it is an empty space
                if (/^[^\]\[)(}{><0-9.,:;`~!@#^&*\/?|_=+ -]+$/.test(name) == false)  return null;   // it cannot contain some characters
                var exponent = null;
                if (ex.length > 1) {
                    var matches = /(.*)([0-9]+)(.*)/.exec(ex[1]);
                    if (matches.length == 0)  return null;     // the '^' must be followed by something, should it be matches == null???
                    if (matches[1] == '' && matches[3] == '')  exponent = parseInt(matches[2]);
                    if (matches[1] == '-' && matches[3] == '')  exponent = -parseInt(matches[2]);
                    if (matches[1] == '(-' && matches[3] == ')')  exponent = -parseInt(matches[2]);
                    if (exponent == null)  return null;    // no one pattern matched
                }
                else
                    exponent = 1;
                if (name in unit_set)  return null;
                unit.push([name, exponent]);
                unit_set[name] = exponent;
            }
            return unit;
        },

        // return the formatted number in scientific notation, if necessary
        format_number : function(number, is_original) {
        try {
            if (is_original == null)  is_original = false;
            if (is_original) {
                if (/[eE]/.exec(number) == null)  return number;
                var standardform = number;
                var symbol = '';
            }
            else {
                var value = parseFloat(number);
                var absnum = Math.abs(value);
                if (absnum == 0)  return number;
                var loc = number.search(/[eE]/);   // if there is e, always show the reformatted scientific notation
                var standardform = value.toExponential();
                var newvalue = parseFloat(standardform);
                var is_equal = value == newvalue;
                if (is_equal && loc == -1 && absnum >= Math.pow(10.,-2) && absnum <= Math.pow(10.,6))
                    return number;
                var symbol = ''; //is_equal ? ' ' : '\u2248 ';    // if they are not equal, show approx sign
            }
            var s = standardform.split(/[eE]/);
            var decimal = s[0].replace(/(\.)?0*$/,'');
            var exponent = (s[1][0] == '+' ? s[1].substr(1) : s[1][0] == '-' ? '\u2212'+s[1].substr(1) : s[1]);
            var newform = decimal + (exponent == 0 ? '' : '\u00D7'+'10<sup>'+exponent+'</sup>');
            return symbol + newform;
        }catch(e) { return ''; }
        },

        // return the formatted unit into a standard format
        format_unit : function(base_units) {
        try {
            var res = [];
            for (var i=0; i<base_units.length; i++) {
                var exponent = base_units[i][1] < 0 ? '\u2212' + Math.abs(base_units[i][1]) : base_units[i][1];
                res[i] = '' + base_units[i][0] + (base_units[i][1] == 1 ? '' : '<sup>' + exponent + '</sup>');
            }
            return res.join('\u00b7');   // · (middle dot),  s^−2,  ✗
        }catch(e) { return ''; }
        },

        // return the string with space trimmed
        trim : function(str) {
            return str.replace(/^\s+|\s+$/g, '');
        }
    };

    // Initialization: Append the display blocks around the input and the data in the input node.
    function init(common, types) {
        var others = [];
        var count = 0;
        var inputs = document.getElementsByTagName('input');
        for (i=0; i<inputs.length; i++)  {
            var input = inputs[i];
            if (input.type != 'text')  continue;    // if it is not a text input field
            // With the Boost theme the element class is on a div element, not on the input element
            // So we re-add it so that classify function works on it.
            if (input.name.indexOf('postunit') == 0) {
                input.classList.add('formulas_editing_unit');
                input.title = M.util.get_string('unit', 'qtype_formulas');
            }
            var type = classify(input.className, types);

            if (type == null)  continue;            // if it does not contain the required class

            var self = {};
            self.input = input;
            self.which = count;
            self.answered = false;  // Default value, it will be checked later.
            self.correct = false;   // Note: if there is no answer, self.correct is always undefined
            self.cur_value = null;  // it will store the trimmed value of the user input
            self.last_value = null; // the input value in the last checking state, nothing by default.
            self.func = type.func;
            self.show_info = (type.show_type || type.show_interpretation);
            self.show_type = type.show_type;
            self.show_sign = type.show_sign;
            self.show_interpretation = type.show_interpretation;
            self.title = input.title;
            input.title = '';       // Remove the title.

            var warning_inner = document.createElement('img');
            warning_inner.src = M.util.image_url('warning', 'qtype_formulas');
            warning_inner.className = 'formulas_input_warning';
            warning_inner.style.display = 'none';
            var warning = document.createElement('span');
            warning.className = 'formulas_input_warning_outer';
            warning.appendChild(warning_inner);
            input.parentNode.insertBefore(warning, input.nextSibling);

            var info_inner = document.createElement('div');
            info_inner.className = 'formulas_input_info';
            info_inner.style.display = 'none';
            info_inner.style.width = Math.max(100, input.clientWidth + input.clientLeft) + 'px';
            var info = document.createElement('div');
            info.className = 'formulas_input_info_outer';
            info.appendChild(info_inner);
            input.parentNode.insertBefore(info, input);

            if (self.show_type) {
                var info_inner_title = document.createElement('div');
                info_inner_title.className = 'formulas_input_info_title';
                info_inner_title.innerHTML = self.title;
                info_inner.appendChild(info_inner_title);
            }
            var info_inner_interpretation = document.createElement('div');
            info_inner_interpretation.className = 'formulas_input_info_interpretation';
            info_inner.appendChild(info_inner_interpretation);

            self.warning = warning_inner;
            self.info = info_inner;
            self.interpretation = info_inner_interpretation;
            others[count] = self;
            count++;

            // attach the data and events to the input field for later use
            input.formulas = {};
            input.formulas.common = common;
            input.formulas.self = self;

            input.onblur = function() {
                this.formulas.common.fn.stop(this.formulas.common, this.formulas.self);
            };
            input.onfocus = function() {
                this.formulas.common.fn.monitor(this.formulas.common, this.formulas.self);
            };
            input.onkeyup = function() {
                this.formulas.common.fn.update(this.formulas.common, this.formulas.self);
            };
        }
        return others;
    };

    // classify the type of the input field, and return the name of the corresponding function
    function classify(className, types) {
        var classes = className.split(' ');
        for (var i=0; i<classes.length; i++)
            for (var j=0; j<types.length; j++)
                if (classes[i] == 'formulas_'+types[j][0])
                    return {func: 'check_'+types[j][0], show_type: types[j][1],
                        show_interpretation: types[j][2], show_sign: types[j][3]};
        return null;
    }

    // return true if the javascript engine make correct classification of the all standard test samples
    function unittest() {
        // test three things: unit check, formula, and number unit splitting
        var numcase = 0;
        var numcorrect = 0;
        var parsingtestcases = [
            // check for simple number
            ['number', true, '3'],
            ['number', true, '3.'],
            ['number', true, '.3'],
            ['number', true, '3.1'],
            ['number', true, '3.1e-10'],
            ['number', true, '3.e10'],
            ['number', true, '.3e10'],
            ['number', true, '-3'],
            ['number', true, '+3'],
            ['number', true, '-3.e10'],
            ['number', true, '-.3e10'],
            ['number', true, 'pi'],
            ['number', false, '- 3'],
            ['number', false, '+ 3'],
            ['number', false, '3 e10'],
            ['number', false, '3e 10'],
            ['number', false, '3e8e8'],
            ['number', false, '3+10*4'],
            ['number', false, '3+10^4'],
            ['number', false, 'sin(3)'],
            ['number', false, '3+exp(4)'],
            ['number', false, '3*4*5'],
            ['number', false, '3 4 5'],
            ['number', false, 'a*b'],
            ['number', false, '#'],

            // numeric is basically a subset of numerical formula, so test below together
            ['numeric', true, '3+10*4/10^4'],
            ['numeric', false, 'sin(3)'],
            ['numeric', false, '3+exp(4)'],

            // numerical formula is basically a subset of algebraic formula, so test below together
            ['numerical_formula', true, '3.1e-10'],
            ['numerical_formula', true, '- 3'], // it is valid for this type
            ['numerical_formula', false, '3 e10'],
            ['numerical_formula', false, '3e 10'],
            ['numerical_formula', false, '3e8e8'],
            ['numerical_formula', false, '3e8e8e8'],

            ['numerical_formula', true, '3+10*4/10^4'],
            ['numerical_formula', true, 'sin(3)-3+exp(4)'],
            ['numerical_formula', true, '3*4*5'],
            ['numerical_formula', true, '3 4 5'],
            ['numerical_formula', true, '3e8 4.e8 .5e8'],
            ['numerical_formula', true, '3e8(4.e8+2)(.5e8/2)5'],
            ['numerical_formula', true, '3e8(4.e8+2) (.5e8/2)5'],
            ['numerical_formula', true, '3e8 (4.e8+2)(.5e8/2) 5'],
            ['numerical_formula', true, '3e8 (4.e8+2) (.5e8/2) 5'],
            ['numerical_formula', true, '3(4.e8+2)3e8(.5e8/2)5'],
            ['numerical_formula', true, '3+4^9'],
            ['numerical_formula', true, '3+(4+5)^9'],
            ['numerical_formula', true, '3+(4+5)^(6+7)'],
            ['numerical_formula', true, '3+sin(4+5)^(6+7)'],
            ['numerical_formula', true, '3+exp(4+5)^sin(6+7)'],
            ['numerical_formula', true, '3+4^-(9)'],
            ['numerical_formula', true, '3+4^-9'],
            ['numerical_formula', true, '3+exp(4+5)^-sin(6+7)'],
            ['numerical_formula', true, '1+ln(3)'],
            ['numerical_formula', true, '1+log10(3)'],
            ['numerical_formula', true, 'pi'],
            ['numerical_formula', false, 'pi()'],

            // test of algebraic formula
            ['algebraic_formula', true, '- 3'], // it is valid for this type
            ['algebraic_formula', true, '3 e10'],   // it is valid for this type
            ['algebraic_formula', true, '3e 10'],   // it is valid for this type
            ['algebraic_formula', true, '3e8e8'],   // it is valid for this type
            ['algebraic_formula', true, '3e8e8e8'],   // it is valid for this type

            ['algebraic_formula', true, 'sin(3)-3+exp(4)'],
            ['algebraic_formula', true, '3e8 4.e8 .5e8'],
            ['algebraic_formula', true, '3e8(4.e8+2)(.5e8/2)5'],
            ['algebraic_formula', true, '3+exp(4+5)^sin(6+7)'],
            ['algebraic_formula', true, '3+4^-(9)'],

            ['algebraic_formula', true, 'sin(a)-a+exp(b)'],
            ['algebraic_formula', true, 'a*b*c'],
            ['algebraic_formula', true, 'a b c'],
            ['algebraic_formula', true, 'a(b+c)(x/y)d'],
            ['algebraic_formula', true, 'a(b+c) (x/y)d'],
            ['algebraic_formula', true, 'a (b+c)(x/y) d'],
            ['algebraic_formula', true, 'a (b+c) (x/y) d'],
            ['algebraic_formula', true, 'a(4.e8+2)3e8(.5e8/2)d'],
            ['algebraic_formula', true, 'pi'],
            ['algebraic_formula', true, 'a+x^y'],
            ['algebraic_formula', true, '3+x^-(y)'],
            ['algebraic_formula', true, '3+x^-y'],
            ['algebraic_formula', true, '3+(u+v)^x'],
            ['algebraic_formula', true, '3+(u+v)^(x+y)'],
            ['algebraic_formula', true, '3+sin(u+v)^(x+y)'],
            ['algebraic_formula', true, '3+exp(u+v)^sin(x+y)'],
            ['algebraic_formula', true, 'a+exp(a)(u+v)^sin(1+2)(b+c)'],
            ['algebraic_formula', true, 'a+exp(u+v)^-sin(x+y)'],
            ['algebraic_formula', true, 'a+b^c^d+f'],
            ['algebraic_formula', true, 'a+b^(c^d)+f'],
            ['algebraic_formula', true, 'a+(b^c)^d+f'],
            ['algebraic_formula', true, 'a+b^c^-d'],
            ['algebraic_formula', true, '1+ln(a)+log10(b)'],
            ['algebraic_formula', true, 'asin(w t)'],   // arcsin(w*t)
            ['algebraic_formula', true, 'a sin(w t)+ b cos(w t)'], // a*sin(w*t) + b*cos(w*t)
            ['algebraic_formula', true, '2 (3) a sin(b)^c - (sin(x+y)+x^y)^-sin(z)c tan(z)(x^2)'],

            ['algebraic_formula', false, 'a-'],
            ['algebraic_formula', false, '*a'],
            // For compatibility with old browsers comment the following line.
            ['algebraic_formula', true, 'a**b'],
            ['algebraic_formula', false, 'a+^c+f'],
            ['algebraic_formula', false, 'a+b^^+f'],
            ['algebraic_formula', false, 'a+(b^c)^+f'],
            ['algebraic_formula', false, 'a+((b^c)^d+f'],
            ['algebraic_formula', false, 'a+(b^c+f'],
            ['algebraic_formula', false, 'a+b^c)+f'],
            ['algebraic_formula', false, 'a+b^(c+f'],
            ['algebraic_formula', false, 'a+b)^c+f'],
            ['algebraic_formula', false, 'pi()'],
            ['algebraic_formula', false, 'sin 3'],
            ['algebraic_formula', false, '1+sin*(3)+2'],
            ['algebraic_formula', false, '1+sin^(3)+2'],
            ['algebraic_formula', false, 'a sin w t'],
            ['algebraic_formula', false, '1==2?3:4'],
            ['algebraic_formula', false, 'a=b'],
            ['algebraic_formula', false, '3&4'],
            ['algebraic_formula', false, '3==4'],
            ['algebraic_formula', false, '3&&4'],
            ['algebraic_formula', false, '3!'],
            ['algebraic_formula', false, '`'],
            ['algebraic_formula', false, '@'],

            // check for unit
            ['unit', true, 'm'],
            ['unit', true, 'km'],
            ['unit', true, 'm^2'],
            ['unit', true, 'm ^ 2'],
            ['unit', true, 'm^-2'],
            ['unit', true, 'm^(-2)'],
            ['unit', true, 'm ^ -2'],
            ['unit', true, 'm/s'],
            ['unit', true, 'm s^-1'],
            ['unit', true, 'm s^(-1)'],
            ['unit', true, 'kg m/s'],
            ['unit', true, 'kg m s^-1'],
            ['unit', true, 'kg m^2'],
            ['unit', true, 'kg m ^ 2'],
            ['unit', false, '2.1'],
            ['unit', false, '^2'],
            ['unit', false, 'm^+2'],
            ['unit', false, 'kg m s ^ - 1'],
            ['unit', false, '`'],
            ['unit', false, '@'],

            // check for simple number and unit
            ['number_unit', true, '.3'],
            ['number_unit', true, '3.1'],
            ['number_unit', true, '3.1e-10'],
            ['number_unit', true, '3m'],
            ['number_unit', true, '3kg m/s'],
            ['number_unit', true, '3.m/s'],
            ['number_unit', true, '3.e-10m/s'],
            ['number_unit', false, '- 3m/s'],
            ['number_unit', false, '3 e10 m/s'],
            ['number_unit', false, '3e 10 m/s'],
            ['number_unit', false, '3e8e8 m/s'],
            ['number_unit', false, '3+10*4 m/s'],
            ['number_unit', false, '3+10^4 m/s'],
            ['number_unit', false, 'sin(3) m/s'],
            ['number_unit', false, '3+exp(4) m/s'],
            ['number_unit', false, '3*4*5 m/s'],
            ['number_unit', false, '3 4 5 m/s'],
            ['number_unit', false, 'm/s'],
            ['number_unit', false, '#'],

            // numeric and unit
            ['numeric_unit', true, '3+4 5+10^4kg m/s'],
            ['numeric_unit', false, 'sin(3)kg m/s'],

            // numerical formula and unit
            ['numerical_formula_unit', true, '3.1e-10kg m/s'],
            ['numerical_formula_unit', true, '-3kg m/s'],
            ['numerical_formula_unit', true, '- 3kg m/s'],
            ['numerical_formula_unit', true, '3e'],
            ['numerical_formula_unit', true, '3e8'],
            ['numerical_formula_unit', true, '3e8e'],
            ['numerical_formula_unit', true, '3+4 5+10^4kg m/s'],
            ['numerical_formula_unit', true, 'sin(3)kg m/s'],
            ['numerical_formula_unit', true, '3*4*5 kg m/s'],
            ['numerical_formula_unit', true, '3 4 5 kg m/s'],
            ['numerical_formula_unit', true, '3e8(4.e8+2)(.5e8/2)5kg m/s'],
            ['numerical_formula_unit', true, '3+exp(4+5)^sin(6+7)kg m/s'],
            ['numerical_formula_unit', true, '3+exp(4+5)^-sin(6+7)kg m/s'],
            ['numerical_formula_unit', true, '3exp^2'], // Note the unit is exp to the power 2
            ['numerical_formula_unit', false, '3 e8'],
            ['numerical_formula_unit', false, '3e 8'],
            ['numerical_formula_unit', false, '3e8e8'],
            ['numerical_formula_unit', false, '3e8e8e8'],
            ['numerical_formula_unit', false, '3+exp(4+5).m/s'],
            ['numerical_formula_unit', false, '3+(4.m/s'],
            ['numerical_formula_unit', false, '3+4.)m/s'],
            ['numerical_formula_unit', false, '3 m^'],
            ['numerical_formula_unit', false, '3 m/'],
            ['numerical_formula_unit', false, '3 /s'],
            ['numerical_formula_unit', false, '3 m+s'],
            ['numerical_formula_unit', false, '1==2?3:4'],
            ['numerical_formula_unit', false, 'a=b'],
            ['numerical_formula_unit', false, '3&4'],
            ['numerical_formula_unit', false, '3==4'],
            ['numerical_formula_unit', false, '3&&4'],
            ['numerical_formula_unit', false, '3!'],
            ['numerical_formula_unit', false, '`'],
            ['numerical_formula_unit', false, '@']
        ];

        var failcases = [];
        for (var i=0; i<parsingtestcases.length; i++) {
            var tc = parsingtestcases[i];
            var result = fn['check_'+tc[0]](fn, tc[2]);
            var evaluable = (result != null);
            var iscorrect = evaluable == tc[1];
            if (iscorrect)  numcorrect++;
            else failcases.push(tc[2]);
            numcase++;
        }

        return {'numcorrect': numcorrect, 'numcase': numcase, 'failcases': failcases};
    };

    function signal_fail(common) {
        var warning = document.createElement('span');
        warning.innerHTML = '<div style="position:fixed; bottom:20px; right:5px;">'
            +(unittest_fail_show_details ? common.testresult.failcases.join('<br>') : '')
            +'<img src="'+M.util.image_url('warning', 'qtype_formulas')+'" title="Format check initialization fail ('+(common.testresult.numcase-common.testresult.numcorrect) +'/'+ common.testresult.numcase+')"></img>'
            +'</div>';
        document.body.insertBefore(warning, null);
    }

    var common = {};

    // Define a set of function that is used to manipulate the input
    common.fn = fn;

    // set the state to not ready
    common.ready = false;

    // check whether the javascript can produce the correct response
    common.testresult = unittest();
    common.pass = common.testresult.numcorrect == common.testresult.numcase;
    if (!common.pass && unittest_fail_show_icon)  signal_fail(common);

    // get the set of
    common.others = init(common, types);

    // set the state to ready, now we can use the functions
    common.ready = true;

    // update the initial value
    for (var i=0; i<common.others.length; i++)  try {
        common.fn.update(common, common.others[i]);
    } catch(e) {}
};

window.onload = (function(oldfunc, newfunc) {
    if (oldfunc && typeof oldfunc == 'function')
        return function() { oldfunc(); newfunc(); }
    else
        return newfunc;
})(window.onload, function() { formulas_format_check(); });
