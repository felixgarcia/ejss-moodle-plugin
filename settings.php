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
 * Settings
 *
 * @package    mod_ejsssimulation
 * @copyright  2018 Felix J. Garcia
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

$ADMIN->add('reports', new admin_externalpage('reportejssstats', get_string('pluginname', 'ejsssimulation'), "$CFG->wwwroot/mod/ejsssimulation/report_allcourses.php"));

if ($ADMIN->fulltree) {
    require_once("$CFG->libdir/resourcelib.php");
	
    //--- general settings -----------------------------------------------------------------------------------
    $settings->add(new admin_setting_configtext('resource/framesize',
        get_string('framesize', 'ejsssimulation'), get_string('configframesize', 'ejsssimulation'), 130, PARAM_INT));
}

