<?php
require_once(__DIR__ . '/../../config.php');
require_login();
require_capability('moodle/site:config', \context_system::instance());

$PAGE->set_url('/mod/certificatebeautiful/generate-cert.php');
$PAGE->set_context(\context_system::instance());
$PAGE->set_title(get_string('sign_gen_title', 'certificatebeautiful'));
$PAGE->set_heading(get_string('sign_gen_title', 'certificatebeautiful'));

$mform = new \mod_certificatebeautiful\form\generate_cert();

if ($mform->is_cancelled()) {
    redirect(new \moodle_url('/admin/settings.php', ['section' => 'modsettingcertificatebeautiful']));
}

if ($data = $mform->get_data()) {
    require_sesskey();

    try {
        $pfxcontent = \mod_certificatebeautiful\pdf\signer\signer::generate_self_signed(
            $data->cn,
            $data->org ?? '',
            $data->country ?? '',
            $data->password
        );

        $fs = get_file_storage();
        $syscontext = \context_system::instance();
        $fs->delete_area_files($syscontext->id, 'mod_certificatebeautiful', 'pfxfile');
        $fs->create_file_from_string([
            'contextid' => $syscontext->id,
            'component' => 'mod_certificatebeautiful',
            'filearea'  => 'pfxfile',
            'itemid'    => 0,
            'filepath'  => '/',
            'filename'  => 'selfsigned_' . date('Ymd') . '.pfx',
        ], $pfxcontent);

        set_config('sign_certpassword', $data->password, 'certificatebeautiful');

        redirect(
            new \moodle_url('/admin/settings.php', ['section' => 'modsettingcertificatebeautiful']),
            get_string('sign_gen_success', 'certificatebeautiful'),
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
    } catch (\Exception $e) {
        redirect(
            $PAGE->url,
            get_string('erroropenssl', 'certificatebeautiful', $e->getMessage()),
            null,
            \core\output\notification::NOTIFY_ERROR
        );
    }
}

echo $OUTPUT->header();
$mform->display();
echo $OUTPUT->footer();
