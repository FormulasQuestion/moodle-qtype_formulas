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
use ReturnTypeWillChange;
use Traversable;

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
     * Constructor. FIXME.
     *
     */
    public function __construct() {
    }

    /**
     * FIXME
     *
     * @param token $value
     * @return void
     */
    public function append_value(token $value): void {
        $this->elements[] = ['type' => 'value', 'value' => $value, 'count' => 1];
        $this->cachedcount = null;
    }

    /**
     * FIXME
     *
     * @param token $value
     * @return void
     */
    public function prepend_value(token $value): void {
        array_unshift($this->elements, ['type' => 'value', 'value' => $value, 'count' => 1]);
        $this->cachedcount = null;
    }

    /**
     * FIXME
     *
     * @param range $range
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
     * FIXME
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
     * FIXME
     *
     * @return Traversable
     */
    public function getIterator(): Traversable {
        foreach ($this->elements as $element) {
            if ($element['type'] === 'value') {
                yield $element['value'];
            } else {
                for ($i = 0; $i < $element['value']->count(); $i++) {
                    yield $element['value']->get_element($i);
                }
            }
        }
    }

    /**
     * FIXME
     *
     * @return int
     */
    public function count(): int {
        if ($this->cachedcount === null) {
            $this->cachedcount = array_sum(array_column($this->elements, 'count'));
        }
        return $this->cachedcount;
    }

    /**
     * FIXME
     *
     * @param mixed $offset
     * @return boolean
     */
    public function offsetExists($offset): bool {
        return $offset >= 0 && $offset < $this->count();
    }

    /**
     * FIXME
     *
     * @param [type] $offset
     * @return mixed
     */
    #[ReturnTypeWillChange]
    public function offsetGet($offset) {
        if (!$this->offsetExists($offset)) {
            throw new \OutOfBoundsException("Invalid index: $offset for lazylist");
        }

        $i = 0;
        foreach ($this->elements as $element) {
            if ($offset < $i + $element['count']) {
                if ($element['type'] === 'value') {
                    return $element['value'];
                } else {
                    $relative = $offset - $i;
                    return $element['value']->get_element($relative);
                }
            }
            $i += $element['count'];
        }
    }

    /**
     * FIXME
     *
     * @param [type] $offset
     * @param [type] $value
     * @return void
     */
    public function offsetSet($offset, $value): void {
        throw new Exception('offsetSet() not implemented for lazylist class');
    }

    /**
     * FIXME
     *
     * @param integer $offset
     * @return void
     */
    public function offsetUnset($offset): void {
        throw new Exception('offsetUnset() not implemented for lazylist class');
    }
}
