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
import {latexify} from 'qtype_formulas/latexify';
import {notifyFilterContentUpdated} from 'core_filters/events';
import {eventTypes as filterEventTypes} from 'core_filters/events';

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
    // We will trigger MathJax to make sure it is initialized very early.
    if (typeof window.MathJax === 'undefined') {
        forceInitMathJax();
    }

    let inputs = document.getElementsByTagName('input');
    for (let input of inputs) {
        // First make sure the input belongs to our qtype. We only have text fields and
        // they all have a 'formulas_' class, e. g. 'formulas_number_unit'.
        if (input.type !== 'text' || !input.className.match('formulas_')) {
            continue;
        }

        // Also, we do not currently validate unit fields.
        if (input.dataset.answertype == 'unit') {
            continue;
        }

        // Attach event listener for the input event.
        input.addEventListener('input', setDebounceTimer);
    }

    // If we have a recent version of Moodle, the MathJax filter will notify us when our LaTex is
    // rendered.
    if (typeof filterEventTypes.filterContentRenderingComplete !== 'undefined') {
        document.addEventListener(filterEventTypes.filterContentRenderingComplete, handleRenderingComplete);
    }

    // on focus: if empty return, if invalid retun, otherwise: render and show preview
    //  --> if same as last focus, unhide preview, otherwise delete preview and recreate

    // on blur: hide mathjax preview

    // on input: if empty return, if invalid remove jax and show warning, if valid remove warning and show jax
};

const forceInitMathJax = () => {
    if (typeof window.MathJax === 'undefined') {
        notifyFilterContentUpdated(document.querySelector('body'));
        setTimeout(forceInitMathJax, 200);
    } else {
        window.MathJax.Hub.Register.MessageHook('New Math', handleRenderingComplete);
    }
};

const handleRenderingComplete = (evt) => {
    let mathjaxSpan = null;
    // For older Moodle versions, we get an array of two strings. The first string
    // is just 'New Math' (message type), the second is 'MathJax-Element-xxx' indicating
    // the name of the new element.
    // For more recent versions, we get an event. The nodes will be stored in the array
    // in evt.detail.nodes.
    if (Array.isArray(evt)) {
        let id = evt[1];
        mathjaxSpan = document.querySelector(`span#${id}-Frame`);
    } else if (evt instanceof CustomEvent) {
        for (let element of evt.detail.nodes) {
            // Iterate until we find our preview <div>.
            if (element.id === 'qtype_formulas_mathjax_display') {
                mathjaxSpan = document.querySelector('span[id^=MathJax-Element-][id$=Frame]');
                break;
            }
        }
    }
    let width = 0;
    if (mathjaxSpan !== null) {
        width = mathjaxSpan.getBoundingClientRect().width;
    }
    window.console.log(width);
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
                'answer': field.value,
                'answertype': field.dataset.answertype,
                'withunit': field.dataset.withunit,
            },
        }])[0];
        symbol.style.visibility = (validationResult ? 'hidden' : 'visible');
        if (validationResult) {
            let texcode = await latexify(field.value);
            showMathJax(id, texcode);
        } else {
            removeDiv(null);
        }
    } catch (err) {
        Notification.exception(err);
    }

    pendingPromise.resolve();

    // The event listener will not be added multiple times, because the handler is a named function.
    // So we do not have to check whether we have already added it or not.
    field.addEventListener('blur', removeDiv);
};

const removeDiv = (evt) => {
    let div = document.getElementById('qtype_formulas_mathjax_display');
    if (div !== null) {
        //div.remove();
    }
    if (evt !== null) {
        evt.target.removeEventListener('blur', removeDiv, false);
    }
};

const showMathJax = (id, texcode) => {
    let field = document.getElementById(id);

    let div = document.getElementById('qtype_formulas_mathjax_display');
    if (div === null) {
        div = document.createElement('div');
        div.id = 'qtype_formulas_mathjax_display';
        div.classList.add('filter_mathjaxloader_equation');
        field.parentNode.insertBefore(div, field.nextSibling);
    }

    div.innerText = `\\(\\displaystyle ${texcode} \\)`;

    // Tell the MathJax filter that we have added some content to be rendered.
    notifyFilterContentUpdated(div.parentNode);


    return div;
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
