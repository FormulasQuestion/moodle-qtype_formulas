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
use qtype_formulas;
use qtype_formulas\local\answer_parser;

/**
 * Unit tests for the answer_parser class.
 *
 * @package    qtype_formulas
 * @category   test
 * @copyright  2024 Philipp Imhof
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @covers \qtype_formulas\local\answer_parser
 */
final class answer_parser_test extends \advanced_testcase {

    /**
     * Data provider.
     *
     * @return array
     */
    public static function provide_numbers(): array {
        return [
            [qtype_formulas::ANSWER_TYPE_NUMBER, '3'],
            [qtype_formulas::ANSWER_TYPE_NUMBER, '3.'],
            [qtype_formulas::ANSWER_TYPE_NUMBER, '.3'],
            [qtype_formulas::ANSWER_TYPE_NUMBER, '0.3'],
            [qtype_formulas::ANSWER_TYPE_NUMBER, '3.1'],
            [qtype_formulas::ANSWER_TYPE_NUMBER, '3.1e-10'],
            [qtype_formulas::ANSWER_TYPE_NUMBER, '3.e10'],
            [qtype_formulas::ANSWER_TYPE_NUMBER, '.3e10'],
            [qtype_formulas::ANSWER_TYPE_NUMBER, '-3'],
            [qtype_formulas::ANSWER_TYPE_NUMBER, '+3'],
            [qtype_formulas::ANSWER_TYPE_NUMBER, '-3.e10'],
            [qtype_formulas::ANSWER_TYPE_NUMBER, '-.3e10'],
            [qtype_formulas::ANSWER_TYPE_NUMBER, 'pi'],
            [qtype_formulas::ANSWER_TYPE_NUMBER, 'π'],
            [qtype_formulas::ANSWER_TYPE_NUMBER, '-pi'],
            [qtype_formulas::ANSWER_TYPE_NUMBER, '-π'],
            [qtype_formulas::ANSWER_TYPE_NUMBER, '- 3'], // TODO doc: This is allowed now.
            [qtype_formulas::ANSWER_TYPE_NUMBER, '+ 3'], // TODO doc: This is allowed now.
            [qtype_formulas::ANSWER_TYPE_NUMERIC, '3+10*4'],
            [qtype_formulas::ANSWER_TYPE_NUMERIC, '3+10^4'],
            [qtype_formulas::ANSWER_TYPE_NUMERIC, '3*4*5'],
            [qtype_formulas::ANSWER_TYPE_NUMERIC, '3 4 5'],
            [qtype_formulas::ANSWER_TYPE_NUMERIC, '3+4'],
            [qtype_formulas::ANSWER_TYPE_NUMERIC, '3-4'],
            [qtype_formulas::ANSWER_TYPE_NUMERIC, '3+4*5'],
            [qtype_formulas::ANSWER_TYPE_NUMERIC, '(3+4)*5'],
            [qtype_formulas::ANSWER_TYPE_NUMERIC, '3/4'],
            [qtype_formulas::ANSWER_TYPE_NUMERIC, '2**(1/2)'],
            [qtype_formulas::ANSWER_TYPE_NUMERICAL_FORMULA, 'sin(3)'],
            [qtype_formulas::ANSWER_TYPE_NUMERICAL_FORMULA, '3+exp(4)'],
            [qtype_formulas::ANSWER_TYPE_ALGEBRAIC, '3 e10'],
            [qtype_formulas::ANSWER_TYPE_ALGEBRAIC, '3e 10'],
            [qtype_formulas::ANSWER_TYPE_ALGEBRAIC, '3e8e8'],
            [qtype_formulas::ANSWER_TYPE_ALGEBRAIC, 'a*b'],
            [false, '(1+2)*ln'],
            [false, '\sin(3)'],
            [false, '\ 3'],
            [false, '3; 4'],
            [false, '[1,2]'],
            [false, '{1,2}'],
            [false, 'stdnormpdf(0.5)'],
            [false, '#'],
        ];
    }

    /**
     * Data provider.
     *
     * @return array
     */
    public static function provide_algebraic_formulas(): array {
        return [
            [qtype_formulas::ANSWER_TYPE_NUMBER, 'pi'],
            [qtype_formulas::ANSWER_TYPE_NUMERIC, '3e8 4.e8 .5e8'],
            [qtype_formulas::ANSWER_TYPE_NUMERICAL_FORMULA, '- 3'],
            [qtype_formulas::ANSWER_TYPE_NUMERICAL_FORMULA, 'sin(3)'],
            [qtype_formulas::ANSWER_TYPE_NUMERICAL_FORMULA, 'sin(pi)'],
            [qtype_formulas::ANSWER_TYPE_NUMERICAL_FORMULA, 'sin(π)'],
            [qtype_formulas::ANSWER_TYPE_NUMERICAL_FORMULA, 'sin(3)-3+exp(4)'],
            [qtype_formulas::ANSWER_TYPE_NUMERICAL_FORMULA, '3+exp(4+5)^sin(6+7)'],
            [qtype_formulas::ANSWER_TYPE_NUMERICAL_FORMULA, '3+4^-(9)'],
            [qtype_formulas::ANSWER_TYPE_ALGEBRAIC, 'sin(a)-a+exp(b)'],
            [qtype_formulas::ANSWER_TYPE_ALGEBRAIC, '3e 10'],
            [qtype_formulas::ANSWER_TYPE_ALGEBRAIC, '3e8(4.e8+2)(.5e8/2)5'],
            [qtype_formulas::ANSWER_TYPE_ALGEBRAIC, 'sin(a)-a+exp(b)'],
            [qtype_formulas::ANSWER_TYPE_ALGEBRAIC, 'a*b*c'],
            [qtype_formulas::ANSWER_TYPE_ALGEBRAIC, 'a b c'],
            [qtype_formulas::ANSWER_TYPE_ALGEBRAIC, 'a(b+c)(x/y)d'],
            [qtype_formulas::ANSWER_TYPE_ALGEBRAIC, 'a(b+c) (x/y)d'],
            [qtype_formulas::ANSWER_TYPE_ALGEBRAIC, 'a (b+c)(x/y) d'],
            [qtype_formulas::ANSWER_TYPE_ALGEBRAIC, 'a (b+c) (x/y) d'],
            [qtype_formulas::ANSWER_TYPE_ALGEBRAIC, 'a(4.e8+2)3e8(.5e8/2)d'],
            [qtype_formulas::ANSWER_TYPE_ALGEBRAIC, 'a+x^y'],
            [qtype_formulas::ANSWER_TYPE_ALGEBRAIC, '3+x^-(y)'],
            [qtype_formulas::ANSWER_TYPE_ALGEBRAIC, '3+x^-y'],
            [qtype_formulas::ANSWER_TYPE_ALGEBRAIC, '3+(u+v)^x'],
            [qtype_formulas::ANSWER_TYPE_ALGEBRAIC, '3+(u+v)^(x+y)'],
            [qtype_formulas::ANSWER_TYPE_ALGEBRAIC, '3+sin(u+v)^(x+y)'],
            [qtype_formulas::ANSWER_TYPE_ALGEBRAIC, '3+exp(u+v)^sin(x+y)'],
            [qtype_formulas::ANSWER_TYPE_ALGEBRAIC, 'a+exp(a)(u+v)^sin(1+2)(b+c)'],
            [qtype_formulas::ANSWER_TYPE_ALGEBRAIC, 'a+exp(u+v)^-sin(x+y)'],
            [qtype_formulas::ANSWER_TYPE_ALGEBRAIC, 'a+b^c^d+f'],
            [qtype_formulas::ANSWER_TYPE_ALGEBRAIC, 'a+b^(c^d)+f'],
            [qtype_formulas::ANSWER_TYPE_ALGEBRAIC, 'a+(b^c)^d+f'],
            [qtype_formulas::ANSWER_TYPE_ALGEBRAIC, 'a+b^c^-d'],
            [qtype_formulas::ANSWER_TYPE_ALGEBRAIC, '1+ln(a)+log10(b)+lg(c)'],
            [qtype_formulas::ANSWER_TYPE_ALGEBRAIC, 'asin(w t)'],
            [qtype_formulas::ANSWER_TYPE_ALGEBRAIC, 'a sin(w t)+ b cos(w t)'],
            [qtype_formulas::ANSWER_TYPE_ALGEBRAIC, '2 (3) a sin(b)^c - (sin(x+y)+x^y)^-sin(z)c tan(z)(x^2)'],
            [qtype_formulas::ANSWER_TYPE_ALGEBRAIC, 'a**b'],
            [qtype_formulas::ANSWER_TYPE_ALGEBRAIC, 'a/(b-b)'],
            [qtype_formulas::ANSWER_TYPE_ALGEBRAIC, 'a+(b^c)^+f'], // TODO doc: now allowed, + is unary plus.
            [qtype_formulas::ANSWER_TYPE_ALGEBRAIC, 'pi()'], // TODO doc: now allowed.
            // Note: the following is syntactically valid and is read as 3*e10; it can be evaluated if e10 is a valid variable.
            [qtype_formulas::ANSWER_TYPE_ALGEBRAIC, '3 e10'],
            // Note: the following is syntactically valid and is read as 3e8*e8; it can be evaluated if e8 is a valid variable.
            [qtype_formulas::ANSWER_TYPE_ALGEBRAIC, '3e8e8'],
            // Note: the following is syntactically valid and is read as 3e8*e8e8; it can be evaluated if e8e8 is a valid variable.
            [qtype_formulas::ANSWER_TYPE_ALGEBRAIC, '3e8e8e8'],
            [false, 'a-'],
            [false, '*a'],
            [false, 'a+^c+f'],
            [false, 'a+b^^+f'],
            [false, 'a+((b^c)^d+f'],
            [false, 'a+(b^c+f'],
            [false, 'a+b^c)+f'],
            [false, 'a+b^(c+f'],
            [false, 'a+b)^c+f'],
            [false, 'sin 3'],
            [false, '1+sin*(3)+2'],
            [false, '1+sin^(3)+2'],
            [false, 'a sin w t'],
            [false, '1==2?3:4'],
            [false, 'a=b'],
            [false, '3&4'],
            [false, '3==4'],
            [false, '3&&4'],
            [false, '3!'],
            [false, '@'],
            [false, '\ 4'],
            [false, '\sin(pi)'],
            [false, '1+sin'],
        ];
    }

    /**
     * Prepare an answer parser to be used in the tests.
     *
     * @param string $input given input
     * @return answer_parser
     */
    private static function prepare_answer_parser($input): answer_parser {
        try {
            $parser = new answer_parser($input);
        } catch (Exception $e) {
            // If there was an exception already during the creation of the parser,
            // it is not initialized yet. In that case, we create a new, empty parser.
            // In such a case, validations will fail, as expected.
            if (!isset($parser)) {
                $parser = new answer_parser('');
            }
        }
        return $parser;
    }

    /**
     * Test for answer_parser::is_acceptable_number().
     *
     * @dataProvider provide_numbers
     */
    public function test_is_acceptable_number($expected, $input): void {
        $parser = self::prepare_answer_parser($input);

        if ($expected === false || $expected > qtype_formulas::ANSWER_TYPE_NUMBER) {
            self::assertFalse($parser->is_acceptable_for_answertype(qtype_formulas::ANSWER_TYPE_NUMBER));
        } else {
            self::assertTrue($parser->is_acceptable_for_answertype(qtype_formulas::ANSWER_TYPE_NUMBER));
        }
    }

    /**
     * Test for answer_parser::is_acceptable_numberic().
     *
     * @dataProvider provide_numbers
     */
    public function test_is_acceptable_numeric($expected, $input): void {
        $parser = self::prepare_answer_parser($input);

        if ($expected === false || $expected > qtype_formulas::ANSWER_TYPE_NUMERIC) {
            self::assertFalse($parser->is_acceptable_for_answertype(qtype_formulas::ANSWER_TYPE_NUMERIC));
        } else {
            self::assertTrue($parser->is_acceptable_for_answertype(qtype_formulas::ANSWER_TYPE_NUMERIC));
        }
    }

    /**
     * Test for answer_parser::is_acceptable_numberical_formula().
     *
     * @dataProvider provide_numbers
     */
    public function test_is_acceptable_numerical_formula($expected, $input): void {
        $parser = self::prepare_answer_parser($input);

        if ($expected === false || $expected > qtype_formulas::ANSWER_TYPE_NUMERICAL_FORMULA) {
            self::assertFalse($parser->is_acceptable_for_answertype(qtype_formulas::ANSWER_TYPE_NUMERICAL_FORMULA));
        } else {
            self::assertTrue($parser->is_acceptable_for_answertype(qtype_formulas::ANSWER_TYPE_NUMERICAL_FORMULA));
        }
    }

    /**
     * Test for answer_parser::is_acceptable_algebraic_formula().
     *
     * @dataProvider provide_numbers
     * @dataProvider provide_algebraic_formulas
     */
    public function test_is_acceptable_algebraic_formula($expected, $input): void {
        $parser = self::prepare_answer_parser($input);

        if ($expected === false) {
            self::assertFalse($parser->is_acceptable_for_answertype(qtype_formulas::ANSWER_TYPE_ALGEBRAIC));
        } else {
            self::assertTrue($parser->is_acceptable_for_answertype(qtype_formulas::ANSWER_TYPE_ALGEBRAIC));
        }
    }

    /**
     * Test splitting of number and unit as entered in a combined answer box.
     *
     * @dataProvider provide_numbers_and_units
     */
    public function test_unit_split($expected, $input): void {
        $parser = new answer_parser($input);
        $index = $parser->find_start_of_units();
        $number = substr($input, 0, $index);
        $unit = substr($input, $index);

        self::assertEquals($expected[0], trim($number));
        self::assertEquals($expected[1], $unit);
    }

    /**
     * Provide combined responses with numbers and units to test splitting.
     *
     * @return array
     */
    public static function provide_numbers_and_units(): array {
        return [
            'missing unit' => [['123', ''], '123'],
            'missing number' => [['', 'm/s'], 'm/s'],
            'length 1' => [['100', 'm'], '100 m'],
            'length 2' => [['100', 'cm'], '100cm'],
            'length 3' => [['1.05', 'mm'], '1.05 mm'],
            'length 4' => [['-1.3', 'nm'], '-1.3 nm'],
            'area 1' => [['-7.5e-3', 'm^2'], '-7.5e-3 m^2'],
            'area 2' => [['6241509.47e6', 'MeV'], '6241509.47e6 MeV'],
            'speed' => [['1', 'km/s'], '1 km/s'],
            'combination 1' => [['1', 'm g/us'], '1 m g/us'],
            'combination 2' => [['1', 'kPa s^-2'], '1 kPa s^-2'],
            'combination 3' => [['1', 'm kg s^-2'], '1 m kg s^-2'],
            'numerical' => [['12 + 3 * 4/8', 'm^2'], '12 + 3 * 4/8 m^2'],
            'numerical formula' => [['12 * sqrt(3)', 'kg/s'], '12 * sqrt(3) kg/s'],

            [['.3', ''], '.3'],
            [['3.1', ''], '3.1'],
            [['3.1e-10', ''], '3.1e-10'],
            [['3', 'm'], '3m'],
            [['3', 'kg m/s'], '3kg m/s'],
            [['3.', 'm/s'], '3.m/s'],
            [['3.e-10', 'm/s'], '3.e-10m/s'],
            [['- 3', 'm/s'], '- 3m/s'],
            [['3', 'e10 m/s'], '3 e10 m/s'],
            [['3', 'e 10 m/s'], '3e 10 m/s'],
            [['3e8', 'e8 m/s'], '3e8e8 m/s'],
            [['3+10*4', 'm/s'], '3+10*4 m/s'],
            [['3+10^4', 'm/s'], '3+10^4 m/s'],
            [['sin(3)', 'm/s'], 'sin(3) m/s'],
            [['3+exp(4)', 'm/s'], '3+exp(4) m/s'],
            [['3*4*5', 'm/s'], '3*4*5 m/s'],

            'old unit tests, 18' => [['', 'm/s'], 'm/s'],
            'old unit tests, 20' => [['sin(3)', 'kg m/s'], 'sin(3)kg m/s'],

            [['3.1e-10', 'kg m/s'], '3.1e-10kg m/s'],
            [['-3', 'kg m/s'], '-3kg m/s'],
            [['- 3', 'kg m/s'], '- 3kg m/s'],
            [['3', 'e'], '3e'],
            [['3e8', ''], '3e8'],
            [['3e8', 'e'], '3e8e'],

            [['sin(3)', 'kg m/s'], 'sin(3)kg m/s'],
            [['3*4*5', 'kg m/s'], '3*4*5 kg m/s'],

            [['3e8(4.e8+2)(.5e8/2)5', 'kg m/s'], '3e8(4.e8+2)(.5e8/2)5kg m/s'],
            [['3+exp(4+5)^sin(6+7)', 'kg m/s'], '3+exp(4+5)^sin(6+7)kg m/s'],
            [['3+exp(4+5)^-sin(6+7)', 'kg m/s'], '3+exp(4+5)^-sin(6+7)kg m/s'],

            [['3', 'e8'], '3 e8'],
            [['3', 'e 8'], '3e 8'],
            [['3e8', 'e8'], '3e8e8'],
            [['3e8', 'e8e8'], '3e8e8e8'],

            [['3 /', 's'], '3 /s'],
            [['3', 'm+s'], '3 m+s'],
            [['', 'a=b'], 'a=b'],

            [['3 4 5', 'm/s'], '3 4 5 m/s'],
            [['3+4 5+10^4', 'kg m/s'], '3+4 5+10^4kg m/s'],
            [['3+4 5+10^4', 'kg m/s'], '3+4 5+10^4kg m/s'],
            [['3 4 5', 'kg m/s'], '3 4 5 kg m/s'],
        ];
    }

    /**
     * Provide special cases with units that are named like existing functions.
     *
     * @return array
     */
    public static function provide_special_units(): array {
        return [
            [
                ['number' => '3', 'unit' => 'exp^2'],
                ['response' => '3 exp^2', 'knownvars' => ['exp']],
            ],
            [
                ['number' => '3', 'unit' => 'exp^2'],
                ['response' => '3 exp^2', 'knownvars' => []],
            ],
            [
                ['number' => '3', 'unit' => 'exp^2'],
                ['response' => '3exp^2', 'knownvars' => ['exp']],
            ],
            [
                ['number' => '3', 'unit' => 'exp^2'],
                ['response' => '3exp^2', 'knownvars' => []],
            ],
            [
                ['number' => '5', 'unit' => 'min'],
                ['response' => '5 min', 'knownvars' => ['min']],
            ],
            [
                ['number' => '5', 'unit' => 'min'],
                ['response' => '5 min', 'knownvars' => []],
            ],
            [
                ['number' => '5', 'unit' => 'min'],
                ['response' => '5min', 'knownvars' => ['min']],
            ],
            [
                ['number' => '5', 'unit' => 'min'],
                ['response' => '5min', 'knownvars' => []],
            ],
        ];
    }

    /**
     * Test splitting of units if they are named like a function.
     *
     * @dataProvider provide_special_units
     */
    public function test_special_unit_split($expected, $input): void {
        // As exp is not followed by parens, it is not considered as a function, but as a variable.
        // Hence, splitting should work just fine.
        $parser = new answer_parser($input['response'], $input['knownvars']);
        $index = $parser->find_start_of_units();
        $number = substr($input['response'], 0, $index);
        $unit = substr($input['response'], $index);
        self::assertEquals($expected['number'], trim($number));
        self::assertEquals($expected['unit'], $unit);
    }

    public function test_is_acceptable_for_answertype_with_invalid_type(): void {
        $parser = new answer_parser('1');
        self::assertFalse($parser->is_acceptable_for_answertype(PHP_INT_MAX));
    }

    public function test_constructor_with_known_variables(): void {
        // The teacher should be able to block functions by defining variables with the same name,
        // e. g. sin = 1.
        $parser = new answer_parser('3 sin(30)', ['sin']);
        self::assertFalse($parser->is_acceptable_for_answertype(qtype_formulas::ANSWER_TYPE_ALGEBRAIC));
        self::assertFalse($parser->is_acceptable_for_answertype(qtype_formulas::ANSWER_TYPE_NUMERICAL_FORMULA));
        // In normal circumstances, the student should, of course, be able to use the sine function.
        $parser = new answer_parser('3 sin(30)');
        self::assertTrue($parser->is_acceptable_for_answertype(qtype_formulas::ANSWER_TYPE_ALGEBRAIC));
        self::assertTrue($parser->is_acceptable_for_answertype(qtype_formulas::ANSWER_TYPE_NUMERICAL_FORMULA));

        // The function stdnormpdf() is not in the whitelist, so students are not allowed to use it in
        // an algebraic formula. Also, they cannot use it as a variable, because of the name conflict.
        // It does not make a difference whether stdnormpdf is a known variable or not.
        $parser = new answer_parser('3 stdnormpdf');
        self::assertFalse($parser->is_acceptable_for_answertype(qtype_formulas::ANSWER_TYPE_ALGEBRAIC));
        self::assertFalse($parser->is_acceptable_for_answertype(qtype_formulas::ANSWER_TYPE_NUMERICAL_FORMULA));
        $parser = new answer_parser('3 stdnormpdf', ['stdnormpdf']);
        self::assertFalse($parser->is_acceptable_for_answertype(qtype_formulas::ANSWER_TYPE_ALGEBRAIC));
        self::assertFalse($parser->is_acceptable_for_answertype(qtype_formulas::ANSWER_TYPE_NUMERICAL_FORMULA));

        // If it is written as a function, it should not work either. The student must be warned that they
        // are using a function that is not available for them.
        $parser = new answer_parser('3 stdnormpdf(2)');
        self::assertFalse($parser->is_acceptable_for_answertype(qtype_formulas::ANSWER_TYPE_ALGEBRAIC));
        self::assertFalse($parser->is_acceptable_for_answertype(qtype_formulas::ANSWER_TYPE_NUMERICAL_FORMULA));
        // If stdnormpdf is made available, this should still fail, because students may not use a variable
        // named stdnormpdf.
        $parser = new answer_parser('3 stdnormpdf(2)', ['stdnormpdf']);
        self::assertFalse($parser->is_acceptable_for_answertype(qtype_formulas::ANSWER_TYPE_ALGEBRAIC));
        self::assertFalse($parser->is_acceptable_for_answertype(qtype_formulas::ANSWER_TYPE_NUMERICAL_FORMULA));
    }
}
