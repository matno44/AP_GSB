<?php
namespace Pg\GsbFraisBundle\Controller;

require_once __DIR__.'/include/fct.inc.php';

use Symfony\Component\HttpFoundation\Request;

class HomeController extends ControleurGsb
{
    public function indexAction(Request $request)
    {
        $session = $request->getSession();
        if (estConnecte($session)) {
            return $this->render('PgGsbFraisBundle::accueil.html.twig');
        }

        // GardeCompteListener ferme la session d'un compte désactivé ou supprimé
        // et dépose ici le motif. Sans cela l'utilisateur se retrouverait sur le
        // formulaire de connexion sans explication, et croirait à une panne.
        $lesMessages = $session->getFlashBag()->get('connexion');

        return $this->render('PgGsbFraisBundle:Home:connexion.html.twig', array(
            'message' => count($lesMessages) > 0 ? $lesMessages[0] : null));
    }

    /**
     * Cherche le compte correspondant aux identifiants, dans les trois tables.
     *
     * Un seul formulaire de connexion pour les trois rôles : l'utilisateur n'a
     * pas à savoir quelle porte prendre, et le système ne révèle pas qui est
     * comptable ni qui est administrateur.
     *
     * L'ordre d'essai n'a pas d'importance fonctionnelle TANT QUE les logins ne
     * se répètent pas d'une table à l'autre. C'est la limite connue du modèle à
     * trois tables : aucune contrainte UNIQUE ne peut couvrir trois tables, et
     * en cas de doublon c'est la première table essayée qui l'emporterait
     * silencieusement. L'interface d'administration empêche ce doublon en
     * contrôlant le login sur la vue v_compte avant tout enregistrement
     * (PdoGsb::loginExiste()).
     *
     * @return array(infos, role) ou null si aucun compte ne correspond
     */
    private function authentifier($pdo, $login, $mdp)
    {
        // Les trois rôles dans une boucle plutôt que trois blocs recopiés : la
        // phase 8 en ajoute un, et il ne faut pas qu'un quatrième oblige à
        // réécrire la méthode.
        $lesTentatives = array(
            'administrateur' => 'getInfosAdministrateur',
            'comptable'      => 'getInfosComptable',
            'visiteur'       => 'getInfosVisiteur',
        );
        foreach ($lesTentatives as $role => $laMethode) {
            $infos = $pdo->$laMethode($login, $mdp);
            if (is_array($infos)) {
                return array($infos, $role);
            }
        }

        return null;
    }

    public function validerconnexionAction(Request $request)
    {
        // Même un formulaire de connexion mérite un jeton : sans lui, un site
        // malveillant peut connecter la victime sur SON compte à lui, et
        // récolter ensuite tout ce qu'elle y saisit.
        $this->verifierJeton($request, 'connexion');
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

        // mdpDoitChanger vient de la base et part en session : GardeCompteListener
        // s'en sert à chaque requête pour bloquer l'utilisateur sur l'écran de
        // changement de mot de passe. isset() plutôt qu'un accès direct : un
        // appelant plus ancien de cette méthode pourrait ne pas fournir la colonne.
        $doitChanger = isset($infos['mdpDoitChanger']) ? $infos['mdpDoitChanger'] : 0;
        connecterAvecRole($session, $infos['id'], $infos['nom'], $infos['prenom'],
                          $role, $doitChanger);

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
