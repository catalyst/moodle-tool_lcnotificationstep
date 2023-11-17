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
 * Trigger test for end date delay trigger.
 *
 * @package    tool_lcnotificationstep
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace tool_lcnotificationstep\tests;

use tool_lifecycle\action;
use tool_lifecycle\local\entity\trigger_subplugin;
use tool_lifecycle\local\manager\process_manager;
use tool_lifecycle\local\manager\settings_manager;
use tool_lifecycle\local\manager\trigger_manager;
use tool_lifecycle\local\manager\workflow_manager;
use tool_lifecycle\processor;
use tool_lifecycle\settings_type;

defined('MOODLE_INTERNAL') || die();

/**
 * Trigger test for start date delay trigger.
 *
 * @package    tool_lcnotificationstep
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class step_test extends \advanced_testcase {
    /** Icon of the manual trigger. */
    const MANUAL_TRIGGER1_ICON = 't/up';

    /** Display name of the manual trigger. */
    const MANUAL_TRIGGER1_DISPLAYNAME = 'Up';

    /** Capability of the manual trigger. */
    const MANUAL_TRIGGER1_CAPABILITY = 'moodle/course:manageactivities';

    /** @var trigger_subplugin $trigger Instances of the triggers under test. */
    private $trigger;

    /** @var \stdClass $course Instance of the course under test. */
    private $course;

    /** @var stdClass $teacher a teacher. */
    private $teacher;

    public function setUp() : void {
        global $USER, $DB;

        // We do not need a sesskey check in these tests.
        $USER->ignoresesskey = true;

        // Create manual workflow.
        $generator = $this->getDataGenerator()->get_plugin_generator('tool_lifecycle');
        $triggersettings = new \stdClass();
        $triggersettings->icon = self::MANUAL_TRIGGER1_ICON;
        $triggersettings->displayname = self::MANUAL_TRIGGER1_DISPLAYNAME;
        $triggersettings->capability = self::MANUAL_TRIGGER1_CAPABILITY;
        $manualworkflow = $generator->create_manual_workflow($triggersettings);

        // Trigger.
        $this->trigger = trigger_manager::get_triggers_for_workflow($manualworkflow->id)[0];

        // Delay step.
        $step = $generator->create_step("instance1", "tool_lcnotificationstep", $manualworkflow->id);

        $teacherroleid = $DB->get_field('role', 'id', ['shortname' => 'teacher']);
        $editingteacherroleid = $DB->get_field('role', 'id', ['shortname' => 'editingteacher']);
        $studentroleid = $DB->get_field('role', 'id', ['shortname' => 'student']);

        settings_manager::save_settings($step->id, settings_type::STEP,
            "tool_lcnotificationstep",
            [
                "roles" => "$teacherroleid, $editingteacherroleid",
                "emails" => 'external@email.com',
                "subject" => 'Subject ##courseshortname##',
                "content" => 'Plain content ##userfirstname## ##userlastname## ##coursefullname##',
                "contenthtml" => 'HTML content ##userfirstname## ##userlastname## ##coursefullname##',
            ]
        );

        // Course.
        $this->course = $this->getDataGenerator()->create_course();

        // Create 2 users.
        $this->teacher = $this->getDataGenerator()->create_user();
        $student = $this->getDataGenerator()->create_user();

        // Enrol a teacher in the course.
        $this->getDataGenerator()->enrol_user($this->teacher->id, $this->course->id, $teacherroleid);

        // Enrol a student in the course.
        $this->getDataGenerator()->enrol_user($student->id, $this->course->id, $studentroleid);

        // Activate the workflow.
        workflow_manager::handle_action(action::WORKFLOW_ACTIVATE, $manualworkflow->id);
    }

    /**
     * Test course is hidden.
     */
    public function test_notification_step() {
        $this->resetAfterTest();

        // Run trigger.
        process_manager::manually_trigger_process($this->course->id, $this->trigger->id);

        // Check that email was sent.
        $sink = $this->redirectEmails();
        $processor = new processor();
        $processor->process_courses();
        $emails = $sink->get_messages();
        $this->assertCount(2, $emails);

        // Get email send to teacher (users with role are added first, then external emails)
        $email = $emails[0];
        $this->assertEquals($this->teacher->email, $email->to);
        $this->assertEquals('Subject ' . $this->course->shortname, $email->subject);
        $this->assertStringContainsString('Plain content '
            . $this->teacher->firstname . ' ' . $this->teacher->lastname . ' '
            . $this->course->fullname,
            quoted_printable_decode($email->body));
        $this->assertStringContainsString('HTML content '
            . $this->teacher->firstname . ' ' . $this->teacher->lastname . ' '
            . $this->course->fullname,
            quoted_printable_decode($email->body));

        // External Email.
        $email = $emails[1];
        $this->assertEquals('external@email.com', $email->to);
        $this->assertEquals('Subject ' . $this->course->shortname, $email->subject);
        $this->assertStringContainsString('Plain content ' . $this->course->fullname, quoted_printable_decode($email->body));
        $this->assertStringContainsString('HTML content ' . $this->course->fullname, quoted_printable_decode($email->body));
    }

}
