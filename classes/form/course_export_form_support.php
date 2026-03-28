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

namespace local_gradefiller\form;


use context_course;
use grade_seq;

/**
 * Pure helpers used by the Grade Filler export form.
 *
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class course_export_form_support {
    /**
     * Return the visible grade items that can be exported.
     *
     * @param \stdClass $course
     * @param \stdClass $cfg
     * @return array<int, array<string, mixed>>
     */
    public function get_visible_grade_items(\stdClass $course, \stdClass $cfg): array {
        $switch = grade_get_setting($course->id, 'aggregationposition', $cfg->grade_aggregationposition);
        $sequence = new grade_seq($course->id, $switch);
        $canviewhidden = has_capability('moodle/grade:viewhidden', context_course::instance($course->id));

        $gradeitems = [];
        foreach ($sequence->items ?? [] as $gradeitem) {
            if ($gradeitem->is_hidden() && !$canviewhidden) {
                continue;
            }

            $gradeitems[] = [
                'id' => $gradeitem->id,
                'name' => $gradeitem->get_name(),
            ];
        }

        return $gradeitems;
    }

    /**
     * Build human-readable format options.
     *
     * @param array $formats
     * @return array<string, string>
     */
    public function build_format_options(array $formats): array {
        $formatoptions = [];
        foreach ($formats as $format) {
            $formatoptions[$format->get_key()] = $format->get_name() . ' - ' . $format->get_description();
        }

        return $formatoptions;
    }

    /**
     * Build the accepted extension list for the selected spreadsheet scope.
     *
     * @param array $formats
     * @param object|null $selectedspreadsheet
     * @return string[]
     */
    public function build_accepted_extensions(array $formats, $selectedspreadsheet): array {
        $acceptedextensions = [];
        if ($selectedspreadsheet !== null) {
            foreach ($selectedspreadsheet->get_supported_extensions() as $extension) {
                $acceptedextensions[] = '.' . ltrim((string)$extension, '.');
            }
        } else {
            foreach ($formats as $format) {
                foreach ($format->get_supported_extensions() as $extension) {
                    $acceptedextensions[] = '.' . ltrim((string)$extension, '.');
                }
            }
        }

        $acceptedextensions = array_values(array_unique($acceptedextensions));
        return !empty($acceptedextensions) ? $acceptedextensions : ['.xlsx'];
    }

    /**
     * Force at least one display type like Moodle core does.
     *
     * @param \stdClass|null $data
     * @param int $displaytype
     * @return \stdClass|null
     */
    public function normalise_display_selection(?\stdClass $data, int $displaytype): ?\stdClass {
        if ($data === null || !isset($data->display) || !is_array($data->display) || count(array_filter($data->display)) > 0) {
            return $data;
        }

        if ($displaytype == GRADE_DISPLAY_TYPE_LETTER) {
            $data->display['letter'] = GRADE_DISPLAY_TYPE_LETTER;
        } else if ($displaytype == GRADE_DISPLAY_TYPE_PERCENTAGE) {
            $data->display['percentage'] = GRADE_DISPLAY_TYPE_PERCENTAGE;
        } else {
            $data->display['real'] = GRADE_DISPLAY_TYPE_REAL;
        }

        return $data;
    }
}
