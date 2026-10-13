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
 * Detects leaving the quiz window, blocks copy/paste, ends the attempt at the limit.
 *
 * @module     quizaccess_privateexam/monitor
 * @copyright  2026 Arigun Jamsran
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Ajax from 'core/ajax';
import Notification from 'core/notification';
import {getStrings} from 'core/str';
import {markFormSubmitted} from 'core_form/changechecker';

/**
 * Do what the quiz timer does when time runs out: submit with timeup=1, the server then ends the attempt.
 */
const endAttempt = () => {
    const timeup = document.querySelector('input[name=timeup]');
    if (!timeup) {
        window.location.reload();
        return;
    }
    timeup.value = 1;
    const finish = timeup.form.querySelector('input[name=finishattempt]');
    if (finish) {
        finish.value = 0;
    }
    markFormSubmitted(timeup.form);
    timeup.form.submit();
};

/**
 * Show the student how close they are to the limit.
 *
 * @param {Object} r the web service result
 */
const warn = (r) => {
    getStrings([
        {key: 'warningtitle', component: 'quizaccess_privateexam'},
        {key: 'warning', component: 'quizaccess_privateexam', param: {
            switches: r.switches, maxswitches: r.maxswitches || '∞',
            away: r.away, maxaway: r.maxaway || '∞',
        }},
    ]).then(([title, message]) => Notification.alert(title, message)).catch(Notification.exception);
};

/**
 * Start monitoring.
 *
 * @param {Number} attemptid the quiz attempt id
 */
export const init = (attemptid) => {
    // Start as away: the first return closes a leave left open by a closed tab and checks the limit.
    let away = true;
    let unloading = false;

    const send = (event) => Ajax.call([{
        methodname: 'quizaccess_privateexam_report',
        args: {attemptid, event},
    }])[0];

    const leave = () => {
        if (away || unloading) {
            return;
        }
        away = true;
        send('leave').catch(Notification.exception);
    };

    const back = () => {
        if (!away) {
            return;
        }
        away = false;
        send('return').then((r) => {
            if (r.exceeded) {
                endAttempt();
            } else if (r.switches) {
                warn(r);
            }
            return r;
        }).catch(Notification.exception);
    };

    // Moving between quiz pages also hides the document; don't count that.
    window.addEventListener('beforeunload', () => {
        unloading = true;
        setTimeout(() => {
            unloading = false; // Navigation was cancelled.
        }, 2000);
    });
    document.addEventListener('visibilitychange', () => (document.hidden ? leave() : back()));
    // Alt+Tab to another app only fires blur. Clicking into an editor iframe also blurs the window, so skip that.
    // Known gap: Alt+Tab while typing inside an editor iframe is not seen.
    window.addEventListener('blur', () => setTimeout(() => {
        if (!document.activeElement || document.activeElement.tagName !== 'IFRAME') {
            leave();
        }
    }, 0));
    window.addEventListener('focus', back);

    ['copy', 'cut', 'paste', 'contextmenu'].forEach((type) => document.addEventListener(type, (e) => e.preventDefault()));

    back();
};
