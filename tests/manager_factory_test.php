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

/**
 * Tests for the manager factory.
 *
 * @package    local_gradefiller
 */
final class manager_factory_test extends \advanced_testcase {
    public function test_create_default_returns_wired_manager(): void {
        $manager = manager_factory::create_default();

        $this->assertInstanceOf(manager::class, $manager);
        $this->assertSame('university_standard', $manager->get_format('university_standard')->get_key());
        $this->assertCount(2, $manager->get_available_drivers());
    }
}
