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
 * Extend Moodle's "Export as" selector with Grade Filler.
 *
 * @module     local_gradefiller/grade_export_bridge
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define([], function() {
    /**
     * Add or refresh the Grade Filler option on the native export selector.
     *
     * @param {Object} config Bridge configuration
     */
    var injectOption = function(config) {
        var input = document.querySelector('input[name="exportas"]');
        if (!input || !config || !config.url || !config.label) {
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

        var option = null;
        listbox.querySelectorAll('.dropdown-item[role="option"]').forEach(function(item) {
            if (item.dataset.value === config.url) {
                option = item;
            }
        });

        if (!option) {
            option = document.createElement('li');
            option.className = 'dropdown-item';
            option.setAttribute('role', 'option');
            option.setAttribute('data-value', config.url);
            option.textContent = config.label;
            listbox.appendChild(option);
        }

        if (!option.dataset.gradefillerBound) {
            option.tabIndex = 0;

            var navigate = function(event) {
                if (event.type === 'keydown' && event.key !== 'Enter' && event.key !== ' ') {
                    return;
                }
                event.preventDefault();
                window.location.href = config.url;
            };

            option.addEventListener('click', navigate);
            option.addEventListener('keydown', navigate);
            option.dataset.gradefillerBound = '1';
        }

        if (window.location.pathname.indexOf('/local/gradefiller/gradeexport.php') !== -1) {
            input.value = config.url;

            listbox.querySelectorAll('.dropdown-item[role="option"]').forEach(function(item) {
                item.removeAttribute('aria-selected');
            });
            option.setAttribute('aria-selected', 'true');

            var selected = toggle.querySelector('[data-selected-option]');
            if (selected) {
                selected.textContent = config.label;
            } else {
                toggle.textContent = config.label;
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
