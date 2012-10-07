<?php
/**
 * Used to save and restore image correctly
 * 
 * @copyright &copy; 2010-2011 Hon Wai, Lau
 * @author Hon Wai, Lau <lau65536@gmail.com>
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License version 3
 * @package questionbank
 * @subpackage questiontypes
 */

function qtype_formulas_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options=array()) {
    global $CFG;
    require_once($CFG->libdir . '/questionlib.php');
    question_pluginfile($course, $context, 'qtype_formulas', $filearea, $args, $forcedownload, $options);
}
