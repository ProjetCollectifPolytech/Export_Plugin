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

defined('MOODLE_INTERNAL') || die();

use cm_info;
use context;
use context_course;
use context_module;
use grade_item;
use local_gradefiller\manager;
use local_gradefiller\manager_factory;
use moodle_page;
use moodle_url;
use navigation_node;
use pix_icon;
use settings_navigation;
use stdClass;

/**
 * Encapsulates activity access metadata, buttons and navigation hooks.
 *
 * @package    local_gradefiller
 */
class activity_navigation_integration {
    /** @var manager */
    private manager $manager;

    /**
     * Constructor.
     *
     * @param manager|null $manager
     */
    public function __construct(?manager $manager = null) {
        $this->manager = $manager ?? manager_factory::create_default();
    }

    /**
     * Check whether the current user can view Grade Filler.
     *
     * @param context $context
     * @return bool
     */
    public function user_can_view(context $context): bool {
        return has_capability('local/gradefiller:view', $context)
            || has_capability('local/gradefiller:use', $context);
    }

    /**
     * Check whether the current user can process files in Grade Filler.
     *
     * @param context $context
     * @return bool
     */
    public function user_can_process(context $context): bool {
        return has_capability('local/gradefiller:process', $context)
            || has_capability('local/gradefiller:use', $context);
    }

    /**
     * Resolve activity access metadata for Grade Filler links and buttons.
     *
     * @param stdClass|cm_info $cm
     * @param context_course $coursecontext
     * @return array|null
     */
    public function get_activity_access_data($cm, context_course $coursecontext): ?array {
        $modulecontext = context_module::instance($cm->id);
        if (!$this->user_can_view($modulecontext)) {
            return null;
        }

        $gradeitem = grade_item::fetch([
            'itemtype' => 'mod',
            'itemmodule' => $cm->modname,
            'iteminstance' => $cm->instance,
            'courseid' => $coursecontext->instanceid,
        ]);
        $driver = $this->manager->get_driver_for_cm($cm);

        if (!$gradeitem && $driver === null) {
            return null;
        }

        return [
            'label' => get_string('fill_grades', 'local_gradefiller'),
            'url' => new moodle_url('/local/gradefiller/index.php', ['id' => $cm->id]),
            'nodekey' => 'gradefiller_fill',
            'icon' => new pix_icon('i/grades', ''),
            'can_process' => $this->user_can_process($modulecontext),
            'has_grade_item' => (bool)$gradeitem,
            'supports_anonymous' => ($driver !== null),
            'driver_name' => $driver ? $driver->get_name() : '',
        ];
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

        $buttonlink = \html_writer::link(
            $accessdata['url'],
            $accessdata['label'],
            ['class' => 'btn btn-primary']
        );
        $buttonhtml = \html_writer::div($buttonlink, 'singlebutton local-gradefiller-activity-button');

        $page->set_button($buttonhtml . (string)$page->button);
    }

    /**
     * Extend the activity settings navigation with a Grade Filler link.
     *
     * @param settings_navigation $settingsnav
     * @param moodle_page $page
     * @return void
     */
    public function extend_settings_navigation(settings_navigation $settingsnav, moodle_page $page): void {
        if ($page->cm === null || $page->course === null) {
            return;
        }

        $coursecontext = context_course::instance($page->course->id);
        $accessdata = $this->get_activity_access_data($page->cm, $coursecontext);
        if ($accessdata === null) {
            return;
        }

        if ($this->should_add_activity_button($page)) {
            $this->add_activity_button($page, $accessdata);
        }

        if ($settingsnode = $settingsnav->find('modulesettings', navigation_node::TYPE_SETTING)) {
            if (!$settingsnode->find($accessdata['nodekey'], navigation_node::TYPE_SETTING)) {
                $settingsnode->add(
                    $accessdata['label'],
                    $accessdata['url'],
                    navigation_node::TYPE_SETTING,
                    null,
                    $accessdata['nodekey'],
                    $accessdata['icon']
                );
            }
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

        $navigation->add(
            $accessdata['label'],
            $accessdata['url'],
            navigation_node::TYPE_CUSTOM,
            null,
            $accessdata['nodekey'],
            $accessdata['icon']
        );
    }
}
