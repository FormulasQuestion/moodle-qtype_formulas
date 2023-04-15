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

class variable {
    const UNDEFINED = 0;
    const BOOLEAN = 0; // This type is not currently used.
    const NUMERIC = token::NUMBER;
    const STRING = token::STRING;
    const LIST = token::LIST;
    const SET = token::SET;
    const MATRIX = 32;
    const ALGEBRAIC = 1024;

    /** @var string the identifier used to refer to this variable */
    public $name;

    /** @var int the variable's data type */
    public $type;

    /** @var mixed the variable's content */
    public $value;

    public function __construct(string $name, $value = null, int $type = self::UNDEFINED) {
        $this->name = $name;
        $this->value = $value;
        $this->type = $type;
    }

    public function __toString() {
        // Arrays are printed in their [...] form.
        if (gettype($this->value) === 'array') {
            return $this->stringify_array($this->value);
        }

        // Sets are printed in their {...} form. Note: Sets are internally stored
        // as arrays, so we process them in the same way and simply replace the leading
        // and final brackets by braces.
        if (gettype($this->value) === 'array') {
            $result = $this->stringify_array($this->value);
            return '{' . substr($result, 1, -1) . '}';
        }

        // For booleans, we cannot use strval() directly, because strval(false) is ''.
        if ($this->type === self::BOOLEAN) {
            return ($this->value ? '1' : '0');
        }

        // For algebraic variables, we just return their name, because they do not
        // have a concrete value.
        if ($this->type === self::ALGEBRAIC) {
            return $this->name;
        }

        // For everything else, we use PHP's string conversion.
        return strval($this->value);
    }

    /**
     * Recursively convert an array to a string.
     *
     * @param array $arr the array to be converted
     */
    private function stringify_array($arr) {
        $result = '[';
        foreach ($arr as $element) {
            if (gettype($element) === 'array') {
                $result .= $this->stringify_array($element);
            } else {
                $result .= strval($element);
            }
            $result .= ', ';
        }
        $result .= ']';
        $result = str_replace(', ]', ']', $result);
        return $result;
    }
}
