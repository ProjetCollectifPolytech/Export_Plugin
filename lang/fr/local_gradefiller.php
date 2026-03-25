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
 * French language strings for local_gradefiller.
 *
 * @package    local_gradefiller
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['pluginname'] = 'Remplisseur de notes';

// Navigation.
$string['fill_grades'] = 'Remplir les notes dans un tableur';
$string['gradebook_export_selector_label'] = 'Grade Filler';

// Capabilities.
$string['gradefiller:view'] = 'Voir Grade Filler';
$string['gradefiller:process'] = 'Traiter des tableurs avec Grade Filler';
$string['gradefiller:use'] = 'Utiliser Grade Filler pour remplir les notes dans un tableur';

// Driver names.
$string['driver_anonymousgrader'] = 'Anonymous Grader';
$string['driver_offlinequiz'] = 'Test hors ligne (anonyme)';
$string['driver_papergrade'] = 'Papergrade (scanner)';

// Page.
$string['page_title'] = 'Remplir les notes dans un tableur';
$string['gradebook_export_page_title'] = 'Exporter les notes avec Grade Filler';
$string['gradebook_export_section'] = 'Template Grade Filler';
$string['gradebook_export_intro'] = 'Conservez les options natives d\'export Moodle, puis injectez le résultat dans votre classeur modèle.';
$string['gradebook_export_target_spreadsheet'] = 'Format du tableur cible';
$string['activity_info'] = 'Informations sur l\'activité';
$string['activity'] = 'Activité';
$string['type'] = 'Type';
$string['anonymous_supported'] = 'Notes anonymes supportées';
$string['read_only_notice'] = 'Vous pouvez consulter cette page, mais vous n\'avez pas la permission de traiter des tableurs.';

// Upload form.
$string['upload_spreadsheet'] = 'Téléverser votre tableur';
$string['spreadsheet_file'] = 'Fichier tableur';
$string['drag_drop_zone'] = 'Glissez-déposez votre fichier ici';
$string['or_click_to_select'] = 'ou cliquez pour sélectionner un fichier';
$string['file_formats_accepted'] = 'Formats acceptés : XLSX, XLSM';
$string['gradebook_template_file'] = 'Classeur modèle';
$string['gradebook_template_file_help'] = 'Téléversez le classeur modèle qui doit recevoir le tableau d\'export Moodle.';

$string['spreadsheet_format'] = 'Format du tableur';
$string['select_format'] = '-- Sélectionnez un format --';
$string['gradebook_export_format'] = 'Format d\'export Grade Filler';
$string['gradebook_export_download'] = 'Télécharger le classeur exporté';

$string['grade_source'] = 'Source des notes';
$string['source_auto'] = 'Détection automatique (ID Moodle ou anonyme)';
$string['source_standard'] = 'Standard (numéro ID utilisateur Moodle uniquement)';
$string['source_anonymous'] = 'Anonyme (codes spécifiques à l\'activité uniquement)';
$string['grade_source_help'] = 'Choisissez comment faire correspondre les identifiants dans votre fichier';

$string['btn_process_download'] = 'Traiter et télécharger le fichier rempli';

// Formats.
$string['format_university_standard_name'] = 'Apogee';
$string['format_university_standard_desc'] = 'Ignorer 17 lignes d\'en-tête, colonne A = ID, colonne E = note';
$string['gradebook_export_format_workbook_name'] = 'Classeur modèle';
$string['gradebook_export_format_workbook_desc'] = 'Téléversez un classeur XLSX. Grade Filler injecte l\'export Moodle classique dans une feuille nommée "Export Moodle".';

// Help.
$string['how_it_works'] = 'Comment ça marche';
$string['help_step1'] = 'Téléversez votre tableur contenant les identifiants des étudiants';
$string['help_step2'] = 'Sélectionnez le format qui correspond à la structure de votre fichier';
$string['help_step3'] = 'Choisissez la source des notes (IDs Moodle ou codes anonymes)';
$string['help_step4'] = 'Le plugin remplira les notes et téléchargera le fichier complété';

// Messages.
$string['file_processed'] = 'Fichier traité : {$a->matched} notes remplies, {$a->unmatched} non trouvées';

// Events.
$string['event_page_viewed'] = 'Page Grade Filler consultée';
$string['event_file_processed'] = 'Tableur Grade Filler traité';

// Privacy.
$string['privacy:metadata'] = 'Le plugin Grade Filler ne stocke pas de données personnelles.';

// Errors.
$string['error_activity_unsupported'] = 'Cette activité ne supporte pas Grade Filler.';
$string['error_invalid_action'] = 'Action demandée invalide.';
$string['error_invalid_grade_source'] = 'Source de notes sélectionnée invalide.';
$string['error_post_required'] = 'Une requête POST est requise pour cette action.';
$string['error_no_file'] = 'Aucun fichier téléversé';
$string['error_moving_file'] = 'Le fichier téléversé n\'a pas pu être déplacé dans le stockage temporaire';
$string['error_format_not_found'] = 'Format non trouvé : {$a}';
$string['error_reading_file'] = 'Erreur de lecture du fichier : {$a}';
$string['error_writing_file'] = 'Erreur d\'écriture du fichier : {$a}';
$string['error_format_invalid'] = 'Format de fichier invalide : {$a}';
$string['error_format_insufficient_rows'] = 'Le fichier doit avoir au moins {$a} lignes';
$string['error_format_no_identifiers'] = 'Aucun identifiant trouvé dans la colonne attendue';
$string['error_export_format_not_found'] = 'Format d\'export Grade Filler introuvable : {$a}';
$string['error_export_template_required'] = 'Un fichier modèle est requis';
$string['error_export_template_invalid'] = 'Classeur modèle invalide : {$a}';
$string['error_export_template_write'] = 'Impossible d\'écrire le classeur d\'export Grade Filler : {$a}';
$string['error_export_template_extension'] = 'Extension de classeur modèle non supportée : {$a}. Utilisez XLSX.';
$string['error_no_permission'] = 'Vous n\'avez pas la permission d\'accéder à cette page';
$string['error_no_grade_item'] = 'Cette activité n\'a pas d\'élément de notation';
$string['error_unsupported_extension'] = 'Extension de fichier non supportée : {$a}. Utilisez XLSX ou XLSM.';
