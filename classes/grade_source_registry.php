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
 * Registry for grade source drivers.
 *
 * @package    local_gradefiller
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_gradefiller;

use local_gradefiller\source\grade_source_interface;
use local_gradefiller\source\grade_source_offlinequiz;
use local_gradefiller\source\grade_source_papergrade;

defined('MOODLE_INTERNAL') || die();

/**
 * Keeps driver selection explicit and deterministic.
 *
 * @package    local_gradefiller
 */
class grade_source_registry {
    /** @var grade_source_interface[]|null */
    private ?array $drivers = null;

    /**
     * Return available grade source drivers.
     *
     * @return grade_source_interface[]
     */
    public function get_available_drivers(): array {
        if ($this->drivers !== null) {
            return $this->drivers;
        }

        $this->drivers = [
            new grade_source_papergrade(),
            new grade_source_offlinequiz(),
        ];

        return $this->drivers;
    }

    /**
     * Resolve the first driver supporting a course module.
     *
     * @param \stdClass|\cm_info $cm
     * @return grade_source_interface|null
     */
    public function get_driver_for_cm($cm): ?grade_source_interface {
        foreach ($this->get_available_drivers() as $driver) {
            if ($driver->supports($cm)) {
                return $driver;
            }
        }

        return null;
    }
}
