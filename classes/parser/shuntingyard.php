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

use Error;


class ShuntingYard {
    /**
     * Return numeric precedence value for an operator
     *
     * @param string $operator operator or function name
     * @return integer
     */
    private static function get_precedence($operator) {
        switch ($operator) {
            case '**':  // right-associative -> put onto stack
                return 160;
            case '_':
                return 150;
            case '~':
                return 140;
            case '!':
                return 130;
            case '*':
            case '/':
            case '%':
                return 120;
            case '+':
            case '-':
                return 110;
            case '<<':
            case '>>':
                return 100;
            case '<':
            case '>':
            case '>=':
            case '<=':
                return 90;
            case '!=':
            case '==':
                return 80;
            case '&':
                return 70;
            case '^':
                return 60;
            case '|':
                return 50;
            case '&&':
                return 40;
            case '||':
                return 30;
            case '?':
            case ':':
                return 20;
            case '=':  // right-associative -> put onto stack
                return 10;
        }
    }

    /**
     * Return whether an operator is left-associative
     *
     * @param string $operator operator or function name
     * @return boolean
     */
    private static function is_left_associative($operator) {
        switch ($operator) {
            case '=':
            case '**':
                return false;
            case '*':
            case '/':
            case '%':
            case '+':
            case '-':
            case '<<':
            case '>>':
            case '&':
            case '^':
            case '|':
            case '&&':
            case '||':
                return true;
            // The following operators are not associative at all, either
            // because they are unary (like _ or ~ or !) or because associativity
            // does not make sense for them.
            case '_':
            case '~':
            case '!':
            case '<':
            case '>':
            case '>=':
            case '<=':
            case '!=':
            case '==':
                return false;
            // In PHP, the ternary operator is not associative (it was left-associative before 8.0.0)
            // but many languages (e.g. JavaScript) define it to be right-associative which allows
            // for easy chaining, i.e. condition1 ? value1 : condition2 ? value2 : value 3.
            case '?':
            case ':':
                return false;
        }
    }

    /**
     * Pop elements from the end of an array until the callback function returns true. If desired,
     * popped elements can be appended to another array; otherwise they will be discarded. Also
     * the last element can be left in the input array or removed from it. If it is removed, it can be
     * appended to the output or discarded. The function modifies the input array and resets its internal pointer.
     *
     * @param array &$input input array, will be modified
     * @param callable $callback custom comparison function
     * @param array &$out optional output array, will be modified
     * @param boolean $poplast whether the last element should be popped or not
     * @param boolean $discardlast whether the last element should be discarded when popping it
     * @throws Error if the last element should not be discarded, but is not to be popped
     */
    private static function flush_until(&$input, $callback, &$out = null, $poplast = false, $discardlast = false) {
        if (!$poplast && $discardlast) {
            throw new Error('Cannot move last element to output queue if it is not to be popped.');
        }
        $head = end($input);
        while ($head !== false) {
            if ($callback($head)) {
                break;
            }
            $out[] = $head;
            $head = prev($input);
            array_pop($input);
        }
        if ($poplast) {
            $tmp = array_pop($input);
            if (!$discardlast && !is_null($tmp)) {
                $out[] = $tmp;
            }
        }
    }

    /**
     * Translate statement from infix into RPN notation via Dijkstra's shunting yard algorithm,
     * because this makes evaluation much easier. The method is declared as static, because it
     * should be possible to use it for arbitrary arithmetic expression with no context at all.
     *
     * @param array $tokens the tokens forming the statement that is to be translated
     * @return array
     */
    public static function shunting_yard($tokens) {
        // FIXME: maybe include implicit multiplication here instead of in the parser
        $output = [];
        $opstack = [];

        foreach ($tokens as $key => $token) {
            $type = $token->type;
            $value = $token->value;
            if ($key === array_key_first($tokens)) {
                $unarypossible = true;
                $implicitpossible = false;
            }
            // Literals (numbers or strings) go straight to the output queue.
            if ($type === Token::NUMBER || $type === Token::STRING) {
                $output[] = $token;
                $unarypossible = false;
                $implicitpossible = true;
                continue;
            }
            // Variable names go straight to the output queue.
            if ($type === Token::VARIABLE) {
                $output[] = $token;
                $unarypossible = false;
                $implicitpossible = true;
                continue;
            }
            if ($type === Token::IDENTIFIER) {
                // FIXME: die with error "unknown identifier"
                print("why do I see an IDENTIFIER: $value\n");
                $unarypossible = false;
                $implicitpossible = true;
                continue;
            }
            // Opening parenthesis goes straight to the operator stack.
            if ($type === Token::OPENING_PAREN) {
                $opstack[] = $token;
                $unarypossible = true;
                continue;
            }
            // Function name goes straight to the operator stack.
            // FIXME implement function call and arguments
            if ($type === Token::FUNCTION) {
                $opstack[] = $token;
                $unarypossible = false;
                continue;
            }
            // Closing parenthesis means we flush all operators until we get to the
            // matching opening parenthesis.
            if ($type === Token::CLOSING_PAREN) {
                self::flush_until($opstack, function($operator) {
                    return $operator->value === '(';
                }, $output, true, true);
                // FIXME: error if no matching paren is found
                // FIXME: treat case where opstack's head is function call (needs argument count)
                $unarypossible = false;
                $implicitpossible = true;
                continue;
            }
            // FIXME: Ternary operator

            // Classic operators are treated according to precedence.
            if ($type === Token::OPERATOR) {
                // First we check whether the operator could be unary. An unary + will be silently dropped.
                // An unary - will be changed to negation.
                if ($unarypossible) {
                    if ($value === '+') {
                        continue;
                    }
                    if ($value === '-') {
                        $value = ($token->value = '_');
                    }
                }
                // After an operator, it is always possible to have an unary operator in the next token.
                $unarypossible = true;
                $head = end($opstack);
                // If operator stack is empty, push the new operator to the stack.
                if ($head === false) {
                    $opstack[] = $token;
                    continue;
                }
                $thisprecedence = self::get_precedence($value);
                // All stacked operators with higher precedence go to the output queue.
                if (self::is_left_associative($value)) {
                    self::flush_until($opstack, function($operator) use ($thisprecedence) {
                        return $thisprecedence > self::get_precedence($operator->value);
                    }, $output);
                }
                // Put this operator on the stack.
                $opstack[] = $token;
                continue;
            }
            // Ship out the statement if we reach a ; or if we are at the last token.
            // The latter is useful to avoid code duplication after the loop.
            if ($type === Token::END_OF_STATEMENT) {
                // FIXME die with error message
                print("\n\n **** should not have seen END OF STATEMENT ***\n\n");
                die();
            }
        }
        self::flush_until($opstack, function() {
            return false;
        }, $output, true);
        return $output;
    }
}
