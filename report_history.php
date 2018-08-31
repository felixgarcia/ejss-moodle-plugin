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
 * History of ejsS simulation
 *
 * @package    mod_ejsssimulation
 * @copyright  2018 Felix J. Garcia
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(dirname(__FILE__).'/../../config.php');
require(__DIR__. '/report_constants.php');

$active_type = optional_param('active_type', '', PARAM_TEXT);
$title =  optional_param('title', '', PARAM_TEXT);
$cmid = optional_param('cm', -1, PARAM_INT);
$cminstance = optional_param('cminstance', -1, PARAM_INT);
$courseid = optional_param('course', -1, PARAM_INT);
$datechart = optional_param('datechart', '', PARAM_TEXT);
$userradio = optional_param('userradio', -1, PARAM_INT);
        
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

// Title
echo html_writer::tag('h2', $title);
echo html_writer::tag('h4', 'Students using the simulation');

//------ Selector 

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

$urlhistory = file_encode_url($CFG->wwwroot . '/mod/ejsssimulation/report_history.php');
echo html_writer::start_tag('form', array('action' => $urlhistory));
echo html_writer::tag('input', '', array('type'=>'month', 'name'=>'datechart', 'value'=>$datechart));
echo html_writer::tag('input', '', array('type'=>'submit'));
echo html_writer::tag('input', '', array('type'=>'hidden', 'name'=>'active_type', 'value'=>$active_type));
echo html_writer::tag('input', '', array('type'=>'hidden', 'name'=>'title', 'value'=>$title));
echo html_writer::tag('input', '', array('type'=>'hidden', 'name'=>'cm', 'value'=>$cmid));
echo html_writer::tag('input', '', array('type'=>'hidden', 'name'=>'cminstance', 'value'=>$cminstance));
echo html_writer::tag('input', '', array('type'=>'hidden', 'name'=>'course', 'value'=>$courseid));
if ($userradio > 0) {
	$radioinput = html_writer::tag('input', '', array('type'=>'radio', 'name'=>'userradio', 'value'=>'0'));
} else {
	$radioinput = html_writer::tag('input', '', array('type'=>'radio', 'name'=>'userradio', 'value'=>'0', 'checked'=>'true'));
}

//------ User Table

$table = new html_table();
$table->size = array( '5%', '40%', '10%', '35%', '10%');
$table->head = array( $radioinput, 'Name', 'Views', 'Last View'); 
$users = [];
$views = $DB->get_recordset_select(PLUGIN_VIEWS_TABLE_NAME, 'contextinstanceid = ' . $cmid . 
				' AND MONTH(FROM_UNIXTIME(timestamp)) LIKE ' . +$month . ' AND YEAR(FROM_UNIXTIME(timestamp)) LIKE ' . +$year  . ' GROUP BY userid', null, 
				'ct DESC', "userid, MAX(timestamp) AS mx, COUNT(timestamp) AS ct");
foreach ($views as $view) {
	$user = $DB->get_record(USER_TABLE_NAME, array('id'=>$view->userid));
	$users[] = $user->id;

	$urlhistory = file_encode_url($CFG->wwwroot . '/mod/ejsssimulation/report_user.php?active_type='.$active_type.'&title=' . urlencode($title) . '&user=' . $user->id . '&cm=' . $cmid . '&cminstance=' . $cminstance . '&course=' . $courseid);
	if ($userradio == $user->id) {
		$table->data[] = array(
				html_writer::tag('input', '', array('type'=>'radio', 'name'=>'userradio', 'value'=>$user->id, 'checked'=>'true')),
				$user->firstname . ' ' . $user->lastname,
				$view->ct,
				userdate($view->mx),
				html_writer::link($urlhistory, 'Views', array('class' => 'btn btn-secondary')));
	} else {
		$table->data[] = array(
				html_writer::tag('input', '', array('type'=>'radio', 'name'=>'userradio', 'value'=>$user->id)),
				$user->firstname . ' ' . $user->lastname,
				$view->ct,
				userdate($view->mx),
				html_writer::link($urlhistory, 'Views', array('class' => 'btn btn-secondary')));
	}
}
echo html_writer::table($table);
$views->close();	
echo html_writer::end_tag('form');

//------ Views chart

echo html_writer::tag('h4', 'Activity of students');
$dayspermonth = [31,28,31,30,31,30,31,31,30,31,30,31]; 	
$labels = [];
$valuesview = [];
$valuesuser = [];
$valuesradio = [];
for ($i=0; $i<$dayspermonth[+$month-1]; $i++) {
	$labels[$i] = $i + 1;
	$valuesview[$i] = 0;
	$valuesuser[$i] = [];
	$valuesradio[$i] = 0;
}
$viewsbyday = $DB->get_recordset_select(PLUGIN_VIEWS_TABLE_NAME, 
	'contextinstanceid = ' . $cmid . ' AND MONTH(FROM_UNIXTIME(timestamp)) LIKE ' . +$month . ' AND YEAR(FROM_UNIXTIME(timestamp)) LIKE ' . +$year, null, '', 
	"id, timestamp, userid, DAY(FROM_UNIXTIME(timestamp)) as day");
$viewsperactions = [];
$usersperactions = [];
$viewsperactionsradio = [];
foreach ($viewsbyday as $view) {
	$valuesview[(+$view->day)-1] += 1;
	$valuesuser[(+$view->day)-1][$view->userid] = 1;
	if($view->userid == $userradio) $valuesradio[(+$view->day)-1] += 1;
	$userdatas = $DB->get_recordset(PLUGIN_USERDATA_TABLE_NAME, array('viewid'=>$view->id));
	foreach ($userdatas as $userdata) {
		$viewsperactions[+$userdata->actions] += 1;	
		$usersperactions[+$userdata->actions][$view->userid] = 1;
		if($view->userid == $userradio) 
			$viewsperactionsradio[+$userdata->actions] += 1;
	}
}
for ($j=0; $j<$dayspermonth[+$month-1]; $j++) {
	$valuesuser[$j] = count($valuesuser[$j]); // count elements
}
$viewsbyday->close();
$valuesavg = [];
foreach($valuesview as $label => $value) {
	if($value > 0)
		$valuesavg[$label] = round($value/$valuesuser[$label]);
	else
		$valuesavg[$label] = 0;
}

// Show chart
if (array_sum($valuesview) > 0) {
	echo html_writer::start_tag('div', array('style'=>"width: 100%"));
	echo html_writer::start_tag('div', array('style'=>"float: left; width: 50%"));
	$chart = new \core\chart_bar();
	$chart->set_title('Views per day');
	if ($userradio <= 0) $chart->add_series(new \core\chart_series('Total Views', $valuesview));
	$chart->add_series(new \core\chart_series('Views per Student', $valuesavg));
	if($userradio > 0) $chart->add_series(new \core\chart_series('Views of Student', $valuesradio));
	if ($userradio <= 0) $chart->add_series(new \core\chart_series('Students', $valuesuser));
	$chart->set_labels($labels);
	echo $OUTPUT->render($chart,false);
	echo html_writer::end_tag('div');
	echo html_writer::start_tag('div', array('style'=>"float: left; width: 50%"));
	$chart = new \core\chart_bar();
	$chart->set_title('Views with certain amount of actions');
	$labels = [];
	$valuesview = [];
	$valuesuser = [];
	$valuesviewradio = [];
	if (count($viewsperactions) < 5) {
		ksort($viewsperactions);
		foreach ($viewsperactions as $key => $value) {
			$labels[] = $key . ' Actions';
			$valuesview[] = $value;
			$valuesuser[] = array_sum($usersperactions[$key]);
			$valuesviewradio[] = $viewsperactionsradio[$key];
		}
	} else {
		// 4 parts
		ksort($viewsperactions);
		$max = max(array_keys($viewsperactions));
		$labels = [ '[0-'.floor($max/4 + 1).'] Actions', 
					'['.floor($max/4 + 2).'-'.floor($max/2 + 1).'] Actions', 
					'['.floor($max/2 + 2).'-'.floor($max*3/4 + 1).'] Actions', 
					'['.floor($max*3/4 + 2).'-'.floor($max).'] Actions'];
		$valuesview = [0,0,0,0];
		$valuesuser = [0,0,0,0];
		$valuesviewradio = [0,0,0,0];
		$valuesusercounter = [];
		foreach ($viewsperactions as $key => $value) {
			if ($key < $max/4) {
				$valuesview[0] += $value;
				foreach (array_keys($usersperactions[$key]) as $userid)
					$valuesusercounter[0][$userid] = 1;
				$valuesviewradio[0] += $viewsperactionsradio[$key];
			} elseif ($key < $max/2) {
				$valuesview[1] += $value;
				foreach (array_keys($usersperactions[$key]) as $userid)
					$valuesusercounter[1][$userid] = 1;
				$valuesviewradio[1] += $viewsperactionsradio[$key];
			} elseif ($key < $max*3/4) {
				$valuesview[2] += $value;
				foreach (array_keys($usersperactions[$key]) as $userid)
					$valuesusercounter[2][$userid] = 1;
				$valuesviewradio[2] += $viewsperactionsradio[$key];
			} else {
				$valuesview[3] += $value;
				foreach (array_keys($usersperactions[$key]) as $userid)
					$valuesusercounter[3][$userid] = 1;
				$valuesviewradio[3] += $viewsperactionsradio[$key];
			}
		}		
		$valuesuser = [array_sum($valuesusercounter[0]),array_sum($valuesusercounter[1]),array_sum($valuesusercounter[2]),array_sum($valuesusercounter[3])];
	}
	
	$valuesavg = [];
	foreach($valuesview as $label => $value) {
		if($value > 0)
			$valuesavg[$label] = round($value/$valuesuser[$label]);
		else
			$valuesavg[$label] = 0;
	}

	if ($userradio <= 0) $chart->add_series(new core\chart_series('Total Views', $valuesview)); 
	$chart->add_series(new \core\chart_series('Views per Student', $valuesavg));
	if($userradio > 0) $chart->add_series(new core\chart_series('Views of Student', $valuesviewradio)); 
	if ($userradio <= 0) $chart->add_series(new core\chart_series('Students', $valuesuser)); 
	$chart->set_labels($labels);
	echo $OUTPUT->render($chart);
	echo html_writer::end_tag('div');
	echo html_writer::end_tag('div');
} else {
	echo html_writer::div('No views in this month.');
}

//------ Properties and Events chart

$props = [];
$acts = [];
$count = 0;
$actsradio = [];
$countradio = 0;
$views = $DB->get_recordset_select(PLUGIN_VIEWS_TABLE_NAME, 
	'contextinstanceid = ' . $cmid . ' AND MONTH(FROM_UNIXTIME(timestamp)) LIKE ' . +$month . ' AND YEAR(FROM_UNIXTIME(timestamp)) LIKE ' . +$year);
foreach ($views as $view) {
	$userdatas = $DB->get_recordset(PLUGIN_USERDATA_TABLE_NAME, array('viewid'=>$view->id));
	foreach ($userdatas as $userdata) {
		// Actions
		$json = json_decode($userdata->info);
		$actions = $json->{'interactions'};
		$events = $json->{'events'};
		$model = $json->{'model'};

		foreach ($actions as $action) {
			$ele = $action->{'element'};
			$data = json_encode($action->{'data'});
			if (isset($action->{'property'})) {
				$prop = $action->{'property'};
				$props[$ele.':'.$prop] += 1;
				if ($view->userid == $userradio) $propsradio[$ele.':'.$prop] += 1;
			} elseif (isset($action->{'action'})) {
				$act = $action->{'action'};
				$acts[$ele.':'.$act] += 1;
				if ($view->userid == $userradio) $actsradio[$ele.':'.$act] += 1;
			}
		}
		
	}
	$userdatas->close();
	$count++;
}
$views->close();
$propsperview = [];
$actsperview = [];
$propsperviewradio = [];
$actsperviewradio = [];
foreach($props as $label => $prop) {
	$propsperview[$label] = round($prop/$count);
}
foreach($propsradio as $label => $prop) {
	$propsperviewradio[$label] = round($prop/$count);
}
foreach($acts as $label => $act) {
	$actsperview[$label] = round($act/$count);
}
foreach($actsradio as $label => $act) {
	$actsperviewradio[$label] = round($act/$count);
}
// Show chart
if (array_sum($props) > 0 or array_sum($acts) > 0)
	echo html_writer::tag('h4', 'Activity in simulation');

echo html_writer::start_tag('div', array('style'=>"width: 100%"));
echo html_writer::start_tag('div', array('style'=>"float: left; width: 50%"));
if (array_sum($props) > 0) {
	ksort($props);
	ksort($propsradio);
	ksort($propsperview);
	ksort($propsperviewradio);
	$chart = new \core\chart_bar();
	$chart->set_title('Property changes');
	//if ($userradio > 0) $CFG->chart_colorset = ['LightCoral','Crimson','Gold','DarkOrange']; 
	//else $CFG->chart_colorset = ['LightCoral','Gold'];
	if ($userradio <= 0) $chart->add_series(new \core\chart_series('Total changes', array_values($props)));
	$chart->add_series(new \core\chart_series('Changes per View', array_values($propsperview)));
	// if ($userradio > 0) $chart->add_series(new \core\chart_series('Changes of Student', array_values($propsradio)));
	if ($userradio > 0) $chart->add_series(new \core\chart_series('Changes per View of Student', array_values($propsperview)));
	$chart->set_labels(array_keys($props));
	echo $OUTPUT->render($chart,false);
} else {
	echo html_writer::div('No property changes.');
}
echo html_writer::end_tag('div');
echo html_writer::start_tag('div', array('style'=>"float: left; width: 50%"));
if (array_sum($acts) > 0) {
	ksort($acts);
	ksort($actsradio);
	ksort($actsperview);
	ksort($actsperviewradio);
	$chart = new \core\chart_bar();
	$chart->set_title('Events generated');
	//if ($userradio > 0) $CFG->chart_colorset = ['MediumPurple','Purple','Cyan','Turquoise']; 
	//else $CFG->chart_colorset = ['MediumPurple','Cyan'];
	if ($userradio <= 0) $chart->add_series(new \core\chart_series('Total events', array_values($acts)));
	$chart->add_series(new \core\chart_series('Events per View', array_values($actsperview)));
	// if ($userradio > 0) $chart->add_series(new \core\chart_series('Events of Student', array_values($actsradio)));
	if ($userradio > 0) $chart->add_series(new \core\chart_series('Events per View of Student', array_values($actsperviewradio)));
	$chart->set_labels(array_keys($acts));
	echo $OUTPUT->render($chart,false);
} else {
	echo html_writer::div('No view events.');
}
echo html_writer::end_tag('div');
echo html_writer::end_tag('div');


// Back link
if(active_type == 'report') {
	$url = new moodle_url($CFG->wwwroot . '/mod/ejsssimulation/report_course.php?active_type=report&course=' . $courseid);
	$link = html_writer::link($url, get_string('link_back', 'ejsssimulation'));
	echo html_writer::tag('p', $link, array('align' => 'center'));
}

echo $OUTPUT->footer();

