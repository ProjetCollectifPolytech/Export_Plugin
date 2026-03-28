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

use local_gradefiller\error\error_message_resolver;

/**
 * Tests for the error message resolver.
 *
 * @package    local_gradefiller
 */
final class error_message_resolver_test extends \advanced_testcase {

    public function test_to_user_message_returns_moodle_exception_message(): void {
        $exception = new \moodle_exception('missingparam', 'error', '', 'id');

        $this->assertSame($exception->getMessage(), error_message_resolver::to_user_message($exception));
    }

    public function test_to_user_message_falls_back_for_generic_exception(): void {
        $message = error_message_resolver::to_user_message(new \RuntimeException('boom'));

        $this->assertDebuggingCalled('Grade Filler unexpected error: boom', DEBUG_DEVELOPER);
        $this->assertSame(get_string('error_operation_failed', 'local_gradefiller'), $message);
    }
}
