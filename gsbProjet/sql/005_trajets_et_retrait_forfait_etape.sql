-- ---------------------------------------------------------------------------
-- 005 - Détail des trajets, et retrait du forfait étape (phase 9)
--
-- Objet : deux changements liés au frais kilométrique.
--
--   1) RETRAIT DU FORFAIT ÉTAPE. Son montant (110,00 €) est arithmétiquement
--      celui d'une nuitée (80,00) plus un repas (25,00), à 5 € près. Les quatre
--      postes se recouvraient donc, sans qu'aucune règle écrite ne dise si l'on
--      peut cumuler une étape et une nuitée pour la même nuit -- ce qui
--      revenait à payer deux fois le coucher. Le forfait est retiré de l'usage.
--
--   2) DÉTAIL DES TRAJETS. Jusqu'ici le visiteur devait additionner lui-même
--      toutes ses distances du mois pour n'en saisir qu'un total. C'était une
--      addition à la main, source d'erreur, et surtout invérifiable : le
--      cahier des charges demande au comptable de contrôler que « la distance
--      kilométrique est plausible », or devant un total de 480 km il n'a rien
--      à quoi la confronter. Chaque trajet est désormais saisi séparément.
--
-- Lancement :
--   mysql -u root bd_gsb < sql/005_trajets_et_retrait_forfait_etape.sql
--
-- Aucun DROP de table. AUCUNE SUPPRESSION DE DONNÉES : voir l'encadré du
-- point 1, le retrait du forfait étape est une désactivation.
-- Script à jouer UNE SEULE FOIS.
-- ---------------------------------------------------------------------------

USE bd_gsb;

-- --------------------------------------------------------------------------
-- 1) Retrait du forfait étape : une désactivation, pas une suppression
--
--    POURQUOI NE PAS SUPPRIMER. Mesuré avant de décider : 209 lignes de frais
--    portent ce forfait, pour 469 étapes et 51 590,00 €. Et 990,00 € de ce
--    total reposent sur des fiches déjà VALIDÉES ou REMBOURSÉES.
--
--    Or fichefrais.montantValide est un montant FIGÉ au moment de la
--    validation (phase 5), et il inclut ces étapes. Exemple vérifié :
--    a17 / 202608, à l'état « Remboursée », porte montantValide = 710,60 €,
--    soit exactement la somme de ses lignes, dont 110,00 € d'étape. Supprimer
--    la ligne laisserait une fiche remboursée affirmant un montant que ses
--    lignes ne justifient plus : 710,60 € versés pour 600,60 € justifiés.
--
--    C'est le même raisonnement que la décision 8.2, appliqué cette fois au
--    référentiel et non aux comptes : le besoin réel n'est pas « faire
--    disparaître », c'est « ne plus proposer ». Une colonne actif y répond
--    sans rien détruire.
--
--    DEFAULT 1 : les trois autres forfaits restent actifs sans reprise.
-- --------------------------------------------------------------------------

ALTER TABLE fraisforfait
    ADD COLUMN actif TINYINT(1) NOT NULL DEFAULT 1
        COMMENT '0 = retiré de l usage : absent des nouvelles fiches, historique conservé';

UPDATE fraisforfait SET actif = 0 WHERE id = 'ETP';

-- --------------------------------------------------------------------------
-- 2) La table trajet
--
--    Même forme que lignefraishorsforfait, pour la même raison : plusieurs
--    lignes par fiche, donc une table à part avec sa propre clé technique.
--    lignefraisforfait ne pouvait pas convenir, sa clé primaire étant
--    (idVisiteur, mois, idFraisForfait) -- une seule ligne par forfait et par
--    mois, par construction.
--
--    La clé étrangère porte sur le COUPLE (idVisiteur, mois) et non sur le
--    seul visiteur : un trajet appartient à une fiche de frais précise. En
--    ON DELETE CASCADE comme les deux autres tables de lignes, pour qu'une
--    fiche supprimée n'y laisse pas de trajets orphelins.
--
--    distance en INT : un kilométrage se compte en kilomètres entiers, et la
--    quantité de lignefraisforfait qu'il alimente est elle-même un INT.
--    NOT NULL sans valeur par défaut : un trajet sans distance n'est pas un
--    trajet, l'insertion doit échouer plutôt que d'enregistrer un 0 muet.
-- --------------------------------------------------------------------------

CREATE TABLE trajet (
    id         INT          NOT NULL AUTO_INCREMENT,
    idVisiteur CHAR(4)      NOT NULL,
    mois       CHAR(6)      NOT NULL,
    date       DATE         NOT NULL
               COMMENT 'Date du déplacement, pour le contrôle de cohérence avec les congés',
    libelle    VARCHAR(100) NOT NULL
               COMMENT 'Trajet effectué, par exemple « Besançon -> Dijon, Dr Lemoine »',
    distance   INT          NOT NULL
               COMMENT 'Distance en kilomètres entiers',
    PRIMARY KEY (id),
    KEY fk_trajet_fichefrais (idVisiteur, mois),
    CONSTRAINT fk_trajet_fichefrais FOREIGN KEY (idVisiteur, mois)
        REFERENCES fichefrais (idVisiteur, mois) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------------------------
-- 3) Reprise des distances déjà saisies -- ÉTAPE INDISPENSABLE
--
--    À partir de cette migration, la quantité de la ligne kilométrique est
--    DÉRIVÉE : elle vaut la somme des distances des trajets de la fiche, et
--    elle est recalculée à chaque ajout ou suppression de trajet (même
--    principe que nbJustificatifs, décision 2.4 : recalculer, jamais
--    incrémenter).
--
--    Sans cette reprise, les fiches existantes auraient une quantité
--    kilométrique sans aucun trajet pour la justifier, et le PREMIER recalcul
--    la remettrait à zéro -- détruisant du montant sur des fiches validées.
--    On crée donc, pour chaque fiche ayant déjà une distance, un trajet unique
--    qui la porte.
--
--    La date retenue est le 1er du mois de la fiche : la date réelle des
--    déplacements n'a jamais été saisie, elle n'existe nulle part. Le libellé
--    le dit explicitement plutôt que d'inventer un trajet plausible.
-- --------------------------------------------------------------------------

INSERT INTO trajet (idVisiteur, mois, date, libelle, distance)
SELECT l.idVisiteur,
       l.mois,
       STR_TO_DATE(CONCAT(l.mois, '01'), '%Y%m%d'),
       'Report de saisie antérieure au détail des trajets',
       l.quantite
  FROM lignefraisforfait l
 WHERE l.idFraisForfait = 'KM'
   AND l.quantite > 0;

-- --------------------------------------------------------------------------
-- 4) Contrôles
-- --------------------------------------------------------------------------

-- Doit renvoyer 0 : aucune fiche ne doit avoir une quantité kilométrique
-- différente de la somme de ses trajets.
SELECT CONCAT('Fiches dont la distance ne correspond pas aux trajets : ',
    (SELECT COUNT(*)
       FROM lignefraisforfait l
       LEFT JOIN (SELECT idVisiteur, mois, SUM(distance) AS tot
                    FROM trajet GROUP BY idVisiteur, mois) t
              ON t.idVisiteur = l.idVisiteur AND t.mois = l.mois
      WHERE l.idFraisForfait = 'KM'
        AND l.quantite <> COALESCE(t.tot, 0))
) AS 'CONTROLE 1 (doit valoir 0)';

SELECT CONCAT('Forfait étape : actif=', actif,
              ' — lignes historiques conservées : ',
              (SELECT COUNT(*) FROM lignefraisforfait WHERE idFraisForfait = 'ETP'))
  FROM fraisforfait WHERE id = 'ETP';

SELECT CONCAT('Trajets créés par la reprise : ', COUNT(*),
              ' pour ', SUM(distance), ' km au total') AS 'CONTROLE 2'
  FROM trajet;
