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
 * Upload page view-model builder.
 *
 * @package    local_gradefiller
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_gradefiller\page;

defined('MOODLE_INTERNAL') || die();

/**
 * Builds template data for the upload page.
 *
 * @package    local_gradefiller
 */
class upload_page {

    /**
     * Build upload template data.
     *
     * @param int $cmid
     * @param \stdClass $cm
     * @param array $formats
     * @param bool $canprocess
     * @param object|null $driver
     * @param string $wwwroot
     * @return array
     */
    public function build(
        int $cmid,
        \stdClass $cm,
        array $formats,
        bool $canprocess,
        $driver,
        string $wwwroot
    ): array {
        $formatoptions = [];
        foreach ($formats as $format) {
            $formatoptions[] = [
                'key' => $format->get_key(),
                'name' => $format->get_name(),
                'description' => $format->get_description(),
            ];
        }

        $supportsanonymous = ($driver !== null);

        return [
            'cmid' => $cmid,
            'activity_name' => $cm->name,
            'activity_type' => get_string('modulename', $cm->modname),
            'formats' => $formatoptions,
            'supports_anonymous' => $supportsanonymous,
            'driver_name' => $supportsanonymous ? $driver->get_name() : '',
            'can_process' => $canprocess,
            'sesskey' => sesskey(),
            'wwwroot' => $wwwroot,
        ];
    }
}
