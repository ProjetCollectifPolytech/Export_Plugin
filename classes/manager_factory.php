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
 * Composition root for the Grade Filler manager facade.
 *
 * @package    local_gradefiller
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_gradefiller;

defined('MOODLE_INTERNAL') || die();

use local_gradefiller\service\grade_lookup_service;
use local_gradefiller\service\spreadsheet_fill_service;

/**
 * Builds fully-wired manager instances outside the facade itself.
 *
 * @package    local_gradefiller
 */
class manager_factory {

    /**
     * Create a default manager instance.
     *
     * @return manager
     */
    public static function create_default(): manager {
        return (new self())->create();
    }

    /**
     * Build one manager with the plugin's standard dependency graph.
     *
     * @return manager
     */
    public function create(): manager {
        $formatregistry = new spreadsheet_format_registry();
        $driverregistry = new grade_source_registry();
        $gradelookupservice = new grade_lookup_service($driverregistry);

        return new manager(
            $formatregistry,
            $driverregistry,
            $gradelookupservice,
            new spreadsheet_fill_service($formatregistry, $gradelookupservice)
        );
    }
}
