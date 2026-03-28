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

use local_gradefiller\page\upload_page;
use local_gradefiller\spreadsheet\spreadsheet_format_interface;
use local_gradefiller\source\grade_source_interface;

/**
 * Tests for the upload page builder.
 *
 * @package    local_gradefiller
 */
final class upload_page_test extends \advanced_testcase {

    public function test_build_exposes_format_and_driver_metadata(): void {
        $this->resetAfterTest(true);
        $this->setAdminUser();

        $page = new upload_page();
        $format = new class implements spreadsheet_format_interface {
            public function get_name(): string {
                return 'University standard';
            }
            public function get_key(): string {
                return 'university_standard';
            }
            public function get_description(): string {
                return 'Template description';
            }
            public function get_supported_extensions(): array {
                return ['xlsx'];
            }
            public function get_upload_label(): string {
                return 'Upload workbook';
            }
            public function get_upload_help(): string {
                return 'Help';
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
        $driver = new class implements grade_source_interface {
            public function get_name(): string {
                return 'PaperGrade';
            }
            public function supports($cm): bool {
                return true;
            }
            public function fetch_grade_by_anonkey(int $cmid, string $anonkey): ?object {
                return null;
            }
            public function is_anonymous_identifier(string $identifier): bool {
                return true;
            }
        };

        $templatedata = $page->build(
            12,
            (object) ['name' => 'Quiz 1', 'modname' => 'offlinequiz'],
            [$format],
            true,
            $driver,
            'http://example.test'
        );

        $this->assertSame(12, $templatedata['cmid']);
        $this->assertSame('Quiz 1', $templatedata['activity_name']);
        $this->assertCount(1, $templatedata['formats']);
        $this->assertSame('university_standard', $templatedata['formats'][0]['key']);
        $this->assertTrue($templatedata['supports_anonymous']);
        $this->assertSame('PaperGrade', $templatedata['driver_name']);
        $this->assertTrue($templatedata['can_process']);
    }
}
