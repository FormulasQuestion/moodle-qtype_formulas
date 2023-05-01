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
 * Parser for qtype_formulas
 *
 * @package    qtype_formulas
 * @copyright  2022 Philipp Imhof
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace qtype_formulas;
use Exception;

class random_variable extends variable {
    /** @var string the identifier used to refer to this variable */
    public $name;

    /** @var array the set of possible values to choose from */
    private $reservoir = [];

    /** @var int the variable's data type */
    public $type;

    /** @var mixed the variable's content */
    public $value;

    /** @var bool if the variable is a shuffled array */
    private $shuffle;

    public function __construct(string $name, array $reservoir, bool $useshuffle) {
        $this->name = $name;
        $this->shuffle = $useshuffle;
        $this->reservoir = $reservoir;

        // Instantiate the newly created variable.
        $this->instantiate();
    }

    public function instantiate() {
        // We have two types of random variables. One is a list that will be shuffled.
        // The other is a set where we pick one random element.
        if ($this->shuffle) {
            $this->type = variable::LIST;
            $this->value = $this->reservoir;
            shuffle($this->value);
        } else {
            $i = mt_rand(0, count($this->reservoir) - 1);
            $this->value = $this->reservoir[$i]->value;
            $this->type = $this->reservoir[$i]->type;
        }
    }
}
