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
 * OpenXML workbook writer helpers.
 *
 * @package    local_gradefiller
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_gradefiller\spreadsheet;


use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;
use moodle_exception;
use ZipArchive;

/**
 * Writes numeric values directly into an OOXML worksheet while preserving
 * macros and workbook-level artefacts.
 *
 * @package    local_gradefiller
 */
class openxml_workbook_writer {
    /** @var string SpreadsheetML namespace */
    private const EXCEL_NS = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';

    /**
     * Copy a workbook template and update a set of numeric cell values.
     *
     * @param string $filepath
     * @param string $sheetname
     * @param array $cellvalues
     * @return string
     */
    public function write_numeric_cells(string $filepath, string $sheetname, array $cellvalues): string {
        $extension = strtolower(pathinfo($filepath, PATHINFO_EXTENSION));
        $outputfile = make_temp_directory('gradefiller') . '/' . 'filled_' . uniqid('', true) . '.' . $extension;

        if (!copy($filepath, $outputfile)) {
            throw new moodle_exception('error_file_write_failed', 'local_gradefiller');
        }

        $zip = new ZipArchive();
        if ($zip->open($outputfile) !== true) {
            throw new moodle_exception('error_file_write_failed', 'local_gradefiller');
        }

        $xmlstring = $zip->getFromName($sheetname);
        if ($xmlstring === false) {
            $zip->close();
            throw new moodle_exception('error_file_write_failed', 'local_gradefiller');
        }

        $dom = new DOMDocument();
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = false;
        if (!$dom->loadXML($xmlstring)) {
            $zip->close();
            throw new moodle_exception('error_file_write_failed', 'local_gradefiller');
        }

        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('x', self::EXCEL_NS);

        foreach ($cellvalues as $cellref => $value) {
            $cell = $this->get_or_create_cell($dom, $xpath, (string) $cellref);
            if ($cell->hasAttribute('t')) {
                $cell->removeAttribute('t');
            }

            $valuenodes = $xpath->query('x:v', $cell);
            if ($valuenodes->length > 0) {
                $valuenodes->item(0)->nodeValue = (string) $value;
            } else {
                $cell->appendChild($dom->createElementNS(self::EXCEL_NS, 'v', (string) $value));
            }
        }

        $zip->addFromString($sheetname, $dom->saveXML());
        $zip->close();

        return $outputfile;
    }

    /**
     * Resolve one cell, creating the row and cell when they are missing.
     *
     * @param \DOMDocument $dom
     * @param \DOMXPath $xpath
     * @param string $cellref
     * @return \DOMElement
     */
    private function get_or_create_cell(DOMDocument $dom, DOMXPath $xpath, string $cellref): DOMElement {
        $entries = $xpath->query('//x:c[@r="' . $cellref . '"]');
        if ($entries->length > 0) {
            return $entries->item(0);
        }

        $rownumber = $this->extract_row_number($cellref);
        $sheetdata = $xpath->query('/x:worksheet/x:sheetData')->item(0);
        if (!$sheetdata instanceof DOMElement) {
            throw new moodle_exception('error_file_write_failed', 'local_gradefiller');
        }
        $row = $this->get_or_create_row($dom, $xpath, $sheetdata, $rownumber);

        $cell = $dom->createElementNS(self::EXCEL_NS, 'c');
        $cell->setAttribute('r', $cellref);
        $this->insert_cell_in_order($row, $cell, $this->extract_column_letters($cellref));

        return $cell;
    }

    /**
     * Resolve one worksheet row, creating it in row order when missing.
     *
     * @param \DOMDocument $dom
     * @param \DOMXPath $xpath
     * @param \DOMNode $sheetdata
     * @param int $rownumber
     * @return \DOMElement
     */
    private function get_or_create_row(DOMDocument $dom, DOMXPath $xpath, DOMNode $sheetdata, int $rownumber): DOMElement {
        $rows = $xpath->query('//x:row[@r="' . $rownumber . '"]');
        if ($rows->length > 0) {
            return $rows->item(0);
        }

        $row = $dom->createElementNS(self::EXCEL_NS, 'row');
        $row->setAttribute('r', (string) $rownumber);

        foreach ($sheetdata->childNodes as $child) {
            if (!$child instanceof DOMElement || $child->localName !== 'row') {
                continue;
            }

            if ((int) $child->getAttribute('r') > $rownumber) {
                $sheetdata->insertBefore($row, $child);
                return $row;
            }
        }

        $sheetdata->appendChild($row);
        return $row;
    }

    /**
     * Insert a cell into a row while preserving column order.
     *
     * @param \DOMElement $row
     * @param \DOMElement $cell
     * @param string $columnletters
     * @return void
     */
    private function insert_cell_in_order(DOMElement $row, DOMElement $cell, string $columnletters): void {
        $targetindex = $this->column_letters_to_index($columnletters);

        foreach ($row->childNodes as $child) {
            if (!$child instanceof DOMElement || $child->localName !== 'c') {
                continue;
            }

            $childindex = $this->column_letters_to_index($this->extract_column_letters($child->getAttribute('r')));
            if ($childindex > $targetindex) {
                $row->insertBefore($cell, $child);
                return;
            }
        }

        $row->appendChild($cell);
    }

    /**
     * Extract the row number from a cell reference.
     *
     * @param string $cellref
     * @return int
     */
    private function extract_row_number(string $cellref): int {
        return (int) preg_replace('/[^0-9]/', '', $cellref);
    }

    /**
     * Extract the column letters from a cell reference.
     *
     * @param string $cellref
     * @return string
     */
    private function extract_column_letters(string $cellref): string {
        return preg_replace('/[^A-Z]/', '', strtoupper($cellref));
    }

    /**
     * Convert Excel column letters to a sortable numeric index.
     *
     * @param string $letters
     * @return int
     */
    private function column_letters_to_index(string $letters): int {
        $index = 0;
        $letters = strtoupper($letters);

        foreach (str_split($letters) as $char) {
            $index = ($index * 26) + (ord($char) - 64);
        }

        return $index;
    }
}
