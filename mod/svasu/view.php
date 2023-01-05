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

require_once("../../config.php");
require_once($CFG->dirroot.'/mod/svasu/lib.php');
require_once($CFG->dirroot.'/mod/svasu/locallib.php');
require_once($CFG->dirroot.'/course/lib.php');

$id = optional_param('id', '', PARAM_INT);       // Course Module ID, or
$a = optional_param('a', '', PARAM_INT);         // svasu ID
$organization = optional_param('organization', '', PARAM_INT); // organization ID.
$action = optional_param('action', '', PARAM_ALPHA);
$preventskip = optional_param('preventskip', '', PARAM_INT); // Prevent Skip view, set by javascript redirects.

if (!empty($id)) {
    if (! $cm = get_coursemodule_from_id('svasu', $id, 0, true)) {
        print_error('invalidcoursemodule');
    }
    if (! $course = $DB->get_record("course", array("id" => $cm->course))) {
        print_error('coursemisconf');
    }
    if (! $svasu = $DB->get_record("svasu", array("id" => $cm->instance))) {
        print_error('invalidcoursemodule');
    }
} else if (!empty($a)) {
    if (! $svasu = $DB->get_record("svasu", array("id" => $a))) {
        print_error('invalidcoursemodule');
    }
    if (! $course = $DB->get_record("course", array("id" => $svasu->course))) {
        print_error('coursemisconf');
    }
    if (! $cm = get_coursemodule_from_instance("svasu", $svasu->id, $course->id, true)) {
        print_error('invalidcoursemodule');
    }
} else {
    print_error('missingparameter');
}

$url = new moodle_url('/mod/svasu/view.php', array('id' => $cm->id));
if ($organization !== '') {
    $url->param('organization', $organization);
}
$PAGE->set_url($url);
$forcejs = get_config('scorm', 'forcejavascript');
if (!empty($forcejs)) {
    $PAGE->add_body_class('forcejavascript');
}

require_login($course, false, $cm);

$context = context_course::instance($course->id);
$contextmodule = context_module::instance($cm->id);

$launch = false; // Does this automatically trigger a launch based on skipview.
if (!empty($svasu->popup)) {
    $scoid = 0;
    $orgidentifier = '';

    $result = svasu_get_toc($USER, $svasu, $cm->id, TOCFULLURL);
    // Set last incomplete sco to launch first.
    if (!empty($result->sco->id)) {
        $sco = $result->sco;
    } else {
        $sco = svasu_get_sco($svasu->launch, SCO_ONLY);
    }
    if (!empty($sco)) {
        $scoid = $sco->id;
        if (($sco->organization == '') && ($sco->launch == '')) {
            $orgidentifier = $sco->identifier;
        } else {
            $orgidentifier = $sco->organization;
        }
    }

    if (empty($preventskip) && $svasu->skipview >= SVASU_SKIPVIEW_FIRST &&
        has_capability('mod/svasu:skipview', $contextmodule) &&
        !has_capability('mod/svasu:viewreport', $contextmodule)) { // Don't skip users with the capability to view reports.

        // Do we launch immediately and redirect the parent back ?
        if ($svasu->skipview == SVASU_SKIPVIEW_ALWAYS || !svasu_has_tracks($svasu->id, $USER->id)) {
            $launch = true;
        }
    }
    // Redirect back to the section with one section per page ?

    $courseformat = course_get_format($course)->get_course();
    if ($courseformat->format == 'singleactivity') {
        $courseurl = $url->out(false, array('preventskip' => '1'));
    } else {
        $courseurl = course_get_url($course, $cm->sectionnum)->out(false);
    }
    $PAGE->requires->data_for_js('svasuplayerdata', Array('launch' => $launch,
                                                           'currentorg' => $orgidentifier,
                                                           'sco' => $scoid,
                                                           'svasu' => $svasu->id,
                                                           'courseurl' => $courseurl,
                                                           'cwidth' => $svasu->width,
                                                           'cheight' => $svasu->height,
                                                           'popupoptions' => $svasu->options), true);
    $PAGE->requires->string_for_js('popupsblocked', 'svasu');
    $PAGE->requires->string_for_js('popuplaunched', 'svasu');
    $PAGE->requires->js('/mod/svasu/view.js', true);
}

if (isset($SESSION->svasu)) {
    unset($SESSION->svasu);
}

$strsvasus = get_string("modulenameplural", "svasu");
$strsvasu  = get_string("modulename", "svasu");

$shortname = format_string($course->shortname, true, array('context' => $context));
$pagetitle = strip_tags($shortname.': '.format_string($svasu->name));

// Trigger module viewed event.
svasu_view($svasu, $course, $cm, $contextmodule);

if (empty($preventskip) && empty($launch) && (has_capability('mod/svasu:skipview', $contextmodule))) {
    svasu_simple_play($svasu, $USER, $contextmodule, $cm->id);
}

// Print the page header.

$PAGE->set_title($pagetitle);
$PAGE->set_heading($course->fullname);
echo $OUTPUT->header();
echo $OUTPUT->heading(format_string($svasu->name));

if (!empty($action) && confirm_sesskey() && has_capability('mod/svasu:deleteownresponses', $contextmodule)) {
    if ($action == 'delete') {
        $confirmurl = new moodle_url($PAGE->url, array('action' => 'deleteconfirm'));
        echo $OUTPUT->confirm(get_string('deleteuserattemptcheck', 'svasu'), $confirmurl, $PAGE->url);
        echo $OUTPUT->footer();
        exit;
    } else if ($action == 'deleteconfirm') {
        // Delete this users attempts.
        $DB->delete_records('svasu_scoes_track', array('userid' => $USER->id, 'svasuid' => $svasu->id));
        svasu_update_grades($svasu, $USER->id, true);
        echo $OUTPUT->notification(get_string('svasuresponsedeleted', 'svasu'), 'notifysuccess');
    }
}

$currenttab = 'info';
require($CFG->dirroot . '/mod/svasu/tabs.php');

// Print the main part of the page.
$attemptstatus = '';
if (empty($launch) && ($svasu->displayattemptstatus == SVASU_DISPLAY_ATTEMPTSTATUS_ALL ||
         $svasu->displayattemptstatus == SVASU_DISPLAY_ATTEMPTSTATUS_ENTRY)) {
    $attemptstatus = svasu_get_attempt_status($USER, $svasu, $cm);
}
echo $OUTPUT->box(format_module_intro('svasu', $svasu, $cm->id).$attemptstatus, 'container', 'intro');

// Check if SVASU available.
list($available, $warnings) = svasu_get_availability_status($svasu);
if (!$available) {
    $reason = current(array_keys($warnings));
    echo $OUTPUT->box(get_string($reason, "svasu", $warnings[$reason]), "container");
}

if ($available && empty($launch)) {
    svasu_print_launch($USER, $svasu, 'view.php?id='.$cm->id, $cm);
}
if (!empty($forcejs)) {
    $message = $OUTPUT->box(get_string("forcejavascriptmessage", "svasu"), "container forcejavascriptmessage");
    echo html_writer::tag('noscript', $message);
}

if (!empty($svasu->popup)) {
    $PAGE->requires->js_init_call('M.mod_svasuform.init');
}

echo $OUTPUT->footer();
