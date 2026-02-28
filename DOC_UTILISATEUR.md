# Documentation Utilisateur — local_gradefiller (v1.0.0-alpha)

**Plugin Moodle — Remplisseur de Notes dans un Tableur**

- **Date de rédaction :** 27 février 2026
- **Compatible avec :** Moodle 4.3+

---

## Table des matières

1. [Présentation du plugin](#1-présentation-du-plugin)
2. [Prérequis](#2-prérequis)
3. [Installation](#3-installation)
4. [Accès au plugin](#4-accès-au-plugin)
5. [Guide d'utilisation pas à pas](#5-guide-dutilisation-pas-à-pas)
6. [Formats de fichier supportés](#6-formats-de-fichier-supportés)
7. [Sources de notes](#7-sources-de-notes)
8. [Format de tableur "Apogée"](#8-format-de-tableur-apogée)
9. [Messages d'erreur et résolution](#9-messages-derreur-et-résolution)
10. [Questions fréquentes (FAQ)](#10-questions-fréquentes-faq)

---

## 1. Présentation du plugin

Grade Filler (Remplisseur de Notes) est un plugin local Moodle qui permet aux enseignants de **remplir automatiquement les notes dans un tableur** (fichier Excel ou similaire) à partir des notes existantes dans Moodle.

**Cas d'utilisation typique :**

1. Vous avez un fichier Excel fourni par votre administration (ex: Apogée) contenant la liste des étudiants identifiés par leur numéro étudiant.
2. Vous avez déjà corrigé les étudiants dans Moodle (activités, quiz, etc.).
3. Vous téléversez votre fichier dans le plugin.
4. Le plugin recherche les notes de chaque étudiant dans Moodle et les inscrit automatiquement dans le tableur.
5. Vous téléchargez le fichier rempli, prêt à être réimporté dans Apogée.

> Le plugin **préserve intégralement** la mise en forme et le contenu de votre fichier original (y compris les macros pour les fichiers `.xlsm`).

---

## 2. Prérequis

- Moodle version **4.3 ou supérieure**.
- Rôle **"Enseignant éditeur"** ou **"Gestionnaire"** dans le cours concerné.
- L'activité cible doit avoir un **élément de notation** (grade item) dans le carnet de notes Moodle, OU supporter les **notes anonymes** (ex: Offline Quiz).
- Les étudiants doivent avoir un **"Numéro d'identification"** (idnumber) renseigné dans leur profil Moodle, correspondant à celui du tableur (mode Standard).

---

## 3. Installation

L'installation est effectuée par l'administrateur Moodle :

1. Copier le dossier `gradefiller` dans : `/local/gradefiller/`
2. Se connecter en tant qu'administrateur Moodle.
3. Aller dans : **Administration du site > Notifications**.
4. Suivre les instructions de mise à jour de la base de données.
5. Le plugin est maintenant installé et actif.

> Aucune configuration supplémentaire n'est nécessaire.

---

## 4. Accès au plugin

Le lien vers Grade Filler apparaît automatiquement dans le menu d'administration de chaque activité notée.

**Pour y accéder :**

1. Aller dans votre cours Moodle.
2. Cliquer sur l'activité souhaitée (Devoir, Quiz, Offline Quiz, etc.).
3. Dans le menu d'administration de l'activité (roue dentée ou menu "Paramètres"), chercher le lien : **"Remplir les notes dans un tableur"**
4. Cliquer dessus pour ouvrir l'interface du plugin.

> **Note :** Le lien n'apparaît que si :
> - Vous avez les droits nécessaires (enseignant éditeur ou gestionnaire).
> - L'activité possède un élément de notation.

---

## 5. Guide d'utilisation pas à pas

### Étape 1 — Préparer votre fichier

- Assurez-vous que votre fichier tableur contient les identifiants des étudiants (numéro étudiant ou code anonyme).
- Le fichier doit être dans un des formats supportés (voir [section 6](#6-formats-de-fichier-supportés)).

### Étape 2 — Accéder au plugin

- Naviguer vers l'activité concernée dans Moodle.
- Ouvrir le menu d'administration > **"Remplir les notes dans un tableur"**.

### Étape 3 — Vérifier les informations

- L'interface affiche les informations de l'activité sélectionnée : nom, type, et si les notes anonymes sont supportées.

### Étape 4 — Téléverser le fichier

- Cliquer sur la zone de dépôt ou **glisser-déposer** votre fichier.
- Le nom du fichier sélectionné apparaît en vert.

### Étape 5 — Choisir le format du tableur

- Sélectionner le format qui correspond à la structure de votre fichier.
- Actuellement disponible : **"Apogée"** (voir [section 8](#8-format-de-tableur-apogée) pour les détails).
- Une description du format sélectionné s'affiche pour vous aider.

### Étape 6 — Choisir la source des notes

- **"Standard"** : Les identifiants du tableur correspondent aux numéros d'identification Moodle (idnumber) des utilisateurs.
- **"Anonyme"** : Les identifiants correspondent à des codes anonymes (ex: codes-barres Offline Quiz). Cette option n'apparaît que si l'activité supporte les notes anonymes.

### Étape 7 — Traiter et télécharger

- Cliquer sur **"Traiter et Télécharger le Fichier Rempli"**.
- Le plugin traite le fichier et lance automatiquement le téléchargement du fichier rempli.
- Le fichier téléchargé porte le nom : `filled_grades_AAAA-MM-JJ_HHMMSS.ext`

---

## 6. Formats de fichier supportés

**Formats acceptés en téléversement (upload) :**

- `.xlsx` — Excel 2007+
- `.xlsm` — Excel avec macros
- `.xls` — Excel 97-2003
- `.ods` — OpenDocument Spreadsheet / LibreOffice
- `.csv` — Fichier texte à valeurs séparées

### Compatibilité en écriture

| Format | Lecture | Écriture | Remarques |
|--------|---------|----------|-----------|
| `.xlsx` | ✅ OUI | ✅ OUI | Pleinement supporté |
| `.xlsm` | ✅ OUI | ✅ OUI | Macros préservées |
| `.xls` | ✅ OUI | ❌ NON | Format binaire non supporté en écriture (utiliser `.xlsx` à la place) |
| `.ods` | ✅ OUI | ⚠️ PARTIEL | Structure XML interne différente |
| `.csv` | ✅ OUI | ❌ NON | Fichier texte, non supporté en écriture (utiliser `.xlsx` à la place) |

> Pour les formats `.xls` et `.csv`, une erreur peut survenir lors du traitement. Il est recommandé de convertir votre fichier en `.xlsx` avant de l'utiliser avec ce plugin.

> **Recommandation :** Utilisez de préférence les formats **`.xlsx`** ou **`.xlsm`**.

---

## 7. Sources de notes

### 7.1 Mode Standard

- Utilise le **numéro d'identification** (idnumber) des profils utilisateurs Moodle.
- Le plugin cherche chaque identifiant du tableur dans le champ `idnumber` de la table des utilisateurs Moodle.
- Vérifie que l'utilisateur est inscrit au cours.
- Récupère la note finale depuis le carnet de notes Moodle.

**Conditions pour que la correspondance fonctionne :**

- Le champ "Numéro d'identification" du profil utilisateur doit être renseigné (souvent le numéro étudiant).
- L'identifiant dans le tableur doit correspondre **exactement** à ce champ.
- L'étudiant doit être inscrit au cours.
- L'activité doit avoir une note dans le carnet de notes.

### 7.2 Mode Anonyme

- Disponible uniquement pour les activités supportant les notes anonymes (ex: Offline Quiz avec le plugin `local_anonymousgrader`).
- Les identifiants du tableur sont des codes anonymes (codes-barres, etc.).
- Le plugin utilise un "driver" spécifique à l'activité pour retrouver la note correspondant au code anonyme.

**Drivers anonymes disponibles :**

| Driver | Description |
|---|---|
| **Offline Quiz** | Recherche dans les résultats de pages scannées (table `offlinequiz_scanned_pages`, champ `userkey`). |
| **Anonymous Grader (Scanner)** | Recherche dans les résultats validés du plugin `local_anonymousgrader`. |

---

## 8. Format de tableur "Apogée"

Le format **"Apogée"** est le format intégré par défaut. Il correspond à la structure standard des fichiers d'export Apogée utilisés dans les universités françaises.

### Structure attendue

- **Lignes 1 à 17 :** En-têtes et métadonnées (ignorées par le plugin).
- **À partir de la ligne 18 :** Données des étudiants.
- **Colonne A :** Identifiant étudiant (numéro étudiant ou code anonyme).
- **Colonne E :** Colonne de destination des notes (remplie par le plugin).

### Fonctionnement du plugin

1. Lit les identifiants dans la **colonne A** à partir de la **ligne 18**.
2. Ignore les lignes vides.
3. Recherche la note correspondante dans Moodle.
4. Écrit la note dans la **colonne E** de la même ligne.
5. Préserve tout le reste du fichier (en-têtes, mise en forme, macros).

---

## 9. Messages d'erreur et résolution

| Message | Cause / Solution |
|---|---|
| **"Aucun fichier téléversé"** | Vérifiez que vous avez bien sélectionné un fichier avant de soumettre. |
| **"Format non trouvé"** | Sélectionnez un format valide dans la liste déroulante. |
| **"Erreur de lecture du fichier"** | Le fichier est peut-être corrompu ou dans un format non supporté. Essayez de le réenregistrer depuis Excel. |
| **"Erreur d'écriture du fichier"** | Le format du fichier ne supporte pas l'écriture (ex: `.xls`, `.csv`). Convertissez votre fichier en `.xlsx` et réessayez. |
| **"Format de fichier invalide"** | La structure du fichier ne correspond pas au format sélectionné. |
| **"Le fichier doit avoir au moins X lignes"** | Le fichier ne contient pas assez de lignes pour le format sélectionné (ex: Apogée nécessite au moins 18 lignes). |
| **"Aucun identifiant trouvé dans la colonne attendue"** | La colonne A (pour le format Apogée) ne contient pas de données après la ligne d'en-tête. |
| **"Vous n'avez pas la permission d'accéder à cette page"** | Vous n'avez pas le rôle requis (enseignant éditeur ou gestionnaire). |
| **"Cette activité n'a pas d'élément de notation"** | L'activité sélectionnée n'a pas de notes configurées. |

> **Note sur les notes non trouvées :** Si certains identifiants du tableur ne correspondent à aucun utilisateur ou n'ont pas de note, la cellule de note correspondante restera vide. Le plugin ne supprime jamais de données existantes.

---

## 10. Questions fréquentes (FAQ)

**Q : Le plugin modifie-t-il mon fichier original ?**
> Non. Le plugin travaille sur une copie de votre fichier. Le fichier original n'est jamais modifié. Un nouveau fichier rempli est généré et proposé en téléchargement.

**Q : Les macros de mon fichier .xlsm sont-elles préservées ?**
> Oui. Le plugin utilise une technique de manipulation XML directe (ZipArchive) qui préserve les macros, les contrôles ActiveX et les éléments VML.

**Q : Puis-je utiliser le plugin avec n'importe quelle activité ?**
> Le plugin fonctionne avec toute activité possédant un élément de notation dans le carnet de notes Moodle (Devoir, Quiz, Atelier, etc.). Le mode anonyme nécessite une activité spécifiquement supportée (Offline Quiz).

**Q : Que se passe-t-il si un étudiant n'a pas de note ?**
> La cellule correspondante reste inchangée dans le fichier de sortie.

**Q : Puis-je utiliser un format de tableur différent d'Apogée ?**
> Actuellement, seul le format Apogée est intégré. D'autres formats peuvent être ajoutés par un développeur (voir la documentation développeur).

**Q : Le plugin fonctionne-t-il avec les groupes Moodle ?**
> Le plugin traite tous les étudiants inscrits au cours, sans distinction de groupe.

**Q : Combien d'étudiants le plugin peut-il traiter ?**
> Il n'y a pas de limite théorique. Le plugin traite les identifiants un par un. Pour de très grands fichiers (>1000 étudiants), le traitement peut prendre quelques secondes.

**Q : Dans quelles langues le plugin est-il disponible ?**
> Le plugin est disponible en français et en anglais. La langue affichée dépend des préférences de votre profil Moodle.
