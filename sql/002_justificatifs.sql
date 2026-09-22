-- ---------------------------------------------------------------------------
-- 002 - Justificatifs des frais hors forfait
--
-- Objet : permettre au visiteur de joindre une facture à une ligne de frais
--         hors forfait, et fiabiliser le compteur fichefrais.nbJustificatifs.
--
-- Lancement :
--   mysql -u root bd_gsb < sql/002_justificatifs.sql
--
-- Script NON destructif au sens du schéma : aucun DROP, aucune table ni
-- colonne supprimée. Il MODIFIE en revanche une donnée existante
-- (fichefrais.nbJustificatifs) : voir l'encadré plus bas.
-- Script à jouer UNE SEULE FOIS (pas de ADD COLUMN IF NOT EXISTS sous MySQL).
--
-- Pas de START TRANSACTION : le DDL est validé implicitement par MySQL
-- (voir sql/001). Le filet de sécurité est la sauvegarde préalable.
-- ---------------------------------------------------------------------------

USE bd_gsb;

-- --------------------------------------------------------------------------
-- 1) Le justificatif est porté par la LIGNE de frais hors forfait
--
--    Pourquoi trois colonnes plutôt qu'une :
--    - nomFichier   : le nom RÉEL sur le disque. Il est généré aléatoirement
--                     (32 caractères hexadécimaux) pour qu'on ne puisse pas
--                     deviner l'adresse du justificatif d'un collègue.
--    - nomOriginal  : le nom choisi par le visiteur, assaini. Sert uniquement
--                     à l'affichage et au téléchargement ; il n'est JAMAIS
--                     utilisé pour construire un chemin sur le disque.
--    - typeMime     : type réel détecté à l'envoi, pour renvoyer le bon
--                     en-tête Content-Type sans refaire l'analyse à chaque
--                     consultation.
--
--    Les trois colonnes sont NULL : un frais hors forfait peut parfaitement
--    exister sans justificatif, le visiteur ayant le droit de l'ajouter plus
--    tard (le cahier des charges lui laisse le mois entier pour modifier).
-- --------------------------------------------------------------------------
ALTER TABLE lignefraishorsforfait
    ADD COLUMN nomFichier  VARCHAR(64)  DEFAULT NULL AFTER montant,
    ADD COLUMN nomOriginal VARCHAR(255) DEFAULT NULL AFTER nomFichier,
    ADD COLUMN typeMime    VARCHAR(100) DEFAULT NULL AFTER nomOriginal;

-- Un fichier ne peut être rattaché qu'à une seule ligne de frais : l'unicité
-- empêche qu'une erreur de code fasse pointer deux lignes vers le même
-- fichier, auquel cas supprimer l'une rendrait l'autre illisible.
ALTER TABLE lignefraishorsforfait
    ADD UNIQUE KEY uk_lhf_nomfichier (nomFichier);

-- --------------------------------------------------------------------------
-- 2) Remise à la réalité du compteur de justificatifs
--
--    ATTENTION, CECI MODIFIE DES DONNÉES EXISTANTES.
--    fichefrais.nbJustificatifs contenait des valeurs du jeu de démonstration
--    (4, 2, 3, 1) qui ne correspondaient à aucun fichier : le compteur
--    n'était alimenté par aucun code (voir COMPTE-RENDU.md, point B16).
--    À partir de maintenant, ce compteur signifie « nombre de lignes de frais
--    hors forfait accompagnées d'un justificatif téléversé ». Aucun fichier
--    n'ayant encore été téléversé, il repart donc à 0 partout.
--
--    Alternative écartée : laisser les anciennes valeurs. Elles auraient
--    affiché « 4 justificatifs reçus » sur une fiche n'en présentant aucun.
--    Un compteur faux est pire qu'un compteur à zéro.
-- --------------------------------------------------------------------------
UPDATE fichefrais
   SET nbJustificatifs = (
       SELECT COUNT(*) FROM lignefraishorsforfait
        WHERE lignefraishorsforfait.idVisiteur = fichefrais.idVisiteur
          AND lignefraishorsforfait.mois       = fichefrais.mois
          AND lignefraishorsforfait.nomFichier IS NOT NULL);

-- --------------------------------------------------------------------------
-- Contrôles à passer après exécution
--
--   SHOW COLUMNS FROM lignefraishorsforfait LIKE 'nom%';      -- 2 lignes
--   SELECT idVisiteur, mois, nbJustificatifs FROM fichefrais; -- 0 partout
-- --------------------------------------------------------------------------
