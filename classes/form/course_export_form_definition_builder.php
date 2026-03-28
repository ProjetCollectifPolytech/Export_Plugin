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

namespace local_gradefiller\form;


use context_course;
use MoodleQuickForm;

/**
 * Adds Grade Filler sections to the grade export form.
 *
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class course_export_form_definition_builder {
    /** @var course_export_form_support */
    private course_export_form_support $support;

    /**
     * Constructor.
     *
     * @param course_export_form_support|null $support
     */
    public function __construct(?course_export_form_support $support = null) {
        $this->support = $support ?? new course_export_form_support();
    }

    /**
     * Define the section listing selectable grade items.
     *
     * @param MoodleQuickForm $mform
     * @param \stdClass $course
     * @param \stdClass $cfg
     * @return bool
     */
    public function add_grade_items_section(MoodleQuickForm $mform, \stdClass $course, \stdClass $cfg): bool {
        $mform->addElement('header', 'gradeitems', get_string('gradeitemsinc', 'grades'));
        $mform->setExpanded('gradeitems', true);

        $gradeitems = $this->support->get_visible_grade_items($course, $cfg);
        foreach ($gradeitems as $gradeitem) {
            $mform->addElement(
                'advcheckbox',
                'itemids[' . $gradeitem['id'] . ']',
                $gradeitem['name'],
                null,
                ['group' => 1]
            );
            $mform->setDefault('itemids[' . $gradeitem['id'] . ']', 1);
        }

        return !empty($gradeitems);
    }

    /**
     * Define Moodle-native export options reused by the bridge page.
     *
     * @param MoodleQuickForm $mform
     * @param \stdClass $course
     * @param \stdClass $cfg
     * @return void
     */
    public function add_export_options_section(MoodleQuickForm $mform, \stdClass $course, \stdClass $cfg): void {
        $mform->addElement('header', 'options', get_string('exportformatoptions', 'grades'));
        $mform->setExpanded('options', false);

        $mform->addElement('advcheckbox', 'export_feedback', get_string('exportfeedback', 'grades'));
        $mform->setDefault('export_feedback', $cfg->grade_export_exportfeedback ?? 0);

        $this->add_export_only_active_field($mform, $course);
        $this->add_display_type_group($mform, $cfg);

        $mform->addElement(
            'select',
            'decimals',
            get_string('gradeexportdecimalpoints', 'grades'),
            [0 => 0, 1 => 1, 2 => 2, 3 => 3, 4 => 4, 5 => 5]
        );
        $mform->setDefault('decimals', $cfg->grade_export_decimalpoints);
    }

    /**
     * Define Grade Filler-specific fields on top of Moodle's export form.
     *
     * @param MoodleQuickForm $mform
     * @param \stdClass $course
     * @param array $formats
     * @param object|null $selectedspreadsheet
     * @param \stdClass $cfg
     * @return void
     */
    public function add_gradefiller_options_section(
        MoodleQuickForm $mform,
        \stdClass $course,
        array $formats,
        $selectedspreadsheet,
        \stdClass $cfg
    ): void {
        $formatoptions = $this->support->build_format_options($formats);
        $acceptedextensions = $this->support->build_accepted_extensions($formats, $selectedspreadsheet);
        $sectiontitle = $selectedspreadsheet !== null
            ? $selectedspreadsheet->get_name()
            : get_string('gradebook_export_section', 'local_gradefiller');

        $mform->addElement('header', 'gradefilleroptions', $sectiontitle);
        $mform->setExpanded('gradefilleroptions', true);

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
            'maxbytes' => get_max_upload_file_size($cfg->maxbytes, $course->maxbytes),
        ]);
        $mform->addRule('templatefile', null, 'required', null, 'client');
        if ($selectedspreadsheet === null) {
            $mform->addHelpButton('templatefile', 'gradebook_template_file', 'local_gradefiller');
        }
    }

    /**
     * Add the active-user filter, falling back to a hidden constant when required.
     *
     * @param MoodleQuickForm $mform
     * @param \stdClass $course
     * @return void
     */
    private function add_export_only_active_field(MoodleQuickForm $mform, \stdClass $course): void {
        $coursecontext = context_course::instance($course->id);

        if (has_capability('moodle/course:viewsuspendedusers', $coursecontext)) {
            $mform->addElement('advcheckbox', 'export_onlyactive', get_string('exportonlyactive', 'grades'));
            $mform->setType('export_onlyactive', PARAM_BOOL);
            $mform->setDefault('export_onlyactive', 1);
            $mform->addHelpButton('export_onlyactive', 'exportonlyactive', 'grades');
            return;
        }

        $mform->addElement('hidden', 'export_onlyactive', 1);
        $mform->setType('export_onlyactive', PARAM_BOOL);
        $mform->setConstant('export_onlyactive', 1);
    }

    /**
     * Add Moodle's grade display type selector group.
     *
     * @param MoodleQuickForm $mform
     * @param \stdClass $cfg
     * @return void
     */
    private function add_display_type_group(MoodleQuickForm $mform, \stdClass $cfg): void {
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
        $mform->setDefault('display[real]', $cfg->grade_export_displaytype == GRADE_DISPLAY_TYPE_REAL);
        $mform->setDefault('display[percentage]', $cfg->grade_export_displaytype == GRADE_DISPLAY_TYPE_PERCENTAGE);
        $mform->setDefault('display[letter]', $cfg->grade_export_displaytype == GRADE_DISPLAY_TYPE_LETTER);
    }
}
