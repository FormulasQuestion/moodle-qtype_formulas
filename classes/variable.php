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
    const NUMERIC = token::NUMBER;
    const STRING = token::STRING;
    const LIST = token::LIST;
    const SET = token::SET;
    const MATRIX = 32;
    const ALGEBRAIC = 1024;

    /** @var string the identifier used to refer to this variable */
    public string $name;

    /** @var int the variable's data type */
    public int $type;

    /** @var mixed the variable's content */
    public $value;

    /** @var float microtime() timestamp of last update */
    public float $timestamp;

    public function __construct(string $name, $value = null, int $type = self::UNDEFINED, ?float $timestamp = null) {
        $this->name = $name;
        $this->value = $value;
        $this->type = $type;
        if (!isset($timestamp)) {
            $timestamp = microtime(true);
        }
        $this->timestamp = $timestamp;
    }

    public function __toString() {
        // For algebraic variables, we just return their name, because they do not
        // have a concrete value.
        if ($this->type === self::ALGEBRAIC) {
            return $this->name;
        }

        // Arrays are printed in their [...] form, sets are printed as {...}.
        if (gettype($this->value) === 'array') {
            $result = token::stringify_array($this->value);
            if ($this->type === self::SET) {
                return '{' . substr($result, 1, -1) . '}';
            }
            return $result;
        }

        // For everything else, we use PHP's string conversion.
        return strval($this->value);
    }

}
