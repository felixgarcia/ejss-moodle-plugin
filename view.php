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
 * Resource view
 *
 * @package    mod_ejsssimulation
 * @copyright  2018 Felix J. Garcia
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require('../../config.php');
require_once($CFG->dirroot.'/mod/ejsssimulation/lib.php');
require_once($CFG->dirroot.'/mod/ejsssimulation/resource_lib.php');
require_once($CFG->libdir.'/completionlib.php');

$id       = optional_param('id', 0, PARAM_INT); // Course Module ID
$r        = optional_param('r', 0, PARAM_INT);  // Resource instance ID
$redirect = optional_param('redirect', 0, PARAM_BOOL);
$forceview = optional_param('forceview', 0, PARAM_BOOL);

if ($r) {
    if (!$resource = $DB->get_record('ejsssimulation', array('id'=>$r))) {
        print_error('invalidaccessparameter');
    }
    $cm = get_coursemodule_from_instance('ejsssimulation', $resource->id, $resource->course, false, MUST_EXIST);

} else {
    if (!$cm = get_coursemodule_from_id('ejsssimulation', $id)) {
        print_error('invalidcoursemodule');
    }
    $resource = $DB->get_record('ejsssimulation', array('id'=>$cm->instance), '*', MUST_EXIST);
}

$course = $DB->get_record('course', array('id'=>$cm->course), '*', MUST_EXIST);

require_course_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/ejsssimulation:view', $context);

// Completion and trigger events.
$params = ejsssimulation_view($resource, $course, $cm, $context);

$PAGE->set_url('/mod/ejsssimulation/view.php', array('id' => $cm->id));

$fs = get_file_storage();
$files = $fs->get_area_files($context->id, 'mod_ejsssimulation', 'content', 0, 'sortorder DESC, id ASC', false); // TODO: this is not very efficient!!
if (count($files) < 1) {
    ejsssimulation_print_filenotfound($resource, $cm, $course);
    die;
} else {
    $file = reset($files);
    unset($files);
}

$resource->mainfile = $file->get_filename();
ejsssimulation_display_embed($resource, $cm, $course, $file, $params);

