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

namespace local_gradefiller\tests;

use local_gradefiller\export\format\workbook_template_format;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Tests for the workbook template export format.
 *
 * @package    local_gradefiller
 */
final class workbook_template_format_test extends \advanced_testcase {
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);
    }

    public function test_export_to_template_creates_export_sheet(): void {
        $this->require_phpspreadsheet();

        $format = new workbook_template_format();
        $template = $this->create_template_workbook();
        $output = $format->export_to_template($template, (object) [
            'headers' => ['Student', 'Grade'],
            'rows' => [
                ['Alice', '15.00'],
                ['Bob', '12.00'],
            ],
        ]);

        $workbook = IOFactory::load($output);
        $sheet = $workbook->getSheetByName('Export Moodle');

        $this->assertNotNull($sheet);
        $this->assertSame('Student', $sheet->getCell('A1')->getValue());
        $this->assertSame('15.00', $sheet->getCell('B2')->getFormattedValue());
    }

    public function test_validate_template_rejects_unsupported_extensions(): void {
        $this->require_phpspreadsheet();
        $this->expectException(\moodle_exception::class);

        (new workbook_template_format())->validate_template(__DIR__ . '/fixtures/not_supported.ods');
    }

    public function test_export_to_template_accepts_xlsm_extension(): void {
        $this->require_phpspreadsheet();

        $format = new workbook_template_format();
        $template = $this->create_template_workbook('xlsm');
        $output = $format->export_to_template($template, (object) [
            'headers' => ['Student', 'Grade'],
            'rows' => [
                ['Alice', '15.00'],
            ],
        ]);

        $this->assertStringEndsWith('.xlsm', $output);
        $workbook = IOFactory::load($output);
        $sheet = $workbook->getSheetByName('Export Moodle');

        $this->assertNotNull($sheet);
        $this->assertSame('Alice', $sheet->getCell('A2')->getValue());
    }

    /**
     * Ensure PhpSpreadsheet is present in the current Moodle test installation.
     */
    private function require_phpspreadsheet(): void {
        global $CFG;

        if (!file_exists($CFG->dirroot . '/lib/phpspreadsheet/vendor/autoload.php')) {
            $this->markTestSkipped('PhpSpreadsheet is not available in this Moodle installation.');
        }
    }

    /**
     * Create a simple XLSX template workbook for the export format.
     *
     * @return string
     */
    private function create_template_workbook(string $extension = 'xlsx'): string {
        $spreadsheet = new Spreadsheet();
        $spreadsheet->getActiveSheet()->setTitle('Template');
        $spreadsheet->getActiveSheet()->setCellValue('A1', 'Keep me');

        $filepath = make_temp_directory('gradefiller') . '/template_' . uniqid('', true) . '.' . $extension;
        (new Xlsx($spreadsheet))->save($filepath);

        return $filepath;
    }
}
