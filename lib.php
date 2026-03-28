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
 * Library functions for local_gradefiller.
 *
 * @package    local_gradefiller
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/gradelib.php');
require_once($CFG->dirroot . '/grade/lib.php');

use local_gradefiller\integration\activity_navigation_integration;
use local_gradefiller\integration\grade_export_bridge_integration;

/**
 * Return the shared activity navigation integration instance.
 *
 * @return activity_navigation_integration
 */
function local_gradefiller_get_activity_navigation_integration(): activity_navigation_integration {
    static $integration = null;

    if ($integration === null) {
        $integration = new activity_navigation_integration();
    }

    return $integration;
}

/**
 * Return the shared grade export bridge integration instance.
 *
 * @return grade_export_bridge_integration
 */
function local_gradefiller_get_grade_export_bridge_integration(): grade_export_bridge_integration {
    static $integration = null;

    if ($integration === null) {
        $integration = new grade_export_bridge_integration();
    }

    return $integration;
}

/**
 * Check whether the current user can view Grade Filler.
 *
 * Supports the new split capabilities while remaining compatible with the
 * legacy local/gradefiller:use capability.
 *
 * @param context $context Context to check
 * @return bool
 */
function local_gradefiller_user_can_view(context $context): bool {
    return local_gradefiller_get_activity_navigation_integration()->user_can_view($context);
}

/**
 * Check whether the current user can process files in Grade Filler.
 *
 * Supports the new split capabilities while remaining compatible with the
 * legacy local/gradefiller:use capability.
 *
 * @param context $context Context to check
 * @return bool
 */
function local_gradefiller_user_can_process(context $context): bool {
    return local_gradefiller_get_activity_navigation_integration()->user_can_process($context);
}

/**
 * Resolve activity access metadata for Grade Filler links and buttons.
 *
 * @param \stdClass|\cm_info $cm Course module
 * @param context_course $coursecontext Course context
 * @return array|null
 */
function local_gradefiller_get_activity_access_data($cm, context_course $coursecontext): ?array {
    return local_gradefiller_get_activity_navigation_integration()->get_activity_access_data($cm, $coursecontext);
}

/**
 * Determine whether the current page should display a prominent activity button.
 *
 * @return bool
 */
function local_gradefiller_should_add_activity_button(): bool {
    global $PAGE;
    return local_gradefiller_get_activity_navigation_integration()->should_add_activity_button($PAGE);
}

/**
 * Add a visible page button for supported activity pages.
 *
 * @param array $accessdata Access data returned by local_gradefiller_get_activity_access_data()
 * @return void
 */
function local_gradefiller_add_activity_button(array $accessdata): void {
    global $PAGE;
    local_gradefiller_get_activity_navigation_integration()->add_activity_button($PAGE, $accessdata);
}

/**
 * Extend the activity settings navigation with a Grade Filler link.
 *
 * @param settings_navigation $settingsnav The settings navigation object
 * @param context $context The current page context
 * @return void
 */
function local_gradefiller_extend_settings_navigation($settingsnav, $context) {
    global $PAGE;
    local_gradefiller_get_activity_navigation_integration()->extend_settings_navigation($settingsnav, $PAGE);
}

/**
 * Add link to secondary navigation on supported activity pages.
 *
 * @param navigation_node $navigation The navigation node to extend
 * @param stdClass $course The course object
 * @param cm_info $cm The course module info object
 * @return void
 */
function local_gradefiller_extend_navigation_module($navigation, $course, $cm) {
    local_gradefiller_get_activity_navigation_integration()->extend_navigation_module($navigation, $course, $cm);
}

/**
 * Backward-compatible wrapper for older navigation integrations.
 *
 * @param navigation_node $navigation The navigation node to extend
 * @param stdClass $course The course object
 * @param context $context The context of the course
 * @return void
 */
function local_gradefiller_extend_navigation_course($navigation, $course, $context) {
    global $PAGE;

    if ($PAGE->cm === null) {
        return;
    }

    local_gradefiller_extend_navigation_module($navigation, $course, $PAGE->cm);
}

/**
 * Return the local Grade Filler course export URL.
 *
 * @param int $courseid Course ID
 * @param string|null $spreadsheetformat Optional spreadsheet format key carried by the export bridge
 * @return moodle_url
 */
function local_gradefiller_get_grade_export_url(int $courseid, ?string $spreadsheetformat = null): moodle_url {
    return local_gradefiller_get_grade_export_bridge_integration()->get_grade_export_url($courseid, $spreadsheetformat);
}

/**
 * Return the Grade Filler spreadsheet formats that should be exposed in Moodle's
 * native "Export as" menu.
 *
 * @param int $courseid Course ID
 * @return array<int, array<string, string>>
 */
function local_gradefiller_get_grade_export_bridge_options(int $courseid): array {
    return local_gradefiller_get_grade_export_bridge_integration()->get_bridge_options($courseid);
}

/**
 * Check whether the current user can access the Grade Filler grade export bridge.
 *
 * This bridge extends Moodle's native grade export workflow, so it follows the
 * same course-level export permission model without changing the existing
 * activity-based Grade Filler permissions.
 *
 * @param context_course $context Course context
 * @return bool
 */
function local_gradefiller_can_access_grade_export_bridge(context_course $context): bool {
    return local_gradefiller_get_grade_export_bridge_integration()->can_access_grade_export_bridge($context);
}

/**
 * Resolve a native grade export plugin key for the action bar on Grade Filler pages.
 *
 * @param int $courseid Course ID
 * @return string
 */
function local_gradefiller_get_grade_export_action_plugin(int $courseid): string {
    return local_gradefiller_get_grade_export_bridge_integration()->get_action_plugin($courseid);
}

/**
 * Determine whether the current page is a grade export page that should expose the Grade Filler bridge.
 *
 * @return bool
 */
function local_gradefiller_is_grade_export_page(): bool {
    return local_gradefiller_get_grade_export_bridge_integration()->is_grade_export_page();
}

/**
 * Build the configuration passed to the grade export bridge AMD module.
 *
 * @param context_course $context Course context
 * @return array<string, mixed>
 */
function local_gradefiller_get_grade_export_bridge_config(context_course $context): array {
    return local_gradefiller_get_grade_export_bridge_integration()->get_bridge_config($context);
}

/**
 * Require the bridge AMD module exactly once per request.
 *
 * @param context_course $context Course context
 * @return void
 */
function local_gradefiller_require_grade_export_bridge(context_course $context): void {
    local_gradefiller_get_grade_export_bridge_integration()->require_bridge($context);
}

/**
 * Inject the Grade Filler entry into Moodle's native grade export selector.
 *
 * @return string
 */
function local_gradefiller_before_standard_top_of_body_html(): string {
    return local_gradefiller_get_grade_export_bridge_integration()->before_standard_top_of_body_html();
}

/**
 * Inject the Grade Filler bridge again before footer for pages where late DOM
 * initialisation can replace parts of the export action bar.
 *
 * @return string
 */
function local_gradefiller_before_footer(): string {
    return local_gradefiller_get_grade_export_bridge_integration()->before_footer();
}
