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
 * Library functions for local_gradefiller.
 *
 * @package    local_gradefiller
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/gradelib.php');
require_once($CFG->dirroot . '/grade/lib.php');

/**
 * Check whether the current user can view Grade Filler.
 *
 * Supports the new split capabilities while remaining compatible with the
 * legacy local/gradefiller:use capability.
 *
 * @param context $context Context to check
 * @return bool
 */
function local_gradefiller_user_can_view(context $context): bool {
    return has_capability('local/gradefiller:view', $context)
        || has_capability('local/gradefiller:use', $context);
}

/**
 * Check whether the current user can process files in Grade Filler.
 *
 * Supports the new split capabilities while remaining compatible with the
 * legacy local/gradefiller:use capability.
 *
 * @param context $context Context to check
 * @return bool
 */
function local_gradefiller_user_can_process(context $context): bool {
    return has_capability('local/gradefiller:process', $context)
        || has_capability('local/gradefiller:use', $context);
}

/**
 * Resolve activity access metadata for Grade Filler links and buttons.
 *
 * @param \stdClass|\cm_info $cm Course module
 * @param context_course $coursecontext Course context
 * @return array|null
 */
function local_gradefiller_get_activity_access_data($cm, context_course $coursecontext): ?array {
    $modulecontext = context_module::instance($cm->id);
    if (!local_gradefiller_user_can_view($modulecontext)) {
        return null;
    }

    $manager = new \local_gradefiller\manager();
    $gradeitem = grade_item::fetch([
        'itemtype' => 'mod',
        'itemmodule' => $cm->modname,
        'iteminstance' => $cm->instance,
        'courseid' => $coursecontext->instanceid,
    ]);
    $driver = $manager->get_driver_for_cm($cm);

    if (!$gradeitem && $driver === null) {
        return null;
    }

    return [
        'label' => get_string('fill_grades', 'local_gradefiller'),
        'url' => new moodle_url('/local/gradefiller/index.php', ['id' => $cm->id]),
        'nodekey' => 'gradefiller_fill',
        'icon' => new pix_icon('i/grades', ''),
        'can_process' => local_gradefiller_user_can_process($modulecontext),
        'has_grade_item' => (bool)$gradeitem,
        'supports_anonymous' => ($driver !== null),
        'driver_name' => $driver ? $driver->get_name() : '',
    ];
}

/**
 * Determine whether the current page should display a prominent activity button.
 *
 * @return bool
 */
function local_gradefiller_should_add_activity_button(): bool {
    global $PAGE;

    if (empty($PAGE->cm) || empty($PAGE->activityname)) {
        return false;
    }

    if ((int)$PAGE->context->contextlevel !== CONTEXT_MODULE) {
        return false;
    }

    return str_starts_with((string)$PAGE->pagetype, 'mod-' . $PAGE->activityname . '-');
}

/**
 * Add a visible page button for supported activity pages.
 *
 * @param array $accessdata Access data returned by local_gradefiller_get_activity_access_data()
 * @return void
 */
function local_gradefiller_add_activity_button(array $accessdata): void {
    global $PAGE;

    if (strpos((string)$PAGE->button, 'local-gradefiller-activity-button') !== false) {
        return;
    }

    $buttonlink = html_writer::link(
        $accessdata['url'],
        $accessdata['label'],
        ['class' => 'btn btn-primary']
    );
    $buttonhtml = html_writer::div($buttonlink, 'singlebutton local-gradefiller-activity-button');

    $PAGE->set_button($buttonhtml . (string)$PAGE->button);
}

/**
 * Extend the activity settings navigation with a Grade Filler link.
 *
 * @param settings_navigation $settingsnav The settings navigation object
 * @param context $context The current page context
 * @return void
 */
function local_gradefiller_extend_settings_navigation($settingsnav, $context) {
    global $PAGE;

    if ($PAGE->cm === null || $PAGE->course === null) {
        return;
    }

    $coursecontext = context_course::instance($PAGE->course->id);
    $accessdata = local_gradefiller_get_activity_access_data($PAGE->cm, $coursecontext);
    if ($accessdata === null) {
        return;
    }

    if (local_gradefiller_should_add_activity_button()) {
        local_gradefiller_add_activity_button($accessdata);
    }

    if ($settingsnode = $settingsnav->find('modulesettings', navigation_node::TYPE_SETTING)) {
        if (!$settingsnode->find($accessdata['nodekey'], navigation_node::TYPE_SETTING)) {
            $settingsnode->add(
                $accessdata['label'],
                $accessdata['url'],
                navigation_node::TYPE_SETTING,
                null,
                $accessdata['nodekey'],
                $accessdata['icon']
            );
        }
    }
}

/**
 * Add link to secondary navigation on supported activity pages.
 *
 * @param navigation_node $navigation The navigation node to extend
 * @param stdClass $course The course object
 * @param cm_info $cm The course module info object
 * @return void
 */
function local_gradefiller_extend_navigation_module($navigation, $course, $cm) {
    $coursecontext = context_course::instance($course->id);
    $accessdata = local_gradefiller_get_activity_access_data($cm, $coursecontext);
    if ($accessdata === null) {
        return;
    }

    $navigation->add(
        $accessdata['label'],
        $accessdata['url'],
        navigation_node::TYPE_CUSTOM,
        null,
        $accessdata['nodekey'],
        $accessdata['icon']
    );
}

/**
 * Backward-compatible wrapper for older navigation integrations.
 *
 * @param navigation_node $navigation The navigation node to extend
 * @param stdClass $course The course object
 * @param context $context The context of the course
 * @return void
 */
function local_gradefiller_extend_navigation_course($navigation, $course, $context) {
    global $PAGE;

    if ($PAGE->cm === null) {
        return;
    }

    local_gradefiller_extend_navigation_module($navigation, $course, $PAGE->cm);
}

/**
 * Return the local Grade Filler course export URL.
 *
 * @param int $courseid Course ID
 * @param string|null $spreadsheetformat Optional spreadsheet format key carried by the export bridge
 * @return moodle_url
 */
function local_gradefiller_get_grade_export_url(int $courseid, ?string $spreadsheetformat = null): moodle_url {
    $params = ['id' => $courseid];
    if (!empty($spreadsheetformat)) {
        $params['spreadsheetformat'] = $spreadsheetformat;
    }

    return new moodle_url('/local/gradefiller/gradeexport.php', $params);
}

/**
 * Return the Grade Filler spreadsheet formats that should be exposed in Moodle's
 * native "Export as" menu.
 *
 * @param int $courseid Course ID
 * @return array<int, array<string, string>>
 */
function local_gradefiller_get_grade_export_bridge_options(int $courseid): array {
    $manager = new \local_gradefiller\manager();
    $options = [];

    foreach ($manager->get_available_formats() as $format) {
        $options[] = [
            'key' => $format->get_key(),
            'label' => $format->get_name(),
            'description' => $format->get_description(),
            'url' => local_gradefiller_get_grade_export_url($courseid, $format->get_key())->out(false),
        ];
    }

    return $options;
}

/**
 * Check whether the current user can access the Grade Filler grade export bridge.
 *
 * This bridge extends Moodle's native grade export workflow, so it follows the
 * same course-level export permission model without changing the existing
 * activity-based Grade Filler permissions.
 *
 * @param context_course $context Course context
 * @return bool
 */
function local_gradefiller_can_access_grade_export_bridge(context_course $context): bool {
    return has_capability('moodle/grade:export', $context);
}

/**
 * Resolve a native grade export plugin key for the action bar on Grade Filler pages.
 *
 * @param int $courseid Course ID
 * @return string
 */
function local_gradefiller_get_grade_export_action_plugin(int $courseid): string {
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
 * Determine whether the current page is a grade export page that should expose the Grade Filler bridge.
 *
 * @return bool
 */
function local_gradefiller_is_grade_export_page(): bool {
    global $PAGE;

    if (!$PAGE->has_set_url() || !$PAGE->context instanceof context_course) {
        return false;
    }
    $path = $PAGE->url->get_path();
    $supportedpaths = [
        '/grade/export/ods/index.php',
        '/grade/export/txt/index.php',
        '/grade/export/xls/index.php',
        '/grade/export/xml/index.php',
        '/local/gradefiller/gradeexport.php',
    ];

    return in_array($path, $supportedpaths, true);
}

/**
 * Build the inline bridge script for Moodle's native grade export selector.
 *
 * @param context_course $context Course context
 * @return string
 */
function local_gradefiller_get_grade_export_bridge_html(context_course $context): string {
    global $PAGE;

    $options = local_gradefiller_get_grade_export_bridge_options($context->instanceid);
    if (empty($options)) {
        return '';
    }

    $config = [
        'options' => $options,
        'currenturl' => $PAGE->url->out(false),
    ];
    $json = json_encode($config, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
    $script = <<<JS
(function(config) {
    var findSelectedConfig = function() {
        if (!config || !config.options || !config.options.length) {
            return null;
        }

        for (var i = 0; i < config.options.length; i++) {
            if (config.options[i].url === config.currenturl) {
                return config.options[i];
            }
        }

        return null;
    };

    var bindOption = function(option, optionConfig) {
        if (option.dataset.gradefillerBound) {
            return;
        }

        option.tabIndex = 0;

        var navigate = function(event) {
            if (event.type === 'keydown' && event.key !== 'Enter' && event.key !== ' ') {
                return;
            }
            event.preventDefault();
            window.location.href = optionConfig.url;
        };

        option.addEventListener('click', navigate);
        option.addEventListener('keydown', navigate);
        option.dataset.gradefillerBound = '1';
    };

    var ensureOption = function(listbox, optionConfig) {
        var option = null;
        listbox.querySelectorAll('.dropdown-item[role="option"]').forEach(function(item) {
            if (item.dataset.value === optionConfig.url) {
                option = item;
            }
        });

        if (!option) {
            option = document.createElement('li');
            option.className = 'dropdown-item';
            option.setAttribute('role', 'option');
            option.setAttribute('data-value', optionConfig.url);
            option.textContent = optionConfig.label;
            listbox.appendChild(option);
        } else if (option.textContent !== optionConfig.label) {
            option.textContent = optionConfig.label;
        }

        bindOption(option, optionConfig);

        return option;
    };

    var applyBridge = function() {
        var input = document.querySelector('input[name="exportas"]');
        if (!input || !config || !config.options || !config.options.length) {
            return false;
        }

        var selectMenu = input.closest('.select-menu');
        if (!selectMenu) {
            return false;
        }

        var listbox = selectMenu.querySelector('[role="listbox"]');
        var toggle = selectMenu.querySelector('.dropdown-toggle');
        if (!listbox || !toggle) {
            return false;
        }

        var selectedOption = null;
        config.options.forEach(function(optionConfig) {
            var option = ensureOption(listbox, optionConfig);
            if (optionConfig.url === config.currenturl) {
                selectedOption = option;
            }
        });

        if (window.location.pathname.indexOf('/local/gradefiller/gradeexport.php') !== -1) {
            var selectedConfig = findSelectedConfig();
            if (!selectedConfig || !selectedOption) {
                return true;
            }

            input.value = selectedConfig.url;
            listbox.querySelectorAll('.dropdown-item[role="option"]').forEach(function(item) {
                item.removeAttribute('aria-selected');
            });
            selectedOption.setAttribute('aria-selected', 'true');

            var selected = toggle.querySelector('[data-selected-option]');
            if (selected) {
                selected.textContent = selectedConfig.label;
            } else {
                toggle.textContent = selectedConfig.label;
            }
        }

        return true;
    };

    var scheduleBridge = function() {
        if (applyBridge()) {
            return;
        }

        var attempts = 0;
        var interval = window.setInterval(function() {
            attempts++;
            if (applyBridge() || attempts >= 20) {
                window.clearInterval(interval);
            }
        }, 150);

        if (window.MutationObserver) {
            var observer = new MutationObserver(function() {
                if (applyBridge()) {
                    observer.disconnect();
                }
            });
            observer.observe(document.documentElement, {childList: true, subtree: true});
            window.setTimeout(function() {
                observer.disconnect();
            }, 4000);
        }
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', scheduleBridge);
    } else {
        scheduleBridge();
    }

    document.addEventListener('click', function(event) {
        if (event.target.closest('.select-menu')) {
            window.setTimeout(scheduleBridge, 0);
        }
    });
})({$json});
JS;

    return html_writer::script($script);
}

/**
 * Inject the Grade Filler entry into Moodle's native grade export selector.
 *
 * @return string
 */
function local_gradefiller_before_standard_top_of_body_html(): string {
    global $PAGE;

    if (!local_gradefiller_is_grade_export_page()) {
        return '';
    }

    $context = $PAGE->context;
    if (!$context instanceof context_course || !local_gradefiller_can_access_grade_export_bridge($context)) {
        return '';
    }

    return local_gradefiller_get_grade_export_bridge_html($context);
}

/**
 * Inject the Grade Filler bridge again before footer for pages where late DOM
 * initialisation can replace parts of the export action bar.
 *
 * @return string
 */
function local_gradefiller_before_footer(): string {
    global $PAGE;

    if (!local_gradefiller_is_grade_export_page()) {
        return '';
    }

    $context = $PAGE->context;
    if (!$context instanceof context_course || !local_gradefiller_can_access_grade_export_bridge($context)) {
        return '';
    }

    return local_gradefiller_get_grade_export_bridge_html($context);
}
