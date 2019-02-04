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
 * Formulas question type upgrade code.
 *
 * @package    qtype_formulas
 * @copyright  2010 Hon Wai, Lau <lau65536@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// This file keeps track of upgrades to
// the formulas qtype plugin.
function xmldb_qtype_formulas_upgrade($oldversion=0) {
    global $DB, $CFG;

    $dbman = $DB->get_manager();

    // Moodle v1.9.0 release upgrade line.
    // Put any upgrade step following this.

    // Add the format for the subqtext and feedback.
    if ($oldversion < 2011080200) {
        // Define field subqtextformat to be added to question_formulas_answers.
        $table = new xmldb_table('question_formulas_answers');
        $field = new xmldb_field('subqtextformat', XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, null, '0', 'subqtext');

        // Conditionally launch add field subqtextformat.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Define field feedbackformat to be added to question_formulas_answers.
        $table = new xmldb_table('question_formulas_answers');
        $field = new xmldb_field('feedbackformat', XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, null, '0', 'feedback');

        // Conditionally launch add field feedbackformat.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Formulas savepoint reached.
        upgrade_plugin_savepoint(true, 2011080200, 'qtype', 'formulas');
    }

    // Drop the answerids field wich is totaly redundant.
    if ($oldversion < 2011080700) {
        $table = new xmldb_table('question_formulas');
        $field = new xmldb_field('answerids');

        if ($dbman->field_exists($table, $field)) {
            $dbman->drop_field($table, $field);
        }
        upgrade_plugin_savepoint(true, 2011080700, 'qtype', 'formulas');
    }

    // Moodle v2.1.0 release upgrade line.
    // Put any upgrade step following this.

    if ($oldversion < 2012071400) {
        // Renaming old tables.
        $table = new xmldb_table('question_formulas');
        if ($dbman->table_exists($table)) {
            $dbman->rename_table($table, 'qtype_formulas');
        }
        $table = new xmldb_table('question_formulas_answers');
        if ($dbman->table_exists($table)) {
            $dbman->rename_table($table, 'qtype_formulas_answers');
        }

        // Add combined feedback fields.
        $table = new xmldb_table('qtype_formulas');

        // Define field correctfeedback to be added to qtype_formulas.
        $field = new xmldb_field('correctfeedback', XMLDB_TYPE_TEXT, 'small', null,
                null, null, null, 'showperanswermark');

        // Conditionally launch add field correctfeedback.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);

            // Now fill it with ''.
            $DB->set_field('qtype_formulas', 'correctfeedback', '');

            // Now add the not null constraint.
            $field = new xmldb_field('correctfeedback', XMLDB_TYPE_TEXT, 'small', null,
                    XMLDB_NOTNULL, null, null, 'showperanswermark');
            $dbman->change_field_notnull($table, $field);
        }

        // Define field correctfeedbackformat to be added to qtype_formulas.
        $field = new xmldb_field('correctfeedbackformat', XMLDB_TYPE_INTEGER, '2', null,
                XMLDB_NOTNULL, null, '0', 'correctfeedback');

        // Conditionally launch add field correctfeedbackformat.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Define field partiallycorrectfeedback to be added to qtype_formulas.
        $field = new xmldb_field('partiallycorrectfeedback', XMLDB_TYPE_TEXT, 'small', null,
                null, null, null, 'correctfeedbackformat');

        // Conditionally launch add field partiallycorrectfeedback.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);

            // Now fill it with ''.
            $DB->set_field('qtype_formulas', 'partiallycorrectfeedback', '');

            // Now add the not null constraint.
            $field = new xmldb_field('partiallycorrectfeedback', XMLDB_TYPE_TEXT, 'small', null,
                    XMLDB_NOTNULL, null, null, 'correctfeedbackformat');
            $dbman->change_field_notnull($table, $field);
        }

        // Define field partiallycorrectfeedbackformat to be added to qtype_formulas.
        $field = new xmldb_field('partiallycorrectfeedbackformat', XMLDB_TYPE_INTEGER, '2', null,
                XMLDB_NOTNULL, null, '0', 'partiallycorrectfeedback');

        // Conditionally launch add field partiallycorrectfeedbackformat.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Define field incorrectfeedback to be added to qtype_formulas.
        $field = new xmldb_field('incorrectfeedback', XMLDB_TYPE_TEXT, 'small', null,
                null, null, null, 'partiallycorrectfeedbackformat');

        // Conditionally launch add field incorrectfeedback.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);

            // Now fill it with ''.
            $DB->set_field('qtype_formulas', 'incorrectfeedback', '');

            // Now add the not null constraint.
            $field = new xmldb_field('incorrectfeedback', XMLDB_TYPE_TEXT, 'small', null,
                    XMLDB_NOTNULL, null, null, 'partiallycorrectfeedbackformat');
            $dbman->change_field_notnull($table, $field);
        }

        // Define field incorrectfeedbackformat to be added to qtype_formulas.
        $field = new xmldb_field('incorrectfeedbackformat', XMLDB_TYPE_INTEGER, '2', null,
                XMLDB_NOTNULL, null, '0', 'incorrectfeedback');

        // Conditionally launch add field incorrectfeedbackformat.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Define field shownumcorrect to be added to qtype_formulas.
        $field = new xmldb_field('shownumcorrect', XMLDB_TYPE_INTEGER, '2', null,
                XMLDB_NOTNULL, null, '0', 'incorrectfeedbackformat');

        // Conditionally launch add field shownumcorrect.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Formulas savepoint reached.
        upgrade_plugin_savepoint(true, 2012071400, 'qtype', 'formulas');
    }

    if ($oldversion < 2012071401) {
        // Suppress some obsolete fields.
        $table = new xmldb_table('qtype_formulas');
        $field = new xmldb_field('peranswersubmit');

        if ($dbman->field_exists($table, $field)) {
            $dbman->drop_field($table, $field);
        }
        $field = new xmldb_field('showperanswermark');

        if ($dbman->field_exists($table, $field)) {
            $dbman->drop_field($table, $field);
        }
        $table = new xmldb_table('qtype_formulas_answers');
        $field = new xmldb_field('trialmarkseq');

        if ($dbman->field_exists($table, $field)) {
            $dbman->drop_field($table, $field);
        }

        // Formulas savepoint reached.
        upgrade_plugin_savepoint(true, 2012071401, 'qtype', 'formulas');
    }

    if ($oldversion < 2012071402) {
        // Define table qtype_formulas to be renamed to qtype_formulas_options.
        $table = new xmldb_table('qtype_formulas');

        // Launch rename table for qtype_formulas_options.
        $dbman->rename_table($table, 'qtype_formulas_options');

        // Formulas savepoint reached.
        upgrade_plugin_savepoint(true, 2012071402, 'qtype', 'formulas');
    }

    if ($oldversion < 2012071406) {

        // Define field partindex to be added to qtype_formulas_answers.
        $table = new xmldb_table('qtype_formulas_answers');
        $field = new xmldb_field('partindex', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'questionid');

        // Conditionally launch add field partindex.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Formulas savepoint reached.
        upgrade_plugin_savepoint(true, 2012071406, 'qtype', 'formulas');
    }

    if ($oldversion < 2012071407) {
        // Get all formulas questions.
        $questions = $DB->get_records('question',
                array('qtype' => 'formulas'), 'id');
        foreach ($questions as $question) {
            $anscount = 0;
            $rs = $DB->get_recordset('qtype_formulas_answers', array('questionid' => $question->id),
                   'id');
            foreach ($rs as $record) {
                $record->partindex = $anscount;
                $DB->update_record('qtype_formulas_answers', $record);
                ++$anscount;
            }
            $rs->close();
        }
        // Formulas savepoint reached.
        upgrade_plugin_savepoint(true, 2012071407, 'qtype', 'formulas');
    }

    if ($oldversion < 2018042800) {
        // Add combined feedback fields for each question part.
        $table = new xmldb_table('qtype_formulas_answers');

        // Define field partcorrectfb to be added to qtype_formulas_answers.
        $field = new xmldb_field('partcorrectfb', XMLDB_TYPE_TEXT, 'small', null,
                null, null, null, 'feedbackformat');

        // Conditionally launch add field partcorrectfb.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);

            // Now fill it with ''.
            $DB->set_field('qtype_formulas_answers', 'partcorrectfb', '');

            // Now add the not null constraint.
            $field = new xmldb_field('partcorrectfb', XMLDB_TYPE_TEXT, 'small', null,
                    XMLDB_NOTNULL, null, null, 'feedbackformat');
            $dbman->change_field_notnull($table, $field);
        }

        // Define field partcorrectfbformat to be added to qtype_formulas_answers.
        $field = new xmldb_field('partcorrectfbformat', XMLDB_TYPE_INTEGER, '2', null,
                XMLDB_NOTNULL, null, '0', 'partcorrectfb');

        // Conditionally launch add field partcorrectfbformat.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
            // Now fill it with FORMAT_HTML.
            $DB->set_field('qtype_formulas_answers', 'partcorrectfbformat', FORMAT_HTML);

        }

        // Define field partpartiallycorrectfb to be added to qtype_formulas_answers.
        $field = new xmldb_field('partpartiallycorrectfb', XMLDB_TYPE_TEXT, 'small', null,
                null, null, null, 'partcorrectfbformat');

        // Conditionally launch add field partpartiallycorrectfb.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);

            // Now fill it with ''.
            $DB->set_field('qtype_formulas_answers', 'partpartiallycorrectfb', '');

            // Now add the not null constraint.
            $field = new xmldb_field('partpartiallycorrectfb', XMLDB_TYPE_TEXT, 'small', null,
                    XMLDB_NOTNULL, null, null, 'partcorrectfbformat');
            $dbman->change_field_notnull($table, $field);
        }

        // Define field partpartiallycorrectfbformat to be added to qtype_formulas_answers.
        $field = new xmldb_field('partpartiallycorrectfbformat', XMLDB_TYPE_INTEGER, '2', null,
                XMLDB_NOTNULL, null, '0', 'partpartiallycorrectfb');

        // Conditionally launch add field partpartiallycorrectfbformat.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
            // Now fill it with FORMAT_HTML.
            $DB->set_field('qtype_formulas_answers', 'partpartiallycorrectfbformat', FORMAT_HTML);
        }

        // Define field partincorrectfb to be added to qtype_formulas_answers.
        $field = new xmldb_field('partincorrectfb', XMLDB_TYPE_TEXT, 'small', null,
                null, null, null, 'partpartiallycorrectfbformat');

        // Conditionally launch add field partincorrectfb.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);

            // Now fill it with ''.
            $DB->set_field('qtype_formulas_answers', 'partincorrectfb', '');

            // Now add the not null constraint.
            $field = new xmldb_field('partincorrectfb', XMLDB_TYPE_TEXT, 'small', null,
                    XMLDB_NOTNULL, null, null, 'partpartiallycorrectfbformat');
            $dbman->change_field_notnull($table, $field);
        }

        // Define field partincorrectfbformat to be added to qtype_formulas_answers.
        $field = new xmldb_field('partincorrectfbformat', XMLDB_TYPE_INTEGER, '2', null,
                XMLDB_NOTNULL, null, '0', 'partincorrectfb');

        // Conditionally launch add field partincorrectfbformat.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
            // Now fill it with FORMAT_HTML.
            $DB->set_field('qtype_formulas_answers', 'partincorrectfbformat', FORMAT_HTML);
        }

        // Formulas savepoint reached.
        upgrade_plugin_savepoint(true, 2018042800, 'qtype', 'formulas');
    }
    if ($oldversion < 2018042801) {
        $sql = "UPDATE {qtype_formulas_answers} SET partincorrectfb = feedback";
        $DB->execute($sql);
        $DB->set_field('qtype_formulas_answers', 'feedback', '');
        upgrade_plugin_savepoint(true, 2018042801, 'qtype', 'formulas');
    }

    if ($oldversion < 2018060400) {
        // Define field answernumbering to be added to qtype_formulas_options.
        $table = new xmldb_table('qtype_formulas_options');
        $field = new xmldb_field('answernumbering', XMLDB_TYPE_CHAR, '10', null, XMLDB_NOTNULL, null, 'none', 'shownumcorrect');

        // Conditionally launch add field answernumbering.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
            // Now fill it with 'none' for compatibility with existing questions'.
            $DB->set_field('qtype_formulas_options', 'answernumbering', 'none');
        }

        // Formulas savepoint reached.
        upgrade_plugin_savepoint(true, 2018060400, 'qtype', 'formulas');
    }

    if ($oldversion < 2018080300) {
        // Import from xml code was wrong for answernumbering,
        // There was also a typo in the upgrade code.
        // Fix all broken questions in database.
        $DB->set_field('qtype_formulas_options', 'answernumbering', 'none', array('answernumbering' => ''));

        // Formulas savepoint reached.
        upgrade_plugin_savepoint(true, 2018080300, 'qtype', 'formulas');
    }
    return true;
}
