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
 * Additional functions qtype_formulas
 *
 * @package    qtype_formulas
 * @copyright  2022 Philipp Imhof
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace qtype_formulas;
use Exception;

class functions {
    /* function name => [min params, max params] */
    const FUNCTIONS = [
        'binomialcdf' => [3, 3],
        'binomialpdf' => [3, 3],
        'fact' => [1, 1],
        'fmod' => [2, 2],
        'gcd' => [2, 2],
        'lcm' => [2, 2],
        'modinv' => [2, 2],
        'modpow' => [3, 3],
        'ncr' => [2, 2],
        'normcdf' => [3, 3],
        'npr' => [2, 2],
        'stdnormcdf' => [1, 1],
        'stdnormpdf' => [1, 1],
    ];

    /**
     * Calculate the factorial n! of a number.
     *
     * @param int $n the number
     * @return int
     */
    public static function fact(int $n): int {
        $n = (int) $n;
        if ($n < 2) {
            return 1;
        }
        $result = 1;
        for ($i = $n; $i > 1; $i--) {
            $result *= $i;
        }
        return $result;
    }

    /**
     * calculate standard normal probability density
     *
     * @param float $z value
     * @return float standard normal density of $z
     */
    public static function stdnormpdf(float $z): float {
        return 1 / (sqrt(2) * M_SQRTPI) * exp(-.5 * $z ** 2);
    }

    /**
     * calculate standard normal cumulative distribution by approximation
     * using Simpson's rule, accurate to ~5 decimal places
     *
     * @param float $z value
     * @return float probability for a value of $z or less under standard normal distribution
     */
    public static function stdnormcdf(float $z): float {
        if ($z < 0) {
            return 1 - self::stdnormcdf(-$z);
        }
        $n = max(10, floor(10 * $z));
        $h = $z / $n;
        $res = self::stdnormpdf(0) + self::stdnormpdf($z);
        for ($i = 1; $i < $n; $i++) {
            $res += 2 * self::stdnormpdf($i * $h);
            $res += 4 * self::stdnormpdf(($i - 0.5) * $h);
        }
        $res += 4 * self::stdnormpdf(($n - 0.5) * $h);
        $res *= $h / 6;
        return $res + 0.5;
    }

    /**
     * calculate normal cumulative distribution by approximation
     * using Simpson's rule, accurate to ~5 decimal places
     *
     * @param float $x value
     * @param float $mu mean
     * @param float $sigma standard deviation
     * @return float probability for a value of $x or less
     */
    public static function normcdf(float $x, float $mu, float $sigma): float {
        return self::stdnormcdf(($x - $mu) / $sigma);
    }

    /**
     * raise $a to the $b-th power modulo $m using efficient
     * square and multiply
     *
     * @param int $a base
     * @param int $b exponent
     * @param int $m modulus
     * @return int
     */
    public static function modpow(int $a, int $b, int $m): int {
        $bin = decbin($b);
        $res = $a;
        if ($b == 0) {
            return 1;
        }
        for ($i = 1; $i < strlen($bin); $i++) {
            if ($bin[$i] == "0") {
                $res = ($res * $res) % $m;
            } else {
                $res = ($res * $res) % $m;
                $res = ($res * $a) % $m;
            }
        }
        return $res;
    }

    /**
     * calculate the multiplicative inverse of $a modulo $m using the
     * extended euclidean algorithm
     *
     * @param int $a the number whose inverse is to be found
     * @param int $m the modulus
     * @return int the result or 0 if the inverse does not exist
     */
    public static function modinv(int $a, int $m): int {
        $origm = $m;
        if (gcd($a, $m) != 1) {
            // Inverse does not exist.
            return 0;
        }
        list($s, $t, $lasts, $lastt) = [1, 0, 0, 1];
        while ($m != 0) {
            $q = floor($a / $m);
            list($a, $m) = [$m, $a - $q * $m];
            list($s, $lasts) = [$lasts, $s - $q * $lasts];
            list($t, $lastt) = [$lastt, $t - $q * $lastt];
        }
        return $s < 0 ? $s + $origm : $s;
    }

    /**
     * Calculate the floating point remainder of the division of
     * the arguments, i. e. x - m * floor(x / m). There is no
     * canonical definition for this function; some calculators
     * use flooring (round down to nearest integer) and others
     * use truncation (round to nearest integer, but towards zero).
     * This implementation gives the same results as e. g. Wolfram Alpha.
     *
     * @param float $x the dividend
     * @param float $m the modulus
     * @return float remainder of $x modulo $m
     * @throws Exception
     */
    public static function fmod(float $x, float $m): float {
        if ($m === 0) {
            // FIXME: revise error message.
            throw new Exception(get_string('error_eval_numerical', 'qtype_formulas'));
        }
        return $x - $m * floor($x / $m);
    }

    /**
     * Calculate the probability of exactly $x successful outcomes for
     * $n trials under a binomial distribution with a probability of success
     * of $p.
     *
     * @param int $n number of trials
     * @param float $p probability of success for each trial
     * @param int $x number of successful outcomes
     *
     * @return float probability for exactly $x successful outcomes
     * @throws Exception
     */
    public static function binomialpdf(int $n, float $p, int $x): float {
        // Probability must be 0 <= p <= 1.
        if ($p < 0 || $p > 1) {
            // FIXME: revise error message.
            throw new Exception(get_string('error_eval_numerical', 'qtype_formulas'));
        }
        // Number of successful outcomes must be at least 0 and at most number of trials.
        if ($x < 0 || $x > $n) {
            // FIXME: revise error message.
            throw new Exception(get_string('error_eval_numerical', 'qtype_formulas'));
        }
        return self::ncr($n, $x) * $p ** $x * (1 - $p) ** ($n - $x);
    }

    /**
     * Calculate the probability of up to $x successful outcomes for
     * $n trials under a binomial distribution with a probability of success
     * of $p, known as the cumulative distribution function.
     *
     * @param int $n number of trials
     * @param float $p probability of success for each trial
     * @param int $x number of successful outcomes
     *
     * @return float probability for up to $x successful outcomes
     * @throws Exception
     */
    public static function binomialcdf(int $n, float $p, int $x): float {
        // Probability must be 0 <= p <= 1.
        if ($p < 0 || $p > 1) {
            // FIXME: revise error message.
            throw new Exception(get_string('error_eval_numerical', 'qtype_formulas'));
        }
        // Number of successful outcomes must be at least 0 and at most number of trials.
        if ($x < 0 || $x > $n) {
            // FIXME: revise error message.
            throw new Exception(get_string('error_eval_numerical', 'qtype_formulas'));
        }
        $res = 0;
        for ($i = 0; $i <= $x; $i++) {
            $res += self::binomialpdf($n, $p, $i);
        }
        return $res;
    }

    /**
     * Calculate the number of permutations when taking $r elements
     * from a set of $n elements.
     *
     * @param int $n the number of elements to choose from
     * @param int $r the number of elements to be rearranged
     * @return int
     */
    public static function npr(int $n, int $r): int {
        $n = (int)$n;
        $r = (int)$r;
        if ($r == 0 && $n == 0) {
            return 0;
        }
        return self::ncr($n, $r) * self::fact($r);
    }

    /**
     * Calculate the number of combination when taking $r elements
     * from a set of $n elements.
     *
     * @param int $n the number of elements to choose from
     * @param int $r the number of elements to be chosen
     * @return int
     */
    public static function ncr(int $n, int $r): int {
        $n = (int)$n;
        $r = (int)$r;
        if ($r > $n) {
            return 0;
        }
        if (($n - $r) < $r) {
            return self::ncr($n, ($n - $r));
        }
        $return = 1;
        for ($i = 0; $i < $r; $i++) {
            $return *= ($n - $i) / ($i + 1);
        }
        return $return;
    }

    /**
     * Calculate the greatest common divisor of two integers $a and $b
     * via the Euclidean algorithm.
     *
     * @param int $a first number
     * @param int $b second number
     * @return int
     */
    public static function gcd($a, $b) {
        if ($a < 0) {
            $a = abs($a);
        }
        if ($b < 0) {
            $b = abs($b);
        }
        if ($a == 0 && $b == 0) {
            return 0;
        }
        if ($a == 0 || $b == 0) {
            return $a + $b;
        }
        if ($a == $b) {
            return $a;
        }
        do {
            $rest = (int) $a % $b;
            $a = $b;
            $b = $rest;
        } while ($rest > 0);
        return $a;
    }

    /**
     * Calculate the least (non-negative) common multiple of two integers $a and $b
     * via the Euclidean algorithm.
     *
     * @param int $a first number
     * @param int $b second number
     * @return int
     */
    public static function lcm($a, $b) {
        if ($a == 0 || $b == 0) {
            return 0;
        }
        return $a * $b / self::gcd($a, $b);
    }
}
