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

namespace qtype_formulas\local;

use Exception;
use qtype_formulas\local\lazylist;

/**
 * Parser for qtype_formulas
 *
 * @package    qtype_formulas
 * @copyright  2022 Philipp Imhof
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class random_variable extends variable {
    /** @var string the identifier used to refer to this variable */
    public string $name;

    /** @var lazylist|array the set of possible values to choose from */
    public $reservoir;

    /** @var int the variable's data type */
    public int $type;

    /** @var mixed the variable's content */
    public $value = null;

    /** @var bool if the variable is a shuffled array */
    private bool $shuffle;

    /**
     * Constructor.
     *
     * @param string $name identifier used to refer to this variable
     * @param lazylist|array $reservoir set of possible values to choose from
     * @param bool $useshuffle whether the variable is a shuffled array
     * @param int $seed the seed for the PRNG
     */
    public function __construct(string $name, $reservoir, bool $useshuffle, int $seed = 1) {
        $this->name = $name;
        $this->shuffle = $useshuffle;
        $this->reservoir = $reservoir;
    }

    /**
     * Instantiate the random variable, e. g. assigning one of the possible values to it.
     *
     * @return token assigned value
     */
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

    /**
     * Calculate the number of possible values for this random variable.
     *
     * @return int
     */
    public function how_many(): int {
        if ($this->shuffle) {
            try {
                $result = functions::fact(count($this->reservoir));
            } catch (Exception $e) {
                // TODO: non-capturing catch.
                return PHP_INT_MAX;
            }
            return $result;
        }
        return count($this->reservoir);
    }

    /**
     * Return a string that can be used to set the variable to its instantiated value.
     * This is how Formulas question prior to version 6.x used to store their state.
     * We implement this for maximum backwards compatibility, i. e. in order to allow
     * switching back to a 5.x version. We continue to use the old format, i. e.
     * <variablename> = <instantiated-value>; for every random variable.
     *
     * @return string
     */
    public function get_instantiated_definition(): string {
        if ($this->value === null) {
            return '';
        }
        $definition = $this->name . '=';

        // If the value is a string, we wrap it in quotes. Also, we remove all existing quotes inside
        // the string, because in Formulas question < 6.0, it was not possible to have escaped quotes
        // in a string.
        if (is_string($this->value)) {
            return $definition . '"' . preg_replace('/(\\\\)?["\']/', '', $this->value) . '";';
        }

        // If the value is a number, we return it as it is. We do this after the string, because numeric
        // strings should be returned as strings, not numbers.
        if (is_numeric($this->value)) {
            return $definition . $this->value . ';';
        }

        // If we are still here, the value is an array. We iterate over all entries and, if necessary,
        // wrap them in quotes. Note that these individual values are all tokens.
        $values = [];
        foreach ($this->value as $valuetoken) {
            if (is_string($valuetoken->value)) {
                $values[] = '"' . preg_replace('/(\\\\)?["\']/', '', $valuetoken->value) . '"';
                continue;
            }
            if (is_numeric($valuetoken->value)) {
                $values[] = $valuetoken->value;
                continue;
            }
            // If we are still here, the element is itself an array (a list). Nested lists are not
            // allowed in legacy versions of Formulas question, so we simply drop the value.
        }

        return $definition . '[' . implode(',', $values) . '];';
    }
}
