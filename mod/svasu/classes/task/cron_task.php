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
 * A scheduled task for svasu cron.
 *
 * @package    mod_svasu
 * @copyright  2017 Abhishek kumar <ganitgenius@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace mod_svasu\task;
defined('MOODLE_INTERNAL') || die();

/**
 * A cron_task class to be used by Tasks API.
 *
 * @copyright  2017 Abhishek kumar <ganitgenius@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class cron_task extends \core\task\scheduled_task {

    /**
     * Get a descriptive name for this task (shown to admins).
     *
     * @return string
     */
    public function get_name() {
        return get_string('crontask', 'mod_svasu');
    }

    /**
     * Run svasu cron.
     */
    public function execute() {
        global $CFG;
        require_once($CFG->dirroot . '/mod/svasu/lib.php');
        svasu_cron_scheduled_task();
    }

}
