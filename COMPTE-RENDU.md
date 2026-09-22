# Compte rendu — Application GSB Frais (AP BTS SIO 2)

Document vivant, enrichi à chaque phase. Toute la terminologie employée est
définie au § 11 (glossaire).

| Phase | Intitulé | État |
|-------|----------|------|
| 0 | Audit du code existant | **terminée** |
| 1 | Puissance fiscale du véhicule | à faire |
| 2 | Upload des justificatifs | à faire |
| 3 | Génération PDF des fiches | à faire |
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

## 5. Pourquoi de cette manière (décisions de la phase 0)

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

---

## 6. Logique des boucles et des algorithmes

**La phase 0 ne contient aucune boucle : je n'ai écrit aucun code.**
En revanche j'ai lu les boucles existantes, et trois d'entre elles
conditionnent les phases suivantes. Je les documente ici parce que je vais
devoir m'y raccrocher.

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

*Aucune erreur applicative n'a été rencontrée : le test de fumée est passé du
premier coup (voir § 9).*

---

## 8. Ce qui ne fonctionne pas / limites / points bloquants

Classés par criticité pour la suite du projet.

### 🔴 B1 — Aucun calcul de montant n'existe dans l'application
`fraisforfait.montant` n'est jamais lu (§ 3.3). Aucun total forfait, aucun total
hors forfait, aucun total général n'est calculé ni affiché.
**Impact :** phases 1 et 3. La phase 1 devra créer le calcul, pas le corriger.

### 🔴 B2 — `lignefraisforfait` ne stocke que la quantité
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
| **Twig** | Moteur de templates de Symfony (`.html.twig`) : produit le HTML à partir de variables fournies par le contrôleur. |
| **Fichier de routage** | `routing.yml` : associe une URL à un couple contrôleur/action. |
| **CSRF** | *Cross-Site Request Forgery* : attaque faisant exécuter une action à un utilisateur connecté à son insu ; se contre par un jeton dans le formulaire. |

---

*Fin du compte rendu de la phase 0.*
