<?php
namespace Pg\GsbFraisBundle\Controller;

require_once __DIR__.'/include/fct.inc.php';

use Symfony\Component\HttpFoundation\Request;

/**
 * Écran « Mon véhicule » du visiteur (phase 10).
 *
 * CE QUI A CHANGÉ. Jusqu'à la phase 9, cet écran portait une liste déroulante
 * par laquelle le visiteur choisissait lui-même sa ligne de barème. Il était
 * donc à la fois le déclarant et le seul contrôleur de sa déclaration, sans
 * qu'aucune pièce ne l'atteste : sur 1 000 km mensuels, passer de « 4 CV
 * Diesel » (0,52 €/km) à « 7 CV et + Essence » (0,72) rapportait 200 € de plus
 * sans laisser la moindre trace.
 *
 * Le visiteur envoie désormais sa carte grise et consulte l'état de sa
 * demande. C'est le comptable qui y relève les caractéristiques et qui valide
 * — voir CarteGriseController.
 *
 * validerAction(), qui écrivait visiteur.idTypeVehicule depuis une valeur
 * venue du navigateur, a été SUPPRIMÉE avec sa route : laisser l'action en
 * place en retirant seulement le formulaire aurait conservé une URL par
 * laquelle un visiteur averti aurait continué à fixer son propre tarif.
 */
class VehiculeController extends ControleurGsb
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

        return $this->rendreEcran($request, array(), null);
    }

    /**
     * Reçoit la carte grise envoyée par le visiteur.
     *
     * Le fichier est le SEUL élément qui arrive du navigateur : aucune
     * caractéristique du véhicule n'est acceptée ici, pas même à titre
     * indicatif. Un champ « puissance fiscale » rempli par le visiteur
     * finirait tôt ou tard recopié par un comptable pressé, et le contrôle
     * demandé n'aurait plus lieu.
     */
    public function envoyerAction(Request $request)
    {
        $session = $request->getSession();
        if (!estConnecte($session)) {
            return $this->redirect($this->generateUrl('pg_gsb_frais_home'));
        }
        if (!estVisiteur($session)) {
            return $this->refuserNonVisiteur();
        }
        $this->verifierJeton($request, 'carte_grise');
        $idVisiteur = $session->get('id');
        $pdo = $this->get('pg_gsb_frais.pdo');

        // Une seule demande en attente à la fois. Le formulaire n'est pas
        // affiché dans ce cas, mais masquer un formulaire n'est pas contrôler
        // un envoi : la règle doit être appliquée côté serveur.
        if ($pdo->aUneDemandeEnAttente($idVisiteur)) {
            return $this->rendreEcran($request,
                array('Une demande est déjà en attente de validation. '
                    .'Attendez la décision du comptable avant d\'envoyer un '
                    .'nouveau document.'), null);
        }

        $stockage = $this->get('pg_gsb_frais.cartesgrises');
        $fichier = $request->files->get('cartegrise');
        $lesErreurs = $stockage->valider($fichier);
        if (count($lesErreurs) > 0) {
            return $this->rendreEcran($request, $lesErreurs, null);
        }

        // Le nom d'origine est assaini AVANT l'enregistrement : move() rend
        // l'objet UploadedFile inutilisable pour lire le fichier déplacé, mais
        // getClientOriginalName() reste disponible. L'ordre est sans
        // conséquence ici, il suit celui de JustificatifController.
        $nomOriginal = $stockage->assainirNom($fichier->getClientOriginalName());
        $typeMime = $fichier->getMimeType();
        $nomFichier = $stockage->enregistrer($fichier);
        $pdo->creerDemandeCarteGrise($idVisiteur, $nomFichier, $nomOriginal,
            $typeMime);

        return $this->rendreEcran($request, array(),
            'Votre carte grise a bien été envoyée. Elle sera examinée par le '
            .'service comptable.');
    }

    /**
     * Rend l'écran « Mon véhicule », point d'arrivée des deux actions.
     *
     * Le tarif affiché est toujours celui réellement appliqué, c'est-à-dire
     * celui de la dernière validation — ou le tarif historique à défaut. Une
     * demande en attente ne le modifie pas : le dire à l'écran évite au
     * visiteur de croire que son envoi a déjà pris effet.
     */
    private function rendreEcran(Request $request, array $erreurs, $message)
    {
        $idVisiteur = $request->getSession()->get('id');
        $pdo = $this->get('pg_gsb_frais.pdo');
        $stockage = $this->get('pg_gsb_frais.cartesgrises');
        $leTypeVehicule = $pdo->getTypeVehiculeVisiteur($idVisiteur);
        $laDemande = $pdo->getLaDerniereCarteGrise($idVisiteur);

        return $this->render('PgGsbFraisBundle:Vehicule:monvehicule.html.twig',
            array('letarif' => $pdo->getTarifKilometrique($idVisiteur),
                // false quand aucun véhicule n'est validé : le template
                // affiche alors le tarif historique appliqué par défaut.
                'letypevehicule' => is_array($leTypeVehicule) ? $leTypeVehicule : null,
                'lademande' => is_array($laDemande) ? $laDemande : null,
                // Distingue un véhicule validé sur pièce d'un véhicule déclaré
                // par le visiteur lui-même avant la phase 10.
                'estvalidesurpiece' => $pdo->aUneCarteGriseValidee($idVisiteur),
                'lataillemax' => $stockage->getTailleMaxEnMo(),
                'lesextensions' => $stockage->getExtensionsAutorisees(),
                'leserreurs' => $erreurs,
                'lemessage' => $message));
    }
}
