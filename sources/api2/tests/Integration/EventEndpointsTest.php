<?php

namespace App\Tests\Integration;

/**
 * Endpoints publics d'événements — GET /events/{mode} et GET /event/{id}.
 *
 * Ce sont les endpoints que consomme app2 (le frontend public) : une régression
 * ici vide les listes du site. Ils portent trois logiques non triviales, et ce
 * sont elles qu'on teste — pas le fait que « ça répond 200 » :
 *
 *  1. la whitelist de `mode` (std | champ | all), tout le reste → 403 ;
 *  2. des filtres de publication DIFFÉRENTS selon le mode (`app` pour std,
 *     `Publication` pour all) — c'est subtil et facile à casser ;
 *  3. la bascule `id < 3000` de GET /event/{id} : en dessous on lit un tournoi
 *     (kp_evenement), au-dessus une journée de championnat (kp_journee).
 *
 * Données : SQL/fixtures/ (voir son README pour le mapping ligne → cas).
 */
final class EventEndpointsTest extends ApiTestCase
{
    // ------------------------------------------------------- GET /events/{mode}

    /**
     * Mode 'std' = filtre `app = 'O'` UNIQUEMENT. Conséquence contre-intuitive
     * mais volontaire : un événement non publié (Publication='N') sort quand même
     * s'il est marqué app='O'. Ce test fige ce comportement pour qu'on ne le
     * « corrige » pas par accident.
     */
    public function testStdModeListsAppEventsRegardlessOfPublication(): void
    {
        $ids = self::column($this->getJson('/events/std'), 'id');

        self::assertContains(9001, $ids, 'app=O + publié → présent');
        self::assertContains(9002, $ids, 'app=O mais non publié → présent quand même (filtre = app seul)');
        self::assertNotContains(9003, $ids, 'app=N → absent même si publié');
        self::assertNotContains(9004, $ids, 'app=N + non publié → absent');
    }

    /**
     * Mode 'all' = filtre `Publication = 'O'` UNIQUEMENT (et non `app`). C'est
     * l'exact complément du cas précédent : les deux ensembles se croisent sans
     * se confondre.
     */
    public function testAllModeListsPublishedEventsRegardlessOfAppFlag(): void
    {
        $ids = self::column($this->getJson('/events/all'), 'id');

        self::assertContains(9001, $ids, 'publié + app=O → présent');
        self::assertContains(9003, $ids, 'publié mais app=N → présent (filtre = Publication seul)');
        self::assertNotContains(9002, $ids, 'non publié → absent même si app=O');
        self::assertNotContains(9004, $ids, 'non publié + app=N → absent');
    }

    /** Les événements sont rendus du plus récent au plus ancien (Date_debut DESC). */
    public function testEventsAreOrderedByStartDateDescending(): void
    {
        $rows = $this->getJson('/events/all');
        $years = array_map('intval', self::column($rows, 'year'));

        $sorted = $years;
        rsort($sorted);
        self::assertSame($sorted, $years, 'les événements doivent être triés par date décroissante');
    }

    /** Le champ `year` est dérivé de Date_debut par SQL (YEAR()). */
    public function testEventExposesDerivedYear(): void
    {
        $rows = $this->getJson('/events/all');

        $alpha = null;
        foreach ($rows as $row) {
            if ((int) $row['id'] === 9001) {
                $alpha = $row;
                break;
            }
        }

        self::assertNotNull($alpha, 'l\'événement de fixture 9001 doit être listé');
        self::assertSame(2999, (int) $alpha['year']);
        self::assertSame('Tournoi Test Alpha', $alpha['libelle']);
        self::assertSame('Testville', $alpha['place']);
    }

    /**
     * Mode 'champ' : ne sortent que les journées publiées de compétitions CHPT
     * publiées, dans la saison ACTIVE. Quatre filtres empilés — chacun a sa
     * ligne de fixture dédiée, donc chaque assertion négative teste bien UN filtre.
     */
    public function testChampModeAppliesAllFourFilters(): void
    {
        $ids = self::column($this->getJson('/events/champ'), 'id');

        self::assertContains(9101, $ids, 'journée publiée, CHPT publiée, saison active → présente');
        self::assertNotContains(9103, $ids, 'journée non publiée → exclue');
        self::assertNotContains(9104, $ids, 'compétition non publiée → exclue');
        self::assertNotContains(9107, $ids, 'saison inactive → exclue');
    }

    /**
     * Le logo du mode 'champ' est calculé par un CASE SQL : bandeau prioritaire,
     * puis logo, sinon NULL — et chaque source n'est retenue que si son drapeau
     * `_actif` vaut 'O'. Trois compétitions de fixture couvrent les 3 branches.
     */
    public function testChampModeResolvesLogoByPriority(): void
    {
        $rows = $this->getJson('/events/champ');

        $logos = [];
        foreach ($rows as $row) {
            $logos[(int) $row['id']] = $row['logo'];
        }

        self::assertSame('logo/bandeau-test.png', $logos[9101] ?? null, 'bandeau actif → prioritaire sur le logo');
        self::assertSame('logo/logo-seul.png', $logos[9105] ?? null, 'bandeau inactif → on retombe sur le logo');
        // `?? ` ne conviendrait pas ici : la valeur ATTENDUE étant null, il ne
        // distinguerait pas « logo null » de « journée absente de la réponse ».
        // On vérifie donc d'abord la présence de la clé, puis sa valeur.
        self::assertArrayHasKey(9106, $logos, 'la journée sans visuel doit être listée');
        self::assertNull($logos[9106], 'aucun visuel actif → NULL');
    }

    /** Tout mode hors whitelist est refusé par un 403 (et non un 404/500). */
    public function testInvalidModeIsRejectedWith403(): void
    {
        foreach (['bogus', 'STD', 'champ2', '1'] as $mode) {
            $body = $this->getJson('/events/' . $mode, 403);
            self::assertSame('Invalid mode', $body['error'] ?? null, sprintf('mode "%s"', $mode));
        }
    }

    // --------------------------------------------------------- GET /event/{id}

    /**
     * Branche `id < 3000` : lecture dans kp_evenement, avec filtre `app = 'O'`.
     * L'endpoint renvoie une LISTE (et non un objet) — comportement historique
     * que app2 consomme tel quel, donc à figer.
     */
    public function testSingleLegacyEventIsReadFromEvenementTable(): void
    {
        $rows = $this->getJson('/event/42');

        self::assertCount(1, $rows);
        self::assertSame(42, (int) $rows[0]['id']);
        self::assertSame('Tournoi Test Retro', $rows[0]['libelle']);
        self::assertSame('logo/retro.png', $rows[0]['logo']);
    }

    /**
     * Branche `id >= 3000` : lecture dans kp_journee + kp_competition. Le même id
     * n'a donc PAS le même sens selon qu'il est au-dessus ou en dessous de 3000.
     */
    public function testSingleChampionshipEventIsReadFromJourneeTable(): void
    {
        $rows = $this->getJson('/event/9101');

        self::assertCount(1, $rows);
        self::assertSame(9101, (int) $rows[0]['id']);
        self::assertSame('Journee Test 1', $rows[0]['libelle']);
        self::assertSame('logo/bandeau-test.png', $rows[0]['logo']);
    }

    /**
     * Id inexistant → 200 avec un tableau VIDE, pas un 404. C'est le contrat
     * actuel de l'endpoint ; app2 s'appuie dessus (il teste la longueur).
     */
    public function testUnknownEventReturnsEmptyList(): void
    {
        self::assertSame([], $this->getJson('/event/999999'));
    }

    /**
     * Une journée non publiée n'est pas lisible non plus en accès direct : le
     * filtre de publication s'applique aussi au singulier, pas seulement aux
     * listes (sinon l'URL directe fuiterait un contenu masqué).
     */
    public function testUnpublishedChampionshipEventIsNotReadableDirectly(): void
    {
        self::assertSame([], $this->getJson('/event/9103'), 'journée non publiée');
        self::assertSame([], $this->getJson('/event/9104'), 'compétition non publiée');
    }
}
