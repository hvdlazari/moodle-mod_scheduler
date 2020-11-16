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
 * Shows a sortable list of appointments
 *
 * @package    mod_scheduler
 * @copyright  2020 Hellen Lazari
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot.'/mod/scheduler/grading_table.php');

$PAGE->set_docs_path('mod/scheduler/grade');

$scope = optional_param('scope', 'activity', PARAM_TEXT);
if (!in_array($scope, array('activity', 'course', 'site'))) {
    $scope = 'activity';
}
$teacherid = optional_param('teacherid', 0, PARAM_INT);

if ($scope == 'site') {
    $scopecontext = context_system::instance();
} else if ($scope == 'course') {
    $scopecontext = context_course::instance($scheduler->courseid);
} else {
    $scopecontext = $context;
}

if (!has_capability('mod/scheduler:seeoverviewoutsideactivity', $context)) {
    $scope = 'activity';
}
if (!has_capability('mod/scheduler:canseeotherteachersbooking', $scopecontext)) {
    $teacherid = 0;
}

$taburl = new moodle_url('/mod/scheduler/view.php',
                array('id' => $scheduler->cmid, 'what' => 'grade', 'scope' => $scope, 'teacherid' => $teacherid));

$PAGE->set_url($taburl);

echo $output->header();

// Print top tabs.
echo $output->teacherview_tabs($scheduler, $permissions, $taburl, 'grade');

// Find active group in case that group mode is in use.
$currentgroupid = 0;
$groupmode = groups_get_activity_groupmode($scheduler->cm);
if ($groupmode) {
    $currentgroupid = groups_get_activity_group($scheduler->cm, true);

    echo html_writer::start_div('dropdownmenu');
    groups_print_activity_menu($scheduler->cm, $taburl);
    echo html_writer::end_div();
}

$scopemenukey = 'scopemenuself';
if (has_capability('mod/scheduler:canseeotherteachersbooking', $scopecontext)) {
    $teachers = $scheduler->get_available_teachers($currentgroupid);
    $teachermenu = array();
    foreach ($teachers as $teacher) {
        $teachermenu[$teacher->id] = fullname($teacher);
    }
    $select = $output->single_select($taburl, 'teacherid', $teachermenu, $teacherid,
                    array(0 => get_string('myself', 'scheduler')), 'teacheridform');
    echo html_writer::div(get_string('teachersmenu', 'scheduler', $select), 'dropdownmenu');
    $scopemenukey = 'scopemenu';
}
if (has_capability('mod/scheduler:seeoverviewoutsideactivity', $context)) {
    $scopemenu = array('activity' => get_string('thisscheduler', 'scheduler'),
                    'course' => get_string('thiscourse', 'scheduler'),
                    'site' => get_string('thissite', 'scheduler'));
    $select = $output->single_select($taburl, 'scope', $scopemenu, $scope, null, 'scopeform');
    echo html_writer::div(get_string($scopemenukey, 'scheduler', $select), 'dropdownmenu');
}

// Getting date list.
$params = array();
$params['teacherid']   = $teacherid == 0 ? $USER->id : $teacherid;
$params['courseid']    = $scheduler->courseid;
$params['schedulerid'] = $scheduler->id;

$scopecond = '';
if ($scope == 'activity') {
    $scopecond = ' AND sc.id = :schedulerid';
} else if ($scope == 'course') {
    $scopecond = ' AND c.id = :courseid';
}

$sqlcount =
       "SELECT COUNT(*)
          FROM {course} c,
               {scheduler} sc,
               {scheduler_appointment} a,
               {scheduler_slots} s
         WHERE c.id = sc.course AND
               sc.id = s.schedulerid AND
               a.slotid = s.id AND
               s.teacherid = :teacherid ".
               $scopecond;

$numrecords = $DB->count_records_sql($sqlcount, $params);

if ($numrecords) {
    $limit = 50;

    $table = new grading_table($scheduler, $scope, $params, $taburl, $currentgroupid);
    $table->out($limit, true);

} else {
    notice(get_string('noresults', 'scheduler'));
}

echo $output->footer();