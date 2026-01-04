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
 * University standard format implementation
 *
 * Format: Skip 17 lines, ID in Column A, Grade in Column E
 *
 * @package    local_gradefiller
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_gradefiller\spreadsheet;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/phpspreadsheet/vendor/autoload.php');

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Writer\Xls;
use PhpOffice\PhpSpreadsheet\Writer\Ods;
use PhpOffice\PhpSpreadsheet\Writer\Csv;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

/**
 * University standard format handler
 *
 * This format expects:
 * - First 17 rows are header/metadata
 * - Column A (index 0) contains student identifiers
 * - Column E (index 4) should receive grades
 *
 * @package    local_gradefiller
 */
class format_university_standard implements spreadsheet_format_interface {

    /** @var int Number of header rows to skip */
    const HEADER_ROWS = 17;

    /** @var int Column index for identifier (0-based) */
    const COLUMN_IDENTIFIER = 0; // Column A

    /** @var int Column index for grade (0-based) */
    const COLUMN_GRADE = 4; // Column E

    /**
     * Get the human-readable name of this format
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('format_university_standard_name', 'local_gradefiller');
    }

    /**
     * Get the unique identifier for this format
     *
     * @return string
     */
    public function get_key(): string {
        return 'university_standard';
    }

    /**
     * Get the description of this format
     *
     * @return string
     */
    public function get_description(): string {
        return get_string('format_university_standard_desc', 'local_gradefiller');
    }

    /**
     * Read identifiers from the spreadsheet
     *
     * @param string $filepath Path to the uploaded spreadsheet file
     * @return array Array of objects with properties: identifier, row_number
     * @throws \moodle_exception
     */
    public function read_identifiers(string $filepath): array {
        try {
            $spreadsheet = IOFactory::load($filepath);
            $sheet = $spreadsheet->getActiveSheet();
            $highestRow = $sheet->getHighestRow();

            $identifiers = [];
            for ($row = self::HEADER_ROWS + 1; $row <= $highestRow; $row++) {
                $identifier = $sheet->getCellByColumnAndRow(
                    self::COLUMN_IDENTIFIER + 1, 
                    $row
                )->getValue();

                // Skip empty rows.
                if (empty($identifier)) {
                    continue;
                }

                // Clean identifier.
                $identifier = trim($identifier);

                $identifiers[] = (object)[
                    'identifier' => $identifier,
                    'row_number' => $row,
                ];
            }

            return $identifiers;

        } catch (\Exception $e) {
            throw new \moodle_exception('error_reading_file', 'local_gradefiller', '', null, $e->getMessage());
        }
    }

    /**
     * Write grades to the spreadsheet
     *
     * @param string $filepath Path to the original spreadsheet file
     * @param array $grades Array of objects with properties: identifier, grade, row_number
     * @return string Path to the filled spreadsheet file
     * @throws \moodle_exception
     */
    public function write_grades(string $filepath, array $grades): string {
        global $CFG;

        try {
            $spreadsheet = IOFactory::load($filepath);
            $sheet = $spreadsheet->getActiveSheet();

            // Create a map for quick lookup.
            $grademap = [];
            foreach ($grades as $gradeobj) {
                $grademap[$gradeobj->identifier] = $gradeobj;
            }

            // Write grades to the specified column.
            foreach ($grademap as $identifier => $gradeobj) {
                if (isset($gradeobj->row_number) && isset($gradeobj->grade)) {
                    $cell = $sheet->getCellByColumnAndRow(
                        self::COLUMN_GRADE + 1,
                        $gradeobj->row_number
                    );
                    
                    // Set the value as numeric
                    $cell->setValueExplicit(
                        $gradeobj->grade,
                        \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC
                    );
                    
                    // Format to show 2 decimal places
                    $cell->getStyle()->getNumberFormat()->setFormatCode(
                        NumberFormat::FORMAT_NUMBER_00
                    );
                }
            }

            // Detect original file format from extension.
            $extension = strtolower(pathinfo($filepath, PATHINFO_EXTENSION));
            
            // Save to temporary file with same format.
            $tempdir = make_temp_directory('gradefiller');
            $outputfile = $tempdir . '/' . 'filled_' . time() . '.' . $extension;

            // Choose appropriate writer based on format.
            switch ($extension) {
                case 'xlsx':
                case 'xlsm': // Excel with macros - use Xlsx writer to preserve macros.
                case 'xlsb': // Excel binary - use Xlsx writer.
                    $writer = new Xlsx($spreadsheet);
                    break;
                case 'xls':
                    $writer = new Xls($spreadsheet);
                    break;
                case 'ods':
                    $writer = new Ods($spreadsheet);
                    break;
                case 'csv':
                    $writer = new Csv($spreadsheet);
                    break;
                default:
                    // Default to xlsx for unknown formats.
                    $writer = new Xlsx($spreadsheet);
                    $outputfile = $tempdir . '/' . 'filled_' . time() . '.xlsx';
            }
            
            $writer->save($outputfile);

            return $outputfile;

        } catch (\Exception $e) {
            throw new \moodle_exception('error_writing_file', 'local_gradefiller', '', null, $e->getMessage());
        }
    }

    /**
     * Validate that the file can be processed by this format
     *
     * @param string $filepath Path to the spreadsheet file
     * @return bool True if valid
     * @throws \moodle_exception with detailed error message
     */
    public function validate_file(string $filepath): bool {
        try {
            $spreadsheet = IOFactory::load($filepath);
            $sheet = $spreadsheet->getActiveSheet();
            $highestRow = $sheet->getHighestRow();

            // Check if there are enough rows.
            if ($highestRow <= self::HEADER_ROWS) {
                throw new \moodle_exception(
                    'error_format_insufficient_rows',
                    'local_gradefiller',
                    '',
                    self::HEADER_ROWS + 1
                );
            }

            // Check if column A has data after header rows.
            $hasdata = false;
            for ($row = self::HEADER_ROWS + 1; $row <= min($highestRow, self::HEADER_ROWS + 10); $row++) {
                $value = $sheet->getCellByColumnAndRow(self::COLUMN_IDENTIFIER + 1, $row)->getValue();
                if (!empty(trim($value))) {
                    $hasdata = true;
                    break;
                }
            }

            if (!$hasdata) {
                throw new \moodle_exception('error_format_no_identifiers', 'local_gradefiller');
            }

            return true;

        } catch (\moodle_exception $e) {
            throw $e;
        } catch (\Exception $e) {
            throw new \moodle_exception('error_format_invalid', 'local_gradefiller', '', null, $e->getMessage());
        }
    }
}
