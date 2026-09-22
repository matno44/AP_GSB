<?php
namespace Pg\GsbFraisBundle\services;

use Dompdf\Dompdf;
use Dompdf\Options;

// moisEnLettres() vient des fonctions utilitaires du prédécesseur : on les
// charge explicitement plutôt que de compter sur un require déjà fait ailleurs.
require_once __DIR__.'/../Controller/include/fct.inc.php';

/**
 * Production des fiches de remboursement au format PDF (annexe 1).
 *
 * Le service produit UNE fiche à la fois et renvoie son contenu binaire.
 * C'est ce découpage qui permet la génération de masse sans saturer la
 * mémoire : l'appelant traite les fiches une par une et n'en conserve jamais
 * plus d'une en mémoire. Voir COMPTE-RENDU.md, § volumétrie.
 */
class GenerateurFichePdf
{
    private $pdo;
    private $moteurTwig;
    private $cheminLogo;

    public function __construct($pdo, $moteurTwig, $cheminLogo)
    {
        $this->pdo = $pdo;
        $this->moteurTwig = $moteurTwig;
        $this->cheminLogo = $cheminLogo;
    }

    /**
     * Construit le PDF d'une fiche de frais.
     *
     * @param  $idVisiteur
     * @param  $mois sous la forme aaaamm
     * @return le contenu binaire du PDF, ou null si la fiche n'existe pas
     */
    public function genererFiche($idVisiteur, $mois)
    {
        $donnees = $this->collecterDonnees($idVisiteur, $mois);
        if ($donnees === null) {
            return null;
        }
        $html = $this->moteurTwig->render(
            'PgGsbFraisBundle:FichePdf:fiche.html.twig', $donnees);

        $options = new Options();
        // DejaVu est la police livrée avec dompdf ; elle contient les
        // caractères accentués. Sans cela, « Nuitée » ou « Août » sortiraient
        // avec des caractères manquants.
        $options->set('defaultFont', 'DejaVu Serif');
        // Le gabarit n'inclut aucune ressource distante : on coupe l'accès
        // réseau de dompdf. Cela le rend plus rapide, et surtout cela évite
        // qu'une balise <img src="http://..."> glissée dans un libellé de
        // frais fasse émettre une requête par le serveur.
        $options->set('isRemoteEnabled', false);
        // chroot délimite les dossiers dans lesquels dompdf a le droit de lire
        // un fichier local (ici, le logo). C'est une barrière de sécurité :
        // sans elle, dompdf accepterait un chemin arbitraire du disque.
        if ($this->cheminLogo !== null) {
            $options->set('chroot', dirname($this->cheminLogo));
        }
        $dompdf = new Dompdf($options);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->render();

        return $dompdf->output();
    }

    /**
     * Construit une archive ZIP contenant une fiche PDF par visiteur.
     *
     * VOLUMÉTRIE — c'est le point sensible de cette phase.
     * Les fiches sont traitées UNE PAR UNE : on produit le PDF, on l'écrit
     * aussitôt dans un fichier temporaire, on libère la mémoire, puis on passe
     * à la suivante. À aucun moment la mémoire ne contient plus d'un PDF.
     * Le coût mémoire est donc celui de la plus grosse fiche, pas celui du
     * mois entier : 20 fiches ou 500 fiches consomment autant.
     *
     * Les deux pièges évités :
     * - charger toutes les fiches en mémoire avant de les écrire ;
     * - construire un unique PDF multi-pages, que la bibliothèque assemble
     *   intégralement en mémoire avant de l'écrire sur le disque.
     *
     * @param  $mois sous la forme aaaamm
     * @param  $etat l'état des fiches à imprimer, par exemple 'CL'
     * @param  $cheminZip chemin du fichier ZIP à produire
     * @return le nombre de fiches écrites dans l'archive
     */
    public function genererArchiveMois($mois, $etat, $cheminZip)
    {
        $lesFiches = $this->pdo->getLesFichesDuMois($mois, $etat);
        if (count($lesFiches) == 0) {
            return 0;
        }
        $dossierTemp = sys_get_temp_dir().'/gsb_fiches_'.getmypid();
        if (!is_dir($dossierTemp)) {
            mkdir($dossierTemp, 0700, true);
        }
        $zip = new \ZipArchive();
        if ($zip->open($cheminZip, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException("Impossible de créer l'archive ".$cheminZip);
        }

        $lesTemporaires = array();
        $nbEcrites = 0;
        foreach ($lesFiches as $uneFiche) {
            $pdf = $this->genererFiche($uneFiche['idVisiteur'], $mois);
            if ($pdf === null) {
                continue;
            }
            $nom = $this->nomFichier($uneFiche['idVisiteur'], $mois);
            $chemin = $dossierTemp.'/'.$nom;
            file_put_contents($chemin, $pdf);
            // Libération explicite : sans cela, la variable resterait occupée
            // jusqu'au tour suivant, et deux PDF cohabiteraient en mémoire.
            unset($pdf);
            $zip->addFile($chemin, $nom);
            $lesTemporaires[] = $chemin;
            ++$nbEcrites;
        }
        // ZipArchive ne lit réellement les fichiers qu'au close() : les
        // temporaires doivent exister jusque-là, d'où leur suppression après.
        $zip->close();
        foreach ($lesTemporaires as $unTemporaire) {
            unlink($unTemporaire);
        }
        rmdir($dossierTemp);

        return $nbEcrites;
    }

    /**
     * Nom de fichier proposé pour une fiche.
     *
     * @param  $idVisiteur
     * @param  $mois sous la forme aaaamm
     * @return par exemple « fiche_a17_202608.pdf »
     */
    public function nomFichier($idVisiteur, $mois)
    {
        return 'fiche_'.$idVisiteur.'_'.$mois.'.pdf';
    }

    /**
     * Rassemble tout ce que le gabarit doit afficher.
     *
     * Trois requêtes exactement par fiche : l'identité du visiteur, les frais
     * forfaitisés, les frais hors forfait. Ce nombre ne dépend pas du nombre
     * de lignes de frais.
     *
     * @param  $idVisiteur
     * @param  $mois
     * @return un tableau de variables pour le gabarit, ou null
     */
    private function collecterDonnees($idVisiteur, $mois)
    {
        $visiteur = $this->pdo->getVisiteur($idVisiteur);
        if (!is_array($visiteur)) {
            return null;
        }
        $lesFraisForfait = $this->pdo->getLesFraisForfait($idVisiteur, $mois);
        $lesFraisHorsForfait = $this->pdo->getLesFraisHorsForfait($idVisiteur, $mois);
        // Une fiche sans aucune ligne forfaitisée n'existe pas : les quatre
        // lignes sont créées en même temps que la fiche. Un tableau vide
        // signifie donc « pas de fiche pour ce visiteur ce mois-là ».
        if (count($lesFraisForfait) == 0) {
            return null;
        }

        return array(
            'visiteur' => $visiteur,
            'lesfraisforfait' => $lesFraisForfait,
            'lesfraishorsforfait' => $lesFraisHorsForfait,
            'total' => $this->calculerTotal($lesFraisForfait, $lesFraisHorsForfait),
            'moislettres' => moisEnLettres($mois),
            'nummois' => substr($mois, 4, 2),
            'numannee' => substr($mois, 0, 4),
            'datedujour' => date('d/m/Y'),
            'logo' => $this->cheminLogo,
        );
    }

    /**
     * Calcule le total général de la fiche.
     *
     * @param  $lesFraisForfait lignes forfaitisées, avec leur total déjà calculé
     * @param  $lesFraisHorsForfait lignes hors forfait
     * @return le total, en nombre à virgule
     */
    private function calculerTotal($lesFraisForfait, $lesFraisHorsForfait)
    {
        // Aucune requête supplémentaire : les deux tableaux sont DÉJÀ chargés
        // pour l'affichage. Faire calculer ce total par SQL coûterait deux
        // requêtes d'agrégation de plus par fiche, pour additionner une
        // quinzaine de nombres déjà présents en mémoire.
        // totalDesLignes() est la même fonction que celle employée par
        // l'écran de validation : un seul endroit où la somme est écrite.
        return totalDesLignes($lesFraisForfait, 'montant')
            + totalDesLignes($lesFraisHorsForfait, 'montant');
    }
}
