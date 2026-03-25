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
        var selector = document.querySelector('select[name="exportas"]');
        if (!selector || !config || !config.url || !config.label) {
            return;
        }

        var option = selector.querySelector('option[value="' + config.url + '"]');
        if (!option) {
            option = document.createElement('option');
            option.value = config.url;
            selector.appendChild(option);
        }

        option.textContent = config.label;

        if (window.location.pathname.indexOf('/local/gradefiller/gradeexport.php') !== -1) {
            selector.value = config.url;
        }

        if (!selector.dataset.gradefillerBound) {
            selector.addEventListener('change', function() {
                if (selector.value && selector.value !== window.location.href) {
                    window.location.href = selector.value;
                }
            });
            selector.dataset.gradefillerBound = '1';
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
        }
    };
});
