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
 * Spreadsheet-driven multi-activity export manager.
 *
 * @package    local_gradefiller
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_gradefiller\export;

defined('MOODLE_INTERNAL') || die();

use grade_item;
use local_gradefiller\manager;
use local_gradefiller\spreadsheet\multi_activity_grade_aggregation_interface;
use local_gradefiller\spreadsheet\spreadsheet_format_interface;
use stdClass;
use Throwable;

/**
 * Fills a teacher spreadsheet directly from selected course grade items.
 *
 * @package    local_gradefiller
 */
class course_spreadsheet_export_manager {

    /**
     * Build and write a multi-activity spreadsheet export.
     *
     * @param string $filepath Uploaded spreadsheet path
     * @param spreadsheet_format_interface $spreadsheetformat Selected spreadsheet strategy
     * @param \stdClass $course Course record
     * @param int $groupid Current group filter
     * @param \stdClass $formdata Submitted form data
     * @param string $originalfilename Original uploaded file name
     * @return array
     */
    public function process_export(
        string $filepath,
        spreadsheet_format_interface $spreadsheetformat,
        stdClass $course,
        int $groupid,
        stdClass $formdata,
        string $originalfilename
    ): array {
        $spreadsheetformat->validate_file($filepath);
        $identifiers = $spreadsheetformat->read_identifiers($filepath);
        $gradeitems = $this->get_selected_grade_items($course, $formdata);
        $resolver = new manager();

        $stats = [
            'total' => count($identifiers),
            'matched' => 0,
            'unmatched' => 0,
            'errors' => 0,
        ];

        $grades = [];
        foreach ($identifiers as $identifierentry) {
            try {
                $collectedgrades = [];
                foreach ($gradeitems as $gradeitem) {
                    $grade = $resolver->fetch_grade_for_item(
                        $identifierentry->identifier,
                        $gradeitem,
                        $course->id,
                        $spreadsheetformat->get_identifier_mode(),
                        !empty($formdata->export_onlyactive),
                        $groupid
                    );

                    if ($grade !== null && $grade->grade !== null) {
                        $collectedgrades[] = (object) [
                            'itemid' => $gradeitem->id,
                            'itemname' => $gradeitem->get_name(),
                            'grade' => (float) $grade->grade,
                            'maxgrade' => isset($grade->maxgrade) ? (float) $grade->maxgrade : (float) $gradeitem->grademax,
                            'source' => $grade->source ?? null,
                            'gradeitem' => $gradeitem,
                        ];
                    }
                }

                $aggregated = $this->aggregate_identifier_grades(
                    $spreadsheetformat,
                    $identifierentry,
                    $collectedgrades,
                    $course,
                    $formdata
                );

                if ($aggregated !== null) {
                    $grades[] = (object) [
                        'identifier' => $identifierentry->identifier,
                        'grade' => $aggregated,
                        'row_number' => $identifierentry->row_number,
                    ];
                    $stats['matched']++;
                } else {
                    $stats['unmatched']++;
                }
            } catch (Throwable $e) {
                $stats['errors']++;
                debugging(
                    'Grade Filler skipped identifier "' . $identifierentry->identifier . '" during aggregation: ' . $e->getMessage(),
                    DEBUG_DEVELOPER
                );
            }
        }

        $outputfile = $spreadsheetformat->write_grades($filepath, $grades);

        return [
            'filepath' => $outputfile,
            'downloadname' => $this->build_download_filename($originalfilename, $outputfile),
            'stats' => $stats,
        ];
    }

    /**
     * Resolve checked grade items from the submitted Moodle export form.
     *
     * @param \stdClass $course Course record
     * @param \stdClass $formdata Submitted form data
     * @return grade_item[]
     */
    private function get_selected_grade_items(stdClass $course, stdClass $formdata): array {
        $selecteditemids = array_keys(array_filter((array) ($formdata->itemids ?? [])));
        $gradeitems = [];

        foreach ($selecteditemids as $itemid) {
            $gradeitem = grade_item::fetch(['id' => (int) $itemid, 'courseid' => $course->id]);
            if ($gradeitem !== false && $gradeitem !== null) {
                $gradeitems[] = $gradeitem;
            }
        }

        return $gradeitems;
    }

    /**
     * Aggregate the grades collected for one spreadsheet identifier.
     *
     * @param spreadsheet_format_interface $spreadsheetformat Spreadsheet strategy
     * @param object $identifierentry Identifier metadata read from the file
     * @param array $grades Grade entries collected from selected activities
     * @param \stdClass $course Course record
     * @param \stdClass $formdata Submitted form data
     * @return float|null
     */
    private function aggregate_identifier_grades(
        spreadsheet_format_interface $spreadsheetformat,
        object $identifierentry,
        array $grades,
        stdClass $course,
        stdClass $formdata
    ): ?float {
        if (empty($grades)) {
            return null;
        }

        $context = (object) [
            'identifier' => $identifierentry->identifier,
            'row_number' => $identifierentry->row_number,
            'course' => $course,
            'formdata' => $formdata,
        ];

        if ($spreadsheetformat instanceof multi_activity_grade_aggregation_interface) {
            return $spreadsheetformat->aggregate_multi_activity_grades($grades, $context);
        }

        return $this->aggregate_average_grade($grades, (int) ($formdata->decimals ?? 2));
    }

    /**
     * Default multi-activity aggregation strategy: arithmetic mean.
     *
     * @param array $grades Grade entry objects
     * @param int $decimals Number of decimal places requested by the teacher
     * @return float|null
     */
    private function aggregate_average_grade(array $grades, int $decimals): ?float {
        $values = [];
        foreach ($grades as $grade) {
            if (isset($grade->grade) && is_numeric($grade->grade)) {
                $values[] = (float) $grade->grade;
            }
        }

        if (empty($values)) {
            return null;
        }

        return round(array_sum($values) / count($values), max(0, $decimals));
    }

    /**
     * Build the download name matching the actual generated file extension.
     *
     * @param string $originalfilename Original uploaded file name
     * @param string $outputfile Generated output path
     * @return string
     */
    private function build_download_filename(string $originalfilename, string $outputfile): string {
        $extension = strtolower(pathinfo($outputfile, PATHINFO_EXTENSION));
        return clean_filename(pathinfo($originalfilename, PATHINFO_FILENAME) . '_gradefiller_export.' . $extension);
    }
}
