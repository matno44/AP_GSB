<?php
namespace Pg\GsbFraisBundle\Command;

require_once __DIR__ . '/../Controller/include/fct.inc.php';

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

abstract class CycleEtatCommand extends ContainerAwareCommand
{
    abstract protected function getEtatDepart();

    abstract protected function getEtatArrivee();

    abstract protected function getParticipe();

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

        $nbFiches = $this->getContainer()
                         ->get('pg_gsb_frais.pdo')
                         ->majEtatFichesDuMois($mois, $this->getEtatDepart(), $this->getEtatArrivee());

        if ($nbFiches == 0) {
            $output->writeln(sprintf(
                                 "<comment>Aucune fiche à l'état %s pour le mois %s (%s).</comment>",
                                 $this->getEtatDepart(), $mois, moisEnLettres($mois)
                             ));

            return 0;
        }
        $output->writeln(sprintf(
                             '<info>%d fiche(s)</info> du mois %s (%s) : %s (%s -> %s).',
                             $nbFiches, $mois, moisEnLettres($mois), $this->getParticipe(),
                             $this->getEtatDepart(), $this->getEtatArrivee()
                         ));

        return 0;
    }

    protected function configurerMois()
    {
        $this->addArgument('mois', InputArgument::OPTIONAL,
                           'Mois au format aaaamm. Par défaut : le mois précédent.');
    }
}
