<?php
namespace Pg\GsbFraisBundle\Command;

require_once __DIR__.'/../Controller/include/fct.inc.php';

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Remplit la base de visiteurs et de fiches de frais aléatoires.
 *
 * Le cahier des charges présente ce script comme livré par le prédécesseur ;
 * il est absent de l'archive (point B7 de l'audit). Il est écrit ici parce que
 * la phase 3 demande de traiter la volumétrie : sans jeu de données
 * volumineux, l'affirmation « la mémoire ne croît pas avec le nombre de
 * fiches » resterait une opinion.
 *
 * La commande n'écrase rien : elle AJOUTE des visiteurs dont l'identifiant
 * commence par « z », espace réservé aux données de test. Les 9 visiteurs
 * d'origine ne sont jamais touchés.
 */
class GenererDonneesCommand extends ContainerAwareCommand
{
    /** Préfixe des identifiants de test, pour pouvoir les distinguer. */
    const PREFIXE_TEST = 'z';

    private static $lesNoms = array('Martin', 'Bernard', 'Dubois', 'Thomas',
        'Robert', 'Petit', 'Durand', 'Leroy', 'Moreau', 'Simon', 'Laurent',
        'Lefebvre', 'Michel', 'Garcia', 'David', 'Bertrand', 'Roux', 'Vincent');

    private static $lesPrenoms = array('Camille', 'Dominique', 'Claude',
        'Sacha', 'Charlie', 'Alix', 'Maxime', 'Noa', 'Elie', 'Yann');

    private static $lesLibellesHorsForfait = array('Péage autoroute',
        'Parking congrès', 'Repas avec spécialiste', 'Achat documentation',
        'Location salle de réunion', 'Taxi gare', 'Invitation confrère',
        'Fournitures de bureau');

    protected function configure()
    {
        $this
            ->setName('gsb:generer-donnees')
            ->setDescription('Ajoute des visiteurs et des fiches de frais de test')
            ->addOption('visiteurs', null, InputOption::VALUE_REQUIRED,
                'Nombre de visiteurs de test à créer', 50)
            ->addOption('mois', null, InputOption::VALUE_REQUIRED,
                'Mois des fiches créées, au format aaaamm')
            ->addOption('etat', null, InputOption::VALUE_REQUIRED,
                'État des fiches créées', 'CL');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $nbVisiteurs = (int) $input->getOption('visiteurs');
        $mois = $input->getOption('mois');
        if ($mois === null) {
            $mois = moisPrecedent(getMois(date('d/m/Y')));
        }
        if (!preg_match('/^[0-9]{6}$/', $mois) || $nbVisiteurs < 1) {
            $output->writeln('<error>Paramètres invalides</error>');

            return 1;
        }
        $etat = $input->getOption('etat');

        $pdo = $this->getContainer()->get('pg_gsb_frais.pdo');
        $debut = microtime(true);
        $nbCrees = $pdo->genererVisiteursDeTest(
            self::PREFIXE_TEST, $nbVisiteurs, $mois, $etat,
            self::$lesNoms, self::$lesPrenoms, self::$lesLibellesHorsForfait);

        $output->writeln(sprintf(
            '<info>%d visiteur(s) de test</info> et leur fiche %s à l\'état %s, en %.1f s',
            $nbCrees, $mois, $etat, microtime(true) - $debut));
        $output->writeln(sprintf(
            'Identifiants créés : préfixe « %s ». Pour les retirer, DANS CET ORDRE :',
            self::PREFIXE_TEST));
        // fichefrais référence visiteur SANS ON DELETE CASCADE : supprimer
        // d'abord le visiteur échouerait sur la contrainte de clé étrangère.
        // En revanche les lignes de frais, elles, suivent bien la fiche.
        $output->writeln(sprintf(
            "  <comment>DELETE FROM fichefrais WHERE idVisiteur LIKE '%s%%';</comment>"
            .' (les lignes de frais suivent par cascade)', self::PREFIXE_TEST));
        $output->writeln(sprintf(
            "  <comment>DELETE FROM visiteur   WHERE id LIKE '%s%%';</comment>",
            self::PREFIXE_TEST));

        return 0;
    }
}
