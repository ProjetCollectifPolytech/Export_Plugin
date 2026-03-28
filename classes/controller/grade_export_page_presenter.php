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
 * Configures and renders the grade export bridge page.
 *
 * @package    local_gradefiller
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_gradefiller\controller;

use core_grades\output\export_action_bar;
use html_writer;
use local_gradefiller\integration\grade_export_bridge_config_builder;

/**
 * Prepares and outputs the grade export bridge page.
 *
 * @package    local_gradefiller
 */
class grade_export_page_presenter {
    /** @var grade_export_bridge_config_builder */
    private grade_export_bridge_config_builder $bridgeconfigbuilder;

    /**
     * Constructor.
     *
     * @param grade_export_bridge_config_builder|null $bridgeconfigbuilder
     */
    public function __construct(?grade_export_bridge_config_builder $bridgeconfigbuilder = null) {
        $this->bridgeconfigbuilder = $bridgeconfigbuilder ?? new grade_export_bridge_config_builder();
    }

    /**
     * Configure Moodle page globals for the export bridge.
     *
     * @param grade_export_request_context $request
     * @param string $pageurl
     * @return void
     */
    public function prepare_page(grade_export_request_context $request, string $pageurl): void {
        global $PAGE;

        $PAGE->set_url($pageurl);
        $PAGE->set_pagelayout('incourse');
        $PAGE->set_title(get_string('gradebook_export_page_title', 'local_gradefiller'));
        $PAGE->set_heading($request->course->fullname);
        $PAGE->set_pagetype('grade-export-gradefiller-index');
    }

    /**
     * Render the group warning page and stop the request.
     *
     * @param grade_export_request_context $request
     * @return void
     */
    public function render_not_in_group(grade_export_request_context $request): void {
        global $OUTPUT;

        $activeplugin = $this->bridgeconfigbuilder->get_action_plugin($request->course->id);
        $actionbar = new export_action_bar($request->context, null, $activeplugin);
        print_grade_page_head(
            $request->course->id,
            'export',
            $activeplugin,
            get_string('gradebook_export_page_title', 'local_gradefiller'),
            false,
            false,
            true,
            null,
            null,
            null,
            $actionbar
        );

        echo $OUTPUT->heading(get_string('notingroup'));
        echo $OUTPUT->footer();
    }

    /**
     * Render the export bridge form page.
     *
     * @param grade_export_request_context $request
     * @param string $pageurl
     * @return void
     */
    public function render_form(grade_export_request_context $request, string $pageurl): void {
        global $OUTPUT;

        $activeplugin = $this->bridgeconfigbuilder->get_action_plugin($request->course->id);
        $actionbar = new export_action_bar($request->context, null, $activeplugin);
        print_grade_page_head(
            $request->course->id,
            'export',
            $activeplugin,
            get_string('gradebook_export_page_title', 'local_gradefiller'),
            false,
            false,
            true,
            null,
            null,
            null,
            $actionbar
        );

        groups_print_course_menu($request->course, $pageurl);
        echo html_writer::div('', 'clearer');
        echo $OUTPUT->notification(get_string('gradebook_export_intro', 'local_gradefiller'), 'info');
        $request->form->display();
        echo $OUTPUT->footer();
    }
}
