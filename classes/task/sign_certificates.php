<?php
namespace mod_certificatebeautiful\task;

defined('MOODLE_INTERNAL') || die();

class sign_certificates extends \core\task\scheduled_task {

    public function get_name() {
        return get_string('task_sign', 'certificatebeautiful');
    }

    public function execute() {
        global $DB;

        mtrace('certificatebeautiful sign task: started.');

        if (!get_config('certificatebeautiful', 'sign_autosign')) {
            mtrace('certificatebeautiful sign task: disabled in settings, skipping.');
            return;
        }

        $pfxcontent = \mod_certificatebeautiful\pdf\signer\signer::get_pfx_content();
        $password = get_config('certificatebeautiful', 'sign_certpassword');
        if ($pfxcontent === null || empty($password)) {
            mtrace('certificatebeautiful sign task: no PFX configured, skipping.');
            return;
        }

        $interval = (int) get_config('certificatebeautiful', 'sign_task_interval');
        if ($interval <= 0) {
            $interval = 2;
        }
        $lastrun = (int) get_config('certificatebeautiful', 'sign_task_lastrun');
        if ($lastrun > 0 && (time() - $lastrun) < $interval * 60) {
            mtrace("certificatebeautiful sign task: last run " . (time() - $lastrun) . "s ago, skipping.");
            return;
        }
        set_config('sign_task_lastrun', time(), 'certificatebeautiful');

        $fs = get_file_storage();

        $sql = "SELECT ci.id, ci.userid, ci.cmid, ci.code, ci.certificatebeautifulid, ci.timecreated
                  FROM {certificatebeautiful_issue} ci
             LEFT JOIN {certificatebeautiful_sign_log} l ON l.issueid = ci.id
                 WHERE l.id IS NULL";
        $issues = $DB->get_records_sql($sql);
        mtrace('certificatebeautiful sign task: found ' . count($issues) . ' pending certificate(s).');

        if (empty($issues)) {
            mtrace('certificatebeautiful sign task: no pending certificates.');
            return;
        }

        $count = 0;
        foreach ($issues as $issue) {
            try {
                $cm = get_coursemodule_from_id('certificatebeautiful', $issue->cmid);
                if (!$cm) {
                    continue;
                }

                $context = \context_module::instance($cm->id);
                $filename = "{$issue->code}.pdf";

                $file = $fs->get_file(
                    $context->id,
                    'mod_certificatebeautiful',
                    'certificate',
                    $issue->userid,
                    '/',
                    $filename
                );

                if (!$file) {
                    continue;
                }

                $pdfcontent = $file->get_content();
                $signedpdf = \mod_certificatebeautiful\pdf\signer\signer::sign_pdf($pdfcontent);

                $file->delete();
                $fs->create_file_from_string([
                    'contextid' => $context->id,
                    'component' => 'mod_certificatebeautiful',
                    'filearea'  => 'certificate',
                    'itemid'    => $issue->userid,
                    'filepath'  => '/',
                    'filename'  => $filename,
                ], $signedpdf);

                $DB->insert_record('certificatebeautiful_sign_log', (object)[
                    'issueid'     => $issue->id,
                    'timecreated' => time(),
                ]);

                $count++;
                mtrace("certificatebeautiful sign task: signed issue {$issue->id}");
            } catch (\Exception $e) {
                mtrace("certificatebeautiful sign task: error {$issue->id}: {$e->getMessage()}");
            }
        }

        mtrace("certificatebeautiful sign task: {$count} certificate(s) signed.");
    }
}
