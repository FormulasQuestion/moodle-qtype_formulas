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
import {notifyFilterContentUpdated} from 'core_filters/events';
import {eventTypes as filterEventTypes} from 'core_filters/events';

/**
 * Variable to store pending timer, allowing to reset / cancel it.
 */
var timer = null;

/**
 * Delay (in milliseconds) before sending the current input of a field to validation.
 */
const DELAY = 250;

/**
 * Initialisation, i. e. attaching event handlers to the input fields and making sure MathJax
 * is ready.
 */
export const init = () => {
    // We will trigger MathJax to make sure it is initialized very early.
    if (typeof window.MathJax === 'undefined') {
        forceInitMathJax();
    }

    // Attach event listener for the input, focus and blur events for all our answer fields.
    let inputs = document.querySelectorAll("input[type='text'][class*='formulas_'],input[type='text'][id*='_postunit_']");
    for (let input of inputs) {
        input.addEventListener('input', setDebounceTimer);
        input.addEventListener('focus', focusReceived);
        input.addEventListener('blur', hideMathJax);
    }

    // If we have a recent version of Moodle (4.3 and newer), the MathJax filter will notify us when our
    // LaTex is rendered. Otherwise, we register a legacy callback.
    if (typeof filterEventTypes.filterContentRenderingComplete !== 'undefined') {
        document.addEventListener(filterEventTypes.filterContentRenderingComplete, handleRenderingComplete);
    } else {
        addLegacyMathJaxListener();
    }
};

/**
 * Event handler when input field receives focus.
 *
 * @param {Event} evt event with details
 * @returns void
 */
const focusReceived = (evt) => {
    const field = evt.target;

    // If the field is empty, there is nothing to do.
    if (field.value.trim() == '') {
        return;
    }

    // If the field is not empty, not invalid and we already have a MathJax display for this field,
    // we can simply reactivate it -- unless the field does not need rendering, because in that case,
    // the content might not be accurate.
    const div = document.getElementById('qtype_formulas_mathjax_display');
    let isOurDiv = div !== null && div.dataset.for == field.id;
    if (!doesNotNeedRendering(field.value) && !field.classList.contains('is-invalid') && isOurDiv) {
        div.style.visibility = 'visible';
        return;
    }

    // In all other cases, we have to process the field's content again and recreate
    // the MathJax preview.
    validateStudentAnswer(field.id);
};

/**
 * Make sure MathJax is properly initialized. If the page contains MathJax content, e. g. in the question
 * text, this will happen automatically. However, we might have a page with no other MathJax content and
 * in this case, MathJax has just been loaded, but is not ready to typeset content.
 */
const forceInitMathJax = () => {
    if (typeof window.MathJax === 'undefined') {
        notifyFilterContentUpdated(document.querySelector('body'));
        setTimeout(forceInitMathJax, 200);
    }
};

/**
 * This function is only needed in Moodle versions prior to 4.3. It registers a callback in the
 * MathJax Hub in order to auto-resize our MathJax preview once the rendering is complete.
 */
const addLegacyMathJaxListener = () => {
    // If MathJax is not ready yet, retry in a moment.
    if (typeof window.MathJax === 'undefined') {
        setTimeout(addLegacyMathJaxListener, 200);
    } else {
        window.MathJax.Hub.Register.MessageHook('New Math', handleRenderingComplete);
    }
};

/**
 * Fetch the element containing the rendered MathJax, according to the MathJax version being
 * used.
 *
 * @param {Element} element DOM element where the MathJax was rendered
 * @returns Element
 */
const getMathJaxContainer = (element) => {
    // If we are using MathJax v3, the rendered output is in a custom <mjx-container> tag.
    // If we are using MathJax v2, the rendered output is in a <span> with a certain id.
    let v3container = element.querySelector('mjx-container');
    let v2container = element.querySelector("span[id^='MathJax-Element-'][id$='Frame']");

    return v3container || v2container;
};

/**
 * Once MathJax rendering is complete, we can find the width of the rendered content and adjust
 * our preview div's width accordingly.
 *
 * @param {Event|Array} evt event with details or array of two strings
 */
const handleRenderingComplete = (evt) => {
    let mathjaxSpan = null;

    // For older Moodle versions (prior to 4.3), we get an array of two strings. The first string
    // is just 'New Math' (message type), the second is 'MathJax-Element-xxx' indicating the name
    // of the new element. For more recent versions, we get a CustomEvent. The nodes will be stored in
    // the array in evt.detail.nodes.
    if (Array.isArray(evt)) {
        let id = evt[1];
        mathjaxSpan = document.querySelector(`span#${id}-Frame`);
    } else if (evt instanceof CustomEvent) {
        for (let element of evt.detail.nodes) {
            // Iterate until we find our preview <div>.
            if (element.id === 'qtype_formulas_mathjax_display') {
                mathjaxSpan = getMathJaxContainer(element);
                break;
            }
        }
    }

    // Fetch the width from MathJax' <span> via the bounding rectangle.
    let width = 0;
    if (mathjaxSpan !== null) {
        width = mathjaxSpan.getBoundingClientRect().width;
    }

    // Now fetch our preview <div> and set its width. We must account for the padding and
    // want to make sure that the preview is not larger than the rectangle around the question
    // itself.
    let div = document.getElementById('qtype_formulas_mathjax_display');
    if (div !== null) {
        let style = window.getComputedStyle(div);
        width += 3 * parseInt(style.padding);
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
    if (field.value.trim() === '') {
        field.classList.remove('is-invalid');
        hideMathJax();
        return;
    }

    // Send the input to the appropriate webservice.
    let pendingPromise = new Pending('qtype_formulas/validateanswer');
    try {
        let method, args;
        if (field.dataset.answertype === 'unit' || field.id.includes('_postunit_')) {
            method = 'qtype_formulas_validate_unit';
            args = {'unit': field.value};
        } else {
            method = 'qtype_formulas_validate_student_answer';
            args = {
                'answer': field.value,
                'answertype': field.dataset.answertype,
                'withunit': field.dataset.withunit,
            };
        }
        // The result will have a 'status' field ('success' or 'error') and a 'detail' field
        // containing either the error message or the LaTeX code.
        let validationResult = await fetchMany([{methodname: method, args: args}])[0];
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

/**
 * Function to hide our MathJax preview <div>.
 */
const hideMathJax = () => {
    let div = document.getElementById('qtype_formulas_mathjax_display');
    if (div !== null) {
        div.style.visibility = 'hidden';
    }
};

/**
 * If the field contains only a simple number (i. e. not in scientific notation) or
 * a simple unit (i. e. one unit with no exponent), there is no need to show the MathJax
 * rendering.
 *
 * @param {string} content the field's content
 * @returns bool
 */
const doesNotNeedRendering = (content) => {
    return content.trim().match(/^([A-Za-z]+|[0-9]*[.,]?[0-9]*)$/);
};

/**
 * Render LaTeX code and show the preview <div> at the right place.
 *
 * @param {string} id the input field's id
 * @param {*} texcode LaTeX code to be rendered and shown
 * @returns void
 */
const showMathJax = (id, texcode) => {
    let field = document.getElementById(id);

    // If the field does not have focus anymore, we stop here.
    if (document.activeElement.id !== id) {
        return;
    }

    if (doesNotNeedRendering(field.value)) {
        hideMathJax();
        return;
    }

    // If the div exists, but does not belong to our input field, delete it.
    let div = document.getElementById('qtype_formulas_mathjax_display');
    if (div !== null && div.dataset.for !== id) {
        div.remove();
        div = null;
    }

    // If there is no div (we might just have deleted it or it might not have existed at all), create one.
    if (div === null) {
        div = document.createElement('div');
        div.id = 'qtype_formulas_mathjax_display';
        div.classList.add('filter_mathjaxloader_equation');
        div.dataset.for = id;
        div.style.left = field.offsetLeft + 'px';
        // We insert the div right after the relevant input field.
        field.parentNode.insertBefore(div, field.nextSibling);
    }

    // Copy the LaTeX code into the div, show it and tell the MathJax filter that there is work to be done.
    div.innerText = `\\(\\displaystyle ${texcode} \\)`;
    div.style.visibility = 'visible';
    notifyFilterContentUpdated(div.parentNode);
};

/**
 * Event handler: set or re-initialize timer for a given input field.
 *
 * @param {Event} evt event with details
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
