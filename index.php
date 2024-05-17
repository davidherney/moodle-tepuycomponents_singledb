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

$uid        = optional_param('uid', '', PARAM_TEXT);
$courseid   = optional_param('courseid', 0, PARAM_INT);
$groupid    = optional_param('groupid', 0, PARAM_INT);
$id         = optional_param('id', 0, PARAM_INT); // Course module id.
$type       = optional_param('type', 'scorm', PARAM_TEXT); // Course module type.

$url = new moodle_url('/local/tepuy/components/singledb/index.php',
            array('id' => $id, 'uid' => $uid, 'course' => $courseid, 'type' => $type, 'groupid' => $groupid));

$PAGE->set_url($url);

// If empty id search by scorm sco.
if (empty($id)) {

    // Only scorm type is valid when an id is not received.
    $type = 'scorm';

    if (!$modtype = $DB->get_record('modules', array('name' => $type))) {
        print_error('invalidmodule');
    }

    if (!$course = $DB->get_record('course', array('id' => $courseid))) {
        print_error('invalidcourseid');
    }

    $sql  = "SELECT s.id AS id
                FROM {scorm} AS s
                INNER JOIN {scorm_scoes} AS ss ON s.id = ss.scorm
                WHERE ss.manifest = :uid AND s.course = :courseid";

    $params = array('uid' => $uid, 'courseid' => $courseid, 'type' => $modtype->id );

    if (!$module = $DB->get_records_sql($sql, $params, 0, 1)) {
        print_error('invalidcoursemodule');
    }

    $id = reset($module)->id;

    if (!$cm = get_coursemodule_from_instance('scorm', $id, $course->id)) {
        print_error('invalidcoursemodule');
    }

} else {
    if (!$modtype = $DB->get_record('modules', array('name' => $type))) {
        print_error('invalidmodule');
    }

    if (!$cm = get_coursemodule_from_id($type, $id)) {
        print_error('invalidcoursemodule');
    }

    if (!$course = $DB->get_record('course', array('id' => $cm->course))) {
        print_error('invalidcourseid');
    }
}

require_login($course, false, $cm);

// Check to see if groups are being used here.
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

if (!$settings = $DB->get_record('local_tepuy_settings', array('cmid' => $cm->id))) {
    print_error('settingsnotfound');
}

$settingsdata = json_decode($settings->param1);

try {
    $userpicture = new user_picture(core_user::get_user($USER->id));
    $pictureurl = $userpicture->get_url($PAGE);
    $pictureurl = $pictureurl->out();
} catch (Exception $e) {
    $userpicture = '';
}

$res = new stdClass();
$res->skey = $sess->skey;
$res->cmid = $cm->id;
$res->userid = $USER->id;
$res->usernames = fullname($USER);
$res->userpicture = $pictureurl;
$res->courseid = $course->id;
$res->courseshortname = format_string($course->shortname, true, array('context' => context_course::instance($course->id)));
$res->groupid = $groupid;
$res->groupname = $groupname;

echo json_encode($res);
exit;
