<?php
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
 * Base class for formulas unit tests.
 *
 * @package    qtype_formulas
 * @copyright  2012 Jean-Michel Védrine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace qtype_formulas;
use question_pattern_expectation;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/question/engine/tests/helpers.php');


/**
 * Base class for formulas walkthrough tests.
 *
 * Provides some additional asserts.
 *
 * @copyright 2012 Jean-Michel Védrine
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class walkthrough_test_base extends \qbehaviour_walkthrough_test_base {
    protected $currentoutput = null;

    protected function render() {
        $this->currentoutput = $this->quba->render_question($this->slot, $this->displayoptions);
    }

    protected function get_contains_num_parts_correct($num) {
        $s = get_string('yougotoneright', 'qtype_formulas');
        if ($num !== 1) {
            $s = get_string('yougotnright', 'qtype_formulas', $num);
        }

        return new question_pattern_expectation('/<div class="numpartscorrect">' .
            preg_quote($s, '/') . '/');
    }

    protected function get_contains_hint_expectation($text) {
        return new question_pattern_expectation('/<div class="hint">' .
            preg_quote($text, '/') . '/');
    }

    protected function check_output_contains_part_feedback($name = null) {
        $class = 'formulaspartfeedback';
        if ($name) {
            $class .= ' formulaspartfeedback-' . $name;
        }
        $this->assertTag(['tag' => 'div', 'attributes' => ['class' => $class]], $this->currentoutput,
                'part feedback for ' . $name . ' not found in ' . $this->currentoutput);
    }

    protected function check_output_does_not_contain_part_feedback($name = null) {
        $class = 'formulaspartfeedback';
        if ($name) {
            $class .= ' formulaspartfeedback-' . $name;
        }
        $this->assertNotTag(['tag' => 'div', 'attributes' => ['class' => $class]], $this->currentoutput,
                'part feedback for ' . $name . ' should not be present in ' . $this->currentoutput);
    }

    protected function check_output_does_not_contain_stray_placeholders() {
        // Keeping the old way for 3.9 until it reaches end-of-life.
        if (version_compare(\PHPUnit\Runner\Version::id(), '9.0.0', '>=')) {
            $this->assertDoesNotMatchRegularExpression('~\{|\}~', $this->currentoutput, 'Not all placehoders were replaced.');
        } else {
            $this->assertNotRegexp('~\{|\}~', $this->currentoutput, 'Not all placehoders were replaced.');
        }
    }
}
