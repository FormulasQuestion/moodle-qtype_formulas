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
 * Javascript function for the editing interface of formulas question type
 *
 * @copyright &copy; 2010-2011 Hon Wai, Lau
 * @author Hon Wai, Lau <lau65536@gmail.com>
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License version 3
 */

/**
 * Update the correctness simple elements from the expert one.
 */
function formulas_form_correctness(id, checked) {
    var err_names = new Array(M.util.get_string('relerror', 'qtype_formulas'), M.util.get_string('abserror', 'qtype_formulas'));
        var nid = 'correctness[' + id + ']';
        var n = document.getElementsByName(nid)[0];
        if (n == null) {
            return;
        }
        var bid = 'id_correctness_' + id + '_buttons';
        var b = document.getElementById(bid);
        if (b == null) {
            var tmp = document.createElement('div');
            tmp.id = bid;
            b = n.parentNode.appendChild(tmp);
        }
        var use_raw_input = checked;
        if (!use_raw_input) {
            var res = /^\s*(_relerr|_err)\s*(<|==)\s*([-+]?[0-9]*\.?[0-9]+([eE][-+]?[0-9]+)?)\s*$/.exec(n.value);
            if (res == null) {
                if (n.value.replace(/^\s+|\s+$/g,"").length == 0) {
                    res = ['','_relerr','<','0.01'];
                    n.value = '_relerr < 0.01';
                }
            }
            if (res == null) {
                use_raw_input = true;
            } else {
                var s = '<select id="' + bid + '_type" class="mform form-inline form-control" onchange="formulas_form_merge(' + id + ')">';
                s += '<option value="_relerr"' + (res[1] == '_relerr' ? ' selected="selected"' : '') + '>' + err_names[0] + '</option>';
                s += '<option value="_err"' + (res[1] == '_err' ? ' selected="selected"' : '') + '>' + err_names[1] + '</option>';
                s += '</select><select id="' + bid + '_op" class="mform form-inline form-control" onchange="formulas_form_merge(' + id + ')">';
                s += '<option value="<"' + (res[2] == '<' ? ' selected="selected"' : '') + '>&lt</option>';
                s += '<option value="=="' + (res[2] == '==' ? ' selected="selected"' : '') + '>==</option>';
                s += '</select><input id="' + bid + '_tol" type="text" class="mform form-inline form-control" value="' + res[3] + '" onchange="formulas_form_merge(' + id + ')">';
                b.innerHTML = s;
            }
        }
        n.style.display = use_raw_input ? 'block' : 'none';
        b.style.display = use_raw_input ? 'none' : 'block';
}

/**
 * Update the correctness expert element from the simple ones.
 */
function formulas_form_merge(id) {
    var nid = 'correctness[' + id + ']';
    var n = document.getElementsByName(nid)[0];
    var bid = 'id_correctness_' + id + '_buttons';
    var b = document.getElementById(bid);
    var error_type = document.getElementById(bid + '_type').value;
    var error_op   = document.getElementById(bid + '_op').value;
    var error_val  = document.getElementById(bid + '_tol').value;
    n.value = error_type + ' ' + error_op + ' ' + error_val;
}

function formulas_form_init_global_options_update(name) {
    var n_global = document.getElementsByName('global' + name)[0];
    var i = 0;
    while (true) {
        var n = document.getElementsByName(name + '[' + i + ']')[0];
        if (n == null) {
            return false;  // Break until there is no more box.
        }
        n.value = n_global.value;
        n.parentNode.parentNode.style.display = 'none';
        i++;
    }
}

// It contains all methods to modify the editing form, aiming to provide a better interface.
var formulasform = {
    // The initialization  function that should be called just after the form is constructed.
    init : function() {
        // Get all the values that will be usable for the methods in this object.
        this.numsubq = this.count_subq();

        // Global options allow the change of the same options in all parts at once.
        try {
            this.init_global_options('unitpenalty');
            this.init_global_options('ruleid');
        } catch (e) {}

        // Allow the easier selection of correctness, rather than manual input of formula.
        for (i = 0; i < this.numsubq; i++) {
            try {
                this.init_selective_criteria(i);
            } catch (e) {
                alert(e);
            }
        }

        // Add the button to select the number of dataset.
        try {
            this.init_numdataset_option();
            this.show_dataset_and_preview('none');
        } catch (e) {}
    },

    // Return the number of parts in the form.
    count_subq : function() {
        var i = 0;
        while (true) {
            var tmp = document.getElementsByName('answermark' + '[' + i + ']')[0];
            if (tmp == null) {
                break;
            }
            i++;
        }
        return i;
    },

    // By default, the value of global input field will apply to all its parts.
    init_global_options : function(name) {
        var n_global = document.getElementsByName('global' + name)[0];
        var n = document.getElementsByName(name + '[0]')[0];    // Pick the first value as global.
        n_global.value = n.value;
        n_global.onchange = function() {
            formulas_form_init_global_options_update(name);
        };
        formulas_form_init_global_options_update(name);
    },

    // Allow a more user friend way to select the commonly used criteria.
    init_selective_criteria : function(i) {
        var n = document.getElementById('id_correctness_' +i);
        loc = n.parentNode
        while(!loc.classList.contains("fitem")) {
            loc = loc.parentNode;
        }

        var showid = 'id_correctness_' + i + '_show';
        var b = document.getElementById(showid);
        if (b == null) {
            var tmp = document.createElement('div');
            tmp.id = showid;
            tmp.classList.add('formulas_correctness_show');
            b = loc.insertBefore(tmp, loc.firstChild);
        }

        initial_checked = false;    // Always unchecked by default.

        formulas_form_correctness(i, initial_checked);

        var ctext = initial_checked ? ' checked="checked" ' : '';
        var s = '<input type="checkbox" onclick="formulas_form_correctness(' + i + ',this.checked)" ' + ctext + ' id="' + showid + '" value="Expert">';
        var t = '<span onclick="var t=document.getElementById(\'' + showid + '\'); t.checked = !t.checked; formulas_form_correctness(' + i + ',t.checked);">' + b.innerHTML + '</span>';
        b.innerHTML = s + t;
    },

    // Add the options to select the number of datasets.
    init_numdataset_option : function() {
        var s = '';
        var a = {1:1, 5:5 ,10:10, 25:25, 50:50, 100:100, 250:250, 500:500, 1000:1000, '-1':'*'};
        for (var i in a) {
            s += '<option value="' + i + '" ' + (i == 5 ? ' selected="selected"' : '') + '>' + a[i] + '</option>';
        }
        s = '<select name="numdataset" id="numdataset">' + s + '</select><input type="button" value="' +
                M.util.get_string('instantiate', 'qtype_formulas') + '" onclick="formulasform.instantiate_dataset()"><div id="xxx"></div>';
        var loc = document.getElementById('numdataset_option');
        loc.innerHTML = s;
    },

    // Instantiate the dataset by the server and get back the data.
    instantiate_dataset : function() {
        var data = [];
        data['varsrandom'] = document.getElementById('id_varsrandom').value;
        data['varsglobal'] = document.getElementById('id_varsglobal').value;
        for (var i = 0; i < this.numsubq; i++) {
            data['varslocals[' + i + ']'] = document.getElementById('id_vars1_' + i).value;
            data['answers[' + i + ']'] = document.getElementById('id_answer_' + i).value;
        }
        data['start'] = 0;
        data['N'] = document.getElementById('numdataset').value;
        data['random'] = 0;

        var p = [];
        for (var key in data) {
            p[p.length] = encodeURIComponent(key) + '=' + encodeURIComponent(data[key]);
        }
        params = p.join('&').replace(/ /g,'+');

        var url = M.cfg.wwwroot + '/question/type/formulas/instantiate.php';

        var http_request = new XMLHttpRequest();
        http_request.open( "POST", url, true );

        http_request.setRequestHeader("Content-type", "application/x-www-form-urlencoded");
        http_request.setRequestHeader("Content-length", params.length);
        http_request.setRequestHeader("Connection", "close");

        http_request.onreadystatechange = function () {
            if (http_request.readyState == 4 && http_request.status == 200) {
                //document.getElementById('xxx').innerHTML = http_request.responseText;
                formulasform.vars = JSON.parse( http_request.responseText );
                formulasform.show_dataset_and_preview('block');
                //alert("message=" +JSON.stringify(formulasform.vars));
                // Add the controls for the display of dataset and preview.
                try { formulasform.update_dataset(); } catch (e) { alert(e); }
                try { formulasform.update_statistics(); } catch (e) {}
                try { formulasform.init_preview_controls(); } catch (e) {}
            }
        };
        http_request.send(params);
        formulasform.show_dataset_and_preview('hidden');
    },

    // Show or hide the dataset and preview region.
    show_dataset_and_preview : function(show) {
        var childen_ids = ['qtextpreview_display','varsstatistics_display','varsdata_display'];
        for (var i = 0; i < childen_ids.length; i++) {
            try {
                var style = document.getElementById(childen_ids[i]).parentNode.parentNode.style;
                style.visibility = (show == 'hidden' ? 'hidden' : 'visible');
            if (show != 'hidden') {
                style.display = show;
            }
            } catch(e) {}
        }
    },

    // Return the set of groupnames selected for display.
    get_groupnames : function() {
        var groupnames = ['leading','random','global'];
        for (var i = 0; i < 100; i++) {     // At most 100 parts.
            groupnames.push('local' + i);
            groupnames.push('answer' + i);
        }
        return groupnames;
    },

    // Add the controls to view the dataset.
    update_dataset : function() {
        var loc = document.getElementById('varsdata_display');
        loc.innerHTML = '';

        var groupnames = this.get_groupnames();
        var names = {};
        for (var z in this.vars.names) {
            names[z] = this.vars.names[z];
        }
        names['leading'] = ['#'];
        var lists = [];
        for (var k = 0; k < this.vars.lists.length; k++) {
            lists.push({});
            for (var z in this.vars.lists[k]) {
                lists[k][z] = this.vars.lists[k][z];
            }
            lists[k]['leading'] = [k];
        }

        var result = this.get_dataset_display(names, lists, this.vars.errors, groupnames);
        loc.innerHTML = result;
    },

    // Show the statistics for the dataset.
    update_statistics : function() {
        var loc = document.getElementById('varsstatistics_display');
        loc.innerHTML = '';

        var groupnames = this.get_groupnames();
        //var quantities = ['N', 'mean', 'variance', 'min', 'Q1', 'median', 'Q3', 'max'];
        //var quantities = ['min', 'max', 'mean', 'SD', 'N'];
        var quantities = ['min', 'max'];
        var errors = [];
        var names = {};
        names['leading'] = [''];
        for (var z in this.vars.names) {
            names[z] = this.vars.names[z];
        }
        var lists = [];
        for (var k = 0; k < quantities.length; k++) {
            lists.push({});
        }

        for (var i = 0; i < groupnames.length; i++) {
            var n = this.vars.names[groupnames[i]];
            if (n == null) {
                continue;
            }
            var stat = [];
            for (var j = 0; j < n.length; j++) {
                var data = [];
                for (var count = 0; count < this.vars.lists.length; count++) {
                    try {   // Skip all unknown data.
                        var subset = this.vars.lists[count][groupnames[i]];
                        data.push(subset[j]);
                    } catch(e) {}
                }
                var tmpst = this.get_statistics(data);
                stat.push(tmpst);
            }
            for (var k = 0; k < quantities.length; k++) {
                lists[k][groupnames[i]] = [];
                for (var z = 0; z < stat.length; z++) {
                    lists[k][groupnames[i]][z] = stat[z][quantities[k]];
                }
            }
        }
        for (var k = 0; k < quantities.length; k++) {
            lists[k]['leading'] = [quantities[k]];
            errors[k] = '';
        }

        var result = this.get_dataset_display(names, lists, errors, groupnames);
        loc.innerHTML = result;
    },

    // Return the statistics for the input data.
    get_statistics : function(data) {
        var sum = 0.;
        var sum2 = 0.;
        var minimum = Number.MAX_VALUE;
        var maximum = -Number.MAX_VALUE;
        var N = 0.;
        for (var i = 0; i < data.length; i++) {
            if (!isNaN(data[i])) {
                sum += data[i];
                sum2 += data[i] * data[i];
                minimum = Math.min(minimum, data[i]);
                maximum = Math.max(maximum, data[i]);
                N++;
            }
        }

        if (N == 0)  return {};   // No need to perform statistics.
        var sd = Math.sqrt((sum2 - sum * sum / N) / (N - 1.));
        if (N <= 1 || isNaN(sd)) {
            sd = 0;
        }
        return {'N': N, 'mean': sum / N, 'SD': sd, 'min':minimum, 'max':maximum};
    },

    // Display the datatable of the instantiated variables.
    get_dataset_display : function(names, lists, errors, groupnames) {
        var header = '';
        for (var i = 0; i < groupnames.length; i++) {
            var n = names[groupnames[i]];
            if (n == null) {
                continue;
            }
            for (var j = 0; j < n.length; j++) {
                header += '<th class="header">' + n[j] + '</th>';
            }
        }
        header = '<tr>' + header + '</tr>';

        var s = '';
        for (var count = 0; count < lists.length; count++) {
            if (count % 50 == 0) {
                s += header;
            }
            var row = '';
            for (var i = 0; i < groupnames.length; i++) {
                var n = names[groupnames[i]];
                if (n == null) {
                    continue;
                }
                var subset = lists[count][groupnames[i]];
                if (subset == null || subset.length != n.length) {
                    break;    // Stop outputting any further data for this row.
                }
                for (var j = 0; j < n.length; j++) {
                    try {
                        row += '<td>' + this.get_dataset_shorten(subset[j]) + '</td>';
                    } catch(e) {
                        row += '<td></td>';
                    }
                }
            }
            s += '<tr class="r' + (count % 2) + '">' + row + (errors[count] == '' ? '' : '<td>' + errors[count] + '</td>') + '</tr>';
        }

        return '<table border="1" width="100%" cellpadding="3">' + s + '</table>';
    },

    // Return a html string of the shortened element in the dataset table.
    get_dataset_shorten : function(elem) {
        if (elem instanceof Array) {
            var tmp_ss = [];
            for (var k = 0; k < elem.length; k++) {
                if (typeof elem[k] == 'string') {
                    tmp_ss[k] = elem[k];
                } else {
                    var s = elem[k].toPrecision(4).length < ('' + elem[k]).length ? elem[k].toPrecision(4) : '' + elem[k];  // Get the shorter one.
                    tmp_ss[k] = '<span title="' + elem[k] + '">' + s + '</span>';
                }
            }
            return tmp_ss.join(', ');
        } else {
            if (typeof elem == 'string') {
                return '<span title="' + elem + '">' + elem + '</span>';
            } else {
                s = elem.toPrecision(4).length < ('' + elem).length ? elem.toPrecision(4) : '' + elem;  // Get the shorter one.
                return '<span title="' + elem + '">' + s + '</span>';
            }
        }
    },

    // Add the controls for the preview function.
    init_preview_controls : function() {
        var block0 = '';
        for (var i = 0; i < this.vars.lists.length; i++) {
            block0 += '<option value="' + i + '">' + i + '</option>';
        }
        block0 = (this.vars.lists.length == 0) ? '' : '<select id="id_formulas_idataset" onchange="formulasform.update_preview()">' + block0 + '</select>';
        var block1 = '<input type="button" onclick="formulasform.update_preview()" value="' + M.util.get_string('renew', 'qtype_formulas') + '">';
        var loc = document.getElementById('qtextpreview_controls');
        loc.innerHTML = block0 + block1;

        this.update_preview();
    },

    // Show the questiontext with variables replaced.
    update_preview : function() {
        try {
            var globaltext = tinyMCE.get('id_questiontext').getContent();
        } catch(e) {
            var globaltext = document.getElementById('id_questiontext').value;
        }
        var idataset = document.getElementById('id_formulas_idataset').value;
        var res = this.substitute_variables_in_text(globaltext, this.get_variables_mapping(idataset, ['random','global']));

        for (var i = 0; i < this.numsubq; i++) {
            try {
                var txt = tinyMCE.get('id_subqtext_' + i).getContent();
            } catch(e) {
                var txt = document.getElementById('id_subqtext_' + i).value;
            }
            try {
                var fb = tinyMCE.get('id_feeback_' + i).getContent();
            } catch(e) {
                var fb = document.getElementById('id_feedback_' + i).value;
            }
            var ans = document.getElementsByName('answer' + '[' + i + ']')[0];
            var ph = document.getElementsByName('placeholder' + '[' + i + ']')[0];
            var unit = document.getElementsByName('postunit' + '[' + i + ']')[0];
            var answer = this.get_variables_mapping(idataset, ['answer' + i])['@' + (i + 1)];
            if (answer == null) {
                continue;
            }
            var mapping = this.get_variables_mapping(idataset, ['random','global','local' + i]);
            var t = txt + '<div style="border: solid 1px #aaaaaa; margin : 10px">' + answer + ' ' + unit.value.split('=')[0] + '</div>';
            t += (fb.length > 0) ? '<div style="border: solid 1px #aaaaaa; margin : 10px">' + fb + '</div>' : '';
            t = this.substitute_variables_in_text(t, mapping);
            t = '<div style="border: solid 1px #ddddff;"> ' + t + '</div>';
            if (ph.value == '') {
                res += t;     // add the text at the end
            } else {
                res = res.replace('{' + ph.value + '}', t);
            }
        }

        var preview = document.getElementById('qtextpreview_display');
        preview.innerHTML = '<div style="border: solid black 2px; padding : 5px">' + res + '</div>';
    },

    // Return the mapping from name to variable values, for the groups specified by groupnames.
    get_variables_mapping : function(idataset, groupnames) {
        mapping = {};
        for (var i = 0; i < groupnames.length; i++) {
            var names = this.vars.names[groupnames[i]];
            if (names == null) {
                continue;
            }
            var subset = this.vars.lists[idataset][groupnames[i]];
            if (subset == null || subset.length != names.length) {
                break;    // Stop outputting any further data for this row.
            }
            for (var j = 0; j < names.length; j++) {
                mapping[names[j]] = subset[j];
            }
        }
        return mapping;
    },

    // Substitute the variables in the text, where the variables is given by the mapping.
    substitute_variables_in_text : function(text, mapping) {
        var matches = text.match(/\{([A-Za-z][A-Za-z0-9_]*)(\[([0-9]+)\])?\}/g);
        if (matches == null || matches.length == 0) {
            return text;
        }
        for (var i = 0; i < matches.length; i++) {
            var d = /\{([A-Za-z][A-Za-z0-9_]*)(\[([0-9]+)\])?\}/.exec(matches[i]);
            if (d == null) {
                continue;
            }
            if (mapping[d[1]] == null) {
                continue;
            }
            value = mapping[d[1]];
            if (value instanceof Array) {
                var idx = parseInt(d[3]);
                if (idx >= value.length) {
                    continue;
                }
                value = value[idx];
            }
            text = text.replace(matches[i], value);
        }
        return text;
    }
};



window.onload = (function(oldfunc, newfunc) {
    if (oldfunc && typeof oldfunc == 'function') {
        return function() { oldfunc(); newfunc(); }
    } else {
        return newfunc;
    }
})(window.onload, function() { formulasform.init(); });
