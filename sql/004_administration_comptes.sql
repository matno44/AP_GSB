-- ---------------------------------------------------------------------------
-- 004 - Interface d'administration des comptes (phase 8, hors cahier des charges)
--
-- Objet : permettre de gérer les comptes visiteurs, comptables et
--         administrateurs depuis l'application, sans écrire de SQL à la main
--         dans la base de production. Introduit le rôle administrateur, la
--         désactivation logique des comptes, et la traçabilité des actions
--         d'administration.
--
-- Lancement :
--   mysql -u root bd_gsb < sql/004_administration_comptes.sql
--   php app/console gsb:hacher-mots-de-passe      <-- OBLIGATOIRE ensuite
--
-- Aucun DROP de table, aucune suppression de données.
-- Script à jouer UNE SEULE FOIS.
--
-- ATTENTION - CE SCRIPT NE SUFFIT PAS À LUI SEUL. Il élargit la colonne
-- visiteur.mdp pour qu'elle puisse recevoir une empreinte bcrypt, mais il ne
-- peut pas produire ces empreintes : bcrypt n'existe pas en SQL, MySQL n'offre
-- que SHA2() et MD5(), inutilisables ici -- un mot de passe se hache avec un
-- grain de sel et un coût, pas avec un simple condensat. Le hachage des 209
-- mots de passe existants est donc fait par la commande
-- gsb:hacher-mots-de-passe, à lancer IMMÉDIATEMENT après ce script : entre les
-- deux, aucun visiteur ne peut se connecter.
-- ---------------------------------------------------------------------------

USE bd_gsb;

-- --------------------------------------------------------------------------
-- 1) Désactivation logique : la colonne « actif »
--
--    Pourquoi une colonne et non une suppression : 202 des 209 visiteurs ont
--    au moins une fiche de frais, et fk_fichefrais_visiteur est en RESTRICT.
--    Les supprimer est donc impossible sans détruire d'abord leurs fiches -- ce
--    qui effacerait en cascade (fk_lff_fichefrais et fk_lhf_fichefrais sont en
--    ON DELETE CASCADE) toutes leurs lignes de frais, y compris des montants
--    déjà validés, mis en paiement ou remboursés. Ce sont des pièces
--    comptables : elles se conservent.
--
--    Le besoin réel n'est de toute façon jamais « faire disparaître » mais
--    « empêcher de se connecter ». Un départ en retraite n'efface rien.
--
--    DEFAULT 1 : les 210 comptes existants restent actifs, aucune reprise de
--    données n'est nécessaire.
-- --------------------------------------------------------------------------

ALTER TABLE visiteur
    ADD COLUMN actif TINYINT(1) NOT NULL DEFAULT 1
        COMMENT '0 = compte désactivé : connexion refusée, données conservées';

ALTER TABLE comptable
    ADD COLUMN actif TINYINT(1) NOT NULL DEFAULT 1
        COMMENT ' 0 = compte désactivé : connexion refusée, données conservées';

-- --------------------------------------------------------------------------
-- 2) Changement de mot de passe imposé
--
--    Levé par l'administrateur à la création d'un compte et à chaque
--    réinitialisation. Tant qu'il vaut 1, l'utilisateur est authentifié mais
--    ne peut atteindre que l'écran de changement de mot de passe : sans cela,
--    un tiers -- l'administrateur -- connaîtrait durablement son mot de passe.
-- --------------------------------------------------------------------------

ALTER TABLE visiteur
    ADD COLUMN mdpDoitChanger TINYINT(1) NOT NULL DEFAULT 0
        COMMENT '1 = changement de mot de passe imposé à la prochaine connexion';

ALTER TABLE comptable
    ADD COLUMN mdpDoitChanger TINYINT(1) NOT NULL DEFAULT 0
        COMMENT '1 = changement de mot de passe imposé à la prochaine connexion';

-- --------------------------------------------------------------------------
-- 3) visiteur.mdp : de VARCHAR(30) en clair à VARCHAR(255) haché
--
--    La table livrée stocke les mots de passe des visiteurs EN CLAIR dans un
--    VARCHAR(30) -- 8 caractères au maximum sont réellement utilisés. Une
--    empreinte bcrypt fait 60 caractères : la colonne doit être élargie avant
--    toute tentative d'écriture, sinon MySQL tronquerait silencieusement
--    l'empreinte en mode non strict, et plus personne ne pourrait se connecter.
--
--    255 et non 60 : c'est la largeur recommandée par la documentation de
--    password_hash(), pour que la colonne survive à un changement d'algorithme
--    par défaut dans une version future de PHP.
--
--    Aucun visiteur ne perdra son mot de passe : on les possède en clair, la
--    commande gsb:hacher-mots-de-passe les hache tels quels.
-- --------------------------------------------------------------------------

ALTER TABLE visiteur
    MODIFY COLUMN mdp VARCHAR(255) NOT NULL
        COMMENT 'Empreinte bcrypt (password_hash). Jamais le mot de passe en clair.';

-- --------------------------------------------------------------------------
-- 4) La table administrateur
--
--    Troisième table de comptes, pour la même raison que comptable en phase 3 :
--    un administrateur n'est ni un visiteur ni un comptable. Il n'a ni fiche de
--    frais, ni véhicule, ni date d'embauche. L'ajouter à visiteur ferait
--    accepter à fk_fichefrais_visiteur d'associer une fiche de frais à un
--    administrateur, ce qui n'a aucun sens.
--
--    mdp en VARCHAR(255) dès l'origine : ce compte naît haché.
-- --------------------------------------------------------------------------

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

-- --------------------------------------------------------------------------
-- 5) Le journal d'administration
--
--    Les actions d'administration sont les seules de l'application à ne
--    laisser aucune trace dans les données. Une fiche validée garde son état
--    et sa date ; une désactivation ne fait que basculer un 0, et personne ne
--    pourrait dire qui a coupé l'accès de qui, ni quand.
--
--    Surtout : réinitialiser un mot de passe permet de se connecter à la place
--    du titulaire, donc -- pour un compte comptable -- de valider des fiches
--    en son nom. C'est le seul point de l'application où un utilisateur peut
--    usurper l'identité d'un autre. Sans journal, l'usurpation serait
--    indétectable ET indémontrable.
--
--    idAdministrateur n'est PAS une clé étrangère vers administrateur : le
--    journal doit survivre à la suppression du compte qui a agi. Une trace
--    qu'on peut effacer en supprimant son auteur ne prouve rien. Le nom est
--    donc recopié (nomAdministrateur), pas seulement référencé.
--
--    Aucun mot de passe, même temporaire, même haché, n'est écrit ici : une
--    table consultable depuis l'interface est une table qui finira exportée.
-- --------------------------------------------------------------------------

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

-- --------------------------------------------------------------------------
-- 6) La vue v_compte : lecture unifiée des trois tables
--
--    L'interface d'administration doit lister, filtrer, rechercher et paginer
--    les comptes des trois rôles ensemble. C'est un besoin de LECTURE : une
--    vue le satisfait sans toucher aux tables ni aux clés étrangères.
--
--    Les écritures, elles, restent dirigées vers la bonne table par le code :
--    une vue portant sur une UNION n'est pas modifiable, et c'est tant mieux
--    -- cela interdit structurellement d'écrire dans la mauvaise table.
--
--    Le rôle est une constante littérale par branche : c'est la table
--    d'origine qui le porte, pas une colonne à maintenir.
--
--    CAST en CHAR : sans lui, MySQL dimensionne la colonne « role » de la vue
--    sur la première branche de l'UNION, et les rôles plus longs des branches
--    suivantes seraient tronqués.
-- --------------------------------------------------------------------------

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

-- --------------------------------------------------------------------------
-- 7) Le premier administrateur
--
--    Son mot de passe est écrit dans ce fichier, donc versionné, donc connu de
--    quiconque lit le dépôt. C'est inévitable : il faut bien un premier compte
--    pour entrer dans l'interface. D'où mdpDoitChanger = 1 -- l'application le
--    forcera à en choisir un autre dès sa première connexion, et il ne pourra
--    atteindre aucun autre écran avant de l'avoir fait.
--
--    Mot de passe initial : AdminGsb2026!
--    L'empreinte ci-dessous a été produite par password_hash($mdp,
--    PASSWORD_BCRYPT). La lire ne permet pas de retrouver le mot de passe ;
--    c'est le commentaire au-dessus qui le divulgue, pas la colonne.
--
--    Matricule ad01 : préfixe « ad » + 2 chiffres. Les visiteurs générés
--    occupent déjà le préfixe « a » (a17, a55), et « ad » se distingue sans
--    ambiguïté dans le journal, où les trois familles de matricule voisinent.
-- --------------------------------------------------------------------------

INSERT INTO administrateur (id, nom, prenom, login, mdp, actif, mdpDoitChanger) VALUES
    ('ad01', 'Dubois', 'Hélène', 'hdubois',
     '$2y$10$QwXIv65G8F/Pr/w2k.hkE.CPKcJUMs1OFph2XC40CKj/zgqS/Xi2m', 1, 1);

-- --------------------------------------------------------------------------
-- 8) Rappel de l'étape obligatoire
-- --------------------------------------------------------------------------

SELECT CONCAT(
    'Script 004 appliqué. ',
    (SELECT COUNT(*) FROM visiteur), ' visiteur(s) ont encore un mot de passe EN CLAIR. ',
    'Lancez maintenant : php app/console gsb:hacher-mots-de-passe'
) AS 'ACTION REQUISE';
