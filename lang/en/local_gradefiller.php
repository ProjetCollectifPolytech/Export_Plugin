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
 * English language strings for local_gradefiller.
 *
 * @package    local_gradefiller
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['pluginname'] = 'Grade Filler';

// Navigation.
$string['fill_grades'] = 'Fill grades in a spreadsheet';
$string['gradebook_export_selector_label'] = 'Grade Filler';

// Capabilities.
$string['gradefiller:view'] = 'View Grade Filler';
$string['gradefiller:process'] = 'Process spreadsheets with Grade Filler';
$string['gradefiller:use'] = 'Use Grade Filler to fill grades in a spreadsheet';

// Driver names.
$string['driver_offlinequiz'] = 'Offline Quiz (Anonymous)';
$string['driver_papergrade'] = 'Papergrade (Scanner)';
// Page.
$string['page_title'] = 'Fill Grades in a Spreadsheet';
$string['gradebook_export_page_title'] = 'Export grades with Grade Filler';
$string['gradebook_export_section'] = 'Grade Filler template';
$string['gradebook_export_intro'] = 'Keep Moodle\'s native grade export options, then inject the result into your workbook template.';
$string['gradebook_export_target_spreadsheet'] = 'Target spreadsheet format';
$string['activity_info'] = 'Activity Information';
$string['activity'] = 'Activity';
$string['type'] = 'Type';
$string['anonymous_supported'] = 'Anonymous grades supported';
$string['read_only_notice'] = 'You can view this page, but you do not have permission to process spreadsheets.';

// Upload form.
$string['upload_spreadsheet'] = 'Upload Your Spreadsheet';
$string['spreadsheet_file'] = 'Spreadsheet file';
$string['drag_drop_zone'] = 'Drag and drop your file here';
$string['or_click_to_select'] = 'or click to select a file';
$string['file_formats_accepted'] = 'Accepted formats: XLSX, XLSM';
$string['gradebook_template_file'] = 'Workbook template';
$string['gradebook_template_file_help'] = 'Upload the file expected by the selected spreadsheet format. Accepted file types adapt automatically to the chosen format.';

$string['spreadsheet_format'] = 'Spreadsheet format';
$string['select_format'] = '-- Select format --';
$string['gradebook_export_format'] = 'Grade Filler export format';
$string['gradebook_export_download'] = 'Download workbook export';

$string['grade_source'] = 'Grade source';
$string['source_auto'] = 'Auto-detect (Moodle ID or Anonymous)';
$string['source_standard'] = 'Standard (Moodle User ID Number only)';
$string['source_anonymous'] = 'Anonymous (Activity-specific codes only)';
$string['grade_source_help'] = 'Choose how to match identifiers in your file';

$string['btn_process_download'] = 'Process and Download Filled File';

// Formats.
$string['format_university_standard_name'] = 'Apoge';
$string['format_university_standard_desc'] = 'Skip 17 header rows, Column A = ID, Column E = Grade';
$string['gradebook_export_format_workbook_name'] = 'Workbook template';
$string['gradebook_export_format_workbook_desc'] = 'Upload an XLSX or XLSM workbook. Grade Filler injects the classic Moodle export into a worksheet named "Export Moodle".';

// Help.
$string['how_it_works'] = 'How it works';
$string['help_step1'] = 'Upload your spreadsheet containing student identifiers';
$string['help_step2'] = 'Select the format that matches your file structure';
$string['help_step3'] = 'Choose the grade source (Moodle IDs or anonymous codes)';
$string['help_step4'] = 'The plugin will fill the grades and download the completed file';

// Messages.
$string['file_processed'] = 'File processed: {$a->matched} grades filled, {$a->unmatched} not found';

// Events.
$string['event_page_viewed'] = 'Grade Filler page viewed';
$string['event_file_processed'] = 'Grade Filler spreadsheet processed';
$string['event_page_viewed_desc'] = 'User {$a->userid} viewed Grade Filler for module {$a->cmid} in course {$a->courseid}.';
$string['event_file_processed_desc'] = 'User {$a->userid} processed a Grade Filler spreadsheet for module {$a->cmid} in course {$a->courseid} using source {$a->gradesource} ({$a->matched} matched, {$a->unmatched} unmatched, {$a->errors} errors).';
// Privacy.
$string['privacy:metadata'] = 'The Grade Filler plugin does not store personal data.';

// Errors.
$string['error_activity_unsupported'] = 'This activity does not support Grade Filler.';
$string['error_invalid_action'] = 'Invalid action requested.';
$string['error_invalid_grade_source'] = 'Invalid grade source selected.';
$string['error_post_required'] = 'A POST request is required for this action.';
$string['error_no_file'] = 'No file uploaded';
$string['error_moving_file'] = 'The uploaded file could not be moved to temporary storage';
$string['error_format_not_found'] = 'Format not found: {$a}';
$string['error_reading_file'] = 'Error reading file: {$a}';
$string['error_writing_file'] = 'Error writing file: {$a}';
$string['error_format_invalid'] = 'Invalid file format: {$a}';
$string['error_format_insufficient_rows'] = 'File must have at least {$a} rows';
$string['error_format_no_identifiers'] = 'No identifiers found in the expected column';
$string['error_export_format_not_found'] = 'Grade Filler export format not found: {$a}';
$string['error_export_template_required'] = 'A workbook template file is required';
$string['error_export_template_invalid'] = 'Invalid workbook template: {$a}';
$string['error_export_template_write'] = 'Unable to write the Grade Filler export workbook: {$a}';
$string['error_export_template_invalid_generic'] = 'The workbook template could not be read.';
$string['error_export_template_write_generic'] = 'The workbook template could not be updated.';
$string['error_export_template_extension'] = 'Unsupported workbook template extension: {$a}. Use XLSX or XLSM.';
$string['error_no_permission'] = 'You do not have permission to access this page';
$string['error_no_grade_item'] = 'This activity does not have a grade item';
$string['error_unsupported_extension'] = 'Unsupported file extension: {$a}. Use XLSX or XLSM.';
$string['error_operation_failed'] = 'The requested export could not be completed.';
$string['error_file_read_failed'] = 'The spreadsheet could not be read.';
$string['error_file_write_failed'] = 'The spreadsheet could not be updated.';
$string['error_format_invalid_generic'] = 'The spreadsheet does not match the selected format.';
