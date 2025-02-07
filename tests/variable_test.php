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

namespace qtype_formulas;

use qtype_formulas\local\variable;

/**
 * Unit tests for the variable class.
 *
 * @package    qtype_formulas
 * @copyright  2022 Philipp Imhof
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @covers \qtype_formulas\local\variable
 */
final class variable_test extends \advanced_testcase {

    /**
     * Test conversion of variable object into string.
     *
     * @dataProvider provide_variables
     */
    public function test_string_representation($expected, $input): void {
        $s = '' . $input;

        self::assertEquals($expected, $s);
    }

    /**
     * Data provider.
     *
     * @return array
     */
    public static function provide_variables(): array {
        return [
            ['1', new variable('x', 1, variable::NUMERIC)],
            ['1', new variable('x', 1.0, variable::NUMERIC)],
            ['1.5', new variable('x', 1.5, variable::NUMERIC)],
            ['foo', new variable('x', 'foo', variable::STRING)],
            ['[1, 2, 3]', new variable('x', [1, 2, 3], variable::LIST)],
            ['[a, 1, 1]', new variable('x', ['a', 1, 1.0], variable::LIST)],
            ['[a, [1, 2], 3]', new variable('x', ['a', [1, 2], 3], variable::LIST)],
            ['{1, 2, 3}', new variable('x', [1, 2, 3], variable::SET)],
            ['{1, [2, 3]}', new variable('x', [1, [2, 3]], variable::SET)],
            ['x', new variable('x', 2, variable::ALGEBRAIC)],
            ['foo', new variable('foo', 'bar', variable::ALGEBRAIC)],
            ['x', new variable('x', [1, [2, 3]], variable::ALGEBRAIC)],
        ];
    }

    public function test_timestamp(): void {
        // When giving a timestamp, it should be applied.
        $var = new variable('x', 1, variable::NUMERIC, 123);
        self::assertEquals(123, $var->timestamp);

        // When not giving a timestamp, the current microtime() should be used.
        $before = microtime(true);
        $var = new variable('x', 1, variable::NUMERIC);
        $after = microtime(true);
        // Note: assertLessThanOrEqual checks whether the actual value (second parameter)
        // is less than or equal the expected value (first parameter).
        self::assertGreaterThanOrEqual($before, $var->timestamp);
        self::assertLessThanOrEqual($after, $var->timestamp);
    }
}
