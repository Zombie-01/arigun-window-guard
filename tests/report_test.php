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

namespace quizaccess_privateexam;

use quizaccess_privateexam\external\report;

/**
 * Tests for the leave/return counter and the attempt end time it produces.
 *
 * @package    quizaccess_privateexam
 * @copyright  2026 Arigun Jamsran
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(report::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\quizaccess_privateexam::class)]
final class report_test extends \advanced_testcase {
    /**
     * Create a quiz with the rule enabled and an in-progress attempt for a new student.
     *
     * @param int $maxswitches switch limit
     * @param int $maxaway seconds-away limit
     * @return array [quiz, attempt, user]
     */
    private function create_attempt(int $maxswitches, int $maxaway): array {
        global $DB;
        $course = $this->getDataGenerator()->create_course();
        $quiz = $this->getDataGenerator()->create_module('quiz', ['course' => $course->id]);
        $user = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $DB->insert_record(
            'quizaccess_privateexam',
            ['quizid' => $quiz->id, 'maxswitches' => $maxswitches, 'maxaway' => $maxaway]
        );
        $attempt = (object) ['quiz' => $quiz->id, 'userid' => $user->id, 'attempt' => 1, 'uniqueid' => $quiz->id,
            'layout' => '', 'state' => 'inprogress', 'timestart' => time(), 'timemodified' => time()];
        $attempt->id = $DB->insert_record('quiz_attempts', $attempt);
        return [$quiz, $attempt, $user];
    }

    public function test_switch_limit_ends_attempt(): void {
        global $CFG;
        require_once($CFG->dirroot . '/mod/quiz/accessrule/privateexam/rule.php');
        $this->resetAfterTest();
        [$quiz, $attempt, $user] = $this->create_attempt(2, 0);
        $this->setUser($user);
        $rule = new \quizaccess_privateexam(\mod_quiz\quiz_settings::create($quiz->id), time());

        report::execute($attempt->id, 'leave');
        $r = report::execute($attempt->id, 'return');
        $this->assertSame(1, $r['switches']);
        $this->assertFalse($r['exceeded']);
        $this->assertFalse($rule->end_time($attempt));

        report::execute($attempt->id, 'leave');
        report::execute($attempt->id, 'leave'); // Blur and hidden both fire: counted once.
        $r = report::execute($attempt->id, 'return');
        $this->assertSame(2, $r['switches']);
        $this->assertTrue($r['exceeded']);
        // The end time makes core quiz time the attempt out.
        $this->assertIsInt($rule->end_time($attempt));
    }

    public function test_time_away_is_measured_by_the_server(): void {
        global $DB;
        $this->resetAfterTest();
        [, $attempt, $user] = $this->create_attempt(0, 30);
        $this->setUser($user);

        report::execute($attempt->id, 'leave');
        $DB->set_field('quizaccess_privateexam_log', 'leftat', time() - 31, ['attemptid' => $attempt->id]);
        $r = report::execute($attempt->id, 'return');
        $this->assertGreaterThanOrEqual(31, $r['away']);
        $this->assertTrue($r['exceeded']);
    }

    public function test_cannot_report_for_another_users_attempt(): void {
        $this->resetAfterTest();
        [, $attempt] = $this->create_attempt(3, 0);
        $this->setUser($this->getDataGenerator()->create_user());

        $this->expectException(\dml_missing_record_exception::class);
        report::execute($attempt->id, 'leave');
    }
}
