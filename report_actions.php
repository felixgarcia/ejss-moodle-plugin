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
$userid = optional_param('user', -1, PARAM_INT);
$username =  optional_param('name', '', PARAM_TEXT);
$viewid =  optional_param('viewid', '', PARAM_TEXT);
$cmid = optional_param('cm', -1, PARAM_INT);
$cminstance = optional_param('cminstance', -1, PARAM_INT);
$min =  optional_param('min', -1, PARAM_INT);
$courseid = optional_param('course', -1, PARAM_INT);        

$elementsel = optional_param('elementsel', 'Any', PARAM_TEXT);        
$actionsel = optional_param('actionsel', 'Any', PARAM_TEXT);        
$datasel = optional_param('datasel', '', PARAM_TEXT);        

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
$urlhistory = file_encode_url($CFG->wwwroot . '/mod/ejsssimulation/report_actions.php');
echo html_writer::start_tag('form', array('action' => $urlhistory));
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
echo html_writer::tag('input', '', array('type'=>'hidden', 'name'=>'user', 'value'=>$userid));
echo html_writer::tag('input', '', array('type'=>'hidden', 'name'=>'name', 'value'=>$username));
echo html_writer::tag('input', '', array('type'=>'hidden', 'name'=>'viewid', 'value'=>$viewid));
echo html_writer::tag('input', '', array('type'=>'hidden', 'name'=>'cm', 'value'=>$cmid));
echo html_writer::tag('input', '', array('type'=>'hidden', 'name'=>'cminstance', 'value'=>$cminstance));
echo html_writer::tag('input', '', array('type'=>'hidden', 'name'=>'min', 'value'=>$min));
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
		option.text = 'Any';
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
$table->size = array( '10%', '10%', '80%');
$table->head = array( 'Acction', 'Time', 'Actions'); 
$views = $DB->get_recordset(PLUGIN_USERDATA_TABLE_NAME, array('viewid'=>$viewid));
$count = 0;
foreach ($views as $view) {
	$json = json_decode($view->info);
	$actions = $json->{'interactions'};
	$events = $json->{'events'};
	$model = $json->{'model'};

	$text = '';
	foreach ($actions as $action) {
		$ele = $action->{'element'};
		if ($elementsel != 'Any' and $ele != $elementsel) continue;
		$data = json_encode($action->{'data'});
		if ($datasel != '' and strpos($data, $datasel) === false) continue;
		if (isset($action->{'property'})) {
			$prop = $action->{'property'};
			if ($actionsel == 'Any' or $prop == $actionsel) 
				$text = $text . html_writer::tag('p', 'Element: ' . $action->{'element'} . ' - Property: ' . $action->{'property'} . ' - Data: ' . json_encode($action->{'data'}));
		}
		if (isset($action->{'action'})) {
			$act = $action->{'action'};
			if ($actionsel == 'Any' or $act == $actionsel) 
				$text = $text . html_writer::tag('p', 'Element: ' . $action->{'element'} . ' - Event: ' . $action->{'action'} );
		}
	}
	
	if ($text != '') {
		$count++;
		$table->data[] = array(
				$count,
				mod_ejsssimulation_mediaTimeDeFormater($view->timestamp - $min),
				$text);
	}
}
echo html_writer::table($table);
$views->close();	

// Back link
$url = new moodle_url($CFG->wwwroot . '/mod/ejsssimulation/report_user.php?active_type='.$active_type.'&title='.urlencode($title).'&user=' . $userid .'&cm=' . $cmid . '&cminstance=' . $cminstance . '&course=' . $courseid);
$link = html_writer::link($url, get_string('link_back', 'ejsssimulation'));
echo html_writer::tag('p', $link, array('align' => 'center'));

echo $OUTPUT->footer();



