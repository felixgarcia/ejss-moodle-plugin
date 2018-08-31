<?php

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
 * Internal lib
 *
 * @package    mod_ejsssimulation
 * @copyright  2018 Felix J. Garcia
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

require_once($CFG->libdir . '/externallib.php');

 // Extend navigation menu of the courses with a link to EjsS Reports
 function ejsssimulation_extend_navigation_course($navigation, $course, $coursecontext) {
    $coursecontext = context_course::instance($course->id);
    if(has_capability('moodle/course:manageactivities', $coursecontext)) {
        // students can not peak here!
		// see https://docs.moodle.org/dev/Tutorial
		$url = new moodle_url('/mod/ejsssimulation/report_course.php?active_type=course&course=' . $course->id);
		$devcoursenode = navigation_node::create('EjsS Analytics', $url, navigation_node::TYPE_COURSE, 'Reports', 'ejsssimulation');
		$devcoursenode->make_active();
		$navigation->add_node($devcoursenode);	
	}
}

// New Resource - EjsS Simulation (based on Resource - see https://github.com/moodle/moodle/blob/master/mod/resource/lib.php)

/**
 * List of features supported in Resource module
 * @param string $feature FEATURE_xx constant for requested feature
 * @return mixed True if module supports feature, false if not, null if doesn't know
 */
function ejsssimulation_supports($feature) {
    switch($feature) {
        case FEATURE_MOD_ARCHETYPE:           return MOD_ARCHETYPE_RESOURCE;
        case FEATURE_GROUPS:                  return false;
        case FEATURE_GROUPINGS:               return false;
        case FEATURE_MOD_INTRO:               return true;
        case FEATURE_COMPLETION_TRACKS_VIEWS: return true;
        case FEATURE_GRADE_HAS_GRADE:         return false;
        case FEATURE_GRADE_OUTCOMES:          return false;
        case FEATURE_BACKUP_MOODLE2:          return true;
        case FEATURE_SHOW_DESCRIPTION:        return true;
        default: return null;
    }
}

/**
 * Returns all other caps used in module
 * @return array
 */
function ejsssimulation_get_extra_capabilities() {
    return array('moodle/site:accessallgroups');
}

/**
 * This function is used by the reset_course_userdata function in moodlelib.
 * @param $data the data submitted from the reset course.
 * @return array status array
 */
function ejsssimulation_reset_userdata($data) {
    // Any changes to the list of dates that needs to be rolled should be same during course restore and course reset.
    // See MDL-9367.
    return array();
}

/**
 * List the actions that correspond to a view of this module.
 * This is used by the participation report.
 *
 * Note: This is not used by new logging system. Event with
 *       crud = 'r' and edulevel = LEVEL_PARTICIPATING will
 *       be considered as view action.
 *
 * @return array
 */
function ejsssimulation_get_view_actions() {
    return array('view','view all');
}

/**
 * List the actions that correspond to a post of this module.
 * This is used by the participation report.
 *
 * Note: This is not used by new logging system. Event with
 *       crud = ('c' || 'u' || 'd') and edulevel = LEVEL_PARTICIPATING
 *       will be considered as post action.
 *
 * @return array
 */
function ejsssimulation_get_post_actions() {
    return array('update', 'add');
}

/**
 * Add resource instance.
 * @param object $data
 * @param object $mform
 * @return int new resource instance id
 */
function ejsssimulation_add_instance($data, $mform) {
    global $CFG, $DB;
    require_once("$CFG->libdir/resourcelib.php");
    require_once("$CFG->dirroot/mod/ejsssimulation/resource_lib.php");
    $cmid = $data->coursemodule;
    $data->timemodified = time();
    $data->id = $DB->insert_record('ejsssimulation', $data);
    // we need to use context now, so we need to make sure all needed info is already in db
    $DB->set_field('course_modules', 'instance', $data->id, array('id'=>$cmid));
    ejsssimulation_set_mainfile($data);
    $completiontimeexpected = !empty($data->completionexpected) ? $data->completionexpected : null;
    \core_completion\api::update_completion_date_event($cmid, 'ejsssimulation', $data->id, $completiontimeexpected);
    return $data->id;
}

/**
 * Update resource instance.
 * @param object $data
 * @param object $mform
 * @return bool true
 */
function ejsssimulation_update_instance($data, $mform) {
    global $CFG, $DB;
    require_once("$CFG->libdir/resourcelib.php");
    $data->timemodified = time();
    $data->id = $data->instance;
    $data->revision++;
    $DB->update_record('ejsssimulation', $data);
    ejsssimulation_set_mainfile($data);
    $completiontimeexpected = !empty($data->completionexpected) ? $data->completionexpected : null;
    \core_completion\api::update_completion_date_event($data->coursemodule, 'ejsssimulation', $data->id, $completiontimeexpected);
    return true;
}

/**
 * Delete resource instance.
 * @param int $id
 * @return bool true
 */
function ejsssimulation_delete_instance($id) {
    global $DB;
    if (!$resource = $DB->get_record('ejsssimulation', array('id'=>$id))) {
        return false;
    }
    $cm = get_coursemodule_from_instance('ejsssimulation', $id);
    \core_completion\api::update_completion_date_event($cm->id, 'ejsssimulation', $id, null);
    // note: all context files are deleted automatically
    $DB->delete_records('ejsssimulation', array('id'=>$resource->id));
    return true;
}

/**
 * Given a course_module object, this function returns any
 * "extra" information that may be needed when printing
 * this activity in a course listing.
 *
 * See {@link get_array_of_activities()} in course/lib.php
 *
 * @param stdClass $coursemodule
 * @return cached_cm_info info
 */
function ejsssimulation_get_coursemodule_info($coursemodule) {
    global $CFG, $DB;
    require_once("$CFG->libdir/filelib.php");
    require_once("$CFG->dirroot/mod/ejsssimulation/resource_lib.php");
    require_once($CFG->libdir.'/completionlib.php');
    $context = context_module::instance($coursemodule->id);
    if (!$resource = $DB->get_record('ejsssimulation', array('id'=>$coursemodule->instance),
            'id, name, revision, intro, introformat')) {
        return NULL;
    }
    $info = new cached_cm_info();
    $info->name = $resource->name;
    if ($coursemodule->showdescription) {
        // Convert intro to html. Do not filter cached version, filters run at display time.
        $info->content = format_module_intro('ejsssimulation', $resource, $coursemodule->id, false);
    }
    // See if there is at least one file.
    $fs = get_file_storage();
    $files = $fs->get_area_files($context->id, 'mod_ejsssimulation', 'content', 0, 'sortorder DESC, id ASC', false, 0, 0, 1);
    if (count($files) >= 1) {
        $mainfile = reset($files);
        $info->icon = file_file_icon($mainfile, 24);
        $resource->mainfile = $mainfile->get_filename();
    }

    return $info;
}

/**
 * Called when viewing course page. Shows extra details after the link if
 * enabled.
 *
 * @param cm_info $cm Course module information
 */
function ejsssimulation_cm_info_view(cm_info $cm) {
    global $CFG;
    require_once($CFG->dirroot . '/mod/ejsssimulation/resource_lib.php');
}

/**
 * Lists all browsable file areas
 *
 * @category files
 * @param stdClass $course course object
 * @param stdClass $cm course module object
 * @param stdClass $context context object
 * @return array
 */
function ejsssimulation_get_file_areas($course, $cm, $context) {
    $areas = array();
    $areas['content'] = get_string('resourcecontent', 'ejsssimulation');
    return $areas;
}

/**
 * File browsing support for resource module content area.
 *
 * @category files
 * @param stdClass $browser file browser instance
 * @param stdClass $areas file areas
 * @param stdClass $course course object
 * @param stdClass $cm course module object
 * @param stdClass $context context object
 * @param string $filearea file area
 * @param int $itemid item ID
 * @param string $filepath file path
 * @param string $filename file name
 * @return file_info instance or null if not found
 */
function ejsssimulation_get_file_info($browser, $areas, $course, $cm, $context, $filearea, $itemid, $filepath, $filename) {
    global $CFG;
    if (!has_capability('moodle/course:managefiles', $context)) {
        // students can not peak here!
        return null;
    }
    $fs = get_file_storage();
    if ($filearea === 'content') {
        $filepath = is_null($filepath) ? '/' : $filepath;
        $filename = is_null($filename) ? '.' : $filename;
        $urlbase = $CFG->wwwroot.'/pluginfile.php';
        if (!$storedfile = $fs->get_file($context->id, 'mod_ejsssimulation', 'content', 0, $filepath, $filename)) {
            if ($filepath === '/' and $filename === '.') {
                $storedfile = new virtual_root_file($context->id, 'mod_ejsssimulation', 'content', 0);
            } else {
                // not found
                return null;
            }
        }
        require_once("$CFG->dirroot/mod/ejsssimulation/resource_lib.php");
        return new ejsssimulation_content_file_info($browser, $context, $storedfile, $urlbase, $areas[$filearea], true, true, true, false);
    }
    // note: ejsssimulation_intro handled in file_browser automatically
    return null;
}

/**
 * Serves the resource files.
 *
 * @category files
 * @param stdClass $course course object
 * @param stdClass $cm course module object
 * @param stdClass $context context object
 * @param string $filearea file area
 * @param array $args extra arguments
 * @param bool $forcedownload whether or not force download
 * @param array $options additional options affecting the file serving
 * @return bool false if file not found, does not return if found - just send the file
 */
function ejsssimulation_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options=array()) {
    global $CFG, $DB;
    require_once("$CFG->libdir/resourcelib.php");
    if ($context->contextlevel != CONTEXT_MODULE) {
        return false;
    }
    require_course_login($course, true, $cm);
    if (!has_capability('mod/ejsssimulation:view', $context)) {
        return false;
    }
    if ($filearea !== 'content') {
        // intro is handled automatically in pluginfile.php
        return false;
    }
    array_shift($args); // ignore revision - designed to prevent caching problems only
    $fs = get_file_storage();
    $relativepath = implode('/', $args);
    $fullpath = rtrim("/$context->id/mod_ejsssimulation/$filearea/0/$relativepath", '/');
    do {
        if (!$file = $fs->get_file_by_hash(sha1($fullpath))) {
            if ($fs->get_file_by_hash(sha1("$fullpath/."))) {
                if ($file = $fs->get_file_by_hash(sha1("$fullpath/index.htm"))) {
                    break;
                }
                if ($file = $fs->get_file_by_hash(sha1("$fullpath/index.html"))) {
                    break;
                }
                if ($file = $fs->get_file_by_hash(sha1("$fullpath/Default.htm"))) {
                    break;
                }
            }
        }
    } while (false);
    // finally send the file
    send_stored_file($file, null, 0, $forcedownload, $options);
}

/**
 * Return a list of page types
 * @param string $pagetype current page type
 * @param stdClass $parentcontext Block's parent context
 * @param stdClass $currentcontext Current context of block
 */
function ejsssimulation_page_type_list($pagetype, $parentcontext, $currentcontext) {
    $module_pagetype = array('mod-ejsssimulation-*'=>get_string('page-mod-ejsssimulation-x', 'ejsssimulation'));
    return $module_pagetype;
}

/**
 * Export file resource contents
 *
 * @return array of file content
 */
function ejsssimulation_export_contents($cm, $baseurl) {
    global $CFG, $DB;

    $contents = array();
    $context = context_module::instance($cm->id);
    $resource = $DB->get_record('ejsssimulation', array('id'=>$cm->instance), '*', MUST_EXIST);
    $fs = get_file_storage();
    $files = $fs->get_area_files($context->id, 'mod_ejsssimulation', 'content', 0, 'sortorder DESC, id ASC', false);
    foreach ($files as $fileinfo) {
        $file = array();
        $file['type'] = 'file';
        $file['filename']     = $fileinfo->get_filename();
        $file['filepath']     = $fileinfo->get_filepath();
        $file['filesize']     = $fileinfo->get_filesize();
        $file['fileurl']      = file_encode_url("$CFG->wwwroot/" . $baseurl, '/'.$context->id.'/mod_ejsssimulation/content/'.$resource->revision.$fileinfo->get_filepath().$fileinfo->get_filename(), true);
        $file['timecreated']  = $fileinfo->get_timecreated();
        $file['timemodified'] = $fileinfo->get_timemodified();
        $file['sortorder']    = $fileinfo->get_sortorder();
        $file['userid']       = $fileinfo->get_userid();
        $file['author']       = $fileinfo->get_author();
        $file['license']      = $fileinfo->get_license();
        $file['mimetype']     = $fileinfo->get_mimetype();
        $file['isexternalfile'] = $fileinfo->is_external_file();
        if ($file['isexternalfile']) {
            $file['repositorytype'] = $fileinfo->get_repository_type();
        }
        $contents[] = $file;
    }
    return $contents;
}

/**
 * Register the ability to handle drag and drop file uploads
 * @return array containing details of the files / types the mod can handle
 */
function ejsssimulation_dndupload_register() {
    return array('files' => array(
                     array('extension' => '*', 'message' => get_string('dnduploadresource', 'ejsssimulation'))
                 ));
}

/**
 * Handle a file that has been uploaded
 * @param object $uploadinfo details of the file / content that has been uploaded
 * @return int instance id of the newly created mod
 */
function ejsssimulation_dndupload_handle($uploadinfo) {
    // Gather the required info.
    $data = new stdClass();
    $data->course = $uploadinfo->course->id;
    $data->name = $uploadinfo->displayname;
    $data->intro = '';
    $data->introformat = FORMAT_HTML;
    $data->coursemodule = $uploadinfo->coursemodule;
    $data->files = $uploadinfo->draftitemid;
    return ejsssimulation_add_instance($data, null);
}

/**
 * Mark the activity completed (if required) and trigger the course_module_viewed event.
 *
 * @param  stdClass $resource   resource object
 * @param  stdClass $course     course object
 * @param  stdClass $cm         course module object
 * @param  stdClass $context    context object
 * @since Moodle 3.0
 */
function ejsssimulation_view($resource, $course, $cm, $context) {
	global $DB, $USER;
	
    // Trigger course_module_viewed event.
    $params = array(
        'context' => $context,
        'objectid' => $resource->id
    );
    $event = \mod_ejsssimulation\event\course_module_viewed::create($params);
    $event->add_record_snapshot('course_modules', $cm);
    $event->add_record_snapshot('course', $course);
    $event->add_record_snapshot('ejsssimulation', $resource);
    $event->trigger();
    // Completion.
    $completion = new completion_info($course);
    $completion->set_module_viewed($cm);

	// Register in DB
	$record = new stdClass();
	$record->userid = $USER->id;
	$record->contextinstanceid = $cm->id;
	$record->objecttable = 'ejsssimulation';
	$record->timestamp =  time();
	$view_id = $DB->insert_record(PLUGIN_VIEWS_TABLE_NAME, $record);		

	// Create params to EjsS Simulations
	$service = $DB->get_record('external_services', array('shortname' => 'EjssStats', 'enabled' => 1));
	$token = external_generate_token_for_current_user($service);
	$url = file_encode_url($CFG->wwwroot.'/moodle/webservice/rest/server.php');
	$wsfunction = 'report_get_interactions';
	$params = array('userid'=>$USER->id, 'viewid'=>$view_id, 'wstoken'=>$token->token, 'url'=>urlencode($url), 'wsfunction'=>$wsfunction);
	
	return $params;
}

/**
 * Check if the module has any update that affects the current user since a given time.
 *
 * @param  cm_info $cm course module data
 * @param  int $from the time to check updates from
 * @param  array $filter  if we need to check only specific updates
 * @return stdClass an object with the different type of areas indicating if they were updated or not
 * @since Moodle 3.2
 */
function ejsssimulation_check_updates_since(cm_info $cm, $from, $filter = array()) {
    $updates = course_check_module_updates_since($cm, $from, array('content'), $filter);
    return $updates;
}

/**
 * This function receives a calendar event and returns the action associated with it, or null if there is none.
 *
 * This is used by block_myoverview in order to display the event appropriately. If null is returned then the event
 * is not displayed on the block.
 *
 * @param calendar_event $event
 * @param \core_calendar\action_factory $factory
 * @return \core_calendar\local\event\entities\action_interface|null
 */
function mod_ejsssimulation_core_calendar_provide_event_action(calendar_event $event,
                                                      \core_calendar\action_factory $factory) {
    $cm = get_fast_modinfo($event->courseid)->instances['ejsssimulation'][$event->instance];
    $completion = new \completion_info($cm->get_course());
    $completiondata = $completion->get_data($cm, false);
    if ($completiondata->completionstate != COMPLETION_INCOMPLETE) {
        return null;
    }
    return $factory->create_instance(
        get_string('view'),
        new \moodle_url('/mod/ejsssimulation/view.php', ['id' => $cm->id]),
        1,
        true
    );
}