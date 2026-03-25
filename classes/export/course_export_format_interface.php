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
 * Strategy interface for gradebook export template formats.
 *
 * @package    local_gradefiller
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_gradefiller\export;

defined('MOODLE_INTERNAL') || die();

/**
 * Defines how Grade Filler injects a Moodle grade export into a teacher template.
 *
 * @package    local_gradefiller
 */
interface course_export_format_interface {

    /**
     * Get the human-readable name of the export format.
     *
     * @return string
     */
    public function get_name(): string;

    /**
     * Get the stable format key.
     *
     * @return string
     */
    public function get_key(): string;

    /**
     * Get the human-readable description shown to teachers.
     *
     * @return string
     */
    public function get_description(): string;

    /**
     * Get the file extensions accepted by this export format for teacher uploads.
     *
     * Returned values must not include the leading dot.
     *
     * @return string[]
     */
    public function get_supported_extensions(): array;

    /**
     * Validate the uploaded workbook template before processing.
     *
     * @param string $filepath Absolute path to the uploaded file
     * @return bool
     */
    public function validate_template(string $filepath): bool;

    /**
     * Inject the classic Moodle grade export into the provided template.
     *
     * @param string $filepath Absolute path to the uploaded file
     * @param object $exportdata Grade export data object
     * @return string Absolute path to the generated file
     */
    public function export_to_template(string $filepath, object $exportdata): string;
}
