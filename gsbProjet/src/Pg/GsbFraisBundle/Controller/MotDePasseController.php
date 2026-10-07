<?php
namespace Pg\GsbFraisBundle\Controller;

require_once __DIR__ . '/include/fct.inc.php';

use Symfony\Component\HttpFoundation\Request;

/**
 * Changement de mot de passe imposé (phase 8).
 *
 * Écran commun aux trois rôles : il est atteint chaque fois que
 * mdpDoitChanger vaut 1, c'est-à-dire après une création de compte ou une
 * réinitialisation par un administrateur. Dans les deux cas, un tiers connaît
 * le mot de passe en cours -- c'est ce qui justifie d'en imposer le changement.
 *
 * Il n'y a volontairement pas de bouton « plus tard » : c'est
 * GardeCompteListener qui renvoie ici, à chaque requête, tant que l'indicateur
 * est levé.
 */
class MotDePasseController extends ControleurGsb
{
    public function formulaireAction(Request $request)
    {
        $session = $request->getSession();
        if (!estConnecte($session)) {
            return $this->redirect($this->generateUrl('pg_gsb_frais_home'));
        }
        // Atteindre cet écran sans y être obligé n'aurait pas de sens : le
        // changement volontaire de mot de passe n'est pas au programme de cette
        // phase, et l'écran ne propose pas de retour.
        if (!doitChangerMotDePasse($session)) {
            return $this->redirect($this->generateUrl('pg_gsb_frais_home'));
        }

        return $this->rendreFormulaire(array());
    }

    public function validerAction(Request $request)
    {
        $session = $request->getSession();
        if (!estConnecte($session)) {
            return $this->redirect($this->generateUrl('pg_gsb_frais_home'));
        }
        if (!doitChangerMotDePasse($session)) {
            return $this->redirect($this->generateUrl('pg_gsb_frais_home'));
        }
        $this->verifierJeton($request, 'changer_mot_de_passe');

        $ancien   = (string) $request->request->get('ancien');
        $nouveau  = (string) $request->request->get('nouveau');
        $nouveau2 = (string) $request->request->get('nouveau2');

        $role = $session->get('role');
        $pdo  = $this->get('pg_gsb_frais.pdo');

        $lesErreurs = array();

        // Le mot de passe actuel est redemandé : sans cela, quiconque accéderait
        // à une session ouverte pourrait s'approprier définitivement le compte.
        // On le vérifie en réauthentifiant, ce qui réutilise exactement le
        // mécanisme de la connexion plutôt que d'en écrire une variante.
        $compte = $this->reauthentifier($pdo, $role, $session->get('id'), $ancien);
        if ($compte === false) {
            $lesErreurs[] = 'Le mot de passe actuel est incorrect.';
        }

        if ($nouveau !== $nouveau2) {
            $lesErreurs[] = 'Le nouveau mot de passe et sa confirmation diffèrent.';
        }
        $lesErreurs = array_merge($lesErreurs, valideMotDePasse($nouveau));

        // Un nouveau mot de passe identique à l'ancien viderait l'opération de
        // son sens : le tiers qui connaissait l'ancien le connaîtrait encore.
        if ($nouveau !== '' && $nouveau === $ancien) {
            $lesErreurs[] = 'Le nouveau mot de passe doit être différent de l\'actuel.';
        }

        if (count($lesErreurs) > 0) {
            return $this->rendreFormulaire($lesErreurs);
        }

        $pdo->majEmpreinteMotDePasse($role, $session->get('id'),
                                     password_hash($nouveau, PASSWORD_BCRYPT), 0);
        // La session porte une copie de l'indicateur : la remettre à jour tout
        // de suite évite que GardeCompteListener renvoie ici une fois de plus.
        $session->set('mdpDoitChanger', 0);
        // Le mot de passe a changé : l'identifiant de session est régénéré, pour
        // la même raison qu'à la connexion.
        $session->migrate(true);
        $session->getFlashBag()->add('confirmation',
            'Votre mot de passe a été changé. Il n\'est connu que de vous.');

        return $this->redirect($this->generateUrl('pg_gsb_frais_home'));
    }

    /**
     * Revérifie le mot de passe actuel du compte connecté.
     *
     * Passe par la méthode d'authentification du rôle, qui est déjà la seule à
     * savoir comparer une empreinte. Le login est relu en base à partir de l'id
     * en session : le formulaire ne le fournit pas, et il ne faut surtout pas
     * lui faire confiance s'il le fournissait.
     *
     * @return array|false
     */
    private function reauthentifier($pdo, $role, $id, $mdp)
    {
        $leCompte = $pdo->getCompte($role, $id);
        if (!is_array($leCompte)) {
            return false;
        }
        $lesMethodes = array(
            'visiteur'       => 'getInfosVisiteur',
            'comptable'      => 'getInfosComptable',
            'administrateur' => 'getInfosAdministrateur',
        );
        if (!isset($lesMethodes[$role])) {
            return false;
        }
        $laMethode = $lesMethodes[$role];

        return $pdo->$laMethode($leCompte['login'], $mdp);
    }

    private function rendreFormulaire(array $lesErreurs)
    {
        return $this->render('PgGsbFraisBundle:MotDePasse:changer.html.twig',
                             array('leserreurs' => $lesErreurs));
    }
}
