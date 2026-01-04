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
 * English language strings for local_gradefiller
 *
 * @package    local_gradefiller
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['pluginname'] = 'Grade Filler';

// Navigation.
$string['fill_grades'] = 'Fill grades in a spreadsheet';

// Capabilities.
$string['gradefiller:use'] = 'Use grade filler to fill grades in a spreadsheet';

// Driver names.
$string['driver_offlinequiz'] = 'Offline Quiz (Anonymous)';

// Page.
$string['page_title'] = 'Fill Grades in a Spreadsheet';
$string['activity_info'] = 'Activity Information';
$string['activity'] = 'Activity';
$string['type'] = 'Type';
$string['anonymous_supported'] = 'Anonymous grades supported';

// Upload form.
$string['upload_spreadsheet'] = 'Upload Your Spreadsheet';
$string['spreadsheet_file'] = 'Spreadsheet file';
$string['drag_drop_zone'] = 'Drag and drop your file here';
$string['or_click_to_select'] = 'or click to select a file';
$string['file_formats_accepted'] = 'Accepted formats: XLSX, XLS, XLSM, ODS, CSV';

$string['spreadsheet_format'] = 'Spreadsheet format';
$string['select_format'] = '-- Select format --';

$string['grade_source'] = 'Grade source';
$string['source_auto'] = 'Auto-detect (Moodle ID or Anonymous)';
$string['source_standard'] = 'Standard (Moodle User ID Number only)';
$string['source_anonymous'] = 'Anonymous (Activity-specific codes only)';
$string['grade_source_help'] = 'Choose how to match identifiers in your file';

$string['btn_process_download'] = 'Process and Download Filled File';

// Formats.
$string['format_university_standard_name'] = 'University Standard Format';
$string['format_university_standard_desc'] = 'Skip 17 header rows, Column A = ID, Column E = Grade';

// Help.
$string['how_it_works'] = 'How it works';
$string['help_step1'] = 'Upload your spreadsheet containing student identifiers';
$string['help_step2'] = 'Select the format that matches your file structure';
$string['help_step3'] = 'Choose the grade source (Moodle IDs or anonymous codes)';
$string['help_step4'] = 'The plugin will fill the grades and download the completed file';

// Messages.
$string['file_processed'] = 'File processed: {$a->matched} grades filled, {$a->unmatched} not found';

// Errors.
$string['error_no_file'] = 'No file uploaded';
$string['error_format_not_found'] = 'Format not found: {$a}';
$string['error_reading_file'] = 'Error reading file: {$a}';
$string['error_writing_file'] = 'Error writing file: {$a}';
$string['error_format_invalid'] = 'Invalid file format: {$a}';
$string['error_format_insufficient_rows'] = 'File must have at least {$a} rows';
$string['error_format_no_identifiers'] = 'No identifiers found in the expected column';
$string['error_no_permission'] = 'You do not have permission to access this page';
$string['error_no_grade_item'] = 'This activity does not have a grade item';
