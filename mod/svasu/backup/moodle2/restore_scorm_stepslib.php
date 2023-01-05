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
 * @package    mod_svasu
 * @subpackage backup-moodle2
 * @copyright  2010 onwards Eloy Lafuente (stronk7) {@link http://stronk7.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Define all the restore steps that will be used by the restore_svasu_activity_task
 */

/**
 * Structure step to restore one svasu activity
 */
class restore_svasu_activity_structure_step extends restore_activity_structure_step {

    protected function define_structure() {

        $paths = array();
        $userinfo = $this->get_setting_value('userinfo');

        $paths[] = new restore_path_element('svasu', '/activity/svasu');
        $paths[] = new restore_path_element('svasu_sco', '/activity/svasu/scoes/sco');
        $paths[] = new restore_path_element('svasu_sco_data', '/activity/svasu/scoes/sco/sco_datas/sco_data');
        $paths[] = new restore_path_element('svasu_seq_objective', '/activity/svasu/scoes/sco/seq_objectives/seq_objective');
        $paths[] = new restore_path_element('svasu_seq_rolluprule', '/activity/svasu/scoes/sco/seq_rolluprules/seq_rolluprule');
        $paths[] = new restore_path_element('svasu_seq_rolluprulecond', '/activity/svasu/scoes/sco/seq_rollupruleconds/seq_rolluprulecond');
        $paths[] = new restore_path_element('svasu_seq_rulecond', '/activity/svasu/scoes/sco/seq_ruleconds/seq_rulecond');
        $paths[] = new restore_path_element('svasu_seq_rulecond_data', '/activity/svasu/scoes/sco/seq_rulecond_datas/seq_rulecond_data');

        $paths[] = new restore_path_element('svasu_seq_mapinfo', '/activity/svasu/scoes/sco/seq_objectives/seq_objective/seq_mapinfos/seq_mapinfo');
        if ($userinfo) {
            $paths[] = new restore_path_element('svasu_sco_track', '/activity/svasu/scoes/sco/sco_tracks/sco_track');
        }

        // Return the paths wrapped into standard activity structure
        return $this->prepare_activity_structure($paths);
    }

    protected function process_svasu($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;

        $data->course = $this->get_courseid();

        // Any changes to the list of dates that needs to be rolled should be same during course restore and course reset.
        // See MDL-9367.
        $data->timeopen = $this->apply_date_offset($data->timeopen);
        $data->timeclose = $this->apply_date_offset($data->timeclose);

        if (!isset($data->displayactivityname)) {
            $data->displayactivityname = true;
        }
        if (!isset($data->completionstatusallscos)) {
            $data->completionstatusallscos = false;
        }
        // insert the svasu record
        $newitemid = $DB->insert_record('svasu', $data);
        // immediately after inserting "activity" record, call this
        $this->apply_activity_instance($newitemid);
    }

    protected function process_svasu_sco($data) {
        global $DB;

        $data = (object)$data;

        $oldid = $data->id;
        $data->svasu = $this->get_new_parentid('svasu');

        $newitemid = $DB->insert_record('svasu_scoes', $data);
        $this->set_mapping('svasu_sco', $oldid, $newitemid);
    }

    protected function process_svasu_sco_data($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;
        $data->scoid = $this->get_new_parentid('svasu_sco');

        $newitemid = $DB->insert_record('svasu_scoes_data', $data);
        // No need to save this mapping as far as nothing depend on it
        // (child paths, file areas nor links decoder)
    }

    protected function process_svasu_seq_objective($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;
        $data->scoid = $this->get_new_parentid('svasu_sco');

        $newitemid = $DB->insert_record('svasu_seq_objective', $data);
        $this->set_mapping('svasu_seq_objective', $oldid, $newitemid);
    }

    protected function process_svasu_seq_rolluprule($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;
        $data->scoid = $this->get_new_parentid('svasu_sco');

        $newitemid = $DB->insert_record('svasu_seq_rolluprule', $data);
        $this->set_mapping('svasu_seq_rolluprule', $oldid, $newitemid);
    }

    protected function process_svasu_seq_rolluprulecond($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;
        $data->scoid = $this->get_new_parentid('svasu_sco');
        $data->ruleconditions = $this->get_new_parentid('svasu_seq_rolluprule');

        $newitemid = $DB->insert_record('svasu_seq_rolluprulecond', $data);
        // No need to save this mapping as far as nothing depend on it
        // (child paths, file areas nor links decoder)
    }

    protected function process_svasu_seq_rulecond($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;
        $data->scoid = $this->get_new_parentid('svasu_sco');

        $newitemid = $DB->insert_record('svasu_seq_ruleconds', $data);
        $this->set_mapping('svasu_seq_ruleconds', $oldid, $newitemid);
    }

    protected function process_svasu_seq_rulecond_data($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;
        $data->scoid = $this->get_new_parentid('svasu_sco');
        $data->ruleconditions = $this->get_new_parentid('svasu_seq_ruleconds');

        $newitemid = $DB->insert_record('svasu_seq_rulecond', $data);
        // No need to save this mapping as far as nothing depend on it
        // (child paths, file areas nor links decoder)
    }



    protected function process_svasu_seq_mapinfo($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;
        $data->scoid = $this->get_new_parentid('svasu_sco');
        $data->objectiveid = $this->get_new_parentid('svasu_seq_objective');
        $newitemid = $DB->insert_record('svasu_scoes_data', $data);
        // No need to save this mapping as far as nothing depend on it
        // (child paths, file areas nor links decoder)
    }

    protected function process_svasu_sco_track($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;
        $data->svasuid = $this->get_new_parentid('svasu');
        $data->scoid = $this->get_new_parentid('svasu_sco');
        $data->userid = $this->get_mappingid('user', $data->userid);

        $newitemid = $DB->insert_record('svasu_scoes_track', $data);
        // No need to save this mapping as far as nothing depend on it
        // (child paths, file areas nor links decoder)
    }

    protected function after_execute() {
        global $DB;

        // Add svasu related files, no need to match by itemname (just internally handled context)
        $this->add_related_files('mod_svasu', 'intro', null);
        $this->add_related_files('mod_svasu', 'content', null);
        $this->add_related_files('mod_svasu', 'package', null);

        // Fix launch param in svasu table to use new sco id.
        $svasuid = $this->get_new_parentid('svasu');
        $svasu = $DB->get_record('svasu', array('id' => $svasuid));
        $svasu->launch = $this->get_mappingid('svasu_sco', $svasu->launch, '');

        if (!empty($svasu->launch)) {
            // Check that this sco has a valid launch value.
            $scolaunch = $DB->get_field('svasu_scoes', 'launch', array('id' => $svasu->launch));
            if (empty($scolaunch)) {
                // This is not a valid sco - set to empty so we can find a valid launch sco.
                $svasu->launch = '';
            }
        }

        if (empty($svasu->launch)) {
            // This svasu has an invalid launch param - we need to calculate it and get the first launchable sco.
            $sqlselect = 'svasu = ? AND '.$DB->sql_isnotempty('svasu_scoes', 'launch', false, true);
            // We use get_records here as we need to pass a limit in the query that works cross db.
            $scoes = $DB->get_records_select('svasu_scoes', $sqlselect, array($svasuid), 'sortorder', 'id', 0, 1);
            if (!empty($scoes)) {
                $sco = reset($scoes); // We only care about the first record - the above query only returns one.
                $svasu->launch = $sco->id;
            }
        }
        if (!empty($svasu->launch)) {
            $DB->update_record('svasu', $svasu);
        }
    }
}
