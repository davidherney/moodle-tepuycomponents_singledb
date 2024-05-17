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

define('AJAX_SCRIPT', true);

require_once('../../../../config.php');
require_once($CFG->dirroot . '/lib/outputcomponents.php');

$cmid       = required_param('cmid', PARAM_INT); // Course module id.
$groupid    = optional_param('groupid', 0, PARAM_INT);
$type       = optional_param('type', 'scorm', PARAM_TEXT); // Course module type.
$op         = optional_param('op', 'select', PARAM_TEXT);
$table      = required_param('table', PARAM_TEXT);
$relatedid  = optional_param('relatedid', 0, PARAM_INT);

$url = new moodle_url('/local/tepuy/components/singledb/index.php',
            array('id' => $cmid, 'type' => $type, 'groupid' => $groupid));

$PAGE->set_url($url);

if (!$modtype = $DB->get_record('modules', array('name' => $type))) {
    print_error('invalidmodule');
}

if (!$cm = get_coursemodule_from_id($type, $cmid)) {
    print_error('invalidcoursemodule');
}

if (!$course = $DB->get_record('course', array('id' => $cm->course))) {
    print_error('invalidcourseid');
}

require_login($course, false, $cm);

$setting = $DB->get_record('local_tepuy_settings', array('cmid' => $cmid));

if (!$setting || !$setting->enabled) {
    print_error('resourcedisabled', 'local_tepuy');
}

// Check to see if groups are being used here
if ($groupmode = groups_get_activity_groupmode($cm)) {   // Groups are being used.
    if ($groupid = groups_get_activity_group($cm)) {
        if (!$group = groups_get_group($groupid)) {
            print_error('invalidgroupid');
        }
        $groupname = $group->name;
    } else {
        if ($groups = groups_get_activity_allowed_groups($cm)) {
            $group = reset($groups);

            $groupid = $group->id;
            $groupname = $group->name;
        } else {
            $groupname = get_string('allparticipants');
        }
    }
} else {
    $groupid = 0;
    $groupname = '';
}

switch($op) {
    case 'list':
        $params = array('cmid' => $cmid, 'tablekey' => $table);

        if ($relatedid) {
            $params['relatedid'] = $relatedid;
        }

        $records = $DB->get_records('local_tepuy_singledb', $params);

        if(!$records) {
            $records = array();
        } else {
            $res = array();
            foreach($records as $key => $record) {
                $data = @json_decode($record->datastore);
                $data->id = $key;
                $data->relatedid = $record->relatedid;
                $res[] = $data;
            }
        }
    break;
    case 'save':
        $fields = required_param('fields', PARAM_RAW);
        $data = @json_decode($fields);

        if (!$data || !is_object($data)) {
            $dataobj = new stdClass();
            $dataobj->value = $data;
            $datastore = json_encode($dataobj);
        } else {
            $datastore = json_encode($data);
        }

        $params = array('cmid' => $cmid,
                        'groupid' => $groupid,
                        'tablekey' => $table,
                        'relatedid' => $USER->id,
                        'datastore' => $datastore);
        $id = $DB->insert_record('local_tepuy_singledb', $params, true);
        $res = new stdClass();
        $res->id = $id;
    break;
    case 'set':
        $fields = required_param('fields', PARAM_RAW);
        $to = required_param('to', PARAM_TEXT);
        $id = required_param('id', PARAM_INT);

        $params = array('cmid' => $cmid,
                        'tablekey' => $table,
                        'id' => $id);
        $record = $DB->get_record('local_tepuy_singledb', $params);

        $res = new stdClass();

        if(!$record) {
            $res->result = false;
        } else if ($record->relatedid != $USER->id) {
            $res->error = get_string('changenotowner', 'tepuycomponents_singledb');
            $res->result = false;
        } else {
            $newdata = @json_decode($fields);
            $record->datastore = @json_decode($record->datastore);

            if (!property_exists($record->datastore, $to)) {
                $record->datastore->$to = [];
            }

            $record->datastore->$to[] = $newdata;
            $record->datastore = json_encode($record->datastore);

            $DB->update_record('local_tepuy_singledb', $record);

            $res->result = true;
        }
    break;
    case 'change':
        $fields = required_param('fields', PARAM_RAW);
        $id = required_param('id', PARAM_INT);

        $params = array('cmid' => $cmid,
                        'tablekey' => $table,
                        'id' => $id);
        $record = $DB->get_record('local_tepuy_singledb', $params);

        $res = new stdClass();

        if(!$record) {
            $res->result = false;
        } else if ($record->relatedid != $USER->id) {
            $res->error = get_string('changenotowner', 'tepuycomponents_singledb');
            $res->result = false;
        } else {
            $newdata = @json_decode($fields);
            $record->datastore = @json_decode($record->datastore);

            if (!is_object($newdata)) {
                $res->error = get_string('changefieldserror', 'tepuycomponents_singledb');
            } else {
                foreach($newdata as $field => $value) {
                    if (property_exists($record->datastore, $field)) {
                        $record->datastore->$field = $value;
                    }
                }

                $record->datastore = json_encode($record->datastore);

                $DB->update_record('local_tepuy_singledb', $record);

                $res->result = true;
            }
        }
    break;
    case 'delete':
        $id = required_param('id', PARAM_INT);
        $res = new stdClass();

        $params = array('cmid' => $cmid,
                        'tablekey' => $table,
                        'id' => $id);
        $record = $DB->get_record('local_tepuy_singledb', $params);

        if(!$record) {
            $res->result = false;
        } else if ($record->relatedid != $USER->id) {
            $res->error = get_string('deletenotowner', 'tepuycomponents_singledb');
            $res->result = false;
        } else {
            $DB->delete_records('local_tepuy_singledb', array('id' => $id));
            $res->result = true;
        }

    break;
}

echo json_encode($res);
exit;