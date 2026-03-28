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
 * Tests for the university identifier column resolver.
 *
 * @package    local_gradefiller
 * @category   test
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_gradefiller\tests;

use local_gradefiller\spreadsheet\university_standard_identifier_column_resolver;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Covers column detection for the university spreadsheet format.
 *
 * @covers \local_gradefiller\spreadsheet\university_standard_identifier_column_resolver
 */
final class university_standard_identifier_column_resolver_test extends \advanced_testcase {
    /** @var university_standard_identifier_column_resolver */
    private university_standard_identifier_column_resolver $resolver;

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);
        $this->resolver = new university_standard_identifier_column_resolver();
    }

    /**
     * The preferred identifier column must win when it already contains values.
     *
     * @return void
     */
    public function test_resolve_identifier_column_prefers_primary_column(): void {
        $sheet = $this->create_worksheet([
            ['A18' => 'ID-001', 'B18' => 'ALT-001'],
            ['A19' => 'ID-002', 'B19' => 'ALT-002'],
        ]);

        $column = $this->resolver->resolve_identifier_column($sheet, 19);

        $this->assertSame(0, $column);
    }

    /**
     * The first populated candidate column should be selected when column A is empty.
     *
     * @return void
     */
    public function test_resolve_identifier_column_falls_back_to_best_populated_candidate(): void {
        $sheet = $this->create_worksheet([
            ['B18' => 'STUDENT-001'],
            ['B19' => 'STUDENT-002'],
            ['B20' => 'STUDENT-003'],
            ['C18' => 'SECONDARY-001'],
        ]);

        $column = $this->resolver->resolve_identifier_column($sheet, 20);

        $this->assertSame(1, $column);
    }

    /**
     * Identifier detection should distinguish between empty and populated columns.
     *
     * @return void
     */
    public function test_has_identifier_data_requires_at_least_one_non_empty_cell(): void {
        $emptysheet = $this->create_worksheet([]);
        $this->assertFalse($this->resolver->has_identifier_data($emptysheet, 0, 25));

        $populatedsheet = $this->create_worksheet([
            ['C18' => 'ID-777'],
        ]);
        $this->assertTrue($this->resolver->has_identifier_data($populatedsheet, 2, 25));
    }

    /**
     * Skip the test when the PhpSpreadsheet library is unavailable.
     *
     * @return void
     */
    private function require_phpspreadsheet(): void {
        global $CFG;

        $autoload = $CFG->dirroot . '/lib/phpspreadsheet/vendor/autoload.php';
        if (!file_exists($autoload)) {
            $this->markTestSkipped('PhpSpreadsheet is not available in this Moodle installation.');
        }

        require_once($autoload);
    }

    /**
     * Create a worksheet populated with the supplied cells.
     *
     * @param array<int, array<string, string>> $rows
     * @return Worksheet
     */
    private function create_worksheet(array $rows): Worksheet {
        $this->require_phpspreadsheet();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        foreach ($rows as $cells) {
            foreach ($cells as $coordinate => $value) {
                $sheet->setCellValue($coordinate, $value);
            }
        }

        return $sheet;
    }
}
