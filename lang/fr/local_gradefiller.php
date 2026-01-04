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
 * French language strings for local_gradefiller
 *
 * @package    local_gradefiller
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['pluginname'] = 'Remplisseur de Notes';

// Navigation.
$string['fill_grades'] = 'Remplir les notes dans un tableur';

// Capabilities.
$string['gradefiller:use'] = 'Utiliser le remplisseur de notes dans un tableur';

// Driver names.
$string['driver_offlinequiz'] = 'Test Hors Ligne (Anonyme)';

// Page.
$string['page_title'] = 'Remplir les Notes dans un Tableur';
$string['activity_info'] = 'Informations sur l\'activité';
$string['activity'] = 'Activité';
$string['type'] = 'Type';
$string['anonymous_supported'] = 'Notes anonymes supportées';

// Upload form.
$string['upload_spreadsheet'] = 'Téléverser votre Tableur';
$string['spreadsheet_file'] = 'Fichier tableur';
$string['drag_drop_zone'] = 'Glissez-déposez votre fichier ici';
$string['or_click_to_select'] = 'ou cliquez pour sélectionner un fichier';
$string['file_formats_accepted'] = 'Formats acceptés : XLSX, XLS, XLSM, ODS, CSV';

$string['spreadsheet_format'] = 'Format du tableur';
$string['select_format'] = '-- Sélectionnez un format --';

$string['grade_source'] = 'Source des notes';
$string['source_auto'] = 'Détection automatique (ID Moodle ou Anonyme)';
$string['source_standard'] = 'Standard (Numéro ID Utilisateur Moodle uniquement)';
$string['source_anonymous'] = 'Anonyme (Codes spécifiques à l\'activité uniquement)';
$string['grade_source_help'] = 'Choisissez comment faire correspondre les identifiants dans votre fichier';

$string['btn_process_download'] = 'Traiter et Télécharger le Fichier Rempli';

// Formats.
$string['format_university_standard_name'] = 'Apogé';
$string['format_university_standard_desc'] = 'Ignorer 17 lignes d\'en-tête, Colonne A = ID, Colonne E = Note';

// Help.
$string['how_it_works'] = 'Comment ça marche';
$string['help_step1'] = 'Téléversez votre tableur contenant les identifiants des étudiants';
$string['help_step2'] = 'Sélectionnez le format qui correspond à la structure de votre fichier';
$string['help_step3'] = 'Choisissez la source des notes (IDs Moodle ou codes anonymes)';
$string['help_step4'] = 'Le plugin remplira les notes et téléchargera le fichier complété';

// Messages.
$string['file_processed'] = 'Fichier traité : {$a->matched} notes remplies, {$a->unmatched} non trouvées';

// Errors.
$string['error_no_file'] = 'Aucun fichier téléversé';
$string['error_format_not_found'] = 'Format non trouvé : {$a}';
$string['error_reading_file'] = 'Erreur de lecture du fichier : {$a}';
$string['error_writing_file'] = 'Erreur d\'écriture du fichier : {$a}';
$string['error_format_invalid'] = 'Format de fichier invalide : {$a}';
$string['error_format_insufficient_rows'] = 'Le fichier doit avoir au moins {$a} lignes';
$string['error_format_no_identifiers'] = 'Aucun identifiant trouvé dans la colonne attendue';
$string['error_no_permission'] = 'Vous n\'avez pas la permission d\'accéder à cette page';
$string['error_no_grade_item'] = 'Cette activité n\'a pas d\'élément de notation';
