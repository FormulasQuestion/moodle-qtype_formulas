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

defined('MOODLE_INTERNAL') || die();

/**
 * Coverage information for the qtype_formulas
 *
 * @package    qtype_formulas
 * @copyright  2023 Philipp Imhof
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
return new class extends phpunit_coverage_info {
    /** @var array list of folders to include in coverage generation. */
    protected $includelistfolders = ['.'];

    /** @var array list of files to include in coverage generation. */
    protected $includelistfiles = [];

    /** @var array list of folders to exclude from coverage generation. */
    protected $excludelistfolders = [
        'tests',
        'lang'
    ];

    /** @var array list of files to exclude from coverage generation. */
    protected $excludelistfiles = [
        'classes/output/mobile.php',
        'classes/privacy/provider.php',
        'db/mobile.php',
        'db/services.php',
        'db/upgrade.php',
        'lib.php',
        'settings.php',
        'version.php',
    ];
};
