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
 * Spreadsheet filling service for Grade Filler.
 *
 * @package    local_gradefiller
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_gradefiller\service;

use local_gradefiller\manager;
use local_gradefiller\spreadsheet_format_registry;
use moodle_exception;
use Throwable;


/**
 * Orchestrates identifier reading, grade lookup and workbook writing.
 *
 * @package    local_gradefiller
 */
class spreadsheet_fill_service {
    /** @var spreadsheet_format_registry */
    private spreadsheet_format_registry $formatregistry;

    /** @var grade_lookup_service */
    private grade_lookup_service $gradelookupservice;

    /**
     * Constructor.
     *
     * @param spreadsheet_format_registry $formatregistry
     * @param grade_lookup_service $gradelookupservice
     */
    public function __construct(
        spreadsheet_format_registry $formatregistry,
        grade_lookup_service $gradelookupservice
    ) {
        $this->formatregistry = $formatregistry;
        $this->gradelookupservice = $gradelookupservice;
    }

    /**
     * Process a spreadsheet file and fill it with grades.
     *
     * @param string $filepath
     * @param string $formatkey
     * @param int $cmid
     * @param int $courseid
     * @param string $gradesource
     * @return array
     */
    public function process_file(string $filepath, string $formatkey, int $cmid, int $courseid, string $gradesource): array {
        $format = $this->formatregistry->get_format($formatkey);
        if ($format === null) {
            throw new moodle_exception('error_format_not_found', 'local_gradefiller', '', $formatkey);
        }

        $format->validate_file($filepath);
        $identifiers = $format->read_identifiers($filepath);

        $stats = [
            'total' => count($identifiers),
            'matched' => 0,
            'unmatched' => 0,
            'errors' => 0,
        ];
        $grades = [];

        foreach ($identifiers as $item) {
            try {
                $result = $this->gradelookupservice->fetch_grade($item->identifier, $cmid, $courseid, $gradesource);
                if ($result !== null) {
                    $grades[] = (object) [
                        'identifier' => $item->identifier,
                        'grade' => $result->grade,
                        'row_number' => $item->row_number,
                    ];
                    $stats['matched']++;
                } else {
                    $stats['unmatched']++;
                }
            } catch (Throwable $e) {
                $stats['errors']++;
                debugging(
                    'Grade Filler skipped identifier "' . $item->identifier . '" after a lookup failure: ' . $e->getMessage(),
                    DEBUG_DEVELOPER
                );
            }
        }

        $outputfile = $format->write_grades($filepath, $grades);

        return [
            'filepath' => $outputfile,
            'stats' => $stats,
        ];
    }
}
