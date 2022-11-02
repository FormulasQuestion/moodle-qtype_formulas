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

import 'core/config';

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

    for (let i = 0; i < numberOfParts; i++) {
        let textfield = document.getElementById(`id_correctness_${i}`);
        // Constantly check whether the current grading criterion is simple enough
        // to allow to switch to simple mode.
        textfield.addEventListener('input', blockModeSwitcherIfNeeded.bind(null, i));

        // Event listener for the submission of the form (attach only once)
        if (i === 0) {
            textfield.form.addEventListener('submit', reenableCriterionTextfields);
        }

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
        document.getElementById(`id_correctness_simple_type_${i}`).addEventListener(
            'change', handleSimpleCriterionChanges.bind(null, i)
        );
        document.getElementById(`id_correctness_simple_comp_${i}`).addEventListener(
            'change', handleSimpleCriterionChanges.bind(null, i)
        );
        document.getElementById(`id_correctness_simple_tol_${i}`).addEventListener(
            'change', handleSimpleCriterionChanges.bind(null, i)
        );
        document.getElementById(`id_correctness_simple_tol_${i}`).addEventListener(
            'change', normalizeTolerance
        );
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
