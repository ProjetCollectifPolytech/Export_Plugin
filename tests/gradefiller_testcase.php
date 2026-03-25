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

namespace local_gradefiller;

use grade_category;
use grade_item;
use stdClass;

/**
 * Shared PHPUnit fixture helpers for local_gradefiller tests.
 *
 * @package    local_gradefiller
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class gradefiller_testcase extends \advanced_testcase {

    /**
     * Create a basic label activity and return its course module.
     *
     * @param stdClass $course Course record
     * @return stdClass
     */
    protected function create_label_cm(stdClass $course): stdClass {
        $module = $this->getDataGenerator()->create_module('label', ['course' => $course->id]);
        return get_coursemodule_from_instance('label', $module->id, $course->id, false, MUST_EXIST);
    }

    /**
     * Create a grade item for the provided course module.
     *
     * @param stdClass $course Course record
     * @param stdClass $cm Course module
     * @param float $grademax Maximum grade
     * @return grade_item
     */
    protected function create_grade_item_for_cm(stdClass $course, stdClass $cm, float $grademax = 20.0): grade_item {
        $coursecategory = grade_category::fetch_course_category($course->id);

        $gradeitem = new grade_item();
        $gradeitem->courseid = $course->id;
        $gradeitem->categoryid = $coursecategory->id;
        $gradeitem->itemname = $cm->name ?? 'Grade filler test item';
        $gradeitem->itemtype = 'mod';
        $gradeitem->itemmodule = $cm->modname;
        $gradeitem->iteminstance = $cm->instance;
        $gradeitem->itemnumber = 0;
        $gradeitem->gradetype = GRADE_TYPE_VALUE;
        $gradeitem->grademax = $grademax;
        $gradeitem->grademin = 0;
        $gradeitem->timecreated = time();
        $gradeitem->timemodified = time();
        $gradeitem->insert();

        return $gradeitem;
    }

    /**
     * Create a lightweight offlinequiz activity fixture.
     *
     * @param stdClass $course Course record
     * @param string $name Activity name
     * @return array
     */
    protected function create_offlinequiz_activity(stdClass $course, string $name = 'Grade filler test activity'): array {
        global $DB;

        $module = $DB->get_record('modules', ['name' => 'offlinequiz'], '*', MUST_EXIST);

        $offlinequiz = (object)[
            'course' => $course->id,
            'name' => $name,
            'intro' => 'Test activity',
            'introformat' => FORMAT_HTML,
            'timeopen' => 0,
            'timeclose' => 0,
            'time' => 0,
            'grade' => 20.0,
            'numgroups' => 1,
            'decimalpoints' => 2,
            'review' => 0,
            'questionsperpage' => 0,
            'docscreated' => 0,
            'shufflequestions' => 0,
            'shuffleanswers' => 0,
            'printstudycodefield' => 1,
            'papergray' => 650,
            'fontsize' => 10,
            'timecreated' => time(),
            'timemodified' => time(),
            'showquestioninfo' => 0,
            'fileformat' => 0,
            'showgrades' => 0,
            'showtutorial' => 0,
            'disableimgnewlines' => 0,
            'experimentalevaluation' => 0,
        ];
        $offlinequiz->id = $DB->insert_record('offlinequiz', $offlinequiz);

        $section = $DB->get_record('course_sections', ['course' => $course->id, 'section' => 1]);
        if (!$section) {
            $section = (object)[
                'course' => $course->id,
                'section' => 1,
                'summary' => '',
                'summaryformat' => FORMAT_HTML,
                'sequence' => '',
                'visible' => 1,
            ];
            $section->id = $DB->insert_record('course_sections', $section);
        }

        $coursemodule = (object)[
            'course' => $course->id,
            'module' => $module->id,
            'instance' => $offlinequiz->id,
            'section' => $section->id,
            'added' => time(),
            'score' => 0,
            'indent' => 0,
            'visible' => 1,
            'visibleold' => 1,
            'groupmode' => 0,
            'groupingid' => 0,
            'completion' => 0,
            'completionview' => 0,
            'completionexpected' => 0,
            'showdescription' => 0,
        ];
        $coursemodule->id = $DB->insert_record('course_modules', $coursemodule);

        $section->sequence = empty($section->sequence)
            ? (string)$coursemodule->id
            : $section->sequence . ',' . $coursemodule->id;
        $DB->update_record('course_sections', $section);

        rebuild_course_cache($course->id, true);

        return [
            $offlinequiz,
            get_coursemodule_from_id('offlinequiz', $coursemodule->id, 0, false, MUST_EXIST),
        ];
    }

    /**
     * Create an offlinequiz group.
     *
     * @param int $offlinequizid Offlinequiz ID
     * @param float $sumgrades Maximum grade for the group
     * @return int
     */
    protected function create_offlinequiz_group(int $offlinequizid, float $sumgrades = 20.0): int {
        global $DB;

        return $DB->insert_record('offlinequiz_groups', (object)[
            'offlinequizid' => $offlinequizid,
            'groupnumber' => 1,
            'sumgrades' => $sumgrades,
            'numberofpages' => 1,
            'templateusageid' => 0,
        ]);
    }

    /**
     * Insert an offlinequiz result and optional scanned page.
     *
     * @param int $offlinequizid Offlinequiz ID
     * @param int $groupid Offlinequiz group ID
     * @param string|null $anonkey Anonymous key stored on scanned pages
     * @param float|null $sumgrades Result grade
     * @param int $userid User ID
     * @param int|null $timemodified Timestamp override
     * @return int
     */
    protected function insert_offlinequiz_result(
        int $offlinequizid,
        int $groupid,
        ?string $anonkey,
        ?float $sumgrades,
        int $userid = 0,
        ?int $timemodified = null
    ): int {
        global $DB;

        $timemodified = $timemodified ?? time();
        $resultid = $DB->insert_record('offlinequiz_results', (object)[
            'offlinequizid' => $offlinequizid,
            'offlinegroupid' => $groupid,
            'userid' => $userid,
            'sumgrades' => $sumgrades,
            'usageid' => 0,
            'teacherid' => 0,
            'attendant' => 'scanonly',
            'status' => 'complete',
            'timestart' => $timemodified,
            'timemodified' => $timemodified,
        ]);

        if ($anonkey !== null) {
            $DB->insert_record('offlinequiz_scanned_pages', (object)[
                'offlinequizid' => $offlinequizid,
                'groupnumber' => $groupid,
                'userkey' => $anonkey,
                'resultid' => $resultid,
                'status' => 'ok',
                'time' => $timemodified,
            ]);
        }

        return $resultid;
    }
}
