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
 * qtype_formulas token class tests
 *
 * @package    qtype_formulas
 * @category   test
 * @copyright  2022 Philipp Imhof
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace qtype_formulas;

class token_test extends \advanced_testcase {
    /**
     * @dataProvider provide_tokens
     */
    public function test_string_representation($expected, $input): void {
        $s = '' . $input;

        self::assertEquals($expected, $s);
    }

    public function provide_tokens(): array {
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

    public function provide_tokens_to_wrap(): array {
        $one = new token(token::NUMBER, 1);
        $two = new token(token::NUMBER, 2);
        $three = new token(token::NUMBER, 3);
        $foo = new token(token::STRING, 'foo');

        return [
            [$one, $one],
            [$one, 1],
            [$one, 1.0],
            [$foo, $foo],
            [$foo, 'foo'],
            [new token(token::STRING, '1'), '1'],
            [new token(token::NUMBER, 1.5), 1.5],
            [new token(token::STRING, '1'), ['value' => 1, 'type' => token::STRING]],
            [new token(token::LIST, [$one, $two, $three]), [$one, $two, $three]],
            [$one, ['value' => '1', 'type' => token::NUMBER]],
            ['cannot wrap a non-numeric value into a NUMBER token', ['value' => 'a', 'type' => token::NUMBER]],
            ['cannot wrap the given value into a STRING token', ['value' => [1, 2], 'type' => token::STRING]],
            ["the given value 'null' has an invalid data type and cannot be converted to a token", null],
        ];
    }
}
