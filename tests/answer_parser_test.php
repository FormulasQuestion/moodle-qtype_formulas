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
            [qtype_formulas::ANSWER_TYPE_ALGEBRAIC, '1+ln(a)+log10(b)'],
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

    public function test_constructor_with_known_variables(): void {
        // The function stdnormpdf() is not in the whitelist, so students are not allowed to use it.
        // Also, used like this, it would be a syntax error anyway. But in this case, we make it
        // available as a variable.
        $parser = new answer_parser('3 stdnormpdf', ['stdnormpdf']);
        self::assertTrue($parser->is_acceptable_for_answertype(qtype_formulas::ANSWER_TYPE_ALGEBRAIC));
        // Verify it works even if written as a function. Actually, this expression would mean
        // 3*stdnormpdf*2.
        $parser = new answer_parser('3 stdnormpdf(2)', ['stdnormpdf']);
        self::assertTrue($parser->is_acceptable_for_answertype(qtype_formulas::ANSWER_TYPE_ALGEBRAIC));

        // If stdnormpdf is not made available, this should fail, because the function is not allowed.
        $parser = new answer_parser('3 stdnormpdf');
        self::assertFalse($parser->is_acceptable_for_answertype(qtype_formulas::ANSWER_TYPE_ALGEBRAIC));
        // Make sure that it did not just fail because of the syntax error.
        $parser = new answer_parser('3 stdnormpdf(2)');
        self::assertFalse($parser->is_acceptable_for_answertype(qtype_formulas::ANSWER_TYPE_ALGEBRAIC));

        // See whether we can separate an imaginary unit 'exp' in a number expression.
        $input = '3 exp';
        $parser = new answer_parser($input, ['exp']);
        $splitindex = $parser->find_start_of_units();
        $number = trim(substr($input, 0, $splitindex));
        $unit = trim(substr($input, $splitindex));
        self::assertEquals(3, $number);
        self::assertEquals('exp', $unit);
    }
}
