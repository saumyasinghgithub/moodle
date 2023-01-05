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
 * mod_svasu generator tests
 *
 * @package    mod_svasu
 * @category   test
 * @copyright  2013 Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Genarator tests class for mod_svasu.
 *
 * @package    mod_svasu
 * @category   test
 * @copyright  2013 Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_svasu_generator_testcase extends advanced_testcase {

    public function test_create_instance() {
        global $DB, $CFG, $USER;
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();

        $this->assertFalse($DB->record_exists('svasu', array('course' => $course->id)));
        $svasu = $this->getDataGenerator()->create_module('svasu', array('course' => $course));
        $records = $DB->get_records('svasu', array('course' => $course->id), 'id');
        $this->assertEquals(1, count($records));
        $this->assertTrue(array_key_exists($svasu->id, $records));

        $params = array('course' => $course->id, 'name' => 'Another svasu');
        $svasu = $this->getDataGenerator()->create_module('svasu', $params);
        $records = $DB->get_records('svasu', array('course' => $course->id), 'id');
        $this->assertEquals(2, count($records));
        $this->assertEquals('Another svasu', $records[$svasu->id]->name);

        // Examples of specifying the package file (do not validate anything, just check for exceptions).
        // 1. As path to the file in filesystem.
        $params = array(
            'course' => $course->id,
            'packagefilepath' => $CFG->dirroot.'/mod/svasu/tests/packages/singlescobasic.zip'
        );
        $svasu = $this->getDataGenerator()->create_module('svasu', $params);

        // 2. As file draft area id.
        $fs = get_file_storage();
        $params = array(
            'course' => $course->id,
            'packagefile' => file_get_unused_draft_itemid()
        );
        $usercontext = context_user::instance($USER->id);
        $filerecord = array('component' => 'user', 'filearea' => 'draft',
                'contextid' => $usercontext->id, 'itemid' => $params['packagefile'],
                'filename' => 'singlescobasic.zip', 'filepath' => '/');
        $fs->create_file_from_pathname($filerecord, $CFG->dirroot.'/mod/svasu/tests/packages/singlescobasic.zip');
        $svasu = $this->getDataGenerator()->create_module('svasu', $params);
    }
}
