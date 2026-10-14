
DROP DATABASE IF EXISTS bd_gsb;
CREATE DATABASE bd_gsb CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE bd_gsb;


CREATE TABLE etat (
    id      CHAR(2)     NOT NULL,
    libelle VARCHAR(30) NOT NULL,
    PRIMARY KEY (id)
) ENGINE=InnoDB;


CREATE TABLE fraisforfait (
    id      CHAR(3)       NOT NULL,
    libelle VARCHAR(20)   NOT NULL,
    montant DECIMAL(5, 2) NOT NULL,
    actif   TINYINT(1)    NOT NULL DEFAULT 1,
    PRIMARY KEY (id)
) ENGINE=InnoDB;


CREATE TABLE typevehicule (
    id      CHAR(3)       NOT NULL,
    libelle VARCHAR(30)   NOT NULL,
    montant DECIMAL(5, 2) NOT NULL,
    PRIMARY KEY (id)
) ENGINE=InnoDB;

CREATE TABLE visiteur (
    id           CHAR(4)     NOT NULL,
    nom          VARCHAR(30) NOT NULL,
    prenom       VARCHAR(30) NOT NULL,
    login        VARCHAR(20) NOT NULL,
  
    mdp          VARCHAR(255) NOT NULL,
    adresse      VARCHAR(30) DEFAULT NULL,
    cp           CHAR(5)     DEFAULT NULL,
    ville        VARCHAR(30) DEFAULT NULL,
    dateEmbauche DATE        DEFAULT NULL,
   
    idTypeVehicule CHAR(3)   DEFAULT NULL,
   
    actif          TINYINT(1) NOT NULL DEFAULT 1,
    mdpDoitChanger TINYINT(1) NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    UNIQUE KEY uk_visiteur_login (login),
    KEY fk_visiteur_typevehicule (idTypeVehicule),
    CONSTRAINT fk_visiteur_typevehicule FOREIGN KEY (idTypeVehicule) REFERENCES typevehicule (id)
) ENGINE=InnoDB;


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

CREATE TABLE fichefrais (
    idVisiteur       CHAR(4)        NOT NULL,
    mois             CHAR(6)        NOT NULL,
    nbJustificatifs  INT            NOT NULL DEFAULT 0,
    montantValide    DECIMAL(10, 2) NOT NULL DEFAULT 0,
    
    dateModif        DATE           NOT NULL,
    idEtat           CHAR(2)        NOT NULL DEFAULT 'CR',
    PRIMARY KEY (idVisiteur, mois),
    KEY fk_fichefrais_etat (idEtat),
    CONSTRAINT fk_fichefrais_visiteur FOREIGN KEY (idVisiteur) REFERENCES visiteur (id),
    CONSTRAINT fk_fichefrais_etat     FOREIGN KEY (idEtat)     REFERENCES etat (id)
) ENGINE=InnoDB;

CREATE TABLE lignefraisforfait (
    idVisiteur     CHAR(4) NOT NULL,
    mois           CHAR(6) NOT NULL,
    idFraisForfait CHAR(3) NOT NULL,
    quantite       INT     NOT NULL DEFAULT 0,
   
    montantUnitaire DECIMAL(5, 2) NOT NULL,
    PRIMARY KEY (idVisiteur, mois, idFraisForfait),
    KEY fk_lff_fraisforfait (idFraisForfait),
    CONSTRAINT fk_lff_fichefrais   FOREIGN KEY (idVisiteur, mois) REFERENCES fichefrais (idVisiteur, mois) ON DELETE CASCADE,
    CONSTRAINT fk_lff_fraisforfait FOREIGN KEY (idFraisForfait)   REFERENCES fraisforfait (id)
) ENGINE=InnoDB;


CREATE TABLE lignefraishorsforfait (
    id         INT            NOT NULL AUTO_INCREMENT,
    idVisiteur CHAR(4)        NOT NULL,
    mois       CHAR(6)        NOT NULL,
    libelle    VARCHAR(100)   NOT NULL,
    date       DATE           NOT NULL,
    montant    DECIMAL(10, 2) NOT NULL,
    
    nomFichier  VARCHAR(64)   DEFAULT NULL,
    nomOriginal VARCHAR(255)  DEFAULT NULL,
    typeMime    VARCHAR(100)  DEFAULT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uk_lhf_nomfichier (nomFichier),
    KEY fk_lhf_fichefrais (idVisiteur, mois),
    CONSTRAINT fk_lhf_fichefrais FOREIGN KEY (idVisiteur, mois) REFERENCES fichefrais (idVisiteur, mois) ON DELETE CASCADE
) ENGINE=InnoDB;


CREATE TABLE trajet (
    id         INT          NOT NULL AUTO_INCREMENT,
    idVisiteur CHAR(4)      NOT NULL,
    mois       CHAR(6)      NOT NULL,
    date       DATE         NOT NULL,
    libelle    VARCHAR(100) NOT NULL,
    distance   INT          NOT NULL,
    PRIMARY KEY (id),
    KEY fk_trajet_fichefrais (idVisiteur, mois),
    CONSTRAINT fk_trajet_fichefrais FOREIGN KEY (idVisiteur, mois)
        REFERENCES fichefrais (idVisiteur, mois) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO etat (id, libelle) VALUES
    ('CR', 'Fiche créée, saisie en cours'),
    ('CL', 'Saisie clôturée'),
    ('VA', 'Validée'),
    ('MP', 'Mise en paiement'),
    ('RB', 'Remboursée');

INSERT INTO fraisforfait (id, libelle, montant, actif) VALUES
    ('ETP', 'Forfait Etape',       110.00, 0),
    ('KM',  'Frais Kilométrique',    0.62, 1),
    ('NUI', 'Nuitée Hôtel',         80.00, 1),
    ('REP', 'Repas Restaurant',     25.00, 1);

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


INSERT INTO fichefrais (idVisiteur, mois, nbJustificatifs, montantValide, dateModif, idEtat) VALUES
    ('a17', '202606', 0, 1250.40, '2026-07-05', 'RB'),
    ('a17', '202607', 0,  842.00, '2026-08-04', 'VA'),
    ('a17', '202608', 0,    0.00, '2026-09-02', 'CL'),
    ('a55', '202608', 0,    0.00, '2026-09-03', 'CL');


INSERT INTO lignefraisforfait (idVisiteur, mois, idFraisForfait, quantite, montantUnitaire) VALUES
    ('a17', '202606', 'ETP', 3, 110.00), ('a17', '202606', 'KM', 480, 0.62),
    ('a17', '202606', 'NUI', 4,  80.00), ('a17', '202606', 'REP', 12, 25.00),
    ('a17', '202607', 'ETP', 2, 110.00), ('a17', '202607', 'KM', 310, 0.62),
    ('a17', '202607', 'NUI', 2,  80.00), ('a17', '202607', 'REP',  8, 25.00),
    ('a17', '202608', 'ETP', 1, 110.00), ('a17', '202608', 'KM', 225, 0.62),
    ('a17', '202608', 'NUI', 3,  80.00), ('a17', '202608', 'REP',  9, 25.00),
    ('a55', '202608', 'ETP', 0, 110.00), ('a55', '202608', 'KM', 120, 0.62),
    ('a55', '202608', 'NUI', 1,  80.00), ('a55', '202608', 'REP',  4, 25.00);

INSERT INTO trajet (idVisiteur, mois, date, libelle, distance) VALUES
    ('a17', '202606', '2026-06-04', 'Besançon -> Dijon, Dr Lemoine',        180),
    ('a17', '202606', '2026-06-11', 'Besançon -> Lyon, congrès régional',   220),
    ('a17', '202606', '2026-06-25', 'Tournée locale Besançon',               80),
    ('a17', '202607', '2026-07-09', 'Besançon -> Dijon, CHU',                96),
    ('a17', '202607', '2026-07-21', 'Besançon -> Mulhouse, Dr Keller',      214),
    ('a17', '202608', '2026-08-06', 'Besançon -> Belfort, cabinet Martin',  130),
    ('a17', '202608', '2026-08-20', 'Tournée Haut-Doubs',                    95),
    ('a55', '202608', '2026-08-18', 'Paris -> Orléans, Dr Rivière',         120);

INSERT INTO lignefraishorsforfait (idVisiteur, mois, libelle, date, montant) VALUES
    ('a17', '202606', 'Péage autoroute A36',        '2026-06-08',  18.40),
    ('a17', '202606', 'Invitation Dr. Lemoine',     '2026-06-21', 132.00),
    ('a17', '202607', 'Train Paris-Besançon',       '2026-07-12',  96.50),
    ('a17', '202608', 'Parking congrès de Dijon',   '2026-08-19',  24.00),
    ('a17', '202608', 'Documentation pharmacologie','2026-08-27',  57.90);



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


INSERT INTO administrateur (id, nom, prenom, login, mdp, actif, mdpDoitChanger) VALUES
    ('ad01', 'Dubois', 'Hélène', 'hdubois',
     '$2y$10$QwXIv65G8F/Pr/w2k.hkE.CPKcJUMs1OFph2XC40CKj/zgqS/Xi2m', 1, 1);

SELECT CONCAT(
    'Base reconstruite. ',
    (SELECT COUNT(*) FROM visiteur), ' visiteur(s) ont un mot de passe EN CLAIR. ',
    'Lancez maintenant : php app/console gsb:hacher-mots-de-passe'
) AS 'ACTION REQUISE';