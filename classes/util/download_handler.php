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
 * Download handler utility class
 *
 * @package    local_gradefiller
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_gradefiller\util;

defined('MOODLE_INTERNAL') || die();

/**
 * Download handler utility class
 *
 * Handles file download with proper HTTP headers.
 */
class download_handler {

    /**
     * Send file for download and terminate script
     *
     * @param string $filepath Path to the file to send
     * @param string $downloadname Desired filename for download (without path)
     * @return void This method terminates script execution
     */
    public static function send_file(string $filepath, string $downloadname): void {
        send_temp_file($filepath, clean_filename($downloadname));
    }

    /**
     * Generate download filename with timestamp
     *
     * @param string $prefix Filename prefix
     * @param string $extension File extension (without dot)
     * @return string Generated filename
     */
    public static function generate_filename(string $prefix, string $extension): string {
        return $prefix . '_' . date('Y-m-d_His') . '.' . $extension;
    }
}
