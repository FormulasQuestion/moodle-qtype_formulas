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

namespace qtype_formulas\local;
use Exception;

class random_variable extends variable {
    /** @var string the identifier used to refer to this variable */
    public string $name;

    /** @var array the set of possible values to choose from */
    public array $reservoir = [];

    /** @var int the variable's data type */
    public int $type;

    /** @var mixed the variable's content */
    public $value = null;

    /** @var bool if the variable is a shuffled array */
    private bool $shuffle;

    public function __construct(string $name, array $reservoir, bool $useshuffle, int $seed = 1) {
        $this->name = $name;
        $this->shuffle = $useshuffle;
        $this->reservoir = $reservoir;
    }

    public function instantiate(): token {
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
        return token::wrap($this->value, $this->type);
    }

    public function how_many(): int {
        if ($this->shuffle) {
            try {
                $result = functions::fact(count($this->reservoir));
            } catch (Exception $e) {
                // TODO: non-capturing catch
                return PHP_INT_MAX;
            }
            return $result;
        }
        return count($this->reservoir);
    }
}
