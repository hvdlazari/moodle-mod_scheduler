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
 * This file contains the definition for the grading table
 *
 * @package   mod_assign
 * @copyright 2020 Hellen Lazari {hellen.lazari@gmail.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir.'/tablelib.php');
require_once($CFG->libdir.'/gradelib.php');

class grading_table extends table_sql
{
    protected $perpage = 50;

    protected $output;

    public function __construct($scheduler, $scope, $params, $baseurl, $currentgroup)
    {
        global $CFG, $PAGE, $DB, $USER;
        parent::__construct('mod_scheduler_grading');
        $this->output = $PAGE->get_renderer('mod_scheduler');

        $this->is_persistent(true);
        $this->scheduler = $scheduler;

        $fields = " sa.id AS appoid, ";
        $fields .= user_picture::fields('u1', ['department']) . ', ';
        $fields .= 'u1.id as userid, ';
        $fields .= "sa.attended,
                    sa.appointmentnote,
                    sa.appointmentnoteformat,
                    sa.teachernote,
                    sa.teachernoteformat,
                    sa.grade,
                    s.name as schedulername,
                    s.id AS schedulerid,
                    s.scale,
                    c.shortname AS courseshort,
                    c.id AS courseid,
                    u2.id as teacherid,
                    ss.id AS sid,
                    ss.starttime,
                    ss.duration";
        $from = " {course} c
                   inner join {scheduler} s on s.course = c.id
                   inner join {scheduler_slots} ss on ss.schedulerid = s.id
                   right join {scheduler_appointment} sa on sa.slotid = ss.id
                   join {user} u1 on u1.id = sa.studentid
                   join {user} u2 on u2.id = ss.teacherid";

        $scopecond = '';
        if ($scope == 'activity') {
            $scopecond = ' AND s.id = :schedulerid';
        } else if ($scope == 'course') {
            $scopecond = ' AND c.id = :courseid';
        }
        $where = " ss.teacherid = :teacherid ".$scopecond;

        $this->set_sql($fields, $from, $where, $params);

        $coursestr = get_string('course', 'scheduler');
        $schedulerstr = get_string('scheduler', 'scheduler');
        $whenstr = get_string('date');
        $whostr = get_string('user');
        $wherefromstr = get_string('department');
        $attendedstr = get_string('attended','scheduler');
        $whatresultedstr = get_string('grade');
        $whathappenedstr = get_string('comments');

        $tablecolumns = array('courseshort', 'schedulername', 'starttime', 'fullname', 'studentdepartment', 'attended', 'grade');
        $tableheaders = array($coursestr, $schedulerstr, $whenstr, $whostr, $wherefromstr, $attendedstr, $whatresultedstr);

        $this->define_columns($tablecolumns);
        $this->define_headers($tableheaders);

        $this->define_baseurl($baseurl);

        $this->sortable(true, 'when'); // Sorted by date by default.
        $this->collapsible(true);      // Allow column hiding.
        $this->initialbars(true);

        $this->column_suppress('courseshort');
        $this->column_suppress('schedulername');
        $this->column_suppress('starttime');
        $this->column_suppress('studentfullname');

        $this->set_attribute('id', 'dates');
        $this->set_attribute('class', 'grade');

        $this->column_class('course', 'grade_course');
        $this->column_class('scheduler', 'grade_scheduler');

        $this->setup();

        $PAGE->requires->yui_module('moodle-mod_scheduler-saveseen',
            'M.mod_scheduler.saveseen.init', array($this->scheduler->cmid) );
        $PAGE->requires->yui_module('moodle-mod_scheduler-savegrade',
            'M.mod_scheduler.savegrade.init', array($this->scheduler->cmid) );
    }

    public function col_courseshort($row) {
        return $row->courseshort;
    }

    public function col_schedulername($row) {
        return $row->schedulername;
    }

    public function col_starttime($row) {
        if ($row->duration) {
            $a = mod_scheduler_renderer::slotdatetime($row->starttime, $row->duration);
            return get_string('slotdatetime', 'scheduler', $a);
        }
        return userdate($row->starttime);
    }

    public function col_appointmentlocation($row) {
        return $row->appointmentlocation;
    }

    public function col_studentdepartment($row) {
        return $row->department;
    }

    public function col_attended($row) {
        $actionurl = new moodle_url('/mod/scheduler/view.php',
            array(
                'what' => 'saveseen',
                'id' => $this->scheduler->cmid,
                'slotid' => $row->sid,
                'sesskey' => sesskey(),
                'subpage' => 'grade'
            )
        );

        $o = '';
        $o .= html_writer::start_tag('form', array('action' => $actionurl,
            'method' => 'post', 'class' => 'studentselectform'));
        $o .= html_writer::start_div();
        $o .= html_writer::checkbox('seen[]', $row->appoid, $row->attended, '',
            array('class' => 'studentselect'));
        $o .= html_writer::end_div();
        $o .= html_writer::empty_tag('input', array(
            'type' => 'submit',
            'class' => 'studentselectsubmit',
            'value' => get_string('saveseen', 'scheduler')
        ));
        $o .= html_writer::end_tag('form');
       return $o;
    }

    public function col_grade($row) {
        $gradechoices = $this->output->grading_choices($this->scheduler);
        if ($this->scheduler->scale != 0) {
            $actionurl = new moodle_url('/mod/scheduler/view.php',
                array(
                    'what' => 'savegrade',
                    'id' => $this->scheduler->cmid,
                    'slotid' => $row->sid,
                    'sesskey' => sesskey(),
                    'subpage' => 'grade'
                )
            );

            $o = '';
            $o .= html_writer::start_tag('form', array('action' => $actionurl,
                'method' => 'post', 'class' => 'studentselectform'));
            $o .= html_writer::start_div();
            $o .= html_writer::select($gradechoices,'grade[]', $row->grade, '',
                array('class' => 'studentselect','id' => 'grade_'.$row->appoid,'data-appid' => $row->appoid));
            $o .= html_writer::end_div();
            $o .= html_writer::empty_tag('input', array(
                'type' => 'submit',
                'class' => 'studentselectsubmit',
                'value' => 'savegrade'
            ));
            $o .= html_writer::end_tag('form');
            return $o;
        }

        return $row->scale == 0 ? '' : $this->output->format_grade($row->scale, $row->grade);
    }

    public function col_appointmentnote($row) {
        return  $this->output->format_appointment_notes($this->scheduler, $row);
    }
}