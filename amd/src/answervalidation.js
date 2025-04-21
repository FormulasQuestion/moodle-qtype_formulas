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
// import {latexify} from 'qtype_formulas/latexify';
import {notifyFilterContentUpdated} from 'core_filters/events';
import {eventTypes as filterEventTypes} from 'core_filters/events';

/**
 * Variable to store pending timer, allowing to reset / cancel it.
 */
var timer = null;

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

        // Attach event listener for the focus and blur events.
        input.addEventListener('focus', focusReceived);
        input.addEventListener('blur', hideMathJax);
    }

    // If we have a recent version of Moodle, the MathJax filter will notify us when our LaTex is
    // rendered. Otherwise, we register a legacy callback.
    if (typeof filterEventTypes.filterContentRenderingComplete !== 'undefined') {
        document.addEventListener(filterEventTypes.filterContentRenderingComplete, handleRenderingComplete);
    } else {
        addLegacyMathJaxListener();
    }
};

const focusReceived = (evt) => {
    let field = evt.target;

    // If the field is empty, there is nothing to do.
    if (field.value == '') {
        return;
    }

    // If the field is not empty and we already have a MathJax display for this
    // field, we can simply reactivate it.
    let div = document.getElementById('qtype_formulas_mathjax_display');
    if (div !== null && div.dataset.for == field.id) {
        div.style.visibility = 'visible';
        return;
    }

    // In all other cases, we have to process the field's content again and recreate
    // the MathJax preview.
    validateStudentAnswer(field.id);
};

const forceInitMathJax = () => {
    if (typeof window.MathJax === 'undefined') {
        notifyFilterContentUpdated(document.querySelector('body'));
        setTimeout(forceInitMathJax, 200);
    }
};

const addLegacyMathJaxListener = () => {
    if (typeof window.MathJax === 'undefined') {
        setTimeout(addLegacyMathJaxListener, 200);
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
    let div = document.getElementById('qtype_formulas_mathjax_display');
    if (div !== null) {
        let style = window.getComputedStyle(div);
        width += 3 * parseInt(style.padding);
        // The preview should not be larger than the rectangle around the question.
        width = Math.min(width, div.parentNode.getBoundingClientRect().width);
        div.style.width = width + 'px';
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

    // Empty fields do not have to be validated and must not be marked as invalid.
    // If the MathJax preview is currently shown, it must be hidden.
    if (field.value === '') {
        field.classList.remove('is-invalid');
        hideMathJax();
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
        if (validationResult.status === 'success') {
            field.classList.remove('is-invalid');
            showMathJax(id, validationResult.detail);
        } else {
            field.classList.add('is-invalid');
            hideMathJax();
        }
    } catch (err) {
        Notification.exception(err);
    }

    pendingPromise.resolve();
};

const hideMathJax = () => {
    let div = document.getElementById('qtype_formulas_mathjax_display');
    if (div !== null) {
        div.style.visibility = 'hidden';
    }
};

const showMathJax = (id, texcode) => {
    let field = document.getElementById(id);

    // If the field does not have focus anymore, we stop here.
    if (document.activeElement.id !== id) {
        return;
    }

    let div = document.getElementById('qtype_formulas_mathjax_display');
    // If the div exists, but does not belong to our input field, delete it.
    if (div !== null && div.dataset.for !== id) {
        div.remove();
        div = null;
    }

    // If there is no div (or no div anymore), create one.
    if (div === null) {
        div = document.createElement('div');
        div.id = 'qtype_formulas_mathjax_display';
        div.classList.add('filter_mathjaxloader_equation');
        div.dataset.for = id;
        div.style.left = field.offsetLeft + 'px';
        field.parentNode.insertBefore(div, field.nextSibling);
    }

    div.innerText = `\\(\\displaystyle ${texcode} \\)`;
    div.style.visibility = 'visible';

    // Tell the MathJax filter that we have added some content to be rendered.
    notifyFilterContentUpdated(div.parentNode);
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
    timer = setTimeout(validateStudentAnswer, DELAY, evt.target.id);
};

export default {init};
