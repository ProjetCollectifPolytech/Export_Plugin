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
 * Process upload action handler.
 *
 * @package    local_gradefiller
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_gradefiller\action;

use Exception;
use local_gradefiller\event\file_processed;
use local_gradefiller\util\download_handler;
use local_gradefiller\util\file_handler;
use moodle_exception;

defined('MOODLE_INTERNAL') || die();

/**
 * Handles the file upload and processing action.
 *
 * This action:
 * 1. Validates the POST request and selected grade source
 * 2. Moves the uploaded file to Moodle temp storage
 * 3. Processes it with the selected format and grade source
 * 4. Triggers an audit event
 * 5. Sends the filled spreadsheet for download
 */
class process_upload extends base_action {

    /**
     * Execute the upload processing action.
     *
     * This method terminates script execution after sending the file.
     *
     * @return void
     */
    public function execute(): void {
        $this->require_post_request();

        $formatkey = required_param('format', PARAM_ALPHANUMEXT);
        $gradesource = required_param('gradesource', PARAM_ALPHA);
        if (!$this->manager->is_supported_grade_source($this->cm, $gradesource)) {
            throw new moodle_exception('error_invalid_grade_source', 'local_gradefiller');
        }

        $tempfile = null;

        try {
            $tempfile = file_handler::handle_upload('spreadsheet');

            $result = $this->manager->process_file(
                $tempfile,
                $formatkey,
                $this->cmid,
                $this->course->id,
                $gradesource
            );

            file_processed::create([
                'objectid' => $this->cmid,
                'courseid' => $this->course->id,
                'context' => $this->context,
                'other' => [
                    'format' => $formatkey,
                    'gradesource' => $gradesource,
                    'total' => $result['stats']['total'],
                    'matched' => $result['stats']['matched'],
                    'unmatched' => $result['stats']['unmatched'],
                    'errors' => $result['stats']['errors'],
                ],
            ])->trigger();

            $extension = strtolower(pathinfo($result['filepath'], PATHINFO_EXTENSION));
            $downloadname = download_handler::generate_filename('filled_grades', $extension);

            file_handler::cleanup($tempfile);
            $tempfile = null;

            download_handler::send_file($result['filepath'], $downloadname);
        } catch (Exception $e) {
            file_handler::cleanup($tempfile ?? '');
            throw $e;
        }
    }
}
