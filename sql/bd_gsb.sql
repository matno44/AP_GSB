-- ---------------------------------------------------------------------------
-- Base de données de l'application GSB - Suivi du remboursement des frais
--
-- Rechargement complet :
--   mysql -u root < sql/bd_gsb.sql
--
-- Le schéma reprend exactement les noms de tables, de colonnes et d'alias
-- attendus par Pg\GsbFraisBundle\services\PdoGsb.
--
-- Ce fichier est tenu à jour avec les migrations du dossier sql/ : il doit
-- toujours produire le MÊME schéma qu'une base créée à l'origine puis migrée.
-- Migrations intégrées ici : 001 (puissance fiscale du véhicule),
--                            002 (justificatifs des frais hors forfait),
--                            003 (comptable et cycle d'état complet),
--                            004 (administration des comptes).
--
-- APRÈS ce script, lancer OBLIGATOIREMENT :
--   php app/console gsb:hacher-mots-de-passe
-- Les mots de passe visiteur du jeu de données sont écrits en clair ci-dessous
-- -- ils viennent du sujet et doivent rester reconnaissables à la lecture --
-- mais la colonne attend une empreinte bcrypt, que seul PHP sait produire.
-- Entre les deux commandes, aucun visiteur ne peut se connecter.
-- ---------------------------------------------------------------------------

DROP DATABASE IF EXISTS bd_gsb;
CREATE DATABASE bd_gsb CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE bd_gsb;

-- --------------------------------------------------------- etat
CREATE TABLE etat (
    id      CHAR(2)     NOT NULL,
    libelle VARCHAR(30) NOT NULL,
    PRIMARY KEY (id)
) ENGINE=InnoDB;

-- --------------------------------------------------------- fraisforfait
CREATE TABLE fraisforfait (
    id      CHAR(3)       NOT NULL,
    libelle VARCHAR(20)   NOT NULL,
    montant DECIMAL(5, 2) NOT NULL,
    PRIMARY KEY (id)
) ENGINE=InnoDB;

-- --------------------------------------------------------- typevehicule
-- Barème de remboursement kilométrique (migration 001). Stocké en base et non
-- codé en dur : la direction peut le faire évoluer sans toucher au code.
CREATE TABLE typevehicule (
    id      CHAR(3)       NOT NULL,
    libelle VARCHAR(30)   NOT NULL,
    montant DECIMAL(5, 2) NOT NULL,
    PRIMARY KEY (id)
) ENGINE=InnoDB;

-- --------------------------------------------------------- visiteur
CREATE TABLE visiteur (
    id           CHAR(4)     NOT NULL,
    nom          VARCHAR(30) NOT NULL,
    prenom       VARCHAR(30) NOT NULL,
    login        VARCHAR(20) NOT NULL,
    -- VARCHAR(255) depuis la migration 004 : la colonne contient une EMPREINTE
    -- bcrypt (60 caractères) et non plus le mot de passe en clair. 255 est la
    -- largeur recommandée par la documentation de password_hash(), pour
    -- survivre à un changement d'algorithme par défaut dans PHP.
    mdp          VARCHAR(255) NOT NULL,
    adresse      VARCHAR(30) DEFAULT NULL,
    cp           CHAR(5)     DEFAULT NULL,
    ville        VARCHAR(30) DEFAULT NULL,
    dateEmbauche DATE        DEFAULT NULL,
    -- NULL tant que le visiteur n'a pas déclaré son véhicule (migration 001) ;
    -- l'application retombe alors sur le tarif historique fraisforfait.KM.
    idTypeVehicule CHAR(3)   DEFAULT NULL,
    -- Désactivation logique (migration 004). 202 des 209 visiteurs ont des
    -- fiches de frais et fk_fichefrais_visiteur est en RESTRICT : les supprimer
    -- est impossible sans détruire leur historique comptable. On coupe donc
    -- l'accès sans rien effacer.
    actif          TINYINT(1) NOT NULL DEFAULT 1,
    mdpDoitChanger TINYINT(1) NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    UNIQUE KEY uk_visiteur_login (login),
    KEY fk_visiteur_typevehicule (idTypeVehicule),
    CONSTRAINT fk_visiteur_typevehicule FOREIGN KEY (idTypeVehicule) REFERENCES typevehicule (id)
) ENGINE=InnoDB;

-- --------------------------------------------------------- comptable
-- Le comptable a sa propre table (migration 003) : il n'a ni fiche de frais,
-- ni véhicule, ni date d'embauche. L'ajouter à visiteur permettrait à la clé
-- étrangère fichefrais.idVisiteur de lui associer une fiche de frais.
-- mdp contient une EMPREINTE password_hash(), d'où VARCHAR(255).
CREATE TABLE comptable (
    id     CHAR(4)      NOT NULL,
    nom    VARCHAR(30)  NOT NULL,
    prenom VARCHAR(30)  NOT NULL,
    login  VARCHAR(20)  NOT NULL,
    mdp    VARCHAR(255) NOT NULL,
    actif          TINYINT(1) NOT NULL DEFAULT 1,
    mdpDoitChanger TINYINT(1) NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    UNIQUE KEY uk_comptable_login (login)
) ENGINE=InnoDB;

-- --------------------------------------------------------- administrateur
-- Troisième table de comptes (migration 004), pour la même raison que
-- comptable : un administrateur n'a ni fiche de frais, ni véhicule, ni date
-- d'embauche. Il ne gère que des comptes.
CREATE TABLE administrateur (
    id             CHAR(4)      NOT NULL,
    nom            VARCHAR(30)  NOT NULL,
    prenom         VARCHAR(30)  NOT NULL,
    login          VARCHAR(20)  NOT NULL,
    mdp            VARCHAR(255) NOT NULL
                   COMMENT 'Empreinte bcrypt (password_hash)',
    actif          TINYINT(1)   NOT NULL DEFAULT 1,
    mdpDoitChanger TINYINT(1)   NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    UNIQUE KEY uk_administrateur_login (login)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------- journaladmin
-- Traçabilité des actions d'administration (migration 004).
--
-- Ces actions sont les seules de l'application à ne laisser aucune trace dans
-- les données : une désactivation ne fait que basculer un 0. Surtout,
-- réinitialiser un mot de passe permet de se connecter à la place du titulaire
-- -- pour un compte comptable, de valider des fiches en son nom. Sans journal,
-- l'usurpation serait indétectable ET indémontrable.
--
-- idAdministrateur n'est PAS une clé étrangère : le journal doit survivre à la
-- suppression du compte qui a agi. Une trace qu'on efface en supprimant son
-- auteur ne prouve rien. D'où la recopie des noms.
CREATE TABLE journaladmin (
    id                INT          NOT NULL AUTO_INCREMENT,
    horodatage        DATETIME     NOT NULL,
    idAdministrateur  CHAR(4)      NOT NULL,
    nomAdministrateur VARCHAR(61)  NOT NULL,
    action            VARCHAR(40)  NOT NULL,
    roleCible         VARCHAR(15)  NOT NULL,
    idCible           CHAR(4)      NOT NULL,
    nomCible          VARCHAR(61)  NOT NULL,
    detail            VARCHAR(255) DEFAULT NULL,
    PRIMARY KEY (id),
    KEY idx_journal_horodatage (horodatage),
    KEY idx_journal_cible (roleCible, idCible),
    KEY idx_journal_auteur (idAdministrateur)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- --------------------------------------------------------- fichefrais
CREATE TABLE fichefrais (
    idVisiteur       CHAR(4)        NOT NULL,
    mois             CHAR(6)        NOT NULL,
    nbJustificatifs  INT            NOT NULL DEFAULT 0,
    montantValide    DECIMAL(10, 2) NOT NULL DEFAULT 0,
    -- volontairement DATE et non DATETIME : dateAnglaisVersFrancais()
    -- découpe la valeur sur '-' et ne sait pas gérer une partie horaire.
    dateModif        DATE           NOT NULL,
    idEtat           CHAR(2)        NOT NULL DEFAULT 'CR',
    PRIMARY KEY (idVisiteur, mois),
    KEY fk_fichefrais_etat (idEtat),
    CONSTRAINT fk_fichefrais_visiteur FOREIGN KEY (idVisiteur) REFERENCES visiteur (id),
    CONSTRAINT fk_fichefrais_etat     FOREIGN KEY (idEtat)     REFERENCES etat (id)
) ENGINE=InnoDB;

-- --------------------------------------------------------- lignefraisforfait
CREATE TABLE lignefraisforfait (
    idVisiteur     CHAR(4) NOT NULL,
    mois           CHAR(6) NOT NULL,
    idFraisForfait CHAR(3) NOT NULL,
    quantite       INT     NOT NULL DEFAULT 0,
    -- Historisation (migration 001) : montant unitaire figé à la création de la
    -- ligne, pour qu'un changement de barème ne recalcule pas les fiches
    -- passées. Pas de valeur par défaut, volontairement : une insertion qui
    -- l'oublierait doit échouer au lieu de produire un total à 0.
    montantUnitaire DECIMAL(5, 2) NOT NULL,
    PRIMARY KEY (idVisiteur, mois, idFraisForfait),
    KEY fk_lff_fraisforfait (idFraisForfait),
    CONSTRAINT fk_lff_fichefrais   FOREIGN KEY (idVisiteur, mois) REFERENCES fichefrais (idVisiteur, mois) ON DELETE CASCADE,
    CONSTRAINT fk_lff_fraisforfait FOREIGN KEY (idFraisForfait)   REFERENCES fraisforfait (id)
) ENGINE=InnoDB;

-- --------------------------------------------------------- lignefraishorsforfait
-- L'ordre des colonnes est significatif : creeNouveauFraisHorsForfait()
-- insère en nommant les colonnes, mais les exports/imports historiques
-- utilisent cet ordre.
CREATE TABLE lignefraishorsforfait (
    id         INT            NOT NULL AUTO_INCREMENT,
    idVisiteur CHAR(4)        NOT NULL,
    mois       CHAR(6)        NOT NULL,
    libelle    VARCHAR(100)   NOT NULL,
    date       DATE           NOT NULL,
    montant    DECIMAL(10, 2) NOT NULL,
    -- Justificatif (migration 002). Les trois colonnes sont NULL : le frais
    -- peut exister sans facture, le visiteur pouvant la joindre plus tard.
    -- nomFichier est le nom RÉEL sur le disque, généré aléatoirement ;
    -- nomOriginal est le nom affiché, jamais utilisé comme chemin.
    nomFichier  VARCHAR(64)   DEFAULT NULL,
    nomOriginal VARCHAR(255)  DEFAULT NULL,
    typeMime    VARCHAR(100)  DEFAULT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uk_lhf_nomfichier (nomFichier),
    KEY fk_lhf_fichefrais (idVisiteur, mois),
    CONSTRAINT fk_lhf_fichefrais FOREIGN KEY (idVisiteur, mois) REFERENCES fichefrais (idVisiteur, mois) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ===========================================================================
-- Jeu de données
-- ===========================================================================

-- Cinq états (migration 003), conformes au diagramme du cahier des charges :
-- « Validée » (le comptable a contrôlé) et « Mise en paiement » (le virement
-- est lancé, le 20 du mois) sont deux étapes distinctes.
INSERT INTO etat (id, libelle) VALUES
    ('CR', 'Fiche créée, saisie en cours'),
    ('CL', 'Saisie clôturée'),
    ('VA', 'Validée'),
    ('MP', 'Mise en paiement'),
    ('RB', 'Remboursée');

INSERT INTO fraisforfait (id, libelle, montant) VALUES
    ('ETP', 'Forfait Etape',       110.00),
    ('KM',  'Frais Kilométrique',    0.62),
    ('NUI', 'Nuitée Hôtel',         80.00),
    ('REP', 'Repas Restaurant',     25.00);

-- Compte comptable de démonstration : login « mvalette », mot de passe
-- « comptable26 ». La valeur stockée est l'empreinte produite par
-- password_hash() : la lire ne permet pas de retrouver le mot de passe.
INSERT INTO comptable (id, nom, prenom, login, mdp) VALUES
    ('c01', 'Valette', 'Marie', 'mvalette', '$2y$10$TF7Ljwa95mygt.ProOl86OrlrWrk8TA2IBT6hc4cQHbG5Ho2C8gFy'),
    ('c02', 'test', 'test', 'test', '$2y$10$pK0WJqMg2ha7ZFhVER/IOepbGwmZtkKmLIYLidytdiKsgY70f6jaC');
    
INSERT INTO typevehicule (id, libelle, montant) VALUES
    ('D4',  '4 CV Diesel',       0.52),
    ('D56', '5/6 CV Diesel',     0.58),
    ('D7',  '7 CV et + Diesel',  0.65),
    ('E4',  '4 CV Essence',      0.62),
    ('E56', '5/6 CV Essence',    0.67),
    ('E7',  '7 CV et + Essence', 0.72);

-- Les visiteurs sont créés sans véhicule déclaré (idTypeVehicule NULL) :
-- c'est l'état réel de la reprise, chacun renseignera le sien via
-- l'écran « Mon véhicule ».
INSERT INTO visiteur (id, nom, prenom, login, mdp, adresse, cp, ville, dateEmbauche) VALUES
    ('a17', 'Andre',     'David',    'dandre',   'oppg5', '1 rue Petit',          '25000', 'Besancon',  '2000-11-13'),
    ('a55', 'Bedos',     'Christian','cbedos',   'gthe8', '3 rue Blaise Pascal',  '75008', 'Paris',     '1998-05-26'),
    ('b13', 'Bentot',    'Pascal',   'pbentot',  'dool7', '5 avenue des Lilas',   '69003', 'Lyon',      '2001-03-01'),
    ('b16', 'Bioret',    'Luc',      'lbioret',  'nofa1', '9 place de la Gare',   '44000', 'Nantes',    '1998-11-17'),
    ('b19', 'Bunisset',  'Francine', 'fbunisset','eSqi1', '4 rue du Stade',       '21000', 'Dijon',     '2001-10-14'),
    ('b25', 'Cacheux',   'Bernard',  'bcacheux', 'ahre4', '12 rue Victor Hugo',   '59000', 'Lille',     '1999-02-08'),
    ('b28', 'Charoze',   'Catherine','ccharoze', 'ault5', '7 rue des Ecoles',     '35000', 'Rennes',    '2000-06-19'),
    ('b34', 'Clepkens',  'Christophe','cclepkens','zuwa4','2 impasse Verte',      '67000', 'Strasbourg','2002-01-07'),
    ('b50', 'Daburon',   'François', 'fdaburon', 'jaqu2', '18 rue de la Poste',   '33000', 'Bordeaux',  '1997-09-15');

-- ---------------------------------------------------------------------------
-- Fiches de frais du visiteur a17 (David Andre) — sert de compte de démo.
-- Un mois clôturé, un mois validé, et un mois remboursé.
-- Le mois courant est créé automatiquement par l'application à la
-- première visite de la page « Saisie fiche de frais ».
-- ---------------------------------------------------------------------------
-- nbJustificatifs vaut 0 : ce compteur désigne le nombre de lignes hors
-- forfait accompagnées d'un justificatif TÉLÉVERSÉ (migration 002), et le jeu
-- de démonstration n'en contient aucun.
INSERT INTO fichefrais (idVisiteur, mois, nbJustificatifs, montantValide, dateModif, idEtat) VALUES
    ('a17', '202606', 0, 1250.40, '2026-07-05', 'RB'),
    ('a17', '202607', 0,  842.00, '2026-08-04', 'VA'),
    ('a17', '202608', 0,    0.00, '2026-09-02', 'CL'),
    ('a55', '202608', 0,    0.00, '2026-09-03', 'CL');

-- Le montant unitaire est historisé sur la ligne (migration 001). Les fiches
-- de démonstration sont antérieures au barème par véhicule : elles portent
-- donc le tarif kilométrique historique de 0,62 €/km.
INSERT INTO lignefraisforfait (idVisiteur, mois, idFraisForfait, quantite, montantUnitaire) VALUES
    ('a17', '202606', 'ETP', 3, 110.00), ('a17', '202606', 'KM', 480, 0.62),
    ('a17', '202606', 'NUI', 4,  80.00), ('a17', '202606', 'REP', 12, 25.00),
    ('a17', '202607', 'ETP', 2, 110.00), ('a17', '202607', 'KM', 310, 0.62),
    ('a17', '202607', 'NUI', 2,  80.00), ('a17', '202607', 'REP',  8, 25.00),
    ('a17', '202608', 'ETP', 1, 110.00), ('a17', '202608', 'KM', 225, 0.62),
    ('a17', '202608', 'NUI', 3,  80.00), ('a17', '202608', 'REP',  9, 25.00),
    ('a55', '202608', 'ETP', 0, 110.00), ('a55', '202608', 'KM', 120, 0.62),
    ('a55', '202608', 'NUI', 1,  80.00), ('a55', '202608', 'REP',  4, 25.00);

INSERT INTO lignefraishorsforfait (idVisiteur, mois, libelle, date, montant) VALUES
    ('a17', '202606', 'Péage autoroute A36',        '2026-06-08',  18.40),
    ('a17', '202606', 'Invitation Dr. Lemoine',     '2026-06-21', 132.00),
    ('a17', '202607', 'Train Paris-Besançon',       '2026-07-12',  96.50),
    ('a17', '202608', 'Parking congrès de Dijon',   '2026-08-19',  24.00),
    ('a17', '202608', 'Documentation pharmacologie','2026-08-27',  57.90);

-- ===========================================================================
-- Vue de lecture unifiée des comptes (migration 004)
--
-- L'interface d'administration liste, filtre et pagine les trois rôles
-- ensemble. C'est un besoin de LECTURE : une vue le satisfait sans toucher aux
-- tables ni aux clés étrangères. Les écritures restent dirigées vers la bonne
-- table par le code -- une vue sur une UNION n'est pas modifiable, ce qui
-- interdit structurellement d'écrire dans la mauvaise table.
--
-- COLLATE explicite sur les littéraux : ils prendraient sinon la collation de
-- CONNEXION, quand les colonnes portent celle de la BASE, et l'UNION échouerait
-- sur « Illegal mix of collations ».
-- ===========================================================================

CREATE OR REPLACE VIEW v_compte AS
    SELECT v.id                              AS id,
           v.nom                             AS nom,
           v.prenom                          AS prenom,
           v.login                           AS login,
           CAST('visiteur' AS CHAR(15)) COLLATE utf8mb4_unicode_ci AS role,
           v.actif                           AS actif,
           v.mdpDoitChanger                  AS mdpDoitChanger,
           v.dateEmbauche                    AS dateEmbauche
      FROM visiteur v
    UNION ALL
    SELECT c.id, c.nom, c.prenom, c.login,
           CAST('comptable' AS CHAR(15)) COLLATE utf8mb4_unicode_ci, c.actif, c.mdpDoitChanger, NULL
      FROM comptable c
    UNION ALL
    SELECT a.id, a.nom, a.prenom, a.login,
           CAST('administrateur' AS CHAR(15)) COLLATE utf8mb4_unicode_ci, a.actif, a.mdpDoitChanger, NULL
      FROM administrateur a;

-- Premier administrateur. Mot de passe initial : AdminGsb2026!
-- Il est écrit ici, donc versionné, donc connu de quiconque lit le dépôt :
-- inévitable, il faut bien un premier compte pour entrer. D'où
-- mdpDoitChanger = 1, qui force son remplacement dès la première connexion.
INSERT INTO administrateur (id, nom, prenom, login, mdp, actif, mdpDoitChanger) VALUES
    ('ad01', 'Dubois', 'Hélène', 'hdubois',
     '$2y$10$QwXIv65G8F/Pr/w2k.hkE.CPKcJUMs1OFph2XC40CKj/zgqS/Xi2m', 1, 1);

SELECT CONCAT(
    'Base reconstruite. ',
    (SELECT COUNT(*) FROM visiteur), ' visiteur(s) ont un mot de passe EN CLAIR. ',
    'Lancez maintenant : php app/console gsb:hacher-mots-de-passe'
) AS 'ACTION REQUISE';