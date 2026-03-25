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

require_once(__DIR__ . '/gradefiller_testcase.php');
require_once(__DIR__ . '/../lib.php');

/**
 * Tests for lib.php navigation helpers.
 *
 * @package    local_gradefiller
 */
final class lib_test extends gradefiller_testcase {

    public function test_get_activity_access_data_returns_link_for_graded_activity(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $cm = $this->create_label_cm($course);
        $this->create_grade_item_for_cm($course, $cm);

        $accessdata = \local_gradefiller_get_activity_access_data($cm, \context_course::instance($course->id));

        $this->assertNotNull($accessdata);
        $this->assertTrue($accessdata['has_grade_item']);
        $this->assertFalse($accessdata['supports_anonymous']);
        $this->assertSame('gradefiller_fill', $accessdata['nodekey']);
        $this->assertSame(
            (new \moodle_url('/local/gradefiller/index.php', ['id' => $cm->id]))->out(false),
            $accessdata['url']->out(false)
        );
    }

    public function test_get_activity_access_data_returns_link_for_anonymous_driver_without_grade_item(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        [, $cm] = $this->create_offlinequiz_activity($course);

        $accessdata = \local_gradefiller_get_activity_access_data($cm, \context_course::instance($course->id));

        $this->assertNotNull($accessdata);
        $this->assertFalse($accessdata['has_grade_item']);
        $this->assertTrue($accessdata['supports_anonymous']);
        $this->assertSame(get_string('fill_grades', 'local_gradefiller'), $accessdata['label']);
    }

    public function test_get_activity_access_data_returns_null_for_unsupported_ungraded_activity(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $cm = $this->create_label_cm($course);

        $accessdata = \local_gradefiller_get_activity_access_data($cm, \context_course::instance($course->id));

        $this->assertNull($accessdata);
    }

    public function test_get_grade_export_bridge_options_returns_spreadsheet_labels_and_urls(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();

        $options = \local_gradefiller_get_grade_export_bridge_options($course->id);

        $this->assertNotEmpty($options);
        $this->assertSame('university_standard', $options[0]['key']);
        $this->assertSame(get_string('format_university_standard_name', 'local_gradefiller'), $options[0]['label']);
        $this->assertStringContainsString('spreadsheetformat=university_standard', $options[0]['url']);
    }
}
