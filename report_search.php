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
 * view report of ejsS simulation
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
$cmid = optional_param('cm', -1, PARAM_INT);
$cminstance = optional_param('cminstance', -1, PARAM_INT);
$courseid = optional_param('course', -1, PARAM_INT);        

$elementsel = optional_param('elementsel', 'Any', PARAM_TEXT);        
$actionsel = optional_param('actionsel', 'Any', PARAM_TEXT);        
$datasel = optional_param('datasel', '', PARAM_TEXT);        
$datechart = optional_param('datechart', '', PARAM_TEXT);

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

// Info about view
echo html_writer::tag('h2', 'Actions for ' . $username . ' in ' . $title);

// Selectors
$urlsearch = file_encode_url($CFG->wwwroot . '/mod/ejsssimulation/report_search.php');
echo html_writer::start_tag('form', array('action' => $urlsearch));
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
echo html_writer::tag('span', '&nbspElement:');
echo html_writer::tag('select','', array('type'=>'select', 'name'=>'elementsel', 'id'=>'elementsel', 'onchange'=>'selectorchange()'));
echo html_writer::tag('span', '&nbspProperty/Event:');
echo html_writer::tag('select','', array('type'=>'select', 'name'=>'actionsel', 'id'=>'actionsel'));
echo html_writer::tag('span', '&nbspData:');
echo html_writer::tag('input','', array('type'=>'text', 'name'=>'datasel', 'value'=>$datasel));
echo html_writer::tag('span', '&nbsp&nbsp');
echo html_writer::tag('button', 'Update', array('type'=>'submit'));
echo html_writer::tag('input', '', array('type'=>'hidden', 'name'=>'active_type', 'value'=>$active_type));
echo html_writer::tag('input', '', array('type'=>'hidden', 'name'=>'title', 'value'=>$title));
echo html_writer::tag('input', '', array('type'=>'hidden', 'name'=>'cm', 'value'=>$cmid));
echo html_writer::tag('input', '', array('type'=>'hidden', 'name'=>'cminstance', 'value'=>$cminstance));
echo html_writer::tag('input', '', array('type'=>'hidden', 'name'=>'course', 'value'=>$courseid));
echo html_writer::end_tag('form');

// Javascript selector
$elements = mod_ejsssimulation_elements($cmid);
$elejson = json_encode($elements['elements']);
$evejson = json_encode($elements['events']);
$propjson = json_encode($elements['properties']);
echo html_writer::tag('script', "
	var selectorelejson;
	var selectoractjson;
	var selectorpropjson;
	window.addEventListener('load', function(event) { 
		selectorelejson = {$elejson};
		selectoractjson = {$evejson};
		selectorpropjson = {$propjson};
		
		var selele = document.getElementById('elementsel');
		var option = document.createElement('option');
		option.text = 'Select';
		option.value = 'Any';
		selele.add(option);
		for (var i=0; i<selectorelejson.length; i++) {
			var option = document.createElement('option');
			option.text = selectorelejson[i];
			option.value = selectorelejson[i];
			selele.add(option);
		}
		var actsel = document.getElementById('actionsel');
		var option = document.createElement('option');
		option.text = 'Any';
		option.value = 'Any';
		actsel.add(option);
		
		selele.value = '{$elementsel}';
		selectorchange();
		actsel.value = '{$actionsel}';
	});

	function selectorchange() {
		var e = document.getElementById('elementsel');
		var ele = e.options[e.selectedIndex].value;
		var selact = document.getElementById('actionsel');
		selact.innerHTML = '';
		
		var option = document.createElement('option');
		option.text = 'Any';
		option.value = 'Any';
		selact.add(option);
		if (selectorpropjson[ele]) {
			for (var i=0; i<selectorpropjson[ele].length; i++) {
				var option = document.createElement('option');
				option.text = selectorpropjson[ele][i];
				option.value = selectorpropjson[ele][i];
				selact.add(option);
			}
		}
		if (selectoractjson[ele]) {
			for (var i=0; i<selectoractjson[ele].length; i++) {
				var option = document.createElement('option');
				option.text = selectoractjson[ele][i];
				option.value = selectoractjson[ele][i];
				selact.add(option);
			}		
		}
	}
");

$table = new html_table();
$table->size = array( '5%', '20%', '25%', '50%');
$table->head = array( 'Num.', 'Student', 'View Date', 'Match (only shown the last five actions per view)'); 

$views = $DB->get_recordset_select(PLUGIN_VIEWS_TABLE_NAME, 'contextinstanceid = ' . $cmid .  
	' AND MONTH(FROM_UNIXTIME(timestamp)) = ' . +$month . ' AND YEAR(FROM_UNIXTIME(timestamp)) = ' . +$year, null, 'timestamp DESC', 'id, userid, timestamp, DAY(FROM_UNIXTIME(timestamp)) as day');
$count = 0;
foreach ($views as $view) {
	$userdatas = $DB->get_recordset(PLUGIN_USERDATA_TABLE_NAME, array('viewid'=>$view->id));
	$text = '';
	$txtlines = 0;
	foreach ($userdatas as $userdata) {
		$json = json_decode($userdata->info);
		$actions = $json->{'interactions'};
		$events = $json->{'events'};
		$model = $json->{'model'};

		foreach ($actions as $action) {
			$ele = $action->{'element'};
			if ($ele != $elementsel) continue;
			$data = json_encode($action->{'data'});
			if ($datasel != '' and strpos($data, $datasel) === false) continue;
			if (isset($action->{'property'})) {
				$prop = $action->{'property'};
				if ($txtlines < 5 && ($actionsel == 'Any' or $prop == $actionsel)) {
					$text = $text . html_writer::tag('p', 'Element: ' . $action->{'element'} . ' - Property: ' . $action->{'property'} . ' - Data: ' . json_encode($action->{'data'}));
					$txtlines++;
				}
			}
			if (isset($action->{'action'})) {
				$act = $action->{'action'};
				if ($txtlines < 5 && ($actionsel == 'Any' or $act == $actionsel)) {
					$text = $text . html_writer::tag('p', 'Element: ' . $action->{'element'} . ' - Event: ' . $action->{'action'} );
					$txtlines++;
				}
			}
		}		
	}
	if ($text != '') {
		$user = $DB->get_record(USER_TABLE_NAME, array('id'=>$view->userid));
		$count++;
		$table->data[] = array(
				$count,
				$user->firstname . ' ' . $user->lastname,
				userdate($view->timestamp),
				$text);
	}
}
echo html_writer::table($table);
$views->close();	

// Back link
if(active_type == 'report') {
	$url = new moodle_url($CFG->wwwroot . '/mod/ejsssimulation/report_course.php?active_type=report&course=' . $courseid);
	$link = html_writer::link($url, get_string('link_back', 'ejsssimulation'));
	echo html_writer::tag('p', $link, array('align' => 'center'));
}

echo $OUTPUT->footer();



