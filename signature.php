<?php
defined('MOODLE_INTERNAL') || die();
require_once(__DIR__ . '/../../config.php');

$userid = optional_param('userid', $USER->id, PARAM_INT);
require_login();
$context = context_user::instance($userid, IGNORE_MISSING);
if (!$context) {
    throw new moodle_exception('invaliduser');
}
if ($userid != $USER->id) {
    require_capability('moodle/user:editprofile', $context);
}
redirect(new moodle_url('/local/usersignature/index.php', ['userid' => $userid]));
