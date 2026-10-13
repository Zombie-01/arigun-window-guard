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

namespace quizaccess_privateexam\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;

/**
 * Records leave/return events. Time away is measured with server time, not trusted from the browser.
 *
 * @package    quizaccess_privateexam
 * @copyright  2026 Arigun Jamsran
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class report extends external_api {
    /**
     * Describe the parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'attemptid' => new external_value(PARAM_INT, 'Quiz attempt id'),
            'event' => new external_value(PARAM_ALPHA, 'leave or return'),
        ]);
    }

    /**
     * Record a leave or return and report whether the attempt has reached its limit.
     *
     * @param int $attemptid the student's own in-progress attempt
     * @param string $event 'leave' or 'return'
     * @return array counters, limits and whether the limit was reached
     */
    public static function execute(int $attemptid, string $event): array {
        global $DB, $USER;
        ['attemptid' => $attemptid, 'event' => $event] =
            self::validate_parameters(self::execute_parameters(), ['attemptid' => $attemptid, 'event' => $event]);

        $attempt = $DB->get_record(
            'quiz_attempts',
            ['id' => $attemptid, 'userid' => $USER->id, 'state' => 'inprogress'],
            '*',
            MUST_EXIST
        );
        $cm = get_coursemodule_from_instance('quiz', $attempt->quiz, 0, false, MUST_EXIST);
        $context = \context_module::instance($cm->id);
        self::validate_context($context);
        require_capability('mod/quiz:attempt', $context);
        $settings = $DB->get_record('quizaccess_privateexam', ['quizid' => $attempt->quiz], '*', MUST_EXIST);

        $log = $DB->get_record('quizaccess_privateexam_log', ['attemptid' => $attemptid]);
        if (!$log) {
            $log = (object) ['attemptid' => $attemptid, 'switches' => 0, 'awaysecs' => 0, 'leftat' => 0, 'timeexceeded' => 0];
            $log->id = $DB->insert_record('quizaccess_privateexam_log', $log);
        }

        $now = time();
        if ($event === 'leave' && !$log->leftat) {
            $log->switches++;
            $log->leftat = $now;
        } else if ($event === 'return' && $log->leftat) {
            $log->awaysecs += $now - $log->leftat;
            $log->leftat = 0;
            // Checked on return (not on leave) so the auto-submit happens inside the quiz grace period and keeps answers.
            $toomany = $settings->maxswitches && $log->switches >= $settings->maxswitches;
            $toolong = $settings->maxaway && $log->awaysecs >= $settings->maxaway;
            if (!$log->timeexceeded && ($toomany || $toolong)) {
                $log->timeexceeded = $now;
            }
        }
        $DB->update_record('quizaccess_privateexam_log', $log);

        return [
            'switches' => (int) $log->switches,
            'away' => (int) $log->awaysecs,
            'maxswitches' => (int) $settings->maxswitches,
            'maxaway' => (int) $settings->maxaway,
            'exceeded' => (bool) $log->timeexceeded,
        ];
    }

    /**
     * Describe the return value.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'switches' => new external_value(PARAM_INT, 'Times left the window'),
            'away' => new external_value(PARAM_INT, 'Total seconds away'),
            'maxswitches' => new external_value(PARAM_INT, 'Limit, 0 = unlimited'),
            'maxaway' => new external_value(PARAM_INT, 'Limit in seconds, 0 = unlimited'),
            'exceeded' => new external_value(PARAM_BOOL, 'Limit reached, attempt must end'),
        ]);
    }
}
