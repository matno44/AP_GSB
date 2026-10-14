<?php
namespace Pg\GsbFraisBundle\Controller;

require_once __DIR__ . '/include/fct.inc.php';

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Cas d'utilisation « Valider fiche de frais » (annexe 2 du cahier des charges).
 *
 * Acteur : le comptable. Chaque action correspond à une ou plusieurs étapes du
 * scénario nominal, rappelées dans les commentaires.
 */
class ValidationController extends ControleurGsb
{
    /**
     * Garde commune à toutes les actions de ce contrôleur.
     *
     * Le contrôle est fait ici, côté serveur, et non en masquant le menu :
     * masquer un lien n'empêche personne de taper l'URL.
     *
     * @return une Response à renvoyer si l'accès est refusé, null sinon
     */
    private function refuserSiPasComptable(Request $request)
    {
        $session = $request->getSession();
        if (!estConnecte($session)) {
            return $this->redirect($this->generateUrl('pg_gsb_frais_home'));
        }
        if (!estComptable($session)) {
            return new Response(
                'Cette page est réservée au service comptable.',
                Response::HTTP_FORBIDDEN
            );
        }

        return null;
    }

    /**
     * Étapes 1 à 3 : le système propose de choisir le visiteur et le mois.
     */
    public function indexAction(Request $request)
    {
        $refus = $this->refuserSiPasComptable($request);
        if ($refus !== null) {
            return $refus;
        }
        $pdo = $this->get('pg_gsb_frais.pdo');

        if ($request->isMethod('POST')) {
            $idVisiteur = $request->request->get('lstVisiteur');
            $mois       = $request->request->get('lstMois');
            // Redirection plutôt que rendu direct : l'adresse de la fiche
            // devient partageable, et un rafraîchissement ne repostera pas
            // le formulaire.
            return $this->redirect($this->generateUrl('pg_gsb_frais_validationfiche',
                                                      array('idVisiteur' => $idVisiteur, 'mois' => $mois)));
        }

        return $this->render('PgGsbFraisBundle:Validation:choix.html.twig',
                             array('lesvisiteurs' => $pdo->getLesVisiteurs(),
                                   'lesmois'      => $this->construireListeMois(),
                                   'levisiteur'   => null, 'lemois' => null, 'message' => null));
    }

    /**
     * Étape 4 : le système affiche le détail de la fiche de frais.
     * Exception 4-a : aucune fiche n'existe pour ce visiteur ce mois.
     */
    public function ficheAction(Request $request, $idVisiteur, $mois)
    {
        $refus = $this->refuserSiPasComptable($request);
        if ($refus !== null) {
            return $refus;
        }

        return $this->rendreFiche($idVisiteur, $mois, null);
    }

    /**
     * Étapes 5 et 6 : le comptable actualise les frais forfaitisés, le système
     * confirme la prise en compte et réaffiche les données actualisées.
     */
    public function corrigerAction(Request $request, $idVisiteur, $mois)
    {
        $refus = $this->refuserSiPasComptable($request);
        if ($refus !== null) {
            return $refus;
        }
        $this->verifierJeton($request, 'validation_corriger');
        $pdo     = $this->get('pg_gsb_frais.pdo');
        $laFiche = $pdo->getLesInfosFicheFrais($idVisiteur, $mois);
        // On ne corrige que ce qui est encore corrigeable : une fiche déjà
        // validée, mise en paiement ou remboursée ne se retouche pas.
        if (!is_array($laFiche) || $laFiche['idEtat'] !== 'CL') {
            return $this->rendreFiche($idVisiteur, $mois,
                                      array('erreur', "Cette fiche n'est plus modifiable."));
        }

        $lesFrais = $request->request->get('lesFrais');
        // La quantité kilométrique est dérivée de la somme des trajets
        // (phase 9) : on l'écarte avant la mise à jour. Le champ n'est plus
        // émis par le gabarit, mais un formulaire forgé pourrait l'ajouter,
        // et la corriger à la main la désynchroniserait du détail affiché.
        if (is_array($lesFrais)) {
            unset($lesFrais[$pdo->getIdFraisKilometrique()]);
        }
        if (!lesQteFraisValides($lesFrais)) {
            return $this->rendreFiche($idVisiteur, $mois,
                                      array('erreur', 'Les quantités doivent être des nombres entiers positifs.'));
        }
        // Aucune trace de la valeur précédente n'est conservée : c'est la règle
        // du cahier des charges (« sans que ne soit conservée trace de la
        // modification »). Elle est appliquée telle quelle, et signalée comme
        // discutable dans COMPTE-RENDU.md.
        $pdo->majFraisForfait($idVisiteur, $mois, $lesFrais);

        return $this->rendreFiche($idVisiteur, $mois,
                                  array('confirmation', 'Les frais forfaitisés ont été actualisés.'));
    }

    /**
     * Étape 7 : le comptable refuse une ligne de frais hors forfait.
     *
     * Le cahier des charges impose la suppression pure et simple, sans
     * enregistrer de motif. Le comptable doit prévenir le visiteur lui-même.
     */
    public function refuserAction(Request $request, $idVisiteur, $mois, $id)
    {
        $refus = $this->refuserSiPasComptable($request);
        if ($refus !== null) {
            return $refus;
        }
        $this->verifierJeton($request, 'validation_refuser');
        $pdo     = $this->get('pg_gsb_frais.pdo');
        $laFiche = $pdo->getLesInfosFicheFrais($idVisiteur, $mois);
        if (!is_array($laFiche) || $laFiche['idEtat'] !== 'CL') {
            return $this->rendreFiche($idVisiteur, $mois,
                                      array('erreur', "Cette fiche n'est plus modifiable."));
        }
        // La ligne doit bien appartenir à CETTE fiche : sans ce contrôle, un
        // identifiant modifié dans le formulaire supprimerait le frais d'un
        // autre visiteur ou d'un autre mois.
        if (!$pdo->estValideSuppressionFrais($idVisiteur, $mois, $id)) {
            throw $this->createNotFoundException('Frais hors forfait introuvable');
        }

        // Même enchaînement qu'à la suppression par le visiteur (phase 2) :
        // le justificatif doit disparaître avec la ligne, sinon il reste un
        // fichier orphelin sur le disque.
        $laLigne = $pdo->getLigneFraisHorsForfait($id);
        $pdo->supprimerFraisHorsForfait($id);
        if (is_array($laLigne) && $laLigne['nomFichier'] !== null) {
            $this->get('pg_gsb_frais.justificatifs')->supprimer($laLigne['nomFichier']);
        }
        $pdo->recalculerNbJustificatifs($idVisiteur, $mois);

        return $this->rendreFiche($idVisiteur, $mois, array('confirmation',
                                                            'Le frais « ' . $laLigne['libelle'] . ' » a été refusé et supprimé. '
                                                                . 'Pensez à en informer le visiteur : le système ne le fait pas.'));
    }

    /**
     * Étapes 8 et 9 : le comptable valide la fiche, le système la passe à
     * l'état « Validée » et met à jour sa date de modification.
     */
    public function validerAction(Request $request, $idVisiteur, $mois)
    {
        $refus = $this->refuserSiPasComptable($request);
        if ($refus !== null) {
            return $refus;
        }
        $this->verifierJeton($request, 'validation_valider');
        $pdo = $this->get('pg_gsb_frais.pdo');
        // validerFicheFrais() ne modifie que les fiches à l'état 'CL' et
        // renvoie faux sinon : le contrôle est dans la requête, il tient donc
        // même si deux comptables valident la même fiche au même instant.
        if (!$pdo->validerFicheFrais($idVisiteur, $mois)) {
            return $this->rendreFiche($idVisiteur, $mois, array('erreur',
                                                                "Cette fiche n'a pas pu être validée : elle doit être à l'état clôturé."));
        }

        return $this->rendreFiche($idVisiteur, $mois, array('confirmation',
                                                            "La fiche a été validée. Elle passe à l'état « Validée » et "
                                                                . "n'est plus modifiable."));
    }

    /**
     * Affiche le détail d'une fiche, point d'arrivée de toutes les actions.
     *
     * @param $message array(type, texte) ou null
     */
    private function rendreFiche($idVisiteur, $mois, $message)
    {
        $pdo        = $this->get('pg_gsb_frais.pdo');
        $leVisiteur = $pdo->getVisiteur($idVisiteur);
        $lesInfos   = $pdo->getLesInfosFicheFrais($idVisiteur, $mois);

        // EXCEPTION 4-a du cas d'utilisation : « Aucune fiche de frais
        // n'existe, le système affiche "Pas de fiche de frais pour ce visiteur
        // ce mois". Retour au 2. » On réaffiche donc l'écran de choix, la
        // sélection conservée, plutôt qu'une page d'erreur séparée.
        if (!is_array($leVisiteur) || !is_array($lesInfos)) {
            return $this->render('PgGsbFraisBundle:Validation:choix.html.twig',
                                 array('lesvisiteurs' => $pdo->getLesVisiteurs(),
                                       'lesmois'      => $this->construireListeMois(),
                                       'levisiteur'   => $idVisiteur, 'lemois' => $mois,
                                       'message'      => array('erreur',
                                                               'Pas de fiche de frais pour ce visiteur ce mois')));
        }

        $lesFraisForfait     = $pdo->getLesFraisForfait($idVisiteur, $mois);
        $lesFraisHorsForfait = $pdo->getLesFraisHorsForfait($idVisiteur, $mois);
        // Les totaux sont calculés ici et non dans le gabarit : en Twig, une
        // variable affectée par {% set %} à l'intérieur d'une boucle n'existe
        // plus après elle. Additionner dans la vue donnerait silencieusement 0.
        $lesTrajets          = $pdo->getLesTrajets($idVisiteur, $mois);
        $totalDistance       = totalDesLignes($lesTrajets, 'distance');
        $totalForfait        = totalDesLignes($lesFraisForfait, 'montant');
        $totalHorsForfait    = totalDesLignes($lesFraisHorsForfait, 'montant');

        return $this->render('PgGsbFraisBundle:Validation:fiche.html.twig',
                             array('levisiteur'          => $leVisiteur,
                                   'totalforfait'        => $totalForfait,
                                   'totalhorsforfait'    => $totalHorsForfait,
                                   'totalgeneral'        => $totalForfait + $totalHorsForfait,
                                   'lemois'              => $mois,
                                   'moislettres'         => moisEnLettres($mois),
                                   'lesinfos'            => $lesInfos,
                                   'datemodif'           => dateAnglaisVersFrancais($lesInfos['dateModif']),
                                   'modifiable'          => ($lesInfos['idEtat'] === 'CL'),
                                   'lestrajets'          => $lesTrajets,
                                   'totaldistance'       => $totalDistance,
                                   'idfraiskm'           => $pdo->getIdFraisKilometrique(),
                                   'lesfraisforfait'     => $lesFraisForfait,
                                   'lesfraishorsforfait' => $lesFraisHorsForfait,
                                   'message'             => $message));
    }

    /**
     * Construit la liste des douze derniers mois, du plus récent au plus ancien.
     *
     * Le cahier des charges autorise des frais remontant à un an : proposer
     * douze mois couvre donc tout ce qui peut encore être traité. La liste est
     * calculée et non lue en base, pour que le comptable puisse sélectionner
     * un mois où un visiteur n'a rien saisi — l'exception 4-a doit rester
     * atteignable, elle rend visible l'absence de saisie.
     *
     * @return un tableau : mois au format aaaamm => libellé
     */
    private function construireListeMois()
    {
        $lesMois = array();
        $mois    = getMois(date('d/m/Y'));
        for ($i = 0; $i < 12; ++$i) {
            $lesMois[$mois] = moisEnLettres($mois);
            $mois           = moisPrecedent($mois);
        }

        return $lesMois;
    }

    /**
     * Tableau récapitulatif : nombre de fiches en cours, à valider et
     * validées pour chaque visiteur.
     */
    public function recapAction(Request $request)
    {
        $refus = $this->refuserSiPasComptable($request);
        if ($refus !== null) {
            return $refus;
        }
        $pdo       = $this->get('pg_gsb_frais.pdo');
        $lesLignes = $pdo->getRecapFiches();

        $recap = array();
        foreach ($lesLignes as $uneLigne) {
            $id = $uneLigne['idvisiteur'];
            if (!isset($recap[$id])) {
                $recap[$id] = array(
                    'nom'        => $uneLigne['nom'],
                    'prenom'     => $uneLigne['prenom'],
                    'nbEnCours'  => 0,
                    'nbAValider' => 0,
                    'nbValidees' => 0,
                    // Mois cliquables du récapitulatif. Compter les fiches ne
                    // suffit pas : sans le mois, le gabarit ne peut pas
                    // construire l'URL de la fiche, et le comptable doit
                    // repasser par l'écran de choix pour l'ouvrir.
                    'aValider'     => array(),
                    // getRecapFiches() trie les mois du plus récent au plus
                    // ancien : la première ligne rencontrée pour un visiteur
                    // porte donc son mois le plus récent.
                    'dernierMois'    => $uneLigne['mois'],
                    'dernierLibelle' => moisEnLettres($uneLigne['mois']),
                );
            }
            switch ($uneLigne['idEtat']) {
                case 'CR':
                    $recap[$id]['nbEnCours']++;
                    break;
                case 'CL':
                    $recap[$id]['nbAValider']++;
                    $recap[$id]['aValider'][$uneLigne['mois']] = moisEnLettres($uneLigne['mois']);
                    break;
                default:  // VA, MP, RB
                    $recap[$id]['nbValidees']++;
            }
        }

        return $this->render('PgGsbFraisBundle:Validation:recap.html.twig',
                             array('recap' => $recap));
    }
}
