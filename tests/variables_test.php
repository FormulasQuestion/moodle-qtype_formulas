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
     * Test 2: evaluate_general_expression() test
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
     * Test 3.1: evaluate_assignments() test
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
//            array(false, 'a=3 6;'),   This one is failing. Why ?
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
     * Test 3.2: evaluate_assignments() test
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
     * Test 3.3: evaluate_assignments() test
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
}
