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
 * External functions and service definitions for the Formulas question type plugin.
 *
 * @package    qtype_formulas
 * @copyright  2023 Philipp Imhof
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = [
    'qtype_formulas_instantiate' => [
            'classname' => 'qtype_formulas\external\instantiation',
            'methodname' => 'instantiate',
            'description' => 'Instantiate a Formulas question based on the values provided in the request',
            'type' => 'read',
            'ajax' => true,
            'capabilities' => '',
            'services' => [],
    ],
    'qtype_formulas_check_random_global_vars' => [
        'classname' => 'qtype_formulas\external\instantiation',
        'methodname' => 'check_random_global_vars',
        'description' => 'Validate definition of random and/or global variables',
        'type' => 'read',
        'ajax' => true,
        'capabilities' => '',
        'services' => [],
    ],
    'qtype_formulas_check_local_vars' => [
        'classname' => 'qtype_formulas\external\instantiation',
        'methodname' => 'check_local_vars',
        'description' => 'Validate definition of a part\'s local variables',
        'type' => 'read',
        'ajax' => true,
        'capabilities' => '',
        'services' => [],
    ],
    'qtype_formulas_render_question_text' => [
        'classname' => 'qtype_formulas\external\instantiation',
        'methodname' => 'render_question_text',
        'description' => 'Substitute variables inside the question and/or part by their values',
        'type' => 'read',
        'ajax' => true,
        'capabilities' => '',
        'services' => [],
    ],
];
