# Documentation Développeur — local_gradefiller (v1.2)

**Plugin Moodle — Remplisseur de Notes dans un Tableur**

- **Date de rédaction :** 28 février 2026
- **Composant :** local_gradefiller
- **Version :** 2026022800
- **Moodle requis :** 4.3+ (2023100900)
- **Maturité :** MATURITY_ALPHA

---

## Table des matières

1. [Vue d'ensemble de l'architecture](#1-vue-densemble-de-larchitecture)
2. [Arborescence des fichiers](#2-arborescence-des-fichiers)
3. [Patterns de conception (Design Patterns)](#3-patterns-de-conception-design-patterns)
4. [Flux d'exécution principal](#4-flux-dexécution-principal)
5. [Classes et interfaces détaillées](#5-classes-et-interfaces-détaillées)
6. [Système de navigation et permissions](#6-système-de-navigation-et-permissions)
7. [Frontend (Templates et JavaScript)](#7-frontend-templates-et-javascript)
8. [Fichiers de langue (i18n)](#8-fichiers-de-langue-i18n)
9. [Guide : Ajouter un nouveau format de tableur](#9-guide--ajouter-un-nouveau-format-de-tableur)
10. [Guide : Ajouter un nouveau driver de source de notes](#10-guide--ajouter-un-nouveau-driver-de-source-de-notes)
11. [Limitations connues et pistes d'amélioration](#11-limitations-connues-et-pistes-damélioration)
12. [Conventions et standards de code](#12-conventions-et-standards-de-code)

---

## 1. Vue d'ensemble de l'architecture

Le plugin est construit autour de deux axes stratégiques (Strategy Pattern) :

1. **Format de tableur (Spreadsheet Format)**
   - Définit **COMMENT** lire/écrire un fichier tableur spécifique.
   - Interface : `spreadsheet_format_interface`
   - Implémentation : `format_university_standard` (Apogée)

2. **Source de notes (Grade Source / Driver)**
   - Définit **D'OÙ** récupérer les notes (gradebook standard ou anonyme).
   - Interface : `grade_source_interface`
   - Implémentations : `grade_source_offlinequiz`, `grade_source_anonymousgrader`

La classe `manager` orchestre l'ensemble du workflow :

```
Fichier uploadé -> Format -> Lecture identifiants -> Source -> Notes -> Écriture
```

### Diagramme simplifié

```
[index.php] --> [action/process_upload] --> [manager::process_file()]
                                                |
                                  +-------------+-------------+
                                  |                           |
                        [spreadsheet_format]          [grade_source]
                        read_identifiers()            fetch_grade()
                        write_grades()                fetch_grade_by_anonkey()
```

---

## 2. Arborescence des fichiers

```
local/gradefiller/
│
├── index.php                     Point d'entrée principal (contrôleur)
├── lib.php                       Hooks Moodle (navigation)
├── version.php                   Métadonnées du plugin
├── styles.css                    Styles CSS du formulaire
│
├── classes/
│   ├── manager.php               Orchestrateur principal (business logic)
│   │
│   ├── action/
│   │   ├── base_action.php       Classe abstraite des action handlers
│   │   └── process_upload.php    Handler de traitement d'upload
│   │
│   ├── source/
│   │   ├── grade_source_interface.php          Interface des drivers
│   │   ├── grade_source_offlinequiz.php        Driver Offline Quiz
│   │   └── grade_source_anonymousgrader.php    Driver Anonymous Grader
│   │
│   ├── spreadsheet/
│   │   ├── spreadsheet_format_interface.php    Interface des formats
│   │   └── format_university_standard.php      Format Apogée
│   │
│   └── util/
│       ├── file_handler.php      Utilitaire gestion de fichiers
│       └── download_handler.php  Utilitaire envoi de téléchargement
│
├── db/
│   └── access.php                Définition des capabilities
│
├── lang/
│   ├── en/local_gradefiller.php  Chaînes anglaises
│   └── fr/local_gradefiller.php  Chaînes françaises
│
├── templates/
│   └── upload_form.mustache      Template Mustache du formulaire
│
└── amd/
    ├── src/upload_form.js        Module AMD (drag & drop, UX)
    └── build/upload_form.min.js  Version minifiée
```

---

## 3. Patterns de conception (Design Patterns)

### 3.1 Strategy Pattern (Formats de tableur)

**Interface :** `local_gradefiller\spreadsheet\spreadsheet_format_interface`

Méthodes requises :

| Méthode | Retour | Description |
|---|---|---|
| `get_name()` | `string` | Nom humain du format |
| `get_key()` | `string` | Clé unique du format |
| `get_description()` | `string` | Description du format |
| `read_identifiers($filepath)` | `array` | Lire les identifiants |
| `write_grades($filepath, $grades)` | `string` | Écrire les notes |
| `validate_file($filepath)` | `bool` | Valider le fichier |

Le `manager` sélectionne le format via `get_format($formatkey)`.

### 3.2 Strategy Pattern (Sources de notes)

**Interface :** `local_gradefiller\source\grade_source_interface`

Méthodes requises :

| Méthode | Retour | Description |
|---|---|---|
| `get_name()` | `string` | Nom du driver |
| `supports($cm)` | `bool` | Supporte cette activité ? |
| `fetch_grade_by_anonkey($cmid, $anonkey)` | `?object` | Chercher note |
| `is_anonymous_identifier($identifier)` | `bool` | Est-ce un ID anonyme ? |

Le `manager` sélectionne le driver via `get_driver_for_cm($cm)`.

### 3.3 Command Pattern (Actions)

**Classe abstraite :** `local_gradefiller\action\base_action`

- Le constructeur initialise le contexte (CM, cours, permissions).
- Chaque action implémente `execute(): void`.
- Les actions sont dispatchées dynamiquement par `index.php` via le paramètre `action` (nom de la classe dans le namespace `action/`).

Flux dans `index.php` :

```php
$actionclass = "\\local_gradefiller\\action\\{$action}";
$handler = new $actionclass($cmid);
$handler->execute();
```

### 3.4 Front Controller

`index.php` agit comme front controller unique :

- Gère l'authentification et les permissions.
- Dispatche les actions POST.
- Affiche le formulaire en GET.

---

## 4. Flux d'exécution principal

### 4.1 Affichage du formulaire (GET)

1. `index.php` reçoit le paramètre `?id=<cmid>`
2. Vérifications : `require_login()`, `require_capability()`
3. Initialisation du `manager`
4. Récupération des formats disponibles (`get_available_formats()`)
5. Détection du support anonyme (`get_driver_for_cm()`)
6. Rendu du template `upload_form.mustache` avec les données
7. Chargement du module AMD `upload_form.js`

### 4.2 Traitement de l'upload (POST)

1. `index.php` détecte `action=process_upload` et POST
2. Vérification sesskey (CSRF)
3. Instanciation de `process_upload` (extends `base_action`)
4. `base_action::__construct()` :
   - Charge CM, cours, contexte
   - `require_login` + `require_capability`
   - Instancie `manager`
5. `process_upload::execute()` :
   1. Récupère les paramètres (format, gradesource)
   2. Déplace le fichier uploadé vers `/temp/gradefiller/`
   3. Appelle `manager::process_file()`
6. `manager::process_file()` :
   1. Obtient le format handler (`get_format()`)
   2. Valide le fichier (`format->validate_file()`)
   3. Lit les identifiants (`format->read_identifiers()`)
   4. Pour chaque identifiant :
      - **Mode standard :** `fetch_grade_from_standard()`
        - Cherche user par idnumber
        - Vérifie inscription au cours
        - Récupère `grade_grade` via l'API gradebook
      - **Mode anonyme :** `fetch_grade_from_anonymous()`
        - Trouve le driver approprié
        - Appelle `driver->fetch_grade_by_anonkey()`
   5. Écrit les notes (`format->write_grades()`)
   6. Retourne le chemin du fichier rempli + statistiques
7. `process_upload` détecte l'extension et génère le nom de téléchargement
8. `download_handler::send_file()` envoie le fichier et termine l'exécution

---

## 5. Classes et interfaces détaillées

### 5.1 `manager` — `classes/manager.php`

**Namespace :** `local_gradefiller`
**Rôle :** Orchestrateur principal

**Propriétés :**

| Propriété | Type | Description |
|---|---|---|
| `$formats` | `array\|null` | Cache des formats disponibles |
| `$drivers` | `array\|null` | Cache des drivers disponibles |

**Méthodes publiques :**

- **`get_available_formats(): array`**
  Retourne tous les formats de tableur enregistrés.
  Actuellement : `[format_university_standard]`

- **`get_format(string $formatkey): ?spreadsheet_format_interface`**
  Retourne un format par sa clé unique.

- **`get_available_drivers(): array`**
  Retourne tous les drivers de source de notes.
  Actuellement : `[grade_source_offlinequiz]`
  > **Note :** `grade_source_anonymousgrader` n'est PAS enregistré dans cette méthode (à ajouter si nécessaire).

- **`get_driver_for_cm($cm): ?grade_source_interface`**
  Retourne le premier driver compatible avec le CM donné.

- **`fetch_grade(string $identifier, int $cmid, int $courseid, string $gradesource): ?object`**
  Point d'entrée principal pour la récupération de notes.
  Retourne : `{grade, maxgrade, userid, source}` ou `null`.

- **`process_file(string $filepath, string $formatkey, int $cmid, int $courseid, string $gradesource): array`**
  Orchestration complète du traitement.
  Retourne : `['filepath' => string, 'stats' => array]`
  Stats : `{total, matched, unmatched, errors}`

**Méthodes privées :**

- **`fetch_grade_from_standard(string $idnumber, int $cmid, int $courseid)`**
  Recherche via idnumber Moodle + API gradebook.

- **`fetch_grade_from_anonymous(string $anonkey, int $cmid)`**
  Recherche via le driver anonyme approprié.

### 5.2 `base_action` — `classes/action/base_action.php`

**Namespace :** `local_gradefiller\action`
**Type :** Classe abstraite

**Propriétés protégées :**

| Propriété | Type |
|---|---|
| `$cmid` | `int` |
| `$cm` | `stdClass` |
| `$course` | `stdClass` |
| `$context` | `context_module` |
| `$manager` | `manager` |

- **Constructeur :** Initialise tout le contexte + authentification.
- **Méthode abstraite :** `execute(): void`

### 5.3 `process_upload` — `classes/action/process_upload.php`

**Namespace :** `local_gradefiller\action`
**Extends :** `base_action`

**`execute()` :**

- Lit les paramètres : `format` (`PARAM_ALPHANUMEXT`), `gradesource` (`PARAM_ALPHA`)
- Gère le fichier uploadé via `$_FILES['spreadsheet']`
- Appelle `manager->process_file()`
- Envoie le résultat via `download_handler::send_file()`
- Gère le cleanup en cas d'erreur (`try/catch`)

### 5.4 `format_university_standard` — `classes/spreadsheet/format_university_standard.php`

**Namespace :** `local_gradefiller\spreadsheet`
**Implements :** `spreadsheet_format_interface`

**Constantes :**

| Constante | Valeur | Description |
|---|---|---|
| `HEADER_ROWS` | `17` | Lignes d'en-tête à ignorer |
| `COLUMN_IDENTIFIER` | `0` | Colonne A (0-based) |
| `COLUMN_GRADE` | `4` | Colonne E (0-based) |

**`read_identifiers($filepath)` :**

- Utilise PhpSpreadsheet `IOFactory::load()` (auto-détection du format)
- Parcourt les lignes à partir de `HEADER_ROWS + 1`
- Retourne `[{identifier, row_number}, ...]`

**`write_grades($filepath, $grades)` :**

- Copie le fichier original vers un fichier temporaire
- Ouvre le fichier comme `ZipArchive`
- Extrait `xl/worksheets/sheet1.xml`
- Manipule le XML via `DOMDocument` + `DOMXPath`
- Pour chaque note :
  - Cherche la cellule `E{row}` dans le XML
  - Supprime l'attribut `t="s"` (type string) si présent
  - Met à jour ou crée la balise `<v>` (valeur)
- Sauvegarde le XML modifié dans le ZIP
- Retourne le chemin du fichier rempli

> **IMPORTANT :** Cette approche ZipArchive préserve les macros VBA, les contrôles ActiveX et les éléments VML, contrairement à PhpSpreadsheet qui les supprime à la réécriture.

> **LIMITATION :** Ne fonctionne qu'avec les formats basés sur ZIP (xlsx, xlsm). Les formats xls (binaire) et csv (texte) ne sont pas supportés en écriture.

**`validate_file($filepath)` :**

- Vérifie que le fichier a suffisamment de lignes (`> HEADER_ROWS`)
- Vérifie la présence de données dans la colonne A après l'en-tête

### 5.5 `grade_source_offlinequiz` — `classes/source/grade_source_offlinequiz.php`

**Namespace :** `local_gradefiller\source`
**Implements :** `grade_source_interface`

- **`supports($cm)` :** Retourne `true` si `$cm->modname === 'offlinequiz'`

- **`fetch_grade_by_anonkey($cmid, $anonkey)` :**
  - **Stratégie 1 :** Cherche dans `offlinequiz_results` + `offlinequiz_scanned_pages` où `userid = 0` et `userkey = $anonkey` (résultats anonymes).
  - **Stratégie 2 :** Fallback (retourne `null` si pas de correspondance).
  - Retourne : `{grade, maxgrade}` ou `null`.

- **`is_anonymous_identifier($identifier)` :**
  Accepte tout identifiant non vide (le mode anonyme est choisi explicitement par l'enseignant).

### 5.6 `grade_source_anonymousgrader` — `classes/source/grade_source_anonymousgrader.php`

**Namespace :** `local_gradefiller\source`
**Implements :** `grade_source_interface`

- **`supports($cm)` :**
  - Vérifie que `$cm->modname === 'offlinequiz'`
  - Vérifie l'existence de la table `local_anonymousgrader_exam`
  - Vérifie qu'un enregistrement exam existe pour cette instance

- **`fetch_grade_by_anonkey($cmid, $anonkey)` :**
  - Cherche dans `local_anonymousgrader_results`
  - Filtre par `status = 'validated'`
  - Retourne : `{grade, maxgrade}` ou `null`.

- **`is_anonymous_identifier($identifier)` :**
  Retourne `true` si l'identifiant est numérique.

> **NOTE :** Ce driver n'est PAS enregistré dans `manager::get_available_drivers()`. Pour l'activer, il faut l'ajouter dans cette méthode.

### 5.7 `download_handler` — `classes/util/download_handler.php`

**Namespace :** `local_gradefiller\util`

**`MIME_TYPES` :** Constante de classe avec les types MIME pour : xlsx, xlsm, xlsb, xls, ods, csv

- **`send_file($filepath, $downloadname)` :**
  - Nettoie les buffers de sortie (`ob_end_clean`)
  - Envoie les headers HTTP (Content-Type, Content-Disposition, etc.)
  - `readfile()` + `unlink()` + `die()`
  - ⚠️ **Termine l'exécution du script !**

- **`generate_filename($prefix, $extension)` :**
  Retourne : `{prefix}_{YYYY-MM-DD_HHmmss}.{extension}`

### 5.8 `file_handler` — `classes/util/file_handler.php`

**Namespace :** `local_gradefiller\util`

- **`handle_upload($filekey)` :**
  Valide `$_FILES[$filekey]`, déplace vers `make_temp_directory('gradefiller')`, retourne le chemin du fichier temporaire.

- **`cleanup($filepath)` :**
  Supprime le fichier si il existe.

- **`validate_extension($filename, $allowedext)` :**
  Vérifie l'extension du fichier contre une liste autorisée.

> **NOTE :** Cette classe utilitaire n'est PAS utilisée directement par `process_upload` (qui gère l'upload en interne). Elle est disponible pour d'éventuelles extensions futures.

---

## 6. Système de navigation et permissions

### 6.1 Capability

Définition dans `db/access.php` :

| Propriété | Valeur |
|---|---|
| **Capability** | `local/gradefiller:use` |
| **Type** | `write` |
| **Contexte** | `CONTEXT_COURSE` |
| **Risques** | `RISK_SPAM \| RISK_PERSONAL \| RISK_XSS` |
| **Archétypes autorisés** | `editingteacher` → `CAP_ALLOW`, `manager` → `CAP_ALLOW` |

### 6.2 Hooks de navigation (`lib.php`)

Deux hooks sont implémentés :

1. **`local_gradefiller_extend_settings_navigation()`**
   - Ajoute le lien dans le menu d'administration du module.
   - Conditions : page de module, capability, grade item ou driver.
   - Position : sous le noeud `modulesettings`.

2. **`local_gradefiller_extend_navigation_course()`**
   - Ajoute un lien dans la navigation secondaire du cours.
   - Conditions : page de module, capability, grade item.

### 6.3 Sécurité

- `require_login()` est appelé dans `index.php` ET dans `base_action`.
- `require_capability()` vérifie `local/gradefiller:use`.
- `confirm_sesskey()` protège contre les attaques CSRF sur les POST.
- `clean_filename()` est utilisé pour nettoyer les noms de fichiers.
- Les fichiers temporaires sont nettoyés après traitement.

---

## 7. Frontend (Templates et JavaScript)

### 7.1 Template Mustache — `templates/upload_form.mustache`

**Contexte requis :**

| Variable | Type | Description |
|---|---|---|
| `cmid` | `int` | ID du module de cours |
| `activity_name` | `string` | Nom de l'activité |
| `activity_type` | `string` | Type de l'activité |
| `formats` | `array` | `[{key, name, description}, ...]` |
| `supports_anonymous` | `bool` | Support anonyme disponible |
| `driver_name` | `string` | Nom du driver anonyme |
| `sesskey` | `string` | Token CSRF |
| `wwwroot` | `string` | URL racine Moodle |

**Structure du formulaire :**

- Zone d'information de l'activité (alert)
- Zone de drag & drop pour le fichier (accept: `.xlsx,.xls,.xlsm,.ods,.csv`)
- Sélection du format (dropdown)
- Sélection de la source de notes (dropdown)
- Bouton de soumission
- Section d'aide

Le formulaire est soumis en POST avec `enctype="multipart/form-data"`.
Champs hidden : `id`, `action=process_upload`, `sesskey`.

### 7.2 Module AMD — `amd/src/upload_form.js`

**Module :** `local_gradefiller/upload_form`
**Dépendances :** aucune (vanilla JS)

Fonctionnalités :

- Drag & drop sur la zone de fichier
- Affichage du nom du fichier sélectionné
- Changement visuel de la zone (classe `has-file`, `drag-over`)
- Affichage de la description du format sélectionné

Initialisation : appelé via `require()` dans le template Mustache.

Pour recompiler après modification :

```bash
npx grunt amd   # depuis le répertoire racine Moodle
```

### 7.3 Styles CSS — `styles.css`

Styles principaux :

| Sélecteur | Description |
|---|---|
| `.file-drop-zone` | Zone de drag & drop avec bordure en pointillés |
| `.file-drop-zone:hover` | Surbrillance bleue |
| `.file-drop-zone.drag-over` | Surbrillance verte (drag actif) |
| `.file-drop-zone.has-file` | Fond vert (fichier sélectionné) |

Responsive : Adaptation mobile (< 768px).

Le fichier CSS est chargé via `$PAGE->requires->css()` dans `index.php`.

---

## 8. Fichiers de langue (i18n)

**Langues disponibles :**

- `lang/en/local_gradefiller.php` (anglais)
- `lang/fr/local_gradefiller.php` (français)

**Conventions :**

- Clé du composant : `local_gradefiller`
- Préfixes utilisés :
  - `driver_*` — Noms des drivers
  - `format_*` — Noms et descriptions des formats
  - `error_*` — Messages d'erreur
  - `help_step*` — Étapes d'aide
  - `btn_*` — Libellés de boutons
  - `source_*` — Sources de notes

**Pour ajouter une langue :**

1. Créer `lang/{code}/local_gradefiller.php`
2. Copier le contenu de `lang/en/local_gradefiller.php`
3. Traduire toutes les chaînes

---

## 9. Guide : Ajouter un nouveau format de tableur

Pour ajouter un nouveau format (ex: un format spécifique à votre université) :

### Étape 1 — Créer la classe

Fichier : `classes/spreadsheet/format_mon_format.php`

```php
<?php
namespace local_gradefiller\spreadsheet;

class format_mon_format implements spreadsheet_format_interface {

    public function get_name(): string {
        return get_string('format_mon_format_name', 'local_gradefiller');
    }

    public function get_key(): string {
        return 'mon_format';  // Clé unique
    }

    public function get_description(): string {
        return get_string('format_mon_format_desc', 'local_gradefiller');
    }

    public function read_identifiers(string $filepath): array {
        // Utiliser PhpSpreadsheet pour lire les identifiants
        // Retourner [{identifier, row_number}, ...]
    }

    public function write_grades(string $filepath, array $grades): string {
        // Écrire les notes dans le fichier
        // Retourner le chemin du fichier rempli
    }

    public function validate_file(string $filepath): bool {
        // Valider la structure du fichier
        // Lancer moodle_exception si invalide
    }
}
```

### Étape 2 — Enregistrer le format

Dans `classes/manager.php`, méthode `get_available_formats()` :

```php
$this->formats[] = new \local_gradefiller\spreadsheet\format_mon_format();
```

### Étape 3 — Ajouter les chaînes de langue

Dans `lang/en/local_gradefiller.php` :

```php
$string['format_mon_format_name'] = 'My Format';
$string['format_mon_format_desc'] = 'Description of my format';
```

Dans `lang/fr/local_gradefiller.php` :

```php
$string['format_mon_format_name'] = 'Mon Format';
$string['format_mon_format_desc'] = 'Description de mon format';
```

### Étape 4 — Incrémenter la version

Dans `version.php`, incrémenter `$plugin->version`.

---

## 10. Guide : Ajouter un nouveau driver de source de notes

Pour ajouter un driver supportant un nouveau type d'activité anonyme :

### Étape 1 — Créer la classe

Fichier : `classes/source/grade_source_monactivite.php`

```php
<?php
namespace local_gradefiller\source;

class grade_source_monactivite implements grade_source_interface {

    public function get_name(): string {
        return get_string('driver_monactivite', 'local_gradefiller');
    }

    public function supports($cm): bool {
        return $cm->modname === 'monactivite';
    }

    public function fetch_grade_by_anonkey(int $cmid, string $anonkey): ?object {
        global $DB;
        // Requête SQL pour trouver la note par code anonyme
        // Retourner (object)['grade' => X, 'maxgrade' => Y] ou null
    }

    public function is_anonymous_identifier(string $identifier): bool {
        // Déterminer si l'identifiant est valide pour ce driver
        return !empty(trim($identifier));
    }
}
```

### Étape 2 — Enregistrer le driver

Dans `classes/manager.php`, méthode `get_available_drivers()` :

```php
$this->drivers[] = new \local_gradefiller\source\grade_source_monactivite();
```

### Étape 3 — Ajouter les chaînes de langue

```php
$string['driver_monactivite'] = 'Mon Activité (Anonyme)';
```

### Étape 4 — Incrémenter la version

Dans `version.php`, incrémenter `$plugin->version`.

---

## 11. Limitations connues et pistes d'amélioration

### 11.1 Limitations actuelles

| ID | Description |
|---|---|
| **L1** | **Écriture limitée aux formats ZIP** — La méthode `write_grades()` utilise `ZipArchive` et ne supporte que les formats basés sur ZIP (xlsx, xlsm). ~~Les formats xls/csv échouaient silencieusement~~ → **Corrigé en v1.2** : une validation rejette désormais les formats non supportés avec un message d'erreur clair. |
| **L2** | **Pas d'auto-découverte des formats/drivers** — Les formats et drivers sont enregistrés manuellement dans le manager. Un système d'auto-découverte (scan des fichiers `format_*.php` et `grade_source_*.php`) est prévu. |
| **L3** | **`grade_source_anonymousgrader` non enregistré** — Le driver est implémenté mais n'est pas ajouté dans `manager::get_available_drivers()`. Il faut l'ajouter manuellement. |
| **L4** | **Pas de table de base de données propre** — Le plugin n'a pas de tables propres dans `db/install.xml`. Aucun historique de traitement n'est conservé. |
| **L5** | **Pas de tests unitaires** — Aucun test PHPUnit n'est fourni. |
| **L6** | **Structure XML ODS différente** — La méthode `write_grades()` cible `xl/worksheets/sheet1.xml` (format Excel). Pour ODS, le fichier XML principal est `content.xml` avec un namespace différent. L'écriture ODS est désormais rejetée en amont (voir L1). |
| **L7** | **Pas de gestion des cellules inexistantes** — Si une cellule `E{row}` n'existe pas dans le XML (ligne vide), la note n'est pas écrite. Le code ne crée pas la structure `row/cell` nécessaire. |

### 11.2 Corrections apportées en v1.2

| Fix | Description |
|---|---|
| **F1** | **Fuite de fichier temporaire** — Le fichier uploadé original restait sur le serveur après un traitement réussi. Il est désormais supprimé systématiquement. |
| **F2** | **Collisions de noms de fichiers** — Les fichiers temporaires utilisaient le nom fourni par l'utilisateur et `time()` (granularité 1s). Remplacé par `uniqid('', true)` pour éviter les conflits en accès concurrent. |
| **F3** | **Validation du format avant écriture** — Les formats `.xls`, `.ods` et `.csv` sont désormais rejetés avec un message d'erreur explicite avant le traitement, au lieu d'échouer sur `ZipArchive`. |
| **F4** | **Chaînes de langue manquantes** — Ajout de `invalidaction` et `error_unsupported_write_format` dans les fichiers EN et FR. |

### 11.2 Pistes d'amélioration

| ID | Description |
|---|---|
| **A1** | Ajouter le support d'écriture pour XLS et CSV (Writers de PhpSpreadsheet comme fallback). |
| **A2** | Implémenter l'auto-découverte des formats et drivers (scan des dossiers `classes/spreadsheet/` et `classes/source/`). |
| **A3** | Ajouter un rapport de traitement affiché à l'utilisateur après le traitement. |
| **A4** | Ajouter des tests PHPUnit couvrant `manager`, `format_university_standard` et les drivers. |
| **A5** | Utiliser le Moodle File API (draft area + `file_storage`) au lieu de `$_FILES`. |
| **A6** | Ajouter un système de logging/audit avec table d'historisation. |
| **A7** | Support multi-feuilles (choix de la feuille de calcul à traiter). |
| **A8** | Ajouter une fonctionnalité de preview avant la génération du fichier final. |
| **A9** | ~~Restreindre le formulaire aux formats supportés en écriture~~ → **Résolu en v1.2** : la validation côté serveur rejette les formats non supportés. |

---

## 12. Conventions et standards de code

- Conformité Moodle Coding Style (PSR-like avec adaptations Moodle).
- **Namespaces :** `local_gradefiller\{sous-namespace}`
- **Autoloading :** Moodle class autoloader (basé sur les namespaces).
- **PHPDoc :** Toutes les classes, méthodes et propriétés sont documentées.
- **Chaînes :** Toutes externalisées dans les fichiers de langue.
- **Sécurité :** `sesskey`, `require_login`, `require_capability` sur chaque point d'entrée.
- **Templates :** Mustache (Moodle standard).
- **JavaScript :** AMD modules (Moodle standard).
- **CSS :** Fichier dédié chargé via `$PAGE->requires->css()`.

**Bibliothèques externes utilisées :**

| Bibliothèque | Provenance |
|---|---|
| PhpOffice/PhpSpreadsheet | Inclus dans Moodle core via `$CFG->libdir/phpspreadsheet/` |
| ZipArchive | Extension PHP standard |
| DOMDocument / DOMXPath | Extension PHP standard |
