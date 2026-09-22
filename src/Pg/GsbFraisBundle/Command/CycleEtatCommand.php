<?php
namespace Pg\GsbFraisBundle\Command;

require_once __DIR__.'/../Controller/include/fct.inc.php';

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Socle commun aux trois traitements par lot du cycle de vie d'une fiche.
 *
 * Le cahier des charges décrit trois événements de gestion qui font avancer
 * toutes les fiches d'un mois d'un état au suivant :
 *   - la clôture, au début de la campagne de validation ;
 *   - la mise en paiement, le 20 du mois suivant ;
 *   - le remboursement, une fois le virement effectué.
 *
 * Ces trois commandes ne diffèrent que par leur nom, leur état de départ et
 * leur état d'arrivée. Le traitement lui-même est écrit une seule fois, ici :
 * trois copies du même code auraient triplé le risque d'erreur.
 *
 * Chaque commande porte le nom de l'événement de gestion, et non un nom
 * technique du genre « changer-etat » : on ne peut pas se tromper de
 * transition en tapant la commande.
 */
abstract class CycleEtatCommand extends ContainerAwareCommand
{
    /** @return string l'identifiant d'état que doivent avoir les fiches visées */
    abstract protected function getEtatDepart();

    /** @return string l'identifiant d'état vers lequel les faire passer */
    abstract protected function getEtatArrivee();

    /** @return string le verbe employé dans les messages, au participe passé */
    abstract protected function getParticipe();

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $mois = $input->getArgument('mois');
        if ($mois === null) {
            // Ces traitements portent toujours sur le mois écoulé.
            $mois = moisPrecedent(getMois(date('d/m/Y')));
        }
        if (!preg_match('/^[0-9]{6}$/', $mois)) {
            $output->writeln('<error>Mois invalide : attendu aaaamm</error>');

            return 1;
        }

        $nbFiches = $this->getContainer()->get('pg_gsb_frais.pdo')
            ->majEtatFichesDuMois($mois, $this->getEtatDepart(), $this->getEtatArrivee());

        if ($nbFiches == 0) {
            // Ce n'est pas une erreur : relancer un traitement déjà passé ne
            // doit rien casser et doit le dire clairement.
            $output->writeln(sprintf(
                '<comment>Aucune fiche à l\'état %s pour le mois %s (%s).</comment>',
                $this->getEtatDepart(), $mois, moisEnLettres($mois)));

            return 0;
        }
        $output->writeln(sprintf(
            '<info>%d fiche(s)</info> du mois %s (%s) : %s (%s -> %s).',
            $nbFiches, $mois, moisEnLettres($mois), $this->getParticipe(),
            $this->getEtatDepart(), $this->getEtatArrivee()));

        return 0;
    }

    /**
     * Déclaration commune de l'argument « mois », facultatif.
     */
    protected function configurerMois()
    {
        $this->addArgument('mois', InputArgument::OPTIONAL,
            'Mois au format aaaamm. Par défaut : le mois précédent.');
    }
}
