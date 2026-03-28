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
 * Grade lookup service for Grade Filler.
 *
 * @package    local_gradefiller
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_gradefiller\service;

use context_course;
use grade_grade;
use grade_item;
use local_gradefiller\grade_source_registry;
use local_gradefiller\manager;
use local_gradefiller\spreadsheet\spreadsheet_format_interface;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/gradelib.php');

/**
 * Resolves standard and anonymous grades.
 *
 * @package    local_gradefiller
 */
class grade_lookup_service {
    /** @var grade_source_registry */
    private grade_source_registry $registry;

    /**
     * Constructor.
     *
     * @param grade_source_registry $registry
     */
    public function __construct(grade_source_registry $registry) {
        $this->registry = $registry;
    }

    /**
     * Determine the type of identifier and fetch the corresponding grade.
     *
     * @param string $identifier
     * @param int $cmid
     * @param int $courseid
     * @param string $gradesource
     * @return object|null
     */
    public function fetch_grade(string $identifier, int $cmid, int $courseid, string $gradesource = manager::GRADE_SOURCE_STANDARD): ?object {
        $identifier = trim($identifier);

        if ($gradesource === manager::GRADE_SOURCE_STANDARD) {
            return $this->fetch_grade_from_standard($identifier, $cmid, $courseid);
        }

        if ($gradesource === manager::GRADE_SOURCE_ANONYMOUS) {
            return $this->fetch_grade_from_anonymous($identifier, $cmid);
        }

        return null;
    }

    /**
     * Fetch a grade for one grade item in the multi-activity export pipeline.
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
        }

        if ($preferanonymous) {
            return null;
        }

        return $this->fetch_grade_from_standard_item($identifier, $gradeitem, $courseid, $onlyactive, $groupid);
    }

    /**
     * Fetch a standard Moodle grade for one activity.
     *
     * @param string $idnumber
     * @param int $cmid
     * @param int $courseid
     * @return object|null
     */
    private function fetch_grade_from_standard(string $idnumber, int $cmid, int $courseid): ?object {
        $cm = get_coursemodule_from_id('', $cmid, 0, false, MUST_EXIST);
        $gradeitem = grade_item::fetch([
            'itemtype' => 'mod',
            'itemmodule' => $cm->modname,
            'iteminstance' => $cm->instance,
            'courseid' => $courseid,
        ]);

        if (!$gradeitem) {
            return null;
        }

        return $this->fetch_grade_from_standard_item($idnumber, $gradeitem, $courseid, false, 0);
    }

    /**
     * Fetch an anonymous grade from the activity driver.
     *
     * @param string $anonkey
     * @param int $cmid
     * @return object|null
     */
    private function fetch_grade_from_anonymous(string $anonkey, int $cmid): ?object {
        $cm = get_coursemodule_from_id('', $cmid, 0, false, MUST_EXIST);
        $driver = $this->registry->get_driver_for_cm($cm);
        if ($driver === null || !$driver->is_anonymous_identifier($anonkey)) {
            return null;
        }

        $result = $driver->fetch_grade_by_anonkey($cmid, $anonkey);
        if ($result !== null) {
            $result->source = manager::GRADE_SOURCE_ANONYMOUS;
        }

        return $result;
    }

    /**
     * Fetch a standard Moodle grade for an already resolved grade item.
     *
     * @param string $idnumber
     * @param grade_item $gradeitem
     * @param int $courseid
     * @param bool $onlyactive
     * @param int $groupid
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

        $user = $DB->get_record('user', ['idnumber' => $idnumber, 'deleted' => 0], 'id', IGNORE_MULTIPLE);
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
                'source' => manager::GRADE_SOURCE_STANDARD,
            ];
        }

        return null;
    }

    /**
     * Fetch an anonymous grade for a resolved grade item.
     *
     * @param string $anonkey
     * @param grade_item $gradeitem
     * @param int $courseid
     * @return object|null
     */
    private function fetch_grade_from_anonymous_item(string $anonkey, grade_item $gradeitem, int $courseid): ?object {
        if ($gradeitem->itemtype !== 'mod') {
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

        $driver = $this->registry->get_driver_for_cm($cm);
        if ($driver === null || !$driver->is_anonymous_identifier($anonkey)) {
            return null;
        }

        $result = $driver->fetch_grade_by_anonkey($cm->id, $anonkey);
        if ($result !== null) {
            $result->source = manager::GRADE_SOURCE_ANONYMOUS;
        }

        return $result;
    }
}
