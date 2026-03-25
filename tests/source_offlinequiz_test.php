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

use local_gradefiller\source\grade_source_offlinequiz;

/**
 * Tests for the Offline Quiz grade source.
 *
 * @package    local_gradefiller
 */
final class source_offlinequiz_test extends gradefiller_testcase {

    public function test_supports_only_offlinequiz_modules(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        [, $offlinequizcm] = $this->create_offlinequiz_activity($course);
        $labelcm = $this->create_label_cm($course);

        $driver = new grade_source_offlinequiz();

        $this->assertTrue($driver->supports($offlinequizcm));
        $this->assertFalse($driver->supports($labelcm));
    }

    public function test_fetch_grade_by_anonkey_returns_latest_matching_grade(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        [$offlinequiz, $cm] = $this->create_offlinequiz_activity($course);
        $groupid = $this->create_offlinequiz_group($offlinequiz->id, 20.0);

        $this->insert_offlinequiz_result($offlinequiz->id, $groupid, 'A-001', 12.0, 0, 100);
        $this->insert_offlinequiz_result($offlinequiz->id, $groupid, 'A-001', 16.5, 0, 200);
        $this->insert_offlinequiz_result($offlinequiz->id, $groupid, 'A-404', 9.0, 0, 300);

        $result = (new grade_source_offlinequiz())->fetch_grade_by_anonkey($cm->id, 'A-001');

        $this->assertNotNull($result);
        $this->assertSame(16.5, (float)$result->grade);
        $this->assertSame(20.0, (float)$result->maxgrade);
    }

    public function test_is_anonymous_identifier_accepts_non_empty_values(): void {
        $driver = new grade_source_offlinequiz();

        $this->assertTrue($driver->is_anonymous_identifier('ABC123'));
        $this->assertFalse($driver->is_anonymous_identifier('   '));
    }
}
