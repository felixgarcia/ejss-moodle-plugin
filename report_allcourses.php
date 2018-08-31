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
 * Report all courses with ejsS simulations
 *
 * @package    mod_ejsssimulation
 * @copyright  2018 Felix J. Garcia
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(dirname(__FILE__).'/../../config.php');
require_once($CFG->libdir.'/adminlib.php');
require(__DIR__. '/report_constants.php');

admin_externalpage_setup('reportejssstats', '', null, '', array('pagelayout'=>'report'));

echo $OUTPUT->header();

// Table with course info		
$table = new html_table();
$table->size = array( '55%', '15%', '15%', '15%');
$table->head = array(get_string('lb_courses_with_ejss', 'ejsssimulation'), 
					get_string('lb_sims_amount', 'ejsssimulation'),
					get_string('lb_views_amount', 'ejsssimulation'),
					get_string('lb_users_amount', 'ejsssimulation'),);

$module = $DB->get_record(MODULES_TABLE_NAME, array('name'=>'ejsssimulation'));

$courses_count = 0;
$courses_with_ejss_count = 0;
$courses = $DB->get_recordset('course');
foreach ($courses as $course) {
	$count = 0;
	$views = 0;
	$cmids = [];
	$cms = $DB->get_recordset(COURSE_MODULES_TABLE_NAME, array('course'=>$course->id, 'module'=>$module->id));
	
	// Get EjsS simulations and their views
	foreach ($cms as $cm) {
		$count = $count + 1;
		$views = $views + $DB->count_records(PLUGIN_VIEWS_TABLE_NAME, array('contextinstanceid'=>$cm->id));
		$cmids[] = $cm->id;
	}

	if ($count > 0) {
		// Show info in table
		$users = $DB->count_records_select(PLUGIN_VIEWS_TABLE_NAME, 'contextinstanceid IN (' . implode(',', $cmids) . ')', null, "COUNT(DISTINCT 'userid')");
		$link = '<a href=' . $CFG->wwwroot . '/mod/ejsssimulation/report_course.php?active_type=report&course=' . $course->id . '>' . $course->shortname . '</a>';
		$table->data[] = array($link, $count, $views, $users);
		$courses_with_ejss_count = $courses_with_ejss_count + 1;
	}
	
	$courses_count = $courses_count + 1;
	
	$cms->close();	
}
$courses->close();

if ($courses_with_ejss_count == 0) {
	// No ejsS simulations in courses
	echo html_writer::div('No EjsS simulations in courses.');
} else {
	echo html_writer::table($table);

	// Chart with general info
	$cat_array = array();
	$created_courses_array = array();
	$used_courses_array = array();
	$percentage_used_courses_array = array();

	$cat_array[] = get_string('lb_general_chart_bar_label', 'ejsssimulation');
	$created_courses_array[] = $courses_count-1;
	$used_courses_array[] = $courses_with_ejss_count;

	if (class_exists('core\chart_bar')) {
		$chart_stacked = new core\chart_bar();
		
		$created_courses_serie = new core\chart_series(get_string('lb_courses_created_amount', 'ejsssimulation'), $created_courses_array);
		$used_courses_serie = new core\chart_series(get_string('lb_used_courses', 'ejsssimulation'), $used_courses_array);
		
		$chart_stacked->add_series($created_courses_serie);
		$chart_stacked->add_series($used_courses_serie);
		$chart_stacked->set_labels($cat_array);
		
		echo $OUTPUT->render_chart($chart_stacked, false);
	}

	// $url_csv = new moodle_url($CFG->wwwroot . '/report/coursestats/csvgen.php');
	// $link_csv = html_writer::link($url_csv, get_string('link_csv', 'ejsssimulation'));
	// echo '<p align="center">' . $link_csv . '</p>';
}

echo $OUTPUT->footer();

