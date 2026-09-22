-- ---------------------------------------------------------------------------
-- 001 - Prise en compte de la puissance fiscale du véhicule
--
-- Objet : indexer le remboursement kilométrique sur le véhicule du visiteur,
--         sans recalculer les fiches de frais déjà saisies.
--
-- Lancement :
--   mysql -u root bd_gsb < sql/001_puissance_fiscale_vehicule.sql
--
-- Script NON destructif : aucun DROP, aucun DELETE, aucun TRUNCATE.
-- Script à jouer UNE SEULE FOIS : MySQL ne connaît pas
-- « ALTER TABLE ... ADD COLUMN IF NOT EXISTS ». Un second passage s'arrêtera
-- sur l'erreur 1060 « Duplicate column name », sans rien abîmer.
-- ---------------------------------------------------------------------------

USE bd_gsb;

-- Pas de START TRANSACTION ici, et c'est volontaire : sous MySQL, toute
-- instruction DDL (CREATE, ALTER) provoque une validation implicite. Encadrer
-- ce script d'une transaction donnerait l'illusion d'un « tout ou rien » qui
-- n'existe pas. En cas d'échec en cours de route, on revient à l'état initial
-- par la sauvegarde : mysql -u root < sauvegardes/bd_gsb_avant_phase1.sql
-- --------------------------------------------------------------------------
-- 1) Le barème, stocké en base et non codé en dur dans l'application.
--    Même forme que la table fraisforfait (id CHAR(3), libelle, montant),
--    pour rester cohérent avec le schéma du prédécesseur.
-- --------------------------------------------------------------------------
CREATE TABLE typevehicule (
    id      CHAR(3)       NOT NULL,
    libelle VARCHAR(30)   NOT NULL,
    -- tarif de remboursement en euros par kilomètre
    montant DECIMAL(5, 2) NOT NULL,
    PRIMARY KEY (id)
) ENGINE=InnoDB;

INSERT INTO typevehicule (id, libelle, montant) VALUES
    ('D4',  '4 CV Diesel',       0.52),
    ('D56', '5/6 CV Diesel',     0.58),
    ('D7',  '7 CV et + Diesel',  0.65),
    ('E4',  '4 CV Essence',      0.62),
    ('E56', '5/6 CV Essence',    0.67),
    ('E7',  '7 CV et + Essence', 0.72);

-- --------------------------------------------------------------------------
-- 2) Rattachement d'un visiteur à un type de véhicule.
--    NULL est autorisé : les 9 visiteurs existants n'ont pas encore déclaré
--    leur véhicule, et on ne peut pas inventer cette information à leur place.
--    Tant que la colonne vaut NULL, l'application retombe sur le tarif
--    historique de fraisforfait.KM (0,62 €/km).
-- --------------------------------------------------------------------------
ALTER TABLE visiteur
    ADD COLUMN idTypeVehicule CHAR(3) DEFAULT NULL AFTER dateEmbauche,
    ADD KEY fk_visiteur_typevehicule (idTypeVehicule),
    ADD CONSTRAINT fk_visiteur_typevehicule
        FOREIGN KEY (idTypeVehicule) REFERENCES typevehicule (id);

-- --------------------------------------------------------------------------
-- 3) HISTORISATION - le point clé de cette phase.
--    On enregistre sur CHAQUE ligne de frais forfaitisé le montant unitaire
--    appliqué au moment de la saisie. Sans cette colonne, le montant d'une
--    ligne ne peut être obtenu qu'en lisant le barème courant : changer le
--    barème réécrirait le passé.
--    C'est aussi la colonne « Montant unitaire » exigée par l'annexe 1 du
--    cahier des charges sur la fiche imprimée.
--
--    La colonne est créée NULL pour pouvoir remplir l'existant, puis passée
--    en NOT NULL : ainsi une future insertion qui oublierait le montant
--    unitaire échouera bruyamment au lieu de produire un total à 0.
-- --------------------------------------------------------------------------
ALTER TABLE lignefraisforfait
    ADD COLUMN montantUnitaire DECIMAL(5, 2) DEFAULT NULL AFTER quantite;

-- Reprise de l'existant : aucun historique de barème n'a jamais été conservé,
-- la seule valeur disponible pour les fiches déjà saisies est le barème
-- courant. C'est une hypothèse explicite, valable ici parce que les tarifs
-- n'ont jamais changé depuis la création de la base.
UPDATE lignefraisforfait lff
    INNER JOIN fraisforfait ff ON ff.id = lff.idFraisForfait
    SET lff.montantUnitaire = ff.montant
    WHERE lff.montantUnitaire IS NULL;

ALTER TABLE lignefraisforfait
    MODIFY COLUMN montantUnitaire DECIMAL(5, 2) NOT NULL;

-- --------------------------------------------------------------------------
-- Contrôles à passer après exécution (doivent tous renvoyer 0 ligne en écart)
--
--   SELECT * FROM typevehicule;                       -- 6 lignes
--   SELECT COUNT(*) FROM lignefraisforfait
--       WHERE montantUnitaire IS NULL OR montantUnitaire = 0;   -- 0
--   SELECT idVisiteur, mois, idFraisForfait, quantite, montantUnitaire,
--          quantite * montantUnitaire AS total
--     FROM lignefraisforfait ORDER BY idVisiteur, mois;
-- --------------------------------------------------------------------------
