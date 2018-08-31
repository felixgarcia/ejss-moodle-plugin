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
 * Resource configuration form
 *
 * @package    mod_ejsssimulation
 * @copyright  2018 Felix J. Garcia
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

require_once($CFG->dirroot.'/course/moodleform_mod.php');
require_once($CFG->dirroot.'/mod/ejsssimulation/resource_lib.php');
require_once($CFG->libdir.'/filelib.php');

class mod_ejsssimulation_mod_form extends moodleform_mod {
    function definition() {
        global $CFG, $DB;
        $mform =& $this->_form;

        $config = get_config('ejsssimulation');

        //-------------------------------------------------------
        $mform->addElement('header', 'general', get_string('general', 'form'));
        $mform->addElement('text', 'name', get_string('name'), array('size'=>'48'));
        if (!empty($CFG->formatstringstriptags)) {
            $mform->setType('name', PARAM_TEXT);
        } else {
            $mform->setType('name', PARAM_CLEANHTML);
        }
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addRule('name', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');
        $this->standard_intro_elements();
        $element = $mform->getElement('introeditor');
        $attributes = $element->getAttributes();
        $attributes['rows'] = 5;
        $element->setAttributes($attributes);
        $filemanager_options = array();
        $filemanager_options['accepted_types'] = '*';
        $filemanager_options['maxbytes'] = 0;
        $filemanager_options['maxfiles'] = -1;
        $filemanager_options['mainfile'] = true;

        $mform->addElement('filemanager', 'files', get_string('selectfiles'), null, $filemanager_options);

        //-------------------------------------------------------
        $this->standard_coursemodule_elements();

        //-------------------------------------------------------
        $this->add_action_buttons();

        //-------------------------------------------------------
        $mform->addElement('hidden', 'revision');
        $mform->setType('revision', PARAM_INT);
        $mform->setDefault('revision', 1);
    }

    function data_preprocessing(&$default_values) {
        if ($this->current->instance) {
            $draftitemid = file_get_submitted_draft_itemid('files');
            file_prepare_draft_area($draftitemid, $this->context->id, 'mod_ejsssimulation', 'content', 0, array('subdirs'=>true));
            $default_values['files'] = $draftitemid;
        }		
    }

    function definition_after_data() {
        parent::definition_after_data();
    }

    function validation($data, $files) {
        global $USER;

        $errors = parent::validation($data, $files);

        $usercontext = context_user::instance($USER->id);
        $fs = get_file_storage();
        if (!$files = $fs->get_area_files($usercontext->id, 'user', 'draft', $data['files'], 'sortorder, id', false)) {
            $errors['files'] = get_string('required');
            return $errors;
        }
        if (count($files) == 1) {
            // no need to select main file if only one picked
            return $errors;
        } else if(count($files) > 1) {
            $mainfile = false;
            foreach($files as $file) {
                if ($file->get_sortorder() == 1) {
                    $mainfile = true;
                    break;
                }
            }
            // set a default main file
            if (!$mainfile) {
                $file = reset($files);
                file_set_sortorder($file->get_contextid(), $file->get_component(), $file->get_filearea(), $file->get_itemid(),
                                   $file->get_filepath(), $file->get_filename(), 1);
            }
        }
        return $errors;
    }
}