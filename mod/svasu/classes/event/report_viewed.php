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
 * The mod_svasu report viewed event.
 *
 * @package    mod_svasu
 * @copyright  2013 onwards Ankit Agarwal
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_svasu\event;
defined('MOODLE_INTERNAL') || die();

/**
 * The mod_svasu report viewed event class.
 *
 * @property-read array $other {
 *      Extra information about event properties.
 *
 *      - int svasuid: The ID of the svasu.
 *      - string mode: Mode of the report viewed.
 * }
 *
 * @package    mod_svasu
 * @since      Moodle 2.7
 * @copyright  2013 onwards Ankit Agarwal
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class report_viewed extends \core\event\base {

    /**
     * Init method.
     */
    protected function init() {
        $this->data['crud'] = 'r';
        $this->data['edulevel'] = self::LEVEL_TEACHING;
    }

    /**
     * Returns non-localised description of what happened.
     *
     * @return string
     */
    public function get_description() {
        return "The user with id '$this->userid' viewed the svasu report '{$this->other['mode']}' for the svasu with " .
            "course module id '$this->contextinstanceid'.";
    }

    /**
     * Returns localised general event name.
     *
     * @return string
     */
    public static function get_name() {
        return get_string('eventreportviewed', 'mod_svasu');
    }

    /**
     * Get URL related to the action
     *
     * @return \moodle_url
     */
    public function get_url() {
        return new \moodle_url('/mod/svasu/report.php', array('id' => $this->contextinstanceid, 'mode' => $this->other['mode']));
    }

    /**
     * Replace add_to_log() statement.
     *
     * @return array of parameters to be passed to legacy add_to_log() function.
     */
    protected function get_legacy_logdata() {
        return array($this->courseid, 'svasu', 'report', 'report.php?id=' . $this->contextinstanceid .
                '&mode=' . $this->other['mode'], $this->other['svasuid'], $this->contextinstanceid);
    }

    /**
     * Custom validation.
     *
     * @throws \coding_exception
     * @return void
     */
    protected function validate_data() {
        parent::validate_data();

        if (empty($this->other['svasuid'])) {
            throw new \coding_exception('The \'svasuid\' value must be set in other.');
        }

        if (empty($this->other['mode'])) {
            throw new \coding_exception('The \'mode\' value must be set in other.');
        }
    }

    public static function get_other_mapping() {
        $othermapped = array();
        $othermapped['svasuid'] = array('db' => 'svasu', 'restore' => 'svasu');

        return $othermapped;
    }
}
