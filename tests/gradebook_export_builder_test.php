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

require_once(__DIR__ . '/gradefiller_testcase.php');

use local_gradefiller\export\gradebook_export_builder;
use local_gradefiller\gradefiller_testcase;

/**
 * Tests for the Grade Filler classic export builder.
 *
 * @package    local_gradefiller
 */
final class gradebook_export_builder_test extends gradefiller_testcase {

    public function test_build_export_data_returns_one_row_per_enrolled_user(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');

        $label = $this->getDataGenerator()->create_module('label', ['course' => $course->id, 'name' => 'Bridge label']);
        $cm = get_coursemodule_from_instance('label', $label->id, $course->id, false, MUST_EXIST);
        $gradeitem = $this->create_grade_item_for_cm($course, $cm, 20.0);

        $gradegrade = new \grade_grade([
            'itemid' => $gradeitem->id,
            'userid' => $student->id,
            'finalgrade' => 14.5,
            'rawgrade' => 14.5,
            'rawgrademax' => 20.0,
        ]);
        $gradegrade->insert();

        $formdata = (object) [
            'itemids' => [$gradeitem->id => 1],
            'export_feedback' => 0,
            'export_onlyactive' => 1,
            'display' => ['real' => GRADE_DISPLAY_TYPE_REAL],
            'decimals' => 2,
        ];

        $exportdata = (new gradebook_export_builder($course, 0, $formdata))->build_export_data();

        $this->assertNotEmpty($exportdata->headers);
        $this->assertCount(1, $exportdata->rows);
        $this->assertContains('14.50', array_map('strval', $exportdata->rows[0]));
    }
}
