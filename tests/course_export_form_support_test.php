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

require_once(__DIR__ . '/gradefiller_testcase.php');

use local_gradefiller\form\course_export_form_support;
use local_gradefiller\spreadsheet\spreadsheet_format_interface;

/**
 * Tests for course export form helper logic.
 *
 * @package    local_gradefiller
 */
final class course_export_form_support_test extends gradefiller_testcase {

    public function test_build_accepted_extensions_deduplicates_extensions(): void {
        $support = new course_export_form_support();
        $formats = [
            $this->create_format(['xlsx', '.csv']),
            $this->create_format(['csv', 'ods']),
        ];

        $extensions = $support->build_accepted_extensions($formats, null);

        $this->assertSame(['.xlsx', '.csv', '.ods'], $extensions);
    }

    public function test_normalise_display_selection_restores_default_display_type(): void {
        $support = new course_export_form_support();
        $data = (object)['display' => ['real' => 0, 'percentage' => 0, 'letter' => 0]];

        $normalised = $support->normalise_display_selection($data, GRADE_DISPLAY_TYPE_PERCENTAGE);

        $this->assertSame(GRADE_DISPLAY_TYPE_PERCENTAGE, $normalised->display['percentage']);
    }

    public function test_get_visible_grade_items_includes_visible_grade_items(): void {
        global $CFG;

        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $cm = $this->create_label_cm($course);
        $gradeitem = $this->create_grade_item_for_cm($course, $cm);

        $items = (new course_export_form_support())->get_visible_grade_items($course, $CFG);

        $itemids = array_column($items, 'id');

        $this->assertContains($gradeitem->id, $itemids);
    }

    /**
     * Create a lightweight spreadsheet format stub.
     *
     * @param array $extensions
     * @return spreadsheet_format_interface
     */
    private function create_format(array $extensions): spreadsheet_format_interface {
        return new class($extensions) implements spreadsheet_format_interface {
            /** @var array */
            private array $extensions;

            public function __construct(array $extensions) {
                $this->extensions = $extensions;
            }

            public function get_name(): string {
                return 'Test format';
            }

            public function get_key(): string {
                return 'test_format';
            }

            public function get_description(): string {
                return 'Description';
            }

            public function get_supported_extensions(): array {
                return $this->extensions;
            }

            public function get_upload_label(): string {
                return 'Upload';
            }

            public function get_upload_help(): string {
                return '';
            }

            public function get_identifier_mode(): string {
                return self::IDENTIFIER_MODE_AUTO;
            }

            public function read_identifiers(string $filepath): array {
                return [];
            }

            public function write_grades(string $filepath, array $grades): string {
                return $filepath;
            }

            public function validate_file(string $filepath): bool {
                return true;
            }
        };
    }
}
