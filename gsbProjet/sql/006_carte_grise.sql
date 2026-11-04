-- ---------------------------------------------------------------------------
-- 006 - Carte grise validée par le comptable (phase 10)
--
-- Objet : le visiteur ne déclare plus lui-même les caractéristiques de son
-- véhicule. Il envoie sa carte grise ; le comptable la lit, y relève les
-- informations et valide — ou refuse en motivant.
--
-- POURQUOI. Le tarif kilométrique dépend de la puissance fiscale et du
-- carburant (table typevehicule, de 0,52 à 0,72 €/km). Jusqu'ici le visiteur
-- choisissait lui-même sa ligne de barème dans une liste déroulante, sans
-- qu'aucune pièce ne l'atteste : sur 1 000 km mensuels, passer de « 4 CV
-- Diesel » à « 7 CV et + Essence » rapportait 200 € de plus, et rien dans
-- l'application ne permettait de s'en apercevoir. Le visiteur était à la fois
-- le déclarant et le contrôleur de sa propre déclaration.
--
-- CE QUI NE CHANGE PAS. visiteur.idTypeVehicule reste la colonne qui porte le
-- tarif appliqué, et elle reste la SEULE que lisent les calculs de frais
-- (getLesMontantsUnitairesApplicables, getTarifKilometrique). Seul son
-- écrivain change : le comptable au lieu du visiteur. C'est ce qui réalise la
-- règle « en attendant la validation, les frais continuent d'être calculés
-- avec les dernières informations validées » sans toucher une seule ligne de
-- calcul : tant que personne n'a validé, la colonne ne bouge pas.
--
-- Lancement :
--   mysql -u root bd_gsb < sql/006_carte_grise.sql
--
-- Aucun DROP de table. AUCUNE SUPPRESSION DE DONNÉES : les véhicules déjà
-- déclarés sont conservés tels quels (voir l'encadré du point 3).
-- Script à jouer UNE SEULE FOIS.
-- ---------------------------------------------------------------------------

USE bd_gsb;

-- --------------------------------------------------------------------------
-- 1) Référentiel des statuts d'une demande
--
-- Même forme que la table etat (id CHAR(2) + libellé) : les trois statuts du
-- cahier des charges sont des DONNÉES, pas des valeurs codées en dur dans les
-- requêtes. Une clé étrangère interdit alors tout statut inventé — un ENUM
-- aurait eu le même effet, mais sans libellé affichable et avec un ALTER TABLE
-- à écrire pour ajouter un quatrième cas.
-- --------------------------------------------------------------------------
CREATE TABLE statutcartegrise (
    id      CHAR(2)     NOT NULL,
    libelle VARCHAR(30) NOT NULL,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO statutcartegrise (id, libelle) VALUES
    ('EA', 'En attente de validation'),
    ('VA', 'Validée'),
    ('RE', 'Refusée');

-- --------------------------------------------------------------------------
-- 2) Les demandes d'enregistrement de véhicule
--
-- UN HISTORIQUE, ET NON QUELQUES COLONNES AJOUTÉES À visiteur. Le cahier des
-- charges demande qu'un refus soit motivé et qu'un nouveau document puisse
-- être envoyé ensuite : il y a donc plusieurs demandes successives pour un
-- même visiteur, et le motif du refus précédent doit rester lisible. Une
-- ligne par envoi répond aux deux besoins. Le statut courant du visiteur est
-- celui de sa demande la plus récente.
--
-- Les trois colonnes nomFichier / nomOriginal / typeMime reprennent exactement
-- celles posées en phase 2 sur lignefraishorsforfait : le nom de stockage est
-- généré par l'application, le nom d'origine ne sert qu'à l'affichage et au
-- téléchargement, et le type MIME est celui constaté dans le CONTENU du
-- fichier, pas celui annoncé par le navigateur.
--
-- idTypeVehicule, immatriculation et marqueModele sont NULL à l'envoi : c'est
-- le comptable qui les renseigne en lisant le document. Seule la première
-- entre dans le calcul des frais ; les deux autres rattachent la décision à un
-- véhicule identifié et attestent que le document a bien été lu.
-- --------------------------------------------------------------------------
CREATE TABLE cartegrise (
    id              INT          NOT NULL AUTO_INCREMENT,
    idVisiteur      CHAR(4)      NOT NULL,

    nomFichier      VARCHAR(64)  NOT NULL,
    nomOriginal     VARCHAR(255) NOT NULL,
    typeMime        VARCHAR(100) NOT NULL,
    dateEnvoi       DATETIME     NOT NULL,

    idStatut        CHAR(2)      NOT NULL DEFAULT 'EA',
    dateDecision    DATETIME     DEFAULT NULL,
    idComptable     CHAR(4)      DEFAULT NULL,
    motifRefus      VARCHAR(255) DEFAULT NULL,

    idTypeVehicule  CHAR(3)      DEFAULT NULL,
    immatriculation VARCHAR(15)  DEFAULT NULL,
    marqueModele    VARCHAR(60)  DEFAULT NULL,

    PRIMARY KEY (id),

    -- Le nom de stockage est tiré de random_bytes() : une collision est
    -- hautement improbable, mais si elle survenait le second enregistrement
    -- écraserait le fichier du premier. La contrainte la transforme en erreur
    -- visible plutôt qu'en perte silencieuse d'un document.
    UNIQUE KEY uk_cartegrise_nomfichier (nomFichier),

    -- L'écran du comptable liste les demandes en attente, et celui du visiteur
    -- cherche sa demande la plus récente : les deux parcours passent par ces
    -- deux colonnes.
    KEY ix_cartegrise_statut (idStatut, dateEnvoi),
    KEY ix_cartegrise_visiteur (idVisiteur, dateEnvoi),

    -- ON DELETE CASCADE : la suppression d'un compte visiteur (phase 8)
    -- emporte ses demandes. Les FICHIERS, eux, restent sur le disque — la base
    -- ne sait pas les supprimer ; c'est signalé dans le README.
    CONSTRAINT fk_cartegrise_visiteur FOREIGN KEY (idVisiteur)
        REFERENCES visiteur (id) ON DELETE CASCADE,
    CONSTRAINT fk_cartegrise_statut FOREIGN KEY (idStatut)
        REFERENCES statutcartegrise (id),
    CONSTRAINT fk_cartegrise_typevehicule FOREIGN KEY (idTypeVehicule)
        REFERENCES typevehicule (id),
    -- ON DELETE SET NULL et non CASCADE : si le compte du comptable qui a
    -- tranché est supprimé, la DÉCISION doit survivre. La perdre reviendrait à
    -- remettre en cause des remboursements déjà calculés.
    CONSTRAINT fk_cartegrise_comptable FOREIGN KEY (idComptable)
        REFERENCES comptable (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------------------------
-- 3) Les véhicules déjà déclarés
--
-- AUCUNE DONNÉE N'EST TOUCHÉE. Les visiteurs qui avaient déclaré un véhicule
-- avant cette phase le conservent : visiteur.idTypeVehicule garde sa valeur,
-- leurs frais kilométriques continuent d'être calculés au même tarif, et leurs
-- fiches en cours ne sont pas recalculées.
--
-- On pourrait être tenté de leur créer une demande 'VA' pour que la table soit
-- « complète ». Ce serait une FAUSSE TRACE : aucun document n'a été envoyé,
-- aucun comptable n'a rien validé. L'absence de ligne dit la vérité — ce
-- véhicule n'a jamais été justifié — et l'écran « Mon véhicule » l'affiche
-- comme tel, en invitant le visiteur à régulariser.
--
-- Rien à exécuter ici : ce point est une décision, et elle consiste à ne rien
-- faire. Pour vérifier qui est concerné :
--
--   SELECT id, nom, prenom, idTypeVehicule FROM visiteur
--    WHERE idTypeVehicule IS NOT NULL
--      AND id NOT IN (SELECT idVisiteur FROM cartegrise WHERE idStatut = 'VA');
-- --------------------------------------------------------------------------
