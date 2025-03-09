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

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/question/type/formulas/questiontype.php');

use Exception;
use qtype_formulas\local\lexer;
use qtype_formulas\local\token;
use qtype_formulas\local\unit_parser;

/**
 * Unit tests for the unit_parser class.
 *
 * @package    qtype_formulas
 * @category   test
 * @copyright  2025 Philipp Imhof
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @covers \qtype_formulas\local\unit_parser
 * @covers \qtype_formulas\local\shunting_yard
 */
final class unit_parser_test extends \advanced_testcase {

    public function test_parse_unit_FIXME_REMOVE_WHEN_FINISHED() {
        self::assertTrue(true);
        return;
        $input = '(m/s)^2';
        $parser = new unit_parser($input);
        var_dump($parser->get_statements()[0]);

        echo $parser->get_legacy_unit_string();
    }

    public function test_parse_unit_rules_FIXME() {
        $input = 'm: k da d c m u; s: m u; min = 60 s;';



        $lexer = new lexer($input);
        $tokens = $lexer->get_tokens();
        var_dump($tokens);

        $rules = [];
        foreach ($tokens as $token) {


            if ($token->type === token::END_OF_STATEMENT) {
                $rules[] = $currentrule;
            }
        }

    }

    /**
     * Test parsing of unit inputs.
     *
     * @dataProvider provide_units
     */
    public function test_parse_unit($expected, $input) {
        $e = null;
        $error = '';
        try {
            new unit_parser($input);
        } catch (Exception $e) {
            $error = $e->getMessage();
        }

        // If we are expecting an error message, the exception object should not be null and
        // the message should match, without checking row and column number.
        if ($expected[0] === '!') {
            self::assertNotNull($e);
            self::assertStringEndsWith(substr($expected, 1), $error);
        } else {
            self::assertNull($e);
        }
    }

    /**
     * Test conversion of unit inputs to legacy input format.
     *
     * @dataProvider provide_units
     */
    public function test_get_legacy_unit_string($expected, $input) {
        $e = null;
        try {
            $parser = new unit_parser($input);
        } catch (Exception $e) {
            $e->getMessage();
        }

        // If we are not expecting an error, check that the input has been translated as expected.
        if ($expected[0] !== '!') {
            self::assertEquals($expected, $parser->get_legacy_unit_string());
        } else {
            self::assertNotNull($e);
        }
    }

    /**
     * Data provider for the test functions. For simplicity, we use the same provider
     * for valid and invalid expressions. In case of invalid expressions, we put an
     * exclamation mark (!) at the start of the error message.
     *
     * @return array
     */
    public static function provide_units(): array {
        return [
            ['J/(m K)', 'J / m K'],
            ['J/(m K)', 'J / m*K'],
            ['J/(m K)', 'J / (m K)'],
            ['J/(m K)', 'J / (m*K)'],
            ['m kg/(s^2)', 'm kg/s^2'],
            ['m kg/(s^2)', 'm kg / s^2'],
            ['m kg/(s^2)', 'm*kg / s^2'],
            ['m kg/(s^2)', 'm*(kg / s^2)'],
            ['kg m/(s^2)', '(m/s^2)*kg'],
            ['kg m/(s^2)', '(m/s^2) kg'],
            ['m kg/(s^2)', '(m (kg / s^(2)))'],
            ['m K kg/s', 'm (kg / s) K'],
            ['s^(-1)', 's^-1'],
            ['s^2', 's**2'],
            ['s^(-1)', 's**-1'],
            ['s^(-1)', 's^(-1)'],
            ['s^(-1)', 's**(-1)'],
            ['s^(-1)/(m^(-1))', 's**-1 / m**-1'],
            ['m', 'm'],
            ['m', '(m)'],
            ['km', 'km'],
            ['m^2', 'm^2'],
            ['m^2', 'm^(2)'],
            ['m^2', '(m^2)'],
            ['m^2', 'm**2'],
            ['m^2', '(m**2)'],
            ['m^2', 'm**(2)'],
            ['m^2', 'm ^ 2'],
            ['m^2', 'm ^ (2)'],
            ['m^2', 'm ** 2'],
            ['m^2', 'm ** (2)'],
            ['m^(-2)', 'm^-2'],
            ['m^(-2)', '(m^-2)'],
            ['m^(-2)', 'm^(-2)'],
            ['m^(-2)', 'm ^ -2'],
            ['m^(-2)', 'm ^ (-2)'],
            ['m/s', 'm/s'],
            ['m/s', '(m)/(s)'],
            ['m/s', '(m/s)'],
            ['m s^(-1)', 'm s^-1'],
            ['m s^(-1)', 'm (s^-1)'],
            ['m s^(-1)', 'm (s^(-1))'],
            ['m s^(-1)', 'm s^(-1)'],
            ['m/(s^(-1))', 'm / (s^(-1))'],
            ['m/(s^(-1))', 'm / ((s^(-1)))'],
            ['kg m/s', 'kg m/s'],
            ['kg m/s', 'kg (m/s)'],
            ['kg m/s', 'kg*(m/s)'],
            ['kg m/s', 'kg*m/s'],
            ['kg m/s', '(kg m)/s'],
            ['kg m/s', '(kg*m)/s'],
            ['kg m s^(-1)', 'kg m s^-1'],
            ['kg m^2', 'kg m^2'],
            ['kg m^2', 'kg m ^ 2'],
            ['kg m s^(-1)', 'kg m s ^ - 1'],
            ['!Unit already used: m', 'm kg / m'],
            ['!Unexpected token: 1', 'm 1/s'],
            ['!Unexpected token: 1', '1/s'],
            ['!Unexpected token: 1', '1 m/s'],
            ['!Unexpected token: 2', '2/s'],
            ['!Unexpected token: 2.1', '2.1'],
            ['!Unexpected token: ^', '^2'],
            ['!Unexpected token: *', '*s'],
            ['!Unexpected token: *', 'm* *kg'],
            ['!Unexpected token: /', '/s'],
            ['!Unexpected token: *', 'm*'],
            ['!Unexpected token: /', 'm/'],
            ['!Unexpected token: ^', 'm^'],
            ['!Unexpected token: /', 'm^(/2)'],
            ['!Unexpected token: +', 'm^+2'],
            ['!Unexpected token: ^', '(m/s)^2'],
            ['!Unexpected token: **', '(m/s)**2'],
            ["!Unexpected input: '@'", '@'],
        ];
    }
}
