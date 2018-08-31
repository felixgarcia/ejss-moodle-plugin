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
 * Lib for plugin
 *
 * @package    mod_ejsssimulation
 * @copyright  2018 Felix J. Garcia
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

require_once("$CFG->libdir/filelib.php");
require_once("$CFG->libdir/resourcelib.php");
require_once("$CFG->dirroot/mod/resource/lib.php");

/**
 * Display embedded resource file.
 * @param object $resource
 * @param object $cm
 * @param object $course
 * @param stored_file $file main file
 * @return does not return
 */
function ejsssimulation_display_embed($resource, $cm, $course, $file, $params) {
    global $CFG, $PAGE, $OUTPUT;

    $context = context_module::instance($cm->id);
    $path = '/'.$context->id.'/mod_ejsssimulation/content/'.$resource->revision.$file->get_filepath().$file->get_filename().'?wstoken='.
		$params['wstoken'].'&user_id='.$params['userid'].'&view_id='.$params['viewid'].'&url='.$params['url'].'&wsfunction='.$params['wsfunction'];
    $fullurl = file_encode_url($CFG->wwwroot.'/pluginfile.php', $path, false);
    $moodleurl = new moodle_url('/pluginfile.php' . $path);

    $mimetype = $file->get_mimetype();
    $title    = $resource->name;

    $extension = resourcelib_get_extension($file->get_filename());

    $mediamanager = core_media_manager::instance($PAGE);
    $embedoptions = array(
        core_media_manager::OPTION_TRUSTED => true,
        core_media_manager::OPTION_BLOCK => true,
    );

	// We need a way to discover if we are loading remote docs inside an iframe.
	$moodleurl->param('embed', 1);

	// anything else - just try object tag enlarged as much as possible
	$code = resourcelib_embed_general($moodleurl, $title, $clicktoopen, $mimetype);

    ejsssimulation_print_header($resource, $cm, $course);
    ejsssimulation_print_heading($resource, $cm, $course);

    echo $code;

	echo $resource->intro;
	
    echo $OUTPUT->footer();
    die;
}

/**
 * Print resource header.
 * @param object $resource
 * @param object $cm
 * @param object $course
 * @return void
 */
function ejsssimulation_print_header($resource, $cm, $course) {
    global $PAGE, $OUTPUT;

    $PAGE->set_title($course->shortname.': '.$resource->name);
    $PAGE->set_heading($course->fullname);
    $PAGE->set_activity_record($resource);
    echo $OUTPUT->header();
}

/**
 * Print resource heading.
 * @param object $resource
 * @param object $cm
 * @param object $course
 * @param bool $notused This variable is no longer used
 * @return void
 */
function ejsssimulation_print_heading($resource, $cm, $course, $notused = false) {
    global $OUTPUT;
    echo $OUTPUT->heading(format_string($resource->name), 2);
}

/**
 * Print warning that file can not be found.
 * @param object $resource
 * @param object $cm
 * @param object $course
 * @return void, does not return
 */
function ejsssimulation_print_filenotfound($resource, $cm, $course) {
    global $DB, $OUTPUT;

    ejsssimulation_print_header($resource, $cm, $course);
    ejsssimulation_print_heading($resource, $cm, $course);
	echo $OUTPUT->notification(get_string('filenotfound', 'ejsssimulation'));
    echo $OUTPUT->footer();
    die;
}

/**
 * File browsing support class
 */
class ejsssimulation_content_file_info extends file_info_stored {
    public function get_parent() {
        if ($this->lf->get_filepath() === '/' and $this->lf->get_filename() === '.') {
            return $this->browser->get_file_info($this->context);
        }
        return parent::get_parent();
    }
    public function get_visible_name() {
        if ($this->lf->get_filepath() === '/' and $this->lf->get_filename() === '.') {
            return $this->topvisiblename;
        }
        return parent::get_visible_name();
    }
}

function ejsssimulation_set_mainfile($data) {
    global $DB;
    $fs = get_file_storage();
    $cmid = $data->coursemodule;
    $draftitemid = $data->files;

    $context = context_module::instance($cmid);
    if ($draftitemid) {
        $options = array('subdirs' => true, 'embed' => true);
        file_save_draft_area_files($draftitemid, $context->id, 'mod_ejsssimulation', 'content', 0, $options);
    }
    $files = $fs->get_area_files($context->id, 'mod_ejsssimulation', 'content', 0, 'sortorder', false);
    if (count($files) == 1) {
        // only one file attached, set it as main file automatically
        $file = reset($files);
        file_set_sortorder($context->id, 'mod_ejsssimulation', 'content', 0, $file->get_filepath(), $file->get_filename(), 1);
    }
}