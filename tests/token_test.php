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
use qtype_formulas\local\token;

/**
 * qtype_formulas token class tests
 *
 * @package    qtype_formulas
 * @category   test
 * @copyright  2022 Philipp Imhof
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @covers \qtype_formulas\local\token
 */
final class token_test extends \advanced_testcase {
    /**
     * Test conversion of token to string.
     *
     * @param string $expected expected string representation of token
     * @param token $input token to convert to string
     * @dataProvider provide_tokens
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
    public static function provide_tokens(): array {
        $one = new token(token::NUMBER, 1);
        $two = new token(token::NUMBER, 2);
        $three = new token(token::NUMBER, 3);
        $foo = new token(token::STRING, 'foo');
        return [
            ['1', $one],
            ['1', new token(token::NUMBER, 1.0)],
            ['1.5', new token(token::NUMBER, 1.5)],
            ['foo', $foo],
            ['[1, 2, 3]', new token(token::LIST, [1, 2, 3])],
            ['[1, 2, 3]', new token(token::LIST, [$one, $two, $three])],
            ['[a, 2, 3]', new token(token::LIST, ['a', 2, 3])],
            ['[foo, 2, 3]', new token(token::LIST, [$foo, $two, $three])],
            ['[a, [1, 2], 3]', new token(token::LIST, ['a', [1, 2], 3])],
            ['[foo, [1, 2], 3]', new token(token::LIST, [$foo, [$one, $two], $three])],
            ['{1, 2, 3}', new token(token::SET, [1, 2, 3])],
            ['{1, 2, 3}', new token(token::SET, [$one, $two, $three])],
            ['{1, [2, 3]}', new token(token::SET, [1, [2, 3]])],
            ['{1, [2, 3]}', new token(token::SET, [$one, [$two, $three]])],
        ];
    }

    /**
     * Test wrapping of values into tokens.
     *
     * @param token $expected expected token after wrapping
     * @param mixed $input input value to be wrapped (string, number, another token etc.)
     *
     * @dataProvider provide_tokens_to_wrap
     */
    public function test_wrap($expected, $input): void {
        $type = null;
        $value = $input;
        // Input may contain explicit type.
        if (is_array($input) && array_key_exists('type', $input)) {
            $type = $input['type'];
            $value = $input['value'];
        }
        if (is_string($expected)) {
            try {
                token::wrap($value, $type);
            } catch (\Exception $e) {
                self::assertEquals($expected, $e->getMessage());
                return;
            }
        }
        self::assertEquals($expected, token::wrap($value, $type));
    }

    /**
     * Data provider.
     *
     * @return array
     */
    public static function provide_tokens_to_wrap(): array {
        $one = new token(token::NUMBER, 1);
        $two = new token(token::NUMBER, 2);
        $three = new token(token::NUMBER, 3);
        $foo = new token(token::STRING, 'foo');
        $list = new token(token::LIST, [$one, $two, $three]);
        $lazylist = new lazylist();
        $lazylist->append_value($one);
        $lazylist->append_value($two);
        $lazylist->append_value($three);
        $set = new token(token::SET, $lazylist);

        return [
            [$one, $one],
            [$one, 1],
            [$one, 1.0],
            [$foo, $foo],
            [$foo, 'foo'],
            [new token(token::STRING, '1'), '1'],
            [new token(token::NUMBER, 1), true],
            [new token(token::NUMBER, 0), false],
            [new token(token::NUMBER, 1.5), 1.5],
            [new token(token::STRING, '1'), ['value' => 1, 'type' => token::STRING]],
            [$list, [$one, $two, $three]],
            [$set, $lazylist],
            [new token(token::LIST, [$list]), [[1, 2, 3]]],
            [$one, ['value' => '1', 'type' => token::NUMBER]],
            ['Cannot wrap a non-numeric value into a NUMBER token.', ['value' => 'a', 'type' => token::NUMBER]],
            ['Cannot wrap the given value into a STRING token.', ['value' => [1, 2], 'type' => token::STRING]],
            ['List must not contain more than 1000 elements.', range(1, 2000)],
            ['List must not contain more than 1000 elements.', [range(1, 500), range(1, 500), range(1, 500)]],
            ['List must not contain more than 1000 elements.', [[range(1, 500), range(1, 500)], [[[range(1, 500)]]]]],
            ["The given value 'null' has an invalid data type and cannot be converted to a token.", null],
        ];
    }

    /**
     * Test unpacking of tokens (or literals).
     *
     * @param mixed $expected expected return value
     * @param mixed $input token or literal
     * @return void
     *
     * @dataProvider provide_tokens_and_values_to_unpack
     */
    public function test_unpack($expected, $input): void {
        $unpacked = token::unpack($input);

        self::assertSame($expected, $unpacked);
    }

    /**
     * Data provider.
     *
     * @return array
     */
    public static function provide_tokens_and_values_to_unpack(): array {
        return [
            // Scalar values that are not tokens will be returned as they are.
            [1, 1],
            [1.0, 1.0],
            [true, true],
            [false, false],
            ['foo', 'foo'],
            ['1', '1'],
            [[1, 2.0, true, false, 'foo', '3', '4.0'], [1, 2.0, true, false, 'foo', '3', '4.0']],
            // STRING and NUMBER tokens should have just their value returned.
            [1, new token(token::NUMBER, 1)],
            [1.0, new token(token::NUMBER, 1.0)],
            ['foo', new token(token::STRING, 'foo')],
            ['1.0', new token(token::STRING, '1.0')],
            ['1', new token(token::STRING, '1')],
            [
                [1, 2.0, 'foo', '3', '4.0'],
                new token(token::LIST, [
                    new token(token::NUMBER, 1),
                    new token(token::NUMBER, 2.0),
                    new token(token::STRING, 'foo'),
                    new token(token::STRING, '3'),
                    new token(token::STRING, '4.0'),
                ]),
            ],
            [
                [[1, 2.0], ['foo', '3'], '4.0'],
                new token(token::LIST, [
                    new token(token::LIST, [
                        new token(token::NUMBER, 1),
                        new token(token::NUMBER, 2.0),
                    ]),
                    new token(token::LIST, [
                        new token(token::STRING, 'foo'),
                        new token(token::STRING, '3'),
                    ]),
                    new token(token::STRING, '4.0'),
                ]),
            ],
            [
                [1, 2.0, 'foo', '3', '4.0'],
                new token(token::SET, [
                    new token(token::NUMBER, 1),
                    new token(token::NUMBER, 2.0),
                    new token(token::STRING, 'foo'),
                    new token(token::STRING, '3'),
                    new token(token::STRING, '4.0'),
                ]),
            ],
        ];
    }

    /**
     * Data provider.
     *
     * @return array
     */
    public static function provide_tokens_to_count(): array {
        return [
            [1, token::wrap(1)],
            [1, token::wrap('foo')],
            [3, token::wrap([1, 2, 3])],
            [6, token::wrap([[1, 2], [2, 3], [3, 4]])],
        ];
    }

    /**
     * Test recursive counting of tokens.
     *
     * @param int $expected the expected number of tokens
     * @param token $input the token to be counted
     * @return void
     *
     * @dataProvider provide_tokens_to_count
     */
    public function test_recursive_count($expected, $input): void {
        self::assertEquals($expected, token::recursive_count($input));
    }

}
