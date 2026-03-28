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
 * Controller for the activity-based Grade Filler upload page.
 *
 * @package    local_gradefiller
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_gradefiller\controller;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/local/gradefiller/lib.php');

use core\notification;
use core\output\notification as output_notification;
use Exception;
use local_gradefiller\action\process_upload;
use local_gradefiller\error\error_message_resolver;
use local_gradefiller\event\page_viewed;
use local_gradefiller\manager;
use local_gradefiller\manager_factory;
use local_gradefiller\page\upload_page;
use moodle_exception;
use moodle_url;

/**
 * Coordinates request handling and rendering for the upload page.
 *
 * @package    local_gradefiller
 */
class upload_controller {
    /** @var manager */
    private manager $manager;

    /** @var upload_page */
    private upload_page $pagebuilder;

    /**
     * Constructor.
     *
     * @param manager|null $manager
     * @param upload_page|null $pagebuilder
     */
    public function __construct(?manager $manager = null, ?upload_page $pagebuilder = null) {
        $this->manager = $manager ?? manager_factory::create_default();
        $this->pagebuilder = $pagebuilder ?? new upload_page();
    }

    /**
     * Handle the request and render the upload page.
     *
     * @param int $cmid
     * @param string $action
     * @return void
     */
    public function handle(int $cmid, string $action): void {
        global $DB, $PAGE, $OUTPUT, $SESSION, $CFG;

        $cm = get_coursemodule_from_id('', $cmid, 0, false, MUST_EXIST);
        $course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
        $context = \context_module::instance($cm->id);
        $coursecontext = \context_course::instance($course->id);

        require_login($course, false, $cm);
        if (!local_gradefiller_user_can_view($context)) {
            require_capability('local/gradefiller:view', $context);
        }

        $PAGE->set_url('/local/gradefiller/index.php', ['id' => $cmid]);
        $PAGE->set_context($context);
        $PAGE->set_pagelayout('incourse');
        $PAGE->set_title(get_string('page_title', 'local_gradefiller'));
        $PAGE->set_heading($course->fullname);
        $PAGE->requires->css('/local/gradefiller/styles.css');

        $accessdata = local_gradefiller_get_activity_access_data($cm, $coursecontext);
        if ($accessdata === null) {
            throw new moodle_exception('error_activity_unsupported', 'local_gradefiller');
        }

        $this->dispatch_action($cmid, $action);
        $this->display_flash_notifications($SESSION);

        $canprocess = local_gradefiller_user_can_process($context);
        $formats = $this->manager->get_available_formats();
        $driver = $this->manager->get_driver_for_cm($cm);

        page_viewed::create([
            'objectid' => $cm->id,
            'courseid' => $course->id,
            'context' => $context,
            'other' => [
                'supports_anonymous' => $driver !== null ? 1 : 0,
                'can_process' => $canprocess ? 1 : 0,
            ],
        ])->trigger();

        $templatedata = $this->pagebuilder->build($cmid, $cm, $formats, $canprocess, $driver, $CFG->wwwroot);

        echo $OUTPUT->header();
        echo $OUTPUT->heading(get_string('page_title', 'local_gradefiller'));
        echo $OUTPUT->render_from_template('local_gradefiller/upload_form', $templatedata);
        echo $OUTPUT->footer();
    }

    /**
     * Dispatch the upload action.
     *
     * @param int $cmid
     * @param string $action
     * @return void
     */
    private function dispatch_action(int $cmid, string $action): void {
        if ($action === '') {
            return;
        }

        if ($action !== 'process_upload') {
            redirect(
                new moodle_url('/local/gradefiller/index.php', ['id' => $cmid]),
                get_string('error_invalid_action', 'local_gradefiller'),
                null,
                output_notification::NOTIFY_ERROR
            );
        }

        try {
            (new process_upload($cmid))->execute();
            redirect(new moodle_url('/local/gradefiller/index.php', ['id' => $cmid]));
        } catch (Exception $e) {
            redirect(
                new moodle_url('/local/gradefiller/index.php', ['id' => $cmid]),
                error_message_resolver::to_user_message($e),
                null,
                output_notification::NOTIFY_ERROR
            );
        }
    }

    /**
     * Display any flash notification stored in the Moodle session.
     *
     * @param \stdClass $session
     * @return void
     */
    private function display_flash_notifications(\stdClass $session): void {
        if (isset($session->gradefiller_success)) {
            notification::success($session->gradefiller_success);
            unset($session->gradefiller_success);
        }
        if (isset($session->gradefiller_error)) {
            notification::error($session->gradefiller_error);
            unset($session->gradefiller_error);
        }
    }
}
