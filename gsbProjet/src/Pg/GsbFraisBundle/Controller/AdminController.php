<?php
namespace Pg\GsbFraisBundle\Controller;

require_once __DIR__ . '/include/fct.inc.php';

use Symfony\Component\HttpFoundation\Request;

/**
 * Interface d'administration des comptes (phase 8, hors cahier des charges).
 *
 * Besoin métier : aujourd'hui, embaucher un visiteur ou couper l'accès d'un
 * salarié parti suppose d'écrire du SQL à la main dans la base de production.
 * C'est à la fois hors de portée d'un gestionnaire et dangereux -- un DELETE mal
 * ciblé détruit des fiches de frais déjà remboursées.
 *
 * TOUTES les actions de ce contrôleur commencent par refuserSiPasAdministrateur().
 * Masquer les entrées de menu ne protège rien : l'URL reste tapable.
 */
class AdminController extends ControleurGsb
{
    /** Nombre de lignes par page, pour la liste comme pour le journal. */
    const PAR_PAGE = 25;

    /**
     * Garde commune à toutes les actions.
     *
     * @return une Response à renvoyer si l'accès est refusé, null sinon
     */
    private function refuserSiPasAdministrateur(Request $request)
    {
        $session = $request->getSession();
        if (!estConnecte($session)) {
            return $this->redirect($this->generateUrl('pg_gsb_frais_home'));
        }
        if (!estAdministrateur($session)) {
            return $this->refuserNonAdministrateur();
        }

        return null;
    }

    // =====================================================================
    // Liste des comptes
    // =====================================================================

    public function indexAction(Request $request)
    {
        $refus = $this->refuserSiPasAdministrateur($request);
        if ($refus !== null) {
            return $refus;
        }
        $pdo = $this->get('pg_gsb_frais.pdo');

        // Les filtres passent par l'URL et non par la session : chaque état de
        // la liste a ainsi sa propre adresse, partageable et rechargeable, et le
        // bouton « page suivante » n'a rien à mémoriser.
        $recherche = trim((string) $request->query->get('recherche', ''));
        $role      = (string) $request->query->get('role', '');
        $etat      = (string) $request->query->get('etat', '');
        $page      = max(1, (int) $request->query->get('page', 1));

        $total = $pdo->compterLesComptes($recherche, $role, $etat);
        // Le nombre de pages est calculé AVANT de borner la page demandée :
        // « ?page=999 » sur 9 pages doit retomber sur la dernière et non
        // afficher une liste vide.
        $nbPages = max(1, (int) ceil($total / AdminController::PAR_PAGE));
        $page    = min($page, $nbPages);

        $lesComptes = $pdo->getLesComptes($recherche, $role, $etat,
                                          AdminController::PAR_PAGE,
                                          ($page - 1) * AdminController::PAR_PAGE);

        return $this->render('PgGsbFraisBundle:Admin:comptes.html.twig', array(
            'lescomptes'  => $lesComptes,
            'lesroles'    => $pdo->getLesRoles(),
            'recherche'   => $recherche,
            'role'        => $role,
            'etat'        => $etat,
            'page'        => $page,
            'nbpages'     => $nbPages,
            'total'       => $total,
            'parpage'     => AdminController::PAR_PAGE,
            'moncompte'   => $request->getSession()->get('id'),
            'message'     => $this->lireMessage($request),
        ));
    }

    // =====================================================================
    // Création
    // =====================================================================

    public function creerAction(Request $request)
    {
        $refus = $this->refuserSiPasAdministrateur($request);
        if ($refus !== null) {
            return $refus;
        }

        // Le rôle vient de l'URL : le sélecteur de rôle est un formulaire GET
        // distinct, pour que changer de rôle soit une navigation et non une
        // soumission qui déclencherait la validation de champs encore vides.
        $saisie = $this->valeursParDefaut();
        $role   = (string) $request->query->get('role', 'visiteur');
        if (in_array($role, $this->get('pg_gsb_frais.pdo')->getLesRoles(), true)) {
            $saisie['role'] = $role;
        }

        return $this->rendreFormulaireCreation(array(), $saisie);
    }

    public function enregistrerCreationAction(Request $request)
    {
        $refus = $this->refuserSiPasAdministrateur($request);
        if ($refus !== null) {
            return $refus;
        }
        $this->verifierJeton($request, 'admin_creer');
        $pdo = $this->get('pg_gsb_frais.pdo');

        $saisie     = $this->lireSaisie($request);
        $lesErreurs = $this->validerSaisie($pdo, $saisie, null, null);

        $mdp  = (string) $request->request->get('mdp');
        $mdp2 = (string) $request->request->get('mdp2');
        if ($mdp !== $mdp2) {
            $lesErreurs[] = 'Le mot de passe et sa confirmation diffèrent.';
        }
        $lesErreurs = array_merge($lesErreurs, valideMotDePasse($mdp));

        if (count($lesErreurs) > 0) {
            // La saisie est renvoyée au formulaire pour que l'administrateur
            // n'ait pas à tout retaper. Le mot de passe, lui, n'est jamais
            // réaffiché.
            return $this->rendreFormulaireCreation($lesErreurs, $saisie);
        }

        $donnees                = $saisie;
        $donnees['empreinte']   = password_hash($mdp, PASSWORD_BCRYPT);
        // Coché par défaut dans le formulaire : l'administrateur a forcément
        // choisi ce premier mot de passe, le laisser en place signifierait qu'un
        // tiers le connaît durablement.
        $donnees['doitChanger'] = $request->request->get('forcerChangement') ? 1 : 0;

        // Le matricule est généré par PdoGsb::creerCompte(), qui retente si la
        // clé primaire refuse : deux administrateurs peuvent créer un compte au
        // même instant.
        $id = $pdo->creerCompte($saisie['role'], $donnees);

        $this->journaliser($request, 'Création de compte', $saisie['role'], $id,
                           $saisie['prenom'] . ' ' . $saisie['nom'],
                           'rôle ' . $saisie['role']
                           . ($donnees['doitChanger'] ? ' ; changement de mot de passe imposé' : ''));

        return $this->retourAvecMessage($request, 'confirmation', sprintf(
            'Le compte de %s %s a été créé. Matricule attribué : %s.',
            $saisie['prenom'], $saisie['nom'], $id));
    }

    // =====================================================================
    // Modification
    // =====================================================================

    public function modifierAction(Request $request, $role, $id)
    {
        $refus = $this->refuserSiPasAdministrateur($request);
        if ($refus !== null) {
            return $refus;
        }
        $leCompte = $this->get('pg_gsb_frais.pdo')->getCompte($role, $id);
        if (!is_array($leCompte)) {
            throw $this->createNotFoundException('Compte introuvable');
        }

        return $this->rendreFormulaireModification($role, $leCompte, array(),
                                                   $this->saisieDepuisCompte($role, $leCompte));
    }

    public function enregistrerModificationAction(Request $request, $role, $id)
    {
        $refus = $this->refuserSiPasAdministrateur($request);
        if ($refus !== null) {
            return $refus;
        }
        $this->verifierJeton($request, 'admin_modifier');
        $pdo      = $this->get('pg_gsb_frais.pdo');
        $leCompte = $pdo->getCompte($role, $id);
        if (!is_array($leCompte)) {
            throw $this->createNotFoundException('Compte introuvable');
        }

        // Le rôle et le matricule viennent de l'URL, jamais du formulaire : le
        // champ matricule y est en lecture seule, et un attribut readonly se
        // retire en trois clics dans un navigateur.
        $saisie         = $this->lireSaisie($request);
        $saisie['role'] = $role;
        $lesErreurs     = $this->validerSaisie($pdo, $saisie, $role, $id);

        if (count($lesErreurs) > 0) {
            return $this->rendreFormulaireModification($role, $leCompte, $lesErreurs, $saisie);
        }

        $pdo->majCompte($role, $id, $saisie);
        $this->journaliser($request, 'Modification de compte', $role, $id,
                           $saisie['prenom'] . ' ' . $saisie['nom'], null);

        return $this->retourAvecMessage($request, 'confirmation', sprintf(
            'Le compte de %s %s (%s) a été mis à jour.',
            $saisie['prenom'], $saisie['nom'], $id));
    }

    // =====================================================================
    // Confirmation : désactivation, réactivation, suppression
    // =====================================================================

    /**
     * Écran de confirmation commun aux trois actions sensibles.
     *
     * Une PAGE et non un confirm() JavaScript : elle a son URL, survit à un
     * rechargement et fonctionne sans JS. Un confirm() vit dans le navigateur,
     * qui n'est pas de notre côté.
     *
     * Un seul gabarit pour les trois actions : le titre et la liste des
     * conséquences changent, la structure non. Trois écrans recopiés auraient
     * fini par divulguer des formulations divergentes.
     */
    public function confirmerAction(Request $request, $role, $id, $action)
    {
        $refus = $this->refuserSiPasAdministrateur($request);
        if ($refus !== null) {
            return $refus;
        }
        $pdo      = $this->get('pg_gsb_frais.pdo');
        $leCompte = $pdo->getCompte($role, $id);
        if (!is_array($leCompte)) {
            throw $this->createNotFoundException('Compte introuvable');
        }

        $lesLiaisons = ($role === 'visiteur') ? $pdo->compterDonneesLiees($id) : null;
        $blocage     = $this->chercherBlocage($request, $pdo, $role, $id, $action, $lesLiaisons);

        return $this->render('PgGsbFraisBundle:Admin:confirmer.html.twig', array(
            'lecompte'    => $leCompte,
            'lerole'      => $role,
            'laction'     => $action,
            'lesliaisons' => $lesLiaisons,
            'blocage'     => $blocage,
        ));
    }

    /**
     * Détermine si une action sensible doit être refusée, et pourquoi.
     *
     * Écrite une fois et appelée DEUX fois : à l'affichage de la confirmation,
     * pour ne pas proposer un bouton qui échouerait, et à l'exécution, parce que
     * l'état peut changer entre les deux -- un autre administrateur peut avoir
     * été désactivé dans un onglet voisin, ou une fiche de frais créée.
     *
     * @return string|null le motif du refus, ou null si l'action est permise
     */
    private function chercherBlocage(Request $request, $pdo, $role, $id, $action, $lesLiaisons)
    {
        $moi         = $request->getSession()->get('id');
        $estDestructif = ($action === 'desactiver' || $action === 'supprimer');

        // Garde-fou : il doit toujours rester un administrateur actif. Sans lui,
        // l'interface d'administration deviendrait définitivement inaccessible
        // et il faudrait repasser par du SQL à la main -- précisément ce que
        // cette phase supprime.
        //
        // Testé AVANT le garde-fou « pas soi-même » : quand les deux
        // s'appliquent, c'est-à-dire quand le dernier administrateur tente de
        // se désactiver, ce motif-ci est le plus utile. Il dit ce qu'il faut
        // faire pour débloquer la situation, l'autre constate seulement le refus.
        if ($role === 'administrateur' && $estDestructif
            && $pdo->compterAdministrateursActifs() <= 1) {
            return 'Ce compte est le dernier administrateur actif. Sans lui, plus '
                . 'personne ne pourrait gérer les comptes de l\'application. '
                . 'Créez ou réactivez d\'abord un autre administrateur.';
        }

        // Garde-fou : on ne se désactive ni ne se supprime soi-même. Sans cela,
        // un administrateur pourrait se verrouiller dehors d'un clic.
        if ($role === 'administrateur' && $id === $moi && $estDestructif) {
            return 'Vous ne pouvez pas ' . ($action === 'supprimer' ? 'supprimer' : 'désactiver')
                . ' votre propre compte.';
        }

        // Garde-fou : pas de suppression physique d'un compte qui a des données.
        if ($action === 'supprimer' && is_array($lesLiaisons)
            && (int) $lesLiaisons['nbFiches'] > 0) {
            return 'Ce visiteur a des fiches de frais rattachées.';
        }

        return null;
    }

    public function basculerActifAction(Request $request, $role, $id)
    {
        $refus = $this->refuserSiPasAdministrateur($request);
        if ($refus !== null) {
            return $refus;
        }
        $this->verifierJeton($request, 'admin_basculer');
        $pdo      = $this->get('pg_gsb_frais.pdo');
        $leCompte = $pdo->getCompte($role, $id);
        if (!is_array($leCompte)) {
            throw $this->createNotFoundException('Compte introuvable');
        }

        // On désactive si le compte est actif, on réactive sinon : l'action
        // déduite de l'état évite qu'un formulaire rejoué fasse l'inverse de ce
        // que l'administrateur voyait.
        $actifVoulu = ((int) $leCompte['actif'] === 1) ? 0 : 1;
        $action     = $actifVoulu ? 'reactiver' : 'desactiver';
        $nomCible   = $leCompte['prenom'] . ' ' . $leCompte['nom'];

        $blocage = $this->chercherBlocage($request, $pdo, $role, $id, $action, null);
        if ($blocage !== null) {
            // Le refus est journalisé autant que la réussite : une tentative
            // d'auto-désactivation dit quelque chose sur ce qui a été cherché.
            $this->journaliser($request, 'Désactivation refusée', $role, $id, $nomCible, $blocage);

            return $this->retourAvecMessage($request, 'erreur', $blocage);
        }

        $nb = $pdo->majActifCompte($role, $id, $actifVoulu);
        if ($nb === 0) {
            // majActifCompte() ne modifie que si l'état change réellement : on
            // n'écrit pas au journal une désactivation qui n'a pas eu lieu.
            return $this->retourAvecMessage($request, 'erreur',
                'Ce compte était déjà dans l\'état demandé, rien n\'a été modifié.');
        }

        $this->journaliser($request, $actifVoulu ? 'Réactivation' : 'Désactivation',
                           $role, $id, $nomCible,
                           trim((string) $request->request->get('motif')) ?: null);

        return $this->retourAvecMessage($request, 'confirmation', $actifVoulu
            ? sprintf('Le compte de %s (%s) a été réactivé.', $nomCible, $id)
            : sprintf('Le compte de %s (%s) a été désactivé. Aucune donnée n\'a été '
                      . 'supprimée : ses fiches restent consultables.', $nomCible, $id));
    }

    public function supprimerAction(Request $request, $role, $id)
    {
        $refus = $this->refuserSiPasAdministrateur($request);
        if ($refus !== null) {
            return $refus;
        }
        $this->verifierJeton($request, 'admin_supprimer');
        $pdo      = $this->get('pg_gsb_frais.pdo');
        $leCompte = $pdo->getCompte($role, $id);
        if (!is_array($leCompte)) {
            throw $this->createNotFoundException('Compte introuvable');
        }
        $nomCible    = $leCompte['prenom'] . ' ' . $leCompte['nom'];
        $lesLiaisons = ($role === 'visiteur') ? $pdo->compterDonneesLiees($id) : null;

        // Recontrôle : le compte a pu recevoir une fiche depuis l'affichage de
        // la confirmation.
        $blocage = $this->chercherBlocage($request, $pdo, $role, $id, 'supprimer', $lesLiaisons);
        if ($blocage !== null) {
            $this->journaliser($request, 'Suppression refusée', $role, $id, $nomCible, $blocage);

            return $this->retourAvecMessage($request, 'erreur', $blocage
                . ' La suppression a été refusée ; utilisez la désactivation.');
        }

        $pdo->supprimerCompte($role, $id);
        // Journalisé APRÈS la suppression, et le nom y est recopié : c'est
        // précisément pour cela que journaladmin ne référence pas les comptes
        // par une clé étrangère.
        $this->journaliser($request, 'Suppression définitive', $role, $id, $nomCible,
                           trim((string) $request->request->get('motif')) ?: null);

        return $this->retourAvecMessage($request, 'confirmation', sprintf(
            'Le compte de %s (%s) a été supprimé définitivement.', $nomCible, $id));
    }

    // =====================================================================
    // Réinitialisation de mot de passe
    // =====================================================================

    public function reinitialiserAction(Request $request, $role, $id)
    {
        $refus = $this->refuserSiPasAdministrateur($request);
        if ($refus !== null) {
            return $refus;
        }
        $this->verifierJeton($request, 'admin_reinitialiser');
        $pdo      = $this->get('pg_gsb_frais.pdo');
        $leCompte = $pdo->getCompte($role, $id);
        if (!is_array($leCompte)) {
            throw $this->createNotFoundException('Compte introuvable');
        }

        $temporaire = genererMotDePasseTemporaire();
        $pdo->majEmpreinteMotDePasse($role, $id,
                                     password_hash($temporaire, PASSWORD_BCRYPT), 1);

        // Le mot de passe temporaire N'EST PAS journalisé : journaladmin est
        // consultable depuis l'interface, et une table consultable finit
        // exportée. Seul le fait de la réinitialisation est tracé.
        $this->journaliser($request, 'Réinitialisation de mot de passe', $role, $id,
                           $leCompte['prenom'] . ' ' . $leCompte['nom'],
                           'changement imposé à la prochaine connexion');

        // Rendu direct et non redirection : le mot de passe en clair ne doit pas
        // transiter par l'URL, et il ne doit pas non plus être mis en session
        // pour survivre à une redirection -- il n'existe que le temps de cette
        // réponse.
        return $this->render('PgGsbFraisBundle:Admin:motdepasse.html.twig', array(
            'lecompte'   => $leCompte,
            'lerole'     => $role,
            'temporaire' => $temporaire,
        ));
    }

    // =====================================================================
    // Journal
    // =====================================================================

    public function journalAction(Request $request)
    {
        $refus = $this->refuserSiPasAdministrateur($request);
        if ($refus !== null) {
            return $refus;
        }
        $pdo = $this->get('pg_gsb_frais.pdo');

        $auteur = (string) $request->query->get('auteur', '');
        $action = (string) $request->query->get('action', '');
        $cible  = trim((string) $request->query->get('cible', ''));
        $page   = max(1, (int) $request->query->get('page', 1));

        $total   = $pdo->compterLeJournal($auteur, $action, $cible);
        $nbPages = max(1, (int) ceil($total / AdminController::PAR_PAGE));
        $page    = min($page, $nbPages);

        return $this->render('PgGsbFraisBundle:Admin:journal.html.twig', array(
            'lesentrees' => $pdo->getLeJournal($auteur, $action, $cible,
                                               AdminController::PAR_PAGE,
                                               ($page - 1) * AdminController::PAR_PAGE),
            'lesauteurs' => $pdo->getLesAuteursDuJournal(),
            'lesactions' => $pdo->getLesActionsDuJournal(),
            'auteur'     => $auteur,
            'action'     => $action,
            'cible'      => $cible,
            'page'       => $page,
            'nbpages'    => $nbPages,
            'total'      => $total,
        ));
    }

    // =====================================================================
    // Outils privés
    // =====================================================================

    /**
     * Lit et assainit les champs du formulaire de compte.
     *
     * trim() sur tout : un login avec une espace finale serait invisible à
     * l'écran et empêcherait la connexion. Les champs métier ne sont lus que
     * pour un visiteur -- un comptable ou un administrateur n'a ni véhicule ni
     * date d'embauche, et leur table n'a pas ces colonnes.
     *
     * L'échappement HTML n'est PAS fait ici : Twig échappe à l'affichage, par
     * défaut. Le faire aussi en amont stockerait des &amp; en base et
     * afficherait « Dupont &amp; Fils » à l'écran.
     */
    private function lireSaisie(Request $request)
    {
        $saisie = array(
            'role'   => (string) $request->request->get('role', 'visiteur'),
            'nom'    => trim((string) $request->request->get('nom')),
            'prenom' => trim((string) $request->request->get('prenom')),
            'login'  => trim((string) $request->request->get('login')),
        );
        // Valeurs par défaut pour les rôles sans informations métier : majCompte()
        // et creerCompte() ne les liront pas, mais les clés doivent exister.
        $saisie['dateEmbauche']   = null;
        $saisie['idTypeVehicule'] = null;
        $saisie['adresse']        = null;
        $saisie['cp']             = null;
        $saisie['ville']          = null;

        if ($saisie['role'] === 'visiteur') {
            $dateFr = trim((string) $request->request->get('dateEmbauche'));
            // La base attend aaaa-mm-jj, le formulaire propose jj/mm/aaaa comme
            // partout ailleurs dans l'application.
            $saisie['dateEmbauche']   = ($dateFr === '') ? null : dateFrancaisVersAnglais($dateFr);
            $saisie['dateEmbaucheFr'] = $dateFr;
            $vehicule                 = trim((string) $request->request->get('idTypeVehicule'));
            $saisie['idTypeVehicule'] = ($vehicule === '') ? null : $vehicule;
            $saisie['adresse']        = trim((string) $request->request->get('adresse')) ?: null;
            $saisie['cp']             = trim((string) $request->request->get('cp')) ?: null;
            $saisie['ville']          = trim((string) $request->request->get('ville')) ?: null;
        }

        return $saisie;
    }

    /**
     * Contrôle la saisie, indépendamment de la création ou de la modification.
     *
     * @param $roleExclu, $idExclu le compte modifié, autorisé à garder son login
     * @return array les messages d'erreur
     */
    private function validerSaisie($pdo, array $saisie, $roleExclu, $idExclu)
    {
        $lesErreurs = array();

        if (!in_array($saisie['role'], $pdo->getLesRoles(), true)) {
            // Le rôle vient d'une liste déroulante : s'il est hors liste, le
            // formulaire a été forgé. On refuse sans chercher à comprendre.
            $lesErreurs[] = 'Rôle invalide.';

            return $lesErreurs;
        }
        if ($saisie['nom'] === '') {
            $lesErreurs[] = 'Le nom est obligatoire.';
        }
        if ($saisie['prenom'] === '') {
            $lesErreurs[] = 'Le prénom est obligatoire.';
        }
        if (mb_strlen($saisie['nom']) > 30 || mb_strlen($saisie['prenom']) > 30) {
            $lesErreurs[] = 'Le nom et le prénom sont limités à 30 caractères.';
        }

        if ($saisie['login'] === '') {
            $lesErreurs[] = 'Le login est obligatoire.';
        } elseif (mb_strlen($saisie['login']) > 20) {
            $lesErreurs[] = 'Le login est limité à 20 caractères.';
        } elseif (!preg_match('/^[A-Za-z0-9._-]+$/', $saisie['login'])) {
            $lesErreurs[] = 'Le login ne peut contenir que des lettres, des chiffres, '
                . 'un point, un tiret ou un souligné.';
        } elseif ($pdo->loginExiste($saisie['login'], $roleExclu, $idExclu)) {
            // Contrôle sur la vue v_compte, donc sur les trois tables : aucune
            // contrainte UNIQUE ne peut couvrir trois tables à la fois.
            $lesErreurs[] = 'Ce login est déjà utilisé par un autre compte.';
        }

        if ($saisie['role'] === 'visiteur') {
            $dateFr = isset($saisie['dateEmbaucheFr']) ? $saisie['dateEmbaucheFr'] : '';
            if ($dateFr !== '' && !estDateValide($dateFr)) {
                $lesErreurs[] = 'La date d\'embauche est invalide (format attendu jj/mm/aaaa).';
            }
            if ($saisie['cp'] !== null && !preg_match('/^[0-9]{5}$/', $saisie['cp'])) {
                $lesErreurs[] = 'Le code postal doit comporter 5 chiffres.';
            }
            if ($saisie['idTypeVehicule'] !== null
                && !$this->vehiculeExiste($pdo, $saisie['idTypeVehicule'])) {
                $lesErreurs[] = 'Type de véhicule inconnu.';
            }
        }

        return $lesErreurs;
    }

    /** Le barème est en base : un identifiant reçu du formulaire doit y figurer. */
    private function vehiculeExiste($pdo, $idTypeVehicule)
    {
        foreach ($pdo->getLesTypesVehicule() as $unType) {
            if ($unType['id'] === $idTypeVehicule) {
                return true;
            }
        }

        return false;
    }

    private function valeursParDefaut()
    {
        return array('role' => 'visiteur', 'nom' => '', 'prenom' => '', 'login' => '',
                     'dateEmbaucheFr' => '', 'idTypeVehicule' => null,
                     'adresse' => '', 'cp' => '', 'ville' => '');
    }

    /** Prépare les valeurs du formulaire de modification depuis la base. */
    private function saisieDepuisCompte($role, array $leCompte)
    {
        return array(
            'role'           => $role,
            'nom'            => $leCompte['nom'],
            'prenom'         => $leCompte['prenom'],
            'login'          => $leCompte['login'],
            'dateEmbaucheFr' => ($leCompte['dateEmbauche'] === null)
                                ? '' : dateAnglaisVersFrancais($leCompte['dateEmbauche']),
            'idTypeVehicule' => $leCompte['idTypeVehicule'],
            'adresse'        => $leCompte['adresse'],
            'cp'             => $leCompte['cp'],
            'ville'          => $leCompte['ville'],
        );
    }

    private function rendreFormulaireCreation(array $lesErreurs, array $saisie)
    {
        $pdo = $this->get('pg_gsb_frais.pdo');

        return $this->render('PgGsbFraisBundle:Admin:creer.html.twig', array(
            'leserreurs'      => $lesErreurs,
            'saisie'          => $saisie,
            'lesroles'        => $pdo->getLesRoles(),
            'lestypevehicule' => $pdo->getLesTypesVehicule(),
        ));
    }

    private function rendreFormulaireModification($role, array $leCompte,
                                                  array $lesErreurs, array $saisie)
    {
        $pdo         = $this->get('pg_gsb_frais.pdo');
        $lesLiaisons = ($role === 'visiteur') ? $pdo->compterDonneesLiees($leCompte['id']) : null;

        return $this->render('PgGsbFraisBundle:Admin:modifier.html.twig', array(
            'leserreurs'      => $lesErreurs,
            'saisie'          => $saisie,
            'lecompte'        => $leCompte,
            'lerole'          => $role,
            'lesliaisons'     => $lesLiaisons,
            'lestypevehicule' => $pdo->getLesTypesVehicule(),
        ));
    }

    /**
     * Écrit une entrée au journal pour l'administrateur connecté.
     *
     * Le nom de l'auteur est pris en session et recopié en base : le journal
     * doit rester lisible même après la suppression du compte qui a agi.
     */
    private function journaliser(Request $request, $action, $roleCible, $idCible,
                                 $nomCible, $detail)
    {
        $session = $request->getSession();
        $this->get('pg_gsb_frais.pdo')->journaliser(
            $session->get('id'),
            $session->get('prenom') . ' ' . $session->get('nom'),
            $action, $roleCible, $idCible, $nomCible, $detail);
    }

    /**
     * Redirige vers la liste en déposant un message.
     *
     * Redirection et non rendu direct : après une écriture, un rafraîchissement
     * de page ne doit pas rejouer l'action. Le message passe par le flash bag,
     * qui survit à la redirection et se vide à la première lecture.
     */
    private function retourAvecMessage(Request $request, $type, $texte)
    {
        $request->getSession()->getFlashBag()->add('admin', $type . '|' . $texte);

        return $this->redirect($this->generateUrl('pg_gsb_frais_admin'));
    }

    /** @return array(type, texte) ou null */
    private function lireMessage(Request $request)
    {
        $lesMessages = $request->getSession()->getFlashBag()->get('admin');
        if (count($lesMessages) === 0) {
            return null;
        }
        // Le séparateur est une barre verticale : elle n'apparaît dans aucun des
        // messages émis, et évite de sérialiser un tableau en session.
        $morceaux = explode('|', $lesMessages[0], 2);

        return array('type' => $morceaux[0], 'texte' => $morceaux[1]);
    }
}
