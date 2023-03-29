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

* special variables like _err and _relerr; _0, _1 etc., _a, _r and _d:
*   -> must not be LHS in assignment, can only be used in certain contexts
* variables stack
* context -> already defined variables and their values
            + instantiated random values
            export (serialize) and import
* parsing and instantiation of random vars
* units
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
     * @param [type] $knownvariables
     */
    public function __construct(array $tokenlist, array $knownvariables = []) {
        $this->count = count($tokenlist);
        $this->tokenlist = $tokenlist;
        $this->variableslist = $knownvariables;

        // Check for unbalanced / mismatched parentheses. There will be some redundancy, because
        // the shunting yard algorithm will also do some checks on its own, but doing it here allows better
        // and faster error reporting.
        $this->check_unbalanced_parens();

        // Go through all tokens and read either general expressions (assignments or expressions)
        // or for loops.
        $currenttoken = $this->peek();
        while ($currenttoken !== self::EOF) {
            $this->statements[] = $this->parse_the_right_thing($currenttoken);
            $currenttoken = $this->peek();
        }
    }

    private function parse_the_right_thing(token $token) {
        // FIXME maybe add if / elseif / else clauses in the future?
        if ($token->type === token::RESERVED_WORD && $token->value === 'for') {
            return $this->parse_forloop();
        } else {
            return $this->parse_general_expression();
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

    /*public function parse_answer_expression(): expression {
        // Walk through all tokens and search for a ^ operator (XOR). In order to maintain backwards
        // compatibility, it will be replaced by the ** operator (exponentiation).
        $currenttoken = $this->peek();
        $i = 1;
        while ($currenttoken !== self::EOF) {
            if ($currenttoken->type === token::OPERATOR && $currenttoken->value === '^') {
                $currenttoken->value = '**';
            }
            $currenttoken = $this->peek($i);
            $i++;
        }
        // Now that this is done, we can parse the expression normally.
        return $this->parse_general_expression();
    }*/

    public function parse_general_expression(?int $stopat = null): expression {
        // Start by reading the first token.
        $currenttoken = $this->read_next();
        $expression = [$currenttoken];

        while ($currenttoken !== self::EOF && $currenttoken->type !== $stopat) {
            $type = $currenttoken->type;
            $value = $currenttoken->value;
            $nexttoken = $this->peek();
            if ($nexttoken === self::EOF) {
                // The last token must not be an OPERATOR, a PREFIX, an ARG_SEPARATOR or a RANGE_SEPARATOR.
                if (in_array($type, [token::OPERATOR, token::PREFIX, token::ARG_SEPARATOR, token::RANGE_SEPARATOR])) {
                    $this->die("syntax error: unexpected end of expression after '$value'", $currenttoken);
                }
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
            // The criteria are as follows:
            // - if is is in the list of known variables and not preceded by the PREFIX, it must be a VARIABLE
            // - if it is not a known variable, but followed by a ( symbol, we assume it is a FUNCTION
            // - if it is not a known variable and not followed by a ( symbol, we assume it is a VARIABLE
            // Examples for the last point include identifiers followed by = for assignment or [ for indexation.
            if ($type === token::IDENTIFIER) {
                if (!$this->is_known_variable($currenttoken) && $nexttype === token::OPENING_PAREN) {
                    $type = ($currenttoken->type = token::FUNCTION);
                } else {
                    // The identfier pi, if used like a variable, will be classified as CONSTANT.
                    if ($value === 'pi') {
                        $type = ($currenttoken->type = token::CONSTANT);
                        $value = ($currenttoken->value = 'π');
                    } else {
                        $type = ($currenttoken->type = token::VARIABLE);
                        $this->register_variable($currenttoken);
                    }
                }
            }

            // If we have a RANGE_SEPARATOR (:) token, we look ahead until we find a closing brace
            // or closing bracket, because ranges must not be used outside of sets or lists.
            // As we know all parentheses are balanced, it is enough to look for the closing one.
            if ($type === token::RANGE_SEPARATOR) {
                $lookahead = $nexttoken;
                $i = 1;
                while ($lookahead !== self::EOF) {
                    if (in_array($lookahead->type, [token::CLOSING_BRACE, token::CLOSING_BRACKET])) {
                        break;
                    }
                    $lookahead = $this->peek($i);
                    $i++;
                }
                // If we had to go all the way until the end of the token list, the range was not
                // used inside a list or a set.
                if ($lookahead === self::EOF) {
                    $this->die('syntax error: ranges can only be used in {} or []', $currenttoken);
                }
            }

            // Check syntax for ternary operators:
            // We do not currently allow the short ternary operator aka "Elvis operator" (a ?: b)
            // which is a short form for (a ? a : b). Also, if we do not find a corresponding : part,
            // we die with a syntax error.
            if ($type === token::OPERATOR && $value === '?') {
                if ($nexttype === token::OPERATOR && $nextvalue === ':') {
                    $this->die('syntax error: ternary operator missing middle part', $nexttoken);
                }
                $latype = $nexttype;
                $lavalue = $nextvalue;
                $i = 1;
                // Look ahead until we find the corresponding : part.
                while ($latype !== token::OPERATOR && $lavalue !== ':') {
                    // We have a syntax error, if...
                    // - we come to another ?
                    // - we come to an END_OF_STATEMENT marker
                    // - we reach the end of the token list
                    // before having seen the : part.
                    $anotherquestionmark = ($latype === token::OPERATOR &&  $lavalue === '?');
                    $endofstatement = ($latype === token::END_OF_STATEMENT);
                    $endoflist = ($i + $this->position >= $this->count);
                    if ($anotherquestionmark || $endofstatement || $endoflist) {
                        $this->die('syntax error: missing : part for ternary operator', $currenttoken);
                    }
                    $lookahead = $this->peek($i);
                    $latype = $lookahead->type;
                    $lavalue = $lookahead->value;
                    $i++;
                }
            }

            // We do not allow two subsequent numbers, two subsequent strings or a string following a number
            // (and vice versa), because that's probably a typo and we do not know for sure what to do with them.
            // For numbers, it could be an implicit multiplication, but who knows...
            if (in_array($type, [token::NUMBER, token::STRING]) && in_array($nexttype, [token::NUMBER, token::STRING])) {
                $this->die('syntax error: did you forget to put an operator?', $nexttoken);
            }

            // We do not allow to subsequent commas, a comma following an opening parenthesis
            // or a comma followed by a closing parenthesis.
            $parenpluscomma = ($type & token::ANY_OPENING_PAREN) && $nexttype === token::ARG_SEPARATOR;
            $commaplusparen = $type === token::ARG_SEPARATOR && ($nexttype & token::ANY_CLOSING_PAREN);
            $twocommas = ($type === token::ARG_SEPARATOR && $nexttype === token::ARG_SEPARATOR);
            if ($parenpluscomma || $commaplusparen || $twocommas) {
                $this->die('syntax error: invalid use of separator token (,)', $nexttoken);
            }

            // Similarly, We do not allow to subsequent colons, a colon following an opening parenthesis,
            // a colon following an argument separator or a colon followed by a closing parenthesis.
            $parenpluscolon = ($type & token::ANY_OPENING_PAREN) && $nexttype === token::RANGE_SEPARATOR;
            $colonplusparen = $type === token::RANGE_SEPARATOR && ($nexttype & token::ANY_CLOSING_PAREN);
            $twocolons = ($type === token::RANGE_SEPARATOR && $nexttype === token::RANGE_SEPARATOR);
            $commapluscolon = ($type === token::ARG_SEPARATOR && $nexttype === token::RANGE_SEPARATOR);
            if ($parenpluscolon || $colonplusparen || $twocolons || $commapluscolon) {
                $this->die('syntax error: invalid use of range separator (:)', $nexttoken);
            }

            // If we're one token away from the end of the statement, we just read and discard the end-of-statement marker.
            if ($nexttype === token::END_OF_STATEMENT) {
                $this->read_next();
                break;
            }
            // Otherwise, let's read the next token and append it to the list of tokens for this statement.
            $currenttoken = $this->read_next();
            $expression[] = $currenttoken;
        }

        // Feed the expression to the shunting yard algorithm and return the result.
        return new expression(shunting_yard::infix_to_rpn($expression));
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

    // FIXME doc
    private function is_known_variable(token $token): bool {
        return in_array($token->value, $this->variableslist);
    }

    private function register_variable(token $token): void {
        // Do not register a variable twice.
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
     * @return for_loop
     */
    public function parse_forloop(): for_loop {
        // FIXME: better error reporting (row/col number of error)
        // FIXME: IDENTIFIER must be changed to VARIABLE
        $variable = null;
        $range = [];
        $statements = [];

        // Consume the 'for' token.
        $currenttoken = $this->read_next();

        // Next must be an opening parenthesis.
        $currenttoken = $this->peek();
        if (!$currenttoken || $currenttoken->type !== token::OPENING_PAREN) {
            $this->die('syntax error: ( expected after for');
        }
        // Consume the opening parenthesis.
        $currenttoken = $this->read_next();

        // Next must be a variable name.
        $currenttoken = $this->peek();
        if (!$currenttoken || $currenttoken->type !== token::IDENTIFIER) {
            $this->die('syntax error: identifier expected');
        }
        $currenttoken = $this->read_next();
        $currenttoken->type = token::VARIABLE;
        $variable = $currenttoken->value;

        // Next must be a colon.
        $currenttoken = $this->peek();
        if (!$currenttoken || $currenttoken->type !== token::RANGE_SEPARATOR) {
            $this->die('syntax error: : expected');
        }
        $currenttoken = $this->read_next();

        // Next must be an opening bracket.
        $currenttoken = $this->peek();
        if (!$currenttoken || $currenttoken->type !== token::OPENING_BRACKET) {
            $this->die('syntax error: [ expected');
        }

        // Read up to the closing bracket. We are sure there is one, because the parser has already
        // checked for mismatched / unbalanced parens.
        $range = $this->parse_general_expression(token::CLOSING_BRACKET);
        // FIXME: feed the range to the shunting yard algorithm

        // Next must be a closing parenthesis.
        $currenttoken = $this->peek();
        if (!$currenttoken || $currenttoken->type !== token::CLOSING_PAREN) {
            $this->die('syntax error: ) expected');
        }
        $currenttoken = $this->read_next();

        // Next must either be an opening brace or the start of a statement.
        $currenttoken = $this->peek();
        if (!$currenttoken) {
            $this->die('syntax error: { or statement expected');
        }

        // If the token is an opening brace, we have to recursively read upcoming lines,
        // because there might be nested for loops. Otherwise, we read one single statement.
        if ($currenttoken->type === token::OPENING_BRACE) {
            // Consume the brace.
            $this->read_next();
            // Parse each statement.
            while ($currenttoken && $currenttoken->type !== token::CLOSING_BRACE) {
                $statements[] = $this->parse_the_right_thing($currenttoken);
                $currenttoken = $this->peek();
            }
            // Consume the closing brace.
            $this->read_next();
        } else {
            $statements[] = $this->parse_general_expression();
        }

        return new for_loop($variable, $range, $statements);
    }
}
