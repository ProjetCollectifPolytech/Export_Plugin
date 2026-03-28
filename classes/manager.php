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
 * Facade for Grade Filler's activity-based spreadsheet workflow.
 *
 * @package    local_gradefiller
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_gradefiller;

use grade_item;
use local_gradefiller\service\grade_lookup_service;
use local_gradefiller\service\spreadsheet_fill_service;
use local_gradefiller\spreadsheet\spreadsheet_format_interface;
use local_gradefiller\source\grade_source_interface;

defined('MOODLE_INTERNAL') || die();

/**
 * Thin facade preserved for the existing pages, hooks and tests.
 *
 * The real logic is now split between explicit registries and specialised
 * services so the plugin no longer relies on one manager owning every concern.
 *
 * @package    local_gradefiller
 */
class manager {
    /** @var string Standard grade source key */
    public const GRADE_SOURCE_STANDARD = 'standard';

    /** @var string Anonymous grade source key */
    public const GRADE_SOURCE_ANONYMOUS = 'anonymous';

    /** @var spreadsheet_format_registry */
    private spreadsheet_format_registry $formatregistry;

    /** @var grade_source_registry */
    private grade_source_registry $driverregistry;

    /** @var grade_lookup_service */
    private grade_lookup_service $gradelookupservice;

    /** @var spreadsheet_fill_service */
    private spreadsheet_fill_service $spreadsheetfillservice;

    /**
     * Constructor.
     */
    public function __construct(
        spreadsheet_format_registry $formatregistry,
        grade_source_registry $driverregistry,
        grade_lookup_service $gradelookupservice,
        spreadsheet_fill_service $spreadsheetfillservice
    ) {
        $this->formatregistry = $formatregistry;
        $this->driverregistry = $driverregistry;
        $this->gradelookupservice = $gradelookupservice;
        $this->spreadsheetfillservice = $spreadsheetfillservice;
    }

    /**
     * Return available spreadsheet formats.
     *
     * @return spreadsheet_format_interface[]
     */
    public function get_available_formats(): array {
        return $this->formatregistry->get_available_formats();
    }

    /**
     * Resolve one spreadsheet format by key.
     *
     * @param string $formatkey
     * @return spreadsheet_format_interface|null
     */
    public function get_format(string $formatkey): ?spreadsheet_format_interface {
        return $this->formatregistry->get_format($formatkey);
    }

    /**
     * Return available grade source drivers.
     *
     * @return grade_source_interface[]
     */
    public function get_available_drivers(): array {
        return $this->driverregistry->get_available_drivers();
    }

    /**
     * Resolve the driver for one activity.
     *
     * @param \cm_info|\stdClass $cm
     * @return grade_source_interface|null
     */
    public function get_driver_for_cm($cm): ?grade_source_interface {
        return $this->driverregistry->get_driver_for_cm($cm);
    }

    /**
     * List supported grade source modes for an activity.
     *
     * @param \cm_info|\stdClass $cm
     * @return array
     */
    public function get_supported_grade_sources($cm): array {
        $sources = [self::GRADE_SOURCE_STANDARD];
        if ($this->driverregistry->get_driver_for_cm($cm) !== null) {
            $sources[] = self::GRADE_SOURCE_ANONYMOUS;
        }

        return $sources;
    }

    /**
     * Check whether the requested grade source is supported.
     *
     * @param \cm_info|\stdClass $cm
     * @param string $gradesource
     * @return bool
     */
    public function is_supported_grade_source($cm, string $gradesource): bool {
        return in_array($gradesource, $this->get_supported_grade_sources($cm), true);
    }

    /**
     * Fetch one grade for an identifier.
     *
     * @param string $identifier
     * @param int $cmid
     * @param int $courseid
     * @param string $gradesource
     * @return object|null
     */
    public function fetch_grade(string $identifier, int $cmid, int $courseid, string $gradesource = self::GRADE_SOURCE_STANDARD): ?object {
        return $this->gradelookupservice->fetch_grade($identifier, $cmid, $courseid, $gradesource);
    }

    /**
     * Fetch one grade for a resolved grade item.
     *
     * @param string $identifier
     * @param grade_item $gradeitem
     * @param int $courseid
     * @param string $identifiermode
     * @param bool $onlyactive
     * @param int $groupid
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
        return $this->gradelookupservice->fetch_grade_for_item(
            $identifier,
            $gradeitem,
            $courseid,
            $identifiermode,
            $onlyactive,
            $groupid
        );
    }

    /**
     * Process a teacher spreadsheet and fill it with grades.
     *
     * @param string $filepath
     * @param string $formatkey
     * @param int $cmid
     * @param int $courseid
     * @param string $gradesource
     * @return array
     */
    public function process_file(string $filepath, string $formatkey, int $cmid, int $courseid, string $gradesource): array {
        return $this->spreadsheetfillservice->process_file($filepath, $formatkey, $cmid, $courseid, $gradesource);
    }
}
