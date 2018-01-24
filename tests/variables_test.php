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
 * Unit tests for the short answer question definition class.
 *
 * @package    qtype_formulas
 * @copyright  2018 Jean-Michel Vedrine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/question/engine/tests/helpers.php');
require_once($CFG->dirroot . '/question/type/formulas/variables.php');


/**
 * Unit tests for the formulas question variables class.
 *
 * @copyright  2018 Jean-Michel Vedrine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

class qtype_formulas_variables_test extends advanced_testcase {
    /**
     * Test 1: get_expressions_in_bracket() test.
     */
    public function test_get_expressions_in_bracket() {
        $qv = new qtype_formulas_variables;
        $brackettest = array(
            array(true, '8+sum([1,2+2])', '('),
            array(true, '8+[1,2+2,3+sin(3),sum([1,2,3])]', '['),
            array(true, 'a=0; for x in [1,2,3] { a=a+sum([1,x]); }', '{')
        );
        foreach ($brackettest as $b) {
            $errmsg = null;
            try{
                $res = $qv->get_expressions_in_bracket($b[1], 0, $b[2]);
            } catch(Exception $e) { $errmsg = $e->getMessage(); }
            $eval = $errmsg===null;
            $this->assertEquals($b[0], $eval);
        }
    }

    /**
     * Test 2: evaluate_general_expression() test.
     */
    public function test_evaluate_general_expression() {
        $qv = new qtype_formulas_variables;
        $errmsg = null;
        try {
            $v = $qv->vstack_create();
            $res = $qv->evaluate_general_expression($v, 'sin(4) + exp(cos(4+5))');
        } catch(Exception $e) { $errmsg = $e->getMessage(); }
        $eval = $errmsg===null;
        $this->assertEquals(true, $eval);
    }

    /**
     * Test 3.1: evaluate_assignments() test.
     */
    public function test_evaluate_assignments_1() {
        $qv = new qtype_formulas_variables;
        $testcases = array(
            array(true, '#--------- basic operation ---------#'),
            array(true, 'a = 1;'),
            array(true, 'a = 1; b = 4;'),
            array(true, 'a = 1; # This is comment! So it will be skipped. '),
            array(true, 'c = cos(0)+3.14;'),
            array(true, 'd = "Hello!";'),
            array(true, 'e =[1,2,3,4];'),
            array(true, 'f =["A","B","C"];'),
            array(true, 'a = 1; b = 4; c = a*b; g= [1,2+45, cos(0)+1,exp(a),b*c];'),
            array(true, 'h = [1,2+3,sin(4),5]; j=h[1];'),
            array(true, 'e = [1,2,3,4][1];'),
            array(true, 'e = [1,2,3,4]; e[2]=111;'),
            array(true, 'e = [1,2,3,4]; a=1; e[a]=111;'),
            array(true, 'e = [1,2,3,4]; a=1-1; e[a]=111;'),
            array(true, 'g = [3][0];'),
            array(true, 'a = [7,8,9]; g = [a[1]][0];'),
            array(true, 'h = [0:10]; k=[4:8:1]; m=[-20:-10:1.5];'),
            array(true, 'a = [1,2,3]; s=[2,0,1]; n=[3*a[s[0]], 3*a[s[1]], 3*a[s[2]]*9];'),
//            array(false, 'a=3 6;'),  // Problem parseerror unexpected '$a' (T_VARIABLE)
            array(false, 'a=3`6;'),
            array(false, 'f=1; g=f[1];'),
            array(false, 'e=[];')
        );
        foreach ($testcases as $idx => $testcase) {
            $errmsg = null;
            try {
                $v = $qv->vstack_create();
                $v = $qv->evaluate_assignments($v, $testcase[1]);
            } catch(Exception $e) { $errmsg = $e->getMessage(); }
            $eval = $errmsg===null;
            $this->assertEquals($testcase[0], $eval);
        }
    }

    /**
     * Test 3.2: evaluate_assignments() test.
     */
    public function test_evaluate_assignments_2() {
        $qv = new qtype_formulas_variables;
        $testcases = array(
            array(false, 'e=[1,2,3,4]; a=1-1; e[a]="A";'),
            array(false, 'e=[1,2,"A"];'),
            array(false, 'e=[1,2,3][4,5];'),
            array(false, 'e=[1,2,3]; f=e[4,5]'),
            array(false, 'e=[1,2,3,4]; f=e*2;'),
            array(false, 'e=[1,2,3][1][4,5,6][2];'),
            array(false, 'e=[0:10,"k"];'),
            array(false, 'e=[[1,2],[3,4]];'),
            array(false, 'e=[[[1,2],[3,4]]];'),
            array(false, 'e=[1,2,3]; e[0] = [8,9];'),
            array(true, '#--------- additional function (correct) ---------#'),
            array(true, 'a=4; A = fill(2,0); B= fill ( 3,"Hello"); C=fill(a,4);'),
            array(true, 'a=[1,2,3,4]; b=len(a); c=fill(len(a),"rr")'),
            array(true, 'p1=pick(4,[2,3,5,7,11]);'),
            array(true, 'p1=pick(3.1,[2,3,5,7,11]);'),
            array(true, 'p1=pick(1000,[2,3,5,7,11]);'),
            array(true, 'p1=pick(2,[2,3],[4,5],[6,7]);'),
            array(true, 's=sort([7,5,3,11,2]);'),
            array(true, 's=sort(["B","A2","A1"]);'),
            array(true, 's=sort(["B","A2","A1"],[2,4,1]);'),
            array(true, 's=sublist(["A","B","C","D"],[1,3]);'),
            array(true, 's=sublist(["A","B","C","D"],[0,0,2,3]);'),
            array(true, 's=inv([2,0,3,1]);'),
            array(true, 's=inv(inv([2,0,3,1]));')
        );
        foreach ($testcases as $idx => $testcase) {
            $errmsg = null;
            try {
                $v = $qv->vstack_create();
                $v = $qv->evaluate_assignments($v, $testcase[1]);
            } catch(Exception $e) { $errmsg = $e->getMessage(); }
            $eval = $errmsg===null;
            $this->assertEquals($testcase[0], $eval);
        }
    }

    /**
     * Test 3.3: evaluate_assignments() test.
     */
    public function test_evaluate_assignments_3() {
        $qv = new qtype_formulas_variables;
        $testcases = array(
            array(true, 'A=["A","B","C","D"]; B=[2,0,3,1]; s=sublist(sublist(A,B),inv(B));'),
            array(true, 'a=[1,2,3]; A=map("exp",a);'),
            array(true, 'a=[1,2,3]; A=map("+",a,2.3);'),
            array(true, 'a=[1,2,3]; b=[4,5,6]; A=map("+",a,b);'),
            array(true, 'a=[1,2,3]; b=[4,5,6]; A=map("pow",a,b);'),
            array(true, 'r=sum([4,5,6]);'),
            array(true, 'r=3+sum(fill(10,-1))+3;'),
            array(true, 's=concat([1,2,3], [4,5,6], [7,8]);'),
            array(true, 's=concat(["A","B"],["X","Y","Z"],["Hello"]);'),
            array(true, 's=join("~", [1,2,3]);'),
            array(true, 's=str(45);'),
            array(true, 'a=[4,5]; s = join(",","A","B", [ 1 , a  [1]], 3, [join("+",a,"?"),"9"]);'),
            array(true, '#--------- additional function (incorrect) ---------#'),
            array(false, 'c=fill(0,"rr")'),
            array(false, 'c=fill(10000,"rr")'),
            array(false, 's=fill);'),
            array(false, 's=fill(10,"rr";'),
            array(false, 'a=1; l=len(a);'),
            array(false, 'a=[1,2,3,4]; c=fill(len(a)+1,"rr")'),
            array(false, 'p1=pick("r",[2,3,5,7,11]);'),
            array(false, 'p1=pick(2,[2,3],[4,5],["a","b"]);'),
            array(false, 's=concat(0, [1,2,3], [5,6], 100);'),
            array(false, 's=concat([1,2,3], ["A","B"]);'),
            array(true, '#--------- for loop ---------#'),
            array(true, 'A = 1; Z = A + 3; Y = "Hello!"; X = sum([4:12:2]) + 3;'),
            array(true, 'for(i:[1,2,3]){};'),
            array(true, 'for ( i : [1,2,3] ) {};'),
            array(true, 'z = 0; A=[1,2,3]; for(i:A) z=z+i;'),
            array(true, 'z = 0; for(i: [0:5]){z = z + i;}'),
            array(true, 's = ""; for(i: ["A","B","C"]) { s=join("",s,[i]); }'),
            array(true, 'z = 0; for(i: [0:5]) for(j: [0:3]) z=z+i;'),
            array(false, 'z = 0; for(: [0:5]) z=z+i;'),
            array(false, 'z = 0; for(i:) z=z+i;'),
            // array(false, 'z = 0; for(i: [0:5]) '),
            array(false, 'z = 0; for(i: [0:5]) for(j [0:3]) z=z+i;'),
            array(false, 'z = 0; for(i: [0:5]) z=z+i; b=[1,"b"];'),
            array(true, '#--------- algebraic variable ---------#'),
            array(true, 'x = {1,2,3};'),
            array(true, 'x = { 1 , 2 , 3 };'),
            array(true, 'x = {1:3, 4:5:0.1 , 8:10:0.5 };'),
            array(true, 's=diff([3*3+3],[3*4]);'),
            array(true, 'x={1:10}; y={1:10}; s=diff(["x*x+y*y"],["x^2+y^2"],50);'),
            array(true, 'x={1:10}; y={1:10}; s=diff(["x*x+y*y"],["x+y^2"],50)[0];'),
            array(false, 's=diff([3*3+3,0],[3*4]);'),
            array(false, 'x = {"A", "B"};'),
        );
        foreach ($testcases as $idx => $testcase) {
            $errmsg = null;
            try {
                $v = $qv->vstack_create();
                $v = $qv->evaluate_assignments($v, $testcase[1]);
            } catch(Exception $e) { $errmsg = $e->getMessage(); }
            $eval = $errmsg===null;
            $this->assertEquals($testcase[0], $eval);
        }
    }

    /**
     * Test 4: parse_random_variables(), instantiate_random_variables().
     */
    public function test_parse_random_variables() {
        $qv = new qtype_formulas_variables;
        $testcases = array(
            array(true, 'a = shuffle ( ["A","B", "C" ])'),
            array(true, 'a = {1,2,3}'),
            array(true, 'a = {[1,2], [3,4]}'),
            array(true, 'a = {"A","B","C"}'),
            array(true, 'a = {["A","B"],["C","D"]}'),
            array(true, 'a = {0, 1:3:0.1, 10:30, 100}'),
            array(true, 'a = {1:3:0.1}; b={"A","B","C"};'),
            array(false, 'a = {10:1:1}'),
            array(false, 'a = {1:10,}'),
            array(false, 'a = {1:10?}'),
            array(false, 'a = {0, 1:3:0.1, 10:30, 100}*3'),
            array(false, 'a = {1:3:0.1}; b={a,12,13};'),
            array(false, 'a = {[1,2],[3,4,5]}'),
            array(false, 'a = {[1,2],["A","B"]}'),
            array(false, 'a = {[1,2],["A","B","C"]}'),
        );
        foreach ($testcases as $idx => $testcase) {
            $errmsg = null;
            $var = (object)array('all' => null);
            try {
                $var = $qv->parse_random_variables($testcase[1]);
                $inst = $qv->instantiate_random_variables($var);
                $serialized = $qv->vstack_get_serialization($inst);
            } catch(Exception $e) { $errmsg = $e->getMessage(); }
            $eval = $errmsg===null;
            $this->assertEquals($testcase[0], $eval);
//            var_dump($serialized);
        }
    }
    /**
     * Test 5: substitute_variables_in_text.
     */
    public function test_substitute_variables_in_text() {
        $qv = new qtype_formulas_variables;
        $vstack = $qv->vstack_create();
        $variable_text = 'a=1; b=[2,3,4];';
        $vstack = $qv->evaluate_assignments($vstack, $variable_text);
        $text = '{a}, {a }, { a}, {b}, {b[0]}, {b[0] }, { b[0]}, {b [0]}, {=a*100}, {=b[0]*b[1]}, {= b[1] * b[2] }, {=100+[4:8][1]} ';
        $newtext = $qv->substitute_variables_in_text($vstack, $text);
        $expected = '1, {a }, { a}, {b}, 2, {b[0] }, { b[0]}, {b [0]}, 100, 6, 12, 105 ';
        $this->assertEquals($expected, $newtext);
    }
    
    /**
     * Test 6.1: Numerical formula.
     */
    public function test_numerical_formula_1() {
        $qv = new qtype_formulas_variables;
        $testcases = array(
            array(true, 0, '3', 3),
            array(true, 0, '3.', 3),
            array(true, 0, '.3', 0.3),
            array(true, 0, '3.1', 3.1),
            array(true, 0, '3.1e-10', 3.1e-10),
            array(true, 0, '3.e10', 30000000000),
            array(true, 0, '.3e10', 3000000000),
            array(true, 0, '-3', -3),
            array(true, 0, '+3', 3),
            array(true, 0, '-3.e10', -30000000000),
            array(true, 0, '-.3e10', -3000000000),
            array(true, 0, 'pi', 0),
            array(false, 0, '- 3'),
            array(false, 0, '+ 3'),
            array(false, 0, '3 e10'),
            array(false, 0, '3e 10'),
            array(false, 0, '3e8e8'),
            array(false, 0, '3+10*4'),
            array(false, 0, '3+10^4'),
            array(false, 0, 'sin(3)'),
            array(false, 0, '3+exp(4)'),
            array(false, 0, '3*4*5'),
            array(false, 0, '3 4 5'),
            array(false, 0, 'a*b'),
            array(false, 0, '#')
        );
        foreach ($testcases as $idx => $testcase) {
            $result = $qv->compute_numerical_formula_value($testcase[2], $testcase[1]);
            $eval = $result!==null;
            $this->assertEquals($testcase[0], $eval);
            if ($testcase[0]) {
                $this->assertEquals($testcase[3], $result);
            }
        }
    }
    
    /**
     * Test 6.2: Numerical formula.
     */
    public function test_numerical_formula_2() {
        $qv = new qtype_formulas_variables;
        $testcases = array(
            // Numeric is basically a subset of 10al formula.
            array(true, 10, '3+10*4/10^4', 3.004),
            array(false, 10, 'sin(3)'),
            array(false, 10, '3+exp(4)'),

            // Numerical formula is basically a subset of algebraic formula, so test below together
            array(true, 100, '3.1e-10', 3.1e-10),
            array(true, 100, '- 3', -3), // it is valid for this type
            array(false, 100, '3 e10'),
            array(false, 100, '3e 10'),
            array(false, 100, '3e8e8'),
            array(false, 100, '3e8e8e8'),

            array(true, 100, '3+10*4/10^4', 3.004),
            array(true, 100, 'sin(3)-3+exp(4)', 51.739270041204),
            array(true, 100, '3*4*5', 60),
            array(true, 100, '3 4 5', 60),
            array(true, 100, '3e8 4.e8 .5e8', 6.0E+24),
            array(true, 100, '3e8(4.e8+2)(.5e8/2)5', 1.5000000075E+25),
            array(true, 100, '3e8(4.e8+2) (.5e8/2)5',  1.5000000075E+25),
            array(true, 100, '3e8 (4.e8+2)(.5e8/2) 5', 1.5000000075E+25),
            array(true, 100, '3e8 (4.e8+2) (.5e8/2) 5', 1.5000000075E+25),
            array(true, 100, '3(4.e8+2)3e8(.5e8/2)5', 4.5000000225E+25),
            array(true, 100, '3+4^9', 262147),
            array(true, 100, '3+(4+5)^9', 387420492),
            array(true, 100, '3+(4+5)^(6+7)', 2541865828332),
            array(true, 100, '3+sin(4+5)^(6+7)', 3.0000098920712),
            array(true, 100, '3+exp(4+5)^sin(6+7)', 46.881961305748),
            array(true, 100, '3+4^-(9)', 3.0000038146973),
            array(true, 100, '3+4^-9', 3.0000038146973),
            array(true, 100, '3+exp(4+5)^-sin(6+7)', 3.0227884071323),
            array(true, 100, '1+ln(3)', 2.0986122886681),
            array(true, 100, '1+log10(3)', 1.4771212547197),
            array(true, 100, 'pi', 3.1415926535898),
            array(false, 100, 'pi()'),
        );
        foreach ($testcases as $idx => $testcase) {
            $result = $qv->compute_numerical_formula_value($testcase[2], $testcase[1]);
            $eval = $result!==null;
            $this->assertEquals($testcase[0], $eval);
            if ($testcase[0]) {
                $this->assertEquals($testcase[3], $result);
            }
        }
    }
    
    /**
     * Test 7: Algebraic formula.
     */
    public function test_algebraic_formula() {
        $qv = new qtype_formulas_variables;
        $testcases = array(
            array(true, '- 3'),
            array(false, '3 e10'),
            array(true, '3e 10'),
            array(false, '3e8e8'),
            array(false, '3e8e8e8'),

            array(true, 'sin(3)-3+exp(4)'),
            array(true, '3e8 4.e8 .5e8'),
            array(true, '3e8(4.e8+2)(.5e8/2)5'),
            array(true, '3+exp(4+5)^sin(6+7)'),
            array(true, '3+4^-(9)'),

            array(true, 'sin(a)-a+exp(b)'),
            array(true, 'a*b*c'),
            array(true, 'a b c'),
            array(true, 'a(b+c)(x/y)d'),
            array(true, 'a(b+c) (x/y)d'),
            array(true, 'a (b+c)(x/y) d'),
            array(true, 'a (b+c) (x/y) d'),
            array(true, 'a(4.e8+2)3e8(.5e8/2)d'),
            array(true, 'pi'),
            array(true, 'a+x^y'),
            array(true, '3+x^-(y)'),
            array(true, '3+x^-y'),
            array(true, '3+(u+v)^x'),
            array(true, '3+(u+v)^(x+y)'),
            array(true, '3+sin(u+v)^(x+y)'),
            array(true, '3+exp(u+v)^sin(x+y)'),
            array(true, 'a+exp(a)(u+v)^sin(1+2)(b+c)'),
            array(true, 'a+exp(u+v)^-sin(x+y)'),
            array(true, 'a+b^c^d+f'),
            array(true, 'a+b^(c^d)+f'),
            array(true, 'a+(b^c)^d+f'),
            array(true, 'a+b^c^-d'),
            array(true, '1+ln(a)+log10(b)'),
            array(true, 'asin(w t)'),   // arcsin(w*t)
            array(true, 'a sin(w t)+ b cos(w t)'), // a*sin(w*t) + b*cos(w*t)
            array(true, '2 (3) a sin(b)^c - (sin(x+y)+x^y)^-sin(z)c tan(z)(x^2)'),

//            array(false, 'a-'),     Problem: parse error unexpected '(', expecting ',' or ')'
//            array(false, '*a'),        Problem: parseerror unexpected '*'
//            array(false, 'a**b'),     Problem : gives true rather than false.
            array(false, 'a+^c+f'),
            array(false, 'a+b^^+f'),
            array(false, 'a+(b^c)^+f'),
            array(false, 'a+((b^c)^d+f'),
            array(false, 'a+(b^c+f'),
            array(false, 'a+b^c)+f'),
            array(false, 'a+b^(c+f'),
            array(false, 'a+b)^c+f'),
            array(false, 'pi()'),
            array(false, 'sin 3'),
            array(false, '1+sin*(3)+2'),
            array(false, '1+sin^(3)+2'),
            array(false, 'a sin w t'),
            array(false, '1==2?3:4'),
            array(false, 'a=b'),
            array(false, '3&4'),
            array(false, '3==4'),
            array(false, '3&&4'),
            array(false, '3!'),
            array(false, '`'),
            array(false, '@'),
        );
        $v = $qv->vstack_create();
        $v = $qv->evaluate_assignments($v, 'a={1:10}; b={1:10}; c={1:10}; d={1:10}; e={1:10}; f={1:10}; t={1:10}; u={1:10}; v={1:10}; w={1:10}; x={1:10}; y={1:10}; z={1:10};');
        foreach ($testcases as $idx => $testcase) {
            try {
                $result = $qv->compute_algebraic_formula_difference($v, array($testcase[1]), array($testcase[1]), 100);
            } catch (Exception $e) { $result = null; }
            $eval = $result!==null;
            $this->assertEquals($testcase[0], $eval);
        }

        $v = $qv->vstack_create();
        $v = $qv->evaluate_assignments($v, 'x={-10:11:1}; y={-10:-5, 6:11};');
        $result = $qv->compute_algebraic_formula_difference($v, array('x','1+x+y+3','(1+sqrt(x))^2'), array('0','2+x+y+2','1+x'), 100);
        $this->assertEquals($result[1], 0);
        $this->assertEquals($result[2], INF);
//        var_dump($result);
        $result = $qv->compute_algebraic_formula_difference($v, array('x','(x+y)^2'), array('0','x^2+2*x*y+y^2'), 100);
        $this->assertEquals($result[1], 0);
//        var_dump($result);
    }
            
    /**
     * Test 8: Split formula unit.
     */
    public function test_split_formula_unit() {
        $qv = new qtype_formulas_variables;
        $testcases = array(
            // check for simple number and unit
            array('.3', array('.3','')),
            array('3.1', array('3.1','')),
            array('3.1e-10', array('3.1e-10','')),
            array('3m', array('3','m')),
            array('3kg m/s', array('3','kg m/s')),
            array('3.m/s', array('3.','m/s')),
            array('3.e-10m/s', array('3.e-10','m/s')),
            array('- 3m/s', array('- 3','m/s')),
            array('3 e10 m/s', array('3 ','e10 m/s')),
            array('3e 10 m/s', array('3','e 10 m/s')),
            array('3e8e8 m/s', array('3e8','e8 m/s')),
            array('3+10*4 m/s', array('3+10*4 ','m/s')),
            array('3+10^4 m/s', array('3+10^4 ','m/s')),
            array('sin(3) m/s', array('sin(3) ','m/s')),
            array('3+exp(4) m/s', array('3+exp(4) ','m/s')),
            array('3*4*5 m/s', array('3*4*5 ','m/s')),
            array('3 4 5 m/s', array('3 4 5 ','m/s')),
            array('m/s', array('','m/s')),
            array('#', array('','#')),

            // numeric and unit
            array('3+4 5+10^4kg m/s', array('3+4 5+10^4','kg m/s')),
            array('sin(3)kg m/s', array('sin(3)','kg m/s')),

            // numerical formula and unit
            array('3.1e-10kg m/s', array('3.1e-10','kg m/s')),
            array('-3kg m/s', array('-3','kg m/s')),
            array('- 3kg m/s', array('- 3','kg m/s')),
            array('3e', array('3','e')),
            array('3e8', array('3e8','')),
            array('3e8e', array('3e8','e')),
            array('3+4 5+10^4kg m/s', array('3+4 5+10^4','kg m/s')),
            array('sin(3)kg m/s', array('sin(3)','kg m/s')),
            array('3*4*5 kg m/s', array('3*4*5 ','kg m/s')),
            array('3 4 5 kg m/s', array('3 4 5 ','kg m/s')),
            array('3e8(4.e8+2)(.5e8/2)5kg m/s', array('3e8(4.e8+2)(.5e8/2)5','kg m/s')),
            array('3+exp(4+5)^sin(6+7)kg m/s', array('3+exp(4+5)^sin(6+7)','kg m/s')),
            array('3+exp(4+5)^-sin(6+7)kg m/s', array('3+exp(4+5)^-sin(6+7)','kg m/s')),
            array('3exp^2', array('3','exp^2')), // Note the unit is exp to the power 2
            array('3 e8', array('3 ','e8')),
            array('3e 8', array('3','e 8')),
            array('3e8e8', array('3e8','e8')),
            array('3e8e8e8', array('3e8','e8e8')),
            array('3+exp(4+5).m/s', array('3+exp(4+5)','.m/s')),
            array('3+(4.m/s', array('3+(4.','m/s')),
            array('3+4.)m/s', array('3+4.)','m/s')),
            array('3 m^', array('3 ','m^')),
            array('3 m/', array('3 ','m/')),
            array('3 /s', array('3 /','s')),
            array('3 m+s', array('3 ','m+s')),
            array('1==2?3:4', array('1','==2?3:4')),
            array('a=b', array('','a=b')),
            array('3&4', array('3','&4')),
            array('3==4', array('3','==4')),
            array('3&&4', array('3','&&4')),
            array('3!', array('3','!')),
            array('`', array('','`')),
            array('@', array('','@')),
        );
        $v = $qv->vstack_create();
        $v = $qv->evaluate_assignments($v, 'a={1:10}; b={1:10}; c={1:10}; d={1:10}; e={1:10}; f={1:10}; t={1:10}; u={1:10}; v={1:10}; w={1:10}; x={1:10}; y={1:10}; z={1:10};');
        foreach ($testcases as $idx => $testcase) {
            $result = $qv->split_formula_unit($testcase[0]);
            $this->assertEquals($testcase[1][0], $result[0]);
            $this->assertEquals($testcase[1][1], $result[1]);
        }
    }
}
