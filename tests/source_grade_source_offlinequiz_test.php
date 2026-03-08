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
 * Unit tests for the grade_source_offlinequiz source driver
 *
 * @package    local_gradefiller
 * @category   test
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_gradefiller\tests;

use local_gradefiller\source\grade_source_offlinequiz;

/**
 * Tests for \local_gradefiller\source\grade_source_offlinequiz
 *
 * @covers \local_gradefiller\source\grade_source_offlinequiz
 */
class source_grade_source_offlinequiz_test extends \advanced_testcase {
    /** @var grade_source_offlinequiz */
    private grade_source_offlinequiz $driver;

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);
        $this->driver = new grade_source_offlinequiz();
    }

    // Tests for get_name.

    /**
     * get_name returns a non-empty string identifier for this driver.
     *
     * @covers \local_gradefiller\source\grade_source_offlinequiz::get_name
     */
    public function test_get_name_returns_string(): void {
        $name = $this->driver->get_name();
        $this->assertIsString($name);
        $this->assertNotEmpty($name);
    }

    // Tests for supports.

    /**
     * supports() must return true for offlinequiz course modules.
     *
     * @covers \local_gradefiller\source\grade_source_offlinequiz::supports
     */
    public function test_supports_returns_true_for_offlinequiz(): void {
        $cm = (object) ['modname' => 'offlinequiz', 'instance' => 1];
        $this->assertTrue($this->driver->supports($cm));
    }

    /**
     * supports() must return false for non-offlinequiz activities.
     *
     * @covers \local_gradefiller\source\grade_source_offlinequiz::supports
     * @dataProvider provider_unsupported_mods
     */
    public function test_supports_returns_false_for_other_mods(string $modname): void {
        $cm = (object) ['modname' => $modname, 'instance' => 1];
        $this->assertFalse($this->driver->supports($cm));
    }

    /**
     * Data provider with activity types that are NOT offlinequiz.
     */
    public static function provider_unsupported_mods(): array {
        return [
            'quiz'   => ['quiz'],
            'assign' => ['assign'],
            'forum'  => ['forum'],
            'label'  => ['label'],
            'empty'  => [''],
        ];
    }

    // Tests for is_anonymous_identifier.

    /**
     * is_anonymous_identifier returns true for any non-empty, non-whitespace-only string.
     *
     * @covers \local_gradefiller\source\grade_source_offlinequiz::is_anonymous_identifier
     */
    public function test_is_anonymous_identifier_returns_true_for_non_empty_string(): void {
        $this->assertTrue($this->driver->is_anonymous_identifier('KEY-001'));
        $this->assertTrue($this->driver->is_anonymous_identifier('123'));
        $this->assertTrue($this->driver->is_anonymous_identifier('abc'));
    }

    /**
     * is_anonymous_identifier returns false for an empty string.
     *
     * @covers \local_gradefiller\source\grade_source_offlinequiz::is_anonymous_identifier
     */
    public function test_is_anonymous_identifier_returns_false_for_empty_string(): void {
        $this->assertFalse($this->driver->is_anonymous_identifier(''));
    }

    /**
     * is_anonymous_identifier returns false for a whitespace-only string.
     *
     * @covers \local_gradefiller\source\grade_source_offlinequiz::is_anonymous_identifier
     */
    public function test_is_anonymous_identifier_returns_false_for_whitespace_only(): void {
        $this->assertFalse($this->driver->is_anonymous_identifier('   '));
    }

    // Tests for fetch_grade_by_anonkey.

    /**
     * fetch_grade_by_anonkey throws a dml_exception when the offlinequiz table is absent.
     *
     * @covers \local_gradefiller\source\grade_source_offlinequiz::fetch_grade_by_anonkey
     */
    public function test_fetch_grade_by_anonkey_throws_when_offlinequiz_not_installed(): void {
        global $DB;

        // Skip if offlinequiz is actually installed.
        if ($DB->get_manager()->table_exists('offlinequiz')) {
            $this->markTestSkipped('offlinequiz tables exist; skipping absence test.');
        }

        $this->expectException(\dml_exception::class);

        // This will fail at get_coursemodule_from_id if the offlinequiz table is absent.
        $this->driver->fetch_grade_by_anonkey(1, 'KEY-001');
    }
}
