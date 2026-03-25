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
 * Core manager class for grade filler
 *
 * @package    local_gradefiller
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_gradefiller;

use context_course;
use Exception;
use grade_grade;
use grade_item;
use local_gradefiller\source\grade_source_interface;
use local_gradefiller\source\grade_source_offlinequiz;
use local_gradefiller\source\grade_source_papergrade;
use local_gradefiller\spreadsheet\format_university_standard;
use local_gradefiller\spreadsheet\spreadsheet_format_interface;
use moodle_exception;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/gradelib.php');

/**
 * Manager class - Core business logic
 *
 * This class orchestrates the entire workflow:
 * 1. File upload and format selection
 * 2. Reading identifiers from spreadsheet (Strategy 1: Spreadsheet Format)
 * 3. Determining identifier type (Moodle ID vs Anonymous)
 * 4. Fetching grades (Strategy 2: Grade Fetcher)
 * 5. Writing filled spreadsheet
 *
 * @package    local_gradefiller
 */
class manager {
    /** @var string Standard grade source key */
    public const GRADE_SOURCE_STANDARD = 'standard';

    /** @var string Anonymous grade source key */
    public const GRADE_SOURCE_ANONYMOUS = 'anonymous';

    /** @var array Cache of available spreadsheet formats */
    private $formats = null;

    /** @var array Cache of available grade source drivers */
    private $drivers = null;

    /**
     * Get all available spreadsheet formats
     *
     * @return array Array of format objects implementing spreadsheet_format_interface
     */
    public function get_available_formats(): array {
        if ($this->formats !== null) {
            return $this->formats;
        }

        $this->formats = [];

        // Register built-in formats.
        $this->formats[] = new format_university_standard();

        // TODO: Add auto-discovery of additional formats from /local/gradefiller/classes/spreadsheet/format_*.php

        return $this->formats;
    }

    /**
     * Get a specific format by its key
     *
     * @param string $formatkey Format key
     * @return \local_gradefiller\spreadsheet\spreadsheet_format_interface|null
     */
    public function get_format(string $formatkey): ?spreadsheet_format_interface {
        $formats = $this->get_available_formats();

        foreach ($formats as $format) {
            if ($format->get_key() === $formatkey) {
                return $format;
            }
        }

        return null;
    }

    /**
     * Get all available grade source drivers
     *
     * @return array Array of driver objects implementing grade_source_interface
     */
    public function get_available_drivers(): array {
        if ($this->drivers !== null) {
            return $this->drivers;
        }

        $this->drivers = [];

        // Register built-in drivers.
        $this->drivers[] = new grade_source_offlinequiz();

        // The driver performs its own table checks and simply declines when
        // local_papergrade is not installed for the current Moodle site.
        $this->drivers[] = new grade_source_papergrade();

        return $this->drivers;
    }

    /**
     * Find the appropriate driver for a course module
     *
     * @param \cm_info $cm Course module
     * @return \local_gradefiller\source\grade_source_interface|null
     */
    public function get_driver_for_cm($cm): ?grade_source_interface {
        $drivers = $this->get_available_drivers();

        foreach ($drivers as $driver) {
            if ($driver->supports($cm)) {
                return $driver;
            }
        }

        return null;
    }

    /**
     * Get the supported grade sources for an activity.
     *
     * @param \stdClass|\cm_info $cm Course module
     * @return array
     */
    public function get_supported_grade_sources($cm): array {
        $sources = [self::GRADE_SOURCE_STANDARD];

        if ($this->get_driver_for_cm($cm) !== null) {
            $sources[] = self::GRADE_SOURCE_ANONYMOUS;
        }

        return $sources;
    }

    /**
     * Check whether a grade source is supported for the given activity.
     *
     * @param \stdClass|\cm_info $cm Course module
     * @param string $gradesource Grade source key
     * @return bool
     */
    public function is_supported_grade_source($cm, string $gradesource): bool {
        return in_array($gradesource, $this->get_supported_grade_sources($cm), true);
    }

    /**
     * Determine the type of identifier and fetch grade accordingly
     *
     * Algorithm:
     * 1. Check if identifier is a Moodle user idnumber -> Use standard gradebook
     * 2. Check if it's an anonymous key -> Use driver for current activity
     * 3. Return null if not found
     *
     * @param string $identifier The identifier from the spreadsheet
     * @param int $cmid Course module ID
     * @param int $courseid Course ID
     * @param string $gradesource Grade source selection ('standard' or 'anonymous')
     * @return object|null Object with properties: grade, maxgrade, userid, source
     */
    public function fetch_grade(string $identifier, int $cmid, int $courseid, string $gradesource = 'standard'): ?object {
        global $DB;

        $identifier = trim($identifier);

        // Strategy determination based on user selection.
        if ($gradesource === self::GRADE_SOURCE_STANDARD) {
            // Standard Moodle ID lookup.
            return $this->fetch_grade_from_standard($identifier, $cmid, $courseid);
        } else if ($gradesource === self::GRADE_SOURCE_ANONYMOUS) {
            // Anonymous driver lookup.
            return $this->fetch_grade_from_anonymous($identifier, $cmid);
        }

        return null;
    }

    /**
     * Fetch a grade for one specific grade item.
     *
     * This additive helper powers the multi-activity spreadsheet export without
     * changing the historical activity-by-activity workflow.
     *
     * @param string $identifier Identifier read from the spreadsheet
     * @param grade_item $gradeitem Selected grade item
     * @param int $courseid Course ID
     * @param string $identifiermode Preferred identifier resolution strategy
     * @param bool $onlyactive Whether to require active enrolment
     * @param int $groupid Group filter (0 = no filter)
     * @return object|null
     */
    public function fetch_grade_for_item(
        string $identifier,
        grade_item $gradeitem,
        int $courseid,
        string $identifiermode = spreadsheet_format_interface::IDENTIFIER_MODE_AUTO,
        bool $onlyactive = true,
        int $groupid = 0
    ): ?object {
        $identifier = trim($identifier);

        $preferanonymous = ($identifiermode === spreadsheet_format_interface::IDENTIFIER_MODE_ANONYMOUS);
        $preferstandard = ($identifiermode === spreadsheet_format_interface::IDENTIFIER_MODE_STANDARD);

        if ($preferstandard) {
            return $this->fetch_grade_from_standard_item($identifier, $gradeitem, $courseid, $onlyactive, $groupid);
        }

        if ($gradeitem->itemtype === 'mod') {
            $anonymousgrade = $this->fetch_grade_from_anonymous_item($identifier, $gradeitem, $courseid);
            if ($anonymousgrade !== null) {
                return $anonymousgrade;
            }

            if ($preferanonymous) {
                return null;
            }
        }

        return $this->fetch_grade_from_standard_item($identifier, $gradeitem, $courseid, $onlyactive, $groupid);
    }

    /**
     * Fetch grade from standard Moodle gradebook using user idnumber
     *
     * @param string $idnumber User ID number
     * @param int $cmid Course module ID
     * @param int $courseid Course ID
     * @return object|null Object with properties: grade, maxgrade, userid, source
     */
    private function fetch_grade_from_standard(string $idnumber, int $cmid, int $courseid): ?object {
        // Get course module info.
        $cm = get_coursemodule_from_id('', $cmid, 0, false, MUST_EXIST);

        // Get grade item.
        $gradeitem = grade_item::fetch([
            'itemtype' => 'mod',
            'itemmodule' => $cm->modname,
            'iteminstance' => $cm->instance,
            'courseid' => $courseid
        ]);

        if (!$gradeitem) {
            return null;
        }

        return $this->fetch_grade_from_standard_item($idnumber, $gradeitem, $courseid, false, 0);
    }

    /**
     * Fetch grade from anonymous driver
     *
     * @param string $anonkey Anonymous identifier
     * @param int $cmid Course module ID
     * @return object|null Object with properties: grade, maxgrade, source
     */
    private function fetch_grade_from_anonymous(string $anonkey, int $cmid): ?object {
        // Get course module.
        $cm = get_coursemodule_from_id('', $cmid, 0, false, MUST_EXIST);

        // Find appropriate driver.
        $driver = $this->get_driver_for_cm($cm);
        if (!$driver) {
            return null;
        }

        // Check if this looks like an anonymous identifier for this driver.
        if (!$driver->is_anonymous_identifier($anonkey)) {
            return null;
        }

        // Fetch grade from driver.
        $result = $driver->fetch_grade_by_anonkey($cmid, $anonkey);

        if ($result) {
            $result->source = 'anonymous';
            return $result;
        }

        return null;
    }

    /**
     * Fetch a standard Moodle grade for an already resolved grade item.
     *
     * @param string $idnumber User idnumber
     * @param grade_item $gradeitem Grade item
     * @param int $courseid Course ID
     * @param bool $onlyactive Whether to require active enrolment
     * @param int $groupid Group filter (0 = no filter)
     * @return object|null
     */
    private function fetch_grade_from_standard_item(
        string $idnumber,
        grade_item $gradeitem,
        int $courseid,
        bool $onlyactive,
        int $groupid
    ): ?object {
        global $DB;

        $user = $DB->get_record('user', ['idnumber' => $idnumber, 'deleted' => 0]);
        if (!$user) {
            return null;
        }

        $context = context_course::instance($courseid);
        if (!is_enrolled($context, $user->id, '', $onlyactive)) {
            return null;
        }

        if ($groupid > 0 && !groups_is_member($groupid, $user->id)) {
            return null;
        }

        $grade = grade_grade::fetch(['itemid' => $gradeitem->id, 'userid' => $user->id]);
        if ($grade && $grade->finalgrade !== null) {
            return (object) [
                'grade' => (float) $grade->finalgrade,
                'maxgrade' => (float) $gradeitem->grademax,
                'userid' => $user->id,
                'source' => 'standard',
            ];
        }

        return null;
    }

    /**
     * Fetch an anonymous grade for an already resolved grade item.
     *
     * @param string $anonkey Anonymous identifier
     * @param grade_item $gradeitem Grade item
     * @param int $courseid Course ID
     * @return object|null
     */
    private function fetch_grade_from_anonymous_item(
        string $anonkey,
        grade_item $gradeitem,
        int $courseid
    ): ?object {
        if ($gradeitem->itemtype !== 'mod' || empty($gradeitem->itemmodule) || empty($gradeitem->iteminstance)) {
            return null;
        }

        $cm = get_coursemodule_from_instance(
            $gradeitem->itemmodule,
            $gradeitem->iteminstance,
            $courseid,
            false,
            IGNORE_MISSING
        );
        if (!$cm) {
            return null;
        }

        $driver = $this->get_driver_for_cm($cm);
        if (!$driver || !$driver->is_anonymous_identifier($anonkey)) {
            return null;
        }

        $result = $driver->fetch_grade_by_anonkey($cm->id, $anonkey);
        if ($result !== null) {
            $result->source = 'anonymous';
        }

        return $result;
    }

    /**
     * Process spreadsheet file and fill with grades
     *
     * Main orchestration method:
     * 1. Validate file with selected format
     * 2. Read identifiers
     * 3. Fetch grades for each identifier
     * 4. Write filled spreadsheet
     * 5. Return file path
     *
     * @param string $filepath Path to uploaded file
     * @param string $formatkey Selected format key
     * @param int $cmid Course module ID
     * @param int $courseid Course ID
     * @param string $gradesource Grade source ('standard' or 'anonymous')
     * @return array Result with 'filepath', 'stats' (matched, unmatched, errors)
     * @throws \moodle_exception
     */
    public function process_file(string $filepath, string $formatkey, int $cmid, int $courseid, string $gradesource): array {
        // Get format handler.
        $format = $this->get_format($formatkey);
        if (!$format) {
            throw new moodle_exception('error_format_not_found', 'local_gradefiller', '', $formatkey);
        }

        // Validate file.
        $format->validate_file($filepath);

        // Read identifiers.
        $identifiers = $format->read_identifiers($filepath);

        $stats = [
            'total' => count($identifiers),
            'matched' => 0,
            'unmatched' => 0,
            'errors' => 0,
        ];

        $grades = [];

        // Fetch grades for each identifier.
        foreach ($identifiers as $item) {
            try {
                $result = $this->fetch_grade($item->identifier, $cmid, $courseid, $gradesource);

                if ($result) {
                    $grades[] = (object)[
                        'identifier' => $item->identifier,
                        'grade' => $result->grade,
                        'row_number' => $item->row_number,
                    ];
                    $stats['matched']++;
                } else {
                    $stats['unmatched']++;
                }
            } catch (Exception $e) {
                $stats['errors']++;
                debugging('Error fetching grade for identifier ' . $item->identifier . ': ' . $e->getMessage());
            }
        }

        // Write filled spreadsheet.
        $outputfile = $format->write_grades($filepath, $grades);

        return [
            'filepath' => $outputfile,
            'stats' => $stats,
        ];
    }
}
