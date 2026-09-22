<?php
namespace Pg\GsbFraisBundle\Controller;

require_once __DIR__.'/include/fct.inc.php';

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

/**
 * Impression des fiches de frais au format de l'annexe 1.
 */
class FichePdfController extends Controller
{
    /**
     * Imprime une fiche de frais.
     *
     * Même règle d'accès que pour les justificatifs : le visiteur concerné,
     * ou un comptable. Une seule route sert les deux cas, donc une seule
     * règle à écrire, à tester et à maintenir.
     */
    public function imprimerAction(Request $request, $idVisiteur, $mois)
    {
        $session = $request->getSession();
        if (!estConnecte($session)) {
            return $this->redirect($this->generateUrl('pg_gsb_frais_home'));
        }
        if ($idVisiteur !== $session->get('id') && !estComptable($session)) {
            // 404 plutôt que 403 : ne pas révéler l'existence de la fiche.
            throw $this->createNotFoundException('Fiche de frais introuvable');
        }

        $pdf = $this->get('pg_gsb_frais.fiche_pdf')->genererFiche($idVisiteur, $mois);
        if ($pdf === null) {
            throw $this->createNotFoundException(
                'Pas de fiche de frais pour ce visiteur ce mois');
        }
        $nom = $this->get('pg_gsb_frais.fiche_pdf')->nomFichier($idVisiteur, $mois);

        // Le PDF est déjà en mémoire : une Response suffit, inutile de passer
        // par un fichier temporaire comme pour les justificatifs.
        $reponse = new Response($pdf);
        $reponse->headers->set('Content-Type', 'application/pdf');
        $reponse->headers->set('Content-Disposition',
            $reponse->headers->makeDisposition(
                ResponseHeaderBag::DISPOSITION_INLINE, $nom));

        return $reponse;
    }

    /**
     * Génère en un clic toutes les fiches clôturées d'un mois, dans une archive.
     *
     * Réservé au comptable : estComptable() renvoie faux tant qu'aucun compte
     * comptable n'existe (phase 5). En attendant, la même fonctionnalité est
     * accessible par la commande console gsb:imprimer-fiches, qui appelle
     * exactement le même service.
     */
    public function imprimerMoisAction(Request $request, $mois = null)
    {
        if ($mois === null) {
            // « toutes les fiches de frais clôturées du mois précédent »
            $mois = moisPrecedent(getMois(date('d/m/Y')));
        }
        $session = $request->getSession();
        if (!estConnecte($session)) {
            return $this->redirect($this->generateUrl('pg_gsb_frais_home'));
        }
        if (!estComptable($session)) {
            // Réponse 403 construite à la main, et non createAccessDeniedException() :
            // cette dernière est interceptée par le composant Security de Symfony,
            // qui — faute de pare-feu configuré dans ce projet — la transforme en
            // 401 « authentifiez-vous ». Or l'utilisateur EST authentifié ; il lui
            // manque seulement le rôle. 403 est le code juste.
            // À la différence des justificatifs, on ne masque rien ici en 404 :
            // l'existence d'un export mensuel n'a rien de confidentiel.
            return new Response(
                'Seul le service comptable peut imprimer toutes les fiches',
                Response::HTTP_FORBIDDEN);
        }

        // L'archive peut peser plusieurs dizaines de mégaoctets : on l'écrit
        // dans un fichier temporaire et on la sert en flux, plutôt que de la
        // charger entièrement en mémoire pour la renvoyer.
        // tempnam() CRÉE le fichier et renvoie son chemin. Lui accoler « .zip »
        // désignerait un fichier qui n'existe pas : l'archive serait écrite là,
        // le fichier créé par tempnam resterait orphelin dans /tmp, et le
        // unlink() de la ligne d'échec porterait sur un chemin inexistant.
        // On garde donc le chemin tel quel ; l'extension vue par l'utilisateur
        // vient de l'en-tête Content-Disposition, plus bas.
        $cheminZip = tempnam(sys_get_temp_dir(), 'gsb_fiches_');
        $nbFiches = $this->get('pg_gsb_frais.fiche_pdf')
            ->genererArchiveMois($mois, 'CL', $cheminZip);
        if ($nbFiches == 0) {
            if (is_file($cheminZip)) {
                unlink($cheminZip);
            }
            throw $this->createNotFoundException(
                'Aucune fiche clôturée pour le mois '.$mois);
        }

        $reponse = new BinaryFileResponse($cheminZip);
        $reponse->headers->set('Content-Type', 'application/zip');
        $reponse->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            'fiches_'.$mois.'.zip');
        // Le fichier temporaire est effacé une fois la réponse envoyée.
        $reponse->deleteFileAfterSend(true);

        return $reponse;
    }
}
