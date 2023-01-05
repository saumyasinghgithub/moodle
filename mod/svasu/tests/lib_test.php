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
 * SVASU module library functions tests
 *
 * @package    mod_svasu
 * @category   test
 * @copyright  2015 Juan Leyva <juan@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 3.0
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;

require_once($CFG->dirroot . '/webservice/tests/helpers.php');
require_once($CFG->dirroot . '/mod/svasu/lib.php');

/**
 * SVASU module library functions tests
 *
 * @package    mod_svasu
 * @category   test
 * @copyright  2015 Juan Leyva <juan@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 3.0
 */
class mod_svasu_lib_testcase extends externallib_advanced_testcase {

    /**
     * Set up for every test
     */
    public function setUp() {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        // Setup test data.
        $this->course = $this->getDataGenerator()->create_course();
        $this->svasu = $this->getDataGenerator()->create_module('svasu', array('course' => $this->course->id));
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

    /** Test svasu_check_mode
     *
     * @return void
     */
    public function test_svasu_check_mode() {
        global $CFG;

        $newattempt = 'on';
        $attempt = 1;
        $mode = 'normal';
        svasu_check_mode($this->svasu, $newattempt, $attempt, $this->student->id, $mode);
        $this->assertEquals('off', $newattempt);

        $scoes = svasu_get_scoes($this->svasu->id);
        $sco = array_pop($scoes);
        svasu_insert_track($this->student->id, $this->svasu->id, $sco->id, 1, 'cmi.core.lesson_status', 'completed');
        $newattempt = 'on';
        svasu_check_mode($this->svasu, $newattempt, $attempt, $this->student->id, $mode);
        $this->assertEquals('on', $newattempt);

        // Now do the same with a SVASU 2004 package.
        $record = new stdClass();
        $record->course = $this->course->id;
        $record->packagefilepath = $CFG->dirroot.'/mod/svasu/tests/packages/RuntimeBasicCalls_SVASU20043rdEdition.zip';
        $svasu13 = $this->getDataGenerator()->create_module('svasu', $record);
        $newattempt = 'on';
        $attempt = 1;
        $mode = 'normal';
        svasu_check_mode($svasu13, $newattempt, $attempt, $this->student->id, $mode);
        $this->assertEquals('off', $newattempt);

        $scoes = svasu_get_scoes($svasu13->id);
        $sco = array_pop($scoes);
        svasu_insert_track($this->student->id, $svasu13->id, $sco->id, 1, 'cmi.completion_status', 'completed');

        $newattempt = 'on';
        $attempt = 1;
        $mode = 'normal';
        svasu_check_mode($svasu13, $newattempt, $attempt, $this->student->id, $mode);
        $this->assertEquals('on', $newattempt);
    }

    /**
     * Test svasu_view
     * @return void
     */
    public function test_svasu_view() {
        global $CFG;

        // Trigger and capture the event.
        $sink = $this->redirectEvents();

        svasu_view($this->svasu, $this->course, $this->cm, $this->context);

        $events = $sink->get_events();
        $this->assertCount(1, $events);
        $event = array_shift($events);

        // Checking that the event contains the expected values.
        $this->assertInstanceOf('\mod_svasu\event\course_module_viewed', $event);
        $this->assertEquals($this->context, $event->get_context());
        $url = new \moodle_url('/mod/svasu/view.php', array('id' => $this->cm->id));
        $this->assertEquals($url, $event->get_url());
        $this->assertEventContextNotUsed($event);
        $this->assertNotEmpty($event->get_name());
    }

    /**
     * Test svasu_get_availability_status and svasu_require_available
     * @return void
     */
    public function test_svasu_check_and_require_available() {
        global $DB;

        $this->setAdminUser();

        // User override case.
        $this->svasu->timeopen = time() + DAYSECS;
        $this->svasu->timeclose = time() - DAYSECS;
        list($status, $warnings) = svasu_get_availability_status($this->svasu, true, $this->context);
        $this->assertEquals(true, $status);
        $this->assertCount(0, $warnings);

        // Now check with a student.
        list($status, $warnings) = svasu_get_availability_status($this->svasu, true, $this->context, $this->student->id);
        $this->assertEquals(false, $status);
        $this->assertCount(2, $warnings);
        $this->assertArrayHasKey('notopenyet', $warnings);
        $this->assertArrayHasKey('expired', $warnings);
        $this->assertEquals(userdate($this->svasu->timeopen), $warnings['notopenyet']);
        $this->assertEquals(userdate($this->svasu->timeclose), $warnings['expired']);

        // Reset the svasu's times.
        $this->svasu->timeopen = $this->svasu->timeclose = 0;

        // Set to the student user.
        self::setUser($this->student);

        // Usual case.
        list($status, $warnings) = svasu_get_availability_status($this->svasu, false);
        $this->assertEquals(true, $status);
        $this->assertCount(0, $warnings);

        // SVASU not open.
        $this->svasu->timeopen = time() + DAYSECS;
        list($status, $warnings) = svasu_get_availability_status($this->svasu, false);
        $this->assertEquals(false, $status);
        $this->assertCount(1, $warnings);

        // SVASU closed.
        $this->svasu->timeopen = 0;
        $this->svasu->timeclose = time() - DAYSECS;
        list($status, $warnings) = svasu_get_availability_status($this->svasu, false);
        $this->assertEquals(false, $status);
        $this->assertCount(1, $warnings);

        // SVASU not open and closed.
        $this->svasu->timeopen = time() + DAYSECS;
        list($status, $warnings) = svasu_get_availability_status($this->svasu, false);
        $this->assertEquals(false, $status);
        $this->assertCount(2, $warnings);

        // Now additional checkings with different parameters values.
        list($status, $warnings) = svasu_get_availability_status($this->svasu, true, $this->context);
        $this->assertEquals(false, $status);
        $this->assertCount(2, $warnings);

        // SVASU not open.
        $this->svasu->timeopen = time() + DAYSECS;
        $this->svasu->timeclose = 0;
        list($status, $warnings) = svasu_get_availability_status($this->svasu, true, $this->context);
        $this->assertEquals(false, $status);
        $this->assertCount(1, $warnings);

        // SVASU closed.
        $this->svasu->timeopen = 0;
        $this->svasu->timeclose = time() - DAYSECS;
        list($status, $warnings) = svasu_get_availability_status($this->svasu, true, $this->context);
        $this->assertEquals(false, $status);
        $this->assertCount(1, $warnings);

        // SVASU not open and closed.
        $this->svasu->timeopen = time() + DAYSECS;
        list($status, $warnings) = svasu_get_availability_status($this->svasu, true, $this->context);
        $this->assertEquals(false, $status);
        $this->assertCount(2, $warnings);

        // As teacher now.
        self::setUser($this->teacher);

        // SVASU not open and closed.
        $this->svasu->timeopen = time() + DAYSECS;
        list($status, $warnings) = svasu_get_availability_status($this->svasu, false);
        $this->assertEquals(false, $status);
        $this->assertCount(2, $warnings);

        // Now, we use the special capability.
        // SVASU not open and closed.
        $this->svasu->timeopen = time() + DAYSECS;
        list($status, $warnings) = svasu_get_availability_status($this->svasu, true, $this->context);
        $this->assertEquals(true, $status);
        $this->assertCount(0, $warnings);

        // Check exceptions does not broke anything.
        svasu_require_available($this->svasu, true, $this->context);
        // Now, expect exceptions.
        $this->expectException('moodle_exception');
        $this->expectExceptionMessage(get_string("notopenyet", "svasu", userdate($this->svasu->timeopen)));

        // Now as student other condition.
        self::setUser($this->student);
        $this->svasu->timeopen = 0;
        $this->svasu->timeclose = time() - DAYSECS;

        $this->expectException('moodle_exception');
        $this->expectExceptionMessage(get_string("expired", "svasu", userdate($this->svasu->timeclose)));
        svasu_require_available($this->svasu, false);
    }

    /**
     * Test svasu_get_last_completed_attempt
     *
     * @return void
     */
    public function test_svasu_get_last_completed_attempt() {
        $this->assertEquals(1, svasu_get_last_completed_attempt($this->svasu->id, $this->student->id));
    }

    public function test_svasu_core_calendar_provide_event_action_open() {
        $this->resetAfterTest();

        $this->setAdminUser();

        // Create a course.
        $course = $this->getDataGenerator()->create_course();

        // Create a svasu activity.
        $svasu = $this->getDataGenerator()->create_module('svasu', array('course' => $course->id,
            'timeopen' => time() - DAYSECS, 'timeclose' => time() + DAYSECS));

        // Create a calendar event.
        $event = $this->create_action_event($course->id, $svasu->id, SVASU_EVENT_TYPE_OPEN);

        // Only students see svasu events.
        $this->setUser($this->student);

        // Create an action factory.
        $factory = new \core_calendar\action_factory();

        // Decorate action event.
        $actionevent = mod_svasu_core_calendar_provide_event_action($event, $factory);

        // Confirm the event was decorated.
        $this->assertInstanceOf('\core_calendar\local\event\value_objects\action', $actionevent);
        $this->assertEquals(get_string('enter', 'svasu'), $actionevent->get_name());
        $this->assertInstanceOf('moodle_url', $actionevent->get_url());
        $this->assertEquals(1, $actionevent->get_item_count());
        $this->assertTrue($actionevent->is_actionable());
    }

    public function test_svasu_core_calendar_provide_event_action_closed() {
        $this->resetAfterTest();

        $this->setAdminUser();

        // Create a course.
        $course = $this->getDataGenerator()->create_course();

        // Create a svasu activity.
        $svasu = $this->getDataGenerator()->create_module('svasu', array('course' => $course->id,
            'timeclose' => time() - DAYSECS));

        // Create a calendar event.
        $event = $this->create_action_event($course->id, $svasu->id, SVASU_EVENT_TYPE_OPEN);

        // Create an action factory.
        $factory = new \core_calendar\action_factory();

        // Decorate action event.
        $actionevent = mod_svasu_core_calendar_provide_event_action($event, $factory);

        // No event on the dashboard if module is closed.
        $this->assertNull($actionevent);
    }

    public function test_svasu_core_calendar_provide_event_action_open_in_future() {
        $this->resetAfterTest();

        $this->setAdminUser();

        // Create a course.
        $course = $this->getDataGenerator()->create_course();

        // Create a svasu activity.
        $svasu = $this->getDataGenerator()->create_module('svasu', array('course' => $course->id,
            'timeopen' => time() + DAYSECS));

        // Create a calendar event.
        $event = $this->create_action_event($course->id, $svasu->id, SVASU_EVENT_TYPE_OPEN);

        // Only students see svasu events.
        $this->setUser($this->student);

        // Create an action factory.
        $factory = new \core_calendar\action_factory();

        // Decorate action event.
        $actionevent = mod_svasu_core_calendar_provide_event_action($event, $factory);

        // Confirm the event was decorated.
        $this->assertInstanceOf('\core_calendar\local\event\value_objects\action', $actionevent);
        $this->assertEquals(get_string('enter', 'svasu'), $actionevent->get_name());
        $this->assertInstanceOf('moodle_url', $actionevent->get_url());
        $this->assertEquals(1, $actionevent->get_item_count());
        $this->assertFalse($actionevent->is_actionable());
    }

    public function test_svasu_core_calendar_provide_event_action_with_different_user_as_admin() {
        $this->resetAfterTest();

        $this->setAdminUser();

        // Create a course.
        $course = $this->getDataGenerator()->create_course();

        // Create a svasu activity.
        $svasu = $this->getDataGenerator()->create_module('svasu', array('course' => $course->id,
            'timeopen' => time() + DAYSECS));

        // Create a calendar event.
        $event = $this->create_action_event($course->id, $svasu->id, SVASU_EVENT_TYPE_OPEN);

        // Create an action factory.
        $factory = new \core_calendar\action_factory();

        // Decorate action event override with a passed in user.
        $actionevent = mod_svasu_core_calendar_provide_event_action($event, $factory, $this->student->id);
        $actionevent2 = mod_svasu_core_calendar_provide_event_action($event, $factory);

        // Only students see svasu events.
        $this->assertNull($actionevent2);

        // Confirm the event was decorated.
        $this->assertInstanceOf('\core_calendar\local\event\value_objects\action', $actionevent);
        $this->assertEquals(get_string('enter', 'svasu'), $actionevent->get_name());
        $this->assertInstanceOf('moodle_url', $actionevent->get_url());
        $this->assertEquals(1, $actionevent->get_item_count());
        $this->assertFalse($actionevent->is_actionable());
    }

    public function test_svasu_core_calendar_provide_event_action_no_time_specified() {
        $this->resetAfterTest();

        $this->setAdminUser();

        // Create a course.
        $course = $this->getDataGenerator()->create_course();

        // Create a svasu activity.
        $svasu = $this->getDataGenerator()->create_module('svasu', array('course' => $course->id));

        // Create a calendar event.
        $event = $this->create_action_event($course->id, $svasu->id, SVASU_EVENT_TYPE_OPEN);

        // Only students see svasu events.
        $this->setUser($this->student);

        // Create an action factory.
        $factory = new \core_calendar\action_factory();

        // Decorate action event.
        $actionevent = mod_svasu_core_calendar_provide_event_action($event, $factory);

        // Confirm the event was decorated.
        $this->assertInstanceOf('\core_calendar\local\event\value_objects\action', $actionevent);
        $this->assertEquals(get_string('enter', 'svasu'), $actionevent->get_name());
        $this->assertInstanceOf('moodle_url', $actionevent->get_url());
        $this->assertEquals(1, $actionevent->get_item_count());
        $this->assertTrue($actionevent->is_actionable());
    }

    public function test_svasu_core_calendar_provide_event_action_already_completed() {
        $this->resetAfterTest();
        set_config('enablecompletion', 1);
        $this->setAdminUser();

        // Create the activity.
        $course = $this->getDataGenerator()->create_course(array('enablecompletion' => 1));
        $svasu = $this->getDataGenerator()->create_module('svasu', array('course' => $course->id),
            array('completion' => 2, 'completionview' => 1, 'completionexpected' => time() + DAYSECS));

        // Get some additional data.
        $cm = get_coursemodule_from_instance('svasu', $svasu->id);

        // Create a calendar event.
        $event = $this->create_action_event($course->id, $svasu->id,
            \core_completion\api::COMPLETION_EVENT_TYPE_DATE_COMPLETION_EXPECTED);

        // Mark the activity as completed.
        $completion = new completion_info($course);
        $completion->set_module_viewed($cm);

        // Create an action factory.
        $factory = new \core_calendar\action_factory();

        // Decorate action event.
        $actionevent = mod_svasu_core_calendar_provide_event_action($event, $factory);

        // Ensure result was null.
        $this->assertNull($actionevent);
    }

    public function test_svasu_core_calendar_provide_event_action_already_completed_for_user() {
        $this->resetAfterTest();
        set_config('enablecompletion', 1);
        $this->setAdminUser();

        // Create the activity.
        $course = $this->getDataGenerator()->create_course(array('enablecompletion' => 1));
        $svasu = $this->getDataGenerator()->create_module('svasu', array('course' => $course->id),
            array('completion' => 2, 'completionview' => 1, 'completionexpected' => time() + DAYSECS));

        // Enrol a student in the course.
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');

        // Get some additional data.
        $cm = get_coursemodule_from_instance('svasu', $svasu->id);

        // Create a calendar event.
        $event = $this->create_action_event($course->id, $svasu->id,
            \core_completion\api::COMPLETION_EVENT_TYPE_DATE_COMPLETION_EXPECTED);

        // Mark the activity as completed for the student.
        $completion = new completion_info($course);
        $completion->set_module_viewed($cm, $student->id);

        // Create an action factory.
        $factory = new \core_calendar\action_factory();

        // Decorate action event for the student.
        $actionevent = mod_svasu_core_calendar_provide_event_action($event, $factory, $student->id);

        // Ensure result was null.
        $this->assertNull($actionevent);
    }

    /**
     * Creates an action event.
     *
     * @param int $courseid
     * @param int $instanceid The data id.
     * @param string $eventtype The event type. eg. DATA_EVENT_TYPE_OPEN.
     * @param int|null $timestart The start timestamp for the event
     * @return bool|calendar_event
     */
    private function create_action_event($courseid, $instanceid, $eventtype, $timestart = null) {
        $event = new stdClass();
        $event->name = 'Calendar event';
        $event->modulename = 'svasu';
        $event->courseid = $courseid;
        $event->instance = $instanceid;
        $event->type = CALENDAR_EVENT_TYPE_ACTION;
        $event->eventtype = $eventtype;
        $event->eventtype = $eventtype;

        if ($timestart) {
            $event->timestart = $timestart;
        } else {
            $event->timestart = time();
        }

        return calendar_event::create($event);
    }

    /**
     * Test the callback responsible for returning the completion rule descriptions.
     * This function should work given either an instance of the module (cm_info), such as when checking the active rules,
     * or if passed a stdClass of similar structure, such as when checking the the default completion settings for a mod type.
     */
    public function test_mod_svasu_completion_get_active_rule_descriptions() {
        $this->resetAfterTest();
        $this->setAdminUser();

        // Two activities, both with automatic completion. One has the 'completionsubmit' rule, one doesn't.
        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 2]);
        $svasu1 = $this->getDataGenerator()->create_module('svasu', [
            'course' => $course->id,
            'completion' => 2,
            'completionstatusrequired' => 6,
            'completionscorerequired' => 5,
            'completionstatusallscos' => 1
        ]);
        $svasu2 = $this->getDataGenerator()->create_module('svasu', [
            'course' => $course->id,
            'completion' => 2,
            'completionstatusrequired' => null,
            'completionscorerequired' => null,
            'completionstatusallscos' => null
        ]);
        $cm1 = cm_info::create(get_coursemodule_from_instance('svasu', $svasu1->id));
        $cm2 = cm_info::create(get_coursemodule_from_instance('svasu', $svasu2->id));

        // Data for the stdClass input type.
        // This type of input would occur when checking the default completion rules for an activity type, where we don't have
        // any access to cm_info, rather the input is a stdClass containing completion and customdata attributes, just like cm_info.
        $moddefaults = new stdClass();
        $moddefaults->customdata = ['customcompletionrules' => [
            'completionstatusrequired' => 6,
            'completionscorerequired' => 5,
            'completionstatusallscos' => 1
        ]];
        $moddefaults->completion = 2;

        // Determine the selected statuses using a bitwise operation.
        $cvalues = array();
        foreach (svasu_status_options(true) as $key => $value) {
            if (($svasu1->completionstatusrequired & $key) == $key) {
                $cvalues[] = $value;
            }
        }
        $statusstring = implode(', ', $cvalues);

        $activeruledescriptions = [
            get_string('completionstatusrequireddesc', 'svasu', $statusstring),
            get_string('completionscorerequireddesc', 'svasu', $svasu1->completionscorerequired),
            get_string('completionstatusallscos', 'svasu'),
        ];
        $this->assertEquals(mod_svasu_get_completion_active_rule_descriptions($cm1), $activeruledescriptions);
        $this->assertEquals(mod_svasu_get_completion_active_rule_descriptions($cm2), []);
        $this->assertEquals(mod_svasu_get_completion_active_rule_descriptions($moddefaults), $activeruledescriptions);
        $this->assertEquals(mod_svasu_get_completion_active_rule_descriptions(new stdClass()), []);
    }

    /**
     * An unkown event type should not change the svasu instance.
     */
    public function test_mod_svasu_core_calendar_event_timestart_updated_unknown_event() {
        global $CFG, $DB;
        require_once($CFG->dirroot . "/calendar/lib.php");

        $this->resetAfterTest(true);
        $this->setAdminUser();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $svasugenerator = $generator->get_plugin_generator('mod_svasu');
        $timeopen = time();
        $timeclose = $timeopen + DAYSECS;
        $svasu = $svasugenerator->create_instance(['course' => $course->id]);
        $svasu->timeopen = $timeopen;
        $svasu->timeclose = $timeclose;
        $DB->update_record('svasu', $svasu);

        // Create a valid event.
        $event = new \calendar_event([
            'name' => 'Test event',
            'description' => '',
            'format' => 1,
            'courseid' => $course->id,
            'groupid' => 0,
            'userid' => 2,
            'modulename' => 'svasu',
            'instance' => $svasu->id,
            'eventtype' => SVASU_EVENT_TYPE_OPEN . "SOMETHING ELSE",
            'timestart' => 1,
            'timeduration' => 86400,
            'visible' => 1
        ]);

        mod_svasu_core_calendar_event_timestart_updated($event, $svasu);

        $svasu = $DB->get_record('svasu', ['id' => $svasu->id]);
        $this->assertEquals($timeopen, $svasu->timeopen);
        $this->assertEquals($timeclose, $svasu->timeclose);
    }

    /**
     * A SVASU_EVENT_TYPE_OPEN event should update the timeopen property of
     * the svasu activity.
     */
    public function test_mod_svasu_core_calendar_event_timestart_updated_open_event() {
        global $CFG, $DB;
        require_once($CFG->dirroot . "/calendar/lib.php");

        $this->resetAfterTest(true);
        $this->setAdminUser();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $svasugenerator = $generator->get_plugin_generator('mod_svasu');
        $timeopen = time();
        $timeclose = $timeopen + DAYSECS;
        $timemodified = 1;
        $newtimeopen = $timeopen - DAYSECS;
        $svasu = $svasugenerator->create_instance(['course' => $course->id]);
        $svasu->timeopen = $timeopen;
        $svasu->timeclose = $timeclose;
        $svasu->timemodified = $timemodified;
        $DB->update_record('svasu', $svasu);

        // Create a valid event.
        $event = new \calendar_event([
            'name' => 'Test event',
            'description' => '',
            'format' => 1,
            'courseid' => $course->id,
            'groupid' => 0,
            'userid' => 2,
            'modulename' => 'svasu',
            'instance' => $svasu->id,
            'eventtype' => SVASU_EVENT_TYPE_OPEN,
            'timestart' => $newtimeopen,
            'timeduration' => 86400,
            'visible' => 1
        ]);

        // Trigger and capture the event when adding a contact.
        $sink = $this->redirectEvents();

        mod_svasu_core_calendar_event_timestart_updated($event, $svasu);

        $triggeredevents = $sink->get_events();
        $moduleupdatedevents = array_filter($triggeredevents, function($e) {
            return is_a($e, 'core\event\course_module_updated');
        });

        $svasu = $DB->get_record('svasu', ['id' => $svasu->id]);
        // Ensure the timeopen property matches the event timestart.
        $this->assertEquals($newtimeopen, $svasu->timeopen);
        // Ensure the timeclose isn't changed.
        $this->assertEquals($timeclose, $svasu->timeclose);
        // Ensure the timemodified property has been changed.
        $this->assertNotEquals($timemodified, $svasu->timemodified);
        // Confirm that a module updated event is fired when the module
        // is changed.
        $this->assertNotEmpty($moduleupdatedevents);
    }

    /**
     * A SVASU_EVENT_TYPE_CLOSE event should update the timeclose property of
     * the svasu activity.
     */
    public function test_mod_svasu_core_calendar_event_timestart_updated_close_event() {
        global $CFG, $DB;
        require_once($CFG->dirroot . "/calendar/lib.php");

        $this->resetAfterTest(true);
        $this->setAdminUser();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $svasugenerator = $generator->get_plugin_generator('mod_svasu');
        $timeopen = time();
        $timeclose = $timeopen + DAYSECS;
        $timemodified = 1;
        $newtimeclose = $timeclose + DAYSECS;
        $svasu = $svasugenerator->create_instance(['course' => $course->id]);
        $svasu->timeopen = $timeopen;
        $svasu->timeclose = $timeclose;
        $svasu->timemodified = $timemodified;
        $DB->update_record('svasu', $svasu);

        // Create a valid event.
        $event = new \calendar_event([
            'name' => 'Test event',
            'description' => '',
            'format' => 1,
            'courseid' => $course->id,
            'groupid' => 0,
            'userid' => 2,
            'modulename' => 'svasu',
            'instance' => $svasu->id,
            'eventtype' => SVASU_EVENT_TYPE_CLOSE,
            'timestart' => $newtimeclose,
            'timeduration' => 86400,
            'visible' => 1
        ]);

        // Trigger and capture the event when adding a contact.
        $sink = $this->redirectEvents();

        mod_svasu_core_calendar_event_timestart_updated($event, $svasu);

        $triggeredevents = $sink->get_events();
        $moduleupdatedevents = array_filter($triggeredevents, function($e) {
            return is_a($e, 'core\event\course_module_updated');
        });

        $svasu = $DB->get_record('svasu', ['id' => $svasu->id]);
        // Ensure the timeclose property matches the event timestart.
        $this->assertEquals($newtimeclose, $svasu->timeclose);
        // Ensure the timeopen isn't changed.
        $this->assertEquals($timeopen, $svasu->timeopen);
        // Ensure the timemodified property has been changed.
        $this->assertNotEquals($timemodified, $svasu->timemodified);
        // Confirm that a module updated event is fired when the module
        // is changed.
        $this->assertNotEmpty($moduleupdatedevents);
    }

    /**
     * An unkown event type should not have any limits
     */
    public function test_mod_svasu_core_calendar_get_valid_event_timestart_range_unknown_event() {
        global $CFG, $DB;
        require_once($CFG->dirroot . "/calendar/lib.php");

        $this->resetAfterTest(true);
        $this->setAdminUser();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $timeopen = time();
        $timeclose = $timeopen + DAYSECS;
        $svasu = new \stdClass();
        $svasu->timeopen = $timeopen;
        $svasu->timeclose = $timeclose;

        // Create a valid event.
        $event = new \calendar_event([
            'name' => 'Test event',
            'description' => '',
            'format' => 1,
            'courseid' => $course->id,
            'groupid' => 0,
            'userid' => 2,
            'modulename' => 'svasu',
            'instance' => 1,
            'eventtype' => SVASU_EVENT_TYPE_OPEN . "SOMETHING ELSE",
            'timestart' => 1,
            'timeduration' => 86400,
            'visible' => 1
        ]);

        list ($min, $max) = mod_svasu_core_calendar_get_valid_event_timestart_range($event, $svasu);
        $this->assertNull($min);
        $this->assertNull($max);
    }

    /**
     * The open event should be limited by the svasu's timeclose property, if it's set.
     */
    public function test_mod_svasu_core_calendar_get_valid_event_timestart_range_open_event() {
        global $CFG, $DB;
        require_once($CFG->dirroot . "/calendar/lib.php");

        $this->resetAfterTest(true);
        $this->setAdminUser();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $timeopen = time();
        $timeclose = $timeopen + DAYSECS;
        $svasu = new \stdClass();
        $svasu->timeopen = $timeopen;
        $svasu->timeclose = $timeclose;

        // Create a valid event.
        $event = new \calendar_event([
            'name' => 'Test event',
            'description' => '',
            'format' => 1,
            'courseid' => $course->id,
            'groupid' => 0,
            'userid' => 2,
            'modulename' => 'svasu',
            'instance' => 1,
            'eventtype' => SVASU_EVENT_TYPE_OPEN,
            'timestart' => 1,
            'timeduration' => 86400,
            'visible' => 1
        ]);

        // The max limit should be bounded by the timeclose value.
        list ($min, $max) = mod_svasu_core_calendar_get_valid_event_timestart_range($event, $svasu);

        $this->assertNull($min);
        $this->assertEquals($timeclose, $max[0]);

        // No timeclose value should result in no upper limit.
        $svasu->timeclose = 0;
        list ($min, $max) = mod_svasu_core_calendar_get_valid_event_timestart_range($event, $svasu);

        $this->assertNull($min);
        $this->assertNull($max);
    }

    /**
     * The close event should be limited by the svasu's timeopen property, if it's set.
     */
    public function test_mod_svasu_core_calendar_get_valid_event_timestart_range_close_event() {
        global $CFG, $DB;
        require_once($CFG->dirroot . "/calendar/lib.php");

        $this->resetAfterTest(true);
        $this->setAdminUser();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $timeopen = time();
        $timeclose = $timeopen + DAYSECS;
        $svasu = new \stdClass();
        $svasu->timeopen = $timeopen;
        $svasu->timeclose = $timeclose;

        // Create a valid event.
        $event = new \calendar_event([
            'name' => 'Test event',
            'description' => '',
            'format' => 1,
            'courseid' => $course->id,
            'groupid' => 0,
            'userid' => 2,
            'modulename' => 'svasu',
            'instance' => 1,
            'eventtype' => SVASU_EVENT_TYPE_CLOSE,
            'timestart' => 1,
            'timeduration' => 86400,
            'visible' => 1
        ]);

        // The max limit should be bounded by the timeclose value.
        list ($min, $max) = mod_svasu_core_calendar_get_valid_event_timestart_range($event, $svasu);

        $this->assertEquals($timeopen, $min[0]);
        $this->assertNull($max);

        // No timeclose value should result in no upper limit.
        $svasu->timeopen = 0;
        list ($min, $max) = mod_svasu_core_calendar_get_valid_event_timestart_range($event, $svasu);

        $this->assertNull($min);
        $this->assertNull($max);
    }

    /**
     * A user who does not have capabilities to add events to the calendar should be able to create a SVASU.
     */
    public function test_creation_with_no_calendar_capabilities() {
        $this->resetAfterTest();
        $course = self::getDataGenerator()->create_course();
        $context = context_course::instance($course->id);
        $user = self::getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $roleid = self::getDataGenerator()->create_role();
        self::getDataGenerator()->role_assign($roleid, $user->id, $context->id);
        assign_capability('moodle/calendar:manageentries', CAP_PROHIBIT, $roleid, $context, true);
        $generator = self::getDataGenerator()->get_plugin_generator('mod_svasu');
        // Create an instance as a user without the calendar capabilities.
        $this->setUser($user);
        $time = time();
        $params = array(
            'course' => $course->id,
            'timeopen' => $time + 200,
            'timeclose' => $time + 2000,
        );
        $generator->create_instance($params);
    }
}
