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

require_once(__DIR__ . '/gradefiller_testcase.php');

use local_gradefiller\source\grade_source_offlinequiz;
use local_gradefiller\source\grade_source_papergrade;

/**
 * Tests for the grade source registry.
 *
 * @package    local_gradefiller
 */
final class grade_source_registry_test extends gradefiller_testcase {
    /** @var string[] */
    private array $createdtables = [];

    protected function tearDown(): void {
        $this->drop_created_tables();
        parent::tearDown();
    }

    public function test_get_available_drivers_keeps_specific_driver_first(): void {
        $drivers = (new grade_source_registry())->get_available_drivers();

        $this->assertCount(2, $drivers);
        $this->assertInstanceOf(grade_source_papergrade::class, $drivers[0]);
        $this->assertInstanceOf(grade_source_offlinequiz::class, $drivers[1]);
    }

    public function test_get_driver_for_cm_prefers_papergrade_when_exam_exists(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();
        $this->ensure_papergrade_exam_table_exists();

        $course = $this->getDataGenerator()->create_course();
        [, $cm] = $this->create_offlinequiz_activity($course);

        $DB->insert_record('local_papergrade_exam', (object) [
            'offlinequizid' => $cm->instance,
            'grade' => 20.0,
            'mode' => 'anonymous',
            'timecreated' => time(),
            'timemodified' => time(),
        ]);

        $driver = (new grade_source_registry())->get_driver_for_cm($cm);

        $this->assertInstanceOf(grade_source_papergrade::class, $driver);
    }

    /**
     * Create the minimal Papergrade exam table required by the registry routing test.
     *
     * @return void
     */
    private function ensure_papergrade_exam_table_exists(): void {
        global $CFG, $DB;

        require_once($CFG->libdir . '/xmldb/xmldb_table.php');

        $dbman = $DB->get_manager();
        $examtable = new \xmldb_table('local_papergrade_exam');
        if ($dbman->table_exists($examtable)) {
            return;
        }

        $examtable->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $examtable->add_field('offlinequizid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $examtable->add_field('grade', XMLDB_TYPE_NUMBER, '10,2', null, XMLDB_NOTNULL, null, '20.00');
        $examtable->add_field('mode', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'anonymous');
        $examtable->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $examtable->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $examtable->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);

        $dbman->create_table($examtable);
        $this->createdtables[] = 'local_papergrade_exam';
    }

    /**
     * Drop any temporary Papergrade tables created by this test case.
     *
     * @return void
     */
    private function drop_created_tables(): void {
        global $CFG, $DB;

        if (empty($this->createdtables)) {
            return;
        }

        require_once($CFG->libdir . '/xmldb/xmldb_table.php');

        $dbman = $DB->get_manager();
        $tables = array_reverse($this->createdtables);
        $this->createdtables = [];

        foreach ($tables as $tablename) {
            $table = new \xmldb_table($tablename);
            if ($dbman->table_exists($table)) {
                $dbman->drop_table($table);
            }
        }
    }
}
