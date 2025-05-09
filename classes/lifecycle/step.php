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

namespace tool_lcnotificationstep\lifecycle;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/admin/tool/lifecycle/step/lib.php');

use core_user;
use tool_lifecycle\local\manager\settings_manager;
use tool_lifecycle\local\response\step_response;
use tool_lifecycle\settings_type;
use tool_lifecycle\step\instance_setting;
use tool_lifecycle\step\libbase;

/**
 * Email notification.
 *
 * @package    tool_lcnotificationstep
 * @copyright  2024 Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class step extends libbase {
    /**
     * Get the subplugin name.
     *
     * @return string
     */
    public function get_subpluginname() {
        return 'tool_lcnotificationstep';
    }

    /**
     * Get the description.
     *
     * @return string
     */
    public function get_plugin_description() {
        return "User notification step";
    }

    /**
     * Processes the course.
     *
     * @param int $processid the process id.
     * @param int $instanceid step instance id.
     * @param object $course the course object.
     * @return step_response
     */
    public function process_course($processid, $instanceid, $course) {
        $users = [];

        // Get subject and content from settings.
        $subjecttemplate = settings_manager::get_settings($instanceid, settings_type::STEP)['subject'];
        $contenttemplate = settings_manager::get_settings($instanceid, settings_type::STEP)['content'];
        $contenthtmltemplate = settings_manager::get_settings($instanceid, settings_type::STEP)['contenthtml'];

        try {
            // Get specified roles to send notification.
            $roles = settings_manager::get_settings($instanceid, settings_type::STEP)['roles'];
            $roles = explode(',', $roles);

            // Get all users with given roles in the course.
            $coursecontext = \context_course::instance($course->id);
            foreach ($roles as $roleid) {
                if (!empty($roleid)) {
                    $roleusers = get_role_users($roleid, $coursecontext);
                    $users = array_merge($users, $roleusers);
                }
            }
        } catch (\dml_missing_record_exception $e) {
            mtrace("The course with id {$course->id} no longer exists.");
        }

        // Get additional email addresses.
        $emails = settings_manager::get_settings($instanceid, settings_type::STEP)['emails'];
        $emails = explode(';', $emails);
        foreach ($emails as $email) {
            // Create a fake user for each  additional email address.
            $user = new \stdClass();
            $user->id = -1;
            $user->email = $email;
            $user->mailformat = 1;
            $users[] = $user;
        }

        // Send email to each user.
        foreach ($users as $user) {
            $fakeuser = $user->id === -1;

            // Replace placeholders.
            $usersubject = $this->replace_placeholders($subjecttemplate, $course->id, $fakeuser ? null : $user->id);
            $usercontent = $this->replace_placeholders($contenttemplate, $course->id, $fakeuser ? null : $user->id);
            $usercontenthtml = $this->replace_placeholders($contenthtmltemplate, $course->id, $fakeuser ? null : $user->id);

            // Email to users.
            email_to_user(
                $user,
                \core_user::get_noreply_user(),
                $usersubject,
                $usercontent,
                $usercontenthtml
            );
        }

        return step_response::proceed();
    }

    /**
     * Replace placeholders in strings.
     *
     * @param string $strings the text to replace.
     * @param int $courseid the course id.
     * @param int $userid the user id.
     * @return array|string|string[]|null
     */
    private function replace_placeholders($strings, $courseid, $userid = null) {
        $patterns = [];
        $replacements = [];

        try {
            $course = get_course($courseid);

            // Replace course short name.
            $patterns[] = '##courseshortname##';
            $replacements[] = $course->shortname;

            // Replace course short name.
            $patterns[] = '##courseid##';
            $replacements[] = $courseid;

            // Replace course full name.
            $patterns[] = '##coursefullname##';
            $replacements[] = $course->fullname;

        } catch (\dml_missing_record_exception $e) {
            mtrace("The course with id {$courseid} no longer exists.");

            // Replace course short name.
            $patterns[] = '##courseshortname##';
            $replacements[] = 'Course short name not found';

            // Replace course short name.
            $patterns[] = '##courseid##';
            $replacements[] = $courseid;

            // Replace course full name.
            $patterns[] = '##coursefullname##';
            $replacements[] = 'Course full name not found';

        }

        // Current date.
        $patterns[] = '##currentdate##';
        $replacements[] = userdate(time(), get_string('strftimedatetime', 'core_langconfig'));

        // User if specified.
        if ($userid) {
            $user = core_user::get_user($userid);
            if ($user) {
                $firstname = $user->firstname;
                $lastname = $user->lastname;
            }
        } else {
            $firstname = '';
            $lastname = '';
        }

        // Blank first name.
        $patterns[] = '##userfirstname##';
        $replacements[] = $firstname;

        // Blank last name.
        $patterns[] = '##userlastname##';
        $replacements[] = $lastname;

        $result = str_ireplace($patterns, $replacements, $strings);

        // Remove multiple spaces.
        $result = preg_replace('/\s+/', ' ', $result);

        return $result;
    }

    /**
     * Get the instance settings.
     *
     * @return array
     */
    public function instance_settings() {
        return [
            new instance_setting('roles', PARAM_SEQUENCE, true),
            new instance_setting('emails', PARAM_TEXT, true),
            new instance_setting('subject', PARAM_TEXT, true),
            new instance_setting('content', PARAM_RAW, true),
            new instance_setting('contenthtml', PARAM_RAW, true),
        ];
    }

    /**
     * Extend the add instance form definition.
     *
     * @param \moodleform $mform the form.
     */
    public function extend_add_instance_form_definition($mform) {

        // Role selection.
        $choices = [];
        $roles = get_all_roles();
        foreach ($roles as $role) {
            $choices[$role->id] = role_get_name($role);
        }

        $options = [
            'multiple' => true,
            'noselectionstring' => get_string('roles_noselection', 'tool_lcnotificationstep'),
        ];
        $mform->addElement('autocomplete', 'roles', get_string('roles'), $choices, $options);
        $mform->setType('roles', PARAM_SEQUENCE);

        // List of email address to send.
        $elementname = 'emails';
        $mform->addElement('textarea', $elementname, get_string('email_addresses', 'tool_lcnotificationstep'));
        $mform->addHelpButton($elementname, 'email_addresses', 'tool_lcnotificationstep');
        $mform->setType($elementname, PARAM_TEXT);

        // Subject.
        $elementname = 'subject';
        $mform->addElement('text', $elementname, get_string('email_subject', 'tool_lcnotificationstep'));
        $mform->addHelpButton($elementname, 'email_subject', 'tool_lcnotificationstep');
        $mform->setType($elementname, PARAM_TEXT);

        // Content.
        $elementname = 'content';
        $mform->addElement('textarea', $elementname, get_string('email_content', 'tool_lcnotificationstep'));
        $mform->addHelpButton($elementname, 'email_content', 'tool_lcnotificationstep');
        $mform->setType($elementname, PARAM_TEXT);

        // Content HTML.
        $elementname = 'contenthtml';
        $mform->addElement('editor', $elementname, get_string('email_content_html', 'tool_lcnotificationstep'));
        $mform->addHelpButton($elementname, 'email_content_html', 'tool_lcnotificationstep');
        $mform->setType($elementname, PARAM_RAW);
    }

    /**
     * This method can be overriden, to set default values to the form_step_instance.
     * It is called in definition_after_data().
     * @param \MoodleQuickForm $mform
     * @param array $settings array containing the settings from the db.
     */
    public function extend_add_instance_form_definition_after_data($mform, $settings) {
        $mform->setDefault('contenthtml',
                ['text' => isset($settings['contenthtml']) ? $settings['contenthtml'] : '', 'format' => FORMAT_HTML]);
    }
}
