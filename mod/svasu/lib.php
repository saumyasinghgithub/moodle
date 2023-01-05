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

/**
 * @package   mod_svasu
 * @copyright 1999 onwards Martin Dougiamas  {@link http://moodle.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/** SVASU_TYPE_LOCAL = local */
define('SVASU_TYPE_LOCAL', 'local');
/** SVASU_TYPE_LOCALSYNC = localsync */
define('SVASU_TYPE_LOCALSYNC', 'localsync');
/** SVASU_TYPE_EXTERNAL = external */
define('SVASU_TYPE_EXTERNAL', 'external');
/** SVASU_TYPE_AICCURL = external AICC url */
define('SVASU_TYPE_AICCURL', 'aiccurl');

define('SVASU_TOC_SIDE', 0);
define('SVASU_TOC_HIDDEN', 1);
define('SVASU_TOC_POPUP', 2);
define('SVASU_TOC_DISABLED', 3);

// Used to show/hide navigation buttons and set their position.
define('SVASU_NAV_DISABLED', 0);
define('SVASU_NAV_UNDER_CONTENT', 1);
define('SVASU_NAV_FLOATING', 2);

// Used to check what SVASU version is being used.
define('SVASU_12', 1);
define('SVASU_13', 2);
define('SVASU_AICC', 3);

// List of possible attemptstatusdisplay options.
define('SVASU_DISPLAY_ATTEMPTSTATUS_NO', 0);
define('SVASU_DISPLAY_ATTEMPTSTATUS_ALL', 1);
define('SVASU_DISPLAY_ATTEMPTSTATUS_MY', 2);
define('SVASU_DISPLAY_ATTEMPTSTATUS_ENTRY', 3);

define('SVASU_EVENT_TYPE_OPEN', 'open');
define('SVASU_EVENT_TYPE_CLOSE', 'close');

/**
 * Return an array of status options
 *
 * Optionally with translated strings
 *
 * @param   bool    $with_strings   (optional)
 * @return  array
 */
function svasu_status_options($withstrings = false) {
    // Id's are important as they are bits.
    $options = array(
        2 => 'passed',
        4 => 'completed'
    );

    if ($withstrings) {
        foreach ($options as $key => $value) {
            $options[$key] = get_string('completionstatus_'.$value, 'svasu');
        }
    }

    return $options;
}


/**
 * Given an object containing all the necessary data,
 * (defined by the form in mod_form.php) this function
 * will create a new instance and return the id number
 * of the new instance.
 *
 * @global stdClass
 * @global object
 * @uses CONTEXT_MODULE
 * @uses SVASU_TYPE_LOCAL
 * @uses SVASU_TYPE_LOCALSYNC
 * @uses SVASU_TYPE_EXTERNAL
 * @param object $svasu Form data
 * @param object $mform
 * @return int new instance id
 */
function svasu_add_instance($svasu, $mform=null) {
    global $CFG, $DB;

    require_once($CFG->dirroot.'/mod/svasu/locallib.php');

    if (empty($svasu->timeopen)) {
        $svasu->timeopen = 0;
    }
    if (empty($svasu->timeclose)) {
        $svasu->timeclose = 0;
    }
    if (empty($svasu->completionstatusallscos)) {
        $svasu->completionstatusallscos = 0;
    }
    $cmid       = $svasu->coursemodule;
    $cmidnumber = $svasu->cmidnumber;
    $courseid   = $svasu->course;

    $context = context_module::instance($cmid);

    $svasu = svasu_option2text($svasu);
    $svasu->width  = (int)str_replace('%', '', $svasu->width);
    $svasu->height = (int)str_replace('%', '', $svasu->height);

    if (!isset($svasu->whatgrade)) {
        $svasu->whatgrade = 0;
    }

    $id = $DB->insert_record('svasu', $svasu);

    // Update course module record - from now on this instance properly exists and all function may be used.
    $DB->set_field('course_modules', 'instance', $id, array('id' => $cmid));

    // Reload svasu instance.
    $record = $DB->get_record('svasu', array('id' => $id));

    // Store the package and verify.
    if ($record->svasutype === SVASU_TYPE_LOCAL) {
        if (!empty($svasu->packagefile)) {
            $fs = get_file_storage();
            $fs->delete_area_files($context->id, 'mod_svasu', 'package');
            file_save_draft_area_files($svasu->packagefile, $context->id, 'mod_svasu', 'package',
                0, array('subdirs' => 0, 'maxfiles' => 1));
            // Get filename of zip that was uploaded.
            $files = $fs->get_area_files($context->id, 'mod_svasu', 'package', 0, '', false);
            $file = reset($files);
            $filename = $file->get_filename();
            if ($filename !== false) {
                $record->reference = $filename;
            }
        }

    } else if ($record->svasutype === SVASU_TYPE_LOCALSYNC) {
        $record->reference = $svasu->packageurl;
    } else if ($record->svasutype === SVASU_TYPE_EXTERNAL) {
        $record->reference = $svasu->packageurl;
    } else if ($record->svasutype === SVASU_TYPE_AICCURL) {
        $record->reference = $svasu->packageurl;
        $record->hidetoc = SVASU_TOC_DISABLED; // TOC is useless for direct AICCURL so disable it.
    } else {
        return false;
    }

    // Save reference.
    $DB->update_record('svasu', $record);

    // Extra fields required in grade related functions.
    $record->course     = $courseid;
    $record->cmidnumber = $cmidnumber;
    $record->cmid       = $cmid;

    svasu_parse($record, true);

    svasu_grade_item_update($record);
    svasu_update_calendar($record, $cmid);
    if (!empty($svasu->completionexpected)) {
        \core_completion\api::update_completion_date_event($cmid, 'svasu', $record, $svasu->completionexpected);
    }

    return $record->id;
}

/**
 * Given an object containing all the necessary data,
 * (defined by the form in mod_form.php) this function
 * will update an existing instance with new data.
 *
 * @global stdClass
 * @global object
 * @uses CONTEXT_MODULE
 * @uses SVASU_TYPE_LOCAL
 * @uses SVASU_TYPE_LOCALSYNC
 * @uses SVASU_TYPE_EXTERNAL
 * @param object $svasu Form data
 * @param object $mform
 * @return bool
 */
function svasu_update_instance($svasu, $mform=null) {
    global $CFG, $DB;

    require_once($CFG->dirroot.'/mod/svasu/locallib.php');

    if (empty($svasu->timeopen)) {
        $svasu->timeopen = 0;
    }
    if (empty($svasu->timeclose)) {
        $svasu->timeclose = 0;
    }
    if (empty($svasu->completionstatusallscos)) {
        $svasu->completionstatusallscos = 0;
    }

    $cmid       = $svasu->coursemodule;
    $cmidnumber = $svasu->cmidnumber;
    $courseid   = $svasu->course;

    $svasu->id = $svasu->instance;

    $context = context_module::instance($cmid);

    if ($svasu->svasutype === SVASU_TYPE_LOCAL) {
        if (!empty($svasu->packagefile)) {
            $fs = get_file_storage();
            $fs->delete_area_files($context->id, 'mod_svasu', 'package');
            file_save_draft_area_files($svasu->packagefile, $context->id, 'mod_svasu', 'package',
                0, array('subdirs' => 0, 'maxfiles' => 1));
            // Get filename of zip that was uploaded.
            $files = $fs->get_area_files($context->id, 'mod_svasu', 'package', 0, '', false);
            $file = reset($files);
            $filename = $file->get_filename();
            if ($filename !== false) {
                $svasu->reference = $filename;
            }
        }

    } else if ($svasu->svasutype === SVASU_TYPE_LOCALSYNC) {
        $svasu->reference = $svasu->packageurl;
    } else if ($svasu->svasutype === SVASU_TYPE_EXTERNAL) {
        $svasu->reference = $svasu->packageurl;
    } else if ($svasu->svasutype === SVASU_TYPE_AICCURL) {
        $svasu->reference = $svasu->packageurl;
        $svasu->hidetoc = SVASU_TOC_DISABLED; // TOC is useless for direct AICCURL so disable it.
    } else {
        return false;
    }

    $svasu = svasu_option2text($svasu);
    $svasu->width        = (int)str_replace('%', '', $svasu->width);
    $svasu->height       = (int)str_replace('%', '', $svasu->height);
    $svasu->timemodified = time();

    if (!isset($svasu->whatgrade)) {
        $svasu->whatgrade = 0;
    }

    $DB->update_record('svasu', $svasu);
    // We need to find this out before we blow away the form data.
    $completionexpected = (!empty($svasu->completionexpected)) ? $svasu->completionexpected : null;

    $svasu = $DB->get_record('svasu', array('id' => $svasu->id));

    // Extra fields required in grade related functions.
    $svasu->course   = $courseid;
    $svasu->idnumber = $cmidnumber;
    $svasu->cmid     = $cmid;

    svasu_parse($svasu, (bool)$svasu->updatefreq);

    svasu_grade_item_update($svasu);
    svasu_update_grades($svasu);
    svasu_update_calendar($svasu, $cmid);
    \core_completion\api::update_completion_date_event($cmid, 'svasu', $svasu, $completionexpected);

    return true;
}

/**
 * Given an ID of an instance of this module,
 * this function will permanently delete the instance
 * and any data that depends on it.
 *
 * @global stdClass
 * @global object
 * @param int $id Scorm instance id
 * @return boolean
 */
function svasu_delete_instance($id) {
    global $CFG, $DB;

    if (! $svasu = $DB->get_record('svasu', array('id' => $id))) {
        return false;
    }

    $result = true;

    // Delete any dependent records.
    if (! $DB->delete_records('svasu_scoes_track', array('svasuid' => $svasu->id))) {
        $result = false;
    }
    if ($scoes = $DB->get_records('svasu_scoes', array('svasu' => $svasu->id))) {
        foreach ($scoes as $sco) {
            if (! $DB->delete_records('svasu_scoes_data', array('scoid' => $sco->id))) {
                $result = false;
            }
        }
        $DB->delete_records('svasu_scoes', array('svasu' => $svasu->id));
    }

    svasu_grade_item_delete($svasu);

    // We must delete the module record after we delete the grade item.
    if (! $DB->delete_records('svasu', array('id' => $svasu->id))) {
        $result = false;
    }

    /*if (! $DB->delete_records('svasu_sequencing_controlmode', array('svasuid'=>$svasu->id))) {
        $result = false;
    }
    if (! $DB->delete_records('svasu_sequencing_rolluprules', array('svasuid'=>$svasu->id))) {
        $result = false;
    }
    if (! $DB->delete_records('svasu_sequencing_rolluprule', array('svasuid'=>$svasu->id))) {
        $result = false;
    }
    if (! $DB->delete_records('svasu_sequencing_rollupruleconditions', array('svasuid'=>$svasu->id))) {
        $result = false;
    }
    if (! $DB->delete_records('svasu_sequencing_rolluprulecondition', array('svasuid'=>$svasu->id))) {
        $result = false;
    }
    if (! $DB->delete_records('svasu_sequencing_rulecondition', array('svasuid'=>$svasu->id))) {
        $result = false;
    }
    if (! $DB->delete_records('svasu_sequencing_ruleconditions', array('svasuid'=>$svasu->id))) {
        $result = false;
    }*/

    return $result;
}

/**
 * Return a small object with summary information about what a
 * user has done with a given particular instance of this module
 * Used for user activity reports.
 *
 * @global stdClass
 * @param int $course Course id
 * @param int $user User id
 * @param int $mod
 * @param int $svasu The svasu id
 * @return mixed
 */
function svasu_user_outline($course, $user, $mod, $svasu) {
    global $CFG;
    require_once($CFG->dirroot.'/mod/svasu/locallib.php');

    require_once("$CFG->libdir/gradelib.php");
    $grades = grade_get_grades($course->id, 'mod', 'svasu', $svasu->id, $user->id);
    if (!empty($grades->items[0]->grades)) {
        $grade = reset($grades->items[0]->grades);
        $result = (object) [
            'time' => grade_get_date_for_user_grade($grade, $user),
        ];
        if (!$grade->hidden || has_capability('moodle/grade:viewhidden', context_course::instance($course->id))) {
            $result->info = get_string('grade') . ': '. $grade->str_long_grade;
        } else {
            $result->info = get_string('grade') . ': ' . get_string('hidden', 'grades');
        }

        return $result;
    }
    return null;
}

/**
 * Print a detailed representation of what a user has done with
 * a given particular instance of this module, for user activity reports.
 *
 * @global stdClass
 * @global object
 * @param object $course
 * @param object $user
 * @param object $mod
 * @param object $svasu
 * @return boolean
 */
function svasu_user_complete($course, $user, $mod, $svasu) {
    global $CFG, $DB, $OUTPUT;
    require_once("$CFG->libdir/gradelib.php");

    $liststyle = 'structlist';
    $now = time();
    $firstmodify = $now;
    $lastmodify = 0;
    $sometoreport = false;
    $report = '';

    // First Access and Last Access dates for SCOs.
    require_once($CFG->dirroot.'/mod/svasu/locallib.php');
    $timetracks = svasu_get_sco_runtime($svasu->id, false, $user->id);
    $firstmodify = $timetracks->start;
    $lastmodify = $timetracks->finish;

    $grades = grade_get_grades($course->id, 'mod', 'svasu', $svasu->id, $user->id);
    if (!empty($grades->items[0]->grades)) {
        $grade = reset($grades->items[0]->grades);
        if (!$grade->hidden || has_capability('moodle/grade:viewhidden', context_course::instance($course->id))) {
            echo $OUTPUT->container(get_string('grade').': '.$grade->str_long_grade);
            if ($grade->str_feedback) {
                echo $OUTPUT->container(get_string('feedback').': '.$grade->str_feedback);
            }
        } else {
            echo $OUTPUT->container(get_string('grade') . ': ' . get_string('hidden', 'grades'));
        }
    }

    if ($orgs = $DB->get_records_select('svasu_scoes', 'svasu = ? AND '.
                                         $DB->sql_isempty('svasu_scoes', 'launch', false, true).' AND '.
                                         $DB->sql_isempty('svasu_scoes', 'organization', false, false),
                                         array($svasu->id), 'sortorder, id', 'id, identifier, title')) {
        if (count($orgs) <= 1) {
            unset($orgs);
            $orgs = array();
            $org = new stdClass();
            $org->identifier = '';
            $orgs[] = $org;
        }
        $report .= html_writer::start_div('mod-svasu');
        foreach ($orgs as $org) {
            $conditions = array();
            $currentorg = '';
            if (!empty($org->identifier)) {
                $report .= html_writer::div($org->title, 'orgtitle');
                $currentorg = $org->identifier;
                $conditions['organization'] = $currentorg;
            }
            $report .= html_writer::start_tag('ul', array('id' => '0', 'class' => $liststyle));
                $conditions['svasu'] = $svasu->id;
            if ($scoes = $DB->get_records('svasu_scoes', $conditions, "sortorder, id")) {
                // Drop keys so that we can access array sequentially.
                $scoes = array_values($scoes);
                $level = 0;
                $sublist = 1;
                $parents[$level] = '/';
                foreach ($scoes as $pos => $sco) {
                    if ($parents[$level] != $sco->parent) {
                        if ($level > 0 && $parents[$level - 1] == $sco->parent) {
                            $report .= html_writer::end_tag('ul').html_writer::end_tag('li');
                            $level--;
                        } else {
                            $i = $level;
                            $closelist = '';
                            while (($i > 0) && ($parents[$level] != $sco->parent)) {
                                $closelist .= html_writer::end_tag('ul').html_writer::end_tag('li');
                                $i--;
                            }
                            if (($i == 0) && ($sco->parent != $currentorg)) {
                                $report .= html_writer::start_tag('li');
                                $report .= html_writer::start_tag('ul', array('id' => $sublist, 'class' => $liststyle));
                                $level++;
                            } else {
                                $report .= $closelist;
                                $level = $i;
                            }
                            $parents[$level] = $sco->parent;
                        }
                    }
                    $report .= html_writer::start_tag('li');
                    if (isset($scoes[$pos + 1])) {
                        $nextsco = $scoes[$pos + 1];
                    } else {
                        $nextsco = false;
                    }
                    if (($nextsco !== false) && ($sco->parent != $nextsco->parent) &&
                            (($level == 0) || (($level > 0) && ($nextsco->parent == $sco->identifier)))) {
                        $sublist++;
                    } else {
                        $report .= $OUTPUT->spacer(array("height" => "12", "width" => "13"));
                    }

                    if ($sco->launch) {
                        $score = '';
                        $totaltime = '';
                        if ($usertrack = svasu_get_tracks($sco->id, $user->id)) {
                            if ($usertrack->status == '') {
                                $usertrack->status = 'notattempted';
                            }
                            $strstatus = get_string($usertrack->status, 'svasu');
                            $report .= $OUTPUT->pix_icon($usertrack->status, $strstatus, 'svasu');
                        } else {
                            if ($sco->svasutype == 'sco') {
                                $report .= $OUTPUT->pix_icon('notattempted', get_string('notattempted', 'svasu'), 'svasu');
                            } else {
                                $report .= $OUTPUT->pix_icon('asset', get_string('asset', 'svasu'), 'svasu');
                            }
                        }
                        $report .= "&nbsp;$sco->title $score$totaltime".html_writer::end_tag('li');
                        if ($usertrack !== false) {
                            $sometoreport = true;
                            $report .= html_writer::start_tag('li').html_writer::start_tag('ul', array('class' => $liststyle));
                            foreach ($usertrack as $element => $value) {
                                if (substr($element, 0, 3) == 'cmi') {
                                    $report .= html_writer::tag('li', $element.' => '.s($value));
                                }
                            }
                            $report .= html_writer::end_tag('ul').html_writer::end_tag('li');
                        }
                    } else {
                        $report .= "&nbsp;$sco->title".html_writer::end_tag('li');
                    }
                }
                for ($i = 0; $i < $level; $i++) {
                    $report .= html_writer::end_tag('ul').html_writer::end_tag('li');
                }
            }
            $report .= html_writer::end_tag('ul').html_writer::empty_tag('br');
        }
        $report .= html_writer::end_div();
    }
    if ($sometoreport) {
        if ($firstmodify < $now) {
            $timeago = format_time($now - $firstmodify);
            echo get_string('firstaccess', 'svasu').': '.userdate($firstmodify).' ('.$timeago.")".html_writer::empty_tag('br');
        }
        if ($lastmodify > 0) {
            $timeago = format_time($now - $lastmodify);
            echo get_string('lastaccess', 'svasu').': '.userdate($lastmodify).' ('.$timeago.")".html_writer::empty_tag('br');
        }
        echo get_string('report', 'svasu').":".html_writer::empty_tag('br');
        echo $report;
    } else {
        print_string('noactivity', 'svasu');
    }

    return true;
}

/**
 * Function to be run periodically according to the moodle Tasks API
 * This function searches for things that need to be done, such
 * as sending out mail, toggling flags etc ...
 *
 * @global stdClass
 * @global object
 * @return boolean
 */
function svasu_cron_scheduled_task () {
    global $CFG, $DB;

    require_once($CFG->dirroot.'/mod/svasu/locallib.php');

    $sitetimezone = core_date::get_server_timezone();
    // Now see if there are any svasu updates to be done.

    if (!isset($CFG->svasu_updatetimelast)) {    // To catch the first time.
        set_config('svasu_updatetimelast', 0);
    }

    $timenow = time();
    $updatetime = usergetmidnight($timenow, $sitetimezone);

    if ($CFG->svasu_updatetimelast < $updatetime and $timenow > $updatetime) {

        set_config('svasu_updatetimelast', $timenow);

        mtrace('Updating svasu packages which require daily update');// We are updating.

        $svasusupdate = $DB->get_records('svasu', array('updatefreq' => SVASU_UPDATE_EVERYDAY));
        foreach ($svasusupdate as $svasuupdate) {
            svasu_parse($svasuupdate, true);
        }

        // Now clear out AICC session table with old session data.
        $cfgsvasu = get_config('scorm');
        if (!empty($cfgsvasu->allowaicchacp)) {
            $expiretime = time() - ($cfgsvasu->aicchacpkeepsessiondata * 24 * 60 * 60);
            $DB->delete_records_select('svasu_aicc_session', 'timemodified < ?', array($expiretime));
        }
    }

    return true;
}

/**
 * Return grade for given user or all users.
 *
 * @global stdClass
 * @global object
 * @param int $svasuid id of svasu
 * @param int $userid optional user id, 0 means all users
 * @return array array of grades, false if none
 */
function svasu_get_user_grades($svasu, $userid=0) {
    global $CFG, $DB;
    require_once($CFG->dirroot.'/mod/svasu/locallib.php');

    $grades = array();
    if (empty($userid)) {
        $scousers = $DB->get_records_select('svasu_scoes_track', "svasuid=? GROUP BY userid",
                                            array($svasu->id), "", "userid,null");
        if ($scousers) {
            foreach ($scousers as $scouser) {
                $grades[$scouser->userid] = new stdClass();
                $grades[$scouser->userid]->id         = $scouser->userid;
                $grades[$scouser->userid]->userid     = $scouser->userid;
                $grades[$scouser->userid]->rawgrade = svasu_grade_user($svasu, $scouser->userid);
            }
        } else {
            return false;
        }

    } else {
        $preattempt = $DB->get_records_select('svasu_scoes_track', "svasuid=? AND userid=? GROUP BY userid",
                                                array($svasu->id, $userid), "", "userid,null");
        if (!$preattempt) {
            return false; // No attempt yet.
        }
        $grades[$userid] = new stdClass();
        $grades[$userid]->id         = $userid;
        $grades[$userid]->userid     = $userid;
        $grades[$userid]->rawgrade = svasu_grade_user($svasu, $userid);
    }

    return $grades;
}

/**
 * Update grades in central gradebook
 *
 * @category grade
 * @param object $svasu
 * @param int $userid specific user only, 0 mean all
 * @param bool $nullifnone
 */
function svasu_update_grades($svasu, $userid=0, $nullifnone=true) {
    global $CFG;
    require_once($CFG->libdir.'/gradelib.php');
    require_once($CFG->libdir.'/completionlib.php');

    if ($grades = svasu_get_user_grades($svasu, $userid)) {
        svasu_grade_item_update($svasu, $grades);
        // Set complete.
        svasu_set_completion($svasu, $userid, COMPLETION_COMPLETE, $grades);
    } else if ($userid and $nullifnone) {
        $grade = new stdClass();
        $grade->userid   = $userid;
        $grade->rawgrade = null;
        svasu_grade_item_update($svasu, $grade);
        // Set incomplete.
        svasu_set_completion($svasu, $userid, COMPLETION_INCOMPLETE);
    } else {
        svasu_grade_item_update($svasu);
    }
}

/**
 * Update/create grade item for given svasu
 *
 * @category grade
 * @uses GRADE_TYPE_VALUE
 * @uses GRADE_TYPE_NONE
 * @param object $svasu object with extra cmidnumber
 * @param mixed $grades optional array/object of grade(s); 'reset' means reset grades in gradebook
 * @return object grade_item
 */
function svasu_grade_item_update($svasu, $grades=null) {
    global $CFG, $DB;
    require_once($CFG->dirroot.'/mod/svasu/locallib.php');
    if (!function_exists('grade_update')) { // Workaround for buggy PHP versions.
        require_once($CFG->libdir.'/gradelib.php');
    }

    $params = array('itemname' => $svasu->name);
    if (isset($svasu->cmidnumber)) {
        $params['idnumber'] = $svasu->cmidnumber;
    }

    if ($svasu->grademethod == GRADESCOES) {
        $maxgrade = $DB->count_records_select('svasu_scoes', 'svasu = ? AND '.
                                                $DB->sql_isnotempty('svasu_scoes', 'launch', false, true), array($svasu->id));
        if ($maxgrade) {
            $params['gradetype'] = GRADE_TYPE_VALUE;
            $params['grademax']  = $maxgrade;
            $params['grademin']  = 0;
        } else {
            $params['gradetype'] = GRADE_TYPE_NONE;
        }
    } else {
        $params['gradetype'] = GRADE_TYPE_VALUE;
        $params['grademax']  = $svasu->maxgrade;
        $params['grademin']  = 0;
    }

    if ($grades === 'reset') {
        $params['reset'] = true;
        $grades = null;
    }

    return grade_update('mod/svasu', $svasu->course, 'mod', 'svasu', $svasu->id, 0, $grades, $params);
}

/**
 * Delete grade item for given svasu
 *
 * @category grade
 * @param object $svasu object
 * @return object grade_item
 */
function svasu_grade_item_delete($svasu) {
    global $CFG;
    require_once($CFG->libdir.'/gradelib.php');

    return grade_update('mod/svasu', $svasu->course, 'mod', 'svasu', $svasu->id, 0, null, array('deleted' => 1));
}

/**
 * List the actions that correspond to a view of this module.
 * This is used by the participation report.
 *
 * Note: This is not used by new logging system. Event with
 *       crud = 'r' and edulevel = LEVEL_PARTICIPATING will
 *       be considered as view action.
 *
 * @return array
 */
function svasu_get_view_actions() {
    return array('pre-view', 'view', 'view all', 'report');
}

/**
 * List the actions that correspond to a post of this module.
 * This is used by the participation report.
 *
 * Note: This is not used by new logging system. Event with
 *       crud = ('c' || 'u' || 'd') and edulevel = LEVEL_PARTICIPATING
 *       will be considered as post action.
 *
 * @return array
 */
function svasu_get_post_actions() {
    return array();
}

/**
 * @param object $svasu
 * @return object $svasu
 */
function svasu_option2text($svasu) {
    $svasupopoupoptions = svasu_get_popup_options_array();

    if (isset($svasu->popup)) {
        if ($svasu->popup == 1) {
            $optionlist = array();
            foreach ($svasupopoupoptions as $name => $option) {
                if (isset($svasu->$name)) {
                    $optionlist[] = $name.'='.$svasu->$name;
                } else {
                    $optionlist[] = $name.'=0';
                }
            }
            $svasu->options = implode(',', $optionlist);
        } else {
            $svasu->options = '';
        }
    } else {
        $svasu->popup = 0;
        $svasu->options = '';
    }
    return $svasu;
}

/**
 * Implementation of the function for printing the form elements that control
 * whether the course reset functionality affects the svasu.
 *
 * @param object $mform form passed by reference
 */
function svasu_reset_course_form_definition(&$mform) {
    $mform->addElement('header', 'svasuheader', get_string('modulenameplural', 'svasu'));
    $mform->addElement('advcheckbox', 'reset_svasu', get_string('deleteallattempts', 'svasu'));
}

/**
 * Course reset form defaults.
 *
 * @return array
 */
function svasu_reset_course_form_defaults($course) {
    return array('reset_svasu' => 1);
}

/**
 * Removes all grades from gradebook
 *
 * @global stdClass
 * @global object
 * @param int $courseid
 * @param string optional type
 */
function svasu_reset_gradebook($courseid, $type='') {
    global $CFG, $DB;

    $sql = "SELECT s.*, cm.idnumber as cmidnumber, s.course as courseid
              FROM {svasu} s, {course_modules} cm, {modules} m
             WHERE m.name='svasu' AND m.id=cm.module AND cm.instance=s.id AND s.course=?";

    if ($svasus = $DB->get_records_sql($sql, array($courseid))) {
        foreach ($svasus as $svasu) {
            svasu_grade_item_update($svasu, 'reset');
        }
    }
}

/**
 * Actual implementation of the reset course functionality, delete all the
 * svasu attempts for course $data->courseid.
 *
 * @global stdClass
 * @global object
 * @param object $data the data submitted from the reset course.
 * @return array status array
 */
function svasu_reset_userdata($data) {
    global $CFG, $DB;

    $componentstr = get_string('modulenameplural', 'svasu');
    $status = array();

    if (!empty($data->reset_svasu)) {
        $svasussql = "SELECT s.id
                         FROM {svasu} s
                        WHERE s.course=?";

        $DB->delete_records_select('svasu_scoes_track', "svasuid IN ($svasussql)", array($data->courseid));

        // Remove all grades from gradebook.
        if (empty($data->reset_gradebook_grades)) {
            svasu_reset_gradebook($data->courseid);
        }

        $status[] = array('component' => $componentstr, 'item' => get_string('deleteallattempts', 'svasu'), 'error' => false);
    }

    // Any changes to the list of dates that needs to be rolled should be same during course restore and course reset.
    // See MDL-9367.
    shift_course_mod_dates('svasu', array('timeopen', 'timeclose'), $data->timeshift, $data->courseid);
    $status[] = array('component' => $componentstr, 'item' => get_string('datechanged'), 'error' => false);

    return $status;
}

/**
 * Lists all file areas current user may browse
 *
 * @param object $course
 * @param object $cm
 * @param object $context
 * @return array
 */
function svasu_get_file_areas($course, $cm, $context) {
    $areas = array();
    $areas['content'] = get_string('areacontent', 'svasu');
    $areas['package'] = get_string('areapackage', 'svasu');
    return $areas;
}

/**
 * File browsing support for SVASU file areas
 *
 * @package  mod_svasu
 * @category files
 * @param file_browser $browser file browser instance
 * @param array $areas file areas
 * @param stdClass $course course object
 * @param stdClass $cm course module object
 * @param stdClass $context context object
 * @param string $filearea file area
 * @param int $itemid item ID
 * @param string $filepath file path
 * @param string $filename file name
 * @return file_info instance or null if not found
 */
function svasu_get_file_info($browser, $areas, $course, $cm, $context, $filearea, $itemid, $filepath, $filename) {
    global $CFG;

    if (!has_capability('moodle/course:managefiles', $context)) {
        return null;
    }

    // No writing for now!

    $fs = get_file_storage();

    if ($filearea === 'content') {

        $filepath = is_null($filepath) ? '/' : $filepath;
        $filename = is_null($filename) ? '.' : $filename;

        $urlbase = $CFG->wwwroot.'/pluginfile.php';
        if (!$storedfile = $fs->get_file($context->id, 'mod_svasu', 'content', 0, $filepath, $filename)) {
            if ($filepath === '/' and $filename === '.') {
                $storedfile = new virtual_root_file($context->id, 'mod_svasu', 'content', 0);
            } else {
                // Not found.
                return null;
            }
        }
        require_once("$CFG->dirroot/mod/svasu/locallib.php");
        return new svasu_package_file_info($browser, $context, $storedfile, $urlbase, $areas[$filearea], true, true, false, false);

    } else if ($filearea === 'package') {
        $filepath = is_null($filepath) ? '/' : $filepath;
        $filename = is_null($filename) ? '.' : $filename;

        $urlbase = $CFG->wwwroot.'/pluginfile.php';
        if (!$storedfile = $fs->get_file($context->id, 'mod_svasu', 'package', 0, $filepath, $filename)) {
            if ($filepath === '/' and $filename === '.') {
                $storedfile = new virtual_root_file($context->id, 'mod_svasu', 'package', 0);
            } else {
                // Not found.
                return null;
            }
        }
        return new file_info_stored($browser, $context, $storedfile, $urlbase, $areas[$filearea], false, true, false, false);
    }

    // Scorm_intro handled in file_browser.

    return false;
}

/**
 * Serves svasu content, introduction images and packages. Implements needed access control ;-)
 *
 * @package  mod_svasu
 * @category files
 * @param stdClass $course course object
 * @param stdClass $cm course module object
 * @param stdClass $context context object
 * @param string $filearea file area
 * @param array $args extra arguments
 * @param bool $forcedownload whether or not force download
 * @param array $options additional options affecting the file serving
 * @return bool false if file not found, does not return if found - just send the file
 */
function svasu_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options=array()) {
    global $CFG, $DB;

    if ($context->contextlevel != CONTEXT_MODULE) {
        return false;
    }

    require_login($course, true, $cm);

    $canmanageactivity = has_capability('moodle/course:manageactivities', $context);
    $lifetime = null;

    // Check SVASU availability.
    if (!$canmanageactivity) {
        require_once($CFG->dirroot.'/mod/svasu/locallib.php');

        $svasu = $DB->get_record('svasu', array('id' => $cm->instance), 'id, timeopen, timeclose', MUST_EXIST);
        list($available, $warnings) = svasu_get_availability_status($svasu);
        if (!$available) {
            return false;
        }
    }

    if ($filearea === 'content') {
        $revision = (int)array_shift($args); // Prevents caching problems - ignored here.
        $relativepath = implode('/', $args);
        $fullpath = "/$context->id/mod_svasu/content/0/$relativepath";
        $options['immutable'] = true; // Add immutable option, $relativepath changes on file update.

    } else if ($filearea === 'package') {
        // Check if the global setting for disabling package downloads is enabled.
        $protectpackagedownloads = get_config('scorm', 'protectpackagedownloads');
        if ($protectpackagedownloads and !$canmanageactivity) {
            return false;
        }
        $revision = (int)array_shift($args); // Prevents caching problems - ignored here.
        $relativepath = implode('/', $args);
        $fullpath = "/$context->id/mod_svasu/package/0/$relativepath";
        $lifetime = 0; // No caching here.

    } else if ($filearea === 'imsmanifest') { // This isn't a real filearea, it's a url parameter for this type of package.
        $revision = (int)array_shift($args); // Prevents caching problems - ignored here.
        $relativepath = implode('/', $args);

        // Get imsmanifest file.
        $fs = get_file_storage();
        $files = $fs->get_area_files($context->id, 'mod_svasu', 'package', 0, '', false);
        $file = reset($files);

        // Check that the package file is an imsmanifest.xml file - if not then this method is not allowed.
        $packagefilename = $file->get_filename();
        if (strtolower($packagefilename) !== 'imsmanifest.xml') {
            return false;
        }

        $file->send_relative_file($relativepath);
    } else {
        return false;
    }

    $fs = get_file_storage();
    if (!$file = $fs->get_file_by_hash(sha1($fullpath)) or $file->is_directory()) {
        if ($filearea === 'content') { // Return file not found straight away to improve performance.
            send_header_404();
            die;
        }
        return false;
    }

    // Finally send the file.
    send_stored_file($file, $lifetime, 0, false, $options);
}

/**
 * @uses FEATURE_GROUPS
 * @uses FEATURE_GROUPINGS
 * @uses FEATURE_MOD_INTRO
 * @uses FEATURE_COMPLETION_TRACKS_VIEWS
 * @uses FEATURE_COMPLETION_HAS_RULES
 * @uses FEATURE_GRADE_HAS_GRADE
 * @uses FEATURE_GRADE_OUTCOMES
 * @param string $feature FEATURE_xx constant for requested feature
 * @return mixed True if module supports feature, false if not, null if doesn't know
 */
function svasu_supports($feature) {
    switch($feature) {
        case FEATURE_GROUPS:                  return true;
        case FEATURE_GROUPINGS:               return true;
        case FEATURE_MOD_INTRO:               return true;
        case FEATURE_COMPLETION_TRACKS_VIEWS: return true;
        case FEATURE_COMPLETION_HAS_RULES:    return true;
        case FEATURE_GRADE_HAS_GRADE:         return true;
        case FEATURE_GRADE_OUTCOMES:          return true;
        case FEATURE_BACKUP_MOODLE2:          return true;
        case FEATURE_SHOW_DESCRIPTION:        return true;

        default: return null;
    }
}

/**
 * Get the filename for a temp log file
 *
 * @param string $type - type of log(aicc,svasu12,svasu13) used as prefix for filename
 * @param integer $scoid - scoid of object this log entry is for
 * @return string The filename as an absolute path
 */
function svasu_debug_log_filename($type, $scoid) {
    global $CFG, $USER;

    $logpath = $CFG->tempdir.'/svasulogs';
    $logfile = $logpath.'/'.$type.'debug_'.$USER->id.'_'.$scoid.'.log';
    return $logfile;
}

/**
 * writes log output to a temp log file
 *
 * @param string $type - type of log(aicc,svasu12,svasu13) used as prefix for filename
 * @param string $text - text to be written to file.
 * @param integer $scoid - scoid of object this log entry is for.
 */
function svasu_debug_log_write($type, $text, $scoid) {
    global $CFG;

    $debugenablelog = get_config('scorm', 'allowapidebug');
    if (!$debugenablelog || empty($text)) {
        return;
    }
    if (make_temp_directory('svasulogs/')) {
        $logfile = svasu_debug_log_filename($type, $scoid);
        @file_put_contents($logfile, date('Y/m/d H:i:s O')." DEBUG $text\r\n", FILE_APPEND);
        @chmod($logfile, $CFG->filepermissions);
    }
}

/**
 * Remove debug log file
 *
 * @param string $type - type of log(aicc,svasu12,svasu13) used as prefix for filename
 * @param integer $scoid - scoid of object this log entry is for
 * @return boolean True if the file is successfully deleted, false otherwise
 */
function svasu_debug_log_remove($type, $scoid) {

    $debugenablelog = get_config('scorm', 'allowapidebug');
    $logfile = svasu_debug_log_filename($type, $scoid);
    if (!$debugenablelog || !file_exists($logfile)) {
        return false;
    }

    return @unlink($logfile);
}

/**
 * @deprecated since Moodle 3.3, when the block_course_overview block was removed.
 */
function svasu_print_overview() {
    throw new coding_exception('svasu_print_overview() can not be used any more and is obsolete.');
}

/**
 * Return a list of page types
 * @param string $pagetype current page type
 * @param stdClass $parentcontext Block's parent context
 * @param stdClass $currentcontext Current context of block
 */
function svasu_page_type_list($pagetype, $parentcontext, $currentcontext) {
    $modulepagetype = array('mod-svasu-*' => get_string('page-mod-svasu-x', 'svasu'));
    return $modulepagetype;
}

/**
 * Returns the SVASU version used.
 * @param string $svasuversion comes from $svasu->version
 * @param string $version one of the defined vars SVASU_12, SVASU_13, SVASU_AICC (or empty)
 * @return Scorm version.
 */
function svasu_version_check($svasuversion, $version='') {
    $svasuversion = trim(strtolower($svasuversion));
    if (empty($version) || $version == SVASU_12) {
        if ($svasuversion == 'scorm_12' || $svasuversion == 'svasu_1.2') {
            return SVASU_12;
        }
        if (!empty($version)) {
            return false;
        }
    }
    if (empty($version) || $version == SVASU_13) {
        if ($svasuversion == 'scorm_13' || $svasuversion == 'svasu_1.3') {
            return SVASU_13;
        }
        if (!empty($version)) {
            return false;
        }
    }
    if (empty($version) || $version == SVASU_AICC) {
        if (strpos($svasuversion, 'aicc')) {
            return SVASU_AICC;
        }
        if (!empty($version)) {
            return false;
        }
    }
    return false;
}

/**
 * Obtains the automatic completion state for this svasu based on any conditions
 * in svasu settings.
 *
 * @param object $course Course
 * @param object $cm Course-module
 * @param int $userid User ID
 * @param bool $type Type of comparison (or/and; can be used as return value if no conditions)
 * @return bool True if completed, false if not. (If no conditions, then return
 *   value depends on comparison type)
 */
function svasu_get_completion_state($course, $cm, $userid, $type) {
    global $DB;

    $result = $type;

    // Get svasu.
    if (!$svasu = $DB->get_record('svasu', array('id' => $cm->instance))) {
        print_error('cannotfindsvasu');
    }
    // Only check for existence of tracks and return false if completionstatusrequired or completionscorerequired
    // this means that if only view is required we don't end up with a false state.
    if ($svasu->completionstatusrequired !== null ||
        $svasu->completionscorerequired !== null) {
        // Get user's tracks data.
        $tracks = $DB->get_records_sql(
            "
            SELECT
                id,
                scoid,
                element,
                value
            FROM
                {svasu_scoes_track}
            WHERE
                svasuid = ?
            AND userid = ?
            AND element IN
            (
                'cmi.core.lesson_status',
                'cmi.completion_status',
                'cmi.success_status',
                'cmi.core.score.raw',
                'cmi.score.raw'
            )
            ",
            array($svasu->id, $userid)
        );

        if (!$tracks) {
            return completion_info::aggregate_completion_states($type, $result, false);
        }
    }

    // Check for status.
    if ($svasu->completionstatusrequired !== null) {

        // Get status.
        $statuses = array_flip(svasu_status_options());
        $nstatus = 0;
        // Check any track for these values.
        $scostatus = array();
        foreach ($tracks as $track) {
            if (!in_array($track->element, array('cmi.core.lesson_status', 'cmi.completion_status', 'cmi.success_status'))) {
                continue;
            }
            if (array_key_exists($track->value, $statuses)) {
                $scostatus[$track->scoid] = true;
                $nstatus |= $statuses[$track->value];
            }
        }

        if (!empty($svasu->completionstatusallscos)) {
            // Iterate over all scos and make sure each has a lesson_status.
            $scos = $DB->get_records('svasu_scoes', array('svasu' => $svasu->id, 'svasutype' => 'sco'));
            foreach ($scos as $sco) {
                if (empty($scostatus[$sco->id])) {
                    return completion_info::aggregate_completion_states($type, $result, false);
                }
            }
            return completion_info::aggregate_completion_states($type, $result, true);
        } else if ($svasu->completionstatusrequired & $nstatus) {
            return completion_info::aggregate_completion_states($type, $result, true);
        } else {
            return completion_info::aggregate_completion_states($type, $result, false);
        }
    }

    // Check for score.
    if ($svasu->completionscorerequired !== null) {
        $maxscore = -1;

        foreach ($tracks as $track) {
            if (!in_array($track->element, array('cmi.core.score.raw', 'cmi.score.raw'))) {
                continue;
            }

            if (strlen($track->value) && floatval($track->value) >= $maxscore) {
                $maxscore = floatval($track->value);
            }
        }

        if ($svasu->completionscorerequired <= $maxscore) {
            return completion_info::aggregate_completion_states($type, $result, true);
        } else {
            return completion_info::aggregate_completion_states($type, $result, false);
        }
    }

    return $result;
}

/**
 * Register the ability to handle drag and drop file uploads
 * @return array containing details of the files / types the mod can handle
 */
function svasu_dndupload_register() {
    return array('files' => array(
        array('extension' => 'zip', 'message' => get_string('dnduploadsvasu', 'svasu'))
    ));
}

/**
 * Handle a file that has been uploaded
 * @param object $uploadinfo details of the file / content that has been uploaded
 * @return int instance id of the newly created mod
 */
function svasu_dndupload_handle($uploadinfo) {

    $context = context_module::instance($uploadinfo->coursemodule);
    file_save_draft_area_files($uploadinfo->draftitemid, $context->id, 'mod_svasu', 'package', 0);
    $fs = get_file_storage();
    $files = $fs->get_area_files($context->id, 'mod_svasu', 'package', 0, 'sortorder, itemid, filepath, filename', false);
    $file = reset($files);

    // Validate the file, make sure it's a valid SVASU package!
    $errors = svasu_validate_package($file);
    if (!empty($errors)) {
        return false;
    }
    // Create a default svasu object to pass to svasu_add_instance()!
    $svasu = get_config('scorm');
    $svasu->course = $uploadinfo->course->id;
    $svasu->coursemodule = $uploadinfo->coursemodule;
    $svasu->cmidnumber = '';
    $svasu->name = $uploadinfo->displayname;
    $svasu->svasutype = SVASU_TYPE_LOCAL;
    $svasu->reference = $file->get_filename();
    $svasu->intro = '';
    $svasu->width = $svasu->framewidth;
    $svasu->height = $svasu->frameheight;

    return svasu_add_instance($svasu, null);
}

/**
 * Sets activity completion state
 *
 * @param object $svasu object
 * @param int $userid User ID
 * @param int $completionstate Completion state
 * @param array $grades grades array of users with grades - used when $userid = 0
 */
function svasu_set_completion($svasu, $userid, $completionstate = COMPLETION_COMPLETE, $grades = array()) {
    $course = new stdClass();
    $course->id = $svasu->course;
    $completion = new completion_info($course);

    // Check if completion is enabled site-wide, or for the course.
    if (!$completion->is_enabled()) {
        return;
    }

    $cm = get_coursemodule_from_instance('svasu', $svasu->id, $svasu->course);
    if (empty($cm) || !$completion->is_enabled($cm)) {
            return;
    }

    if (empty($userid)) { // We need to get all the relevant users from $grades param.
        foreach ($grades as $grade) {
            $completion->update_state($cm, $completionstate, $grade->userid);
        }
    } else {
        $completion->update_state($cm, $completionstate, $userid);
    }
}

/**
 * Check that a Zip file contains a valid SVASU package
 *
 * @param $file stored_file a Zip file.
 * @return array empty if no issue is found. Array of error message otherwise
 */
function svasu_validate_package($file) {
    $packer = get_file_packer('application/zip');
    $errors = array();
    if ($file->is_external_file()) { // Get zip file so we can check it is correct.
        $file->import_external_file_contents();
    }
    $filelist = $file->list_files($packer);

    if (!is_array($filelist)) {
        $errors['packagefile'] = get_string('badarchive', 'svasu');
    } else {
        $aiccfound = false;
        $badmanifestpresent = false;
        foreach ($filelist as $info) {
            if ($info->pathname == 'imsmanifest.xml') {
                return array();
            } else if (strpos($info->pathname, 'imsmanifest.xml') !== false) {
                // This package has an imsmanifest file inside a folder of the package.
                $badmanifestpresent = true;
            }
            if (preg_match('/\.cst$/', $info->pathname)) {
                return array();
            }
        }
        if (!$aiccfound) {
            if ($badmanifestpresent) {
                $errors['packagefile'] = get_string('badimsmanifestlocation', 'svasu');
            } else {
                $errors['packagefile'] = get_string('nomanifest', 'svasu');
            }
        }
    }
    return $errors;
}

/**
 * Check and set the correct mode and attempt when entering a SVASU package.
 *
 * @param object $svasu object
 * @param string $newattempt should a new attempt be generated here.
 * @param int $attempt the attempt number this is for.
 * @param int $userid the userid of the user.
 * @param string $mode the current mode that has been selected.
 */
function svasu_check_mode($svasu, &$newattempt, &$attempt, $userid, &$mode) {
    global $DB;

    if (($mode == 'browse')) {
        if ($svasu->hidebrowse == 1) {
            // Prevent Browse mode if hidebrowse is set.
            $mode = 'normal';
        } else {
            // We don't need to check attempts as browse mode is set.
            return;
        }
    }

    if ($svasu->forcenewattempt == SVASU_FORCEATTEMPT_ALWAYS) {
        // This SVASU is configured to force a new attempt on every re-entry.
        $newattempt = 'on';
        $mode = 'normal';
        if ($attempt == 1) {
            // Check if the user has any existing data or if this is really the first attempt.
            $exists = $DB->record_exists('svasu_scoes_track', array('userid' => $userid, 'svasuid' => $svasu->id));
            if (!$exists) {
                // No records yet - Attempt should == 1.
                return;
            }
        }
        $attempt++;

        return;
    }
    // Check if the svasu module is incomplete (used to validate user request to start a new attempt).
    $incomplete = true;

    // Note - in SVASU_13 the cmi-core.lesson_status field was split into
    // 'cmi.completion_status' and 'cmi.success_status'.
    // 'cmi.completion_status' can only contain values 'completed', 'incomplete', 'not attempted' or 'unknown'.
    // This means the values 'passed' or 'failed' will never be reported for a track in SVASU_13 and
    // the only status that will be treated as complete is 'completed'.

    $completionelements = array(
        SVASU_12 => 'cmi.core.lesson_status',
        SVASU_13 => 'cmi.completion_status',
        SVASU_AICC => 'cmi.core.lesson_status'
    );
    $svasuversion = svasu_version_check($svasu->version);
    if($svasuversion===false) {
        $svasuversion = SVASU_12;
    }
    $completionelement = $completionelements[$svasuversion];

    $sql = "SELECT sc.id, t.value
              FROM {svasu_scoes} sc
         LEFT JOIN {svasu_scoes_track} t ON sc.svasu = t.svasuid AND sc.id = t.scoid
                   AND t.element = ? AND t.userid = ? AND t.attempt = ?
             WHERE sc.svasutype = 'sco' AND sc.svasu = ?";
    $tracks = $DB->get_recordset_sql($sql, array($completionelement, $userid, $attempt, $svasu->id));

    foreach ($tracks as $track) {
        if (($track->value == 'completed') || ($track->value == 'passed') || ($track->value == 'failed')) {
            $incomplete = false;
        } else {
            $incomplete = true;
            break; // Found an incomplete sco, so the result as a whole is incomplete.
        }
    }
    $tracks->close();

    // Validate user request to start a new attempt.
    if ($incomplete === true) {
        // The option to start a new attempt should never have been presented. Force false.
        $newattempt = 'off';
    } else if (!empty($svasu->forcenewattempt)) {
        // A new attempt should be forced for already completed attempts.
        $newattempt = 'on';
    }

    if (($newattempt == 'on') && (($attempt < $svasu->maxattempt) || ($svasu->maxattempt == 0))) {
        $attempt++;
        $mode = 'normal';
    } else { // Check if review mode should be set.
        if ($incomplete === true) {
            $mode = 'normal';
        } else {
            $mode = 'review';
        }
    }
}

/**
 * Trigger the course_module_viewed event.
 *
 * @param  stdClass $svasu        svasu object
 * @param  stdClass $course     course object
 * @param  stdClass $cm         course module object
 * @param  stdClass $context    context object
 * @since Moodle 3.0
 */
function svasu_view($svasu, $course, $cm, $context) {

    // Trigger course_module_viewed event.
    $params = array(
        'context' => $context,
        'objectid' => $svasu->id
    );

    $event = \mod_svasu\event\course_module_viewed::create($params);
    $event->add_record_snapshot('course_modules', $cm);
    $event->add_record_snapshot('course', $course);
    $event->add_record_snapshot('svasu', $svasu);
    $event->trigger();
}

/**
 * Check if the module has any update that affects the current user since a given time.
 *
 * @param  cm_info $cm course module data
 * @param  int $from the time to check updates from
 * @param  array $filter  if we need to check only specific updates
 * @return stdClass an object with the different type of areas indicating if they were updated or not
 * @since Moodle 3.2
 */
function svasu_check_updates_since(cm_info $cm, $from, $filter = array()) {
    global $DB, $USER, $CFG;
    require_once($CFG->dirroot . '/mod/svasu/locallib.php');

    $svasu = $DB->get_record($cm->modname, array('id' => $cm->instance), '*', MUST_EXIST);
    $updates = new stdClass();
    list($available, $warnings) = svasu_get_availability_status($svasu, true, $cm->context);
    if (!$available) {
        return $updates;
    }
    $updates = course_check_module_updates_since($cm, $from, array('package'), $filter);

    $updates->tracks = (object) array('updated' => false);
    $select = 'svasuid = ? AND userid = ? AND timemodified > ?';
    $params = array($svasu->id, $USER->id, $from);
    $tracks = $DB->get_records_select('svasu_scoes_track', $select, $params, '', 'id');
    if (!empty($tracks)) {
        $updates->tracks->updated = true;
        $updates->tracks->itemids = array_keys($tracks);
    }

    // Now, teachers should see other students updates.
    if (has_capability('mod/svasu:viewreport', $cm->context)) {
        $select = 'svasuid = ? AND timemodified > ?';
        $params = array($svasu->id, $from);

        if (groups_get_activity_groupmode($cm) == SEPARATEGROUPS) {
            $groupusers = array_keys(groups_get_activity_shared_group_members($cm));
            if (empty($groupusers)) {
                return $updates;
            }
            list($insql, $inparams) = $DB->get_in_or_equal($groupusers);
            $select .= ' AND userid ' . $insql;
            $params = array_merge($params, $inparams);
        }

        $updates->usertracks = (object) array('updated' => false);
        $tracks = $DB->get_records_select('svasu_scoes_track', $select, $params, '', 'id');
        if (!empty($tracks)) {
            $updates->usertracks->updated = true;
            $updates->usertracks->itemids = array_keys($tracks);
        }
    }
    return $updates;
}

/**
 * Get icon mapping for font-awesome.
 */
function mod_svasu_get_fontawesome_icon_map() {
    return [
        'mod_svasu:assetc' => 'fa-file-archive-o',
        'mod_svasu:asset' => 'fa-file-archive-o',
        'mod_svasu:browsed' => 'fa-book',
        'mod_svasu:completed' => 'fa-check-square-o',
        'mod_svasu:failed' => 'fa-times',
        'mod_svasu:incomplete' => 'fa-pencil-square-o',
        'mod_svasu:minus' => 'fa-minus',
        'mod_svasu:notattempted' => 'fa-square-o',
        'mod_svasu:passed' => 'fa-check',
        'mod_svasu:plus' => 'fa-plus',
        'mod_svasu:popdown' => 'fa-window-close-o',
        'mod_svasu:popup' => 'fa-window-restore',
        'mod_svasu:suspend' => 'fa-pause',
        'mod_svasu:wait' => 'fa-clock-o',
    ];
}

/**
 * This standard function will check all instances of this module
 * and make sure there are up-to-date events created for each of them.
 * If courseid = 0, then every svasu event in the site is checked, else
 * only svasu events belonging to the course specified are checked.
 *
 * @param int $courseid
 * @param int|stdClass $instance svasu module instance or ID.
 * @param int|stdClass $cm Course module object or ID.
 * @return bool
 */
function svasu_refresh_events($courseid = 0, $instance = null, $cm = null) {
    global $CFG, $DB;

    require_once($CFG->dirroot . '/mod/svasu/locallib.php');

    // If we have instance information then we can just update the one event instead of updating all events.
    if (isset($instance)) {
        if (!is_object($instance)) {
            $instance = $DB->get_record('svasu', array('id' => $instance), '*', MUST_EXIST);
        }
        if (isset($cm)) {
            if (!is_object($cm)) {
                $cm = (object)array('id' => $cm);
            }
        } else {
            $cm = get_coursemodule_from_instance('svasu', $instance->id);
        }
        svasu_update_calendar($instance, $cm->id);
        return true;
    }

    if ($courseid) {
        // Make sure that the course id is numeric.
        if (!is_numeric($courseid)) {
            return false;
        }
        if (!$svasus = $DB->get_records('svasu', array('course' => $courseid))) {
            return false;
        }
    } else {
        if (!$svasus = $DB->get_records('svasu')) {
            return false;
        }
    }

    foreach ($svasus as $svasu) {
        $cm = get_coursemodule_from_instance('svasu', $svasu->id);
        svasu_update_calendar($svasu, $cm->id);
    }

    return true;
}

/**
 * This function receives a calendar event and returns the action associated with it, or null if there is none.
 *
 * This is used by block_myoverview in order to display the event appropriately. If null is returned then the event
 * is not displayed on the block.
 *
 * @param calendar_event $event
 * @param \core_calendar\action_factory $factory
 * @param int $userid User id override
 * @return \core_calendar\local\event\entities\action_interface|null
 */
function mod_svasu_core_calendar_provide_event_action(calendar_event $event,
                                                      \core_calendar\action_factory $factory, $userid = null) {
    global $CFG, $USER;

    require_once($CFG->dirroot . '/mod/svasu/locallib.php');

    if (empty($userid)) {
        $userid = $USER->id;
    }

    $cm = get_fast_modinfo($event->courseid, $userid)->instances['svasu'][$event->instance];

    if (has_capability('mod/svasu:viewreport', $cm->context, $userid)) {
        // Teachers do not need to be reminded to complete a svasu.
        return null;
    }

    $completion = new \completion_info($cm->get_course());

    $completiondata = $completion->get_data($cm, false, $userid);

    if ($completiondata->completionstate != COMPLETION_INCOMPLETE) {
        return null;
    }

    if (!empty($cm->customdata['timeclose']) && $cm->customdata['timeclose'] < time()) {
        // The svasu has closed so the user can no longer submit anything.
        return null;
    }

    // Restore svasu object from cached values in $cm, we only need id, timeclose and timeopen.
    $customdata = $cm->customdata ?: [];
    $customdata['id'] = $cm->instance;
    $svasu = (object)($customdata + ['timeclose' => 0, 'timeopen' => 0]);

    // Check that the SVASU activity is open.
    list($actionable, $warnings) = svasu_get_availability_status($svasu, false, null, $userid);

    return $factory->create_instance(
        get_string('enter', 'svasu'),
        new \moodle_url('/mod/svasu/view.php', array('id' => $cm->id)),
        1,
        $actionable
    );
}

/**
 * Add a get_coursemodule_info function in case any SVASU type wants to add 'extra' information
 * for the course (see resource).
 *
 * Given a course_module object, this function returns any "extra" information that may be needed
 * when printing this activity in a course listing.  See get_array_of_activities() in course/lib.php.
 *
 * @param stdClass $coursemodule The coursemodule object (record).
 * @return cached_cm_info An object on information that the courses
 *                        will know about (most noticeably, an icon).
 */
function svasu_get_coursemodule_info($coursemodule) {
    global $DB;

    $dbparams = ['id' => $coursemodule->instance];
    $fields = 'id, name, intro, introformat, completionstatusrequired, completionscorerequired, completionstatusallscos, '.
        'timeopen, timeclose';
    if (!$svasu = $DB->get_record('svasu', $dbparams, $fields)) {
        return false;
    }

    $result = new cached_cm_info();
    $result->name = $svasu->name;

    if ($coursemodule->showdescription) {
        // Convert intro to html. Do not filter cached version, filters run at display time.
        $result->content = format_module_intro('svasu', $svasu, $coursemodule->id, false);
    }

    // Populate the custom completion rules as key => value pairs, but only if the completion mode is 'automatic'.
    if ($coursemodule->completion == COMPLETION_TRACKING_AUTOMATIC) {
        $result->customdata['customcompletionrules']['completionstatusrequired'] = $svasu->completionstatusrequired;
        $result->customdata['customcompletionrules']['completionscorerequired'] = $svasu->completionscorerequired;
        $result->customdata['customcompletionrules']['completionstatusallscos'] = $svasu->completionstatusallscos;
    }
    // Populate some other values that can be used in calendar or on dashboard.
    if ($svasu->timeopen) {
        $result->customdata['timeopen'] = $svasu->timeopen;
    }
    if ($svasu->timeclose) {
        $result->customdata['timeclose'] = $svasu->timeclose;
    }

    return $result;
}

/**
 * Callback which returns human-readable strings describing the active completion custom rules for the module instance.
 *
 * @param cm_info|stdClass $cm object with fields ->completion and ->customdata['customcompletionrules']
 * @return array $descriptions the array of descriptions for the custom rules.
 */
function mod_svasu_get_completion_active_rule_descriptions($cm) {
    // Values will be present in cm_info, and we assume these are up to date.
    if (empty($cm->customdata['customcompletionrules'])
        || $cm->completion != COMPLETION_TRACKING_AUTOMATIC) {
        return [];
    }

    $descriptions = [];
    foreach ($cm->customdata['customcompletionrules'] as $key => $val) {
        switch ($key) {
            case 'completionstatusrequired':
                if (!is_null($val)) {
                    // Determine the selected statuses using a bitwise operation.
                    $cvalues = array();
                    foreach (svasu_status_options(true) as $bit => $string) {
                        if (($val & $bit) == $bit) {
                            $cvalues[] = $string;
                        }
                    }
                    $statusstring = implode(', ', $cvalues);
                    $descriptions[] = get_string('completionstatusrequireddesc', 'svasu', $statusstring);
                }
                break;
            case 'completionscorerequired':
                if (!is_null($val)) {
                    $descriptions[] = get_string('completionscorerequireddesc', 'svasu', $val);
                }
                break;
            case 'completionstatusallscos':
                if (!empty($val)) {
                    $descriptions[] = get_string('completionstatusallscos', 'svasu');
                }
                break;
            default:
                break;
        }
    }
    return $descriptions;
}

/**
 * This function will update the svasu module according to the
 * event that has been modified.
 *
 * It will set the timeopen or timeclose value of the svasu instance
 * according to the type of event provided.
 *
 * @throws \moodle_exception
 * @param \calendar_event $event
 * @param stdClass $svasu The module instance to get the range from
 */
function mod_svasu_core_calendar_event_timestart_updated(\calendar_event $event, \stdClass $svasu) {
    global $DB;

    if (empty($event->instance) || $event->modulename != 'svasu') {
        return;
    }

    if ($event->instance != $svasu->id) {
        return;
    }

    if (!in_array($event->eventtype, [SVASU_EVENT_TYPE_OPEN, SVASU_EVENT_TYPE_CLOSE])) {
        return;
    }

    $courseid = $event->courseid;
    $modulename = $event->modulename;
    $instanceid = $event->instance;
    $modified = false;

    $coursemodule = get_fast_modinfo($courseid)->instances[$modulename][$instanceid];
    $context = context_module::instance($coursemodule->id);

    // The user does not have the capability to modify this activity.
    if (!has_capability('moodle/course:manageactivities', $context)) {
        return;
    }

    if ($event->eventtype == SVASU_EVENT_TYPE_OPEN) {
        // If the event is for the svasu activity opening then we should
        // set the start time of the svasu activity to be the new start
        // time of the event.
        if ($svasu->timeopen != $event->timestart) {
            $svasu->timeopen = $event->timestart;
            $svasu->timemodified = time();
            $modified = true;
        }
    } else if ($event->eventtype == SVASU_EVENT_TYPE_CLOSE) {
        // If the event is for the svasu activity closing then we should
        // set the end time of the svasu activity to be the new start
        // time of the event.
        if ($svasu->timeclose != $event->timestart) {
            $svasu->timeclose = $event->timestart;
            $modified = true;
        }
    }

    if ($modified) {
        $svasu->timemodified = time();
        $DB->update_record('svasu', $svasu);
        $event = \core\event\course_module_updated::create_from_cm($coursemodule, $context);
        $event->trigger();
    }
}

/**
 * This function calculates the minimum and maximum cutoff values for the timestart of
 * the given event.
 *
 * It will return an array with two values, the first being the minimum cutoff value and
 * the second being the maximum cutoff value. Either or both values can be null, which
 * indicates there is no minimum or maximum, respectively.
 *
 * If a cutoff is required then the function must return an array containing the cutoff
 * timestamp and error string to display to the user if the cutoff value is violated.
 *
 * A minimum and maximum cutoff return value will look like:
 * [
 *     [1505704373, 'The date must be after this date'],
 *     [1506741172, 'The date must be before this date']
 * ]
 *
 * @param \calendar_event $event The calendar event to get the time range for
 * @param \stdClass $instance The module instance to get the range from
 * @return array Returns an array with min and max date.
 */
function mod_svasu_core_calendar_get_valid_event_timestart_range(\calendar_event $event, \stdClass $instance) {
    $mindate = null;
    $maxdate = null;

    if ($event->eventtype == SVASU_EVENT_TYPE_OPEN) {
        // The start time of the open event can't be equal to or after the
        // close time of the svasu activity.
        if (!empty($instance->timeclose)) {
            $maxdate = [
                $instance->timeclose,
                get_string('openafterclose', 'svasu')
            ];
        }
    } else if ($event->eventtype == SVASU_EVENT_TYPE_CLOSE) {
        // The start time of the close event can't be equal to or earlier than the
        // open time of the svasu activity.
        if (!empty($instance->timeopen)) {
            $mindate = [
                $instance->timeopen,
                get_string('closebeforeopen', 'svasu')
            ];
        }
    }

    return [$mindate, $maxdate];
}

/**
 * Given an array with a file path, it returns the itemid and the filepath for the defined filearea.
 *
 * @param  string $filearea The filearea.
 * @param  array  $args The path (the part after the filearea and before the filename).
 * @return array The itemid and the filepath inside the $args path, for the defined filearea.
 */
function mod_svasu_get_path_from_pluginfile(string $filearea, array $args) : array {
    // SVASU never has an itemid (the number represents the revision but it's not stored in database).
    array_shift($args);

    // Get the filepath.
    if (empty($args)) {
        $filepath = '/';
    } else {
        $filepath = '/' . implode('/', $args) . '/';
    }

    return [
        'itemid' => 0,
        'filepath' => $filepath,
    ];
}
