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
-- Migrations intégrées ici : 001 (puissance fiscale du véhicule).
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
    mdp          VARCHAR(30) NOT NULL,
    adresse      VARCHAR(30) DEFAULT NULL,
    cp           CHAR(5)     DEFAULT NULL,
    ville        VARCHAR(30) DEFAULT NULL,
    dateEmbauche DATE        DEFAULT NULL,
    -- NULL tant que le visiteur n'a pas déclaré son véhicule (migration 001) ;
    -- l'application retombe alors sur le tarif historique fraisforfait.KM.
    idTypeVehicule CHAR(3)   DEFAULT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uk_visiteur_login (login),
    KEY fk_visiteur_typevehicule (idTypeVehicule),
    CONSTRAINT fk_visiteur_typevehicule FOREIGN KEY (idTypeVehicule) REFERENCES typevehicule (id)
) ENGINE=InnoDB;

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
    PRIMARY KEY (id),
    KEY fk_lhf_fichefrais (idVisiteur, mois),
    CONSTRAINT fk_lhf_fichefrais FOREIGN KEY (idVisiteur, mois) REFERENCES fichefrais (idVisiteur, mois) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ===========================================================================
-- Jeu de données
-- ===========================================================================

INSERT INTO etat (id, libelle) VALUES
    ('CR', 'Fiche créée, saisie en cours'),
    ('CL', 'Saisie clôturée'),
    ('VA', 'Validée et mise en paiement'),
    ('RB', 'Remboursée');

INSERT INTO fraisforfait (id, libelle, montant) VALUES
    ('ETP', 'Forfait Etape',       110.00),
    ('KM',  'Frais Kilométrique',    0.62),
    ('NUI', 'Nuitée Hôtel',         80.00),
    ('REP', 'Repas Restaurant',     25.00);

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
INSERT INTO fichefrais (idVisiteur, mois, nbJustificatifs, montantValide, dateModif, idEtat) VALUES
    ('a17', '202606', 4, 1250.40, '2026-07-05', 'RB'),
    ('a17', '202607', 2,  842.00, '2026-08-04', 'VA'),
    ('a17', '202608', 3,    0.00, '2026-09-02', 'CL'),
    ('a55', '202608', 1,    0.00, '2026-09-03', 'CL');

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
