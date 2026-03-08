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
 * Unit tests for the download_handler utility class
 *
 * @package    local_gradefiller
 * @category   test
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_gradefiller\tests;

use local_gradefiller\util\download_handler;

/**
 * Tests for \local_gradefiller\util\download_handler
 *
 * @covers \local_gradefiller\util\download_handler
 */
class download_handler_test extends \advanced_testcase {

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);
    }

    /**
     * generate_filename returns a string beginning with the given prefix
     * and ending with .{extension}.
     *
     * @covers \local_gradefiller\util\download_handler::generate_filename
     */
    public function test_generate_filename_returns_correct_format(): void {
        $filename = download_handler::generate_filename('filled_grades', 'xlsx');

        $this->assertIsString($filename);
        $this->assertStringStartsWith('filled_grades_', $filename);
        $this->assertStringEndsWith('.xlsx', $filename);
    }

    /**
     * generate_filename embeds a date portion in YYYY-MM-DD format.
     *
     * @covers \local_gradefiller\util\download_handler::generate_filename
     */
    public function test_generate_filename_contains_date_portion(): void {
        $filename = download_handler::generate_filename('export', 'ods');

        // The date portion must match YYYY-MM-DD.
        $today = date('Y-m-d');
        $this->assertStringContainsString($today, $filename);
    }

    /**
     * generate_filename accepts any extension and returns it unchanged (lowercased by caller).
     *
     * @covers \local_gradefiller\util\download_handler::generate_filename
     */
    public function test_generate_filename_accepts_various_extensions(): void {
        foreach (['xlsx', 'xlsm', 'ods', 'csv'] as $ext) {
            $filename = download_handler::generate_filename('grades', $ext);
            $this->assertStringEndsWith('.' . $ext, $filename);
        }
    }
}
