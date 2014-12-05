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
 * Upgrade library code for the formulas question type.
 *
 * @package    qtype_formulas
 * @copyright  2013 Jean-Michel Vedrine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Class for converting attempt data for formulas questions when upgrading
 * attempts to the new question engine.
 *
 * This class is used by the code in question/engine/upgrade/upgradelib.php.
 *
 * @copyright  2013 Jean-Michel Vedrine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class qtype_formulas_qe2_attempt_updater extends question_qtype_attempt_updater {
    public function right_answer() {
        return '';
    }

    public function question_summary() {
        // Done later when we know random variables.
        return '';
    }

    public function was_answered($state) {
        $parsedanswer = $this->parse_answer($state->answer);
        $allresponses = implode('', $parsedanswer['responses']);
        return !empty($allresponses);
    }

    public function response_summary($state) {
        $summary = array();
        $parsedanswer = $this->parse_answer($state->answer);
        $responses = $parsedanswer['responses'];
        foreach ($this->question->options->answers as $part) {
            if ($this->has_combined_unit_field($part)) {
                $summary [] = $responses[$part->partindex . '_0'] . $responses[$part->partindex . '_1'];;
            } else {
                foreach (range(0, $part->numbox - 1) as $j) {
                    $summary [] = $responses[$part->partindex . "_$j"];
                }
                if ($this->has_separate_unit_field($part)) {
                    $summary [] = $responses[$part->partindex . '_' . $part->numbox];
                }
            }
        }
        $summary = implode(', ', $summary);
        return $summary;
    }

    public function set_first_step_data_elements($state, &$data) {
        $parsedanswer = $this->parse_answer($state->answer);
        $data['_randomsvars_text'] = $parsedanswer['randomvars'];
        $data['_varsglobal'] = $this->question->options->varsglobal;

        // Now that we know random and global variables, we can do our best for question summary.
        $summary = $this->to_text($this->question->questiontext, $this->question->questiontextformat);
        foreach ($this->question->options->answers as $part) {
            $answerbit = $this->to_text($part->subqtext, $part->subqtextformat);
            if ($part->placeholder != '') {
                $summary = str_replace('{' . $part->placeholder . '}', $answerbit, $summary);
            } else {
                $summary .= $answerbit;
            }
        }
        if ($parsedanswer['randomvars']) {
            $summary .= ' ('. $parsedanswer['randomvars'] .')';
        }
        $this->updater->qa->questionsummary = $summary;
    }

    public function supply_missing_first_step_data(&$data) {
        // There is nothing we can do now for global variables,
        // but we will try to fix it later.
        $data['_randomsvars_text'] = 'Missing';
        $data['_varsglobal'] = $this->question->options->varsglobal;

    }

    public function set_data_elements_for_step($state, &$data) {
        $parsedanswer = $this->parse_answer($state->answer);
        $responses = $parsedanswer['responses'];
        foreach ($this->question->options->answers as $part) {
            if ($this->has_combined_unit_field($part)) {
                // If part has a combined unit field we need to group answer and unit.
                $data[$part->partindex . '_'] = $responses[$part->partindex . '_0'] . $responses[$part->partindex . '_1'];
            } else {
                foreach (range(0, $part->numbox - 1) as $j) {
                    // All coordinates of response for part.
                    $data[$part->partindex . "_$j"] = $responses[$part->partindex . "_$j"];
                }
                if ($this->has_separate_unit_field($part)) {
                    // If there is a separated unit field
                    // Last response is unit.
                    $data[$part->partindex . '_' . $part->numbox] = $responses[$part->partindex . '_' . $part->numbox];
                }

            }
        }
        // If first step was missing we try to fix it now.
        if ($this->updater->qa->steps[0]->data['_randomsvars_text'] == 'Missing' && $parsedanswer['randomvars'] != '') {
            $this->updater->qa->steps[0]->data['_randomsvars_text'] = $parsedanswer['randomvars'];
        }
    }

    protected function parse_answer($answer) {
        $data = array();
        $lines = explode("\n", $answer);
        $counter = 0;
        $details = array();
        while (strlen(trim($lines[$counter])) != 0) { // Read lines until an empty one is encountered.
            $pair = explode('=', $lines[$counter], 2);
            if (count($pair) == 2) {
                $details[trim($pair[0])] = $pair[1];  // Skip lines without "=", if any.
            }
            $counter++;
        }
        $grading = array();
        $responses = array();
        foreach ($this->question->options->answers as $i => $part) {
            $grading[$i] = array_key_exists($i, $details) ? explode(',', $details[$i]) : array(0, 0, 0, 0);
            foreach (range(0, $part->numbox) as $j) {
                $responses["${i}_$j"] = array_key_exists("${i}_$j", $details) ? $details["${i}_$j"] : '';
            }
        }
        $subanum = intval($lines[$counter + 1]);
        $randomvars = implode("", array_slice($lines, $counter + 3));
        return array('responses' => $responses, 'subanum' => $subanum, 'grading' => $grading, 'randomvars' => trim($randomvars));

    }

    // Copied and adapted from question definition.
    public function has_separate_unit_field($part) {
        return strlen($part->postunit) != 0 && $this->has_combined_unit_field($part) == false;
    }

    // Copied and adapted from question definition.
    public function has_combined_unit_field($part) {
        return strlen($part->postunit) != 0 && $part->numbox == 1 && $part->answertype != 1000
                && (strpos($part->subqtext, "{_0}{_u}") !== false
                || (strpos($part->subqtext, "{_0}") === false && strpos($part->subqtext, "{_u}") === false));
    }
}
