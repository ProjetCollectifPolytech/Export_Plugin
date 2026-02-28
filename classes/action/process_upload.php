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
 * Process upload action handler
 *
 * @package    local_gradefiller
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_gradefiller\action;

defined('MOODLE_INTERNAL') || die();

use local_gradefiller\util\file_handler;
use local_gradefiller\util\download_handler;

/**
 * Handles the file upload and processing action
 *
 * This action:
 * 1. Validates the uploaded file
 * 2. Processes it with the selected format and grade source
 * 3. Sends the filled spreadsheet for download
 */
class process_upload extends base_action {

    /**
     * Execute the upload processing action
     *
     * This method terminates script execution after sending the file.
     *
     * @return void
     */
    public function execute(): void {
        // Get form parameters.
        $formatkey = required_param('format', PARAM_ALPHANUMEXT);
        $gradesource = required_param('gradesource', PARAM_ALPHA);

        // Check if file was uploaded.
        if (!isset($_FILES['spreadsheet']) || $_FILES['spreadsheet']['error'] !== UPLOAD_ERR_OK) {
            throw new \moodle_exception('error_no_file', 'local_gradefiller');
        }

        $tempfile = null;

        try {
            // Move uploaded file to temp directory with unique name to avoid collisions.
            $tempdir = make_temp_directory('gradefiller');
            $originalext = strtolower(pathinfo($_FILES['spreadsheet']['name'], PATHINFO_EXTENSION));
            $tempfile = $tempdir . '/' . uniqid('upload_', true) . '.' . $originalext;
            move_uploaded_file($_FILES['spreadsheet']['tmp_name'], $tempfile);

            // Process file.
            $result = $this->manager->process_file(
                $tempfile,
                $formatkey,
                $this->cmid,
                $this->course->id,
                $gradesource
            );

            // Cleanup the original uploaded temp file.
            if (file_exists($tempfile)) {
                @unlink($tempfile);
            }

            // Detect file extension and generate download filename.
            $extension = strtolower(pathinfo($result['filepath'], PATHINFO_EXTENSION));
            $downloadname = download_handler::generate_filename('filled_grades', $extension);

            // Send file for download (this terminates execution).
            download_handler::send_file($result['filepath'], $downloadname);

        } catch (\Exception $e) {
            // Cleanup on error.
            if ($tempfile && file_exists($tempfile)) {
                @unlink($tempfile);
            }
            throw $e;
        }
    }
}
