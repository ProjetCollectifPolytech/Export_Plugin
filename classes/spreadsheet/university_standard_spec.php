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
 * Explicit specification for the university-standard workbook contract.
 *
 * @package    local_gradefiller
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_gradefiller\spreadsheet;

defined('MOODLE_INTERNAL') || die();

/**
 * Documents the structural invariants expected by the university template.
 *
 * @package    local_gradefiller
 */
class university_standard_spec {
    /** @var int Number of header rows before real data starts */
    public const HEADER_ROWS = 17;

    /** @var int Preferred identifier column index (zero-based) */
    public const PRIMARY_IDENTIFIER_COLUMN = 0;

    /** @var int[] Candidate identifier columns (zero-based) */
    private const IDENTIFIER_CANDIDATE_COLUMNS = [0, 1, 2, 3];

    /** @var string[] Supported upload extensions */
    private const SUPPORTED_EXTENSIONS = ['xlsx', 'xlsm'];

    /** @var string Worksheet XML path in OOXML workbooks */
    private const SHEET_XML_PATH = 'xl/worksheets/sheet1.xml';

    /** @var string Grade column letter in the template */
    private const GRADE_COLUMN_LETTER = 'E';

    /**
     * Return the supported workbook extensions.
     *
     * @return string[]
     */
    public function get_supported_extensions(): array {
        return self::SUPPORTED_EXTENSIONS;
    }

    /**
     * Return the number of header rows.
     *
     * @return int
     */
    public function get_header_rows(): int {
        return self::HEADER_ROWS;
    }

    /**
     * Return the preferred identifier column index.
     *
     * @return int
     */
    public function get_primary_identifier_column(): int {
        return self::PRIMARY_IDENTIFIER_COLUMN;
    }

    /**
     * Return all candidate identifier columns.
     *
     * @return int[]
     */
    public function get_identifier_candidate_columns(): array {
        return self::IDENTIFIER_CANDIDATE_COLUMNS;
    }

    /**
     * Return the worksheet XML path to patch when writing grades.
     *
     * @return string
     */
    public function get_sheet_xml_path(): string {
        return self::SHEET_XML_PATH;
    }

    /**
     * Return the grade column letter.
     *
     * @return string
     */
    public function get_grade_column_letter(): string {
        return self::GRADE_COLUMN_LETTER;
    }

    /**
     * Return a human-readable description of the contract for tests and tooling.
     *
     * @return array<string, mixed>
     */
    public function describe_contract(): array {
        return [
            'worksheet_index' => 1,
            'worksheet_xml_path' => $this->get_sheet_xml_path(),
            'header_rows' => $this->get_header_rows(),
            'preferred_identifier_column' => 'A',
            'identifier_candidate_columns' => ['A', 'B', 'C', 'D'],
            'grade_column' => $this->get_grade_column_letter(),
            'supported_extensions' => $this->get_supported_extensions(),
        ];
    }
}
