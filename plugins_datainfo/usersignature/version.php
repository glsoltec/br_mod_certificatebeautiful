<?php
defined('MOODLE_INTERNAL') || die();

$plugin->component    = 'certificatebeautifuldatainfo_usersignature';
$plugin->version      = 2026081300;
$plugin->requires     = 2025041400;
$plugin->dependencies = [
    'local_usersignature' => 2026070302,
    'mod_certificatebeautiful' => 2026081300,
];
$plugin->maturity = MATURITY_STABLE;
$plugin->release  = '2.2.0';
