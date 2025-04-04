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
 * On-the-fly conversion of student response to LaTeX and rendering via the MathJax filter.
 *
 * @module     qtype_formulas/latexify
 * @copyright  2025 Philipp Imhof
 * @author     Philipp Imhof
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import * as Notification from 'core/notification';
import Pending from 'core/pending';
import {call as fetchMany} from 'core/ajax';

/**
 * FIXME
 *
 * @param {string} input student's input to render
 */
const latexify = async(input) => {
    let pendingPromise = new Pending('qtype_formulas/latexify');
    let texString = '';
    try {
        texString = await fetchMany([{
            methodname: 'qtype_formulas_latexify',
            args: {
                'input': input,
            },
        }])[0];
    } catch (err) {
        Notification.exception(err);
    }
    pendingPromise.resolve();
    return texString;
};

export default {latexify};
