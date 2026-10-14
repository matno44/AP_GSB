<?php
namespace Pg\GsbFraisBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Contrôle, et répare au besoin, l'égalité « quantité kilométrique = somme des
 * trajets ».
 *
 * POURQUOI CETTE COMMANDE EXISTE. Depuis la phase 9, la quantité de la ligne
 * kilométrique est une valeur DÉRIVÉE : elle vaut la somme des distances des
 * trajets de la fiche. Mais cet invariant est maintenu par l'APPLICATION -- par
 * PdoGsb::recalculerDistance(), appelée à chaque ajout et à chaque suppression
 * de trajet -- et non par la base. Aucun déclencheur ne le garantit.
 *
 * Il peut donc dériver, et je l'ai constaté en cours de développement : une
 * suppression de trajets faite directement en SQL, hors de l'application, a
 * laissé une fiche à 260 km sans aucun trajet pour les justifier. Le même
 * effet se produirait si l'on restaurait une sauvegarde partielle, ou si une
 * fiche était supprimée -- ON DELETE CASCADE efface alors ses trajets sans que
 * le code PHP intervienne.
 *
 * Plutôt que de signaler ce risque dans un coin du compte rendu et de laisser
 * l'utilisateur sans recours, cette commande permet de le détecter et de le
 * corriger. Sans option, elle ne fait que CONSTATER : c'est le comportement par
 * défaut d'un outil de diagnostic, qui ne doit pas écrire sans qu'on le lui
 * demande.
 */
class VerifierDistancesCommand extends ContainerAwareCommand
{
    protected function configure()
    {
        $this->setName('gsb:verifier-distances')
            ->setDescription(
                'Contrôle que chaque quantité kilométrique égale la somme des trajets de sa fiche')
            ->addOption('reparer', null, InputOption::VALUE_NONE,
                'Recalcule les quantités divergentes au lieu de seulement les signaler');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $pdo         = $this->getContainer()->get('pg_gsb_frais.pdo');
        $lesEcarts   = $pdo->getLesDistancesDivergentes();
        $nb          = count($lesEcarts);

        if ($nb === 0) {
            $output->writeln(
                '<info>Aucun écart.</info> Chaque quantité kilométrique correspond '
                . 'à la somme des trajets de sa fiche.');

            return 0;
        }

        $output->writeln(sprintf('<comment>%d fiche(s) en écart :</comment>', $nb));
        foreach ($lesEcarts as $unEcart) {
            $output->writeln(sprintf(
                '  %s / %s : quantité %d km, trajets %d km (écart %+d)',
                $unEcart['idVisiteur'], $unEcart['mois'],
                $unEcart['quantite'], $unEcart['sommeTrajets'],
                $unEcart['sommeTrajets'] - $unEcart['quantite']));
        }

        if (!$input->getOption('reparer')) {
            $output->writeln('');
            $output->writeln('Relancez avec <info>--reparer</info> pour recalculer ces quantités.');
            // Code de retour 1 : un écart est une anomalie, et une commande de
            // contrôle doit pouvoir être enchaînée dans un script.
            return 1;
        }

        $nbRepares = 0;
        foreach ($lesEcarts as $unEcart) {
            $pdo->recalculerDistance($unEcart['idVisiteur'], $unEcart['mois']);
            $nbRepares++;
        }
        $output->writeln(sprintf('<info>%d quantité(s) recalculée(s).</info>', $nbRepares));

        // On revérifie plutôt que d'annoncer le succès sur la foi du compteur.
        $nbRestants = count($pdo->getLesDistancesDivergentes());
        if ($nbRestants > 0) {
            $output->writeln(sprintf(
                '<error>%d écart(s) subsistent après réparation.</error>', $nbRestants));

            return 1;
        }
        $output->writeln('Plus aucun écart.');

        return 0;
    }
}
