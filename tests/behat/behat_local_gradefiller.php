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
 * Behat step definitions for local_gradefiller
 *
 * @package    local_gradefiller
 * @category   test
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../../lib/behat/behat_base.php');

use Behat\Behat\Context\Context;
use Behat\Gherkin\Node\PyStringNode;
use Behat\Gherkin\Node\TableNode;

/**
 * Step definitions for local_gradefiller Behat tests.
 */
class behat_local_gradefiller extends behat_base {

    /**
     * Navigate to the grade filler page for a specific activity in a course.
     *
     * @When I am on the grade filler page for :activityname in course :shortname
     */
    public function i_am_on_grade_filler_page(string $activityname, string $shortname): void {
        global $DB;

        $course = $DB->get_record('course', ['shortname' => $shortname], '*', MUST_EXIST);

        // Find the course module id for the given activity name.
        $sql = "SELECT cm.id
                  FROM {course_modules} cm
                  JOIN {modules} m ON m.id = cm.module
                  JOIN {assign} a ON a.id = cm.instance AND m.name = 'assign'
                 WHERE cm.course = :courseid AND a.name = :name
                 UNION
                SELECT cm.id
                  FROM {course_modules} cm
                  JOIN {modules} m ON m.id = cm.module
                  JOIN {offlinequiz} oq ON oq.id = cm.instance AND m.name = 'offlinequiz'
                 WHERE cm.course = :courseid2 AND oq.name = :name2";

        $params = [
            'courseid'  => $course->id,
            'name'      => $activityname,
            'courseid2' => $course->id,
            'name2'     => $activityname,
        ];

        // Fallback: generic lookup across all module tables using course_modules.
        $cmid = null;
        $modules = $DB->get_records('modules');
        foreach ($modules as $module) {
            $table = $module->name;
            if (!$DB->get_manager()->table_exists($table)) {
                continue;
            }
            $sql = "SELECT cm.id
                      FROM {course_modules} cm
                      JOIN {modules} m ON m.id = cm.module
                      JOIN {{$table}} t ON t.id = cm.instance
                     WHERE cm.course = :courseid
                       AND m.name = :modname
                       AND t.name = :actname";
            $record = $DB->get_record_sql($sql, [
                'courseid' => $course->id,
                'modname'  => $table,
                'actname'  => $activityname,
            ]);
            if ($record) {
                $cmid = $record->id;
                break;
            }
        }

        if (!$cmid) {
            throw new \Exception("Activity '{$activityname}' not found in course '{$shortname}'.");
        }

        $url = new \moodle_url('/local/gradefiller/index.php', ['id' => $cmid]);
        $this->getSession()->visit($this->locate_path($url->out_as_local_url()));
    }

    /**
     * Upload a spreadsheet fixture file to the grade filler form.
     *
     * @When I upload the spreadsheet fixture :filename
     */
    public function i_upload_spreadsheet_fixture(string $filename): void {
        $fixturepath = __DIR__ . '/../fixtures/' . $filename;

        if (!file_exists($fixturepath)) {
            throw new \Exception("Fixture file '{$filename}' not found at {$fixturepath}.");
        }

        $page = $this->getSession()->getPage();
        $fileinput = $page->find('css', 'input[name="spreadsheet"]');

        if (!$fileinput) {
            throw new \Exception('Spreadsheet file input not found on the page.');
        }

        $fileinput->attachFile($fixturepath);
    }
}
