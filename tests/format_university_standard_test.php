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
 * Unit tests for format_university_standard spreadsheet format
 *
 * @package    local_gradefiller
 * @category   test
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_gradefiller\tests;

use local_gradefiller\spreadsheet\format_university_standard;

/**
 * Tests for \local_gradefiller\spreadsheet\format_university_standard
 *
 * @covers \local_gradefiller\spreadsheet\format_university_standard
 */
class format_university_standard_test extends \advanced_testcase {
    /** @var format_university_standard */
    private format_university_standard $format;

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);
        $this->format = new format_university_standard();
    }

    // Simple getter tests (no PhpSpreadsheet required).

    /**
     * get_key returns exactly 'university_standard'.
     *
     * @covers \local_gradefiller\spreadsheet\format_university_standard::get_key
     */
    public function test_get_key_returns_university_standard(): void {
        $this->assertSame('university_standard', $this->format->get_key());
    }

    /**
     * get_name returns a non-empty string.
     *
     * @covers \local_gradefiller\spreadsheet\format_university_standard::get_name
     */
    public function test_get_name_returns_string(): void {
        $name = $this->format->get_name();
        $this->assertIsString($name);
        $this->assertNotEmpty($name);
    }

    /**
     * get_description returns a non-empty string.
     *
     * @covers \local_gradefiller\spreadsheet\format_university_standard::get_description
     */
    public function test_get_description_returns_string(): void {
        $description = $this->format->get_description();
        $this->assertIsString($description);
        $this->assertNotEmpty($description);
    }

    /**
     * get_supported_extensions returns the extensions declared by the format.
     *
     * @covers \local_gradefiller\spreadsheet\format_university_standard::get_supported_extensions
     */
    public function test_get_supported_extensions_returns_xlsx_and_xlsm(): void {
        $this->assertSame(['xlsx', 'xlsm'], $this->format->get_supported_extensions());
    }

    /**
     * Upload UI texts are sourced from the spreadsheet format.
     *
     * @covers \local_gradefiller\spreadsheet\format_university_standard::get_upload_label
     * @covers \local_gradefiller\spreadsheet\format_university_standard::get_upload_help
     */
    public function test_upload_ui_texts_are_defined_by_format(): void {
        $this->assertSame($this->format->get_name(), $this->format->get_upload_label());
        $this->assertSame($this->format->get_description(), $this->format->get_upload_help());
    }

    /**
     * Multi-activity exports should let the spreadsheet resolve identifiers automatically.
     *
     * @covers \local_gradefiller\spreadsheet\format_university_standard::get_identifier_mode
     */
    public function test_get_identifier_mode_defaults_to_auto(): void {
        $this->assertSame('auto', $this->format->get_identifier_mode());
    }

    // File-dependent tests (require PhpSpreadsheet).

    /**
     * Returns whether PhpSpreadsheet is available in this Moodle installation.
     */
    private function require_phpspreadsheet(): void {
        global $CFG;
        $autoload = $CFG->dirroot . '/lib/phpspreadsheet/vendor/autoload.php';
        if (!file_exists($autoload)) {
            $this->markTestSkipped('PhpSpreadsheet is not available in this Moodle installation.');
        }
    }

    /**
     * validate_file throws a moodle_exception for a non-existent file.
     *
     * @covers \local_gradefiller\spreadsheet\format_university_standard::validate_file
     */
    public function test_validate_file_throws_for_nonexistent_file(): void {
        $this->require_phpspreadsheet();

        $this->expectException(\moodle_exception::class);
        $this->format->validate_file('/tmp/this_file_does_not_exist_gradefiller_' . uniqid() . '.xlsx');
    }

    /**
     * read_identifiers returns the correct identifiers from a fixture file.
     *
     * @covers \local_gradefiller\spreadsheet\format_university_standard::read_identifiers
     */
    public function test_read_identifiers_with_fixture(): void {
        $this->require_phpspreadsheet();

        $fixturepath = __DIR__ . '/fixtures/sample_apogee.xlsx';
        if (!file_exists($fixturepath)) {
            $this->markTestSkipped('No XLSX fixture available at tests/fixtures/sample_apogee.xlsx');
        }

        $identifiers = $this->format->read_identifiers($fixturepath);

        $this->assertIsArray($identifiers);
        $this->assertNotEmpty($identifiers);

        // Each item must have an 'identifier' and a 'row_number'.
        $first = $identifiers[0];
        $this->assertObjectHasProperty('identifier', $first);
        $this->assertObjectHasProperty('row_number', $first);
        $this->assertIsString($first->identifier);
        $this->assertGreaterThan(format_university_standard::HEADER_ROWS, $first->row_number);
    }

    /**
     * When the first identifier column is empty, the format should fall back to
     * the first populated early column instead of rejecting the workbook.
     *
     * @covers \local_gradefiller\spreadsheet\format_university_standard::read_identifiers
     * @covers \local_gradefiller\spreadsheet\format_university_standard::validate_file
     */
    public function test_read_identifiers_falls_back_to_first_populated_early_column(): void {
        $this->require_phpspreadsheet();

        $filepath = $this->create_apogee_like_workbook([
            ['row' => 18, 'A' => '', 'B' => 'STUDENT-A', 'E' => 0],
            ['row' => 19, 'A' => '', 'B' => 'STUDENT-B', 'E' => 0],
        ]);

        $this->assertTrue($this->format->validate_file($filepath));

        $identifiers = $this->format->read_identifiers($filepath);

        $this->assertCount(2, $identifiers);
        $this->assertSame('STUDENT-A', $identifiers[0]->identifier);
        $this->assertSame(18, $identifiers[0]->row_number);
        $this->assertSame('STUDENT-B', $identifiers[1]->identifier);
    }

    /**
     * Column A remains the preferred identifier column when it already has data.
     *
     * @covers \local_gradefiller\spreadsheet\format_university_standard::read_identifiers
     */
    public function test_read_identifiers_prefers_column_a_when_present(): void {
        $this->require_phpspreadsheet();

        $filepath = $this->create_apogee_like_workbook([
            ['row' => 18, 'A' => 'ID-001', 'B' => 'SHOULD-NOT-BE-USED', 'E' => 0],
            ['row' => 19, 'A' => 'ID-002', 'B' => 'SHOULD-NOT-BE-USED', 'E' => 0],
        ]);

        $identifiers = $this->format->read_identifiers($filepath);

        $this->assertCount(2, $identifiers);
        $this->assertSame('ID-001', $identifiers[0]->identifier);
        $this->assertSame('ID-002', $identifiers[1]->identifier);
    }

    /**
     * write_grades throws a moodle_exception for an unsupported output extension (e.g. CSV).
     *
     * @covers \local_gradefiller\spreadsheet\format_university_standard::write_grades
     */
    public function test_write_grades_throws_for_unsupported_extension(): void {
        $this->require_phpspreadsheet();

        $this->expectException(\moodle_exception::class);

        $tmpfile = tempnam(sys_get_temp_dir(), 'gradefiller_test_') . '.csv';
        touch($tmpfile);
        try {
            $this->format->write_grades($tmpfile, []);
        } finally {
            @unlink($tmpfile);
        }
    }

    /**
     * Create a lightweight workbook compatible with the university format.
     *
     * @param array $rows Row definitions keyed by Excel column letters
     * @return string
     */
    private function create_apogee_like_workbook(array $rows): string {
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setCellValue('A17', 'N°anonyme');
        $sheet->setCellValue('B17', 'Nom');
        $sheet->setCellValue('C17', 'Prénom');
        $sheet->setCellValue('D17', 'Naissance');
        $sheet->setCellValue('E17', 'Note');

        foreach ($rows as $rowdata) {
            $row = (int) $rowdata['row'];
            foreach (['A', 'B', 'C', 'D', 'E'] as $column) {
                if (array_key_exists($column, $rowdata)) {
                    $sheet->setCellValue($column . $row, $rowdata[$column]);
                }
            }
        }

        $filepath = make_temp_directory('gradefiller') . '/apogee_fixture_' . uniqid('', true) . '.xlsx';
        (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet))->save($filepath);

        return $filepath;
    }
}
