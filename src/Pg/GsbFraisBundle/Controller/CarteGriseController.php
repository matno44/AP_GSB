<?php
namespace Pg\GsbFraisBundle\Controller;

require_once __DIR__.'/include/fct.inc.php';

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

/**
 * Traitement des cartes grises par le comptable, et consultation des documents
 * (phase 10).
 *
 * Les fichiers sont stockés hors de web/ : ils ne sont donc jamais servis
 * directement par le serveur web. Toute lecture passe par consulterAction(),
 * qui vérifie d'abord que le demandeur a le droit de voir le document — le
 * visiteur concerné, ou un comptable. Même schéma que les justificatifs de la
 * phase 2.
 *
 * CE QUE CE CONTRÔLEUR GARANTIT. Aucune caractéristique de véhicule n'entre
 * dans les données qui servent au calcul des frais sans passer par
 * validerAction(), donc sans qu'un comptable ait ouvert le document et pris
 * une décision tracée.
 */
class CarteGriseController extends ControleurGsb
{
    /**
     * Garde commune aux actions réservées au comptable.
     *
     * Le contrôle est fait ici, côté serveur, et non en masquant le menu :
     * masquer un lien n'empêche personne de taper l'URL.
     *
     * @return une Response à renvoyer si l'accès est refusé, null sinon
     */
    private function refuserSiPasComptable(Request $request)
    {
        $session = $request->getSession();
        if (!estConnecte($session)) {
            return $this->redirect($this->generateUrl('pg_gsb_frais_home'));
        }
        if (!estComptable($session)) {
            return $this->refuserNonComptable();
        }

        return null;
    }

    /**
     * Sert le document d'une demande.
     *
     * Seule action de ce contrôleur ouverte au visiteur : il doit pouvoir
     * relire ce qu'il a envoyé, ne serait-ce que pour comprendre un refus.
     */
    public function consulterAction(Request $request, $id)
    {
        $session = $request->getSession();
        if (!estConnecte($session)) {
            return $this->redirect($this->generateUrl('pg_gsb_frais_home'));
        }
        $pdo = $this->get('pg_gsb_frais.pdo');
        $laDemande = $pdo->getCarteGrise($id);
        if (!is_array($laDemande)) {
            throw $this->createNotFoundException('Carte grise introuvable');
        }

        // Contrôle d'accès : le visiteur concerné, ou un comptable.
        $estProprietaire = ($laDemande['idVisiteur'] === $session->get('id')
            && estVisiteur($session));
        if (!$estProprietaire && !estComptable($session)) {
            // 404 et non 403 : répondre « interdit » confirmerait au curieux
            // que ce document existe bel et bien. Une carte grise porte une
            // adresse et une identité, le silence est préférable.
            throw $this->createNotFoundException('Carte grise introuvable');
        }

        $stockage = $this->get('pg_gsb_frais.cartesgrises');
        $chemin = $stockage->chemin($laDemande['nomFichier']);
        if ($chemin === null) {
            // La base référence un fichier absent du disque : anomalie réelle,
            // qu'il vaut mieux voir qu'ignorer.
            throw $this->createNotFoundException(
                'Le fichier de la carte grise est introuvable sur le serveur');
        }

        $reponse = new BinaryFileResponse($chemin);
        $reponse->headers->set('Content-Type',
            $laDemande['typeMime'] ?: 'application/octet-stream');
        // INLINE : le PDF ou l'image s'ouvre dans le navigateur, ce qui permet
        // au comptable de lire le document sans quitter son poste de travail.
        // Le nom proposé est le nom d'origine assaini, jamais le nom de
        // stockage.
        $reponse->setContentDisposition(ResponseHeaderBag::DISPOSITION_INLINE,
            $laDemande['nomOriginal'] ?: 'cartegrise');

        return $reponse;
    }

    /**
     * Liste les demandes en attente de traitement.
     */
    public function attenteAction(Request $request)
    {
        $refus = $this->refuserSiPasComptable($request);
        if ($refus !== null) {
            return $refus;
        }
        $pdo = $this->get('pg_gsb_frais.pdo');

        return $this->render('PgGsbFraisBundle:CarteGrise:attente.html.twig',
            array('lesdemandes' => $pdo->getLesCartesGrisesEnAttente()));
    }

    /**
     * Affiche une demande : le document, et le formulaire de décision.
     */
    public function examinerAction(Request $request, $id)
    {
        $refus = $this->refuserSiPasComptable($request);
        if ($refus !== null) {
            return $refus;
        }

        return $this->rendreExamen($id, array(), array());
    }

    /**
     * Valide une demande : les caractéristiques relevées deviennent celles du
     * visiteur, et son tarif kilométrique avec.
     */
    public function validerAction(Request $request, $id)
    {
        $refus = $this->refuserSiPasComptable($request);
        if ($refus !== null) {
            return $refus;
        }
        $this->verifierJeton($request, 'carte_grise_decision');
        $pdo = $this->get('pg_gsb_frais.pdo');

        $lesValeurs = array(
            'idTypeVehicule' => $request->request->get('idTypeVehicule'),
            'immatriculation' => trim($request->request->get('immatriculation', '')),
            'marqueModele' => trim($request->request->get('marqueModele', '')),
        );
        $lesErreurs = $this->validerReleve($pdo, $lesValeurs);
        if (count($lesErreurs) > 0) {
            // Les valeurs saisies sont renvoyées au gabarit : le comptable ne
            // doit pas avoir à relever une seconde fois ce qu'il a déjà lu.
            return $this->rendreExamen($id, $lesErreurs, $lesValeurs);
        }

        $aValide = $pdo->validerCarteGrise($id,
            $lesValeurs['idTypeVehicule'],
            strtoupper($lesValeurs['immatriculation']),
            $lesValeurs['marqueModele'],
            $request->getSession()->get('id'));
        if (!$aValide) {
            // La demande n'était plus en attente : un autre comptable l'avait
            // tranchée entre l'affichage du formulaire et son envoi.
            return $this->rendreExamen($id,
                array('Cette demande a déjà été traitée entre-temps. '
                    .'Rafraîchissez la liste.'), $lesValeurs);
        }

        return $this->redirigerVersLaListe($request,
            'La carte grise a été validée et le véhicule du visiteur mis à jour.');
    }

    /**
     * Refuse une demande. Le motif est obligatoire.
     */
    public function refuserAction(Request $request, $id)
    {
        $refus = $this->refuserSiPasComptable($request);
        if ($refus !== null) {
            return $refus;
        }
        $this->verifierJeton($request, 'carte_grise_decision');
        $pdo = $this->get('pg_gsb_frais.pdo');
        $motif = trim($request->request->get('motifRefus', ''));

        // Le motif est la seule chose que le visiteur recevra pour comprendre
        // ce qu'il doit corriger. Un refus sans motif le renverrait au même
        // envoi, et le document reviendrait à l'identique.
        if ($motif === '') {
            return $this->rendreExamen($id,
                array('Le motif du refus est obligatoire : sans lui, le '
                    .'visiteur ne saura pas quoi corriger.'), array());
        }
        if (strlen($motif) > 255) {
            // La colonne fait 255 caractères : on tronque ici plutôt que de
            // laisser MySQL le faire silencieusement.
            $motif = substr($motif, 0, 255);
        }

        $aRefuse = $pdo->refuserCarteGrise($id, $motif,
            $request->getSession()->get('id'));
        if (!$aRefuse) {
            return $this->rendreExamen($id,
                array('Cette demande a déjà été traitée entre-temps. '
                    .'Rafraîchissez la liste.'), array());
        }

        return $this->redirigerVersLaListe($request,
            'La carte grise a été refusée. Le visiteur peut en envoyer une '
            .'nouvelle.');
    }

    /**
     * Contrôle les informations relevées par le comptable.
     *
     * Les trois champs sont exigés. L'immatriculation et la marque ne servent
     * aucun calcul : elles rattachent la décision à un véhicule identifié et
     * attestent que le document a bien été ouvert. Les rendre facultatives
     * reviendrait à pouvoir valider sans l'avoir lu, ce qui vide le contrôle
     * de son sens.
     *
     * @return un tableau de messages, vide si le relevé est acceptable
     */
    private function validerReleve($pdo, array $lesValeurs)
    {
        $lesErreurs = array();
        // La valeur vient d'une liste déroulante, donc du navigateur, donc
        // elle n'est pas digne de confiance. La clé étrangère refuserait de
        // toute façon une valeur inconnue, mais avec une erreur SQL brute.
        if (!$pdo->estTypeVehiculeValide($lesValeurs['idTypeVehicule'])) {
            $lesErreurs[] = 'Choisissez la puissance fiscale et le carburant '
                .'relevés sur la carte grise.';
        }
        if ($lesValeurs['immatriculation'] === '') {
            $lesErreurs[] = "L'immatriculation ne doit pas être vide.";
        } elseif (!preg_match('/^[A-Za-z0-9 -]{4,15}$/',
                $lesValeurs['immatriculation'])) {
            // Volontairement permissif : les plaques françaises ont changé de
            // format en 2009 (AA-123-AA contre 123 AB 45), et un véhicule
            // immatriculé à l'étranger reste possible. On écarte les saisies
            // manifestement fausses, sans imposer un format qui refuserait un
            // cas légitime.
            $lesErreurs[] = "L'immatriculation ne doit contenir que des "
                .'lettres, des chiffres, des espaces et des tirets.';
        }
        if ($lesValeurs['marqueModele'] === '') {
            $lesErreurs[] = 'La marque et le modèle ne doivent pas être vides.';
        } elseif (strlen($lesValeurs['marqueModele']) > 60) {
            $lesErreurs[] = 'La marque et le modèle ne doivent pas dépasser '
                .'60 caractères.';
        }

        return $lesErreurs;
    }

    /**
     * Rend l'écran d'examen d'une demande.
     *
     * @param $lesErreurs messages à afficher
     * @param $lesValeurs valeurs saisies à réafficher, vide au premier accès
     */
    private function rendreExamen($id, array $lesErreurs, array $lesValeurs)
    {
        $pdo = $this->get('pg_gsb_frais.pdo');
        $laDemande = $pdo->getCarteGrise($id);
        if (!is_array($laDemande)) {
            throw $this->createNotFoundException('Carte grise introuvable');
        }

        return $this->render('PgGsbFraisBundle:CarteGrise:examiner.html.twig',
            array('lademande' => $laDemande,
                'lestypesvehicule' => $pdo->getLesTypesVehicule(),
                'lesvaleurs' => $lesValeurs,
                'leserreurs' => $lesErreurs));
    }

    /**
     * Redirige vers la liste en déposant un message de confirmation.
     *
     * Redirection plutôt que rendu direct : un rafraîchissement ne repostera
     * pas la décision. Le message passe par le flash bag, qui est fait pour
     * survivre à une redirection puis disparaître.
     */
    private function redirigerVersLaListe(Request $request, $message)
    {
        $request->getSession()->getFlashBag()->add('confirmation', $message);

        return $this->redirect($this->generateUrl('pg_gsb_frais_cartesgrises'));
    }
}
