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
 * SVASU module external functions tests
 *
 * @package    mod_svasu
 * @category   external
 * @copyright  2015 Juan Leyva <juan@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 3.0
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;

require_once($CFG->dirroot . '/webservice/tests/helpers.php');
require_once($CFG->dirroot . '/mod/svasu/lib.php');

/**
 * SVASU module external functions tests
 *
 * @package    mod_svasu
 * @category   external
 * @copyright  2015 Juan Leyva <juan@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 3.0
 */
class mod_svasu_external_testcase extends externallib_advanced_testcase {

    /**
     * Set up for every test
     */
    public function setUp() {
        global $DB, $CFG;
        $this->resetAfterTest();
        $this->setAdminUser();

        $CFG->enablecompletion = 1;
        // Setup test data.
        $this->course = $this->getDataGenerator()->create_course(array('enablecompletion' => 1));
        $this->svasu = $this->getDataGenerator()->create_module('svasu', array('course' => $this->course->id),
            array('completion' => 2, 'completionview' => 1));
        $this->context = context_module::instance($this->svasu->cmid);
        $this->cm = get_coursemodule_from_instance('svasu', $this->svasu->id);

        // Create users.
        $this->student = self::getDataGenerator()->create_user();
        $this->teacher = self::getDataGenerator()->create_user();

        // Users enrolments.
        $this->studentrole = $DB->get_record('role', array('shortname' => 'student'));
        $this->teacherrole = $DB->get_record('role', array('shortname' => 'editingteacher'));
        $this->getDataGenerator()->enrol_user($this->student->id, $this->course->id, $this->studentrole->id, 'manual');
        $this->getDataGenerator()->enrol_user($this->teacher->id, $this->course->id, $this->teacherrole->id, 'manual');
    }

    /**
     * Test view_svasu
     */
    public function test_view_svasu() {
        global $DB;

        // Test invalid instance id.
        try {
            mod_svasu_external::view_svasu(0);
            $this->fail('Exception expected due to invalid mod_svasu instance id.');
        } catch (moodle_exception $e) {
            $this->assertEquals('invalidrecord', $e->errorcode);
        }

        // Test not-enrolled user.
        $user = self::getDataGenerator()->create_user();
        $this->setUser($user);
        try {
            mod_svasu_external::view_svasu($this->svasu->id);
            $this->fail('Exception expected due to not enrolled user.');
        } catch (moodle_exception $e) {
            $this->assertEquals('requireloginerror', $e->errorcode);
        }

        // Test user with full capabilities.
        $this->studentrole = $DB->get_record('role', array('shortname' => 'student'));
        $this->getDataGenerator()->enrol_user($user->id, $this->course->id, $this->studentrole->id);

        // Trigger and capture the event.
        $sink = $this->redirectEvents();

        $result = mod_svasu_external::view_svasu($this->svasu->id);
        $result = external_api::clean_returnvalue(mod_svasu_external::view_svasu_returns(), $result);

        $events = $sink->get_events();
        $this->assertCount(1, $events);
        $event = array_shift($events);

        // Checking that the event contains the expected values.
        $this->assertInstanceOf('\mod_svasu\event\course_module_viewed', $event);
        $this->assertEquals($this->context, $event->get_context());
        $moodleurl = new \moodle_url('/mod/svasu/view.php', array('id' => $this->cm->id));
        $this->assertEquals($moodleurl, $event->get_url());
        $this->assertEventContextNotUsed($event);
        $this->assertNotEmpty($event->get_name());
    }

    /**
     * Test get svasu attempt count
     */
    public function test_mod_svasu_get_svasu_attempt_count_own_empty() {
        // Set to the student user.
        self::setUser($this->student);

        // Retrieve my attempts (should be 0).
        $result = mod_svasu_external::get_svasu_attempt_count($this->svasu->id, $this->student->id);
        $result = external_api::clean_returnvalue(mod_svasu_external::get_svasu_attempt_count_returns(), $result);
        $this->assertEquals(0, $result['attemptscount']);
    }

    public function test_mod_svasu_get_svasu_attempt_count_own_with_complete() {
        // Set to the student user.
        self::setUser($this->student);

        // Create attempts.
        $scoes = svasu_get_scoes($this->svasu->id);
        $sco = array_shift($scoes);
        svasu_insert_track($this->student->id, $this->svasu->id, $sco->id, 1, 'cmi.core.lesson_status', 'completed');
        svasu_insert_track($this->student->id, $this->svasu->id, $sco->id, 2, 'cmi.core.lesson_status', 'completed');

        $result = mod_svasu_external::get_svasu_attempt_count($this->svasu->id, $this->student->id);
        $result = external_api::clean_returnvalue(mod_svasu_external::get_svasu_attempt_count_returns(), $result);
        $this->assertEquals(2, $result['attemptscount']);
    }

    public function test_mod_svasu_get_svasu_attempt_count_own_incomplete() {
        // Set to the student user.
        self::setUser($this->student);

        // Create a complete attempt, and an incomplete attempt.
        $scoes = svasu_get_scoes($this->svasu->id);
        $sco = array_shift($scoes);
        svasu_insert_track($this->student->id, $this->svasu->id, $sco->id, 1, 'cmi.core.lesson_status', 'completed');
        svasu_insert_track($this->student->id, $this->svasu->id, $sco->id, 2, 'cmi.core.credit', '0');

        $result = mod_svasu_external::get_svasu_attempt_count($this->svasu->id, $this->student->id, true);
        $result = external_api::clean_returnvalue(mod_svasu_external::get_svasu_attempt_count_returns(), $result);
        $this->assertEquals(1, $result['attemptscount']);
    }

    public function test_mod_svasu_get_svasu_attempt_count_others_as_teacher() {
        // As a teacher.
        self::setUser($this->teacher);

        // Create a completed attempt for student.
        $scoes = svasu_get_scoes($this->svasu->id);
        $sco = array_shift($scoes);
        svasu_insert_track($this->student->id, $this->svasu->id, $sco->id, 1, 'cmi.core.lesson_status', 'completed');

        // I should be able to view the attempts for my students.
        $result = mod_svasu_external::get_svasu_attempt_count($this->svasu->id, $this->student->id);
        $result = external_api::clean_returnvalue(mod_svasu_external::get_svasu_attempt_count_returns(), $result);
        $this->assertEquals(1, $result['attemptscount']);
    }

    /**
     * @expectedException required_capability_exception
     */
    public function test_mod_svasu_get_svasu_attempt_count_others_as_student() {
        // Create a second student.
        $student2 = self::getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($student2->id, $this->course->id, $this->studentrole->id, 'manual');

        // As a student.
        self::setUser($student2);

        // I should not be able to view the attempts of another student.
        mod_svasu_external::get_svasu_attempt_count($this->svasu->id, $this->student->id);
    }

    /**
     * @expectedException moodle_exception
     */
    public function test_mod_svasu_get_svasu_attempt_count_invalid_instanceid() {
        // As student.
        self::setUser($this->student);

        // Test invalid instance id.
        mod_svasu_external::get_svasu_attempt_count(0, $this->student->id);
    }

    /**
     * @expectedException moodle_exception
     */
    public function test_mod_svasu_get_svasu_attempt_count_invalid_userid() {
        // As student.
        self::setUser($this->student);

        mod_svasu_external::get_svasu_attempt_count($this->svasu->id, -1);
    }

    /**
     * Test get svasu scoes
     */
    public function test_mod_svasu_get_svasu_scoes() {
        global $DB;

        $this->resetAfterTest(true);

        // Create users.
        $student = self::getDataGenerator()->create_user();
        $teacher = self::getDataGenerator()->create_user();

        // Create courses to add the modules.
        $course = self::getDataGenerator()->create_course();

        // First svasu, dates restriction.
        $record = new stdClass();
        $record->course = $course->id;
        $record->timeopen = time() + DAYSECS;
        $record->timeclose = $record->timeopen + DAYSECS;
        $svasu = self::getDataGenerator()->create_module('svasu', $record);

        // Set to the student user.
        self::setUser($student);

        // Users enrolments.
        $studentrole = $DB->get_record('role', array('shortname' => 'student'));
        $teacherrole = $DB->get_record('role', array('shortname' => 'editingteacher'));
        $this->getDataGenerator()->enrol_user($student->id, $course->id, $studentrole->id, 'manual');
        $this->getDataGenerator()->enrol_user($teacher->id, $course->id, $teacherrole->id, 'manual');

        // Retrieve my scoes, warning!.
        try {
             mod_svasu_external::get_svasu_scoes($svasu->id);
            $this->fail('Exception expected due to invalid dates.');
        } catch (moodle_exception $e) {
            $this->assertEquals('notopenyet', $e->errorcode);
        }

        $svasu->timeopen = time() - DAYSECS;
        $svasu->timeclose = time() - HOURSECS;
        $DB->update_record('svasu', $svasu);

        try {
             mod_svasu_external::get_svasu_scoes($svasu->id);
            $this->fail('Exception expected due to invalid dates.');
        } catch (moodle_exception $e) {
            $this->assertEquals('expired', $e->errorcode);
        }

        // Retrieve my scoes, user with permission.
        self::setUser($teacher);
        $result = mod_svasu_external::get_svasu_scoes($svasu->id);
        $result = external_api::clean_returnvalue(mod_svasu_external::get_svasu_scoes_returns(), $result);
        $this->assertCount(2, $result['scoes']);
        $this->assertCount(0, $result['warnings']);

        $scoes = svasu_get_scoes($svasu->id);
        $sco = array_shift($scoes);
        $sco->extradata = array();
        $this->assertEquals((array) $sco, $result['scoes'][0]);

        $sco = array_shift($scoes);
        $sco->extradata = array();
        $sco->extradata[] = array(
            'element' => 'isvisible',
            'value' => $sco->isvisible
        );
        $sco->extradata[] = array(
            'element' => 'parameters',
            'value' => $sco->parameters
        );
        unset($sco->isvisible);
        unset($sco->parameters);

        // Sort the array (if we don't sort tests will fails for Postgres).
        usort($result['scoes'][1]['extradata'], function($a, $b) {
            return strcmp($a['element'], $b['element']);
        });

        $this->assertEquals((array) $sco, $result['scoes'][1]);

        // Use organization.
        $organization = 'golf_sample_default_org';
        $result = mod_svasu_external::get_svasu_scoes($svasu->id, $organization);
        $result = external_api::clean_returnvalue(mod_svasu_external::get_svasu_scoes_returns(), $result);
        $this->assertCount(1, $result['scoes']);
        $this->assertEquals($organization, $result['scoes'][0]['organization']);
        $this->assertCount(0, $result['warnings']);

        // Test invalid instance id.
        try {
             mod_svasu_external::get_svasu_scoes(0);
            $this->fail('Exception expected due to invalid instance id.');
        } catch (moodle_exception $e) {
            $this->assertEquals('invalidrecord', $e->errorcode);
        }

    }

    /**
     * Test get svasu scoes (with a complex SVASU package)
     */
    public function test_mod_svasu_get_svasu_scoes_complex_package() {
        global $CFG;

        // As student.
        self::setUser($this->student);

        $record = new stdClass();
        $record->course = $this->course->id;
        $record->packagefilepath = $CFG->dirroot.'/mod/svasu/tests/packages/complexsvasu.zip';
        $svasu = self::getDataGenerator()->create_module('svasu', $record);

        $result = mod_svasu_external::get_svasu_scoes($svasu->id);
        $result = external_api::clean_returnvalue(mod_svasu_external::get_svasu_scoes_returns(), $result);
        $this->assertCount(9, $result['scoes']);
        $this->assertCount(0, $result['warnings']);

        $expectedscoes = array();
        $scoreturnstructure = mod_svasu_external::get_svasu_scoes_returns();
        $scoes = svasu_get_scoes($svasu->id);
        foreach ($scoes as $sco) {
            $sco->extradata = array();
            foreach ($sco as $element => $value) {
                // Add the extra data to the extradata array and remove the object element.
                if (!isset($scoreturnstructure->keys['scoes']->content->keys[$element])) {
                    $sco->extradata[] = array(
                        'element' => $element,
                        'value' => $value
                    );
                    unset($sco->{$element});
                }
            }
            $expectedscoes[] = (array) $sco;
        }

        $this->assertEquals($expectedscoes, $result['scoes']);
    }

    /*
     * Test get svasu user data
     */
    public function test_mod_svasu_get_svasu_user_data() {
        global $DB;

        $this->resetAfterTest(true);

        // Create users.
        $student1 = self::getDataGenerator()->create_user();
        $teacher = self::getDataGenerator()->create_user();

        // Set to the student user.
        self::setUser($student1);

        // Create courses to add the modules.
        $course = self::getDataGenerator()->create_course();

        // First svasu.
        $record = new stdClass();
        $record->course = $course->id;
        $svasu = self::getDataGenerator()->create_module('svasu', $record);

        // Users enrolments.
        $studentrole = $DB->get_record('role', array('shortname' => 'student'));
        $teacherrole = $DB->get_record('role', array('shortname' => 'teacher'));
        $this->getDataGenerator()->enrol_user($student1->id, $course->id, $studentrole->id, 'manual');
        $this->getDataGenerator()->enrol_user($teacher->id, $course->id, $teacherrole->id, 'manual');

        // Create attempts.
        $scoes = svasu_get_scoes($svasu->id);
        $sco = array_shift($scoes);
        svasu_insert_track($student1->id, $svasu->id, $sco->id, 1, 'cmi.core.lesson_status', 'completed');
        svasu_insert_track($student1->id, $svasu->id, $sco->id, 1, 'cmi.core.score.raw', '80');
        svasu_insert_track($student1->id, $svasu->id, $sco->id, 2, 'cmi.core.lesson_status', 'completed');

        $result = mod_svasu_external::get_svasu_user_data($svasu->id, 1);
        $result = external_api::clean_returnvalue(mod_svasu_external::get_svasu_user_data_returns(), $result);
        $this->assertCount(2, $result['data']);
        // Find our tracking data.
        $found = 0;
        foreach ($result['data'] as $scodata) {
            foreach ($scodata['userdata'] as $userdata) {
                if ($userdata['element'] == 'cmi.core.lesson_status' and $userdata['value'] == 'completed') {
                    $found++;
                }
                if ($userdata['element'] == 'cmi.core.score.raw' and $userdata['value'] == '80') {
                    $found++;
                }
            }
        }
        $this->assertEquals(2, $found);

        // Test invalid instance id.
        try {
             mod_svasu_external::get_svasu_user_data(0, 1);
            $this->fail('Exception expected due to invalid instance id.');
        } catch (moodle_exception $e) {
            $this->assertEquals('invalidrecord', $e->errorcode);
        }
    }

    /**
     * Test insert svasu tracks
     */
    public function test_mod_svasu_insert_svasu_tracks() {
        global $DB;

        $this->resetAfterTest(true);

        // Create users.
        $student = self::getDataGenerator()->create_user();

        // Create courses to add the modules.
        $course = self::getDataGenerator()->create_course();

        // First svasu, dates restriction.
        $record = new stdClass();
        $record->course = $course->id;
        $record->timeopen = time() + DAYSECS;
        $record->timeclose = $record->timeopen + DAYSECS;
        $svasu = self::getDataGenerator()->create_module('svasu', $record);

        // Get a SCO.
        $scoes = svasu_get_scoes($svasu->id);
        $sco = array_shift($scoes);

        // Tracks.
        $tracks = array();
        $tracks[] = array(
            'element' => 'cmi.core.lesson_status',
            'value' => 'completed'
        );
        $tracks[] = array(
            'element' => 'cmi.core.score.raw',
            'value' => '80'
        );

        // Set to the student user.
        self::setUser($student);

        // Users enrolments.
        $studentrole = $DB->get_record('role', array('shortname' => 'student'));
        $this->getDataGenerator()->enrol_user($student->id, $course->id, $studentrole->id, 'manual');

        // Exceptions first.
        try {
            mod_svasu_external::insert_svasu_tracks($sco->id, 1, $tracks);
            $this->fail('Exception expected due to dates');
        } catch (moodle_exception $e) {
            $this->assertEquals('notopenyet', $e->errorcode);
        }

        $svasu->timeopen = time() - DAYSECS;
        $svasu->timeclose = time() - HOURSECS;
        $DB->update_record('svasu', $svasu);

        try {
            mod_svasu_external::insert_svasu_tracks($sco->id, 1, $tracks);
            $this->fail('Exception expected due to dates');
        } catch (moodle_exception $e) {
            $this->assertEquals('expired', $e->errorcode);
        }

        // Test invalid instance id.
        try {
             mod_svasu_external::insert_svasu_tracks(0, 1, $tracks);
            $this->fail('Exception expected due to invalid sco id.');
        } catch (moodle_exception $e) {
            $this->assertEquals('cannotfindsco', $e->errorcode);
        }

        $svasu->timeopen = 0;
        $svasu->timeclose = 0;
        $DB->update_record('svasu', $svasu);

        // Retrieve my tracks.
        $result = mod_svasu_external::insert_svasu_tracks($sco->id, 1, $tracks);
        $result = external_api::clean_returnvalue(mod_svasu_external::insert_svasu_tracks_returns(), $result);
        $this->assertCount(0, $result['warnings']);

        $trackids = $DB->get_records('svasu_scoes_track', array('userid' => $student->id, 'scoid' => $sco->id,
                                                                'svasuid' => $svasu->id, 'attempt' => 1));
        // We use asort here to prevent problems with ids ordering.
        $expectedkeys = array_keys($trackids);
        $this->assertEquals(asort($expectedkeys), asort($result['trackids']));
    }

    /**
     * Test get svasu sco tracks
     */
    public function test_mod_svasu_get_svasu_sco_tracks() {
        global $DB;

        $this->resetAfterTest(true);

        // Create users.
        $student = self::getDataGenerator()->create_user();
        $otherstudent = self::getDataGenerator()->create_user();
        $teacher = self::getDataGenerator()->create_user();

        // Set to the student user.
        self::setUser($student);

        // Create courses to add the modules.
        $course = self::getDataGenerator()->create_course();

        // First svasu.
        $record = new stdClass();
        $record->course = $course->id;
        $svasu = self::getDataGenerator()->create_module('svasu', $record);

        // Users enrolments.
        $studentrole = $DB->get_record('role', array('shortname' => 'student'));
        $teacherrole = $DB->get_record('role', array('shortname' => 'teacher'));
        $this->getDataGenerator()->enrol_user($student->id, $course->id, $studentrole->id, 'manual');
        $this->getDataGenerator()->enrol_user($teacher->id, $course->id, $teacherrole->id, 'manual');

        // Create attempts.
        $scoes = svasu_get_scoes($svasu->id);
        $sco = array_shift($scoes);
        svasu_insert_track($student->id, $svasu->id, $sco->id, 1, 'cmi.core.lesson_status', 'completed');
        svasu_insert_track($student->id, $svasu->id, $sco->id, 1, 'cmi.core.score.raw', '80');
        svasu_insert_track($student->id, $svasu->id, $sco->id, 2, 'cmi.core.lesson_status', 'completed');

        $result = mod_svasu_external::get_svasu_sco_tracks($sco->id, $student->id, 1);
        $result = external_api::clean_returnvalue(mod_svasu_external::get_svasu_sco_tracks_returns(), $result);
        // 7 default elements + 2 custom ones.
        $this->assertCount(9, $result['data']['tracks']);
        $this->assertEquals(1, $result['data']['attempt']);
        $this->assertCount(0, $result['warnings']);
        // Find our tracking data.
        $found = 0;
        foreach ($result['data']['tracks'] as $userdata) {
            if ($userdata['element'] == 'cmi.core.lesson_status' and $userdata['value'] == 'completed') {
                $found++;
            }
            if ($userdata['element'] == 'cmi.core.score.raw' and $userdata['value'] == '80') {
                $found++;
            }
        }
        $this->assertEquals(2, $found);

        // Try invalid attempt.
        $result = mod_svasu_external::get_svasu_sco_tracks($sco->id, $student->id, 10);
        $result = external_api::clean_returnvalue(mod_svasu_external::get_svasu_sco_tracks_returns(), $result);
        $this->assertCount(0, $result['data']['tracks']);
        $this->assertEquals(10, $result['data']['attempt']);
        $this->assertCount(1, $result['warnings']);
        $this->assertEquals('notattempted', $result['warnings'][0]['warningcode']);

        // Capabilities check.
        try {
             mod_svasu_external::get_svasu_sco_tracks($sco->id, $otherstudent->id);
            $this->fail('Exception expected due to invalid instance id.');
        } catch (required_capability_exception $e) {
            $this->assertEquals('nopermissions', $e->errorcode);
        }

        self::setUser($teacher);
        // Ommit the attempt parameter, the function should calculate the last attempt.
        $result = mod_svasu_external::get_svasu_sco_tracks($sco->id, $student->id);
        $result = external_api::clean_returnvalue(mod_svasu_external::get_svasu_sco_tracks_returns(), $result);
        // 7 default elements + 1 custom one.
        $this->assertCount(8, $result['data']['tracks']);
        $this->assertEquals(2, $result['data']['attempt']);

        // Test invalid instance id.
        try {
             mod_svasu_external::get_svasu_sco_tracks(0, 1);
            $this->fail('Exception expected due to invalid instance id.');
        } catch (moodle_exception $e) {
            $this->assertEquals('cannotfindsco', $e->errorcode);
        }
        // Invalid user.
        try {
             mod_svasu_external::get_svasu_sco_tracks($sco->id, 0);
            $this->fail('Exception expected due to invalid instance id.');
        } catch (moodle_exception $e) {
            $this->assertEquals('invaliduser', $e->errorcode);
        }
    }

    /*
     * Test get svasus by courses
     */
    public function test_mod_svasu_get_svasus_by_courses() {
        global $DB;

        $this->resetAfterTest(true);

        // Create users.
        $student = self::getDataGenerator()->create_user();
        $teacher = self::getDataGenerator()->create_user();

        // Set to the student user.
        self::setUser($student);

        // Create courses to add the modules.
        $course1 = self::getDataGenerator()->create_course();
        $course2 = self::getDataGenerator()->create_course();

        // First svasu.
        $record = new stdClass();
        $record->introformat = FORMAT_HTML;
        $record->course = $course1->id;
        $record->hidetoc = 2;
        $record->displayattemptstatus = 2;
        $record->skipview = 2;
        $svasu1 = self::getDataGenerator()->create_module('svasu', $record);

        // Second svasu.
        $record = new stdClass();
        $record->introformat = FORMAT_HTML;
        $record->course = $course2->id;
        $svasu2 = self::getDataGenerator()->create_module('svasu', $record);

        $studentrole = $DB->get_record('role', array('shortname' => 'student'));
        $teacherrole = $DB->get_record('role', array('shortname' => 'editingteacher'));

        // Users enrolments.
        $this->getDataGenerator()->enrol_user($student->id, $course1->id, $studentrole->id, 'manual');
        $this->getDataGenerator()->enrol_user($teacher->id, $course1->id, $teacherrole->id, 'manual');

        // Execute real Moodle enrolment as we'll call unenrol() method on the instance later.
        $enrol = enrol_get_plugin('manual');
        $enrolinstances = enrol_get_instances($course2->id, true);
        foreach ($enrolinstances as $courseenrolinstance) {
            if ($courseenrolinstance->enrol == "manual") {
                $instance2 = $courseenrolinstance;
                break;
            }
        }
        $enrol->enrol_user($instance2, $student->id, $studentrole->id);

        $returndescription = mod_svasu_external::get_svasus_by_courses_returns();

        // Test open/close dates.

        $timenow = time();
        $svasu1->timeopen = $timenow - DAYSECS;
        $svasu1->timeclose = $timenow - HOURSECS;
        $DB->update_record('svasu', $svasu1);

        $result = mod_svasu_external::get_svasus_by_courses(array($course1->id));
        $result = external_api::clean_returnvalue($returndescription, $result);
        $this->assertCount(1, $result['warnings']);
        // Only 'id', 'coursemodule', 'course', 'name', 'intro', 'introformat', 'introfiles'.
        $this->assertCount(7, $result['svasus'][0]);
        $this->assertEquals('expired', $result['warnings'][0]['warningcode']);

        $svasu1->timeopen = $timenow + DAYSECS;
        $svasu1->timeclose = $svasu1->timeopen + DAYSECS;
        $DB->update_record('svasu', $svasu1);

        $result = mod_svasu_external::get_svasus_by_courses(array($course1->id));
        $result = external_api::clean_returnvalue($returndescription, $result);
        $this->assertCount(1, $result['warnings']);
        // Only 'id', 'coursemodule', 'course', 'name', 'intro', 'introformat', 'introfiles'.
        $this->assertCount(7, $result['svasus'][0]);
        $this->assertEquals('notopenyet', $result['warnings'][0]['warningcode']);

        // Reset times.
        $svasu1->timeopen = 0;
        $svasu1->timeclose = 0;
        $DB->update_record('svasu', $svasu1);

        // Create what we expect to be returned when querying the two courses.
        // First for the student user.
        $expectedfields = array('id', 'coursemodule', 'course', 'name', 'intro', 'introformat', 'version', 'maxgrade',
                                'grademethod', 'whatgrade', 'maxattempt', 'forcecompleted', 'forcenewattempt', 'lastattemptlock',
                                'displayattemptstatus', 'displaycoursestructure', 'sha1hash', 'md5hash', 'revision', 'launch',
                                'skipview', 'hidebrowse', 'hidetoc', 'nav', 'navpositionleft', 'navpositiontop', 'auto',
                                'popup', 'width', 'height', 'timeopen', 'timeclose', 'displayactivityname', 'packagesize',
                                'packageurl', 'svasutype', 'reference');

        // Add expected coursemodule and data.
        $svasu1->coursemodule = $svasu1->cmid;
        $svasu1->section = 0;
        $svasu1->visible = true;
        $svasu1->groupmode = 0;
        $svasu1->groupingid = 0;

        $svasu2->coursemodule = $svasu2->cmid;
        $svasu2->section = 0;
        $svasu2->visible = true;
        $svasu2->groupmode = 0;
        $svasu2->groupingid = 0;

        // SVASU size. The same package is used in both SVASUs.
        $svasucontext1 = context_module::instance($svasu1->cmid);
        $svasucontext2 = context_module::instance($svasu2->cmid);
        $fs = get_file_storage();
        $packagefile = $fs->get_file($svasucontext1->id, 'mod_svasu', 'package', 0, '/', $svasu1->reference);
        $packagesize = $packagefile->get_filesize();

        $packageurl1 = moodle_url::make_webservice_pluginfile_url(
                            $svasucontext1->id, 'mod_svasu', 'package', 0, '/', $svasu1->reference)->out(false);
        $packageurl2 = moodle_url::make_webservice_pluginfile_url(
                            $svasucontext2->id, 'mod_svasu', 'package', 0, '/', $svasu2->reference)->out(false);

        $svasu1->packagesize = $packagesize;
        $svasu1->packageurl = $packageurl1;
        $svasu2->packagesize = $packagesize;
        $svasu2->packageurl = $packageurl2;

        // Forced to boolean as it is returned as PARAM_BOOL.
        $protectpackages = (bool)get_config('scorm', 'protectpackagedownloads');
        $expected1 = array('protectpackagedownloads' => $protectpackages);
        $expected2 = array('protectpackagedownloads' => $protectpackages);
        foreach ($expectedfields as $field) {

            // Since we return the fields used as boolean as PARAM_BOOL instead PARAM_INT we need to force casting here.
            // From the returned fields definition we obtain the type expected for the field.
            if (empty($returndescription->keys['svasus']->content->keys[$field]->type)) {
                continue;
            }
            $fieldtype = $returndescription->keys['svasus']->content->keys[$field]->type;
            if ($fieldtype == PARAM_BOOL) {
                $expected1[$field] = (bool) $svasu1->{$field};
                $expected2[$field] = (bool) $svasu2->{$field};
            } else {
                $expected1[$field] = $svasu1->{$field};
                $expected2[$field] = $svasu2->{$field};
            }
        }
        $expected1['introfiles'] = [];
        $expected2['introfiles'] = [];

        $expectedsvasus = array();
        $expectedsvasus[] = $expected2;
        $expectedsvasus[] = $expected1;

        // Call the external function passing course ids.
        $result = mod_svasu_external::get_svasus_by_courses(array($course2->id, $course1->id));
        $result = external_api::clean_returnvalue($returndescription, $result);
        $this->assertEquals($expectedsvasus, $result['svasus']);

        // Call the external function without passing course id.
        $result = mod_svasu_external::get_svasus_by_courses();
        $result = external_api::clean_returnvalue($returndescription, $result);
        $this->assertEquals($expectedsvasus, $result['svasus']);

        // Unenrol user from second course and alter expected svasus.
        $enrol->unenrol_user($instance2, $student->id);
        array_shift($expectedsvasus);

        // Call the external function without passing course id.
        $result = mod_svasu_external::get_svasus_by_courses();
        $result = external_api::clean_returnvalue($returndescription, $result);
        $this->assertEquals($expectedsvasus, $result['svasus']);

        // Call for the second course we unenrolled the user from, expected warning.
        $result = mod_svasu_external::get_svasus_by_courses(array($course2->id));
        $this->assertCount(1, $result['warnings']);
        $this->assertEquals('1', $result['warnings'][0]['warningcode']);
        $this->assertEquals($course2->id, $result['warnings'][0]['itemid']);

        // Now, try as a teacher for getting all the additional fields.
        self::setUser($teacher);

        $additionalfields = array('updatefreq', 'timemodified', 'options',
                                    'completionstatusrequired', 'completionscorerequired', 'completionstatusallscos',
                                    'autocommit', 'section', 'visible', 'groupmode', 'groupingid');

        foreach ($additionalfields as $field) {
            $fieldtype = $returndescription->keys['svasus']->content->keys[$field]->type;

            if ($fieldtype == PARAM_BOOL) {
                $expectedsvasus[0][$field] = (bool) $svasu1->{$field};
            } else {
                $expectedsvasus[0][$field] = $svasu1->{$field};
            }
        }

        $result = mod_svasu_external::get_svasus_by_courses();
        $result = external_api::clean_returnvalue($returndescription, $result);
        $this->assertEquals($expectedsvasus, $result['svasus']);

        // Even with the SVASU closed in time teacher should retrieve the info.
        $svasu1->timeopen = $timenow - DAYSECS;
        $svasu1->timeclose = $timenow - HOURSECS;
        $DB->update_record('svasu', $svasu1);

        $expectedsvasus[0]['timeopen'] = $svasu1->timeopen;
        $expectedsvasus[0]['timeclose'] = $svasu1->timeclose;

        $result = mod_svasu_external::get_svasus_by_courses();
        $result = external_api::clean_returnvalue($returndescription, $result);
        $this->assertEquals($expectedsvasus, $result['svasus']);

        // Admin also should get all the information.
        self::setAdminUser();

        $result = mod_svasu_external::get_svasus_by_courses(array($course1->id));
        $result = external_api::clean_returnvalue($returndescription, $result);
        $this->assertEquals($expectedsvasus, $result['svasus']);
    }

    /**
     * Test launch_sco
     */
    public function test_launch_sco() {
        global $DB;

        // Test invalid instance id.
        try {
            mod_svasu_external::launch_sco(0);
            $this->fail('Exception expected due to invalid mod_svasu instance id.');
        } catch (moodle_exception $e) {
            $this->assertEquals('invalidrecord', $e->errorcode);
        }

        // Test not-enrolled user.
        $user = self::getDataGenerator()->create_user();
        $this->setUser($user);
        try {
            mod_svasu_external::launch_sco($this->svasu->id);
            $this->fail('Exception expected due to not enrolled user.');
        } catch (moodle_exception $e) {
            $this->assertEquals('requireloginerror', $e->errorcode);
        }

        // Test user with full capabilities.
        $this->setUser($this->student);

        // Trigger and capture the event.
        $sink = $this->redirectEvents();

        $scoes = svasu_get_scoes($this->svasu->id);
        foreach ($scoes as $sco) {
            // Find launchable SCO.
            if ($sco->launch != '') {
                break;
            }
        }

        $result = mod_svasu_external::launch_sco($this->svasu->id, $sco->id);
        $result = external_api::clean_returnvalue(mod_svasu_external::launch_sco_returns(), $result);

        $events = $sink->get_events();
        $this->assertCount(3, $events);
        $event = array_pop($events);

        // Checking that the event contains the expected values.
        $this->assertInstanceOf('\mod_svasu\event\sco_launched', $event);
        $this->assertEquals($this->context, $event->get_context());
        $moodleurl = new \moodle_url('/mod/svasu/player.php', array('cm' => $this->cm->id, 'scoid' => $sco->id));
        $this->assertEquals($moodleurl, $event->get_url());
        $this->assertEventContextNotUsed($event);
        $this->assertNotEmpty($event->get_name());

        $event = array_shift($events);
        $this->assertInstanceOf('\core\event\course_module_completion_updated', $event);

        // Check completion status.
        $completion = new completion_info($this->course);
        $completiondata = $completion->get_data($this->cm);
        $this->assertEquals(COMPLETION_VIEWED, $completiondata->completionstate);

        // Invalid SCO.
        try {
            mod_svasu_external::launch_sco($this->svasu->id, -1);
            $this->fail('Exception expected due to invalid SCO id.');
        } catch (moodle_exception $e) {
            $this->assertEquals('cannotfindsco', $e->errorcode);
        }
    }

    /**
     * Test mod_svasu_get_svasu_access_information.
     */
    public function test_mod_svasu_get_svasu_access_information() {
        global $DB;

        $this->resetAfterTest(true);

        $student = self::getDataGenerator()->create_user();
        $course = self::getDataGenerator()->create_course();
        // Create the svasu.
        $record = new stdClass();
        $record->course = $course->id;
        $svasu = self::getDataGenerator()->create_module('svasu', $record);
        $context = context_module::instance($svasu->cmid);

        $studentrole = $DB->get_record('role', array('shortname' => 'student'));
        $this->getDataGenerator()->enrol_user($student->id, $course->id, $studentrole->id, 'manual');

        self::setUser($student);
        $result = mod_svasu_external::get_svasu_access_information($svasu->id);
        $result = external_api::clean_returnvalue(mod_svasu_external::get_svasu_access_information_returns(), $result);

        // Check default values for capabilities.
        $enabledcaps = array('canskipview', 'cansavetrack', 'canviewscores');

        unset($result['warnings']);
        foreach ($result as $capname => $capvalue) {
            if (in_array($capname, $enabledcaps)) {
                $this->assertTrue($capvalue);
            } else {
                $this->assertFalse($capvalue);
            }
        }
        // Now, unassign one capability.
        unassign_capability('mod/svasu:viewscores', $studentrole->id);
        array_pop($enabledcaps);
        accesslib_clear_all_caches_for_unit_testing();

        $result = mod_svasu_external::get_svasu_access_information($svasu->id);
        $result = external_api::clean_returnvalue(mod_svasu_external::get_svasu_access_information_returns(), $result);
        unset($result['warnings']);
        foreach ($result as $capname => $capvalue) {
            if (in_array($capname, $enabledcaps)) {
                $this->assertTrue($capvalue);
            } else {
                $this->assertFalse($capvalue);
            }
        }
    }
}
