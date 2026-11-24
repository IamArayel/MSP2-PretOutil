<?php
// Prêt'Outil : vérification de ReservationService sur les fixtures (ticket #44).
// Prérequis : fixtures chargées le jour même. ATTENTION : enregistre et annule des réservations ;
// recharger les fixtures ensuite (doctrine:fixtures:load --em=proprietaire).
// Exécution : docker compose exec -T php php < docs/conception/reservation_service_tests.php 2>/tmp/sql.log
// Affiche un tableau Markdown : n°, cas, attendu, obtenu.
require '/app/vendor/autoload.php';
(new Symfony\Component\Dotenv\Dotenv())->bootEnv('/app/.env');
$kernel = new App\Kernel('dev', true); $kernel->boot();
$conteneur = $kernel->getContainer();
$doctrine = $conteneur->get('doctrine');
$em = $doctrine->getManager();
// Construction explicite (comme l'autowiring) : le conteneur de dev ne rend pas les services privés.
$horloge = new Symfony\Component\Clock\NativeClock();
$audit = new App\Service\AuditLogger();
$reservations = $doctrine->getRepository(App\Entity\Reservation::class);
$service = new App\Service\ReservationService($em, $reservations, $audit, $horloge);
$utilisateur = fn(string $email) => $doctrine->getRepository(App\Entity\Utilisateur::class)->findOneBy(['email' => $email]);
$objet = fn(string $nom) => $doctrine->getRepository(App\Entity\Objet::class)->findOneBy(['nom' => $nom]);
$j = fn(int $n) => new DateTimeImmutable("today $n days");
$lignes = [];
$essai = function (string $cas, string $attendu, callable $action) use (&$lignes, $em) {
    try { $r = $action(); $obtenu = $r instanceof App\Entity\Reservation ? 'acceptée (statut '.$r->getStatut()->value.')' : 'acceptée'; }
    catch (App\Exception\ReservationRefuseeException $e) { $obtenu = (new ReflectionClass($e))->getShortName().' : '.$e->getMessage(); }
    catch (Throwable $e) { $obtenu = 'ERREUR '.get_class($e).' : '.$e->getMessage(); }
    $lignes[] = [$cas, $attendu, $obtenu];
};
// Comptes : Camille (2 actives), Noah (3, quota atteint), Sacha (1), Léa (1, pour RG2).
$camille = $utilisateur('adherent@pretoutil.test'); $noah = $utilisateur('adherent.quota@pretoutil.test'); $sacha = $utilisateur('admin@pretoutil.test'); $lea = $utilisateur('gestionnaire@pretoutil.test');

$essai('Réservation valide (Brouette, J+5 à J+6)', 'acceptée (statut confirmee)', fn() => $service->reserver($camille, $objet('Brouette'), $j(5), $j(6)));
$lignes[count($lignes)-1][2] .= $camille->getDerniereActiviteLe() ? ', dernière activité mise à jour' : ', dernière activité NON mise à jour';
$lignes[count($lignes)-1][1] .= ', dernière activité mise à jour';
$essai('RG1 : fin avant début', 'PeriodeInvalideException', fn() => $service->reserver($sacha, $objet('Brouette'), $j(9), $j(8)));
$essai('RG1 : début hier', 'PeriodeInvalideException', fn() => $service->reserver($sacha, $objet('Brouette'), $j(-1), $j(2)));
$essai('RG1 : début aujourd\'hui', 'acceptée', fn() => $service->reserver($sacha, $objet('Lampe frontale'), $j(0), $j(1)));
$essai('RG3 : 15 jours (J+20 à J+34)', 'DureeDepasseeException', fn() => $service->reserver($sacha, $objet('Escabeau 5 marches'), $j(20), $j(34)));
$essai('RG3 : 14 jours exactement (J+20 à J+33)', 'acceptée', fn() => $service->reserver($sacha, $objet('Escabeau 5 marches'), $j(20), $j(33)));
$essai('RG5 : objet désactivé (Motobineuse)', 'ObjetNonReservableException', fn() => $service->reserver($camille, $objet('Motobineuse'), $j(30), $j(31)));
$essai('RG5 : objet hors service (Meuleuse)', 'ObjetNonReservableException', fn() => $service->reserver($camille, $objet("Meuleuse d'angle 125 mm"), $j(30), $j(31)));
$essai('Retour en retard (Glacière, J+30)', 'ObjetNonReservableException', fn() => $service->reserver($camille, $objet('Glacière électrique'), $j(30), $j(31)));
$essai('RG4 : 4e réservation active (adhérent quota)', 'QuotaAtteintException', fn() => $service->reserver($noah, $objet('Paddle gonflable'), $j(30), $j(31)));
$essai('RG2 : chevauchement (Tente, J+8 à J+10)', 'PeriodeIndisponibleException', fn() => $service->reserver($lea, $objet('Tente 4 places'), $j(8), $j(10)));
$essai('RG2 : période qui commence le lendemain de la fin (Tente, J+10)', 'acceptée', fn() => $service->reserver($lea, $objet('Tente 4 places'), $j(10), $j(11)));
// Accès simultanés : un repository qui ne voit pas le chevauchement, comme si une autre requête
// avait inséré sa réservation entre la vérification et l'INSERT. La contrainte EXCLUDE doit refuser.
$aveugle = new class($doctrine) extends App\Repository\ReservationRepository {
    public function existeChevauchement(App\Entity\Objet $objet, DateTimeImmutable $debut, DateTimeImmutable $fin): bool { return false; }
};
$serviceAveugle = new App\Service\ReservationService($em, $aveugle, $audit, $horloge);
$essai('RG2 en accès simultanés : contrainte EXCLUDE (23P01)', 'PeriodeIndisponibleException', fn() => $serviceAveugle->reserver($lea, $objet('Tente 4 places'), $j(8), $j(9)));
$lignes[count($lignes)-1][2] .= ', en base : '.$em->getConnection()->fetchOne("select count(*) from reservation r join objet o on o.id = r.objet_id where o.nom = 'Tente 4 places' and r.date_debut = current_date + 8").' réservation';
$lignes[count($lignes)-1][1] .= ', en base : 0 réservation';
// Après l'échec du flush, Doctrine ferme l'EntityManager : on repart d'un gestionnaire neuf.
$doctrine->resetManager(); $em = $doctrine->getManager(); $reservations = $doctrine->getRepository(App\Entity\Reservation::class);
$service = new App\Service\ReservationService($em, $reservations, $audit, $horloge);
$noah = $utilisateur('adherent.quota@pretoutil.test');
$aAnnuler = $reservations->findOneBy(['utilisateur' => $noah, 'statut' => App\Enum\StatutReservation::Confirmee], ['dateDebut' => 'DESC']);
$essai('Annuler une réservation confirmée (Taille-haie)', 'acceptée, statut annulee', function () use ($service, $aAnnuler, $noah) { $service->annuler($aAnnuler, $noah); return 'acceptée, statut '.$aAnnuler->getStatut()->value; });
$lignes[count($lignes)-1][2] = 'acceptée, statut '.$aAnnuler->getStatut()->value;
$essai('Annulation : la réservation annulée ne compte plus dans le quota', 'acceptée (statut confirmee)', fn() => $service->reserver($noah, $objet('Paddle gonflable'), $j(30), $j(31)));
$enCours = $reservations->findOneBy(['utilisateur' => $noah, 'statut' => App\Enum\StatutReservation::EnCours]);
$essai('Annuler une réservation en cours', 'AnnulationImpossibleException', fn() => $service->annuler($enCours, $noah));
foreach ($lignes as $i => [$cas, $att, $obt]) { printf("| %d | %s | %s | %s |\n", $i + 1, $cas, $att, $obt); }
