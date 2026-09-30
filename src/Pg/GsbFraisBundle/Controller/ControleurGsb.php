<?php
namespace Pg\GsbFraisBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Socle commun aux contrôleurs de l'application.
 *
 * Il ne porte qu'une chose : la vérification du jeton anti-CSRF. Écrite une
 * seule fois, elle ne peut pas diverger d'un contrôleur à l'autre, et l'oubli
 * se voit à la relecture — une action d'écriture qui n'appelle pas
 * verifierJeton() saute aux yeux.
 *
 * Pourquoi une classe de base plutôt qu'un service : la vérification a besoin
 * de la requête et doit interrompre l'action en cours. C'est le rôle naturel
 * d'une méthode de contrôleur.
 */
abstract class ControleurGsb extends Controller
{
    /**
     * Vérifie le jeton anti-CSRF envoyé avec un formulaire.
     *
     * LE PROBLÈME QU'ON RÉSOUT ICI. Le navigateur d'un visiteur connecté
     * envoie automatiquement son cookie de session à chaque requête vers
     * l'application — y compris si la requête est déclenchée par un AUTRE
     * site. Un site malveillant peut donc faire exécuter une action au nom de
     * la victime, à son insu : c'est la falsification de requête inter-site
     * (CSRF).
     *
     * LA PARADE. Chaque formulaire embarque un jeton aléatoire, propre à la
     * session et à l'intention. Le site malveillant peut faire envoyer le
     * cookie, mais il ne peut pas LIRE le jeton : la politique de même origine
     * du navigateur l'en empêche. Sans jeton valide, l'action est refusée.
     *
     * L'INTENTION sert à cloisonner : un jeton obtenu pour le formulaire de
     * véhicule ne vaut rien pour la validation d'une fiche. Sans cela, un
     * jeton récupéré sur le formulaire le plus anodin ouvrirait tous les
     * autres.
     *
     * @param $request
     * @param $intention identifiant du formulaire, par exemple 'vehicule'
     * @throws AccessDeniedHttpException si le jeton est absent ou invalide
     */
    protected function verifierJeton(Request $request, $intention)
    {
        $jeton = $request->request->get('_token');
        if (!$this->isCsrfTokenValid($intention, $jeton)) {
            // AccessDeniedHttpException et non AccessDeniedException : la
            // seconde est interceptée par le composant Security, qui — faute
            // de pare-feu configuré ici — la transforme en 401
            // « identifiez-vous ». Or le problème n'est pas l'identité.
            throw new AccessDeniedHttpException(
                'Jeton de sécurité absent ou invalide. '
                .'Revenez à la page précédente et recommencez.');
        }
    }

    /**
     * Refuse l'accès à un écran réservé aux visiteurs médicaux.
     *
     * Même choix que dans FichePdfController : une Response 403 construite à
     * la main plutôt que createAccessDeniedException(), que le composant
     * Security transformerait en 401 « identifiez-vous » faute de pare-feu
     * configuré. L'utilisateur EST identifié ; il lui manque le rôle.
     *
     * @return Response
     */
    protected function refuserNonVisiteur()
    {
        return new Response(
            'Cet écran est réservé aux visiteurs médicaux.',
            Response::HTTP_FORBIDDEN);
    }
}
