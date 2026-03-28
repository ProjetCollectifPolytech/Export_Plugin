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


use context_course;

/**
 * Encapsulates the native grade export bridge integration.
 *
 * @package    local_gradefiller
 */
class grade_export_bridge_integration {
    /** @var grade_export_bridge_config_builder */
    private grade_export_bridge_config_builder $configbuilder;

    /** @var grade_export_page_detector */
    private grade_export_page_detector $pagedetector;

    /** @var bool */
    private bool $bridgeloaded = false;

    /**
     * Constructor.
     *
     * @param grade_export_bridge_config_builder|null $configbuilder
     * @param grade_export_page_detector|null $pagedetector
     */
    public function __construct(
        ?grade_export_bridge_config_builder $configbuilder = null,
        ?grade_export_page_detector $pagedetector = null
    ) {
        $this->configbuilder = $configbuilder ?? new grade_export_bridge_config_builder();
        $this->pagedetector = $pagedetector ?? new grade_export_page_detector();
    }

    /**
     * Return the local Grade Filler course export URL.
     *
     * @param int $courseid
     * @param string|null $spreadsheetformat
     * @return \moodle_url
     */
    public function get_grade_export_url(int $courseid, ?string $spreadsheetformat = null): \moodle_url {
        return $this->configbuilder->get_grade_export_url($courseid, $spreadsheetformat);
    }

    /**
     * Return the spreadsheet formats that should be exposed in Moodle's native "Export as" menu.
     *
     * @param int $courseid
     * @return array<int, array<string, string>>
     */
    public function get_bridge_options(int $courseid): array {
        return $this->configbuilder->get_bridge_options($courseid);
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
        return $this->configbuilder->get_action_plugin($courseid);
    }

    /**
     * Determine whether the current page is a grade export page that should expose the bridge.
     *
     * @return bool
     */
    public function is_grade_export_page(): bool {
        global $PAGE;

        return $this->pagedetector->is_grade_export_page($PAGE);
    }

    /**
     * Build the configuration passed to the grade export bridge AMD module.
     *
     * @param context_course $context
     * @return array<string, mixed>
     */
    public function get_bridge_config(context_course $context): array {
        global $PAGE;

        return $this->configbuilder->get_bridge_config($context, $PAGE->url->out(false));
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
        $this->bootstrap_bridge_for_current_page();
        return '';
    }

    /**
     * Inject the bridge before footer on pages where late DOM initialisation can replace action-bar content.
     *
     * @return string
     */
    public function before_footer(): string {
        $this->bootstrap_bridge_for_current_page();
        return '';
    }

    /**
     * Require the bridge when the current page and user are eligible.
     *
     * @return void
     */
    private function bootstrap_bridge_for_current_page(): void {
        $context = $this->get_current_bridge_context();
        if ($context === null) {
            return;
        }

        $this->require_bridge($context);
    }

    /**
     * Resolve the current course context when the bridge should be active.
     *
     * @return context_course|null
     */
    private function get_current_bridge_context(): ?context_course {
        global $PAGE;

        if (!$this->is_grade_export_page()) {
            return null;
        }

        $context = $PAGE->context;
        if (!$context instanceof context_course) {
            return null;
        }

        if (!$this->can_access_grade_export_bridge($context)) {
            return null;
        }

        return $context;
    }
}
