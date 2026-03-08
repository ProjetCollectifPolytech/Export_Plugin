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
 * Unit tests for the file_handler utility class
 *
 * @package    local_gradefiller
 * @category   test
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_gradefiller\tests;

use local_gradefiller\util\file_handler;

/**
 * Tests for \local_gradefiller\util\file_handler
 *
 * @covers \local_gradefiller\util\file_handler
 */
class file_handler_test extends \advanced_testcase {
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);
    }

    // Tests for validate_extension.

    /**
     * validate_extension returns true for extensions present in the allowed list.
     *
     * @covers \local_gradefiller\util\file_handler::validate_extension
     * @dataProvider provider_allowed_extensions
     */
    public function test_validate_extension_returns_true_for_allowed_extension(string $filename): void {
        $allowed = ['xlsx', 'xlsm', 'xls', 'ods', 'csv'];
        $this->assertTrue(file_handler::validate_extension($filename, $allowed));
    }

    /**
     * Data provider for filenames with allowed extensions.
     */
    public static function provider_allowed_extensions(): array {
        return [
            'xlsx file' => ['grades.xlsx'],
            'xlsm file' => ['grades.xlsm'],
            'xls file'  => ['grades.xls'],
            'ods file'  => ['grades.ods'],
            'csv file'  => ['grades.csv'],
        ];
    }

    /**
     * validate_extension returns false for extensions absent from the allowed list.
     *
     * @covers \local_gradefiller\util\file_handler::validate_extension
     * @dataProvider provider_disallowed_extensions
     */
    public function test_validate_extension_returns_false_for_disallowed_extension(string $filename): void {
        $allowed = ['xlsx', 'xlsm', 'xls', 'ods', 'csv'];
        $this->assertFalse(file_handler::validate_extension($filename, $allowed));
    }

    /**
     * Data provider for filenames with disallowed extensions.
     */
    public static function provider_disallowed_extensions(): array {
        return [
            'pdf file'  => ['grades.pdf'],
            'txt file'  => ['grades.txt'],
            'docx file' => ['grades.docx'],
            'zip file'  => ['archive.zip'],
            'php file'  => ['attack.php'],
        ];
    }

    /**
     * validate_extension is case-insensitive — uppercase extensions must also match.
     *
     * @covers \local_gradefiller\util\file_handler::validate_extension
     */
    public function test_validate_extension_handles_uppercase_extension(): void {
        $allowed = ['xlsx', 'ods'];
        $this->assertTrue(file_handler::validate_extension('grades.XLSX', $allowed));
        $this->assertTrue(file_handler::validate_extension('Grades.Ods', $allowed));
    }

    /**
     * validate_extension returns false when the filename has no extension.
     *
     * @covers \local_gradefiller\util\file_handler::validate_extension
     */
    public function test_validate_extension_returns_false_for_no_extension(): void {
        $allowed = ['xlsx', 'ods'];
        $this->assertFalse(file_handler::validate_extension('grades', $allowed));
    }

    // Tests for cleanup.

    /**
     * cleanup removes an existing file from the filesystem.
     *
     * @covers \local_gradefiller\util\file_handler::cleanup
     */
    public function test_cleanup_removes_existing_file(): void {
        $tmpfile = tempnam(sys_get_temp_dir(), 'gradefiller_test_');
        $this->assertFileExists($tmpfile);

        file_handler::cleanup($tmpfile);

        $this->assertFileDoesNotExist($tmpfile);
    }

    /**
     * cleanup does not throw when the file does not exist.
     *
     * @covers \local_gradefiller\util\file_handler::cleanup
     */
    public function test_cleanup_silently_ignores_missing_file(): void {
        // Should not throw any exception.
        file_handler::cleanup('/tmp/gradefiller_nonexistent_file_' . uniqid() . '.tmp');
        $this->assertTrue(true); // Reached here without exception.
    }
}
