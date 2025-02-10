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

use qtype_formulas\local\random_variable;
use qtype_formulas\local\token;

/**
 * Unit tests for the random_variable class.
 *
 * @package    qtype_formulas
 * @category   test
 * @copyright  2025 Philipp Imhof
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @covers \qtype_formulas\local\random_variable
 */
final class random_variable_test extends \advanced_testcase {

    /**
     * Test random_variable::get_instantiated_definition().
     *
     * @param string $expected regex pattern for expected output
     * @param random_variable $input random variable
     *
     * @dataProvider provide_random_variables_for_instantiation
     */
    public function test_get_instantiated_definition(string $expected, random_variable $input): void {
        $input->instantiate();
        self::assertMatchesRegularExpression(
            $expected, $input->get_instantiated_definition()
        );
    }

    /**
     * Data provider.
     *
     * @return array
     */
    public static function provide_random_variables_for_instantiation(): array {
        return [
            ['/^a=[123];$/', new random_variable('a', [token::wrap(1), token::wrap(2), token::wrap(3)], false)],
            ['/^a="[ABC]";$/', new random_variable('a', [token::wrap('A'), token::wrap('B'), token::wrap('C')], false)],
            ['/^a=(\[1,2\]|\[3,4\]);$/', new random_variable('a', [token::wrap([1, 2]), token::wrap([3, 4])], false)],
            [
                '/^a=(\["A","B"\]|\["C","D"\]);$/',
                new random_variable('a', [token::wrap(['A', 'B']), token::wrap(['C', 'D'])], false),
            ],
            [
                '/^a=(\[1,2,3\]|\[1,3,2\]|\[2,1,3\]|\[2,3,1\]|\[3,1,2\]|\[3,2,1\]);$/',
                new random_variable('a', [token::wrap(1), token::wrap(2), token::wrap(3)], true),
            ],
        ];
    }

    public function test_get_instantiated_definition_if_not_instantiated(): void {
        $var = new random_variable('a', [token::wrap(1), token::wrap(2), token::wrap(3)], false);
        self::assertEmpty($var->get_instantiated_definition());
    }

    /**
     * Test random_variable::get_instantiated_definition().
     *
     * @param int $expected expected number of variants
     * @param random_variable $input random variable
     *
     * @dataProvider provide_random_variables_for_how_many
     */
    public function test_how_many(int $expected, random_variable $input): void {
        self::assertEquals(
            $expected, $input->how_many()
        );
    }

    /**
     * Data provider.
     *
     * @return array
     */
    public static function provide_random_variables_for_how_many(): array {
        return [
            [3, new random_variable('a', [token::wrap(1), token::wrap(2), token::wrap(3)], false)],
            [50, new random_variable('a', array_fill(0, 50, token::wrap(1)), false)],
            [3, new random_variable('a', [token::wrap('A'), token::wrap('B'), token::wrap('C')], false)],
            [2, new random_variable('a', [token::wrap([1, 2]), token::wrap([3, 4])], false)],
            [
                2,
                new random_variable('a', [token::wrap(['A', 'B']), token::wrap(['C', 'D'])], false),
            ],
            [
                6,
                new random_variable('a', [token::wrap(1), token::wrap(2), token::wrap(3)], true),
            ],
            [
                3628800,
                new random_variable('a', array_fill(0, 10, token::wrap(1)), true),
            ],
            [
                PHP_INT_MAX,
                new random_variable('a', array_fill(0, 200, token::wrap(1)), true),
            ],
        ];
    }
}
