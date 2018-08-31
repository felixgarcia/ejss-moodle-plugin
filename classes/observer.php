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
 * Event observers used in plugin.
 *
 * @package    mod_ejsssimulation
 * @copyright  2018 Felix J. Garcia
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();
require(__DIR__. '/../report_constants.php');
require(__DIR__. '/../report_lib.php');

ini_set('display_errors', '1');

class mod_ejsssimulation_observer {	
	
	public static function course_module_created(\core\event\course_module_created $event) {
		// nothing to do
	}

	public static function course_module_updated(\core\event\course_module_updated $event) {
		// nothing to do
	}
	
	public static function course_module_deleted(\core\event\course_module_deleted $event) {
		// nothing to do
	}
	
	public static function course_module_viewed(\core\event\course_module_viewed $event) {
		// nothing to do
	}

	public static function course_viewed(\core\event\course_viewed $event) {
		// nothing to do
	}	
}
