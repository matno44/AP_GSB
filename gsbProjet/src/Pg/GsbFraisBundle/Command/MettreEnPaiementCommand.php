<?php
namespace Pg\GsbFraisBundle\Command;

class MettreEnPaiementCommand extends CycleEtatCommand
{
    protected function configure()
    {
        $this->setName('gsb:mettre-en-paiement')
             ->setDescription("Passe les fiches validées d'un mois en « Mise en paiement »");
        $this->configurerMois();
    }

    protected function getEtatDepart()
    {
        return 'VA';
    }

    protected function getEtatArrivee()
    {
        return 'MP';
    }

    protected function getParticipe()
    {
        return 'mise(s) en paiement';
    }
}
