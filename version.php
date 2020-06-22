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
 * Version information for the formulas question type.
 *
 * @package    qtype_formulas
 * @copyright  2010 Hon Wai, Lau <lau65536@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$plugin->component = 'qtype_formulas';
$plugin->version   = 2020061900;

$plugin->cron      = 0;
$plugin->requires  = 2015111600;
$plugin->dependencies = array(
    'qbehaviour_adaptive' => 2015111600,
    'qbehaviour_adaptivemultipart'     => 2014092500,
    'qtype_multichoice' => 2015111600,
);
$plugin->release   = '4.91 for Moodle 3.0 to 3.9';

$plugin->maturity  = MATURITY_STABLE;
