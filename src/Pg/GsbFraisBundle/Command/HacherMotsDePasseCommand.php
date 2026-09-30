<?php
namespace Pg\GsbFraisBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Remplace les mots de passe visiteur en clair par leur empreinte bcrypt.
 *
 * Deuxième moitié, obligatoire, de la migration 004. Le script SQL élargit
 * visiteur.mdp de VARCHAR(30) à VARCHAR(255) pour qu'il puisse recevoir une
 * empreinte, mais il ne peut pas la produire : bcrypt n'existe pas en SQL.
 * MySQL n'offre que SHA2() et MD5(), inutilisables pour un mot de passe -- un
 * condensat sans grain de sel ni coût se casse par dictionnaire.
 *
 * Aucun visiteur ne perd son mot de passe : la base livrée les stocke EN CLAIR,
 * on les hache donc tels quels. C'est la seule et unique fois où cette
 * information est disponible, et c'est précisément ce qui rend la migration
 * indolore.
 *
 * La commande est IDEMPOTENTE : elle ne traite que les lignes dont la valeur ne
 * commence pas par $2 -- le préfixe de toute empreinte bcrypt. La relancer ne
 * re-hache donc pas une empreinte déjà produite, ce qui rendrait le mot de
 * passe définitivement inutilisable.
 *
 * Aucun mot de passe n'est affiché ni journalisé : seuls les matricules et des
 * compteurs sortent de cette commande.
 */
class HacherMotsDePasseCommand extends ContainerAwareCommand
{
    protected function configure()
    {
        $this->setName('gsb:hacher-mots-de-passe')
            ->setDescription(
                'Convertit en empreintes bcrypt les mots de passe visiteur encore en clair')
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
                '<comment>Aucun mot de passe en clair : rien à faire.</comment>');
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
            // Le mot de passe en clair ne sort pas de cette boucle : ni affiché,
            // ni concaténé dans un message, ni journalisé.
            $empreinte = password_hash($uneLigne['mdp'], PASSWORD_BCRYPT);

            // password_hash() renvoie false si l'algorithme n'est pas disponible.
            // Écrire ce false écraserait le mot de passe par une chaîne vide et
            // condamnerait le compte : mieux vaut sauter la ligne et le dire.
            if ($empreinte === false || $empreinte === null) {
                $output->writeln(sprintf(
                    '<error>%s : password_hash() a échoué, ligne laissée intacte.</error>', $id));
                $nbEchecs++;
                continue;
            }
            // Vérification avant écriture : si l'empreinte produite ne valide pas
            // le mot de passe d'origine, la convertir rendrait le compte
            // inaccessible. Ce contrôle ne devrait jamais échouer -- c'est
            // justement pour cela qu'il doit être là.
            if (!password_verify($uneLigne['mdp'], $empreinte)) {
                $output->writeln(sprintf(
                    '<error>%s : empreinte incohérente, ligne laissée intacte.</error>', $id));
                $nbEchecs++;
                continue;
            }

            if (!$simuler) {
                // mdpDoitChanger reste à 0 : le visiteur garde SON mot de passe,
                // que personne d'autre n'a choisi pour lui. Rien ne justifie de
                // lui imposer un changement, et le faire pour 209 comptes d'un
                // coup transformerait une migration invisible en incident.
                $pdo->majEmpreinteMotDePasse('visiteur', $id, $empreinte, 0);
            }
            $nbTraites++;
        }

        if ($simuler) {
            $output->writeln(sprintf(
                '<info>%d mot(s) de passe seraient convertis</info>, %d en échec.',
                $nbTraites, $nbEchecs));

            return $nbEchecs === 0 ? 0 : 1;
        }

        $output->writeln(sprintf('<info>%d mot(s) de passe converti(s)</info> en empreinte bcrypt.',
            $nbTraites));
        if ($nbEchecs > 0) {
            $output->writeln(sprintf(
                '<error>%d ligne(s) en échec : ces comptes ont encore un mot de passe en clair.</error>',
                $nbEchecs));

            return 1;
        }

        $nbRestants = count($pdo->getLesMotsDePasseEnClair());
        if ($nbRestants > 0) {
            $output->writeln(sprintf(
                '<error>%d mot(s) de passe en clair subsistent après traitement.</error>',
                $nbRestants));

            return 1;
        }
        $output->writeln('Plus aucun mot de passe en clair en base.');

        return 0;
    }
}
