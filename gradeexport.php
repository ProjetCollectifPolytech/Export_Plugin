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
 * Gradebook export bridge page for Grade Filler.
 *
 * @package    local_gradefiller
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/grade/export/lib.php');
require_once($CFG->dirroot . '/local/gradefiller/lib.php');

use core_form\util as form_util;
use core_grades\output\export_action_bar;
use local_gradefiller\export\course_export_manager;
use local_gradefiller\export\course_spreadsheet_export_manager;
use local_gradefiller\form\course_export_form;
use local_gradefiller\manager;
use local_gradefiller\util\download_handler;
use local_gradefiller\util\file_handler;

$id = required_param('id', PARAM_INT);
$selectedspreadsheetkey = optional_param('spreadsheetformat', '', PARAM_ALPHANUMEXT);

$pageparams = ['id' => $id];
if ($selectedspreadsheetkey !== '') {
    $pageparams['spreadsheetformat'] = $selectedspreadsheetkey;
}
$PAGE->set_url('/local/gradefiller/gradeexport.php', $pageparams);

if (!$course = $DB->get_record('course', ['id' => $id])) {
    throw new moodle_exception('invalidcourseid');
}

require_login($course);
$context = context_course::instance($id);

if (!local_gradefiller_can_access_grade_export_bridge($context)) {
    throw new moodle_exception('error_no_permission', 'local_gradefiller');
}

export_verify_grades($course->id);

$PAGE->set_pagelayout('incourse');
$PAGE->set_title(get_string('gradebook_export_page_title', 'local_gradefiller'));
$PAGE->set_heading($course->fullname);
$PAGE->set_pagetype('grade-export-gradefiller-index');

$spreadsheetmanager = new manager();
$selectedspreadsheet = null;
if ($selectedspreadsheetkey !== '') {
    $selectedspreadsheet = $spreadsheetmanager->get_format($selectedspreadsheetkey);
    if ($selectedspreadsheet === null) {
        throw new moodle_exception('error_format_not_found', 'local_gradefiller', '', $selectedspreadsheetkey);
    }
}

$manager = new course_export_manager();
$formats = $manager->get_available_formats();
$form = new course_export_form(
    local_gradefiller_get_grade_export_url($id, $selectedspreadsheetkey ?: null),
    [
        'course' => $course,
        'formats' => $formats,
        'selectedspreadsheet' => $selectedspreadsheet,
    ]
);

$groupmode = groups_get_course_groupmode($course);
$currentgroup = groups_get_course_group($course, true);
if (($groupmode == SEPARATEGROUPS) && !$currentgroup && !has_capability('moodle/site:accessallgroups', $context)) {
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
    exit;
}

if ($form->is_cancelled()) {
    redirect(new moodle_url('/grade/export/index.php', ['id' => $course->id]));
}

if ($data = $form->get_data()) {
    $originalfilename = $form->get_new_filename('templatefile');
    $tempdir = make_temp_directory('gradefiller');
    $tempfile = $tempdir . '/' . uniqid('gradeexport_', true) . '_' . clean_filename($originalfilename);

    if (!$form->save_file('templatefile', $tempfile, true)) {
        throw new moodle_exception('error_export_template_required', 'local_gradefiller');
    }

    try {
        if ($selectedspreadsheet !== null) {
            $result = (new course_spreadsheet_export_manager())->process_export(
                $tempfile,
                $selectedspreadsheet,
                $course,
                (int) $currentgroup,
                $data,
                $originalfilename
            );
        } else {
            $result = $manager->process_export(
                $tempfile,
                $data->gradefiller_format,
                $course,
                (int)$currentgroup,
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

groups_print_course_menu($course, local_gradefiller_get_grade_export_url($id, $selectedspreadsheetkey ?: null)->out(false));
echo html_writer::div('', 'clearer');
echo $OUTPUT->notification(get_string('gradebook_export_intro', 'local_gradefiller'), 'info');
$form->display();
echo $OUTPUT->footer();
