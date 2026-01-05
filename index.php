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
 * Grade Filler - Main interface
 *
 * @package    local_gradefiller
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_gradefiller\manager;

// Get parameters.
$cmid = required_param('id', PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHANUMEXT);

// Get course module and course.
$cm = get_coursemodule_from_id('', $cmid, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$context = context_module::instance($cm->id);

// Security checks.
require_login($course, false, $cm);
require_capability('local/gradefiller:use', $context);

// Page setup.
$PAGE->set_url('/local/gradefiller/index.php', ['id' => $cmid]);
$PAGE->set_context($context);
$PAGE->set_pagelayout('incourse');
$PAGE->set_title(get_string('page_title', 'local_gradefiller'));
$PAGE->set_heading($course->fullname);
$PAGE->requires->css('/local/gradefiller/styles.css');

// Handle actions via action handlers.
if ($action && $_SERVER['REQUEST_METHOD'] === 'POST' && confirm_sesskey()) {
    $actionclass = "\\local_gradefiller\\action\\{$action}";
    
    if (class_exists($actionclass)) {
        $handler = new $actionclass($cmid);
        $handler->execute();
        // Note: action handlers may terminate execution (e.g., for downloads).
    } else {
        throw new \moodle_exception('invalidaction', 'local_gradefiller');
    }
}

// Display any messages from session.
if (isset($SESSION->gradefiller_success)) {
    \core\notification::success($SESSION->gradefiller_success);
    unset($SESSION->gradefiller_success);
}
if (isset($SESSION->gradefiller_error)) {
    \core\notification::error($SESSION->gradefiller_error);
    unset($SESSION->gradefiller_error);
}

// Initialize manager.
$manager = new manager();

// Get available formats.
$formats = $manager->get_available_formats();
$formatoptions = [];
foreach ($formats as $format) {
    $formatoptions[] = [
        'key' => $format->get_key(),
        'name' => $format->get_name(),
        'description' => $format->get_description(),
    ];
}

// Check if activity supports anonymous grades (has a driver).
$driver = $manager->get_driver_for_cm($cm);
$supportsanonymous = ($driver !== null);

// Prepare template data.
$templatedata = [
    'cmid' => $cmid,
    'activity_name' => $cm->name,
    'activity_type' => get_string('modulename', $cm->modname),
    'formats' => $formatoptions,
    'supports_anonymous' => $supportsanonymous,
    'driver_name' => $supportsanonymous ? $driver->get_name() : '',
    'sesskey' => sesskey(),
    'wwwroot' => $CFG->wwwroot,
];

// Output page.
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('page_title', 'local_gradefiller'));
echo $OUTPUT->render_from_template('local_gradefiller/upload_form', $templatedata);
echo $OUTPUT->footer();
