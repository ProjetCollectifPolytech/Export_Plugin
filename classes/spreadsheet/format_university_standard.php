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
 * University standard format implementation
 *
 * Format: Skip 17 lines, ID in Column A, Grade in Column E
 *
 * @package    local_gradefiller
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_gradefiller\spreadsheet;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/phpspreadsheet/vendor/autoload.php');

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Writer\Xls;
use PhpOffice\PhpSpreadsheet\Writer\Ods;
use PhpOffice\PhpSpreadsheet\Writer\Csv;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

/**
 * University standard format handler
 *
 * This format expects:
 * - First 17 rows are header/metadata
 * - Column A (index 0) contains student identifiers
 * - Column E (index 4) should receive grades
 *
 * @package    local_gradefiller
 */
class format_university_standard implements spreadsheet_format_interface {
    /** @var string[] Supported file extensions for this format */
    private const ALLOWED_EXTENSIONS = ['xlsx', 'xlsm'];

    /** @var int Number of header rows to skip */
    const HEADER_ROWS = 17;

    /** @var int Column index for identifier (0-based) */
    const COLUMN_IDENTIFIER = 0; // Column A

    /** @var int Column index for grade (0-based) */
    const COLUMN_GRADE = 4; // Column E

    /**
     * Get the human-readable name of this format
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('format_university_standard_name', 'local_gradefiller');
    }

    /**
     * Get the unique identifier for this format
     *
     * @return string
     */
    public function get_key(): string {
        return 'university_standard';
    }

    /**
     * Get the description of this format
     *
     * @return string
     */
    public function get_description(): string {
        return get_string('format_university_standard_desc', 'local_gradefiller');
    }

    /**
     * Get the file extensions supported by this spreadsheet format.
     *
     * @return string[]
     */
    public function get_supported_extensions(): array {
        return self::ALLOWED_EXTENSIONS;
    }

    /**
     * Get the upload field label for this spreadsheet format.
     *
     * @return string
     */
    public function get_upload_label(): string {
        return $this->get_name();
    }

    /**
     * Get the descriptive upload help for this spreadsheet format.
     *
     * @return string
     */
    public function get_upload_help(): string {
        return $this->get_description();
    }

    /**
     * Read identifiers from the spreadsheet
     *
     * @param string $filepath Path to the uploaded spreadsheet file
     * @return array Array of objects with properties: identifier, row_number
     * @throws \moodle_exception
     */
    public function read_identifiers(string $filepath): array {
        try {
            $spreadsheet = IOFactory::load($filepath);
            $sheet = $spreadsheet->getActiveSheet();
            $highestRow = $sheet->getHighestRow();

            $identifiers = [];
            for ($row = self::HEADER_ROWS + 1; $row <= $highestRow; $row++) {
                $identifier = $sheet->getCellByColumnAndRow(
                    self::COLUMN_IDENTIFIER + 1, 
                    $row
                )->getValue();

                // Skip empty rows.
                if (empty($identifier)) {
                    continue;
                }

                // Clean identifier.
                $identifier = trim($identifier);

                $identifiers[] = (object)[
                    'identifier' => $identifier,
                    'row_number' => $row,
                ];
            }

            return $identifiers;

        } catch (\Exception $e) {
            throw new \moodle_exception('error_reading_file', 'local_gradefiller', '', null, $e->getMessage());
        }
    }

    /**
     * Write grades to the spreadsheet using direct XML manipulation (ZipArchive)
     * This preserves Macros, VML, and ActiveX controls perfectly.
     *
     * @param string $filepath Path to the original spreadsheet file
     * @param array $grades Array of objects with properties: identifier, grade, row_number
     * @return string Path to the filled spreadsheet file
     * @throws \moodle_exception
     */
    public function write_grades(string $filepath, array $grades): string {
        // Validate the file extension before touching the workbook.
        $extension = $this->validate_extension($filepath);

        // 1. Préparation du fichier de sortie
        $tempdir = make_temp_directory('gradefiller');
        $outputfile = $tempdir . '/' . 'filled_' . time() . '.' . $extension;

        // On copie le fichier original (ne jamais travailler sur l'original)
        if (!copy($filepath, $outputfile)) {
            throw new \moodle_exception('error_writing_file', 'local_gradefiller', '', null, 'Could not copy temp file');
        }

        // 2. Utilisation de ZipArchive pour ouvrir le .xlsm sans le corrompre
        $zip = new \ZipArchive();
        if ($zip->open($outputfile) !== true) {
            throw new \moodle_exception('error_writing_file', 'local_gradefiller', '', null, 'Could not open XLSX/XLSM as ZIP');
        }

        // On cible la première feuille de calcul (standard pour ce type d'export)
        $sheetname = 'xl/worksheets/sheet1.xml';
        $xmlstring = $zip->getFromName($sheetname);

        if (!$xmlstring) {
            $zip->close();
            throw new \moodle_exception('error_writing_file', 'local_gradefiller', '', null, 'Could not find sheet1.xml');
        }

        // 3. Manipulation du XML avec DOMDocument
        $dom = new \DOMDocument();
        // Options pour éviter les warnings sur les namespaces
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = false; 
        $dom->loadXML($xmlstring);

        $xpath = new \DOMXPath($dom);
        // Enregistrement du namespace par défaut d'Excel
        $xpath->registerNamespace('x', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');

        // Création d'une map pour accès rapide : [row_number => grade]
        $grademap = [];
        foreach ($grades as $grade) {
            if (isset($grade->row_number) && isset($grade->grade)) {
                $grademap[$grade->row_number] = $grade->grade;
            }
        }

        // 4. Parcours et mise à jour des cellules
        // On cherche toutes les lignes qui sont dans notre map
        foreach ($grademap as $rownum => $gradeval) {
            // La colonne E correspond à la 5ème lettre. Dans le XML Excel, la référence est "E18" pour la ligne 18.
            $cellref = 'E' . $rownum;

            // Recherche de la cellule spécifique
            // Note: On cherche la balise <c> avec l'attribut r="E{row}"
            $entries = $xpath->query("//x:c[@r='$cellref']");

            if ($entries->length > 0) {
                $cell = $entries->item(0);

                // On supprime l'attribut 't' (type) s'il existe (pour éviter le type 's' string partagée)
                // On veut que ce soit un nombre direct
                if ($cell->hasAttribute('t')) {
                    $cell->removeAttribute('t');
                }

                // On cherche la balise <v> (valeur) à l'intérieur de la cellule
                $valuenodes = $xpath->query("x:v", $cell);
                
                if ($valuenodes->length > 0) {
                    // Mise à jour de la valeur existante
                    $valuenodes->item(0)->nodeValue = $gradeval;
                } else {
                    // Création de la balise valeur si elle n'existe pas (cellule vide)
                    $v = $dom->createElement('v', $gradeval);
                    $cell->appendChild($v);
                }
            } else {
                // Si la cellule n'existe pas, c'est plus complexe (il faut créer la row ou la cell).
                // Pour un template universitaire, on suppose que les lignes existent déjà (pré-remplies avec les IDs).
                // On log juste pour debug si besoin.
            }
        }

        // 5. Sauvegarde du XML modifié dans le ZIP
        $newxml = $dom->saveXML();
        $zip->addFromString($sheetname, $newxml);
        
        // Fermeture et finalisation
        $zip->close();

        return $outputfile;
    }

    /**
     * Validate that the file can be processed by this format
     *
     * @param string $filepath Path to the spreadsheet file
     * @return bool True if valid
     * @throws \moodle_exception with detailed error message
     */
    public function validate_file(string $filepath): bool {
        try {
            $this->validate_extension($filepath);

            $spreadsheet = IOFactory::load($filepath);
            $sheet = $spreadsheet->getActiveSheet();
            $highestRow = $sheet->getHighestRow();

            // Check if there are enough rows.
            if ($highestRow <= self::HEADER_ROWS) {
                throw new \moodle_exception(
                    'error_format_insufficient_rows',
                    'local_gradefiller',
                    '',
                    self::HEADER_ROWS + 1
                );
            }

            // Check if column A has data after header rows.
            $hasdata = false;
            for ($row = self::HEADER_ROWS + 1; $row <= min($highestRow, self::HEADER_ROWS + 10); $row++) {
                $value = $sheet->getCellByColumnAndRow(self::COLUMN_IDENTIFIER + 1, $row)->getValue();
                if (!empty(trim($value))) {
                    $hasdata = true;
                    break;
                }
            }

            if (!$hasdata) {
                throw new \moodle_exception('error_format_no_identifiers', 'local_gradefiller');
            }

            return true;

        } catch (\moodle_exception $e) {
            throw $e;
        } catch (\Exception $e) {
            throw new \moodle_exception('error_format_invalid', 'local_gradefiller', '', null, $e->getMessage());
        }
    }

    /**
     * Validate and return the spreadsheet extension supported by this format.
     *
     * @param string $filepath Path to the spreadsheet file
     * @return string
     * @throws \moodle_exception
     */
    private function validate_extension(string $filepath): string {
        $extension = strtolower(pathinfo($filepath, PATHINFO_EXTENSION));
        if (!in_array($extension, self::ALLOWED_EXTENSIONS, true)) {
            throw new \moodle_exception('error_unsupported_extension', 'local_gradefiller', '', $extension);
        }

        return $extension;
    }
}
