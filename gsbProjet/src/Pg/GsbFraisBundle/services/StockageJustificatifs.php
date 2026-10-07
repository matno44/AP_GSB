<?php
namespace Pg\GsbFraisBundle\services;

use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Stockage des justificatifs de frais hors forfait.
 *
 * Toute la manipulation de fichiers est regroupée ici : les contrôleurs ne
 * touchent jamais directement au disque. Cela évite d'avoir la règle « quelles
 * extensions sont autorisées » recopiée à plusieurs endroits, et cela permet
 * de vérifier une seule fois que le chemin construit reste bien dans le
 * dossier prévu.
 *
 * Le dossier de stockage est situé HORS de web/ : aucun justificatif n'est
 * donc accessible par une URL directe, même en connaissant son nom. Le seul
 * accès possible passe par JustificatifController, qui contrôle que le
 * demandeur a le droit de voir le fichier.
 */
class StockageJustificatifs
{
    /**
     * Extensions acceptées, associées au type MIME réel attendu.
     *
     * On ne se fie pas à l'extension fournie par le navigateur : un fichier
     * « facture.pdf » peut très bien être un script. getMimeType() analyse le
     * CONTENU du fichier (via l'extension PHP fileinfo). Les deux doivent
     * concorder pour que le fichier soit accepté.
     */
    private static $typesAutorises = array(
        'pdf'  => array('application/pdf'),
        'jpg'  => array('image/jpeg'),
        'jpeg' => array('image/jpeg'),
        'png'  => array('image/png'),
    );

    private $dossier;
    private $tailleMax;

    public function __construct($dossier, $tailleMax)
    {
        $this->dossier = rtrim($dossier, '/');
        $this->tailleMax = (int) $tailleMax;
    }

    /**
     * Contrôle un fichier reçu et retourne la liste des erreurs.
     *
     * @param  $fichier UploadedFile ou null
     * @return un tableau de messages, vide si le fichier est acceptable
     */
    public function valider($fichier)
    {
        $lesErreurs = array();
        if (!($fichier instanceof UploadedFile)) {
            $lesErreurs[] = "Aucun fichier n'a été reçu";

            return $lesErreurs;
        }
        if (!$fichier->isValid()) {
            // Cas le plus fréquent : le fichier dépasse upload_max_filesize de
            // php.ini et PHP l'a écarté avant même d'arriver ici.
            $lesErreurs[] = "Le transfert du fichier a échoué : "
                .$fichier->getErrorMessage();

            return $lesErreurs;
        }
        if ($fichier->getSize() > $this->tailleMax) {
            $lesErreurs[] = "Le fichier dépasse la taille maximale autorisée ("
                .$this->getTailleMaxEnMo()." Mo)";
        }

        $extension = strtolower($fichier->getClientOriginalExtension());
        if (!isset(self::$typesAutorises[$extension])) {
            $lesErreurs[] = "Extension non autorisée : seuls les fichiers "
                .$this->getExtensionsAutorisees()." sont acceptés";

            return $lesErreurs;
        }
        // Le contenu réel doit correspondre à l'extension annoncée.
        if (!in_array($fichier->getMimeType(), self::$typesAutorises[$extension])) {
            $lesErreurs[] = "Le contenu du fichier ne correspond pas à son "
                ."extension .".$extension;
        }

        return $lesErreurs;
    }

    /**
     * Enregistre le fichier sur le disque sous un nom généré.
     *
     * Le nom est tiré de random_bytes() : 16 octets, soit 32 caractères
     * hexadécimaux. Il est imprévisible, ce qui interdit de deviner l'adresse
     * du justificatif d'un autre visiteur. Le nom d'origine, lui, n'est jamais
     * utilisé pour construire un chemin : c'est ce qui coupe court aux
     * tentatives du type « ../../app/config/parameters.yml ».
     *
     * @param  $fichier UploadedFile déjà validé
     * @return le nom du fichier créé, à enregistrer en base
     */
    public function enregistrer(UploadedFile $fichier)
    {
        if (!is_dir($this->dossier)) {
            mkdir($this->dossier, 0755, true);
        }
        $extension = strtolower($fichier->getClientOriginalExtension());
        $nomFichier = bin2hex(random_bytes(16)).'.'.$extension;
        // move() lève une exception en cas d'échec : on ne risque pas
        // d'enregistrer en base le nom d'un fichier qui n'existe pas.
        $fichier->move($this->dossier, $nomFichier);

        return $nomFichier;
    }

    /**
     * Retourne le chemin absolu d'un justificatif stocké.
     *
     * basename() est une sécurité de dernier rideau : même si un nom
     * fantaisiste arrivait jusqu'ici, il ne pourrait pas faire sortir le
     * chemin du dossier de stockage.
     *
     * @param  $nomFichier
     * @return le chemin absolu, ou null si le fichier n'existe pas
     */
    public function chemin($nomFichier)
    {
        if ($nomFichier === null || $nomFichier === '') {
            return null;
        }
        $chemin = $this->dossier.'/'.basename($nomFichier);

        return is_file($chemin) ? $chemin : null;
    }

    /**
     * Supprime un justificatif du disque.
     *
     * Appelée quand le visiteur remplace son justificatif ou supprime la
     * ligne de frais : sans cela, les fichiers orphelins s'accumuleraient.
     *
     * @param $nomFichier
     */
    public function supprimer($nomFichier)
    {
        $chemin = $this->chemin($nomFichier);
        if ($chemin !== null) {
            unlink($chemin);
        }
    }

    /**
     * Assainit le nom de fichier affiché à l'écran.
     *
     * On ne garde que des caractères inoffensifs et on tronque : ce nom est
     * réaffiché dans la page et proposé au téléchargement.
     *
     * @param  $nom le nom d'origine fourni par le navigateur
     * @return un nom assaini
     */
    public function assainirNom($nom)
    {
        $nom = basename($nom);
        $nom = preg_replace('/[^A-Za-z0-9._-]/', '_', $nom);

        return substr($nom, 0, 255);
    }

    public function getTailleMax()
    {
        return $this->tailleMax;
    }

    public function getTailleMaxEnMo()
    {
        return round($this->tailleMax / (1024 * 1024), 1);
    }

    /**
     * @return les extensions autorisées, pour les messages et le formulaire
     */
    public function getExtensionsAutorisees()
    {
        return implode(', ', array_keys(self::$typesAutorises));
    }
}
