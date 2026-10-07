

USE bd_gsb;


ALTER TABLE visiteur
    ADD COLUMN actif TINYINT(1) NOT NULL DEFAULT 1
        COMMENT '0 = compte désactivé : connexion refusée, données conservées';

ALTER TABLE comptable
    ADD COLUMN actif TINYINT(1) NOT NULL DEFAULT 1
        COMMENT ' 0 = compte désactivé : connexion refusée, données conservées';



ALTER TABLE visiteur
    ADD COLUMN mdpDoitChanger TINYINT(1) NOT NULL DEFAULT 0
        COMMENT '1 = changement de mot de passe imposé à la prochaine connexion';

ALTER TABLE comptable
    ADD COLUMN mdpDoitChanger TINYINT(1) NOT NULL DEFAULT 0
        COMMENT '1 = changement de mot de passe imposé à la prochaine connexion';



ALTER TABLE visiteur
    MODIFY COLUMN mdp VARCHAR(255) NOT NULL
        COMMENT 'Empreinte bcrypt (password_hash). Jamais le mot de passe en clair.';



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
    nomAdministrateur VARCHAR(61)  NOT NULL
                      COMMENT 'Recopié : le journal survit à la suppression du compte',
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


--
--    Mot de passe initial : AdminGsb2026!
--    Mot de passe modifié par l'administrateur : AdminGsb2026?


INSERT INTO administrateur (id, nom, prenom, login, mdp, actif, mdpDoitChanger) VALUES
    ('ad01', 'Dubois', 'Hélène', 'hdubois',
     '$2y$10$QwXIv65G8F/Pr/w2k.hkE.CPKcJUMs1OFph2XC40CKj/zgqS/Xi2m', 1, 1);



SELECT CONCAT(
    'Script 004 appliqué. ',
    (SELECT COUNT(*) FROM visiteur), ' visiteur(s) ont encore un mot de passe EN CLAIR. ',
    'Lancez maintenant : php app/console gsb:hacher-mots-de-passe'
) AS 'ACTION REQUISE';
