<?php
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
 * Base class for formulas unit tests.
 *
 * @package    qtype_formulas
 * @copyright  2012 Jean-Michel Védrine
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
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
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class walkthrough_test_base extends \qbehaviour_walkthrough_test_base {
    /** @var string|null $currentoutput */
    protected $currentoutput = null;

    /**
     * Render the question and fetch the generated HTML.
     *
     * @return void
     */
    protected function render() {
        $this->currentoutput = $this->quba->render_question($this->slot, $this->displayoptions);
    }

    /**
     * Test whether the feedback concerning the number of correct parts is right.
     *
     * @param int $num
     * @return void
     */
    protected function get_contains_num_parts_correct($num) {
        $s = get_string('yougotoneright', 'qtype_formulas');
        if ($num !== 1) {
            $s = get_string('yougotnright', 'qtype_formulas', $num);
        }

        return new question_pattern_expectation('/<div class="numpartscorrect">' .
            preg_quote($s, '/') . '/');
    }

    /**
     * Create assertion to check that a hint with a given text is shown.
     *
     * @param string $text
     * @return void
     */
    protected function get_contains_hint_expectation($text) {
        return new question_pattern_expectation('/<div class="hint">' .
            preg_quote($text, '/') . '/');
    }

    /**
     * Create assertion to check that a <div> with certain class attributes is shown.
     *
     * @param string $class
     * @return void
     */
    protected function get_contains_div_with_class_expectation($class) {
        return new \question_contains_tag_with_attributes(
            'div', ['class' => $class]
        );
    }

    /**
     * Create assertion to check that a <div> with certain class attributes is *not* shown.
     *
     * @param string $class
     * @return void
     */
    protected function get_does_not_contain_div_with_class_expectation($class) {
        return new \question_does_not_contain_tag_with_attributes(
            'div', ['class' => $class]
        );
    }

    /**
     * Check whether there are placeholders for answer boxes, expressions and/or variables left in the HTML output,
     * e. g. {_0} or {x} or {=2*a}.
     *
     * @return void
     */
    protected function check_output_does_not_contain_stray_placeholders() {
        // Keeping the old way for 3.9 until it reaches end-of-life.
        if (version_compare(\PHPUnit\Runner\Version::id(), '9.0.0', '>=')) {
            $this->assertDoesNotMatchRegularExpression('~\{|\}~', $this->currentoutput, 'Not all placehoders were replaced.');
        } else {
            $this->assertNotRegexp('~\{|\}~', $this->currentoutput, 'Not all placehoders were replaced.');
        }
    }
}
