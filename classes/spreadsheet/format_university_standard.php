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
 * University standard format implementation.
 *
 * @package    local_gradefiller
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_gradefiller\spreadsheet;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/phpspreadsheet/vendor/autoload.php');

use Exception;
use moodle_exception;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Spreadsheet strategy for the Apogee-like university template.
 *
 * This format expects:
 * - 17 header rows
 * - an identifier in one of the first early columns, with column A preferred
 * - grades written back to column E in the first worksheet
 *
 * @package    local_gradefiller
 */
class format_university_standard implements spreadsheet_format_interface {
    /** @var string[] Supported file extensions for this format */
    private const ALLOWED_EXTENSIONS = ['xlsx', 'xlsm'];

    /** @var string OpenXML worksheet path used by the template */
    private const SHEET_XML_PATH = 'xl/worksheets/sheet1.xml';

    /** @var string Grade column letter in the target worksheet */
    private const GRADE_COLUMN_LETTER = 'E';

    /** @var int Number of header rows to skip */
    public const HEADER_ROWS = 17;

    /** @var int Preferred identifier column index (zero-based) */
    private const COLUMN_IDENTIFIER = 0;

    /**
     * Get the human-readable name of this format.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('format_university_standard_name', 'local_gradefiller');
    }

    /**
     * Get the stable key used to select this format.
     *
     * @return string
     */
    public function get_key(): string {
        return 'university_standard';
    }

    /**
     * Get the human-readable description of this format.
     *
     * @return string
     */
    public function get_description(): string {
        return get_string('format_university_standard_desc', 'local_gradefiller');
    }

    /**
     * Return the file extensions accepted by this format.
     *
     * @return string[]
     */
    public function get_supported_extensions(): array {
        return self::ALLOWED_EXTENSIONS;
    }

    /**
     * Return the label shown next to the upload control.
     *
     * @return string
     */
    public function get_upload_label(): string {
        return $this->get_name();
    }

    /**
     * Return the help shown next to the upload control.
     *
     * @return string
     */
    public function get_upload_help(): string {
        return $this->get_description();
    }

    /**
     * Apogee-style workbooks may mix Moodle IDs and anonymous identifiers, so
     * multi-activity exports let the spreadsheet pipeline auto-detect them.
     *
     * @return string
     */
    public function get_identifier_mode(): string {
        return self::IDENTIFIER_MODE_AUTO;
    }

    /**
     * Read identifiers from the spreadsheet.
     *
     * @param string $filepath
     * @return array
     */
    public function read_identifiers(string $filepath): array {
        try {
            $spreadsheet = IOFactory::load($filepath);
            $sheet = $spreadsheet->getActiveSheet();
            $highestrow = $sheet->getHighestRow();
            $identifiercolumn = $this->resolve_identifier_column($sheet, $highestrow);

            $identifiers = [];
            for ($row = self::HEADER_ROWS + 1; $row <= $highestrow; $row++) {
                $identifier = $sheet->getCellByColumnAndRow($identifiercolumn + 1, $row)->getValue();
                if (empty($identifier)) {
                    continue;
                }

                $identifiers[] = (object) [
                    'identifier' => trim((string) $identifier),
                    'row_number' => $row,
                ];
            }

            return $identifiers;
        } catch (moodle_exception $e) {
            throw $e;
        } catch (Exception $e) {
            throw new moodle_exception('error_file_read_failed', 'local_gradefiller');
        }
    }

    /**
     * Write grades back into the workbook template.
     *
     * The workbook is copied first, then updated directly in OOXML form so
     * existing workbook artefacts such as macros remain intact.
     *
     * @param string $filepath
     * @param array $grades
     * @return string
     */
    public function write_grades(string $filepath, array $grades): string {
        $this->validate_extension($filepath);

        $cellvalues = [];
        foreach ($grades as $grade) {
            if (!isset($grade->row_number) || !isset($grade->grade)) {
                continue;
            }

            $cellvalues[self::GRADE_COLUMN_LETTER . (int) $grade->row_number] = $grade->grade;
        }

        try {
            return (new openxml_workbook_writer())->write_numeric_cells($filepath, self::SHEET_XML_PATH, $cellvalues);
        } catch (moodle_exception $e) {
            throw $e;
        } catch (Exception $e) {
            throw new moodle_exception('error_file_write_failed', 'local_gradefiller');
        }
    }

    /**
     * Validate that the workbook matches the expected format.
     *
     * @param string $filepath
     * @return bool
     */
    public function validate_file(string $filepath): bool {
        try {
            $this->validate_extension($filepath);

            $spreadsheet = IOFactory::load($filepath);
            $sheet = $spreadsheet->getActiveSheet();
            $highestrow = $sheet->getHighestRow();
            $identifiercolumn = $this->resolve_identifier_column($sheet, $highestrow);

            if ($highestrow <= self::HEADER_ROWS) {
                throw new moodle_exception('error_format_insufficient_rows', 'local_gradefiller', '', self::HEADER_ROWS + 1);
            }

            if (!$this->has_identifier_data($sheet, $identifiercolumn, $highestrow)) {
                throw new moodle_exception('error_format_no_identifiers', 'local_gradefiller');
            }

            return true;
        } catch (moodle_exception $e) {
            throw $e;
        } catch (Exception $e) {
            throw new moodle_exception('error_format_invalid_generic', 'local_gradefiller');
        }
    }

    /**
     * Validate and return the supported workbook extension.
     *
     * @param string $filepath
     * @return string
     */
    private function validate_extension(string $filepath): string {
        $extension = strtolower(pathinfo($filepath, PATHINFO_EXTENSION));
        if (!in_array($extension, self::ALLOWED_EXTENSIONS, true)) {
            throw new moodle_exception('error_unsupported_extension', 'local_gradefiller', '', $extension);
        }

        return $extension;
    }

    /**
     * Resolve the identifier column used by the spreadsheet.
     *
     * Column A stays the preferred location, but some institutional exports put
     * the effective identifier in another early column while keeping the same
     * note column.
     *
     * @param Worksheet $sheet
     * @param int $highestrow
     * @return int
     */
    private function resolve_identifier_column(Worksheet $sheet, int $highestrow): int {
        if ($this->count_identifier_candidates($sheet, self::COLUMN_IDENTIFIER, $highestrow) > 0) {
            return self::COLUMN_IDENTIFIER;
        }

        $bestcolumn = self::COLUMN_IDENTIFIER;
        $bestscore = 0;
        foreach ([0, 1, 2, 3] as $candidate) {
            $score = $this->count_identifier_candidates($sheet, $candidate, $highestrow);
            if ($score > $bestscore) {
                $bestcolumn = $candidate;
                $bestscore = $score;
            }
        }

        return $bestcolumn;
    }

    /**
     * Count identifier-like values in the first data rows for one column.
     *
     * @param Worksheet $sheet
     * @param int $columnindex
     * @param int $highestrow
     * @return int
     */
    private function count_identifier_candidates(Worksheet $sheet, int $columnindex, int $highestrow): int {
        $score = 0;
        $endrow = min($highestrow, self::HEADER_ROWS + 25);

        for ($row = self::HEADER_ROWS + 1; $row <= $endrow; $row++) {
            $value = trim((string) $sheet->getCellByColumnAndRow($columnindex + 1, $row)->getFormattedValue());
            if ($value !== '') {
                $score++;
            }
        }

        return $score;
    }

    /**
     * Check whether the resolved identifier column actually contains data.
     *
     * @param Worksheet $sheet
     * @param int $identifiercolumn
     * @param int $highestrow
     * @return bool
     */
    private function has_identifier_data(Worksheet $sheet, int $identifiercolumn, int $highestrow): bool {
        for ($row = self::HEADER_ROWS + 1; $row <= min($highestrow, self::HEADER_ROWS + 10); $row++) {
            $value = $sheet->getCellByColumnAndRow($identifiercolumn + 1, $row)->getValue();
            if (!empty(trim((string) $value))) {
                return true;
            }
        }

        return false;
    }
}
