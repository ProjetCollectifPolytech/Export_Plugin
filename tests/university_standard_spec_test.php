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

namespace local_gradefiller;

use local_gradefiller\spreadsheet\university_standard_spec;

/**
 * Tests for the university standard workbook spec.
 *
 * @package    local_gradefiller
 */
final class university_standard_spec_test extends \advanced_testcase {
    public function test_describe_contract_returns_explicit_workbook_invariants(): void {
        $spec = new university_standard_spec();

        $this->assertSame(['xlsx', 'xlsm'], $spec->get_supported_extensions());
        $this->assertSame(17, $spec->get_header_rows());
        $this->assertSame(0, $spec->get_primary_identifier_column());
        $this->assertSame([0, 1, 2, 3], $spec->get_identifier_candidate_columns());
        $this->assertSame('xl/worksheets/sheet1.xml', $spec->get_sheet_xml_path());
        $this->assertSame('E', $spec->get_grade_column_letter());
        $this->assertSame(
            [
                'worksheet_index' => 1,
                'worksheet_xml_path' => 'xl/worksheets/sheet1.xml',
                'header_rows' => 17,
                'preferred_identifier_column' => 'A',
                'identifier_candidate_columns' => ['A', 'B', 'C', 'D'],
                'grade_column' => 'E',
                'supported_extensions' => ['xlsx', 'xlsm'],
            ],
            $spec->describe_contract()
        );
    }
}
