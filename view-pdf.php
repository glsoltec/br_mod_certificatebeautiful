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
 * view PDF file
 *
 * @package   mod_certificatebeautiful
 * @copyright 2025 Eduardo Kraus https://eduardokraus.com/
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use mod_certificatebeautiful\issue;
use mod_certificatebeautiful\pdf\page_pdf;
use mod_certificatebeautiful\vo\certificatebeautiful;
use mod_certificatebeautiful\vo\certificatebeautiful_issue;
use mod_certificatebeautiful\vo\certificatebeautiful_model;

// phpcs:disable moodle.Files.MoodleInternal.MoodleInternalGlobalState
global $PAGE, $CFG, $DB, $USER;

require_once('../../config.php');
require_once("{$CFG->libdir}/tablelib.php");

ob_start();

$code = required_param("code", PARAM_TEXT);
$action = required_param("action", PARAM_TEXT);

$token = optional_param("token", false, PARAM_TEXT);
if ($token) {
    $extservice = $DB->get_record("external_services", ["shortname" => MOODLE_OFFICIAL_MOBILE_SERVICE]);
    $exttoken = $extservice
        ? $DB->get_record("external_tokens", ["token" => $token, "externalserviceid" => $extservice->id], "userid")
        : false;
    if ($exttoken) {
        $tokenuser = $DB->get_record("user", ["id" => $exttoken->userid]);
        if ($tokenuser) {
            \core\session\manager::login_user($tokenuser);
        }
    }
}

if ($action == "createadmin") {
    $userid = required_param("userid", PARAM_INT);
    $user = $DB->get_record("user", ["id" => $userid], '*', MUST_EXIST);

    $cmid = required_param("cmid", PARAM_INT);
    $cm = get_coursemodule_from_id("certificatebeautiful", $cmid, 0, false, MUST_EXIST);

    $context = context_module::instance($cm->id);
    require_login();
    require_capability('mod/certificatebeautiful:addinstance', $context);

    $certificatebeautiful = $DB->get_record("certificatebeautiful", ["id" => $cm->instance], '*', MUST_EXIST);
    $certificatebeautifulissue = issue::get($user, $certificatebeautiful, $cm);
} else {
    /** @var certificatebeautiful_issue $certificatebeautifulissue */
    $certificatebeautifulissue = $DB->get_record("certificatebeautiful_issue", ["code" => $code], '*', MUST_EXIST);

    $cm = get_coursemodule_from_id("certificatebeautiful", $certificatebeautifulissue->cmid, 0, false, MUST_EXIST);
    $certificatebeautiful = $DB->get_record("certificatebeautiful", ["id" => $cm->instance], '*', MUST_EXIST);
    $user = $DB->get_record("user", ["id" => $certificatebeautifulissue->userid], '*', MUST_EXIST);
    $context = context_module::instance($cm->id);
}

$course = $DB->get_record("course", ["id" => $cm->course], '*', MUST_EXIST);

if ($token) {
    require_course_login($cm->course, false, null, false, true);
} else {
    require_course_login($cm->course);
}
require_capability('mod/certificatebeautiful:view', $context);
if ($action !== 'createadmin' && $USER->id != $certificatebeautifulissue->userid) {
    require_capability('mod/certificatebeautiful:addinstance', $context);
}

if ($token) {
    $auditaction = 'token_view';
} elseif ($action === 'download') {
    $auditaction = 'download';
} else {
    $auditaction = 'view';
}

$username = fullname($user);
$name = "{$certificatebeautiful->name} - {$username}.pdf";

$fs = get_file_storage();
$filerecord = (object)[
    "component" => "mod_certificatebeautiful",
    "contextid" => $context->id,
    "userid" => $user->id,
    "filearea" => "certificate",
    "filepath" => "/",
    "itemid" => $user->id,
    "filename" => "{$certificatebeautifulissue->code}.pdf",
];

/** @var certificatebeautiful_model $certificatebeautifulmodel */
$certificatebeautifulmodel = $DB->get_record("certificatebeautiful_model",
    ["id" => $certificatebeautiful->model], "*", MUST_EXIST);

$storedfile = $fs->get_file(
    $filerecord->contextid, $filerecord->component,
    $filerecord->filearea, $filerecord->itemid,
    $filerecord->filepath, $filerecord->filename);

if ($certificatebeautiful->timemodified != $certificatebeautifulissue->version) {
    if ($storedfile) {
        $storedfile->delete();
        $storedfile = null;
    }
}

if ($storedfile) {
    if ($storedfile->get_timecreated() > $certificatebeautifulmodel->timemodified) {
        certificatebeautiful_require_signed_issue($certificatebeautifulissue);
        certificatebeautiful_audit_access($certificatebeautifulissue, $auditaction);
        certificatebeautiful_show_header($action, $context, $name);
        ob_clean();
        send_stored_file($storedfile, 86400, 0, $action !== 'view');
        die();
    } else {
        $storedfile->delete();
    }
}

$certificatebeautifulmodel->pages_info_object = json_decode($certificatebeautifulmodel->pages_info);

$pagepdf = new page_pdf();
$contentpdf = $pagepdf->create_pdf(
    $certificatebeautiful, $certificatebeautifulissue, $certificatebeautifulmodel, $user, $course);

$storedfile = $fs->create_file_from_string($filerecord, $contentpdf);

$certificatebeautifulissueupdate = (object) [
    "id" => $certificatebeautifulissue->id,
    "version" => $certificatebeautiful->timemodified,
];
$DB->update_record("certificatebeautiful_issue", $certificatebeautifulissueupdate);

certificatebeautiful_require_signed_issue($certificatebeautifulissue);
certificatebeautiful_audit_access($certificatebeautifulissue, $auditaction);
certificatebeautiful_show_header($action, $context, $name);
ob_clean();
send_stored_file($storedfile, 86400, 0, $action !== 'view');

/**
 * Function certificatebeautiful_show_header
 *
 * @param string $action
 * @param context $context
 * @param string $name
 *
 * @throws Exception
 */
function certificatebeautiful_require_signed_issue($issue): void {
    if (!class_exists('\\local_certificatesign\\manager')) {
        throw new moodle_exception('missing_signature_plugin', 'certificatebeautiful');
    }
    if (!get_config('local_certificatesign', 'autosign_enabled')) {
        throw new moodle_exception('signature_disabled', 'certificatebeautiful');
    }
    if (!\local_certificatesign\manager::is_signed((int) $issue->id)) {
        \local_certificatesign\manager::audit_access($issue, 'pending');
        throw new moodle_exception('pending_signature', 'certificatebeautiful');
    }
}

function certificatebeautiful_audit_access($issue, string $action): void {
    if (!class_exists('\\local_certificatesign\\manager')) {
        return;
    }
    \local_certificatesign\manager::audit_access($issue, $action);
}

function certificatebeautiful_show_header($action, $context, $name) {
    switch ($action) {
        case "createadmin":
            require_login();
            require_capability('mod/certificatebeautiful:addinstance', $context);
            header('Content-Type: application/pdf');
            header('Content-Disposition: attachment; filename="' . $name . '"');
            header('Cache-Control: public, must-revalidate, max-age=0');
            header('Pragma: public');
            header('Expires: Sat, 26 Jul 1997 05:00:00 GMT');
            header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
            header('Content-Description: File Transfer');
            header('Content-Transfer-Encoding: binary');
            break;
        case "view":
            header('Content-Type: application/pdf');
            header('Content-disposition: inline; filename="' . $name . '"');
            header('Cache-Control: public, must-revalidate, max-age=0');
            header('Pragma: public');
            header('Expires: Sat, 26 Jul 1997 05:00:00 GMT');
            header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
            break;
        case "download":
            header('Content-Type: application/pdf');
            header('Content-Disposition: attachment; filename="' . $name . '"');
            header('Cache-Control: public, must-revalidate, max-age=0');
            header('Pragma: public');
            header('Expires: Sat, 26 Jul 1997 05:00:00 GMT');
            header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');

            header('Content-Description: File Transfer');
            header('Content-Transfer-Encoding: binary');
            break;

        default:
            throw new \moodle_exception('invalidaction', 'moodle');
    }
}

die();
