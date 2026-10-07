<?php
namespace Pg\GsbFraisBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class HacherMotsDePasseCommand extends ContainerAwareCommand
{
    protected function configure()
    {
        $this->setName('gsb:hacher-mots-de-passe')
             ->setDescription(
                 'Convertit en empreintes bcrypt les mots de passe visiteur encore en clair'
             )
             ->addOption('simuler', null, InputOption::VALUE_NONE,
                         'Affiche ce qui serait fait, sans rien écrire en base');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $simuler = $input->getOption('simuler');
        $pdo     = $this->getContainer()->get('pg_gsb_frais.pdo');
        $lesMdp  = $pdo->getLesMotsDePasseEnClair();
        $nb      = count($lesMdp);

        if ($nb === 0) {
            $output->writeln(
                '<comment>Aucun mot de passe en clair : rien à faire.</comment>'
            );
            $output->writeln('La migration des mots de passe est déjà complète.');

            return 0;
        }

        $output->writeln(sprintf('%d mot(s) de passe en clair à convertir.', $nb));
        if ($simuler) {
            $output->writeln('<comment>Mode simulation : aucune écriture en base.</comment>');
        }

        $nbTraites = 0;
        $nbEchecs  = 0;
        foreach ($lesMdp as $uneLigne) {
            $id = $uneLigne['id'];

            $empreinte = password_hash($uneLigne['mdp'], PASSWORD_BCRYPT);

            if ($empreinte === false || $empreinte === null) {
                $output->writeln(sprintf(
                                     '<error>%s : password_hash() a échoué, ligne laissée intacte.</error>', $id
                                 ));
                $nbEchecs++;
                continue;
            }

            if (!password_verify($uneLigne['mdp'], $empreinte)) {
                $output->writeln(sprintf(
                                     '<error>%s : empreinte incohérente, ligne laissée intacte.</error>', $id
                                 ));
                $nbEchecs++;
                continue;
            }

            if (!$simuler) {
                $pdo->majEmpreinteMotDePasse('visiteur', $id, $empreinte, 0);
            }
            $nbTraites++;
        }

        if ($simuler) {
            $output->writeln(sprintf(
                                 '<info>%d mot(s) de passe seraient convertis</info>, %d en échec.',
                                 $nbTraites, $nbEchecs
                             ));

            return $nbEchecs === 0 ? 0 : 1;
        }

        $output->writeln(sprintf('<info>%d mot(s) de passe converti(s)</info> en empreinte bcrypt.',
                                 $nbTraites));
        if ($nbEchecs > 0) {
            $output->writeln(sprintf(
                                 '<error>%d ligne(s) en échec : ces comptes ont encore un mot de passe en clair.</error>',
                                 $nbEchecs
                             ));

            return 1;
        }

        $nbRestants = count($pdo->getLesMotsDePasseEnClair());
        if ($nbRestants > 0) {
            $output->writeln(sprintf(
                                 '<error>%d mot(s) de passe en clair subsistent après traitement.</error>',
                                 $nbRestants
                             ));

            return 1;
        }
        $output->writeln('Plus aucun mot de passe en clair en base.');

        return 0;
    }
}
