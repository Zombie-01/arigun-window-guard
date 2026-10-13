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

namespace quizaccess_privateexam\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\transform;
use mod_quiz\quiz_settings;

/**
 * Privacy provider. The per-attempt counters belong to the attempt's student; mod_quiz finds the
 * contexts and users through its attempts and calls these hooks.
 *
 * @package    quizaccess_privateexam
 * @copyright  2026 Arigun Jamsran
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \mod_quiz\privacy\quizaccess_provider,
    \mod_quiz\privacy\quizaccess_user_provider {
    /**
     * Describe the stored personal data.
     *
     * @param collection $collection the collection to add to
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('quizaccess_privateexam_log', [
            'attemptid' => 'privacy:metadata:log:attemptid',
            'switches' => 'privacy:metadata:log:switches',
            'awaysecs' => 'privacy:metadata:log:awaysecs',
            'leftat' => 'privacy:metadata:log:leftat',
            'timeexceeded' => 'privacy:metadata:log:timeexceeded',
        ], 'privacy:metadata:log');
        return $collection;
    }

    /**
     * Export the user's counters for one quiz.
     *
     * @param quiz_settings $quiz the quiz
     * @param \stdClass $user the user
     * @return \stdClass
     */
    public static function export_quizaccess_user_data(quiz_settings $quiz, \stdClass $user): \stdClass {
        global $DB;
        $records = $DB->get_records_sql(
            'SELECT qa.attempt, l.switches, l.awaysecs, l.timeexceeded
               FROM {quizaccess_privateexam_log} l
               JOIN {quiz_attempts} qa ON qa.id = l.attemptid
              WHERE qa.quiz = :quizid AND qa.userid = :userid
           ORDER BY qa.attempt',
            ['quizid' => $quiz->get_quizid(), 'userid' => $user->id]
        );
        if (!$records) {
            return (object) [];
        }
        $attempts = [];
        foreach ($records as $r) {
            $attempts[] = (object) [
                'attempt' => $r->attempt,
                'timesleftwindow' => $r->switches,
                'secondsaway' => $r->awaysecs,
                'limitreached' => $r->timeexceeded ? transform::datetime($r->timeexceeded) : null,
            ];
        }
        return (object) ['attempts' => $attempts];
    }

    /**
     * Delete everyone's counters for one quiz.
     *
     * @param quiz_settings $quiz the quiz
     */
    public static function delete_quizaccess_data_for_all_users_in_context(quiz_settings $quiz) {
        self::delete_logs($quiz->get_quizid());
    }

    /**
     * Delete one user's counters for one quiz.
     *
     * @param quiz_settings $quiz the quiz
     * @param \stdClass $user the user
     */
    public static function delete_quizaccess_data_for_user(quiz_settings $quiz, \stdClass $user) {
        self::delete_logs($quiz->get_quizid(), [$user->id]);
    }

    /**
     * Delete several users' counters in one quiz context.
     *
     * @param approved_userlist $userlist the users and the context
     */
    public static function delete_quizaccess_data_for_users(approved_userlist $userlist) {
        $context = $userlist->get_context();
        if ($context->contextlevel != CONTEXT_MODULE) {
            return;
        }
        $cm = get_coursemodule_from_id('quiz', $context->instanceid);
        if ($cm && $userlist->get_userids()) {
            self::delete_logs($cm->instance, $userlist->get_userids());
        }
    }

    /**
     * Delete counters of a quiz's attempts, optionally only those of some users.
     *
     * @param int $quizid the quiz id
     * @param int[]|null $userids limit to these users, null for everyone
     */
    private static function delete_logs(int $quizid, ?array $userids = null): void {
        global $DB;
        $where = 'quiz = :quizid';
        $params = ['quizid' => $quizid];
        if ($userids !== null) {
            [$insql, $inparams] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);
            $where .= " AND userid $insql";
            $params += $inparams;
        }
        $DB->delete_records_select(
            'quizaccess_privateexam_log',
            "attemptid IN (SELECT id FROM {quiz_attempts} WHERE $where)",
            $params
        );
    }
}
