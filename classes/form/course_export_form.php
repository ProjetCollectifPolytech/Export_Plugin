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
 * Gradebook export form that extends Moodle's native export options with Grade Filler fields.
 *
 * @package    local_gradefiller
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_gradefiller\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/grade/export/lib.php');
require_once($CFG->libdir . '/formslib.php');
use moodleform;
/**
 * Gradebook export form for the Grade Filler bridge page.
 *
 * @package    local_gradefiller
 */
class course_export_form extends moodleform {
    /** @var course_export_form_support */
    private course_export_form_support $support;
    /** @var course_export_form_definition_builder */
    private course_export_form_definition_builder $definitionbuilder;
    /**
     * Constructor.
     *
     * @param mixed $action
     * @param mixed $customdata
     * @param string $method
     * @param string $target
     * @param array|null $attributes
     * @param bool $editable
     */
    public function __construct(
        $action = null,
        $customdata = null,
        $method = 'post',
        $target = '',
        $attributes = null,
        $editable = true
    ) {

        $this->support = new course_export_form_support();
        $this->definitionbuilder = new course_export_form_definition_builder($this->support);
        parent::__construct($action, $customdata, $method, $target, $attributes, $editable);
    }

    /**
     * Define the form fields.
     */
    public function definition() {

        global $CFG, $COURSE;
        $mform = $this->_form;
        $course = $this->_customdata['course'] ?? $COURSE;
        $formats = $this->_customdata['formats'] ?? [];
        $selectedspreadsheet = $this->_customdata['selectedspreadsheet'] ?? null;
        if ($this->definitionbuilder->add_grade_items_section($mform, $course, $CFG)) {
            $this->add_checkbox_controller(1, null, null, 1);
        }
        $this->definitionbuilder->add_export_options_section($mform, $course, $CFG);
        $this->definitionbuilder->add_gradefiller_options_section($mform, $course, $formats, $selectedspreadsheet, $CFG);
        $mform->addElement('hidden', 'id', $course->id);
        $mform->setType('id', PARAM_INT);
        $this->add_sticky_action_buttons(false, get_string('gradebook_export_download', 'local_gradefiller'));
    }

    /**
     * Force at least one display type like Moodle core does.
     *
     * @return \stdClass|null
     */
    public function get_data() {

        global $CFG;
        return $this->support->normalise_display_selection(parent::get_data(), (int)$CFG->grade_export_displaytype);
    }
    /**
     * Validate Grade Filler-specific fields.
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        if (empty($data['gradefiller_format'])) {
            $errors['gradefiller_format'] = get_string('required');
        }

        return $errors;
    }
}
