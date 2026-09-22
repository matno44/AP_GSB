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

    /**
     * Cherche le compte correspondant aux identifiants, comptables d'abord.
     *
     * Un seul formulaire de connexion pour les deux rôles : l'utilisateur n'a
     * pas à savoir quelle porte prendre, et le système ne révèle pas qui est
     * comptable. L'ordre n'a pas d'importance fonctionnelle, les logins étant
     * uniques dans chacune des deux tables.
     *
     * @return array(infos, role) ou null si aucun compte ne correspond
     */
    private function authentifier($pdo, $login, $mdp)
    {
        $comptable = $pdo->getInfosComptable($login, $mdp);
        if (is_array($comptable)) {
            return array($comptable, 'comptable');
        }
        $visiteur = $pdo->getInfosVisiteur($login, $mdp);
        if (is_array($visiteur)) {
            return array($visiteur, 'visiteur');
        }

        return null;
    }

    public function validerconnexionAction(Request $request)
    {
        $session = $request->getSession();
        $login = $request->request->get('login');
        $mdp = $request->request->get('mdp');
        $pdo = $this->get('pg_gsb_frais.pdo');
        $compte = $this->authentifier($pdo, $login, $mdp);
        if ($compte === null) {
            // Message volontairement identique que le login soit inconnu ou
            // que le mot de passe soit faux : distinguer les deux cas
            // indiquerait à un curieux quels comptes existent.
            return $this->render('PgGsbFraisBundle:Home:connexion.html.twig', array(
                'message' => 'Erreur de login ou de mot de passe '));
        }
        list($infos, $role) = $compte;

        connecterAvecRole($session, $infos['id'], $infos['nom'], $infos['prenom'], $role);

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
