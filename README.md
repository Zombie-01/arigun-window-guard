# Exam Window Guard

Exam Window Guard (`quizaccess_privateexam`) is a quiz access rule that counts how many times, and for how long,
a student leaves the quiz window during an attempt. The student is warned each time they come back, and the attempt
is submitted automatically once a limit set by the teacher is reached. Copy, cut, paste and the right-click menu are
disabled on the attempt pages.

## What it does

* Detects the student switching tab, minimising the browser or switching to another application (Alt+Tab).
* Measures time away with the **server clock**, so the browser cannot under-report it.
* Shows a warning on return: "You left the exam window 2/3 times, 15/30 seconds in total."
* When either limit is reached, sets the attempt's end time to that moment. Moodle then treats the attempt as timed
  out and applies the quiz's **When time expires** setting, exactly as it does for an ordinary time limit. Reloading
  the page does not help: the server refuses to continue the attempt.
* Moving between pages of the quiz is not counted as leaving.

## What it does not do

* It is not a lockdown browser. A student who disables JavaScript or uses a second device is not detected. For a
  full lockdown use Safe Exam Browser (supported by Moodle core); the two can be combined.
* Alt+Tab while the cursor is inside a rich-text editor (for example an essay answer) is not detected. Switching tab
  or minimising the window is.
* Teacher previews are not ended (Moodle does not enforce end times on previews).

## Requirements

Moodle 4.5 to 5.2.

## Installation

1. Copy this folder to `mod/quiz/accessrule/privateexam` (Moodle 5.1+: `public/mod/quiz/accessrule/privateexam`),
   or install the zip from *Site administration → Plugins → Install plugins*.
2. Visit *Site administration → Notifications* to complete the installation.

## Setting up a quiz

In the quiz settings, under **Extra restrictions on attempts**:

| Setting | Meaning |
|---|---|
| Monitor leaving the exam window | Turns the rule on for this quiz. |
| Max times leaving the window | The attempt ends when the student has left this many times. 0 = unlimited. |
| Max total seconds away | The attempt ends when the time away adds up to this. 0 = unlimited. |

Then, under **Timing → When time expires**, choose **Open attempts are submitted automatically**. With the other
options the attempt becomes overdue (grace period) or is abandoned instead.

## Privacy

For each attempt the plugin stores the number of times the student left, the total seconds away and when the limit
was reached. The data is exported and deleted together with the student's quiz attempts through Moodle's privacy
API.

## Support

Bugs and feature requests: the issue tracker of this repository. Help with setup: **mm6816557@gmail.com**, in
English or Mongolian.

**Тусламж хэрэгтэй бол:** суулгах, тохируулахад туслалцаа хэрэгтэй бол **mm6816557@gmail.com** хаягаар монгол
эсвэл англи хэлээр холбогдоорой.

Report security issues privately to the address above rather than in the public issue tracker.

## Licence

GNU GPL v3 or later. See [LICENSE](LICENSE).
