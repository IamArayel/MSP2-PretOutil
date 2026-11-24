<?php
// Prêt'Outil : vérification des requêtes du catalogue sur les fixtures (ticket #33).
// Prérequis : fixtures chargées le jour même (les dates sont relatives à aujourd'hui).
// Exécution : docker compose exec -T php php < docs/conception/requetes_catalogue_tests.php 2>/dev/null
// (2>/dev/null masque le journal SQL de l'environnement dev, utile pour vérifier les requêtes générées)
// Affiche un tableau Markdown : n°, cas, attendu, obtenu. Lecture seule.
require '/app/vendor/autoload.php';
(new Symfony\Component\Dotenv\Dotenv())->bootEnv('/app/.env');
$kernel = new App\Kernel('dev', true); $kernel->boot();
$c = $kernel->getContainer()->get('doctrine')->getManager();
$objets = $c->getRepository(App\Entity\Objet::class);
$resas = $c->getRepository(App\Entity\Reservation::class);
$cat = fn(string $l) => $c->getRepository(App\Entity\Categorie::class)->findOneBy(['libelle' => $l]);
$obj = fn(string $n) => $objets->findOneBy(['nom' => $n]);
$j = fn(int $n) => new DateTimeImmutable("today $n days");
$crit = fn($cat = null, $d = null, $f = null) => new App\Dto\CriteresCatalogue($cat, $d === null ? null : $j($d), $f === null ? null : $j($f));
$noms = fn(App\Pagination\Page $p) => array_map(fn($o) => $o->getNom(), $p->elements);
$lignes = [];
$ajoute = function (string $libelle, string $attendu, string $obtenu) use (&$lignes) { $lignes[] = [$libelle, $attendu, $obtenu]; };
$absents = function (App\Pagination\Page $p, array $liste) use ($noms) { return implode(', ', array_intersect($liste, $noms($p))) ?: 'aucun'; };

$p = $objets->rechercherCatalogue($crit(), $j(0));
$ajoute('Sans filtre, page 1', 'total 23, 20 objets, 2 pages, ordre alphabétique', sprintf('total %d, %d objets, %d pages, premiers : %s', $p->total, count($p->elements), $p->nombreDePages(), implode(', ', array_slice($noms($p), 0, 3))));
$ajoute('Sans filtre : objet désactivé (Motobineuse) absent', 'absent', in_array('Motobineuse', array_merge($noms($p), $noms($objets->rechercherCatalogue($crit(), $j(0), 2))), true) ? 'présent' : 'absent');
$ajoute('Sans filtre : objet hors service (Meuleuse) affiché', 'présent', in_array("Meuleuse d'angle 125 mm", array_merge($noms($p), $noms($objets->rechercherCatalogue($crit(), $j(0), 2))), true) ? 'présent' : 'absent');
$p2 = $objets->rechercherCatalogue($crit(), $j(0), 2);
$ajoute('Sans filtre, page 2', '3 objets', count($p2->elements).' objets');
$ajoute('Page 3, au-delà de la dernière', '0 objet, total 23', count($objets->rechercherCatalogue($crit(), $j(0), 3)->elements).' objet, total '.$objets->rechercherCatalogue($crit(), $j(0), 3)->total);
$ajoute('Page 0 ou négative', 'ramenée à la page 1', $objets->rechercherCatalogue($crit(), $j(0), -4)->numero === 1 ? 'ramenée à la page 1' : 'non');
$p = $objets->rechercherCatalogue($crit($cat('Outillage')), $j(0));
$ajoute('Catégorie Outillage', '6 objets', $p->total.' objets');
$p = $objets->rechercherCatalogue($crit(null, 7, 9), $j(0));
$ajoute('Période J+7 à J+9', '20 objets ; Tente (réservée), Meuleuse (hors service) et Glacière (en retard) exclues', $p->total.' objets ; exclus parmi Tente/Meuleuse : '.(count(array_intersect(['Tente 4 places', "Meuleuse d'angle 125 mm"], array_merge($noms($p), $noms($objets->rechercherCatalogue($crit(null,7,9), $j(0), 2))))) === 0 ? 'les deux' : 'NON'));
$p = $objets->rechercherCatalogue($crit(null, 9, 10), $j(0));
$all = array_merge($noms($p), $noms($objets->rechercherCatalogue($crit(null,9,10), $j(0), 2)));
$ajoute('Période J+9 à J+10 (bornes incluses)', '19 objets ; Tente (finit J+9) et Taille-haie (commence J+10) exclus', $p->total.' objets ; '.(in_array('Tente 4 places',$all,true)||in_array('Taille-haie électrique',$all,true) ? 'NON exclus' : 'exclus'));
$p = $objets->rechercherCatalogue($crit(null, 5, 6), $j(0));
$ajoute('Période J+5 à J+6, aucune réservation', '21 objets (Meuleuse hors service et Glacière en retard exclues)', $p->total.' objets');
$p = $objets->rechercherCatalogue($crit(null, 3, 4), $j(0));
$all = array_merge($noms($p), $noms($objets->rechercherCatalogue($crit(null,3,4), $j(0), 2)));
$ajoute('Période J+3 à J+4 : Scie réservée, Tondeuse annulée', '20 objets ; Scie exclue, Tondeuse présente', $p->total.' objets ; Scie '.(in_array('Scie sauteuse',$all,true)?'présente':'exclue').', Tondeuse '.(in_array('Tondeuse thermique',$all,true)?'présente':'exclue'));
$p = $objets->rechercherCatalogue($crit($cat('Outillage'), 3, 4), $j(0));
$ajoute('Outillage et période J+3 à J+4', '4 objets', $p->total.' objets');
$p = $objets->rechercherCatalogue($crit(null, 0, 1), $j(0));
$all = array_merge($noms($p), $noms($objets->rechercherCatalogue($crit(null,0,1), $j(0), 2)));
$ajoute('Période J à J+1 : Vidéoprojecteur en cours, Enceinte réservée', 'Vidéoprojecteur et Enceinte exclus', 'Vidéoprojecteur '.(in_array('Vidéoprojecteur',$all,true)?'présent':'exclu').', Enceinte '.(in_array('Enceinte Bluetooth',$all,true)?'présente':'exclue'));
$ajoute('Période J à J+1 : Glacière en retour en retard (fin J-1)', 'exclue (pas réservable avant son retour)', in_array('Glacière électrique',$all,true)?'présente':'exclue');
$p = $objets->rechercherCatalogue($crit(null, 20, 21), $j(0));
$all = array_merge($noms($p), $noms($objets->rechercherCatalogue($crit(null,20,21), $j(0), 2)));
$ajoute('Période J+20 à J+21 : Glacière toujours en retard', 'exclue (le retard bloque toute période)', in_array('Glacière électrique',$all,true)?'présente':'exclue');
$ajoute('Une seule date fournie', 'filtre de période ignoré : 23 objets', $objets->rechercherCatalogue(new App\Dto\CriteresCatalogue(null, $j(3), null), $j(0))->total.' objets');
$f = fn($n) => implode(' ; ', array_map(fn($r) => $r['dateDebut']->format('d/m').'-'.$r['dateFin']->format('d/m'), $resas->periodesReservees($obj($n), $j(0))));
$ajoute('Périodes réservées : Tente', $j(7)->format('d/m').'-'.$j(9)->format('d/m'), $f('Tente 4 places') ?: 'aucune');
$ajoute('Périodes réservées : Vidéoprojecteur (en cours)', $j(-2)->format('d/m').'-'.$j(1)->format('d/m'), $f('Vidéoprojecteur') ?: 'aucune');
$ajoute('Périodes réservées : Tondeuse (annulée)', 'aucune', $f('Tondeuse thermique') ?: 'aucune');
$ajoute('Périodes réservées : Perceuse (terminée)', 'aucune', $f('Perceuse-visseuse 18 V') ?: 'aucune');
foreach ($lignes as $i => [$cas_, $att, $obt]) { printf("| %d | %s | %s | %s |\n", $i + 1, $cas_, $att, $obt); }
