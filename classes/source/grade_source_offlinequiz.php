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
 * Offline Quiz driver for anonymous grade fetching
 *
 * Architecture reused from local_anonimapper for compatibility
 *
 * @package    local_gradefiller
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_gradefiller\source;

/**
 * Offline Quiz driver implementation
 *
 * Fetches grades from Offline Quiz activities using anonymous identifiers (barcodes).
 *
 * @package    local_gradefiller
 */
class grade_source_offlinequiz implements grade_source_interface {

    /**
     * Get the human-readable name of this driver
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('driver_offlinequiz', 'local_gradefiller', null) ?? 'Offline Quiz';
    }

    /**
     * Check if this driver supports the given course module
     *
     * @param \stdClass|\cm_info $cm Course module object
     * @return bool
     */
    public function supports($cm): bool {
        global $DB;

        if ($cm->modname !== 'offlinequiz') {
            return false;
        }

        // Don't claim activities managed by papergrade — let the papergrade driver handle those.
        if ($DB->get_manager()->table_exists('local_papergrade_exam')) {
            if ($DB->record_exists('local_papergrade_exam', ['offlinequizid' => $cm->instance])) {
                return false;
            }
        }

        return true;
    }

    /**
     * Fetch grade for a specific anonymous identifier
     *
     * @param int $cmid Course module ID
     * @param string $anonkey Anonymous identifier (barcode)
     * @return object|null Object with properties: grade, maxgrade, or null if not found
     * @throws \dml_exception
     */
    public function fetch_grade_by_anonkey(int $cmid, string $anonkey): ?object {
        global $DB;

        // Get the course module and offlinequiz instance.
        $cm = get_coursemodule_from_id('offlinequiz', $cmid, 0, false, MUST_EXIST);
        $offlinequiz = $DB->get_record('offlinequiz', ['id' => $cm->instance], '*', MUST_EXIST);

        // Strategy 1: Check results with userid=0 and matching userkey in scanned pages
        // This matches anonimapper's approach for anonymous results
        $sql = "SELECT 
                    r.id,
                    r.sumgrades as grade,
                    og.sumgrades as maxgrade
                FROM {offlinequiz_results} r
                JOIN {offlinequiz_scanned_pages} sp ON sp.resultid = r.id
                JOIN {offlinequiz_groups} og ON og.id = r.offlinegroupid
                WHERE og.offlinequizid = :offlinequizid
                  AND r.userid = 0
                  AND sp.userkey = :userkey
                  AND r.sumgrades IS NOT NULL
                ORDER BY r.timemodified DESC";

        $params = [
            'offlinequizid' => $offlinequiz->id,
            'userkey' => $anonkey
        ];

        $records = $DB->get_records_sql($sql, $params, 0, 1);
        $record = !empty($records) ? reset($records) : null;

        if ($record && $record->grade !== null) {
            return (object)[
                'grade' => (float)$record->grade,
                'maxgrade' => (float)$record->maxgrade,
            ];
        }

        // Strategy 2: Check results directly if no scanned page found.
        $sql2 = "SELECT 
                    r.id,
                    r.sumgrades as grade,
                    og.sumgrades as maxgrade
                FROM {offlinequiz_results} r
                JOIN {offlinequiz_groups} og ON og.id = r.offlinegroupid
                WHERE og.offlinequizid = :offlinequizid
                  AND r.userid = 0
                ORDER BY r.timemodified DESC";

        $results = $DB->get_records_sql($sql2, ['offlinequizid' => $offlinequiz->id]);

        // This is a fallback - we can't directly match without userkey.
        // Return null to indicate no match found.
        return null;
    }

    /**
     * Check if an identifier is valid for anonymous lookup
     *
     * Since the teacher explicitly chooses "Anonymous" mode in the interface,
     * we don't try to detect patterns. Any non-empty identifier is valid.
     * The anonymous code can be anything: ANON-XXX, U-XXXX-YYYY, ABC123, etc.
     *
     * @param string $identifier The identifier to check
     * @return bool True if identifier is non-empty
     */
    public function is_anonymous_identifier(string $identifier): bool {
        // Accept any non-empty identifier when teacher selects anonymous mode.
        return !empty(trim($identifier));
    }
}
