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

namespace qtype_formulas;

use Exception;
use qtype_formulas\local\lazylist;
use qtype_formulas\local\range;
use qtype_formulas\local\token;

/**
 * qtype_formulas lazylist class tests
 *
 * @package    qtype_formulas
 * @category   test
 * @copyright  2025 Philipp Imhof
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @covers \qtype_formulas\local\lazylist
 */
final class lazylist_test extends \advanced_testcase {

    public function test_append_value_and_range(): void {
        $list = new lazylist();
        self::assertEquals(0, $list->count());

        $list->append_value(new token(token::NUMBER, 5));
        self::assertEquals(1, $list->count());
        self::assertCount(1, $list);

        $list->append_range(new range(1, 10));
        self::assertEquals(10, $list->count());
        self::assertCount(10, $list);

        $list->append_value(new token(token::STRING, 'foo'));
        self::assertEquals(11, $list->count());
        self::assertCount(11, $list);

        $list->append_range(new range(1, 2, .01));
        self::assertEquals(111, $list->count());
        self::assertCount(111, $list);
    }

    public function test_prepend_value_and_range(): void {
        $list = new lazylist();
        self::assertCount(0, $list);

        $list->prepend_value(new token(token::NUMBER, 5));
        self::assertCount(1, $list);
        self::assertEquals(5, $list[0]->value);

        $list->prepend_range(new range(1, 10));
        self::assertCount(10, $list);
        self::assertEquals(1, $list[0]->value);
        self::assertEquals(5, $list[9]->value);

        $list->prepend_value(new token(token::STRING, 'foo'));
        self::assertCount(11, $list);
        self::assertEquals('foo', $list[0]->value);
        self::assertEquals(1, $list[1]->value);
        self::assertEquals(5, $list[10]->value);

        $list->prepend_range(new range(1, 2, .01));
        self::assertCount(111, $list);
        self::assertEquals(1, $list[0]->value);
        self::assertEquals(1.01, $list[1]->value);
    }

    public function test_iterate_with_foreach(): void {
        $list = new lazylist();
        $list->append_value(new token(token::NUMBER, 1));
        $list->append_range(new range(2, 10));
        $list->append_value(new token(token::NUMBER, 10));
        self::assertEquals(10, $list->count());

        foreach ($list as $i => $num) {
            self::assertEquals($i + 1, $num->value);
        }
    }

    public function test_array_access_of_elements(): void {
        $list = new lazylist();
        $list->append_value(new token(token::NUMBER, 1));
        $list->append_range(new range(2, 10));
        $list->append_value(new token(token::NUMBER, 10));
        self::assertEquals(10, $list->count());

        for ($i = 0; $i < 10; $i++) {
            self::assertEquals($i + 1, $list[$i]->value);
        }

        $invalid = null;
        $message = '';
        try {
            $invalid = $list[10];
        } catch (Exception $e) {
            $message = $e->getMessage();
        }
        self::assertEquals('Evaluation error: index 10 out of range.', $message);
        self::assertNull($invalid);
    }

    public function test_unimplemented_methods(): void {
        $list = new lazylist();

        $message = '';
        try {
            $list[] = 1;
        } catch (Exception $e) {
            $message = $e->getMessage();
        }
        self::assertEquals('offsetSet() not implemented for lazylist class', $message);
        self::assertCount(0, $list);

        $message = '';
        try {
            $list->offsetSet(0, 'foo');
        } catch (Exception $e) {
            $message = $e->getMessage();
        }
        self::assertEquals('offsetSet() not implemented for lazylist class', $message);
        self::assertCount(0, $list);

        $message = '';
        $list->append_value(new token(token::NUMBER, 1));
        try {
            $list->offsetUnset(0);
        } catch (Exception $e) {
            $message = $e->getMessage();
        }
        self::assertEquals('offsetUnset() not implemented for lazylist class', $message);
        self::assertCount(1, $list);

        $message = '';
        try {
            unset($list[0]);
        } catch (Exception $e) {
            $message = $e->getMessage();
        }
        self::assertEquals('offsetUnset() not implemented for lazylist class', $message);
        self::assertCount(1, $list);
    }

    public function test_conversion_to_array(): void {
        // First, create a lazylist with just a range.
        $list = new lazylist();
        $list->append_range(new range(0, 1000));
        self::assertCount(1000, $list);
        $array = $list->convert_to_limited_array(1000);
        self::assertCount(1000, $array);

        // Limiting the size to 999 should lead to an error.
        $array = null;
        $message = '';
        try {
            $array = $list->convert_to_limited_array(999);
        } catch (Exception $e) {
            $message = $e->getMessage();
        }
        self::assertEquals('List must not contain more than 999 elements.', $message);
        self::assertNull($array);

        // Adding a single value to the start. Now conversion should fail even if 1000 elements are allowed.
        $list->prepend_value(new token(token::NUMBER, 42));
        self::assertCount(1001, $list);
        $array = null;
        $message = '';
        try {
            $array = $list->convert_to_limited_array(1000);
        } catch (Exception $e) {
            $message = $e->getMessage();
        }
        self::assertEquals('List must not contain more than 1000 elements.', $message);
        self::assertNull($array);

        // Adding an array as the first value. Note that we use PHP's range() function
        // and are not creating an instance of our own range class. The count should go
        // up by 1 only (one more element), but conversion should fail, because the
        // nested tokens still eat up memory.
        $list->prepend_value(token::wrap(range(1, 1000)));
        self::assertCount(1002, $list);
        $array = null;
        $message = '';
        try {
            $array = $list->convert_to_limited_array(2000);
        } catch (Exception $e) {
            $message = $e->getMessage();
        }
        self::assertEquals('List must not contain more than 2000 elements.', $message);
        self::assertNull($array);
    }

    public function test_are_all_numeric(): void {
        $list = new lazylist();

        // The empty list has no non-numeric entries.
        self::assertTrue($list->are_all_numeric());

        // Add numbers.
        $list->append_value(new token(token::NUMBER, 1));
        self::assertTrue($list->are_all_numeric());
        $list->append_value(new token(token::NUMBER, 2));
        self::assertTrue($list->are_all_numeric());

        // Add a range.
        $list->append_range(new range(1, 100));
        self::assertTrue($list->are_all_numeric());

        $otherlist = clone $list;

        // Add a string to the list.
        $list->append_value(new token(token::STRING, 'foo'));
        self::assertFalse($list->are_all_numeric());

        // Add a numeric string to the other list.
        $otherlist->append_value(new token(token::STRING, '1'));
        self::assertFalse($otherlist->are_all_numeric());
    }
}
