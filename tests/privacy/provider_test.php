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

use core_privacy\local\request\approved_userlist;
use mod_quiz\quiz_settings;

/**
 * Privacy provider tests.
 *
 * @package    quizaccess_privateexam
 * @copyright  2026 Arigun Jamsran
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(provider::class)]
final class provider_test extends \core_privacy\tests\provider_testcase {
    /** @var \stdClass the quiz */
    private $quiz;

    /** @var \stdClass[] two students, each with one logged attempt */
    private $users = [];

    protected function setUp(): void {
        global $DB;
        parent::setUp();
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $this->quiz = $this->getDataGenerator()->create_module('quiz', ['course' => $course->id]);
        foreach ([1, 2] as $i) {
            $user = $this->getDataGenerator()->create_and_enrol($course, 'student');
            $attemptid = $DB->insert_record('quiz_attempts', ['quiz' => $this->quiz->id, 'userid' => $user->id,
                'attempt' => 1, 'uniqueid' => $i, 'layout' => '', 'state' => 'inprogress']);
            $DB->insert_record(
                'quizaccess_privateexam_log',
                ['attemptid' => $attemptid, 'switches' => $i, 'awaysecs' => 10 * $i]
            );
            $this->users[] = $user;
        }
    }

    /**
     * Number of counter rows left for a user.
     *
     * @param \stdClass $user the user
     * @return int
     */
    private function count_logs(\stdClass $user): int {
        global $DB;
        return $DB->count_records_sql('SELECT COUNT(1) FROM {quizaccess_privateexam_log} l
            JOIN {quiz_attempts} qa ON qa.id = l.attemptid WHERE qa.userid = ?', [$user->id]);
    }

    public function test_export(): void {
        $data = provider::export_quizaccess_user_data(quiz_settings::create($this->quiz->id), $this->users[1]);
        $this->assertCount(1, $data->attempts);
        $this->assertEquals(2, $data->attempts[0]->timesleftwindow);
        $this->assertEquals(20, $data->attempts[0]->secondsaway);
    }

    public function test_delete_for_user(): void {
        provider::delete_quizaccess_data_for_user(quiz_settings::create($this->quiz->id), $this->users[0]);
        $this->assertSame(0, $this->count_logs($this->users[0]));
        $this->assertSame(1, $this->count_logs($this->users[1]));
    }

    public function test_delete_for_users(): void {
        $context = \context_module::instance($this->quiz->cmid);
        provider::delete_quizaccess_data_for_users(
            new approved_userlist($context, 'mod_quiz', [$this->users[1]->id])
        );
        $this->assertSame(1, $this->count_logs($this->users[0]));
        $this->assertSame(0, $this->count_logs($this->users[1]));
    }

    public function test_delete_for_all_users(): void {
        provider::delete_quizaccess_data_for_all_users_in_context(quiz_settings::create($this->quiz->id));
        $this->assertSame(0, $this->count_logs($this->users[0]) + $this->count_logs($this->users[1]));
    }
}
