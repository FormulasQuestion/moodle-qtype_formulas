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

use qtype_formulas\answer_unit_conversion;
use qtype_formulas\local\answer_parser;
use qtype_formulas\local\latexifier;

/**
 * Tests for the latexifier class.
 *
 * @package    qtype_formulas
 * @copyright  2025 Philipp Imhof
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @covers \qtype_formulas\local\latexifier
 */
final class latexifier_test extends \advanced_testcase {
    public function setUp(): void {
        global $CFG;
        require_once($CFG->dirroot . '/question/type/formulas/answer_unit.php');

        $this->resetAfterTest(true);
        parent::setUp();
    }

    /**
     * Data provider.
     *
     * @return array
     */
    public static function provide_answers(): array {
        return [
            ['1+2', '1+2'],
            ['-1', '-1'],
            ['1+2\cdot 3', '1+(2*3)'],
            ['1+\frac{2}{3}', '1+2/3'],
            ['\frac{1+2}{3}', '(1+2)/3'],
            ['1\cdot 2+3', '1*2+3'],
            ['1+2\cdot 3', '1+2*3'],
            ['1\cdot 2\cdot 3', '1*2*3'],
            ['\left(1+2\right)\cdot \left(3+4\right)', '(1+2)*(3+4)'],
            ['\left(1+2\right)^{3}', '(1+2)**3'],
            ['5^{3}', '5**3'],
            ['\frac{2+4}{3\cdot 4}', '(2+4)/(3*4)'],
            ['5=3+4', '5 == 3 + 4'],
            ['\left(-4\right)^{3}', '(-4)^3'],
            ['\left(a\cdot b\right)^{3}', '(a*b)^3'],
            ['a\cdot b^{3}', 'a*b^3'],
            ['\frac{1}{2^{3}}', '1/2^3'],
            ['\left(\frac{1}{2}\right)^{3}', '(1/2)^3'],
            ['7\bmod 5', '7 % 5'],
            ['5\cdot 4=3+4', '5 * 4 == 3 + 4'],
            ['3\cdot \sin\left(x^{2}\right)', '3 * sin(x^2)'],
            ['3\cdot \log_{7}\left( x^{2} \right)', '3 * log(x^2, 7)'],
            ['5.12\cdot 10^{-3}', '5.12e-3'],
            ['\sqrt{ 2 }', 'sqrt(2)'],
            ['2^{3}\cdot \left(7+2\right)', '2^3(7+2)'],
            ['\left(2^{3}\right)^{4}', '(2^3)^4'],
        ];
    }

    /**
     * Data provider.
     *
     * @return array
     */
    public static function provide_units(): array {
        return [
            ['\frac{\mathrm{J}}{\mathrm{m}\cdot\mathrm{K}}', 'J / m K'],
            ['\frac{\mathrm{J}}{\mathrm{m}\cdot\mathrm{K}}', 'J / (m K)'],
            ['\frac{\mathrm{m}\cdot\mathrm{kg}}{\mathrm{s}^{2}}', 'm kg/s^2'],
            ['\frac{1}{\mathrm{s}}', 's^-1'],
            ['\mathrm{s}^{2}', 's^2'],
            ['\frac{1}{\mathrm{s}}', 's^(-1)'],
            ['\frac{\mathrm{m}}{\mathrm{s}}', 's^-1 / m^-1'],
            ['\mathrm{m}', 'm'],
            ['\mathrm{m}^{2}', 'm^2'],
            ['\mathrm{m}^{2}', 'm ^ 2'],
            ['\frac{1}{\mathrm{m}^{2}}', 'm^-2'],
            ['\frac{1}{\mathrm{m}^{2}}', 'm^(-2)'],
            ['\frac{1}{\mathrm{m}^{2}}', 'm ^ -2'],
            ['\frac{1}{\mathrm{m}^{2}}', 'm ^ (-2)'],
            ['\frac{\mathrm{m}}{\mathrm{s}}', 'm/s'],
            ['\frac{\mathrm{m}}{\mathrm{s}}', 'm s^-1'],
            ['\frac{\mathrm{m}}{\mathrm{s}}', 'm s^(-1)'],
            ['\frac{\mathrm{kg}\cdot\mathrm{m}}{\mathrm{s}}', 'kg m/s'],
            ['\frac{\mathrm{kg}\cdot\mathrm{m}}{\mathrm{s}}', 'kg m s^-1'],
            ['\frac{\mathrm{kg}\cdot\mathrm{m}}{\mathrm{s}}', 'kg m s ^ -1'],
            ['\mathrm{kg}\cdot\mathrm{m}^{2}', 'kg m^2'],
            ['\mathrm{kg}\cdot\mathrm{m}^{2}', 'kg m ^ 2'],
        ];
    }

    /**
     * Test conversion of various answers into LaTeX code.
     *
     * @dataProvider provide_answers
     */
    public function test_latexify(string $expected, string $input): void {
        $parser = new answer_parser($input);

        self::assertEquals($expected, latexifier::latexify($parser->get_statements()[0]->body));
    }

    /**
     * Test conversion of units into LaTeX code.
     *
     * @dataProvider provide_units
     */
    public function test_latexify_unit(string $expected, string $input): void {
        $unitconverter = new answer_unit_conversion();
        $unitcheck = $unitconverter->parse_unit($input);

        self::assertNotNull($unitcheck);
        self::assertEquals($expected, latexifier::latexify_unit($unitcheck));
    }
}
