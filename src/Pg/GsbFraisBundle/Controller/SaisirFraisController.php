<?php
namespace Pg\GsbFraisBundle\Controller;

require_once __DIR__.'/include/fct.inc.php';

use Symfony\Component\HttpFoundation\Request;

class SaisirFraisController extends ControleurGsb
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
            $this->verifierJeton($request, 'saisie_forfait');
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
        $this->verifierJeton($request, 'frais_hors_forfait');
        $idVisiteur = $session->get('id');
        $mois = getMois(date("d/m/Y"));
        $pdo = $this->get('pg_gsb_frais.pdo');
        $dateFrais = $request->request->get('dateFrais');
        $libelle = $request->request->get('libelle');
        $montant = $request->request->get('montant');
        $lesErreursHorsForfait = valideInfosFrais($dateFrais, $libelle, $montant);

        // Le justificatif est facultatif à la création : le cahier des charges
        // laisse au visiteur tout le mois pour fournir sa facture. On ne le
        // contrôle donc que si un fichier a effectivement été choisi.
        $stockage = $this->get('pg_gsb_frais.justificatifs');
        $fichier = $request->files->get('justificatif');
        if ($fichier !== null) {
            $lesErreursHorsForfait = array_merge($lesErreursHorsForfait,
                $stockage->valider($fichier));
        }

        if (count($lesErreursHorsForfait) == 0) {
            $idFrais = $pdo->creeNouveauFraisHorsForfait($idVisiteur, $mois, $libelle, $dateFrais, $montant);
            if ($fichier !== null) {
                $nomOriginal = $stockage->assainirNom($fichier->getClientOriginalName());
                $typeMime = $fichier->getMimeType();
                $pdo->majJustificatifFrais($idFrais, $stockage->enregistrer($fichier),
                    $nomOriginal, $typeMime);
            }
        }

        return $this->rendreSaisie($pdo, $idVisiteur, $mois, array(), $lesErreursHorsForfait);
    }

    public function supprimerfraishorsforfaitAction(Request $request, $id)
    {
        $session = $request->getSession();
        if (!estConnecte($session)) {
            return $this->redirect($this->generateUrl('pg_gsb_frais_home'));
        }
        $this->verifierJeton($request, 'suppression_frais');
        $idVisiteur = $session->get('id');
        $mois = getMois(date("d/m/Y"));
        $pdo = $this->get('pg_gsb_frais.pdo');
        // Le frais doit appartenir au visiteur connecté et au mois en cours,
        // sinon l'URL permettrait de supprimer le frais de n'importe qui.
        if (!$pdo->estValideSuppressionFrais($idVisiteur, $mois, $id)) {
            throw $this->createNotFoundException('Frais hors forfait introuvable');
        }
        // Le justificatif éventuel doit disparaître avec la ligne, sinon des
        // fichiers orphelins s'accumulent sur le disque sans que rien ne les
        // référence. On lit son nom AVANT la suppression en base.
        $laLigne = $pdo->getLigneFraisHorsForfait($id);
        $pdo->supprimerFraisHorsForfait($id);
        if (is_array($laLigne) && $laLigne['nomFichier'] !== null) {
            $this->get('pg_gsb_frais.justificatifs')->supprimer($laLigne['nomFichier']);
        }
        // La ligne n'existe plus : le compteur est recalculé à partir du
        // couple visiteur/mois et non à partir de l'identifiant du frais.
        $pdo->recalculerNbJustificatifs($idVisiteur, $mois);

        return $this->redirect($this->generateUrl('pg_gsb_frais_saisirfrais'));
    }

    /**
     * Rend la page de saisie, qui est le point d'arrivée des trois actions.
     */
    private function rendreSaisie($pdo, $idVisiteur, $mois, array $erreursForfait, array $erreursHorsForfait)
    {
        $lesFraisForfait = $pdo->getLesFraisForfait($idVisiteur, $mois);
        $lesFraisHorsForfait = $pdo->getLesFraisHorsForfait($idVisiteur, $mois);

        // JustificatifController::joindreAction() redirige vers cette page :
        // ses messages d'erreur transitent par le flash bag, qui survit à une
        // redirection et se vide à la première lecture.
        $session = $this->get('request_stack')->getCurrentRequest()->getSession();
        $erreursHorsForfait = array_merge($erreursHorsForfait, getLesErreurs($session));

        return $this->render('PgGsbFraisBundle:SaisirFrais:saisirtouslesfrais.html.twig',
            array('lesfraisforfait' => $lesFraisForfait,
                'lesfraishorsforfait' => $lesFraisHorsForfait,
                'nummois' => substr($mois, 4, 2), 'numannee' => substr($mois, 0, 4),
                'nbjustificatifs' => $pdo->getNbjustificatifs($idVisiteur, $mois),
                'extensions' => $this->get('pg_gsb_frais.justificatifs')->getExtensionsAutorisees(),
                'taillemax' => $this->get('pg_gsb_frais.justificatifs')->getTailleMaxEnMo(),
                'leserreursforfait' => $erreursForfait,
                'leserreurshorsforfait' => $erreursHorsForfait));
    }
}
