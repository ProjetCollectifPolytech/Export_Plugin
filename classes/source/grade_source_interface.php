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
 * Interface for grade source drivers (anonymous grade fetchers)
 *
 * Strategy Pattern: Defines how to fetch grades from specific activity types
 * Reuses architecture from local_anonimapper for compatibility
 *
 * @package    local_gradefiller
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_gradefiller\source;

/**
 * Interface grade_source_interface
 *
 * All grade source drivers must implement this interface.
 * This interface is architecturally compatible with anonimapper's source_interface.
 *
 * @package    local_gradefiller
 */
interface grade_source_interface {
    /**
     * Get the human-readable name of this driver
     *
     * @return string Driver name (e.g. "Offline Quiz")
     */
    public function get_name(): string;

    /**
     * Check if this driver supports the given course module
     *
     * @param \stdClass|\cm_info $cm Course module object
     * @return bool True if this driver can handle this module type
     */
    public function supports($cm): bool;

    /**
     * Fetch grade for a specific anonymous identifier
     *
     * @param int $cmid Course module ID
     * @param string $anonkey Anonymous identifier (e.g., barcode, code)
     * @return object|null Object with properties: grade, maxgrade, or null if not found
     * @throws \dml_exception
     */
    public function fetch_grade_by_anonkey(int $cmid, string $anonkey): ?object;

    /**
     * Check if this identifier looks like an anonymous key for this driver
     *
     * @param string $identifier The identifier to check
     * @return bool True if this looks like an anonymous identifier
     */
    public function is_anonymous_identifier(string $identifier): bool;
}
