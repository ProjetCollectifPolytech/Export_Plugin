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

defined('MOODLE_INTERNAL') || die();

use context_course;
use local_gradefiller\manager;
use local_gradefiller\manager_factory;
use moodle_url;

/**
 * Builds URLs and AMD configuration for the grade export bridge.
 *
 * @package    local_gradefiller
 */
class grade_export_bridge_config_builder {
    /** @var manager */
    private manager $manager;

    /**
     * Constructor.
     *
     * @param manager|null $manager
     */
    public function __construct(?manager $manager = null) {
        $this->manager = $manager ?? manager_factory::create_default();
    }

    /**
     * Return the local Grade Filler course export URL.
     *
     * @param int $courseid
     * @param string|null $spreadsheetformat
     * @return moodle_url
     */
    public function get_grade_export_url(int $courseid, ?string $spreadsheetformat = null): moodle_url {
        $params = ['id' => $courseid];
        if (!empty($spreadsheetformat)) {
            $params['spreadsheetformat'] = $spreadsheetformat;
        }

        return new moodle_url('/local/gradefiller/gradeexport.php', $params);
    }

    /**
     * Return the spreadsheet formats that should be exposed in Moodle's native "Export as" menu.
     *
     * @param int $courseid
     * @return array<int, array<string, string>>
     */
    public function get_bridge_options(int $courseid): array {
        $options = [];
        foreach ($this->manager->get_available_formats() as $format) {
            $options[] = [
                'key' => $format->get_key(),
                'label' => $format->get_name(),
                'description' => $format->get_description(),
                'url' => $this->get_grade_export_url($courseid, $format->get_key())->out(false),
            ];
        }

        return $options;
    }

    /**
     * Resolve a native grade export plugin key for the action bar on Grade Filler pages.
     *
     * @param int $courseid
     * @return string
     */
    public function get_action_plugin(int $courseid): string {
        $exports = \grade_helper::get_plugins_export($courseid);
        if (empty($exports) || !is_array($exports)) {
            return 'ods';
        }

        foreach ($exports as $export) {
            if ($export->id !== 'keymanager') {
                return $export->id;
            }
        }

        return 'ods';
    }

    /**
     * Build the configuration passed to the grade export bridge AMD module.
     *
     * @param context_course $context
     * @param string $currenturl
     * @return array<string, mixed>
     */
    public function get_bridge_config(context_course $context, string $currenturl): array {
        return [
            'options' => $this->get_bridge_options($context->instanceid),
            'currenturl' => $currenturl,
        ];
    }
}
