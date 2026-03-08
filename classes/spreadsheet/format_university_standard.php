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
    const COLUMN_IDENTIFIER = 0; // Column A.

    /** @var int Column index for grade (0-based) */
    const COLUMN_GRADE = 4; // Column E.

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
            $highestrow = $sheet->getHighestRow();

            $identifiers = [];
            for ($row = self::HEADER_ROWS + 1; $row <= $highestrow; $row++) {
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
     * Write grades to the spreadsheet using direct XML manipulation (ZipArchive)
     * This preserves Macros, VML, and ActiveX controls perfectly.
     *
     * @param string $filepath Path to the original spreadsheet file
     * @param array $grades Array of objects with properties: identifier, grade, row_number
     * @return string Path to the filled spreadsheet file
     * @throws \moodle_exception
     */
    public function write_grades(string $filepath, array $grades): string {
        global $CFG;

        // 1. Prepare the output file.
        $extension = strtolower(pathinfo($filepath, PATHINFO_EXTENSION));

        // Validate that the file format supports ZipArchive-based writing.
        $supportedwriteformats = ['xlsx', 'xlsm'];
        if (!in_array($extension, $supportedwriteformats)) {
            throw new \moodle_exception('error_unsupported_write_format', 'local_gradefiller', '', $extension);
        }
        $tempdir = make_temp_directory('gradefiller');
        $outputfile = $tempdir . '/' . 'filled_' . uniqid('', true) . '.' . $extension;

        // Copy the original file (never modify the original).
        if (!copy($filepath, $outputfile)) {
            throw new \moodle_exception('error_writing_file', 'local_gradefiller', '', null, 'Could not copy temp file');
        }

        // 2. Use ZipArchive to open the .xlsm without corrupting it.
        $zip = new \ZipArchive();
        if ($zip->open($outputfile) !== true) {
            throw new \moodle_exception('error_writing_file', 'local_gradefiller', '', null, 'Could not open XLSX/XLSM as ZIP');
        }

        // Target the first worksheet (standard for this export type).
        $sheetname = 'xl/worksheets/sheet1.xml';
        $xmlstring = $zip->getFromName($sheetname);

        if (!$xmlstring) {
            $zip->close();
            throw new \moodle_exception('error_writing_file', 'local_gradefiller', '', null, 'Could not find sheet1.xml');
        }

        // 3. Manipulate the XML with DOMDocument.
        $dom = new \DOMDocument();
        // Prevent warnings on namespaces.
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = false;
        $dom->loadXML($xmlstring);

        $xpath = new \DOMXPath($dom);
        // Register the default Excel namespace.
        $xpath->registerNamespace('x', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');

        // Build a map for fast access: [row_number => grade].
        $grademap = [];
        foreach ($grades as $grade) {
            if (isset($grade->row_number) && isset($grade->grade)) {
                $grademap[$grade->row_number] = $grade->grade;
            }
        }

        // 4. Iterate and update cells.
        // Find all rows that are in our map.
        foreach ($grademap as $rownum => $gradeval) {
            // Column E is the 5th letter. In Excel XML the reference is "E18" for row 18.
            $cellref = 'E' . $rownum;

            // Search for the specific cell.
            // Note: look for <c> tag with attribute r="E{row}".
            $entries = $xpath->query("//x:c[@r='$cellref']");

            if ($entries->length > 0) {
                $cell = $entries->item(0);

                // Remove the 't' (type) attribute if present to avoid shared-string type.
                // We want a plain numeric value.
                if ($cell->hasAttribute('t')) {
                    $cell->removeAttribute('t');
                }

                // Look for the <v> (value) tag inside the cell.
                $valuenodes = $xpath->query("x:v", $cell);

                if ($valuenodes->length > 0) {
                    // Update the existing value.
                    $valuenodes->item(0)->nodeValue = $gradeval;
                } else {
                    // Create the value tag if it does not exist (empty cell).
                    $v = $dom->createElement('v', $gradeval);
                    $cell->appendChild($v);
                }
            }
        }

        // 5. Save the modified XML back into the ZIP.
        $newxml = $dom->saveXML();
        $zip->addFromString($sheetname, $newxml);

        // Close and finalise.
        $zip->close();

        return $outputfile;
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
            $highestrow = $sheet->getHighestRow();

            // Check if there are enough rows.
            if ($highestrow <= self::HEADER_ROWS) {
                throw new \moodle_exception(
                    'error_format_insufficient_rows',
                    'local_gradefiller',
                    '',
                    self::HEADER_ROWS + 1
                );
            }

            // Check if column A has data after header rows.
            $hasdata = false;
            for ($row = self::HEADER_ROWS + 1; $row <= min($highestrow, self::HEADER_ROWS + 10); $row++) {
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