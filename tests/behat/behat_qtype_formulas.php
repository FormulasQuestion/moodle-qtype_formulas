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

require_once(__DIR__ . '/../../../../../lib/behat/behat_base.php');

/**
 * Behat qtype_formulas related steps definitions.
 *
 * @package    qtype_formulas
 * @category   test
 * @copyright  2022 Philipp Imhof
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class behat_qtype_formulas extends behat_base {
    /**
     * Return the list of exact named selectors.
     *
     * @return array
     */
    public static function get_exact_named_selectors(): array {
        return [
            new behat_component_named_selector(
                'Formulas field with warning',
                ["//input[contains(@class, 'formulas')][(contains(@class, 'is-invalid'))]"],
            ),
            new behat_component_named_selector(
                'Postunit field with warning',
                ["//input[contains(@id, 'postunit')][(contains(@class, 'is-invalid'))]"],
            ),
            new behat_component_named_selector(
                'MathJax display',
                ["//div[@id='qtype_formulas_mathjax_display']"],
            ),
        ];
    }

    /**
     * Click a row in the instantiation table to select the given set of values.
     *
     * @When /^I click on row number "(?P<rownumber>\d+)" of the Formulas Question instantiation table$/
     *
     * @param int $rownumber which row
     */
    public function i_click_on_row_number_of_the_formulas_question_instantiation_table(int $rownumber): void {
        $xpath = "//div[contains(@class, 'tabulator-row')][not(contains(@class, 'tabulator-calc'))][$rownumber]";
        $this->execute("behat_general::i_click_on", [$this->escape($xpath), "xpath_element"]);
    }

    // phpcs:disable moodle.Files.LineLength.TooLong
    /**
     * Check values inside the instantiation table.
     *
     * phpcs:ignore moodle.Files.LineLength.TooLong
     * @Given /^I should see "(?P<text>[^"]*)" in the "(?P<field>[^"]*)" field of row number "(?P<rownumber>\d+)" of the Formulas Question instantiation table$/
     *
     * @param string $text the text to look for
     * @param string $field the field name
     * @param int $rownumber which row
     */
    public function i_should_see_in_the_field_of_row_of_the_formulas_question_instatiation_table(string $text, string $field, int $rownumber): void {
        // phpcs:enable
        $field = behat_context_helper::escape($field);

        $xpath = "//div[contains(@class, 'tabulator-row')][not(contains(@class, 'tabulator-calc'))][$rownumber]"
            . "/div[contains(@class, 'tabulator-cell')][@tabulator-field=$field]";

        $this->execute("behat_general::assert_element_contains_text", [$text, $xpath, "xpath_element"]);
    }

    /**
     * Confirm the quiz should be submitted.
     *
     * @Given /^I confirm the quiz submission in the modal dialog for the formulas plugin$/
     */
    public function i_confirm_the_quiz_submission_in_the_modal_dialog_for_the_formulas_plugin() {
        global $CFG;
        require_once($CFG->libdir . '/environmentlib.php');
        require($CFG->dirroot . '/version.php');
        $currentversion = normalize_version($release);
        if (version_compare($currentversion, '4.1', ">=")) {
            $xpath = "//div[contains(@class, 'modal-dialog')]/*/*/button[contains(@class, 'btn-primary')]";
        } else if (version_compare($currentversion, '3.9', ">=")) {
            $xpath = "//div[contains(@class, 'confirmation-dialogue')]/*/input[contains(@class, 'btn-primary')]";
        }
        $this->execute("behat_general::i_click_on", [$this->escape($xpath), "xpath_element"]);
    }
}
