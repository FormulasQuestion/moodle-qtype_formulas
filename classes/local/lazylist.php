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

use function Symfony\Component\DependencyInjection\Loader\Configurator\iterator;

/**
 * lazy list class for qtype_formulas parser
 *
 * @package    qtype_formulas
 * @copyright  2025 Philipp Imhof
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class lazylist implements ArrayAccess, Countable, IteratorAggregate {
    /** @var array $elements elements of the lazy array */
    private array $elements = [];

    /** @var ?int $cachedcount cache storing the number of elements or null if not up-to-date */
    private ?int $cachedcount = null;

    /**
     * Append a single value (as a token) to the list.
     *
     * @param token $value the value token
     * @return void
     */
    public function append_value(token $value): void {
        $this->elements[] = ['type' => 'value', 'value' => $value, 'count' => 1];
        $this->cachedcount = null;
    }

    /**
     * Add a single value (as a token) to the beginning of the list.
     *
     * @param token $value the value token
     * @return void
     */
    public function prepend_value(token $value): void {
        array_unshift($this->elements, ['type' => 'value', 'value' => $value, 'count' => 1]);
        $this->cachedcount = null;
    }

    /**
     * Append a range of numbers to the list.
     *
     * @param range $range the range of numbers
     * @return void
     */
    public function append_range(range $range): void {
        $count = $range->count();
        if ($count > 0) {
            $this->elements[] = ['type' => 'range', 'value' => $range, 'count' => $count];
            $this->cachedcount = null;
        }
    }

    /**
     * Add a range of numbers to the beginning of the list.
     *
     * @param range $range
     * @return void
     */
    public function prepend_range(range $range): void {
        $count = $range->count();
        if ($count > 0) {
            array_unshift($this->elements, ['type' => 'range', 'value' => $range, 'count' => $count]);
            $this->cachedcount = null;
        }
    }

    /**
     * Create the iterator to walk through the lazylist in a foreach loop.
     *
     * @return Traversable
     */
    public function getIterator(): Traversable {
        foreach ($this->elements as $element) {
            if ($element['type'] === 'range') {
                // For ranges, we return each number via the range class' methods.
                for ($i = 0; $i < $element['value']->count(); $i++) {
                    yield $element['value']->get_element($i);
                }
            } else {
                // Single-value elements can be returned directly.
                yield $element['value'];
            }
        }
    }

    /**
     * Return the number of elements that are stored in this lazylist.
     *
     * @return int
     */
    public function count(): int {
        // Update the cached count, if necessary.
        if ($this->cachedcount === null) {
            $this->cachedcount = array_sum(array_column($this->elements, 'count'));
        }
        return $this->cachedcount;
    }

    /**
     * Check whether a given index is valid.
     *
     * @param mixed $offset index
     * @return boolean
     */
    public function offsetExists($offset): bool {
        return $offset >= 0 && $offset < $this->count();
    }

    /**
     * Get the element at a given index.
     *
     * @param int $offset
     * @return mixed
     */
    #[\ReturnTypeWillChange]
    public function offsetGet($offset) {
        if (!$this->offsetExists($offset)) {
            throw new Exception(get_string('error_indexoutofrange', 'qtype_formulas', $index));
        }

        $i = 0;
        // Iterate over all single-value or range elements of the list. If the current element would bring
        // the count of passed elements over the requested index, either get the single-value token or the
        // appropriate value from the range. Otherwise, increase the counter and move to the next element.
        foreach ($this->elements as $element) {
            if ($offset < $i + $element['count']) {
                if ($element['type'] === 'value') {
                    return $element['value'];
                } else {
                    // The range element to fetch is the difference between the requested index and the
                    // total number of list elements that have already passed.
                    $relative = $offset - $i;
                    return $element['value']->get_element($relative);
                }
            }
            $i += $element['count'];
        }
    }

    /**
     * Mandatory override of method from ArrayAccess interface. We do not currently support setting
     * individual elements of a lazylist.
     *
     * @param int $offset
     * @param mixed $value
     * @return void
     */
    public function offsetSet($offset, $value): void {
        throw new Exception('offsetSet() not implemented for lazylist class');
    }

    /**
     * Mandatory override of method from ArrayAccess interface. We do not currently support removing
     * individual elements from a lazylist.
     *
     * @param int $offset
     * @return void
     */
    public function offsetUnset($offset): void {
        throw new Exception('offsetUnset() not implemented for lazylist class');
    }

    /**
     * Convert the lazylist to a fully developed array. If needed, the array can be
     * limited in size; if the list is too large, an error will be thrown instead.
     *
     * @param int $maxsize size of list not to be exceeded
     * @return void
     */
    public function convert_to_limited_array(int $maxsize = 0) {
        if ($maxsize > 0 && $this->count() > $maxsize) {
            // FIXME: output error list size > 1000
        }
        return iterator_to_array($this);
    }
}
