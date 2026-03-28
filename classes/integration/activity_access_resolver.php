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

namespace local_gradefiller\integration;

defined('MOODLE_INTERNAL') || die();

use cm_info;
use context;
use context_course;
use context_module;
use grade_item;
use local_gradefiller\manager;
use local_gradefiller\manager_factory;
use moodle_url;
use pix_icon;
use stdClass;

/**
 * Resolves activity-level access metadata for Grade Filler.
 *
 * @package    local_gradefiller
 */
class activity_access_resolver {
    /** @var manager */
    private manager $manager;

    /**
     * Constructor.
     *
     * @param manager|null $manager
     */
    public function __construct(?manager $manager = null) {
        $this->manager = $manager ?? manager_factory::create_default();
    }

    /**
     * Check whether the current user can view Grade Filler.
     *
     * @param context $context
     * @return bool
     */
    public function user_can_view(context $context): bool {
        return has_capability('local/gradefiller:view', $context)
            || has_capability('local/gradefiller:use', $context);
    }

    /**
     * Check whether the current user can process files in Grade Filler.
     *
     * @param context $context
     * @return bool
     */
    public function user_can_process(context $context): bool {
        return has_capability('local/gradefiller:process', $context)
            || has_capability('local/gradefiller:use', $context);
    }

    /**
     * Resolve activity access metadata for Grade Filler links and buttons.
     *
     * @param stdClass|cm_info $cm
     * @param context_course $coursecontext
     * @return array|null
     */
    public function resolve($cm, context_course $coursecontext): ?array {
        $modulecontext = context_module::instance($cm->id);
        if (!$this->user_can_view($modulecontext)) {
            return null;
        }

        $gradeitem = grade_item::fetch([
            'itemtype' => 'mod',
            'itemmodule' => $cm->modname,
            'iteminstance' => $cm->instance,
            'courseid' => $coursecontext->instanceid,
        ]);
        $driver = $this->manager->get_driver_for_cm($cm);

        if (!$gradeitem && $driver === null) {
            return null;
        }

        return [
            'label' => get_string('fill_grades', 'local_gradefiller'),
            'url' => new moodle_url('/local/gradefiller/index.php', ['id' => $cm->id]),
            'nodekey' => 'gradefiller_fill',
            'icon' => new pix_icon('i/grades', ''),
            'can_process' => $this->user_can_process($modulecontext),
            'has_grade_item' => (bool)$gradeitem,
            'supports_anonymous' => ($driver !== null),
            'driver_name' => $driver ? $driver->get_name() : '',
        ];
    }
}
