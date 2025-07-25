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

use ArrayAccess;
use Countable;
use Exception;
use IteratorAggregate;
use Traversable;

/**
 * range token for qtype_formulas parser
 *
 * @package    qtype_formulas
 * @copyright  2025 Philipp Imhof
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class range implements ArrayAccess, Countable, IteratorAggregate {
    /** @var float start value */
    public float $start;

    /** @var float end value (not included) */
    public float $end;

    /** @var float step */
    public float $step;

    /**
     * Constructor for a new range object. As this class is designed as a helper class to be used inside
     * the Formulas question plugin, error checking (e. g. whether the range is empty or the step is zero)
     * will happen in Formulas' evaluator class where we can give better error messages based on the user's
     * input.
     *
     * @param float $start
     * @param float $end
     * @param float $step
     */
    public function __construct(float $start, float $end, float $step = 1) {
        $this->start = $start;
        $this->end = $end;
        $this->step = $step;
    }

    /**
     * Return the number of elements in this range.
     *
     * @return int
     */
    public function count(): int {
        return ceil(($this->end - $this->start) / $this->step);
    }

    /**
     * Get a certain element of this range. The first element has index 0, the last has
     * $this->count() - 1. We explicitly allow indexing "from the end" by using negative
     * indices, e. g. get_element(-1) will return the last element.
     *
     * @param int $index
     * @return token
     */
    public function get_element(int $index): token {
        if (!$this->is_within_bounds($index)) {
            throw new Exception(get_string('error_indexoutofrange', 'qtype_formulas', $index));
        }

        $index = $this->translate_negative_index($index);
        return token::wrap($this->start + $index * $this->step, token::NUMBER);
    }

    /**
     * Split a range into two sepearate ranges at a certain element. Returns the "left" and "right"
     * partial range. The usage is similar to array_slice(), meaning that the given element will
     * be part of the "right" range.
     *
     * @param int $index
     * @return array
     */
    public function split(int $index): array {
        if (!$this->is_within_bounds($index)) {
            throw new Exception(get_string('error_indexoutofrange', 'qtype_formulas', $index));
        }

        $index = $this->translate_negative_index($index);
        $left = new range($this->start, $this->get_element($index)->value, $this->step);
        $right = new range($this->get_element($index)->value, $this->end, $this->step);
        return [$left, $right];
    }

    /**
     * Check whether a given index is valid for this range.
     *
     * @param int $index
     * @return bool
     */
    private function is_within_bounds(int $index): bool {
        return $index >= -$this->count() && $index < $this->count();
    }

    /**
     * Convert a negative index (access from the end of the range) to a
     * regular index.
     *
     * @param int $index
     * @return int
     */
    private function translate_negative_index(int $index): int {
        if ($index < 0) {
            $index = $index + $this->count();
        }

        return $index;
    }

    #[\Override]
    public function getIterator(): Traversable {
        for ($i = 0; $i < $this->count(); $i++) {
            yield token::wrap($this->get_element($i), token::NUMBER);
        }
    }

    /**
     * Check whether a given index is valid.
     *
     * @param mixed $offset
     * @return boolean
     */
    public function offsetExists($offset): bool {
        return $offset >= 0 && $offset < $this->count();
    }

    /**
     * Get the number at a given index.
     *
     * @param int $offset
     * @return mixed
     */
    #[\ReturnTypeWillChange]
    public function offsetGet($offset) {
        return $this->get_element($offset);
    }

    /**
     * Mandatory override of method from ArrayAccess interface. We do not currently support setting
     * individual elements of a range.
     *
     * @param int $offset
     * @param mixed $value
     * @return void
     */
    public function offsetSet($offset, $value): void {
        throw new Exception('offsetSet() not implemented for range class');
    }

    /**
     * Mandatory override of method from ArrayAccess interface. We do not currently support removing
     * individual elements from a lazylist.
     *
     * @param int $offset
     * @return void
     */
    public function offsetUnset($offset): void {
        throw new Exception('offsetUnset() not implemented for range class');
    }
}
