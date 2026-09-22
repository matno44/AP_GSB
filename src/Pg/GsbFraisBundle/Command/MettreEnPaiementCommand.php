<?php
namespace Pg\GsbFraisBundle\Command;

/**
 * Met en paiement les fiches validées d'un mois.
 *
 * « La mise en paiement est faite au 20 du mois suivant la saisie par les
 * visiteurs. » C'est un traitement par lot, déclenché par une date, et non une
 * action fiche par fiche : d'où une commande et non un bouton.
 */
class MettreEnPaiementCommand extends CycleEtatCommand
{
    protected function configure()
    {
        $this->setName('gsb:mettre-en-paiement')
            ->setDescription('Passe les fiches validées d\'un mois en « Mise en paiement »');
        $this->configurerMois();
    }

    protected function getEtatDepart()  { return 'VA'; }
    protected function getEtatArrivee() { return 'MP'; }
    protected function getParticipe()   { return 'mise(s) en paiement'; }
}
