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
 * Grade export bridge integration for Grade Filler.
 *
 * @package    local_gradefiller
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_gradefiller\integration;

defined('MOODLE_INTERNAL') || die();

use context_course;
use local_gradefiller\manager;
use local_gradefiller\manager_factory;
use moodle_url;

/**
 * Encapsulates the native grade export bridge integration.
 *
 * @package    local_gradefiller
 */
class grade_export_bridge_integration {
    /** @var manager */
    private manager $manager;

    /** @var bool */
    private bool $bridgeloaded = false;

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
     * Check whether the current user can access the Grade Filler grade export bridge.
     *
     * @param context_course $context
     * @return bool
     */
    public function can_access_grade_export_bridge(context_course $context): bool {
        return has_capability('moodle/grade:export', $context);
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
     * Determine whether the current page is a grade export page that should expose the bridge.
     *
     * @return bool
     */
    public function is_grade_export_page(): bool {
        global $PAGE;

        if (!$PAGE->has_set_url() || !$PAGE->context instanceof context_course) {
            return false;
        }

        $supportedpaths = [
            '/grade/export/ods/index.php',
            '/grade/export/txt/index.php',
            '/grade/export/xls/index.php',
            '/grade/export/xml/index.php',
            '/local/gradefiller/gradeexport.php',
        ];

        return in_array($PAGE->url->get_path(), $supportedpaths, true);
    }

    /**
     * Build the configuration passed to the grade export bridge AMD module.
     *
     * @param context_course $context
     * @return array<string, mixed>
     */
    public function get_bridge_config(context_course $context): array {
        global $PAGE;

        return [
            'options' => $this->get_bridge_options($context->instanceid),
            'currenturl' => $PAGE->url->out(false),
        ];
    }

    /**
     * Require the bridge AMD module exactly once per request.
     *
     * @param context_course $context
     * @return void
     */
    public function require_bridge(context_course $context): void {
        global $PAGE;

        if ($this->bridgeloaded) {
            return;
        }

        $config = $this->get_bridge_config($context);
        if (empty($config['options'])) {
            return;
        }

        $PAGE->requires->js_call_amd('local_gradefiller/grade_export_bridge', 'init', [$config]);
        $this->bridgeloaded = true;
    }

    /**
     * Inject the bridge before the top of body when relevant.
     *
     * @return string
     */
    public function before_standard_top_of_body_html(): string {
        global $PAGE;

        if (!$this->is_grade_export_page()) {
            return '';
        }

        $context = $PAGE->context;
        if (!$context instanceof context_course || !$this->can_access_grade_export_bridge($context)) {
            return '';
        }

        $this->require_bridge($context);
        return '';
    }

    /**
     * Inject the bridge before footer on pages where late DOM initialisation can replace action-bar content.
     *
     * @return string
     */
    public function before_footer(): string {
        global $PAGE;

        if (!$this->is_grade_export_page()) {
            return '';
        }

        $context = $PAGE->context;
        if (!$context instanceof context_course || !$this->can_access_grade_export_bridge($context)) {
            return '';
        }

        $this->require_bridge($context);
        return '';
    }
}
