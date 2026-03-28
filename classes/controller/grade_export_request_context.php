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

namespace local_gradefiller\controller;

defined('MOODLE_INTERNAL') || die();

use local_gradefiller\form\course_export_form;

/**
 * Immutable request context for the grade export bridge page.
 *
 * @package    local_gradefiller
 */
final class grade_export_request_context {
    /**
     * Constructor.
     *
     * @param \stdClass $course
     * @param \context_course $context
     * @param object|null $selectedspreadsheet
     * @param string $selectedspreadsheetkey
     * @param course_export_form $form
     * @param int $currentgroup
     * @param bool $requiresgroupselection
     */
    public function __construct(
        public readonly \stdClass $course,
        public readonly \context_course $context,
        public readonly mixed $selectedspreadsheet,
        public readonly string $selectedspreadsheetkey,
        public readonly course_export_form $form,
        public readonly int $currentgroup,
        public readonly bool $requiresgroupselection
    ) {
    }
}
