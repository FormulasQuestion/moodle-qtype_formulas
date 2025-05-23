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
 * support for the Moodle mobile app >= 3.9.5. PHP calls this from within
 * classes/output/mobile.php
 */

var that = this;
var result = {
    componentInit: function() {
        var div = document.createElement('div');
        div.innerHTML = this.question.html;

        // We use Bootstrap tooltips in the classic renderer, but we don't want them in the
        // Moodle App, because they can take away the focus.
        div.innerHTML = div.innerHTML.replaceAll(/data(-bs)?-toggle="tooltip"/g, '');
        this.question.text = div.querySelector('.qtext').innerHTML;

        // Replace Moodle's correct/incorrect and feedback classes with our own.
        that.CoreQuestionHelperProvider.replaceCorrectnessClasses(div);
        that.CoreQuestionHelperProvider.replaceFeedbackClasses(div);
        that.CoreQuestionHelperProvider.treatCorrectnessIcons(div);

        if (typeof this.question.text === 'undefined') {
            return that.CoreQuestionHelperProvider.showComponentError(this.onAbort);
        }

        if (div.querySelector('.readonly') !== null) {
            this.question.readOnly = true;
        }

        if (div.querySelector('.feedback') !== null) {
            this.question.feedback = div.querySelector('.feedback');
            this.question.feedbackHTML = true;
        }

        return true;
    }
};

/* eslint-disable-next-line */
result;