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
 * Unit tests for the grade_source_anonymousgrader source driver
 *
 * @package    local_gradefiller
 * @category   test
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_gradefiller\tests;

use local_gradefiller\source\grade_source_anonymousgrader;

/**
 * Tests for \local_gradefiller\source\grade_source_anonymousgrader
 *
 * @covers \local_gradefiller\source\grade_source_anonymousgrader
 */
class source_grade_source_anonymousgrader_test extends \advanced_testcase {
    /** @var grade_source_anonymousgrader */
    private grade_source_anonymousgrader $driver;

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);
        $this->driver = new grade_source_anonymousgrader();
    }

    // -----------------------------------------------------------------------
    // get_name tests
    // -----------------------------------------------------------------------

    /**
     * get_name returns a non-empty string identifier for this driver.
     *
     * @covers \local_gradefiller\source\grade_source_anonymousgrader::get_name
     */
    public function test_get_name_returns_string(): void {
        $name = $this->driver->get_name();
        $this->assertIsString($name);
        $this->assertNotEmpty($name);
    }

    // -----------------------------------------------------------------------
    // supports() tests
    // -----------------------------------------------------------------------

    /**
     * supports() must return false when the local_anonymousgrader_exam table is absent.
     *
     * @covers \local_gradefiller\source\grade_source_anonymousgrader::supports
     */
    public function test_supports_returns_false_when_table_missing(): void {
        global $DB;

        if ($DB->get_manager()->table_exists('local_anonymousgrader_exam')) {
            $this->markTestSkipped('local_anonymousgrader_exam table exists; skipping absence test.');
        }

        $cm = (object) ['modname' => 'offlinequiz', 'instance' => 1];
        $this->assertFalse($this->driver->supports($cm));
    }

    /**
     * supports() must return false for non-offlinequiz activities
     * regardless of whether the anonymousgrader tables exist.
     *
     * @covers \local_gradefiller\source\grade_source_anonymousgrader::supports
     * @dataProvider provider_unsupported_mods
     */
    public function test_supports_returns_false_for_non_offlinequiz(string $modname): void {
        $cm = (object) ['modname' => $modname, 'instance' => 1];
        $this->assertFalse($this->driver->supports($cm));
    }

    /**
     * Data provider for non-offlinequiz module names.
     */
    public static function provider_unsupported_mods(): array {
        return [
            'quiz'   => ['quiz'],
            'assign' => ['assign'],
            'forum'  => ['forum'],
        ];
    }

    /**
     * supports() returns true when both conditions are met:
     * - The module is an offlinequiz.
     * - An exam record exists for it in local_anonymousgrader_exam.
     *
     * This test only runs when the local_anonymousgrader plugin is installed.
     *
     * @covers \local_gradefiller\source\grade_source_anonymousgrader::supports
     */
    public function test_supports_returns_true_when_exam_exists(): void {
        global $DB;

        if (!$DB->get_manager()->table_exists('local_anonymousgrader_exam')) {
            $this->markTestSkipped('local_anonymousgrader plugin is not installed.');
        }

        // Insert a fake exam record.
        $examid = $DB->insert_record('local_anonymousgrader_exam', (object) [
            'offlinequizid' => 42,
            'timecreated'   => time(),
        ]);

        $cm = (object) ['modname' => 'offlinequiz', 'instance' => 42];
        $this->assertTrue($this->driver->supports($cm));

        // Cleanup.
        $DB->delete_records('local_anonymousgrader_exam', ['id' => $examid]);
    }

    /**
     * supports() returns false when modname is offlinequiz but no exam record exists.
     *
     * @covers \local_gradefiller\source\grade_source_anonymousgrader::supports
     */
    public function test_supports_returns_false_when_no_exam_record(): void {
        global $DB;

        if (!$DB->get_manager()->table_exists('local_anonymousgrader_exam')) {
            $this->markTestSkipped('local_anonymousgrader plugin is not installed.');
        }

        $cm = (object) ['modname' => 'offlinequiz', 'instance' => 99999];
        $this->assertFalse($this->driver->supports($cm));
    }

    // -----------------------------------------------------------------------
    // is_anonymous_identifier() tests
    // -----------------------------------------------------------------------

    /**
     * is_anonymous_identifier returns true for purely numeric strings.
     *
     * @covers \local_gradefiller\source\grade_source_anonymousgrader::is_anonymous_identifier
     */
    public function test_is_anonymous_identifier_returns_true_for_numeric_string(): void {
        $this->assertTrue($this->driver->is_anonymous_identifier('123'));
        $this->assertTrue($this->driver->is_anonymous_identifier('  456  ')); // trim is applied.
        $this->assertTrue($this->driver->is_anonymous_identifier('0'));
    }

    /**
     * is_anonymous_identifier returns false for non-numeric strings.
     *
     * @covers \local_gradefiller\source\grade_source_anonymousgrader::is_anonymous_identifier
     */
    public function test_is_anonymous_identifier_returns_false_for_non_numeric_string(): void {
        $this->assertFalse($this->driver->is_anonymous_identifier('ANON-001'));
        $this->assertFalse($this->driver->is_anonymous_identifier('STU-001'));
        $this->assertFalse($this->driver->is_anonymous_identifier('abc'));
    }

    /**
     * is_anonymous_identifier returns false for an empty string.
     *
     * @covers \local_gradefiller\source\grade_source_anonymousgrader::is_anonymous_identifier
     */
    public function test_is_anonymous_identifier_returns_false_for_empty_string(): void {
        $this->assertFalse($this->driver->is_anonymous_identifier(''));
        $this->assertFalse($this->driver->is_anonymous_identifier('   '));
    }
}
