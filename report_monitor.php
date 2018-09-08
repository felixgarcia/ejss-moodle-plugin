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
 * Monitor of ejsS simulation
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
$viewsel = optional_param('viewsel', '', PARAM_TEXT);        

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

// Info about students
echo html_writer::tag('h2', $title);
echo html_writer::tag('h4', 'Real Time Monitor');

// Selectors
$urlhistory = file_encode_url($CFG->wwwroot . '/mod/ejsssimulation/report_monitor.php');
echo html_writer::start_tag('form', array('action' => $urlhistory));
echo html_writer::start_tag('div');
echo html_writer::tag('span', 'View:&nbsp');
if ($viewsel == 'grid' OR $viewsel == '') {
	echo html_writer::tag('input', '', array('type'=>'radio', 'name'=>'viewsel', 'value'=>'grid', 'checked'=>'true'));
	echo html_writer::tag('span', '&nbspGrid&nbsp');
	echo html_writer::tag('input', '', array('type'=>'radio', 'name'=>'viewsel', 'value'=>'table'));
	echo html_writer::tag('span', '&nbspTable&nbsp');
} else {
	echo html_writer::tag('input', '', array('type'=>'radio', 'name'=>'viewsel', 'value'=>'grid'));
	echo html_writer::tag('span', '&nbspGrid&nbsp');
	echo html_writer::tag('input', '', array('type'=>'radio', 'name'=>'viewsel', 'value'=>'table', 'checked'=>'true'));
	echo html_writer::tag('span', '&nbspTable&nbsp');
}
echo html_writer::end_tag('div');
echo html_writer::start_tag('div');
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
echo html_writer::end_tag('div');
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

if ($viewsel == 'table') { // Table View

	$table = new html_table();
	$table->size = array( '35%', '10%', '10%', '45%');
	$table->head = array( 'User', 'Moodle', 'In Action', 'Match (only shown last four actions)'); 

	// Get students
	// user -> id, username, picture
	// user_enrolments -> id, enrolid, userid, 
	// enrol -> id, courseid
	$rs = $DB->get_recordset_sql( 'SELECT u.* FROM {'.USER_TABLE_NAME.'} AS u, {'.ENROL_TABLE_NAME.'} AS e, {'.USER_ENROLMENTS_TABLE_NAME.'} AS ue '. 
		' WHERE u.id=ue.userid AND ue.enrolid=e.id AND e.courseid=? ORDER BY lastname ASC', array($courseid));

	foreach( $rs as $r ) {
		$status_url = file_encode_url($CFG->wwwroot . '/mod/ejsssimulation/report_status.php?active_type='.$active_type.'&user='.$r->id.
			'&cm=' . $cmid . '&cminstance=' . $cminstance . '&course=' . $courseid.
			'&elementsel=' . $elementsel . '&actionsel=' . $actionsel . '&datasel=' . $datasel);
		$status_script = "
			function update_status() {
				var xhr = new XMLHttpRequest();
				xhr.open('GET', '{$status_url}');
				xhr.onload = function() {
					if (xhr.status === 200) {
						if(xhr.responseText.length > 0) {
							var data = xhr.responseText.split('\\n');
							// colors
							document.getElementById('text{$r->id}').parentNode.parentNode.style.backgroundColor = 'OrangeRed'
							if (+data[0]) document.getElementById('text{$r->id}').parentNode.parentNode.style.backgroundColor = 'LightYellow';
							if (+data[1] && +data[2]) document.getElementById('text{$r->id}').parentNode.parentNode.style.backgroundColor = 'SpringGreen';
							// text
							document.getElementById('st{$r->id}').innerHTML = data[3];
							document.getElementById('act{$r->id}').innerHTML = data[4];
							if (document.getElementById('text{$r->id}').innerHTML != data[5]) {
								document.getElementById('text{$r->id}').innerHTML = data[5];
							}
						} else {
							console.log('No user actions');
						}
					}
					else {
						console.log('Request failed.  Returned status of ' + xhr.status);
					}		
				};
				xhr.send();		
			};
			update_status();
			setInterval(update_status,1500);
		";
		
		echo html_writer::tag('script', $status_script);
		
		$table->data[] = array(
				$OUTPUT->user_picture($r, array('size' => 100, 'courseid'=>$courseid)) . $r->lastname . ', ' . $r->firstname . '<br>',
				html_writer::tag('span', '', array('id'=>'st'.$r->id)),
				html_writer::tag('span', '', array('id'=>'act'.$r->id)),
				html_writer::tag('p', '', array('id'=>'text'.$r->id))
		);

	}
	echo html_writer::table($table);
	$rs->close();

} else { // Grid View

	$table = new html_table();
	$table->size = array( '12%', '12%', '12%', '12%', '12%', '12%', '12%', '12%');

	// Get students
	// user -> id, username, picture
	// user_enrolments -> id, enrolid, userid, 
	// enrol -> id, courseid
	$rs = $DB->get_recordset_sql( 'SELECT u.* FROM {'.USER_TABLE_NAME.'} AS u, {'.ENROL_TABLE_NAME.'} AS e, {'.USER_ENROLMENTS_TABLE_NAME.'} AS ue '. 
		' WHERE u.id=ue.userid AND ue.enrolid=e.id AND e.courseid=?  ORDER BY lastname ASC', array($courseid));

	$row = ['','','','','',''];
	$col = 0;
	foreach( $rs as $r ) {
		$status_url = file_encode_url($CFG->wwwroot . '/mod/ejsssimulation/report_status.php?active_type='.$active_type.'&user='.$r->id.
			'&cm=' . $cmid . '&cminstance=' . $cminstance . '&course=' . $courseid.
			'&elementsel=' . $elementsel . '&actionsel=' . $actionsel . '&datasel=' . $datasel . '&format=simple');
		$status_script = "
			function update_status() {
				var xhr = new XMLHttpRequest();
				xhr.open('GET', '{$status_url}');
				xhr.onload = function() {
					if (xhr.status === 200) {
						if(xhr.responseText.length > 0) {
							var data = xhr.responseText.split('\\n');
							// colors
							document.getElementById('text{$r->id}').parentNode.style.backgroundColor = 'OrangeRed'
							if (+data[0]) document.getElementById('text{$r->id}').parentNode.style.backgroundColor = 'LightYellow';
							if (+data[1] && +data[2]) document.getElementById('text{$r->id}').parentNode.style.backgroundColor = 'SpringGreen';
							// text
							document.getElementById('st{$r->id}').innerHTML = data[3];
							document.getElementById('act{$r->id}').innerHTML = data[4];
							var action = data[5].substring(data[5].indexOf('<p>')+3,data[5].indexOf('</p>'));
							if (document.getElementById('text{$r->id}').innerHTML != action) {
								if (!action || action == '')
									document.getElementById('text{$r->id}').innerHTML = '<br>';
								else
									document.getElementById('text{$r->id}').innerHTML = action;
							}
						} else {
							console.log('No user actions');
						}
					}
					else {
						console.log('Request failed.  Returned status of ' + xhr.status);
					}		
				};
				xhr.send();		
			};
			update_status();
			setInterval(update_status,1500);
		";
		
		echo html_writer::tag('script', $status_script);

		$row[$col] = html_writer::start_tag('center',array('style'=>'border-radius: 5px; border: solid 1px; border-bottom: 6px solid DodgerBlue;')).
				html_writer::tag('span', $OUTPUT->user_picture($r, array('size' => 100, 'courseid'=>$courseid))).
				html_writer::tag('span', '<br><b>' . $r->lastname . '</b><br>').
				html_writer::tag('span', '', array('id'=>'st'.$r->id)).
				html_writer::tag('span', '<br> In Action: ').
				html_writer::tag('span', '', array('id'=>'act'.$r->id)).
				html_writer::tag('span', '<br>').
				html_writer::tag('span', '<br>', array('id'=>'text'.$r->id)).
				html_writer::end_tag('center');

		if ($col == 8) {
			$table->data[] = $row;
			$col = 0;
			$row = ['','','','','','','',''];
		} else {
			$col++;
		}
	}
	if ($col > 0) $table->data[] = $row;

	echo html_writer::table($table);
	$rs->close();
	
}
echo $OUTPUT->footer();



