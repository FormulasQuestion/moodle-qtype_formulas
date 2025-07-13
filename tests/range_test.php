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
use qtype_formulas\local\range;

/**
 * qtype_formulas range class tests
 *
 * @package    qtype_formulas
 * @category   test
 * @copyright  2025 Philipp Imhof
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @covers \qtype_formulas\local\range
 */
final class range_test extends \advanced_testcase {

    public function test_split(): void {
        $range = new range(1, 10);
        self::assertEquals(4, $range->get_element(3)->value);
        list($left, $right) = $range->split(3);

        self::assertEquals(3, $left->count());
        self::assertEquals(6, $right->count());
        self::assertEquals(1, $left->get_element(0)->value);
        self::assertEquals(3, $left->get_element(-1)->value);
        self::assertEquals(4, $right->get_element(0)->value);
        self::assertEquals(9, $right->get_element(-1)->value);

        $range = new range(20, 10, -1);
        self::assertEquals(16, $range->get_element(4)->value);
        list($left, $right) = $range->split(4);

        self::assertEquals(4, $left->count());
        self::assertEquals(6, $right->count());
        self::assertEquals(20, $left->get_element(0)->value);
        self::assertEquals(17, $left->get_element(-1)->value);
        self::assertEquals(16, $right->get_element(0)->value);
        self::assertEquals(11, $right->get_element(-1)->value);

        $invalid = null;
        $message = '';
        try {
            $invalid = $range->split(100);
        } catch (Exception $e) {
            $message = $e->getMessage();
        }
        self::assertEquals('Evaluation error: index 100 out of range.', $message);
        self::assertNull($invalid);
    }

    public function test_get_element(): void {
        $range = new range(2, 100, 2);
        self::assertEquals(2, $range->get_element(0)->value);
        self::assertEquals(4, $range->get_element(1)->value);
        self::assertEquals(98, $range->get_element(48)->value);
        self::assertEquals(98, $range->get_element(-1)->value);
        self::assertEquals(2, $range->get_element(-49)->value);

        $range = new range(1, 1e10, .1);
        self::assertEquals(1, $range->get_element(0)->value);
        self::assertEqualsWithDelta(1.1, $range->get_element(1)->value, 1e-6);
        self::assertEqualsWithDelta(1e10 - .1, $range->get_element(-1)->value, 1e-6);

        $range = new range(20, 1, -1);
        self::assertEquals(20, $range->get_element(0)->value);
        self::assertEquals(19, $range->get_element(1)->value);
        self::assertEquals(2, $range->get_element(18)->value);
        self::assertEquals(2, $range->get_element(-1)->value);
        self::assertEquals(20, $range->get_element(-19)->value);
    }

    public function test_count(): void {
        $range = new range(2, 100, 2);
        self::assertEquals(49, $range->count());

        $range = new range(1, 1e10, .1);
        self::assertEquals(1e11 - 10, $range->count());

        $range = new range(20, 1, -1);
        self::assertEquals(19, $range->count());
    }

    public function test_count_if_size_and_step_dont_match(): void {
        $range = new range(.1, .95, .1);
        self::assertEquals(9, $range->count());

        $range = new range(.9, .05, -.1);
        self::assertEquals(9, $range->count());
    }

    public function test_iterate_with_foreach(): void {
        $range = new range(1, 10);
        self::assertEquals(9, $range->count());

        foreach ($range as $i => $num) {
            self::assertEquals($i + 1, $num->value);
        }
    }

    public function test_array_access_of_elements(): void {
        $range = new range(1, 10);
        self::assertEquals(9, $range->count());

        for ($i = 0; $i < 9; $i++) {
            self::assertEquals($i + 1, $range[$i]->value);
        }

        $invalid = null;
        $message = '';
        try {
            $invalid = $range[10];
        } catch (Exception $e) {
            $message = $e->getMessage();
        }
        self::assertEquals('Evaluation error: index 10 out of range.', $message);
        self::assertNull($invalid);
    }

    public function test_unimplemented_methods(): void {
        $range = new range(1, 10);

        $message = '';
        try {
            $range[] = 1;
        } catch (Exception $e) {
            $message = $e->getMessage();
        }
        self::assertEquals('offsetSet() not implemented for range class', $message);
        self::assertCount(9, $range);

        $message = '';
        try {
            $range->offsetSet(0, 42);
        } catch (Exception $e) {
            $message = $e->getMessage();
        }
        self::assertEquals('offsetSet() not implemented for range class', $message);
        self::assertCount(9, $range);

        $message = '';
        try {
            $range->offsetUnset(0);
        } catch (Exception $e) {
            $message = $e->getMessage();
        }
        self::assertEquals('offsetUnset() not implemented for range class', $message);
        self::assertCount(9, $range);

        $message = '';
        try {
            unset($range[0]);
        } catch (Exception $e) {
            $message = $e->getMessage();
        }
        self::assertEquals('offsetUnset() not implemented for range class', $message);
        self::assertCount(9, $range);
    }
}
