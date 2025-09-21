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

use Exception;

/**
 * Parser for qtype_formulas
 *
 * @package    qtype_formulas
 * @copyright  2022 Philipp Imhof
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class parser {
    /** @var null */
    const EOF = null;

    /** @var array list of all (raw) tokens */
    protected array $tokenlist;

    /** @var int number of (raw) tokens */
    private int $count;

    /** @var int position w.r.t. list of (raw) tokens */
    private int $position = -1;

    /** @var array list of all (parsed) statements */
    protected array $statements = [];

    /** @var array list of known variables */
    private array $variableslist = [];

    /** @var array list of operators that may exceptionally appear at the end of the input, for overriding in subclasses */
    protected $allowedoperatorsatend = [];

    /**
     * Create a parser class and have it parse a given input. The input can be given as a string, in
     * which case it will first be sent to the lexer. If that step has already been made, the constructor
     * also accepts a list of tokens. The user can specify a list of known variables to help the
     * parser classify identifiers as functions or variables.
     *
     * @param string|array $tokenlist list of tokens as returned from the lexer or input string
     * @param array $knownvariables
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

    /**
     * Invoke the parser for the thing at hand, currently either a for loop or a general
     * expression, e.g. an assignment or an answer given by a student.
     *
     * @param token $token the first token
     * @return for_loop|expression
     */
    private function parse_the_right_thing(token $token) {
        if ($token->type === token::END_OF_STATEMENT) {
            // If the statement starts with a semicolon, we simply consume it and return an empty
            // expression.
            $this->read_next();
            return new expression([]);
        } else if ($token->type === token::RESERVED_WORD && $token->value === 'for') {
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
                    $this->die(get_string('error_strayparen', 'qtype_formulas', $token->value), $token);
                }
                // Let's check whether the opening and closing parenthesis have the same type.
                // If they do, XORing them should leave just the 16- and the 32-bit. Otherwise,
                // we can stop here.
                if (($top->type ^ $type) !== 0b110000) {
                    $a = (object)['closer' => $token->value, 'opener' => $top->value, 'row' => $top->row, 'column' => $top->column];
                    $this->die(get_string('error_parenmismatch', 'qtype_formulas', $a), $token);
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
     * Find the matching parenthesis that closes the given opening paren.
     *
     * @param token $opener opening paren (, { or [
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
    }

    /**
     * Stop processing and indicate the human readable position (row/column) where the error occurred.
     *
     * @param string $message error message
     * @param token|null $offendingtoken the token where the error happened
     * @return void
     * @throws Exception
     */
    protected function die(string $message, ?token $offendingtoken = null): void {
        if (is_null($offendingtoken)) {
            $offendingtoken = $this->tokenlist[$this->position];
        }
        throw new \Exception($offendingtoken->row . ':' . $offendingtoken->column . ':' . $message);
    }

    /**
     * Check whether the token list contains at least one token with the given type and value.
     *
     * @param int $type the token type to look for
     * @param mixed $value the value to look for
     * @return bool
     */
    public function has_token_in_tokenlist(int $type, $value = null): bool {
        foreach ($this->tokenlist as $token) {
            // If the value does not matter, we also set the token's value to null.
            $tokenvalue = $token->value;
            if ($value === null) {
                $tokenvalue = null;
            }

            // We do not use strict comparison for the value.
            if ($token->type === $type && $tokenvalue == $value) {
                return true;
            }
        }
        return false;
    }

    /**
     * Parse a general expression, i. e. an assignment or an answer expression as it could
     * be given by a student. Can be requested to stop when finding the first token of a
     * given type, if needed.
     *
     * @param int|null $stopat stop parsing when reaching a token with the given type
     * @return expression
     */
    private function parse_general_expression(?int $stopat = null): expression {
        // Start by reading the first token.
        $currenttoken = $this->read_next();
        $expression = [$currenttoken];

        while ($currenttoken !== self::EOF && $currenttoken->type !== $stopat) {
            $type = $currenttoken->type;
            $value = $currenttoken->value;
            $nexttoken = $this->peek();
            if ($nexttoken === self::EOF) {
                $invalidoperator = $type === token::OPERATOR && !in_array($value, $this->allowedoperatorsatend);
                $invalidothertoken = in_array($type, [token::PREFIX, token::ARG_SEPARATOR, token::RANGE_SEPARATOR]);
                // The last token must not be a PREFIX, an ARG_SEPARATOR or a RANGE_SEPARATOR. Also, it must not
                // be an OPERATOR, unless it is in the whitelist of operators that may appear at the end of the input.
                if ($invalidoperator || $invalidothertoken) {
                    // When coming from the random parser, the assignment operator is 'r=' instead of '=', but
                    // the user does not know that. We must instead report the value they entered.
                    if ($value === 'r=') {
                        $value = '=';
                    }
                    $this->die(get_string('error_unexpectedend', 'qtype_formulas', $value), $currenttoken);
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
            // that one as a FUNCTION, unless it is not a known function name. If the PREFIX is not
            // followed by an identifier, we silently ignore it for maximum backwards compatibility, as
            // legacy versions used to remove backslashes from variable definitions.
            if ($type === token::PREFIX) {
                if ($nexttype === token::IDENTIFIER) {
                    if (!self::is_valid_function_name($nextvalue)) {
                        $this->die(get_string('error_prefix', 'qtype_formulas'));
                    }
                    $nexttype = ($nexttoken->type = token::FUNCTION);
                }
            }

            // If the token is already classified as a FUNCTION, it MUST be followed by an
            // opening parenthesis.
            if ($type === token::FUNCTION && $nexttype !== token::OPENING_PAREN) {
                $this->die(get_string('error_func_paren', 'qtype_formulas'));
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
                $isknownfunction = self::is_valid_function_name($value);
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
                    $this->die(get_string('error_rangeusage', 'qtype_formulas'), $currenttoken);
                }
            }

            // Check syntax for ternary operators:
            // We do not currently allow the short ternary operator aka "Elvis operator" (a ?: b)
            // which is a short form for (a ? a : b). Also, if we do not find a corresponding : part,
            // we die with a syntax error.
            if ($type === token::OPERATOR && $value === '?') {
                if ($nexttype === token::OPERATOR && $nextvalue === ':') {
                    $this->die(get_string('error_ternary_missmiddle', 'qtype_formulas'), $nexttoken);
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
                        $this->die(get_string('error_ternary_incomplete', 'qtype_formulas'), $currenttoken);
                    }
                    $lookahead = $this->peek($i);
                    $latype = $lookahead->type;
                    $lavalue = $lookahead->value;
                    $i++;
                }
            }

            // We do not allow two subsequent strings or a string followed by a number, because that's probably
            // a typo and we do not know for sure what to do with them. We make an exception for two subsequent
            // numbers and consider that as implicit multiplication, similar to what e. g. Wolfram Alpha does.
            if ($type === token::STRING && in_array($nexttype, [token::NUMBER, token::STRING])) {
                $this->die(get_string('error_forgotoperator', 'qtype_formulas'), $nexttoken);
            }
            if ($type === token::NUMBER && $nexttype === token::STRING) {
                $this->die(get_string('error_forgotoperator', 'qtype_formulas'), $nexttoken);
            }

            // We do not allow to subsequent commas, a comma following an opening parenthesis
            // or a comma followed by a closing parenthesis.
            $parenpluscomma = ($type & token::ANY_OPENING_PAREN) && $nexttype === token::ARG_SEPARATOR;
            $commaplusparen = $type === token::ARG_SEPARATOR && ($nexttype & token::ANY_CLOSING_PAREN);
            $twocommas = ($type === token::ARG_SEPARATOR && $nexttype === token::ARG_SEPARATOR);
            if ($parenpluscomma || $commaplusparen || $twocommas) {
                $this->die(get_string('error_invalidargsep', 'qtype_formulas'), $nexttoken);
            }

            // Similarly, We do not allow to subsequent colons, a colon following an opening parenthesis,
            // a colon following an argument separator or a colon followed by a closing parenthesis.
            $parenpluscolon = ($type & token::ANY_OPENING_PAREN) && $nexttype === token::RANGE_SEPARATOR;
            $colonplusparen = $type === token::RANGE_SEPARATOR && ($nexttype & token::ANY_CLOSING_PAREN);
            $twocolons = ($type === token::RANGE_SEPARATOR && $nexttype === token::RANGE_SEPARATOR);
            $commapluscolon = ($type === token::ARG_SEPARATOR && $nexttype === token::RANGE_SEPARATOR);
            $colonpluscomma = ($type === token::RANGE_SEPARATOR && $nexttype === token::ARG_SEPARATOR);
            if ($parenpluscolon || $colonplusparen || $twocolons || $commapluscolon || $colonpluscomma) {
                $this->die(get_string('error_invalidrangesep', 'qtype_formulas'), $nexttoken);
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

    /**
     * Check if a given name is a valid function name.
     *
     * @param string $identifier the name to check
     * @return bool
     */
    public static function is_valid_function_name(string $identifier): bool {
        return array_key_exists($identifier, functions::FUNCTIONS + evaluator::PHPFUNCTIONS);
    }

    /**
     * Retrieve the parsed statements.
     *
     * @return array
     */
    public function get_statements(): array {
        return $this->statements;
    }

    /**
     * Look at the next (or one of the next) token, without moving the processing index any further.
     * Returns NULL if peeking beyond the end of the token list.
     *
     * @param int $skip skip a certain number of tokens
     * @return token|null
     */
    private function peek(int $skip = 0): ?token {
        if ($this->position < $this->count - $skip - 1) {
            return $this->tokenlist[$this->position + $skip + 1];
        }
        return self::EOF;
    }

    /**
     * Read the next token from the token list and move the processing index forward by one position.
     * Returns NULL if we have reached the end of the list.
     *
     * @return token|null
     */
    private function read_next(): ?token {
        $nexttoken = $this->peek();
        if ($nexttoken !== self::EOF) {
            $this->position++;
        }
        return $nexttoken;
    }

    /**
     * Check whether a given identifier is a known variable.
     *
     * @param token $token token containing the identifier
     * @return bool
     */
    protected function is_known_variable(token $token): bool {
        return in_array($token->value, $this->variableslist);
    }

    /**
     * Register an identifier as a known variable.
     *
     * @param token $token
     * @return void
     */
    private function register_variable(token $token): void {
        // Do not register a variable twice.
        if ($this->is_known_variable($token)) {
            return;
        }
        $this->variableslist[] = $token->value;
    }

    /**
     * Return the list of all known variables.
     *
     * @return array
     */
    public function export_known_variables(): array {
        return $this->variableslist;
    }

    /**
     * This function parses a for loop.
     * The general syntax of a for loop is for (<var>:<range/list>) { <statements> }, but the braces can be
     * left out if there is only one statement. The range can be defined with the loop, but it can also
     * be stored in a variable.
     * Notes:
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
    private function parse_forloop(): for_loop {
        $variable = null;
        $range = [];
        $statements = [];

        // Consume the 'for' token.
        $this->read_next();

        // Next must be an opening parenthesis.
        $currenttoken = $this->peek();
        if (!$currenttoken || $currenttoken->type !== token::OPENING_PAREN) {
            $this->die(get_string('error_for_expectparen', 'qtype_formulas'), $currenttoken);
        }
        // Consume the opening parenthesis.
        $currenttoken = $this->read_next();

        // Next must be a variable name.
        $currenttoken = $this->peek();
        if (!$currenttoken || $currenttoken->type !== token::IDENTIFIER) {
            $this->die(get_string('error_for_expectidentifier', 'qtype_formulas'), $currenttoken);
        }
        $currenttoken = $this->read_next();
        $currenttoken->type = token::VARIABLE;
        $variable = $currenttoken;

        // Next must be a colon.
        $currenttoken = $this->peek();
        if (!$currenttoken || $currenttoken->type !== token::RANGE_SEPARATOR) {
            $this->die(get_string('error_for_expectcolon', 'qtype_formulas'), $currenttoken);
        }
        $currenttoken = $this->read_next();

        // Next must be an opening bracket or a variable. The variable should contain a list,
        // but we cannot check that at this point. Note that at this point, IDENTIFIER tokens
        // have not yet been classified into VARIABLE or FUNCTION tokens.
        $currenttoken = $this->peek();
        $isbracket = ($currenttoken->type === token::OPENING_BRACKET);
        $isvariable = ($currenttoken->type === token::IDENTIFIER);
        if (empty($currenttoken) || (!$isbracket && !$isvariable)) {
            $this->die(get_string('error_expectbracketorvarname', 'qtype_formulas'), $currenttoken);
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
            $this->die(get_string('error_expectclosingparen', 'qtype_formulas'), $currenttoken);
        }
        $currenttoken = $this->read_next();

        // Next must either be an opening brace or the start of a statement.
        $currenttoken = $this->peek();
        if (!$currenttoken) {
            $this->die(get_string('error_expectbraceorstatement', 'qtype_formulas'), $currenttoken);
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
