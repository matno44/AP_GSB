-- ---------------------------------------------------------------------------
-- 003 - Comptable et cycle d'état complet
--
-- Objet : créer l'acteur « comptable » et compléter le cycle de vie de la
--         fiche de frais pour qu'il corresponde au diagramme du cahier des
--         charges : Créée -> Clôturée -> Validée -> Mise en paiement ->
--         Remboursée.
--
-- Lancement :
--   mysql -u root bd_gsb < sql/003_comptable_et_etats.sql
--
-- Aucun DROP, aucune suppression de données. Le script MODIFIE en revanche
-- le libellé d'un état existant : voir l'encadré du point 2.
-- Script à jouer UNE SEULE FOIS.
-- ---------------------------------------------------------------------------

USE bd_gsb;

-- --------------------------------------------------------------------------
-- 1) Le comptable, dans SA PROPRE table
--
--    Pourquoi pas une colonne « role » dans visiteur : un comptable n'est pas
--    un visiteur. Il n'a ni fiche de frais, ni véhicule, ni date d'embauche.
--    L'ajouter à la table visiteur créerait des lignes qui ne peuvent pas
--    être ce que la table prétend décrire, et la clé étrangère
--    fichefrais.idVisiteur accepterait alors d'associer une fiche de frais à
--    un comptable — ce qui n'a aucun sens.
--
--    mdp est en VARCHAR(255) et non VARCHAR(30) : il contient une EMPREINTE
--    produite par password_hash(), et non le mot de passe. 255 est la
--    longueur recommandée par la documentation PHP, pour rester compatible
--    avec les algorithmes futurs.
-- --------------------------------------------------------------------------
CREATE TABLE comptable (
    id     CHAR(4)      NOT NULL,
    nom    VARCHAR(30)  NOT NULL,
    prenom VARCHAR(30)  NOT NULL,
    login  VARCHAR(20)  NOT NULL,
    mdp    VARCHAR(255) NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uk_comptable_login (login)
) ENGINE=InnoDB;

-- Compte de démonstration : login « mvalette », mot de passe « comptable26 ».
-- La valeur stockée est l'empreinte, pas le mot de passe : même en lisant la
-- base, on ne peut pas retrouver « comptable26 ».
INSERT INTO comptable (id, nom, prenom, login, mdp) VALUES
    ('c01', 'Valette', 'Marie', 'mvalette', '$2y$10$TF7Ljwa95mygt.ProOl86OrlrWrk8TA2IBT6hc4cQHbG5Ho2C8gFy');

-- --------------------------------------------------------------------------
-- 2) Le cinquième état
--
--    Le diagramme du cahier des charges distingue « Validée » (le comptable a
--    contrôlé la fiche) de « Mise en paiement » (le virement est lancé, le 20
--    du mois). La base livrée n'avait que quatre états, dont un seul intitulé
--    « Validée et mise en paiement » qui confondait les deux.
--
--    ATTENTION, CECI MODIFIE UNE DONNÉE EXISTANTE : le libellé de 'VA'.
--    L'identifiant 'VA' ne change PAS, donc aucune fiche ne perd son état et
--    aucune clé étrangère n'est cassée. Seul le texte affiché est corrigé.
-- --------------------------------------------------------------------------
INSERT INTO etat (id, libelle) VALUES ('MP', 'Mise en paiement');

UPDATE etat SET libelle = 'Validée' WHERE id = 'VA';

-- --------------------------------------------------------------------------
-- Contrôles à passer après exécution
--
--   SELECT * FROM etat ORDER BY FIELD(id,'CR','CL','VA','MP','RB');  -- 5 lignes
--   SELECT id, login, LENGTH(mdp) FROM comptable;   -- empreinte de 60 caractères
-- --------------------------------------------------------------------------
