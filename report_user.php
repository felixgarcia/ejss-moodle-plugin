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
 * User report of ejsS simulation
 *
 * @package    mod_ejsssimulation
 * @copyright  2018 Felix J. Garcia
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(dirname(__FILE__).'/../../config.php');
require(__DIR__. '/report_constants.php');
require(__DIR__. '/report_lib.php');

$active_type = optional_param('active_type', '', PARAM_TEXT);
$title =  optional_param('title', '', PARAM_TEXT);
$userid = optional_param('user', -1, PARAM_INT);
$cmid = optional_param('cm', -1, PARAM_INT);
$cminstance = optional_param('cminstance', -1, PARAM_INT);
$courseid = optional_param('course', -1, PARAM_INT);        

$datechart = optional_param('datechart', '', PARAM_TEXT);
$ignorezero = optional_param('ignorezero', 0, PARAM_INT);

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

// Info about simulation & user
$user = $DB->get_record(USER_TABLE_NAME, array('id'=>$userid));
echo html_writer::tag('h2', 'Info about ' . $user->firstname . ' ' . $user->lastname . ' using ' . $title);

// Show views
echo html_writer::tag('h4', 'Views');

// Selectors
$urlhistory = file_encode_url($CFG->wwwroot . '/mod/ejsssimulation/report_user.php');
echo html_writer::start_tag('form', array('action' => $urlhistory));
if ($ignorezero)
	echo html_writer::tag('input', 'Ignore views with 0 actions &emsp;', array('type'=>'checkbox', 'name'=>'ignorezero', 'checked'=>'1','value'=>'1'));
else
	echo html_writer::tag('input', 'Ignore views with 0 actions &emsp;', array('type'=>'checkbox', 'name'=>'ignorezero', 'value'=>'1'));

// Set month and year to date picker
if(strlen($datechart) > 0) {
	// format year-month
	$year = substr($datechart,0,4);
	$month = substr($datechart,5,2);
} else {
	// current year and month
	$year = date('Y');
	$month = date('m');
	$datechart = $year.'-'.$month;
}

echo html_writer::tag('input', '', array('type'=>'month', 'name'=>'datechart', 'value'=>$datechart));
echo html_writer::tag('button', 'Update', array('type'=>'submit'));
echo html_writer::tag('input', '', array('type'=>'hidden', 'name'=>'active_type', 'value'=>$active_type));
echo html_writer::tag('input', '', array('type'=>'hidden', 'name'=>'title', 'value'=>$title));
echo html_writer::tag('input', '', array('type'=>'hidden', 'name'=>'user', 'value'=>$userid));
echo html_writer::tag('input', '', array('type'=>'hidden', 'name'=>'cm', 'value'=>$cmid));
echo html_writer::tag('input', '', array('type'=>'hidden', 'name'=>'cminstance', 'value'=>$cminstance));
echo html_writer::tag('input', '', array('type'=>'hidden', 'name'=>'course', 'value'=>$courseid));
echo html_writer::end_tag('form');

// Calculate values based on selectors
$table = new html_table();
$table->size = array( '10%', '50%', '20%', '20%', '10%');
$table->head = array( 'Num.', 'Date', 'Duration', 'Actions (property changes or events)'); 

$views = $DB->get_recordset_select(PLUGIN_VIEWS_TABLE_NAME, 'contextinstanceid = ' . $cmid . ' AND userid = ' . $userid . 
	' AND MONTH(FROM_UNIXTIME(timestamp)) = ' . +$month . ' AND YEAR(FROM_UNIXTIME(timestamp)) = ' . +$year, null, 'timestamp DESC', 'id, timestamp, DAY(FROM_UNIXTIME(timestamp)) as day');
$count = 0;
foreach ($views as $view) {
	$count++;

	$recordset = $DB->get_recordset_select(PLUGIN_USERDATA_TABLE_NAME, 'userid = ' . $userid . ' AND viewid = ' . $view->id . ' GROUP BY viewid', null, '', 
				"MIN(timestamp) AS mn, MAX(timestamp) AS mx, SUM(actions) AS sm");
	if (!$recordset->valid()) {
		if (!$ignorezero) {
			$table->data[] = array(
					$count,
					userdate($view->timestamp),
					'-',
					'0',
					'-');	
		}
	} else {
		$userdata = $recordset->current();
		$urlview = file_encode_url($CFG->wwwroot . '/mod/ejsssimulation/report_actions.php?active_type='.$active_type.'&course='.$courseid .
			'&title=' . urlencode($title) . '&user=' . $userid . '&cm=' . $cmid . '&cminstance=' . $cminstance . 
			'&viewid=' . $view->id . '&name=' . urlencode($user->firstname . ' ' . $user->lastname) . '&min=' . $view->timestamp);
		$table->data[] = array(
				$count,
				userdate($view->timestamp),
				mod_ejsssimulation_mediaTimeDeFormater(($userdata->mx - $view->timestamp)),
				$userdata->sm,
				html_writer::link($urlview, 'Actions', array('class' => 'btn btn-secondary')));
	}
}
$views->close();	

echo html_writer::start_tag('div', array('style'=>"width: 100%"));
echo html_writer::start_tag('div', array('style'=>"float: left; width: 50%"));
$chart = new \core\chart_bar();
$chart->set_title('Actions in each view');
$chart->add_series(new \core\chart_series('', array_column($table->data,3)));
$chart->set_labels(array_column($table->data,0));
echo $OUTPUT->render($chart,false);
echo html_writer::end_tag('div');
echo html_writer::start_tag('div', array('style'=>"float: left; width: 50%"));
$chart = new \core\chart_bar();
$chart->set_title('Minutes of each view');
$seconds = [];
foreach(array_column($table->data,2) as $formated)
	$seconds[] = mod_ejsssimulation_minutes($formated);
$chart->add_series(new \core\chart_series('', $seconds));
$chart->set_labels(array_column($table->data,0));
echo $OUTPUT->render($chart,false);
echo html_writer::end_tag('div');
echo html_writer::end_tag('div');

echo html_writer::table($table);	

// Back link
$url = new moodle_url($CFG->wwwroot . '/mod/ejsssimulation/report_history.php?active_type='.$active_type.'&title='.urlencode($title).'&cm=' . $cmid . '&cminstance=' . $cminstance . '&course=' . $courseid);
$link = html_writer::link($url, get_string('link_back', 'ejsssimulation'));
echo html_writer::tag('p', $link, array('align' => 'center'));

echo $OUTPUT->footer();



