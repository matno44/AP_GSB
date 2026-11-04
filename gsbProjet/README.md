GSB – Suivi du remboursement des frais
======================================

Application Symfony 2.8 (bundle `PgGsbFraisBundle`) permettant à un visiteur
médical de saisir et de consulter ses fiches de frais mensuelles.

Prérequis
---------

| Composant | Version utilisée | Remarque |
|-----------|------------------|----------|
| PHP       | **7.4**          | imposé par `.php-version` et `config.platform.php` |
| MySQL     | 8 ou 9           | voir la note « collation » plus bas |
| Composer  | 2.x              | les dépendances sont déjà dans `vendor/` |
| dompdf    | 2.0.8            | génération des fiches PDF |

> **Note sur `composer install` / `composer require`.** Le script
> post-installation de `sensio/distribution-bundle` échoue avec
> `Process::__construct() must be of the type array, string given` : ce paquet
> de 2016 est incompatible avec le Symfony Process 5 qu'embarque le Composer
> actuel. **C'est sans conséquence** — seule la régénération de
> `app/bootstrap.php.cache` est concernée, et ce fichier reste valide. Lancer
> ensuite à la main `php app/console cache:clear --env=dev` et
> `php app/console assets:install web`.

> **Ne pas utiliser PHP 5.6.** Le projet y était épinglé à l'origine, mais
> PHP 5.6 ne sait ni négocier la collation ni l'authentification
> `caching_sha2_password` de MySQL 8/9 : la connexion échoue avec
> `SQLSTATE[HY000] [2054]`. MySQL 9 a par ailleurs supprimé
> `mysql_native_password`, il n'existe donc aucun contournement côté serveur.

Installation
------------

### 1. Base de données

```bash
mysql -u root < sql/bd_gsb.sql
```

Le script crée la base `bd_gsb`, ses treize tables et un jeu de données de
démonstration. Il est tenu à jour avec les migrations : une base créée de zéro
a exactement le même schéma qu'une base migrée.

**Sur une base déjà existante**, ne pas rejouer `bd_gsb.sql` (il commence par
`DROP DATABASE`) : appliquer les migrations du dossier `sql/`, une seule fois
chacune et dans l'ordre.

```bash
mysqldump -u root --databases bd_gsb --add-drop-database=FALSE > sauvegardes/bd_gsb_avant_001.sql
mysql -u root bd_gsb < sql/001_puissance_fiscale_vehicule.sql
```

| Migration | Objet |
|-----------|-------|
| `001_puissance_fiscale_vehicule.sql` | barème kilométrique par véhicule, rattachement du visiteur, historisation du montant unitaire |
| `002_justificatifs.sql` | pièces jointes des frais hors forfait, remise à zéro du compteur `nbJustificatifs` |
| `003_comptable_et_etats.sql` | table `comptable`, 5ᵉ état « Mise en paiement », re-libellé de « Validée » |
| `004_administration_comptes.sql` | table `administrateur`, journal d'administration, empreintes de mots de passe |
| `005_trajets_et_retrait_forfait_etape.sql` | table `trajet`, désactivation du forfait étape |
| `006_carte_grise.sql` | tables `cartegrise` et `statutcartegrise` : validation du véhicule sur pièce |

Les paramètres de connexion sont dans
`src/Pg/GsbFraisBundle/Resources/config/services.yml` :

```yaml
parameters:
    db.host: mysql:host=localhost
    db.name: dbname=bd_gsb
    db.user: root
    db.mdp:
```

**Note collation (MySQL 8 et 9).** Par défaut le serveur annonce
`utf8mb4_0900_ai_ci` (id 255) pendant le handshake, que les clients mysqlnd
antérieurs à PHP 7.2 ne connaissent pas. Si vous rencontrez
`Server sent charset unknown to the client`, ajoutez dans `my.cnf` :

```ini
[mysqld]
character-set-server = utf8mb4
collation-server     = utf8mb4_general_ci
```

### 2. Dépendances et assets

```bash
composer install
php app/console assets:install web
php app/console cache:clear --env=dev
```

`assets:install` copie la feuille de style et le logo du bundle vers
`web/bundles/pggsbfrais/`. Sans cette étape les pages s'affichent sans style.

### 3. Lancement

```bash
symfony server:start -d        # http://127.0.0.1:8000
# ou
php -d upload_max_filesize=6M -d post_max_size=8M -S 127.0.0.1:8000 -t web web/app_dev.php
```

Les deux directives `-d` servent à l'envoi des cartes grises (5 Mo). Sans
elles, `upload_max_filesize` vaut 2M par défaut et **PHP écarte le fichier
avant que l'application le voie** : le visiteur reçoit un message technique en
anglais citant une directive de configuration, au lieu du message de
l'application. La valeur doit être **strictement supérieure** à la limite
applicative — à 5M exactement, les deux seuils se confondent et PHP reprend la
main. Avec `symfony server:start`, régler les mêmes valeurs dans le `php.ini`
de la version de PHP utilisée.

Comptes de démonstration
------------------------

| Login      | Mot de passe | Visiteur           |
|------------|--------------|--------------------|
| `dandre`   | `oppg5`      | David Andre        |
| `cbedos`   | `gthe8`      | Christian Bedos    |
| `pbentot`  | `dool7`      | Pascal Bentot      |
| `lbioret`  | `nofa1`      | Luc Bioret         |
| `fbunisset`| `eSqi1`      | Francine Bunisset  |
| `bcacheux` | `ahre4`      | Bernard Cacheux    |
| `ccharoze` | `ault5`      | Catherine Charoze  |
| `cclepkens`| `zuwa4`      | Christophe Clepkens|
| `fdaburon` | `jaqu2`      | François Daburon   |

### Compte comptable

| Login | Mot de passe | Comptable |
|-------|--------------|-----------|
| `mvalette` | `comptable26` | Marie Valette |

Seul `dandre` possède des fiches de frais pré-remplies.

**Mots de passe.** Ceux des visiteurs sont stockés **en clair**, conformément au
sujet d'origine : ne pas réutiliser ce schéma en production. Celui du comptable
est en revanche **haché** (`password_hash` / `password_verify`) : la base ne
contient qu'une empreinte. Cette asymétrie est assumée et documentée dans
`COMPTE-RENDU.md` (décision 5.2, limite L27).

Fonctionnalités
---------------

| Route | URL | Rôle |
|-------|-----|------|
| `pg_gsb_frais_accueil` | `/` | redirige vers `/accueil` |
| `pg_gsb_frais_home` | `/accueil` | formulaire de connexion ou page d'accueil |
| `pg_gsb_frais_validerconnexion` | `/validerconnexion` | authentification (POST) |
| `pg_gsb_frais_deconnexion` | `/deconnexion` | vide la session |
| `pg_gsb_frais_listefrais` | `/listefrais` | consultation d'une fiche par mois |
| `pg_gsb_frais_saisirfrais` | `/saisirfrais` | saisie des frais du mois courant |
| `pg_gsb_frais_validerfraishorsforfais` | `/validerfraishorsforfait` | ajout d'un frais hors forfait (POST) |
| `pg_gsb_frais_supprimerfraishorsforfait` | `/supprimerfraishorsforfait/{id}` | suppression d'un frais hors forfait |
| `pg_gsb_frais_monvehicule` | `/monvehicule` | déclaration du véhicule du visiteur |
| `pg_gsb_frais_validermonvehicule` | `/validermonvehicule` | enregistrement du véhicule (POST) |
| `pg_gsb_frais_joindrejustificatif` | `/joindrejustificatif/{id}` | dépôt d'un justificatif sur une ligne hors forfait (POST) |
| `pg_gsb_frais_consulterjustificatif` | `/justificatif/{id}` | consultation d'un justificatif |
| `pg_gsb_frais_fichepdf` | `/fichepdf/{idVisiteur}/{mois}` | impression d'une fiche au format de l'annexe 1 |
| `pg_gsb_frais_fichespdfmois` | `/fichespdf/{mois}` | archive ZIP des fiches clôturées d'un mois (comptable ; sans argument : le mois précédent) |
| `pg_gsb_frais_validation` | `/validation` | choix du visiteur et du mois à valider (comptable) |
| `pg_gsb_frais_validationfiche` | `/validation/{idVisiteur}/{mois}` | détail de la fiche à valider |
| `pg_gsb_frais_validationcorriger` | `/validation/{idVisiteur}/{mois}/corriger` | correction des frais forfaitisés (POST) |
| `pg_gsb_frais_validationrefuser` | `/validation/{idVisiteur}/{mois}/refuser/{id}` | refus d'un frais hors forfait (POST) |
| `pg_gsb_frais_validationvalider` | `/validation/{idVisiteur}/{mois}/valider` | validation de la fiche (POST) |

> Le nom `pg_gsb_frais_validerfraishorsforfais` comporte une faute de frappe
> d'origine (`…forfai**s**`). Elle est conservée telle quelle : les templates
> l'utilisent et le sujet impose de ne pas réécrire l'existant.

### Remboursement kilométrique

Le tarif dépend du véhicule du visiteur (table `typevehicule`, de 0,52 à
0,72 €/km). Depuis la phase 10, le visiteur **ne le déclare plus lui-même** :
il envoie sa carte grise, et c'est le comptable qui y relève la puissance
fiscale et le carburant (voir *Carte grise*). Le tarif appliqué est **figé sur
chaque ligne de frais** (`lignefraisforfait.montantUnitaire`) : un changement
de barème ne recalcule jamais une fiche déjà clôturée. Un visiteur sans
véhicule enregistré est remboursé au tarif historique de 0,62 €/km.

### Justificatifs

Un frais hors forfait n'est remboursé que sur présentation de la facture. Le
visiteur peut joindre un fichier (**pdf, jpg, jpeg, png**, 2 Mo maximum) à
chaque ligne hors forfait, au moment de la saisie ou plus tard.

Les fichiers sont stockés dans **`app/justificatifs/`**, donc en dehors de
`web/` : ils ne sont jamais servis directement par le serveur web. Chaque
fichier reçoit un nom généré aléatoirement (32 caractères hexadécimaux) ; le
nom d'origine n'est conservé que pour l'affichage. La consultation passe
obligatoirement par `/justificatif/{id}`, qui vérifie côté serveur que le
demandeur est le propriétaire du frais (ou un comptable, à partir de la
phase 5). Ce dossier n'est pas versionné et **n'est pas inclus dans un
`mysqldump`** : une sauvegarde complète doit emporter la base *et* ce dossier.

Pour accepter des fichiers plus volumineux, modifier `upload_max_filesize` et
`post_max_size` dans `php.ini`, **puis** `justificatifs.taille_max` dans
`src/Pg/GsbFraisBundle/Resources/config/services.yml`.

### Carte grise

Les caractéristiques du véhicule déterminent le tarif kilométrique : les
laisser déclarer par le visiteur lui-même revenait à lui faire fixer son propre
remboursement, sans pièce justificative. Sur 1 000 km mensuels, l'écart entre
« 4 CV Diesel » (0,52 €/km) et « 7 CV et + Essence » (0,72) représente 200 €.

**Côté visiteur**, l'écran « Mon véhicule » ne comporte plus de liste
déroulante. Le visiteur y envoie sa carte grise (**pdf, jpg, jpeg, png**, 5 Mo
maximum) et y suit l'état de sa demande : *en attente*, *validée*, ou *refusée*
avec le motif. Après un refus, il peut envoyer un nouveau document ; tant qu'une
demande est en attente, il ne peut pas en déposer une seconde.

**Côté comptable**, « Cartes grises en attente » liste les demandes de la plus
ancienne à la plus récente. L'écran d'examen affiche le document dans la page —
`<object>` pour un PDF, `<img>` pour une image — à côté du formulaire de relevé.
Le comptable y saisit la **puissance fiscale et le carburant** (champs P.6 et
P.3 de la carte grise, seule information qui entre dans le calcul),
l'**immatriculation** (champ A) et la **marque et le modèle** (D.1 et D.3). Les
trois sont obligatoires : les deux dernières ne servent aucun calcul, mais
rattachent la décision à un véhicule identifié et attestent que le document a
été lu. Un refus exige un motif, transmis tel quel au visiteur.

**Les informations ne sont écrites qu'à la validation.** `visiteur.idTypeVehicule`
reste la seule colonne lue par les calculs de frais ; seul son écrivain a changé.
Tant qu'une demande est en attente, elle ne bouge pas, et les frais continuent
donc d'être calculés avec les dernières informations validées — sans qu'aucune
ligne de calcul ait eu à être modifiée. La validation répercute le nouveau tarif
sur la fiche **en cours de saisie uniquement** ; les fiches clôturées, validées
ou remboursées conservent le leur. Un refus ne change rien au tarif appliqué.

Les fichiers sont stockés dans **`app/cartesgrises/`**, donc hors de `web/`,
sous un nom généré aléatoirement (32 caractères hexadécimaux). Le **vrai type
du fichier est vérifié côté serveur** par `getMimeType()`, qui analyse le
contenu, et doit concorder avec l'extension annoncée : un script renommé
`carte.pdf` est refusé. La consultation passe obligatoirement par
`/cartegrise/{id}`, qui vérifie que le demandeur est **le visiteur concerné ou
un comptable** — sinon 404, et non 403, pour ne pas confirmer l'existence du
document. Comme `app/justificatifs/`, ce dossier n'est pas versionné et **n'est
pas inclus dans un `mysqldump`**.

Deux conséquences assumées : les documents des demandes refusées sont
**conservés** (c'est l'historique qui rend le refus vérifiable), et la
suppression d'un compte visiteur efface ses demandes en base — `ON DELETE
CASCADE` — mais **pas les fichiers sur le disque**.

### Impression des fiches

Chaque fiche s'imprime au format de l'annexe 1 du cahier des charges (en-tête
GSB, identité, frais forfaitisés avec montant unitaire, autres frais, total,
« Vu l'agent comptable »). Le **montant unitaire imprimé est celui historisé sur
la ligne** : réimprimer une fiche ancienne restitue le tarif de l'époque.

Commandes console :

```bash
# archive ZIP des fiches clôturées du mois précédent (une fiche = un PDF)
php app/console gsb:imprimer-fiches
php app/console gsb:imprimer-fiches 202608 --etat=CL --sortie=/tmp/fiches.zip

# jeu de données de test, pour les essais de volumétrie
php app/console gsb:generer-donnees --visiteurs=200 --mois=202608 --etat=CL
```

Les visiteurs de test portent un identifiant préfixé par `z`. Pour les retirer,
**dans cet ordre** (`fichefrais` référence `visiteur` sans `ON DELETE CASCADE`) :

```sql
DELETE FROM fichefrais WHERE idVisiteur LIKE 'z%';
DELETE FROM visiteur   WHERE id LIKE 'z%';
```

Les fiches sont produites **une par une** et écrites au fil de l'eau dans
l'archive : la mémoire ne contient jamais plus d'un PDF. Mesuré sur ce poste :
1 fiche → 30 Mo, 202 fiches → 38 Mo.

### Sécurité des formulaires

Toute action qui écrit en base ou sur le disque exige un **jeton anti-CSRF**
(`security.csrf.token_manager` de Symfony), avec une intention distincte par
formulaire. Neuf actions sont concernées : connexion, saisie des forfaits,
création / suppression d'un frais hors forfait, dépôt d'un justificatif,
déclaration du véhicule, et les trois actions du comptable.

La vérification est portée par `Controller/ControleurGsb.php`, dont héritent
tous les contrôleurs : une action d'écriture sans `verifierJeton()` se repère
immédiatement à la relecture.

La suppression d'un frais hors forfait se fait en **POST** et non plus en GET :
une balise `<img src="…/supprimerfraishorsforfait/7">` sur un site tiers
suffisait auparavant à supprimer un frais du visiteur connecté.

Trois POST ne portent pas de jeton, volontairement, parce qu'ils n'écrivent
rien : la sélection du mois en consultation, la sélection visiteur/mois côté
comptable, et la déconnexion.

### Sessions

Trois réglages protègent la session contre la **fixation** — attaque où un tiers
impose à la victime un identifiant de session qu'il connaît, puis s'en sert une
fois qu'elle s'est connectée :

| Réglage | Où | Effet |
|---------|-----|-------|
| `use_strict_mode: true` | `app/config/config.yml` | PHP refuse d'adopter un identifiant qu'il n'a pas émis |
| `$session->migrate(true)` | `fct.inc.php`, `connecterAvecRole()` | l'identifiant est régénéré à la connexion, l'ancien détruit |
| `$session->invalidate()` | `fct.inc.php`, `deconnecter()` | l'identifiant cesse d'être valable à la déconnexion |

`cookie_httponly: true` empêche par ailleurs la lecture du cookie par
JavaScript.

> **En production**, passer également `framework.session.cookie_secure` à
> `true` et servir le site en HTTPS : sinon le cookie de session circule en
> clair. Il est laissé à `false` pour permettre le développement en HTTP.

### Partie comptable

Le comptable se connecte par le **même formulaire** que les visiteurs ; c'est
son compte qui détermine son menu. Il choisit un visiteur et un mois, corrige
les quantités forfaitisées, refuse les frais hors forfait non justifiés,
consulte les factures jointes, puis valide la fiche — qui passe à l'état
« Validée », avec sa date de modification et son montant validé.

Les montants unitaires ne sont **jamais** modifiables : ils ont été figés à la
saisie et les changer réécrirait le passé.

#### Cycle de vie d'une fiche

```
Créée ──[fin de mois]──► Clôturée ──[validation comptable]──► Validée
                                                                 │
                                          [le 20 du mois] ───────┘
                                                 ▼
                                        Mise en paiement ──[paiement]──► Remboursée
```

Les trois transitions par lot sont des commandes console. Chacune ne traite que
les fiches se trouvant dans l'état de départ attendu : **les relancer est sans
effet et sans risque**.

```bash
php app/console gsb:cloturer-fiches       # Créée -> Clôturée
php app/console gsb:mettre-en-paiement    # Validée -> Mise en paiement
php app/console gsb:rembourser-fiches     # Mise en paiement -> Remboursée
```

Sans argument, chacune traite le mois précédent ; un mois `aaaamm` peut être
passé en argument.

La fiche du mois en cours est créée automatiquement à la première visite de
`/saisirfrais`, et la fiche du mois précédent passe alors de l'état `CR`
(saisie en cours) à `CL` (clôturée).

Architecture
------------

```
src/Pg/GsbFraisBundle/
├── Controller/
│   ├── HomeController.php          connexion / déconnexion / accueil
│   ├── ListeFraisController.php     consultation
│   ├── SaisirFraisController.php    saisie
│   └── include/fct.inc.php          fonctions utilitaires (dates, validation, session)
├── services/PdoGsb.php              accès aux données (PDO natif, service pg_gsb_frais.pdo)
└── Resources/
    ├── config/{routing,services}.yml
    ├── public/{styles,images}       assets copiés vers web/bundles/pggsbfrais/
    └── views/                       templates Twig
sql/bd_gsb.sql                       schéma + jeu de données
```

L'accès aux données passe uniquement par `PdoGsb`. **L'ORM Doctrine est
volontairement désactivée** dans `app/config/config.yml` : aucune entité n'est
mappée, et la version de `doctrine/orm` figée dans `composer.lock` (2.5) est
incompatible avec PHP 7.3+.
