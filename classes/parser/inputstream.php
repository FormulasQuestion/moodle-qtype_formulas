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
 * Lexer for qtype_formulas
 *
 * @package    qtype_formulas
 * @copyright  2022 Philipp Imhof
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace qtype_formulas;

/**
 * Helper class to go through the input.
 */
class InputStream {
    const EOF = '';

    /** @var integer current position in the input */
    private $position = -1;

    /** @var integer the row (line number) of the current character */
    private $row = 1;

    /** @var integer the column number of the current character */
    private $column = 0;

    /** @var integer the length of the input */
    private $length = 0;

    /** @var array array containing the input's individual characters */
    private $input = [];

    /**
     * Constructor
     *
     * @param string $str the raw input
     */
    public function __construct($str) {
        // Input can contain unicode characters, so it's better to operate on an array of characters
        // rather than a string.
        $this->input = mb_str_split($str);
        $this->length = count($this->input);
    }

    /**
     * Return the next character of the stream, without consuming it. The optional
     * parameter allows to retrieve characters farther behind, if they exist.
     *
     * @param integer $skip skip a certain number of characters
     * @return string
     */
    public function peek($skip = 0) {
        if ($this->position < $this->length - $skip - 1) {
            return $this->input[$this->position + $skip + 1];
        }
        return self::EOF;
    }

    /**
     * Return the next character of the stream and move the position index one step forward.
     *
     * @return string
     */
    public function read() {
        $nextchar = $this->peek();
        if ($nextchar !== self::EOF) {
            $this->advance($nextchar === "\n");
        }
        return $nextchar;
    }

    /**
     * Advance the position index by one and keep row/column numbers in sync.
     *
     * @param boolean $newline
     * @return void
     */
    private function advance($newline) {
        $this->position++;
        $this->column++;
        if ($newline) {
            $this->row++;
            $this->column = 0;
        }
    }

    /**
     * Stop processing the input and indicate the human readable position (row/column) where the error occurred.
     *
     * @param string $message error message
     * @return void
     * @throws Exception
     */
    public function die($message) {
        throw new \Exception($this->row . ':' . $this->column . ':' . $message);
    }

    /**
     * Return the human readable position (row/column) of the current character in the input stream
     *
     * @return array position, associative array with keys 'row' and 'column'
     */
    public function get_position() {
        return ['row' => $this->row, 'column' => $this->column];
    }
}


