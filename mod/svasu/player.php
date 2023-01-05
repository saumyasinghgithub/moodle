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

// This page prints a particular instance of aicc/svasu package.

require_once('../../config.php');
require_once($CFG->dirroot.'/mod/svasu/locallib.php');
require_once($CFG->libdir . '/completionlib.php');

$id = optional_param('cm', '', PARAM_INT);                          // Course Module ID, or
$a = optional_param('a', '', PARAM_INT);                            // svasu ID
$scoid = required_param('scoid', PARAM_INT);                        // sco ID
$mode = optional_param('mode', 'normal', PARAM_ALPHA);              // navigation mode
$currentorg = optional_param('currentorg', '', PARAM_RAW);          // selected organization
$newattempt = optional_param('newattempt', 'off', PARAM_ALPHA);     // the user request to start a new attempt.
$displaymode = optional_param('display', '', PARAM_ALPHA);

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

// PARAM_RAW is used for $currentorg, validate it against records stored in the table.
if (!empty($currentorg)) {
    if (!$DB->record_exists('svasu_scoes', array('svasu' => $svasu->id, 'identifier' => $currentorg))) {
        $currentorg = '';
    }
}

// If new attempt is being triggered set normal mode and increment attempt number.
$attempt = svasu_get_last_attempt($svasu->id, $USER->id);

// Check mode is correct and set/validate mode/attempt/newattempt (uses pass by reference).
svasu_check_mode($svasu, $newattempt, $attempt, $USER->id, $mode);

if (!empty($scoid)) {
    $scoid = svasu_check_launchable_sco($svasu, $scoid);
}

$url = new moodle_url('/mod/svasu/player.php', array('scoid' => $scoid, 'cm' => $cm->id));
if ($mode !== 'normal') {
    $url->param('mode', $mode);
}
if ($currentorg !== '') {
    $url->param('currentorg', $currentorg);
}
if ($newattempt !== 'off') {
    $url->param('newattempt', $newattempt);
}
if ($displaymode !== '') {
    $url->param('display', $displaymode);
}
$PAGE->set_url($url);
$forcejs = get_config('scorm', 'forcejavascript');
if (!empty($forcejs)) {
    $PAGE->add_body_class('forcejavascript');
}
$collapsetocwinsize = get_config('scorm', 'collapsetocwinsize');
if (empty($collapsetocwinsize)) {
    // Set as default window size to collapse TOC.
    $collapsetocwinsize = 767;
} else {
    $collapsetocwinsize = intval($collapsetocwinsize);
}

require_login($course, false, $cm);

$strsvasus = get_string('modulenameplural', 'svasu');
$strsvasu  = get_string('modulename', 'svasu');
$strpopup = get_string('popup', 'svasu');
$strexit = get_string('exitactivity', 'svasu');

$coursecontext = context_course::instance($course->id);

if ($displaymode == 'popup') {
    $PAGE->set_pagelayout('embedded');
} else {
    $shortname = format_string($course->shortname, true, array('context' => $coursecontext));
    $pagetitle = strip_tags("$shortname: ".format_string($svasu->name));
    $PAGE->set_title($pagetitle);
    $PAGE->set_heading($course->fullname);
}
if (!$cm->visible and !has_capability('moodle/course:viewhiddenactivities', context_module::instance($cm->id))) {
    echo $OUTPUT->header();
    notice(get_string("activityiscurrentlyhidden"));
    echo $OUTPUT->footer();
    die;
}

// Check if SVASU available.
list($available, $warnings) = svasu_get_availability_status($svasu);
if (!$available) {
    $reason = current(array_keys($warnings));
    echo $OUTPUT->header();
    echo $OUTPUT->box(get_string($reason, "svasu", $warnings[$reason]), "generalbox boxaligncenter");
    echo $OUTPUT->footer();
    die;
}

// TOC processing
$svasu->version = strtolower(clean_param($svasu->version, PARAM_SAFEDIR));   // Just to be safe.
if (!file_exists($CFG->dirroot.'/mod/svasu/datamodels/'.$svasu->version.'lib.php')) {
    $svasu->version = 'scorm_12';
}
require_once($CFG->dirroot.'/mod/svasu/datamodels/'.$svasu->version.'lib.php');

$result = svasu_get_toc($USER, $svasu, $cm->id, TOCJSLINK, $currentorg, $scoid, $mode, $attempt, true, true);
$sco = $result->sco;
if ($svasu->lastattemptlock == 1 && $result->attemptleft == 0) {
    echo $OUTPUT->header();
    echo $OUTPUT->notification(get_string('exceededmaxattempts', 'svasu'));
    echo $OUTPUT->footer();
    exit;
}

$scoidstr = '&amp;scoid='.$sco->id;
$modestr = '&amp;mode='.$mode;

$SESSION->svasu = new stdClass();
$SESSION->svasu->scoid = $sco->id;
$SESSION->svasu->svasustatus = 'Not Initialized';
$SESSION->svasu->svasumode = $mode;
$SESSION->svasu->attempt = $attempt;

// Mark module viewed.
$completion = new completion_info($course);
$completion->set_module_viewed($cm);

// Print the page header.
if (empty($svasu->popup) || $displaymode == 'popup') {
    if ($course->format == 'singleactivity' && $svasu->skipview == SVASU_SKIPVIEW_ALWAYS
        && !has_capability('mod/svasu:viewreport', context_module::instance($cm->id))) {
        // Redirect students back to site home to avoid redirect loop.
        $exiturl = $CFG->wwwroot;
    } else {
        // Redirect back to the correct section if one section per page is being used.
        $exiturl = course_get_url($course, $cm->sectionnum);
    }

    $exitlink = html_writer::link($exiturl, $strexit, array('title' => $strexit, 'class' => 'btn btn-secondary'));
    $PAGE->set_button($exitlink);
}

$PAGE->requires->data_for_js('svasuplayerdata', Array('launch' => false,
                                                       'currentorg' => '',
                                                       'sco' => 0,
                                                       'svasu' => 0,
                                                       'courseid' => $svasu->course,
                                                       'cwidth' => $svasu->width,
                                                       'cheight' => $svasu->height,
                                                       'popupoptions' => $svasu->options), true);
$PAGE->requires->js('/mod/svasu/request.js', true);
$PAGE->requires->js('/lib/cookies.js', true);

if (file_exists($CFG->dirroot.'/mod/svasu/datamodels/'.$svasu->version.'.js')) {
    $PAGE->requires->js('/mod/svasu/datamodels/'.$svasu->version.'.js', true);
} else {
    $PAGE->requires->js('/mod/svasu/datamodels/scorm_12.js', true);
}

echo $OUTPUT->header();
if (!empty($svasu->displayactivityname)) {
    echo $OUTPUT->heading(format_string($svasu->name));
}

$PAGE->requires->string_for_js('navigation', 'svasu');
$PAGE->requires->string_for_js('toc', 'svasu');
$PAGE->requires->string_for_js('hide', 'moodle');
$PAGE->requires->string_for_js('show', 'moodle');
$PAGE->requires->string_for_js('popupsblocked', 'svasu');

$name = false;

echo html_writer::start_div('', array('id' => 'svasupage'));
echo html_writer::start_div('', array('id' => 'tocbox'));
echo html_writer::div(html_writer::tag('script', '', array('id' => 'external-svasuapi', 'type' => 'text/JavaScript')), '',
                        array('id' => 'svasuapi-parent'));

if ($svasu->hidetoc == SVASU_TOC_POPUP or $mode == 'browse' or $mode == 'review') {
    echo html_writer::start_div('', array('id' => 'svasutop'));
    echo $mode == 'browse' ? html_writer::div(get_string('browsemode', 'svasu'), 'svasu-left', array('id' => 'svasumode')) : '';
    echo $mode == 'review' ? html_writer::div(get_string('reviewmode', 'svasu'), 'svasu-left', array('id' => 'svasumode')) : '';
    if ($svasu->hidetoc == SVASU_TOC_POPUP) {
        echo html_writer::div($result->tocmenu, 'svasu-right', array('id' => 'svasunav'));
    }
    echo html_writer::end_div();
}

echo html_writer::start_div('', array('id' => 'toctree'));

if (empty($svasu->popup) || $displaymode == 'popup') {
    echo $result->toc;
} else {
    // Added incase javascript popups are blocked we don't provide a direct link
    // to the pop-up as JS communication can fail - the user must disable their pop-up blocker.
    $linkcourse = html_writer::link($CFG->wwwroot.'/course/view.php?id='.
                    $svasu->course, get_string('finishsvasulinkname', 'svasu'));
    echo $OUTPUT->box(get_string('finishsvasu', 'svasu', $linkcourse), 'generalbox', 'altfinishlink');
}
echo html_writer::end_div(); // Toc tree ends.
echo html_writer::end_div(); // Toc box ends.
echo html_writer::tag('noscript', html_writer::div(get_string('noscriptnosvasu', 'svasu'), '', array('id' => 'noscript')));

if ($result->prerequisites) {
    if ($svasu->popup != 0 && $displaymode !== 'popup') {
        // Clean the name for the window as IE is fussy.
        $name = preg_replace("/[^A-Za-z0-9]/", "", $svasu->name);
        if (!$name) {
            $name = 'DefaultPlayerWindow';
        }
        $name = 'svasu_'.$name;
        echo html_writer::script('', $CFG->wwwroot.'/mod/svasu/player.js');
        $url = new moodle_url($PAGE->url, array('scoid' => $sco->id, 'display' => 'popup', 'mode' => $mode));
        echo html_writer::script(
            js_writer::function_call('svasu_openpopup', Array($url->out(false),
                                                       $name, $svasu->options,
                                                       $svasu->width, $svasu->height)));
        echo html_writer::tag('noscript', html_writer::tag('iframe', '', array('id' => 'main',
                                'class' => 'scoframe', 'name' => 'main', 'src' => 'loadSCO.php?id='.$cm->id.$scoidstr.$modestr)));
    }
} else {
    echo $OUTPUT->box(get_string('noprerequisites', 'svasu'));
}
echo html_writer::end_div(); // Scorm page ends.

$scoes = svasu_get_toc_object($USER, $svasu, $currentorg, $sco->id, $mode, $attempt);
$adlnav = svasu_get_adlnav_json($scoes['scoes']);

if (empty($svasu->popup) || $displaymode == 'popup') {
    if (!isset($result->toctitle)) {
        $result->toctitle = get_string('toc', 'svasu');
    }
    $jsmodule = array(
        'name' => 'mod_svasu',
        'fullpath' => '/mod/svasu/module.js',
        'requires' => array('json'),
    );
    $svasu->nav = intval($svasu->nav);
    $PAGE->requires->js_init_call('M.mod_svasu.init', array($svasu->nav, $svasu->navpositionleft, $svasu->navpositiontop,
                            $svasu->hidetoc, $collapsetocwinsize, $result->toctitle, $name, $sco->id, $adlnav), false, $jsmodule);
}
if (!empty($forcejs)) {
    $message = $OUTPUT->box(get_string("forcejavascriptmessage", "svasu"), "generalbox boxaligncenter forcejavascriptmessage");
    echo html_writer::tag('noscript', $message);
}

if (file_exists($CFG->dirroot.'/mod/svasu/datamodels/'.$svasu->version.'.php')) {
    include_once($CFG->dirroot.'/mod/svasu/datamodels/'.$svasu->version.'.php');
} else {
    include_once($CFG->dirroot.'/mod/svasu/datamodels/scorm_12.php');
}

// Add the keepalive system to keep checking for a connection.
\core\session\manager::keepalive('networkdropped', 'mod_svasu', 30, 10);

echo $OUTPUT->footer();

// Set the start time of this SCO.
svasu_insert_track($USER->id, $svasu->id, $scoid, $attempt, 'x.start.time', time());
