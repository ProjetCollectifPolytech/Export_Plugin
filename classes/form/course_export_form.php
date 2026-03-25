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

/**
 * Gradebook export form for the Grade Filler bridge page.
 *
 * @package    local_gradefiller
 */
class course_export_form extends \moodleform {

    /**
     * Define the form fields.
     */
    public function definition() {
        global $CFG, $COURSE;

        $mform = $this->_form;
        $course = $this->_customdata['course'] ?? $COURSE;
        $formats = $this->_customdata['formats'] ?? [];
        $selectedspreadsheet = $this->_customdata['selectedspreadsheet'] ?? null;

        $mform->addElement('header', 'gradeitems', get_string('gradeitemsinc', 'grades'));
        $mform->setExpanded('gradeitems', true);

        $switch = grade_get_setting($course->id, 'aggregationposition', $CFG->grade_aggregationposition);
        $sequence = new \grade_seq($course->id, $switch);
        $needsmultiselect = false;
        $canviewhidden = has_capability('moodle/grade:viewhidden', \context_course::instance($course->id));

        if ($gradeitems = $sequence->items) {
            foreach ($gradeitems as $gradeitem) {
                if ($gradeitem->is_hidden() && !$canviewhidden) {
                    continue;
                }

                $mform->addElement(
                    'advcheckbox',
                    'itemids[' . $gradeitem->id . ']',
                    $gradeitem->get_name(),
                    null,
                    ['group' => 1]
                );
                $mform->setDefault('itemids[' . $gradeitem->id . ']', 1);
                $needsmultiselect = true;
            }
        }

        if ($needsmultiselect) {
            $this->add_checkbox_controller(1, null, null, 1);
        }

        $mform->addElement('header', 'options', get_string('exportformatoptions', 'grades'));
        $mform->setExpanded('options', false);

        $mform->addElement('advcheckbox', 'export_feedback', get_string('exportfeedback', 'grades'));
        $mform->setDefault('export_feedback', $CFG->grade_export_exportfeedback ?? 0);

        $coursecontext = \context_course::instance($course->id);
        if (has_capability('moodle/course:viewsuspendedusers', $coursecontext)) {
            $mform->addElement('advcheckbox', 'export_onlyactive', get_string('exportonlyactive', 'grades'));
            $mform->setType('export_onlyactive', PARAM_BOOL);
            $mform->setDefault('export_onlyactive', 1);
            $mform->addHelpButton('export_onlyactive', 'exportonlyactive', 'grades');
        } else {
            $mform->addElement('hidden', 'export_onlyactive', 1);
            $mform->setType('export_onlyactive', PARAM_BOOL);
            $mform->setConstant('export_onlyactive', 1);
        }

        $checkboxes = [];
        $checkboxes[] = $mform->createElement(
            'advcheckbox',
            'display[real]',
            null,
            get_string('real', 'grades'),
            null,
            [0, GRADE_DISPLAY_TYPE_REAL]
        );
        $checkboxes[] = $mform->createElement(
            'advcheckbox',
            'display[percentage]',
            null,
            get_string('percentage', 'grades'),
            null,
            [0, GRADE_DISPLAY_TYPE_PERCENTAGE]
        );
        $checkboxes[] = $mform->createElement(
            'advcheckbox',
            'display[letter]',
            null,
            get_string('letter', 'grades'),
            null,
            [0, GRADE_DISPLAY_TYPE_LETTER]
        );
        $mform->addGroup($checkboxes, 'displaytypes', get_string('gradeexportdisplaytypes', 'grades'), ' ', false);
        $mform->setDefault('display[real]', $CFG->grade_export_displaytype == GRADE_DISPLAY_TYPE_REAL);
        $mform->setDefault('display[percentage]', $CFG->grade_export_displaytype == GRADE_DISPLAY_TYPE_PERCENTAGE);
        $mform->setDefault('display[letter]', $CFG->grade_export_displaytype == GRADE_DISPLAY_TYPE_LETTER);

        $mform->addElement(
            'select',
            'decimals',
            get_string('gradeexportdecimalpoints', 'grades'),
            [0 => 0, 1 => 1, 2 => 2, 3 => 3, 4 => 4, 5 => 5]
        );
        $mform->setDefault('decimals', $CFG->grade_export_decimalpoints);

        $sectiontitle = $selectedspreadsheet !== null
            ? $selectedspreadsheet->get_name()
            : get_string('gradebook_export_section', 'local_gradefiller');

        $mform->addElement('header', 'gradefilleroptions', $sectiontitle);
        $mform->setExpanded('gradefilleroptions', true);

        $formatoptions = [];
        foreach ($formats as $format) {
            $formatoptions[$format->get_key()] = $format->get_name() . ' - ' . $format->get_description();
        }

        $acceptedextensions = [];
        if ($selectedspreadsheet !== null) {
            foreach ($selectedspreadsheet->get_supported_extensions() as $extension) {
                $acceptedextensions[] = '.' . ltrim((string) $extension, '.');
            }
        } else {
            foreach ($formats as $format) {
                foreach ($format->get_supported_extensions() as $extension) {
                    $acceptedextensions[] = '.' . ltrim((string) $extension, '.');
                }
            }
        }
        $acceptedextensions = array_values(array_unique($acceptedextensions));
        if (empty($acceptedextensions)) {
            $acceptedextensions = ['.xlsx'];
        }

        if ($selectedspreadsheet !== null && $selectedspreadsheet->get_upload_help() !== '') {
            $mform->addElement(
                'static',
                'spreadsheetformat_help',
                '',
                \html_writer::div(s($selectedspreadsheet->get_upload_help()), 'text-muted')
            );
        }

        if ($selectedspreadsheet !== null) {
            $mform->addElement('hidden', 'spreadsheetformat', $selectedspreadsheet->get_key());
            $mform->setType('spreadsheetformat', PARAM_ALPHANUMEXT);
        }

        if (count($formatoptions) > 1) {
            $mform->addElement(
                'select',
                'gradefiller_format',
                get_string('gradebook_export_format', 'local_gradefiller'),
                $formatoptions
            );
            $mform->setType('gradefiller_format', PARAM_ALPHANUMEXT);
            $mform->addRule('gradefiller_format', null, 'required', null, 'client');
            $mform->setDefault('gradefiller_format', array_key_first($formatoptions));
        } else if (!empty($formatoptions)) {
            $mform->addElement('hidden', 'gradefiller_format', array_key_first($formatoptions));
            $mform->setType('gradefiller_format', PARAM_ALPHANUMEXT);
        }

        $filepickerlabel = $selectedspreadsheet !== null
            ? $selectedspreadsheet->get_upload_label()
            : get_string('gradebook_template_file', 'local_gradefiller');

        $mform->addElement('filepicker', 'templatefile', $filepickerlabel, null, [
            'accepted_types' => $acceptedextensions,
            'maxbytes' => get_max_upload_file_size($CFG->maxbytes, $course->maxbytes),
        ]);
        $mform->addRule('templatefile', null, 'required', null, 'client');
        if ($selectedspreadsheet === null) {
            $mform->addHelpButton('templatefile', 'gradebook_template_file', 'local_gradefiller');
        }

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

        $data = parent::get_data();
        if ($data && isset($data->display) && is_array($data->display) && count(array_filter($data->display)) === 0) {
            if ($CFG->grade_export_displaytype == GRADE_DISPLAY_TYPE_LETTER) {
                $data->display['letter'] = GRADE_DISPLAY_TYPE_LETTER;
            } else if ($CFG->grade_export_displaytype == GRADE_DISPLAY_TYPE_PERCENTAGE) {
                $data->display['percentage'] = GRADE_DISPLAY_TYPE_PERCENTAGE;
            } else {
                $data->display['real'] = GRADE_DISPLAY_TYPE_REAL;
            }
        }

        return $data;
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
