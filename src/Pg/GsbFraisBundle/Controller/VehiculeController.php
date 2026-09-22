<?php
namespace Pg\GsbFraisBundle\Controller;

require_once __DIR__.'/include/fct.inc.php';

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;

/**
 * Déclaration du véhicule du visiteur connecté.
 *
 * Le tarif de remboursement kilométrique dépend de la puissance fiscale et du
 * carburant du véhicule ; le barème est stocké dans la table typevehicule.
 */
class VehiculeController extends Controller
{
    public function indexAction(Request $request)
    {
        $session = $request->getSession();
        if (!estConnecte($session)) {
            return $this->redirect($this->generateUrl('pg_gsb_frais_home'));
        }

        return $this->rendreFormulaire($request, array(), null);
    }

    public function validerAction(Request $request)
    {
        $session = $request->getSession();
        if (!estConnecte($session)) {
            return $this->redirect($this->generateUrl('pg_gsb_frais_home'));
        }
        $idVisiteur = $session->get('id');
        $idTypeVehicule = $request->request->get('idTypeVehicule');
        $pdo = $this->get('pg_gsb_frais.pdo');

        // La valeur vient du navigateur : on la contrôle côté serveur avant de
        // l'écrire, sans se contenter du fait que la liste déroulante ne
        // proposait que des valeurs correctes.
        if (!$pdo->estTypeVehiculeValide($idTypeVehicule)) {
            return $this->rendreFormulaire($request,
                array("Ce type de véhicule n'existe pas dans le barème"), null);
        }

        $pdo->majTypeVehiculeVisiteur($idVisiteur, $idTypeVehicule);

        return $this->rendreFormulaire($request, array(),
            'Votre véhicule a bien été enregistré.');
    }

    /**
     * Rend l'écran « Mon véhicule », point d'arrivée des deux actions.
     */
    private function rendreFormulaire(Request $request, array $erreurs, $message)
    {
        $idVisiteur = $request->getSession()->get('id');
        $pdo = $this->get('pg_gsb_frais.pdo');
        $leTypeVehicule = $pdo->getTypeVehiculeVisiteur($idVisiteur);

        return $this->render('PgGsbFraisBundle:Vehicule:monvehicule.html.twig',
            array('lestypesvehicule' => $pdo->getLesTypesVehicule(),
                'letarif' => $pdo->getTarifKilometrique($idVisiteur),
                // false quand aucun véhicule n'est déclaré : le template
                // affiche alors le tarif historique appliqué par défaut.
                'letypevehicule' => is_array($leTypeVehicule) ? $leTypeVehicule : null,
                'leserreurs' => $erreurs,
                'lemessage' => $message));
    }
}
