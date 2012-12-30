/**
 * Javascript function for the quiz interface of formulas question type
 *
 * @copyright &copy; 2010-2011 Hon Wai, Lau
 * @author Hon Wai, Lau <lau65536@gmail.com>
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License version 3
 */

function formulas_submit(submit_location_id, submit_button_name, subans_track_id, subans_num, qid) {
    var insert_location = document.getElementById(submit_location_id);
    insert_location.innerHTML = "<input name='" + submit_button_name + "' value=''>";
    var subans_tracking = document.getElementById(subans_track_id);
    subans_tracking.value = subans_num;
    var responseform = document.getElementById('responseform');
    responseform.action = responseform.action + '#q' + qid;
    responseform.submit();
}
