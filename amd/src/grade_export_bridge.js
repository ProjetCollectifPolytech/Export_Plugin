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
 * Extend Moodle's "Export as" selector with one Grade Filler entry per
 * spreadsheet format.
 *
 * @module     local_gradefiller/grade_export_bridge
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define([], function() {
    /**
     * Resolve the currently selected Grade Filler bridge option.
     *
     * @param {Object} config Bridge configuration
     * @returns {?Object}
     */
    var findSelectedConfig = function(config) {
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

    /**
     * Bind keyboard and mouse navigation once on a bridge option.
     *
     * @param {HTMLElement} option
     * @param {Object} optionConfig
     */
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

    /**
     * Create or refresh a Grade Filler option in the export menu.
     *
     * @param {HTMLElement} listbox
     * @param {Object} optionConfig
     * @returns {?HTMLElement}
     */
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

    /**
     * Add or refresh the Grade Filler options on the native export selector.
     *
     * @param {Object} config Bridge configuration
     */
    var injectOption = function(config) {
        var input = document.querySelector('input[name="exportas"]');
        if (!input || !config || !config.options || !config.options.length) {
            return;
        }

        var selectMenu = input.closest('.select-menu');
        if (!selectMenu) {
            return;
        }

        var listbox = selectMenu.querySelector('[role="listbox"]');
        var toggle = selectMenu.querySelector('.dropdown-toggle');
        if (!listbox || !toggle) {
            return;
        }

        var selectedOption = null;
        config.options.forEach(function(optionConfig) {
            var option = ensureOption(listbox, optionConfig);
            if (optionConfig.url === config.currenturl) {
                selectedOption = option;
            }
        });

        if (window.location.pathname.indexOf('/local/gradefiller/gradeexport.php') !== -1) {
            var selectedConfig = findSelectedConfig(config);
            if (!selectedConfig || !selectedOption) {
                return;
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
    };

    return {
        /**
         * Initialize the bridge.
         *
         * @param {Object} config Bridge configuration
         */
        init: function(config) {
            injectOption(config);
            document.addEventListener('DOMContentLoaded', function() {
                injectOption(config);
            });
            window.setTimeout(function() {
                injectOption(config);
            }, 50);
            window.setTimeout(function() {
                injectOption(config);
            }, 300);
        }
    };
});
