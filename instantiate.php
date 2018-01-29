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

define('AJAX_SCRIPT', true);

require_once(__DIR__ . '/../../../config.php');
require_once(__DIR__ . '/variables.php');

$qv = new qtype_formulas_variables();

// Given the variable assignments, it try to instantiate multiple datasets and return a data structure used by javascript.
function instantiate_multiple_datasets($varsrandom, $varsglobal, $varslocals, $answers, $start, $nbdataset, $alwaysrandom) {
    global $qv;
    $showall = ($nbdataset < 0);   // If $nbdataset > 0, it will try to enumerate all possible combinations, if # dataset < 1000.
    $vrinfo = $qv->parse_random_variables($varsrandom);
    $maxdataset = $qv->vstack_get_number_of_dataset($vrinfo);   // It is the same for all instantiations.
    if ($showall) {
        $nbdataset = min(1000, $maxdataset);   // Dynamic resize to the same # as exhaustive enumeration, limited to 1000.
    }
    $hasshuffle = $qv->vstack_get_has_shuffle($vrinfo);
    if ($nbdataset >= $maxdataset && !$hasshuffle) {
        $nbdataset = $maxdataset;     // There is no need to generate redundant datasets if there is no shuffle assignment.
    }

    $names = array();
    $data = array();
    $errors = array();
    for ($count = 0; $count < $nbdataset; $count++) {
        $errors[$count] = '';
        $v = array();
        try {
            $datasetid = ($alwaysrandom || $nbdataset < $maxdataset) ? -1 : $start + $count;   // Use enumeration if possible, -1 means random.
            $v['random'] = $qv->instantiate_random_variables($vrinfo, $datasetid);
            $names['random'] = isset($names['random']) ? $names['random'] + $v['random']->all : $v['random']->all;
            $v['global'] = $qv->evaluate_assignments($v['random'], $varsglobal);
            $names['global'] = isset($names['global']) ? $names['global'] + $v['global']->all : $v['global']->all;

            foreach ($varslocals as $idx => $varslocal) {
                $v['local'.$idx] = $qv->evaluate_assignments($v['global'], $varslocals[$idx]);
                $names['local'.$idx] = isset($names['local'.$idx]) ? $names['local'.$idx] + $v['local'.$idx]->all : $v['local'.$idx]->all;
                if (strlen(trim($answers[$idx])) == 0) {
                    continue;
                }
                $res = $qv->evaluate_general_expression($v['local'.$idx], $answers[$idx]);
                if ($res->type[0] != 'l') {
                    $res->type = 'l'.$res->type;
                    $res->value = array($res->value);   // Change all answers to arrays.
                }
                if ($res->type[1] == 's') {
                    $res->value = $qv->substitute_partial_formula($v['local'.$idx], $res->value);
                }
                $vstack = $qv->vstack_create();
                $qv->vstack_update_variable($vstack, '@'.($idx + 1), null, $res->type, $res->value);
                $v['answer'.$idx] = $vstack;
                $names['answer'.$idx] = $vstack->all;
            }
        } catch (Exception $e) {
            $errors[$count] = $e->getMessage();
        }   // Skip all errors and go to the next instantiation.
        $data[] = $v;
    }

    // Filter the repeated variables.
    $idx = 0;
    while (isset($names['local'.$idx])) {
        $names['answer'.$idx] = filter_redundant_names($data, $names, 'answer'.$idx, '');
        $names['local'.$idx] = filter_redundant_names($data, $names, 'local'.$idx, 'global');
        $idx++;
    }
    $names['global'] = filter_redundant_names($data, $names, 'global', 'random');
    $names['random'] = filter_redundant_names($data, $names, 'random', '');

    // Instantiate the variables and get the values.
    $lists = array();
    for ($count = 0; $count < $nbdataset; $count++) {
        $s = array();
        foreach ($names as $category => $n) {
            $s[$category] = pick_variables_with_names($data, $names, $category, $count);
        }
        $lists[] = $s;
    }
    return json_encode(array('names' => $names, 'lists' => $lists, 'size' => $nbdataset, 'maxdataset' => $maxdataset, 'errors' => $errors));
}




// Filter out the unused variable names in the table header.
function filter_redundant_names($data, $names, $a, $b) {
    global $qv;
    $tmp = array();
    if (!array_key_exists($a, $names)) {
        return null;
    }
    foreach ($names[$a] as $n => $notused) {
        if (check_include_name($data, $names, $a, $b, $n)) {
            $tmp[] = $n;
        }
    }
    return $tmp;
}


// Check whether the name should be included.
function check_include_name($data, $names, $a, $b, $n) {
    global $qv;
    if (!array_key_exists($b, $names) || !array_key_exists($n, $names[$b])) {
        return true;
    }
    for ($i = 0; $i < count($data); $i++) {
        if (!array_key_exists($b, $data[$i])) {
            return true;
        }
        if (!array_key_exists($a, $data[$i])) {
            return true;
        }
        $new = $qv->vstack_get_variable($data[$i][$b], $n);
        $old = $qv->vstack_get_variable($data[$i][$a], $n);
        if ($new !== $old) {
             return true;
        }
    }
    return false;
}


// Pick the corresponding variable value listed in the names[category].
function pick_variables_with_names($data, $names, $category, $idx) {
    global $qv;
    if (!array_key_exists($category, $data[$idx])) {
        return null;
    }
    $d = $data[$idx][$category];
    $res = array();
    for ($i = 0; $i < count($names[$category]); $i++) {
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
    $varsrandom = required_param('varsrandom', PARAM_RAW);
    $varsglobal = required_param('varsglobal', PARAM_RAW);
    $varslocals = required_param('varslocals', PARAM_RAW);
    $answers = required_param('answers', PARAM_RAW);
    $start = optional_param('start', 0, PARAM_INT);
    $nbdataset = required_param('N', PARAM_INT);
    $alwaysrandom = optional_param('random', 0, PARAM_INT);

    $res = instantiate_multiple_datasets($varsrandom, $varsglobal, $varslocals, $answers, $start, $nbdataset, $alwaysrandom);
    header('Content-type: application/json; charset=utf-8');
    echo $res;
} catch (Exception $e) {
    // Prevent the display of all errors.
}

