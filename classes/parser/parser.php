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
 * Parser for qtype_formulas
 *
 * @package    qtype_formulas
 * @copyright  2022 Philipp Imhof
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace qtype_formulas;

/*

TODO:
* translate ^ to ** in certain contexts (backward compatibility)
* variables stack
* context -> already defined variables and their values
            + instantiated random values
            export (serialize) and import
* parsing and instantiation of random vars
* ranges / sets for random vars
* for loop

* possibly class RandomVariable -> instantiate() -> set one value with mt_rand

*/

class Parser {
    const EOF = null;

    /** @var array list of all (raw) tokens */
    private $tokenlist;

    /** @var integer number of (raw) tokens */
    private $count;

    /** @var integer position w.r.t. list of (raw) tokens */
    private $position = -1;

    /** @var array list of all (parsed) statements */
    public $statements = [];

    private $ownfunctions = ['fact'];
    private $variableslist = [];

    /**
     * FIXME Undocumented function
     *
     * @param [type] $tokenlist
     * @param boolean $expressiononly will be used to parse an expression, e. g. an answer or calculation
     * @param [type] $knownvariables
     */
    public function __construct($tokenlist, $expressiononly = false, $knownvariables = []) {
        $this->count = count($tokenlist);
        $this->tokenlist = $tokenlist;
        $this->variableslist = $knownvariables;
        $currenttoken = $this->peek();

        // If we parse a single expression, we can go ahead directly.
        if ($expressiononly) {
            $this->statements[] = $this->parse_assignment($tokenlist);
            return;
        }

        // FIXME maybe add if / elseif / else clauses in the future?
        while ($currenttoken !== self::EOF) {
            $type = $currenttoken->type;
            $value = $currenttoken->value;

            if ($type === Token::IDENTIFIER) {
                $next = $this->peek(1);
                if ($next->type === Token::OPERATOR && $next->value === '=') {
                    $this->statements[] = $this->parse_assignment();
                    $currenttoken = $this->peek();
                    continue;
                }
            } else if ($type === Token::RESERVED_WORD && $value === 'for') {
                $this->parse_forloop();
                // read until {  --> head of loop
                // recursively read the body (may contain other loops)
            } else {
                $this->die("invalid statement, starting with: '$value'", $currenttoken);
            }

            // Advance.
            $currenttoken = $this->read_next();
        }
    }

    /**
     * Stop processing and indicate the human readable position (row/column) where the error occurred.
     *
     * @param string $message error message
     * @return void
     * @throws Exception
     */
    private function die($message, $offendingtoken = null) {
        if (is_null($offendingtoken)) {
            $offendingtoken = $this->tokenlist[$this->position];
        }
        throw new \Exception($offendingtoken->row . ':' . $offendingtoken->column . ':' . $message);
    }

    public function parse_assignment() {
        // Start by reading the first token.
        $currenttoken = $this->read_next();
        $assignment = [$currenttoken];

        while ($currenttoken !== self::EOF) {
            $type = $currenttoken->type;
            $value = $currenttoken->value;
            $nexttoken = $this->peek();
            if ($nexttoken === self::EOF) {
                // The last identifier of a statement cannot be a FUNCTION, because it would have
                // to be followed by parens. We don't register it as a known variable, because it
                // is not assigned a value at this moment.
                if ($type === Token::IDENTIFIER) {
                    $currenttoken->type = Token::VARIABLE;
                }
                break;
            }
            $nexttype = $nexttoken->type;
            $nextvalue = $nexttoken->value;

            // If the current token is a PREFIX and the next one is an IDENTIFIER, we will consider
            // that one as a FUNCTION. Otherwise, this is a syntax error.
            if ($type === Token::PREFIX) {
                if ($nexttype === Token::IDENTIFIER) {
                    $nexttype = ($nexttoken->type = Token::FUNCTION);
                } else {
                    $this->die('syntax error: invalid use of prefix character \\');
                }
            }

            // If the current token is an IDENTIFIER, we will classify it as a VARIABLE or a FUNCTION.
            // The criteria is as follows:
            // - if is is in the list of known variables and not preceded by the PREFIX, it must be a VARIABLE
            // - if it is not a known variable, but followed by a ( symbol, we assume it is a FUNCTION
            // - if it is not a known variable and not followed by a ( symbol, we assume it is a VARIABLE
            // Examples for the last point include identifiers followed by = for assignment or [ for indexation.
            if ($type === Token::IDENTIFIER) {
                if (!$this->is_known_variable($currenttoken) && $nexttype === Token::OPENING_PAREN) {
                    $type = ($currenttoken->type = Token::FUNCTION);
                } else {
                    $this->register_variable($currenttoken);
                    $type = ($currenttoken->type = Token::VARIABLE);
                }
            }

            // We distinguish between normal arrays and a fixed-range interval as a short form
            // for e.g. [1,2,3,...,9] by writing [1:10]. This has to be prepared here.
            if ($type === Token::OPENING_BRACKET) {
                // We do a simple analysis: any range MUST contain one or two colons and one more number
                // than colons. It MUST NOT contain other operators than an (unary) minus and MUST NOT
                // contain strings or argument separators. Our guess might be wrong, but then we just let
                // the error happen during evaluation.
                $lookahead = $nexttoken;
                $colons = 0;
                $numbers = 0;
                $i = 1;
                while ($lookahead !== self::EOF && $lookahead->type !== Token::CLOSING_BRACKET) {
                    $latype = $lookahead->type;
                    $lavalue = $lookahead->value;
                    if ($latype === Token::OPERATOR && $lavalue === ':') {
                        $colons++;
                        $lookahead->value = ',';
                        $lookahead->type = Token::ARG_SEPARATOR;
                    } else if ($latype === Token::NUMBER) {
                        $numbers++;
                    } else if (
                        ($latype === Token::OPERATOR && $lavalue !== '-') ||
                        (in_array($latype, [Token::ARG_SEPARATOR, Token::STRING]))
                      ) {
                        $colons = 0;
                        $numbers = 0;
                        break;
                    }
                    $lookahead = $this->peek($i);
                    $i++;
                }
                // Change the opening bracket's value. It will be interpreted by the shunting yard
                // algorithm.
                if (in_array($colons, [1, 2]) && $numbers === $colons + 1) {
                    $currenttoken->value = '[r';
                }
            }

            // We do not currently allow the short ternary operator aka "Elvis operator" (a ?: b)
            // which is a short form for (a ? a : b).
            if ($type === Token::OPERATOR && $value === '?' && $nexttype === Token::OPERATOR && $nextvalue === ':') {
                $this->die('syntax error: ternary operator missing middle part', $nexttoken);
            }

            // We do not allow two subsequent numbers, two subsequent strings or a string following a number
            // (and vice versa), because that's probably a typo and we do not know for sure what to do with them.
            // For numbers, it could be an implicit multiplication, but who knows...
            if (in_array($type, [Token::NUMBER, Token::STRING]) && in_array($nexttype, [Token::NUMBER, Token::STRING])) {
                $this->die('syntax error: did you forget to put an operator?', $nexttoken);
            }

            // We do not allow to subsequent commas, a comma following an opening parenthesis/bracket
            // or a comma followed by a closing parenthesis/bracket.
            if (
                (in_array($type, [Token::OPENING_PAREN, Token::OPENING_BRACKET]) && $nexttype === Token::ARG_SEPARATOR) ||
                ($type === Token::ARG_SEPARATOR && in_array($nexttype, [Token::ARG_SEPARATOR, Token::CLOSING_BRACKET, Token::CLOSING_PAREN]))
            ) {
                $this->die('syntax error: invalid use of separator token (,)', $nexttoken);
            }

            // If we're one token away from the end of the statement, we just read and discard the end-of-statement marker.
            if ($nexttype === Token::END_OF_STATEMENT) {
                $this->read_next();
                break;
            }
            // Otherwise, let's read the next token and append it to the list of tokens for this statement.
            $currenttoken = $this->read_next();
            $assignment[] = $currenttoken;
        }
        return $assignment;
    }

    public function get_statements() {
        return $this->statements;
    }

    private function peek($skip = 0) {
        if ($this->position < $this->count - $skip - 1) {
            return $this->tokenlist[$this->position + $skip + 1];
        }
        return self::EOF;
    }

    private function read_next() {
        $nexttoken = $this->peek();
        if ($nexttoken !== self::EOF) {
            $this->position++;
        }
        return $nexttoken;
    }

    // FIXME
    public function parse_range() {
        // mixing of type 1 / type 2 is not possible for ranges

        // type 1:
        // opening bracket
        // number
        // colon
        // number
        // optional: colon + step
        // closing bracket

        // type 2:
        // opening bracket
        // number
        // comma
        // ...repeat..
        // number
        // closing bracket
    }

    // FIXME
    public function parse_set() {

    }

    // FIXME
    public function parse_list() {
        // Consume the bracket.
        $currenttoken = $this->read_next();
        $bracketlevel = 1;
        $listelements = [];
        while ($bracketlevel > 0 && $currenttoken !== self::EOF) {
            $currenttoken = $this->peek();
            $type = $currenttoken->type;
            if ($type === Token::OPENING_BRACKET) {
                $bracketlevel++;
                // Recursively parse the sublist. The opening bracked will be consumed there.
                $listelements[] = $this->parse_list();
                continue;
            } else if ($type === Token::CLOSING_BRACKET) {
                $bracketlevel--;
                $this->read_next();
                return $listelements;
            } else if ($type === Token::ARG_SEPARATOR) {
                $this->read_next();
                continue;
            } else {
                $listelements[] = $this->read_next();
            }
        }
        return $listelements;
    }

    // will be in evaluator class
    public function execute_function($funcname, $params) {
        if (in_array($funcname, $this->ownfunctions)) {
            call_user_func_array(__NAMESPACE__ . '\Functions::' . $funcname, $params);
        }
    }

    // FIXME doc
    private function is_known_variable($token) {
        return in_array($token->value, $this->variableslist);
    }

    private function register_variable($token) {
        if ($this->is_known_variable($token)) {
            return;
        }
        $this->variableslist[] = $token->value;
    }

    public function parse_ifelse() {
    }

    /**
     * ... FIXME ...
     * Notes on the syntax of for loops:
     * - The variable will NOT be local to the loop. It will be visible in the entire scope and keep its last value.
     * - It is possible to use a variable that has already been defined. In that case, it will be overwritten.
     * - It is possible to use variables for the start and/or end of the range and also for the step size.
     * - The range is evaluated ONLY ONCE at the initialization of the loop. So if you use variables in the range
     * and you change those variables inside the loop, this will have no effect.
     * - It is possible to change the value of the iterator variable inside the loop. However, at each iteration
     * it will be set to the next planned value regardless of what you did to it.
     *
     * @return void
     */
    public function parse_forloop() {
        $variable = null;
        $from = 0;
        $to = 0;
        $step = 1;
        $statements = [];

        // for
        // opening paren
        // identifier
        // colon
        // bracket --> parse range
        // closing paren
        // opening brace
        // statements --> recursive parsing (can contain other for loops)
        // closing brace
    }
}
