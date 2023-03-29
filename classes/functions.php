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
 * Additional functions qtype_formulas
 *
 * @package    qtype_formulas
 * @copyright  2022 Philipp Imhof
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace qtype_formulas;

class functions {
    /* function name => [min params, max params] */
    const FUNCTIONS = [
        'fact' => [1, 1],
    ];

    /**
     * Calculate the factorial n! of a number.
     *
     * @param integer $n the number
     * @return integer
     */
    public static function fact($n) {
        $n = (int) $n;
        if ($n < 2) {
            return 1;
        }
        $result = 1;
        for ($i = $n; $i > 1; $i--) {
            $result *= $i;
        }
        return $result;
    }
}
