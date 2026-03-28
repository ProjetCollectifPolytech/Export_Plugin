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
 * Activity-level Moodle integration for Grade Filler.
 *
 * @package    local_gradefiller
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_gradefiller\integration;


use cm_info;
use context;
use context_course;
use html_writer;
use moodle_page;
use navigation_node;
use settings_navigation;
use stdClass;

/**
 * Encapsulates activity access metadata, buttons and navigation hooks.
 *
 * @package    local_gradefiller
 */
class activity_navigation_integration {
    /** @var activity_access_resolver */
    private activity_access_resolver $accessresolver;

    /**
     * Constructor.
     *
     * @param activity_access_resolver|null $accessresolver
     */
    public function __construct(?activity_access_resolver $accessresolver = null) {
        $this->accessresolver = $accessresolver ?? new activity_access_resolver();
    }

    /**
     * Check whether the current user can view Grade Filler.
     *
     * @param context $context
     * @return bool
     */
    public function user_can_view(context $context): bool {
        return $this->accessresolver->user_can_view($context);
    }

    /**
     * Check whether the current user can process files in Grade Filler.
     *
     * @param context $context
     * @return bool
     */
    public function user_can_process(context $context): bool {
        return $this->accessresolver->user_can_process($context);
    }

    /**
     * Resolve activity access metadata for Grade Filler links and buttons.
     *
     * @param stdClass|cm_info $cm
     * @param context_course $coursecontext
     * @return array|null
     */
    public function get_activity_access_data($cm, context_course $coursecontext): ?array {
        return $this->accessresolver->resolve($cm, $coursecontext);
    }

    /**
     * Determine whether the current page should display a prominent activity button.
     *
     * @param moodle_page $page
     * @return bool
     */
    public function should_add_activity_button(moodle_page $page): bool {
        if (empty($page->cm) || empty($page->activityname)) {
            return false;
        }

        if ((int)$page->context->contextlevel !== CONTEXT_MODULE) {
            return false;
        }

        return str_starts_with((string)$page->pagetype, 'mod-' . $page->activityname . '-');
    }

    /**
     * Add a visible page button for supported activity pages.
     *
     * @param moodle_page $page
     * @param array $accessdata
     * @return void
     */
    public function add_activity_button(moodle_page $page, array $accessdata): void {
        if (strpos((string)$page->button, 'local-gradefiller-activity-button') !== false) {
            return;
        }

        $page->set_button($this->render_activity_button($accessdata) . (string)$page->button);
    }

    /**
     * Extend the activity settings navigation with a Grade Filler link.
     *
     * @param settings_navigation $settingsnav
     * @param moodle_page $page
     * @return void
     */
    public function extend_settings_navigation(settings_navigation $settingsnav, moodle_page $page): void {
        $accessdata = $this->get_page_access_data($page);
        if ($accessdata === null) {
            return;
        }

        if ($this->should_add_activity_button($page)) {
            $this->add_activity_button($page, $accessdata);
        }

        $settingsnode = $settingsnav->find('modulesettings', navigation_node::TYPE_SETTING);
        if ($settingsnode instanceof navigation_node) {
            $this->add_settings_navigation_node($settingsnode, $accessdata);
        }
    }

    /**
     * Add the module-page secondary navigation link.
     *
     * @param navigation_node $navigation
     * @param stdClass $course
     * @param cm_info $cm
     * @return void
     */
    public function extend_navigation_module(navigation_node $navigation, stdClass $course, cm_info $cm): void {
        $coursecontext = context_course::instance($course->id);
        $accessdata = $this->get_activity_access_data($cm, $coursecontext);
        if ($accessdata === null) {
            return;
        }

        $this->add_navigation_node($navigation, $accessdata, navigation_node::TYPE_CUSTOM);
    }

    /**
     * Resolve access data directly from the current page when possible.
     *
     * @param moodle_page $page
     * @return array|null
     */
    private function get_page_access_data(moodle_page $page): ?array {
        if ($page->cm === null || $page->course === null) {
            return null;
        }

        $coursecontext = context_course::instance($page->course->id);
        return $this->get_activity_access_data($page->cm, $coursecontext);
    }

    /**
     * Build the HTML for the activity call-to-action button.
     *
     * @param array $accessdata
     * @return string
     */
    private function render_activity_button(array $accessdata): string {
        $buttonlink = html_writer::link(
            $accessdata['url'],
            $accessdata['label'],
            ['class' => 'btn btn-primary']
        );

        return html_writer::div($buttonlink, 'singlebutton local-gradefiller-activity-button');
    }

    /**
     * Add a settings-navigation link when it does not already exist.
     *
     * @param navigation_node $settingsnode
     * @param array $accessdata
     * @return void
     */
    private function add_settings_navigation_node(navigation_node $settingsnode, array $accessdata): void {
        if ($settingsnode->find($accessdata['nodekey'], navigation_node::TYPE_SETTING)) {
            return;
        }

        $this->add_navigation_node($settingsnode, $accessdata, navigation_node::TYPE_SETTING);
    }

    /**
     * Add a navigation node described by access metadata.
     *
     * @param navigation_node $navigation
     * @param array $accessdata
     * @param int $nodetype
     * @return void
     */
    private function add_navigation_node(navigation_node $navigation, array $accessdata, int $nodetype): void {
        $navigation->add(
            $accessdata['label'],
            $accessdata['url'],
            $nodetype,
            null,
            $accessdata['nodekey'],
            $accessdata['icon']
        );
    }
}
