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

/**
 * User-facing error resolver for Grade Filler.
 *
 * @package    local_gradefiller
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_gradefiller\error;


use moodle_exception;
use Throwable;

/**
 * Converts unexpected exceptions into safe user messages.
 *
 * @package    local_gradefiller
 */
class error_message_resolver {
    /**
     * Resolve the message that should be shown in the UI.
     *
     * @param \Throwable $exception
     * @param string $fallbackstringid
     * @return string
     */
    public static function to_user_message(Throwable $exception, string $fallbackstringid = 'error_operation_failed'): string {
        if ($exception instanceof moodle_exception) {
            return $exception->getMessage();
        }

        debugging('Grade Filler unexpected error: ' . $exception->getMessage(), DEBUG_DEVELOPER);
        return get_string($fallbackstringid, 'local_gradefiller');
    }
}
