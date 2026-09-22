<?php
namespace Pg\GsbFraisBundle\Command;

/**
 * Clôture toutes les fiches non clôturées d'un mois.
 *
 * Deuxième modalité de clôture du cahier des charges : « Au début de la
 * campagne de validation des fiches par le service comptable, un script est
 * lancé qui clôture toutes les fiches non clôturées du mois qui va être
 * traité. »
 *
 * La première modalité — la fiche du mois N-1 est clôturée dès la première
 * saisie du mois N — est assurée depuis l'origine par
 * PdoGsb::creeNouvellesLignesFrais(). Elle ne suffit pas : un visiteur qui
 * n'ouvre pas sa saisie du mois suivant laisserait sa fiche indéfiniment à
 * l'état « Créée », et elle ne serait jamais soumise au comptable. D'où ce
 * script.
 */
class CloturerFichesCommand extends CycleEtatCommand
{
    protected function configure()
    {
        $this->setName('gsb:cloturer-fiches')
            ->setDescription('Clôture les fiches encore en saisie pour un mois donné');
        $this->configurerMois();
    }

    protected function getEtatDepart()  { return 'CR'; }
    protected function getEtatArrivee() { return 'CL'; }
    protected function getParticipe()   { return 'clôturée(s)'; }
}
