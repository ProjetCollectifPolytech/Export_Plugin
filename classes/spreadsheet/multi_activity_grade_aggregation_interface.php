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
 * Optional override for multi-activity grade aggregation in spreadsheet exports.
 *
 * @package    local_gradefiller
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_gradefiller\spreadsheet;

defined('MOODLE_INTERNAL') || die();

/**
 * Allows a spreadsheet format to override the default multi-activity
 * aggregation strategy used by course exports.
 *
 * @package    local_gradefiller
 */
interface multi_activity_grade_aggregation_interface {

    /**
     * Aggregate the grades collected for one identifier.
     *
     * @param array $grades Array of grade entry objects
     * @param object $context Additional export context
     * @return float|null
     */
    public function aggregate_multi_activity_grades(array $grades, object $context): ?float;
}
