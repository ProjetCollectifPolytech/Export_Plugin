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

use local_gradefiller\source\grade_source_papergrade;

/**
 * Tests for the Papergrade grade source.
 *
 * @package    local_gradefiller
 */
final class source_papergrade_test extends gradefiller_testcase {
    protected function tearDown(): void {
        global $DB;

        if (!empty($DB)) {
            $dbman = $DB->get_manager();
            $resultstable = new \xmldb_table('local_papergrade_results');
            $examtable = new \xmldb_table('local_papergrade_exam');
            if ($dbman->table_exists($resultstable)) {
                $dbman->drop_table($resultstable);
            }
            if ($dbman->table_exists($examtable)) {
                $dbman->drop_table($examtable);
            }
        }

        parent::tearDown();
    }

    public function test_supports_only_anonymous_mode_exams(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();
        $this->create_papergrade_tables();

        $course = $this->getDataGenerator()->create_course();
        [, $cm] = $this->create_offlinequiz_activity($course);

        $DB->insert_record('local_papergrade_exam', (object)[
            'offlinequizid' => $cm->instance,
            'grade' => 20.0,
            'mode' => 'studentid',
            'timecreated' => time(),
            'timemodified' => time(),
        ]);

        $driver = new grade_source_papergrade();
        $this->assertFalse($driver->supports($cm));

        $DB->delete_records('local_papergrade_exam', ['offlinequizid' => $cm->instance]);
        $DB->insert_record('local_papergrade_exam', (object)[
            'offlinequizid' => $cm->instance,
            'grade' => 20.0,
            'mode' => 'anonymous',
            'timecreated' => time(),
            'timemodified' => time(),
        ]);

        $this->assertTrue($driver->supports($cm));
    }

    public function test_fetch_grade_by_anonkey_returns_latest_approved_result(): void {

        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();
        $this->create_papergrade_tables();

        $course = $this->getDataGenerator()->create_course();
        [, $cm] = $this->create_offlinequiz_activity($course);

        $examid = $DB->insert_record('local_papergrade_exam', (object)[
            'offlinequizid' => $cm->instance,
            'grade' => 20.0,
            'mode' => 'anonymous',
            'timecreated' => time(),
            'timemodified' => time(),
        ]);

        $DB->insert_record('local_papergrade_results', (object)[
            'examid' => $examid,
            'anonymousid' => 123,
            'grade' => 12.0,
            'status' => 'validated',
            'timemodified' => 100,
        ]);
        $DB->insert_record('local_papergrade_results', (object)[
            'examid' => $examid, 'anonymousid' => 123, 'grade' => 17.5, 'status' => 'ok', 'timemodified' => 200,
        ]);
        $DB->insert_record('local_papergrade_results', (object)[
            'examid' => $examid,
            'anonymousid' => 123,
            'grade' => 19.0,
            'status' => 'detected',
            'timemodified' => 300,
        ]);

        $result = (new grade_source_papergrade())->fetch_grade_by_anonkey($cm->id, '123');

        $this->assertNotNull($result);
        $this->assertSame(17.5, (float)$result->grade);
        $this->assertSame(20.0, (float)$result->maxgrade);
    }

    public function test_is_anonymous_identifier_requires_numeric_values(): void {
        $driver = new grade_source_papergrade();

        $this->assertTrue($driver->is_anonymous_identifier('123'));
        $this->assertFalse($driver->is_anonymous_identifier('ABC123'));
    }

    /**
     * Create the minimal Papergrade tables required by the source driver.
     *
     * @return void
     */
    private function create_papergrade_tables(): void {
        global $DB;

        $dbman = $DB->get_manager();

        $examtable = new \xmldb_table('local_papergrade_exam');
        $examtable->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $examtable->add_field('offlinequizid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $examtable->add_field('grade', XMLDB_TYPE_NUMBER, '10,2', null, XMLDB_NOTNULL, null, '20.00');
        $examtable->add_field('mode', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'anonymous');
        $examtable->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $examtable->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $examtable->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);

        $resultstable = new \xmldb_table('local_papergrade_results');
        $resultstable->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $resultstable->add_field('examid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $resultstable->add_field('anonymousid', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $resultstable->add_field('grade', XMLDB_TYPE_NUMBER, '10,2', null, null, null, null);
        $resultstable->add_field('status', XMLDB_TYPE_CHAR, '50', null, XMLDB_NOTNULL, null, 'detected');
        $resultstable->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $resultstable->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);

        if (!$dbman->table_exists($examtable)) {
            $dbman->create_table($examtable);
        }
        if (!$dbman->table_exists($resultstable)) {
            $dbman->create_table($resultstable);
        }
    }
}
