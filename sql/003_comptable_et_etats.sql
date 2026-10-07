

USE bd_gsb;


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
INSERT INTO comptable (id, nom, prenom, login, mdp) VALUES
    ('c01', 'Valette', 'Marie', 'mvalette', '$2y$10$TF7Ljwa95mygt.ProOl86OrlrWrk8TA2IBT6hc4cQHbG5Ho2C8gFy'),
    ('c02', 'test', 'test', 'test', '$2y$10$pK0WJqMg2ha7ZFhVER/IOepbGwmZtkKmLIYLidytdiKsgY70f6jaC');


-- --------------------------------------------------------------------------
INSERT INTO etat (id, libelle) VALUES ('MP', 'Mise en paiement');

UPDATE etat SET libelle = 'Validée' WHERE id = 'VA';


