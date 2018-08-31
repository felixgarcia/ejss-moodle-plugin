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
 * Report for a course with ejsS simulations
 *
 * @package    mod_ejsssimulation
 * @copyright  2018 Felix J. Garcia
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(dirname(__FILE__).'/../../config.php');
require(__DIR__. '/report_constants.php');

$active_type = optional_param('active_type', '', PARAM_TEXT);
$courseid = optional_param('course', -1, PARAM_INT);

// Make sure they can even access this course
if (!$course = $DB->get_record('course', array('id' => $courseid))) {
    print_error('invalidcourseid');
}

// Page setup based on the parent web 
if ($active_type == 'report') {
	require_once($CFG->libdir.'/adminlib.php');
	admin_externalpage_setup('reportejssstats', '', null, '', array('pagelayout'=>'report'));
} else {
	require_login($course);
    $coursecontext = context_course::instance($course->id);
    require_capability('moodle/course:manageactivities', $coursecontext);
	$PAGE->set_course($course);
}
	
echo $OUTPUT->header();

// Info about course
echo html_writer::tag('h2', $course->fullname);
echo html_writer::tag('h4', 'Summary');
echo html_writer::tag('p', $course->summary);

echo html_writer::tag('h4', 'EjsS Simulations');
$table = new html_table();
$table->size = array( '10%', '15%', '25%', '20%', '5%', '5%', '5%', '5%', '5%', '5%');
$table->head = array( 'Simulation Logo', 'Activity', 'Simulation Title', 'Authors', 'Views', 'Users', ''); 

$module = $DB->get_record(MODULES_TABLE_NAME, array('name'=>'ejsssimulation'));
$cms = $DB->get_recordset(COURSE_MODULES_TABLE_NAME, array('course'=>$courseid, 'module'=>$module->id));
foreach ($cms as $cm) {
	$context = context_module::instance($cm->id);

	// Get EjsS simulations (see: https://docs.moodle.org/dev/File_API)
	$isEjssSim = false;
	$contents = '';
	$fs = get_file_storage();
	$files = $fs->get_area_files($context->id, 'mod_ejsssimulation', 'content', 0, 'sortorder DESC, id ASC', false);
	foreach ($files as $f) {
		// $f is an instance of stored_file
		if ($f->get_filename() == 'ejss.css' or $f->get_filename() == 'ejsS.v1.min.js' or $f->get_filename() == 'ejsS.v1.max.js')
			$isEjssSim = true;
		elseif (strpos($f->get_filename(),'.ejss') !== false) {
			$contents = $f->get_content();
		}
	}

	if($isEjssSim) {
		$xml = simplexml_load_string($contents);
		
		$logo = $xml->{'Osejs.Information'}->Logo;
		if ($logo and strlen($logo) != 0) {
			$resource = $DB->get_record(EJSSIMULATION_TABLE_NAME, array('id'=>$cm->instance));
			$url = file_encode_url($CFG->wwwroot.'/pluginfile.php/'.$context->id.'/mod_ejsssimulation/content/'.$resource->revision.'/'.$xml->{'Osejs.Information'}->Logo);
			$attr = array(
				'src' => $url,
				'width' => 60,
				'height' => 60,
			);
			$logo_img = html_writer::empty_tag('img', $attr);
		} else {
			$logo_img = html_writer::div('No Logo');
		}			

		$title = $xml->{'Osejs.Information'}->Title;
		if (!$title or strlen($title) == 0) 
			$title = "No Title";
		
		$urlsee = file_encode_url($CFG->wwwroot.'/mod/ejsssimulation/view.php?id='.$cm->id);
		$urlhistory = file_encode_url($CFG->wwwroot . '/mod/ejsssimulation/report_history.php?active_type='.$active_type.'&title='.urlencode($title).'&cm=' . $cm->id . '&cminstance=' . $cm->instance . '&course=' . $courseid);
		$urlsearch = file_encode_url($CFG->wwwroot . '/mod/ejsssimulation/report_search.php?active_type='.$active_type.'&title='.urlencode($title).'&cm=' . $cm->id . '&cminstance=' . $cm->instance . '&course=' . $courseid);
		$urlmonitor = file_encode_url($CFG->wwwroot . '/mod/ejsssimulation/report_monitor.php?active_type='.$active_type.'&title='.urlencode($title).'&cm=' . $cm->id . '&cminstance=' . $cm->instance . '&course=' . $courseid);
		
		$table->data[] = array($logo_img,
			$resource->name,
			$title, 
			html_writer::div($xml->{'Osejs.Information'}->Author),
			$DB->count_records(PLUGIN_VIEWS_TABLE_NAME, array('contextinstanceid'=>$cm->id)),
			$DB->count_records_select(PLUGIN_VIEWS_TABLE_NAME, 'contextinstanceid = ' . $cm->id, null, "COUNT(DISTINCT 'userid')"),
			html_writer::link($urlsee, 'See', array('class' => 'btn btn-secondary')),
			html_writer::link($urlhistory, 'Activity', array('class' => 'btn btn-secondary')),	
			html_writer::link($urlsearch, 'Search', array('class' => 'btn btn-secondary')),	
			html_writer::link($urlmonitor, 'Monitor', array('class' => 'btn btn-secondary')));	
	}
}
echo html_writer::table($table);
$cms->close();	

// Back link
if(active_type == 'report') {
	$url = new moodle_url($CFG->wwwroot . '/mod/ejsssimulation/report_allcourses.php?active_type=report');
	$link = html_writer::link($url, get_string('link_back', 'ejss_simlation'));
	echo html_writer::tag('p', $link, array('align' => 'center'));
}

echo $OUTPUT->footer();

