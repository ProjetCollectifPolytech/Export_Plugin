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
 * Builder for classic Moodle grade export tables.
 *
 * @package    local_gradefiller
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_gradefiller\export;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/grade/export/lib.php');

use coding_exception;
use grade_export;
use graded_users_iterator;
use stdClass;

/**
 * Reuses Moodle's grade export base class to build a matrix of exported values.
 *
 * @package    local_gradefiller
 */
class gradebook_export_builder extends grade_export {
    /** @var string Plugin key used internally by grade_export */
    public $plugin = 'gradefiller';

    /**
     * Constructor.
     *
     * @param \stdClass $course Course record
     * @param int $groupid Current group filter
     * @param \stdClass $formdata Validated form data
     */
    public function __construct(stdClass $course, int $groupid, stdClass $formdata) {
        parent::__construct($course, $groupid, $formdata);

        // Match the one-step export plugins and include custom profile fields.
        $this->usercustomfields = true;
    }

    /**
     * Build the classic Moodle grade export matrix.
     *
     * @return object
     */
    public function build_export_data(): object {
        $headers = [];
        $rows = [];
        $profilefields = \grade_helper::get_user_profile_fields($this->course->id, $this->usercustomfields);
        $timeexported = time();

        foreach ($profilefields as $field) {
            $headers[] = $field->fullname;
        }

        if (!$this->onlyactive) {
            $headers[] = get_string('suspended');
        }

        foreach ($this->columns as $gradeitem) {
            foreach ($this->displaytype as $gradedisplayname => $gradedisplayconst) {
                $headers[] = $this->format_column_name($gradeitem, false, $gradedisplayname);
            }
            if ($this->export_feedback) {
                $headers[] = $this->format_column_name($gradeitem, true);
            }
        }

        $headers[] = get_string('timeexported', 'gradeexport_ods');

        $iterator = new graded_users_iterator($this->course, $this->columns, $this->groupid);
        $iterator->require_active_enrolment($this->onlyactive);
        $iterator->allow_user_custom_fields($this->usercustomfields);
        $iterator->init();

        while ($userdata = $iterator->next_user()) {
            $row = [];
            $user = $userdata->user;

            foreach ($profilefields as $field) {
                $row[] = \grade_helper::get_user_field_value($user, $field);
            }

            if (!$this->onlyactive) {
                $row[] = $user->suspendedenrolment ? get_string('yes') : '';
            }

            foreach ($this->columns as $itemid => $gradeitem) {
                $grade = $userdata->grades[$itemid];

                foreach ($this->displaytype as $gradedisplayconst) {
                    $row[] = $this->format_grade($grade, $gradedisplayconst);
                }

                if ($this->export_feedback) {
                    $row[] = $this->format_feedback($userdata->feedbacks[$itemid], $grade);
                }
            }

            $row[] = $timeexported;
            $rows[] = $row;
        }

        $iterator->close();

        return (object) [
            'headers' => $headers,
            'rows' => $rows,
            'timeexported' => $timeexported,
        ];
    }

    /**
     * Not used by the builder.
     */
    public function print_grades() {
        throw new coding_exception('gradebook_export_builder does not stream files directly.');
    }
}
