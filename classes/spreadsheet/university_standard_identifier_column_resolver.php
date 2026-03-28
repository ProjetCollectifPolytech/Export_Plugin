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
 * Identifier column resolution for the university spreadsheet format.
 *
 * @package    local_gradefiller
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_gradefiller\spreadsheet;


use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Resolves which column contains the effective identifiers in the workbook.
 *
 * @package    local_gradefiller
 */
class university_standard_identifier_column_resolver {
    /** @var university_standard_spec */
    private university_standard_spec $spec;

    /**
     * Constructor.
     *
     * @param university_standard_spec|null $spec
     */
    public function __construct(?university_standard_spec $spec = null) {
        $this->spec = $spec ?? new university_standard_spec();
    }

    /**
     * Resolve the identifier column used by the spreadsheet.
     *
     * Column A stays the preferred location, but some institutional exports put
     * the effective identifier in another early column while keeping the same
     * grade column.
     *
     * @param Worksheet $sheet
     * @param int $highestrow
     * @return int
     */
    public function resolve_identifier_column(Worksheet $sheet, int $highestrow): int {
        $primarycolumn = $this->spec->get_primary_identifier_column();
        if ($this->count_identifier_candidates($sheet, $primarycolumn, $highestrow) > 0) {
            return $primarycolumn;
        }

        $bestcolumn = $primarycolumn;
        $bestscore = 0;
        foreach ($this->spec->get_identifier_candidate_columns() as $candidate) {
            $score = $this->count_identifier_candidates($sheet, $candidate, $highestrow);
            if ($score > $bestscore) {
                $bestcolumn = $candidate;
                $bestscore = $score;
            }
        }

        return $bestcolumn;
    }

    /**
     * Check whether the resolved identifier column actually contains data.
     *
     * @param Worksheet $sheet
     * @param int $identifiercolumn
     * @param int $highestrow
     * @return bool
     */
    public function has_identifier_data(Worksheet $sheet, int $identifiercolumn, int $highestrow): bool {
        $endrow = min($highestrow, $this->spec->get_header_rows() + 10);
        for ($row = $this->spec->get_header_rows() + 1; $row <= $endrow; $row++) {
            $value = $sheet->getCellByColumnAndRow($identifiercolumn + 1, $row)->getValue();
            if (!empty(trim((string)$value))) {
                return true;
            }
        }

        return false;
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
        $endrow = min($highestrow, $this->spec->get_header_rows() + 25);

        for ($row = $this->spec->get_header_rows() + 1; $row <= $endrow; $row++) {
            $value = trim((string)$sheet->getCellByColumnAndRow($columnindex + 1, $row)->getFormattedValue());
            if ($value !== '') {
                $score++;
            }
        }

        return $score;
    }
}
