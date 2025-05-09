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
 * Strings for Email notification.
 *
 * @package    tool_lcnotificationstep
 * @copyright  2024 Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['pluginname'] = 'User notification step';
$string['privacy:metadata'] = 'The plugin does not store any personal data.';
$string['roles_noselection'] = 'Please select one or more roles.';
$string['emptyroles'] = 'Roles must not be empty.';

// Email.
$placeholder = '<p>' . 'You can use the following placeholders:'
    . '<br>' . 'Course short name: ##courseshortname##'
    . '<br>' . 'Course fullname : ##coursefullname##'
    . '<br>' . 'Course id : ##courseid##'
    . '<br>' . 'Current date: ##currentdate##'
    . '<br>' . 'User first name: ##userfirstname##'
    . '<br>' . 'User last name: ##userlastname##'
    . '</p>';

$string['email_addresses'] = 'Additional email addresses';
$string['email_addresses_help'] = 'Additional email addresses (semicolon separated) to send the notification to.';

$string['email_subject'] = 'Subject template';
$string['email_subject_help'] = 'Set the template for the subject of the email.';

$string['email_content'] = 'Content plain text template';
$string['email_content_help'] = 'Set the template for the content of the email (plain text, alternatively you can use HTML template for HTML email below)' . $placeholder;

$string['email_content_html'] = 'Content HTML Template';
$string['email_content_html_help'] = 'Set the html template for the content of the email (HTML email, will be used instead of plaintext field if not empty!)' . $placeholder;
