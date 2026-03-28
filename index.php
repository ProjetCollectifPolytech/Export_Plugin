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
 * Grade Filler main interface.
 *
 * @package    local_gradefiller
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

use core\notification;
use core\output\notification as output_notification;
use local_gradefiller\action\process_upload;
use local_gradefiller\error\error_message_resolver;
use local_gradefiller\event\page_viewed;
use local_gradefiller\manager;
use local_gradefiller\page\upload_page;

$pagebuilder = new upload_page();

$cmid = required_param('id', PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHANUMEXT);
$actionmap = [
    'process_upload' => process_upload::class,
];

$cm = get_coursemodule_from_id('', $cmid, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$context = context_module::instance($cm->id);
$coursecontext = context_course::instance($course->id);

require_login($course, false, $cm);
if (!local_gradefiller_user_can_view($context)) {
    require_capability('local/gradefiller:view', $context);
}

$PAGE->set_url('/local/gradefiller/index.php', ['id' => $cmid]);
$PAGE->set_context($context);
$PAGE->set_pagelayout('incourse');
$PAGE->set_title(get_string('page_title', 'local_gradefiller'));
$PAGE->set_heading($course->fullname);
$PAGE->requires->css('/local/gradefiller/styles.css');

$accessdata = local_gradefiller_get_activity_access_data($cm, $coursecontext);
if ($accessdata === null) {
    throw new moodle_exception('error_activity_unsupported', 'local_gradefiller');
}

if ($action !== '') {
    if (!isset($actionmap[$action])) {
        redirect(
            new moodle_url('/local/gradefiller/index.php', ['id' => $cmid]),
            get_string('error_invalid_action', 'local_gradefiller'),
            null,
            output_notification::NOTIFY_ERROR
        );
    }

    try {
        $handler = new $actionmap[$action]($cmid);
        $handler->execute();
        redirect(new moodle_url('/local/gradefiller/index.php', ['id' => $cmid]));
    } catch (Exception $e) {
        redirect(
            new moodle_url('/local/gradefiller/index.php', ['id' => $cmid]),
            error_message_resolver::to_user_message($e),
            null,
            output_notification::NOTIFY_ERROR
        );
    }
}

if (isset($SESSION->gradefiller_success)) {
    notification::success($SESSION->gradefiller_success);
    unset($SESSION->gradefiller_success);
}
if (isset($SESSION->gradefiller_error)) {
    notification::error($SESSION->gradefiller_error);
    unset($SESSION->gradefiller_error);
}

$manager = new manager();
$canprocess = local_gradefiller_user_can_process($context);
$formats = $manager->get_available_formats();

$driver = $manager->get_driver_for_cm($cm);
$supportsanonymous = ($driver !== null);

page_viewed::create([
    'objectid' => $cm->id,
    'courseid' => $course->id,
    'context' => $context,
    'other' => [
        'supports_anonymous' => $supportsanonymous ? 1 : 0,
        'can_process' => $canprocess ? 1 : 0,
    ],
])->trigger();

$templatedata = $pagebuilder->build($cmid, $cm, $formats, $canprocess, $driver, $CFG->wwwroot);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('page_title', 'local_gradefiller'));
echo $OUTPUT->render_from_template('local_gradefiller/upload_form', $templatedata);
echo $OUTPUT->footer();
