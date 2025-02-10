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

namespace qtype_formulas\local;

/**
 * for loop for qtype_formulas parser
 *
 * @package    qtype_formulas
 * @copyright  2022 Philipp Imhof
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class for_loop {
    /** @var token variable token for the loop's iterator variable */
    public token $variable;

    /** @var expression values the loop will iterate over */
    public expression $range;

    /** @var array statements of the loop */
    public array $body = [];

    /**
     * Constructor
     *
     * @param token $var variable token for the loop's iterator variable
     * @param expression $range expression values the loop will iterate over
     * @param array $body list of statements forming the loop's body
     */
    public function __construct(token $var, expression $range, array $body) {
        $this->variable = $var;
        $this->range = $range;
        $this->body = $body;
    }
}
