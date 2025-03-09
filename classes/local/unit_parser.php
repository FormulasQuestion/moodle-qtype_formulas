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

namespace qtype_formulas\local;

/*

Notes about current implementation:

- only 2 operators allowed: ^ for exponentiation and / for division
- only one / allowed
- no * allowed
- no parens allowed, except in exponent or around the *entire* denominator
- right side of / is considered in parens, even if not written, e.g. J/m*K --> J / (m*K)
- only units, no numbers except for exponents
- positive or negative exponents allowed
- negative exponents allowed with or without parens
- same unit not allowed more than once

Future implementation, must be 100% backwards compatible

- allow parens everywhere
- allow * for explicit multiplication of units
- still only allow one /
- still not allow same unit more than once
- if * is used after /, assume implicit parens, e. g. J / m * K --> J / (m * K)
- do not allow operators other than *, / and ^ as well as unary - (in exponents only)
- allow ** instead of ^
- for the moment: disallow exponent after closing paren to avoid things like (m/s)^2


Syntax for unit conversion rules

Type 1: SI prefixes

<base unit> : <prefix1> <prefix2> ... <prefix-n> ;

- at least one valid prefix
- ; at end, if more statements follow
- base unit single token, no number

example:

m : k da d c m u µ;
s : m u µ;

Type 2: conversion factors / arbitrary prefixes

[<number>] <base unit> = <number> <target unit 1> [ = <number> <target unit 2> ...] ;

- if first number not given --> 1
- all further declarations always relative to base unit

min = 60 s;
h = 60 min;


*/


/**
 * Parser for units for qtype_formulas
 *
 * @package    qtype_formulas
 * @copyright  2025 Philipp Imhof
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class unit_parser extends parser {

    // FIXME: allow unicode chars for micro and ohm in lexer; maybe disallow for variable names (check during assignment)

    const SI_PREFIX_FACTORS = [
        'd' => 0.1,
        'c' => 0.01,
        'm' => 0.001,
        'u' => 1e-6,
        // For convenience, we also allow U+00B5 MICRO SIGN.
        "\u{00B5}" => 1e-6,
        // For convenience, we also allow U+03BC GREEK SMALL LETTER MU.
        "\u{03BC}" => 1e-6,
        'n' => 1e-9,
        'p' => 1e-12,
        'f' => 1e-15,
        'a' => 1e-18,
        'z' => 1e-21,
        'y' => 1e-24,
        'r' => 1e-27,
        'q' => 1e-30,
        'da' => 10,
        'h' => 100,
        'k' => 1000,
        'M' => 1e6,
        'G' => 1e9,
        'T' => 1e12,
        'P' => 1e15,
        'E' => 1e18,
        'Z' => 1e21,
        'Y' => 1e24,
        'R' => 1e27,
        'Q' => 1e30,
    ];

    const DEFAULT_PREFIXES = [
        's' => ['m', 'u', 'n', 'p', 'f'],
        'm' => ['k', 'da', 'c', 'd', 'm', 'u', 'n', 'p', 'f'],
        'g' => ['k', 'm', 'u', 'n', 'p', 'f'],
        'A' => ['m', 'u', 'n', 'p', 'f'],
        'mol' => ['m', 'u', 'n', 'p'],
        'K' => ['m', 'u', 'n', 'k', 'M'],
        'cd' => ['m', 'k', 'M', 'u', 'G'],
        'N' => ['M', 'k', 'm', 'u', 'n', 'p', 'f'],
        'J' => ['M', 'G', 'T', 'P', 'k', 'm', 'u', 'n', 'p', 'f'],
        'eV' => ['M', 'G', 'T', 'P', 'k', 'm', 'u'],
        'W' => ['M', 'G', 'T', 'P', 'k', 'm', 'u', 'n', 'p', 'f'],
        'Pa' => ['M', 'G', 'T', 'P', 'k', 'h'],
        'Hz' => ['M', 'G', 'T', 'P', 'E', 'k'],
        'C' => ['k', 'm', 'u', 'n', 'p', 'f'],
        'V' => ['M', 'G', 'k', 'm', 'u', 'n', 'p', 'f'],
        'ohm' => ['M', 'G', 'T', 'P', 'k', 'm', 'u'],
        'F' => ['m', 'u', 'n', 'p', 'f'],
        'T' => ['k', 'm', 'u', 'n', 'p'],
        'H' => ['k', 'm', 'u', 'n', 'p'],
    ];

    const DEFAULT_SPECIAL_RULES = [
        // For convenience, we also allow U+2126 OHM SIGN.
        "\u{2126}" => ['ohm' => 1],
        // For convenience, we also allow U+03A9 GREEK CAPITAL LETTER OMEGA.
        "\u{03A9}" => ['ohm' => 1],
        'min' => ['s' => 60],
        'h' => ['s' => 3600],
        'J' => ['eV' => 6.24150947e+18],
    ];

    /** @var array list of used units */
    private array $unitlist = [];

    /** @var string list of all units with their prefixes, allowing to find base units quickly */
    private string $baseunitmap = '';

    /**
     * Create a unit parser class and have it parse a given input. The input can be given as a string, in
     * which case it will first be sent to the lexer. If that step has already been made, the constructor
     * also accepts a list of tokens.
     *
     * @param string|array $tokenlist list of tokens as returned from the lexer or input string
     */
    public function __construct($tokenlist) {
        // If the input is given as a string, run it through the lexer first.
        if (is_string($tokenlist)) {
            $lexer = new lexer($tokenlist);
            $tokenlist = $lexer->get_tokens();
        }
        $this->tokenlist = $tokenlist;

        // Check for unbalanced / mismatched parentheses.
        $this->check_parens();

        // Perform basic syntax check, including classification of IDENTIFIER tokens
        // to UNIT tokens.
        $this->check_syntax();

        // Run the tokens through an adapted shunting yard algorithm to bring them into
        // RPN notation.
        $this->statements[] = shunting_yard::unit_infix_to_rpn($this->tokenlist);

        // Build base unit map that will be used to find the base unit for a given unit,
        // e. g. find s from ms or Pa from hPa.
        $this->build_base_unit_map();
    }

    protected function check_syntax(): void {
        // Whether we have already seen a slash or a unit and whether we are in an exponent.
        $seenslash = false;
        $seenunit = false;
        $inexponent = false;
        foreach ($this->tokenlist as $token) {
            // The use of functions is not permitted in units, so all identifiers will be classified
            // as UNIT tokens.
            if ($token->type === token::IDENTIFIER) {
                // If inside an exponent, only numbers (and maybe the unary minus) are allowed.
                if ($inexponent) {
                    $this->die(get_string('error_unexpectedtoken', 'qtype_formulas', $token->value), $token);
                }
                // The same unit must not be used more than once.
                if ($this->has_unit_been_used($token)) {
                    $this->die('Unit already used: ' . $token->value, $token);
                }
                $this->unitlist[] = $token->value;
                $token->type = token::UNIT;
                $seenunit = true;
                continue;
            }

            // Do various syntax checks for operators. We do them separately in order to allow
            // for more specific error messages, if needed.
            if ($token->type === token::OPERATOR) {
                // We can only accept an operator if there has been at least one unit before.
                if (!$seenunit) {
                    $this->die(get_string('error_unexpectedtoken', 'qtype_formulas', $token->value), $token);
                }
                // The only operators allowed are exponentiation, multiplication, division and the unary minus.
                // Note that the caret (^) always means exponentiation in the context of units.
                if (!in_array($token->value, ['^', '**', '/', '*', '-'])) {
                    $this->die(get_string('error_unexpectedtoken', 'qtype_formulas', $token->value), $token);
                }
                // The unary minus is only allowed inside an exponent.
                if ($token->value === '-' && !$inexponent) {
                    $this->die(get_string('error_unexpectedtoken', 'qtype_formulas', $token->value), $token);
                }
                // Only the unary minus is allowed inside an exponent.
                if ($inexponent && $token->value !== '-') {
                    $this->die(get_string('error_unexpectedtoken', 'qtype_formulas', $token->value), $token);
                }
                if ($token->value === '^' || $token->value === '**') {
                    $inexponent = true;
                }
                if ($token->value === '/') {
                    if ($seenslash) {
                        $this->die(get_string('error_unexpectedtoken', 'qtype_formulas', $token->value), $token);
                    }
                    $seenslash = true;
                }
                continue;
            }

            // Numbers can only be used as exponents and exponents must always be integers.
            if ($token->type === token::NUMBER) {
                if (!$inexponent) {
                    $this->die(get_string('error_unexpectedtoken', 'qtype_formulas', $token->value), $token);
                }
                if (intval($token->value) != $token->value) {
                    $this->die(get_string('error_integerexpected', 'qtype_formulas', $token->value), $token);
                }
                // Only one number is allowed in an exponent, so after the number the
                // exponent must be finished.
                $inexponent = false;
                continue;
            }

            // Parentheses are allowed, but we don't have to do anything with them now.
            if (in_array($token->type, [token::OPENING_PAREN, token::CLOSING_PAREN])) {
                continue;
            }

            // All other tokens are not allowed.
            $this->die(get_string('error_unexpectedtoken', 'qtype_formulas', $token->value), $token);
        }

        // The last token must be a number, a unit or a closing parenthesis.
        $finaltoken = end($this->tokenlist);
        if (!in_array($finaltoken->type, [token::UNIT, token::NUMBER, token::CLOSING_PAREN])) {
            $this->die(get_string('error_unexpectedtoken', 'qtype_formulas', $token->value), $token);
        }
    }

    protected function build_base_unit_map(): void {
        // First, we add all the built-in default prefix rules.
        foreach (self::DEFAULT_PREFIXES as $base => $prefixes) {
            foreach ($prefixes as $prefix) {
                $this->baseunitmap .= '|' . $prefix . $base . ':' . $base;
            }
        }

        // Next, the built-in special prefix rules.


        // Finally, we add user-defined rules.
    }

    protected function find_base_unit(string $unit): string {
        // Example, must be built from config / rules. Format "pipe - unit with prefix - colon - base unit".
        // Our definitions first, user's definition last.
        $map = '|s:s|ms:s|us:s|µs:s|cm:m|dm:m|hPa:Pa|kg:g';

        $matches = [];
        preg_match_all('/\|' . $unit . ':([^|]+)/', $map, $matches);

        // Array $matches has two entries, $matches[0] are full pattern matches (e.g. '|ms:s') and
        // $matches[1] are matches of base units. If there are no matches at all, the unit was not found.
        // This cannot normally happen. If it does, we return the unit as-is.
        if (count($matches[1]) === 0) {
            return $unit;
        }

        // In all other cases, we return the last possible match. In most cases there will be only one,
        // but if there are more than one, the user-defined unit should be taken.
        return end($matches[1]);
    }

    /**
     * Check whether a given unit has already been used.
     *
     * @param token $token token containing the unit
     * @return bool
     */
    protected function has_unit_been_used(token $token): bool {
        return in_array($token->value, $this->unitlist);
    }

    /**
     * Check whether all parentheses are balanced and whether only round parens are used.
     * Otherweise, stop all further processing and output an error message.
     *
     * @return void
     */
    protected function check_parens(): void {
        $parenstack = [];
        foreach ($this->tokenlist as $token) {
            $type = $token->type;
            // We only allow round parens.
            if (($token->type & token::ANY_PAREN) && !($token->type & token::OPEN_OR_CLOSE_PAREN)) {
                $this->die(get_string('error_unexpectedtoken', 'qtype_formulas', $token->value), $token);
            }
            if ($type === token::OPENING_PAREN) {
                $parenstack[] = $token;
            }
            if ($type === token::CLOSING_PAREN) {
                $top = end($parenstack);
                // If stack is empty, we have a stray closing paren.
                if (!($top instanceof token)) {
                    $this->die(get_string('error_strayparen', 'qtype_formulas', $token->value), $token);
                }
                array_pop($parenstack);
            }
        }
        // If the stack of parentheses is not empty now, we have an unmatched opening parenthesis.
        if (!empty($parenstack)) {
            $unmatched = end($parenstack);
            $this->die(get_string('error_parennotclosed', 'qtype_formulas', $unmatched->value), $unmatched);
        }
    }

    /**
     * Translate the given input into a string that can be understood by the legacy unit parser, i. e.
     * following all syntax rules. This allows keeping the old unit conversion system in place until
     * we are readyd to eventually replace it.
     *
     * @return string
     */
    public function get_legacy_unit_string(): string {
        $stack = [];

        foreach ($this->statements[0] as $token) {
            // Write numbers and units to the stack.
            if (in_array($token->type, [token::UNIT, token::NUMBER])) {
                $value = $token->value;
                if (is_numeric($value) && $value < 0) {
                    $value = '(' . strval($value) . ')';
                }
                $stack[] = $value;
            }

            // Operators take arguments from stack and stick them together in the appropriate way.
            if ($token->type === token::OPERATOR) {
                $op = $token->value;
                if ($op === '**') {
                    $op = '^';
                }
                if ($op === '*') {
                    $op = ' ';
                }
                $second = array_pop($stack);
                $first = array_pop($stack);
                // With the new syntax, it is possible to write e.g. (m/s^2)*kg. In older versions,
                // everything coming after the / operator will be considered a part of the denominator,
                // so the only way to get the kg into the numerator is to reorder the units and
                // write them as kg*m/s^2. Long story short: if there is a division, it must come last.
                // Note that the syntax currently does not allow more than one /, so we do not need
                // a more sophisticated solution.
                if (strpos($first, '/') !== false) {
                    list($second, $first) = [$first, $second];
                }
                // Legacy syntax allowed parens around the entire denominator, so we do that unless the
                // denominator is just one unit.
                if ($op === '/' && !preg_match('/^[A-Za-z]+$/', $second)) {
                    $second = '(' . $second . ')';
                }
                $stack[] = $first . $op . $second;
            }
        }

        return implode('', $stack);
    }
}
