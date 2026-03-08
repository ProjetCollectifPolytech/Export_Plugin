<?php
defined('MOODLE_INTERNAL') || die();

$dir = __DIR__;
$moodleroot = null;

for ($i = 0; $i < 6; $i++) {
    $dir = dirname($dir);
    if (file_exists($dir . '/config.php') && file_exists($dir . '/lib/setup.php')) {
        $moodleroot = $dir;
        break;
    }
}

if ($moodleroot === null) {
    echo "ERROR: Could not locate Moodle root directory.\n";
    exit(1);
}

require_once($moodleroot . '/lib/phpunit/bootstrap.php');
