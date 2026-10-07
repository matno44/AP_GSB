<?php
/** 
 * Fonctions pour l'application GSB
 
 * @package default
 * @author Cheri Bibi
 * @version    1.0
 */
 /**
 * Teste si un quelconque visiteur est connecté
 * @return vrai ou faux 
 */
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Request;

function estConnecte($session){
    // Les infos du visiteur sont posées en session par
    // HomeController::validerconnexionAction() via $session->set().
    // Le flash bag ne convient pas ici : il est vidé à la première lecture,
    // la connexion serait donc perdue dès la page suivante.
    return $session->has('id');
}
/**
 * Enregistre dans une variable session les infos d'un visiteur
 
 * @param $id 
 * @param $nom
 * @param $prenom
 */
function connecter($session, $id,$nom,$prenom){
    // Conserve la signature d'origine : tout le code existant qui appelle
    // connecter() continue de fonctionner et ouvre une session de visiteur.
    connecterAvecRole($session, $id, $nom, $prenom, 'visiteur');
}
/**
 * Enregistre en session les infos d'un utilisateur et son rôle

 * Le rôle vaut 'visiteur' ou 'comptable'. C'est lui, et lui seul, qui
 * détermine ce que l'utilisateur a le droit de faire : les contrôleurs ne
 * regardent jamais la table d'où vient le compte, seulement la session.

 * @param $id
 * @param $nom
 * @param $prenom
 * @param $role
 */
function connecterAvecRole($session, $id, $nom, $prenom, $role, $doitChanger = 0){
    // FIXATION DE SESSION. Avant cette ligne, l'identifiant de session ne
    // changeait pas à la connexion : un attaquant ayant réussi à imposer un
    // identifiant au navigateur de la victime AVANT qu'elle se connecte se
    // retrouvait, après coup, connecté à sa place — il connaissait
    // l'identifiant d'une session devenue authentifiée.
    // migrate() produit un nouvel identifiant en conservant les données ;
    // l'argument true détruit en plus l'ancien enregistrement, pour que
    // l'identifiant connu de l'attaquant ne désigne plus rien.
    // À faire AVANT d'écrire les infos de connexion, pour qu'aucune donnée
    // d'utilisateur authentifié n'existe un instant sous l'ancien identifiant.
    $session->migrate(true);
    $session->set('id',$id);
    $session->set('nom',$nom);
    $session->set('prenom',$prenom);
    $session->set('role',$role);
    // Lu à chaque requête par GardeCompteListener. Valeur par défaut 0 :
    // connecter(), qui existait avant la phase 8, continue d'ouvrir une
    // session ordinaire sans rien savoir de cet indicateur.
    $session->set('mdpDoitChanger', (int) $doitChanger);
}
/**
 * Indique si l'utilisateur connecté est un comptable

 * Le rôle est posé en session à la connexion par connecterAvecRole().
 * Écrite en phase 2 alors qu'aucun compte comptable n'existait encore, cette
 * fonction a permis de poser dès ce moment-là la règle d'accès aux
 * justificatifs. La phase 5 n'a eu qu'à créer l'acteur : aucune ligne de
 * JustificatifController ni de FichePdfController n'a eu à être modifiée.

 * @return vrai ou faux
 */
function estComptable($session){
    return $session->get('role') === 'comptable';
}
/**
 * Indique si l'utilisateur connecté est un visiteur médical

 * Pendant de estComptable(). Les écrans « mes fiches », « ma saisie » et
 * « mon véhicule » travaillent tous sur l'id en session en le tenant pour
 * un id de visiteur : un comptable qui les atteint par leur URL faisait
 * chercher — et pire, créer — une fiche de frais au nom d'un identifiant
 * absent de la table visiteur, ce qui violait la clé étrangère
 * fk_fichefrais_visiteur. Le menu ne propose jamais ces liens à un
 * comptable, mais masquer un lien n'est pas contrôler un accès : la règle
 * doit être appliquée côté serveur.

 * @return vrai ou faux
 */
function estVisiteur($session){
    return $session->get('role') === 'visiteur';
}
/**
 * Indique si l'utilisateur connecté est un administrateur (phase 8)

 * Troisième rôle, distinct du visiteur et du comptable. Un administrateur ne
 * saisit pas de frais et n'en valide pas : il ne gère que des comptes. Il
 * n'hérite donc d'aucun droit des deux autres rôles, et estVisiteur() comme
 * estComptable() renvoient faux pour lui — ce qui lui interdit déjà tous les
 * écrans métier sans qu'une ligne ait été ajoutée à leurs contrôleurs.

 * @return vrai ou faux
 */
function estAdministrateur($session){
    return $session->get('role') === 'administrateur';
}
/**
 * Indique si l'utilisateur doit changer son mot de passe avant d'aller plus loin

 * Levé à la création d'un compte et à chaque réinitialisation : dans les deux
 * cas, un tiers — l'administrateur — connaît le mot de passe en cours. Tant que
 * l'indicateur est levé, GardeCompteListener renvoie l'utilisateur sur l'écran
 * de changement, quelle que soit l'URL demandée.

 * @return vrai ou faux
 */
function doitChangerMotDePasse($session){
    return (int) $session->get('mdpDoitChanger') === 1;
}
/**
 * Vérifie qu'un mot de passe respecte les règles minimales

 * 12 caractères et trois familles de caractères. Le seuil est volontairement
 * plus haut que les 8 caractères des mots de passe d'origine : ceux-là étaient
 * générés par le jeu de données du sujet, pas choisis par des humains.

 * Les messages énoncent la règle complète plutôt que la seule condition violée,
 * pour éviter à l'utilisateur une succession d'allers-retours.

 * @param $mdp le mot de passe en clair
 * @return un tableau de messages d'erreur, vide si le mot de passe convient
 */
function valideMotDePasse($mdp){
    $lesErreurs = array();
    if (strlen($mdp) < 12) {
        $lesErreurs[] = 'Le mot de passe doit comporter au moins 12 caractères.';
    }
    if (!preg_match('/[a-z]/', $mdp) || !preg_match('/[A-Z]/', $mdp)
        || !preg_match('/[0-9]/', $mdp)) {
        $lesErreurs[] = 'Le mot de passe doit contenir au moins une minuscule, '
            . 'une majuscule et un chiffre.';
    }
    return $lesErreurs;
}
/**
 * Engendre un mot de passe temporaire lisible à l'oral

 * Trois groupes de quatre caractères séparés par des tirets. Les caractères
 * ambigus sont exclus de l'alphabet (0/O, 1/l/I) : ce mot de passe est recopié
 * à la main ou dicté par téléphone, et une confusion coûterait un appel de plus.

 * random_int() et non rand() : le générateur doit être cryptographiquement sûr,
 * sinon le mot de passe devient prévisible pour qui connaît l'heure de création.

 * L'alphabet contient minuscules, majuscules et chiffres, et la construction en
 * force la présence : le mot de passe produit satisfait donc toujours
 * valideMotDePasse().

 * @return string
 */
function genererMotDePasseTemporaire(){
    $minuscules = 'abcdefghijkmnpqrstuvwxyz';   // sans l ni o
    $majuscules = 'ABCDEFGHJKLMNPQRSTUVWXYZ';   // sans I ni O
    $chiffres   = '23456789';                   // sans 0 ni 1
    $alphabet   = $minuscules . $majuscules . $chiffres;

    // Une lettre de chaque famille d'abord, pour garantir la règle, puis on
    // complète et on mélange : sans le mélange, les trois premiers caractères
    // seraient toujours de la même nature.
    $caracteres = array(
        $minuscules[random_int(0, strlen($minuscules) - 1)],
        $majuscules[random_int(0, strlen($majuscules) - 1)],
        $chiffres[random_int(0, strlen($chiffres) - 1)],
    );
    for ($i = count($caracteres); $i < 12; $i++) {
        $caracteres[] = $alphabet[random_int(0, strlen($alphabet) - 1)];
    }
    // shuffle() n'est pas sûr cryptographiquement : on mélange nous-mêmes avec
    // random_int(), par un Fisher-Yates.
    for ($i = count($caracteres) - 1; $i > 0; $i--) {
        $j = random_int(0, $i);
        $tmp = $caracteres[$i];
        $caracteres[$i] = $caracteres[$j];
        $caracteres[$j] = $tmp;
    }
    $mdp = implode('', $caracteres);

    return substr($mdp, 0, 4) . '-' . substr($mdp, 4, 4) . '-' . substr($mdp, 8, 4);
}/**
 * Traduit un mois au format aaaamm en libellé français

 * Exemple : "202609" devient "Septembre 2026".
 * Un tableau plutôt que la classe IntlDateFormatter : le résultat ne dépend
 * alors ni de l'extension intl, ni de la locale configurée sur le serveur,
 * et il est lisible tel quel par un correcteur.

 * @param $mois au format aaaamm
 * @return le libellé, ou le mois inchangé si le format est inattendu
 */
function moisEnLettres($mois){
    $lesMois = array('01'=>'Janvier', '02'=>'Février', '03'=>'Mars',
                     '04'=>'Avril',   '05'=>'Mai',     '06'=>'Juin',
                     '07'=>'Juillet', '08'=>'Août',    '09'=>'Septembre',
                     '10'=>'Octobre', '11'=>'Novembre','12'=>'Décembre');
    // Contrôle strict : "202abc" a bien 6 caractères mais n'est pas un mois.
    // Sans ce test, le transtypage en entier produirait des valeurs absurdes.
    if(!preg_match('/^[0-9]{6}$/', $mois)){
        return $mois;
    }
    $numAnnee = substr($mois, 0, 4);
    $numMois = substr($mois, 4, 2);
    if(!isset($lesMois[$numMois])){
        return $mois;
    }
    return $lesMois[$numMois].' '.$numAnnee;
}
/**
 * Retourne le mois précédant celui passé en argument

 * Exemple : "202601" devient "202512". Le passage de janvier à décembre de
 * l'année précédente est le cas limite que ce calcul doit gérer.

 * @param $mois au format aaaamm
 * @return le mois précédent au format aaaamm
 */
function moisPrecedent($mois){
    if(!preg_match('/^[0-9]{6}$/', $mois)){
        return $mois;
    }
    $numAnnee = (int) substr($mois, 0, 4);
    $numMois = (int) substr($mois, 4, 2);
    $numMois--;
    if($numMois == 0){
        $numMois = 12;
        $numAnnee--;
    }
    return $numAnnee.str_pad($numMois, 2, '0', STR_PAD_LEFT);
}
/**
 * Additionne une colonne d'un tableau de lignes

 * Les montants viennent de PDO, qui restitue les colonnes DECIMAL sous forme
 * de CHAÎNES : d'où le transtypage explicite. Sans lui l'addition
 * fonctionnerait quand même par conversion implicite, mais le code ne dirait
 * pas ce qu'il fait.

 * @param $lesLignes tableau de tableaux associatifs
 * @param $cle le nom de la colonne à additionner
 * @return la somme, en nombre à virgule -0 si le tableau est vide
 */
function totalDesLignes($lesLignes, $cle){
    $total = 0.0;
    foreach($lesLignes as $uneLigne){
        $total += (float) $uneLigne[$cle];
    }
    return $total;
}
/**
 * Détruit la session active
 */
function deconnecter($session){
    // invalidate() plutôt que clear() : clear() vide les données mais CONSERVE
    // l'identifiant de session, qui reste donc valable après la déconnexion.
    // invalidate() vide ET régénère l'identifiant, de sorte que l'ancien ne
    // désigne plus rien — utile sur un poste partagé.
    $session->invalidate();
}
/**
 * Transforme une date au format français jj/mm/aaaa vers le format anglais aaaa-mm-jj
 
 * @param $madate au format  jj/mm/aaaa
 * @return la date au format anglais aaaa-mm-jj
*/
function dateFrancaisVersAnglais($maDate){
	@list($jour,$mois,$annee) = explode('/',$maDate);
	return date('Y-m-d',mktime(0,0,0,$mois,$jour,$annee));
}
/**
 * Transforme une date au format format anglais aaaa-mm-jj vers le format français jj/mm/aaaa 
 
 * @param $madate au format  aaaa-mm-jj
 * @return la date au format format français jj/mm/aaaa
*/
function dateAnglaisVersFrancais($maDate){
   @list($annee,$mois,$jour)=explode('-',$maDate);
   $date="$jour"."/".$mois."/".$annee;
   return $date;
}
/**
 * retourne le mois au format aaaamm selon le jour dans le mois
 
 * @param $date au format  jj/mm/aaaa
 * @return le mois au format aaaamm
*/
function getMois($date){
		@list($jour,$mois,$annee) = explode('/',$date);
		if(strlen($mois) == 1){
			$mois = "0".$mois;
		}
		return $annee.$mois;
}

/* gestion des erreurs*/
/**
 * Indique si une valeur est un entier positif ou nul
 
 * @param $valeur
 * @return vrai ou faux
*/
function estEntierPositif($valeur) {
	// "au moins un chiffre, et rien d'autre" : l'ancien test se contentait
	// de refuser les caractères non numériques et acceptait donc la chaîne
	// vide, qui finissait en erreur sur une colonne INT.
	return preg_match("/^[0-9]+$/", $valeur) == 1;

}

/**
 * Indique si un tableau de valeurs est constitué d'entiers positifs ou nuls
 
 * @param $tabEntiers : le tableau
 * @return vrai ou faux
*/
function estTableauEntiers($tabEntiers) {
	if(!is_array($tabEntiers)){
		return false;
	}
	$ok = true;
	foreach($tabEntiers as $unEntier){
		if(!estEntierPositif($unEntier)){
		 	$ok=false; 
		}
	}
	return $ok;
}
/**
 * Vérifie si une date est inférieure d'un an à la date actuelle
 
 * @param $dateTestee 
 * @return vrai ou faux
*/
function estDateDepassee($dateTestee){
	$dateActuelle=date("d/m/Y");
	@list($jour,$mois,$annee) = explode('/',$dateActuelle);
	$annee--;
	$AnPasse = $annee.$mois.$jour;
	@list($jourTeste,$moisTeste,$anneeTeste) = explode('/',$dateTestee);
	return ($anneeTeste.$moisTeste.$jourTeste < $AnPasse); 
}
/**
 * Vérifie la validité du format d'une date française jj/mm/aaaa 
 
 * @param $date 
 * @return vrai ou faux
*/
function estDateValide($date){
	$tabDate = explode('/',$date);
	$dateOK = true;
	if (count($tabDate) != 3) {
	    $dateOK = false;
    }
    else {
		if (!estTableauEntiers($tabDate)) {
			$dateOK = false;
		}
		else {
			if (!checkdate($tabDate[1], $tabDate[0], $tabDate[2])) {
				$dateOK = false;
			}
		}
    }
	return $dateOK;
}

/**
 * Vérifie que le tableau de frais ne contient que des valeurs numériques 
 
 * @param $lesFrais 
 * @return vrai ou faux
*/
function lesQteFraisValides($lesFrais){
	return estTableauEntiers($lesFrais);
}
/**
 * Vérifie la validité des trois arguments : la date, le libellé du frais et le montant 
 
 * des message d'erreurs sont ajoutés au tableau des erreurs
 
 * @param $dateFrais 
 * @param $libelle 
 * @param $montant
 */
//function valideInfosFrais($session,$dateFrais,$libelle,$montant){
//	if($dateFrais==""){
//		ajouterErreur($session,"Le champ date ne doit pas être vide");
//	}
//	else{
//		if(!estDatevalide($dateFrais)){
//			ajouterErreur($session,"Date invalide");
//		}	
//		else{
//			if(estDateDepassee($dateFrais)){
//				ajouterErreur($session,"date d'enregistrement du frais dépassé, plus de 1 an");
//			}			
//		}
//	}
//	if($libelle == ""){
//		ajouterErreur($session,"Le champ description ne peut pas être vide");
//	}
//	if($montant == ""){
//		ajouterErreur($session,"Le champ montant ne peut pas être vide");
//	}
//	else
//		if( !is_numeric($montant) ){
//			ajouterErreur($session,"Le champ montant doit être numérique");
//		}
//}


function valideInfosFrais($dateFrais,$libelle,$montant){
    $lesErreurs = array();	
    if($dateFrais==""){
		$lesErreurs[] = "Le champ date ne doit pas être vide";
	}
	else{
		if(!estDatevalide($dateFrais)){
			$lesErreurs[] = "Date invalide";
		}	
		else{
			if(estDateDepassee($dateFrais)){
				$lesErreurs[] = "date d'enregistrement du frais dépassé de plus de 1 an";
			}			
		}
	}
	if($libelle == ""){
		$lesErreurs[] = "Le champ description ne peut pas être vide";
	}
	if($montant == ""){
		$lesErreurs[] = "Le champ montant ne peut pas être vide";
	}
	else
		if( !is_numeric($montant) ){
			$lesErreurs[] = "Le champ montant doit être numérique";
		}
        return $lesErreurs;
}

/**
 * Ajoute le libellé d'une erreur au tableau des erreurs 
 
 * @param $msg : le libellé de l'erreur 
 */
function ajouterErreur($session, $msg){
  
 

    $session->getFlashBag()->add('erreurs',$msg);
    
}
function existeErreurs($session){
    
    return $session->getFlashBag()->has('erreurs');
}
function getLesErreurs($session){
    
    return  $session->getFlashBag()->get('erreurs');
}
/**
 * Retoune le nombre de lignes du tableau des erreurs 
 
 * @return le nombre d'erreurs
 */
//function nbErreurs(){
//    $request = Request::createFromGlobals();
//    $erreurs = $request->getSession()->get('erreurs');
//  
//	   return count($erreurs);
//	
//}
