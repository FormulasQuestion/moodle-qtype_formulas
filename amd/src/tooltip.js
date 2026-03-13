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
 * Tooltip implementation for the Formulas question plugin.
 *
 * @module     qtype_formulas/tooltip
 * @copyright  2026 Philipp Imhof
 * @author     Philipp Imhof
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

var mouseIsOver = null;

/**
 * Initialisation, i. e. attaching event handlers to the input fields.
 */
export const init = () => {
    let inputs = document.querySelectorAll("input[type='text'][class*='formulas_'],input[type='text'][id*='_postunit_']");
    for (let input of inputs) {
        if (input.dataset.qtypeFormulasEnableTooltip !== 'true') {
            continue;
        }
        input.addEventListener('focus', focused);
        input.addEventListener('mouseover', mouseOver);
        input.addEventListener('mouseout', mouseOut);
        input.addEventListener('blur', lostFocus);
    }

    // When the user presses the Escape key anywhere, the tooltips should be removed in order to follow
    // accessibility standards.
    document.addEventListener('keydown', dealWithEscape);
};

/**
 * Event handler for the Escape key: remove all our tooltips.
 *
 * @param {Event} evt
 */
const dealWithEscape = (evt) => {
    if (evt.key === 'Escape') {
        const tooltips = document.querySelectorAll('div.qtype_formulas_tooltip_wrapper');
        for (let tooltip of tooltips) {
            hideTooltip(tooltip);
        }
    }
};

/**
 * Fetch the tooltip for a given input field, if it exists. Return null otherwise.
 *
 * @param {Element} field the input field
 * @returns {Element|null}
 */
const fetchTooltipFor = (field) => {
    return document.querySelector(`[data-qtype-formulas-tooltip-for="${field.id}"]`);
};

/**
 * Create and return a tooltip for the given input field, without activating (showing) it.
 *
 * @param {Element} field the input field
 * @returns Element
 */
const createTooltipFor = (field) => {
    let wrapper = document.createElement('div');
    wrapper.classList.add('qtype_formulas_tooltip_wrapper');
    wrapper.role = 'tooltip';
    wrapper.dataset.qtypeFormulasTooltipFor = field.id;

    let inner = document.createElement('div');
    inner.classList.add('qtype_formulas_tooltip_inner');
    inner.textContent = field.title;
    wrapper.appendChild(inner);
    document.body.appendChild(wrapper);

    return wrapper;
};

/**
 * Set whether a given tooltip should be persistent, i. e. remain visible when the mouse goes away.
 * This is generally the desired behaviour when a field is focused.
 *
 * @param {Element} tooltip the tooltip to make persistent
 * @param {boolean} persistent whether it should be persistent (true) or not
 */
const setPersistence = (tooltip, persistent) => {
    tooltip.dataset.qtypeFormulasTooltipPersistent = (persistent ? 'true' : 'false');
};

/**
 * Show a given tooltip.
 *
 * @param {Element} tooltip the tooltip to be shown
 */
const showTooltip = (tooltip) => {
    // Make sure the tooltip is placed right above the input field.
    const input = document.getElementById(tooltip.dataset.qtypeFormulasTooltipFor);
    const rect = input.getBoundingClientRect();
    const raise = parseInt(window.getComputedStyle(tooltip.firstChild, '::after').borderTopWidth);
    tooltip.style.top = `${rect.top - tooltip.offsetHeight - raise}px`;
    tooltip.style.left = `${rect.left + rect.width / 2}px`;
    tooltip.classList.add('show');
};

/**
 * Hide a given tooltip.
 *
 * @param {Element} tooltip the tooltip to be hidden
 */
const hideTooltip = (tooltip) => {
    tooltip.classList.remove('show');
    setPersistence(tooltip, false);
};

/**
 * FIXME
 *
 * @param {Event} evt
 * @returns void
 */
const lostFocus = (evt) => {
    const field = evt.target;

    // Fetch the tooltip. If it does not exist (which should not happen), we just leave.
    let tooltip = fetchTooltipFor(field);
    if (tooltip === null) {
        return;
    }

    // If the mouse is currently over our input field, we just mark the tooltip as non-persistent, in order for it
    // to be hidden when the mouse moves out. Otherwise, we hide the tooltip.
    if (mouseIsOver === field) {
        setPersistence(tooltip, false);
    } else {
        hideTooltip(tooltip);
    }
};

/**
 * FIXME
 *
 * @param {Event} evt
 * @returns void
 */
const mouseOut = (evt) => {
    const field = evt.target;

    // If the mouse is currently registered as being over the field we have just left, clear the
    // reference. If for some strange reason (e. g. delays) the mouse has already been registered
    // as hovering over another field, do not touch the reference.
    if (mouseIsOver === field) {
        mouseIsOver = null;
    }

    // If there is no tooltip, we leave.
    let tooltip = fetchTooltipFor(field);
    if (tooltip === null) {
        return;
    }

    // If the tooltip is meant to be persistent, we leave. Note that it is not enough to check whether
    // the field has focus, because when the user has pressed the Escape key while a field was focused,
    // then we want to hide it on the mouseout event despite the focus.
    if (tooltip.dataset.qtypeFormulasTooltipPersistent === 'true') {
        return;
    }

    hideTooltip(tooltip);
};

/**
 * Event handler when input field receives focus.
 *
 * @param {Event} evt event with details
 * @returns void
 */
const focused = (evt) => {
    const field = evt.target;

    // Fetch the tooltip and, if it does not exist, create it.
    let tooltip = fetchTooltipFor(field);
    if (tooltip === null) {
        tooltip = createTooltipFor(field);
    }
    // When a field gains or regains focus, the tooltip should become persistent, i. e. not be hidden when
    // the mouse moves away.
    setPersistence(tooltip, true);

    showTooltip(tooltip);
};

/**
 * FIXME
 *
 * @param {Event} evt
 */
const mouseOver = (evt) => {
    const field = evt.target;

    mouseIsOver = field;

    // Fetch the tooltip and, if it does not exist, create it. Tooltips created when the mouse hovers over a
    // field should be temporary only. However, persistence should not be changed for existing tooltips.
    let tooltip = fetchTooltipFor(field);
    if (tooltip === null) {
        tooltip = createTooltipFor(field);
        setPersistence(tooltip, false);
    }

    showTooltip(tooltip);
};

export default {init};
