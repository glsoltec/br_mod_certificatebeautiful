<?php
defined('MOODLE_INTERNAL') || die();

$observers = [
    [
        'eventname' => '\core\event\file_created',
        'callback'  => '\mod_certificatebeautiful\sign_observer::file_created',
        'priority'  => 200,
    ],
];
