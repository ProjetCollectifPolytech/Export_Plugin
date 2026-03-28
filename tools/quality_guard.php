<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

$root = dirname(__DIR__);
$issues = [];

$excludedsegments = [
    DIRECTORY_SEPARATOR . '.git' . DIRECTORY_SEPARATOR,
    DIRECTORY_SEPARATOR . 'tests' . DIRECTORY_SEPARATOR,
    DIRECTORY_SEPARATOR . 'lang' . DIRECTORY_SEPARATOR,
    DIRECTORY_SEPARATOR . 'docs' . DIRECTORY_SEPARATOR,
    DIRECTORY_SEPARATOR . 'tools' . DIRECTORY_SEPARATOR,
    DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR,
];

$maxlines = [
    'lib.php' => 270,
    'classes/controller/grade_export_controller.php' => 110,
    'classes/form/course_export_form.php' => 160,
    'classes/integration/activity_navigation_integration.php' => 230,
    'classes/integration/grade_export_bridge_integration.php' => 210,
    'classes/spreadsheet/format_university_standard.php' => 330,
    'classes/spreadsheet/openxml_workbook_writer.php' => 240,
];

$patterns = [
    [
        'regex' => '/\b(?:TODO|FIXME)\b/',
        'message' => 'contains a deferred-work marker',
    ],
    [
        'regex' => '/html_writer::script\s*\(/',
        'message' => 'uses inline html_writer::script',
    ],
    [
        'regex' => '/throw new \\\\?Exception\s*\(/',
        'message' => 'throws generic Exception directly',
    ],
];

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
);

foreach ($iterator as $file) {
    if (!$file->isFile() || $file->getExtension() !== 'php') {
        continue;
    }

    $path = $file->getPathname();
    $normalisedpath = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
    $skip = false;
    foreach ($excludedsegments as $segment) {
        if (strpos($normalisedpath, $segment) !== false) {
            $skip = true;
            break;
        }
    }
    if ($skip) {
        continue;
    }

    $relativepath = str_replace(['\\', '/'], '/', substr($path, strlen($root) + 1));
    $contents = file_get_contents($path);
    if ($contents === false) {
        $issues[] = $relativepath . ': could not be read';
        continue;
    }

    foreach ($patterns as $pattern) {
        if (preg_match($pattern['regex'], $contents)) {
            $issues[] = $relativepath . ': ' . $pattern['message'];
        }
    }

    if (isset($maxlines[$relativepath])) {
        $linecount = substr_count($contents, "\n") + 1;
        if ($linecount > $maxlines[$relativepath]) {
            $issues[] = $relativepath . ': exceeds ' . $maxlines[$relativepath] . ' lines (' . $linecount . ')';
        }
    }
}

if (!empty($issues)) {
    fwrite(STDERR, "Export_Plugin quality guard failed:\n");
    foreach ($issues as $issue) {
        fwrite(STDERR, ' - ' . $issue . "\n");
    }
    exit(1);
}

fwrite(STDOUT, "Export_Plugin quality guard passed.\n");
