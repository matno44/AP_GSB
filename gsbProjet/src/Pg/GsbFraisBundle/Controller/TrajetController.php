<?php
namespace Pg\GsbFraisBundle\Controller;

require_once __DIR__ . '/include/fct.inc.php';

use Symfony\Component\HttpFoundation\Request;

/**
 * Saisie détaillée des trajets (phase 9).
 *
 * Avant cette phase, le visiteur additionnait lui-même toutes ses distances du
 * mois pour n'en saisir qu'un total dans la ligne kilométrique. Deux défauts :
 * l'addition se faisait à la main, hors de l'application, et le comptable ne
 * pouvait rien contrôler -- le cahier des charges lui demande de vérifier que
 * « la distance kilométrique est plausible », or un total de 480 km ne se
 * confronte à rien.
 *
 * Désormais chaque trajet est une ligne, et la quantité kilométrique est
 * recalculée comme leur somme (PdoGsb::recalculerDistance()).
 *
 * Les deux actions renvoient vers l'écran de saisie : elles n'ont pas de vue à
 * elles, les trajets s'affichant dans le formulaire de saisie des frais.
 */
class TrajetController extends ControleurGsb
{
    /**
     * Garde commune aux deux actions.
     *
     * @return une Response à renvoyer si l'accès est refusé, null sinon
     */
    private function refuser(Request $request)
    {
        $session = $request->getSession();
        if (!estConnecte($session)) {
            return $this->redirect($this->generateUrl('pg_gsb_frais_home'));
        }
        if (!estVisiteur($session)) {
            return $this->refuserNonVisiteur();
        }

        return null;
    }

    public function ajouterAction(Request $request)
    {
        $refus = $this->refuser($request);
        if ($refus !== null) {
            return $refus;
        }
        $this->verifierJeton($request, 'trajet_ajouter');

        $session    = $request->getSession();
        $idVisiteur = $session->get('id');
        $mois       = getMois(date('d/m/Y'));
        $pdo        = $this->get('pg_gsb_frais.pdo');

        $date     = trim((string) $request->request->get('date'));
        $libelle  = trim((string) $request->request->get('libelle'));
        $distance = trim((string) $request->request->get('distance'));

        $lesErreurs = valideInfosTrajet($date, $libelle, $distance);

        // La fiche du mois est créée par SaisirFraisController à la première
        // visite de l'écran. Si elle n'existe pas encore, la clé étrangère de
        // trajet refuserait l'insertion : on le dit plutôt que de laisser
        // remonter une erreur de base.
        if (count($lesErreurs) === 0 && !$pdo->ficheEstEnSaisie($idVisiteur, $mois)) {
            $lesErreurs[] = "Votre fiche de frais du mois n'est plus en saisie : "
                . 'aucun trajet ne peut y être ajouté.';
        }

        if (count($lesErreurs) > 0) {
            // Les messages passent par le flash bag : l'action redirige, et un
            // flash survit à une redirection. Même mécanisme que
            // JustificatifController (décision 2.x).
            foreach ($lesErreurs as $uneErreur) {
                ajouterErreur($session, $uneErreur);
            }

            return $this->redirect($this->generateUrl('pg_gsb_frais_saisirfrais'));
        }

        $pdo->creeTrajet($idVisiteur, $mois, $date, $libelle, $distance);

        return $this->redirect($this->generateUrl('pg_gsb_frais_saisirfrais'));
    }

    public function supprimerAction(Request $request, $id)
    {
        $refus = $this->refuser($request);
        if ($refus !== null) {
            return $refus;
        }
        $this->verifierJeton($request, 'trajet_supprimer');

        $session    = $request->getSession();
        $idVisiteur = $session->get('id');
        $mois       = getMois(date('d/m/Y'));
        $pdo        = $this->get('pg_gsb_frais.pdo');

        // Le trajet doit appartenir au visiteur connecté ET au mois en cours,
        // sinon l'URL permettrait de supprimer le trajet de n'importe qui.
        if (!$pdo->estValideSuppressionTrajet($idVisiteur, $mois, $id)) {
            throw $this->createNotFoundException('Trajet introuvable');
        }
        // Et la fiche doit encore être en saisie : supprimer un trajet d'une
        // fiche clôturée modifierait un montant déjà soumis au comptable.
        if (!$pdo->ficheEstEnSaisie($idVisiteur, $mois)) {
            ajouterErreur($session, "Votre fiche de frais du mois n'est plus en saisie : "
                . 'ses trajets ne peuvent plus être modifiés.');

            return $this->redirect($this->generateUrl('pg_gsb_frais_saisirfrais'));
        }

        $pdo->supprimerTrajet($idVisiteur, $mois, $id);

        return $this->redirect($this->generateUrl('pg_gsb_frais_saisirfrais'));
    }
}
