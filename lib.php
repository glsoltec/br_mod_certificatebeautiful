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

/**
 * Checks if certificate activity supports a specific feature.
 *
 * @uses FEATURE_GROUPS
 * @uses FEATURE_GROUPINGS
 * @uses FEATURE_MOD_INTRO
 * @uses FEATURE_SHOW_DESCRIPTION
 * @uses FEATURE_COMPLETION_TRACKS_VIEWS
 * @uses FEATURE_COMPLETION_HAS_RULES
 * @uses FEATURE_MODEDIT_DEFAULT_COMPLETION
 * @uses FEATURE_BACKUP_MOODLE2
 *
 * @param string $feature FEATURE_xx constant for requested feature
 *
 * @return mixed True if module supports feature, false if not, null if doesn't know
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
 * Saves a new instance of the mod_certificatebeautiful into the database.
 *
 * Given an object containing all the necessary data, (defined by the form
 * in mod_form.php) this function will create a new instance and return the id
 * number of the instance.
 *
 * @param stdClass $data                           An object from the form.
 * @param mod_certificatebeautiful_mod_form $mform The form.
 * @return int The id of the newly inserted record.
 * @throws Exception
 */
function certificatebeautiful_add_instance(stdClass $data, $mform = null): int {
    global $DB;

    $data->timecreated = time();
    $cmid = $data->coursemodule;

    $data->id = $DB->insert_record("certificatebeautiful", $data);

    // We need to use context now, so we need to make sure all needed info is already in db.
    $DB->set_field("course_modules", "instance", $data->id, ["id" => $cmid]);

    return $data->id;
}

/**
 * Updates an instance of the mod_certificatebeautiful in the database.
 *
 * Given an object containing all the necessary data (defined in mod_form.php),
 * this function will update an existing instance with new data.
 *
 * @param stdClass $data                           An object from the form in mod_form.php.
 * @param mod_certificatebeautiful_mod_form $mform The form.
 * @return bool True if successful, false otherwise.
 * @throws Exception
 */
function certificatebeautiful_update_instance(stdClass $data, $mform = null): bool {
    global $DB;

    $data->timemodified = time();
    $data->id = $data->instance;

    return $DB->update_record("certificatebeautiful", $data);
}

/**
 * Removes an instance of the mod_certificatebeautiful from the database.
 *
 * @param int $id Id of the module instance.
 * @return bool True if successful, false on failure.
 * @throws Exception
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
 * Return a list of page types
 *
 * @param string $pagetype         current page type
 * @param stdClass $parentcontext  Block's parent context
 * @param stdClass $currentcontext Current context of block
 * @return array array of page types and it's names
 * @throws Exception
 */
function certificatebeautiful_page_type_list($pagetype, $parentcontext, $currentcontext): array {
    $modulepagetype = [
        "mod-certificatebeautiful-*" => get_string("page-mod-certificatebeautiful-x", "mod_certificatebeautiful"),
    ];
    return $modulepagetype;
}

/**
 * The elements to add the course reset form.
 *
 * @param MoodleQuickForm $mform
 * @throws Exception
 */
function certificatebeautiful_reset_course_form_definition($mform) {
    $mform->addElement("header", "certificatebeautifulheader", get_string("modulenameplural", "certificatebeautiful"));
    $mform->addElement("checkbox", "archive_certificates", get_string("archivecertificates", "certificatebeautiful"));
    $mform->addHelpButton("archive_certificates", "archivecertificates", "mod_certificatebeautiful");
}

/**
 * certificatebeautiful_extend_settings_navigation function
 *
 * @param settings_navigation $settings
 * @param navigation_node $certificatebeautifulnode
 * @return void
 * @throws Exception
 */
function certificatebeautiful_extend_settings_navigation($settings, $certificatebeautifulnode) {
    global $PAGE;

    // We want to add these new nodes after the Edit settings node, and before the
    // Locally assigned roles node. Of course, both of those are controlled by capabilities.
    $keys = $certificatebeautifulnode->get_children_key_list();
    $beforekey = null;
    $i = array_search("modedit", $keys);
    if ($i === false && array_key_exists(0, $keys)) {
        $beforekey = $keys[0];
    } else if (array_key_exists($i + 1, $keys)) {
        $beforekey = $keys[$i + 1];
    }

    if (has_capability("moodle/course:manageactivities", $PAGE->cm->context)) {
        $node = navigation_node::create(get_string("report", "certificatebeautiful"),
            new moodle_url("/mod/certificatebeautiful/report.php", ["id" => $PAGE->cm->id]),
            navigation_node::TYPE_SETTING, null, "mod_certificatebeautiful_report",
            new pix_icon("i/report", ""));
        $certificatebeautifulnode->add_node($node, $beforekey);

        $node = navigation_node::create(get_string("manage_models", "certificatebeautiful"),
            new moodle_url("/mod/certificatebeautiful/manage-model-list.php"),
            navigation_node::TYPE_SETTING, null, "mod_certificatebeautiful_manage_models",
            new pix_icon("i/report", ""));
        $certificatebeautifulnode->add_node($node, $beforekey);
    }
}

/**
 * certificatebeautiful_extend_navigation_course function
 *
 * @param navigation_node $navigation
 * @param stdClass $course
 * @param context $context
 * @throws Exception
 */
function certificatebeautiful_extend_navigation_course($navigation, $course, $context) {
    if (has_capability("moodle/course:manageactivities", $context)) {
        $certificatenode1 = $navigation->add(get_string("course_certificates", "certificatebeautiful"),
            null, navigation_node::TYPE_CONTAINER, null, uniqid());
        $url = new moodle_url("/mod/certificatebeautiful/reports.php", ["course" => $course->id]);
        $certificatenode1->add(get_string("course_certificates", "certificatebeautiful"), $url, navigation_node::TYPE_SETTING,
            null, null, new pix_icon("i/report", ""));

        $certificatenode2 = $navigation->add(get_string("manage_models", "certificatebeautiful"),
            null, navigation_node::TYPE_CONTAINER, null, "manage_models");
        $url = new moodle_url("/mod/certificatebeautiful/manage-model-list.php");
        $certificatenode2->add(get_string("manage_models", "certificatebeautiful"), $url, navigation_node::TYPE_SETTING,
            null, null, new pix_icon("i/report", ""));
    }
}

/**
 * certificatebeautiful_myprofile_navigation
 *
 * @param treeAlias $tree Tree object
 * @param stdClass $user                         user object
 * @param bool $iscurrentuser
 * @param stdClass $course                       Course object
 * @return void
 * @throws Exception
 */
function certificatebeautiful_myprofile_navigation(core_user\output\myprofile\tree $tree, $user, $iscurrentuser, $course) {
    global $DB;

    $addnodes = [];
    if ($iscurrentuser || is_siteadmin()) {
        if ($course) {
            $sql = "
            SELECT issue.id, issue.code, issue.timecreated, issue.cmid, cert.name, cert.course, course.fullname
              FROM {certificatebeautiful_issue} issue
              JOIN {certificatebeautiful}       cert   ON cert.id   = issue.certificatebeautifulid
              JOIN {course}                     course ON course.id = cert.course
             WHERE issue.userid = :userid
               AND cert.course  = :courseid";
            $params = ["userid" => $user->id, "courseid" => $course->id];
        } else {
            $sql = "
            SELECT issue.id, issue.code, issue.timecreated, issue.cmid, cert.name, cert.course, course.fullname
              FROM {certificatebeautiful_issue} issue
              JOIN {certificatebeautiful}       cert  ON issue.certificatebeautifulid = cert.id
              JOIN {course}                     course ON course.id = cert.course
             WHERE issue.userid = :userid";
            $params = ["userid" => $user->id];
        }
        $certificates = $DB->get_records_sql($sql, $params);

        if ($certificates) {
            foreach ($certificates as $certificate) {
                $url = new moodle_url("/mod/certificatebeautiful/view.php", ["id" => $certificate->cmid]);
                $link = html_writer::link($url, $certificate->name);

                $addnodes[] = new core_user\output\myprofile\node("certificates", "certificates-{$certificate->cmid}",
                    $certificate->fullname, null, null, $link);
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
 * Adiciona link "Minha Assinatura" ao perfil do usuario.
 *
 * @param navigation_node $parentnode
 * @param stdClass $user
 * @param context_user $context
 * @param stdClass $course
 * @param stdClass $coursecontext
 */
function certificatebeautiful_extend_navigation_user(
    \navigation_node $parentnode,
    \stdClass $user,
    \context_user $context,
    $course,
    $coursecontext
): void {
    global $USER, $CFG;

    if ($USER->id != $user->id && !has_capability('moodle/user:editprofile', $context)) {
        return;
    }

    $parentnode->add(
        get_string('mysignature', 'certificatebeautiful'),
        new \moodle_url('/mod/certificatebeautiful/signature.php', ['userid' => $user->id]),
        \navigation_node::TYPE_SETTING,
        null,
        'certificatebeautiful_signature',
        new \pix_icon('i/edit', '')
    );
}

/**
 * Adiciona link "Minha Assinatura" ao menu de preferencias do usuario.
 *
 * @param navigation_node $parentnode
 * @param stdClass $user
 * @param context_user $context
 * @param stdClass $course
 * @param stdClass $coursecontext
 */
function certificatebeautiful_extend_navigation_user_settings(
    \navigation_node $parentnode,
    \stdClass $user,
    \context_user $context,
    $course,
    $coursecontext
): void {
    global $USER, $CFG;

    if ($USER->id != $user->id && !has_capability('moodle/user:editprofile', $context)) {
        return;
    }

    $parentnode->add(
        get_string('mysignature', 'certificatebeautiful'),
        new \moodle_url('/mod/certificatebeautiful/signature.php', ['userid' => $user->id]),
        \navigation_node::TYPE_SETTING,
        null,
        'certificatebeautiful_signature',
        new \pix_icon('i/edit', '')
    );
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
 * certificatebeautiful_list_all_models
 *
 * @return array
 * @throws Exception
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
