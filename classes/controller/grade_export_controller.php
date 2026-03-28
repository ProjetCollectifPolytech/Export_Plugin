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

require_once($CFG->dirroot . '/local/gradefiller/lib.php');

use core_form\util as form_util;
use core_grades\output\export_action_bar;
use local_gradefiller\export\course_export_manager;
use local_gradefiller\export\course_spreadsheet_export_manager;
use local_gradefiller\form\course_export_form;
use local_gradefiller\manager;
use local_gradefiller\manager_factory;
use local_gradefiller\util\download_handler;
use local_gradefiller\util\file_handler;
use moodle_exception;
use moodle_url;
use Throwable;

/**
 * Coordinates the gradebook export bridge workflow.
 *
 * @package    local_gradefiller
 */
class grade_export_controller {
    /** @var manager */
    private manager $spreadsheetmanager;

    /** @var course_export_manager */
    private course_export_manager $courseexportmanager;

    /**
     * Constructor.
     *
     * @param manager|null $spreadsheetmanager
     * @param course_export_manager|null $courseexportmanager
     */
    public function __construct(?manager $spreadsheetmanager = null, ?course_export_manager $courseexportmanager = null) {
        $this->spreadsheetmanager = $spreadsheetmanager ?? manager_factory::create_default();
        $this->courseexportmanager = $courseexportmanager ?? new course_export_manager();
    }

    /**
     * Handle the request and render the export bridge page.
     *
     * @param int $courseid
     * @param string $selectedspreadsheetkey
     * @return void
     */
    public function handle(int $courseid, string $selectedspreadsheetkey): void {
        global $DB, $PAGE, $OUTPUT;

        $pageparams = ['id' => $courseid];
        if ($selectedspreadsheetkey !== '') {
            $pageparams['spreadsheetformat'] = $selectedspreadsheetkey;
        }
        $PAGE->set_url('/local/gradefiller/gradeexport.php', $pageparams);

        $course = $DB->get_record('course', ['id' => $courseid]);
        if (!$course) {
            throw new moodle_exception('invalidcourseid');
        }

        require_login($course);
        $context = \context_course::instance($courseid);
        if (!local_gradefiller_can_access_grade_export_bridge($context)) {
            throw new moodle_exception('error_no_permission', 'local_gradefiller');
        }

        export_verify_grades($course->id);

        $PAGE->set_pagelayout('incourse');
        $PAGE->set_title(get_string('gradebook_export_page_title', 'local_gradefiller'));
        $PAGE->set_heading($course->fullname);
        $PAGE->set_pagetype('grade-export-gradefiller-index');

        $selectedspreadsheet = $this->resolve_selected_spreadsheet($selectedspreadsheetkey);
        $form = $this->build_form($course, $selectedspreadsheet, $selectedspreadsheetkey);

        $groupmode = groups_get_course_groupmode($course);
        $currentgroup = groups_get_course_group($course, true);
        if (($groupmode == SEPARATEGROUPS) && !$currentgroup && !has_capability('moodle/site:accessallgroups', $context)) {
            $this->render_not_in_group($course, $context);
            return;
        }

        if ($form->is_cancelled()) {
            redirect(new moodle_url('/grade/export/index.php', ['id' => $course->id]));
        }

        if ($data = $form->get_data()) {
            $this->process_form_submission($form, $course, $selectedspreadsheet, $currentgroup, $data);
        }

        $this->render_form($course, $context, $form, $selectedspreadsheetkey);
    }

    /**
     * Resolve the selected spreadsheet strategy.
     *
     * @param string $selectedspreadsheetkey
     * @return object|null
     */
    private function resolve_selected_spreadsheet(string $selectedspreadsheetkey) {
        if ($selectedspreadsheetkey === '') {
            return null;
        }

        $selectedspreadsheet = $this->spreadsheetmanager->get_format($selectedspreadsheetkey);
        if ($selectedspreadsheet === null) {
            throw new moodle_exception('error_format_not_found', 'local_gradefiller', '', $selectedspreadsheetkey);
        }

        return $selectedspreadsheet;
    }

    /**
     * Build the Moodle export form.
     *
     * @param \stdClass $course
     * @param object|null $selectedspreadsheet
     * @param string $selectedspreadsheetkey
     * @return course_export_form
     */
    private function build_form(\stdClass $course, $selectedspreadsheet, string $selectedspreadsheetkey): course_export_form {
        return new course_export_form(
            local_gradefiller_get_grade_export_url($course->id, $selectedspreadsheetkey ?: null),
            [
                'course' => $course,
                'formats' => $this->courseexportmanager->get_available_formats(),
                'selectedspreadsheet' => $selectedspreadsheet,
            ]
        );
    }

    /**
     * Render the group warning page and stop the request.
     *
     * @param \stdClass $course
     * @param \context_course $context
     * @return void
     */
    private function render_not_in_group(\stdClass $course, \context_course $context): void {
        global $OUTPUT;

        $activeplugin = local_gradefiller_get_grade_export_action_plugin($course->id);
        $actionbar = new export_action_bar($context, null, $activeplugin);
        print_grade_page_head(
            $course->id,
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
     * Process the submitted export form and trigger the file download.
     *
     * @param course_export_form $form
     * @param \stdClass $course
     * @param object|null $selectedspreadsheet
     * @param int $currentgroup
     * @param \stdClass $data
     * @return void
     */
    private function process_form_submission(
        course_export_form $form,
        \stdClass $course,
        $selectedspreadsheet,
        int $currentgroup,
        \stdClass $data
    ): void {
        $originalfilename = $form->get_new_filename('templatefile');
        $tempfile = $this->create_temp_template_file($form, $course, $originalfilename);

        try {
            if ($selectedspreadsheet !== null) {
                $result = (new course_spreadsheet_export_manager())->process_export(
                    $tempfile,
                    $selectedspreadsheet,
                    $course,
                    $currentgroup,
                    $data,
                    $originalfilename
                );
            } else {
                $result = $this->courseexportmanager->process_export(
                    $tempfile,
                    $data->gradefiller_format,
                    $course,
                    $currentgroup,
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

    /**
     * Render the export bridge form page.
     *
     * @param \stdClass $course
     * @param \context_course $context
     * @param course_export_form $form
     * @param string $selectedspreadsheetkey
     * @return void
     */
    private function render_form(
        \stdClass $course,
        \context_course $context,
        course_export_form $form,
        string $selectedspreadsheetkey
    ): void {
        global $OUTPUT;

        $activeplugin = local_gradefiller_get_grade_export_action_plugin($course->id);
        $actionbar = new export_action_bar($context, null, $activeplugin);
        print_grade_page_head(
            $course->id,
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

        groups_print_course_menu($course, local_gradefiller_get_grade_export_url($course->id, $selectedspreadsheetkey ?: null)->out(false));
        echo \html_writer::div('', 'clearer');
        echo $OUTPUT->notification(get_string('gradebook_export_intro', 'local_gradefiller'), 'info');
        $form->display();
        echo $OUTPUT->footer();
    }
}
