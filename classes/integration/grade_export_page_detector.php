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

namespace local_gradefiller\integration;


use context_course;
use moodle_page;

/**
 * Detects whether the current Moodle page should expose the grade export bridge.
 *
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class grade_export_page_detector {
    /**
     * Determine whether the supplied page is a supported grade export page.
     *
     * @param moodle_page $page
     * @return bool
     */
    public function is_grade_export_page(moodle_page $page): bool {
        if (!$page->has_set_url() || !$page->context instanceof context_course) {
            return false;
        }

        $supportedpaths = [
            '/grade/export/ods/index.php',
            '/grade/export/txt/index.php',
            '/grade/export/xls/index.php',
            '/grade/export/xml/index.php',
            '/local/gradefiller/gradeexport.php',
        ];

        return in_array($page->url->get_path(), $supportedpaths, true);
    }
}
