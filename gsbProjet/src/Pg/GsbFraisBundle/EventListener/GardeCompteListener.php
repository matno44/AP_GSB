<?php
namespace Pg\GsbFraisBundle\EventListener;

require_once __DIR__ . '/../Controller/include/fct.inc.php';

use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;

/**
 * Vérifie à CHAQUE requête que le compte en session est toujours utilisable.
 *
 * Deux règles, l'une et l'autre impossibles à appliquer correctement au seul
 * moment de la connexion :
 *
 *  1. UN COMPTE DÉSACTIVÉ NE DOIT PLUS RIEN POUVOIR FAIRE. Contrôler « actif »
 *     dans les méthodes d'authentification empêche de SE connecter, mais ne met
 *     pas dehors l'utilisateur déjà connecté : sa session reste valide et il
 *     continue de travailler jusqu'à sa déconnexion. Dire « un compte désactivé
 *     ne peut plus se connecter » serait alors faux pour lui. La désactivation
 *     doit prendre effet tout de suite, donc être revérifiée à chaque requête.
 *
 *  2. UN CHANGEMENT DE MOT DE PASSE IMPOSÉ DOIT BLOQUER TOUS LES ÉCRANS. Le
 *     contrôler dans un seul contrôleur laisserait les autres accessibles par
 *     leur URL.
 *
 * Pourquoi un écouteur et non une méthode appelée dans chaque contrôleur : il
 * y a sept contrôleurs, et un huitième oublierait le contrôle. Ici le passage
 * est obligatoire, y compris pour du code écrit après cette phase.
 *
 * Coût : une requête SQL par requête HTTP authentifiée, sur une clé primaire.
 * C'est le prix de l'effet immédiat, et il a été accepté en connaissance de
 * cause.
 */
class GardeCompteListener
{
    /**
     * Routes atteignables malgré un changement de mot de passe imposé.
     *
     * Sans la route de changement elle-même, la redirection bouclerait à
     * l'infini. Sans la déconnexion, l'utilisateur serait prisonnier de l'écran.
     */
    private static $routesAutorisees = array(
        'pg_gsb_frais_changermotdepasse',
        'pg_gsb_frais_validerchangementmotdepasse',
        'pg_gsb_frais_deconnexion',
    );

    private $container;

    /**
     * Le conteneur, et non le service PdoGsb directement.
     *
     * PdoGsb ouvre sa connexion dans son constructeur : l'injecter ici ferait
     * ouvrir une connexion à la base sur CHAQUE requête, y compris celles où
     * personne n'est connecté et où ce listener n'a rien à faire. On ne demande
     * donc le service qu'au moment où il devient nécessaire.
     */
    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    public function onKernelRequest(GetResponseEvent $event)
    {
        // Seule la requête principale compte. Une sous-requête -- un rendu de
        // fragment, une page d'erreur -- ne doit pas pouvoir produire une
        // redirection qui remplacerait la réponse en préparation.
        if (!$event->isMasterRequest()) {
            return;
        }
        $request = $event->getRequest();
        $route   = $request->attributes->get('_route');

        // Routes internes de l'environnement de développement (_wdt, _profiler,
        // _error). La barre de débogage est chargée par une requête distincte,
        // qui est bien une requête principale : sans ce test, elle serait
        // redirigée et la barre disparaîtrait.
        if ($route !== null && strpos($route, '_') === 0) {
            return;
        }

        $session = $request->getSession();
        if ($session === null || !estConnecte($session)) {
            return;
        }

        $role = $session->get('role');
        $id   = $session->get('id');
        // Une session ouverte avant la phase 8 n'a pas de rôle : on la traite
        // comme périmée plutôt que d'interroger une table inconnue.
        if ($role === null || $id === null) {
            $event->setResponse($this->terminerSession($session,
                'Votre session n\'est plus valide. Merci de vous reconnecter.'));

            return;
        }

        $pdo  = $this->container->get('pg_gsb_frais.pdo');
        $etat = $pdo->getEtatCompte($role, $id);

        // Compte supprimé pendant que la session était ouverte.
        if (!is_array($etat)) {
            $event->setResponse($this->terminerSession($session,
                'Votre compte n\'existe plus. Contactez votre administrateur.'));

            return;
        }

        // Compte désactivé : la session est fermée immédiatement.
        if ((int) $etat['actif'] !== 1) {
            $event->setResponse($this->terminerSession($session,
                'Votre compte a été désactivé. Contactez votre administrateur.'));

            return;
        }

        // L'indicateur est relu en base et non pris en session : un mot de passe
        // réinitialisé par un administrateur doit s'imposer à une session déjà
        // ouverte, pas seulement à la prochaine connexion.
        $session->set('mdpDoitChanger', (int) $etat['mdpDoitChanger']);
        if ((int) $etat['mdpDoitChanger'] === 1
            && !in_array($route, GardeCompteListener::$routesAutorisees, true)) {
            $event->setResponse(new RedirectResponse(
                $this->container->get('router')
                     ->generate('pg_gsb_frais_changermotdepasse')));
        }
    }

    /**
     * Ferme la session, y dépose le motif, et renvoie vers la connexion.
     *
     * Le message est déposé sur LA MÊME instance de session que celle qu'on
     * vient de vider -- celle portée par la requête. Passer par
     * container->get('session') donnerait une seconde poignée vers la session
     * dont rien ne garantit qu'elle est le même objet ; le message était alors
     * écrit quelque part où personne ne le relisait.
     *
     * L'ordre compte : clear() vide tous les sacs, flash bag compris, donc le
     * message doit être ajouté APRÈS. migrate(true) conserve les données tout en
     * changeant l'identifiant, le message survit donc à la régénération.
     *
     * Il est lu par HomeController::indexAction(), qui l'affiche sur le
     * formulaire de connexion -- sans quoi l'utilisateur se retrouverait
     * déconnecté sans savoir pourquoi, et croirait à une panne.
     *
     * @return RedirectResponse
     */
    private function terminerSession($session, $message)
    {
        // invalidate() plutôt que clear() : même choix qu'à la déconnexion
        // (décision 7.4). clear() vide les données mais CONSERVE l'identifiant
        // de session, qui resterait valable ; invalidate() vide ET régénère,
        // de sorte que l'identifiant ayant désigné une session authentifiée ne
        // désigne plus rien.
        $session->invalidate();
        $session->getFlashBag()->add('connexion', $message);

        return new RedirectResponse(
            $this->container->get('router')->generate('pg_gsb_frais_home'));
    }
}
