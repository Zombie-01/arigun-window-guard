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

use mod_quiz\local\access_rule_base;
use mod_quiz\quiz_settings;

/**
 * Counts how often and how long a student leaves the quiz window and ends the attempt at a limit.
 *
 * @package    quizaccess_privateexam
 * @copyright  2026 Arigun Jamsran
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class quizaccess_privateexam extends access_rule_base {
    /**
     * Return an instance of this rule if the quiz has it enabled.
     *
     * @param quiz_settings $quizobj the quiz
     * @param int $timenow the time that should be considered as 'now'
     * @param bool $canignoretimelimits whether the current user is exempt from time limits
     * @return self|null
     */
    public static function make(quiz_settings $quizobj, $timenow, $canignoretimelimits) {
        if (empty($quizobj->get_quiz()->privateexamenabled)) {
            return null;
        }
        return new self($quizobj, $timenow);
    }

    /**
     * Add the rule's fields to the quiz settings form.
     *
     * @param mod_quiz_mod_form $quizform the quiz settings form
     * @param MoodleQuickForm $mform the wrapped form
     */
    public static function add_settings_form_fields(mod_quiz_mod_form $quizform, MoodleQuickForm $mform) {
        $mform->addElement('advcheckbox', 'privateexamenabled', get_string('enabled', 'quizaccess_privateexam'));
        $mform->addHelpButton('privateexamenabled', 'enabled', 'quizaccess_privateexam');

        foreach (['maxswitches' => 3, 'maxaway' => 30] as $name => $default) {
            $mform->addElement('text', 'privateexam' . $name, get_string($name, 'quizaccess_privateexam'));
            $mform->setType('privateexam' . $name, PARAM_INT);
            $mform->setDefault('privateexam' . $name, $default);
            $mform->hideIf('privateexam' . $name, 'privateexamenabled');
        }
    }

    /**
     * Save the rule's settings. A row in the table means the rule is enabled.
     *
     * @param stdClass $quiz the data from the quiz form
     */
    public static function save_settings($quiz) {
        global $DB;
        if (empty($quiz->privateexamenabled)) {
            $DB->delete_records('quizaccess_privateexam', ['quizid' => $quiz->id]);
            return;
        }
        $record = (object) [
            'quizid' => $quiz->id,
            'maxswitches' => max(0, (int) $quiz->privateexammaxswitches),
            'maxaway' => max(0, (int) $quiz->privateexammaxaway),
        ];
        if ($record->id = $DB->get_field('quizaccess_privateexam', 'id', ['quizid' => $quiz->id])) {
            $DB->update_record('quizaccess_privateexam', $record);
        } else {
            $DB->insert_record('quizaccess_privateexam', $record);
        }
    }

    /**
     * Delete the rule's settings and counters when the quiz is deleted.
     *
     * @param stdClass $quiz the quiz being deleted
     */
    public static function delete_settings($quiz) {
        global $DB;
        $DB->delete_records_select(
            'quizaccess_privateexam_log',
            'attemptid IN (SELECT id FROM {quiz_attempts} WHERE quiz = ?)',
            [$quiz->id]
        );
        $DB->delete_records('quizaccess_privateexam', ['quizid' => $quiz->id]);
    }

    /**
     * Load the rule's settings together with the quiz.
     *
     * @param int $quizid the quiz id
     * @return array [fields, joins, params]
     */
    public static function get_settings_sql($quizid) {
        return [
            'qa_pe.id AS privateexamenabled, qa_pe.maxswitches AS privateexammaxswitches, ' .
                'qa_pe.maxaway AS privateexammaxaway',
            'LEFT JOIN {quizaccess_privateexam} qa_pe ON qa_pe.quizid = quiz.id',
            [],
        ];
    }

    /**
     * Tell the student about the rule before they start.
     *
     * @return string
     */
    public function description() {
        return get_string('description', 'quizaccess_privateexam', [
            'maxswitches' => $this->quiz->privateexammaxswitches ?: '∞',
            'maxaway' => $this->quiz->privateexammaxaway ?: '∞',
        ]);
    }

    /**
     * Once the limit is hit the attempt's end time is that moment, so core quiz treats the attempt as
     * timed out and handles it per the quiz's "When time expires" setting.
     *
     * @param stdClass $attempt the attempt
     * @return int|false
     */
    public function end_time($attempt) {
        global $DB;
        if (empty($attempt->id)) {
            return false;
        }
        $exceeded = $DB->get_field('quizaccess_privateexam_log', 'timeexceeded', ['attemptid' => $attempt->id]);
        return $exceeded ? (int) $exceeded : false;
    }

    /**
     * Load the window monitor on the attempt and summary pages.
     *
     * @param moodle_page $page the page being set up
     */
    public function setup_attempt_page($page) {
        $attemptid = optional_param('attempt', 0, PARAM_INT);
        if ($attemptid && in_array($page->pagetype, ['mod-quiz-attempt', 'mod-quiz-summary'])) {
            $page->requires->js_call_amd('quizaccess_privateexam/monitor', 'init', [$attemptid]);
        }
    }
}
