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

/**
 * CLI script: fully reset and provision a local_gradefiller demo environment.
 *
 * What this script does (idempotent - safe to re-run):
 *   1. Deletes the previous demo course (if any) and re-creates it.
 *   2. Creates three test students and enrols them.
 *   3. Creates an Offline Quiz for "Standard" mode demo:
 *        - Students have real grades in the Moodle gradebook.
 *        - Sample spreadsheet uses student ID numbers (Etu001 / Etu002 / Etu003).
 *   4. Creates an Offline Quiz for "Anonymous" mode demo:
 *        - Results stored with userid=0 and anonymous barcodes.
 *        - Sample spreadsheet uses barcode keys (OFF-1111 / OFF-2222 / OFF-3333).
 *   5. Generates two ready-to-upload Apogée XLSX files in the cli/ directory:
 *        - sample_standard.xlsx  (identifiers = Moodle ID numbers)
 *        - sample_anonymous.xlsx (identifiers = barcode keys)
 *
 * Usage:
 *   php local/gradefiller/cli/reset_demo.php
 *
 * Then open the Grade Filler page on either activity to demonstrate the plugin:
 *   Activity 1 (Standard)  -> upload sample_standard.xlsx  -> source = Standard
 *   Activity 2 (Anonymous) -> upload sample_anonymous.xlsx -> source = Anonymous
 *
 * @package    local_gradefiller
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir  . '/clilib.php');
require_once($CFG->libdir  . '/gradelib.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->dirroot . '/user/lib.php');
require_once($CFG->dirroot . '/lib/enrollib.php');
require_once($CFG->libdir  . '/phpspreadsheet/vendor/autoload.php');

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

// ---------------------------------------------------------------------------
// Constants
// ---------------------------------------------------------------------------
define('DEMO_SHORTNAME', 'demo_gradefiller');
define('MAX_GRADE', 20.0);

// Students: username => [idnumber, firstname, lastname]
define('DEMO_STUDENTS', [
    'alice_demo'   => ['Etu001', 'Alice',   'Dupont'],
    'bob_demo'     => ['Etu002', 'Bob',     'Martin'],
    'charlie_demo' => ['Etu003', 'Charlie', 'Durand'],
]);

// Grades for the STANDARD activity (same order as DEMO_STUDENTS).
define('STANDARD_GRADES', [15.0, 12.5, 18.0]);

// Anonymous barcode keys + grades for the ANONYMOUS activity.
define('ANON_KEYS',   ['OFF-1111', 'OFF-2222', 'OFF-3333']);
define('ANON_GRADES', [14.0, 16.5, 9.0]);

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

/**
 * Create a bare Offline Quiz instance + course_module, bypassing full install.
 *
 * @param stdClass $course
 * @param int      $moduleid  mdl_modules.id for offlinequiz
 * @param string   $name
 * @return array [$offlinequiz, $cm]
 */
function gf_create_offlinequiz(stdClass $course, int $moduleid, string $name): array {
    global $DB;

    $oq = (object)[
        'course'                 => $course->id,
        'name'                   => $name,
        'intro'                  => 'Activite de demonstration generee automatiquement.',
        'introformat'            => FORMAT_HTML,
        'timeopen'               => 0,
        'timeclose'              => 0,
        'time'                   => 0,
        'grade'                  => MAX_GRADE,
        'numgroups'              => 1,
        'decimalpoints'          => 2,
        'review'                 => 0,
        'questionsperpage'       => 0,
        'docscreated'            => 0,
        'shufflequestions'       => 0,
        'shuffleanswers'         => 0,
        'printstudycodefield'    => 1,
        'papergray'              => 650,
        'fontsize'               => 10,
        'timecreated'            => time(),
        'timemodified'           => time(),
        'showquestioninfo'       => 0,
        'fileformat'             => 0,
        'showgrades'             => 0,
        'showtutorial'           => 0,
        'disableimgnewlines'     => 0,
        'experimentalevaluation' => 0,
    ];
    $oq->id = $DB->insert_record('offlinequiz', $oq);

    $cm = (object)[
        'course'             => $course->id,
        'module'             => $moduleid,
        'instance'           => $oq->id,
        'section'            => 0,
        'added'              => time(),
        'score'              => 0,
        'indent'             => 0,
        'visible'            => 1,
        'visibleold'         => 1,
        'groupmode'          => 0,
        'groupingid'         => 0,
        'completion'         => 0,
        'completionview'     => 0,
        'completionexpected' => 0,
        'showdescription'    => 0,
    ];
    $cm->id = $DB->insert_record('course_modules', $cm);

    // Map into course section 1.
    $section = $DB->get_record('course_sections', ['course' => $course->id, 'section' => 1]);
    if (!$section) {
        $section = (object)[
            'course'        => $course->id,
            'section'       => 1,
            'summary'       => '',
            'summaryformat' => FORMAT_HTML,
            'sequence'      => '',
            'visible'       => 1,
        ];
        $section->id = $DB->insert_record('course_sections', $section);
    }
    $section->sequence = empty($section->sequence)
        ? (string)$cm->id
        : $section->sequence . ',' . $cm->id;
    $DB->update_record('course_sections', $section);

    return [$oq, $cm];
}

/**
 * Generate an Apogée-format XLSX file (17 header rows, ID in col A, blank grade in col E).
 *
 * @param string   $filepath     Absolute path for the output file.
 * @param string   $sheetTitle   Title written in A1.
 * @param string[] $identifiers  List of identifier strings (one per data row).
 * @return void
 */
function gf_generate_sample_xlsx(string $filepath, string $sheetTitle, array $identifiers): void {
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Export');

    // ---- 17 header / metadata rows (mimic Apogée export layout) ----
    $sheet->setCellValue('A1', $sheetTitle);
    $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(12);

    $metaLabels = [
        'A2'  => 'Etablissement:',   'B2'  => 'Université Démo',
        'A3'  => 'Composante:',      'B3'  => 'UFR Informatique',
        'A4'  => 'Diplôme:',         'B4'  => 'Licence Informatique',
        'A5'  => 'Version diplôme:', 'B5'  => '2025-2026',
        'A6'  => 'Etape:',           'B6'  => 'L3 Informatique',
        'A7'  => 'Année:',           'B7'  => '2025-2026',
        'A8'  => 'Session:',         'B8'  => 'Initiale',
        'A9'  => 'Epreuve:',         'B9'  => $sheetTitle,
        'A10' => 'Code épreuve:',    'B10' => 'INF301',
        'A11' => 'Type épreuve:',    'B11' => 'CC',
        'A12' => 'Coefficient:',     'B12' => '1',
        'A13' => 'Barème:',          'B13' => '20',
        'A14' => 'Date export:',     'B14' => date('d/m/Y'),
        'A15' => '',
        'A16' => 'N° Etudiant',      'B16' => 'Nom',
        'A17' => '',                 // blank separator row
    ];
    foreach ($metaLabels as $cell => $value) {
        $sheet->setCellValue($cell, $value);
    }

    // Bold the column headers on row 16.
    $sheet->getStyle('A16:E16')->getFont()->setBold(true);
    $sheet->setCellValue('E16', 'Note');

    // ---- Data rows (from row 18 = HEADER_ROWS + 1) ----
    $row = 18;
    foreach ($identifiers as $identifier) {
        $sheet->setCellValue('A' . $row, $identifier);
        // Column E: placeholder 0 so the cell exists in XML (write_grades can update it).
        $sheet->setCellValue('E' . $row, 0);
        $row++;
    }

    // Auto-size column A.
    $sheet->getColumnDimension('A')->setAutoSize(true);
    $sheet->getColumnDimension('E')->setWidth(10);

    $writer = new Xlsx($spreadsheet);
    $writer->save($filepath);
}

// ---------------------------------------------------------------------------
// Main
// ---------------------------------------------------------------------------

cli_heading('Provisioning local_gradefiller demo environment');

try {
    $dbman = $DB->get_manager();

    // -----------------------------------------------------------------------
    // 1. (Re)create the demo course.
    // -----------------------------------------------------------------------
    $old = $DB->get_record('course', ['shortname' => DEMO_SHORTNAME]);
    if ($old) {
        cli_writeln('[..] Deleting previous demo course ...');
        delete_course($old, false);
    }

    $course = create_course((object)[
        'shortname' => DEMO_SHORTNAME,
        'fullname'  => 'Demo : Grade Filler',
        'category'  => 1,
        'visible'   => 1,
    ]);
    cli_writeln("[OK] Course \"{$course->fullname}\" created (id={$course->id}).");

    // -----------------------------------------------------------------------
    // 2. Create / update students and enrol them.
    // -----------------------------------------------------------------------
    $enrolplugin   = enrol_get_plugin('manual');
    $enrolinstance = $DB->get_record('enrol', ['courseid' => $course->id, 'enrol' => 'manual']);
    $studentrole   = $DB->get_record('role', ['shortname' => 'student']);

    $studentids = [];
    foreach (DEMO_STUDENTS as $username => [$idnumber, $firstname, $lastname]) {
        $existing = $DB->get_record('user', ['username' => $username, 'deleted' => 0]);
        if ($existing) {
            $existing->idnumber  = $idnumber;
            $existing->firstname = $firstname;
            $existing->lastname  = $lastname;
            $DB->update_record('user', $existing);
            $user = $existing;
        } else {
            $uid = user_create_user((object)[
                'username'   => $username,
                'idnumber'   => $idnumber,
                'firstname'  => $firstname,
                'lastname'   => $lastname,
                'email'      => $username . '@example.com',
                'password'   => 'DemoPass1!',
                'confirmed'  => 1,
                'mnethostid' => $CFG->mnet_localhost_id,
            ], false, false);
            $user = $DB->get_record('user', ['id' => $uid]);
        }
        $enrolplugin->enrol_user($enrolinstance, $user->id, $studentrole->id);
        $studentids[$idnumber] = $user->id;
    }
    cli_writeln('[OK] Students created and enrolled (' .
        implode(', ', array_keys(DEMO_STUDENTS)) . ').');

    // -----------------------------------------------------------------------
    // 3. Check that mod_offlinequiz is installed.
    // -----------------------------------------------------------------------
    $oqmod = $DB->get_record('modules', ['name' => 'offlinequiz']);
    if (!$oqmod) {
        cli_error('mod_offlinequiz is not installed. Cannot continue.');
    }

    // -----------------------------------------------------------------------
    // 4. Activity 1: Standard mode — real grades in Moodle gradebook.
    // -----------------------------------------------------------------------
    list($oq1, $cm1) = gf_create_offlinequiz($course, $oqmod->id, 'QCM Standard (Apogée)');

    // Get (or create) the course-level grade category.
    $coursecategory = grade_category::fetch_course_category($course->id);

    // Create the grade item for oq1.
    $gradeitem1 = new grade_item();
    $gradeitem1->courseid    = $course->id;
    $gradeitem1->categoryid  = $coursecategory->id;
    $gradeitem1->itemname    = $oq1->name;
    $gradeitem1->itemtype    = 'mod';
    $gradeitem1->itemmodule  = 'offlinequiz';
    $gradeitem1->iteminstance = $oq1->id;
    $gradeitem1->itemnumber  = 0;
    $gradeitem1->gradetype   = GRADE_TYPE_VALUE;
    $gradeitem1->grademax    = MAX_GRADE;
    $gradeitem1->grademin    = 0;
    $gradeitem1->gradepass   = 10;
    $gradeitem1->display     = 0;
    $gradeitem1->decimals    = 2;
    $gradeitem1->locked      = 0;
    $gradeitem1->hidden      = 0;
    $gradeitem1->timecreated  = time();
    $gradeitem1->timemodified = time();
    $gradeitem1->insert();

    // Insert a grade for each student.
    $studentIdnumbers = array_keys(array_column(DEMO_STUDENTS, 0, null));
    // Rebuild as idnumber => grade.
    $idnumbers = array_keys(DEMO_STUDENTS);
    $idnumberList = array_map(fn($k) => DEMO_STUDENTS[$k][0], array_keys(DEMO_STUDENTS));
    $standardGrades = array_combine($idnumberList, STANDARD_GRADES);

    foreach ($standardGrades as $idnumber => $gradevalue) {
        if (!isset($studentids[$idnumber])) {
            continue;
        }
        $gradegrade = new grade_grade();
        $gradegrade->itemid         = $gradeitem1->id;
        $gradegrade->userid         = $studentids[$idnumber];
        $gradegrade->rawgrade       = $gradevalue;
        $gradegrade->rawgrademax    = MAX_GRADE;
        $gradegrade->rawgrademin    = 0;
        $gradegrade->finalgrade     = $gradevalue;
        $gradegrade->hidden         = 0;
        $gradegrade->locked         = 0;
        $gradegrade->exported       = 0;
        $gradegrade->timecreated    = time();
        $gradegrade->timemodified   = time();
        $gradegrade->usermodified   = 2;
        $gradegrade->aggregationstatus = 'used';
        $gradegrade->aggregationweight = 1;
        $gradegrade->insert();
    }

    cli_writeln('[OK] Activity 1 "QCM Standard" created with gradebook entries:');
    foreach ($standardGrades as $idnumber => $grade) {
        cli_writeln("       $idnumber => $grade / " . MAX_GRADE);
    }

    // -----------------------------------------------------------------------
    // 5. Activity 2: Anonymous mode — userid=0 results + barcode userkeys.
    // -----------------------------------------------------------------------
    list($oq2, $cm2) = gf_create_offlinequiz($course, $oqmod->id, 'QCM Anonyme (Barcodes)');

    // Offlinequiz group (required FK for results/scanned pages).
    $group2 = (object)[
        'offlinequizid'   => $oq2->id,
        'groupnumber'     => 1,
        'sumgrades'       => MAX_GRADE,
        'numberofpages'   => 1,
        'templateusageid' => 0,
    ];
    $group2->id = $DB->insert_record('offlinequiz_groups', $group2);

    // One result per barcode key (userid = 0 = anonymous).
    $anonKeys   = ANON_KEYS;
    $anonGrades = ANON_GRADES;
    foreach ($anonKeys as $i => $barcode) {
        $resultid = $DB->insert_record('offlinequiz_results', (object)[
            'offlinequizid'  => $oq2->id,
            'offlinegroupid' => $group2->id,
            'userid'         => 0,
            'sumgrades'      => $anonGrades[$i],
            'usageid'        => 0,
            'teacherid'      => 0,
            'attendant'      => 'scanonly',
            'status'         => 'complete',
            'timestart'      => time(),
            'timemodified'   => time(),
        ]);
        $DB->insert_record('offlinequiz_scanned_pages', (object)[
            'offlinequizid' => $oq2->id,
            'groupnumber'   => $group2->id,
            'userkey'       => $barcode,
            'resultid'      => $resultid,
            'status'        => 'ok',
            'time'          => time(),
        ]);
    }

    cli_writeln('[OK] Activity 2 "QCM Anonyme" created with anonymous results:');
    foreach ($anonKeys as $i => $barcode) {
        cli_writeln("       $barcode => {$anonGrades[$i]} / " . MAX_GRADE);
    }

    // -----------------------------------------------------------------------
    // 6. Rebuild course cache so both activities appear in navigation.
    // -----------------------------------------------------------------------
    rebuild_course_cache($course->id, true);

    // -----------------------------------------------------------------------
    // 7. Generate sample Apogée XLSX files.
    // -----------------------------------------------------------------------
    $idnumbersOnly = array_map(fn($v) => $v[0], array_values(DEMO_STUDENTS));

    $path1 = __DIR__ . '/sample_standard.xlsx';
    gf_generate_sample_xlsx(
        $path1,
        'QCM Standard — Apogée Export',
        $idnumbersOnly
    );

    $path2 = __DIR__ . '/sample_anonymous.xlsx';
    gf_generate_sample_xlsx(
        $path2,
        'QCM Anonyme — Apogée Export',
        ANON_KEYS
    );

    cli_writeln('[OK] Sample XLSX files generated in local/gradefiller/cli/:');
    cli_writeln('       - sample_standard.xlsx  (identifiers: ' .
        implode(', ', $idnumbersOnly) . ')');
    cli_writeln('       - sample_anonymous.xlsx (identifiers: ' .
        implode(', ', ANON_KEYS) . ')');

    // -----------------------------------------------------------------------
    // Done – display presenter cheatsheet.
    // -----------------------------------------------------------------------
    $baseurl = $CFG->wwwroot;

    cli_writeln('');
    cli_writeln('=== SETUP COMPLETE ===');
    cli_writeln('');
    cli_writeln('---- DEMO SCENARIO 1 : Standard (ID étudiant) ----');
    cli_writeln("  Activity : \"QCM Standard (Apogée)\"  (cmid={$cm1->id})");
    cli_writeln("  File     : local/gradefiller/cli/sample_standard.xlsx");
    cli_writeln("  Option   : Grade source = Standard");
    cli_writeln("  URL      : {$baseurl}/local/gradefiller/index.php?id={$cm1->id}");
    cli_writeln('');
    cli_writeln('---- DEMO SCENARIO 2 : Anonyme (barcodes) ----');
    cli_writeln("  Activity : \"QCM Anonyme (Barcodes)\"  (cmid={$cm2->id})");
    cli_writeln("  File     : local/gradefiller/cli/sample_anonymous.xlsx");
    cli_writeln("  Option   : Grade source = Anonymous");
    cli_writeln("  URL      : {$baseurl}/local/gradefiller/index.php?id={$cm2->id}");
    cli_writeln('');
    cli_writeln("Course URL : {$baseurl}/course/view.php?id={$course->id}");

} catch (Exception $e) {
    cli_error("Fatal error: " . $e->getMessage() . "\n" . $e->getTraceAsString());
}
