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
 * Unit tests for unit conversion in the Formulas question plugin.
 *
 * @package    qtype_formulas
 * @copyright  2023 Philipp E. Imhof
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace qtype_formulas;
use qtype_formulas\variables;
use Exception;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/question/engine/tests/helpers.php');
require_once($CFG->dirroot . '/question/type/formulas/variables.php');
require_once($CFG->dirroot . '/question/type/formulas/conversion_rules.php');
require_once($CFG->dirroot . '/question/type/formulas/answer_unit.php');


class unit_conversion_test extends \advanced_testcase {

    /**
     * @dataProvider provide_numbers_and_units
     */
    public function test_common_si_units($expected, $inputs): void {
        $qv = new variables;
        $rules = new unit_conversion_rules();
        $converter = new answer_unit_conversion();
        // The ruleset "common SI units" is number 1.
        $entry = $rules->entry(1);
        $converter->assign_default_rules(1, $entry[1]);

        list($modelnumber, $modelunit) = $qv->split_formula_unit($expected);
        foreach ($inputs as $input) {
            list($answernumber, $answerunit) = $qv->split_formula_unit($input);

            // Check if the unit is compatible.
            $checked = $converter->check_convertibility($answerunit, $modelunit);
            $this->assertEquals(true, $checked->convertible);
            // Convert the number and check if the result is OK.
            $factor = $checked->cfactor;
            $this->assertEqualsWithDelta(floatval($modelnumber), floatval($answernumber) * $factor, 1e-8);
        }
    }

    public function provide_numbers_and_units(): array {
        return [
            'length 1' => ['100 m', ['0.1 km', '10000 cm', '1000 dm', '100000 mm']],
            'length 2' => ['1 mm', ['1000 um', '1000000 nm', '0.001 m', '0.1 cm']],
            'length 3' => ['1 nm', ['1000 pm', '1000000 fm']],
            'area' => ['1 m^2', ['1e-6 km^2', '100 dm^2', '10000 cm^2']],
            'volume' => ['1 dm^3', ['0.001 m^3', '1000 cm^3']],
            'time 1' => ['1 ms', ['1000 us', '0.001 s']],
            'time 2' => ['1 ns', ['1000 ps', '0.001 us', '1000000 fs']],
            'time squared' => ['1 s^2', ['1000000 ms^2']],
            'weigth 1' => ['1 g', ['1000 mg', '0.001 kg', '1000000 ug']],
            'weigth 2' => ['1 ug', ['0.001 mg', '0.000001 g', '1000 ng', '1000000 pg', '1000000000 fg']],
            'amount of substance 1' => ['1000 mmol', ['1 mol', '1000000 umol']],
            'amount of substance 2' => ['1 mol', ['1000 mmol', '1000000 umol']],
            'amount of substance 3' => ['1 mmol', ['0.001 mol', '1000 umol', '1000000 nmol', '1000000000 pmol']],
            'force 1' => ['1 N', ['0.001 kN', '1000 mN', '1000000 uN', '0.000001 MN']],
            'force 2' => ['1 uN', ['0.001 mN', '0.000001 N', '1000 nN', '1000000 pN', '1000000000 fN']],
            'force 3' => ['1 kN', ['0.001 MN', '1000 N']],
            'force 4' => ['1 MN', ['1000 kN', '1000000 N']],
            'current 1' => ['1 A', ['1000 mA', '1000000 uA', '1000000000 nA']],
            'current 2' => ['1 uA', ['0.001 mA', '0.000001 A', '1000 nA', '1000000 pA']],
            'energy 1' => ['1 kJ', ['1000 J', '0.001 MJ', '0.000001 GJ']],
            'energy 2' => ['1 GJ', ['1000 MJ', '0.001 TJ', '1000000 kJ', '1000000000 J']],
            'energy 3' => ['1 J', ['1000 mJ', '1000000 uJ', '1000000000 nJ']],
            'energy J/eV' => ['1 J', ['6241509.47e12 eV', '6241509.47e6 MeV', '6241509470 GeV']],
            'power 1' => ['1 kW', ['1000 W', '0.001 MW', '0.000001 GW']],
            'power 2' => ['1 GW', ['1000 MW', '0.001 TW', '1000000 kW', '1000000000 W']],
            'power 3' => ['1 W', ['1000 mW', '1000000 uW', '1000000000 nW']],
            'pressure' => ['1 MPa', ['1000 kPa', '1000000 Pa', '0.001 GPa', '0.000001 TPa']],
            'frequency' => ['1 GHz', ['1000 MHz', '1000000 kHz', '0.001 THz', '0.000001 PHz']],
            'charge' => ['1 C', ['1000 mC', '1000000 uC', '1e9 nC', '0.001 kC']],
            'voltage' => ['1000 V', ['1 kV', '1000000 mV', '1e9 uV', '0.001 MV']],
            'resistance 1' => ['1000 ohm', ['1 kohm', '1000000 mohm', '0.001 Mohm']],
            'resistance 2' => ['1e9 ohm', ['1 Gohm', '0.001 Tohm']],
            'capacitance' => ['1 uF', ['0.001 mF', '0.000001 F', '1000 nF']],
            'flux density' => ['1 uT', ['0.001 mT', '0.000001 T', '1000 nT']],
            'inductance' => ['1 uH', ['0.001 mH', '0.000001 H', '1000 nH']],
            'speed' => ['1 m/ms', ['1 km/s']],
            'combination 1' => ['1 m g/us', ['1000 mm kg ms^-1']],
            'combination 2' => ['1 kPa s^-2', ['1e-3 Pa ms^-2']],
            'combination 3' => ['1 m kg s^-2', ['1 m kg / s^2', '1 km g / s^2', '1 km g s^-2']],
        ];
    }



}
