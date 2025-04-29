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
require_once($CFG->dirroot . '/webservice/tests/helpers.php');
require_once($CFG->dirroot . '/question/type/formulas/questiontype.php');

use qtype_formulas;
use qtype_formulas\external\answervalidation;

/**
 * Tests for the answervalidation web service class.
 *
 * @package    qtype_formulas
 * @copyright  2025 Philipp Imhof
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @runTestsInSeparateProcesses
 * @covers \qtype_formulas\external\answervalidation
 */
final class answervalidation_test extends \advanced_testcase {
    public function setUp(): void {
        global $CFG;
        require_once($CFG->dirroot . '/question/type/formulas/classes/external/answervalidation.php');

        $this->resetAfterTest(true);
        parent::setUp();
    }

    /**
     * Data provider. We grab the test cases from the answer_parser test.
     *
     * @return array
     */
    public static function provide_numbers(): array {
        global $CFG;
        require_once($CFG->dirroot . '/question/type/formulas/tests/answer_parser_test.php');
        return answer_parser_test::provide_numbers();
    }

    /**
     * Data provider. We grab the test cases from the answer_parser test.
     *
     * @return array
     */
    public static function provide_algebraic_formulas(): array {
        global $CFG;
        require_once($CFG->dirroot . '/question/type/formulas/tests/answer_parser_test.php');
        return answer_parser_test::provide_algebraic_formulas();
    }

    /**
     * Data provider.
     *
     * @return array
     */
    public static function provide_numbers_and_units(): array {
        return [
            [qtype_formulas::ANSWER_TYPE_NUMBER, '123'],
            [qtype_formulas::ANSWER_TYPE_NUMBER, '100 m'],
            [qtype_formulas::ANSWER_TYPE_NUMBER, '100cm'],
            [qtype_formulas::ANSWER_TYPE_NUMBER, '1.05 mm'],
            [qtype_formulas::ANSWER_TYPE_NUMBER, '-1.3 nm'],
            [qtype_formulas::ANSWER_TYPE_NUMBER, '-7.5e-3 m^2'],
            [qtype_formulas::ANSWER_TYPE_NUMBER, '6241509.47e6 MeV'],
            [qtype_formulas::ANSWER_TYPE_NUMBER, '1 km/s'],
            [qtype_formulas::ANSWER_TYPE_NUMBER, '1 m g/us'],
            [qtype_formulas::ANSWER_TYPE_NUMBER, '1 kPa s^-2'],
            [qtype_formulas::ANSWER_TYPE_NUMBER, '1 m kg s^-2'],
            [qtype_formulas::ANSWER_TYPE_NUMBER, '3.e-10m/s'],
            [qtype_formulas::ANSWER_TYPE_NUMBER, '- 3m/s'],
            [qtype_formulas::ANSWER_TYPE_NUMBER, '3.1e-10kg m/s'],
            [qtype_formulas::ANSWER_TYPE_NUMBER, '-3kg m/s'],
            [qtype_formulas::ANSWER_TYPE_NUMBER, '- 3kg m/s'],
            [qtype_formulas::ANSWER_TYPE_NUMBER, '3e'],
            [qtype_formulas::ANSWER_TYPE_NUMBER, '3e8'],
            [qtype_formulas::ANSWER_TYPE_NUMBER, '3e8e'],
            [qtype_formulas::ANSWER_TYPE_NUMERIC, '12 + 3 * 4/8 m^2'],
            [qtype_formulas::ANSWER_TYPE_NUMERIC, '3+10*4 m/s'],
            [qtype_formulas::ANSWER_TYPE_NUMERIC, '3*4*5 m/s'],
            [qtype_formulas::ANSWER_TYPE_NUMERIC, '3*4*5 kg m/s'],
            [qtype_formulas::ANSWER_TYPE_NUMERIC, '3e8(4.e8+2)(.5e8/2)5kg m/s'],
            [qtype_formulas::ANSWER_TYPE_NUMERIC, '3+10^4 m/s'],
            [qtype_formulas::ANSWER_TYPE_NUMERIC, '3 4 5 m/s'],
            [qtype_formulas::ANSWER_TYPE_NUMERIC, '3 4 5 kg m/s'],
            [qtype_formulas::ANSWER_TYPE_NUMERIC, '3+4 5+10^4kg m/s'],
            [qtype_formulas::ANSWER_TYPE_NUMERIC, '3+4 5+10^4kg m/s'],
            [qtype_formulas::ANSWER_TYPE_NUMERICAL_FORMULA, '12 * sqrt(3) kg/s'],
            [qtype_formulas::ANSWER_TYPE_NUMERICAL_FORMULA, 'sin(3) m/s'],
            [qtype_formulas::ANSWER_TYPE_NUMERICAL_FORMULA, '3+exp(4) m/s'],
            [qtype_formulas::ANSWER_TYPE_NUMERICAL_FORMULA, 'sin(3)kg m/s'],
            [qtype_formulas::ANSWER_TYPE_NUMERICAL_FORMULA, 'sin(3)kg m/s'],
            [qtype_formulas::ANSWER_TYPE_NUMERICAL_FORMULA, '3+exp(4+5)^sin(6+7)kg m/s'],
            [qtype_formulas::ANSWER_TYPE_NUMERICAL_FORMULA, '3+exp(4+5)^-sin(6+7)kg m/s'],
            [false, 'm/s'],
            [false, '3 e10 m/s'],
            [false, '3e 10 m/s'],
            [false, '3e8e8 m/s'],
            [false, '3 e8'],
            [false, '3e 8'],
            [false, '3e8e8'],
            [false, '3e8e8e8'],
            [false, '3 /s'],
            [false, '3 m+s'],
            [false, 'a=b'],
        ];
    }

    /**
     * Test validation of a student answer.
     *
     * @param bool|int $type the lowest answer type for which the input is valid, or false if invalid
     * @param string $input the simulated input
     * @dataProvider provide_numbers
     * @dataProvider provide_algebraic_formulas
     */
    public function test_validate_student_answer($type, string $input): void {
        $answertypes = [
            qtype_formulas::ANSWER_TYPE_NUMBER,
            qtype_formulas::ANSWER_TYPE_NUMERIC,
            qtype_formulas::ANSWER_TYPE_NUMERICAL_FORMULA,
            qtype_formulas::ANSWER_TYPE_ALGEBRAIC,
        ];

        foreach ($answertypes as $answertype) {
            $result = answervalidation::validate_student_answer($input, $answertype, false);
            $result = \external_api::clean_returnvalue(
                answervalidation::validate_student_answer_returns(), $result
            );
            if ($type === false) {
                self::assertEquals($result['status'], 'error');
                continue;
            }
            if ($type <= $answertype) {
                self::assertEquals($result['status'], 'success');
            } else {
                self::assertEquals($result['status'], 'error');
            }
        }
    }

    /**
     * Test validation of a student answer in a combined field.
     *
     * @param bool|int $type the lowest answer type for which the input is valid, or false if invalid
     * @param string $input the simulated input
     * @dataProvider provide_numbers_and_units
     */
    public function test_validate_student_answer_with_unit($type, string $input): void {
        $answertypes = [
            qtype_formulas::ANSWER_TYPE_NUMBER,
            qtype_formulas::ANSWER_TYPE_NUMERIC,
            qtype_formulas::ANSWER_TYPE_NUMERICAL_FORMULA,
        ];

        foreach ($answertypes as $answertype) {
            $result = answervalidation::validate_student_answer($input, $answertype, true);
            $result = \external_api::clean_returnvalue(
                answervalidation::validate_student_answer_returns(), $result
            );
            if ($type === false) {
                self::assertEquals($result['status'], 'error');
                continue;
            }
            if ($type <= $answertype) {
                self::assertEquals($result['status'], 'success');
            } else {
                self::assertEquals($result['status'], 'error');
            }
        }
    }

    /**
     * Test validation of a student answer in a combined field.
     *
     * @param bool $expected whether or not the input should be valid
     * @param string $input the simulated input
     * @dataProvider provide_units
     */
    public function test_validate_unit(bool $expected, string $input): void {
        $result = answervalidation::validate_unit($input);
        $result = \external_api::clean_returnvalue(
            answervalidation::validate_unit_returns(), $result
        );
        if ($expected) {
            self::assertEquals($result['status'], 'success');
        } else {
            self::assertEquals($result['status'], 'error');
        }
    }

    /**
     * Data provider.
     *
     * @return array
     */
    public static function provide_units(): array {
        return [
            [true, 'm'],
            [true, 'm^2'],
            [true, 'm ^ 2'],
            [true, 'm^-2'],
            [true, 'm^(-2)'],
            [true, 'm ^ -2'],
            [true, 'm/s'],
            [true, 'm s^-1'],
            [true, 'm s^(-1)'],
            [true, 'kg m/s'],
            [true, 'kg m^2'],
            [true, 'kg m ^ 2'],
            [false, '2.1'],
            [false, '^2'],
            [false, 'm^+2'],
            [false, 'kg m s ^ - 1'],
            [true, 'kg m s ^ -1'],
            [false, '@'],
            [true, ''],
            [true, 'J / m K'],
            [false, 'J / m*K'],
            [true, 'J / (m K)'],
            [false, 'J / (m*K)'],
            [true, 'm kg/s^2'],
            [true, 'm kg / s^2'],
            [false, 'm*kg / s^2'],
            [false, 'm*(kg / s^2)'],
            [false, '(m/s^2)*kg'],
            [false, '(m/s^2) kg'],
            [false, 'm (kg / s) K'],
            [false, 's**2'],
            [false, 's**-1'],
            [false, 's**(-1)'],
            [false, 's**-1 / m**-1'],
            [false, '(m)'],
            [true, 'km'],
            [false, '(m^2)'],
            [false, 'm**2'],
            [false, '(m**2)'],
            [false, 'm**(2)'],
            [false, 'm ^ (2)'],
            [false, 'm ** 2'],
            [false, 'm ** (2)'],
            [false, '(m^-2)'],
            [true, 'm ^ (-2)'],
            [false, '(m)/(s)'],
            [false, '(m/s)'],
            [false, 'm (s^-1)'],
            [false, 'm (s^(-1))'],
            [true, 'm / (s^(-1))'],
            [false, 'm / ((s^(-1)))'],
            [false, 'kg (m/s)'],
            [false, 'kg*(m/s)'],
            [false, 'kg*m/s'],
            [false, '(kg m)/s'],
            [false, '(kg*m)/s'],
            [true, 'kg m s^-1'],
            [false, 'm^2.5'],
            [false, 'm^(2)'],
            [false, 'm/s)'],
            [false, '(m/s'],
            [false, 'm^s'],
            [false, 'm*-s'],
            [false, 'm/s/K'],
            [false, 'm/s * kg/m^2'],
            [false, 'm*π'],
            [false, 'm,s'],
            [false, '[m/s]'],
            [false, '(m*)'],
            [false, 'm+km'],
            [false, '*'],
            [false, '*m'],
            [false, '/m'],
            [false, 'm*(*s)'],
            [false, '(*m)'],
            [false, '(**m)'],
            [false, '(^m)'],
            [false, '{m/s}'],
            [false, 'm 1/s'],
            [false, '1/s'],
            [false, '1 m/s'],
            [false, '2/s'],
            [false, '*s'],
            [false, 'm* *kg'],
            [false, '/s'],
            [false, 'm*'],
            [false, 'm/'],
            [false, 'm^'],
            [false, 'm^(/2)'],
            [false, '(m/s)^2'],
            [false, '(m/s)**2'],
            [false, 'm kg / m'],
        ];
    }
}
