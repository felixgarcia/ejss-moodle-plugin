<?php

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
 * External lib
 *
 * @package    mod_ejsssimulation
 * @copyright  2018 Felix J. Garcia
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
 
require_once($CFG->libdir . "/externallib.php");
require(__DIR__. '/report_constants.php');

// checking by: https://iwant2study.org/moodle/webservice/rest/server.php?wstoken=c95efe3ed324fa9f40be2dfed873e157&wsfunction=report_get_interactions&context_id=0&user_id=0&cm_id=0&info=aa
class mod_ejsssimulation_ws extends external_api {

    /**
     * Returns description of method parameters
     * @return external_function_parameters
     */
    public static function get_interactions_parameters() {
        return new external_function_parameters(
                array('view_id' => new external_value(PARAM_INT, 'view id', VALUE_DEFAULT, 0), 
					  'user_id' => new external_value(PARAM_INT, 'user id', VALUE_DEFAULT, 0),
					  'info' => new external_value(PARAM_TEXT, 'interactions')
				)
        );
    }

    /**
     * Collect user interactions
     * @return ID
     */
    public static function get_interactions($view_id, $user_id, $info) {
		global $USER;
		global $DB;

        // Parameter validation
        $params = self::validate_parameters(self::get_interactions_parameters(), array(
					'view_id' => $view_id,
					'user_id' => $user_id, 
					'info' => $info));

        // Context validation
        $context = get_context_instance(CONTEXT_USER, $USER->id);
        self::validate_context($context);

		// See into info
		$json = json_decode($info);
		$actions = count($json->{'interactions'});
		
		// Store user data
		$record = new stdClass();
		$record->viewid = $view_id;
		$record->userid = $user_id;
		$record->info = $info;
		$record->actions = $actions;
		$record->timestamp =  time();
		return $DB->insert_record(PLUGIN_USERDATA_TABLE_NAME, $record);	
    }

    /**
     * Returns description of method result value
     * @return ID
     */
    public static function get_interactions_returns() {
		return new external_value(PARAM_INT, 'ID');
    }
}