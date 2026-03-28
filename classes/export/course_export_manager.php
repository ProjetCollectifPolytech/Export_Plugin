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
 * Orchestrator for Grade Filler's course grade export mode.
 *
 * @package    local_gradefiller
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_gradefiller\export;
use moodle_exception;
use stdClass;
/**
 * Handles gradebook export workflows backed by uploaded templates.
 *
 * @package    local_gradefiller
 */
class course_export_manager {
    /** @var course_export_format_registry */
    private course_export_format_registry $registry;
    /**
     * Constructor.
     *
     * @param course_export_format_registry|null $registry
     */
    public function __construct(?course_export_format_registry $registry = null) {

        $this->registry = $registry ?? new course_export_format_registry();
    }
    /**
     * Get the list of available Grade Filler course export formats.
     *
     * @return array
     */
    public function get_available_formats(): array {

        return $this->registry->get_available_formats();
    }
    /**
     * Resolve a course export format by its stable key.
     *
     * @param string $formatkey
     * @return course_export_format_interface|null
     */
    public function get_format(string $formatkey): ?course_export_format_interface {

        return $this->registry->get_format($formatkey);
    }
    /**
     * Process a gradebook export into a teacher-provided workbook template.
     *
     * @param string $filepath Absolute path to the uploaded template file
     * @param string $formatkey Selected Grade Filler export format
     * @param \stdClass $course Course record
     * @param int $groupid Current group filter
     * @param \stdClass $formdata Validated form data
     * @param string $originalfilename Original uploaded file name
     * @return array
     */
    public function process_export(
        string $filepath,
        string $formatkey,
        stdClass $course,
        int $groupid,
        stdClass $formdata,
        string $originalfilename
    ): array {

        $format = $this->get_format($formatkey);
        if ($format === null) {
            throw new moodle_exception('error_export_format_not_found', 'local_gradefiller', '', $formatkey);
        }
        $builder = new gradebook_export_builder($course, $groupid, $formdata);
        $exportdata = $builder->build_export_data();
        $outputfile = $format->export_to_template($filepath, $exportdata);
        $extension = strtolower(pathinfo($outputfile, PATHINFO_EXTENSION));
        $filename = clean_filename(pathinfo($originalfilename, PATHINFO_FILENAME) . '_gradefiller_export.' . $extension);

        return [
            'filepath' => $outputfile,
            'downloadname' => $filename,
            'stats' => [
                'columns' => count($exportdata->headers),
                'rows' => count($exportdata->rows),
            ],
        ];
    }
}
