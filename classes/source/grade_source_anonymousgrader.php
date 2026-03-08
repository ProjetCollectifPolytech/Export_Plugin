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
 * Anonymous Grader driver for grade filler
 *
 * Fetches grades directly from local_anonymousgrader scanner results.
 *
 * @package    local_gradefiller
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_gradefiller\source;

/**
 * Anonymous Grader driver implementation
 *
 * This driver allows filling spreadsheets using anonymous IDs scanned by local_anonymousgrader.
 *
 * @package    local_gradefiller
 */
class grade_source_anonymousgrader implements grade_source_interface {
    /**
     * Get the human-readable name of this driver
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('driver_anonymousgrader', 'local_gradefiller');
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

        // Check if anonymousgrader table exists and has an exam for this activity.
        if (!$DB->get_manager()->table_exists('local_anonymousgrader_exam')) {
            return false;
        }

        return $DB->record_exists('local_anonymousgrader_exam', ['offlinequizid' => $cm->instance]);
    }

    /**
     * Fetch grade for a specific anonymous identifier from scanner results
     *
     * @param int $cmid Course module ID
     * @param string $anonkey Anonymous identifier (ID scanned)
     * @return object|null Object with properties: grade, maxgrade, or null if not found
     * @throws \dml_exception
     */
    public function fetch_grade_by_anonkey(int $cmid, string $anonkey): ?object {
        global $DB;

        // Get the offlinequiz instance ID.
        $cm = get_coursemodule_from_id('offlinequiz', $cmid, 0, false, MUST_EXIST);

        // Find the exam record.
        $exam = $DB->get_record('local_anonymousgrader_exam', ['offlinequizid' => $cm->instance], '*', MUST_EXIST);

        // Fetch the validated result matching the anonymous ID.
        // We use status = 'validated' to ensure the grade has been approved.
        $sql = "SELECT r.grade
                FROM {local_anonymousgrader_results} r
                WHERE r.examid = :examid
                  AND r.anonymousid = :anonid
                  AND r.status = 'validated'
                ORDER BY r.timemodified DESC";

        $params = [
            'examid' => $exam->id,
            'anonid' => (int)$anonkey,
        ];

        $records = $DB->get_records_sql($sql, $params, 0, 1);
        $record = !empty($records) ? reset($records) : null;

        if ($record && $record->grade !== null) {
            return (object)[
                'grade' => (float)$record->grade,
                'maxgrade' => (float)$exam->grade,
            ];
        }

        return null;
    }

    /**
     * Check if this identifier looks like an anonymous key
     *
     * @param string $identifier The identifier to check
     * @return bool
     */
    public function is_anonymous_identifier(string $identifier): bool {
        // IDs in anonymousgrader are numeric strings.
        return is_numeric(trim($identifier));
    }
}
