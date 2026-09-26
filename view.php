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
 * Prints an instance of mod_certificatebeautiful.
 *
 * @package   mod_certificatebeautiful
 * @copyright 2025 Eduardo Kraus https://eduardokraus.com/
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use mod_certificatebeautiful\access_manager;
use mod_certificatebeautiful\automation;
use mod_certificatebeautiful\event\certificatebeautiful_course_module_viewed;
use mod_certificatebeautiful\report\certificatebeautiful_view;
use mod_certificatebeautiful\vo\certificatebeautiful;

require_once("../../config.php");

global $PAGE, $USER, $CFG;

$id = required_param("id", PARAM_INT);
$token = optional_param("token", false, PARAM_TEXT);

$cm = get_coursemodule_from_id("certificatebeautiful", $id, 0, false, MUST_EXIST);
$course = $DB->get_record("course", ["id" => $cm->course], "*", MUST_EXIST);
$context = context_module::instance($cm->id);

if ($token) {
    $externalservice = $DB->get_record("external_services", ["shortname" => MOODLE_OFFICIAL_MOBILE_SERVICE]);
    $externaltoken = $externalservice
        ? $DB->get_record("external_tokens", ["token" => $token, "externalserviceid" => $externalservice->id], "userid")
        : false;
    $user = $externaltoken ? $DB->get_record("user", ["id" => $externaltoken->userid]) : false;

    if ($user) {
        \core\session\manager::login_user($user);
        $PAGE->set_pagelayout("embedded");
        $PAGE->add_body_class("body-certificatebeautiful-mobile-view");
    }
    require_course_login($course, false, null, false, true);
} else {
    require_course_login($course, true, $cm);
}
// A report-only role may manage/view issued certificates without needing the student-facing view capability.
if (!has_capability("mod/certificatebeautiful:viewreport", $context)) {
    require_capability("mod/certificatebeautiful:view", $context);
}

if (optional_param("action", "", PARAM_ALPHA) === "delete") {
    access_manager::require_manage_issues($context);
    require_sesskey();
    require_capability("mod/certificatebeautiful:addinstance", $context);
    $issueid = required_param("issueid", PARAM_INT);
    $certificatebeautifulissue = $DB->get_record("certificatebeautiful_issue", [
        "id" => $issueid,
        "cmid" => $cm->id,
    ], "*", MUST_EXIST);

    $DB->delete_records("certificatebeautiful_issue", ["id" => $certificatebeautifulissue->id]);

    $fs = get_file_storage();
    $filerecord = (object)[
        "component" => "mod_certificatebeautiful",
        "contextid" => $context->id,
        "filearea" => "certificate",
        "filepath" => "/",
        "itemid" => $certificatebeautifulissue->userid,
        "filename" => "{$certificatebeautifulissue->code}.pdf",
    ];

    $storedfile = $fs->get_file(
        $filerecord->contextid,
        $filerecord->component,
        $filerecord->filearea,
        $filerecord->itemid,
        $filerecord->filepath,
        $filerecord->filename
    );

    if ($storedfile) {
        $storedfile->delete();
    }

    redirect(new moodle_url("/mod/certificatebeautiful/view.php", ["id" => $id]),
        get_string("report_deleted_certificate", "certificatebeautiful"));
}

/** @var certificatebeautiful $certificatebeautiful */
$certificatebeautiful = $DB->get_record("certificatebeautiful", ["id" => $cm->instance], "*", MUST_EXIST);

$PAGE->set_context($context);
$PAGE->set_url("/mod/certificatebeautiful/view.php", ["id" => $id]);
$PAGE->set_title($course->shortname . ": " . $certificatebeautiful->name);
$PAGE->set_heading(format_string($course->fullname));

$event = certificatebeautiful_course_module_viewed::create([
    "objectid" => $cm->instance,
    "context" => $PAGE->context,
]);
$event->add_record_snapshot("course", $PAGE->course);
$event->add_record_snapshot("certificatebeautiful", $certificatebeautiful);
$event->trigger();

// Update "viewed" state if required by completion system.
$completion = new completion_info($course);
$completion->set_module_viewed($cm);

echo $OUTPUT->header();

if (has_capability("mod/certificatebeautiful:viewreport", $context)) {
    $title = get_string("report_filename", "certificatebeautiful");
    echo $OUTPUT->heading($title, 2, "main", "certificatebeautifulheading");

    $table = new certificatebeautiful_view(
        "certificatebeautiful_report",
        $cm->id,
        $certificatebeautiful
    );
    $table->define_baseurl("{$CFG->wwwroot}/mod/certificatebeautiful/report.php?id={$cm->id}");
    $table->out(40, true);
} else {
    if ($certificatebeautiful->autotrigger !== automation::TRIGGER_NONE) {
        automation::process_user($cm->id, $USER->id);
    }

    $certificatebeautifulissue = $DB->get_record("certificatebeautiful_issue", [
        "userid" => $USER->id,
        "cmid" => $cm->id,
    ]);

    if (!$certificatebeautifulissue) {
        echo $OUTPUT->notification(
            get_string("certificate_not_issued", "certificatebeautiful"),
            "info"
        );
    } elseif (certificatebeautiful_is_pending_signature($certificatebeautifulissue)) {
        echo $OUTPUT->notification(
            get_string("pending_signature", "certificatebeautiful"),
            "warning"
        );
    } else {
        $viewerurl = "{$CFG->wwwroot}/mod/certificatebeautiful/_pdfjs-2.8.335-legacy/web/viewer.html";
        $urlbase = "{$CFG->wwwroot}/mod/certificatebeautiful/view-pdf.php?code={$certificatebeautifulissue->code}";

        // No Moodle Mobile o acesso é feito por token (sem sessão/cookie), então o
        // token precisa acompanhar TODAS as URLs, inclusive os botões view/download.
        if ($token) {
            $urlbase .= "&token=" . urlencode($token);
        }

        $data = [
            "issueid" => $certificatebeautifulissue->id,
            "pdf-viewer-url" => "{$viewerurl}?file=" . urlencode("{$urlbase}&action=view"),
            "pdf-url_base" => $urlbase,
            "pdf-direct-url" => "{$urlbase}&action=view",
        ];

        if (class_exists('\\local_certificatesign\\manager')) {
            \local_certificatesign\manager::audit_access(
                $certificatebeautifulissue,
                $token ? 'token_view' : 'view'
            );
        }

        echo $OUTPUT->render_from_template("mod_certificatebeautiful/view", $data);
    }
}

echo $OUTPUT->footer();

function certificatebeautiful_is_pending_signature($issue): bool {
    if (!class_exists('\\local_certificatesign\\manager')) {
        return false;
    }
    if (!\local_certificatesign\manager::is_configured()) {
        return false;
    }
    return !\local_certificatesign\manager::is_signed((int) $issue->id);
}
