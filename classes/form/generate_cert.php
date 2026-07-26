<?php
namespace mod_certificatebeautiful\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

class generate_cert extends \moodleform {

    public function definition() {
        $mform = $this->_form;

        $mform->addElement('text', 'cn', get_string('sign_gen_cn', 'certificatebeautiful'));
        $mform->setType('cn', PARAM_TEXT);
        $mform->addRule('cn', null, 'required', null, 'client');
        $mform->setDefault('cn', 'Instituto Federal');

        $mform->addElement('text', 'org', get_string('sign_gen_org', 'certificatebeautiful'));
        $mform->setType('org', PARAM_TEXT);

        $mform->addElement('text', 'country', get_string('sign_gen_country', 'certificatebeautiful'));
        $mform->setType('country', PARAM_TEXT);
        $mform->setDefault('country', 'BR');

        $mform->addElement('passwordunmask', 'password', get_string('sign_gen_password', 'certificatebeautiful'));
        $mform->setType('password', PARAM_RAW);
        $mform->addRule('password', null, 'required', null, 'client');

        $mform->addElement('passwordunmask', 'password_confirm', get_string('sign_gen_password_confirm', 'certificatebeautiful'));
        $mform->setType('password_confirm', PARAM_RAW);
        $mform->addRule('password_confirm', null, 'required', null, 'client');

        $this->add_action_buttons(true, get_string('sign_gen_generate', 'certificatebeautiful'));
    }

    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        if ($data['password'] !== $data['password_confirm']) {
            $errors['password_confirm'] = get_string('sign_gen_passwords_mismatch', 'certificatebeautiful');
        }
        if (strlen($data['password']) < 4) {
            $errors['password'] = get_string('sign_gen_password_weak', 'certificatebeautiful');
        }

        return $errors;
    }
}
