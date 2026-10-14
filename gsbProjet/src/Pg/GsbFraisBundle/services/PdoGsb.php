<?php
namespace Pg\GsbFraisBundle\services;

use PDO;

// Les méthodes ci-dessous s'appuient sur dateAnglaisVersFrancais() et
// dateFrancaisVersAnglais() : on charge explicitement les fonctions plutôt
// que de compter sur un require_once déjà fait par un contrôleur.
require_once __DIR__ . '/../Controller/include/fct.inc.php';

class PdoGsb
{
        private static $monPdo;

        /**
         * Identifiant, dans la table fraisforfait, de la ligne dont le montant
         * unitaire dépend du véhicule du visiteur.
         *
         * Le barème, lui, est en base (table typevehicule) : c'est bien la valeur
         * qui n'est pas codée en dur. Reste à savoir QUELLE ligne de frais est la
         * ligne kilométrique ; cette information est structurelle (elle ne change
         * pas quand la direction modifie ses tarifs) et elle est déclarée ici, à
         * un seul endroit, plutôt que recopiée dans chaque requête.
         */
        const ID_FRAIS_KILOMETRIQUE = 'KM';

        /**
         * Constructeur public, crée l'instance de PDO qui sera sollicitée
         * pour toutes les méthodes de la classe
         */
        public function __construct($serveur, $bdd, $user, $mdp)
        {
                PdoGsb::$monPdo = new PDO(
                        $serveur . ';' . $bdd . ';charset=utf8mb4',
                        $user,
                        $mdp,
                        array(
                                // Sans ce mode, une requête en échec renvoie false
                                // silencieusement et l'erreur remonte plus loin sous une
                                // forme incompréhensible.
                                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                        )
                );
        }

        public function __destruct()
        {
                PdoGsb::$monPdo = null;
        }

        /**
         * Authentifie un visiteur
         *
         * Depuis la phase 8, visiteur.mdp contient une EMPREINTE bcrypt et non
         * plus le mot de passe en clair. La comparaison ne peut donc plus se
         * faire dans la requête SQL comme à l'origine (« and mdp=:md ») : deux
         * empreintes du même mot de passe sont différentes, chacune portant son
         * propre grain de sel. On récupère la ligne par le seul login, puis
         * password_verify() compare.
         *
         * Le contrôle du compte actif est dans verifierAuthentification(),
         * commune aux trois rôles.
         *
         * @param $login
         * @param $mdp le mot de passe en clair saisi dans le formulaire
         * @return l'id, le nom, le prénom et mdpDoitChanger, ou false
         */
        public function getInfosVisiteur($login, $mdp)
        {
                $req  = 'select visiteur.id as id, visiteur.nom as nom,
                visiteur.prenom as prenom, visiteur.mdp as mdp,
                visiteur.actif as actif, visiteur.mdpDoitChanger as mdpDoitChanger
                from visiteur where visiteur.login = :log';
                $stmt = PdoGsb::$monPdo->prepare($req);
                $stmt->bindParam(':log', $login);
                $stmt->execute();
                return $this->verifierAuthentification($stmt->fetch(), $mdp);
        }

        /**
         * Retourne sous forme d'un tableau associatif toutes les lignes de frais hors forfait
         * concernées par les deux arguments
         *
         * La boucle foreach ne peut être utilisée ici car on procède
         * à une modification de la structure itérée - transformation du champ date-
         *
         * @param $idVisiteur
         * @param $mois sous la forme aaaamm
         * @return tous les champs des lignes de frais hors forfait sous la forme d'un tableau associatif
         */
        public function getLesFraisHorsForfait($idVisiteur, $mois)
        {
                $req  = "select * from lignefraishorsforfait where lignefraishorsforfait.idvisiteur = :idVisiteur 
\t\tand lignefraishorsforfait.mois = :mois ";
                $stmt = PdoGsb::$monPdo->prepare($req);
                $stmt->bindParam(':idVisiteur', $idVisiteur);
                $stmt->bindParam(':mois', $mois);
                $stmt->execute();
                $lesLignes = $stmt->fetchAll();
                $nbLignes  = count($lesLignes);
                for ($i = 0; $i < $nbLignes; $i++) {
                        $date                  = $lesLignes[$i]['date'];
                        $lesLignes[$i]['date'] = dateAnglaisVersFrancais($date);
                }
                return $lesLignes;
        }

        /**
         * Retourne le nombre de justificatif d'un visiteur pour un mois donné
         *
         * @param $idVisiteur
         * @param $mois sous la forme aaaamm
         * @return le nombre entier de justificatifs
         */
        public function getNbjustificatifs($idVisiteur, $mois)
        {
                $req  = 'select fichefrais.nbjustificatifs as nb from  fichefrais where fichefrais.idvisiteur =:idVisiteur and fichefrais.mois = :mois';
                $stmt = PdoGsb::$monPdo->prepare($req);
                $stmt->bindParam(':idVisiteur', $idVisiteur);
                $stmt->bindParam(':mois', $mois);
                $stmt->execute();
                $laLigne = $stmt->fetch();
                return $laLigne['nb'];
        }

        /**
         * Retourne sous forme d'un tableau associatif toutes les lignes de frais au forfait
         * concernées par les deux arguments
         *
         * @param $idVisiteur
         * @param $mois sous la forme aaaamm
         * @return l'id, le libelle et la quantité sous la forme d'un tableau associatif
         */
        public function getLesFraisForfait($idVisiteur, $mois)
        {
                // montantUnitaire vient de la LIGNE et non de la table fraisforfait :
                // c'est le tarif qui était en vigueur au moment de la saisie. Le total
                // est calculé par le serveur SQL, ce qui évite une boucle en PHP.
                $req  = "select fraisforfait.id as idfrais, fraisforfait.libelle as libelle, 
\t\tlignefraisforfait.quantite as quantite, 
\t\tlignefraisforfait.montantUnitaire as montantunitaire, 
\t\tlignefraisforfait.quantite * lignefraisforfait.montantUnitaire as montant, 
\t\tfraisforfait.actif as actif 
\t\tfrom lignefraisforfait inner join fraisforfait 
\t\ton fraisforfait.id = lignefraisforfait.idfraisforfait
\t\twhere lignefraisforfait.idvisiteur = :idVisiteur and lignefraisforfait.mois= :mois 
\t\torder by lignefraisforfait.idfraisforfait";
                $stmt = PdoGsb::$monPdo->prepare($req);
                $stmt->bindParam(':idVisiteur', $idVisiteur);
                $stmt->bindParam(':mois', $mois);
                $stmt->execute();
                $lesLignes = $stmt->fetchAll();
                return $lesLignes;
        }

        /**
         * Retourne tous les id de la table FraisForfait
         *
         * @return un tableau associatif
         */
        public function getLesIdFrais()
        {
                $req  = 'select fraisforfait.id as idfrais from fraisforfait
                where fraisforfait.actif = 1 order by fraisforfait.id';
                $stmt = PdoGsb::$monPdo->prepare($req);
                $stmt->execute();
                $lesLignes = $stmt->fetchAll();
                return $lesLignes;
        }

        /**
         * Met à jour la table ligneFraisForfait
         *
         * Met à jour la table ligneFraisForfait pour un visiteur et
         * un mois donné en enregistrant les nouveaux montants
         *
         * @param $idVisiteur
         * @param $mois sous la forme aaaamm
         * @param $lesFrais tableau associatif de clé idFrais et de valeur la quantité pour ce frais
         * @return un tableau associatif
         */
        public function majFraisForfait($idVisiteur, $mois, $lesFrais)
        {
                $lesCles = array_keys($lesFrais);
                foreach ($lesCles as $unIdFrais) {
                        $qte  = $lesFrais[$unIdFrais];
                        // « and ... in (select id from fraisforfait where actif = 1) »
                        // depuis la phase 9 : un forfait retiré de l'usage ne peut plus
                        // recevoir de quantité. La règle est ICI et non dans les
                        // contrôleurs, pour qu'aucun appelant ne puisse l'oublier --
                        // ni l'écran de saisie, ni la correction par le comptable.
                        $req  = "update lignefraisforfait set lignefraisforfait.quantite = :qte
\t\t\twhere lignefraisforfait.idvisiteur = :idVisiteur and lignefraisforfait.mois = :mois
\t\t\tand lignefraisforfait.idfraisforfait = :unIdFrais
\t\t\tand lignefraisforfait.idfraisforfait in
\t\t\t    (select id from fraisforfait where actif = 1)";
                        $stmt = PdoGsb::$monPdo->prepare($req);
                        $stmt->bindParam(':qte', $qte);
                        $stmt->bindParam(':idVisiteur', $idVisiteur);
                        $stmt->bindParam(':mois', $mois);
                        $stmt->bindParam(':unIdFrais', $unIdFrais);
                        $stmt->execute();
                }
        }

        /**
         * met à jour le nombre de justificatifs de la table ficheFrais
         * pour le mois et le visiteur concerné
         *
         * @param $idVisiteur
         * @param $mois sous la forme aaaamm
         */
        public function majNbJustificatifs($idVisiteur, $mois, $nbJustificatifs)
        {
                $req  = 'update fichefrais set nbjustificatifs = :nbJustificatifs 
                where fichefrais.idvisiteur = :idVisiteur and fichefrais.mois = :mois';
                $stmt = PdoGsb::$monPdo->prepare($req);
                $stmt->bindParam(':idVisiteur', $idVisiteur);
                $stmt->bindParam(':mois', $mois);
                $stmt->bindParam(':nbJustificatifs', $nbJustificatifs);
                $stmt->execute();
        }

        /**
         * Teste si un visiteur possède une fiche de frais pour le mois passé en argument
         *
         * @param $idVisiteur
         * @param $mois sous la forme aaaamm
         * @return vrai ou faux
         */
        public function estPremierFraisMois($idVisiteur, $mois)
        {
                $ok   = false;
                $req  = 'select count(*) as nblignesfrais from fichefrais 
                where fichefrais.mois = :mois and fichefrais.idvisiteur = :idVisiteur';
                $stmt = PdoGsb::$monPdo->prepare($req);
                $stmt->bindParam(':idVisiteur', $idVisiteur);
                $stmt->bindParam(':mois', $mois);
                $stmt->execute();
                $laLigne = $stmt->fetch();
                if ($laLigne['nblignesfrais'] == 0) {
                        $ok = true;
                }
                return $ok;
        }

        /**
         * Retourne le dernier mois en cours d'un visiteur
         *
         * @param $idVisiteur
         * @return le mois sous la forme aaaamm
         */
        public function dernierMoisSaisi($idVisiteur)
        {
                $req  = 'select max(mois) as dernierMois from fichefrais where fichefrais.idvisiteur = :idVisiteur';
                $stmt = PdoGsb::$monPdo->prepare($req);
                $stmt->bindParam(':idVisiteur', $idVisiteur);
                $stmt->execute();
                $laLigne     = $stmt->fetch();
                $dernierMois = $laLigne['dernierMois'];
                return $dernierMois;
        }

        /**
         * Crée une nouvelle fiche de frais et les lignes de frais au forfait pour un visiteur et un mois donnés
         *
         * récupère le dernier mois en cours de traitement, met à 'CL' son champs idEtat, crée une nouvelle fiche de frais
         * avec un idEtat à 'CR' et crée les lignes de frais forfait de quantités nulles
         * @param $idVisiteur
         * @param $mois sous la forme aaaamm
         */
        public function creeNouvellesLignesFrais($idVisiteur, $mois)
        {
                $dernierMois = $this->dernierMoisSaisi($idVisiteur);
                // Un visiteur qui saisit sa toute première fiche n'a pas de
                // mois précédent : getLesInfosFicheFrais renvoie alors false.
                if ($dernierMois != null) {
                        $laDerniereFiche = $this->getLesInfosFicheFrais($idVisiteur, $dernierMois);
                        if (is_array($laDerniereFiche) && $laDerniereFiche['idEtat'] == 'CR') {
                                $this->majEtatFicheFrais($idVisiteur, $dernierMois, 'CL');
                        }
                }
                $req  = "insert into fichefrais(idvisiteur,mois,nbJustificatifs,montantValide,dateModif,idEtat) 
                values(:idVisiteur,:mois,0,0,now(),'CR')";
                $stmt = PdoGsb::$monPdo->prepare($req);
                $stmt->bindParam(':idVisiteur', $idVisiteur);
                $stmt->bindParam(':mois', $mois);
                $stmt->execute();
                // On fige ici le montant unitaire de chaque ligne : c'est ce
                // qui garantit qu'un futur changement de barème ne modifiera
                // pas cette fiche. Le tarif kilométrique retenu est celui du
                // véhicule déclaré par le visiteur.
                $lesMontants = $this->getLesMontantsUnitairesApplicables($idVisiteur);
                foreach ($lesMontants as $uneLigneFrais) {
                        $unIdFrais = $uneLigneFrais['idfrais'];
                        $unMontant = $uneLigneFrais['montantunitaire'];
                        $req       = 'insert into lignefraisforfait(idvisiteur,mois,idFraisForfait,quantite,montantUnitaire) 
                        values(:idVisiteur,:mois,:unIdFrais,0,:unMontant)';
                        $stmt      = PdoGsb::$monPdo->prepare($req);
                        $stmt->bindParam(':idVisiteur', $idVisiteur);
                        $stmt->bindParam(':mois', $mois);
                        $stmt->bindParam(':unIdFrais', $unIdFrais);
                        $stmt->bindParam(':unMontant', $unMontant);
                        $stmt->execute();
                }
        }

        /**
         * Crée un nouveau frais hors forfait pour un visiteur un mois donné
         * à partir des informations fournies en paramètre
         *
         * @param $idVisiteur
         * @param $mois sous la forme aaaamm
         * @param $libelle : le libelle du frais
         * @param $date : la date du frais au format français jj//mm/aaaa
         * @param $montant : le montant
         */
        public function creeNouveauFraisHorsForfait($idVisiteur, $mois, $libelle, $date, $montant)
        {
                $dateAng = dateFrancaisVersAnglais($date);
                // Colonnes nommées : l'ancienne forme positionnelle passait ''
                // comme id auto-incrémenté, ce que MySQL refuse en mode strict.
                $req     = 'insert into lignefraishorsforfait(idVisiteur,mois,libelle,date,montant)
                values(:idVisiteur,:mois,:libelle,:date,:montant)';
                $stmt    = PdoGsb::$monPdo->prepare($req);
                $stmt->bindParam(':idVisiteur', $idVisiteur);
                $stmt->bindParam(':mois', $mois);
                $stmt->bindParam(':libelle', $libelle);
                $stmt->bindParam(':date', $dateAng);
                $stmt->bindParam(':montant', $montant);
                $stmt->execute();
                // Ajout phase 2 : on retourne l'identifiant créé, pour pouvoir
                // rattacher aussitôt le justificatif à la ligne. lastInsertId()
                // est propre à la connexion en cours : aucun risque de récupérer
                // l'identifiant inséré par un autre utilisateur au même instant.
                return PdoGsb::$monPdo->lastInsertId();
        }

        /**
         * Supprime le frais hors forfait dont l'id est passé en argument
         *
         * @param $idFrais
         */
        public function supprimerFraisHorsForfait($idFrais)
        {
                $req  = 'delete from lignefraishorsforfait where lignefraishorsforfait.id =:idFrais ';
                $stmt = PdoGsb::$monPdo->prepare($req);
                $stmt->bindParam(':idFrais', $idFrais);
                $stmt->execute();
        }

        /**
         * Retourne les mois pour lesquel un visiteur a une fiche de frais
         *
         * @param $idVisiteur
         * @return un tableau associatif de clé un mois -aaaamm- et de valeurs l'année et le mois correspondant
         */
        public function getLesMoisDisponibles($idVisiteur)
        {
                $req  = 'select fichefrais.mois as mois from  fichefrais where fichefrais.idvisiteur =:idVisiteur 
                order by fichefrais.mois desc ';
                $stmt = PdoGsb::$monPdo->prepare($req);
                $stmt->bindParam(':idVisiteur', $idVisiteur);
                $stmt->execute();
                $laLigne = $stmt->fetch();
                $lesMois = array();
                while ($laLigne != null) {
                        $mois             = $laLigne['mois'];
                        $numAnnee         = substr($mois, 0, 4);
                        $numMois          = substr($mois, 4, 2);
                        $lesMois["$mois"] = array(
                                'mois'     => "$mois",
                                'numAnnee' => "$numAnnee",
                                'numMois'  => "$numMois"
                        );
                        $laLigne          = $stmt->fetch();
                }
                return $lesMois;
        }

        /**
         * Retourne les informations d'une fiche de frais d'un visiteur pour un mois donné
         *
         * @param $idVisiteur
         * @param $mois sous la forme aaaamm
         * @return un tableau avec des champs de jointure entre une fiche de frais et la ligne d'état
         */
        public function getLesInfosFicheFrais($idVisiteur, $mois)
        {
                $req  = 'select ficheFrais.idEtat as idEtat, ficheFrais.dateModif as dateModif, ficheFrais.nbJustificatifs as nbJustificatifs, 
                        ficheFrais.montantValide as montantValide, etat.libelle as libEtat from  fichefrais inner join Etat on ficheFrais.idEtat = Etat.id 
                        where fichefrais.idvisiteur = :idVisiteur and fichefrais.mois = :mois';
                $stmt = PdoGsb::$monPdo->prepare($req);
                $stmt->bindParam(':idVisiteur', $idVisiteur);
                $stmt->bindParam(':mois', $mois);
                $stmt->execute();
                $laLigne = $stmt->fetch();
                return $laLigne;
        }

        /**
         * Modifie l'état et la date de modification d'une fiche de frais
         *
         * Modifie le champ idEtat et met la date de modif à aujourd'hui
         * @param $idVisiteur
         * @param $mois sous la forme aaaamm
         */
        public function majEtatFicheFrais($idVisiteur, $mois, $etat)
        {
                $req  = 'update ficheFrais set idEtat = :etat, dateModif = now() 
                where fichefrais.idvisiteur = :idVisiteur and fichefrais.mois = :mois';
                $stmt = PdoGsb::$monPdo->prepare($req);
                $stmt->bindParam(':idVisiteur', $idVisiteur);
                $stmt->bindParam(':mois', $mois);
                $stmt->bindParam(':etat', $etat);
                $stmt->execute();
        }

        /**
         * Retourne toutes les fiches de frais dont l'état est à validé
         * @return type
         */
        public function getLesFichesValidees()
        {
                $req  = "select * from fichefrais
                    inner join etat on fichefrais.idetat = etat.id
                    inner join visiteur on visiteur.id = fichefrais.idVisiteur
                    where idetat = 'VA'";
                $stmt = PdoGsb::$monPdo->prepare($req);
                $stmt->execute();
                $lesLignes = $stmt->fetchAll();
                return $lesLignes;
        }

        /**
         * Vérifie si un frais existe pour un visiteur et un mois donné
         * @param type $idVisiteur
         * @param type $mois
         * @param type $idFrais
         * @return 1 ou 0
         */
        public function estValideSuppressionFrais($idVisiteur, $mois, $idFrais)
        {
                $req  = 'select count(*) as nb from lignefraishorsforfait 
            where lignefraishorsforfait.id=:idfrais and lignefraishorsforfait.mois=:mois
            and lignefraishorsforfait.idvisiteur=:idvisiteur';
                $stmt = PdoGsb::$monPdo->prepare($req);
                $stmt->bindParam(':idfrais', $idFrais);
                $stmt->bindParam(':mois', $mois);
                $stmt->bindParam(':idvisiteur', $idVisiteur);
                $stmt->execute();
                $ligne = $stmt->fetch();
                return $ligne['nb'];
        }

        /*
         * =========================================================================
         * Puissance fiscale du véhicule (phase 1)
         * =========================================================================
         */

        /**
         * Retourne tout le barème de remboursement kilométrique
         *
         * Sert à alimenter la liste déroulante de l'écran « Mon véhicule ».
         * @return un tableau associatif : id, libelle, montant
         */
        public function getLesTypesVehicule()
        {
                $req  = 'select typevehicule.id as id, typevehicule.libelle as libelle,
                typevehicule.montant as montant from typevehicule order by typevehicule.id';
                $stmt = PdoGsb::$monPdo->prepare($req);
                $stmt->execute();
                return $stmt->fetchAll();
        }

        /**
         * Retourne le type de véhicule déclaré par un visiteur
         *
         * @param $idVisiteur
         * @return un tableau associatif -id, libelle, montant- ou false si le
         *         visiteur n'a pas encore déclaré de véhicule
         */
        public function getTypeVehiculeVisiteur($idVisiteur)
        {
                $req  = 'select typevehicule.id as id, typevehicule.libelle as libelle,
                typevehicule.montant as montant from visiteur
                inner join typevehicule on typevehicule.id = visiteur.idTypeVehicule
                where visiteur.id = :idVisiteur';
                $stmt = PdoGsb::$monPdo->prepare($req);
                $stmt->bindParam(':idVisiteur', $idVisiteur);
                $stmt->execute();
                return $stmt->fetch();
        }

        /**
         * Vérifie qu'un identifiant de type de véhicule existe bien dans le barème
         *
         * Contrôle de sécurité : la valeur vient d'une liste déroulante, donc du
         * navigateur, donc elle n'est pas digne de confiance. La clé étrangère
         * refuserait de toute façon une valeur inconnue, mais avec une erreur SQL
         * brute ; ici on peut afficher un message compréhensible.
         *
         * @param $idTypeVehicule
         * @return vrai ou faux
         */
        public function estTypeVehiculeValide($idTypeVehicule)
        {
                $req  = 'select count(*) as nb from typevehicule where typevehicule.id = :idTypeVehicule';
                $stmt = PdoGsb::$monPdo->prepare($req);
                $stmt->bindParam(':idTypeVehicule', $idTypeVehicule);
                $stmt->execute();
                $laLigne = $stmt->fetch();
                return $laLigne['nb'] == 1;
        }

        /**
         * Retourne le montant unitaire à appliquer à chaque type de frais forfaitisé
         * pour un visiteur donné
         *
         * Pour toutes les lignes, c'est le montant du référentiel fraisforfait.
         * Pour la ligne kilométrique, c'est le tarif du véhicule du visiteur ; s'il
         * n'a pas déclaré de véhicule, on retombe sur le montant historique de
         * fraisforfait.KM -0,62 €/km-, ce qui préserve le comportement d'avant.
         *
         * Une seule requête, et non une par type de frais : voir le compte rendu,
         * c'est ce qui évite un N+1 dans creeNouvellesLignesFrais().
         *
         * @param $idVisiteur
         * @return un tableau associatif : idfrais, montantunitaire
         */
        public function getLesMontantsUnitairesApplicables($idVisiteur)
        {
                $req       = 'select fraisforfait.id as idfrais,
                case when fraisforfait.id = :idFraisKm
                     then coalesce(typevehicule.montant, fraisforfait.montant)
                     else fraisforfait.montant
                end as montantunitaire
                from fraisforfait
                left join visiteur on visiteur.id = :idVisiteur
                left join typevehicule on typevehicule.id = visiteur.idTypeVehicule
                where fraisforfait.actif = 1
                order by fraisforfait.id';
                $stmt      = PdoGsb::$monPdo->prepare($req);
                $idFraisKm = PdoGsb::ID_FRAIS_KILOMETRIQUE;
                $stmt->bindParam(':idFraisKm', $idFraisKm);
                $stmt->bindParam(':idVisiteur', $idVisiteur);
                $stmt->execute();
                return $stmt->fetchAll();
        }

        /**
         * Retourne le tarif kilométrique réellement appliqué à un visiteur
         *
         * C'est le tarif de son véhicule, ou le tarif historique de fraisforfait.KM
         * s'il n'a pas encore déclaré de véhicule. Sert à l'affichage : l'écran de
         * saisie doit pouvoir annoncer au visiteur le tarif qui lui sera appliqué.
         *
         * @param $idVisiteur
         * @return le tarif en euros par kilomètre
         */
        public function getTarifKilometrique($idVisiteur)
        {
                $req       = 'select coalesce(
                        (select typevehicule.montant from typevehicule
                         inner join visiteur on visiteur.idTypeVehicule = typevehicule.id
                         where visiteur.id = :idVisiteur),
                        (select fraisforfait.montant from fraisforfait
                         where fraisforfait.id = :idFraisKm)) as tarif';
                $stmt      = PdoGsb::$monPdo->prepare($req);
                $idFraisKm = PdoGsb::ID_FRAIS_KILOMETRIQUE;
                $stmt->bindParam(':idVisiteur', $idVisiteur);
                $stmt->bindParam(':idFraisKm', $idFraisKm);
                $stmt->execute();
                $laLigne = $stmt->fetch();
                return $laLigne['tarif'];
        }

        /**
         * Enregistre le véhicule d'un visiteur et répercute le nouveau tarif
         * kilométrique sur ses fiches ENCORE MODIFIABLES
         *
         * Règle de gestion : seules les fiches à l'état 'CR' -saisie en cours- sont
         * mises à jour. Une fiche clôturée, validée ou remboursée conserve pour
         * toujours le tarif qui lui a été appliqué : c'est l'exigence de
         * non-recalcul rétroactif du cahier des charges.
         *
         * Les deux mises à jour sont encadrées par une transaction : si la seconde
         * échoue, la première est annulée, sinon le visiteur se retrouverait avec un
         * véhicule déclaré mais une fiche en cours restée à l'ancien tarif.
         *
         * @param $idVisiteur
         * @param $idTypeVehicule l'identifiant du type de véhicule
         */
        public function majTypeVehiculeVisiteur($idVisiteur, $idTypeVehicule)
        {
                PdoGsb::$monPdo->beginTransaction();
                try {
                        $req  = 'update visiteur set idTypeVehicule = :idTypeVehicule
                        where visiteur.id = :idVisiteur';
                        $stmt = PdoGsb::$monPdo->prepare($req);
                        $stmt->bindParam(':idTypeVehicule', $idTypeVehicule);
                        $stmt->bindParam(':idVisiteur', $idVisiteur);
                        $stmt->execute();

                        // coalesce : si le visiteur n'a plus de véhicule déclaré,
                        // on revient au tarif historique de fraisforfait.KM.
                        $req       = "update lignefraisforfait
                        inner join fichefrais on fichefrais.idVisiteur = lignefraisforfait.idVisiteur
                                              and fichefrais.mois = lignefraisforfait.mois
                        set lignefraisforfait.montantUnitaire = coalesce(
                                (select typevehicule.montant from typevehicule
                                 where typevehicule.id = :idTypeVehicule),
                                (select fraisforfait.montant from fraisforfait
                                 where fraisforfait.id = :idFraisKm))
                        where lignefraisforfait.idVisiteur = :idVisiteur
                        and lignefraisforfait.idFraisForfait = :idFraisKm
                        and fichefrais.idEtat = 'CR'";
                        $stmt      = PdoGsb::$monPdo->prepare($req);
                        $idFraisKm = PdoGsb::ID_FRAIS_KILOMETRIQUE;
                        $stmt->bindParam(':idTypeVehicule', $idTypeVehicule);
                        $stmt->bindParam(':idFraisKm', $idFraisKm);
                        $stmt->bindParam(':idVisiteur', $idVisiteur);
                        $stmt->execute();

                        PdoGsb::$monPdo->commit();
                } catch (\Exception $e) {
                        PdoGsb::$monPdo->rollBack();
                        throw $e;
                }
        }

        /*
         * =========================================================================
         * Justificatifs des frais hors forfait (phase 2)
         * =========================================================================
         */

        /**
         * Retourne une ligne de frais hors forfait avec son justificatif
         *
         * Renvoie aussi idVisiteur et mois : c'est ce qui permet au contrôleur de
         * vérifier À QUI appartient le frais avant de servir le fichier.
         *
         * @param $idFrais
         * @return un tableau associatif, ou false si la ligne n'existe pas
         */
        public function getLigneFraisHorsForfait($idFrais)
        {
                $req  = 'select lignefraishorsforfait.id as id,
                lignefraishorsforfait.idVisiteur as idVisiteur,
                lignefraishorsforfait.mois as mois,
                lignefraishorsforfait.libelle as libelle,
                lignefraishorsforfait.nomFichier as nomFichier,
                lignefraishorsforfait.nomOriginal as nomOriginal,
                lignefraishorsforfait.typeMime as typeMime
                from lignefraishorsforfait where lignefraishorsforfait.id = :idFrais';
                $stmt = PdoGsb::$monPdo->prepare($req);
                $stmt->bindParam(':idFrais', $idFrais);
                $stmt->execute();
                return $stmt->fetch();
        }

        /**
         * Rattache un justificatif à une ligne de frais hors forfait
         *
         * Les deux écritures -la ligne, puis le compteur de la fiche- forment un
         * tout : si la seconde échouait, la fiche afficherait un nombre de
         * justificatifs faux. D'où la transaction.
         *
         * @param $idFrais
         * @param $nomFichier le nom généré sur le disque
         * @param $nomOriginal le nom d'origine, assaini
         * @param $typeMime
         */
        public function majJustificatifFrais($idFrais, $nomFichier, $nomOriginal, $typeMime)
        {
                PdoGsb::$monPdo->beginTransaction();
                try {
                        $req  = 'update lignefraishorsforfait
                        set nomFichier = :nomFichier, nomOriginal = :nomOriginal,
                            typeMime = :typeMime
                        where lignefraishorsforfait.id = :idFrais';
                        $stmt = PdoGsb::$monPdo->prepare($req);
                        $stmt->bindParam(':nomFichier', $nomFichier);
                        $stmt->bindParam(':nomOriginal', $nomOriginal);
                        $stmt->bindParam(':typeMime', $typeMime);
                        $stmt->bindParam(':idFrais', $idFrais);
                        $stmt->execute();

                        $this->recalculerNbJustificatifsDuFrais($idFrais);
                        PdoGsb::$monPdo->commit();
                } catch (\Exception $e) {
                        PdoGsb::$monPdo->rollBack();
                        throw $e;
                }
        }

        /**
         * Recalcule le nombre de justificatifs de la fiche à laquelle appartient un frais
         *
         * Le compteur est REcalculé à partir des lignes réellement pourvues d'un
         * fichier, et non incrémenté. Un compteur incrémenté dérive : il suffit
         * d'une erreur, d'un double clic ou d'une suppression non répercutée pour
         * qu'il devienne faux, sans moyen de s'en apercevoir.
         *
         * Une seule instruction SQL : la sous-requête compte et l'UPDATE écrit dans
         * la foulée, sans aller-retour lecture puis écriture.
         *
         * @param $idFrais
         */
        public function recalculerNbJustificatifsDuFrais($idFrais)
        {
                $req  = 'update fichefrais
                inner join lignefraishorsforfait on lignefraishorsforfait.idVisiteur = fichefrais.idVisiteur
                                                and lignefraishorsforfait.mois = fichefrais.mois
                set fichefrais.nbJustificatifs = (
                        select count(*) from lignefraishorsforfait lhf
                        where lhf.idVisiteur = fichefrais.idVisiteur
                        and lhf.mois = fichefrais.mois
                        and lhf.nomFichier is not null)
                where lignefraishorsforfait.id = :idFrais';
                $stmt = PdoGsb::$monPdo->prepare($req);
                $stmt->bindParam(':idFrais', $idFrais);
                $stmt->execute();
        }

        public function recalculerNbJustificatifs($idVisiteur, $mois)
        {
                $req  = 'update fichefrais
                set nbJustificatifs = (
                        select count(*) from lignefraishorsforfait
                        where lignefraishorsforfait.idVisiteur = :idVisiteur
                        and lignefraishorsforfait.mois = :mois
                        and lignefraishorsforfait.nomFichier is not null)
                where fichefrais.idVisiteur = :idVisiteur and fichefrais.mois = :mois';
                $stmt = PdoGsb::$monPdo->prepare($req);
                $stmt->bindParam(':idVisiteur', $idVisiteur);
                $stmt->bindParam(':mois', $mois);
                $stmt->execute();
        }

        public function getVisiteur($idVisiteur)
        {
                $req  = 'select visiteur.id as id, visiteur.nom as nom,
                visiteur.prenom as prenom from visiteur where visiteur.id = :idVisiteur';
                $stmt = PdoGsb::$monPdo->prepare($req);
                $stmt->bindParam(':idVisiteur', $idVisiteur);
                $stmt->execute();
                return $stmt->fetch();
        }

        public function getLesFichesDuMois($mois, $etat)
        {
                $req  = 'select fichefrais.idVisiteur as idVisiteur,
                visiteur.nom as nom, visiteur.prenom as prenom
                from fichefrais inner join visiteur on visiteur.id = fichefrais.idVisiteur
                where fichefrais.mois = :mois and fichefrais.idEtat = :etat
                order by visiteur.nom, visiteur.prenom';
                $stmt = PdoGsb::$monPdo->prepare($req);
                $stmt->bindParam(':mois', $mois);
                $stmt->bindParam(':etat', $etat);
                $stmt->execute();
                return $stmt->fetchAll();
        }

        public function genererVisiteursDeTest($prefixe, $nbVisiteurs, $mois, $etat,
                                               $lesNoms, $lesPrenoms, $lesLibelles)
        {
                if ($nbVisiteurs > 999) {
                        $nbVisiteurs = 999;
                }
                $reqVisiteur    = PdoGsb::$monPdo->prepare(
                        'insert into visiteur(id,nom,prenom,login,mdp,dateEmbauche,idTypeVehicule)
                         values(:id,:nom,:prenom,:login,:mdp,:dateEmbauche,:idTypeVehicule)'
                );
                $reqFiche       = PdoGsb::$monPdo->prepare(
                        'insert into fichefrais(idVisiteur,mois,nbJustificatifs,montantValide,dateModif,idEtat)
                         values(:id,:mois,0,0,now(),:etat)'
                );
                $reqLignes      = PdoGsb::$monPdo->prepare(
                        "insert into lignefraisforfait(idVisiteur,mois,idFraisForfait,quantite,montantUnitaire)
                         values(:id,:mois,'ETP',:qEtp,:mEtp),
                                (:id,:mois,'KM',:qKm,:mKm),
                                (:id,:mois,'NUI',:qNui,:mNui),
                                (:id,:mois,'REP',:qRep,:mRep)"
                );
                $reqHorsForfait = PdoGsb::$monPdo->prepare(
                        'insert into lignefraishorsforfait(idVisiteur,mois,libelle,date,montant)
                         values(:id,:mois,:libelle,:date,:montant)'
                );

                $lesTypesVehicule   = $this->getLesTypesVehicule();
                $lesMontantsForfait = array();
                foreach ($this->getLesTypesFraisForfait() as $unFrais) {
                        $lesMontantsForfait[$unFrais['id']] = $unFrais['montant'];
                }

                $nbCrees = 0;
                PdoGsb::$monPdo->beginTransaction();
                try {
                        for ($i = 1; $i <= $nbVisiteurs; $i++) {
                                $id       = $prefixe . str_pad($i, 3, '0', STR_PAD_LEFT);
                                $nom      = $lesNoms[array_rand($lesNoms)];
                                $prenom   = $lesPrenoms[array_rand($lesPrenoms)];
                                $vehicule = $lesTypesVehicule[array_rand($lesTypesVehicule)];
                                $reqVisiteur->execute(array(
                                                              ':id'             => $id,
                                                              ':nom'            => $nom,
                                                              ':prenom'         => $prenom,
                                                              ':login'          => 'test' . $id,
                                                              ':mdp'            => 'test' . $id,
                                                              ':dateEmbauche'   => '2020-01-01',
                                                              ':idTypeVehicule' => $vehicule['id'],
                                                      ));
                                $reqFiche->execute(array(
                                                           ':id' => $id, ':mois' => $mois, ':etat' => $etat
                                                   ));

                                $reqLignes->execute(array(
                                                            ':id'   => $id,
                                                            ':mois' => $mois,
                                                            ':qEtp' => rand(0, 5),
                                                            ':mEtp' => $lesMontantsForfait['ETP'],
                                                            ':qKm'  => rand(50, 900),
                                                            ':mKm'  => $vehicule['montant'],
                                                            ':qNui' => rand(0, 8),
                                                            ':mNui' => $lesMontantsForfait['NUI'],
                                                            ':qRep' => rand(0, 20),
                                                            ':mRep' => $lesMontantsForfait['REP'],
                                                    ));
                                $nbHorsForfait = rand(0, 3);
                                for ($j = 0; $j < $nbHorsForfait; $j++) {
                                        $jour = str_pad(rand(1, 28), 2, '0', STR_PAD_LEFT);
                                        $reqHorsForfait->execute(array(
                                                                         ':id'      => $id,
                                                                         ':mois'    => $mois,
                                                                         ':libelle' => $lesLibelles[array_rand($lesLibelles)],
                                                                         ':date'    => substr($mois, 0, 4) . '-' . substr($mois, 4, 2) . '-' . $jour,
                                                                         ':montant' => rand(500, 20000) / 100,
                                                                 ));
                                }
                                $nbCrees++;
                        }
                        PdoGsb::$monPdo->commit();
                } catch (\Exception $e) {
                        PdoGsb::$monPdo->rollBack();
                        throw $e;
                }
                return $nbCrees;
        }

        public function getLesTypesFraisForfait()
        {
                $req  = 'select fraisforfait.id as id, fraisforfait.libelle as libelle,
                fraisforfait.montant as montant from fraisforfait
                where fraisforfait.actif = 1 order by fraisforfait.id';
                $stmt = PdoGsb::$monPdo->prepare($req);
                $stmt->execute();
                return $stmt->fetchAll();
        }

        public function getInfosComptable($login, $mdp)
        {
                // actif et mdpDoitChanger ajoutés en phase 8 : le rejet d'un
                // compte désactivé est fait dans verifierAuthentification(),
                // commune aux trois rôles, pour qu'aucun point d'entrée ne
                // puisse l'oublier.
                $req  = 'select comptable.id as id, comptable.nom as nom,
                comptable.prenom as prenom, comptable.mdp as mdp,
                comptable.actif as actif, comptable.mdpDoitChanger as mdpDoitChanger
                from comptable where comptable.login = :login';
                $stmt = PdoGsb::$monPdo->prepare($req);
                $stmt->bindParam(':login', $login);
                $stmt->execute();
                return $this->verifierAuthentification($stmt->fetch(), $mdp);
        }

        public function getLesVisiteurs()
        {
                $req  = 'select visiteur.id as id, visiteur.nom as nom,
                visiteur.prenom as prenom from visiteur
                where visiteur.actif = 1
                order by visiteur.nom, visiteur.prenom';
                $stmt = PdoGsb::$monPdo->prepare($req);
                $stmt->execute();
                return $stmt->fetchAll();
        }

        public function majEtatFichesDuMois($mois, $etatDepart, $etatArrivee)
        {
                $req  = 'update fichefrais set idEtat = :etatArrivee, dateModif = now()
                where fichefrais.mois = :mois and fichefrais.idEtat = :etatDepart';
                $stmt = PdoGsb::$monPdo->prepare($req);
                $stmt->bindParam(':etatArrivee', $etatArrivee);
                $stmt->bindParam(':mois', $mois);
                $stmt->bindParam(':etatDepart', $etatDepart);
                $stmt->execute();
                // rowCount() donne le nombre de lignes réellement touchées :
                // c'est ce qui permet à la commande d'annoncer un résultat
                // exact plutôt qu'un « traitement terminé » sans contenu.
                return $stmt->rowCount();
        }

        public function validerFicheFrais($idVisiteur, $mois)
        {
                $req  = "update fichefrais set idEtat = 'VA', dateModif = now(),
                montantValide = (
                        select coalesce(sum(lignefraisforfait.quantite * lignefraisforfait.montantUnitaire), 0)
                        from lignefraisforfait
                        where lignefraisforfait.idVisiteur = :idVisiteur
                        and lignefraisforfait.mois = :mois)
                + (
                        select coalesce(sum(lignefraishorsforfait.montant), 0)
                        from lignefraishorsforfait
                        where lignefraishorsforfait.idVisiteur = :idVisiteur
                        and lignefraishorsforfait.mois = :mois)
                where fichefrais.idVisiteur = :idVisiteur and fichefrais.mois = :mois
                and fichefrais.idEtat = 'CL'";
                $stmt = PdoGsb::$monPdo->prepare($req);
                $stmt->bindParam(':idVisiteur', $idVisiteur);
                $stmt->bindParam(':mois', $mois);
                $stmt->execute();
                return $stmt->rowCount() == 1;
        }

        public function cloturerFichesDuMois($mois)
        {
                return $this->majEtatFichesDuMois($mois, 'CR', 'CL');
        }

        public function getRecapFiches()
        {
                $req  = 'select visiteur.id as idvisiteur, visiteur.nom as nom,
            visiteur.prenom as prenom, fichefrais.mois as mois,
            fichefrais.idEtat as idEtat
            from fichefrais
            inner join visiteur on visiteur.id = fichefrais.idVisiteur
            order by visiteur.nom, visiteur.prenom, fichefrais.mois desc';
                $stmt = PdoGsb::$monPdo->prepare($req);
                $stmt->execute();
                return $stmt->fetchAll();
        }

        private static $lesTables = array(
                'visiteur'       => 'visiteur',
                'comptable'      => 'comptable',
                'administrateur' => 'administrateur',
        );

        private static $lesPrefixes = array(
                'visiteur'       => array('v', 3),
                'comptable'      => array('c', 3),
                'administrateur' => array('ad', 2),
        );

        private function tableDuRole($role)
        {
                if (!isset(PdoGsb::$lesTables[$role])) {
                        throw new \InvalidArgumentException('Rôle inconnu : ' . $role);
                }
                return PdoGsb::$lesTables[$role];
        }

        /**
         * @return array les trois rôles gérés
         */
        public function getLesRoles()
        {
                return array_keys(PdoGsb::$lesTables);
        }

        public function getInfosAdministrateur($login, $mdp)
        {
                $req  = 'select administrateur.id as id, administrateur.nom as nom,
                administrateur.prenom as prenom, administrateur.mdp as mdp,
                administrateur.actif as actif,
                administrateur.mdpDoitChanger as mdpDoitChanger
                from administrateur where administrateur.login = :login';
                $stmt = PdoGsb::$monPdo->prepare($req);
                $stmt->bindParam(':login', $login);
                $stmt->execute();
                return $this->verifierAuthentification($stmt->fetch(), $mdp);
        }

        private function verifierAuthentification($laLigne, $mdp)
        {
                if (!is_array($laLigne) || !password_verify($mdp, $laLigne['mdp'])) {
                        return false;
                }
                if ((int) $laLigne['actif'] !== 1) {
                        return false;
                }
                // L'empreinte ne doit pas ressortir : elle n'a aucune raison de
                // circuler dans les contrôleurs ni la session.
                unset($laLigne['mdp']);
                return $laLigne;
        }

        public function getEtatCompte($role, $id)
        {
                $table = $this->tableDuRole($role);
                $req   = 'select actif, mdpDoitChanger from ' . $table . ' where id = :id';
                $stmt  = PdoGsb::$monPdo->prepare($req);
                $stmt->bindParam(':id', $id);
                $stmt->execute();
                return $stmt->fetch();
        }

        public function getLesMotsDePasseEnClair()
        {
                $req  = "select id, mdp from visiteur where mdp not like '\$2%'";
                $stmt = PdoGsb::$monPdo->prepare($req);
                $stmt->execute();
                return $stmt->fetchAll();
        }

        public function majEmpreinteMotDePasse($role, $id, $empreinte, $doitChanger)
        {
                $table = $this->tableDuRole($role);
                $req   = 'update ' . $table . '
                set mdp = :empreinte, mdpDoitChanger = :doitChanger where id = :id';
                $stmt  = PdoGsb::$monPdo->prepare($req);
                $stmt->bindParam(':empreinte', $empreinte);
                $stmt->bindParam(':doitChanger', $doitChanger, PDO::PARAM_INT);
                $stmt->bindParam(':id', $id);
                $stmt->execute();
        }

        private function construireFiltreComptes($recherche, $role, $etat, &$params)
        {
                $where  = ' where 1=1';
                $params = array();
                if ($recherche !== '' && $recherche !== null) {
                        // Une seule expression pour les quatre colonnes : l'utilisateur
                        // tape ce qu'il a sous les yeux sans choisir dans quel champ.
                        $where               .= ' and (nom like :recherche or prenom like :recherche
                                    or login like :recherche or id like :recherche)';
                        $params[':recherche'] = '%' . $recherche . '%';
                }
                if (isset(PdoGsb::$lesTables[$role])) {
                        $where          .= ' and role = :role';
                        $params[':role'] = $role;
                }
                if ($etat === 'actif' || $etat === 'desactive') {
                        $where           .= ' and actif = :actif';
                        $params[':actif'] = ($etat === 'actif') ? 1 : 0;
                }
                return $where;
        }

        public function compterLesComptes($recherche, $role, $etat)
        {
                $params = array();
                $req    = 'select count(*) as nb from v_compte'
                        . $this->construireFiltreComptes($recherche, $role, $etat, $params);
                $stmt   = PdoGsb::$monPdo->prepare($req);
                $stmt->execute($params);
                $laLigne = $stmt->fetch();
                return (int) $laLigne['nb'];
        }

        public function getLesComptes($recherche, $role, $etat, $limite, $decalage)
        {
                $params   = array();
                $limite   = max(1, (int) $limite);
                $decalage = max(0, (int) $decalage);
                $req      = 'select id, nom, prenom, login, role, actif, mdpDoitChanger, dateEmbauche
                from v_compte'
                        . $this->construireFiltreComptes($recherche, $role, $etat, $params)
                        . ' order by nom, prenom, id limit ' . $limite . ' offset ' . $decalage;
                $stmt     = PdoGsb::$monPdo->prepare($req);
                $stmt->execute($params);
                return $stmt->fetchAll();
        }

        public function getCompte($role, $id)
        {
                $this->tableDuRole($role);
                if ($role === 'visiteur') {
                        $req = "select id, nom, prenom, login, actif, mdpDoitChanger,
                        adresse, cp, ville, dateEmbauche, idTypeVehicule,
                        'visiteur' as role
                        from visiteur where id = :id";
                } else {
                        $req = 'select id, nom, prenom, login, actif, mdpDoitChanger,
                        null as adresse, null as cp, null as ville,
                        null as dateEmbauche, null as idTypeVehicule,
                        :role as role
                        from ' . $this->tableDuRole($role) . ' where id = :id';
                }
                $stmt = PdoGsb::$monPdo->prepare($req);
                if ($role !== 'visiteur') {
                        $stmt->bindParam(':role', $role);
                }
                $stmt->bindParam(':id', $id);
                $stmt->execute();
                return $stmt->fetch();
        }

        public function loginExiste($login, $roleExclu = null, $idExclu = null)
        {
                $req = 'select count(*) as nb from v_compte where login = :login';
                if ($roleExclu !== null && $idExclu !== null) {
                        $req .= ' and not (role = :role and id = :id)';
                }
                $stmt = PdoGsb::$monPdo->prepare($req);
                $stmt->bindParam(':login', $login);
                if ($roleExclu !== null && $idExclu !== null) {
                        $stmt->bindParam(':role', $roleExclu);
                        $stmt->bindParam(':id', $idExclu);
                }
                $stmt->execute();
                $laLigne = $stmt->fetch();
                return ((int) $laLigne['nb']) > 0;
        }

        public function genererMatricule($role)
        {
                $table                   = $this->tableDuRole($role);
                list($prefixe, $largeur) = PdoGsb::$lesPrefixes[$role];
                $longueur                = strlen($prefixe) + $largeur;

                $req   = 'select max(cast(substring(id, :debut) as unsigned)) as maxi
                from ' . $table . '
                where id like :motif and char_length(id) = :longueur';
                $stmt  = PdoGsb::$monPdo->prepare($req);
                $debut = strlen($prefixe) + 1;
                $motif = $prefixe . '%';
                $stmt->bindParam(':debut', $debut, PDO::PARAM_INT);
                $stmt->bindParam(':motif', $motif);
                $stmt->bindParam(':longueur', $longueur, PDO::PARAM_INT);
                $stmt->execute();
                $laLigne = $stmt->fetch();

                $numero = ((int) $laLigne['maxi']) + 1;
                if ($numero >= pow(10, $largeur)) {
                        return false;
                }
                return $prefixe . str_pad($numero, $largeur, '0', STR_PAD_LEFT);
        }

        public function creerCompte($role, array $donnees)
        {
                $table = $this->tableDuRole($role);
                if ($role === 'visiteur') {
                        $req = 'insert into visiteur(id, nom, prenom, login, mdp,
                        mdpDoitChanger, actif, dateEmbauche, idTypeVehicule, adresse, cp, ville)
                        values(:id, :nom, :prenom, :login, :mdp,
                        :doitChanger, 1, :dateEmbauche, :idTypeVehicule, :adresse, :cp, :ville)';
                } else {
                        $req = 'insert into ' . $table . '(id, nom, prenom, login, mdp,
                        mdpDoitChanger, actif)
                        values(:id, :nom, :prenom, :login, :mdp, :doitChanger, 1)';
                }
                $stmt = PdoGsb::$monPdo->prepare($req);

                for ($tentative = 0; $tentative < 10; $tentative++) {
                        $id = $this->genererMatricule($role);
                        if ($id === false) {
                                throw new \RuntimeException(
                                        'Plus aucun matricule disponible pour le rôle ' . $role
                                );
                        }
                        $stmt->bindValue(':id', $id);
                        $stmt->bindValue(':nom', $donnees['nom']);
                        $stmt->bindValue(':prenom', $donnees['prenom']);
                        $stmt->bindValue(':login', $donnees['login']);
                        $stmt->bindValue(':mdp', $donnees['empreinte']);
                        $stmt->bindValue(':doitChanger', $donnees['doitChanger'], PDO::PARAM_INT);
                        if ($role === 'visiteur') {
                                $stmt->bindValue(':dateEmbauche', $donnees['dateEmbauche']);
                                $stmt->bindValue(':idTypeVehicule', $donnees['idTypeVehicule']);
                                $stmt->bindValue(':adresse', $donnees['adresse']);
                                $stmt->bindValue(':cp', $donnees['cp']);
                                $stmt->bindValue(':ville', $donnees['ville']);
                        }
                        try {
                                $stmt->execute();
                                return $id;
                        } catch (\PDOException $e) {
                                if ($e->getCode() !== '23000' ||
                                                stripos($e->getMessage(), 'PRIMARY') === false) {
                                        throw $e;
                                }
                        }
                }
                throw new \RuntimeException(
                        "Impossible d'attribuer un matricule après 10 tentatives."
                );
        }

        public function majCompte($role, $id, array $donnees)
        {
                $table = $this->tableDuRole($role);
                if ($role === 'visiteur') {
                        $req = 'update visiteur set nom = :nom, prenom = :prenom,
                        login = :login, dateEmbauche = :dateEmbauche,
                        idTypeVehicule = :idTypeVehicule, adresse = :adresse,
                        cp = :cp, ville = :ville
                        where id = :id';
                } else {
                        $req = 'update ' . $table . ' set nom = :nom, prenom = :prenom,
                        login = :login where id = :id';
                }
                $stmt = PdoGsb::$monPdo->prepare($req);
                $stmt->bindValue(':nom', $donnees['nom']);
                $stmt->bindValue(':prenom', $donnees['prenom']);
                $stmt->bindValue(':login', $donnees['login']);
                if ($role === 'visiteur') {
                        $stmt->bindValue(':dateEmbauche', $donnees['dateEmbauche']);
                        $stmt->bindValue(':idTypeVehicule', $donnees['idTypeVehicule']);
                        $stmt->bindValue(':adresse', $donnees['adresse']);
                        $stmt->bindValue(':cp', $donnees['cp']);
                        $stmt->bindValue(':ville', $donnees['ville']);
                }
                $stmt->bindValue(':id', $id);
                $stmt->execute();
                return $stmt->rowCount();
        }

        public function majActifCompte($role, $id, $actif)
        {
                $table = $this->tableDuRole($role);
                $actif = $actif ? 1 : 0;
                $req   = 'update ' . $table . ' set actif = :actif
                where id = :id and actif <> :actif';
                $stmt  = PdoGsb::$monPdo->prepare($req);
                $stmt->bindParam(':actif', $actif, PDO::PARAM_INT);
                $stmt->bindParam(':id', $id);
                $stmt->execute();
                return $stmt->rowCount();
        }

        public function supprimerCompte($role, $id)
        {
                $table = $this->tableDuRole($role);
                $req   = 'delete from ' . $table . ' where id = :id';
                $stmt  = PdoGsb::$monPdo->prepare($req);
                $stmt->bindParam(':id', $id);
                $stmt->execute();
                return $stmt->rowCount();
        }

        public function compterDonneesLiees($id)
        {
                $req  = "select
                (select count(*) from fichefrais where idVisiteur = :id) as nbFiches,
                (select count(*) from fichefrais where idVisiteur = :id
                        and idEtat in ('VA','MP','RB')) as nbFichesValidees,
                (select count(*) from lignefraisforfait where idVisiteur = :id) as nbLignesForfait,
                (select count(*) from lignefraishorsforfait where idVisiteur = :id) as nbLignesHorsForfait,
                (select count(*) from lignefraishorsforfait where idVisiteur = :id
                        and nomFichier is not null) as nbJustificatifs";
                $stmt = PdoGsb::$monPdo->prepare($req);
                $stmt->bindParam(':id', $id);
                $stmt->execute();
                return $stmt->fetch();
        }

        public function compterAdministrateursActifs()
        {
                $req  = 'select count(*) as nb from administrateur where actif = 1';
                $stmt = PdoGsb::$monPdo->prepare($req);
                $stmt->execute();
                $laLigne = $stmt->fetch();
                return (int) $laLigne['nb'];
        }

        public function journaliser($idAdmin, $nomAdmin, $action,
                                    $roleCible, $idCible, $nomCible, $detail = null)
        {
                $req  = 'insert into journaladmin(horodatage, idAdministrateur,
                nomAdministrateur, action, roleCible, idCible, nomCible, detail)
                values(now(), :idAdmin, :nomAdmin, :action, :roleCible, :idCible,
                :nomCible, :detail)';
                $stmt = PdoGsb::$monPdo->prepare($req);
                $stmt->bindParam(':idAdmin', $idAdmin);
                $stmt->bindParam(':nomAdmin', $nomAdmin);
                $stmt->bindParam(':action', $action);
                $stmt->bindParam(':roleCible', $roleCible);
                $stmt->bindParam(':idCible', $idCible);
                $stmt->bindParam(':nomCible', $nomCible);
                $stmt->bindParam(':detail', $detail);
                $stmt->execute();
        }

        private function construireFiltreJournal($idAdmin, $action, $cible, &$params)
        {
                $where  = ' where 1=1';
                $params = array();
                if ($idAdmin !== '' && $idAdmin !== null) {
                        $where             .= ' and idAdministrateur = :idAdmin';
                        $params[':idAdmin'] = $idAdmin;
                }
                if ($action !== '' && $action !== null) {
                        $where            .= ' and action = :action';
                        $params[':action'] = $action;
                }
                if ($cible !== '' && $cible !== null) {
                        $where           .= ' and (idCible like :cible or nomCible like :cible)';
                        $params[':cible'] = '%' . $cible . '%';
                }
                return $where;
        }

        public function compterLeJournal($idAdmin, $action, $cible)
        {
                $params = array();
                $req    = 'select count(*) as nb from journaladmin'
                        . $this->construireFiltreJournal($idAdmin, $action, $cible, $params);
                $stmt   = PdoGsb::$monPdo->prepare($req);
                $stmt->execute($params);
                $laLigne = $stmt->fetch();
                return (int) $laLigne['nb'];
        }

        public function getLeJournal($idAdmin, $action, $cible, $limite, $decalage)
        {
                $params   = array();
                $limite   = max(1, (int) $limite);
                $decalage = max(0, (int) $decalage);
                $req      = 'select id, horodatage, idAdministrateur, nomAdministrateur,
                action, roleCible, idCible, nomCible, detail from journaladmin'
                        . $this->construireFiltreJournal($idAdmin, $action, $cible, $params)
                        . ' order by horodatage desc, id desc
                        limit ' . $limite . ' offset ' . $decalage;
                $stmt     = PdoGsb::$monPdo->prepare($req);
                $stmt->execute($params);
                return $stmt->fetchAll();
        }

        public function getLesActionsDuJournal()
        {
                $req  = 'select distinct action from journaladmin order by action';
                $stmt = PdoGsb::$monPdo->prepare($req);
                $stmt->execute();
                return $stmt->fetchAll();
        }

        public function getLesAuteursDuJournal()
        {
                $req  = 'select distinct idAdministrateur, nomAdministrateur
                from journaladmin order by nomAdministrateur';
                $stmt = PdoGsb::$monPdo->prepare($req);
                $stmt->execute();
                return $stmt->fetchAll();
        }
        /**
         * Détail des trajets (phase 9)
         * =========================================================================
         */

        /**
         * Expose l'identifiant de la ligne kilométrique
         *
         * Les contrôleurs en ont besoin : l'écran de saisie doit traiter cette
         * ligne à part, sa quantité étant désormais dérivée des trajets. Un
         * accesseur plutôt que la constante lue directement, pour que les
         * contrôleurs continuent de ne connaître que le service injecté.
         *
         * @return string
         */
        public function getIdFraisKilometrique()
        {
                return PdoGsb::ID_FRAIS_KILOMETRIQUE;
        }

        /**
         * Retourne les trajets d'une fiche, du plus ancien au plus récent
         *
         * La date est convertie au format français ici, comme le fait
         * getLesFraisHorsForfait() : les gabarits n'ont pas à connaître le format
         * de stockage.
         *
         * @param $idVisiteur
         * @param $mois sous la forme aaaamm
         * @return tableau : id, date, libelle, distance
         */
        public function getLesTrajets($idVisiteur, $mois)
        {
                $req  = 'select id, date, libelle, distance from trajet
                where idVisiteur = :idVisiteur and mois = :mois
                order by date, id';
                $stmt = PdoGsb::$monPdo->prepare($req);
                $stmt->bindParam(':idVisiteur', $idVisiteur);
                $stmt->bindParam(':mois', $mois);
                $stmt->execute();
                $lesLignes = $stmt->fetchAll();
                $nbLignes  = count($lesLignes);
                for ($i = 0; $i < $nbLignes; $i++) {
                        $lesLignes[$i]['date'] = dateAnglaisVersFrancais($lesLignes[$i]['date']);
                }
                return $lesLignes;
        }

        /**
         * Enregistre un trajet et met à jour la distance de la fiche
         *
         * Les deux opérations sont indissociables : un trajet enregistré sans
         * recalcul laisserait la ligne kilométrique en retard sur ses trajets, et
         * le visiteur verrait un total qui ne correspond pas à ce qu'il vient de
         * saisir. Elles sont donc dans une transaction.
         *
         * @param $date au format français jj/mm/aaaa
         * @return l'identifiant du trajet créé
         */
        public function creeTrajet($idVisiteur, $mois, $date, $libelle, $distance)
        {
                PdoGsb::$monPdo->beginTransaction();
                try {
                        $req  = 'insert into trajet(idVisiteur, mois, date, libelle, distance)
                        values(:idVisiteur, :mois, :date, :libelle, :distance)';
                        $stmt = PdoGsb::$monPdo->prepare($req);
                        $stmt->bindParam(':idVisiteur', $idVisiteur);
                        $stmt->bindParam(':mois', $mois);
                        $dateAng = dateFrancaisVersAnglais($date);
                        $stmt->bindParam(':date', $dateAng);
                        $stmt->bindParam(':libelle', $libelle);
                        $stmt->bindValue(':distance', (int) $distance, PDO::PARAM_INT);
                        $stmt->execute();
                        $id = PdoGsb::$monPdo->lastInsertId();
                        $this->recalculerDistance($idVisiteur, $mois);
                        PdoGsb::$monPdo->commit();
                        return $id;
                } catch (\Exception $e) {
                        PdoGsb::$monPdo->rollBack();
                        throw $e;
                }
        }

        /**
         * Supprime un trajet et met à jour la distance de la fiche
         *
         * @return le nombre de lignes supprimées : 0 ou 1
         */
        public function supprimerTrajet($idVisiteur, $mois, $id)
        {
                PdoGsb::$monPdo->beginTransaction();
                try {
                        $req  = 'delete from trajet where id = :id';
                        $stmt = PdoGsb::$monPdo->prepare($req);
                        $stmt->bindParam(':id', $id);
                        $stmt->execute();
                        $nb = $stmt->rowCount();
                        $this->recalculerDistance($idVisiteur, $mois);
                        PdoGsb::$monPdo->commit();
                        return $nb;
                } catch (\Exception $e) {
                        PdoGsb::$monPdo->rollBack();
                        throw $e;
                }
        }

        /**
         * Recalcule la quantité de la ligne kilométrique d'une fiche
         *
         * La quantité est DÉRIVÉE de la somme des trajets. Elle est recalculée et
         * jamais incrémentée : c'est la décision 2.4, prise pour nbJustificatifs
         * et reprise ici pour la même raison. Un compteur qu'on incrémente dérive
         * dès qu'une opération échoue à mi-chemin ou qu'une ligne est supprimée
         * par une cascade ; une somme recalculée ne peut pas se désynchroniser.
         *
         * coalesce : une fiche dont on vient de supprimer le dernier trajet n'a
         * plus aucune ligne à sommer, et sum() renvoie NULL -- ce qui violerait
         * la contrainte NOT NULL de quantite.
         *
         * Le montantUnitaire n'est PAS touché : il reste celui figé à la création
         * de la ligne (décision 1.1). Seule la quantité bouge.
         */
        public function recalculerDistance($idVisiteur, $mois)
        {
                $req  = 'update lignefraisforfait
                set quantite = coalesce((
                        select sum(distance) from trajet
                        where trajet.idVisiteur = :idVisiteur and trajet.mois = :mois), 0)
                where lignefraisforfait.idVisiteur = :idVisiteur
                and lignefraisforfait.mois = :mois
                and lignefraisforfait.idFraisForfait = :idFraisKm';
                $stmt = PdoGsb::$monPdo->prepare($req);
                $stmt->bindParam(':idVisiteur', $idVisiteur);
                $stmt->bindParam(':mois', $mois);
                $idFraisKm = PdoGsb::ID_FRAIS_KILOMETRIQUE;
                $stmt->bindParam(':idFraisKm', $idFraisKm);
                $stmt->execute();
        }

        /**
         * Liste les fiches dont la quantité kilométrique ne correspond plus à la
         * somme de leurs trajets
         *
         * Sert à la commande gsb:verifier-distances. L'invariant est maintenu par
         * l'application et non par la base : aucun déclencheur ne l'impose, donc
         * il faut pouvoir le contrôler.
         *
         * La jointure est un LEFT JOIN et la somme passe par coalesce : une fiche
         * ayant une quantité mais PLUS AUCUN trajet est précisément le cas qu'on
         * cherche, et un INNER JOIN l'aurait écartée du résultat.
         *
         * @return tableau : idVisiteur, mois, quantite, sommeTrajets
         */
        public function getLesDistancesDivergentes()
        {
                $req  = 'select l.idVisiteur as idVisiteur, l.mois as mois,
                l.quantite as quantite, coalesce(t.tot, 0) as sommeTrajets
                from lignefraisforfait l
                left join (select idVisiteur, mois, sum(distance) as tot
                             from trajet group by idVisiteur, mois) t
                       on t.idVisiteur = l.idVisiteur and t.mois = l.mois
                where l.idFraisForfait = :idFraisKm
                and l.quantite <> coalesce(t.tot, 0)
                order by l.idVisiteur, l.mois';
                $stmt = PdoGsb::$monPdo->prepare($req);
                $idFraisKm = PdoGsb::ID_FRAIS_KILOMETRIQUE;
                $stmt->bindParam(':idFraisKm', $idFraisKm);
                $stmt->execute();
                return $stmt->fetchAll();
        }

        /**
         * Vérifie qu'un trajet appartient bien à la fiche indiquée
         *
         * Même garde que estValideSuppressionFrais() et pour la même raison : sans
         * ce contrôle, un identifiant modifié dans l'URL supprimerait le trajet
         * d'un autre visiteur ou d'un autre mois.
         *
         * @return vrai ou faux
         */
        public function estValideSuppressionTrajet($idVisiteur, $mois, $id)
        {
                $req  = 'select count(*) as nb from trajet
                where id = :id and idVisiteur = :idVisiteur and mois = :mois';
                $stmt = PdoGsb::$monPdo->prepare($req);
                $stmt->bindParam(':id', $id);
                $stmt->bindParam(':idVisiteur', $idVisiteur);
                $stmt->bindParam(':mois', $mois);
                $stmt->execute();
                $laLigne = $stmt->fetch();
                return $laLigne['nb'] == 1;
        }

        /**
         * Indique si la fiche d'un visiteur pour un mois est encore modifiable
         *
         * Un trajet ne s'ajoute ni ne se supprime sur une fiche clôturée,
         * validée, mise en paiement ou remboursée : cela modifierait la quantité
         * kilométrique, donc un montant déjà soumis au comptable ou déjà payé.
         * L'état 'CR' est le seul qui autorise la saisie.
         *
         * @return vrai ou faux
         */
        public function ficheEstEnSaisie($idVisiteur, $mois)
        {
                $req  = "select count(*) as nb from fichefrais
                where idVisiteur = :idVisiteur and mois = :mois and idEtat = 'CR'";
                $stmt = PdoGsb::$monPdo->prepare($req);
                $stmt->bindParam(':idVisiteur', $idVisiteur);
                $stmt->bindParam(':mois', $mois);
                $stmt->execute();
                $laLigne = $stmt->fetch();
                return $laLigne['nb'] == 1;
        }
}
