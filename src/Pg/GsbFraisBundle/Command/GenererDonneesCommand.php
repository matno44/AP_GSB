<?php
namespace Pg\GsbFraisBundle\Command;

require_once __DIR__ . '/../Controller/include/fct.inc.php';

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class GenererDonneesCommand extends ContainerAwareCommand
{
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
        $mois        = $input->getOption('mois');
        if ($mois === null) {
            $mois = moisPrecedent(getMois(date('d/m/Y')));
        }
        if (!preg_match('/^[0-9]{6}$/', $mois) || $nbVisiteurs < 1) {
            $output->writeln('<error>Paramètres invalides</error>');

            return 1;
        }
        $etat = $input->getOption('etat');

        $pdo     = $this->getContainer()->get('pg_gsb_frais.pdo');
        $debut   = microtime(true);
        $nbCrees = $pdo->genererVisiteursDeTest(
            self::PREFIXE_TEST, $nbVisiteurs, $mois, $etat,
            self::$lesNoms, self::$lesPrenoms, self::$lesLibellesHorsForfait
        );

        $output->writeln(sprintf(
                             "<info>%d visiteur(s) de test</info> et leur fiche %s à l'état %s, en %.1f s",
                             $nbCrees, $mois, $etat, microtime(true) - $debut
                         ));
        $output->writeln(sprintf(
                             'Identifiants créés : préfixe « %s ». Pour les retirer, DANS CET ORDRE :',
                             self::PREFIXE_TEST
                         ));

        $output->writeln(sprintf(
                             "  <comment>DELETE FROM fichefrais WHERE idVisiteur LIKE '%s%%';</comment>"
                                 . ' (les lignes de frais suivent par cascade)', self::PREFIXE_TEST
                         ));
        $output->writeln(sprintf(
                             "  <comment>DELETE FROM visiteur   WHERE id LIKE '%s%%';</comment>",
                             self::PREFIXE_TEST
                         ));

        return 0;
    }
}
