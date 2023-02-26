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
* variables stack
* context -> already defined variables and their values
            + instantiated random values
            export (serialize) and import
* parsing and instantiation of random vars
* arrays
* ranges / sets for random vars
* for loop
* prefix access to functions

* ternary operator in shunting yard:
  - shorthand foo ? : bar  === foo ? foo : bar
  - <condition> ? <true-value> : <false-value>
  - precedence is low, only assignment = is lower, so <condition> left delimited by = or (
  - <true-value>, if any, always enclosed between ? and :
  - <false-value> left-delimited by : and right-delimited by ) or end-of-statement (; or EOF)
  - if chained, <false-value> possibly right-delimited by ?

  -> false-value right delimited by ; or EOF or ), may include (<ternary>) in parens
 -> <condition> ? <true-value> : <else-condition> ? <else-true-value> : <else-value>


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
        // FIXME: allow constant PI and pi instead of pi()
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
                // read until {  --> head of loop
                // recursively read the body (may contain other loops)
            } else {
                // FIXME: die with error "invalid statement"
                print("other token: $value\n");
            }

            // Advance.
            $currenttoken = $this->read_next();
        }
        return;

        while ($currenttoken !== self::EOF) {
            $type = $currenttoken->type;
            $value = $currenttoken->value;
            // If we are at the start of a list (array), we combine all relevant tokens into one
            // and append it to the current statement.
            if ($type === Token::OPENING_BRACKET) {
                // An opening [ could also be the start of a range. In that case, the
                // next but one token must be a colon.
                $nextbutone = $this->peek(1);
                if ($nextbutone->type === Token::OPERATOR && $nextbutone->value === ':') {
                    $currentstatement[] = $this->parse_range();
                } else {
                    $currentstatement[] = $this->parse_list();
                }
                $this->statements[] = $currentstatement;
                return;
            }
            $currenttoken = $this->read_next();
        }
        return;

        // The token list is currently in a "raw" form, e. g. an array [1, 2, 3] is built from
        // its opening and closing bracket, the three number tokens and the two interpunction tokens.
        // Before we continue, we must recompose those tokens to form the true syntax elements, e. g.
        // ranges, sets and lists.
        $cleanedlist = [];
        $bracelevel = 0;
        $bracketlevel = 0;
        $count = count($tokenlist);
        $currentstatement = [];
        for ($i = 0; $i++; $i < $count) {
            $currenttoken = $tokenlist[$i];
            if ($i < $count - 1) {
                $followedby = $tokenlist[$i + 1];
            } else {
                $followedby = null;
            }
            $type = $currenttoken->type;
            $value = $currenttoken->value;
            // If we are at the start of a list (array), we combine all relevant tokens into one
            // and append it to the current statement.
            if ($type === Token::OPENING_BRACKET) {
                print('diving into list');
                $currentstatement[] = $this->parse_list();
                $this->statements[] = $currentstatement;
                return;
            }

            // If we have a PREFIX token (\) followed by an identifier, this is a reference to a function.
            // We drop the prefix, append a FUNCTION token to the current statement and add one to the index.
            if ($type === Token::PREFIX && $followedby->type === Token::IDENTIFIER) {
                $followedby->type = Token::FUNCTION;
                $currentstatement[] = $followedby;
                $i++;
                continue;
            }

        }
    }

    // FIXME probably possible to remove parameter, because we always discard the end marker
    public function parse_assignment($discardendmarker = true) {
        // FIXME: take into account PREFIX \ modifier
        // FIXME: implicit multiplication
        // FIXME: arrays

        // Start by reading the first token. If we are here, that means it was an IDENTIFIER.
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
                // FIXME: maybe find a more elegant solution
                if ($type === Token::IDENTIFIER) {
                    $currenttoken->type = Token::VARIABLE;
                }
                break;
            }
            $nexttype = $nexttoken->type;
            $nextvalue = $nexttoken->value;
            // If the current token is an IDENTIFIER, we will classify it as a VARIABLE or a FUNCTION.
            // The criteria is as follows:
            // - if is is in the list of known variables, it must be a VARIABLE
            // - if it is not a known variable, but followed by an = operator, it must be a VARIABLE
            // - if it is not a known variable, but followed by a ( symbol, we assume it is a FUNCTION
            // - if it is not a known variable and not followed by a ( symbol, we assume it is a VARIABLE
            if ($type === Token::IDENTIFIER) {
                if (!$this->is_known_variable($currenttoken) && $nexttype === Token::OPENING_PAREN) {
                    print("changing token {$currenttoken->value}'s type to FUNCTION\n");
                    $type = ($currenttoken->type = Token::FUNCTION);

                } else {
                    $this->register_variable($currenttoken);
                    print("changing token {$currenttoken->value}'s type to VARIABLE\n");
                    $type = ($currenttoken->type = Token::VARIABLE);
                }
            }
            // Add implicit multiplication signs:
            // if the current token is an VARIABLE or a NUMBER or a ) symbol *and*
            // the next token is an IDENTIFIER or a NUMBER or a ( symbol
            // we insert a multiplication sign
            if (in_array($type, [Token::NUMBER, Token::VARIABLE, Token::CLOSING_PAREN])) {
                if (in_array($nexttype, [Token::NUMBER, Token::IDENTIFIER, Token::OPENING_PAREN])) {
                    $this->insert_implicit_multiplication();
                }
            }

            // We read up to an end-of-statement marker.
            if ($nexttype === Token::END_OF_STATEMENT) {
                // FIXME: get rid of this and always discard the end marker
                if ($discardendmarker) {
                    $this->read_next();
                }
                break;
            }
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

    private function insert_implicit_multiplication() {
        array_splice($this->tokenlist, $this->position + 1, 0, [new Token(Token::OPERATOR, '*')]);
        $this->count++;
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
            $value = $currenttoken->value;
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
    }
}
