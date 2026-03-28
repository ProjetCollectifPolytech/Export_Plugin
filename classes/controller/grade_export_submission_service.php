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

namespace local_gradefiller\controller;


use core_form\util as form_util;
use local_gradefiller\export\course_export_manager;
use local_gradefiller\export\course_spreadsheet_export_manager;
use local_gradefiller\form\course_export_form;
use local_gradefiller\util\download_handler;
use local_gradefiller\util\file_handler;
use moodle_exception;
use Throwable;

/**
 * Handles one submitted grade export bridge form.
 *
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class grade_export_submission_service {
    /** @var course_export_manager */
    private course_export_manager $courseexportmanager;

    /** @var course_spreadsheet_export_manager */
    private course_spreadsheet_export_manager $spreadsheetexportmanager;

    /**
     * Constructor.
     *
     * @param course_export_manager|null $courseexportmanager
     * @param course_spreadsheet_export_manager|null $spreadsheetexportmanager
     */
    public function __construct(
        ?course_export_manager $courseexportmanager = null,
        ?course_spreadsheet_export_manager $spreadsheetexportmanager = null
    ) {
        $this->courseexportmanager = $courseexportmanager ?? new course_export_manager();
        $this->spreadsheetexportmanager = $spreadsheetexportmanager ?? new course_spreadsheet_export_manager();
    }

    /**
     * Process one valid export form submission and trigger the download.
     *
     * @param grade_export_request_context $request
     * @param \stdClass $data
     * @return void
     */
    public function process(grade_export_request_context $request, \stdClass $data): void {
        $originalfilename = $request->form->get_new_filename('templatefile');
        $tempfile = $this->create_temp_template_file($request->form, $request->course, $originalfilename);

        try {
            if ($request->selectedspreadsheet !== null) {
                $result = $this->spreadsheetexportmanager->process_export(
                    $tempfile,
                    $request->selectedspreadsheet,
                    $request->course,
                    $request->currentgroup,
                    $data,
                    $originalfilename
                );
            } else {
                $result = $this->courseexportmanager->process_export(
                    $tempfile,
                    $data->gradefiller_format,
                    $request->course,
                    $request->currentgroup,
                    $data,
                    $originalfilename
                );
            }

            form_util::form_download_complete();
            file_handler::cleanup($tempfile);
            download_handler::send_file($result['filepath'], $result['downloadname']);
        } catch (Throwable $e) {
            file_handler::cleanup($tempfile);
            throw $e;
        }
    }

    /**
     * Persist the uploaded template into Moodle temp storage.
     *
     * @param course_export_form $form
     * @param \stdClass $course
     * @param string $originalfilename
     * @return string
     */
    private function create_temp_template_file(course_export_form $form, \stdClass $course, string $originalfilename): string {
        global $CFG;

        $tempdir = make_temp_directory('gradefiller');
        $tempfile = $tempdir . '/' . uniqid('gradeexport_', true) . '_' . clean_filename($originalfilename);

        if (!$form->save_file('templatefile', $tempfile, true)) {
            throw new moodle_exception('error_export_template_required', 'local_gradefiller');
        }

        return $tempfile;
    }
}
