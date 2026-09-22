<?php
namespace Pg\GsbFraisBundle\Controller;

require_once __DIR__.'/include/fct.inc.php';

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;

class HomeController extends Controller
{
    public function indexAction(Request $request)
    {
        $session = $request->getSession();
        if (estConnecte($session)) {
            return $this->render('PgGsbFraisBundle::accueil.html.twig');
        }

        return $this->render('PgGsbFraisBundle:Home:connexion.html.twig');
    }

    public function validerconnexionAction(Request $request)
    {
        $session = $request->getSession();
        $login = $request->request->get('login');
        $mdp = $request->request->get('mdp');
        $pdo = $this->get('pg_gsb_frais.pdo');
        $visiteur = $pdo->getInfosVisiteur($login, $mdp);
        if (!is_array($visiteur)) {
            return $this->render('PgGsbFraisBundle:Home:connexion.html.twig', array(
                'message' => 'Erreur de login ou de mot de passe '));
        }

        connecter($session, $visiteur['id'], $visiteur['nom'], $visiteur['prenom']);

        // Redirection plutôt que rendu direct : un rafraîchissement de la page
        // d'accueil ne repostera pas le formulaire de connexion.
        return $this->redirect($this->generateUrl('pg_gsb_frais_home'));
    }

    public function deconnexionAction(Request $request)
    {
        deconnecter($request->getSession());

        return $this->redirect($this->generateUrl('pg_gsb_frais_home'));
    }
}
