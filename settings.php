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
 * Settings for the qtype_formulas plugin
 *
 * @package    qtype_formulas
 * @copyright  2013 Jean-Michel Vedrine
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

if ($ADMIN->fulltree) {

    $settings->add(new admin_setting_heading(
        'qtype_formulas/settings',
        new lang_string('settings_heading_general', 'qtype_formulas'),
        new lang_string('settings_heading_general_desc', 'qtype_formulas'),
    ));

    // Whether students are allowed to use the comma as decimal separator.
    $settings->add(new admin_setting_configcheckbox(
        'qtype_formulas/allowdecimalcomma',
        new lang_string('settingallowdecimalcomma', 'qtype_formulas'),
        new lang_string('settingallowdecimalcomma_desc', 'qtype_formulas'),
        0
    ));

    // Whether we should omit the check the model answer's correctness during imports.
    $settings->add(new admin_setting_configcheckbox(
        'qtype_formulas/lenientimport',
        new lang_string('settinglenientimport', 'qtype_formulas'),
        new lang_string('settinglenientimport_desc', 'qtype_formulas'),
        0
    ));

    // Default answer type.
    $settings->add(new admin_setting_configselect(
        'qtype_formulas/defaultanswertype',
        new lang_string('defaultanswertype', 'qtype_formulas'),
        new lang_string('defaultanswertype_desc', 'qtype_formulas'),
        0,
        [
            0 => new lang_string('number', 'qtype_formulas'),
            10 => new lang_string('numeric', 'qtype_formulas'),
            100 => new lang_string('numerical_formula', 'qtype_formulas'),
            1000 => new lang_string('algebraic_formula', 'qtype_formulas'),
        ]
    ));
    // Default correctness.
    $settings->add(new admin_setting_configtext(
        'qtype_formulas/defaultcorrectness',
        new lang_string('defaultcorrectness', 'qtype_formulas'),
        new lang_string('defaultcorrectness_desc', 'qtype_formulas'),
        '_relerr < 0.01'
    ));
    // Default answermark.
    $settings->add(new admin_setting_configtext(
        'qtype_formulas/defaultanswermark',
        new lang_string('defaultanswermark', 'qtype_formulas'),
        new lang_string('defaultanswermark_desc', 'qtype_formulas'),
        1,
        PARAM_FLOAT,
        4
    ));
    // Default unit penalty.
    $settings->add(new admin_setting_configtext(
        'qtype_formulas/defaultunitpenalty',
        new lang_string('defaultunitpenalty', 'qtype_formulas'),
        new lang_string('defaultunitpenalty_desc', 'qtype_formulas'),
        1,
        PARAM_FLOAT,
        4
    ));

    $settings->add(new admin_setting_heading(
        'qtype_formulas/defaultwidths',
        new lang_string('settings_heading_width', 'qtype_formulas'),
        new lang_string('settings_heading_width_desc', 'qtype_formulas'),
    ));

    // Unit for default widths.
    $settings->add(new admin_setting_configselect(
        'qtype_formulas/defaultwidthunit',
        new lang_string('defaultwidthunit', 'qtype_formulas'),
        new lang_string('defaultwidthunit_desc', 'qtype_formulas'),
        'px',
        ['px' => 'px', 'em' => 'em', 'rem' => 'rem'],
    ));

    // Default width for answer type "Number".
    $settings->add(new admin_setting_configtext(
        'qtype_formulas/defaultwidth_number',
        new lang_string('defaultwidth_number', 'qtype_formulas'),
        new lang_string('defaultwidth_number_desc', 'qtype_formulas'),
        55,
        PARAM_FLOAT,
        6
    ));

    // Default width for combined answer field of type "Number".
    $settings->add(new admin_setting_configtext(
        'qtype_formulas/defaultwidth_number_unit',
        new lang_string('defaultwidth_number_unit', 'qtype_formulas'),
        new lang_string('defaultwidth_number_unit_desc', 'qtype_formulas'),
        80,
        PARAM_FLOAT,
        6
    ));

    // Default width for answer type "Numeric".
    $settings->add(new admin_setting_configtext(
        'qtype_formulas/defaultwidth_numeric',
        new lang_string('defaultwidth_numeric', 'qtype_formulas'),
        new lang_string('defaultwidth_numeric_desc', 'qtype_formulas'),
        100,
        PARAM_FLOAT,
        6
    ));

    // Default width for combined answer field of type "Numeric".
    $settings->add(new admin_setting_configtext(
        'qtype_formulas/defaultwidth_numeric_unit',
        new lang_string('defaultwidth_numeric_unit', 'qtype_formulas'),
        new lang_string('defaultwidth_numeric_unit_desc', 'qtype_formulas'),
        200,
        PARAM_FLOAT,
        6
    ));

    // Default width for answer type "Numerical formula".
    $settings->add(new admin_setting_configtext(
        'qtype_formulas/defaultwidth_numerical_formula',
        new lang_string('defaultwidth_numerical_formula', 'qtype_formulas'),
        new lang_string('defaultwidth_numerical_formula_desc', 'qtype_formulas'),
        200,
        PARAM_FLOAT,
        6
    ));

    // Default width for combined answer field of type "Numerical formula".
    $settings->add(new admin_setting_configtext(
        'qtype_formulas/defaultwidth_numerical_formula_unit',
        new lang_string('defaultwidth_numerical_formula_unit', 'qtype_formulas'),
        new lang_string('defaultwidth_numerical_formula_unit_desc', 'qtype_formulas'),
        300,
        PARAM_FLOAT,
        6
    ));

    // Default width for answer type "Algebraic formula".
    $settings->add(new admin_setting_configtext(
        'qtype_formulas/defaultwidth_algebraic_formula',
        new lang_string('defaultwidth_algebraic_formula', 'qtype_formulas'),
        new lang_string('defaultwidth_algebraic_formula_desc', 'qtype_formulas'),
        200,
        PARAM_FLOAT,
        6
    ));

    // Default width for separate unit field; not to be confused with the unit for the width (px, em, rem).
    $settings->add(new admin_setting_configtext(
        'qtype_formulas/defaultwidth_unit',
        new lang_string('defaultwidth_unit', 'qtype_formulas'),
        new lang_string('defaultwidth_unit_desc', 'qtype_formulas'),
        55,
        PARAM_FLOAT,
        6
    ));
}
