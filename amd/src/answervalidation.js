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
 * On-the-fly validation for student input to Formulas questions.
 *
 * @module     qtype_formulas/answervalidation
 * @copyright  2025 Philipp Imhof
 * @author     Philipp Imhof
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import * as Notification from 'core/notification';
import Pending from 'core/pending';
import {call as fetchMany} from 'core/ajax';

/**
 * Array to store all pending timers, allowing to reset / cancel them.
 */
var timers = [];

/**
 * Delay (in milliseconds) before sending the current input of a field to validation.
 */
const DELAY = 200;

/**
 * Initialisation, i. e. attaching event handlers to the input fields.
 */
export const init = () => {
    let inputs = document.getElementsByTagName('input');

    for (let input of inputs) {
        // First make sure the input belongs to our qtype. We only have text fields and
        // they all have a 'formulas_' class, e. g. 'formulas_number_unit'.
        if (input.type !== 'text' || !input.className.match('formulas_')) {
            continue;
        }

        // Also, we do not validate unit fields.
        if (input.dataset.answertype == 'unit') {
            continue;
        }

        // Attach event listener for the input event.
        input.addEventListener('input', setDebounceTimer);
    }
};

/**
 * Send student input to the web service for validation and show or hide the warning symbol
 * depending on the result.
 *
 * @param {string} id form field's id
 */
const validateStudentAnswer = async(id) => {
    let field = document.getElementById(id);
    let symbol = document.getElementById(`warning-${id}`);

    // Empty fields must not be validated and should not have a warning symbol.
    if (field.value === '') {
        symbol.style.visibility = 'hidden';
        return;
    }

    let pendingPromise = new Pending('qtype_formulas/validatestudentanswer');
    try {
        let validationResult = await fetchMany([{
            methodname: 'qtype_formulas_validate_student_answer',
            args: {
                answer: field.value,
                answertype: field.dataset.answertype,
                withunit: field.dataset.withunit,
            },
        }])[0];
        symbol.style.visibility = (validationResult ? 'hidden' : 'visible');
    } catch (err) {
        Notification.exception(err);
    }

    pendingPromise.resolve();
};

/**
 * Event handler: set or re-initialize timer for a given input field.
 *
 * @param {Event} evt event
 */
const setDebounceTimer = (evt) => {
    // If a timer has already been set, delete it.
    if (typeof timers[evt.target.id] === 'number') {
        clearTimeout(timers[evt.target.id]);
    }
    // Set timer for given input field.
    timers[evt.target.id] = setTimeout(validateStudentAnswer, DELAY, evt.target.id);
};

export default {init};
