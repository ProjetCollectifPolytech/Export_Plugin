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
 * Registry for spreadsheet input/output formats.
 *
 * @package    local_gradefiller
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_gradefiller;

use local_gradefiller\spreadsheet\format_university_standard;
use local_gradefiller\spreadsheet\spreadsheet_format_interface;

defined('MOODLE_INTERNAL') || die();

/**
 * Keeps spreadsheet format registration explicit.
 *
 * @package    local_gradefiller
 */
class spreadsheet_format_registry {
    /** @var spreadsheet_format_interface[]|null */
    private ?array $formats = null;

    /**
     * Return available spreadsheet formats.
     *
     * @return spreadsheet_format_interface[]
     */
    public function get_available_formats(): array {
        if ($this->formats !== null) {
            return $this->formats;
        }

        $this->formats = [
            new format_university_standard(),
        ];

        return $this->formats;
    }

    /**
     * Resolve one format by key.
     *
     * @param string $formatkey
     * @return spreadsheet_format_interface|null
     */
    public function get_format(string $formatkey): ?spreadsheet_format_interface {
        foreach ($this->get_available_formats() as $format) {
            if ($format->get_key() === $formatkey) {
                return $format;
            }
        }

        return null;
    }
}
