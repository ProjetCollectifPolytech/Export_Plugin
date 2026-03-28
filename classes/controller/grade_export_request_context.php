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

use local_gradefiller\form\course_export_form;

/**
 * Immutable request context for the grade export bridge page.
 *
 * @package    local_gradefiller
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class grade_export_request_context {
    /** @var \stdClass */
    public readonly \stdClass $course;

    /** @var \context_course */
    public readonly \context_course $context;

    /** @var mixed */
    public readonly mixed $selectedspreadsheet;

    /** @var string */
    public readonly string $selectedspreadsheetkey;

    /** @var course_export_form */
    public readonly course_export_form $form;

    /** @var int */
    public readonly int $currentgroup;

    /** @var bool */
    public readonly bool $requiresgroupselection;

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
        \stdClass $course,
        \context_course $context,
        mixed $selectedspreadsheet,
        string $selectedspreadsheetkey,
        course_export_form $form,
        int $currentgroup,
        bool $requiresgroupselection
    ) {
        $this->course = $course;
        $this->context = $context;
        $this->selectedspreadsheet = $selectedspreadsheet;
        $this->selectedspreadsheetkey = $selectedspreadsheetkey;
        $this->form = $form;
        $this->currentgroup = $currentgroup;
        $this->requiresgroupselection = $requiresgroupselection;
    }
}
