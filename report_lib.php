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

defined('MOODLE_INTERNAL') || die();
require(__DIR__. '/report_constants.php');

function mod_ejsssimulation_mediaTimeDeFormater($seconds) {
    if (!is_numeric($seconds))
        throw new Exception("Invalid Parameter Type!");

    $ret = "";

    $hours = (string )floor($seconds / 3600);
    $secs = (string )$seconds % 60;
    $mins = (string )floor(($seconds - ($hours * 3600)) / 60);

    if (strlen($hours) == 1)
        $hours = "0" . $hours;
    if (strlen($secs) == 1)
        $secs = "0" . $secs;
    if (strlen($mins) == 1)
        $mins = "0" . $mins;

    if ($hours == 0)
        $ret = "$mins:$secs";
    else
        $ret = "$hours:$mins:$secs";

    return $ret;
}

function mod_ejsssimulation_minutes($formated) {
	$portions = array_reverse(explode(":", $formated));
	return round($portions[0]/60 + $portions[1] + $portions[2]*60,2);
}

function mod_ejsssimulation_cutstring($str,$mxlen) {
	if (strlen($str) > $mxlen) {
		$str = substr($str, 0, $mxlen-3).'...';
	}
	return $str;
}

function mod_ejsssimulation_elements($cmid) {
	$context = context_module::instance($cmid);

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

	$elements = [];
	$properties = [];
	$events = [];
	if($isEjssSim) {
		$xml = simplexml_load_string($contents);
		$elementsxml = $xml->xpath("//*[name()='HtmlView.Element']/Name");
		while(list( , $nodo) = each($elementsxml)) {
			$elements[] = ''.$nodo;
		};
		foreach($elements as $ele) {
			$propertiesxml = $xml->xpath("//*[name()='HtmlView.Element']/Name[.='{$ele}']/parent::*/Property/@name");
			while(list( , $nodo) = each($propertiesxml)) {
				if(substr(''.$nodo,0,2) == 'On')
					$events[$ele][] = ''.$nodo;
				else
					$properties[$ele][] = ''.$nodo;
			};
			sort($properties[$ele]);
			sort($events[$ele]);
		}
	}
	sort($elements);
	return array('elements'=>$elements, 'properties'=>$properties, 'events'=>$events);
}

