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
 * View PDF file.
 *
 * @package   mod_certificatebeautiful
 * @copyright 2025 Eduardo Kraus https://eduardokraus.com/
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use mod_certificatebeautiful\access_manager;
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

$action = required_param("action", PARAM_ALPHA);

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

if ($action === "createadmin") {
    $cmid = required_param("cmid", PARAM_INT);
    $userid = required_param("userid", PARAM_INT);

    $cm = get_coursemodule_from_id("certificatebeautiful", $cmid, 0, false, MUST_EXIST);
    $course = $DB->get_record("course", ["id" => $cm->course], "*", MUST_EXIST);
    $context = context_module::instance($cm->id);

    require_course_login($course, true, $cm);
    access_manager::require_manage_issues($context);
    require_sesskey();

    $user = $DB->get_record("user", ["id" => $userid, "deleted" => 0], "*", MUST_EXIST);
    $coursecontext = context_course::instance($course->id);
    if (!is_enrolled($coursecontext, $user, "", true)) {
        throw new invalid_parameter_exception("The selected user is not actively enrolled in this course.");
    }

    $certificatebeautiful = $DB->get_record("certificatebeautiful", ["id" => $cm->instance], "*", MUST_EXIST);

    $certificatebeautifulissue = issue::get($user, $certificatebeautiful, $cm);
    redirect(new moodle_url('/mod/certificatebeautiful/view-pdf.php', [
        "code" => $certificatebeautifulissue->code,
        "action" => "view",
    ]));
}

if (!in_array($action, ["view", "download"], true)) {
    throw new invalid_parameter_exception("Invalid certificate action.");
}

$code = required_param("code", PARAM_TEXT);

/** @var certificatebeautiful_issue $certificatebeautifulissue */
$certificatebeautifulissue = $DB->get_record("certificatebeautiful_issue", ["code" => $code], "*", MUST_EXIST);

$cm = get_coursemodule_from_id("certificatebeautiful", $certificatebeautifulissue->cmid, 0, false, MUST_EXIST);
$course = $DB->get_record("course", ["id" => $cm->course], "*", MUST_EXIST);
$context = context_module::instance($cm->id);

if ($token) {
    require_course_login($course, false, null, false, true);
} else {
    require_course_login($course, true, $cm);
}
access_manager::require_view_issue($certificatebeautifulissue, $context);

/** @var certificatebeautiful $certificatebeautiful */
$certificatebeautiful = $DB->get_record("certificatebeautiful", ["id" => $cm->instance], "*", MUST_EXIST);
$user = $DB->get_record("user", ["id" => $certificatebeautifulissue->userid], "*", MUST_EXIST);

if ($token) {
    $auditaction = 'token_view';
} elseif ($action === 'download') {
    $auditaction = 'download';
} else {
    $auditaction = 'view';
}

$username = fullname($user);
$name = clean_filename("{$certificatebeautiful->name} - {$username}.pdf");

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
$certificatebeautifulmodel = $DB->get_record(
    "certificatebeautiful_model",
    ["id" => $certificatebeautiful->model],
    "*",
    MUST_EXIST
);

$storedfile = $fs->get_file(
    $filerecord->contextid,
    $filerecord->component,
    $filerecord->filearea,
    $filerecord->itemid,
    $filerecord->filepath,
    $filerecord->filename
);

if ($certificatebeautiful->timemodified != $certificatebeautifulissue->version) {
    if ($storedfile) {
        $storedfile->delete();
        $storedfile = null;
    }
}

// Regenerate when the cached file predates the recorded completion date so
// dynamic tags like {$ENROLMENTS->timecompleteddate} are not stale.
$completiontime = (int) $DB->get_field(
    "course_completions",
    "timecompleted",
    ["course" => $course->id, "userid" => $user->id]
);
if ($storedfile && $completiontime && $storedfile->get_timecreated() < $completiontime) {
    $storedfile->delete();
    $storedfile = null;
}

if ($storedfile) {
    if ($storedfile->get_timecreated() > $certificatebeautifulmodel->timemodified) {
        certificatebeautiful_require_signed_issue($certificatebeautifulissue);
        certificatebeautiful_audit_access($certificatebeautifulissue, $auditaction);
        certificatebeautiful_show_header($action, $name);
        ob_clean();
        send_stored_file($storedfile, 86400, 0, $action !== 'view');
        die();
    }

    $storedfile->delete();
}

$certificatebeautifulmodel->pages_info_object = json_decode($certificatebeautifulmodel->pages_info);

$pagepdf = new page_pdf();
$contentpdf = $pagepdf->create_pdf(
    $certificatebeautiful,
    $certificatebeautifulissue,
    $certificatebeautifulmodel,
    $user,
    $course
);

$storedfile = $fs->create_file_from_string($filerecord, $contentpdf);

issue::update_version(
    (int)$certificatebeautifulissue->id,
    (int)$certificatebeautiful->timemodified
);

certificatebeautiful_require_signed_issue($certificatebeautifulissue);
certificatebeautiful_audit_access($certificatebeautifulissue, $auditaction);
certificatebeautiful_show_header($action, $name);
ob_clean();
send_stored_file($storedfile, 86400, 0, $action !== 'view');

/**
 * Blocks certificate delivery until the digital signing task has signed the issue.
 *
 * This keeps the local (fork) integration with the local_certificatesign plugin: when
 * that plugin is installed and configured, a pending certificate is not delivered.
 *
 * @param stdClass $issue Certificate issue record.
 * @return void
 */
function certificatebeautiful_require_signed_issue($issue): void {
    global $OUTPUT;

    if (!class_exists('\\local_certificatesign\\manager')) {
        return;
    }
    if (!\local_certificatesign\manager::is_configured()) {
        return;
    }
    if (!\local_certificatesign\manager::is_signed((int) $issue->id)) {
        \local_certificatesign\manager::audit_access($issue, 'pending');
        ob_end_clean();
        echo $OUTPUT->header();
        echo $OUTPUT->notification(
            get_string("pending_signature", "certificatebeautiful"),
            \core\output\notification::NOTIFY_WARNING
        );
        echo $OUTPUT->footer();
        die();
    }
}

/**
 * Registers an access event in the local certificate signing audit log, when available.
 *
 * @param stdClass $issue Certificate issue record.
 * @param string $action Audit action name.
 * @return void
 */
function certificatebeautiful_audit_access($issue, string $action): void {
    if (!class_exists('\\local_certificatesign\\manager')) {
        return;
    }
    \local_certificatesign\manager::audit_access($issue, $action);
}

/**
 * Sends the correct headers for an inline view or download.
 *
 * @param string $action Action name.
 * @param string $name Download filename.
 * @return void
 */
function certificatebeautiful_show_header($action, $name): void {
    header('Content-Type: application/pdf');

    if ($action === "download") {
        header('Content-Disposition: attachment; filename="' . $name . '"');
        header('Content-Description: File Transfer');
        header('Content-Transfer-Encoding: binary');
    } else {
        header('Content-Disposition: inline; filename="' . $name . '"');
    }

    // Certificates contain personal data and must not be cached by shared proxies.
    header('Cache-Control: private, no-store, max-age=0');
    header('Pragma: no-cache');
    header('Expires: 0');
}

die();
