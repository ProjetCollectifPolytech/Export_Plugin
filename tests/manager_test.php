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

/**
 * Tests for the manager class.
 *
 * @package    local_gradefiller
 */
final class manager_test extends gradefiller_testcase {
    /** @var string[] */
    private array $createdtables = [];
    protected function tearDown(): void {

        $this->drop_created_tables();
        parent::tearDown();
    }

    public function test_get_format_returns_the_university_standard_handler(): void {

        $manager = manager_factory::create_default();
        $format = $manager->get_format('university_standard');
        $this->assertNotNull($format);
        $this->assertSame('university_standard', $format->get_key());
    }

    public function test_get_driver_for_cm_returns_null_for_unsupported_activity(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $cm = $this->create_label_cm($course);
        $this->assertNull(manager_factory::create_default()->get_driver_for_cm($cm));
    }

    public function test_get_driver_for_cm_prefers_papergrade_when_exam_exists(): void {

        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $this->ensure_papergrade_exam_table_exists();
        $course = $this->getDataGenerator()->create_course();
        [, $cm] = $this->create_offlinequiz_activity($course);
        $DB->insert_record('local_papergrade_exam', (object)[
            'offlinequizid' => $cm->instance, 'grade' => 20.0, 'mode' => 'anonymous', 'timecreated' => time(), 'timemodified' => time(),
        ]);
        $driver = manager_factory::create_default()->get_driver_for_cm($cm);
        $this->assertInstanceOf(\local_gradefiller\source\grade_source_papergrade::class, $driver);
    }
    public function test_get_supported_grade_sources_includes_anonymous_for_offlinequiz(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        [, $cm] = $this->create_offlinequiz_activity($course);

        $sources = manager_factory::create_default()->get_supported_grade_sources($cm);
        $this->assertSame(
            [manager::GRADE_SOURCE_STANDARD, manager::GRADE_SOURCE_ANONYMOUS],
            $sources
        );
    }

    public function test_is_supported_grade_source_validates_available_sources(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $cm = $this->create_label_cm($course);

        $manager = manager_factory::create_default();
        $this->assertTrue($manager->is_supported_grade_source($cm, manager::GRADE_SOURCE_STANDARD));
        $this->assertFalse($manager->is_supported_grade_source($cm, manager::GRADE_SOURCE_ANONYMOUS));
    }

    /**
     * Create the minimal Papergrade exam table required by the manager routing test.
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
