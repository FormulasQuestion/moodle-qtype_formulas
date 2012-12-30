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

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/question/engine/tests/helpers.php');


/**
 * Base class for formulas unit tests.
 *
 * @copyright  2012 Jean-Michel Védrine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class qtype_formulas_testcase extends advanced_testcase {

}


/**
 * Base class for formulas walkthrough tests.
 *
 * Provides some additional asserts.
 *
 * @copyright 2012 Jean-Michel Védrine
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class qtype_formulas_walkthrough_test_base extends qbehaviour_walkthrough_test_base {
    protected $currentoutput = null;

    protected function render() {
        $this->currentoutput = $this->quba->render_question($this->slot, $this->displayoptions);
    }

    protected function get_tag_matcher($tag, $attributes) {
        return array(
            'tag' => $tag,
            'attributes' => $attributes,
        );
    }

    protected function check_output_contains_text_input($name, $value = null, $enabled = true) {
        $attributes = array(
            'type' => 'text',
            'name' => $this->quba->get_field_prefix($this->slot) . $name,
        );
        if (!is_null($value)) {
            $attributes['value'] = $value;
        }
        if (!$enabled) {
            $attributes['readonly'] = 'readonly';
        }
        $matcher = $this->get_tag_matcher('input', $attributes);
        $this->assertTag($matcher, $this->currentoutput,
                'Looking for an input with attributes ' . html_writer::attributes($attributes) . ' in ' . $this->currentoutput);

        if ($enabled) {
            $matcher['attributes']['readonly'] = 'readonly';
            $this->assertNotTag($matcher, $this->currentoutput,
                    'input with attributes ' . html_writer::attributes($attributes) .
                    ' should not be read-only in ' . $this->currentoutput);
        }
    }

    protected function check_output_contains_part_feedback($name = null) {
        $class = 'formulaspartfeedback';
        if ($name) {
            $class .= ' formulaspartfeedback-' . $name;
        }
        $this->assertTag(array('tag' => 'div', 'attributes' => array('class' => $class)), $this->currentoutput,
                'part feedback for ' . $name . ' not found in ' . $this->currentoutput);
    }

    protected function check_output_does_not_contain_part_feedback($name = null) {
        $class = 'formulaspartfeedback';
        if ($name) {
            $class .= ' formulaspartfeedback-' . $name;
        }
        $this->assertNotTag(array('tag' => 'div', 'attributes' => array('class' => $class)), $this->currentoutput,
                'part feedback for ' . $name . ' should not be present in ' . $this->currentoutput);
    }

    protected function check_output_does_not_contain_stray_placeholders() {
        $this->assertNotRegExp('~\[\[|\]\]~', $this->currentoutput, 'Not all placehoders were replaced.');
    }

    protected function check_output_contains_lang_string($identifier, $component = '', $a = null) {
        $string = get_string($identifier, $component, $a);
        $this->assertNotContains($string, $this->currentoutput,
                'Expected string ' . $string . ' not found in ' . $this->currentoutput);
    }

    protected function check_output_does_not_contain_lang_string($identifier, $component = '', $a = null) {
        $string = get_string($identifier, $component, $a);
        $this->assertContains($string, $this->currentoutput,
                'The string ' . $string . ' should not be present in ' . $this->currentoutput);
    }
}
