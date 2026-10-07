<?php
namespace Pg\GsbFraisBundle\Controller;

require_once __DIR__.'/include/fct.inc.php';

use Symfony\Component\HttpFoundation\Request;

class ListeFraisController extends ControleurGsb
{
    public function indexAction(Request $request)
    {
        $session = $request->getSession();
        if (!estConnecte($session)) {
            return $this->redirect($this->generateUrl('pg_gsb_frais_home'));
        }
        if (!estVisiteur($session)) {
            return $this->refuserNonVisiteur();
        }
        $idVisiteur = $session->get('id');
        $pdo = $this->get('pg_gsb_frais.pdo');
        $lesMois = $pdo->getLesMoisDisponibles($idVisiteur);

        if ($request->isMethod('POST')) {
            $leMois = $request->request->get('lstMois');
        } else {
            // Afin de sélectionner par défaut le dernier mois dans la zone de
            // liste on prend la première clé, les mois étant triés décroissants.
            $lesCles = array_keys($lesMois);
            $leMois = count($lesCles) > 0 ? $lesCles[0] : null;
        }

        // Aucune fiche saisie, ou mois absent de la liste : on se contente
        // d'afficher le sélecteur de mois, vide le cas échéant.
        if ($leMois === null || !isset($lesMois[$leMois])) {
            return $this->render('PgGsbFraisBundle:ListeFrais:listemois.html.twig',
                array('lesmois' => $lesMois, 'lemois' => $leMois));
        }

        $lesFraisHorsForfait = $pdo->getLesFraisHorsForfait($idVisiteur, $leMois);
        $lesFraisForfait = $pdo->getLesFraisForfait($idVisiteur, $leMois);
        $lesInfosFicheFrais = $pdo->getLesInfosFicheFrais($idVisiteur, $leMois);
        $numAnnee = substr($leMois, 0, 4);
        $numMois = substr($leMois, 4, 2);
        $libEtat = $lesInfosFicheFrais['libEtat'];
        $montantValide = $lesInfosFicheFrais['montantValide'];
        $nbJustificatifs = $lesInfosFicheFrais['nbJustificatifs'];
        $dateModif = dateAnglaisVersFrancais($lesInfosFicheFrais['dateModif']);

        return $this->render('PgGsbFraisBundle:ListeFrais:listetouslesfrais.html.twig',
            array('lesmois' => $lesMois, 'lesfraisforfait' => $lesFraisForfait,
                'lesfraishorsforfait' => $lesFraisHorsForfait,
                'lemois' => $leMois, 'numannee' => $numAnnee, 'nummois' => $numMois,
                'libetat' => $libEtat, 'montantvalide' => $montantValide,
                'nbjustificatifs' => $nbJustificatifs, 'datemodif' => $dateModif));
    }
}
