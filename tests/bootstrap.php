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
 * PHPUnit bootstrap for local_gradefiller
 *
 * Delegates to Moodle's standard PHPUnit bootstrap. This file is referenced
 * from phpunit.xml so that `vendor/bin/phpunit` can be run from the plugin
 * directory directly, as long as it is installed under
 * {moodle_root}/local/gradefiller/.
 *
 * @package    local_gradefiller
 * @category   test
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// Walk up the tree to find the Moodle root (contains config.php).
$dir = __DIR__;
$moodleroot = null;

for ($i = 0; $i < 6; $i++) {
    $dir = dirname($dir);
    if (file_exists($dir . '/config.php') && file_exists($dir . '/lib/setup.php')) {
        $moodleroot = $dir;
        break;
    }
}

if ($moodleroot === null) {
    echo "ERROR: Could not locate Moodle root directory.\n";
    echo "Make sure the plugin is installed at {moodle_root}/local/gradefiller/\n";
    exit(1);
}

require_once($moodleroot . '/lib/phpunit/bootstrap.php');