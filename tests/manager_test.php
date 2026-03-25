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

/**
 * Tests for the manager class.
 *
 * @package    local_gradefiller
 */
final class manager_test extends gradefiller_testcase {

    public function test_get_format_returns_the_university_standard_handler(): void {
        $manager = new manager();
        $format = $manager->get_format('university_standard');

        $this->assertNotNull($format);
        $this->assertSame('university_standard', $format->get_key());
    }

    public function test_get_driver_for_cm_returns_null_for_unsupported_activity(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $cm = $this->create_label_cm($course);

        $this->assertNull((new manager())->get_driver_for_cm($cm));
    }

    public function test_get_supported_grade_sources_includes_anonymous_for_offlinequiz(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        [, $cm] = $this->create_offlinequiz_activity($course);

        $sources = (new manager())->get_supported_grade_sources($cm);

        $this->assertSame(
            [manager::GRADE_SOURCE_STANDARD, manager::GRADE_SOURCE_ANONYMOUS],
            $sources
        );
    }

    public function test_is_supported_grade_source_validates_available_sources(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $cm = $this->create_label_cm($course);

        $manager = new manager();
        $this->assertTrue($manager->is_supported_grade_source($cm, manager::GRADE_SOURCE_STANDARD));
        $this->assertFalse($manager->is_supported_grade_source($cm, manager::GRADE_SOURCE_ANONYMOUS));
    }
}
