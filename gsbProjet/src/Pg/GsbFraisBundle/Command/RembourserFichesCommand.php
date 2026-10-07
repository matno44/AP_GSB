<?php
namespace Pg\GsbFraisBundle\Command;

class RembourserFichesCommand extends CycleEtatCommand
{
    protected function configure()
    {
        $this->setName('gsb:rembourser-fiches')
             ->setDescription("Passe les fiches mises en paiement d'un mois en « Remboursée »");
        $this->configurerMois();
    }

    protected function getEtatDepart()
    {
        return 'MP';
    }

    protected function getEtatArrivee()
    {
        return 'RB';
    }

    protected function getParticipe()
    {
        return 'remboursée(s)';
    }
}
