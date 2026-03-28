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

use local_gradefiller\integration\activity_access_resolver;

/**
 * Tests for the activity access resolver.
 *
 * @package    local_gradefiller
 */
final class activity_access_resolver_test extends gradefiller_testcase {
    public function test_resolve_returns_grade_item_metadata_for_supported_activity(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $cm = $this->create_label_cm($course);
        $this->create_grade_item_for_cm($course, $cm);

        $accessdata = (new activity_access_resolver())->resolve($cm, \context_course::instance($course->id));

        $this->assertNotNull($accessdata);
        $this->assertTrue($accessdata['has_grade_item']);
        $this->assertFalse($accessdata['supports_anonymous']);
        $this->assertSame('gradefiller_fill', $accessdata['nodekey']);
    }

    public function test_resolve_returns_anonymous_driver_metadata_when_grade_item_is_missing(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        [, $cm] = $this->create_offlinequiz_activity($course);

        $accessdata = (new activity_access_resolver())->resolve($cm, \context_course::instance($course->id));

        $this->assertNotNull($accessdata);
        $this->assertFalse($accessdata['has_grade_item']);
        $this->assertTrue($accessdata['supports_anonymous']);
        $this->assertSame('offlinequiz', $cm->modname);
    }
}
