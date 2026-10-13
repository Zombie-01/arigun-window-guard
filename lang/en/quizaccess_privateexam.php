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

/**
 * English strings for Exam Window Guard.
 *
 * @package    quizaccess_privateexam
 * @copyright  2026 Arigun Jamsran
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['description'] = 'Leaving this exam window is monitored and copy/paste is disabled. The attempt ends after leaving {$a->maxswitches} times or {$a->maxaway} seconds away in total.';
$string['enabled'] = 'Monitor leaving the exam window';
$string['enabled_help'] = 'Counts how many times and how long the student leaves the quiz tab or window, disables copy, paste and right click, and ends the attempt when a limit is reached. Set "When time expires" to "Open attempts are submitted automatically".';
$string['maxaway'] = 'Max total seconds away (0 = unlimited)';
$string['maxswitches'] = 'Max times leaving the window (0 = unlimited)';
$string['pluginname'] = 'Exam window guard';
$string['privacy:metadata:log'] = 'How often and how long the student left the quiz window during an attempt.';
$string['privacy:metadata:log:attemptid'] = 'The quiz attempt.';
$string['privacy:metadata:log:awaysecs'] = 'Total seconds spent outside the quiz window.';
$string['privacy:metadata:log:leftat'] = 'When the student last left the window, if they have not returned yet.';
$string['privacy:metadata:log:switches'] = 'Number of times the student left the quiz window.';
$string['privacy:metadata:log:timeexceeded'] = 'When the limit was reached and the attempt was ended.';
$string['warning'] = 'You left the exam window {$a->switches}/{$a->maxswitches} times, {$a->away}/{$a->maxaway} seconds in total. At the limit your attempt will be submitted automatically.';
$string['warningtitle'] = 'Warning';
