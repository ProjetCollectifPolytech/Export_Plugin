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


use local_gradefiller\export\course_export_manager;
use local_gradefiller\form\course_export_form;
use local_gradefiller\manager;
use local_gradefiller\manager_factory;
use moodle_exception;
use moodle_url;

/**
 * Loads and validates the grade export bridge request context.
 *
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class grade_export_context_loader {
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
     * Resolve the request context for the export bridge page.
     *
     * @param int $courseid
     * @param string $selectedspreadsheetkey
     * @return grade_export_request_context
     */
    public function load(int $courseid, string $selectedspreadsheetkey): grade_export_request_context {
        global $DB;

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

        $selectedspreadsheet = $this->resolve_selected_spreadsheet($selectedspreadsheetkey);
        $form = $this->build_form($course, $selectedspreadsheet, $selectedspreadsheetkey);

        $groupmode = groups_get_course_groupmode($course);
        $currentgroup = groups_get_course_group($course, true);
        $requiresgroupselection = ($groupmode == SEPARATEGROUPS)
            && !$currentgroup
            && !has_capability('moodle/site:accessallgroups', $context);

        return new grade_export_request_context(
            $course,
            $context,
            $selectedspreadsheet,
            $selectedspreadsheetkey,
            $form,
            $currentgroup,
            $requiresgroupselection
        );
    }

    /**
     * Build the canonical page URL for one export bridge request.
     *
     * @param int $courseid
     * @param string $selectedspreadsheetkey
     * @return moodle_url
     */
    public function build_page_url(int $courseid, string $selectedspreadsheetkey = ''): moodle_url {
        $params = ['id' => $courseid];
        if ($selectedspreadsheetkey !== '') {
            $params['spreadsheetformat'] = $selectedspreadsheetkey;
        }

        return new moodle_url('/local/gradefiller/gradeexport.php', $params);
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
            $this->build_page_url($course->id, $selectedspreadsheetkey)->out(false),
            [
                'course' => $course,
                'formats' => $this->courseexportmanager->get_available_formats(),
                'selectedspreadsheet' => $selectedspreadsheet,
            ]
        );
    }
}
