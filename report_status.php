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
 * Real time status of ejsS simulation
 *
 * @package    mod_ejsssimulation
 * @copyright  2018 Felix J. Garcia
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(dirname(__FILE__).'/../../config.php');
require(__DIR__. '/report_constants.php');
require(__DIR__. '/report_lib.php');

$active_type = optional_param('active_type', '', PARAM_TEXT);
$viewid = optional_param('viewid', -1, PARAM_INT);
$userid = optional_param('user', -1, PARAM_INT);
$cmid = optional_param('cm', -1, PARAM_INT);
$cminstance = optional_param('cminstance', -1, PARAM_INT);
$courseid = optional_param('course', -1, PARAM_INT);

$format = optional_param('format', '', PARAM_TEXT);        

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
}

// Init vars
$lastacttime = -1;
$text = '';
$matchele = 0;
$matchact = 0;
$matchdata = 0;
				
// Get student status
$sess = $DB->get_recordset_select(SESSIONS_TABLE_NAME, 'userid = ' . $userid . ' GROUP BY userid', null, '', "MAX(timemodified) AS lastses");
if(!$sess->valid()) {
	$status = "Disconnected";
} else {
	$ses = $sess->current();
	$status = 'Online (' . userdate($ses->lastses, "%H:%M" . ')');

	// Get last views
	$views = $DB->get_recordset_select(PLUGIN_VIEWS_TABLE_NAME, 'contextinstanceid = ' . $cmid . ' AND userid = ' . $userid . 
		' AND timestamp >= UNIX_TIMESTAMP(CURDATE()-1)', null, 'timestamp DESC', 'id, timestamp');
	if($views->valid()) {
		// Get last view
		$view = $views->current();
		$txtlines = 0;

		// Get actions of last view
		$inters = $DB->get_recordset(PLUGIN_USERDATA_TABLE_NAME, array('viewid'=>$view->id, 'userid'=>$userid), 'timestamp DESC');
		if(!$inters->valid()) {
			$text = '-';
		} else {
			$lastacttime = time() - $inters->current()->timestamp;
			foreach($inters as $inter) {				
				// Actions
				$json = json_decode($inter->info);
				$actions = $json->{'interactions'};
				$events = $json->{'events'};
				$model = $json->{'model'};

				foreach ($actions as $action) {
					$ele = $action->{'element'};
					if ($elementsel != 'Any' and $ele != $elementsel) continue;
					$matchele = 1;
					if (isset($action->{'property'})) {
						$prop = $action->{'property'};
						if ($txtlines < 4 and ($actionsel == 'Any' or $prop == $actionsel)) {
							if ($format == 'simple') {
								$datastr = mod_ejsssimulation_cutstring(json_encode($action->{'data'}),30);
								$text = $text . html_writer::tag('p', $action->{'element'} . ':' . $action->{'property'} . '=' . $datastr);
							}
							else
								$text = $text . html_writer::tag('p', 'Element: ' . $action->{'element'} . ' - Property: ' . $action->{'property'} . ' - Data: ' . json_encode($action->{'data'}));
							$matchact = 1;
							$txtlines++;
						}
					}
					if (isset($action->{'action'})) {
						$act = $action->{'action'};
						if ($txtlines < 4 and ($actionsel == 'Any' or $act == $actionsel)) {
							if ($format == 'simple')
								$text = $text . html_writer::tag('p', $action->{'element'} . ':' . $action->{'action'} );
							else
								$text = $text . html_writer::tag('p', 'Element: ' . $action->{'element'} . ' - Event: ' . $action->{'action'} );
							$matchact = 1;
							$txtlines++;
						}
					}
					$data = json_encode($action->{'data'});
					if ($datasel == '' or (strpos($data, $datasel) !== false)) {
						$matchdata = 1;
					}
				}		

			}
		}
	}
}

if ($lastacttime != -1) 
	$actstatus = mod_ejsssimulation_mediaTimeDeFormater($lastacttime) . ' ago';
else
	$actstatus = 'Not Yet';

echo $matchele . "\n";
echo $matchact . "\n";
echo $matchdata . "\n";
echo $status . "\n";
echo $actstatus . "\n";
echo $text;


