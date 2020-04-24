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
require_once($CFG->dirroot.'/course/format/renderer.php');

/**
 * Basic renderer for eTask topics format.
 *
 * @copyright 2017 Martin Drlik <martin.drlik@email.cz>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class format_etask_renderer extends format_section_renderer_base
{

    /**
     *
     * @var FormatEtaskLib
     */
    private $etaskLib;

    /**
     *
     * @var array
     */
    private $config;

    /**
     * Constructor method, calls the parent constructor.
     *
     * @param moodle_page $page
     * @param string $target one of rendering target constants
     */
    public function __construct(moodle_page $page, $target)
    {
        parent::__construct($page, $target);

        // Since format_etask_renderer::section_edit_controls() only displays the 'Set current section' control
        // when editing mode is on we need to be sure that the link 'Turn editing mode on' is available for a user
        // who does not have any other managing capability.
        $page->set_other_editing_capability('moodle/course:setcurrentsection');
    }

    /**
     * Html representaiton of user picture and name with link to user profile.
     *
     * @param stdClass $user
     * @return string
     */
    private function renderUserHead(stdClass $user)
    {
        $userPicture = $this->output->user_picture($user, [
            'size' => 35,
            'link' => true,
            'popup' => true
        ]);
        $url = new moodle_url('/user/view.php', [
            'id' => $user->id,
            'course' => $this->page->course->id
        ]);

        return $userPicture . ' ' . html_writer::link($url, $user->firstname . ' ' . $user->lastname);
    }

    /**
     * Html representation of activities head.
     *
     * @param grade_item $gradeItem
     * @param int $itemNum
     * @param int $studentsCount
     * @param array $progressChartData
     * @param int $cmId
     * @param array $completionExpected
     * @return string
     */
    private function renderActivitiesHead(
        grade_item $gradeItem,
        $itemNum,
        $studentsCount,
        array $progressChartData,
        $cmId,
        $completionExpected)
    {
        $sessKey = sesskey();
        $sectionReturn = optional_param('sr', 0, PARAM_INT);

        $itemTitleShort = strtoupper(substr($gradeItem->itemmodule, 0, 1)) . $itemNum;
        $gradeSettings = $this->renderGradeSettings($gradeItem, $this->page->context);

        // Calculate progress chart data count if allowed in cfg.
        $progressCompleted = 0;
        $progressPassed = 0;
        // Calculate progress charts cfg.
        if ($this->config['progresscharts'] === true
            || has_capability('format/etask:teacher', $this->page->context)
            || has_capability('format/etask:noneditingteacher', $this->page->context)) {
            // Init porgress charts data.
            $progressChartDataInit = [
                'passed' => 0,
                'completed' => 0,
                'failed' => 0
            ];

            $progressChartDataCount = array_merge($progressChartDataInit, array_count_values($progressChartData));
            $progressCompleted = round(100 * (
                array_sum([
                    $progressChartDataCount['completed'],
                    $progressChartDataCount['passed'], $progressChartDataCount['failed']
                ]) / $studentsCount));
            $progressPassed = round(100 * ($progressChartDataCount['passed'] / $studentsCount));
        }
        $progressChartCompletedVisibilityClass = empty($progressCompleted) ? 'disabled' : 'enabled';
        $progressChartPassedVisibilityClass = empty($progressPassed) ? 'disabled' : 'enabled';

        // Prepare module icon.
        $ico = html_writer::img($this->output->pix_url('icon', $gradeItem->itemmodule), '', [
            'class' => 'item-ico'
        ]);

        // Prepare grade to pass string.
        $dueDate = $this->etaskLib->getDueDate($gradeItem, $completionExpected);
        $dueDateValue = !empty($dueDate) ? $dueDate : get_string('notset', 'format_etask');
        $gradeToPass = round($gradeItem->gradepass, 0);
        // Get text value of scale.
        if (!empty($gradeItem->scaleid) && !empty($gradeToPass)) {
            $scale = $this->etaskLib->getScale($gradeItem->scaleid);
            $gradeToPass = $scale[$gradeToPass];
        }
        // Switch badge type for grade to pass.
        if (!empty($gradeToPass)) {
            $gradeToPassValue = $gradeToPass;
            $badgeType = 'success';
        } else {
            $gradeToPassValue = get_string('notset', 'format_etask');
            $badgeType = 'secondary';
        }

        // Prepare due date string.
        $dueDateString = html_writer::div(
            html_writer::img($this->output->pix_url('i/calendar', 'core'), '') .
            ' ' . get_string('duedate', 'assign') . ':' .
            html_writer::empty_tag('br') .
            html_writer::link('#', $dueDateValue),
            'due-date'
        );

        // Prepare grade to pass string.
        $gradeToPassString = html_writer::div(
            html_writer::img($this->output->pix_url('i/checkpermissions', 'core'), '') .
            ' ' . get_string('gradepass', 'grades') . ': ' .
            html_writer::tag('span', $gradeToPassValue, [
                'class' => 'badge badge-pill badge-' . $badgeType
            ]),
            'grade-to-pass'
        );
        // Activity popover string.
        $activityPopoverString = implode(' ', [$dueDateString, $gradeToPassString]);
        // Activity popover progress chart completed.
        $progressChartCompleted = html_writer::div(
            html_writer::div('', 'bar', ['style' => 'width: ' . $progressCompleted . '%']),
            'progress-chart-completed progress progress-striped progress-warning active'
            ) . html_writer::div($progressCompleted . '%', 'progress-chart-completed ' . $progressChartCompletedVisibilityClass);

        // Activity popover progress chart passed.
        $progressChartPassed = html_writer::div(
            html_writer::div('', 'bar', ['style' => 'width: ' . $progressPassed . '%']),
            'progress-chart-passed progress progress-striped progress-success active'
            ) . html_writer::div($progressPassed . '%', 'progress-chart-passed ' . $progressChartPassedVisibilityClass);

        // Activity popover progress charts.
        $progressCharts = html_writer::div(
            html_writer::div($progressChartCompleted, 'span12') .
            html_writer::div($progressChartPassed, 'span12'),
            'row-fluid'
        );

        // Prepare activity popover.
        $popover = html_writer::div(
            html_writer::div(
                html_writer::div($progressCharts, 'span5') .
                html_writer::div($activityPopoverString, 'span7'),
                'row-fluid'),
            'popover-container'
        );

        // Prepare activity short link.
        if (has_capability('format/etask:teacher', $this->page->context)) {
            $itemTitleShortLink = html_writer::link(new moodle_url('/course/mod.php', [
                'sesskey' => $sessKey,
                'sr' => $sectionReturn,
                'update' => $cmId
            ]), $ico . ' ' . $itemTitleShort, [
                'data-toggle' => 'popover',
                'title' => get_string('pluginname', $gradeItem->itemmodule) . ': ' . $gradeItem->itemname,
                'data-content' => $popover
            ]);
        } else {
            $itemTitleShortLink = html_writer::link(new moodle_url('/mod/' . $gradeItem->itemmodule . '/view.php', [
                'id' => $cmId
            ]), $ico . ' ' . $itemTitleShort, [
                'data-toggle' => 'popover',
                'title' => get_string('pluginname', $gradeItem->itemmodule) . ': ' . $gradeItem->itemname,
                'data-content' => $popover
            ]);
        }

        // Prepare grade item head.
        $ret = html_writer::div($itemTitleShortLink . $gradeSettings, 'grade-item-container');

        return $ret;
    }

    /**
     * Html representation of grade settings.
     *
     * @param grade_item $gradeItem
     * @param context_course $context
     * @return string
     */
    private function renderGradeSettings(grade_item $gradeItem, context_course $context)
    {
        $gradeSettings = '';

        if ($this->page->user_is_editing() && has_capability('format/etask:teacher', $context)) {
            $ico = html_writer::img($this->output->pix_url('t/edit', 'core'), '', [
                'class' => 'grade-item-dialog pointer',
                'id' => 'edit-grade-item' . $gradeItem->id
            ]);

            $gradeSettings = $ico . $this->renderGradeSettingsForm($gradeItem);
        }

        return $gradeSettings;
    }

    /**
     * Create grade settings form.
     *
     * @param grade_item $gradeItem
     * @return string
     */
    private function renderGradeSettingsForm(grade_item $gradeItem)
    {
        $action = new moodle_url('/course/view.php', [
            'id' => $this->page->course->id,
            'gradeItemId' => $gradeItem->id
        ]);

        if (!empty($gradeItem->scaleid)) {
            $scale = $this->etaskLib->getScale($gradeItem->scaleid);
        } else {
            $gradeMax = round($gradeItem->grademax, 0);

            for ($i = $gradeMax; $i >= 1; --$i) {
                $scale[$i] = $i;
            }
        }

        $form = new GradeSettingsForm($action->out(false), [
            'gradeItem' => $gradeItem,
            'scale' => $scale
        ]);

        // Prepare modal.
        $modal = html_writer::div(
            html_writer::div(
                html_writer::div(
                    html_writer::div(
                        html_writer::tag('button',
                            html_writer::span('&times;', '', ['aria-hidden' => 'true']), [
                                'type' => 'button',
                                'class' => 'close',
                                'data-dismiss' => 'modal',
                                'aria-label' => get_string('closebuttontitle', 'moodle')
                            ]
                        ) . html_writer::tag('h4',
                            get_string('pluginname', $gradeItem->itemmodule) . ': ' . $gradeItem->itemname, [
                                'class' => 'modal-title'
                            ]
                        ),
                        'modal-header'
                    ) . html_writer::div(
                        $form->render(),
                        'modal-body'
                    ) . html_writer::div(
                        html_writer::tag('button',
                            get_string('savechanges', 'moodle'), [
                                'type' => 'button',
                                'class' => 'btn btn-primary',
                                'data-dismiss' => 'modal'
                            ]
                        ) . html_writer::tag('button',
                            get_string('cancel', 'moodle'), [
                                'type' => 'button',
                                'class' => 'btn btn-secondary',
                                'data-dismiss' => 'modal'
                            ]
                        ),
                        'modal-footer'
                    ),
                    'modal-content'
                ),
                'modal-dialog', [
                    'role' => 'document'
                ]
            ),
            'modal moodle-has-zindex hide', [
                'aria-hidden' => 'true',
                'role' => 'dialog',
                'id' => 'grade-settings-edit-grade-item' . $gradeItem->id
            ]
        );

        return html_writer::tag('div', $modal, [
            'class' => 'grade-settings-form'
        ]);
    }

    /**
     * Create grade table form.
     *
     * @param array $groups
     * @param int $studentsCount
     * @param int $selectedGroup
     * @return string
     */
    private function renderGradeTableFooter(array $groups, $studentsCount, $selectedGroup = null)
    {
        global $SESSION;

        $page = isset($SESSION->eTask['page']) ? $SESSION->eTask['page'] : 0;
        $action = new moodle_url('/course/view.php', [
            'id' => $this->page->course->id
        ]);
        $formRender = '';
        if (!empty($groups) && (has_capability('format/etask:teacher', $this->page->context)
            || has_capability('format/etask:noneditingteacher', $this->page->context))) {
            $form = new GradeTableForm($action->out(false), [
                'groups' => $groups,
                'selectedGroup' => $selectedGroup
            ]);

            $formRender = $form->render();
        }

        return html_writer::start_tag('div', ['class' => 'row-fluid grade-table-footer']) .
                html_writer::div($formRender, 'span4') .
                html_writer::div($this->paging_bar($studentsCount, $page, $this->config['studentsperpage'], $action), 'span4 text-center') .
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
                    ]), 'legend'), 'span4') .
                html_writer::end_tag('div');
    }

    /**
     * Html representation of activity body.
     *
     * @param grade_grade $userGrade
     * @param grade_item $gradeItem
     * @param bool $activityCompletionState
     * @param stdClass $user
     * @return array
     */
    private function renderActivityBody(
        grade_grade $userGrade,
        grade_item $gradeItem,
        $activityCompletionState,
        stdClass $user)
    {
        $sectionReturn = optional_param('sr', 0, PARAM_INT);

        $finalGrade = (int) $userGrade->finalgrade;
        $status = $this->etaskLib->getGradeItemStatus($gradeItem, $finalGrade, $activityCompletionState);
        if (empty($userGrade->rawscaleid) && !empty($finalGrade)) {
            $gradeValue = $finalGrade;
        } elseif (!empty($userGrade->rawscaleid) && !empty($finalGrade)) {
            $scale = $this->etaskLib->getScale($gradeItem->scaleid);
            $gradeValue = $scale[$finalGrade];
        } elseif ($status === FormatEtaskLib::STATUS_COMPLETED) {
            $gradeValue = html_writer::img($this->output->pix_url('i/fa-check-square-o', 'format_etask'), '');
        } else {
            $gradeValue = '&ndash;';
        }

        if (has_capability('format/etask:teacher', $this->page->context)) {
            $gradeLinkParams = [
                'courseid' => $this->page->course->id,
                'id' => $userGrade->id,
                'gpr_type' => 'report',
                'gpr_plugin' => 'grader',
                'gpr_courseid' => $this->page->course->id
            ];

            if (empty($userGrade->id)) {
                $gradeLinkParams['userid'] = $user->id;
                $gradeLinkParams['itemid'] = $gradeItem->id;
            }

            $gradeLink = html_writer::link(new moodle_url('/grade/edit/tree/grade.php', $gradeLinkParams), $gradeValue, [
                'class' => 'grade-item-body',
                'title' => $user->firstname . ' ' . $user->lastname . ': ' . $gradeItem->itemname
            ]);
        } else {
            $gradeLink = $gradeValue;
        }

        return [
            'text' => $gradeLink,
            'status' => $status
        ];
    }

    /**
     * Render flash message.
     *
     * @param array $messageData
     * @return string
     */
    public function renderMessage(array $messageData)
    {
        $messageString = '';
        if (!empty($messageData)) {
            $closebutton = html_writer::tag(
                'button',
                html_writer::tag('span', '&times;', ['aria-hidden' => 'true']),
                ['type' => 'button', 'class' => 'close', 'data-dismiss' => 'alert', 'aria-label' => get_string('closebuttontitle', 'moodle')]
            );
            $message = $closebutton . $messageData['message'];
            if ($messageData['success'] === true) {
                $messageString = html_writer::div($message, 'alert alert-success', ['data-dismiss' => 'alert']);
            } else {
                $messageString = html_writer::div($message, 'alert alert-error', ['data-dismiss' => 'alert']);
            }
        }

        return $messageString;
    }

    /**
     * Render grade table.
     *
     * @param context_course $context
     * @param stdClass $course
     * @param FormatEtaskLib $etaskLib
     * @return void
     */
    public function renderGradeTable(context_course $context, stdClass $course, FormatEtaskLib $etaskLib)
    {
        global $CFG;
        global $USER;
        global $SESSION;

        // Set wwwroot.
        $wwwroot = !empty($CFG->httpswwwroot) ? $CFG->httpswwwroot : $CFG->wwwroot;

        echo '
            <style type="text/css" media="screen" title="Graphic layout" scoped>
            <!--
                @import "' . $wwwroot . '/course/format/etask/format_etask.css?v=' . get_config('format_etask', 'version') . '";
            -->
            </style>';

        $this->etaskLib = $etaskLib;
        $this->config = $this->etaskLib->getEtaskConfig($course);

        // Grade pass save message data.
        $gradeItemId = optional_param('gradeItemId', 0, PARAM_INT);
        $messageData = [];
        if (isset($gradeItemId) && !empty($gradeItemId)) {
            $messageData = $this->etaskLib->updateGradePass($context, $gradeItemId);
        }

        // Group filter into session.
        $filterGroup = optional_param('eTaskFilterGroup', 0, PARAM_INT);
        if (!empty($filterGroup)) {
            $SESSION->eTask['filtergroup'] = $filterGroup;
        }

        // Pagination page into session.
        $page = optional_param('page', null, PARAM_INT);
        if (!isset($SESSION->eTask['page']) && !isset($page)) {
            $SESSION->eTask['page'] = 0;
        } elseif (isset($SESSION->eTask['page']) && isset($page)) {
            $SESSION->eTask['page'] = $page;
        }

        // Get all course groups and selected group to the group filter form.
        $allCourseGroups = $this->etaskLib->getCourseGroups((int)$course->id);
        $allUserGroups = current(groups_get_user_groups($course->id, $USER->id));
        $selectedGroup = null;
        if (has_capability('format/etask:teacher', $context)
            || has_capability('format/etask:noneditingteacher', $context)) {
            if (!empty($SESSION->eTask['filtergroup'])) {
                $selectedGroup = $SESSION->eTask['filtergroup'];
            } elseif (!empty($allUserGroups)) {
                $selectedGroup = current($allUserGroups);
            } else {
                $selectedGroup = key($allCourseGroups);
            }
        }

        // Get mod info and prepare mod items.
        $modInfo = get_fast_modinfo($course);
        $modItems = $this->etaskLib->getModItems($modInfo);

        // Get all allowed course students.
        $students = $this->etaskLib->getStudents($context, $course, $selectedGroup);
        // Students count for pagination.
        $studentsCount = count($students);
        // Init grade items and students grades.
        $gradeItems = [];
        $usersGrades = [];
        // Collect students grades for all grade items.
        if (!empty($students)) {
            $gradeItems = grade_item::fetch_all(['courseid' => $course->id, 'itemtype' => 'mod', 'hidden' => 0]);
            if ($gradeItems === false) {
                $gradeItems = [];
            }

            if (!empty($gradeItems)) {
                // Grade items num.
                $gradeItemsNum = [];
                foreach ($gradeItems as $gradeItem) {
                    if (!isset($initNum[$gradeItem->itemmodule])) {
                        $initNum[$gradeItem->itemmodule] = 0;
                    }

                    if (!isset($gradeItemsNum[$gradeItem->itemmodule][$gradeItem->iteminstance])) {
                        $gradeItemsNum[$gradeItem->itemmodule][$gradeItem->iteminstance] = ++$initNum[$gradeItem->itemmodule];
                    }
                }

                // Sorting activities by config.
                switch ($this->config['activitiessorting']) {
                    case FormatEtaskLib::ACTIVITIES_SORTING_OLDEST:
                        ksort($gradeItems);
                        break;
                    case FormatEtaskLib::ACTIVITIES_SORTING_INHERIT:
                        $gradeItems = $this->etaskLib->sortGradeItemsBySections($gradeItems, $modItems, $modInfo->sections);
                        break;
                    default:
                        krsort($gradeItems);
                        break;
                }

                foreach ($gradeItems as $gradeItem) {
                    $usersGrades[$gradeItem->id] = grade_grade::fetch_users_grades($gradeItem, array_keys($students), true);
                }
            }
        }

        $this->page->requires->js(new moodle_url('/course/format/etask/format_etask.js', ['v' => get_config('format_etask', 'version')]));

        $privateView = false;
        $privateViewUserId = 0;
        // If private view is active, students can view only own grades.
        if ($this->config['privateview'] === true
            && has_capability('format/etask:student', $context)
            && !has_capability('format/etask:teacher', $context)
            && !has_capability('format/etask:noneditingteacher', $context)) {
            $privateView = true;
            $privateViewUserId = $USER->id;
            $studentsCount = 1;
        }

        $completion = new completion_info($this->page->course);
        $activityCompletionStates = [];
        $completionExpected = [];
        $data = [];
        $progressChartData = [];
        // Move logged in student at the first position in the grade table.
        if (isset($students[$USER->id]) && $privateView === false) {
            $loggedInStudent = isset($students[$USER->id]) ? $students[$USER->id] : null;
            unset($students[$USER->id]);
            array_unshift($students , $loggedInStudent);
        }
        foreach ($students as $user) {
            $bodyCells = [];
            if ($privateView === false || ($privateView === true && $user->id === $privateViewUserId)) {
                $cell = new html_table_cell();
                $cell->text = $this->renderUserHead($user);
                $cell->attributes = [
                    'class' => 'user-header'
                ];
                $bodyCells[] = $cell;
            }

            foreach ($modInfo->cms as $cm) {
                $completionExpected[$cm->id] = $cm->completionexpected;
                $activityCompletionStates[$cm->id] = (bool) $completion->get_data($cm, true, $user->id, $modInfo)->completionstate;
            }

            foreach ($gradeItems as $gradeItem) {
                $activityCompletionState = $activityCompletionStates[$modItems[$gradeItem->itemmodule][$gradeItem->iteminstance]];
                $grade = $this->renderActivityBody($usersGrades[$gradeItem->id][$user->id], $gradeItem, $activityCompletionState, $user);
                $progressChartData[$gradeItem->id][] = $grade['status'];
                if ($privateView === false || ($privateView === true && $user->id === $privateViewUserId)) {
                    $cell = new html_table_cell();
                    $cell->text = $grade['text'];
                    $cell->attributes = [
                        'class' => 'grade-item-grade text-center ' . $grade['status'],
                        'title' => $user->firstname . ' ' . $user->lastname . ': ' . $gradeItem->itemname
                    ];
                    $bodyCells[] = $cell;
                }
            }

            if ($privateView === false || ($privateView === true && $user->id === $privateViewUserId)) {
                $row = new html_table_row($bodyCells);
                $data[] = $row;
            }
        }
        // Table head.
        $headCells = ['']; // First cell of the head is empty.
        // Render table cells.
        foreach ($gradeItems as $gradeItem) {
            $cmId = (int) $modItems[$gradeItem->itemmodule][$gradeItem->iteminstance];
            $cell = new html_table_cell();
            $cell->text = $this->renderActivitiesHead(
                $gradeItem,
                $gradeItemsNum[$gradeItem->itemmodule][$gradeItem->iteminstance],
                count($students),
                $progressChartData[$gradeItem->id],
                $cmId,
                $completionExpected[$cmId]);
            $cell->attributes = [
                'class' => 'grade-item-header center '
            ];
            $headCells[] = $cell;
        }

        // Slice of students by paging after geting progres chart data.
        $SESSION->eTask['page'] = $studentsCount <= $SESSION->eTask['page'] * $this->config['studentsperpage'] ? 0 : $SESSION->eTask['page'];
        $data = array_slice($data, $SESSION->eTask['page'] * $this->config['studentsperpage'], $this->config['studentsperpage'], $preserve_keys = true);

        // Html table.
        $gradeTable = new html_table();
        $gradeTable->attributes = [
            'class' => 'grade-table table-hover table-striped table-condensed table-responsive',
            'table-layout' => 'fixed'
        ];
        $gradeTable->head = $headCells;
        $gradeTable->data = $data;

        // Grade table footer: groups filter, pagination and legend.
        $gradeTableFooter = $this->renderGradeTableFooter($allCourseGroups, $studentsCount, $selectedGroup);

        echo html_writer::div(
            $this->renderMessage($messageData) . html_writer::div(html_writer::table($gradeTable), 'table-responsive') . $gradeTableFooter,
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
     * Generate the edit controls of a section
     *
     * @param stdClass $course The course entry from DB
     * @param stdClass $section The course_section entry from DB
     * @param bool $onsectionpage true if being printed on a section page
     * @return array of links with edit controls
     */
    protected function section_edit_controls($course, $section, $onsectionpage = false) {
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

        $isstealth = $section->section > $course->numsections;
        $controls = array();
        if (!$isstealth && has_capability('moodle/course:setcurrentsection', $coursecontext)) {
            if ($course->marker == $section->section) {  // Show the "light globe" on/off.
                $url->param('marker', 0);
                $controls[] = html_writer::link($url,
                                    html_writer::empty_tag('img', array('src' => $this->output->pix_url('i/marked'),
                                        'class' => 'icon ', 'alt' => get_string('markedthistopic'))),
                                    array('title' => get_string('markedthistopic'), 'class' => 'editing_highlight'));
            } else {
                $url->param('marker', $section->section);
                $controls[] = html_writer::link($url,
                                html_writer::empty_tag('img', array('src' => $this->output->pix_url('i/marker'),
                                    'class' => 'icon', 'alt' => get_string('markthistopic'))),
                                array('title' => get_string('markthistopic'), 'class' => 'editing_highlight'));
            }
        }

        return array_merge($controls, parent::section_edit_controls($course, $section, $onsectionpage));
    }
}
