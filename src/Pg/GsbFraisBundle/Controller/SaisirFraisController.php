<?php
namespace Pg\GsbFraisBundle\Controller;

require_once __DIR__.'/include/fct.inc.php';

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;

class SaisirFraisController extends Controller
{
    public function indexAction(Request $request)
    {
        $session = $request->getSession();
        if (!estConnecte($session)) {
            return $this->redirect($this->generateUrl('pg_gsb_frais_home'));
        }
        $idVisiteur = $session->get('id');
        $mois = getMois(date("d/m/Y"));
        $pdo = $this->get('pg_gsb_frais.pdo');
        if ($pdo->estPremierFraisMois($idVisiteur, $mois)) {
            $pdo->creeNouvellesLignesFrais($idVisiteur, $mois);
        }

        $lesErreursForfaits = array();
        if ($request->isMethod('POST')) {
            $lesFrais = $request->request->get('lesFrais');
            if (lesQteFraisValides($lesFrais)) {
                $pdo->majFraisForfait($idVisiteur, $mois, $lesFrais);
            } else {
                $lesErreursForfaits[] = "Les valeurs des frais doivent être numériques";
            }
        }

        return $this->rendreSaisie($pdo, $idVisiteur, $mois, $lesErreursForfaits, array());
    }

    public function validerfraishorsforfaitAction(Request $request)
    {
        $session = $request->getSession();
        if (!estConnecte($session)) {
            return $this->redirect($this->generateUrl('pg_gsb_frais_home'));
        }
        $idVisiteur = $session->get('id');
        $mois = getMois(date("d/m/Y"));
        $pdo = $this->get('pg_gsb_frais.pdo');
        $dateFrais = $request->request->get('dateFrais');
        $libelle = $request->request->get('libelle');
        $montant = $request->request->get('montant');
        $lesErreursHorsForfait = valideInfosFrais($dateFrais, $libelle, $montant);
        if (count($lesErreursHorsForfait) == 0) {
            $pdo->creeNouveauFraisHorsForfait($idVisiteur, $mois, $libelle, $dateFrais, $montant);
        }

        return $this->rendreSaisie($pdo, $idVisiteur, $mois, array(), $lesErreursHorsForfait);
    }

    public function supprimerfraishorsforfaitAction(Request $request, $id)
    {
        $session = $request->getSession();
        if (!estConnecte($session)) {
            return $this->redirect($this->generateUrl('pg_gsb_frais_home'));
        }
        $idVisiteur = $session->get('id');
        $mois = getMois(date("d/m/Y"));
        $pdo = $this->get('pg_gsb_frais.pdo');
        // Le frais doit appartenir au visiteur connecté et au mois en cours,
        // sinon l'URL permettrait de supprimer le frais de n'importe qui.
        if (!$pdo->estValideSuppressionFrais($idVisiteur, $mois, $id)) {
            throw $this->createNotFoundException('Frais hors forfait introuvable');
        }
        $pdo->supprimerFraisHorsForfait($id);

        return $this->redirect($this->generateUrl('pg_gsb_frais_saisirfrais'));
    }

    /**
     * Rend la page de saisie, qui est le point d'arrivée des trois actions.
     */
    private function rendreSaisie($pdo, $idVisiteur, $mois, array $erreursForfait, array $erreursHorsForfait)
    {
        $lesFraisForfait = $pdo->getLesFraisForfait($idVisiteur, $mois);
        $lesFraisHorsForfait = $pdo->getLesFraisHorsForfait($idVisiteur, $mois);

        return $this->render('PgGsbFraisBundle:SaisirFrais:saisirtouslesfrais.html.twig',
            array('lesfraisforfait' => $lesFraisForfait,
                'lesfraishorsforfait' => $lesFraisHorsForfait,
                'nummois' => substr($mois, 4, 2), 'numannee' => substr($mois, 0, 4),
                'leserreursforfait' => $erreursForfait,
                'leserreurshorsforfait' => $erreursHorsForfait));
    }
}
