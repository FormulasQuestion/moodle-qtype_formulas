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

namespace qtype_formulas;

/**
 * Parser for qtype_formulas
 *
 * @package    qtype_formulas
 * @copyright  2022 Philipp Imhof
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


/*

TODO:
* translate ^ to ** in certain contexts (backward compatibility)
* variables stack
* context -> already defined variables and their values
            + instantiated random values
            export (serialize) and import
* parsing and instantiation of random vars
* sets for random vars
* for loop

* possibly class RandomVariable -> instantiate() -> set one value with mt_rand

*/

class parser {
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
    public function __construct(array $tokenlist, bool $expressiononly = false, array $knownvariables = []) {
        $this->count = count($tokenlist);
        $this->tokenlist = $tokenlist;
        $this->variableslist = $knownvariables;

        // Check for unbalanced / mismatched parentheses. There will be some redundancy, because
        // the shunting yard algorithm will also do its own checks, but this check allows better
        // and easier error reporting.
        $this->check_unbalanced_parens();

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

            if ($type === token::IDENTIFIER) {
                $next = $this->peek(1);
                if ($next->type === token::OPERATOR && $next->value === '=') {
                    $this->statements[] = $this->parse_assignment();
                    $currenttoken = $this->peek();
                    continue;
                }
            } else if ($type === token::RESERVED_WORD && $value === 'for') {
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
     * Check whether all parenthesis are balanced. Otherweise, stop all further processing
     * and output an error message.
     *
     * @return void
     */
    private function check_unbalanced_parens(): void {
        $parenstack = [];
        foreach ($this->tokenlist as $token) {
            $type = $token->type;
            // All opening parens will have the 16-bit set, other tokens won't.
            if ($type & token::ANY_OPENING_PAREN) {
                $parenstack[] = $token;
            }
            // All closing parens will have the 32-bit set, other tokens won't.
            if ($type & token::ANY_CLOSING_PAREN) {
                $top = end($parenstack);
                // If stack is empty, we have a stray closing paren.
                if (!($top instanceof token)) {
                    $this->die("unbalanced parentheses, stray '{$token->value}' found", $token);
                }
                // Let's check whether the opening and closing parenthesis have the same type.
                // If they do, XORing them should leave just the 16- and the 32-bit. Otherwise,
                // we can stop here.
                if (($top->type ^ $type) !== 0b110000) {
                    $this->die(
                        "mismatched parentheses, '{$token->value}' is closing '{$top->value}' " .
                        "from row {$top->row} and column {$top->column}",
                        $token
                    );
                }
                array_pop($parenstack);
            }
        }
        // If the stack of parentheses is not empty now, we have an unmatched opening parenthesis.
        if (!empty($parenstack)) {
            $unmatched = end($parenstack);
            $this->die("unbalanced parenthesis, '{$unmatched->value}' is never closed", $unmatched);
        }
    }

    /**
     * Stop processing and indicate the human readable position (row/column) where the error occurred.
     *
     * @param string $message error message
     * @return void
     * @throws Exception
     */
    private function die(string $message, ?token $offendingtoken = null): never {
        if (is_null($offendingtoken)) {
            $offendingtoken = $this->tokenlist[$this->position];
        }
        throw new \Exception($offendingtoken->row . ':' . $offendingtoken->column . ':' . $message);
    }

    public function parse_assignment(): array {
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
                if ($type === token::IDENTIFIER) {
                    $currenttoken->type = token::VARIABLE;
                }
                break;
            }
            $nexttype = $nexttoken->type;
            $nextvalue = $nexttoken->value;

            // If the current token is a PREFIX and the next one is an IDENTIFIER, we will consider
            // that one as a FUNCTION. Otherwise, this is a syntax error.
            if ($type === token::PREFIX) {
                if ($nexttype === token::IDENTIFIER) {
                    $nexttype = ($nexttoken->type = token::FUNCTION);
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
            if ($type === token::IDENTIFIER) {
                if (!$this->is_known_variable($currenttoken) && $nexttype === token::OPENING_PAREN) {
                    $type = ($currenttoken->type = token::FUNCTION);
                } else {
                    $this->register_variable($currenttoken);
                    $type = ($currenttoken->type = token::VARIABLE);
                }
            }

            // We distinguish between normal arrays and a fixed-range interval as a short form
            // for e.g. [1,2,3,...,9] by writing [1:10]. This has to be prepared here.
            if ($type === token::OPENING_BRACKET) {
                // We do a simple analysis: any range MUST contain one or two colons and one more number
                // than colons. It MUST NOT contain other operators than an (unary) minus and MUST NOT
                // contain strings or argument separators. Our guess might be wrong, but then we just let
                // the error happen during evaluation.
                $lookahead = $nexttoken;
                $colons = 0;
                $numbers = 0;
                $i = 1;
                // Look ahead until we find the closing bracket. Or the end, but that would be bad syntax...
                while ($lookahead !== self::EOF && $lookahead->type !== token::CLOSING_BRACKET) {
                    $latype = $lookahead->type;
                    $lavalue = $lookahead->value;
                    if ($latype === token::OPERATOR && $lavalue === ':') {
                        $colons++;
                        $lookahead->value = ',';
                        $lookahead->type = token::ARG_SEPARATOR;
                    } else if ($latype === token::NUMBER) {
                        $numbers++;
                    } else if (
                        ($latype === token::OPERATOR && $lavalue !== '-') ||
                        (in_array($latype, [token::ARG_SEPARATOR, token::STRING]))
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
            if ($type === token::OPERATOR && $value === '?' && $nexttype === token::OPERATOR && $nextvalue === ':') {
                $this->die('syntax error: ternary operator missing middle part', $nexttoken);
            }

            // We do not allow two subsequent numbers, two subsequent strings or a string following a number
            // (and vice versa), because that's probably a typo and we do not know for sure what to do with them.
            // For numbers, it could be an implicit multiplication, but who knows...
            if (in_array($type, [token::NUMBER, token::STRING]) && in_array($nexttype, [token::NUMBER, token::STRING])) {
                $this->die('syntax error: did you forget to put an operator?', $nexttoken);
            }

            // We do not allow to subsequent commas, a comma following an opening parenthesis/bracket
            // or a comma followed by a closing parenthesis/bracket.
            $parenpluscomma = ($type & token::ANY_OPENING_PAREN) && $nexttype === token::ARG_SEPARATOR;
            $commaplusparen = $type === token::ARG_SEPARATOR && ($nexttype & token::ANY_CLOSING_PAREN);
            $twocommas = ($type === token::ARG_SEPARATOR && $nexttype === token::ARG_SEPARATOR);
            if ($parenpluscomma || $commaplusparen || $twocommas) {
                $this->die('syntax error: invalid use of separator token (,)', $nexttoken);
            }

            // If we're one token away from the end of the statement, we just read and discard the end-of-statement marker.
            if ($nexttype === token::END_OF_STATEMENT) {
                $this->read_next();
                break;
            }
            // Otherwise, let's read the next token and append it to the list of tokens for this statement.
            $currenttoken = $this->read_next();
            $assignment[] = $currenttoken;
        }
        return $assignment;
    }

    public function get_statements(): array {
        return $this->statements;
    }

    private function peek(int $skip = 0): ?token {
        if ($this->position < $this->count - $skip - 1) {
            return $this->tokenlist[$this->position + $skip + 1];
        }
        return self::EOF;
    }

    private function read_next(): ?token {
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
    public function parse_list(): array {
        // Consume the bracket.
        $currenttoken = $this->read_next();
        $bracketlevel = 1;
        $listelements = [];
        while ($bracketlevel > 0 && $currenttoken !== self::EOF) {
            $currenttoken = $this->peek();
            $type = $currenttoken->type;
            if ($type === token::OPENING_BRACKET) {
                $bracketlevel++;
                // Recursively parse the sublist. The opening bracked will be consumed there.
                $listelements[] = $this->parse_list();
                continue;
            } else if ($type === token::CLOSING_BRACKET) {
                $bracketlevel--;
                $this->read_next();
                return $listelements;
            } else if ($type === token::ARG_SEPARATOR) {
                $this->read_next();
                continue;
            } else {
                $listelements[] = $this->read_next();
            }
        }
        return $listelements;
    }

    // will be in evaluator class
    public function execute_function(string $funcname, array $params): void {
        if (in_array($funcname, $this->ownfunctions)) {
            call_user_func_array(__NAMESPACE__ . '\Functions::' . $funcname, $params);
        }
    }

    // FIXME doc
    private function is_known_variable(token $token): bool {
        return in_array($token->value, $this->variableslist);
    }

    private function register_variable(token $token): void {
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
