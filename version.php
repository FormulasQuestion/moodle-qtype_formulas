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
 * Version information for the formulas question type.
 *
 * @package    qtype_formulas
 * @copyright  2010 Hon Wai, Lau <lau65536@gmail.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$plugin->component = 'qtype_formulas';
$plugin->version = 2025082000;

$plugin->cron = 0;
$plugin->requires = 2022112800;
$plugin->dependencies = [
    'qbehaviour_adaptive' => 2015111600,
    'qbehaviour_adaptivemultipart' => 2014092500,
    'qtype_multichoice' => 2015111600,
    'filter_mathjaxloader' => 2022112800,
];
$plugin->supported = [401, 500];
$plugin->release = '6.1.2';

$plugin->maturity = MATURITY_STABLE;
