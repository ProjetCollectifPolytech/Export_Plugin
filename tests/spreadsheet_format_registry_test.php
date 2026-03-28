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

use local_gradefiller\spreadsheet\format_university_standard;

/**
 * Tests for the spreadsheet format registry.
 *
 * @package    local_gradefiller
 */
final class spreadsheet_format_registry_test extends \advanced_testcase {
    public function test_get_available_formats_returns_cached_explicit_formats(): void {
        $registry = new spreadsheet_format_registry();

        $formats = $registry->get_available_formats();

        $this->assertCount(1, $formats);
        $this->assertInstanceOf(format_university_standard::class, $formats[0]);
        $this->assertSame($formats, $registry->get_available_formats());
    }

    public function test_get_format_returns_registered_format_by_key(): void {
        $registry = new spreadsheet_format_registry();

        $this->assertInstanceOf(format_university_standard::class, $registry->get_format('university_standard'));
        $this->assertNull($registry->get_format('missing_format'));
    }
}
