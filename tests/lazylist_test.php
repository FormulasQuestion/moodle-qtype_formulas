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
        self::assertEquals(1, count($list));

        $list->append_range(new range(1, 10));
        self::assertEquals(10, $list->count());
        self::assertEquals(10, count($list));

        $list->append_value(new token(token::STRING, 'foo'));
        self::assertEquals(11, $list->count());
        self::assertEquals(11, count($list));

        $list->append_range(new range(1, 2, .01));
        self::assertEquals(111, $list->count());
        self::assertEquals(111, count($list));
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
    }

    public function test_conversion_to_array(): void {
        // FIXME: implement test
    }
}
