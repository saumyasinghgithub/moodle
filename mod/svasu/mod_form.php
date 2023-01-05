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
    die('Direct access to this script is forbidden.');    //  It must be included from a Moodle page.
}

require_once($CFG->dirroot.'/course/moodleform_mod.php');
require_once($CFG->dirroot.'/mod/svasu/locallib.php');

class mod_svasu_mod_form extends moodleform_mod {

    public function definition() {
        global $CFG, $COURSE, $OUTPUT;
        $cfgsvasu = get_config('scorm');

        $mform = $this->_form;

        //svasu cloud 
        $PAGE->requires->js( new moodle_url($CFG->wwwroot . '/mod/hotquestion/svasu.js') );
        $selectModelButton = $mform->addElement('button', 'svasu-add-resource', get_string('svasuname', 'svasu')); 
        $buttonattributes = array('title'=>get_string('svasuname', 'svasu'), 'onclick'=>"return openpopup();");   
        $selectModelButton->updateAttributes($buttonattributes);
        //fetch token value from db
        $pluginDataSvasu = $DB->get_record('config_plugins', ['plugin' => 'mod_svasu', 'name' => 'token']);
        $token =  $pluginDataSvasu->value;
        $mform->addElement('hidden','configToken',$token);
        // Add standard elements.
        $mform->addElement('html', '
        <div id="myiframe"></div>');
        //end svasu cloud

        if (!$CFG->slasharguments) {
            $mform->addElement('static', '', '', $OUTPUT->notification(get_string('slashargs', 'svasu'), 'notifyproblem'));
        }

        $mform->addElement('header', 'general', get_string('general', 'form'));

        // Name.
        $mform->addElement('text', 'name', get_string('name'));
        if (!empty($CFG->formatstringstriptags)) {
            $mform->setType('name', PARAM_TEXT);
        } else {
            $mform->setType('name', PARAM_CLEANHTML);
        }
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addRule('name', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');

        // Summary.
        $this->standard_intro_elements();

        // Package.
        $mform->addElement('header', 'packagehdr', get_string('packagehdr', 'svasu'));
        $mform->setExpanded('packagehdr', true);

        // Scorm types.
        $svasutypes = array(SVASU_TYPE_LOCAL => get_string('typelocal', 'svasu'));

        if ($cfgsvasu->allowtypeexternal) {
            $svasutypes[SVASU_TYPE_EXTERNAL] = get_string('typeexternal', 'svasu');
        }

        if ($cfgsvasu->allowtypelocalsync) {
            $svasutypes[SVASU_TYPE_LOCALSYNC] = get_string('typelocalsync', 'svasu');
        }

        if ($cfgsvasu->allowtypeexternalaicc) {
            $svasutypes[SVASU_TYPE_AICCURL] = get_string('typeaiccurl', 'svasu');
        }

        // Reference.
        if (count($svasutypes) > 1) {
            $mform->addElement('select', 'svasutype', get_string('svasutype', 'svasu'), $svasutypes);
            $mform->setType('svasutype', PARAM_ALPHA);
            $mform->addHelpButton('svasutype', 'svasutype', 'svasu');
            $mform->addElement('text', 'packageurl', get_string('packageurl', 'svasu'), array('size' => 60));
            $mform->setType('packageurl', PARAM_RAW);
            $mform->addHelpButton('packageurl', 'packageurl', 'svasu');
            $mform->hideIf('packageurl', 'svasutype', 'eq', SVASU_TYPE_LOCAL);
        } else {
            $mform->addElement('hidden', 'svasutype', SVASU_TYPE_LOCAL);
            $mform->setType('svasutype', PARAM_ALPHA);
        }

        // New local package upload.
        $filemanageroptions = array();
        $filemanageroptions['accepted_types'] = array('.zip', '.xml');
        $filemanageroptions['maxbytes'] = 0;
        $filemanageroptions['maxfiles'] = 1;
        $filemanageroptions['subdirs'] = 0;

        $mform->addElement('filemanager', 'packagefile', get_string('package', 'svasu'), null, $filemanageroptions);
        $mform->addHelpButton('packagefile', 'package', 'svasu');
        $mform->hideIf('packagefile', 'svasutype', 'noteq', SVASU_TYPE_LOCAL);

        // Update packages timing.
        $mform->addElement('select', 'updatefreq', get_string('updatefreq', 'svasu'), svasu_get_updatefreq_array());
        $mform->setType('updatefreq', PARAM_INT);
        $mform->setDefault('updatefreq', $cfgsvasu->updatefreq);
        $mform->addHelpButton('updatefreq', 'updatefreq', 'svasu');

        // Display Settings.
        $mform->addElement('header', 'displaysettings', get_string('appearance'));

        // Framed / Popup Window.
        $mform->addElement('select', 'popup', get_string('display', 'svasu'), svasu_get_popup_display_array());
        $mform->setDefault('popup', $cfgsvasu->popup);
        $mform->setAdvanced('popup', $cfgsvasu->popup_adv);

        // Width.
        $mform->addElement('text', 'width', get_string('width', 'svasu'), 'maxlength="5" size="5"');
        $mform->setDefault('width', $cfgsvasu->framewidth);
        $mform->setType('width', PARAM_INT);
        $mform->setAdvanced('width', $cfgsvasu->framewidth_adv);
        $mform->hideIf('width', 'popup', 'eq', 0);

        // Height.
        $mform->addElement('text', 'height', get_string('height', 'svasu'), 'maxlength="5" size="5"');
        $mform->setDefault('height', $cfgsvasu->frameheight);
        $mform->setType('height', PARAM_INT);
        $mform->setAdvanced('height', $cfgsvasu->frameheight_adv);
        $mform->hideIf('height', 'popup', 'eq', 0);

        // Window Options.
        $winoptgrp = array();
        foreach (svasu_get_popup_options_array() as $key => $value) {
            $winoptgrp[] = &$mform->createElement('checkbox', $key, '', get_string($key, 'svasu'));
            $mform->setDefault($key, $value);
        }
        $mform->addGroup($winoptgrp, 'winoptgrp', get_string('options', 'svasu'), '<br />', false);
        $mform->hideIf('winoptgrp', 'popup', 'eq', 0);
        $mform->setAdvanced('winoptgrp', $cfgsvasu->winoptgrp_adv);

        // Display activity name.
        $mform->addElement('advcheckbox', 'displayactivityname', get_string('displayactivityname', 'svasu'));
        $mform->addHelpButton('displayactivityname', 'displayactivityname', 'svasu');
        $mform->setDefault('displayactivityname', $cfgsvasu->displayactivityname);

        // Skip view page.
        $skipviewoptions = svasu_get_skip_view_array();
        $mform->addElement('select', 'skipview', get_string('skipview', 'svasu'), $skipviewoptions);
        $mform->addHelpButton('skipview', 'skipview', 'svasu');
        $mform->setDefault('skipview', $cfgsvasu->skipview);
        $mform->setAdvanced('skipview', $cfgsvasu->skipview_adv);

        // Hide Browse.
        $mform->addElement('selectyesno', 'hidebrowse', get_string('hidebrowse', 'svasu'));
        $mform->addHelpButton('hidebrowse', 'hidebrowse', 'svasu');
        $mform->setDefault('hidebrowse', $cfgsvasu->hidebrowse);
        $mform->setAdvanced('hidebrowse', $cfgsvasu->hidebrowse_adv);

        // Display course structure.
        $mform->addElement('selectyesno', 'displaycoursestructure', get_string('displaycoursestructure', 'svasu'));
        $mform->addHelpButton('displaycoursestructure', 'displaycoursestructure', 'svasu');
        $mform->setDefault('displaycoursestructure', $cfgsvasu->displaycoursestructure);
        $mform->setAdvanced('displaycoursestructure', $cfgsvasu->displaycoursestructure_adv);

        // Toc display.
        $mform->addElement('select', 'hidetoc', get_string('hidetoc', 'svasu'), svasu_get_hidetoc_array());
        $mform->addHelpButton('hidetoc', 'hidetoc', 'svasu');
        $mform->setDefault('hidetoc', $cfgsvasu->hidetoc);
        $mform->setAdvanced('hidetoc', $cfgsvasu->hidetoc_adv);
        $mform->disabledIf('hidetoc', 'svasutype', 'eq', SVASU_TYPE_AICCURL);

        // Navigation panel display.
        $mform->addElement('select', 'nav', get_string('nav', 'svasu'), svasu_get_navigation_display_array());
        $mform->addHelpButton('nav', 'nav', 'svasu');
        $mform->setDefault('nav', $cfgsvasu->nav);
        $mform->setAdvanced('nav', $cfgsvasu->nav_adv);
        $mform->hideIf('nav', 'hidetoc', 'noteq', SVASU_TOC_SIDE);

        // Navigation panel position from left.
        $mform->addElement('text', 'navpositionleft', get_string('fromleft', 'svasu'), 'maxlength="5" size="5"');
        $mform->setDefault('navpositionleft', $cfgsvasu->navpositionleft);
        $mform->setType('navpositionleft', PARAM_INT);
        $mform->setAdvanced('navpositionleft', $cfgsvasu->navpositionleft_adv);
        $mform->hideIf('navpositionleft', 'hidetoc', 'noteq', SVASU_TOC_SIDE);
        $mform->hideIf('navpositionleft', 'nav', 'noteq', SVASU_NAV_FLOATING);

        // Navigation panel position from top.
        $mform->addElement('text', 'navpositiontop', get_string('fromtop', 'svasu'), 'maxlength="5" size="5"');
        $mform->setDefault('navpositiontop', $cfgsvasu->navpositiontop);
        $mform->setType('navpositiontop', PARAM_INT);
        $mform->setAdvanced('navpositiontop', $cfgsvasu->navpositiontop_adv);
        $mform->hideIf('navpositiontop', 'hidetoc', 'noteq', SVASU_TOC_SIDE);
        $mform->hideIf('navpositiontop', 'nav', 'noteq', SVASU_NAV_FLOATING);

        // Display attempt status.
        $mform->addElement('select', 'displayattemptstatus', get_string('displayattemptstatus', 'svasu'),
                           svasu_get_attemptstatus_array());
        $mform->addHelpButton('displayattemptstatus', 'displayattemptstatus', 'svasu');
        $mform->setDefault('displayattemptstatus', $cfgsvasu->displayattemptstatus);
        $mform->setAdvanced('displayattemptstatus', $cfgsvasu->displayattemptstatus_adv);

        // Availability.
        $mform->addElement('header', 'availability', get_string('availability'));

        $mform->addElement('date_time_selector', 'timeopen', get_string("svasuopen", "svasu"), array('optional' => true));
        $mform->addElement('date_time_selector', 'timeclose', get_string("svasuclose", "svasu"), array('optional' => true));

        // Grade Settings.
        $mform->addElement('header', 'gradesettings', get_string('grade'));

        // Grade Method.
        $mform->addElement('select', 'grademethod', get_string('grademethod', 'svasu'), svasu_get_grade_method_array());
        $mform->addHelpButton('grademethod', 'grademethod', 'svasu');
        $mform->setDefault('grademethod', $cfgsvasu->grademethod);

        // Maximum Grade.
        for ($i = 0; $i <= 100; $i++) {
            $grades[$i] = "$i";
        }
        $mform->addElement('select', 'maxgrade', get_string('maximumgrade'), $grades);
        $mform->setDefault('maxgrade', $cfgsvasu->maxgrade);
        $mform->hideIf('maxgrade', 'grademethod', 'eq', GRADESCOES);

        // Attempts management.
        $mform->addElement('header', 'attemptsmanagementhdr', get_string('attemptsmanagement', 'svasu'));

        // Max Attempts.
        $mform->addElement('select', 'maxattempt', get_string('maximumattempts', 'svasu'), svasu_get_attempts_array());
        $mform->addHelpButton('maxattempt', 'maximumattempts', 'svasu');
        $mform->setDefault('maxattempt', $cfgsvasu->maxattempt);

        // What Grade.
        $mform->addElement('select', 'whatgrade', get_string('whatgrade', 'svasu'),  svasu_get_what_grade_array());
        $mform->hideIf('whatgrade', 'maxattempt', 'eq', 1);
        $mform->addHelpButton('whatgrade', 'whatgrade', 'svasu');
        $mform->setDefault('whatgrade', $cfgsvasu->whatgrade);

        // Force new attempt.
        $newattemptselect = svasu_get_forceattempt_array();
        $mform->addElement('select', 'forcenewattempt', get_string('forcenewattempts', 'svasu'), $newattemptselect);
        $mform->addHelpButton('forcenewattempt', 'forcenewattempts', 'svasu');
        $mform->setDefault('forcenewattempt', $cfgsvasu->forcenewattempt);

        // Last attempt lock - lock the enter button after the last available attempt has been made.
        $mform->addElement('selectyesno', 'lastattemptlock', get_string('lastattemptlock', 'svasu'));
        $mform->addHelpButton('lastattemptlock', 'lastattemptlock', 'svasu');
        $mform->setDefault('lastattemptlock', $cfgsvasu->lastattemptlock);

        // Compatibility settings.
        $mform->addElement('header', 'compatibilitysettingshdr', get_string('compatibilitysettings', 'svasu'));

        // Force completed.
        $mform->addElement('selectyesno', 'forcecompleted', get_string('forcecompleted', 'svasu'));
        $mform->addHelpButton('forcecompleted', 'forcecompleted', 'svasu');
        $mform->setDefault('forcecompleted', $cfgsvasu->forcecompleted);

        // Autocontinue.
        $mform->addElement('selectyesno', 'auto', get_string('autocontinue', 'svasu'));
        $mform->addHelpButton('auto', 'autocontinue', 'svasu');
        $mform->setDefault('auto', $cfgsvasu->auto);

        // Autocommit.
        $mform->addElement('selectyesno', 'autocommit', get_string('autocommit', 'svasu'));
        $mform->addHelpButton('autocommit', 'autocommit', 'svasu');
        $mform->setDefault('autocommit', $cfgsvasu->autocommit);

        // Mastery score overrides status.
        $mform->addElement('selectyesno', 'masteryoverride', get_string('masteryoverride', 'svasu'));
        $mform->addHelpButton('masteryoverride', 'masteryoverride', 'svasu');
        $mform->setDefault('masteryoverride', $cfgsvasu->masteryoverride);

        // Hidden Settings.
        $mform->addElement('hidden', 'datadir', null);
        $mform->setType('datadir', PARAM_RAW);
        $mform->addElement('hidden', 'pkgtype', null);
        $mform->setType('pkgtype', PARAM_RAW);
        $mform->addElement('hidden', 'launch', null);
        $mform->setType('launch', PARAM_RAW);
        $mform->addElement('hidden', 'redirect', null);
        $mform->setType('redirect', PARAM_RAW);
        $mform->addElement('hidden', 'redirecturl', null);
        $mform->setType('redirecturl', PARAM_RAW);

        $this->standard_coursemodule_elements();

        // Buttons.
        $this->add_action_buttons();
    }

    public function data_preprocessing(&$defaultvalues) {
        global $COURSE;

        if (isset($defaultvalues['popup']) && ($defaultvalues['popup'] == 1) && isset($defaultvalues['options'])) {
            if (!empty($defaultvalues['options'])) {
                $options = explode(',', $defaultvalues['options']);
                foreach ($options as $option) {
                    list($element, $value) = explode('=', $option);
                    $element = trim($element);
                    $defaultvalues[$element] = trim($value);
                }
            }
        }
        if (isset($defaultvalues['grademethod'])) {
            $defaultvalues['grademethod'] = intval($defaultvalues['grademethod']);
        }
        if (isset($defaultvalues['width']) && (strpos($defaultvalues['width'], '%') === false)
                                           && ($defaultvalues['width'] <= 100)) {
            $defaultvalues['width'] .= '%';
        }
        if (isset($defaultvalues['height']) && (strpos($defaultvalues['height'], '%') === false)
                                           && ($defaultvalues['height'] <= 100)) {
            $defaultvalues['height'] .= '%';
        }
        $svasus = get_all_instances_in_course('svasu', $COURSE);
        $coursesvasu = current($svasus);

        $draftitemid = file_get_submitted_draft_itemid('packagefile');
        file_prepare_draft_area($draftitemid, $this->context->id, 'mod_svasu', 'package', 0,
            array('subdirs' => 0, 'maxfiles' => 1));
        $defaultvalues['packagefile'] = $draftitemid;

        if (($COURSE->format == 'singleactivity') && ((count($svasus) == 0) || ($defaultvalues['instance'] == $coursesvasu->id))) {
            $defaultvalues['redirect'] = 'yes';
            $defaultvalues['redirecturl'] = '../course/view.php?id='.$defaultvalues['course'];
        } else {
            $defaultvalues['redirect'] = 'no';
            $defaultvalues['redirecturl'] = '../mod/svasu/view.php?id='.$defaultvalues['coursemodule'];
        }
        if (isset($defaultvalues['version'])) {
            $defaultvalues['pkgtype'] = (substr($defaultvalues['version'], 0, 5) == 'SVASU') ? 'svasu' : 'aicc';
        }
        if (isset($defaultvalues['instance'])) {
            $defaultvalues['datadir'] = $defaultvalues['instance'];
        }
        if (empty($defaultvalues['timeopen'])) {
            $defaultvalues['timeopen'] = 0;
        }
        if (empty($defaultvalues['timeclose'])) {
            $defaultvalues['timeclose'] = 0;
        }

        // Set some completion default data.
        $cvalues = array();
        if (empty($this->_instance)) {
            // When in add mode, set a default completion rule that requires the SVASU's status be set to "Completed".
            $cvalues[4] = 1;
        } else if (!empty($defaultvalues['completionstatusrequired']) && !is_array($defaultvalues['completionstatusrequired'])) {
            // Unpack values.
            foreach (svasu_status_options() as $key => $value) {
                if (($defaultvalues['completionstatusrequired'] & $key) == $key) {
                    $cvalues[$key] = 1;
                }
            }
        }
        if (!empty($cvalues)) {
            $defaultvalues['completionstatusrequired'] = $cvalues;
        }

        if (!isset($defaultvalues['completionscorerequired']) || !strlen($defaultvalues['completionscorerequired'])) {
            $defaultvalues['completionscoredisabled'] = 1;
        }
    }

    public function validation($data, $files) {
        global $CFG, $USER;
        $errors = parent::validation($data, $files);

        $type = $data['svasutype'];

        if ($type === SVASU_TYPE_LOCAL) {
            if (empty($data['packagefile'])) {
                $errors['packagefile'] = get_string('required');

            } else {
                $draftitemid = file_get_submitted_draft_itemid('packagefile');

                file_prepare_draft_area($draftitemid, $this->context->id, 'mod_svasu', 'packagefilecheck', null,
                    array('subdirs' => 0, 'maxfiles' => 1));

                // Get file from users draft area.
                $usercontext = context_user::instance($USER->id);
                $fs = get_file_storage();
                $files = $fs->get_area_files($usercontext->id, 'user', 'draft', $draftitemid, 'id', false);

                if (count($files) < 1) {
                    $errors['packagefile'] = get_string('required');
                    return $errors;
                }
                $file = reset($files);
                if (!$file->is_external_file() && !empty($data['updatefreq'])) {
                    // Make sure updatefreq is not set if using normal local file.
                    $errors['updatefreq'] = get_string('updatefreq_error', 'mod_svasu');
                }
                if (strtolower($file->get_filename()) == 'imsmanifest.xml') {
                    if (!$file->is_external_file()) {
                        $errors['packagefile'] = get_string('aliasonly', 'mod_svasu');
                    } else {
                        $repository = repository::get_repository_by_id($file->get_repository_id(), context_system::instance());
                        if (!$repository->supports_relative_file()) {
                            $errors['packagefile'] = get_string('repositorynotsupported', 'mod_svasu');
                        }
                    }
                } else if (strtolower(substr($file->get_filename(), -3)) == 'xml') {
                    $errors['packagefile'] = get_string('invalidmanifestname', 'mod_svasu');
                } else {
                    // Validate this SVASU package.
                    $errors = array_merge($errors, svasu_validate_package($file));
                }
            }

        } else if ($type === SVASU_TYPE_EXTERNAL) {
            $reference = $data['packageurl'];
            // Syntax check.
            if (!preg_match('/(http:\/\/|https:\/\/|www).*\/imsmanifest.xml$/i', $reference)) {
                $errors['packageurl'] = get_string('invalidurl', 'svasu');
            } else {
                // Availability check.
                $result = svasu_check_url($reference);
                if (is_string($result)) {
                    $errors['packageurl'] = $result;
                }
            }

        } else if ($type === 'packageurl') {
            $reference = $data['reference'];
            // Syntax check.
            if (!preg_match('/(http:\/\/|https:\/\/|www).*(\.zip|\.pif)$/i', $reference)) {
                $errors['packageurl'] = get_string('invalidurl', 'svasu');
            } else {
                // Availability check.
                $result = svasu_check_url($reference);
                if (is_string($result)) {
                    $errors['packageurl'] = $result;
                }
            }

        } else if ($type === SVASU_TYPE_AICCURL) {
            $reference = $data['packageurl'];
            // Syntax check.
            if (!preg_match('/(http:\/\/|https:\/\/|www).*/', $reference)) {
                $errors['packageurl'] = get_string('invalidurl', 'svasu');
            } else {
                // Availability check.
                $result = svasu_check_url($reference);
                if (is_string($result)) {
                    $errors['packageurl'] = $result;
                }
            }

        }

        // Validate availability dates.
        if ($data['timeopen'] && $data['timeclose']) {
            if ($data['timeopen'] > $data['timeclose']) {
                $errors['timeclose'] = get_string('closebeforeopen', 'svasu');
            }
        }
        if (!empty($data['completionstatusallscos'])) {
            $requirestatus = false;
            foreach (svasu_status_options(true) as $key => $value) {
                if (!empty($data['completionstatusrequired'][$key])) {
                    $requirestatus = true;
                }
            }
            if (!$requirestatus) {
                $errors['completionstatusallscos'] = get_string('youmustselectastatus', 'svasu');
            }
        }

        return $errors;
    }

    // Need to translate the "options" and "reference" field.
    public function set_data($defaultvalues) {
        $defaultvalues = (array)$defaultvalues;

        if (isset($defaultvalues['svasutype']) and isset($defaultvalues['reference'])) {
            switch ($defaultvalues['svasutype']) {
                case SVASU_TYPE_LOCALSYNC :
                case SVASU_TYPE_EXTERNAL:
                case SVASU_TYPE_AICCURL:
                    $defaultvalues['packageurl'] = $defaultvalues['reference'];
            }
        }
        unset($defaultvalues['reference']);

        if (!empty($defaultvalues['options'])) {
            $options = explode(',', $defaultvalues['options']);
            foreach ($options as $option) {
                $opt = explode('=', $option);
                if (isset($opt[1])) {
                    $defaultvalues[$opt[0]] = $opt[1];
                }
            }
        }

        parent::set_data($defaultvalues);
    }

    public function add_completion_rules() {
        $mform =& $this->_form;
        $items = array();

        // Require score.
        $group = array();
        $group[] =& $mform->createElement('text', 'completionscorerequired', '', array('size' => 5));
        $group[] =& $mform->createElement('checkbox', 'completionscoredisabled', null, get_string('disable'));
        $mform->setType('completionscorerequired', PARAM_INT);
        $mform->addGroup($group, 'completionscoregroup', get_string('completionscorerequired', 'svasu'), '', false);
        $mform->addHelpButton('completionscoregroup', 'completionscorerequired', 'svasu');
        $mform->disabledIf('completionscorerequired', 'completionscoredisabled', 'checked');
        $mform->setDefault('completionscorerequired', 0);

        $items[] = 'completionscoregroup';

        // Require status.
        $first = true;
        $firstkey = null;
        foreach (svasu_status_options(true) as $key => $value) {
            $name = null;
            $key = 'completionstatusrequired['.$key.']';
            if ($first) {
                $name = get_string('completionstatusrequired', 'svasu');
                $first = false;
                $firstkey = $key;
            }
            $mform->addElement('checkbox', $key, $name, $value);
            $mform->setType($key, PARAM_BOOL);
            $items[] = $key;
        }
        $mform->addHelpButton($firstkey, 'completionstatusrequired', 'svasu');

        $mform->addElement('checkbox', 'completionstatusallscos', get_string('completionstatusallscos', 'svasu'));
        $mform->setType('completionstatusallscos', PARAM_BOOL);
        $mform->addHelpButton('completionstatusallscos', 'completionstatusallscos', 'svasu');
        $mform->setDefault('completionstatusallscos', 0);
        $items[] = 'completionstatusallscos';

        return $items;
    }

    public function completion_rule_enabled($data) {
        $status = !empty($data['completionstatusrequired']);
        $score = empty($data['completionscoredisabled']) && strlen($data['completionscorerequired']);

        return $status || $score;
    }

    /**
     * Allows module to modify the data returned by form get_data().
     * This method is also called in the bulk activity completion form.
     *
     * Only available on moodleform_mod.
     *
     * @param stdClass $data the form data to be modified.
     */
    public function data_postprocessing($data) {
        parent::data_postprocessing($data);
        // Convert completionstatusrequired to a proper integer, if any.
        $total = 0;
        if (isset($data->completionstatusrequired) && is_array($data->completionstatusrequired)) {
            foreach (array_keys($data->completionstatusrequired) as $state) {
                $total |= $state;
            }
            $data->completionstatusrequired = $total;
        }

        if (!empty($data->completionunlocked)) {
            // Turn off completion settings if the checkboxes aren't ticked.
            $autocompletion = isset($data->completion) && $data->completion == COMPLETION_TRACKING_AUTOMATIC;

            if (!(isset($data->completionstatusrequired) && $autocompletion)) {
                $data->completionstatusrequired = null;
            }
            // Else do nothing: completionstatusrequired has been already converted
            //             into a correct integer representation.

            if (!empty($data->completionscoredisabled) || !$autocompletion) {
                $data->completionscorerequired = null;
            }
        }
    }
}
