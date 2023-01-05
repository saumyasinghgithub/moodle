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
require_once($CFG->dirroot.'/mod/svasu/locallib.php');

$id = required_param('id', PARAM_INT);   // Course id.

$PAGE->set_url('/mod/svasu/index.php', array('id' => $id));

if (!empty($id)) {
    if (!$course = $DB->get_record('course', array('id' => $id))) {
        print_error('invalidcourseid');
    }
} else {
    print_error('missingparameter');
}

require_course_login($course);
$PAGE->set_pagelayout('incourse');

// Trigger instances list viewed event.
$event = \mod_svasu\event\course_module_instance_list_viewed::create(array('context' => context_course::instance($course->id)));
$event->add_record_snapshot('course', $course);
$event->trigger();

$strsvasu = get_string("modulename", "svasu");
$strsvasus = get_string("modulenameplural", "svasu");
$strname = get_string("name");
$strsummary = get_string("summary");
$strreport = get_string("report", 'svasu');
$strlastmodified = get_string("lastmodified");

$PAGE->set_title($strsvasus);
$PAGE->set_heading($course->fullname);
$PAGE->navbar->add($strsvasus);
echo $OUTPUT->header();
echo $OUTPUT->heading($strsvasus);

$usesections = course_format_uses_sections($course->format);

if ($usesections) {
    $sortorder = "cw.section ASC";
} else {
    $sortorder = "m.timemodified DESC";
}

if (! $svasus = get_all_instances_in_course("svasu", $course)) {
    notice(get_string('thereareno', 'moodle', $strsvasus), "../../course/view.php?id=$course->id");
    exit;
}

$table = new html_table();

if ($usesections) {
    $strsectionname = get_string('sectionname', 'format_'.$course->format);
    $table->head  = array ($strsectionname, $strname, $strsummary, $strreport);
    $table->align = array ("center", "left", "left", "left");
} else {
    $table->head  = array ($strlastmodified, $strname, $strsummary, $strreport);
    $table->align = array ("left", "left", "left", "left");
}

foreach ($svasus as $svasu) {
    $context = context_module::instance($svasu->coursemodule);
    $tt = "";
    if ($usesections) {
        if ($svasu->section) {
            $tt = get_section_name($course, $svasu->section);
        }
    } else {
        $tt = userdate($svasu->timemodified);
    }
    $report = '&nbsp;';
    $reportshow = '&nbsp;';
    if (has_capability('mod/svasu:viewreport', $context)) {
        $trackedusers = svasu_get_count_users($svasu->id, $svasu->groupingid);
        if ($trackedusers > 0) {
            $reportshow = html_writer::link('report.php?id='.$svasu->coursemodule,
                                                get_string('viewallreports', 'svasu', $trackedusers));
        } else {
            $reportshow = get_string('noreports', 'svasu');
        }
    } else if (has_capability('mod/svasu:viewscores', $context)) {
        require_once('locallib.php');
        $report = svasu_grade_user($svasu, $USER->id);
        $reportshow = get_string('score', 'svasu').": ".$report;
    }
    $options = (object)array('noclean' => true);
    if (!$svasu->visible) {
        // Show dimmed if the mod is hidden.
        $table->data[] = array ($tt, html_writer::link('view.php?id='.$svasu->coursemodule,
                                                        format_string($svasu->name),
                                                        array('class' => 'dimmed')),
                                format_module_intro('svasu', $svasu, $svasu->coursemodule), $reportshow);
    } else {
        // Show normal if the mod is visible.
        $table->data[] = array ($tt, html_writer::link('view.php?id='.$svasu->coursemodule, format_string($svasu->name)),
                                format_module_intro('svasu', $svasu, $svasu->coursemodule), $reportshow);
    }
}

echo html_writer::empty_tag('br');

echo html_writer::table($table);

echo $OUTPUT->footer();