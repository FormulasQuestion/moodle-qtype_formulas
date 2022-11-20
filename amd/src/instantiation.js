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
 * Helper functions to check instantiation of variables
 *
 * @module     qtype_formulas/instantiation
 * @copyright  2022 Philipp Imhof
 * @author     Philipp Imhof
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

 import * as Notification from 'core/notification';
 import * as String from 'core/str';
 import Pending from 'core/pending';
 import {call as fetchMany} from 'core/ajax';
 import {TabulatorFull as Tabulator} from 'qtype_formulas/tabulator';

/**
 * Number of subquestions (parts)
 */
var numberOfParts = 0;

const init = (noParts) => {
    numberOfParts = noParts;
    extendTabulator();
    initTable();
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
    // This includes the case where the variable is an "algebraic variable",
    // because those are represented as {variablename}, e.g. {a} for the variable a.
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
    if (typeof window.tinyMCE !== 'undefined' && window.tinyMCE.get(id) !== null) {
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
const previewQuestionWithDataset = async(row) => {
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

    let pendingPromise = new Pending('qtype_formulas/questionpreview');
    try {
        let renderedTexts = await fetchMany([{
            methodname: 'qtype_formulas_render_question_text',
            args: {
                questiontext: fetchTextFromEditor('id_questiontext'),
                parttexts: parttexts,
                globalvars: questionvars,
                partvars: partvars
            }
        }])[0];
        showRenderedQuestionAndParts(renderedTexts);
    } catch (err) {
        Notification.exception(err);
    }
    pendingPromise.resolve();
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
    let firstRow = data[0];
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
    // Fetch and show localized column group titles for random/global/part variables.
    localizeColumnGroupNames();


    // We do not show the calculation row in the footer if there's just one data set.
    let holders = document.querySelectorAll('div.tabulator-calcs-holder');
    for (let holder of holders) {
        holder.style.display = (data.length > 1 ? 'block' : 'none');
    }
};

/**
 * Make sure the column titles for random, global and part variables are localized.
 *
 * @returns {void}
 */
const localizeColumnGroupNames = async() => {
    // For proper localization, we need to fetch the text for each part separately, because
    // in some languages, the number might come before the word.
    let partStringRequests = [];
    for (let i = 0; i < numberOfParts; i++) {
        partStringRequests.push({key: 'answerno', component: 'qtype_formulas', param: i + 1});
    }
    let strings = null;
    let pendingPromise = new Pending('qtype_formulas/localization');
    try {
        strings = await String.get_strings([
            {key: 'varsrandom', component: 'qtype_formulas'},
            {key: 'varsglobal', component: 'qtype_formulas'},
            ...partStringRequests
        ]);
    } catch (err) {
        Notification.exception(err);
    }
    pendingPromise.resolve();
    // If fetching of strings was not successful, we quit here.
    if (strings === null) {
        return;
    }

    // Fetch all column groups. Unfortunately, Tabulator.js does currently only offer
    // an API to change column titles if the columns are not grouped. Therefore, we're
    // doing it manually.
    let columnGroups = document.querySelectorAll('div.tabulator-col-group');
    let i = 1;
    for (let group of columnGroups) {
        // We do not always have random and global variables, so it's better to make sure.
        if (group.getAttribute('aria-title') == 'Random variables') {
            setTitleForColumnGroup(group, strings[0]);
            continue;
        }
        if (group.getAttribute('aria-title') == 'Global variables') {
            setTitleForColumnGroup(group, strings[1]);
            continue;
        }
        // Remaining groups are for parts and there will always be at least one part.
        setTitleForColumnGroup(group, strings[1 + i]);
        i++;
    }
};

/**
 * Helper function to set the title and aria-title for a column group header.
 * @param {Element} element the <div> holding the column title
 * @param {string} title the new title
 */
const setTitleForColumnGroup = (element, title) => {
    element.setAttribute('aria-title', title);
    element.querySelector('div.tabulator-col-title').innerText = title;
};

/**
 * Prepare the data and send it to the Tabulator.js table for display.
 *
 * @param {object} data instantiation data as received from the backend
 */
const fillTable = (data) => {
    let allRows = [];
    let rowCounter = 0;
    for (let row of data) {
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
const instantiate = async() => {
    let howMany = document.getElementById('id_numdataset').value;
    let localvars = [];
    let answers = [];
    for (let i = 0; i < numberOfParts; i++) {
        localvars[i] = document.getElementById(`id_vars1_${i}`).value;
        answers[i] = document.getElementById(`id_answer_${i}`).value;
    }
    let pendingPromise = new Pending('qtype_formulas/instantiate');
    try {
        let response = await fetchMany([{
            methodname: 'qtype_formulas_instantiate',
            args: {
                n: howMany,
                randomvars: document.getElementById('id_varsrandom').value,
                globalvars: document.getElementById('id_varsglobal').value,
                localvars: localvars,
                answers: answers
            }
        }])[0];
        if (response.status == 'error') {
            let str = await String.get_string('previewerror', 'qtype_formulas');
            document.getElementById('qtextpreview_display').innerHTML = `${str}<br>${response.message}`;
        } else {
            document.getElementById('qtextpreview_display').innerHTML = '';
            prepareTableColumns(response.data);
        }
    } catch (err) {
        Notification.exception(err);
    }
    pendingPromise.resolve();
};

export default {init, instantiate};
