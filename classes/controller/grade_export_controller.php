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
 * Controller for the gradebook export bridge page.
 *
 * @package    local_gradefiller
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_gradefiller\controller;

defined('MOODLE_INTERNAL') || die();

use moodle_url;

/**
 * Coordinates the gradebook export bridge workflow.
 *
 * @package    local_gradefiller
 */
class grade_export_controller {
    /** @var grade_export_context_loader */
    private grade_export_context_loader $contextloader;

    /** @var grade_export_page_renderer */
    private grade_export_page_renderer $pagerenderer;

    /** @var grade_export_submission_service */
    private grade_export_submission_service $submissionservice;

    /**
     * Constructor.
     *
     * @param grade_export_context_loader|null $contextloader
     * @param grade_export_page_renderer|null $pagerenderer
     * @param grade_export_submission_service|null $submissionservice
     */
    public function __construct(
        ?grade_export_context_loader $contextloader = null,
        ?grade_export_page_renderer $pagerenderer = null,
        ?grade_export_submission_service $submissionservice = null
    ) {
        $this->contextloader = $contextloader ?? new grade_export_context_loader();
        $this->pagerenderer = $pagerenderer ?? new grade_export_page_renderer();
        $this->submissionservice = $submissionservice ?? new grade_export_submission_service();
    }

    /**
     * Handle the request and render the export bridge page.
     *
     * @param int $courseid
     * @param string $selectedspreadsheetkey
     * @return void
     */
    public function handle(int $courseid, string $selectedspreadsheetkey): void {
        $request = $this->contextloader->load($courseid, $selectedspreadsheetkey);
        $pageurl = $this->contextloader->build_page_url($courseid, $selectedspreadsheetkey)->out(false);

        $this->pagerenderer->prepare_page($request, $pageurl);

        if ($request->requiresgroupselection) {
            $this->pagerenderer->render_not_in_group($request);
            return;
        }

        if ($request->form->is_cancelled()) {
            redirect(new moodle_url('/grade/export/index.php', ['id' => $request->course->id]));
        }

        if ($data = $request->form->get_data()) {
            $this->submissionservice->process($request, $data);
        }

        $this->pagerenderer->render_form($request, $pageurl);
    }
}
