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

use local_gradefiller\export\course_spreadsheet_export_manager;
use local_gradefiller\spreadsheet\multi_activity_grade_aggregation_interface;
use local_gradefiller\spreadsheet\spreadsheet_format_interface;

/**
 * Tests for the spreadsheet-driven course export manager.
 *
 * @package    local_gradefiller
 */
final class course_spreadsheet_export_manager_test extends gradefiller_testcase {

    public function test_process_export_averages_selected_grade_items_by_default(): void {
        [$course, $studenta, $studentb, $itemone, $itemtwo] = $this->create_standard_gradebook_fixture();

        $format = $this->create_fake_spreadsheet_format([
            ['identifier' => 'S-001', 'row_number' => 18],
            ['identifier' => 'S-002', 'row_number' => 19],
        ]);

        $result = (new course_spreadsheet_export_manager())->process_export(
            $this->create_temp_template('xlsx'),
            $format,
            $course,
            0,
            (object) [
                'itemids' => [$itemone->id => 1, $itemtwo->id => 1],
                'export_onlyactive' => 1,
                'decimals' => 2,
            ],
            'apogee_template.xlsx'
        );

        $this->assertSame('apogee_template_gradefiller_export.xlsx', $result['downloadname']);
        $this->assertSame(2, $result['stats']['matched']);
        $this->assertSame(0, $result['stats']['unmatched']);
        $this->assertSame(0, $result['stats']['errors']);

        $writtengrades = $format->get_written_grades();
        $this->assertCount(2, $writtengrades);
        $this->assertSame(12.0, $this->find_written_grade($writtengrades, 'S-001')->grade);
        $this->assertSame(7.0, $this->find_written_grade($writtengrades, 'S-002')->grade);
        $this->assertSame(18, $this->find_written_grade($writtengrades, 'S-001')->row_number);
        $this->assertSame($result['filepath'], $format->get_last_outputfile());
    }

    public function test_process_export_lets_spreadsheet_override_grade_aggregation(): void {
        [$course, $studenta, $studentb, $itemone, $itemtwo] = $this->create_standard_gradebook_fixture();

        $format = $this->create_fake_spreadsheet_format(
            [
                ['identifier' => 'S-001', 'row_number' => 18],
                ['identifier' => 'S-002', 'row_number' => 19],
            ],
            true
        );

        (new course_spreadsheet_export_manager())->process_export(
            $this->create_temp_template('xlsm'),
            $format,
            $course,
            0,
            (object) [
                'itemids' => [$itemone->id => 1, $itemtwo->id => 1],
                'export_onlyactive' => 1,
                'decimals' => 2,
            ],
            'apogee_template.xlsm'
        );

        $writtengrades = $format->get_written_grades();
        $this->assertSame(14.0, $this->find_written_grade($writtengrades, 'S-001')->grade);
        $this->assertSame(8.0, $this->find_written_grade($writtengrades, 'S-002')->grade);
        $this->assertSame(['S-001', 'S-002'], $format->get_aggregated_identifiers());
        $this->assertStringEndsWith('.xlsm', $format->get_last_outputfile());
    }

    /**
     * Create two enrolled students and two graded activities.
     *
     * @return array
     */
    private function create_standard_gradebook_fixture(): array {
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();

        $studenta = $this->getDataGenerator()->create_user(['idnumber' => 'S-001']);
        $studentb = $this->getDataGenerator()->create_user(['idnumber' => 'S-002']);
        $this->getDataGenerator()->enrol_user($studenta->id, $course->id, 'student');
        $this->getDataGenerator()->enrol_user($studentb->id, $course->id, 'student');

        $cmone = $this->create_label_cm($course);
        $itemone = $this->create_grade_item_for_cm($course, $cmone);
        $cmtwo = $this->create_label_cm($course);
        $itemtwo = $this->create_grade_item_for_cm($course, $cmtwo);

        $this->assign_grade_to_item($itemone, $studenta->id, 10.0);
        $this->assign_grade_to_item($itemtwo, $studenta->id, 14.0);
        $this->assign_grade_to_item($itemone, $studentb->id, 6.0);
        $this->assign_grade_to_item($itemtwo, $studentb->id, 8.0);

        return [$course, $studenta, $studentb, $itemone, $itemtwo];
    }

    /**
     * Create a lightweight fake spreadsheet strategy for export tests.
     *
     * @param array $identifiers
     * @param bool $overrideaggregation
     * @return object
     */
    private function create_fake_spreadsheet_format(array $identifiers, bool $overrideaggregation = false): object {
        $outputfile = make_temp_directory('gradefiller') . '/spreadsheet_export_' . uniqid('', true) . '.'
            . ($overrideaggregation ? 'xlsm' : 'xlsx');

        if ($overrideaggregation) {
            return new class($identifiers, $outputfile) implements spreadsheet_format_interface, multi_activity_grade_aggregation_interface {
                private array $identifiers;
                private string $outputfile;
                private array $writtengrades = [];
                private array $aggregatedidentifiers = [];

                public function __construct(array $identifiers, string $outputfile) {
                    $this->identifiers = $identifiers;
                    $this->outputfile = $outputfile;
                }

                public function get_name(): string {
                    return 'Fake spreadsheet';
                }

                public function get_key(): string {
                    return 'fake_spreadsheet';
                }

                public function get_description(): string {
                    return 'Fake spreadsheet used by tests';
                }

                public function get_supported_extensions(): array {
                    return ['xlsx', 'xlsm'];
                }

                public function get_upload_label(): string {
                    return $this->get_name();
                }

                public function get_upload_help(): string {
                    return $this->get_description();
                }

                public function get_identifier_mode(): string {
                    return self::IDENTIFIER_MODE_STANDARD;
                }

                public function read_identifiers(string $filepath): array {
                    return array_map(static function(array $entry): object {
                        return (object) $entry;
                    }, $this->identifiers);
                }

                public function write_grades(string $filepath, array $grades): string {
                    $this->writtengrades = $grades;
                    file_put_contents($this->outputfile, json_encode($grades));
                    return $this->outputfile;
                }

                public function validate_file(string $filepath): bool {
                    return true;
                }

                public function aggregate_multi_activity_grades(array $grades, object $context): ?float {
                    $this->aggregatedidentifiers[] = $context->identifier;
                    return max(array_map(static function(object $grade): float {
                        return (float) $grade->grade;
                    }, $grades));
                }

                public function get_written_grades(): array {
                    return $this->writtengrades;
                }

                public function get_last_outputfile(): string {
                    return $this->outputfile;
                }

                public function get_aggregated_identifiers(): array {
                    return $this->aggregatedidentifiers;
                }
            };
        }

        return new class($identifiers, $outputfile) implements spreadsheet_format_interface {
            private array $identifiers;
            private string $outputfile;
            private array $writtengrades = [];

            public function __construct(array $identifiers, string $outputfile) {
                $this->identifiers = $identifiers;
                $this->outputfile = $outputfile;
            }

            public function get_name(): string {
                return 'Fake spreadsheet';
            }

            public function get_key(): string {
                return 'fake_spreadsheet';
            }

            public function get_description(): string {
                return 'Fake spreadsheet used by tests';
            }

            public function get_supported_extensions(): array {
                return ['xlsx'];
            }

            public function get_upload_label(): string {
                return $this->get_name();
            }

            public function get_upload_help(): string {
                return $this->get_description();
            }

            public function get_identifier_mode(): string {
                return self::IDENTIFIER_MODE_STANDARD;
            }

            public function read_identifiers(string $filepath): array {
                return array_map(static function(array $entry): object {
                    return (object) $entry;
                }, $this->identifiers);
            }

            public function write_grades(string $filepath, array $grades): string {
                $this->writtengrades = $grades;
                file_put_contents($this->outputfile, json_encode($grades));
                return $this->outputfile;
            }

            public function validate_file(string $filepath): bool {
                return true;
            }

            public function get_written_grades(): array {
                return $this->writtengrades;
            }

            public function get_last_outputfile(): string {
                return $this->outputfile;
            }
        };
    }

    /**
     * Create a lightweight uploaded template placeholder.
     *
     * @param string $extension
     * @return string
     */
    private function create_temp_template(string $extension): string {
        $filepath = make_temp_directory('gradefiller') . '/template_' . uniqid('', true) . '.' . $extension;
        file_put_contents($filepath, 'test-template');
        return $filepath;
    }

    /**
     * Find one written grade object by identifier.
     *
     * @param array $grades
     * @param string $identifier
     * @return object
     */
    private function find_written_grade(array $grades, string $identifier): object {
        foreach ($grades as $grade) {
            if ($grade->identifier === $identifier) {
                return $grade;
            }
        }

        $this->fail('Missing written grade for identifier ' . $identifier);
    }
}
