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

use Exception;
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

    public function test_latexify(): void {
        // FIXME: implement
        self::assertTrue(true);
        return;
        $input = '1+2';
        $input = '-1';
        $input = '1*2+3';
        $input = '1+2*3';
        $input = '1*2*3';
        $input = '(1+2)*(3+4)';
        $input = '(1+2)**3';
        $input = '5**3';
        $input = '(2+4)/(3*4)';
        $input = '5 == 3 + 4';
        $input = '(-4)^3';
        $input = '(a*b)^3';
        $input = 'a*b^3';
        $input = '1/2^3';
        $input = '(1/2)^3';
        $input = '7 % 5';
        $input = '5 * 4 == 3 + 4';
        $input = '3 * sin(x^2)';
        $input = '3 * log(x^2, 7)';
        $input = '5.12e-3';
        $parser = new answer_parser($input);

        var_dump(latexifier::latexify($parser->get_statements()[0]->body));
    }
}
