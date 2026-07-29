<?php
require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/filelib.php');
require_once(__DIR__ . '/lib.php');

$slug  = required_param('font', PARAM_ALPHA);
$fonts = certificatebeautiful_signature_fonts();

if (!isset($fonts[$slug])) {
    send_file_not_found();
}

$mimes = [
    'woff2' => 'font/woff2',
    'woff'  => 'font/woff',
    'ttf'   => 'font/ttf',
    'otf'   => 'font/otf',
];

$base = $fonts[$slug]['file'];
$dirs = [__DIR__ . '/fonts', $CFG->dataroot . '/fonts'];

$path = null;
$mime = null;
foreach ($mimes as $ext => $extmime) {
    foreach ($dirs as $dir) {
        foreach ([$base, strtolower($base)] as $name) {
            $candidate = $dir . '/' . $name . '.' . $ext;
            if (is_readable($candidate)) {
                $path = $candidate;
                $mime = $extmime;
                break 3;
            }
        }
    }
}

if ($path === null) {
    send_file_not_found();
}

header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($path));
header('Cache-Control: public, max-age=31536000, immutable');
send_file($path, basename($path), 31536000, 0, false, false, $mime);
