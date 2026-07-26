<?php
defined('MOODLE_INTERNAL') || die();

$plugin->component    = 'certificatebeautifuldatainfo_usersignature';
$plugin->version      = 2026072500;
$plugin->requires     = 2021041900;
$plugin->dependencies = [
    'mod_certificatebeautiful' => 2026042700,
];
$plugin->maturity = MATURITY_STABLE;
$plugin->release  = '2.1.0';
