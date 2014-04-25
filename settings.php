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
 * @package    qtype_formulas
 * @copyright  2013 Jean-Michel Vedrine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

if ($ADMIN->fulltree) {
    // Use tooltip or not to display correct answer.
    $settings->add(new admin_setting_configcheckbox('qtype_formulas/usepopup',
            new lang_string('settingusepopup', 'qtype_formulas'),
            new lang_string('settingusepopup_desc', 'qtype_formulas'), 0));
    // Default answer type.
    $settings->add(new admin_setting_configselect('qtype_formulas/defaultanswertype',
            new lang_string('defaultanswertype', 'qtype_formulas'),
            new lang_string('defaultanswertype_desc', 'qtype_formulas'), 0,
            array(0 => new lang_string('number', 'qtype_formulas'),
                    10 => new lang_string('numeric', 'qtype_formulas'),
                        100 => new lang_string('numerical_formula', 'qtype_formulas'),
                        1000 => new lang_string('algebraic_formula', 'qtype_formulas'))));
    // Default correctness.
    $settings->add(new admin_setting_configtext('qtype_formulas/defaultcorrectness',
        get_string('defaultcorrectness', 'qtype_formulas'),
        get_string('defaultcorrectness_desc', 'qtype_formulas'), '_relerr < 0.01'));
    // Default answermark.
    $settings->add(new admin_setting_configtext('qtype_formulas/defaultanswermark',
        get_string('defaultanswermark', 'qtype_formulas'),
        get_string('defaultanswermark_desc', 'qtype_formulas'), 1));
    // Default unit penalty.
    $settings->add(new admin_setting_configtext('qtype_formulas/defaultunitpenalty',
        get_string('defaultunitpenalty', 'qtype_formulas'),
        get_string('defaultunitpenalty_desc', 'qtype_formulas'), 1));
}
