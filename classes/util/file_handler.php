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
 * File handler utility class
 *
 * @package    local_gradefiller
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_gradefiller\util;

defined('MOODLE_INTERNAL') || die();

/**
 * File handler utility class
 *
 * Handles file upload and temporary file management.
 */
class file_handler {

    /**
     * Handle uploaded file
     *
     * Validates and moves uploaded file to temporary directory.
     *
     * @param string $filekey The $_FILES array key
     * @return string Path to the temporary file
     * @throws \moodle_exception If upload failed
     */
    public static function handle_upload(string $filekey): string {
        if (!isset($_FILES[$filekey]) || $_FILES[$filekey]['error'] !== UPLOAD_ERR_OK) {
            $error = isset($_FILES[$filekey]) ? $_FILES[$filekey]['error'] : 'No file in $_FILES';
            throw new \moodle_exception('error_no_file', 'local_gradefiller', '', $error);
        }

        // Move uploaded file to temp directory.
        $tempdir = make_temp_directory('gradefiller');
        $tempfile = $tempdir . '/' . clean_filename($_FILES[$filekey]['name']);
        
        if (!move_uploaded_file($_FILES[$filekey]['tmp_name'], $tempfile)) {
            throw new \moodle_exception('error_moving_file', 'local_gradefiller');
        }

        return $tempfile;
    }

    /**
     * Cleanup temporary file
     *
     * @param string $filepath Path to the file to delete
     * @return void
     */
    public static function cleanup(string $filepath): void {
        if (file_exists($filepath)) {
            @unlink($filepath);
        }
    }

    /**
     * Validate file extension
     *
     * @param string $filename Filename to validate
     * @param array $allowedext Array of allowed extensions (without dot)
     * @return bool True if valid
     */
    public static function validate_extension(string $filename, array $allowedext): bool {
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        return in_array($extension, $allowedext);
    }
}
