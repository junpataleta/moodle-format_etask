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
 * This file contains the forms to create and edit an instance of this module
 *
 * @package   format_etask
 * @copyright 2013 Martin Drlik
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();
require_once("$CFG->libdir/formslib.php");

/**
 * Grade settings form.
 *
 * @package     format_etask
 * @copyright   2017 Martin Drlik <martin.drlik@email.cz>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class GradeTableForm extends moodleform
{

    /**
     * Called to define this moodle form.
     *
     * @return void
     */
    public function definition()
    {
        $groups = $this->_customdata['groups'];
        $selected = $this->_customdata['selectedGroup'];

        $mForm =& $this->_form; // Don't forget the underscore.
        $mForm->updateAttributes(['class' => 'inline-form groups']);

        // Select element.
        $select = $mForm->addElement(
            'select',
            'eTaskFilterGroup',
            get_string('group') . ':',
            $groups,
            ['onchange' => 'this.form.submit();']);
        $select->setSelected($selected);
        $mForm->disable_form_change_checker();
    }
}
