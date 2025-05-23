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
 * Helper functions for the form used to create / edit a formulas question.
 *
 * @module     qtype_formulas/editform
 * @copyright  2022 Philipp Imhof
 * @author     Philipp Imhof
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import * as Notification from 'core/notification';
import Pending from 'core/pending';
import {call as fetchMany} from 'core/ajax';
import * as Instantiation from 'qtype_formulas/instantiation';
import * as String from 'core/str';

/**
 * Default grading criterion according to plugin settings (admin)
 */
var defaultCorrectness = '';

/**
 * Number of subquestions (parts)
 */
var numberOfParts = 0;

/**
 * Pending timer, allowing to reset / cancel it.
 */
var timer = null;

/**
 * Delay (in milliseconds) before sending the current input of a field to validation.
 */
const DELAY = 250;

/**
 * Warning text for use of caret in model answer.
 */
var caretWarning = '';

/**
 * Initialization, i. e. registration of event handlers and stuff.
 *
 * @param {string} defCorrectness default correctness criterion from admin settings
 */
export const init = (defCorrectness) => {
    defaultCorrectness = defCorrectness;
    numberOfParts = document.querySelectorAll("fieldset[id^='id_answerhdr_']").length;

    Instantiation.init(numberOfParts);

    // Pre-fetch strings; currently there is only one.
    fetchStrings();

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
        checkbox.addEventListener('change', handleGradingCriterionModeSwitcher.bind(null, i));

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

        // Attach listener for input event to answer fields.
        document.getElementById(`id_answer_${i}`).addEventListener('input', setDebounceTimer);
    }

    // When the form fields for random, global or any part's local variables loses focus,
    // have them validated by the backend. We don't use the 'change' event, because we want
    // the content re-validated, even if there is no change. This is to capture some edge
    // cases where there is an error in both random and global variables. The validation will
    // fail for random and cannot check the globals. Now, if the user fixes the error in random
    // and enters globals, we should have a new validation on blurring, even if there was no change.
    let variableFields = [{field: 'random', handler: validateRandomvars}, {field: 'global', handler: validateGlobalvars}];
    for (let i = 0; i < numberOfParts; i++) {
        variableFields.push({field: `1_${i}`, handler: validateLocalvars.bind(null, i)});
    }
    for (let field of variableFields) {
        document.getElementById(`id_vars${field.field}`).addEventListener(
            'blur', field.handler
        );
    }

    // Event listener for the "instantiate" button.
    document.getElementById('id_instantiatebtn').addEventListener(
        'click', Instantiation.instantiate
    );

    // We want to disable the simplified mode for all grading criterions where the validation
    // has found an error. If the document has finished loading (readyState is 'interactive'
    // or 'complete'), we can access all DOM elements, so we proceed. Otherwise, we attach the
    // corresponding method to the DOMContentLoaded event.
    if (document.readyState !== 'loading') {
        disableSimpleModeIfError();
    } else {
        document.addEventListener('DOMContentLoaded', disableSimpleModeIfError.bind(null));
    }
};

/**
 * Pre-fetch strings from the language file.
 */
const fetchStrings = async() => {
    let pendingPromise = new Pending('qtype_formulas/editformstrings');
    let strings = null;
    try {
        strings = await String.get_strings([
            {key: 'caretwarning', component: 'qtype_formulas'},
        ]);
    } catch (err) {
        Notification.exception(err);
    }
    pendingPromise.resolve();
    // If fetching of strings was not successful, we quit here.
    if (strings === null) {
        return;
    }
    caretWarning = strings[0];
};

/**
 * Event handler: set or re-initialize timer for a given input field.
 *
 * @param {Event} evt event
 */
const setDebounceTimer = (evt) => {
    // If a timer has already been set, delete it.
    if (typeof timer === 'number') {
        clearTimeout(timer);
    }
    // Set timer for given input field.
    timer = setTimeout(warnAboutCaret, DELAY, evt.target.id);
};

/**
 * For all parts, check whether there has been an evaluation error of the grading
 * criterion. If yes, we should not enter simplified mode, because the error message
 * will not be visible.
 */
const disableSimpleModeIfError = () => {
    for (let i = 0; i < numberOfParts; i++) {
        if (document.getElementById(`id_error_correctness_${i}`).innerText.trim() !== '') {
            document.getElementById(`id_correctness_simple_mode_${i}`).checked = false;
        }
    }
};

/**
 * Event handler for the global variables definition. The function will send the text to
 * the backend and try to evaluate it (together with the random variables, because global
 * variables can be based on random variables). If there is an error, it will be shown
 * in the form via {@link showOrClearValidationError}.
 *
 * @param {Event} evt Event object
 */
const validateGlobalvars = async(evt) => {
    // We don't validate an empty field. But if there is an error from earlier validation,
    // we must make sure it is removed.
    if (evt.target.value === '') {
        showOrClearValidationError(evt.target.id, '');
        return;
    }
    let pendingPromise = new Pending('qtype_formulas/validateglobal');
    try {
        let validationResult = await fetchMany([{
            methodname: 'qtype_formulas_check_random_global_vars',
            args: {
                randomvars: document.getElementById('id_varsrandom').value,
                globalvars: evt.target.value
            },
        }])[0];
        if (validationResult.source === '' || validationResult.source === 'global') {
            showOrClearValidationError(evt.target.id, validationResult.message);
        } else {
            showOrClearValidationError('id_varsrandom', validationResult.message, false);
        }
    } catch (err) {
        Notification.exception(err);
    }
    pendingPromise.resolve();
};

/**
 * Event handler for the random variables definition. The function will send the text to
 * the backend which tries to parse it and instantiate the variables. If there is an error,
 * it will be shown in the form via {@link showOrClearValidationError}.
 *
 * @param {Event} evt Event object
 */
const validateRandomvars = async(evt) => {
    // We don't validate an empty field. But if there is an error from earlier validation,
    // we must make sure it is removed.
    if (evt.target.value === '') {
        showOrClearValidationError(evt.target.id, '');
        return;
    }
    let pendingPromise = new Pending('qtype_formulas/validaterandom');
    try {
        let validationResult = await fetchMany([{
            methodname: 'qtype_formulas_check_random_global_vars',
            args: {
                randomvars: evt.target.value
            },
        }])[0];
        showOrClearValidationError(evt.target.id, validationResult.message);
    } catch (err) {
        Notification.exception(err);
    }
    pendingPromise.resolve();
};

/**
 * Send text from local variables to web service for validation.
 *
 * @param {number} part number of part
 */
const validateLocalvars = async(part) => {
    let fieldList = {
        'random': 'id_varsrandom',
        'global': 'id_varsglobal',
        'local': `id_vars1_${part}`
    };
    let target = document.getElementById(fieldList.local);
    // We don't validate an empty field. But if there is an error from earlier validation,
    // we must make sure it is removed.
    if (target.value === '') {
        showOrClearValidationError(target.id, '');
        return;
    }
    let pendingPromise = new Pending('qtype_formulas/validatelocal');
    try {
        let validationResult = await fetchMany([{
            methodname: 'qtype_formulas_check_local_vars',
            args: {
                randomvars: document.getElementById(fieldList.random).value,
                globalvars: document.getElementById(fieldList.global).value,
                localvars: target.value
            }
        }])[0];
        if (validationResult.source === '') {
            validationResult.source = 'local';
        }
        showOrClearValidationError(
            fieldList[validationResult.source],
            validationResult.message,
            validationResult.source === 'local'
        );
    } catch (err) {
        Notification.exception(err);
    }
    pendingPromise.resolve();
};

/**
 * Show a validation error below the corresponding form field and set the field
 * as invalid. Or remove message and marking, if there is no error anymore.
 *
 * @param {string} fieldID id of the form field to which the error belongs
 * @param {string} message error message or empty string, if error is to be removed
 * @param {boolean} sameField did the error occur in the field that was originally validated
 */
const showOrClearValidationError = (fieldID, message, sameField = true) => {
    let field = document.getElementById(fieldID);
    let annotation = document.getElementById(fieldID.replace(/^id_(.*)$/, 'id_error_$1'));
    let alreadyWithError = (annotation.innerText.trim() !== '');
    if (message === '') {
        annotation.innerText = '';
        field.classList.remove('is-invalid');
        return;
    }
    // If row and column number are -1, we remove them.
    annotation.innerText = message.replaceAll('-1:', '');
    field.classList.add('is-invalid');
    // If there is already an error in *this* field, we don't generally force the focus,
    // because that could trap the user. We do, however, set the focus, if the prior error
    // occured in another field.
    if (!alreadyWithError || !sameField) {
        // We set the focus here, so we don't depend on the further processing.
        field.focus();

        // If we have a row and column number, extract them and place the cursor accordingly.
        let messageParts = message.split(':', 2);
        if (messageParts.length < 2) {
            return;
        }
        let row = parseInt(messageParts[0]);
        let col = parseInt(messageParts[1]);
        jumpToRowAndColumn(field, row, col);
    }
};

/**
 * Show a notice about the meaning of the caret (^) symbol in model answers.
 *
 * @param {string} id the answer field's id
 */
const warnAboutCaret = (id) => {
    // If the string could not be loaded, we quit.
    if (caretWarning === '') {
        return;
    }

    let field = document.getElementById(id);
    let annotation = document.getElementById(id.replace(/^id_(.*)$/, 'id_error_$1'));

    // Display or hide the notice, depending on the presence of a caret in the model answer.
    // Also, we make sure not to overwrite or hide existing error messages, e. g. from the
    // form validation.
    if (field.value.includes('^')) {
        if (annotation.innerText.trim() !== '') {
            return;
        }
        annotation.innerText = caretWarning;
        annotation.style.display = 'block';
    } else {
        if (annotation.innerText !== caretWarning) {
            return;
        }
        annotation.innerText = '';
        annotation.style.display = '';
    }
};

/**
 * Jump to a certain text position (row, column) in a textarea field.
 *
 * @param {HTMLElement} field
 * @param {number} row the row
 * @param {number} col the column
 * @returns
 */
const jumpToRowAndColumn = (field, row, col) => {
    let lines = field.value.split('\n');

    // If the row number is invalid, we leave. Focus has already been set by the caller.
    if (row == -1 || col == -1) {
        return;
    }

    let cursorPosition = 0;
    // First, for every line, advance the appropriate number of characters.
    for (let i = 0; i < row - 1; i++) {
        // Stop if the row number is too high. This will bring us to the end of the field.
        if (i >= lines.length) {
            break;
        }
        cursorPosition += lines[i].length + 1;
    }
    // Now shift the cursor (col - 1) characters to the right, but not more than the line's length.
    // Also avoid shifting it to the left, in case col is 0.
    cursorPosition += Math.max(0, Math.min(col - 1, lines[row - 1].length));
    field.focus();
    field.setSelectionRange(cursorPosition, cursorPosition);
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
 *
 * @param {number} partNumber number of the part
 */
const handleSimpleCriterionChanges = (partNumber) => {
    let textbox = document.getElementById(`id_correctness_${partNumber}`);
    textbox.value = convertSimpleCriterionToText(partNumber);
};

/**
 * Parse the tolerance value into a number and put the value back into the textfield.
 * This allows for immediate simplification and some validation; invalid numbers will be replaced by 0.
 *
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
 *
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
 *
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
 *
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
 * If yes, enable said checkbox.
 * If the text box is empty, conversion is possible using the default value.
 *
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
