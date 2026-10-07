<?php
namespace Pg\GsbFraisBundle\Command;

class CloturerFichesCommand extends CycleEtatCommand
{
    protected function configure()
    {
        $this->setName('gsb:cloturer-fiches')
             ->setDescription('Clôture les fiches encore en saisie pour un mois donné');
        $this->configurerMois();
    }

    protected function getEtatDepart()
    {
        return 'CR';
    }

    protected function getEtatArrivee()
    {
        return 'CL';
    }

    protected function getParticipe()
    {
        return 'clôturée(s)';
    }
}
