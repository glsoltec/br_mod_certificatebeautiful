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
 * Library of interface functions and constants.
 *
 * @package   mod_certificatebeautiful
 * @copyright 2025 Eduardo Kraus https://eduardokraus.com/
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use core_user\output\myprofile\tree as treeAlias;
use mod_certificatebeautiful\automation;

/**
 * Checks if certificate activity supports a specific feature.
 *
 * @param string $feature FEATURE_xx constant for requested feature
 * @return mixed
 */
function certificatebeautiful_supports(string $feature) {
    switch ($feature) {
        case FEATURE_GROUPS:
            return true;
        case FEATURE_GROUPINGS:
            return true;
        case FEATURE_MOD_INTRO:
            return true;
        case FEATURE_SHOW_DESCRIPTION:
            return true;
        case FEATURE_COMPLETION_TRACKS_VIEWS:
            return true;
        case FEATURE_MODEDIT_DEFAULT_COMPLETION:
            return true;
        case FEATURE_BACKUP_MOODLE2:
            return true;
        case FEATURE_MOD_PURPOSE:
            return MOD_PURPOSE_ASSESSMENT;
        default:
            return null;
    }
}

/**
 * Saves a new instance of the module.
 *
 * @param stdClass $data
 * @param mod_certificatebeautiful_mod_form|null $mform
 * @return int
 */
function certificatebeautiful_add_instance(stdClass $data, $mform = null): int {
    global $DB;

    $data->timecreated = time();
    $data->gradepass = $data->gradepass ?? "";
    $data->triggercmid = $data->triggercmid ?? 0;
    $data->notifyuser = $data->notifyuser ?? 0;

    if ($data->autotrigger == automation::TRIGGER_NONE) {
        $data->notifyuser = 0;
    }
    if ($data->autotrigger != automation::TRIGGER_ACTIVITY_COMPLETION) {
        $data->triggercmid = 0;
    }
    if ($data->autotrigger != automation::TRIGGER_GRADE_THRESHOLD) {
        $data->gradepass = "";
    }

    $cmid = $data->coursemodule;
    $data->id = $DB->insert_record("certificatebeautiful", $data);

    // We need to use context now, so we need to make sure all needed info is already in db.
    $DB->set_field("course_modules", "instance", $data->id, ["id" => $cmid]);

    return $data->id;
}

/**
 * Updates an instance of the module.
 *
 * @param stdClass $data
 * @param mod_certificatebeautiful_mod_form|null $mform
 * @return bool
 */
function certificatebeautiful_update_instance(stdClass $data, $mform = null): bool {
    global $DB;

    $data->timemodified = time();
    $data->gradepass = $data->gradepass ?? "";
    $data->triggercmid = $data->triggercmid ?? 0;
    $data->notifyuser = $data->notifyuser ?? 0;

    if ($data->autotrigger == automation::TRIGGER_NONE) {
        $data->notifyuser = 0;
    }
    if ($data->autotrigger != automation::TRIGGER_ACTIVITY_COMPLETION) {
        $data->triggercmid = 0;
    }
    if ($data->autotrigger != automation::TRIGGER_GRADE_THRESHOLD) {
        $data->gradepass = "";
    }

    $data->id = $data->instance;

    return $DB->update_record("certificatebeautiful", $data);
}

/**
 * Removes an instance of the module.
 *
 * @param int $id
 * @return bool
 */
function certificatebeautiful_delete_instance(int $id): bool {
    global $DB;

    if (!$DB->record_exists("certificatebeautiful", ["id" => $id])) {
        return false;
    }

    if (!$cm = get_coursemodule_from_instance("certificatebeautiful", $id)) {
        return false;
    }
    $DB->delete_records("certificatebeautiful", ["id" => $id]);
    $DB->delete_records("certificatebeautiful_issue", ["certificatebeautifulid" => $id]);

    return true;
}

/**
 * Returns page types.
 *
 * @param string $pagetype
 * @param stdClass $parentcontext
 * @param stdClass $currentcontext
 * @return array
 */
function certificatebeautiful_page_type_list($pagetype, $parentcontext, $currentcontext): array {
    return [
        "mod-certificatebeautiful-*" => get_string("page-mod-certificatebeautiful-x", "mod_certificatebeautiful"),
    ];
}

/**
 * Adds course reset controls.
 *
 * @param MoodleQuickForm $mform
 * @return void
 */
function certificatebeautiful_reset_course_form_definition($mform) {
    $mform->addElement("header", "certificatebeautifulheader", get_string("modulenameplural", "certificatebeautiful"));
    $mform->addElement("checkbox", "archive_certificates", get_string("archivecertificates", "certificatebeautiful"));
    $mform->addHelpButton("archive_certificates", "archivecertificates", "mod_certificatebeautiful");
}

/**
 * Extends activity settings navigation.
 *
 * @param settings_navigation $settings
 * @param navigation_node $certificatebeautifulnode
 * @return void
 */
function certificatebeautiful_extend_settings_navigation($settings, $certificatebeautifulnode) {
    global $PAGE, $USER;

    $keys = $certificatebeautifulnode->get_children_key_list();
    $beforekey = null;
    $i = array_search("modedit", $keys);
    if ($i === false && array_key_exists(0, $keys)) {
        $beforekey = $keys[0];
    } else if (array_key_exists($i + 1, $keys)) {
        $beforekey = $keys[$i + 1];
    }

    if (!empty($PAGE->cm)
            && \mod_certificatebeautiful\access_manager::can_report($PAGE->cm->context, (int)$USER->id)) {
        $node = navigation_node::create(
            get_string("report", "certificatebeautiful"),
            new moodle_url("/mod/certificatebeautiful/report.php", ["id" => $PAGE->cm->id]),
            navigation_node::TYPE_SETTING,
            null,
            "mod_certificatebeautiful_report",
            new pix_icon("i/report", "")
        );
        $certificatebeautifulnode->add_node($node, $beforekey);
    }

    if (has_capability("mod/certificatebeautiful:managemodels", context_system::instance())) {
        $node = navigation_node::create(
            get_string("manage_models", "certificatebeautiful"),
            new moodle_url("/mod/certificatebeautiful/manage-model-list.php"),
            navigation_node::TYPE_SETTING,
            null,
            "mod_certificatebeautiful_manage_models",
            new pix_icon("i/report", "")
        );
        $certificatebeautifulnode->add_node($node, $beforekey);
    }
}

/**
 * Checks whether the current user can access at least one certificate report in a course.
 *
 * @param int $courseid
 * @return bool
 */
function certificatebeautiful_can_view_course_reports(int $courseid): bool {
    global $USER;

    $modinfo = get_fast_modinfo($courseid);

    foreach ($modinfo->get_cms() as $cm) {
        if ($cm->modname !== "certificatebeautiful" || $cm->deletioninprogress) {
            continue;
        }

        if (\mod_certificatebeautiful\access_manager::can_report(context_module::instance($cm->id), (int)$USER->id)) {
            return true;
        }
    }

    return false;
}

/**
 * Extends course navigation.
 *
 * @param navigation_node $navigation
 * @param stdClass $course
 * @param context $context
 * @return void
 */
function certificatebeautiful_extend_navigation_course($navigation, $course, $context) {
    if (certificatebeautiful_can_view_course_reports((int)$course->id)) {
        $certificatenode = $navigation->add(
            get_string("course_certificates", "certificatebeautiful"),
            null,
            navigation_node::TYPE_CONTAINER,
            null,
            uniqid()
        );
        $url = new moodle_url("/mod/certificatebeautiful/reports.php", ["course" => $course->id]);
        $certificatenode->add(
            get_string("course_certificates", "certificatebeautiful"),
            $url,
            navigation_node::TYPE_SETTING,
            null,
            null,
            new pix_icon("i/report", "")
        );
    }

    if (has_capability("mod/certificatebeautiful:managemodels", context_system::instance())) {
        $certificatenode = $navigation->add(
            get_string("manage_models", "certificatebeautiful"),
            null,
            navigation_node::TYPE_CONTAINER,
            null,
            "manage_models"
        );
        $url = new moodle_url("/mod/certificatebeautiful/manage-model-list.php");
        $certificatenode->add(
            get_string("manage_models", "certificatebeautiful"),
            $url,
            navigation_node::TYPE_SETTING,
            null,
            null,
            new pix_icon("i/report", "")
        );
    }
}

/**
 * Adds issued certificates to the user profile.
 *
 * @param treeAlias $tree
 * @param stdClass $user
 * @param bool $iscurrentuser
 * @param stdClass|null $course
 * @return void
 */
function certificatebeautiful_myprofile_navigation(core_user\output\myprofile\tree $tree, $user, $iscurrentuser, $course) {
    global $DB;

    $addnodes = [];
    if ($iscurrentuser || is_siteadmin()) {
        if ($course) {
            $sql = "SELECT issue.id, issue.code, issue.timecreated, issue.cmid,
                           cert.name, cert.course, course.fullname
                      FROM {certificatebeautiful_issue} issue
                      JOIN {certificatebeautiful}       cert   ON cert.id = issue.certificatebeautifulid
                      JOIN {course}                     course ON course.id = cert.course
                     WHERE issue.userid = :userid
                       AND cert.course  = :courseid";
            $params = ["userid" => $user->id, "courseid" => $course->id];
        } else {
            $sql = "SELECT issue.id, issue.code, issue.timecreated, issue.cmid,
                           cert.name, cert.course, course.fullname
                      FROM {certificatebeautiful_issue} issue
                      JOIN {certificatebeautiful}       cert   ON issue.certificatebeautifulid = cert.id
                      JOIN {course}                     course ON course.id = cert.course
                     WHERE issue.userid = :userid";
            $params = ["userid" => $user->id];
        }
        $certificates = $DB->get_records_sql($sql, $params);

        if ($certificates) {
            foreach ($certificates as $certificate) {
                $url = new moodle_url("/mod/certificatebeautiful/view.php", ["id" => $certificate->cmid]);
                $link = html_writer::link($url, $certificate->name);

                $addnodes[] = new core_user\output\myprofile\node(
                    "certificates",
                    "certificates-{$certificate->cmid}",
                    $certificate->fullname,
                    null,
                    null,
                    $link
                );
            }
        }
    }

    if (!empty($addnodes)) {
        $myname = get_string("my_certificates", "certificatebeautiful");
        $mobilecat = new core_user\output\myprofile\category("certificates", $myname, "contact");
        $tree->add_category($mobilecat);

        foreach ($addnodes as $node) {
            $tree->add_node($node);
        }
    }
}

/**
 * Fontes de assinatura disponiveis.
 *
 * @return array
 */
function certificatebeautiful_signature_fonts(): array {
    return [
        'autography' => ['label' => 'Autography', 'family' => "'Autography', cursive", 'file' => 'Autography', 'size' => 56, 'color' => '#2c4a1e'],
        'caveat'     => ['label' => 'Caveat',     'family' => "'Caveat', cursive",     'file' => 'Caveat',     'size' => 54, 'color' => '#1a2a4a'],
        'sacramento' => ['label' => 'Sacramento', 'family' => "'Sacramento', cursive", 'file' => 'Sacramento', 'size' => 56, 'color' => '#3a1a1a'],
        'aerotis'    => ['label' => 'Aerotis',    'family' => "'Aerotis', cursive",    'file' => 'Aerotis',    'size' => 50, 'color' => '#1a3a5c'],
    ];
}

/**
 * Slug da fonte padrao.
 *
 * @return string
 */
function certificatebeautiful_signature_default_font(): string {
    return 'autography';
}

/**
 * Retorna a URL publica da assinatura do usuario, ou null.
 *
 * @param int $userid
 * @return moodle_url|null
 */
function certificatebeautiful_get_signature_url(int $userid): ?\moodle_url {
    $context = \core\context\user::instance($userid, IGNORE_MISSING);
    if (!$context) {
        return null;
    }
    $fs = get_file_storage();
    $files = $fs->get_area_files($context->id, 'mod_certificatebeautiful', 'signature', 0, 'timemodified DESC', false);
    if (empty($files)) {
        return null;
    }
    $file = reset($files);
    return \moodle_url::make_pluginfile_url(
        $file->get_contextid(),
        $file->get_component(),
        $file->get_filearea(),
        $file->get_itemid(),
        $file->get_filepath(),
        $file->get_filename()
    );
}

/**
 * Retorna metadados da assinatura.
 *
 * @param int $userid
 * @return array
 */
function certificatebeautiful_get_signature_meta(int $userid): array {
    global $DB;
    $record = $DB->get_record('certificatebeautiful_usersignature', ['userid' => $userid]);
    if (!$record) {
        return ['font' => '', 'text' => '', 'timemodified' => 0];
    }
    return [
        'font'         => $record->font_style,
        'text'         => $record->signature_text,
        'timemodified' => (int) $record->timemodified,
    ];
}

/**
 * Retorna a assinatura como Data URI base64 para embutir no PDF.
 *
 * @param int $userid
 * @return string
 */
function certificatebeautiful_get_signature_datauri(int $userid): string {
    $context = \core\context\user::instance($userid, IGNORE_MISSING);
    if (!$context) {
        return '';
    }
    $fs = get_file_storage();
    $files = $fs->get_area_files($context->id, 'mod_certificatebeautiful', 'signature', 0, 'timemodified DESC', false);
    if (empty($files)) {
        return '';
    }
    $file = reset($files);
    return 'data:' . $file->get_mimetype() . ';base64,' . base64_encode($file->get_content());
}

/**
 * Salva ou atualiza metadados da assinatura.
 *
 * @param int $userid
 * @param string $font
 * @param string $text
 */
function certificatebeautiful_signature_save_meta(int $userid, string $font, string $text): void {
    global $DB;
    $existing = $DB->get_record('certificatebeautiful_usersignature', ['userid' => $userid]);
    $now = time();
    if ($existing) {
        $existing->font_style     = $font;
        $existing->signature_text = $text;
        $existing->timemodified   = $now;
        $DB->update_record('certificatebeautiful_usersignature', $existing);
    } else {
        $DB->insert_record('certificatebeautiful_usersignature', (object)[
            'userid'         => $userid,
            'font_style'     => $font,
            'signature_text' => $text,
            'timecreated'    => $now,
            'timemodified'   => $now,
        ]);
    }
}

/**
 * Callback para servir arquivos de assinatura via pluginfile.php.
 *
 * @param stdClass $course
 * @param stdClass $cm
 * @param context $context
 * @param string $filearea
 * @param array $args
 * @param bool $forcedownload
 * @param array $options
 */
function certificatebeautiful_pluginfile(
    $course, $cm, $context, string $filearea, array $args, bool $forcedownload, array $options = []
): void {
    if ($filearea === 'certificate') {
        if ($context->contextlevel != CONTEXT_MODULE || !$cm) {
            send_file_not_found();
        }
        require_login($course, false, $cm);
        require_capability('mod/certificatebeautiful:view', $context);
        $itemid = (int) array_shift($args);
        $filename = array_pop($args);
        $filepath = $args ? '/' . implode('/', $args) . '/' : '/';
        $file = get_file_storage()->get_file($context->id, 'mod_certificatebeautiful', 'certificate', $itemid, $filepath, $filename);
        if (!$file) {
            send_file_not_found();
        }
        send_stored_file($file, 86400, 0, $forcedownload, $options);
    }

    if ($context->contextlevel != CONTEXT_USER) {
        send_file_not_found();
    }
    if ($filearea !== 'signature') {
        return;
    }

    require_login(null, false);

    $fs = get_file_storage();
    $itemid = (int) array_shift($args);
    $filename = array_pop($args);
    $filepath = $args ? '/' . implode('/', $args) . '/' : '/';
    $file = $fs->get_file($context->id, 'mod_certificatebeautiful', 'signature', $itemid, $filepath, $filename);

    if (!$file) {
        send_file_not_found();
    }

    send_stored_file($file, 86400, 0, $forcedownload, $options);
}

/**
 * Lists bundled models.
 *
 * @return array
 */
function certificatebeautiful_list_all_models() {
    return [
        [
            "name" => get_string("certificate-appreciation", "certificatebeautiful"),
            "orientation" => "L",
            "key" => "certificate-appreciation",
        ], [
            "name" => get_string("certificate-details", "certificatebeautiful"),
            "orientation" => "L",
            "key" => "certificate-details",
        ], [
            "name" => get_string("certificate-elegant", "certificatebeautiful"),
            "orientation" => "L",
            "key" => "certificate-elegant",
        ], [
            "name" => get_string("certificate-flat-modern", "certificatebeautiful"),
            "orientation" => "L",
            "key" => "certificate-flat-modern",
        ], [
            "name" => get_string("certificate-golden", "certificatebeautiful"),
            "orientation" => "L",
            "key" => "certificate-golden",
        ], [
            "name" => get_string("certificate-gradient-golden-luxury", "certificatebeautiful"),
            "orientation" => "L",
            "key" => "certificate-gradient-golden-luxury",
        ], [
            "name" => get_string("certificate-kids-animals", "certificatebeautiful"),
            "orientation" => "L",
            "key" => "certificate-kids-animals",
        ], [
            "name" => get_string("certificate-kids-child-medical", "certificatebeautiful"),
            "orientation" => "L",
            "key" => "certificate-kids-child-medical",
        ], [
            "name" => get_string("certificate-kids-gradient-modern", "certificatebeautiful"),
            "orientation" => "L",
            "key" => "certificate-kids-gradient-modern",
        ], [
            "name" => get_string("certificate-kids-hand-drawn", "certificatebeautiful"),
            "orientation" => "L",
            "key" => "certificate-kids-hand-drawn",
        ], [
            "name" => get_string("certificate-kids-pastel", "certificatebeautiful"),
            "orientation" => "L",
            "key" => "certificate-kids-pastel",
        ], [
            "name" => get_string("certificate-modern", "certificatebeautiful"),
            "orientation" => "L",
            "key" => "certificate-modern",
        ], [
            "name" => get_string("certificate-modern-2", "certificatebeautiful"),
            "orientation" => "L",
            "key" => "certificate-modern-2",
        ], [
            "name" => get_string("certificate-simple", "certificatebeautiful"),
            "orientation" => "L",
            "key" => "certificate-simple",
        ], [
            "name" => get_string("certificate-vintage", "certificatebeautiful"),
            "orientation" => "L",
            "key" => "certificate-vintage",
        ], [
            "name" => get_string("sumary-secound-page", "certificatebeautiful"),
            "orientation" => "L",
            "key" => "sumary-secound-page",
        ], [
            "name" => get_string("sumary-secound-page2", "certificatebeautiful"),
            "orientation" => "L",
            "key" => "sumary-secound-page2",
        ],
    ];
}
