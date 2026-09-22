<?php
namespace Pg\GsbFraisBundle\Command;

/**
 * Marque comme remboursées les fiches mises en paiement d'un mois.
 *
 * Dernière transition du diagramme du cahier des charges, déclenchée par
 * l'événement « [Paiement effectué] ». Cet événement se produit hors du
 * système — c'est la banque qui confirme le virement — d'où une commande
 * lancée par le service comptable une fois la confirmation reçue.
 */
class RembourserFichesCommand extends CycleEtatCommand
{
    protected function configure()
    {
        $this->setName('gsb:rembourser-fiches')
            ->setDescription('Passe les fiches mises en paiement d\'un mois en « Remboursée »');
        $this->configurerMois();
    }

    protected function getEtatDepart()  { return 'MP'; }
    protected function getEtatArrivee() { return 'RB'; }
    protected function getParticipe()   { return 'remboursée(s)'; }
}
