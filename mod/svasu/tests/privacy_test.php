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
 * Base class for unit tests for mod_svasu.
 *
 * @package    mod_svasu
 * @category   test
 * @copyright  2018 Sara Arjona <sara@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

use mod_svasu\privacy\provider;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\writer;
use core_privacy\tests\provider_testcase;

/**
 * Unit tests for mod\svasu\classes\privacy\provider.php
 *
 * @copyright  2018 Sara Arjona <sara@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_svasu_testcase extends provider_testcase {

    /** @var stdClass User without any AICC/SVASU attempt. */
    protected $student0;

    /** @var stdClass User with some AICC/SVASU attempt. */
    protected $student1;

    /** @var stdClass User with some AICC/SVASU attempt. */
    protected $student2;

    /** @var context context_module of the SVASU activity. */
    protected $context;

    /**
     * Test getting the context for the user ID related to this plugin.
     */
    public function test_get_contexts_for_userid() {
        $this->resetAfterTest(true);
        $this->setAdminUser();
        $this->svasu_setup_test_scenario_data();

        // The student0 hasn't any attempt.
        $contextlist = provider::get_contexts_for_userid($this->student0->id);
        $this->assertCount(0, (array) $contextlist->get_contextids());

        // The student1 has data in the SVASU context.
        $contextlist = provider::get_contexts_for_userid($this->student1->id);
        $this->assertCount(1, (array) $contextlist->get_contextids());
        $this->assertContains($this->context->id, $contextlist->get_contextids());
    }

    /**
     * Test getting the user IDs for the context related to this plugin.
     */
    public function test_get_users_in_context() {
        $this->resetAfterTest(true);
        $this->setAdminUser();
        $this->svasu_setup_test_scenario_data();
        $component = 'mod_svasu';

        $userlist = new \core_privacy\local\request\userlist($this->context, $component);
        provider::get_users_in_context($userlist);

        // Students 1 and 2 have attempts in the SVASU context, student 0 does not.
        $this->assertCount(2, $userlist);

        $expected = [$this->student1->id, $this->student2->id];
        $actual = $userlist->get_userids();
        sort($expected);
        sort($actual);
        $this->assertEquals($expected, $actual);
    }

    /**
     * Test that data is exported correctly for this plugin.
     */
    public function test_export_user_data() {
        $this->resetAfterTest(true);
        $this->setAdminUser();
        $this->svasu_setup_test_scenario_data();

        // Validate exported data for student0 (without any AICC/SVASU attempt).
        $this->setUser($this->student0);
        $writer = writer::with_context($this->context);

        $this->export_context_data_for_user($this->student0->id, $this->context, 'mod_svasu');
        $subcontextattempt1 = [
            get_string('myattempts', 'svasu'),
            get_string('attempt', 'svasu'). " 1"
        ];
        $subcontextaicc = [
            get_string('myaiccsessions', 'svasu')
        ];
        $data = $writer->get_data($subcontextattempt1);
        $this->assertEmpty($data);
        $data = $writer->get_data($subcontextaicc);
        $this->assertEmpty($data);

        // Validate exported data for student1.
        writer::reset();
        $this->setUser($this->student1);
        $writer = writer::with_context($this->context);
        $this->assertFalse($writer->has_any_data());
        $this->export_context_data_for_user($this->student1->id, $this->context, 'mod_svasu');

        $data = $writer->get_data([]);
        $this->assertEquals('SVASU1', $data->name);

        $data = $writer->get_data($subcontextattempt1);
        $this->assertCount(1, (array) $data);
        $this->assertCount(2, (array) reset($data));
        $subcontextattempt2 = [
            get_string('myattempts', 'svasu'),
            get_string('attempt', 'svasu'). " 2"
        ];
        $data = $writer->get_data($subcontextattempt2);
        $this->assertCount(2, (array) reset($data));
        // The student1 has only 2 scoes_track attempts.
        $subcontextattempt3 = [
            get_string('myattempts', 'svasu'),
            get_string('attempt', 'svasu'). " 3"
        ];
        $data = $writer->get_data($subcontextattempt3);
        $this->assertEmpty($data);
        // The student1 has only 1 aicc_session.
        $data = $writer->get_data($subcontextaicc);
        $this->assertCount(1, (array) $data);
    }

    /**
     * Test for provider::delete_data_for_all_users_in_context().
     */
    public function test_delete_data_for_all_users_in_context() {
        global $DB;

        $this->resetAfterTest(true);
        $this->setAdminUser();
        $this->svasu_setup_test_scenario_data();

        // Before deletion, we should have 8 entries in the svasu_scoes_track table.
        $count = $DB->count_records('svasu_scoes_track');
        $this->assertEquals(8, $count);
        // Before deletion, we should have 4 entries in the svasu_aicc_session table.
        $count = $DB->count_records('svasu_aicc_session');
        $this->assertEquals(4, $count);

        // Delete data based on the context.
        provider::delete_data_for_all_users_in_context($this->context);

        // After deletion, the svasu_scoes_track entries should have been deleted.
        $count = $DB->count_records('svasu_scoes_track');
        $this->assertEquals(0, $count);
        // After deletion, the svasu_aicc_session entries should have been deleted.
        $count = $DB->count_records('svasu_aicc_session');
        $this->assertEquals(0, $count);
    }

    /**
     * Test for provider::delete_data_for_user().
     */
    public function test_delete_data_for_user() {
        global $DB;

        $this->resetAfterTest(true);
        $this->setAdminUser();
        $this->svasu_setup_test_scenario_data();

        // Before deletion, we should have 8 entries in the svasu_scoes_track table.
        $count = $DB->count_records('svasu_scoes_track');
        $this->assertEquals(8, $count);
        // Before deletion, we should have 4 entries in the svasu_aicc_session table.
        $count = $DB->count_records('svasu_aicc_session');
        $this->assertEquals(4, $count);

        $approvedcontextlist = new approved_contextlist($this->student1, 'svasu', [$this->context->id]);
        provider::delete_data_for_user($approvedcontextlist);

        // After deletion, the svasu_scoes_track entries for the first student should have been deleted.
        $count = $DB->count_records('svasu_scoes_track', ['userid' => $this->student1->id]);
        $this->assertEquals(0, $count);
        $count = $DB->count_records('svasu_scoes_track');
        $this->assertEquals(4, $count);
        // After deletion, the svasu_aicc_session entries for the first student should have been deleted.
        $count = $DB->count_records('svasu_aicc_session', ['userid' => $this->student1->id]);
        $this->assertEquals(0, $count);
        $count = $DB->count_records('svasu_aicc_session');
        $this->assertEquals(2, $count);

        // Confirm that the SVASU hasn't been removed.
        $svasucount = $DB->get_records('svasu');
        $this->assertCount(1, (array) $svasucount);

        // Delete scoes_track for student0 (nothing has to be removed).
        $approvedcontextlist = new approved_contextlist($this->student0, 'svasu', [$this->context->id]);
        provider::delete_data_for_user($approvedcontextlist);
        $count = $DB->count_records('svasu_scoes_track');
        $this->assertEquals(4, $count);
        $count = $DB->count_records('svasu_aicc_session');
        $this->assertEquals(2, $count);
    }

    /**
     * Test for provider::delete_data_for_users().
     */
    public function test_delete_data_for_users() {
        global $DB;
        $component = 'mod_svasu';

        $this->resetAfterTest(true);
        $this->setAdminUser();
        $this->svasu_setup_test_scenario_data();

        // Before deletion, we should have 8 entries in the svasu_scoes_track table.
        $count = $DB->count_records('svasu_scoes_track');
        $this->assertEquals(8, $count);
        // Before deletion, we should have 4 entries in the svasu_aicc_session table.
        $count = $DB->count_records('svasu_aicc_session');
        $this->assertEquals(4, $count);

        // Delete only student 1's data, retain student 2's data.
        $approveduserids = [$this->student1->id];
        $approvedlist = new approved_userlist($this->context, $component, $approveduserids);
        provider::delete_data_for_users($approvedlist);

        // After deletion, the svasu_scoes_track entries for the first student should have been deleted.
        $count = $DB->count_records('svasu_scoes_track', ['userid' => $this->student1->id]);
        $this->assertEquals(0, $count);
        $count = $DB->count_records('svasu_scoes_track');
        $this->assertEquals(4, $count);

        // After deletion, the svasu_aicc_session entries for the first student should have been deleted.
        $count = $DB->count_records('svasu_aicc_session', ['userid' => $this->student1->id]);
        $this->assertEquals(0, $count);
        $count = $DB->count_records('svasu_aicc_session');
        $this->assertEquals(2, $count);

        // Confirm that the SVASU hasn't been removed.
        $svasucount = $DB->get_records('svasu');
        $this->assertCount(1, (array) $svasucount);

        // Delete scoes_track for student0 (nothing has to be removed).
        $approveduserids = [$this->student0->id];
        $approvedlist = new approved_userlist($this->context, $component, $approveduserids);
        provider::delete_data_for_users($approvedlist);

        $count = $DB->count_records('svasu_scoes_track');
        $this->assertEquals(4, $count);
        $count = $DB->count_records('svasu_aicc_session');
        $this->assertEquals(2, $count);
    }

    /**
     * Helper function to setup 3 users and 2 SVASU attempts for student1 and student2.
     * $this->student0 is always created without any attempt.
     */
    protected function svasu_setup_test_scenario_data() {
        global $DB;

        set_config('allowaicchacp', 1, 'svasu');

        // Setup test data.
        $course = $this->getDataGenerator()->create_course();
        $params = array('course' => $course->id, 'name' => 'SVASU1');
        $svasu = $this->getDataGenerator()->create_module('svasu', $params);
        $this->context = \context_module::instance($svasu->cmid);

        // Users enrolments.
        $studentrole = $DB->get_record('role', array('shortname' => 'student'));

        // Create student0 withot any SVASU attempt.
        $this->student0 = self::getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($this->student0->id, $course->id, $studentrole->id, 'manual');

        // Create student1 with 2 SVASU attempts and 1 AICC session.
        $this->student1 = self::getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($this->student1->id, $course->id, $studentrole->id, 'manual');
        static::svasu_insert_attempt($svasu, $this->student1->id, 1);
        static::svasu_insert_attempt($svasu, $this->student1->id, 2);

        // Create student2 with 2 SVASU attempts and 1 AICC session.
        $this->student2 = self::getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($this->student2->id, $course->id, $studentrole->id, 'manual');
        static::svasu_insert_attempt($svasu, $this->student2->id, 1);
        static::svasu_insert_attempt($svasu, $this->student2->id, 2);
    }

    /**
     * Create a SVASU attempt.
     *
     * @param  object $svasu SVASU activity.
     * @param  int $userid  Userid who is doing the attempt.
     * @param  int $attempt Number of attempt.
     */
    protected function svasu_insert_attempt($svasu, $userid, $attempt) {
        global $DB;

        $newattempt = 'on';
        $mode = 'normal';
        svasu_check_mode($svasu, $newattempt, $attempt, $userid, $mode);
        $scoes = svasu_get_scoes($svasu->id);
        $sco = array_pop($scoes);
        svasu_insert_track($userid, $svasu->id, $sco->id, $attempt, 'cmi.core.lesson_status', 'completed');
        svasu_insert_track($userid, $svasu->id, $sco->id, $attempt, 'cmi.score.min', '0');
        $now = time();
        $hacpsession = [
            'svasuid' => $svasu->id,
            'attempt' => $attempt,
            'hacpsession' => random_string(20),
            'userid' => $userid,
            'timecreated' => $now,
            'timemodified' => $now
        ];
        $DB->insert_record('svasu_aicc_session', $hacpsession);
    }
}
