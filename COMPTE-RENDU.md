# Compte rendu — Application GSB Frais (AP BTS SIO 2)

Document vivant, enrichi à chaque phase. Toute la terminologie employée est
définie au § 11 (glossaire).

| Phase | Intitulé | État |
|-------|----------|------|
| 0 | Audit du code existant | **terminée** |
| 1 | Puissance fiscale du véhicule | **terminée** |
| 2 | Upload des justificatifs | **terminée** |
| 3 | Génération PDF des fiches | **terminée** |
| 4 | Maquette HTML partie comptable | à faire |
| 5 | Cas d'utilisation « Valider fiche de frais » | à faire |

---

## 1. Ce que j'ai fait

### Phase 0 — Audit (aucune ligne de code écrite, aucune commande destructrice)

| Action | Détail |
|--------|--------|
| Cartographie de l'arborescence | `GsbFraisBundle/` (archive du prof, hors projet) et `gsbProjet/` (projet Symfony réellement exécutable) |
| Lecture intégrale de la couche modèle | `src/Pg/GsbFraisBundle/services/PdoGsb.php` (380 lignes, 19 méthodes) |
| Lecture des 3 contrôleurs | `HomeController`, `ListeFraisController`, `SaisirFraisController` |
| Lecture des fonctions utilitaires | `src/Pg/GsbFraisBundle/Controller/include/fct.inc.php` |
| Lecture des 13 templates Twig | `src/Pg/GsbFraisBundle/Resources/views/` |
| Lecture de la configuration | `app/config/{config,routing,security,parameters}.yml`, `app/AppKernel.php`, `Resources/config/{routing,services}.yml` |
| Relevé du schéma réel en base | requêtes `SHOW TABLES` / `SELECT` en lecture seule sur `bd_gsb` |
| Test de fumée de l'application | serveur PHP local sur le port 8123, `GET /accueil`, `POST /validerconnexion`, `GET /listefrais` |
| Comparaison archive du prof ↔ projet | `diff -rq` entre `GsbFraisBundle/` et `src/Pg/GsbFraisBundle/` |
| Inventaire de l'outillage | PHP 7.4.33, Composer 2.10.3, MySQL 9.5.0, Symfony CLI 5.20, extensions PHP |
| Rédaction du présent document | `COMPTE-RENDU.md` (créé) |

### Phase 0 (suite) — après réception du cahier des charges et de ton accord

| Action | Détail |
|--------|--------|
| Lecture du cahier des charges | `ap-gsb-web.pdf` (9 pages) — PDF scanné, pages reconstruites en images pour être lues (§ 4.1) |
| Dépouillement des règles de gestion | forfait / hors forfait, saisie, clôture, validation, mise en paiement (§ 4.2) |
| Relevé du cycle d'état officiel | diagramme d'états du document — **5 états** (§ 4.3) |
| Relevé de l'annexe 1 | maquette exacte de la fiche à imprimer en phase 3 (§ 4.4) |
| Relevé de l'annexe 2 | cas d'utilisation « Valider fiche de frais », scénario nominal + exception 4-a (§ 4.5) |
| Confrontation document ↔ base | 5 écarts relevés, E1 à E5 (§ 4.6) |
| Sauvegarde de la base | `mysqldump -u root --databases bd_gsb --add-drop-database=FALSE > sauvegardes/bd_gsb_avant_phase1.sql` |
| Mise sous gestion de version | `git init` + commit initial `b2ac4b6` « État initial : reprise du projet du prédécesseur » — 78 fichiers suivis, `vendor/` et caches exclus |
| Complément au `.gitignore` | ajout de `.DS_Store` et `/sauvegardes/` |

**Fichiers créés en phase 0 :** `COMPTE-RENDU.md`,
`sauvegardes/bd_gsb_avant_phase1.sql`, et le dépôt Git.
**Fichiers modifiés en phase 0 :** `.gitignore` (2 lignes d'exclusion ajoutées).
**Aucun fichier de code n'a été modifié.**

### Phase 1 — Puissance fiscale du véhicule

| Action | Fichier | Rôle |
|--------|---------|------|
| Migration du schéma | `sql/001_puissance_fiscale_vehicule.sql` *(créé)* | crée la table `typevehicule` (le barème), ajoute `visiteur.idTypeVehicule` et `lignefraisforfait.montantUnitaire`, puis remplit cette dernière pour les fiches existantes |
| Synchronisation du script de création | `sql/bd_gsb.sql` *(modifié)* | une base créée de zéro doit donner le même schéma qu'une base migrée |
| Nouvelles méthodes d'accès aux données | `src/Pg/GsbFraisBundle/services/PdoGsb.php` *(modifié)* | `getLesTypesVehicule`, `getTypeVehiculeVisiteur`, `estTypeVehiculeValide`, `getLesMontantsUnitairesApplicables`, `getTarifKilometrique`, `majTypeVehiculeVisiteur` |
| Historisation à la création de fiche | `PdoGsb::creeNouvellesLignesFrais()` *(modifié)* | fige le montant unitaire de chaque ligne au moment où la fiche est créée |
| Remontée du montant historisé | `PdoGsb::getLesFraisForfait()` *(modifié)* | renvoie désormais `montantunitaire` et `montant` (= quantité × montant unitaire, calculé en SQL) |
| Écran de déclaration du véhicule | `Controller/VehiculeController.php` *(créé)* | affiche le barème, contrôle la valeur reçue, enregistre |
| Vue associée | `Resources/views/Vehicule/monvehicule.html.twig` *(créé)* | liste déroulante du barème + rappel de la règle de non-recalcul |
| Routes | `Resources/config/routing.yml` *(modifié)* | `pg_gsb_frais_monvehicule` (GET), `pg_gsb_frais_validermonvehicule` (POST) |
| Entrée de menu | `Resources/views/accueil.html.twig` *(modifié)* | lien « Mon véhicule » |
| Affichage en consultation | `Resources/views/ListeFrais/listefraisforfait.html.twig` *(modifié)* | deux lignes ajoutées au tableau : montant unitaire appliqué, et total de la ligne |
| Affichage en saisie | `Resources/views/SaisirFrais/saisirfraisforfait.html.twig` *(modifié)* | le montant unitaire figure en face de chaque libellé, avec un lien vers « Mon véhicule » |
| Style | `Resources/public/styles/styles.css` *(modifié)* | classe `.legendeTableau` pour les légendes sous tableau |

### Phase 2 — Upload des justificatifs

| Action | Fichier | Rôle |
|--------|---------|------|
| Migration du schéma | `sql/002_justificatifs.sql` *(créé)* | ajoute `nomFichier`, `nomOriginal`, `typeMime` à `lignefraishorsforfait` ; remet `fichefrais.nbJustificatifs` en accord avec la réalité |
| Synchronisation | `sql/bd_gsb.sql` *(modifié)* | mêmes colonnes, et compteurs du jeu de démonstration remis à 0 |
| Service de stockage | `services/StockageJustificatifs.php` *(créé)* | validation (extension, type réel, taille), nom généré, écriture, suppression, assainissement du nom affiché |
| Déclaration du service | `Resources/config/services.yml` *(modifié)* | `pg_gsb_frais.justificatifs` + paramètres `justificatifs.dossier` et `justificatifs.taille_max` |
| Dossier de stockage | `app/justificatifs/` *(créé)* | hors de `web/` ; son `.gitignore` exclut les fichiers déposés mais conserve le dossier |
| Accès aux données | `services/PdoGsb.php` *(modifié)* | `getLigneFraisHorsForfait`, `majJustificatifFrais`, `recalculerNbJustificatifsDuFrais`, `recalculerNbJustificatifs` ; `creeNouveauFraisHorsForfait` retourne désormais l'identifiant créé |
| Dépôt et consultation | `Controller/JustificatifController.php` *(créé)* | `joindreAction` (POST) et `consulterAction`, avec contrôle d'accès côté serveur |
| Rôle comptable | `Controller/include/fct.inc.php` *(modifié)* | `estComptable($session)` — la règle d'accès est posée, l'acteur arrivera en phase 5 |
| Saisie | `Controller/SaisirFraisController.php` *(modifié)* | justificatif facultatif à la création ; suppression du fichier et recalcul du compteur quand un frais est supprimé ; remontée des messages du flash bag |
| Routes | `Resources/config/routing.yml` *(modifié)* | `pg_gsb_frais_joindrejustificatif` (POST), `pg_gsb_frais_consulterjustificatif` |
| Vues | `views/SaisirFrais/saisirfraishorsforfait.html.twig`, `views/ListeFrais/listefraishorsforfait.html.twig` *(modifiées)* | colonne « Justificatif », formulaire d'envoi par ligne, compteur dans la légende |
| Style | `Resources/public/styles/styles.css` *(modifié)* | mise en forme des formulaires d'envoi |

### Phase 3 — Génération PDF des fiches de frais

| Action | Fichier | Rôle |
|--------|---------|------|
| Dépendance | `composer.json`, `composer.lock` *(modifiés)* | `dompdf/dompdf` v2.0.8 + 4 dépendances |
| Gabarit de la fiche | `Resources/views/FichePdf/fiche.html.twig` *(créé)* | reproduction de l'annexe 1 en HTML/CSS |
| Générateur | `services/GenerateurFichePdf.php` *(créé)* | `genererFiche()` (une fiche), `genererArchiveMois()` (le ZIP du mois), `calculerTotal()` |
| Déclaration du service | `Resources/config/services.yml` *(modifié)* | `pg_gsb_frais.fiche_pdf` + paramètre `fiche_pdf.logo` |
| Accès aux données | `services/PdoGsb.php` *(modifié)* | `getVisiteur`, `getLesFichesDuMois`, `getLesTypesFraisForfait`, `genererVisiteursDeTest` |
| Fonctions de date | `Controller/include/fct.inc.php` *(modifié)* | `moisEnLettres()`, `moisPrecedent()` |
| Impression web | `Controller/FichePdfController.php` *(créé)* | fiche individuelle (visiteur ou comptable), archive du mois (comptable) |
| Routes | `Resources/config/routing.yml` *(modifié)* | `pg_gsb_frais_fichepdf`, `pg_gsb_frais_fichespdfmois` |
| Impression de masse en console | `Command/ImprimerFichesCommand.php` *(créé)* | `gsb:imprimer-fiches`, utilisable sans compte comptable |
| Jeu de données de test | `Command/GenererDonneesCommand.php` *(créé)* | `gsb:generer-donnees` — comble le point B7 |
| Lien d'impression | `views/ListeFrais/listefraisforfait.html.twig` *(modifié)* | « Imprimer cette fiche (PDF) » |

---

## 2. Cartographie du projet

### 2.1 Deux dossiers, un seul projet vivant

```
/Volumes/LaCie Data/bts sio 2/gsbSymfony/
├── codeGsbSymfony.zip          archive d'origine (février 2013)
├── GsbFraisBundle/             archive décompressée — NON exécutable seule (pas de squelette Symfony)
├── gsbSymfonyV1.0.pdf/.docx    support pédagogique CERTA « Application GSB_FRAIS avec Symfony2 »
└── gsbProjet/                  ← LE PROJET (squelette Symfony 2.8 + bundle intégré)
```

`GsbFraisBundle/` à la racine est **un bundle nu** : il ne contient ni
`composer.json`, ni `app/`, ni `web/`, ni `vendor/`. Un bundle Symfony n'est pas
une application : il doit être posé dans le dossier `src/` d'un squelette
Symfony pour tourner. C'est ce qui a été fait dans `gsbProjet/` :
le bundle vit désormais en `gsbProjet/src/Pg/GsbFraisBundle/`.

**Conséquence pratique : je travaille uniquement dans `gsbProjet/`.**
`GsbFraisBundle/` reste la référence « livraison du prédécesseur », intouchée.

### 2.2 Arborescence utile de `gsbProjet/`

```
gsbProjet/
├── app/
│   ├── AppKernel.php                  liste des bundles activés
│   ├── console                        point d'entrée des commandes (php app/console …)
│   └── config/
│       ├── config.yml                 config framework + Twig + Doctrine DBAL (ORM commentée)
│       ├── routing.yml                importe le routing du bundle
│       ├── security.yml               firewall « main » anonyme — la sécurité n'est PAS utilisée
│       └── parameters.yml             paramètres Doctrine DBAL (non utilisés par l'appli)
├── bin/                               binaires Composer
├── sql/bd_gsb.sql                     schéma + jeu de données (script de rechargement complet)
├── src/
│   ├── AppBundle/                     squelette par défaut de Symfony — code mort, jamais routé
│   └── Pg/GsbFraisBundle/             ← tout le code métier
│       ├── PgGsbFraisBundle.php       déclaration du bundle
│       ├── Controller/
│       │   ├── HomeController.php
│       │   ├── ListeFraisController.php
│       │   ├── SaisirFraisController.php
│       │   └── include/
│       │       ├── fct.inc.php              fonctions utilitaires (dates, validation, session)
│       │       └── class.pdogsb.inc.php     ANCIENNE version de PdoGsb — code mort (voir § 8, point B11)
│       ├── DependencyInjection/       chargement de services.yml
│       ├── Resources/
│       │   ├── config/routing.yml     8 routes
│       │   ├── config/services.yml    déclaration du service pg_gsb_frais.pdo + paramètres BDD
│       │   ├── public/                styles.css + logo.svg
│       │   └── views/                 13 templates Twig
│       ├── services/PdoGsb.php        ← couche d'accès aux données (PDO natif)
│       └── nbproject/                 métadonnées NetBeans (2013) — inutiles, non supprimées
├── vendor/                            dépendances installées
├── web/                               racine web publique (app.php, app_dev.php, bundles/)
└── README.md                          procédure d'installation rédigée lors de la mise en route
```

### 2.3 Versions réellement en place (vérifiées)

| Composant | Version | Où c'est fixé |
|-----------|---------|---------------|
| PHP | **7.4.33** | `.php-version`, `composer.json` → `config.platform.php` |
| Symfony | **2.8.48** | `composer.lock` (`symfony/symfony` v2.8.48) |
| Twig | **1.44.8** | `composer.lock` |
| Doctrine ORM | 2.5.14 installée mais **désactivée** | `app/config/config.yml`, bloc `orm:` commenté |
| MySQL serveur | **9.5.0** | serveur local |
| Composer | 2.10.3 | — |
| Symfony CLI | 5.20.0 | — |

Extensions PHP présentes et utiles pour la suite : `pdo_mysql`, `mbstring`,
`gd`, `dom`, `zip`, `iconv`, `fileinfo`, `intl`, `json`.
`upload_max_filesize = 2M`, `post_max_size = 8M` (à retenir pour la phase 2).

### 2.4 Architecture logicielle réelle

L'application **n'utilise ni l'ORM Doctrine, ni le composant Form, ni le
composant Security de Symfony**. C'est un choix documenté du support CERTA
(« nous n'utiliserons pas le moteur ORM Doctrine […] ni la gestion proposée des
formulaires »), et c'est ce qui donne l'architecture suivante :

```
Navigateur
   │  HTTP
   ▼
routing.yml ──► Contrôleur (HomeController / ListeFraisController / SaisirFraisController)
                    │            │
                    │            └──► fct.inc.php   (fonctions procédurales : dates, validation, session)
                    │
                    └──► service « pg_gsb_frais.pdo » = classe PdoGsb
                                │  SQL préparé (PDO natif)
                                ▼
                            MySQL bd_gsb
                    │
                    └──► Twig (Resources/views) ──► HTML
```

- **Le service `pg_gsb_frais.pdo`** est déclaré dans
  `Resources/config/services.yml` avec les 4 paramètres de connexion
  (`db.host`, `db.name`, `db.user`, `db.mdp`). Les contrôleurs le récupèrent
  par `$this->get('pg_gsb_frais.pdo')`. **Les identifiants de connexion à la
  base sont donc dans `services.yml`, pas dans `parameters.yml`** — piège
  classique : `parameters.yml` ne sert qu'à Doctrine DBAL, qui n'est pas utilisé.
- **L'authentification est « faite main »** : `HomeController::validerconnexionAction()`
  compare login/mdp en SQL, puis `connecter($session, …)` pose `id`, `nom`,
  `prenom` dans la session PHP. Chaque action protégée commence par
  `if (!estConnecte($session)) { redirection }`. Le firewall Symfony
  (`security.yml`) est présent mais **anonyme et inutilisé**.

### 2.5 Les 8 routes existantes

| Nom de la route | URL | Méthode | Contrôleur:action |
|-----------------|-----|---------|-------------------|
| `pg_gsb_frais_accueil` | `/` | GET | redirection vers `pg_gsb_frais_home` |
| `pg_gsb_frais_home` | `/accueil` | GET | `Home:index` |
| `pg_gsb_frais_validerconnexion` | `/validerconnexion` | POST | `Home:validerconnexion` |
| `pg_gsb_frais_deconnexion` | `/deconnexion` | GET | `Home:deconnexion` |
| `pg_gsb_frais_listefrais` | `/listefrais` | GET/POST | `ListeFrais:index` |
| `pg_gsb_frais_saisirfrais` | `/saisirfrais` | GET/POST | `SaisirFrais:index` |
| `pg_gsb_frais_validerfraishorsforfais` | `/validerfraishorsforfait` | POST | `SaisirFrais:validerfraishorsforfait` |
| `pg_gsb_frais_supprimerfraishorsforfait` | `/supprimerfraishorsforfait/{id}` | GET | `SaisirFrais:supprimerfraishorsforfait` |

> ⚠️ Le nom de la 7ᵉ route comporte une **faute de frappe d'origine** :
> `…forfai**s**` au lieu de `…forfai**t**`. Le template
> `saisirfraishorsforfait.html.twig` appelle `path('pg_gsb_frais_validerfraishorsforfais')`,
> donc **ça marche** ; le `README.md` la documente à tort avec le bon
> orthographe. Je ne renomme pas (règle « on ne réécrit pas ce qui fonctionne »),
> je corrigerai le README.

### 2.6 Les 19 méthodes de `PdoGsb`

| Méthode | Rôle | Utilisée par |
|---------|------|--------------|
| `getInfosVisiteur($login,$mdp)` | authentification | HomeController |
| `getLesFraisHorsForfait($idV,$mois)` | lignes hors forfait (+ conversion date FR) | ListeFrais, SaisirFrais |
| `getNbjustificatifs($idV,$mois)` | nb de justificatifs | **personne** |
| `getLesFraisForfait($idV,$mois)` | lignes forfait (jointure `fraisforfait`) | ListeFrais, SaisirFrais |
| `getLesIdFrais()` | les 4 id de `fraisforfait` | `creeNouvellesLignesFrais` |
| `majFraisForfait($idV,$mois,$lesFrais)` | met à jour les quantités | SaisirFrais |
| `majNbJustificatifs($idV,$mois,$nb)` | met à jour `nbJustificatifs` | **personne** |
| `estPremierFraisMois($idV,$mois)` | la fiche du mois existe-t-elle ? | SaisirFrais |
| `dernierMoisSaisi($idV)` | `MAX(mois)` | `creeNouvellesLignesFrais` |
| `creeNouvellesLignesFrais($idV,$mois)` | crée la fiche + 4 lignes forfait, clôture N-1 | SaisirFrais |
| `creeNouveauFraisHorsForfait(…)` | insère une ligne hors forfait | SaisirFrais |
| `supprimerFraisHorsForfait($id)` | supprime une ligne hors forfait | SaisirFrais |
| `getLesMoisDisponibles($idV)` | liste des mois ayant une fiche | ListeFrais |
| `getLesInfosFicheFrais($idV,$mois)` | entête de fiche + libellé d'état | ListeFrais, `creeNouvellesLignesFrais` |
| `majEtatFicheFrais($idV,$mois,$etat)` | change l'état + `dateModif = now()` | `creeNouvellesLignesFrais` |
| `getLesFichesValidees()` | toutes les fiches à l'état `VA` | **personne** |
| `estValideSuppressionFrais($idV,$mois,$idF)` | contrôle d'appartenance avant suppression | SaisirFrais |

Trois méthodes sont donc **écrites mais jamais appelées** : `getNbjustificatifs`,
`majNbJustificatifs`, `getLesFichesValidees`. Ce sont des points d'accroche
laissés par le prédécesseur pour les phases 2 et 5 — je les réutiliserai plutôt
que d'en écrire de nouvelles.

---

## 3. Schéma de la base de données `bd_gsb`

Relevé **sur le serveur**, pas seulement dans le script SQL.

### 3.1 Les 6 tables

```
   visiteur                      etat                     fraisforfait
┌──────────────┐          ┌───────────────┐          ┌──────────────┐
│ id      CHAR(4) PK│      │ id  CHAR(2) PK│          │ id  CHAR(3) PK│
│ nom          │          │ libelle       │          │ libelle      │
│ prenom       │          └───────┬───────┘          │ montant      │
│ login  UNIQUE│                  │                  │ DECIMAL(5,2) │
│ mdp (clair!) │                  │                  └──────┬───────┘
│ adresse,cp,  │                  │                         │
│ ville,       │                  │ idEtat                  │ idFraisForfait
│ dateEmbauche │                  │                         │
└──────┬───────┘                  │                         │
       │ idVisiteur               │                         │
       │        ┌─────────────────▼─────────┐               │
       └───────►│       fichefrais          │               │
                │ PK (idVisiteur, mois)     │               │
                │ mois CHAR(6)  « aaaamm »  │               │
                │ nbJustificatifs   INT     │               │
                │ montantValide DECIMAL(10,2)│              │
                │ dateModif         DATE    │               │
                │ idEtat  CHAR(2) def. 'CR' │               │
                └───┬───────────────────┬───┘               │
     (idVisiteur,mois)│                 │(idVisiteur,mois)   │
     ON DELETE CASCADE│                 │ON DELETE CASCADE   │
        ┌─────────────▼──────┐   ┌──────▼───────────────────▼──┐
        │lignefraishorsforfait│  │    lignefraisforfait         │
        │ id INT AI PK        │  │ PK (idVisiteur,mois,          │
        │ idVisiteur, mois    │  │     idFraisForfait)           │
        │ libelle VARCHAR(100)│  │ quantite INT  ← QUANTITÉ SEULE│
        │ date DATE           │  └──────────────────────────────┘
        │ montant DECIMAL(10,2)│
        └─────────────────────┘
```

### 3.2 Contenu de référence

`etat` — **4 états seulement** :

| id | libelle |
|----|---------|
| CR | Fiche créée, saisie en cours |
| CL | Saisie clôturée |
| VA | Validée et mise en paiement |
| RB | Remboursée |

`fraisforfait` :

| id | libelle | montant |
|----|---------|---------|
| ETP | Forfait Etape | 110.00 |
| KM | Frais Kilométrique | **0.62** |
| NUI | Nuitée Hôtel | 80.00 |
| REP | Repas Restaurant | 25.00 |

9 visiteurs (`a17` David Andre / `dandre`, `a55` Christian Bedos / `cbedos`, …),
5 fiches de frais (`a17` : 202606 RB, 202607 VA, 202608 CL, 202609 CR ;
`a55` : 202608 CL), 5 lignes hors forfait.

### 3.3 Observation capitale pour la phase 1

**La colonne `fraisforfait.montant` n'est lue nulle part dans le code.**
Vérifié par recherche exhaustive : ni les contrôleurs, ni `PdoGsb`, ni les
templates n'y accèdent. `getLesFraisForfait()` ne sélectionne que
`id`, `libelle`, `quantite`.

Autrement dit :
- le « 0,62 €/km figé » de l'énoncé est figé **en base**, pas dans un `switch` ;
- **l'application ne calcule aucun total, nulle part.** `montantValide` est
  affiché mais jamais calculé (il est saisi à la main dans le jeu de données).

C'est la découverte structurante de l'audit : les phases 1 et 3 ne consistent
pas à *modifier* un calcul existant, mais à **en introduire un qui n'existe pas**.

---

### 3.4 Évolution du schéma en phase 1

Deux ajouts, livrés par `sql/001_puissance_fiscale_vehicule.sql`.

```
       typevehicule  (NOUVELLE TABLE - le barème)
     ┌──────────────────────────┐
     │ id      CHAR(3)       PK │  D4 / D56 / D7 / E4 / E56 / E7
     │ libelle VARCHAR(30)      │  « 5/6 CV Essence »
     │ montant DECIMAL(5,2)     │  0.67  (€ par km)
     └───────────┬──────────────┘
                 │ idTypeVehicule (NULL autorisé)
     ┌───────────▼──────────────┐
     │        visiteur          │  + COLONNE idTypeVehicule CHAR(3) NULL
     └──────────────────────────┘

     ┌──────────────────────────────────────────┐
     │           lignefraisforfait              │
     │ ...                                      │
     │ quantite        INT           NOT NULL   │
     │ montantUnitaire DECIMAL(5,2)  NOT NULL   │  ← NOUVELLE COLONNE
     └──────────────────────────────────────────┘
```

Contenu de `typevehicule` :

| id | libelle | montant |
|----|---------|---------|
| D4 | 4 CV Diesel | 0.52 |
| D56 | 5/6 CV Diesel | 0.58 |
| D7 | 7 CV et + Diesel | 0.65 |
| E4 | 4 CV Essence | 0.62 |
| E56 | 5/6 CV Essence | 0.67 |
| E7 | 7 CV et + Essence | 0.72 |

`fraisforfait.KM` **reste à 0,62 €** : cette ligne n'est plus le tarif de tout
le monde, elle devient le **tarif de repli** appliqué à un visiteur qui n'a pas
encore déclaré son véhicule. Aucune donnée n'a été détruite.

### 3.5 Évolution du schéma en phase 2

```
 ┌──────────────────────────────────────────────────────┐
 │              lignefraishorsforfait                   │
 │  id, idVisiteur, mois, libelle, date, montant        │
 │  nomFichier  VARCHAR(64)  NULL  UNIQUE  ← nom réel   │
 │              sur le disque : 32 caractères tirés au  │
 │              hasard + extension                      │
 │  nomOriginal VARCHAR(255) NULL   ← nom affiché,      │
 │              assaini, jamais utilisé comme chemin    │
 │  typeMime    VARCHAR(100) NULL   ← type réel détecté │
 └──────────────────────────────────────────────────────┘

 fichefrais.nbJustificatifs — colonne qui existait déjà mais n'était
 alimentée par personne (point B16). Elle signifie désormais :
 « nombre de lignes hors forfait accompagnées d'un justificatif téléversé ».
```

**L'unicité sur `nomFichier`** n'est pas décorative : elle empêche qu'une erreur
de code fasse pointer deux lignes de frais vers le même fichier, cas où
supprimer l'une rendrait l'autre illisible.

**Aucune table `justificatif` séparée.** Le cahier des charges parle d'une
facture par dépense hors forfait : la relation est de 1 pour 1, les colonnes
vont donc sur la ligne de frais. Une table séparée n'aurait de sens que pour
plusieurs pièces par frais, ce qui n'est pas demandé (voir limite L9).

---

## 4. Le cahier des charges du client (`ap-gsb-web.pdf`)

Document fourni après le premier passage d'audit :
`/Volumes/LaCie Data/bts sio 2/gsbSymfony/ap-gsb-web.pdf` — 9 pages.
**C'est le vrai cahier des charges**, celui qui contient l'annexe 1 (maquette de
la fiche imprimée) et l'annexe 2 (cas d'utilisation « Valider fiche de frais »).
Il lève le point B13 de l'audit initial.

### 4.1 Nature du document et façon dont je l'ai lu

Le PDF **ne contient pas de texte** : ses 9 pages sont des **images scannées**
(7 images RGB compressées en Flate, avec masque de transparence). Une extraction
de texte classique ne renvoie que le titre « AP - GSB web ».
J'ai donc reconstruit les pages en PNG à partir des flux d'images du PDF, puis
je les ai lues visuellement. Détail technique au § 7.4 — c'est un bon exemple à
citer à l'oral sur la différence entre un PDF « texte » et un PDF « image ».

### 4.2 Règles de gestion (transcription des pages 2 à 4)

**Les frais forfaitisés.** À chaque dépense type (hôtel, repas…) correspond un
montant forfaitaire appliqué. **Le justificatif n'est pas demandé** pour ces
frais (les rapports de visite servent de preuve) mais doit être conservé 3 ans
par le visiteur.

**Les frais hors forfait.** Pour toute dépense en dehors du forfait (repas avec
un spécialiste, achat de fournitures, réservation de salle…), le visiteur
enregistre **la date, le montant et le libellé**. Il doit fournir au service
comptable **une facture acquittée**. Citation exacte :
> « Le système doit produire doit lui indiquer le nombre de justificatifs pris
> en compte dans le remboursement. »

**Saisie.** Après authentification, les visiteurs saisissent les quantités de
frais forfaitisés et les frais hors forfait du mois écoulé. Ils ont accès en
modification **tout au long du mois** et peuvent ajouter ou supprimer.
> « Les frais saisis peuvent remonter jusqu'à un an en arrière. »

➡️ C'est exactement ce qu'implémente `estDateDepassee()` dans `fct.inc.php`.
La règle du prédécesseur est donc conforme au cahier des charges.

**Clôture.** La fiche est clôturée au dernier jour du mois, selon **deux**
modalités :
1. « À la première saisie pour le mois N par le visiteur, sa fiche du mois
   précédent est clôturée si elle ne l'est pas » → **déjà implémenté** par
   `PdoGsb::creeNouvellesLignesFrais()` (§ 6.1).
2. « Au début de la campagne de validation des fiches par le service comptable,
   **un script est lancé qui clôture toutes les fiches non clôturées** du mois
   qui va être traité » → **reste à faire** (commande console, phase 5).

**Validation.** Entre le **10 et le 20** du mois suivant, le service
comptabilité opère une validation :
- il contrôle que les frais forfaitisés sont conformes (nombre de jours
  cohérent avec les congés, distance kilométrique plausible) ;
- en cas d'incohérence, contact téléphonique avec le visiteur, puis
  correction des valeurs — citation : « Les valeurs sont corrigées en
  conséquence **sans que ne soit conservée trace de la modification** » ;
- pour les frais hors forfait, le comptable s'appuie sur les factures reçues au
  plus tard le 10 ;
- « Un frais non validé est **supprimé**. Le visiteur doit être tenu informé de
  cette suppression par les comptables. **On n'enregistrera pas la raison du
  refus** mais les documents annotés sont conservés par le service comptable. »
- les éléments reçus après le 10 sont **reportés sur le mois suivant**.

**Mise en paiement.** Faite au **20 du mois suivant** la saisie.

**Consultation.** « Les visiteurs doivent pouvoir consulter sur l'année écoulée,
pour chaque mois, le montant du remboursement effectué et le nombre de
prestations pris en compte. »

### 4.3 Le cycle de vie de la fiche (page 3 du cahier des charges)

Le diagramme d'états du document est sans ambiguïté — **cinq états** :

```
   ●
   │
   ▼
┌────────┐ [Fin de mois]  ┌──────────┐
│ Créée  │───────────────►│ Clôturée │
└────────┘   /Clôturer    └────┬─────┘
                               │ [Frais saisis, pièces envoyées]
                               │ /Traiter les pièces, contrôler frais hors forfait
                               ▼
                          ┌──────────┐
                          │ Validée  │
                          └────┬─────┘
                               │ /Mettre en paiement
                               ▼
                       ┌──────────────────┐
                       │ Mise en paiement │
                       └────────┬─────────┘
                                │ [Paiement effectué]
                                ▼
                         ┌────────────┐
                         │ Remboursée │──► ●
                         └────────────┘
```

➡️ **Confirme le point B4** : la table `etat` n'a que 4 lignes et son `VA` est
libellé « Validée **et** mise en paiement ». Le cahier des charges sépare
clairement « Validée » (action du comptable, étape 9 du cas d'utilisation) de
« Mise en paiement » (le 20 du mois). Il **faudra ajouter un état**.

### 4.4 Annexe 1 — la fiche de remboursement à produire en PDF (page 5)

Maquette exacte du document à générer en phase 3 :

```
                          ┌─────────────┐
                          │  logo gsb   │
                          └─────────────┘
 ┌───────────────────────────────────────────────────────────┐
 │        REMBOURSEMENT DE FRAIS ENGAGES                     │
 ├───────────────────────────────────────────────────────────┤
 │  Visiteur    NRD/125-01      Jamy LEPLAT AUFRAY           │
 │  Mois        Decembre 2010                                │
 │                                                           │
 │  ┌──────────────────┬──────────┬─────────────────┬───────┐│
 │  │ Frais Forfaitaires│ Quantité │ Montant unitaire│ Total ││
 │  ├──────────────────┼──────────┼─────────────────┼───────┤│
 │  │ Nuitée           │        9 │           80.00 │ 630.00││
 │  │ Repas Midi       │       12 │           29.00 │ 348.00││
 │  │ Véhicule         │      750 │            0.67 │ 502.50││
 │  ├──────────────────┴──────────┴─────────────────┴───────┤│
 │  │                     Autres Frais                      ││
 │  ├──────────────┬────────────────────────────┬───────────┤│
 │  │     Date     │          Libellé           │  Montant  ││
 │  ├──────────────┼────────────────────────────┼───────────┤│
 │  │  18/12/2010  │ Repas Représentation       │    156.00 ││
 │  │  22/12/2010  │ Achat Fleuriste Soirée …   │    120.30 ││
 │  └──────────────┴────────────────────────────┴───────────┘│
 │                        ┌──────────────┬──────────┐        │
 │                        │ TOTAL 12/2010│  1756.80 │        │
 │                        └──────────────┴──────────┘        │
 └───────────────────────────────────────────────────────────┘

                          Fait à Paris, le 7 janvier 2011
                          Vu l'agent comptable
                          [signature manuscrite]
```

Quatre enseignements majeurs pour les phases 1 et 3 :

1. **La colonne « Montant unitaire » figure sur la fiche imprimée.** Le tarif
   appliqué doit donc être *connu et restituable fiche par fiche*, y compris des
   années plus tard. C'est l'argument décisif en faveur de l'historisation
   demandée en phase 1 (point B2) : sans elle, réimprimer une fiche de 2010 en
   2026 afficherait le barème de 2026.
2. **La ligne « Véhicule » porte un montant unitaire de 0,67 €** — c'est
   exactement le tarif « 5/6 CV Essence » du barème de la phase 1. Le cahier des
   charges montre donc noir sur blanc que le prix unitaire kilométrique dépend
   du visiteur et s'imprime sur sa fiche.
3. **Le total général additionne forfait + hors forfait** :
   630.00 + 348.00 + 502.50 + 156.00 + 120.30 = 1756.80. L'application ne sait
   calculer **aucun** de ces montants aujourd'hui (point B1).
4. **Le matricule affiché est `NRD/125-01`**, pas l'identifiant `a17` de la
   table `visiteur` (voir écart E1 ci-dessous).

### 4.5 Annexe 2 — cas d'utilisation « Valider fiche de frais » (page 6)

| Rubrique | Contenu du document |
|----------|---------------------|
| Acteur déclencheur | **Comptable** |
| Pré-conditions | Utilisateur Comptable authentifié · toutes les fiches du mois qui vient de s'achever sont clôturées par un script · le comptable dispose de tous les éléments pour évaluer la conformité des frais forfaitisés |
| Post-conditions | Néant |

Scénario nominal (recopié à l'identique) :

1. L'utilisateur comptable demande à valider les fiches de frais
2. Le système propose de choisir le visiteur et le mois concernés
3. L'utilisateur sélectionne les informations et valide
4. Le système affiche le détail de la fiche de frais – frais forfaitisés et hors forfait
5. L'utilisateur actualise les informations des frais forfaitisés
6. Le système indique que la modification a été prise en compte et affiche les informations actualisées
7. L'utilisateur demande le refus des lignes de frais hors forfait non valides
8. L'utilisateur valide la fiche
9. Le système passe la fiche à l'état « Validée » et met à jour la date de modification de la fiche

Exception **4-a** : « Aucune fiche de frais n'existe, le système affiche
*"Pas de fiche de frais pour ce visiteur ce mois"*. Retour au 2. »

Le document précise aussi (page 4) :
> « Avant toute chose, établir une maquette de l'UI (en html) de votre
> application de validation. **Faire valider cette maquette par le client (Moi)
> avant de vous lancer dans le développement.** »

➡️ Le point d'arrêt de la phase 4 est donc bien une exigence du client, pas une
consigne pédagogique ajoutée. Je m'y arrêterai.

### 4.6 Écarts entre le cahier des charges et la base existante

Nouveaux points relevés grâce au document, à trancher avant les phases 1 et 3.

| # | Écart | Conséquence |
|---|-------|-------------|
| **E1** | L'annexe 1 affiche un **matricule** au format `NRD/125-01`. La table `visiteur` n'a qu'un `id CHAR(4)` (`a17`) et aucune colonne matricule. | Phase 3 : soit on imprime `a17` en l'assumant, soit on ajoute une colonne `matricule`. **Je proposerai d'imprimer l'id existant** : ajouter une colonne qu'aucune donnée ne remplit n'apporterait rien, et le cahier des charges ne définit pas la règle de construction du matricule. À confirmer. |
| **E2** | Les libellés de l'annexe (`Nuitée`, `Repas Midi`, `Véhicule`) ne sont pas ceux de la table `fraisforfait` (`Nuitée Hôtel`, `Repas Restaurant`, `Frais Kilométrique`, `Forfait Etape`). L'annexe ne montre que **3** lignes, la base en a **4**. | Phase 3 : j'imprimerai les libellés **de la base**, et les 4 lignes. L'annexe est un exemple de rendu, pas une liste exhaustive — et inventer une table de correspondance de libellés serait une complexité gratuite, non demandée. |
| **E3** | **Incohérence arithmétique dans l'annexe elle-même** : la ligne Nuitée indique 9 × 80.00 = **630.00**, alors que 9 × 80 = 720. Le total général (1756.80) est calculé avec 630.00, donc c'est bien 630 qui « fait foi » dans le document. | Aucune : c'est une erreur de saisie du client dans son exemple. Je calcule `quantité × montant unitaire`. **À signaler à l'oral** : savoir repérer une incohérence dans la spécification est un point apprécié. |
| **E4** | Le cahier des charges décrit la consultation « sur l'année écoulée » du montant remboursé **et du nombre de prestations**. La consultation existante (`ListeFraisController`) affiche mois par mois, sans notion d'année glissante ni de total. | Hors des 5 phases demandées. Je le note comme reste à faire, je ne l'implémente pas sans instruction. |
| **E5** | Le cahier des charges mentionne l'archive `gsbFrais.zip` ; le dossier contient `codeGsbSymfony.zip`. | Simple différence de nom, même contenu (le bundle du prédécesseur). Sans conséquence. |

**Confirmation du point B7 :** le cahier des charges liste bien, parmi les tâches
déjà validées par le prédécesseur, la « création d'un script de génération de
données aléatoires pour remplir la base à des fins de tests ». **Ce script
n'existe pas dans l'archive livrée.** Le constat de l'audit est donc confirmé
par le document lui-même : il manque une livraison annoncée.

---

## 5. Pourquoi de cette manière

### Phase 0

### Décision 0.1 — Travailler dans `gsbProjet/` et non dans `GsbFraisBundle/`
- **Problème :** deux copies du même bundle coexistent.
- **Retenu :** `gsbProjet/src/Pg/GsbFraisBundle/` est la seule copie chargée par
  `AppKernel` ; c'est elle qui tourne.
- **Écarté :** travailler dans `GsbFraisBundle/` à la racine puis recopier →
  risque de divergence silencieuse entre deux sources, et aucune exécution
  possible pour tester.
- Le `diff -rq` montre que les deux copies ont déjà divergé (17 fichiers
  diffèrent) : la version du projet contient des corrections de mise en route
  (mode `ERRMODE_EXCEPTION` sur PDO, insertion par colonnes nommées,
  `_selectionmois.html.twig` extrait en fragment, etc.). Revenir à l'archive
  casserait l'application.

### Décision 0.2 — Ne pas activer l'ORM Doctrine
- **Problème :** l'énoncé des phases suivantes parle d'entités ; la tentation
  est de mapper `Visiteur`, `FicheFrais`… en entités Doctrine.
- **Retenu :** rester sur `PdoGsb` + SQL préparé.
- **Raisons :** (a) c'est l'architecture imposée par le sujet et par le support
  CERTA ; (b) `doctrine/orm` figée en 2.5.14 dans `composer.lock` n'est pas
  compatible PHP 7.4 (erreur fatale « continue targeting switch » dans
  `UnitOfWork.php`) ; (c) la règle n°2 du cahier des charges interdit de
  réécrire l'existant.
- **Conséquence assumée :** pas de `doctrine:migrations`. Toute évolution de
  schéma passera par des scripts `sql/00X_description.sql` **incrémentaux**,
  comme demandé.

### Décision 0.3 — Ne rien installer avant validation
- Règle n°4 du cahier des charges. L'audit est donc purement en lecture :
  aucune commande `DROP`, `TRUNCATE`, `rm`, aucun `composer require`.
  Le seul écrit produit est ce fichier.

### Décision 0.4 — Conserver l'authentification « maison » plutôt que le composant Security
- **Problème :** la phase 5 demande une authentification comptable « séparée,
  avec rôles ». Symfony 2.8 sait faire (firewalls, `ROLE_COMPTABLE`).
- **Analyse :** basculer sur le composant Security obligerait à réécrire
  `HomeController`, `security.yml`, tous les gardes `estConnecte()` des
  contrôleurs existants, et à introduire un `UserProvider` + un encodeur de mot
  de passe — donc à réécrire du code qui fonctionne, ce que la règle n°2
  interdit.
- **Proposition (à valider en phase 5) :** ajouter un `role` en session
  (`visiteur` / `comptable`) et une fonction `estComptable($session)` dans
  `fct.inc.php`, exactement dans le style de `estConnecte()`.
- **Je signale l'arbitrage :** c'est moins « propre » que le composant Security
  officiel, et un jury peut poser la question. La réponse est préparée au § 11.

### Phase 1

### Décision 1.1 — Historiser sur la LIGNE de frais, pas sur la fiche

- **Problème :** garantir qu'un changement de barème ne recalcule pas les fiches
  déjà saisies.
- **Options envisagées :**

| Option | Principe | Verdict |
|--------|----------|---------|
| **A — colonne `montantUnitaire` sur `lignefraisforfait`** | chaque ligne porte le tarif qui lui a été appliqué | ✅ **retenue** |
| B — colonne `tarifKm` sur `fichefrais` | une seule valeur par fiche | ❌ ne protège que le kilométrique ; si la direction change le prix du repas, toutes les fiches passées changent |
| C — table d'historique des barèmes avec dates de validité | on retrouve le tarif en vigueur à la date de la fiche | ❌ correct mais disproportionné : il faut gérer les périodes, les chevauchements, et chaque lecture devient une jointure sur intervalle de dates. Difficile à défendre à l'oral pour un gain nul ici |
| D — ne rien stocker, lire le barème à l'affichage | zéro modification de schéma | ❌ c'est exactement le défaut à corriger : le passé serait réécrit |

- **Ce qui a tranché :** l'annexe 1 du cahier des charges (§ 4.4) imprime une
  colonne **« Montant unitaire » ligne par ligne**. Le document du client
  demande donc lui-même une information par ligne — l'option A n'est pas une
  invention de ma part, c'est la lecture du besoin.
- **Bénéfice non demandé mais gratuit :** l'option A protège aussi les montants
  Nuitée, Repas et Étape, pas seulement le kilométrique.

### Décision 1.2 — Répercuter un changement de véhicule sur les fiches à l'état `CR` uniquement

- **Problème :** un visiteur déclare son véhicule le 10 du mois, alors que sa
  fiche du mois a été créée le 2. Que devient le tarif déjà figé sur sa ligne ?
- **Retenu :** `majTypeVehiculeVisiteur()` met à jour la ligne kilométrique des
  fiches **à l'état `CR`** (saisie en cours), et uniquement celles-là.
- **Raison :** « ne pas recalculer rétroactivement » vise les fiches
  **terminées**. Une fiche encore en saisie n'est pas un historique, c'est un
  brouillon : elle doit refléter la situation réelle du visiteur. Le contraire
  produirait un bug absurde — un visiteur déclarant son véhicule le 10 serait
  remboursé au mauvais tarif pour tout le mois, sans aucun moyen de corriger.
- **Écarté :** ne jamais rien répercuter (le visiteur devrait attendre le mois
  suivant) ; tout répercuter (viole le cahier des charges).
- **Le filtre est dans le `WHERE` de la requête**, pas dans un `if` PHP : la
  règle est appliquée par la base pour toutes les fiches en une seule
  instruction, sans que l'application ait à les parcourir.

### Décision 1.3 — Le barème en base, mais l'identifiant `KM` dans le code

- **Problème :** le cahier des charges interdit de coder le barème en dur.
  Les **valeurs** sont donc en base (`typevehicule`). Mais il faut bien savoir
  *quelle* ligne de `fraisforfait` est la ligne kilométrique.
- **Retenu :** une constante de classe,
  `PdoGsb::ID_FRAIS_KILOMETRIQUE = 'KM'`, déclarée à un seul endroit et
  passée en paramètre lié aux requêtes.
- **Pourquoi c'est acceptable :** ce qui est volatile, c'est le **tarif** (la
  direction le changera), pas le **fait** qu'il existe un type de frais
  kilométrique. Cette information est structurelle.
- **Alternative écartée :** ajouter une colonne booléenne
  `fraisforfait.estKilometrique`. Plus « pur », mais cela ajoute une colonne à
  une table du prédécesseur pour exprimer une information qui ne changera
  jamais. **Je signale que c'est discutable** et qu'un jury peut préférer
  l'autre choix : la réponse est préparée au § 11 (Q15).

### Décision 1.4 — `montantUnitaire` en `NOT NULL` sans valeur par défaut

- **Problème :** que faire si une future insertion oublie le montant unitaire ?
- **Retenu :** `NOT NULL`, **sans** `DEFAULT`. MySQL en mode strict refuse
  alors l'insertion.
- **Écarté :** `NOT NULL DEFAULT 0.00`. L'insertion passerait, et produirait
  silencieusement une ligne remboursée **zéro euro**. Une erreur bruyante vaut
  mieux qu'un faux résultat : on la voit tout de suite, en développement.

### Décision 1.5 — Reprise de l'existant avec le barème courant

- **Problème :** les 16 lignes de frais déjà en base n'ont pas de montant
  unitaire. Avec quelle valeur les remplir ?
- **Retenu :** le montant courant de `fraisforfait` (donc 0,62 € pour le
  kilométrique).
- **C'est une hypothèse, et je l'assume comme telle :** aucun historique de
  barème n'a jamais été conservé, donc la seule valeur connue est celle
  d'aujourd'hui. Elle est exacte **parce que** les tarifs n'ont jamais changé
  depuis la création de la base. Si le barème avait déjà bougé par le passé,
  cette reprise serait fausse et il n'existerait aucun moyen de la corriger.
  C'est précisément le problème que la colonne empêche de reproduire à l'avenir.

### Décision 1.6 — Pas de total général pour l'instant

L'écran de consultation affiche le montant unitaire et le total **de chaque
ligne**, mais pas le total général de la fiche. Ce n'est pas un oubli : le total
général (forfait + hors forfait) appartient à l'annexe 1, donc à la **phase 3**.
L'ajouter ici m'obligerait à le coder deux fois, ou à le coder dans un template
avant d'avoir décidé où il doit vivre.

### Décision 1.7 — Maintenir `sql/bd_gsb.sql` en phase avec les migrations

- **Problème :** après la migration 001, une base créée avec `bd_gsb.sql`
  n'aurait plus le même schéma qu'une base migrée. Deux développeurs auraient
  deux bases différentes.
- **Retenu :** répercuter chaque migration dans `bd_gsb.sql`, et le vérifier.
- **Vérification effectuée :** création d'une base jetable `bd_gsb_test` à
  partir de `bd_gsb.sql`, puis comparaison de son schéma avec celui de `bd_gsb`
  par `mysqldump --no-data` + `diff`. Résultat : **aucune différence de
  structure** (les seuls écarts portaient sur la collation, parce que ma base de
  test avait été créée avec la collation par défaut du serveur). La base jetable
  a été supprimée ensuite ; `bd_gsb` n'a pas été touchée.

### Phase 2

### Décision 2.1 — Stocker hors de `web/` **et** générer un nom imprévisible

- **Problème :** empêcher qu'un visiteur accède au justificatif d'un collègue.
- **Le sujet propose l'un OU l'autre** (« stockage hors du dossier public **ou**
  avec nom généré non devinable »). **J'ai fait les deux.**
- **Pourquoi pas seulement le nom imprévisible :** un fichier dans `web/`
  reste servi par le serveur web sans passer par l'application. Celui qui
  obtient l'adresse — par un historique de navigateur, un copier-coller dans un
  message, un journal de serveur mandataire — y accède pour toujours, même
  après le départ du visiteur de l'entreprise. C'est de la **sécurité par
  l'obscurité** : elle ne résiste pas à une fuite d'adresse.
- **Pourquoi pas seulement hors de `web/` :** ce serait déjà suffisant, le
  contrôle d'accès étant fait par le contrôleur. Le nom aléatoire est une
  **défense en profondeur** : si une erreur de configuration exposait un jour
  le dossier, les noms resteraient indevinables.
- **Comment le nom est généré :** `bin2hex(random_bytes(16))`, soit 32
  caractères hexadécimaux. `random_bytes()` est un générateur
  **cryptographiquement sûr** ; `rand()` ou `uniqid()` ne le sont pas
  (`uniqid()` est fondé sur l'horloge, donc partiellement prévisible).

### Décision 2.2 — Vérifier le contenu du fichier, pas seulement son extension

- **Problème :** un fichier nommé `facture.pdf` peut contenir n'importe quoi.
- **Retenu :** deux contrôles qui doivent **tous les deux** passer — l'extension
  doit figurer dans la liste autorisée, **et** le type MIME réel doit
  correspondre à cette extension.
- **Comment le type réel est obtenu :** `UploadedFile::getMimeType()` s'appuie
  sur l'extension PHP `fileinfo`, qui **lit les premiers octets du fichier**.
  Elle ne fait pas confiance à ce que le navigateur annonce — cette
  déclaration est entièrement sous le contrôle de celui qui envoie.
- **Vérifié en test (T7) :** un PDF renommé en `.png` est refusé avec
  « Le contenu du fichier ne correspond pas à son extension .png ».

### Décision 2.3 — Le nom d'origine n'est jamais un chemin

- **Problème :** l'attaque classique du téléversement consiste à envoyer un
  fichier nommé `../../app/config/parameters.yml` pour écrire, ou lire, hors du
  dossier prévu (« traversée de répertoire »).
- **Retenu :** le nom fourni par le navigateur ne sert **qu'à l'affichage**. Le
  fichier est écrit sous le nom généré. Le nom d'origine est en plus assaini
  (`preg_replace('/[^A-Za-z0-9._-]/', '_', …)` après `basename()`), puis
  tronqué à 255 caractères.
- **Double sécurité :** `StockageJustificatifs::chemin()` applique de nouveau
  `basename()` avant de construire le chemin de lecture. Même si un nom
  fantaisiste parvenait en base, il ne pourrait pas faire sortir le chemin du
  dossier de stockage.

### Décision 2.4 — Recalculer le compteur, ne jamais l'incrémenter

- **Problème :** tenir à jour `fichefrais.nbJustificatifs`.
- **Écarté :** `UPDATE … SET nbJustificatifs = nbJustificatifs + 1`. Un
  compteur incrémenté **dérive** : un double clic, une erreur au milieu d'une
  opération, une suppression non répercutée, et il devient faux — sans que rien
  ne le signale. Or ce compteur est ce que le comptable lit pour décider d'un
  remboursement.
- **Retenu :** un `UPDATE` dont la valeur vient d'un `SELECT COUNT(*)` sur les
  lignes réellement pourvues d'un fichier. Le compteur est donc **recalculé à
  partir de la vérité**, en une seule instruction, à chaque dépôt et à chaque
  suppression. Il ne peut pas diverger.
- **Pourquoi je n'ai pas utilisé `majNbJustificatifs()` du prédécesseur :**
  cette méthode reçoit le nombre **déjà calculé** par l'appelant. Il faudrait
  donc lire puis écrire en deux temps, ce qui rouvre la porte à la dérive si
  deux opérations se croisent. Je l'ai laissée intacte (règle : ne rien
  supprimer) mais elle reste inutilisée.

### Décision 2.5 — Répondre 404 et non 403 à une consultation interdite

- **Problème :** quel code renvoyer à un visiteur qui demande le justificatif
  d'un autre ?
- **Retenu :** **404 « introuvable »**, le même que pour un identifiant qui
  n'existe pas.
- **Pourquoi :** un 403 « interdit » **confirmerait l'existence** du document.
  En comparant 403 et 404, un curieux pourrait cartographier les frais de ses
  collègues sans jamais ouvrir un fichier. Avec un 404 uniforme, il n'apprend
  rien.

### Décision 2.6 — Écrire `estComptable()` maintenant, sans compte comptable

- **Problème :** le sujet exige que « le comptable puisse consulter la pièce
  jointe », mais le comptable n'existe pas avant la phase 5.
- **Retenu :** poser dès maintenant la **règle d'accès** —
  `$estProprietaire || estComptable($session)` — avec une fonction qui renvoie
  faux pour tout le monde tant qu'aucun rôle « comptable » n'est en session.
- **Pourquoi :** le jour où la phase 5 créera le compte, la consultation
  fonctionnera **sans qu'une ligne de `JustificatifController` soit modifiée**.
  L'alternative — écrire le contrôle « propriétaire seulement » puis le
  reprendre en phase 5 — obligerait à retoucher du code de sécurité déjà
  testé, ce qui est exactement là où l'on introduit des failles.
- **Je suis clair là-dessus :** la règle est livrée, **l'acteur ne l'est pas**.
  Ce point est donc partiellement fait, et listé en limite L7.

### Décision 2.7 — Le justificatif est facultatif à la saisie

Le cahier des charges laisse au visiteur tout le mois pour compléter sa fiche,
et le service comptable réceptionne les factures « au plus tard le 10 du mois
suivant ». Imposer la facture au moment de la saisie du frais serait donc plus
restrictif que le besoin réel. La ligne peut être créée seule, le justificatif
joint ou remplacé plus tard.

### Décision 2.8 — Plafond à 2 Mo, aligné sur `php.ini`

- **Retenu :** `justificatifs.taille_max: 2097152` (2 Mo), soit exactement la
  valeur de `upload_max_filesize` du poste.
- **Pourquoi aligner :** au-delà de la limite de `php.ini`, PHP écarte le
  fichier **avant** que l'application le voie ; annoncer 5 Mo dans l'interface
  alors que PHP en refuse 3 produirait un message incompréhensible.
- **Pour accepter des fichiers plus gros**, il faut modifier les **deux** :
  `upload_max_filesize` et `post_max_size` dans `php.ini`, **puis**
  `justificatifs.taille_max` dans `services.yml`. Une photo de facture prise au
  téléphone dépasse souvent 2 Mo : **à toi de décider** si on relève la limite.

### Décision 2.9 — Le disque doit suivre la base à la suppression

- **Problème :** la donnée vit à deux endroits — une ligne en base et un fichier
  sur le disque. La base sait propager une suppression (`ON DELETE CASCADE`) ;
  le disque, non.
- **Retenu :** dans `supprimerfraishorsforfaitAction()`, lire la ligne **avant**
  de la supprimer pour mémoriser le nom du fichier, supprimer la ligne, effacer
  le fichier, puis recalculer le compteur par (visiteur, mois).
- **Ce qui serait arrivé sans cela :** rien de visible. Aucune erreur, aucun
  écran cassé — simplement un dossier `app/justificatifs/` qui grossit
  indéfiniment avec des factures que plus aucune ligne ne référence, et qui
  contiennent des données personnelles. C'est le genre de défaut qu'on ne
  découvre que des mois plus tard.
- **Vérifié en test (T13) :** après suppression du frais, le fichier a disparu
  du disque et le compteur est passé de 2 à 1.

### Phase 3

### Décision 3.1 — dompdf plutôt que TCPDF ou un générateur maison

*Décision validée par le client.*

- **Retenu :** `composer require dompdf/dompdf:^2.0` → dompdf v2.0.8 et
  4 dépendances (`masterminds/html5`, `phenx/php-font-lib`,
  `phenx/php-svg-lib`, `sabberworm/php-css-parser`). Compatibles PHP 7.4,
  aucune extension système supplémentaire.
- **Pourquoi :** on décrit la fiche en **HTML et CSS, dans un gabarit Twig** —
  exactement la technique déjà employée partout ailleurs dans le projet. Un
  ajustement de maquette est une modification de CSS, pas un calcul de
  coordonnées.
- **Écarté — TCPDF :** son interface est impérative, on positionne chaque
  cellule en millimètres (`SetXY`, `Cell`, `MultiCell`). Le tableau de
  l'annexe 1 aurait demandé une centaine de lignes de positionnement, et toute
  retouche se serait faite à la calculette.
- **Écarté — écrire le PDF à la main :** c'est précisément la « magie »
  que le sujet demande d'éviter. Polices, encodage et table des objets à gérer
  soi-même, pour un code indéfendable à l'oral.
- **Sécurité :** les 47 alertes signalées par `composer audit` **ne concernent
  pas dompdf** ; elles portent sur Symfony 2.8 (31), Twig (14) et Swiftmailer
  (1) — des paquets déjà présents, en fin de vie, imposés par le sujet. Voir
  limite L16.

### Décision 3.2 — Une archive ZIP de PDF individuels, pas un PDF multi-pages

*Décision validée par le client.*

- **Retenu :** un fichier PDF par fiche, ajoutés un par un dans une archive ZIP.
- **Pourquoi c'est la bonne réponse à la question de volumétrie du sujet :**
  dompdf construit **tout le document en mémoire** avant de l'écrire. Un PDF
  multi-pages de 500 fiches serait donc intégralement en mémoire à la fin. Avec
  des fichiers séparés, la mémoire ne contient jamais qu'**une** fiche.
- **Et c'est aussi le bon choix métier :** le cahier des charges décrit un
  classement « par ordre chronologique dans une pochette nominative pour chaque
  visiteur ». Une fiche par fichier se classe directement ; un document unique
  demanderait un découpage manuel.
- **Le compromis assumé :** l'impression papier demande d'ouvrir chaque PDF.
  Pour un archivage papier en une seule commande d'impression, le document
  unique serait plus pratique.

### Décision 3.3 — Imprimer l'identifiant du visiteur en guise de matricule

*Décision validée par le client.*

L'annexe 1 affiche `NRD/125-01`. La base ne connaît que `a17`. Le cahier des
charges **ne définit nulle part** la règle de construction de ce matricule :
ajouter une colonne `visiteur.matricule` reviendrait à inventer des valeurs,
donc à livrer des données fausses, et à construire un écran pour les saisir.
La fiche imprime donc `a17`. À l'oral : « l'identifiant du visiteur tient ce
rôle, faute de spécification du matricule ».

### Décision 3.4 — Le traitement de masse existe aussi en commande console

- **Problème :** la génération « en un clic » est une fonction du comptable, et
  le comptable n'existe pas avant la phase 5. Livrer uniquement la route web
  reviendrait à livrer le cœur de la phase 3 sans pouvoir le démontrer.
- **Retenu :** la logique vit dans le **service**
  `GenerateurFichePdf::genererArchiveMois()`. Le contrôleur web (réservé au
  comptable) **et** la commande `gsb:imprimer-fiches` l'appellent tous les deux.
- **Bénéfices :** une seule implémentation à maintenir ; ce qui est vérifié en
  console vaut pour l'écran ; et une commande console **n'est pas soumise au
  délai d'expiration d'une requête HTTP** — ce qui compte pour 500 fiches
  (49 secondes pour 202 fiches sur ce poste).

### Décision 3.5 — Écrire le générateur de données de test manquant

Le cahier des charges présente ce script comme livré par le prédécesseur ; il
est absent de l'archive (point B7). Sans lui, l'affirmation « la mémoire ne
croît pas avec le nombre de fiches » resterait une opinion. `gsb:generer-donnees`
crée des visiteurs dont l'identifiant est **préfixé par `z`** : on peut les
retrouver et les retirer sans jamais toucher aux 9 visiteurs d'origine.

### Décision 3.6 — 403 pour l'export de masse, 404 pour une fiche

Les justificatifs et les fiches individuelles répondent **404** à un accès
interdit, pour ne pas révéler l'existence du document (décision 2.5). L'export
mensuel, lui, répond **403** : l'existence d'un export du mois n'a rien de
confidentiel, et l'utilisateur est bien authentifié — il lui manque seulement
le rôle. 403 est alors le code juste.

### Décision 3.7 — Couper l'accès réseau de dompdf

`$options->set('isRemoteEnabled', false)` et un `chroot` limité au dossier du
logo. Sans cela, une balise `<img src="http://…">` glissée dans un **libellé de
frais** — donc saisie par un visiteur — ferait émettre une requête HTTP **par le
serveur**. C'est une faille connue (SSRF) : le serveur devient un relais pour
atteindre des adresses internes. Le gabarit n'a besoin d'aucune ressource
distante, la coupure ne retire donc rien.

---

## 6. Logique des boucles et des algorithmes

**La phase 0 ne contient aucune boucle : je n'ai écrit aucun code.**
En revanche j'ai lu les boucles existantes, et trois d'entre elles
conditionnent les phases suivantes. Je les documente ici parce que je vais
devoir m'y raccrocher.

### Phase 0 — lecture des boucles existantes

### 6.1 `PdoGsb::creeNouvellesLignesFrais()` — création de fiche + clôture N-1

*Fichier :* `src/Pg/GsbFraisBundle/services/PdoGsb.php` (l. 219-246)

Pseudo-code :

```
dernierMois ← MAX(mois) des fiches du visiteur
SI dernierMois existe ALORS
    fiche ← infos de (visiteur, dernierMois)
    SI fiche.idEtat = 'CR' ALORS passer fiche à 'CL'
INSERER fichefrais(visiteur, mois, 0, 0, aujourd'hui, 'CR')
lesIdFrais ← les 4 id de fraisforfait
POUR CHAQUE idFrais DANS lesIdFrais
    INSERER lignefraisforfait(visiteur, mois, idFrais, 0)
```

- **Ce que la boucle parcourt :** les identifiants de la table `fraisforfait`
  (ETP, KM, NUI, REP). **Ce qu'elle produit :** une ligne de quantité 0 par type
  de frais forfaitisé, pour que le formulaire de saisie ait 4 champs à afficher.
- **Invariant :** après le i-ᵉ tour, la fiche possède exactement i lignes
  forfait, toutes à quantité 0.
- **Pourquoi `foreach` :** on parcourt un tableau associatif renvoyé par
  `fetchAll()`, sans besoin d'indice ; `for` obligerait à gérer `count()` pour
  rien. Pourquoi pas une seule requête `INSERT … SELECT` (qui serait plus
  efficace) : parce que le style du prédécesseur est une requête préparée par
  ligne, et que le volume est de 4 lignes.
- **Cas limites gérés :** premier mois d'un visiteur (`dernierMois = null`) →
  on saute la clôture ; fiche N-1 déjà clôturée → pas de double clôture.
- **Coût :** 2 requêtes + 1 requête par type de frais = **6 requêtes SQL** pour
  4 types. C'est un N+1 de principe, sans gravité ici (N = 4, fixe), mais
  **à ne pas reproduire** dans les phases 3 et 5 où N = nombre de fiches du mois.

### 6.2 `PdoGsb::getLesMoisDisponibles()` — boucle `while` sur le curseur

*Fichier :* `PdoGsb.php` (l. 288-308)

```
laLigne ← fetch()
lesMois ← tableau vide
TANT QUE laLigne n'est pas null
    mois ← laLigne['mois']                      // « 202609 »
    lesMois[mois] ← { mois, numAnnee = mois[0..3], numMois = mois[4..5] }
    laLigne ← fetch()
```

- **Invariant :** à chaque tour, `lesMois` contient les mois déjà lus, indexés
  par la chaîne `aaaamm`, triés du plus récent au plus ancien (l'ordre est donné
  par le `ORDER BY mois DESC` de la requête).
- **Pourquoi `while` et pas `foreach` sur `fetchAll()` :** choix du prédécesseur
  (lecture ligne à ligne du curseur, qui consomme moins de mémoire).
  Je le conserve — c'est exactement le motif dont j'aurai besoin en phase 3 pour
  générer beaucoup de PDF sans saturer la mémoire.
- **Cas limite :** aucun mois → tableau vide ; `ListeFraisController` teste
  `count($lesCles) > 0` et affiche un sélecteur vide. Déjà géré.
- **Coût :** 1 requête, N itérations (N = nombre de mois du visiteur).

### 6.3 `PdoGsb::getLesFraisHorsForfait()` — boucle `for` avec modification en place

*Fichier :* `PdoGsb.php` (l. 66-80)

```
lesLignes ← fetchAll()
POUR i DE 0 À count(lesLignes)-1
    lesLignes[i]['date'] ← dateAnglaisVersFrancais(lesLignes[i]['date'])
```

- **Pourquoi `for` et pas `foreach` :** commentaire d'origine explicite — un
  `foreach` classique travaille sur une *copie* de chaque élément, la
  modification serait perdue. (En PHP moderne on écrirait
  `foreach ($lesLignes as &$ligne)`, mais je ne touche pas à ce qui marche.)
  Pourquoi pas `array_map` : il faudrait reconstruire chaque ligne complète.
- **Coût :** 1 requête, N itérations. Pas de N+1.

### Phase 1

> **Combien de boucles ai-je écrites en phase 1 ? Une seule**, et c'est une
> boucle que j'ai *modifiée*, pas créée. Tout le reste est fait en SQL. Ce n'est
> pas un hasard : à chaque fois qu'on est tenté d'écrire une boucle PHP qui
> exécute une requête à chaque tour, il faut se demander si une seule requête
> ne ferait pas le travail. Le détail ci-dessous.

### 6.4 `PdoGsb::creeNouvellesLignesFrais()` — la boucle modifiée

*Fichier :* `src/Pg/GsbFraisBundle/services/PdoGsb.php`

**Avant (prédécesseur) :**

```
lesIdFrais ← les identifiants de fraisforfait        (1 requête)
POUR CHAQUE idFrais
    INSERER lignefraisforfait(visiteur, mois, idFrais, quantité = 0)
```

**Après (phase 1) :**

```
lesMontants ← pour chaque type de frais, le montant unitaire applicable
              à CE visiteur                          (1 requête)
POUR CHAQUE (idFrais, montantUnitaire) DANS lesMontants
    INSERER lignefraisforfait(visiteur, mois, idFrais,
                              quantité = 0, montantUnitaire)
```

- **Ce que la boucle parcourt :** les 4 types de frais forfaitisés, chacun
  accompagné du montant unitaire qui s'applique **à ce visiteur** (tarif de son
  véhicule pour la ligne kilométrique, tarif du référentiel pour les autres).
- **Ce qu'elle produit :** les 4 lignes de la nouvelle fiche, à quantité 0,
  chacune portant déjà son tarif figé.
- **Invariant :** après le i-ᵉ tour, la fiche possède i lignes, et chacune
  porte le montant unitaire en vigueur **à l'instant de la création**. Ce
  montant ne sera plus jamais modifié, sauf si le visiteur change de véhicule
  alors que la fiche est encore à l'état `CR`.
- **Pourquoi `foreach` et pas `for` :** on parcourt un tableau associatif issu
  de `fetchAll()`, sans avoir besoin de l'indice. Pourquoi pas `array_map` :
  on ne construit pas un nouveau tableau, on provoque un effet de bord (4
  insertions) — `array_map` détourné à cet usage est trompeur pour le lecteur.
- **Pourquoi une boucle plutôt qu'une seule requête `INSERT … SELECT` ?**
  Techniquement une seule instruction suffirait. J'ai gardé la boucle parce
  que **c'est la structure du prédécesseur** et que la règle du sujet est de ne
  pas réécrire l'existant. Le gain aurait été de 3 requêtes, sur une opération
  qui n'a lieu **qu'une fois par visiteur et par mois**. Je signale l'arbitrage :
  ici, la cohérence avec le code repris l'emporte sur la micro-optimisation.
- **Cas limites gérés :** visiteur sans véhicule déclaré → la requête retombe
  sur le tarif historique via `coalesce` ; premier mois d'un visiteur → pas de
  fiche N-1 à clôturer (comportement d'origine, inchangé).
- **Coût :** **6 requêtes SQL au total** — exactement le même nombre qu'avant
  la phase 1. Le point important : la requête qui récupère les montants
  unitaires est faite **une seule fois avant la boucle**, pas à chaque tour.
  Une version naïve aurait appelé `getTarifKilometrique()` dans la boucle et
  serait passée à 6 + 4 = 10 requêtes. **Aucun N+1 introduit.**

### 6.5 L'algorithme du montant unitaire applicable — fait en SQL, sans boucle

*Fichier :* `PdoGsb::getLesMontantsUnitairesApplicables()`

La règle métier est : *« pour chaque type de frais, le montant unitaire est
celui du référentiel, sauf pour la ligne kilométrique, où c'est le tarif du
véhicule du visiteur, et à défaut le tarif historique. »*

Écrite en PHP, cette règle serait une boucle avec un `if` et une requête
supplémentaire. Écrite en SQL, c'est une seule requête :

```sql
select fraisforfait.id as idfrais,
       case when fraisforfait.id = :idFraisKm
            then coalesce(typevehicule.montant, fraisforfait.montant)
            else fraisforfait.montant
       end as montantunitaire
from fraisforfait
left join visiteur     on visiteur.id = :idVisiteur
left join typevehicule on typevehicule.id = visiteur.idTypeVehicule
order by fraisforfait.id
```

- **Pourquoi `LEFT JOIN` et pas `INNER JOIN` :** avec `INNER JOIN`, un visiteur
  **sans véhicule déclaré** ne produirait aucune ligne du tout — la fiche
  serait créée vide. Le `LEFT JOIN` conserve les 4 lignes de `fraisforfait`
  même quand `typevehicule` n'a rien à apparier.
- **Rôle de `coalesce` :** renvoie le premier argument non NULL. Quand le
  visiteur n'a pas de véhicule, `typevehicule.montant` vaut NULL (conséquence
  du `LEFT JOIN`) et on retombe sur `fraisforfait.montant`, c'est-à-dire
  0,62 €/km — **le comportement exact d'avant la phase 1**.
- **Cas limite « valeur NULL en base » :** c'est le cas nominal ici, pas une
  anomalie. Les 9 visiteurs repris ont `idTypeVehicule = NULL`.
- **Coût :** 1 requête, quel que soit le nombre de types de frais.

### 6.6 La répercussion sur les fiches en cours — également sans boucle

*Fichier :* `PdoGsb::majTypeVehiculeVisiteur()`

Pseudo-code :

```
DÉBUT DE TRANSACTION
    visiteur.idTypeVehicule ← le nouveau type
    POUR toutes les lignes kilométriques du visiteur
        dont la fiche est à l'état 'CR' :
            montantUnitaire ← tarif du nouveau véhicule (ou tarif historique)
VALIDER    (ou TOUT ANNULER en cas d'erreur)
```

La deuxième étape **n'est pas une boucle PHP** : c'est un `UPDATE … INNER JOIN
… WHERE fichefrais.idEtat = 'CR'`. Le serveur SQL met à jour toutes les lignes
concernées en une instruction.

- **Pourquoi pas une boucle PHP ?** Il faudrait d'abord lire la liste des
  fiches `CR` du visiteur (1 requête), puis faire un `UPDATE` par fiche : c'est
  le motif **N+1** caractéristique. Avec l'`UPDATE` joint : **1 requête**, quel
  que soit le nombre de fiches.
- **Pourquoi une transaction ?** Les deux écritures forment un tout. Si la
  seconde échouait, le visiteur aurait un véhicule déclaré mais une fiche en
  cours restée à l'ancien tarif : un état incohérent, silencieux, et très
  difficile à diagnostiquer plus tard. `beginTransaction()` / `commit()`, avec
  `rollBack()` dans le `catch`, garantit « les deux ou aucune ».
- **Cas limites gérés :** aucune fiche à l'état `CR` → l'`UPDATE` touche
  0 ligne, ce n'est pas une erreur ; type de véhicule inexistant → rejeté
  **avant** d'entrer ici, par `estTypeVehiculeValide()`.
- **Coût :** 2 requêtes, quel que soit le nombre de fiches du visiteur.

### 6.7 Le calcul des totaux — délégué au moteur SQL

`getLesFraisForfait()` renvoie `quantite * montantUnitaire as montant`. Aucun
calcul en PHP, aucune boucle dans le contrôleur, aucune arithmétique dans le
template Twig — celui-ci se contente d'afficher une valeur déjà calculée.

Le seul « parcours » restant est le `{% for %}` des templates, qui **affiche**
une ligne par élément et n'exécute aucune requête : ce n'est pas un N+1.

### Phase 2

> **Aucune boucle nouvelle n'a été écrite en phase 2**, sauf une boucle de
> recopie de messages d'erreur (§ 6.10). Tout le travail de comptage est fait
> par le moteur SQL. Le détail ci-dessous explique pourquoi, à chaque fois, la
> boucle PHP aurait été un mauvais choix.

### 6.8 Le comptage des justificatifs — une agrégation SQL, pas un parcours

*Fichier :* `PdoGsb::recalculerNbJustificatifs()` et
`recalculerNbJustificatifsDuFrais()`

Pseudo-code de l'intention :

```
POUR la fiche (visiteur, mois) :
    n ← nombre de lignes hors forfait de cette fiche ayant un nomFichier
    fichefrais.nbJustificatifs ← n
```

Écrit tel quel en PHP, cela donnerait : une requête pour lire les lignes, une
boucle pour compter celles qui ont un fichier, puis une requête pour écrire le
résultat. **Trois opérations et un aller-retour lecture/écriture.**

Écrit en SQL, c'est une seule instruction :

```sql
update fichefrais
   set nbJustificatifs = (
       select count(*) from lignefraishorsforfait
        where lignefraishorsforfait.idVisiteur = :idVisiteur
          and lignefraishorsforfait.mois       = :mois
          and lignefraishorsforfait.nomFichier is not null)
 where fichefrais.idVisiteur = :idVisiteur and fichefrais.mois = :mois
```

- **Pourquoi c'est mieux qu'une boucle :** la lecture et l'écriture ont lieu
  dans la **même instruction**, donc dans le même instant logique pour la base.
  Avec lecture puis écriture séparées, un second dépôt de justificatif survenu
  entre les deux serait écrasé par une valeur périmée.
- **Cas limites gérés :** aucune ligne hors forfait → `COUNT(*)` vaut 0, pas
  NULL, donc pas de division ni d'affectation NULL ; ligne supprimée → c'est la
  variante par (visiteur, mois) qui est appelée, puisque l'identifiant du frais
  n'existe plus.
- **Coût :** **1 requête**, quel que soit le nombre de lignes de frais. Aucun
  risque de N+1.

### 6.9 Pourquoi deux variantes de la même méthode

`recalculerNbJustificatifsDuFrais($idFrais)` retrouve la fiche **par jointure**
depuis la ligne de frais ; `recalculerNbJustificatifs($idVisiteur, $mois)`
désigne la fiche directement.

La première est pratique après un dépôt (on n'a sous la main que
l'identifiant du frais). La seconde est **indispensable** après une
suppression : la ligne n'existe plus, la jointure ne trouverait rien et le
compteur resterait figé à sa valeur d'avant. C'est un cas limite qui ne se voit
pas à la lecture du code et qui se serait manifesté par un compteur faussement
élevé après chaque suppression.

### 6.10 La seule boucle de la phase 2

*Fichier :* `JustificatifController::joindreAction()`

```
POUR CHAQUE message d'erreur de validation
    l'ajouter au flash bag de la session
```

- **Ce qu'elle parcourt :** le tableau de messages retourné par
  `StockageJustificatifs::valider()` (0 à 3 éléments).
- **Pourquoi une boucle :** `ajouterErreur()`, la fonction du prédécesseur,
  n'accepte qu'un message à la fois. Je ne la modifie pas.
- **Pourquoi le flash bag et pas un simple tableau :** cette action **redirige**
  vers la page de saisie. Un tableau passé au gabarit serait perdu, puisque la
  redirection déclenche une nouvelle requête. Le flash bag survit à une
  redirection **et se vide à la première lecture** — exactement le comportement
  voulu pour un message ponctuel. (C'est aussi pourquoi il ne convenait pas
  pour la connexion, cf. § 6.2.)
- **Pourquoi rediriger plutôt que rendre la page directement :** sans
  redirection, un rafraîchissement du navigateur réenverrait le fichier.
- **Coût :** aucune requête SQL. Cas limite : tableau vide → la boucle ne
  s'exécute pas, aucun message parasite.

### 6.11 L'ordre des opérations lors d'un remplacement — un algorithme en soi

*Fichier :* `JustificatifController::joindreAction()`

L'ordre des quatre étapes n'est pas indifférent :

```
1. mémoriser le nom de l'ANCIEN fichier
2. écrire le NOUVEAU fichier sur le disque
3. enregistrer le nouveau nom en base (dans une transaction avec le compteur)
4. SEULEMENT MAINTENANT, effacer l'ancien fichier du disque
```

- **Si l'on effaçait l'ancien en premier** et que l'écriture du nouveau
  échouait (disque plein, fichier corrompu), le visiteur perdrait son
  justificatif sans rien obtenir en échange. **Dans l'ordre retenu, le pire qui
  puisse arriver est de conserver l'ancien.**
- **Pourquoi la base et le disque sont dans cet ordre :** si l'écriture en base
  échoue, il reste un fichier orphelin sur le disque — gênant mais sans
  conséquence pour l'utilisateur. Si c'était l'inverse, la base référencerait un
  fichier inexistant, et la consultation planterait.
- La lecture/écriture en base est enveloppée dans une **transaction** :
  la ligne de frais et le compteur de la fiche changent ensemble ou pas du tout.

### Phase 3

### 6.12 La boucle de génération de masse — le cœur de la volumétrie

*Fichier :* `GenerateurFichePdf::genererArchiveMois()`

Pseudo-code :

```
lesFiches ← identifiants et identités des fiches du mois à l'état voulu   (1 requête)
ouvrir l'archive ZIP
POUR CHAQUE fiche DANS lesFiches
    pdf ← générer le PDF de cette fiche                    (3 requêtes)
    SI pdf est vide, passer à la suivante
    écrire pdf dans un fichier temporaire
    LIBÉRER pdf de la mémoire
    déclarer le fichier temporaire dans l'archive
fermer l'archive        ← c'est seulement ici que les fichiers sont lus
effacer les fichiers temporaires
```

- **Ce que la boucle parcourt :** la liste des fiches du mois. **Ce qu'elle
  produit :** un fichier PDF par fiche, rassemblés dans une archive.
- **Invariant — et c'est tout l'enjeu :** *à la fin de chaque tour, la mémoire
  ne contient aucun PDF.* Le `unset($pdf)` juste après l'écriture n'est pas
  décoratif : sans lui, la variable resterait occupée jusqu'au tour suivant et
  deux PDF cohabiteraient.
- **Pourquoi la requête de tête ne ramène que les identités :** si
  `getLesFichesDuMois()` renvoyait le détail des frais, tout le mois serait en
  mémoire avant même d'avoir commencé. Le détail est lu fiche par fiche.
- **Pourquoi `foreach` :** parcours simple d'un tableau, sans indice.
  Pourquoi pas `array_map` : on provoque des effets de bord (écriture de
  fichiers), on ne construit pas un tableau.
- **Cas limites gérés :** aucune fiche pour ce mois → la méthode retourne 0
  sans créer d'archive vide ; une fiche sans ligne forfaitisée (donc
  inexistante) → `genererFiche()` renvoie `null` et la fiche est sautée sans
  interrompre le lot.
- **Coût :** 1 + 3 × N requêtes pour N fiches. **C'est bien un motif N+1**, et
  je le signale : pour 202 fiches, 607 requêtes. Je l'ai conservé parce que
  chaque fiche est un document indépendant qu'il faut de toute façon composer
  séparément, et parce que la solution — une requête unique ramenant toutes les
  lignes de toutes les fiches — **ramènerait en mémoire ce que je cherche
  justement à ne pas y mettre**. Ici, économiser des requêtes coûterait de la
  mémoire : c'est un arbitrage, pas un oubli.

### 6.13 La preuve chiffrée de la volumétrie

C'est le point que le sujet demande d'expliquer. Mesures réelles sur ce poste,
avec `memory_get_peak_usage(true)` :

| Nombre de fiches | Pic de mémoire | Durée | Taille de l'archive |
|------------------|----------------|-------|---------------------|
| 1 | **30,0 Mo** | 1,3 s | 25,6 Ko |
| 202 | **38,0 Mo** | 49,0 s | 5 152 Ko (6,3 Mo décompressés) |

**202 fois plus de fiches pour 8 Mo de mémoire en plus.** Si les PDF
s'accumulaient en mémoire, 202 fiches à ~31 Ko auraient ajouté 6,3 Mo de
contenu — mais surtout, la courbe serait **proportionnelle** : 2 000 fiches
feraient 63 Mo, 20 000 en feraient 630 et le script s'arrêterait sur
`Allowed memory size exhausted`.

- Les **30 Mo de départ** sont le coût fixe de dompdf : il charge et met en
  cache les polices DejaVu (normale, grasse, italique, grasse italique) à la
  première fiche. Ce coût ne se répète pas.
- Les **8 Mo de croissance** ne sont pas les PDF : ce sont les entrées que
  `ZipArchive` conserve en mémoire jusqu'au `close()`, soit environ 40 Ko par
  fichier déclaré. C'est la seule part qui croît, et c'est la limite L15.
- **La durée, elle, croît bien proportionnellement** (~0,24 s par fiche). C'est
  normal et inévitable : il faut composer chaque document. C'est pour cela que
  le traitement de masse est aussi offert en **commande console**, qui n'est pas
  soumise au délai d'expiration d'une requête HTTP.

### 6.14 Le calcul du total général — deux parcours, zéro requête

*Fichier :* `GenerateurFichePdf::calculerTotal()`

```
total ← 0
POUR CHAQUE ligne forfaitisée : total ← total + ligne.montant
POUR CHAQUE ligne hors forfait : total ← total + ligne.montant
```

- **Invariant :** après le i-ᵉ tour, `total` contient la somme des i premières
  lignes parcourues.
- **Pourquoi en PHP et non en SQL :** les deux tableaux sont **déjà en
  mémoire**, chargés pour être affichés. Les faire additionner par le serveur
  SQL coûterait deux requêtes d'agrégation supplémentaires **par fiche** — soit
  404 requêtes de plus sur un lot de 202 — pour additionner une quinzaine de
  nombres déjà présents. C'est l'exemple inverse du § 6.8 : là-bas, SQL évitait
  un aller-retour ; ici, il en ajouterait un.
- **Pourquoi `foreach` et pas `array_sum` :** `array_sum` exigerait d'extraire
  d'abord la colonne (`array_column`), donc de construire un tableau
  intermédiaire, pour le même résultat.
- **Cas limites gérés :** aucune ligne hors forfait → la seconde boucle ne
  s'exécute pas, le total vaut celui des forfaits ; le transtypage `(float)` est
  explicite parce que PDO renvoie les `DECIMAL` sous forme de **chaînes** —
  sans lui, `+` fonctionnerait quand même par conversion implicite, mais le
  code ne dirait pas ce qu'il fait.
- **Coût :** aucune requête, 4 + n itérations.

### 6.15 L'insertion des données de test — préparer une fois, exécuter N fois

*Fichier :* `PdoGsb::genererVisiteursDeTest()`

```
préparer les 4 requêtes d'insertion          ← UNE SEULE FOIS
lire les référentiels (véhicules, frais)     ← UNE SEULE FOIS
DÉBUT DE TRANSACTION
POUR i DE 1 À nbVisiteurs
    exécuter l'insertion du visiteur
    exécuter l'insertion de sa fiche
    exécuter l'insertion de ses 4 lignes forfait   ← une requête, 4 jeux de valeurs
    POUR j DE 0 À (0 à 3 tirés au sort)
        exécuter l'insertion d'un frais hors forfait
VALIDER    (ou TOUT ANNULER)
```

- **Pourquoi `for` et non `foreach` :** on compte des tours, on ne parcourt pas
  une collection existante. `for` exprime exactement cela.
- **Le point de performance :** `prepare()` est appelé **avant** la boucle,
  `execute()` à l'intérieur. Préparer une requête fait analyser son texte par le
  serveur ; préparer dans la boucle multiplierait ce coût par 200. De même, les
  référentiels (`typevehicule`, `fraisforfait`) sont lus une fois et gardés dans
  des tableaux — les relire à chaque tour serait le cas d'école du N+1.
- **Les 4 lignes forfaitisées en une requête** (`VALUES (…),(…),(…),(…)`) au
  lieu de quatre : c'est justement ce que `creeNouvellesLignesFrais()` ne fait
  pas, et que je n'ai pas modifié là-bas par respect du code du prédécesseur
  (§ 6.4). Ici, le code est neuf : rien ne m'oblige à reproduire le motif.
- **Cas limites gérés :** `id` est un `CHAR(4)`, donc préfixe + 3 chiffres →
  le nombre est plafonné à 999 ; une erreur en cours de route annule tout le
  lot par `rollBack()`, plutôt que de laisser un demi-jeu de test.
- **Coût mesuré :** 200 visiteurs et leurs fiches en **0,8 seconde**.

---

## 7. Erreurs rencontrées

### 7.1 `head` détourné par un script Perl
- **Message exact :**
  `Unknown option: 1` / `Unknown option: 0` / `Usage: head [-options] <url>...`
- **Ce que je faisais :** `find . -maxdepth 3 … | head -100`
- **Cause réelle :** sur ce poste, `head` dans le `PATH` n'est pas
  `/usr/bin/head` (le coreutil) mais `lwp-request`, l'outil HTTP de la
  bibliothèque Perl LWP installée par Homebrew, qui interprète `-100` comme une
  option HTTP inconnue. Ce n'est donc pas une erreur de ma commande : c'est un
  conflit de nom dans le `PATH`.
- **Correction :** appeler explicitement `/usr/bin/head`.
- **Pour ne pas le refaire :** en cas de message d'usage incohérent avec la
  commande tapée, lancer `which -a <commande>` pour voir toutes les versions
  présentes dans le `PATH`.

### 7.2 `mysqladmin ping` → accès refusé
- **Message exact :**
  `mysqladmin: connect to server at 'localhost' failed`
  `error: 'Access denied for user 'devakwabanm'@'localhost' (using password: NO)'`
- **Ce que je faisais :** vérifier que le serveur MySQL tourne.
- **Cause réelle :** le serveur répond parfaitement — c'est *l'utilisateur* qui
  est refusé. Sans `-u`, le client MySQL utilise le nom de compte du système
  (`devakwabanm`), qui n'existe pas dans MySQL. Un « Access denied » est en fait
  une **bonne nouvelle** : il prouve que le serveur est joignable (un serveur
  éteint renverrait `Can't connect to local MySQL server`).
- **Correction :** `mysql -u root`, conformément à `services.yml`.
- **Pour ne pas le refaire :** distinguer les deux familles d'erreurs MySQL —
  « Can't connect » = serveur/socket ; « Access denied » = identifiants.

### 7.3 `curl … | head -8` → `(23) Failed writing body`
- **Message exact :** `curl: (23) Failed writing body`
- **Cause réelle :** `head` ferme le tuyau après 8 lignes ; `curl` continue
  d'écrire et reçoit un `SIGPIPE`. C'est un effet de bord normal du test, pas un
  problème de l'application — la réponse HTTP était bien `200 OK`.
- **Correction :** aucune nécessaire.

### 7.4 Le cahier des charges ne contient pas de texte
- **Message exact :**
  `pdftoppm is not installed. Install poppler-utils (e.g. brew install poppler …)`
  puis, après extraction manuelle : 4 flux de contenu ne contenant que
  `A P - G S B   w e b`.
- **Ce que je faisais :** lire `ap-gsb-web.pdf` pour récupérer l'annexe 1.
- **Cause réelle :** deux causes superposées. (a) L'outil de rendu `poppler`
  n'est pas installé sur le poste. (b) Surtout : **ce PDF est un document
  scanné**. Ses 9 pages ne sont pas du texte mais 7 images ; le seul texte réel
  du fichier est son titre. Toute extraction de texte était donc vouée à
  l'échec, quel que soit l'outil.
- **Correction :** j'ai reconstruit les pages en PNG à partir des flux d'images
  du PDF. Chaque image est compressée en `FlateDecode` avec
  `/DecodeParms << /Predictor 15 /Colors 3 /Columns … >>`. Le prédicteur 15 est
  **le prédicteur PNG** : une fois décompressées, les données sont déjà au
  format « ligne de balayage PNG » (un octet de filtre, puis les pixels RGB).
  Il suffit donc de les réemballer dans un en-tête PNG (`IHDR` / `IDAT` /
  `IEND`) — aucune conversion de pixels n'est nécessaire.
- **Pourquoi ça marche :** le format PDF a emprunté au format PNG son algorithme
  de prédiction ; les deux partagent exactement la même disposition d'octets.
- **Pour ne pas le refaire :** avant de vouloir extraire le texte d'un PDF,
  vérifier qu'il en contient. 1,8 Mo pour 9 pages, c'est le poids d'un scan,
  pas d'un document texte (le support CERTA fait 1,3 Mo pour plus de 850 lignes
  de texte réel).
- **Erreur de conception de ma part :** mon premier extracteur sélectionnait les
  flux sur la seule présence des opérateurs `Tj`/`TJ`. Ces deux octets
  apparaissent par hasard dans des données d'image compressées : la sortie a été
  noyée sous des milliers de caractères binaires. **Le premier jet était mauvais
  parce que je filtrais sur un indice trop faible.** Le second filtre exige à la
  fois les opérateurs `BT`/`ET` (début et fin de bloc de texte) *et* un taux de
  caractères imprimables supérieur à 92 % : il a isolé immédiatement les 4 vrais
  flux de texte — et montré qu'ils étaient vides.

*Aucune erreur applicative n'a été rencontrée en phase 0 : le test de fumée est
passé du premier coup (voir § 9).*

### Phase 1

### 7.5 Un faux négatif dans mon propre test de sécurité
- **Ce que je faisais :** vérifier qu'un type de véhicule inventé
  (`idTypeVehicule=ZZZ`) est bien rejeté, avec
  `curl … | grep "Ce type de véhicule n'existe pas dans le barème"`.
- **Symptôme :** la commande n'affichait **rien**. Pendant quelques secondes,
  j'ai cru que le contrôle serveur ne fonctionnait pas.
- **Cause réelle :** le contrôle fonctionnait parfaitement. C'est mon `grep`
  qui était faux : **Twig échappe automatiquement le HTML**, l'apostrophe de
  « n'existe » est rendue `&#039;` dans la page. La chaîne cherchée n'existait
  donc pas telle quelle dans la réponse.
- **Correction :** chercher le bloc `class="erreur"` plutôt qu'un texte
  contenant une apostrophe. Le message est bien là :
  `<li>Ce type de véhicule n&#039;existe pas dans le barème</li>`, réponse
  HTTP 200, et `visiteur.idTypeVehicule` est resté inchangé en base.
- **Ce que ça m'a appris — et c'est un bon point pour l'oral :** cet
  « échec » est la **preuve que l'échappement automatique de Twig est actif**.
  C'est lui qui protège l'application des failles XSS : si un visiteur saisissait
  `<script>…</script>` dans un libellé de frais, Twig l'afficherait comme du
  texte au lieu de l'exécuter.
- **Pour ne pas le refaire :** ne jamais tester la présence d'un texte
  contenant `'`, `"`, `<`, `>` ou `&` dans du HTML généré — tester une balise
  ou une classe CSS.

### 7.6 Une transaction qui ne transactionne rien (erreur de conception)
- **Ce que j'avais écrit :** mon premier jet de
  `sql/001_puissance_fiscale_vehicule.sql` encadrait toute la migration d'un
  `START TRANSACTION … COMMIT`, avec l'idée de pouvoir tout annuler en cas
  d'échec.
- **Pourquoi c'était faux :** sous MySQL, **toute instruction DDL
  (`CREATE TABLE`, `ALTER TABLE`) provoque une validation implicite** de la
  transaction en cours. Le `COMMIT` final ne portait donc sur rien, et un échec
  au milieu du script aurait laissé la base dans un état intermédiaire — avec,
  en prime, la fausse assurance d'être protégé.
- **Correction :** retirer la transaction, et **écrire noir sur blanc dans le
  script pourquoi elle n'y est pas**, en indiquant le vrai filet de sécurité :
  la sauvegarde `sauvegardes/bd_gsb_avant_phase1.sql`.
- **À retenir :** les transactions protègent le **DML** (`INSERT`, `UPDATE`,
  `DELETE`), pas le **DDL**. C'est d'ailleurs pour cela que
  `majTypeVehiculeVisiteur()`, qui ne fait que des `UPDATE`, peut légitimement
  en utiliser une (§ 6.6).

### 7.7 Des liens symboliques là où il fallait des copies
- **Ce que je faisais :** réinstaller les assets après avoir modifié la feuille
  de style, avec `php app/console assets:install web --symlink`.
- **Symptôme :** aucune erreur — et c'est le problème. `web/bundles/pggsbfrais`
  est devenu un **lien symbolique** vers `src/Pg/GsbFraisBundle/Resources/public`.
- **Pourquoi c'est un problème ici :** le projet est sur un disque externe
  (`/Volumes/LaCie Data`). Un lien symbolique contient un chemin **absolu** :
  il casse si le disque est monté sous un autre nom, ou si le projet est copié
  sur une clé USB pour la soutenance. Le `README.md` documente par ailleurs la
  version sans `--symlink`.
- **Correction :** `rm -rf web/bundles/pggsbfrais` puis
  `php app/console assets:install web` (sans option) — copie réelle. Vérifié :
  les trois dossiers de `web/bundles/` sont redevenus de vrais répertoires.
- **Pour ne pas le refaire :** `--symlink` est pratique en développement local
  sur un disque interne ; sur support amovible ou avant une démonstration,
  toujours la copie.

### 7.8 J'ai réécrit sans le vouloir les fins de ligne de trois fichiers
- **Symptôme :** après le premier commit de la phase 1, `git show --stat`
  annonçait **946 lignes modifiées dans `PdoGsb.php`**, alors que je n'y avais
  ajouté qu'environ 190 lignes. Idem pour deux templates (61 et 59 lignes
  modifiées pour 3 lignes ajoutées).
- **Cause réelle :** les fichiers du prédécesseur datent de 2013 et ont été
  écrits sous Windows : leurs lignes se terminent par **CRLF** (`\r\n`). Mes
  scripts de modification ouvraient les fichiers en mode texte
  (`open(f, encoding='utf8')`), ce qui convertit silencieusement les CRLF en
  LF à la lecture ; à la réécriture, tout le fichier repartait en LF.
  Git voyait donc **chaque ligne comme modifiée**.
- **Pourquoi c'est grave ici :** ce n'est pas un problème de fonctionnement —
  PHP se moque des fins de ligne, et l'application marchait. C'est un problème
  de **relecture** : un diff de 946 lignes rend impossible de voir ce qui a
  réellement changé, pour moi comme pour un correcteur. Et cela réécrit le
  fichier du prédécesseur, ce que la consigne interdit.
- **Correction :** restauration des CRLF sur les trois fichiers concernés
  (`PdoGsb.php`, `listefraisforfait.html.twig`, `saisirfraisforfait.html.twig`),
  en mode binaire, puis `git commit --amend`. Le diff par rapport au code
  d'origine est retombé à **+218 / −8 lignes**, ce qui correspond exactement à
  mon travail. Contrôle effectué : les trois fichiers avaient bien 100 % de
  lignes CRLF à l'origine (379/379, 23/23, 26/26), la restauration est donc
  exacte et non approximative.
- **Pour ne pas le refaire :** ouvrir les fichiers en mode binaire (`'rb'` /
  `'wb'`) quand on les modifie par script, ou vérifier
  `git diff --stat` après chaque modification — un diff disproportionné par
  rapport au travail réel est le signal d'alerte.

### Phase 2

### 7.9 Le jeu de démonstration annonçait des justificatifs inexistants
- **Constat :** `fichefrais.nbJustificatifs` valait 4, 2, 3 et 1 dans le jeu de
  données, alors qu'aucun fichier n'existait — et pour cause, la fonctionnalité
  n'existait pas. Ces valeurs n'avaient jamais été calculées par du code
  (point B16 de l'audit) : elles avaient été écrites à la main.
- **Pourquoi c'est un problème :** une fois la phase 2 en place, l'écran aurait
  affiché « 4 justificatif(s) pris en compte dans le remboursement » sur une
  fiche n'en présentant aucun. Un compteur faux est pire qu'un compteur à zéro :
  le comptable prendrait sa décision de remboursement sur une information
  inventée.
- **Correction :** la migration 002 se termine par un `UPDATE` qui recalcule le
  compteur de **toutes** les fiches à partir des lignes réellement pourvues
  d'un fichier — donc 0 partout aujourd'hui. Le jeu de démonstration de
  `sql/bd_gsb.sql` a été aligné sur la même valeur.
- **C'est une modification de données existantes**, signalée en gros dans le
  script et non faite « en douce ». Alternative écartée : laisser les valeurs
  d'origine, pour ne pas toucher aux données — mais conserver un chiffre faux
  par respect de l'historique n'a aucun sens quand ce chiffre n'a jamais eu de
  signification.

### 7.10 Un commentaire qui annonçait l'inverse des données livrées
- **Ce que je faisais :** synchroniser `sql/bd_gsb.sql` avec la migration 002.
- **Ce que j'ai écrit :** un commentaire annonçant « nbJustificatifs vaut 0 :
  ce compteur désigne le nombre de lignes accompagnées d'un justificatif
  téléversé, et le jeu de démonstration n'en contient aucun »… **au-dessus d'un
  `INSERT` qui insérait toujours 4, 2, 3 et 1.**
- **Conséquence si je ne l'avais pas vu :** une base créée de zéro aurait
  reproduit exactement le défaut que la migration 002 venait de corriger sur la
  base existante. Les deux chemins d'installation auraient divergé.
- **Comment je m'en suis aperçu :** en relisant la sortie de ma propre
  vérification, qui affiche les lignes insérées. Le commentaire disait 0, les
  données disaient 4.
- **Correction :** les quatre valeurs passées à 0, puis re-création d'une base
  jetable depuis `bd_gsb.sql` pour confirmer que le résultat correspond bien à
  la base migrée.
- **Pour ne pas le refaire :** un commentaire n'est pas une preuve. Le seul
  contrôle qui vaille est de **rejouer le script et de regarder le résultat** —
  c'est ce que fait la vérification de la décision 1.7, que j'ai réutilisée ici.

### 7.11 Peu d'erreurs en phase 2 — et pourquoi je le dis
Contrairement à la phase 1, cette phase n'a pas produit d'erreur d'exécution :
les tests de téléversement, de contrôle d'accès et de suppression sont passés
du premier coup. Je le signale plutôt que de gonfler cette section, mais je note
les deux pièges que j'ai **évités par conception**, et qui auraient chacun
produit un défaut silencieux :
- **les fichiers orphelins** : supprimer une ligne de frais sans effacer son
  fichier ne provoque aucune erreur visible ; le dossier grossit simplement
  avec des factures que plus personne ne peut consulter. Traité par la
  décision 2.9.
- **`$this->getRequest()`** : cette méthode, omniprésente dans les exemples en
  ligne, est **dépréciée depuis Symfony 2.4**. J'ai utilisé
  `$this->get('request_stack')->getCurrentRequest()`. Sur un projet en 2.8, il
  faut se méfier des exemples écrits pour les versions 2.0 à 2.3.

### Phase 3

### 7.12 L'installation de dompdf casse un script de Composer
- **Message exact :**
  `[TypeError] Argument 1 passed to Symfony\Component\Process\Process::__construct()`
  `must be of the type array, string given`, dans
  `sensio/distribution-bundle/.../Composer/ScriptHandler.php:342`.
- **Ce que je faisais :** `composer require dompdf/dompdf:^2.0`.
- **Cause réelle — et elle n'a rien à voir avec dompdf.** Le paquet
  `sensio/distribution-bundle` (2016) contient un script exécuté après chaque
  installation, qui écrit `new Process('php ...')` avec une **chaîne**. Le
  Composer installé aujourd'hui embarque Symfony Process 5, où le constructeur
  exige un **tableau**. N'importe quel `composer require` sur ce projet
  déclenche la même erreur.
- **Portée réelle :** seul `buildBootstrap` échoue — la régénération de
  `app/bootstrap.php.cache`. Ce fichier existe déjà et reste valide puisque la
  version de Symfony n'a pas bougé. Les scripts suivants (`clearCache`,
  `installAssets`) n'ont pas été exécutés, d'où leur lancement à la main.
- **Vérifications faites avant de conclure :** dompdf bien présent dans
  `vendor/`, classe `Dompdf\Dompdf` instanciable, `php app/console --version`
  répond, cache vidé et assets réinstallés sans erreur, application testée.
- **Pour ne pas s'y tromper :** un script post-installation en échec n'est pas
  forcément une installation en échec. Il faut lire **quel** script échoue et
  ce qu'il produit, au lieu de conclure que le paquet est cassé.

### 7.13 `--dry-run` de Composer modifie quand même `composer.json`
- **Ce que je faisais :** `composer require --dry-run dompdf/dompdf:^2.0`, pour
  voir ce qui serait installé **sans rien installer**, avant de te demander ton
  accord.
- **Symptôme :** la première ligne de sortie est
  `./composer.json has been updated`.
- **Cause réelle :** `require --dry-run` écrit la dépendance dans
  `composer.json` pour pouvoir résoudre le graphe, puis restaure le fichier à
  la fin. La simulation ne porte que sur l'installation dans `vendor/`.
- **Vérification :** `git status` était propre après coup, et `vendor/dompdf`
  absent — la restauration avait bien eu lieu.
- **Ce que j'en retiens :** avoir un dépôt Git a permis de **prouver en une
  commande** que rien n'avait été laissé derrière. Sans historique, il aurait
  fallu me fier au message de l'outil.

### 7.14 Deux `grep` faux, pour la deuxième fois
- **Symptôme :** mes tests W1 et T7 semblaient échouer alors que le code était
  correct. Pour W1, je cherchais
  `<a href="/fichepdf/…" …>…</a>` **sur une seule ligne**, alors que
  l'attribut `target="_blank"` est sur la ligne suivante du gabarit.
- **Cause réelle :** `grep` travaille ligne par ligne ; le HTML, non.
- **Correction :** compter la présence du **texte du lien**
  (`grep -c 'Imprimer cette fiche'`) plutôt que de reconstituer une balise.
- **C'est la deuxième fois** (voir § 7.5, l'apostrophe échappée par Twig). La
  règle que j'aurais dû appliquer plus tôt : **ne jamais tester du HTML avec
  une expression régulière qui suppose une mise en forme.** Chercher un texte
  visible ou une classe CSS.

### 7.15 `createAccessDeniedException()` renvoie 401 et non 403
- **Symptôme :** un visiteur non comptable demandant `/fichespdf/202608`
  recevait **HTTP 401**, alors que j'attendais 403.
- **Cause réelle :** `createAccessDeniedException()` lève une
  `AccessDeniedException` que le composant **Security** de Symfony intercepte.
  Son comportement normal est de déclencher le point d'entrée
  d'authentification — « identifiez-vous », donc 401. Or ce projet n'utilise
  pas le composant Security (décision 0.4) : il n'y a pas de pare-feu
  configuré, et surtout **l'utilisateur est déjà authentifié**. Il ne lui
  manque qu'un rôle.
- **Correction :** construire la réponse à la main,
  `new Response($message, Response::HTTP_FORBIDDEN)`. Vérifié : HTTP 403.
- **Ce que ça illustre :** les raccourcis du contrôleur Symfony supposent que
  l'on utilise tout le cadre. Dès qu'on n'en utilise qu'une partie — ici
  l'authentification faite main — certains d'entre eux se comportent de façon
  inattendue. C'est le prix, connu et assumé, de la décision 0.4.

---

## 8. Ce qui ne fonctionne pas / limites / points bloquants

Classés par criticité pour la suite du projet.

### 🔴 B1 *(état à l'audit — levé en phase 1)* — Aucun calcul de montant n'existe dans l'application
`fraisforfait.montant` n'est jamais lu (§ 3.3). Aucun total forfait, aucun total
hors forfait, aucun total général n'est calculé ni affiché.
**Impact :** phases 1 et 3. La phase 1 devra créer le calcul, pas le corriger.

### ✅ B1 / B2 — LEVÉS en phase 1
**B1** (aucun calcul de montant) : `getLesFraisForfait()` renvoie désormais le
montant unitaire et le total de chaque ligne, calculés en SQL, et les écrans de
saisie et de consultation les affichent. Le **total général** de la fiche reste
à faire — c'est la phase 3 (décision 1.6).
**B2** (pas de prix unitaire sur la ligne) : la colonne
`lignefraisforfait.montantUnitaire` existe, est `NOT NULL`, et est alimentée à
la création de chaque ligne. Vérifié sur les données réelles (§ 9.6).

### Limites propres à la phase 1

#### 🟠 L1 — Un visiteur ne peut pas « annuler » sa déclaration de véhicule
L'écran propose les 6 véhicules du barème, mais aucune option « non renseigné ».
Une fois un véhicule déclaré, le visiteur peut en changer mais pas revenir à
NULL. Ce n'est pas demandé par le cahier des charges, et la couche d'accès aux
données sait déjà le gérer (`majTypeVehiculeVisiteur(null)` retomberait sur le
tarif historique grâce au `coalesce`). **Dette assumée, 5 minutes de travail si
tu la veux.**

#### 🟠 L2 — Le comptable ne peut pas renseigner le véhicule d'un visiteur
Le cahier des charges dit « le visiteur **(ou le comptable)** ». Je n'ai
implémenté que le visiteur, pour une raison simple : **le comptable n'existe pas
encore** — ni compte, ni rôle, ni authentification (point B3). Construire un
écran comptable maintenant obligerait à inventer un contrôle d'accès provisoire
qu'il faudrait jeter en phase 5. L'écran « véhicule » côté comptable sera donc
ajouté en **phase 5**, quand le rôle existera. *C'est une fonctionnalité
partielle, je ne la présente pas comme terminée.*

#### 🟠 L3 — Aucune trace de l'historique des véhicules
On sait quel véhicule un visiteur a **aujourd'hui**, pas quel véhicule il avait
en juin. L'information n'est pas perdue pour le remboursement — le tarif, lui,
est historisé sur chaque ligne — mais on ne peut pas répondre à « quel véhicule
conduisait-il en juin ? ». Le cahier des charges ne le demande pas.

#### 🟡 L4 — Le barème n'est modifiable qu'en SQL
La table `typevehicule` n'a pas d'écran d'administration : changer un tarif se
fait par un `UPDATE`. Le cahier des charges demande que le barème soit **stocké
en base** (fait), pas qu'il soit administrable par écran. À signaler comme
évolution possible.

#### 🟡 L5 — Le script `001` n'est pas rejouable
MySQL ne connaît pas `ADD COLUMN IF NOT EXISTS`. Un second passage s'arrête sur
l'erreur 1060 « Duplicate column name » **sans rien abîmer**, mais il n'existe
pas de table de suivi des migrations qui empêcherait de le relancer par erreur.
C'est le prix de l'absence de Doctrine Migrations (décision 0.2).

#### 🟡 L6 — Données de test laissées en base
Mes tests ont modifié `bd_gsb` : `a17` (dandre) a désormais le véhicule **E56**,
`a55` (cbedos) le véhicule **D7**, une fiche 202609 a été créée pour `a55`, et
un frais hors forfait « Peage A6 » (18,50 €) a été ajouté à la fiche 202609 de
`a17`. **Je ne les ai pas supprimés** : ce sont des `DELETE`, et la règle est de
ne rien détruire sans ton accord. Dis-le-moi si tu veux repartir de l'état
initial — la sauvegarde `sauvegardes/bd_gsb_avant_phase1.sql` le permet, mais
elle est **antérieure à la migration 001** : il faudrait la restaurer puis
rejouer `sql/001_puissance_fiscale_vehicule.sql`.

### ✅ B16 — LEVÉ en phase 2
`fichefrais.nbJustificatifs` est désormais alimenté, et recalculé à partir des
données réelles à chaque dépôt et à chaque suppression. Il est affiché dans la
légende des tableaux hors forfait, en saisie comme en consultation.

### Limites propres à la phase 2

#### 🔴 L7 — Le comptable ne peut pas encore consulter les justificatifs
La **règle d'accès** est écrite et en place : `consulterAction()` autorise le
propriétaire du frais **ou** un comptable. Mais `estComptable()` renvoie faux
pour tout le monde, puisqu'aucun compte comptable n'existe (point B3). **Le
mécanisme est livré, l'acteur ne l'est pas.** La consultation côté comptable
fonctionnera dès la phase 5, sans modification de `JustificatifController`.
*Ce point du sujet est donc partiellement fait, et je ne le présente pas
autrement.* Il n'est pas non plus testable en l'état : la branche « comptable »
du contrôle d'accès sera exercée en phase 5.

#### 🟠 L8 — Plafond de 2 Mo, sans doute insuffisant en pratique
Une photo de facture prise au téléphone dépasse fréquemment 2 Mo. La limite
vient de `upload_max_filesize` du poste ; la relever demande de modifier
`php.ini` (`upload_max_filesize` **et** `post_max_size`) **puis**
`justificatifs.taille_max` dans `services.yml`. **Décision à prendre** — je ne
modifie pas la configuration PHP du poste sans ton accord.

#### 🟠 L9 — Un seul justificatif par ligne de frais
Une dépense justifiée par deux documents (facture + ticket) ne peut pas être
couverte. Le cahier des charges parle d'« une facture » par dépense, la
relation retenue est donc 1 pour 1. Passer à plusieurs pièces demanderait une
table `justificatif` séparée — structure connue, mais non demandée.

#### 🟡 L10 — Aucune protection CSRF sur les formulaires d'envoi
Comme tous les formulaires de l'application (point B9), celui du justificatif
n'a pas de jeton anti-CSRF. Un site malveillant pourrait théoriquement faire
déposer un fichier par un visiteur connecté. L'impact reste limité — il faudrait
connaître l'identifiant d'une ligne de frais appartenant à la victime — mais
**c'est une dette technique réelle**, héritée du choix de ne pas utiliser le
composant Form de Symfony.

#### 🟡 L11 — Pas d'analyse antivirus des fichiers déposés
Les fichiers sont contrôlés en extension, en type réel et en taille, mais leur
contenu n'est pas analysé. Un PDF peut contenir du code malveillant qui ne
s'exécutera qu'à l'ouverture, chez le comptable. Une analyse (ClamAV par
exemple) serait la réponse en production ; c'est hors du périmètre de l'AP et
cela ajouterait une dépendance système.

#### 🟡 L12 — Les fichiers ne sont pas sauvegardés avec la base
`mysqldump` sauvegarde la base, pas `app/justificatifs/`. Restaurer une
sauvegarde sans le dossier laisserait des lignes référençant des fichiers
absents (le contrôleur répond alors 404 avec un message explicite). Toute
procédure de sauvegarde doit donc emporter **les deux**.

### ✅ B7 — LEVÉ en phase 3
Le générateur de données aléatoires annoncé par le cahier des charges et absent
de l'archive existe désormais : `php app/console gsb:generer-donnees`.

### ✅ B15 — TRANCHÉ en phase 3
La fiche imprime l'identifiant du visiteur (`a17`) faute de spécification du
matricule. Décision 3.3, validée par le client.

### Limites propres à la phase 3

#### 🔴 L13 — Le comptable ne peut pas encore lancer la génération de masse
Comme en phases 1 et 2 : la route `/fichespdf/{mois}` existe, elle est réservée
au comptable, et `estComptable()` renvoie faux tant qu'aucun compte comptable
n'existe. **En attendant, la fonctionnalité est pleinement utilisable par la
commande `gsb:imprimer-fiches`**, qui appelle exactement le même service — ce
n'est donc pas une fonctionnalité manquante, mais une fonctionnalité sans
bouton. Le bouton arrivera avec l'écran comptable, en phase 5.

#### 🟠 L14 — La génération de masse est un N+1 assumé
3 requêtes par fiche, soit 607 requêtes pour 202 fiches. Une requête unique
ramenant toutes les lignes de toutes les fiches réduirait ce nombre — mais
remettrait en mémoire exactement ce que la conception cherche à éviter
(§ 6.12). **Arbitrage entre nombre de requêtes et empreinte mémoire, tranché en
faveur de la mémoire**, parce que c'est la contrainte que le sujet met en avant.

#### 🟠 L15 — La mémoire n'est pas parfaitement constante
Elle croît d'environ **40 Ko par fiche**, non pas à cause des PDF (libérés à
chaque tour) mais des entrées que `ZipArchive` conserve jusqu'au `close()`.
Pour 202 fiches, cela représente 8 Mo. Extrapolé à 5 000 fiches, environ
200 Mo : toujours gérable, mais **ce n'est pas illimité**. Si le besoin
apparaissait, il faudrait produire une archive par lots de quelques centaines.
`ZipArchive` n'ouvre les fichiers temporaires qu'au `close()`, ce qui pose par
ailleurs la question du nombre de descripteurs de fichiers ouverts sur de très
gros volumes. *Je préfère donner ce chiffre plutôt que d'affirmer « la mémoire
est constante », ce qui serait faux.*

#### 🟠 L16 — 47 alertes de sécurité sur les dépendances existantes
`composer audit` signale 31 avis pour `symfony/symfony` 2.8, 14 pour `twig/twig`
et 1 pour `swiftmailer`. **Aucune ne concerne dompdf.** Symfony 2.8 n'est plus
maintenu depuis 2018 ; le sujet impose cette version. Rien à corriger dans le
cadre de l'AP, mais **c'est à connaître pour l'oral** : un jury peut demander
si le projet est à jour. La réponse honnête est non, et pourquoi.

#### 🟡 L17 — Durée de la génération de masse
49 secondes pour 202 fiches (~0,24 s par fiche), soit environ 4 minutes pour
1 000 fiches. Par le web, cela dépasserait le délai d'expiration habituel d'un
serveur — d'où l'intérêt de la commande console. Le futur bouton comptable
devra soit se limiter à des volumes raisonnables, soit déclencher un traitement
en arrière-plan.

#### 🟡 L18 — Le PDF n'est pas mis en cache
Chaque demande regénère le document. Pour une fiche clôturée — dont le contenu
ne changera plus — on pourrait conserver le PDF produit. Non demandé, et cela
introduirait la question de l'invalidation du cache.

#### 🟡 L19 — Données de test laissées en base
`gsb:generer-donnees` a créé **200 visiteurs `z001` à `z200`** avec une fiche
clôturée en 202608, pour la mesure de volumétrie. Ils sont toujours là. Pour
les retirer, **dans cet ordre** (`fichefrais` référence `visiteur` sans
`ON DELETE CASCADE`) :

```sql
DELETE FROM fichefrais WHERE idVisiteur LIKE 'z%';  -- les lignes suivent
DELETE FROM visiteur   WHERE id LIKE 'z%';
```

Je ne les supprime pas de moi-même : ce sont des `DELETE`, et la règle est de
ne rien détruire sans ton accord. Une seule commande les recrée si besoin.

### 🔴 B2 *(état à l'audit — levé en phase 1)* — `lignefraisforfait` ne stocke que la quantité
Pas de prix unitaire sur la ligne. Le montant d'une ligne ne peut être obtenu
qu'en joignant `fraisforfait` **à l'instant de la lecture**. Donc si on change
le barème demain, toutes les fiches passées changent de montant.
**Impact :** phase 1 — c'est exactement ce que le cahier des charges interdit
(« pas de recalcul rétroactif »). Il faudra **historiser** le tarif appliqué sur
la ligne (colonne supplémentaire, valeur figée à la saisie).

### 🔴 B3 — Aucune notion de comptable, aucun rôle
La table `visiteur` est la seule table d'utilisateurs ; la session ne porte que
`id`, `nom`, `prenom`. Le firewall Symfony est anonyme.
**Impact :** phases 4 et 5.

### 🔴 B4 — Le cycle d'état de la base ne correspond pas à celui demandé
La phase 5 demande 5 états : Créée → Clôturée → **Validée** → **Mise en
paiement** → Remboursée. La table `etat` n'en contient que 4, et son `VA` est
libellé « Validée **et** mise en paiement » : deux étapes du cahier des charges
sont fusionnées en une seule ligne.
**✔ Confirmé par le cahier des charges** (§ 4.3) : son diagramme d'états montre
sans ambiguïté 5 états, « Validée » et « Mise en paiement » étant séparés par
une transition « /Mettre en paiement » déclenchée le 20 du mois.
**Impact :** phase 5. Il faudra ajouter un 5ᵉ état (`MP`) et re-libeller `VA` en
« Validée ». **Je ne le ferai pas sans ton accord**, parce que `VA` est déjà
porté par une donnée existante (fiche `a17`/202607) et par
`getLesFichesValidees()` : c'est une modification de **données**, pas seulement
de schéma.

### 🟠 B5 — `sql/bd_gsb.sql` est destructeur
Le script commence par `DROP DATABASE IF EXISTS bd_gsb`. Le relancer efface tout.
**Impact :** toutes les phases. Les scripts que je livrerai (`sql/001_…`) seront
**incrémentaux** (`ALTER TABLE`, `INSERT`), jamais destructeurs, et je ne
lancerai jamais `bd_gsb.sql` sans te le demander.

### 🟠 B6 — Le projet n'est pas sous gestion de version
`gsbProjet/` contient un `.gitignore` mais **aucun dépôt Git** (`.git` absent).
Aucun historique, aucun retour arrière possible, et je ne peux pas te montrer un
`diff` propre de ce que je modifie.
**Impact :** toutes les phases, et la soutenance (un jury apprécie un historique
de commits). **Proposition au § 10.**

### 🟠 B7 — Le script de génération de données aléatoires est absent
L'énoncé indique que le prédécesseur en a livré un. Recherche exhaustive dans
`gsbSymfony/` et dans `codeGsbSymfony.zip` : **il n'existe pas**. Le seul
fichier SQL est `sql/bd_gsb.sql`, avec un jeu de données fixe et réduit
(5 fiches, 1 visiteur réellement alimenté).
**Impact :** phase 3 (« volumétrie : beaucoup de fiches dans le mois ») — je ne
pourrai pas démontrer le comportement sur 200 fiches sans générateur. Je propose
d'en écrire un en phase 3, sous forme de commande console.

### 🟠 B8 — Mots de passe stockés en clair
`visiteur.mdp VARCHAR(30)` contient `oppg5`, `gthe8`… et la requête
d'authentification compare en SQL (`where login=:log and mdp=:md`).
**Impact :** sécurité. C'est conforme au sujet d'origine, donc je ne le change
pas sans ton accord, mais **un jury posera la question** : la réponse est
préparée au § 11. Si tu veux, la phase 5 est le bon moment pour passer à
`password_hash()` (le comptable serait alors créé directement en haché).

### 🟡 B9 — Pas de protection CSRF sur les formulaires
Les formulaires sont écrits à la main en Twig, sans jeton. Le composant Form de
Symfony (qui en poserait un automatiquement) n'est pas utilisé.
**Impact :** phases 2 et 5 (suppression et validation sont des actions
sensibles). À signaler à l'oral comme dette technique assumée.

### 🟡 B10 — `supprimerfraishorsforfait` est accessible en GET
Une action destructrice déclenchée par un simple lien. Le contrôle
d'appartenance (`estValideSuppressionFrais`) est bien fait — donc pas de
suppression du frais d'autrui — mais la méthode HTTP n'est pas la bonne.
**Impact :** phase 5, où le refus de ligne hors forfait fait exactement la même
opération. J'appliquerai POST sur la nouvelle action comptable et je te le
signalerai.

### 🟡 B11 — Code mort laissé en place
`src/Pg/GsbFraisBundle/Controller/include/class.pdogsb.inc.php` est l'ancienne
version de la classe (singleton `getPdoGsb()`), **jamais chargée** — c'est
`services/PdoGsb.php` qui est utilisée. Idem pour `src/AppBundle/` et les
dossiers `nbproject/`. Je ne supprime rien (règle n°2) mais attention à ne pas
modifier le mauvais fichier : **le fichier vivant est `services/PdoGsb.php`**.

### 🟡 B12 — `upload_max_filesize = 2 Mo`
Une photo de facture prise au téléphone dépasse souvent 2 Mo.
**Impact :** phase 2. Soit on fixe la taille max à 2 Mo côté application (et on
l'assume), soit on relève la valeur dans `php.ini`. À décider en phase 2.

### ✅ B13 — LEVÉ : le cahier des charges a été fourni
À l'audit initial, seuls `gsbSymfonyV1.0.pdf` / `.docx` étaient présents — le
**support pédagogique CERTA** (Patrice Grand, février 2013), pas le cahier des
charges du client. Le document `ap-gsb-web.pdf` a depuis été fourni : il
contient l'annexe 1 (maquette de la fiche) et l'annexe 2 (cas d'utilisation).
Dépouillé au § 4. **Ce point n'est plus bloquant.**

### 🟠 B15 — Le matricule de l'annexe 1 n'existe pas en base
Voir écart E1 (§ 4.6) : l'annexe imprime `NRD/125-01`, la base ne connaît que
`a17`. **Impact :** phase 3 — cosmétique, mais visible sur le document produit.

### 🟡 B16 — Le nombre de justificatifs n'est jamais alimenté
Le cahier des charges exige que « le système indique le nombre de justificatifs
pris en compte dans le remboursement ». La colonne `fichefrais.nbJustificatifs`
existe, la méthode `PdoGsb::majNbJustificatifs()` existe — mais **aucun
contrôleur ne l'appelle** : la valeur reste figée à celle du jeu de données.
**Impact :** phase 2, où l'ajout d'un justificatif devra l'alimenter.

### 🟡 B14 — Incohérences mineures de documentation
- `README.md` documente la route `pg_gsb_frais_validerfraishorsforfait` alors
  que son vrai nom contient une faute (`…forfais`).
- `PdoGsb::$monPdo` est `private static` alors qu'une seule instance existe
  (service Symfony), et `__destruct()` la met à `null` : sans conséquence ici,
  mais c'est un vestige du singleton d'origine (B11).

---

## 9. Comment tester (état actuel, avant toute modification)

Procédure vérifiée le jour de l'audit. **Tout est déjà installé et fonctionnel.**

### 9.1 Pré-requis à vérifier

```bash
php -v                 # doit afficher 7.4.x
mysql -u root -e "select version();"   # doit répondre (9.5.0 ici)
```

### 9.2 Lancer l'application

```bash
cd "/Volumes/LaCie Data/bts sio 2/gsbSymfony/gsbProjet"
php -S 127.0.0.1:8000 -t web web/app_dev.php
```
Puis ouvrir <http://127.0.0.1:8000/accueil>.

### 9.3 Scénarios et résultats attendus

| # | Action | Jeu de données | Résultat attendu |
|---|--------|----------------|------------------|
| T1 | Ouvrir `/accueil` sans être connecté | — | Formulaire « Identification utilisateur » |
| T2 | Se connecter | `dandre` / `oppg5` | Redirection vers `/accueil`, menu + « Visiteur : bonjour David Andre » |
| T3 | Se connecter avec un mauvais mot de passe | `dandre` / `xxx` | Bandeau rouge « Erreur de login ou de mot de passe » |
| T4 | Menu « Mes fiches de frais » | connecté `dandre` | Liste déroulante des 4 mois (09/2026 sélectionné), fiche affichée avec état et montant validé |
| T5 | Choisir 06/2026 et valider | — | « Etat : Remboursée », montant validé 1250.40, 4 justificatifs |
| T6 | Menu « Saisie fiche de frais » | connecté `dandre` | Formulaire des 4 frais forfaitisés du mois courant |
| T7 | Saisir une quantité non numérique | `abc` dans « Repas Restaurant » | Bandeau « Les valeurs des frais doivent être numériques », rien n'est enregistré |
| T8 | Ajouter un frais hors forfait | date `01/09/2026`, libellé `Péage A6`, montant `18.50` | La ligne apparaît dans le tableau hors forfait |
| T9 | Ajouter un frais avec une date vieille de plus d'un an | `01/01/2020` | « date d'enregistrement du frais dépassé de plus de 1 an » |
| T10 | Supprimer un frais hors forfait | lien « Supprimer ce frais » | Confirmation JavaScript, puis la ligne disparaît |
| T11 | Contrôle d'accès | se déconnecter puis appeler `/listefrais` | Redirection vers `/accueil` |
| T12 | Contrôle d'appartenance | connecté `cbedos`, appeler `/supprimerfraishorsforfait/1` (frais de `dandre`) | **Erreur 404** « Frais hors forfait introuvable », le frais de `dandre` est intact |

### 9.4 Résultat du test de fumée automatisé (audit)

| Requête | Réponse obtenue |
|---------|-----------------|
| `GET /accueil` | `HTTP/1.1 200 OK` |
| `POST /validerconnexion` (`dandre`/`oppg5`) | `HTTP/1.1 302 Found` + cookie de session |
| `GET /listefrais` (avec session) | HTML contenant « Fiche de frais du mois 09-2026 », « Etat : Fiche créée, saisie en cours depuis le 16/09/2026 », « Montant validé : 0.00 » |

➡️ **L'application est opérationnelle en local. Aucune installation n'est
nécessaire pour démarrer la phase 1.**

### 9.5 Phase 1 — installation de la migration

À faire **une seule fois**, sur une base déjà créée :

```bash
cd "/Volumes/LaCie Data/bts sio 2/gsbSymfony/gsbProjet"
mysqldump -u root --databases bd_gsb --add-drop-database=FALSE > sauvegardes/bd_gsb_avant_001.sql
mysql -u root bd_gsb < sql/001_puissance_fiscale_vehicule.sql
php app/console assets:install web
php app/console cache:clear --env=dev
```

Sur une base créée de zéro, `sql/bd_gsb.sql` contient déjà tout : rien à faire.

### 9.6 Phase 1 — scénarios de test

Se connecter avec `dandre` / `oppg5`.

| # | Action | Résultat attendu |
|---|--------|------------------|
| V1 | Menu « Mon véhicule » | Message « Vous n'avez pas encore déclaré votre véhicule… tarif historique de **0.62 € / km** », et une liste des 6 véhicules avec leur tarif |
| V2 | Choisir « 5/6 CV Essence — 0.67 €/km », Valider | « Votre véhicule a bien été enregistré. » puis « Véhicule déclaré : **5/6 CV Essence** — remboursement à **0.67 € / km** » |
| V3 | Menu « Saisie fiche de frais » | En face de « Frais Kilométrique » s'affiche **(0.67 €)**, et **(110.00 €)**, **(80.00 €)**, **(25.00 €)** pour les trois autres |
| V4 | Menu « Mes fiches de frais », choisir le mois en cours | Le tableau des éléments forfaitisés comporte 4 lignes : libellé, quantité, montant unitaire, total |
| **V5** | **Choisir le mois 08/2026 (fiche clôturée)** | **Le montant unitaire kilométrique y est resté à 0.62 €** — la fiche n'a pas été recalculée. **C'est LE test de la phase 1.** |
| V6 | Choisir 07/2026 (validée) puis 06/2026 (remboursée) | Même chose : 0.62 €, inchangé |
| V7 | Revenir sur « Mon véhicule », choisir « 7 CV et + Diesel », Valider | Le mois en cours passe à 0.65 €, les mois clôturés restent à 0.62 € |

**Vérification directe en base (la preuve la plus parlante à l'oral) :**

```sql
SELECT lff.mois, ff.idEtat AS etat, lff.quantite, lff.montantUnitaire,
       lff.quantite * lff.montantUnitaire AS total
  FROM lignefraisforfait lff
 INNER JOIN fichefrais ff ON ff.idVisiteur = lff.idVisiteur AND ff.mois = lff.mois
 WHERE lff.idVisiteur = 'a17' AND lff.idFraisForfait = 'KM'
 ORDER BY lff.mois;
```

Résultat obtenu après avoir déclaré un 5/6 CV Essence :

| mois | etat | quantite | montantUnitaire | total |
|------|------|----------|-----------------|-------|
| 202606 | RB | 480 | **0.62** | 297.60 |
| 202607 | VA | 310 | **0.62** | 192.20 |
| 202608 | CL | 225 | **0.62** | 139.50 |
| 202609 | CR | 300 | **0.67** | 201.00 |

➡️ Seule la fiche en cours de saisie (`CR`) a suivi le nouveau tarif. Les trois
fiches terminées sont intactes.

### 9.7 Phase 1 — cas d'erreur et sécurité

| # | Action | Résultat attendu |
|---|--------|------------------|
| V8 | Envoyer un type de véhicule inexistant (hors navigateur) : `curl -b cookies -d "idTypeVehicule=ZZZ" http://127.0.0.1:8000/validermonvehicule` | Page réaffichée avec le bandeau rouge « Ce type de véhicule n'existe pas dans le barème ». **Rien n'est écrit en base.** Vérifiable par `SELECT idTypeVehicule FROM visiteur WHERE id='a17';` |
| V9 | Appeler `/monvehicule` sans être connecté | Redirection 302 vers `/accueil` |
| V10 | Appeler `/validermonvehicule` en GET | Erreur 404 (la route n'accepte que POST) |

### 9.8 Phase 1 — non-régression (vérifiée)

Tous les scénarios de la phase 0 repassent après modification :

| # | Vérification | Résultat |
|---|--------------|----------|
| R1 | Mauvais mot de passe | Message d'erreur affiché ✔ |
| R2 | Saisie de quantités valides | Valeurs enregistrées et réaffichées ✔ |
| R3 | Quantité non numérique | « Les valeurs des frais doivent être numériques » ✔ |
| R4 | Ajout d'un frais hors forfait | Ligne créée ✔ |
| R5 | Frais daté de plus d'un an | « dépassé de plus de 1 an » ✔ |
| R6 | `/listefrais` sans session | Redirection 302 vers `/accueil` ✔ |
| R7 | Suppression du frais d'un autre visiteur | HTTP 404 ✔ |

### 9.9 Phase 2 — installation

```bash
cd "/Volumes/LaCie Data/bts sio 2/gsbSymfony/gsbProjet"
mysqldump -u root --databases bd_gsb --add-drop-database=FALSE > sauvegardes/bd_gsb_avant_002.sql
mysql -u root bd_gsb < sql/002_justificatifs.sql
php app/console assets:install web
php app/console cache:clear --env=dev
```

Le dossier `app/justificatifs/` est créé automatiquement au premier dépôt. Il
doit être **accessible en écriture** par l'utilisateur qui fait tourner PHP.

### 9.10 Phase 2 — scénarios de test

Préparer quatre fichiers : une vraie facture PDF, une image PNG ou JPG, un
fichier texte `note.txt`, et un PDF que l'on renomme en `.png`.
Se connecter avec `dandre` / `oppg5`, aller dans « Saisie fiche de frais ».

| # | Action | Résultat attendu |
|---|--------|------------------|
| T1 | Ajouter un frais hors forfait **sans** fichier | La ligne apparaît, colonne Justificatif : *aucun*, avec un bouton « Envoyer » |
| T2 | Sur cette ligne, choisir le PDF et cliquer « Envoyer » | Le nom du fichier devient un lien, le bouton devient « Remplacer », et la légende du tableau passe à « 1 justificatif(s) pris en compte dans le remboursement » |
| T3 | Cliquer sur le lien | Le PDF s'ouvre dans un nouvel onglet (il **ne se télécharge pas** : la réponse est envoyée en `inline`) |
| T4 | Remplacer par l'image PNG | Le lien porte le nouveau nom ; **l'ancien fichier a disparu du disque** (`ls app/justificatifs/`) |
| T5 | Créer un frais **avec** un justificatif en une seule fois | La ligne est créée avec son lien, le compteur passe à 2 |
| T6 | Menu « Mes fiches de frais », mois en cours | Colonne Justificatif présente, mêmes liens, même compteur |
| T7 | Supprimer un frais qui porte un justificatif | La ligne disparaît, **le fichier disparaît du disque**, le compteur redescend |

### 9.11 Phase 2 — cas d'erreur (contrôles de validité)

| # | Fichier envoyé | Message attendu |
|---|----------------|-----------------|
| T8 | `note.txt` | « Extension non autorisée : seuls les fichiers pdf, jpg, jpeg, png sont acceptés » |
| T9 | Un PDF renommé en `.png` | « Le contenu du fichier ne correspond pas à son extension .png » |
| T10 | Un fichier de 3 Mo | « Le transfert du fichier a échoué : The file … exceeds your upload_max_filesize ini directive » |

**Après chacun de ces trois refus, vérifier que le justificatif précédent est
toujours en place** (ligne inchangée en base, fichier toujours sur le disque).
C'est le test de la décision 2.9 sur l'ordre des opérations.

### 9.12 Phase 2 — contrôle d'accès (le plus important)

Ces tests se font hors du navigateur, car il s'agit de vérifier ce qui se passe
quand on ne se contente pas de cliquer sur les liens affichés.

```bash
# se connecter en tant que dandre et conserver sa session
curl -s -o /dev/null -c ck_dandre.txt -d "login=dandre&mdp=oppg5" \
     http://127.0.0.1:8000/validerconnexion
# se connecter en tant que cbedos
curl -s -o /dev/null -c ck_cbedos.txt -d "login=cbedos&mdp=gthe8" \
     http://127.0.0.1:8000/validerconnexion
```

| # | Action | Attendu | Obtenu |
|---|--------|---------|--------|
| T11 | `curl -b ck_dandre.txt http://127.0.0.1:8000/justificatif/7` | 200, `Content-Type: application/pdf`, `Content-Disposition: inline; filename="facture.pdf"` | ✔ |
| T12 | **`curl -b ck_cbedos.txt http://127.0.0.1:8000/justificatif/7`** | **404** — un visiteur ne voit pas le justificatif d'un autre | ✔ |
| T13 | `curl http://127.0.0.1:8000/justificatif/7` (sans session) | 302 vers `/accueil` | ✔ |
| T14 | `curl -b ck_cbedos.txt -F "justificatif=@f.pdf" http://127.0.0.1:8000/joindrejustificatif/7` | **404** — on ne joint pas un fichier au frais d'un autre | ✔ |
| T15 | `curl -b ck_dandre.txt http://127.0.0.1:8000/justificatif/999999` | 404 — **le même code que T12**, donc rien n'est révélé sur l'existence du document | ✔ |

**Vérification du stockage :** `ls app/justificatifs/` montre des noms du type
`6a187e57e45151de7e6542b603764f63.png` — 32 caractères hexadécimaux. Le dossier
est en dehors de `web/` : essayer `http://127.0.0.1:8000/justificatifs/…` ou
`http://127.0.0.1:8000/app/justificatifs/…` donne **404**, le serveur web ne
sert que `web/`.

### 9.13 Phase 2 — non-régression (vérifiée)

| # | Vérification | Résultat |
|---|--------------|----------|
| R2 | Saisie de quantités forfaitisées | Valeur enregistrée et réaffichée ✔ |
| R3 | Quantité non numérique | Message d'erreur ✔ |
| R5 | Frais daté de plus d'un an | Message d'erreur ✔ |
| P1 | Tarif kilométrique du véhicule déclaré | 0.67 € affiché en saisie ✔ |
| P1 | Fiche clôturée 202608 | Toujours à 0.62 € — **l'historisation de la phase 1 tient** ✔ |

### 9.14 Phase 3 — installation

```bash
cd "/Volumes/LaCie Data/bts sio 2/gsbSymfony/gsbProjet"
composer install          # dompdf est déjà dans composer.lock
php app/console cache:clear --env=dev
```

> Le script post-installation de Composer s'arrête sur une erreur
> `Process::__construct() must be of the type array` : **c'est normal et sans
> conséquence sur ce projet** (voir § 7.12). Lancer ensuite à la main
> `php app/console cache:clear --env=dev` et `php app/console assets:install web`.

Aucune migration de base en phase 3 : l'impression ne fait que lire.

### 9.15 Phase 3 — impression d'une fiche

Se connecter avec `dandre` / `oppg5`, menu « Mes fiches de frais ».

| # | Action | Résultat attendu |
|---|--------|------------------|
| I1 | Choisir 08/2026, cliquer « Imprimer cette fiche (PDF) » | Un PDF s'ouvre dans un nouvel onglet, nommé `fiche_a17_202608.pdf` |
| I2 | Vérifier l'en-tête | Logo GSB, titre « REMBOURSEMENT DE FRAIS ENGAGES », « Visiteur a17 David Andre », « Mois Août 2026 » |
| I3 | Vérifier le tableau forfaitisé | 4 colonnes : Frais Forfaitaires, Quantité, Montant unitaire, Total |
| **I4** | **Regarder la ligne « Frais Kilométrique »** | **0.62 — le tarif historisé de la fiche clôturée, alors que le véhicule déclaré vaut aujourd'hui 0.67. C'est la phase 1 prouvée sur le document imprimé.** |
| I5 | Vérifier le tableau « Autres Frais » | Date, Libellé, Montant pour chaque frais hors forfait |
| I6 | Vérifier le total | 110,00 + 139,50 + 240,00 + 225,00 + 24,00 + 57,90 = **796,40** |
| I7 | Vérifier le pied | « Fait à Paris, le <date du jour> » et « Vu l'agent comptable » |
| I8 | Choisir un mois sans frais hors forfait | Le tableau « Autres Frais » affiche « Aucun frais hors forfait ce mois. » et le PDF reste correct |

### 9.16 Phase 3 — contrôle d'accès à l'impression

| # | Action | Attendu | Obtenu |
|---|--------|---------|--------|
| I9 | `curl -b ck_cbedos.txt http://127.0.0.1:8000/fichepdf/a17/202608` | **404** — on n'imprime pas la fiche d'un autre | ✔ |
| I10 | `curl -b ck_dandre.txt http://127.0.0.1:8000/fichepdf/a17/201001` | 404 — mois sans fiche | ✔ |
| I11 | `curl http://127.0.0.1:8000/fichepdf/a17/202608` (sans session) | 302 vers `/accueil` | ✔ |
| I12 | `curl -b ck_dandre.txt http://127.0.0.1:8000/fichespdf/202608` | **403** — réservé au comptable | ✔ |

### 9.17 Phase 3 — génération de masse et volumétrie

```bash
# 1) créer un gros jeu de données (200 visiteurs, fiches clôturées d'août)
php app/console gsb:generer-donnees --visiteurs=200 --mois=202608 --etat=CL

# 2) générer l'archive du mois
php app/console gsb:imprimer-fiches 202608 --etat=CL --sortie=/tmp/fiches.zip

# 3) comparer avec une seule fiche
php app/console gsb:imprimer-fiches 202606 --etat=RB --sortie=/tmp/une.zip

# 4) vérifier le contenu
unzip -l /tmp/fiches.zip
```

Résultats obtenus sur ce poste :

| Commande | Fiches | Pic de mémoire | Durée | Archive |
|----------|--------|----------------|-------|---------|
| `gsb:imprimer-fiches 202606 --etat=RB` | 1 | 30,0 Mo | 1,3 s | 25,6 Ko |
| `gsb:imprimer-fiches 202608 --etat=CL` | **202** | **38,0 Mo** | 49,0 s | 5 152 Ko |

➡️ **C'est le test à montrer au jury** : 202 fois plus de fiches, 8 Mo de
mémoire en plus. Si les PDF s'accumulaient, la mémoire serait proportionnelle.

Sans argument, la commande traite **le mois précédent** à l'état `CL`, ce qui
correspond exactement au besoin du cahier des charges :

```bash
php app/console gsb:imprimer-fiches
```

### 9.18 Phase 3 — non-régression (vérifiée)

| # | Vérification | Résultat |
|---|--------------|----------|
| R1 | Mauvais mot de passe | Message d'erreur ✔ |
| R3 | Quantité non numérique | Message d'erreur ✔ |
| R5 | Frais de plus d'un an | Message d'erreur ✔ |
| P1 | Tarif du véhicule en saisie | 0.67 € ✔ |
| P2 | Compteur de justificatifs | « 1 justificatif(s) pris en compte » ✔ |
| P2 | Lien de consultation du justificatif | présent ✔ |
| P2 | Accès croisé à un justificatif | 404 ✔ |

---

## 10. Proposition pour la suite (en attente de ton accord)

### 10.1 Filet de sécurité — ✅ FAIT

| # | Commande exécutée | Résultat vérifié |
|---|-------------------|------------------|
| P1 | `git init` + `git add -A` + commit | Commit `b2ac4b6`, **78 fichiers suivis**. Contrôlé : `vendor/`, `bin/`, `app/cache/*`, `app/logs/*` et `parameters.yml` sont bien exclus (seuls les deux `.gitkeep` sont suivis, volontairement). Corrige B6. |
| P2 | `mysqldump -u root --databases bd_gsb --add-drop-database=FALSE > sauvegardes/bd_gsb_avant_phase1.sql` | 9,4 Ko, 6 instructions `INSERT` (une par table), **0 instruction `DROP DATABASE`**. |

> **Pourquoi `--add-drop-database=FALSE` :** par défaut, avec `--databases`,
> `mysqldump` place un `DROP DATABASE IF EXISTS` en tête du fichier produit. Une
> sauvegarde qui commence par détruire la base est un piège : rejouée par
> erreur, elle fait perdre l'état courant au lieu de le restaurer. L'option
> retire cette ligne.
> **Restauration :** `mysql -u root < sauvegardes/bd_gsb_avant_phase1.sql`
> — à ne lancer qu'en connaissance de cause, elle écrase le contenu des tables.

### 10.2 Ce que je n'installe pas avant la phase concernée

| Phase | Besoin | Dépendance envisagée | Justification que je te détaillerai le moment venu |
|-------|--------|----------------------|-----------------------------------------------------|
| 2 | Upload de fichiers | **aucune** | Le composant `HttpFoundation\File\UploadedFile` de Symfony 2.8 suffit — déjà installé. Pas de librairie exotique. |
| 3 | PDF | `dompdf/dompdf` (`composer require dompdf/dompdf`) | Compatible PHP 7.4, s'écrit en HTML+CSS (donc en Twig, cohérent avec l'existant), très répandu → défendable à l'oral. Alternative : TCPDF (API impérative, plus verbeuse). **Je te demanderai l'accord avant le `composer require`.** |
| 3 | Jeu de données volumineux | **aucune** | Commande console maison (corrige B7). |
| 5 | Rôles / comptable | **aucune** | Session + `fct.inc.php`, dans le style de l'existant (décision 0.4). |

### 10.3 Questions ouvertes à trancher (aucune ne bloque la phase 1)

1. **B4 — cycle d'état :** ajouter un 5ᵉ état « Mise en paiement » (`MP`) et
   re-libeller `VA` en « Validée ». Le cahier des charges l'impose (§ 4.3), mais
   cela touche une donnée existante. → décision en **phase 5**.
2. **B15 / E1 — matricule :** imprimer l'id `a17` sur la fiche PDF, ou ajouter
   une colonne `matricule` à `visiteur` ? → décision en **phase 3**.
3. **B8 — mots de passe :** garder le clair (conforme au sujet d'origine) ou
   passer à `password_hash()` ? → décision en **phase 5**, au moment de créer le
   compte comptable.
4. **B12 — taille des justificatifs :** plafonner à 2 Mo (limite PHP actuelle)
   ou relever `upload_max_filesize` ? → décision en **phase 2**.

---

## 11. Préparation à l'oral — phase 0

**Q1. Pourquoi y a-t-il deux dossiers `GsbFraisBundle` ?**
Parce qu'un *bundle* Symfony n'est pas une application. L'archive du professeur
ne contient que le bundle (contrôleurs, vues, service) ; il manque le squelette
Symfony (`app/`, `web/`, `vendor/`). Le bundle a donc été placé dans
`gsbProjet/src/Pg/GsbFraisBundle/`, où `AppKernel::registerBundles()` le charge.

**Q2. Où est la couche « Modèle » de ce MVC ?**
Dans `services/PdoGsb.php`. Il n'y a pas d'entité ni d'ORM : la classe expose
des méthodes métier (`getLesFraisForfait`, `majEtatFicheFrais`…) qui exécutent
des requêtes SQL préparées. Les contrôleurs ne contiennent aucune requête SQL —
c'est ce qui fait que la séparation MVC est respectée malgré l'absence d'ORM.

**Q3. Comment le contrôleur obtient-il l'accès à la base ?**
Par l'injection de dépendances : `services.yml` déclare le service
`pg_gsb_frais.pdo` de classe `PdoGsb` avec 4 arguments (hôte, base, user, mdp) ;
le contrôleur le récupère avec `$this->get('pg_gsb_frais.pdo')`. Avantage : les
identifiants ne sont pas écrits en dur dans le code, et on peut remplacer
l'implémentation sans toucher aux contrôleurs.

**Q4. L'application est-elle protégée contre l'injection SQL ?**
Oui, sur l'existant : toutes les requêtes de `PdoGsb` sont **préparées**
(`prepare()` + `bindParam()`), donc les valeurs transmises ne sont jamais
concaténées dans le texte SQL. Le point faible n'est pas l'injection mais le
stockage des mots de passe en clair.

**Q5. Comment sait-on qu'un utilisateur est connecté ?**
`connecter()` place `id`, `nom`, `prenom` dans la session
(`Symfony\...\Session`). `estConnecte()` teste `$session->has('id')`.
Chaque action protégée commence par cette vérification et redirige vers
l'accueil sinon. Le *flash bag* ne conviendrait pas : il est vidé à la première
lecture, la connexion serait perdue dès la page suivante.

**Q6. Un visiteur peut-il supprimer le frais d'un collègue en modifiant l'URL ?**
Non. `SaisirFraisController::supprimerfraishorsforfaitAction()` appelle d'abord
`estValideSuppressionFrais($idVisiteur, $mois, $id)`, qui compte les lignes
correspondant **à la fois** à l'id du frais, au mois courant et au visiteur en
session ; si le compte est 0, une 404 est levée. C'est un contrôle d'accès
**côté serveur** : masquer le lien ne suffirait pas.

**Q7. Le tarif kilométrique de 0,62 € est-il codé en dur ?**
Non : il est stocké dans la table `fraisforfait` (ligne `KM`). Mais — et c'est
la découverte de l'audit — **cette colonne `montant` n'est lue par aucune ligne
de code** : l'application n'affiche jamais de total. La phase 1 devra donc
introduire le calcul en même temps que le barème.

**Q8. Pourquoi ne pas utiliser Doctrine et les migrations ?**
Trois raisons : le sujet impose l'architecture du prédécesseur (PdoGsb) ;
la version de `doctrine/orm` figée dans `composer.lock` (2.5.14) plante en
PHP 7.4 ; et réécrire la couche de données contredirait la consigne de reprise.
En contrepartie, les évolutions de schéma sont livrées en scripts SQL
incrémentaux versionnés (`sql/00X_*.sql`), qui jouent le rôle des migrations.

**Q9. Qu'est-ce qu'un problème « N+1 » et y en a-t-il ici ?**
C'est le fait d'exécuter 1 requête pour obtenir une liste de N éléments, puis 1
requête supplémentaire par élément — soit N+1 requêtes au lieu de 1 ou 2.
Ici `creeNouvellesLignesFrais()` fait 1 requête + 1 insertion par type de frais.
N vaut 4 et ne bouge pas, donc c'est sans impact ; mais le même motif appliqué
en phase 3 à « toutes les fiches du mois » serait un vrai problème de
performance. C'est le point que je surveille pour la suite.

**Q10. L'application fonctionne-t-elle vraiment ?**
Oui, vérifié : `GET /accueil` → 200, connexion `dandre`/`oppg5` → 302 +
session, `GET /listefrais` → fiche de septembre 2026 affichée avec son état et
sa date de modification.

**Q11. Pourquoi faut-il historiser le tarif kilométrique plutôt que de le lire
dans la table des barèmes au moment de l'affichage ?**
Parce que l'annexe 1 du cahier des charges imprime une colonne « Montant
unitaire » sur chaque fiche. Une fiche de décembre 2010 doit rester imprimable
à l'identique aujourd'hui. Si le montant est lu dans le référentiel au moment de
l'affichage, tout changement de barème réécrit le passé : les fiches déjà
validées et déjà remboursées changeraient de total. On enregistre donc le tarif
**appliqué** sur la ligne de frais au moment de la saisie — c'est ce qu'on
appelle historiser une donnée.

**Q12. Le cahier des charges impose-t-il vraiment de ne garder aucune trace des
corrections du comptable ?**
Oui, c'est écrit noir sur blanc : « Les valeurs sont corrigées en conséquence
sans que ne soit conservée trace de la modification », et pour les refus : « On
n'enregistrera pas la raison du refus ». Je l'applique parce que c'est la
spécification du client. **Je signale cependant que c'est discutable** : sans
journal des modifications, un visiteur ne peut pas vérifier pourquoi son
remboursement a baissé, et l'entreprise n'a aucune traçabilité en cas de litige.
Le document lui-même prévoit un contournement hors système (« le visiteur doit
être tenu informé par téléphone », « les documents annotés sont conservés par le
service comptable ») : la traçabilité existe, mais sur papier.

### Questions sur la phase 1

**Q13. Où est stocké le barème, et pourquoi pas dans le code ?**
Dans la table `typevehicule` (6 lignes : id, libellé, montant). Le cahier des
charges annonce que la direction le fera évoluer. S'il était dans un `switch` du
contrôleur, chaque changement de tarif imposerait une modification du code, un
test et un redéploiement. En base, c'est un `UPDATE` d'une ligne, sans toucher à
l'application.

**Q14. Comment garantis-tu qu'une fiche déjà saisie n'est pas recalculée ?**
Par **historisation** : la colonne `lignefraisforfait.montantUnitaire` enregistre
le tarif appliqué **au moment de la saisie**. Les écrans lisent ce montant-là,
jamais le barème courant. Concrètement, si la direction porte demain le tarif à
0,80 €, mes fiches de juin resteront à 0,62 € parce que c'est ce qui est écrit
sur leurs lignes. La preuve est au § 9.6 : après changement de véhicule, les
fiches `CL`, `VA` et `RB` sont restées à 0,62 € et seule la fiche `CR` est passée
à 0,67 €.

**Q15. Le barème est en base, mais `'KM'` est écrit dans le code : n'est-ce pas
contradictoire ?**
Non, et c'est un arbitrage assumé. Ce que le cahier des charges veut éviter,
c'est que les **valeurs** du barème soient figées dans le code — elles sont en
base. `'KM'` n'est pas un tarif : c'est l'identifiant du type de frais dont le
montant dépend du véhicule. C'est une information de structure, qui ne change
pas quand les tarifs changent. Elle est déclarée **une seule fois**, dans la
constante `PdoGsb::ID_FRAIS_KILOMETRIQUE`. L'alternative serait une colonne
booléenne `fraisforfait.estKilometrique` : plus pure, mais elle ajoute une
colonne à une table existante pour décrire un fait qui ne variera jamais.

**Q16. Que se passe-t-il pour un visiteur qui n'a pas déclaré son véhicule ?**
Il est remboursé au tarif historique de 0,62 €/km, exactement comme avant la
phase 1. Techniquement, la requête fait un `LEFT JOIN` sur `typevehicule` et un
`coalesce` : si la jointure ne ramène rien, on retombe sur `fraisforfait.KM`.
C'est important : **aucun visiteur ne se retrouve avec un tarif à zéro** parce
qu'il n'a pas encore rempli le formulaire.

**Q17. Si un visiteur change de véhicule le 10 du mois, que devient sa fiche en
cours ?**
Elle est mise à jour. La règle codée est : seules les fiches à l'état `CR`
(saisie en cours) suivent le nouveau tarif. Une fiche en cours est un brouillon,
pas un historique. Sans cette règle, un visiteur qui déclare son véhicule le 10
serait remboursé au mauvais tarif pour tout le mois sans pouvoir le corriger.
Le filtre est dans le `WHERE` de la requête : `AND fichefrais.idEtat = 'CR'`.

**Q18. Pourquoi une transaction dans `majTypeVehiculeVisiteur()` ?**
Parce que l'opération comporte deux écritures : mettre à jour le véhicule du
visiteur, puis répercuter le tarif sur ses fiches en cours. Si la seconde
échouait, on aurait un visiteur avec un véhicule déclaré mais une fiche restée
à l'ancien tarif — une incohérence silencieuse. `beginTransaction()` /
`commit()`, avec `rollBack()` dans le `catch`, garantit « les deux, ou aucune ».

**Q19. Y a-t-il un risque de N+1 dans ton code ?**
Non, et c'est délibéré. Deux endroits auraient pu en produire :
(a) `creeNouvellesLignesFrais()` — la requête qui récupère les montants
unitaires est faite **une fois avant la boucle** ; une version naïve aurait
interrogé la base à chaque tour (6 requêtes au lieu de 10) ;
(b) la répercussion sur les fiches en cours — c'est un `UPDATE … INNER JOIN`,
donc **1 requête quel que soit le nombre de fiches**, au lieu d'une lecture
suivie d'un `UPDATE` par fiche.

**Q20. Pourquoi ne pas avoir mis `DEFAULT 0` sur `montantUnitaire` ?**
Parce qu'une insertion qui oublierait le montant passerait alors sans bruit et
produirait une ligne remboursée **zéro euro** — une erreur invisible en
production. Avec `NOT NULL` sans valeur par défaut, MySQL en mode strict refuse
l'insertion : l'erreur apparaît immédiatement, en développement.

**Q21. Comment as-tu vérifié que le script de création complète et la base
migrée donnent bien le même schéma ?**
J'ai créé une base jetable à partir de `sql/bd_gsb.sql`, puis comparé les deux
schémas avec `mysqldump --no-data` et `diff`. Aucune différence de structure.
Sans cette vérification, un camarade qui installerait le projet de zéro
obtiendrait une base subtilement différente de la mienne.

### Questions sur la phase 2

**Q22. Où sont stockés les justificatifs, et pourquoi pas dans `web/` ?**
Dans `app/justificatifs/`, donc **hors de la racine web**. Un fichier placé
dans `web/` est servi directement par le serveur, sans passer par
l'application : n'importe qui connaissant son adresse y accéderait, sans
contrôle. Hors de `web/`, le seul chemin possible est
`JustificatifController::consulterAction()`, qui vérifie d'abord les droits.

**Q23. Le nom aléatoire suffirait-il, sans sortir de `web/` ?**
Non, et c'est une question de fond. Un nom indevinable est de la **sécurité par
l'obscurité** : elle tient tant que l'adresse ne fuit pas — or une adresse
fuit (historique de navigateur, copier-coller dans un message, journaux d'un
serveur mandataire). Et une fois qu'elle a fui, il n'y a plus aucun contrôle
possible. J'ai donc fait les deux : stockage hors de `web/` pour le contrôle
réel, nom aléatoire comme défense supplémentaire.

**Q24. Comment empêches-tu qu'on envoie un exécutable renommé en `.pdf` ?**
Par un double contrôle. L'extension doit figurer dans la liste autorisée (pdf,
jpg, jpeg, png), **et** le type MIME réel doit correspondre à cette extension.
Ce type réel est obtenu par `getMimeType()`, qui s'appuie sur l'extension PHP
`fileinfo` : elle lit **les premiers octets du fichier**, elle ne fait pas
confiance à ce que le navigateur annonce — cette déclaration étant entièrement
sous le contrôle de l'expéditeur. Testé : un PDF renommé en `.png` est refusé.

**Q25. Que se passe-t-il si quelqu'un envoie un fichier nommé
`../../app/config/parameters.yml` ?**
Rien de dangereux. Le nom fourni ne sert **jamais** à construire un chemin : le
fichier est écrit sous un nom généré aléatoirement. Le nom d'origine est
seulement conservé pour l'affichage, après `basename()` et remplacement de tous
les caractères autres que lettres, chiffres, point, tiret et souligné.
`StockageJustificatifs::chemin()` réapplique `basename()` à la lecture : c'est
une seconde barrière, au cas où un nom anormal parviendrait en base.

**Q26. Pourquoi renvoyer 404 et non 403 quand l'accès est refusé ?**
Parce qu'un 403 « interdit » **confirmerait que le document existe**. En
comparant les réponses 403 et 404, un visiteur curieux pourrait cartographier
les frais de ses collègues sans jamais ouvrir un seul fichier. Avec un 404
uniforme — même code pour « ça n'existe pas » et pour « ce n'est pas à vous » —
il n'apprend rien.

**Q27. Comment est calculé le nombre de justificatifs affiché ?**
Il n'est pas *calculé*, il est **recalculé** : `UPDATE fichefrais SET
nbJustificatifs = (SELECT COUNT(*) …)`, en une seule instruction, à chaque
dépôt et à chaque suppression. J'ai écarté l'incrémentation (`= nbJustificatifs
+ 1`) parce qu'un compteur incrémenté dérive : un double clic, une erreur en
cours d'opération, une suppression non répercutée, et il devient faux sans que
rien ne le signale. Or c'est sur ce chiffre que le comptable décide d'un
remboursement.

**Q28. Que se passe-t-il si le remplacement d'un justificatif échoue ?**
Le visiteur garde l'ancien. L'ordre des opérations est délibéré : mémoriser le
nom de l'ancien, écrire le nouveau, enregistrer en base, **et seulement ensuite**
effacer l'ancien du disque. Si on effaçait d'abord et que l'écriture échouait,
le visiteur perdrait sa facture sans rien obtenir en échange.

**Q29. Et quand on supprime un frais hors forfait ?**
La ligne est lue avant d'être supprimée pour mémoriser le nom du fichier, puis
le fichier est effacé du disque et le compteur recalculé. Sans cela, on
accumulerait des fichiers orphelins — des factures contenant des données
personnelles, que plus aucune ligne ne référence. La base sait propager une
suppression (`ON DELETE CASCADE`), pas le système de fichiers : dès qu'une
donnée vit à deux endroits, chaque suppression doit être pensée deux fois.

**Q30. Le comptable peut-il consulter les justificatifs ?**
La **règle** est écrite : l'accès est accordé au propriétaire du frais **ou** à
un comptable. Mais l'acteur n'existe pas encore — aucun compte comptable n'est
créé avant la phase 5, donc `estComptable()` renvoie faux pour tout le monde.
J'ai écrit la règle dès maintenant pour ne pas avoir à retoucher en phase 5 du
code de sécurité déjà testé : c'est exactement là qu'on introduit des failles.
**Ce point du sujet est donc partiellement livré**, et je le présente comme tel.

### Questions sur la phase 3

**Q31. Pourquoi dompdf et pas une autre bibliothèque ?**
Parce qu'elle prend en entrée du **HTML et du CSS**. La fiche est donc décrite
dans un gabarit Twig, comme toutes les autres pages du projet : une seule
technique à connaître, et un ajustement de maquette se fait en CSS. TCPDF,
l'autre choix courant, demande de positionner chaque cellule en millimètres —
le tableau de l'annexe 1 aurait représenté une centaine de lignes de calculs de
coordonnées.

**Q32. Comment évites-tu de saturer la mémoire si le mois compte 500 fiches ?**
En traitant les fiches **une par une** : je produis le PDF, je l'écris aussitôt
dans un fichier temporaire, je libère la mémoire avec `unset()`, puis je passe
à la suivante. À aucun moment la mémoire ne contient plus d'un PDF. La requête
qui liste les fiches ne ramène d'ailleurs que les identités, pas le détail des
frais — sinon tout le mois serait en mémoire avant même de commencer. Mesuré :
1 fiche = 30 Mo, 202 fiches = 38 Mo.

**Q33. Pourquoi un ZIP de PDF séparés plutôt qu'un seul PDF de 200 pages ?**
Pour deux raisons. Technique : dompdf assemble **tout le document en mémoire**
avant de l'écrire, donc un PDF de 200 pages serait intégralement en mémoire —
exactement ce que je cherche à éviter. Métier : le cahier des charges décrit un
classement « par ordre chronologique dans une pochette nominative pour chaque
visiteur » ; une fiche par fichier se classe directement.

**Q34. Les 30 Mo de départ, ce n'est pas beaucoup pour une seule fiche ?**
C'est le coût fixe de dompdf, qui charge et met en cache les quatre variantes de
la police DejaVu (normale, grasse, italique, grasse italique) à la première
fiche. Ce coût ne se répète pas : c'est pour cela que la 202ᵉ fiche ne coûte
presque rien de plus que la première.

**Q35. Ta génération de masse fait 3 requêtes par fiche : n'est-ce pas un
N+1 ?**
Si, et je l'assume. La solution habituelle — une requête unique ramenant toutes
les lignes de toutes les fiches — **remettrait en mémoire exactement ce que la
conception cherche à ne pas y mettre**. C'est un arbitrage entre nombre de
requêtes et empreinte mémoire, tranché en faveur de la mémoire parce que c'est
la contrainte que le cahier des charges met en avant. Ce n'est pas un oubli.

**Q36. Pourquoi une commande console alors que le sujet parle d'« un seul
clic » ?**
Le bouton existe : c'est la route `/fichespdf/{mois}`, réservée au comptable.
Mais le comptable n'existe qu'en phase 5. La commande console permet d'utiliser
et de démontrer la fonctionnalité dès maintenant — et elle appelle **exactement
le même service**, il n'y a donc pas deux versions à maintenir. Avantage
supplémentaire : une commande console n'est pas soumise au délai d'expiration
d'une requête HTTP, ce qui compte pour 200 fiches et 49 secondes.

**Q37. Pourquoi avoir coupé l'accès réseau de dompdf ?**
Parce que dompdf suit les balises `<img src="http://…">` en allant chercher la
ressource **depuis le serveur**. Or les libellés de frais sont saisis par les
visiteurs. Sans `isRemoteEnabled: false`, quelqu'un pourrait faire émettre à
notre serveur des requêtes vers des adresses internes — c'est une faille connue
sous le nom de SSRF. Le gabarit n'a besoin d'aucune ressource distante.

**Q38. Le total imprimé, qui le calcule ?**
PHP, dans `calculerTotal()`, par deux parcours des tableaux **déjà chargés** en
mémoire pour l'affichage. Le faire calculer par SQL ajouterait deux requêtes
d'agrégation **par fiche** — 404 requêtes de plus sur un lot de 202 — pour
additionner une quinzaine de nombres déjà présents. C'est l'inverse du choix
fait pour le compteur de justificatifs : là-bas, SQL évitait un aller-retour ;
ici, il en ajouterait un. **La bonne règle n'est pas « toujours SQL » ou
« toujours PHP », mais « où la donnée se trouve-t-elle déjà ? »**

**Q39. Pourquoi 403 ici et 404 pour les justificatifs ?**
Un 404 sert à ne pas révéler l'existence d'un document confidentiel : le
justificatif d'un collègue. L'existence d'un export mensuel n'a rien de secret,
et l'utilisateur est bien authentifié — il lui manque seulement le rôle. 403
« interdit » est alors le code juste. J'ai dû le construire à la main, car le
raccourci `createAccessDeniedException()` de Symfony produit un 401 en
l'absence de pare-feu configuré.

**Q40. La fiche imprimée affiche « a17 » alors que l'annexe montre
« NRD/125-01 ». Pourquoi ?**
Parce que ce matricule n'existe pas dans la base et que le cahier des charges ne
définit nulle part sa règle de construction. Ajouter une colonne reviendrait à
inventer des valeurs, donc à livrer des données fausses. L'identifiant du
visiteur tient ce rôle. C'est une décision prise en connaissance de cause, pas
un oubli.

### Glossaire

| Terme | Définition |
|-------|------------|
| **Bundle** | Module Symfony 2 regroupant contrôleurs, vues, configuration et services d'une fonctionnalité. Ici : `PgGsbFraisBundle`. |
| **Squelette (Standard Edition)** | Application Symfony minimale (`app/`, `web/`, `src/`, `vendor/`) dans laquelle on installe des bundles. |
| **Service** | Objet construit et fourni par le conteneur de Symfony. Ici `pg_gsb_frais.pdo` (instance de `PdoGsb`). |
| **Injection de dépendances** | Fournir à un objet ce dont il a besoin (ici : les paramètres de connexion) au lieu qu'il aille le chercher lui-même. |
| **Entité** | Classe PHP représentant une ligne de table, utilisée par un ORM. **Il n'y en a pas dans ce projet** (pas d'ORM). |
| **Repository** | Classe regroupant les requêtes de lecture d'une entité, dans le vocabulaire Doctrine. Le rôle est tenu ici par `PdoGsb`. |
| **ORM** | *Object-Relational Mapping* : bibliothèque qui traduit objets ↔ tables (Doctrine). Volontairement désactivée ici. |
| **Requête préparée** | Requête SQL dont les valeurs sont transmises séparément du texte SQL (`prepare` + `bindParam`), ce qui empêche l'injection SQL. |
| **Injection SQL** | Attaque consistant à glisser du SQL dans une donnée saisie pour détourner la requête. |
| **Contrôle d'accès côté serveur** | Vérification faite par le serveur avant d'exécuter une action, indépendamment de ce que l'interface affiche ou masque. |
| **Session** | Espace de stockage côté serveur associé à un visiteur via un cookie (`PHPSESSID`), qui survit d'une page à l'autre. |
| **Flash bag** | Zone de session dont le contenu est effacé dès la première lecture ; sert aux messages ponctuels, pas à l'état de connexion. |
| **N+1** | Anti-patron : N+1 requêtes SQL exécutées là où 1 ou 2 suffiraient. |
| **Transaction** | Groupe d'opérations SQL validé (`COMMIT`) ou annulé (`ROLLBACK`) en bloc, pour éviter les états intermédiaires incohérents. |
| **Migration** | Script versionné faisant évoluer le schéma de la base d'une version à la suivante. Ici : les fichiers `sql/00X_*.sql`. |
| **Historisation** | Enregistrer la valeur d'une donnée au moment d'un événement, pour qu'un changement futur du référentiel ne modifie pas le passé. Sujet central de la phase 1. |
| **Référentiel** | Table de valeurs de référence, partagée et destinée à évoluer (ici `typevehicule`, `fraisforfait`). S'oppose aux données historisées, qui ne bougent plus. |
| **DDL / DML** | DDL = instructions qui modifient la *structure* (`CREATE`, `ALTER`) ; DML = instructions qui modifient les *données* (`INSERT`, `UPDATE`, `DELETE`). Sous MySQL, une transaction protège le DML, pas le DDL. |
| **LEFT JOIN** | Jointure qui conserve toutes les lignes de la table de gauche, même sans correspondance à droite (les colonnes de droite valent alors NULL). Un `INNER JOIN` les supprimerait. |
| **COALESCE** | Fonction SQL qui renvoie son premier argument non NULL. Sert ici à appliquer un tarif de repli. |
| **Échappement HTML** | Remplacement automatique par Twig des caractères `< > & ' "` par leur équivalent inoffensif (`&#039;`…), pour qu'une donnée saisie ne puisse pas être interprétée comme du code. Base de la protection contre les failles XSS. |
| **XSS** | *Cross-Site Scripting* : injection de code JavaScript via une donnée saisie, exécutée dans le navigateur des autres utilisateurs. |
| **Type MIME** | Étiquette normalisée décrivant la nature d'un fichier (`application/pdf`, `image/png`). Celui annoncé par le navigateur n'est pas fiable ; celui détecté par `fileinfo` à partir du contenu l'est. |
| **Traversée de répertoire** | Attaque consistant à glisser `../` dans un nom de fichier pour atteindre un dossier situé hors de la zone prévue. |
| **Sécurité par l'obscurité** | Protection qui repose uniquement sur le fait qu'une information est difficile à deviner. Elle s'effondre dès que cette information fuit ; elle ne remplace jamais un contrôle d'accès. |
| **Défense en profondeur** | Superposition volontaire de plusieurs protections, pour qu'une seule faille ne suffise pas. |
| **Fichier orphelin** | Fichier resté sur le disque alors que plus aucune donnée ne le référence. Invisible, mais il occupe de l'espace et peut contenir des données personnelles. |
| **Flash bag** (complément) | Le message survit à une **redirection**, ce qui est indispensable quand l'action qui produit l'erreur n'est pas celle qui affiche la page. |
| **SSRF** | *Server-Side Request Forgery* : amener le serveur à émettre lui-même une requête vers une adresse choisie par l'attaquant, souvent pour atteindre des machines internes inaccessibles de l'extérieur. |
| **Pic de mémoire** | Maximum de mémoire occupée depuis le début d'un script (`memory_get_peak_usage`). C'est le chiffre qui compte pour savoir si un traitement tiendra : une moyenne masquerait le pic qui fait échouer. |
| **`unset()`** | Libère explicitement une variable. Utile dans une boucle qui manipule de gros contenus : sans lui, la valeur reste occupée jusqu'à sa réaffectation au tour suivant. |
| **Commande console** | Point d'entrée de l'application lancé en ligne de commande (`php app/console …`) plutôt que par le navigateur. Adaptée aux traitements longs : pas de délai d'expiration HTTP. |
| **Mode strict (MySQL)** | Configuration qui fait échouer une requête invalide au lieu de la corriger silencieusement (valeur tronquée, colonne obligatoire absente…). |
| **Twig** | Moteur de templates de Symfony (`.html.twig`) : produit le HTML à partir de variables fournies par le contrôleur. |
| **Fichier de routage** | `routing.yml` : associe une URL à un couple contrôleur/action. |
| **CSRF** | *Cross-Site Request Forgery* : attaque faisant exécuter une action à un utilisateur connecté à son insu ; se contre par un jeton dans le formulaire. |

---

*Fin du compte rendu de la phase 0.*
