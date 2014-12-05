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
 * return the instantiated dataset of the variables in the form of JSON.
 *
 * @copyright &copy; 2011 Hon Wai, Lau
 * @author Hon Wai, Lau <lau65536@gmail.com>
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License version 3
 * @package qtype_formulas
 */

require_once("../../../config.php");
require_once("variables.php");
require_login();
$qv = new qtype_formulas_variables();

/// Given the variable assignments, it try to instantiate multiple datasets and return a data structure used by javascript
function instantiate_multiple_datasets($varsrandom, $varsglobal, $varslocals, $answers, $start, $N, $always_random) {
    global $qv;
    $show_all = ($N < 0);   // if $N is small than 0, it will try to enumerate all possible combination, if # dataset < 1000
    $vr_info = $qv->parse_random_variables($varsrandom);
    $maxdataset = $qv->vstack_get_number_of_dataset($vr_info);   // it is the same for all instantiation
    if ($show_all)  $N = min(1000, $maxdataset);   // dynamic resize to the same # as exhaustive enumeration, limited to 1000
    $hasshuffle = $qv->vstack_get_has_shuffle($vr_info);
    if ($N>=$maxdataset && !$hasshuffle)  $N = $maxdataset;     // there is no need to generate redundant dataset if there is no shuffle assignment

    $names = array();
    $data = array();
    $errors = array();
    for ($count=0; $count<$N; $count++) {
        $errors[$count] = '';
        $v = array();
        try {
            $datasetid = ($always_random || $N<$maxdataset) ? -1 : $start+$count;   // use enumeration if possible, -1 means random
            $v['random'] = $qv->instantiate_random_variables($vr_info, $datasetid);
            $names['random'] = isset($names['random']) ? $names['random'] + $v['random']->all : $v['random']->all;
            $v['global'] = $qv->evaluate_assignments($v['random'], $varsglobal);
            $names['global'] = isset($names['global']) ? $names['global'] + $v['global']->all : $v['global']->all;

            foreach ($varslocals as $idx => $varslocal) {
                $v['local'.$idx] = $qv->evaluate_assignments($v['global'], $varslocals[$idx]);
                $names['local'.$idx] = isset($names['local'.$idx]) ? $names['local'.$idx] + $v['local'.$idx]->all : $v['local'.$idx]->all;
                if (strlen(trim($answers[$idx])) == 0)  continue;
                $res = $qv->evaluate_general_expression($v['local'.$idx], $answers[$idx]);
                if ($res->type[0] != 'l') {
                    $res->type = 'l'.$res->type;
                    $res->value = array($res->value);   // change all answers to array
                }
                if ($res->type[1] == 's')
                    $res->value = $qv->substitute_partial_formula($v['local'.$idx], $res->value);
                $vstack = $qv->vstack_create();
                $qv->vstack_update_variable($vstack, '@'.($idx+1), null, $res->type, $res->value);
                $v['answer'.$idx] = $vstack;
                $names['answer'.$idx] = $vstack->all;
            }
        } catch (Exception $e) { $errors[$count] = $e->getMessage(); }   // skip all error and go to the next instantiation
        $data[] = $v;
    }

    // filter the repeated variables
    $idx = 0;
    while (isset($names['local'.$idx])) {
        $names['answer'.$idx] = filter_redundant_names($data, $names, 'answer'.$idx, '');
        $names['local'.$idx] = filter_redundant_names($data, $names, 'local'.$idx, 'global');
        $idx++;
    }
    $names['global'] = filter_redundant_names($data, $names, 'global', 'random');
    $names['random'] = filter_redundant_names($data, $names, 'random', '');

    // instantiate the variables and get the values
    $lists = array();
    for ($count=0; $count<$N; $count++) {
        $s = array();
        foreach ($names as $category => $n)
            $s[$category] = pick_variables_with_names($data, $names, $category, $count);
        $lists[] = $s;
    }
    return json_encode(array('names' => $names, 'lists' => $lists, 'size' => $N, 'maxdataset' => $maxdataset, 'errors' => $errors));
}




/// filter out the unused variable name in the table header
function filter_redundant_names($data, $names, $A, $B) {
    global $qv;
    $tmp = array();
    if (!array_key_exists($A, $names))  return null;
    foreach ($names[$A] as $n => $notused)
        if (check_include_name($data, $names, $A, $B, $n))  $tmp[] = $n;
    return $tmp;
}


/// check whether the name should be included
function check_include_name($data, $names, $A, $B, $n) {
    global $qv;
    if (!array_key_exists($B, $names) || !array_key_exists($n, $names[$B]))
        return true;
    for ($i=0; $i<count($data); $i++) {
        if (!array_key_exists($B, $data[$i]))  return true;
        if (!array_key_exists($A, $data[$i]))  return true;
        $new = $qv->vstack_get_variable($data[$i][$B], $n);
        $old = $qv->vstack_get_variable($data[$i][$A], $n);
        if ($new !== $old)  return true;
    }
    return false;
}


/// pick the corresponding variable value listed in the names[category]
function pick_variables_with_names($data, $names, $category, $idx) {
    global $qv;
    if (!array_key_exists($category, $data[$idx]))  return null;
    $d = $data[$idx][$category];
    $res = array();
    for ($i=0; $i<count($names[$category]); $i++) {
        $name = $names[$category][$i];
        $tmp = $qv->vstack_get_variable($d, $name);
        if ($tmp === null) {
            $res[$name] = null;
            continue;
        }
        $res[] = $tmp->value;
    }
    return $res;
}



try {
    $varsrandom = $_POST['varsrandom'];
    $varsglobal = $_POST['varsglobal'];
    $varslocals = $_POST['varslocals'];
    $answers = $_POST['answers'];
    $start = $_POST['start'];
    $N = $_POST['N'];
    $always_random = $_POST['random'];
    $res = instantiate_multiple_datasets($varsrandom, $varsglobal, $varslocals, $answers, $start, $N, $always_random);
    echo $res;
} catch (Exception $e) {}   // prevent the display of all other errors

