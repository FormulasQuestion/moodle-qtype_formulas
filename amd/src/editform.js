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
 * Helper functions for the form used to create / edit a formulas question.
 *
 * @module     qtype_formulas/editform
 * @copyright  2022 Philipp Imhof
 * @author     Philipp Imhof
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import * as Notification from 'core/notification';
import * as String from 'core/str';
import {call as fetchMany} from 'core/ajax';
import {TabulatorFull as Tabulator} from 'qtype_formulas/tabulator';

/**
 * Default grading criterion according to plugin settings (admin)
 */
var defaultCorrectness = '';

/**
 * Number of subquestions (parts)
 */
var numberOfParts = 0;

export const init = (defCorrectness) => {
    defaultCorrectness = defCorrectness;
    numberOfParts = document.querySelectorAll('fieldset[id^=id_answerhdr_]').length;

    extendTabulator();
    initTable();

    for (let i = 0; i < numberOfParts; i++) {
        let textfield = document.getElementById(`id_correctness_${i}`);

        // Event listener for the submission of the form (attach only once)
        if (i === 0) {
            textfield.form.addEventListener('submit', reenableCriterionTextfields);
        }

        // Constantly check whether the current grading criterion is simple enough
        // to allow to switch to simple mode.
        textfield.addEventListener('input', blockModeSwitcherIfNeeded.bind(null, i));

        let checkbox = document.getElementById(`id_correctness_simple_mode_${i}`);
        checkbox.addEventListener('click', handleGradingCriterionModeSwitcher.bind(null, i));

        // Trigger input event in criterion textfields in order to disable the mode switcher
        // checkbox, if needed. If the criterion is simple enough, start with simple mode,
        // unless the form comes back from validation and the textfield is marked as invalid.
        textfield.dispatchEvent(new Event('input'));
        if (!checkbox.disabled && !textfield.classList.contains('is-invalid')) {
            checkbox.checked = true;
            checkbox.dispatchEvent(new Event('click'));
        }

        // Always keep the textual form of the grading criterion in sync, because that's
        // what is going to be submitted in the end.
        let elements = ['type', 'comp', 'tol'];
        for (let element of elements) {
            document.getElementById(`id_correctness_simple_${element}_${i}`).addEventListener(
                'change', handleSimpleCriterionChanges.bind(null, i)
            );
        }
        document.getElementById(`id_correctness_simple_tol_${i}`).addEventListener(
            'change', normalizeTolerance
        );
    }

    // When the definition of random, global or any part's local variables is changed,
    // have them validated by the backend.
    let variableFields = [{field: 'random', handler: validateRandomvars}, {field: 'global', handler: validateGlobalvars}];
    for (let i = 0; i < numberOfParts; i++) {
        variableFields.push({field: `1_${i}`, handler: validateLocalvars.bind(null, i)});
    }
    for (let field of variableFields) {
        document.getElementById(`id_vars${field.field}`).addEventListener(
            'change', field.handler
        );
    }

    // Event listener for the "instantiate" button.
    document.getElementById('id_instantiatebtn').addEventListener(
        'click', instantiate
    );
};

/**
 * Add some customizations to Tabulator.js
 */
const extendTabulator = () => {
    Tabulator.extendModule('columnCalcs', 'calculations', {
        'stats': (values) => {
            var count = 0;
            var min = Infinity;
            var max = -Infinity;
            var sum = 0;

            for (let value of values) {
                sum += parseFloat(value);
                min = Math.min(min, value);
                max = Math.max(max, value);
                count++;
            }

            // If minimum and maximum are the same, we don't display the stats, because
            // the values are constant.
            if (min === max) {
                return ['', '', ''];
            }

            if (count > 0 && !isNaN(sum)) {
                return [(sum / count).toFixed(1), min, max];
            }
            return ['', '', ''];
        },
    });
};

/**
 * Init the table we use for checking the variables' instantiation.
 */
const initTable = () => {
    let table = new Tabulator('#varsdata_display', {
        selectable: 1,
        movableColumns: true,
        pagination: 'local',
        paginationSize: 10,
        paginationButtonCount: 0,
        columns: [
            {title: '#', field: 'id'},
        ],
        langs: {
            'default': {
                'pagination': {
                    'first': '⏮',
                    'last': '⏭',
                    'prev': '⏪',
                    'next': '⏩'
                }
            }
        },
    });
    table.on('rowSelected', previewQuestionWithDataset);
};

/**
 * For proper parsing in the backend, strings must be enclosed in double quotes,
 * but numbers must not.
 *
 * @param {string} value representation of a numberic, string or list (array) value
 * @returns {string} the same value, but with quotes added, if necessary
 */
const quoteNonNumericValue = (value) => {
    // Numbers must not be quoted.
    if (!isNaN(value)) {
        return value;
    }
    // For arrays, we have to check each element individually and quote, if necessary.
    // Formulas question does not currently support nested arrays, so we don't have to deal with that.
    if (value.startsWith('[')) {
        let quotedElements = [];
        // Remove leading and trailing bracket
        value = value.substring(1, value.length - 1);
        let elements = value.split(/\s*,\s*/);
        for (let element of elements) {
            quotedElements.push(quoteNonNumericValue(element));
        }
        return '[' + quotedElements.join(', ') + ']';
    }
    // Not a number and not an array, so we enclose it in double quotes.
    return `"${value}"`;
};

/**
 * The question text and the parts' text are stored in the editor. For some editors,
 * we can take the content from the textarea's value attribute. For TinyMCE (and maybe others),
 * we must use the corresponding API.
 *
 * @param {string} id id of the textarea
 * @returns {string} the question or part's text
 */
const fetchTextFromEditor = (id) => {
    if (typeof window.tinyMCE !== 'undefined') {
        return window.tinyMCE.get(id).getContent();
    }
    return document.getElementById(id).value;
};

/**
 * Extract data from the instantiation table (selected row) and send them to the backend,
 * in order to have the question text and parts' text rendered for the preview.
 *
 * @param {object} row RowComponent from Tabulator.js
 */
const previewQuestionWithDataset = (row) => {
    // The statistics row is clickable, but we cannot use its data to preview the question.
    if (row.getElement().classList.contains('tabulator-calcs')) {
        return;
    }
    let data = row.getData();
    let questionvars = '';
    let partvars = Array(numberOfParts).fill('');

    for (let varname in data) {
        // Variables for the main question are all random or global.
        // Also, as random variables have already been instantiated, they are not random anymore.
        if (varname.match(/^(random|global)_/)) {
            questionvars += varname.replace(/^(random|global)_([^*]+)\*?$/, '$2') + '=';
            questionvars += quoteNonNumericValue(data[varname]) + ';';
        }
        // Variables for a question part always start with part_ + number of the part
        if (varname.match(/^part_(\d+)_/)) {
            // If the variable name starts with _ it should be removed, as these are
            // answers (or otherwise reserved names, but that should not be the case)
            if (varname.match(/^part_(\d+)__/)) {
                continue;
            }
            let index = parseInt(varname.replace(/^part_(\d+)_.*$/, '$1'));
            partvars[index] += varname.replace(/^part_(\d+)_([^*]+)\*?$/, '$2') + '=';
            partvars[index] += quoteNonNumericValue(data[varname]) + ';';
        }
    }

    let parttexts = [];
    for (let i = 0; i < numberOfParts; i++) {
        parttexts[i] = fetchTextFromEditor(`id_subqtext_${i}`);
    }

    fetchMany([{
        methodname: 'qtype_formulas_render_question_text',
        args: {
            questiontext: fetchTextFromEditor('id_questiontext'),
            parttexts: parttexts,
            globalvars: questionvars,
            partvars: partvars
        },
        done: showRenderedQuestionAndParts,
        fail: Notification.exception
    }]);
};

/**
 * Trigger MathJax rendering for the question.
 *
 * @param {Element} element the <div> element where the question text is shown
 */
const triggerMathJax = (element) => {
    if (typeof window.MathJax === 'undefined') {
        return;
    }
    let version = window.MathJax.version;
    if (version[0] == '2') {
        window.MathJax.Hub.Queue(['Typeset', window.MathJax.Hub, element]);
        return;
    }
    if (version[0] == '3') {
        window.MathJax.typesetPromise([element]);
    }
};

/**
 * This function is called after the AJAX request to the backend is completed. It will inject
 * the rendered texts into the preview div.
 *
 * @param {object} data rendered version of question text and parts' text
 */
const showRenderedQuestionAndParts = (data) => {
    let div = document.getElementById('qtextpreview_display');
    div.innerHTML = data.question;
    for (let text of data.parts) {
        div.innerHTML += text;
    }
    triggerMathJax(div);
};

/**
 * Derive the column description from the instantiated variables.
 *
 * @param {object} data instantiation data as received from the backend
 */
const prepareTableColumns = (data) => {
    if (data.status == 'error') {
        String.get_string('previewerror', 'qtype_formulas').then((str) => {
            document.getElementById('qtextpreview_display').innerHTML = `${str}<br>${data.message}`;
        }).catch();
        return;
    }
    let firstRow = data.data[0];
    let calcOptions = {bottomCalc: 'stats', bottomCalcFormatter: (cell) => cell.getValue().join('<br>')};
    let columnDescription = [{title: '#', field: 'id', bottomCalcFormatter: () => '⌀<br>min</br>max'}];

    // Random variables come first
    let randomColumns = [];
    for (let column of firstRow.randomvars) {
        randomColumns.push({
            title: column.name,
            field: `random_${column.name}`,
            ...calcOptions
        });
    }
    if (randomColumns.length > 0) {
        columnDescription.push({title: 'Random variables', columns: randomColumns});
    }

    // Then we take the global variables
    let globalColumns = [];
    for (let column of firstRow.globalvars) {
        globalColumns.push({
            title: column.name,
            field: `global_${column.name}`,
            ...calcOptions
        });
    }
    if (globalColumns.length > 0) {
        columnDescription.push({title: 'Global variables', columns: globalColumns});
    }

    // Finally, we prepare the groups for each part
    let partColumns = [];
    let partIndex = 0;
    for (let part of firstRow.parts) {
        let thisPartsColumns = [];
        for (let vars of part) {
            thisPartsColumns.push({
                title: vars.name,
                field: `part_${partIndex}_${vars.name}`,
                ...calcOptions
            });
        }
        partColumns.push({title: `Part ${partIndex + 1}`, columns: thisPartsColumns});
        partIndex++;
    }
    columnDescription = [...columnDescription, ...partColumns];
    Tabulator.findTable("#varsdata_display")[0].setColumns(columnDescription);
    fillTable(data);

    // We do not show the calculation row in the footer if there's just one data set.
    let holders = document.querySelectorAll('div.tabulator-calcs-holder');
    for (let holder of holders) {
        holder.style.display = (data.data.length > 1 ? 'block' : 'none');
    }
};

/**
 * Prepare the data and send it to the Tabulator.js table for display.
 *
 * @param {object} data instantiation data as received from the backend
 */
const fillTable = (data) => {
    let allRows = [];
    let rowCounter = 0;
    for (let row of data.data) {
        let thisRow = {id: ++rowCounter};
        for (let thisVar of row.randomvars) {
            thisRow[`random_${thisVar.name}`] = thisVar.value;
        }
        for (let thisVar of row.globalvars) {
            thisRow[`global_${thisVar.name}`] = thisVar.value;
        }
        let partCounter = 0;
        for (let thisPart of row.parts) {
            for (let thisVar of thisPart) {
                thisRow[`part_${partCounter}_${thisVar.name}`] = thisVar.value;
            }
            partCounter++;
        }
        allRows.push(thisRow);
    }

    Tabulator.findTable("#varsdata_display")[0].setData(allRows);
};

/**
 * Send the definition of random variables, global variables and parts' local variables
 * to the backend for instantiation. This will generate a certain number of rows, based
 * on the number the user has selected in the corresponding dropdown field. Once the
 * AJAX requeset is completed, the data will be forwarded to {@link prepareTableColumns}.
 */
const instantiate = () => {
    let howMany = document.getElementById('id_numdataset').value;
    let localvars = [];
    let answers = [];
    for (let i = 0; i < numberOfParts; i++) {
        localvars[i] = document.getElementById(`id_vars1_${i}`).value;
        answers[i] = document.getElementById(`id_answer_${i}`).value;
    }
    fetchMany([{
        methodname: 'qtype_formulas_instantiate',
        args: {
            n: howMany,
            randomvars: document.getElementById('id_varsrandom').value,
            globalvars: document.getElementById('id_varsglobal').value,
            localvars: localvars,
            answers: answers
        },
        done: prepareTableColumns,
        fail: Notification.exception
    }]);
};

/**
 * Event handler for the global variables definition. The function will send the text to
 * the backend and try to evaluate it (together with the random variables, because global
 * variables can be based on random variables). If there is an error, it will be shown
 * in the form via {@link showOrClearValidationError}.
 *
 * @param {Event} evt Event object
 */
const validateGlobalvars = (evt) => {
    // We don't validate an empty field. But if there is an error from earlier validation,
    // we must make sure it is removed.
    if (evt.target.value === '') {
        showOrClearValidationError(evt.target.id, '');
        return;
    }
    fetchMany([{
        methodname: 'qtype_formulas_check_random_global_vars',
        args: {
            randomvars: document.getElementById('id_varsrandom').value,
            globalvars: evt.target.value
        },
        done: (answer) => {
            showOrClearValidationError(evt.target.id, answer);
        },
        fail: Notification.exception
    }]);
};

/**
 * Event handler for the random variables definition. The function will send the text to
 * the backend which tries to parse it and instantiate the variables. If there is an error,
 * it will be shown in the form via {@link showOrClearValidationError}.
 *
 * @param {Event} evt Event object
 */
const validateRandomvars = (evt) => {
    // We don't validate an empty field. But if there is an error from earlier validation,
    // we must make sure it is removed.
    if (evt.target.value === '') {
        showOrClearValidationError(evt.target.id, '');
        return;
    }
    fetchMany([{
        methodname: 'qtype_formulas_check_random_global_vars',
        args: {
            randomvars: evt.target.value
        },
        done: (answer) => {
            showOrClearValidationError(evt.target.id, answer);
        },
        fail: Notification.exception
    }]);
};

const validateLocalvars = (part) => {
    let target = document.getElementById(`id_vars1_${part}`);
    // We don't validate an empty field. But if there is an error from earlier validation,
    // we must make sure it is removed.
    if (target.value === '') {
        showOrClearValidationError(target.id, '');
        return;
    }
    fetchMany([{
        methodname: 'qtype_formulas_check_local_vars',
        args: {
            randomvars: document.getElementById('id_varsrandom').value,
            globalvars: document.getElementById('id_varsglobal').value,
            localvars: target.value
        },
        done: (answer) => {
            showOrClearValidationError(target.id, answer);
        },
        fail: Notification.exception
    }]);
};

/**
 * Show a validation error below the corresponding form field and set the field
 * as invalid. Or remove message and marking, if there is no error anymore.
 *
 * @param {string} fieldID id of the form field to which the error belongs
 * @param {string} message error message or empty string, if error is to be removed
 */
const showOrClearValidationError = (fieldID, message) => {
    let field = document.getElementById(fieldID);
    let annotation = document.getElementById(fieldID.replace(/^id_(.*)$/, 'id_error_$1'));
    if (message === '') {
        annotation.innerText = '';
        field.classList.remove('is-invalid');
    } else {
        annotation.innerText = message;
        field.classList.add('is-invalid');
        field.focus();
    }
};

/**
 * The textfields containing the grading criterion might be disabled. However, as disabled elements
 * do not submit their value, they have to be enabled before submitting the form.
 */
const reenableCriterionTextfields = () => {
    for (let i = 0; i < numberOfParts; i++) {
        document.getElementById(`id_correctness_${i}`).disabled = false;
    }
};

/**
 * Handle change event for the elements that allow simplified entry of the grading criterion.
 * On each modification, the current criterion is propagated to the (hidden) textbox,
 * that will be used to store the criterion in the database upon submission of the form.
 * @param {number} partNumber number of the part
 */
const handleSimpleCriterionChanges = (partNumber) => {
    let textbox = document.getElementById(`id_correctness_${partNumber}`);
    textbox.value = convertSimpleCriterionToText(partNumber);
};

/**
 * Parse the tolerance value into a number and put the value back into the textfield.
 * This allows for immediate simplification and some validation; invalid numbers will be replaced by 0.
 * @param {Event} event Event containing the textfield to be normalized
 */
const normalizeTolerance = (event) => {
    let field = event.target;
    let tolerance = parseFloat(field.value);

    if (isNaN(tolerance) || !isFinite(tolerance)) {
        tolerance = 0;
    }

    field.value = tolerance;
};

/**
 * Switch between simplified and normal entry mode for the grading criterion.
 * @param {number} partNumber number of the part
 */
const handleGradingCriterionModeSwitcher = (partNumber) => {
    let checkbox = document.getElementById(`id_correctness_simple_mode_${partNumber}`);

    let criterionTextfield = document.getElementById(`id_correctness_${partNumber}`);

    // If not checked anymore, activate expert mode --> convert settings to string and set textfield.
    if (!checkbox.checked) {
        criterionTextfield.value = convertSimpleCriterionToText(partNumber);
        return;
    }

    // Activate simple mode. If input field is empty, use default value.
    if (criterionTextfield.value.trim() == '') {
        criterionTextfield.value = defaultCorrectness;
    }

    let simpleCriterion = convertTextCriterionToSimple(partNumber);
    document.getElementById(`id_correctness_simple_type_${partNumber}`).value = simpleCriterion.type;
    document.getElementById(`id_correctness_simple_comp_${partNumber}`).value = simpleCriterion.comparison;
    document.getElementById(`id_correctness_simple_tol_${partNumber}`).value = simpleCriterion.tolerance;
};

/**
 * Convert the simple grading criterion into the corresponding text.
 * @param {number} partNumber number of the part
 * @returns {string} text form of the grading criterion
 */
const convertSimpleCriterionToText = (partNumber) => {
    let typeElement = document.getElementById(`id_correctness_simple_type_${partNumber}`);
    let comparisonElement = document.getElementById(`id_correctness_simple_comp_${partNumber}`);
    let toleranceElement = document.getElementById(`id_correctness_simple_tol_${partNumber}`);

    return ['_relerr', '_err'][typeElement.value] + ' '
        + comparisonElement.options[comparisonElement.value].innerText + ' '
        + parseFloat(toleranceElement.value);
};

/**
 * Convert the grading criterion into the simplified form.
 * @param {number} partNumber number of the part
 * @returns {object} criterion the simplified grading criterion
 * @returns {number} criterion.type the type of error (relative or absolute)
 * @returns {number} criterion.comparison the comparison (== or <)
 * @returns {number} criteron.tolerance the tolerance value
 * @throws {TypeError} throws if the value cannot be converted
 */
const convertTextCriterionToSimple = (partNumber) => {
    // Split input into its parts (type, comparison, tolerance).
    let criterionParts = document.getElementById(`id_correctness_${partNumber}`).value.split(/\s*(==|<)\s*/);

    // This should not happen, but it might be better to check anyway.
    if (criterionParts.length != 3 || !criterionParts[0].match(/^\s*_(rel)?err\s*$/)) {
        throw new TypeError('The given grading criterion cannot be shown in simple mode.');
    }

    return {
        'type': ['_relerr', '_err'].indexOf(criterionParts[0]),
        'comparison': ['==', '<'].indexOf(criterionParts[1]),
        'tolerance': parseFloat(criterionParts[2])
    };
};

/**
 * Check whether the current grading criterion can be converted into the simplified form.
 * If not, disable the checkbox that would allow switching to simple mode.
 * If yes, enable sais checkbox.
 * If the text box is empty, conversion is possible using the default value.
 * @param {number} partNumber number of the part
 */
const blockModeSwitcherIfNeeded = (partNumber) => {
    let criterion = document.getElementById(`id_correctness_${partNumber}`).value.trim();
    let modeCheckbox = document.getElementById(`id_correctness_simple_mode_${partNumber}`);
    // If textfield is empty, allow conversion to easy mode
    if (criterion == '') {
        modeCheckbox.disabled = false;
        return;
    }

    // Value must have exactly three parts: type + comparison + tolerance (number).
    let criterionParts = criterion.split(/\s*(==|<)\s*/);
    if (criterionParts.length != 3) {
        modeCheckbox.disabled = true;
        return;
    }

    // Type must be _relerr or _err.
    if (!criterionParts[0].match(/^\s*_(rel)?err\s*$/)) {
        modeCheckbox.disabled = true;
        return;
    }

    // Comparison must be == or <.
    if (!criterionParts[1].match(/\s*(==|<)\s*$/)) {
        modeCheckbox.disabled = true;
        return;
    }

    // Tolerance must be a number.
    let tolerance = parseFloat(criterionParts[2]);
    // As parseFloat ignores trailing characters, we check for that separately;
    // we just don't want the tolerance number to contain obviously invalid characters.
    if (isNaN(tolerance) || !isFinite(tolerance) || criterionParts[2].match(/[^-+0-9.e]/)) {
        modeCheckbox.disabled = true;
        return;
    }

    modeCheckbox.disabled = false;
};

export default {init};
