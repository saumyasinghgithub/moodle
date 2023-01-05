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

if (!defined('MOODLE_INTERNAL')) {
    die('Direct access to this script is forbidden.');    // It must be included from a Moodle page.
}

require_once("$CFG->libdir/formslib.php");
class mod_svasu_report_settings extends moodleform {

    public function definition() {
        global $COURSE;
        $mform    =& $this->_form;
        // -------------------------------------------------------------------------------
        $mform->addElement('header', 'preferencespage', get_string('preferencespage', 'svasu'));

        $options = array();
        if ($COURSE->id != SITEID) {
            $options[SVASU_REPORT_ATTEMPTS_ALL_STUDENTS] = get_string('optallstudents', 'svasu');
            $options[SVASU_REPORT_ATTEMPTS_STUDENTS_WITH] = get_string('optattemptsonly', 'svasu');
            $options[SVASU_REPORT_ATTEMPTS_STUDENTS_WITH_NO] = get_string('optnoattemptsonly', 'svasu');
        }
        $mform->addElement('select', 'attemptsmode', get_string('show', 'svasu'), $options);

        // -------------------------------------------------------------------------------
        $mform->addElement('header', 'preferencesuser', get_string('preferencesuser', 'svasu'));

        $mform->addElement('text', 'pagesize', get_string('pagesize', 'svasu'));
        $mform->setType('pagesize', PARAM_INT);

        $mform->addElement('selectyesno', 'detailedrep', get_string('details', 'svasu'));

        $this->add_action_buttons(false, get_string('savepreferences'));
    }
}
