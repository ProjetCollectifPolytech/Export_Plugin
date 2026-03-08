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
 * Library functions for local_gradefiller
 *
 * @package    local_gradefiller
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/gradelib.php');

/**
 * Extend the module navigation with a link to grade filler
 *
 * @param settings_navigation $settingsnav The settings navigation object
 * @param navigation_node $node The navigation node to extend
 */
function local_gradefiller_extend_settings_navigation($settingsnav, $node) {
    global $PAGE, $DB, $CFG;

    // Only on module pages.
    if ($PAGE->cm === null) {
        return;
    }

    $cm = $PAGE->cm;
    $course = $PAGE->course;

    $coursecontext = context_course::instance($course->id);

    // Check if user has permission.
    if (!has_capability('local/gradefiller:use', $coursecontext)) {
        return;
    }

    // Check if this activity can store grades (has grade item OR has anonymous driver support).
    $gradeitem = grade_item::fetch([
        'itemtype' => 'mod',
        'itemmodule' => $cm->modname,
        'iteminstance' => $cm->instance,
        'courseid' => $course->id,
    ]);

    // Check if there's a driver that supports anonymous grades for this activity.
    $manager = new \local_gradefiller\manager();
    $driver = $manager->get_driver_for_cm($cm);
    $hasdriver = ($driver !== null);

    // Show link if activity has grade item OR has anonymous driver support.
    if (!$gradeitem && !$hasdriver) {
        return;
    }

    // Add link to activity administration block.
    if ($settingsnode = $settingsnav->find('modulesettings', navigation_node::TYPE_SETTING)) {
        $url = new moodle_url('/local/gradefiller/index.php', [
            'id' => $cm->id,
        ]);
        $settingsnode->add(
            get_string('fill_grades', 'local_gradefiller'),
            $url,
            navigation_node::TYPE_SETTING,
            null,
            'gradefiller_fill',
            new pix_icon('i/grades', '')
        );
    }
}

/**
 * Extend the navigation of an activity module
 * Adds a direct link in the secondary navigation
 *
 * @param navigation_node $navigation The navigation node to extend
 * @param stdClass $course The course object
 * @param context $context The context of the course
 * @param stdClass $cm The course module object
 */
function local_gradefiller_extend_navigation_course($navigation, $course, $context) {
    global $PAGE;

    // Only show on activity pages.
    if ($PAGE->cm === null) {
        return;
    }

    $cm = $PAGE->cm;

    // Check permission.
    if (!has_capability('local/gradefiller:use', $context)) {
        return;
    }

    // Check if activity has grades.
    $gradeitem = grade_item::fetch([
        'itemtype' => 'mod',
        'itemmodule' => $cm->modname,
        'iteminstance' => $cm->instance,
        'courseid' => $course->id,
    ]);

    if (!$gradeitem) {
        return;
    }

    // Add link to main navigation.
    $url = new moodle_url('/local/gradefiller/index.php', ['id' => $cm->id]);
    $node = navigation_node::create(
        get_string('fill_grades', 'local_gradefiller'),
        $url,
        navigation_node::TYPE_CUSTOM,
        null,
        'gradefiller',
        new pix_icon('i/grades', '')
    );
    $navigation->add_node($node);
}
