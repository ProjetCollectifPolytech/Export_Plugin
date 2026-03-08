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
 * Integration tests for the manager class
 *
 * @package    local_gradefiller
 * @category   test
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_gradefiller\tests;

use local_gradefiller\manager;
use local_gradefiller\spreadsheet\format_university_standard;
use local_gradefiller\source\grade_source_offlinequiz;

/**
 * Tests for \local_gradefiller\manager
 *
 * Uses advanced_testcase so the database is reset after each test.
 *
 * @covers \local_gradefiller\manager
 */
class manager_test extends \advanced_testcase {
    /** @var \stdClass Course used across most tests */
    private \stdClass $course;

    /** @var manager */
    private manager $manager;

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);

        $this->course  = $this->getDataGenerator()->create_course();
        $this->manager = new manager();
    }

    // Format discovery tests.

    /**
     * get_available_formats returns at least one format.
     *
     * @covers \local_gradefiller\manager::get_available_formats
     */
    public function test_get_available_formats_returns_non_empty_array(): void {
        $formats = $this->manager->get_available_formats();
        $this->assertIsArray($formats);
        $this->assertNotEmpty($formats);
    }

    /**
     * get_format returns the correct instance for the known 'university_standard' key.
     *
     * @covers \local_gradefiller\manager::get_format
     */
    public function test_get_format_returns_instance_for_known_key(): void {
        $format = $this->manager->get_format('university_standard');
        $this->assertNotNull($format);
        $this->assertInstanceOf(format_university_standard::class, $format);
    }

    /**
     * get_format returns null for an unknown format key.
     *
     * @covers \local_gradefiller\manager::get_format
     */
    public function test_get_format_returns_null_for_unknown_key(): void {
        $format = $this->manager->get_format('nonexistent_format_xyz');
        $this->assertNull($format);
    }

    // Driver discovery tests.

    /**
     * get_available_drivers returns at least one driver.
     *
     * @covers \local_gradefiller\manager::get_available_drivers
     */
    public function test_get_available_drivers_returns_non_empty_array(): void {
        $drivers = $this->manager->get_available_drivers();
        $this->assertIsArray($drivers);
        $this->assertNotEmpty($drivers);
    }

    /**
     * get_driver_for_cm returns a grade_source_offlinequiz instance for an offlinequiz cm.
     *
     * @covers \local_gradefiller\manager::get_driver_for_cm
     */
    public function test_get_driver_for_cm_returns_driver_for_offlinequiz(): void {
        $cm = (object) ['modname' => 'offlinequiz', 'instance' => 1];
        $driver = $this->manager->get_driver_for_cm($cm);
        $this->assertNotNull($driver);
        $this->assertInstanceOf(grade_source_offlinequiz::class, $driver);
    }

    /**
     * get_driver_for_cm returns null for a non-offlinequiz activity.
     *
     * @covers \local_gradefiller\manager::get_driver_for_cm
     */
    public function test_get_driver_for_cm_returns_null_for_non_offlinequiz(): void {
        $cm = (object) ['modname' => 'assign', 'instance' => 1];
        $driver = $this->manager->get_driver_for_cm($cm);
        $this->assertNull($driver);
    }

    // Standard source fetch_grade tests.

    /**
     * fetch_grade with 'standard' source returns null when the idnumber is unknown.
     *
     * @covers \local_gradefiller\manager::fetch_grade
     */
    public function test_fetch_grade_standard_returns_null_for_unknown_idnumber(): void {
        // No user with this idnumber exists in the DB.
        $result = $this->manager->fetch_grade('UNKNOWN-99999', 1, $this->course->id, 'standard');
        $this->assertNull($result);
    }

    /**
     * fetch_grade with 'standard' source returns null when the user is not enrolled.
     *
     * @covers \local_gradefiller\manager::fetch_grade
     */
    public function test_fetch_grade_standard_returns_null_for_unenrolled_user(): void {
        global $DB;

        // Create user but do NOT enrol them in the course.
        $outsider = $this->getDataGenerator()->create_user(['idnumber' => 'EXT-001']);

        $result = $this->manager->fetch_grade('EXT-001', 1, $this->course->id, 'standard');
        $this->assertNull($result);
    }

    /**
     * fetch_grade with 'standard' source returns null when the enrolled user has no grade yet.
     *
     * @covers \local_gradefiller\manager::fetch_grade
     */
    public function test_fetch_grade_standard_returns_null_for_enrolled_without_grade(): void {
        global $DB;

        $student = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        $DB->set_field('user', 'idnumber', 'STU-NOGRADE', ['id' => $student->id]);

        // Create a real course module so get_coursemodule_from_id() succeeds.
        $assign = $this->getDataGenerator()->create_module('assign', ['course' => $this->course->id]);
        $cm = get_coursemodule_from_instance('assign', $assign->id);

        // The grade_grade record does not exist yet — must return null.
        $result = $this->manager->fetch_grade('STU-NOGRADE', $cm->id, $this->course->id, 'standard');
        $this->assertNull($result);
    }

    /**
     * fetch_grade with 'standard' source returns the correct grade when found.
     *
     * @covers \local_gradefiller\manager::fetch_grade
     */
    public function test_fetch_grade_standard_returns_grade_for_enrolled_user(): void {
        global $DB;

        $student = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        $DB->set_field('user', 'idnumber', 'STU-WITHGRADE', ['id' => $student->id]);

        // Create an assign activity — Moodle auto-creates the grade_item.
        $assign = $this->getDataGenerator()->create_module('assign', ['course' => $this->course->id]);
        $cm = get_coursemodule_from_instance('assign', $assign->id);

        // Fetch the auto-created grade_item and insert a grade_grade.
        $gradeitem = \grade_item::fetch([
            'itemtype'     => 'mod',
            'itemmodule'   => 'assign',
            'iteminstance' => $assign->id,
            'courseid'     => $this->course->id,
        ]);
        $this->assertNotFalse($gradeitem, 'Assign grade_item should be auto-created.');

        $gradegrade = new \grade_grade([
            'itemid'       => $gradeitem->id,
            'userid'       => $student->id,
            'finalgrade'   => 75.0,
            'rawgrade'     => 75.0,
            'rawgrademax'  => $gradeitem->grademax,
        ]);
        $gradegrade->insert();

        $result = $this->manager->fetch_grade('STU-WITHGRADE', $cm->id, $this->course->id, 'standard');

        $this->assertNotNull($result);
        $this->assertEquals(75.0, $result->grade);
        $this->assertSame('standard', $result->source);
        $this->assertEquals($student->id, $result->userid);
    }

    // Anonymous source fetch_grade tests.

    /**
     * fetch_grade with 'anonymous' source returns null when no driver matches the cm.
     *
     * @covers \local_gradefiller\manager::fetch_grade
     */
    public function test_fetch_grade_anonymous_returns_null_without_driver(): void {
        // Create a non-offlinequiz activity so there is no matching driver.
        $assign = $this->getDataGenerator()->create_module('assign', ['course' => $this->course->id]);
        $cm = get_coursemodule_from_instance('assign', $assign->id);

        $result = $this->manager->fetch_grade('some-key', $cm->id, $this->course->id, 'anonymous');
        $this->assertNull($result);
    }

    // Unknown source fetch_grade tests.

    /**
     * fetch_grade returns null for an unrecognised grade source string.
     *
     * @covers \local_gradefiller\manager::fetch_grade
     */
    public function test_fetch_grade_unknown_source_returns_null(): void {
        // The method returns null for anything other than 'standard' or 'anonymous'.
        $result = $this->manager->fetch_grade('STU-001', 1, $this->course->id, 'invalid_source');
        $this->assertNull($result);
    }

    // Tests for process_file.

    /**
     * process_file throws a moodle_exception when the format key is not found.
     *
     * @covers \local_gradefiller\manager::process_file
     */
    public function test_process_file_throws_for_unknown_format(): void {
        $this->expectException(\moodle_exception::class);
        // The exception is thrown before the filepath is read, so any value works.
        $this->manager->process_file('/tmp/fake.xlsx', 'nonexistent_format', 1, $this->course->id, 'standard');
    }
}
