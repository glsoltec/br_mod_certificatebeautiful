<?php
namespace mod_certificatebeautiful;

defined('MOODLE_INTERNAL') || die();

class admin_setting_sign_certinfo extends \admin_setting {

    public function __construct() {
        $this->nosave = true;
        parent::__construct('certificatebeautiful/sign_certinfo',
            get_string('sign_certinfo', 'certificatebeautiful'),
            get_string('sign_certinfo_help', 'certificatebeautiful'), '');
    }

    public function get_setting() {
        return '';
    }

    public function write_setting($data) {
        return '';
    }

    public function output_html($data, $query = '') {
        $pfxcontent = pdf\signer\signer::get_pfx_content();
        $password = get_config('certificatebeautiful', 'sign_certpassword');

        $html = \html_writer::start_div('form-item clearfix');
        $html .= \html_writer::start_div('form-label');
        $html .= \html_writer::tag('label', $this->visiblename, ['class' => 'form-label-addon']);
        $html .= \html_writer::end_div();
        $html .= \html_writer::start_div('form-setting');

        if ($pfxcontent === null || empty($password)) {
            $html .= \html_writer::tag('span', get_string('sign_certinfonocert', 'certificatebeautiful'), ['class' => 'text-muted']);
        } else {
            try {
                $info = pdf\signer\signer::get_cert_info($pfxcontent, $password);
                $html .= \html_writer::start_tag('table', ['class' => 'table table-sm mb-0', 'style' => 'max-width:500px']);
                $html .= \html_writer::tag('tr',
                    \html_writer::tag('td', get_string('sign_certinfo_cn', 'certificatebeautiful'), ['class' => 'font-weight-bold pr-3']) .
                    \html_writer::tag('td', s($info['cn'])));
                $html .= \html_writer::tag('tr',
                    \html_writer::tag('td', get_string('sign_certinfo_org', 'certificatebeautiful'), ['class' => 'font-weight-bold pr-3']) .
                    \html_writer::tag('td', s($info['org'])));
                $html .= \html_writer::tag('tr',
                    \html_writer::tag('td', get_string('sign_certinfo_valid', 'certificatebeautiful'), ['class' => 'font-weight-bold pr-3']) .
                    \html_writer::tag('td',
                        userdate($info['validfrom'], '%d/%m/%Y') . ' — ' . userdate($info['validto'], '%d/%m/%Y')));
                $html .= \html_writer::tag('tr',
                    \html_writer::tag('td', get_string('sign_certinfo_issuer', 'certificatebeautiful'), ['class' => 'font-weight-bold pr-3']) .
                    \html_writer::tag('td', s($info['issuer'])));
                $html .= \html_writer::end_tag('table');

                if ($info['validto'] < time()) {
                    $html .= \html_writer::tag('div', get_string('sign_certexpired', 'certificatebeautiful'),
                        ['class' => 'alert alert-danger mt-2 mb-0']);
                }
            } catch (\Exception $e) {
                $html .= \html_writer::tag('span', get_string('sign_certinfonocert', 'certificatebeautiful'), ['class' => 'text-danger']);
            }
        }

        $html .= \html_writer::end_div();
        $html .= \html_writer::end_div();

        return $html;
    }
}
