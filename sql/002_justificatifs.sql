

USE bd_gsb;


ALTER TABLE lignefraishorsforfait
    ADD COLUMN nomFichier  VARCHAR(64)  DEFAULT NULL AFTER montant,
    ADD COLUMN nomOriginal VARCHAR(255) DEFAULT NULL AFTER nomFichier,
    ADD COLUMN typeMime    VARCHAR(100) DEFAULT NULL AFTER nomOriginal;


ALTER TABLE lignefraishorsforfait
    ADD UNIQUE KEY uk_lhf_nomfichier (nomFichier);


UPDATE fichefrais
   SET nbJustificatifs = (
       SELECT COUNT(*) FROM lignefraishorsforfait
        WHERE lignefraishorsforfait.idVisiteur = fichefrais.idVisiteur
          AND lignefraishorsforfait.mois       = fichefrais.mois
          AND lignefraishorsforfait.nomFichier IS NOT NULL);


