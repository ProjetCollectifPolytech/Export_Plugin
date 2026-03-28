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

use local_gradefiller\integration\grade_export_bridge_config_builder;

/**
 * Tests for the grade export bridge config builder.
 *
 * @package    local_gradefiller
 */
final class grade_export_bridge_config_builder_test extends \advanced_testcase {
    public function test_get_grade_export_url_preserves_selected_format(): void {
        $url = (new grade_export_bridge_config_builder())->get_grade_export_url(42, 'university_standard');

        $this->assertStringEndsWith('/local/gradefiller/gradeexport.php', $url->get_path());
        $this->assertSame('42', $url->get_param('id'));
        $this->assertSame('university_standard', $url->get_param('spreadsheetformat'));
    }

    public function test_get_bridge_config_exposes_options_and_current_url(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $context = \context_course::instance($course->id);

        $config = (new grade_export_bridge_config_builder())->get_bridge_config($context, 'http://example.test/current');

        $this->assertNotEmpty($config['options']);
        $this->assertSame('http://example.test/current', $config['currenturl']);
        $this->assertArrayHasKey('key', $config['options'][0]);
        $this->assertArrayHasKey('url', $config['options'][0]);
    }
}
