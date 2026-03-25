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
 * Interface for spreadsheet format handlers
 *
 * Strategy Pattern: Defines how to read/write specific spreadsheet formats
 *
 * @package    local_gradefiller
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_gradefiller\spreadsheet;

/**
 * Interface spreadsheet_format_interface
 *
 * All spreadsheet format handlers must implement this interface.
 * This allows the plugin to support different spreadsheet layouts chosen by teachers.
 *
 * @package    local_gradefiller
 */
interface spreadsheet_format_interface {
    /** @var string Prefer automatic identifier resolution */
    public const IDENTIFIER_MODE_AUTO = 'auto';

    /** @var string Prefer Moodle idnumber resolution */
    public const IDENTIFIER_MODE_STANDARD = 'standard';

    /** @var string Prefer anonymous activity-specific identifiers */
    public const IDENTIFIER_MODE_ANONYMOUS = 'anonymous';

    /**
     * Get the human-readable name of this format
     *
     * @return string Format name (e.g. "Format Standard Université")
     */
    public function get_name(): string;

    /**
     * Get the unique identifier for this format
     *
     * @return string Format key (e.g. "university_standard")
     */
    public function get_key(): string;

    /**
     * Get the description of this format
     *
     * @return string Format description
     */
    public function get_description(): string;

    /**
     * Get the file extensions accepted by this spreadsheet format.
     *
     * Returned values must not include the leading dot.
     *
     * @return string[]
     */
    public function get_supported_extensions(): array;

    /**
     * Get the label to display for the upload field when this spreadsheet
     * format is selected in the UI.
     *
     * @return string
     */
    public function get_upload_label(): string;

    /**
     * Get the descriptive help text to display for this spreadsheet format in
     * the upload UI.
     *
     * @return string
     */
    public function get_upload_help(): string;

    /**
     * Describe how identifiers in this spreadsheet should be interpreted when
     * resolving grades.
     *
     * @return string One of the IDENTIFIER_MODE_* constants
     */
    public function get_identifier_mode(): string;

    /**
     * Read identifiers from the spreadsheet
     *
     * @param string $filepath Path to the uploaded spreadsheet file
     * @return array Array of objects with properties: identifier, row_number
     * @throws \moodle_exception
     */
    public function read_identifiers(string $filepath): array;

    /**
     * Write grades to the spreadsheet
     *
     * @param string $filepath Path to the original spreadsheet file
     * @param array $grades Array of objects with properties: identifier, grade
     * @return string Path to the filled spreadsheet file
     * @throws \moodle_exception
     */
    public function write_grades(string $filepath, array $grades): string;

    /**
     * Validate that the file can be processed by this format
     *
     * @param string $filepath Path to the spreadsheet file
     * @return bool True if valid
     * @throws \moodle_exception with detailed error message
     */
    public function validate_file(string $filepath): bool;
}
