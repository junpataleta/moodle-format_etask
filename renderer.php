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
 * Renderer for outputting the eTask topics course format.
 *
 * @package format_etask
 * @copyright 2012 Dan Poltawski
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since Moodle 2.3
 */


defined('MOODLE_INTERNAL') || die();

use \format_etask\output\progress_bar;

require_once($CFG->dirroot.'/course/format/renderer.php');
require_once($CFG->dirroot.'/course/format/etask/classes/output/progress_bar.php');

/**
 * Basic renderer for eTask topics format.
 *
 * @copyright 2017 Martin Drlik <martin.drlik@email.cz>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class format_etask_renderer extends format_section_renderer_base
{

    /**
     * @var FormatEtaskLib
     */
    private $etasklib;

    /**
     * @var string
     */
    private $etaskversion;

    /**
     * @var array
     */
    private $config;

    /**
     * Constructor method, calls the parent constructor.
     *
     * @param moodle_page $page
     * @param string $target one of rendering target constants
     */
    public function __construct(moodle_page $page, $target) {
        parent::__construct($page, $target);

        $this->etaskversion = get_config('format_etask', 'version');

        // Since format_etask_renderer::section_edit_controls() only displays the 'Set current section' control
        // when editing mode is on we need to be sure that the link 'Turn editing mode on' is available for a user
        // who does not have any other managing capability.
        $page->set_other_editing_capability('moodle/course:setcurrentsection');
    }

    /**
     * Render progress bar.
     *
     * @param templatable $progressbar
     * @return string
     * @throws moodle_exception
     */
    public function render_progress_bar(templatable $progressbar): string {
        $data = $progressbar->export_for_template($this);
        return $this->render_from_template('format_etask/progress_bar', $data);
    }

    /**
     * Html representaiton of user picture and name with link to user profile.
     *
     * @param stdClass $user
     * @return string
     */
    private function render_user_head(stdClass $user): string {
        $userpicture = $this->output->user_picture($user, [
            'size' => 35,
            'link' => true,
            'popup' => true
        ]);
        $url = new moodle_url('/user/view.php', [
            'id' => $user->id,
            'course' => $this->page->course->id
        ]);

        return $userpicture . ' ' . html_writer::link($url, $user->firstname . ' ' . $user->lastname);
    }

    /**
     * Html representation of activities head.
     *
     * @param grade_item $gradeitem
     * @param int $itemnum
     * @param int $studentscount
     * @param array $progressbardata
     * @param int $cmid
     * @param string $completionexpected
     * @return string
     * @throws coding_exception
     * @throws moodle_exception
     */
    private function render_activities_head(
        grade_item $gradeitem,
        int $itemnum,
        int $studentscount,
        array $progressbardata,
        int $cmid,
        string $completionexpected): string {
        $sesskey = sesskey();
        $sectionreturn = optional_param('sr', 0, PARAM_INT);

        $itemtitleshort = strtoupper(substr($gradeitem->itemmodule, 0, 1)) . $itemnum;
        $gradesettings = $this->render_grade_settings($gradeitem, $this->page->context);

        // Calculate progress bar data count if allowed in cfg.
        $progresscompleted = 0;
        $progresspassed = 0;
        // Calculate progress bars cfg.
        if ($this->config['progressbars'] === true
            || has_capability('format/etask:teacher', $this->page->context)
            || has_capability('format/etask:noneditingteacher', $this->page->context)) {
            // Init porgress bars data.
            $progressbardatainit = [
                'passed' => 0,
                'completed' => 0,
                'failed' => 0
            ];

            $progressbardatacount = array_merge($progressbardatainit, array_count_values($progressbardata));
            $progresscompleted = round(100 * (
                array_sum([
                    $progressbardatacount['completed'],
                    $progressbardatacount['passed'], $progressbardatacount['failed']
                ]) / $studentscount));
            $progresspassed = round(100 * ($progressbardatacount['passed'] / $studentscount));
        }

        // Prepare module icon.
        $ico = html_writer::img($this->output->image_url('icon', $gradeitem->itemmodule), '', [
            'class' => 'item-ico'
        ]);

        // Prepare grade to pass string.
        $duedate = $this->etasklib->get_due_date($gradeitem, $completionexpected);
        $duedatevalue = !empty($duedate) ? $duedate : get_string('notset', 'format_etask');
        $gradetopass = round($gradeitem->gradepass, 0);
        // Get text value of scale.
        if (!empty($gradeitem->scaleid) && !empty($gradetopass)) {
            $scale = $this->etasklib->get_scale($gradeitem->scaleid);
            $gradetopass = $scale[$gradetopass];
        }
        // Switch badge type for grade to pass.
        if (!empty($gradetopass)) {
            $gradetopassvalue = $gradetopass;
            $badgetype = 'success';
        } else {
            $gradetopassvalue = get_string('notset', 'format_etask');
            $badgetype = 'secondary';
        }

        // Prepare due date string.
        $duedatestring = html_writer::div(
            html_writer::tag(
                'i',
                '', [
                    'class' => 'fa fa-calendar-check-o',
                    'area-hidden' => 'true'
                ]
            ) .
            ' ' . get_string('duedate', 'assign') . ':' .
            html_writer::empty_tag('br') .
            html_writer::link('#', $duedatevalue),
            'due-date'
        );

        // Prepare grade to pass string.
        $gradetopassstring = html_writer::div(
            html_writer::tag('i', '', [
                'class' => 'fa fa-graduation-cap',
                'area-hidden' => 'true'
            ]) .
            ' ' . get_string('gradepass', 'grades') . ': ' .
            html_writer::tag('span', $gradetopassvalue, [
                'class' => 'badge badge-pill badge-' . $badgetype
            ]),
            'grade-to-pass'
        );
        // Activity popover string.
        $activitypopoverstring = implode(' ', [$duedatestring, $gradetopassstring]);
        // Activity popover progress bar completed.
        $datacompleted = new progress_bar($progresscompleted, get_string('activitycompleted', 'format_etask'));
        $progressbarcompleted = html_writer::tag('div',
            $this->render($datacompleted),
            ['class' => 'progress-bar-completed pb-1']);
        // Activity popover progress bar passed.
        $datapassed = new progress_bar($progresspassed, get_string('activitypassed', 'format_etask'));
        $progressbarpassed = html_writer::tag('div',
            $this->render($datapassed),
            ['class' => 'progress-bar-passed']);

        // Activity popover progress bars.
        $progressbars = html_writer::div(
            html_writer::div($progressbarcompleted, 'col-xs-12') .
            html_writer::div($progressbarpassed, 'col-xs-12'),
            'row'
        );

        // Prepare activity popover.
        $popover = html_writer::div(
            html_writer::div(
                html_writer::div($progressbars, 'col-xs-5') .
                html_writer::div($activitypopoverstring, 'col-xs-7'),
                'row'),
            'popover-container'
        );

        // Prepare activity short link.
        if (has_capability('format/etask:teacher', $this->page->context)) {
            $itemtitleshortlink = html_writer::link(new moodle_url('/course/mod.php', [
                'sesskey' => $sesskey,
                'sr' => $sectionreturn,
                'update' => $cmid
            ]), $ico . ' ' . $itemtitleshort, [
                'data-toggle' => 'popover',
                'title' => get_string('pluginname', $gradeitem->itemmodule) . ': ' . $gradeitem->itemname,
                'data-content' => $popover
            ]);
        } else {
            $itemtitleshortlink = html_writer::link(new moodle_url('/mod/' . $gradeitem->itemmodule . '/view.php', [
                'id' => $cmid
            ]), $ico . ' ' . $itemtitleshort, [
                'data-toggle' => 'popover',
                'title' => get_string('pluginname', $gradeitem->itemmodule) . ': ' . $gradeitem->itemname,
                'data-content' => $popover
            ]);
        }

        // Prepare grade item head.
        $ret = html_writer::div($itemtitleshortlink . $gradesettings, 'grade-item-container');

        return $ret;
    }

    /**
     * Html representation of grade settings.
     *
     * @param grade_item $gradeitem
     * @param context_course $context
     * @return string
     */
    private function render_grade_settings(grade_item $gradeitem, context_course $context): string {
        $gradesettings = '';

        if ($this->page->user_is_editing() && has_capability('format/etask:teacher', $context)) {
            $ico = html_writer::span($this->output->pix_icon('t/edit', get_string('edit'), 'core'),
                'iconsmall grade-item-dialog pointer',
                ['id' => 'edit-grade-item' . $gradeitem->id]
            );

            $gradesettings = $ico . html_writer::div($this->render_grade_settings_form($gradeitem), 'grade-settings hide', [
                'id' => 'grade-settings-edit-grade-item' . $gradeitem->id
            ]);
        }

        return $gradesettings;
    }

    /**
     * Create grade settings form.
     *
     * @param grade_item $gradeitem
     * @return string
     */
    private function render_grade_settings_form(grade_item $gradeitem): string {
        $action = new moodle_url('/course/view.php', [
            'id' => $this->page->course->id,
            'gradeItemId' => $gradeitem->id
        ]);

        if (!empty($gradeitem->scaleid)) {
            $scale = $this->etasklib->get_scale($gradeitem->scaleid);
        } else {
            $grademax = round($gradeitem->grademax, 0);

            for ($i = $grademax; $i >= 1; --$i) {
                $scale[$i] = $i;
            }
        }

        $formtitle = html_writer::div(get_string('pluginname', $gradeitem->itemmodule) . ': ' . $gradeitem->itemname, 'title');
        $form = new GradeSettingsForm($action->out(false), [
            'gradeItem' => $gradeitem,
            'scale' => $scale
        ]);

        return $formtitle . html_writer::tag('div', $form->render(), [
            'class' => 'grade-settings-form'
        ]);
    }

    /**
     * Create grade table form.
     *
     * @param array $groups
     * @param int $studentscount
     * @param int $selectedgroup
     * @return string
     */
    private function render_grade_table_footer(array $groups, int $studentscount, int $selectedgroup = null): string {
        global $SESSION;

        $page = isset($SESSION->eTask['page']) ? $SESSION->eTask['page'] : 0;
        $action = new moodle_url('/course/view.php', [
            'id' => $this->page->course->id
        ]);
        $formrender = '';
        if (!empty($groups) && (has_capability('format/etask:teacher', $this->page->context)
            || has_capability('format/etask:noneditingteacher', $this->page->context))) {
            $form = new GradeTableForm($action->out(false), [
                'groups' => $groups,
                'selectedGroup' => $selectedgroup
            ]);

            $formrender = $form->render();
        }

        return html_writer::start_tag('div', ['class' => 'row grade-table-footer']) .
                html_writer::div($formrender, 'col-md-4') .
                html_writer::div($this->paging_bar($studentscount, $page, $this->config['studentsperpage'], $action), 'col-md-4') .
                html_writer::div(html_writer::div(
                    get_string('legend', 'format_etask') . ':' . html_writer::tag(
                        'span',
                        get_string('activitycompleted', 'format_etask'), [
                            'class' => 'badge badge-warning completed'
                        ]
                    ) . html_writer::tag('span', get_string('activitypassed', 'format_etask'), [
                        'class' => 'badge badge-success passed'
                    ]) . html_writer::tag('span', get_string('activityfailed', 'format_etask'), [
                        'class' => 'badge badge-danger failed'
                    ]), 'legend'), 'col-md-4') .
                html_writer::end_tag('div');
    }

    /**
     * Html representation of activity body.
     *
     * @param grade_grade $usergrade
     * @param grade_item $gradeitem
     * @param bool $activitycompletionstate
     * @param stdClass $user
     * @return array
     */
    private function render_activity_body(
        grade_grade $usergrade,
        grade_item $gradeitem,
        bool $activitycompletionstate,
        stdClass $user): array {
        $finalgrade = (int) $usergrade->finalgrade;
        $status = $this->etasklib->get_grade_item_status($gradeitem, $finalgrade, $activitycompletionstate);
        if (empty($usergrade->rawscaleid) && !empty($finalgrade)) {
            $gradevalue = $finalgrade;
        } else if (!empty($usergrade->rawscaleid) && !empty($finalgrade)) {
            $scale = $this->etasklib->get_scale($gradeitem->scaleid);
            $gradevalue = $scale[$finalgrade];
        } else if ($status === FormatEtaskLib::STATUS_COMPLETED) {
            $gradevalue = html_writer::tag('i', '', [
                'class' => 'fa fa-check-square-o',
                'area-hidden' => 'true'
            ]);
        } else {
            $gradevalue = '&ndash;';
        }

        if (has_capability('format/etask:teacher', $this->page->context)) {
            $gradelinkparams = [
                'courseid' => $this->page->course->id,
                'id' => $usergrade->id,
                'gpr_type' => 'report',
                'gpr_plugin' => 'grader',
                'gpr_courseid' => $this->page->course->id
            ];

            if (empty($usergrade->id)) {
                $gradelinkparams['userid'] = $user->id;
                $gradelinkparams['itemid'] = $gradeitem->id;
            }

            $gradelink = html_writer::link(new moodle_url('/grade/edit/tree/grade.php', $gradelinkparams), $gradevalue, [
                'class' => 'grade-item-body',
                'title' => $user->firstname . ' ' . $user->lastname . ': ' . $gradeitem->itemname
            ]);
        } else {
            $gradelink = $gradevalue;
        }

        return [
            'text' => $gradelink,
            'status' => $status
        ];
    }

    /**
     * Render flash message.
     *
     * @param array $messagedata
     * @return string
     */
    public function render_message(array $messagedata): string {
        $messagestring = '';
        if (!empty($messagedata)) {
            $closebutton = html_writer::tag(
                'button',
                html_writer::tag('span', '&times;', ['aria-hidden' => 'true']),
                [
                    'type' => 'button',
                    'class' => 'close',
                    'data-dismiss' => 'alert',
                    'aria-label' => get_string('closebuttontitle', 'moodle')
                ]
            );
            $message = $closebutton . $messagedata['message'];
            if ($messagedata['success'] === true) {
                $messagestring = html_writer::div($message, 'alert alert-success', ['data-dismiss' => 'alert']);
            } else {
                $messagestring = html_writer::div($message, 'alert alert-error', ['data-dismiss' => 'alert']);
            }
        }

        return $messagestring;
    }

    /**
     * Render grade table.
     *
     * @param context_course $context
     * @param stdClass $course
     * @param FormatEtaskLib $etasklib
     * @return void
     */
    public function render_grade_table(context_course $context, stdClass $course, FormatEtaskLib $etasklib) {
        global $CFG;
        global $USER;
        global $SESSION;

        echo '
            <style type="text/css" media="screen" title="Graphic layout" scoped>
            <!--
                @import "' . $CFG->wwwroot . '/course/format/etask/format_etask.css?v=' . $this->etaskversion . '";
            -->
            </style>';

        $this->etasklib = $etasklib;
        $this->config = $this->etasklib->get_etask_config($course);

        // Grade pass save message data.
        $gradeitemid = optional_param('gradeItemId', 0, PARAM_INT);
        $messagedata = [];
        if (isset($gradeitemid) && !empty($gradeitemid)) {
            $messagedata = $this->etasklib->update_grade_pass($context, $gradeitemid);
        }

        // Group filter into session.
        $filtergroup = optional_param('eTaskFilterGroup', 0, PARAM_INT);
        if (!empty($filtergroup)) {
            $SESSION->eTask['filtergroup'] = $filtergroup;
        }

        // Pagination page into session.
        $page = optional_param('page', null, PARAM_INT);
        if (!isset($SESSION->eTask['page']) && !isset($page)) {
            $SESSION->eTask['page'] = 0;
        } else if (isset($SESSION->eTask['page']) && isset($page)) {
            $SESSION->eTask['page'] = $page;
        }

        // Get all course groups and selected group to the group filter form.
        $allcoursegroups = $this->etasklib->get_course_groups((int)$course->id);
        $allusergroups = current(groups_get_user_groups($course->id, $USER->id));
        $selectedgroup = null;
        if (has_capability('format/etask:teacher', $context)
            || has_capability('format/etask:noneditingteacher', $context)) {
            if (!empty($SESSION->eTask['filtergroup'])) {
                $selectedgroup = $SESSION->eTask['filtergroup'];
            } else if (!empty($allusergroups)) {
                $selectedgroup = current($allusergroups);
            } else {
                $selectedgroup = key($allcoursegroups);
            }
        }

        // Get mod info and prepare mod items.
        $modinfo = get_fast_modinfo($course);
        $moditems = $this->etasklib->get_mod_items($modinfo);

        // Get all allowed course students.
        $students = $this->etasklib->get_students($context, $course, $selectedgroup);
        // Students count for pagination.
        $studentscount = count($students);
        // Init grade items and students grades.
        $gradeitems = [];
        $usersgrades = [];
        // Collect students grades for all grade items.
        if (!empty($students)) {
            $gradeitems = grade_item::fetch_all(['courseid' => $course->id, 'itemtype' => 'mod', 'hidden' => 0]);
            if ($gradeitems === false) {
                $gradeitems = [];
            }

            if (!empty($gradeitems)) {
                // Grade items num.
                $gradeitemsnum = [];
                foreach ($gradeitems as $gradeitem) {
                    if (!isset($initnum[$gradeitem->itemmodule])) {
                        $initnum[$gradeitem->itemmodule] = 0;
                    }

                    if (!isset($gradeitemsnum[$gradeitem->itemmodule][$gradeitem->iteminstance])) {
                        $gradeitemsnum[$gradeitem->itemmodule][$gradeitem->iteminstance] = ++$initnum[$gradeitem->itemmodule];
                    }
                }

                // Sorting activities by config.
                switch ($this->config['activitiessorting']) {
                    case FormatEtaskLib::ACTIVITIES_SORTING_OLDEST:
                        ksort($gradeitems);
                        break;
                    case FormatEtaskLib::ACTIVITIES_SORTING_INHERIT:
                        $gradeitems = $this->etasklib->sort_grade_items_by_sections($gradeitems, $moditems, $modinfo->sections);
                        break;
                    default:
                        krsort($gradeitems);
                        break;
                }

                foreach ($gradeitems as $gradeitem) {
                    $usersgrades[$gradeitem->id] = grade_grade::fetch_users_grades($gradeitem, array_keys($students), true);
                }
            }
        }

        $this->page->requires->js(
            new moodle_url('/course/format/etask/format_etask.js', ['v' => $this->etaskversion])
        );

        $privateview = false;
        $privateviewuserid = 0;
        // If private view is active, students can view only own grades.
        if ($this->config['privateview'] === true
            && has_capability('format/etask:student', $context)
            && !has_capability('format/etask:teacher', $context)
            && !has_capability('format/etask:noneditingteacher', $context)) {
            $privateview = true;
            $privateviewuserid = $USER->id;
            $studentscount = 1;
        }

        $completion = new completion_info($this->page->course);
        $activitycompletionstates = [];
        $completionexpected = [];
        $data = [];
        $progressbardata = [];
        // Move logged in student at the first position in the grade table.
        if (isset($students[$USER->id]) && $privateview === false) {
            $loggedinstudent = isset($students[$USER->id]) ? $students[$USER->id] : null;
            unset($students[$USER->id]);
            array_unshift($students , $loggedinstudent);
        }
        foreach ($students as $user) {
            $bodycells = [];
            if ($privateview === false || ($privateview === true && $user->id === $privateviewuserid)) {
                $cell = new html_table_cell();
                $cell->text = $this->render_user_head($user);
                $cell->attributes = [
                    'class' => 'user-header'
                ];
                $bodycells[] = $cell;
            }

            foreach ($modinfo->cms as $cm) {
                $completionexpected[$cm->id] = $cm->completionexpected;
                $activitycompletionstates[$cm->id] = (bool) $completion->get_data(
                    $cm, true, $user->id, $modinfo
                )->completionstate;
            }

            foreach ($gradeitems as $gradeitem) {
                $activitycompletionstate = $activitycompletionstates[$moditems[$gradeitem->itemmodule][$gradeitem->iteminstance]];
                $grade = $this->render_activity_body(
                    $usersgrades[$gradeitem->id][$user->id], $gradeitem, $activitycompletionstate, $user
                );
                $progressbardata[$gradeitem->id][] = $grade['status'];
                if ($privateview === false || ($privateview === true && $user->id === $privateviewuserid)) {
                    $cell = new html_table_cell();
                    $cell->text = $grade['text'];
                    $cell->attributes = [
                        'class' => 'grade-item-grade text-center ' . $grade['status'],
                        'title' => $user->firstname . ' ' . $user->lastname . ': ' . $gradeitem->itemname
                    ];
                    $bodycells[] = $cell;
                }
            }

            if ($privateview === false || ($privateview === true && $user->id === $privateviewuserid)) {
                $row = new html_table_row($bodycells);
                $data[] = $row;
            }
        }
        // Table head.
        $headcells = ['']; // First cell of the head is empty.
        // Render table cells.
        foreach ($gradeitems as $gradeitem) {
            $cmid = (int) $moditems[$gradeitem->itemmodule][$gradeitem->iteminstance];
            $cell = new html_table_cell();
            $cell->text = $this->render_activities_head(
                $gradeitem,
                $gradeitemsnum[$gradeitem->itemmodule][$gradeitem->iteminstance],
                count($students),
                $progressbardata[$gradeitem->id],
                $cmid,
                $completionexpected[$cmid]);
            $cell->attributes = [
                'class' => 'grade-item-header center '
            ];
            $headcells[] = $cell;
        }

        // Slice of students by paging after geting progres bar data.
        $SESSION->eTask['page'] = $studentscount <= $SESSION->eTask['page'] * $this->config['studentsperpage']
            ? 0
            : $SESSION->eTask['page'];
        $data = array_slice(
            $data,
            $SESSION->eTask['page'] * $this->config['studentsperpage'],
            $this->config['studentsperpage'],
            true
        );

        // Html table.
        $gradetable = new html_table();
        $gradetable->attributes = [
            'class' => 'grade-table table-hover table-striped table-condensed table-responsive',
            'table-layout' => 'fixed'
        ];
        $gradetable->head = $headcells;
        $gradetable->data = $data;

        // Grade table footer: groups filter, pagination and legend.
        $gradetablefooter = $this->render_grade_table_footer($allcoursegroups, $studentscount, $selectedgroup);

        echo html_writer::div(
            $this->render_message($messagedata) . html_writer::table($gradetable) . $gradetablefooter,
            'etask-grade-table ' . $this->config['placement']
        );
    }

    /**
     * Generate the starting container html for a list of sections.
     * @return string HTML to output.
     */
    protected function start_section_list() {
        return html_writer::start_tag('ul', array('class' => 'topics'));
    }

    /**
     * Generate the closing container html for a list of sections.
     * @return string HTML to output.
     */
    protected function end_section_list() {
        return html_writer::end_tag('ul');
    }

    /**
     * Generate the title for this section page.
     * @return string the page title
     */
    protected function page_title() {
        return get_string('topicoutline');
    }

    /**
     * Generate the section title, wraps it in a link to the section page if page is to be displayed on a separate page.
     *
     * @param section_info $section The course_section entry from DB
     * @param stdClass $course The course entry from DB
     * @return string HTML to output.
     */
    public function section_title($section, $course) {
        return $this->render(course_get_format($course)->inplace_editable_render_section_name($section));
    }

    /**
     * Generate the section title to be displayed on the section page, without a link.
     *
     * @param stdClass $section The course_section entry from DB
     * @param stdClass $course The course entry from DB
     * @return string HTML to output.
     */
    public function section_title_without_link($section, $course) {
        return $this->render(course_get_format($course)->inplace_editable_render_section_name($section, false));
    }

    /**
     * Generate the edit control items of a section.
     *
     * @param stdClass $course The course entry from DB
     * @param section_info $section The course_section entry from DB
     * @param bool $onsectionpage true if being printed on a section page
     * @return array of edit control items
     */
    protected function section_edit_control_items($course, $section, $onsectionpage = false) {
        global $PAGE;

        if (!$PAGE->user_is_editing()) {
            return array();
        }

        $coursecontext = context_course::instance($course->id);

        if ($onsectionpage) {
            $url = course_get_url($course, $section->section);
        } else {
            $url = course_get_url($course);
        }
        $url->param('sesskey', sesskey());

        $controls = array();
        if ($section->section && has_capability('moodle/course:setcurrentsection', $coursecontext)) {
            if ($course->marker == $section->section) {  // Show the "light globe" on/off.
                $url->param('marker', 0);
                $highlightoff = get_string('highlightoff');
                $controls['highlight'] = array('url' => $url, "icon" => 'i/marked',
                                               'name' => $highlightoff,
                                               'pixattr' => array('class' => ''),
                                               'attr' => array('class' => 'editing_highlight',
                                                   'data-action' => 'removemarker'));
            } else {
                $url->param('marker', $section->section);
                $highlight = get_string('highlight');
                $controls['highlight'] = array('url' => $url, "icon" => 'i/marker',
                                               'name' => $highlight,
                                               'pixattr' => array('class' => ''),
                                               'attr' => array('class' => 'editing_highlight',
                                                   'data-action' => 'setmarker'));
            }
        }

        $parentcontrols = parent::section_edit_control_items($course, $section, $onsectionpage);

        // If the edit key exists, we are going to insert our controls after it.
        if (array_key_exists("edit", $parentcontrols)) {
            $merged = array();
            // We can't use splice because we are using associative arrays.
            // Step through the array and merge the arrays.
            foreach ($parentcontrols as $key => $action) {
                $merged[$key] = $action;
                if ($key == "edit") {
                    // If we have come to the edit key, merge these controls here.
                    $merged = array_merge($merged, $controls);
                }
            }

            return $merged;
        } else {
            return array_merge($controls, $parentcontrols);
        }
    }
}
