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

namespace local_gradefiller\privacy;

/**
 * Tests for the local_gradefiller privacy provider.
 *
 * @package    local_gradefiller
 */
final class provider_test extends \advanced_testcase {
    public function test_provider_implements_null_provider(): void {
        $interfaces = class_implements(provider::class);

        $this->assertArrayHasKey(\core_privacy\local\metadata\null_provider::class, $interfaces);
    }

    public function test_get_reason_returns_privacy_metadata_key(): void {
        $this->assertSame('privacy:metadata', provider::get_reason());
    }
}
