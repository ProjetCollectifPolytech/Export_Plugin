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

use local_gradefiller\export\course_export_manager;
use local_gradefiller\export\format\workbook_template_format;

/**
 * Tests for the Grade Filler course export manager.
 *
 * @package    local_gradefiller
 */
final class course_export_manager_test extends \advanced_testcase {
    public function test_get_available_formats_returns_workbook_template_format(): void {
        $formats = (new course_export_manager())->get_available_formats();

        $this->assertCount(1, $formats);
        $this->assertInstanceOf(workbook_template_format::class, reset($formats));
    }

    public function test_get_format_returns_null_for_unknown_key(): void {
        $this->assertNull((new course_export_manager())->get_format('missing_format'));
    }
}
