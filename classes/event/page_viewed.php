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

namespace local_gradefiller\event;

defined('MOODLE_INTERNAL') || die();

/**
 * Event fired when the Grade Filler page is viewed.
 *
 * @package    local_gradefiller
 */
class page_viewed extends \core\event\base {
    /**
     * Initialise event metadata.
     *
     * @return void
     */
    protected function init() {
        $this->data['crud'] = 'r';
        $this->data['edulevel'] = self::LEVEL_TEACHING;
        $this->data['objecttable'] = 'course_modules';
    }

    /**
     * Event name.
     *
     * @return string
     */
    public static function get_name(): string {
        return get_string('event_page_viewed', 'local_gradefiller');
    }

    /**
     * Event description.
     *
     * @return string
     */
    public function get_description(): string {
        return "The user with id '{$this->userid}' viewed Grade Filler for course module '{$this->objectid}' " .
            "in course '{$this->courseid}'.";
    }

    /**
     * Event URL.
     *
     * @return \moodle_url
     */
    public function get_url(): \moodle_url {
        return new \moodle_url('/local/gradefiller/index.php', ['id' => $this->objectid]);
    }
}
