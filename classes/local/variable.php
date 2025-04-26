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

/**
 * Parser for qtype_formulas
 *
 * @package    qtype_formulas
 * @copyright  2022 Philipp Imhof
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class variable {
    /** @var int used to designate a variable of unknown type*/
    const UNDEFINED = 0;

    /** @var int used to designate a variable storing a number */
    const NUMERIC = token::NUMBER;

    /** @var int used to designate a variable storing a string literal */
    const STRING = token::STRING;

    /** @var int used to designate a variable storing a list */
    const LIST = token::LIST;

    /** @var int used to designate a variable storing a set */
    const SET = token::SET;

    /** @var int used to designate a variable storing a matrix (not yet implemented) */
    const MATRIX = 32;

    /** @var int used to designate an algebraic variable */
    const ALGEBRAIC = 1024;

    /** @var string the identifier used to refer to this variable */
    public string $name;

    /** @var int the variable's data type */
    public int $type;

    /** @var mixed the variable's content */
    public $value;

    /** @var float microtime() timestamp of last update */
    public float $timestamp;

    /**
     * Constructor. If no timestamp is given, the current time (to microseconds) will be used.
     *
     * @param string $name identifier used to refer to this variable
     * @param mixed $value the variable's content
     * @param int $type int the variable's data type, use pre-defined constants
     * @param float|null $timestamp timestamp of last update
     */
    public function __construct(string $name, $value = null, int $type = self::UNDEFINED, ?float $timestamp = null) {
        $this->name = $name;
        $this->value = $value;
        $this->type = $type;
        if (!isset($timestamp)) {
            $timestamp = microtime(true);
        }
        $this->timestamp = $timestamp;
    }

    /**
     * Convert variable to string.
     *
     * @return string
     */
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
