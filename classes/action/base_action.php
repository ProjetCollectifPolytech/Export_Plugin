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
 * Base action handler class.
 *
 * @package    local_gradefiller
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_gradefiller\action;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/local/gradefiller/lib.php');

use context_module;
use local_gradefiller\manager;
use moodle_url;

/**
 * Abstract base class for action handlers.
 *
 * Provides common functionality for all action handlers including
 * authentication, context setup, capability checks and redirect helpers.
 */
abstract class base_action {

    /** @var int Course module ID */
    protected $cmid;

    /** @var \stdClass Course module object */
    protected $cm;

    /** @var \stdClass Course object */
    protected $course;

    /** @var context_module Module context */
    protected $context;

    /** @var manager Grade filling manager */
    protected $manager;

    /**
     * Constructor.
     *
     * @param int $cmid Course module ID
     */
    public function __construct(int $cmid) {
        global $DB;

        $this->cmid = $cmid;
        $this->cm = get_coursemodule_from_id('', $cmid, 0, false, MUST_EXIST);
        $this->course = $DB->get_record('course', ['id' => $this->cm->course], '*', MUST_EXIST);
        $this->context = context_module::instance($cmid);
        $this->manager = new manager();

        require_login($this->course, false, $this->cm);
        if (!local_gradefiller_user_can_process($this->context)) {
            require_capability('local/gradefiller:process', $this->context);
        }
    }

    /**
     * Execute the action.
     *
     * @return void
     */
    abstract public function execute(): void;

    /**
     * Get redirect URL for this action.
     *
     * @return moodle_url
     */
    protected function get_return_url(): moodle_url {
        return new moodle_url('/local/gradefiller/index.php', ['id' => $this->cmid]);
    }

    /**
     * Require a valid POST request for state-changing actions.
     *
     * @return void
     * @throws \moodle_exception
     */
    protected function require_post_request(): void {
        if (!data_submitted()) {
            throw new \moodle_exception('error_post_required', 'local_gradefiller');
        }

        require_sesskey();
    }
}
