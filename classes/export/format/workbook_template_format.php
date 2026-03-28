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
 * Workbook template export format for course gradebook exports.
 *
 * @package    local_gradefiller
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_gradefiller\export\format;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/phpspreadsheet/vendor/autoload.php');

use Exception;
use local_gradefiller\export\course_export_format_interface;
use moodle_exception;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Injects the classic Moodle grade export into a dedicated worksheet.
 *
 * @package    local_gradefiller
 */
class workbook_template_format implements course_export_format_interface {
    /** @var string Sheet used to host the Moodle grade export table */
    private const EXPORT_SHEET_NAME = 'Export Moodle';

    /** @var string[] Allowed workbook extensions for this export mode */
    private const ALLOWED_EXTENSIONS = ['xlsx', 'xlsm'];

    /**
     * @inheritDoc
     */
    public function get_name(): string {
        return get_string('gradebook_export_format_workbook_name', 'local_gradefiller');
    }

    /**
     * @inheritDoc
     */
    public function get_key(): string {
        return 'workbook_template';
    }

    /**
     * @inheritDoc
     */
    public function get_description(): string {
        return get_string('gradebook_export_format_workbook_desc', 'local_gradefiller');
    }

    /**
     * @inheritDoc
     */
    public function get_supported_extensions(): array {
        return self::ALLOWED_EXTENSIONS;
    }

    /**
     * @inheritDoc
     */
    public function validate_template(string $filepath): bool {
        $this->validate_extension($filepath);

        try {
            IOFactory::load($filepath);
        } catch (Exception $e) {
            debugging('Grade Filler could not open workbook template: ' . $e->getMessage(), DEBUG_DEVELOPER);
            throw new moodle_exception('error_export_template_invalid_generic', 'local_gradefiller');
        }

        return true;
    }

    /**
     * @inheritDoc
     */
    public function export_to_template(string $filepath, object $exportdata): string {
        $extension = $this->validate_extension($filepath);
        $this->validate_template($filepath);

        try {
            $spreadsheet = IOFactory::load($filepath);
            $worksheet = $this->reset_export_sheet($spreadsheet);

            $this->write_export_table($worksheet, $exportdata);

            $worksheet->freezePane('A2');
            $worksheet->getStyle('1:1')->getFont()->setBold(true);
            $worksheet->setAutoFilter(
                'A1:' . Coordinate::stringFromColumnIndex(count($exportdata->headers)) . '1'
            );

            $outputfile = make_temp_directory('gradefiller') . '/'
                . 'gradebook_export_' . uniqid('', true) . '.' . $extension;

            $writer = new Xlsx($spreadsheet);
            $writer->save($outputfile);

            return $outputfile;
        } catch (moodle_exception $e) {
            throw $e;
        } catch (Exception $e) {
            debugging('Grade Filler could not write workbook template: ' . $e->getMessage(), DEBUG_DEVELOPER);
            throw new moodle_exception('error_export_template_write_generic', 'local_gradefiller');
        }
    }

    /**
     * Create or replace the worksheet used for Grade Filler exports.
     *
     * @param Spreadsheet $spreadsheet Workbook instance
     * @return Worksheet
     */
    private function reset_export_sheet(Spreadsheet $spreadsheet): Worksheet {
        $existing = $spreadsheet->getSheetByName(self::EXPORT_SHEET_NAME);
        $index = $existing ? $spreadsheet->getIndex($existing) : $spreadsheet->getSheetCount();

        if ($existing) {
            $spreadsheet->removeSheetByIndex($index);
        }

        $worksheet = new Worksheet($spreadsheet, self::EXPORT_SHEET_NAME);
        $spreadsheet->addSheet($worksheet, $index);
        $spreadsheet->setActiveSheetIndex($index);

        return $worksheet;
    }

    /**
     * Write the exported table while preserving Moodle's already formatted values.
     *
     * We intentionally write scalar values explicitly as strings because the
     * grade export builder has already decided how grades should be rendered
     * (decimal places, percentages, letters, timestamps, leading zeros, etc.).
     * Letting PhpSpreadsheet auto-cast those values would strip trailing zeros
     * such as "15.00" and subtly change the exported workbook.
     *
     * @param Worksheet $worksheet Target worksheet
     * @param object $exportdata Exported headers and rows
     * @return void
     */
    private function write_export_table(Worksheet $worksheet, object $exportdata): void {
        foreach ($exportdata->headers as $columnindex => $value) {
            $worksheet->setCellValueExplicitByColumnAndRow(
                $columnindex + 1,
                1,
                (string) $value,
                DataType::TYPE_STRING
            );
        }

        foreach ($exportdata->rows as $rowindex => $rowvalues) {
            foreach ($rowvalues as $columnindex => $value) {
                if ($value === null) {
                    continue;
                }

                $worksheet->setCellValueExplicitByColumnAndRow(
                    $columnindex + 1,
                    $rowindex + 2,
                    (string) $value,
                    DataType::TYPE_STRING
                );
            }
        }
    }

    /**
     * Validate and return the file extension supported by the export format.
     *
     * @param string $filepath Filepath to validate
     * @return string
     */
    private function validate_extension(string $filepath): string {
        $extension = strtolower(pathinfo($filepath, PATHINFO_EXTENSION));
        if (!in_array($extension, self::ALLOWED_EXTENSIONS, true)) {
            throw new moodle_exception('error_export_template_extension', 'local_gradefiller', '', $extension);
        }

        return $extension;
    }
}
