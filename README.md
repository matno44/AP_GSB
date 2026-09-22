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

Le script crée la base `bd_gsb`, ses sept tables et un jeu de données de
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
php -S 127.0.0.1:8000 -t web web/app_dev.php
```

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

Seul `dandre` possède des fiches de frais pré-remplies. Les mots de passe sont
stockés en clair, conformément au sujet d'origine : ne pas réutiliser ce schéma
en production.

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

> Le nom `pg_gsb_frais_validerfraishorsforfais` comporte une faute de frappe
> d'origine (`…forfai**s**`). Elle est conservée telle quelle : les templates
> l'utilisent et le sujet impose de ne pas réécrire l'existant.

### Remboursement kilométrique

Le tarif dépend du véhicule du visiteur (table `typevehicule`, de 0,52 à
0,72 €/km). Le visiteur le déclare via « Mon véhicule ». Le tarif appliqué est
**figé sur chaque ligne de frais** (`lignefraisforfait.montantUnitaire`) : un
changement de barème ne recalcule jamais une fiche déjà clôturée. Un visiteur
qui n'a pas déclaré de véhicule est remboursé au tarif historique de 0,62 €/km.

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
