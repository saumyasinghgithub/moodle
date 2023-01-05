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
 * Sets up the tabs used by the svasu pages based on the users capabilities.
 *
 * @author Dan Marsden and others.
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 * @package mod_svasu
 */

if (empty($svasu)) {
    print_error('cannotaccess', 'mod_svasu');
}
if (!isset($currenttab)) {
    $currenttab = '';
}
$tabs = array();
$row = array();
$inactive = array();
$activated = array();

$scoesurl = new moodle_url('/mod/svasu/report/userreport.php', array('id' => $id,
    'user' => $userid,
    'attempt' => $attempt));

$interactionssurl = new moodle_url('/mod/svasu/report/userreportinteractions.php', array('id' => $id,
    'user' => $userid,
    'attempt' => $attempt));
$row[] = new tabobject('scoes', $scoesurl, get_string('scoes', 'svasu'));
$row[] = new tabobject('interactions', $interactionssurl, get_string('interactions', 'svasu'));

$tabs[] = $row;
print_tabs($tabs, $currenttab, $inactive, $activated);
