

USE bd_gsb;


CREATE TABLE typevehicule (
    id      CHAR(3)       NOT NULL,
    libelle VARCHAR(30)   NOT NULL,
   
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


ALTER TABLE visiteur
    ADD COLUMN idTypeVehicule CHAR(3) DEFAULT NULL AFTER dateEmbauche,
    ADD KEY fk_visiteur_typevehicule (idTypeVehicule),
    ADD CONSTRAINT fk_visiteur_typevehicule
        FOREIGN KEY (idTypeVehicule) REFERENCES typevehicule (id);


ALTER TABLE lignefraisforfait
    ADD COLUMN montantUnitaire DECIMAL(5, 2) DEFAULT NULL AFTER quantite;


UPDATE lignefraisforfait lff
    INNER JOIN fraisforfait ff ON ff.id = lff.idFraisForfait
    SET lff.montantUnitaire = ff.montant
    WHERE lff.montantUnitaire IS NULL;

ALTER TABLE lignefraisforfait
    MODIFY COLUMN montantUnitaire DECIMAL(5, 2) NOT NULL;
