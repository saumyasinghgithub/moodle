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

require_once('../../../config.php');
require_once($CFG->dirroot.'/mod/svasu/locallib.php');

$id = optional_param('id', '', PARAM_INT);  // Course Module ID, or
$a = optional_param('a', '', PARAM_INT);  // svasu ID.
$scoid = required_param('scoid', PARAM_INT);  // Sco ID.
$attempt = required_param('attempt', PARAM_INT);  // Attempt number.
$function  = required_param('function', PARAM_RAW);  // Function to call.
$request = optional_param('request', '', PARAM_RAW);  // Scorm ID.

if (!empty($id)) {
    $cm = get_coursemodule_from_id('svasu', $id, 0, false, MUST_EXIST);
    $course = $DB->get_record("course", array("id" => $cm->course), '*', MUST_EXIST);
    $svasu = $DB->get_record("svasu", array("id" => $cm->instance), '*', MUST_EXIST);
} else if (!empty($a)) {
    $svasu = $DB->get_record("svasu", array("id" => $a), '*', MUST_EXIST);
    $course = $DB->get_record("course", array("id" => $svasu->course), '*', MUST_EXIST);
    $cm = get_coursemodule_from_instance("svasu", $svasu->id, $course->id, false, MUST_EXIST);
} else {
    print_error('missingparameter');
}

$PAGE->set_url('/mod/svasu/datamodels/sequencinghandler.php',
    array('scoid' => $scoid, 'attempt' => $attempt, 'id' => $cm->id, 'function' => $function, 'request' => $request));

require_login($course, false, $cm);

if (!empty($scoid) && !empty($function)) {
    require_once($CFG->dirroot.'/mod/svasu/datamodels/scorm_13lib.php');

    if (has_capability('mod/svasu:savetrack', context_module::instance($cm->id))) {
        $result = null;
        switch ($function) {
            case 'svasu_seq_flow' :
                if ($request == 'forward' || $request == 'backward') {
                    $seq = svasu_seq_navigation ($scoid, $USER->id, $request.'_', $attempt);
                    $sco = svasu_get_sco($scoid);
                    $seq = svasu_seq_flow($sco, $request, $seq, true, $USER->id);
                    if (!empty($seq->nextactivity)) {
                        svasu_seq_end_attempt($sco, $USER->id, $seq);
                    }
                }
                echo json_encode($seq);
                break;
        }
    }
}
