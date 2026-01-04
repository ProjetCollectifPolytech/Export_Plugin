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
 * Upload form interaction
 *
 * @module     local_gradefiller/upload_form
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define([], function() {
    return {
        /**
         * Initialize the upload form interactions
         */
        init: function() {
            var dropZone = document.getElementById('file-drop-zone');
            var fileInput = document.getElementById('spreadsheet');
            var fileName = document.getElementById('file-name');
            var fileNameText = document.getElementById('file-name-text');
            
            if (!dropZone || !fileInput) {
                return;
            }
            
            /**
             * Prevent default drag behaviors
             */
            function preventDefaults(e) {
                e.preventDefault();
                e.stopPropagation();
            }
            
            // Prevent default drag behaviors on drop zone and body.
            ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(function(eventName) {
                dropZone.addEventListener(eventName, preventDefaults, false);
                document.body.addEventListener(eventName, preventDefaults, false);
            });
            
            // Highlight drop zone when dragging over it.
            ['dragenter', 'dragover'].forEach(function(eventName) {
                dropZone.addEventListener(eventName, function() {
                    dropZone.classList.add('drag-over');
                }, false);
            });
            
            ['dragleave', 'drop'].forEach(function(eventName) {
                dropZone.addEventListener(eventName, function() {
                    dropZone.classList.remove('drag-over');
                }, false);
            });
            
            // Handle dropped files.
            dropZone.addEventListener('drop', function(e) {
                var dt = e.dataTransfer;
                var files = dt.files;
                
                if (files.length > 0) {
                    fileInput.files = files;
                    displayFileName(files[0]);
                }
            }, false);
            
            // Handle file selection via click.
            fileInput.addEventListener('change', function() {
                if (fileInput.files.length > 0) {
                    displayFileName(fileInput.files[0]);
                }
            });
            
            /**
             * Display selected filename
             * @param {File} file Selected file
             */
            function displayFileName(file) {
                if (file) {
                    fileNameText.textContent = file.name;
                    fileName.style.display = 'block';
                    dropZone.classList.add('has-file');
                }
            }

            // Format selection - show description.
            var formatSelect = document.getElementById('format');
            if (formatSelect) {
                formatSelect.addEventListener('change', function() {
                    var selectedOption = this.options[this.selectedIndex];
                    var description = selectedOption.getAttribute('data-description');
                    var descriptionElement = document.getElementById('format-description');
                    if (descriptionElement) {
                        descriptionElement.textContent = description || '';
                    }
                });
            }
        }
    };
});
