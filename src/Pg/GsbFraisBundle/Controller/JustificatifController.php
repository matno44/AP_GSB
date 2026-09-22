<?php
namespace Pg\GsbFraisBundle\Controller;

require_once __DIR__.'/include/fct.inc.php';

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

/**
 * Consultation et dépôt des justificatifs de frais hors forfait.
 *
 * Les fichiers sont stockés hors de web/ : ils ne sont donc jamais servis
 * directement par le serveur web. Toute lecture passe par consulterAction(),
 * qui vérifie d'abord que le demandeur a le droit de voir le fichier.
 */
class JustificatifController extends Controller
{
    /**
     * Rattache un justificatif à une ligne de frais hors forfait existante.
     */
    public function joindreAction(Request $request, $id)
    {
        $session = $request->getSession();
        if (!estConnecte($session)) {
            return $this->redirect($this->generateUrl('pg_gsb_frais_home'));
        }
        $pdo = $this->get('pg_gsb_frais.pdo');
        $laLigne = $pdo->getLigneFraisHorsForfait($id);

        // Le frais doit exister ET appartenir au visiteur connecté. Sans ce
        // contrôle, n'importe qui pourrait joindre un fichier au frais d'un
        // collègue en changeant l'identifiant dans l'URL.
        if (!is_array($laLigne) || $laLigne['idVisiteur'] !== $session->get('id')) {
            throw $this->createNotFoundException('Frais hors forfait introuvable');
        }

        $stockage = $this->get('pg_gsb_frais.justificatifs');
        $fichier = $request->files->get('justificatif');
        $lesErreurs = $stockage->valider($fichier);
        if (count($lesErreurs) == 0) {
            // L'ancien fichier est retiré du disque APRÈS l'enregistrement du
            // nouveau : si l'écriture échoue, le visiteur conserve son
            // justificatif précédent plutôt que de tout perdre.
            $ancienFichier = $laLigne['nomFichier'];
            $nomOriginal = $stockage->assainirNom($fichier->getClientOriginalName());
            $typeMime = $fichier->getMimeType();
            $nomFichier = $stockage->enregistrer($fichier);
            $pdo->majJustificatifFrais($id, $nomFichier, $nomOriginal, $typeMime);
            $stockage->supprimer($ancienFichier);
        } else {
            // Les messages sont transmis par le flash bag : l'action redirige,
            // ils doivent survivre à la redirection puis disparaître.
            foreach ($lesErreurs as $uneErreur) {
                ajouterErreur($session, $uneErreur);
            }
        }

        return $this->redirect($this->generateUrl('pg_gsb_frais_saisirfrais'));
    }

    /**
     * Sert le fichier justificatif d'une ligne de frais hors forfait.
     */
    public function consulterAction(Request $request, $id)
    {
        $session = $request->getSession();
        if (!estConnecte($session)) {
            return $this->redirect($this->generateUrl('pg_gsb_frais_home'));
        }
        $pdo = $this->get('pg_gsb_frais.pdo');
        $laLigne = $pdo->getLigneFraisHorsForfait($id);
        if (!is_array($laLigne) || $laLigne['nomFichier'] === null) {
            throw $this->createNotFoundException('Justificatif introuvable');
        }

        // Contrôle d'accès : le propriétaire du frais, ou un comptable.
        // estComptable() renvoie faux tant qu'aucun compte comptable n'existe
        // (phase 5) ; la règle est en place, il ne manquera que l'acteur.
        $estProprietaire = ($laLigne['idVisiteur'] === $session->get('id'));
        if (!$estProprietaire && !estComptable($session)) {
            // 404 et non 403 : répondre « interdit » confirmerait au curieux
            // que ce justificatif existe bel et bien.
            throw $this->createNotFoundException('Justificatif introuvable');
        }

        $stockage = $this->get('pg_gsb_frais.justificatifs');
        $chemin = $stockage->chemin($laLigne['nomFichier']);
        if ($chemin === null) {
            // La base référence un fichier absent du disque : anomalie réelle,
            // qu'il vaut mieux voir qu'ignorer.
            throw $this->createNotFoundException(
                'Le fichier du justificatif est introuvable sur le serveur');
        }

        $reponse = new BinaryFileResponse($chemin);
        $reponse->headers->set('Content-Type',
            $laLigne['typeMime'] ?: 'application/octet-stream');
        // INLINE : le PDF ou l'image s'ouvre dans le navigateur. Le nom
        // proposé est le nom d'origine assaini, jamais le nom de stockage.
        $reponse->setContentDisposition(ResponseHeaderBag::DISPOSITION_INLINE,
            $laLigne['nomOriginal'] ?: 'justificatif');

        return $reponse;
    }
}
