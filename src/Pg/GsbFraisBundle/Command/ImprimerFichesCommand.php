<?php
namespace Pg\GsbFraisBundle\Command;

require_once __DIR__ . '/../Controller/include/fct.inc.php';

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ImprimerFichesCommand extends ContainerAwareCommand
{
    protected function configure()
    {
        $this
            ->setName('gsb:imprimer-fiches')
            ->setDescription("Génère une archive ZIP des fiches de frais d'un mois")
            ->addArgument('mois', InputArgument::OPTIONAL,
                          'Mois au format aaaamm. Par défaut : le mois précédent.')
            ->addOption('etat', null, InputOption::VALUE_REQUIRED,
                        'État des fiches à imprimer', 'CL')
            ->addOption('sortie', null, InputOption::VALUE_REQUIRED,
                        'Chemin du fichier ZIP à produire');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $mois = $input->getArgument('mois');
        if ($mois === null) {
            $mois = moisPrecedent(getMois(date('d/m/Y')));
        }
        if (!preg_match('/^[0-9]{6}$/', $mois)) {
            $output->writeln('<error>Mois invalide : attendu aaaamm</error>');

            return 1;
        }
        $etat      = $input->getOption('etat');
        $cheminZip = $input->getOption('sortie');
        if ($cheminZip === null) {
            $cheminZip = getcwd() . '/fiches_' . $mois . '.zip';
        }

        $output->writeln(sprintf(
                             "Fiches du mois <info>%s</info> (%s) à l'état <info>%s</info>",
                             $mois, moisEnLettres($mois), $etat
                         ));

        $debut    = microtime(true);
        $nbFiches = $this->getContainer()
                         ->get('pg_gsb_frais.fiche_pdf')
                         ->genererArchiveMois($mois, $etat, $cheminZip);

        if ($nbFiches == 0) {
            $output->writeln('<comment>Aucune fiche à imprimer pour ce mois.</comment>');

            return 0;
        }
        $output->writeln(sprintf(
                             '<info>%d fiche(s)</info> écrites dans %s (%s Ko) en %.1f s',
                             $nbFiches, $cheminZip, number_format(filesize($cheminZip) / 1024, 1),
                             microtime(true) - $debut
                         ));

        $output->writeln(sprintf('Pic de mémoire : %.1f Mo',
                                 memory_get_peak_usage(true) / (1024 * 1024)));

        return 0;
    }
}
