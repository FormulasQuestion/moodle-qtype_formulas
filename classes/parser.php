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

use Exception;

/**
 * Parser for qtype_formulas
 *
 * @package    qtype_formulas
 * @copyright  2022 Philipp Imhof
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


/*

TODO:

* maybe add possibility for /* and * / delimited comments ?
* _randomsvars_text in attempt_step_data --> prepend _version = 5; (incompat with legacy, so we know it's recent)
      (alternative: 'version=5' or "version=5")
*

* units

*/

class parser {
    const EOF = null;

    /** @var array list of all (raw) tokens */
    protected $tokenlist;

    /** @var integer number of (raw) tokens */
    private $count;

    /** @var integer position w.r.t. list of (raw) tokens */
    private $position = -1;

    /** @var array list of all (parsed) statements */
    public $statements = [];

    /** @var array list of known variables */
    private $variableslist = [];

    /**
     * FIXME Undocumented function
     *
     * @param [type] $tokenlist list of tokens as returned from the lexer or input string
     * @param [type] $knownvariables
     */
    public function __construct($tokenlist, array $knownvariables = []) {
        // If the input is given as a string, run it through the lexer first.
        if (is_string($tokenlist)) {
            $lexer = new lexer($tokenlist);
            $tokenlist = $lexer->get_tokens();
        }
        $this->tokenlist = $tokenlist;
        $this->count = count($tokenlist);
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
     * Check whether all parentheses are balanced. Otherweise, stop all further processing
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
     * Undocumented function
     *
     * @param token $opener
     * @return token
     */
    private function find_closing_paren(token $opener): token {
        $openertype = $opener->type;
        $i = 0;
        $nested = 0;
        $token = $this->peek();
        while ($token !== self::EOF) {
            $type = $token->type;
            // If we see the same type of opening paren, we enter a new nested level.
            if ($type === $openertype) {
                $nested++;
            }
            // XORing an opening paren's and its closing counterpart's type will have
            // the 16- and the 32-bit set.
            if (($type ^ $openertype) === 0b110000) {
                $nested--;
            }
            // We already know that parens are balanced, so a negative level of nesting
            // means we have reached the closing paren we were looking for.
            if ($nested < 0) {
                return $token;
            }
            $i++;
            $token = $this->peek($i);
        }

        // This cannot happen, because we have already checked that parens are balanced.
        $this->die("syntax error: could not find closing parenthesis for '$opener->value'", $opener);
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

    public function has_token_in_tokenlist(int $type, $value): bool {
        foreach ($this->tokenlist as $token) {
            // We do not use strict comparison for the value.
            if ($token->type === $type && $token->value == $value) {
                return true;
            }
        }
        return false;
    }

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

            // If the token is already classified as a FUNCTION, it MUST be followed by an
            // opening parenthesis.
            if ($type === token::FUNCTION && $nexttype !== token::OPENING_PAREN) {
                $this->die('syntax error: function must be followed by opening parenthesis');
            }

            // If the current token is an IDENTIFIER, we will classify it as a VARIABLE or a FUNCTION.
            // In order to be classified as a function, it must meet the following criteria:
            // - not be a known variable (unless preceded by the PREFIX, see above)
            // - be a known function name
            // - be followed by an opening paren
            // In all other cases, it will be classified as a VARIABLE. Note that being a known function
            // name alone is not enough, because we allow the user to define variables that have the same
            // name as predefined functions to ensure that the introduction of new functions will not
            // break existing questions.
            if ($type === token::IDENTIFIER) {
                $isnotavariable = !$this->is_known_variable($currenttoken);
                $isknownfunction = array_key_exists($value, functions::FUNCTIONS + evaluator::PHPFUNCTIONS);
                $nextisparen = $nexttype === token::OPENING_PAREN;
                if ($isnotavariable && $isknownfunction && $nextisparen) {
                    $type = ($currenttoken->type = token::FUNCTION);
                } else {
                    $type = ($currenttoken->type = token::VARIABLE);
                    $this->register_variable($currenttoken);
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
                while ($latype !== token::OPERATOR || $lavalue !== ':') {
                    // We have a syntax error, if...
                    // - we come to an END_OF_STATEMENT marker
                    // - we reach the end of the token list
                    // before having seen the : part.
                    $endofstatement = ($latype === token::END_OF_STATEMENT);
                    // If $i + $this->position is not smaller than $this->count - 1, the peek()
                    // function will return the EOF token.
                    $endoflist = ($i + $this->position >= $this->count - 1);
                    if ($endofstatement || $endoflist) {
                        $this->die("syntax error: incomplete ternary operator or misplaced '?'", $currenttoken);
                    }
                    $lookahead = $this->peek($i);
                    $latype = $lookahead->type;
                    $lavalue = $lookahead->value;
                    $i++;
                }
                // TODO (maybe, for earlier error reporting): ':' operator must not be followed by
                // - another operator (except +, -, ! and ~ which might be unary, we cannot know yet)
                // - any closing paren ), ] or }
                // - any separator (range or argument)
                // - an END_OF_GROUP or END_OF_STATEMENT
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
            if ($nexttype === token::END_OF_STATEMENT || $nexttype === token::END_GROUP) {
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

    public function is_known_variable(token $token): bool {
        return in_array($token->value, $this->variableslist);
    }

    private function register_variable(token $token): void {
        // Do not register a variable twice.
        if ($this->is_known_variable($token)) {
            return;
        }
        $this->variableslist[] = $token->value;
    }

    /**
     * Undocumented function
     *
     * @return array
     */
    public function export_known_variables(): array {
        return $this->variableslist;
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
        $variable = null;
        $range = [];
        $statements = [];

        // Consume the 'for' token.
        $fortoken = $this->read_next();

        // Next must be an opening parenthesis.
        $currenttoken = $this->peek();
        if (!$currenttoken || $currenttoken->type !== token::OPENING_PAREN) {
            $this->die('syntax error: ( expected after for', $currenttoken);
        }
        // Consume the opening parenthesis.
        $currenttoken = $this->read_next();

        // Next must be a variable name.
        $currenttoken = $this->peek();
        if (!$currenttoken || $currenttoken->type !== token::IDENTIFIER) {
            $this->die('syntax error: identifier expected', $currenttoken);
        }
        $currenttoken = $this->read_next();
        $currenttoken->type = token::VARIABLE;
        $variable = $currenttoken;

        // Next must be a colon.
        $currenttoken = $this->peek();
        if (!$currenttoken || $currenttoken->type !== token::RANGE_SEPARATOR) {
            $this->die('syntax error: : expected', $currenttoken);
        }
        $currenttoken = $this->read_next();

        // Next must be an opening bracket or a variable. The variable should contain a list,
        // but we cannot check that at this point. Note that at this point, IDENTIFIER tokens
        // have not yet been classified into VARIABLE or FUNCTION tokens.
        $currenttoken = $this->peek();
        $isbracket = ($currenttoken->type === token::OPENING_BRACKET);
        $isvariable = ($currenttoken->type === token::IDENTIFIER);
        if (empty($currenttoken) || (!$isbracket && !$isvariable)) {
            $this->die('syntax error: [ or variable name expected', $currenttoken);
        }

        if ($isbracket) {
            // If we had an opening bracket, read up to the closing bracket. We are sure there
            // is one, because the parser has already checked for mismatched / unbalanced parens.
            $range = $this->parse_general_expression(token::CLOSING_BRACKET);
        } else {
            // Otherwise, we set the token's type to VARIABLE, as it must be one and things will
            // blow up later during evaluation if it is not. Then, we define the range to be
            // an expression of just this one token. And we don't forget to consume the token.
            $currenttoken->type = token::VARIABLE;
            $range = new expression([$currenttoken]);
            $this->read_next();
        }

        // Next must be a closing parenthesis.
        $currenttoken = $this->peek();
        if (!$currenttoken || $currenttoken->type !== token::CLOSING_PAREN) {
            $this->die('syntax error: ) expected', $currenttoken);
        }
        $currenttoken = $this->read_next();

        // Next must either be an opening brace or the start of a statement.
        $currenttoken = $this->peek();
        if (!$currenttoken) {
            $this->die('syntax error: { or statement expected', $currenttoken);
        }

        // If the token is an opening brace, we have to parse all upcoming lines until the
        // matching closing brace. Otherwise, we parse one single line. In any case,
        // what we read might be a nested for loop, so we process everything recursively.
        if ($currenttoken->type === token::OPENING_BRACE) {
            // Consume the brace.
            $this->read_next();
            $closer = $this->find_closing_paren($currenttoken);
            $closer->type = token::END_GROUP;
            $currenttoken = $this->peek();
            // Parse each statement.
            while ($currenttoken && $currenttoken->type !== token::END_GROUP) {
                $statements[] = $this->parse_the_right_thing($currenttoken);
                $currenttoken = $this->peek();
            }
            // Consume the closing brace.
            $this->read_next();
            // Check whether the next token (if it exists) is a semicolon. If it is, we consume it also.
            $nexttoken = $this->peek();
            if (isset($nexttoken) && $nexttoken->type === token::END_OF_STATEMENT) {
                $this->read_next();
            }
        } else {
            $statements[] = $this->parse_the_right_thing($currenttoken);
        }

        if (count($statements) === 0) {
            // We do not disallow empty for loops for backward compatibility.
            // $this->die('syntax error: empty for loop', $fortoken);
        }

        return new for_loop($variable, $range, $statements);
    }

    /**
     * Return the token list.
     *
     * @return array
     */
    public function get_tokens(): array {
        return $this->tokenlist;
    }
}