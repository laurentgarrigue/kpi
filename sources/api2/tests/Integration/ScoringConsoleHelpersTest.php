<?php

namespace App\Tests\Integration;

use App\Service\ScoringLiveService;

/**
 * Aides de la vue « Paramètres » de la console de scoring (PAGE_SCORING.md §7.2, §7.8) :
 *
 *  - **match suivant du terrain** : la table enchaîne les matchs d'un même terrain, toutes
 *    compétitions confondues au sein d'un tournoi (événement) ;
 *  - **suggestions d'officiels** : la table de marque et les juges de ligne sont fournis
 *    par les équipes désignées arbitres — leurs joueurs passent en premier.
 *
 * Données : SQL/fixtures/ — événement 77, terrain « 1 » le 2999-04-01 : 9404 (RCH, 09:00)
 * puis 9411 (RCP, 14:00) ; événement 78 : 9401 (10:00) puis 9403 (11:00, non publié).
 */
final class ScoringConsoleHelpersTest extends ApiTestCase
{
    private const ADMIN = 'TADMIN';

    // ------------------------------------------------------------ match suivant

    public function testNextMatchFollowsThePitchAcrossCompetitionsOfTheEvent(): void
    {
        $res = $this->requestAs(self::ADMIN, 'GET', '/admin/scoring/next/9404');

        self::assertSame(9411, $res['id']);
    }

    /** La publication ne compte pas : la table doit pouvoir saisir un match non publié. */
    public function testNextMatchIncludesUnpublishedGames(): void
    {
        $res = $this->requestAs(self::ADMIN, 'GET', '/admin/scoring/next/9401');

        self::assertSame(9403, $res['id']);
    }

    /** Dernier match du terrain : réponse normale (pas une erreur), id nul. */
    public function testLastMatchOfThePitchHasNoNext(): void
    {
        $res = $this->requestAs(self::ADMIN, 'GET', '/admin/scoring/next/9411');

        self::assertNull($res['id']);
    }

    // ------------------------------------------------------------ officiels

    private function designate(string $principal, string $secondaire): void
    {
        $this->beginWrites()->executeStatement(
            'UPDATE kp_match SET Arbitre_principal = ?, Arbitre_secondaire = ? WHERE Id = 9404',
            [$principal, $secondaire]
        );
    }

    /** @return list<array{group:string,value:string,label:string}> */
    private function suggest(string $role, string $q = ''): array
    {
        $res = $this->requestAs(self::ADMIN, 'GET', '/admin/scoring/officials/9404/suggest?role=' . $role . '&q=' . urlencode($q));

        return $res['items'];
    }

    /** Équipe désignée directement : ses joueurs d'abord, au format legacy « NOM Prénom (matricule) ». */
    public function testTableOfficialsComeFirstFromTheFirstRefereeTeam(): void
    {
        $this->designate('Equipe Bravo', 'Equipe Charlie');

        $items = $this->suggest('table');

        self::assertSame(['group' => 'team', 'value' => 'BRAVO Cid (9503)', 'label' => 'BRAVO Cid'], $items[0]);
    }

    /** Désignation nominative : l'équipe est entre parenthèses. Les juges de ligne → arbitre 2. */
    public function testLineJudgesComeFromTheSecondRefereeTeamEvenWhenNamed(): void
    {
        $this->designate('Equipe Bravo', 'ARBITRE Deux (Equipe Charlie)');

        $items = $this->suggest('line');

        self::assertSame('CHARLIE Dan (9504)', $items[0]['value']);
        self::assertSame('team', $items[0]['group']);
    }

    /** Un membre inactif (Capitaine = 'X') n'est jamais proposé. */
    public function testInactiveMembersAreNotSuggested(): void
    {
        $this->designate('Equipe Alpha', '');

        $values = self::column($this->suggest('table'), 'value');

        self::assertContains('ALPHA Ann (9501)', $values);
        self::assertNotContains('ALPHA Inactif (9506)', $values);
    }

    /** Hors équipe désignée : les licenciés correspondant à la saisie, après. */
    public function testOtherLicenceHoldersFollowWhenTyping(): void
    {
        $this->designate('Equipe Bravo', '');

        $items = $this->suggest('table', 'arbitre');

        $values = self::column($items, 'value');
        self::assertContains('ARBITRE Deux (9902)', $values);
        self::assertContains('ARBITRE Un (9901)', $values);
        self::assertSame(['other'], array_values(array_unique(self::column($items, 'group'))));
    }

    public function testUnknownRoleIsRejected(): void
    {
        $this->requestAs(self::ADMIN, 'GET', '/admin/scoring/officials/9404/suggest?role=boss', expectedStatus: 400);
    }

    // ------------------------------------------------------------ abonnement temps réel

    /**
     * En préprod/prod le hub Mercure refuse les abonnés anonymes : l'état de la console doit
     * fournir un JWT d'abonné, sinon la console affiche « Connexion perdue » en permanence.
     * Le jeton n'ouvre que les topics du match (jamais ceux d'un autre terrain/tournoi).
     */
    public function testStateGivesTheConsoleASubscriberTokenLimitedToItsMatchTopics(): void
    {
        $this->beginWrites();
        static::getContainer()->get(ScoringLiveService::class)->ensureState(9404);

        $state = $this->requestAs(self::ADMIN, 'GET', '/admin/scoring/state/9404');

        self::assertTrue($state['exists']);
        self::assertArrayHasKey('mercureToken', $state);
        if ($state['mercureToken'] === '') {
            return; // hub sans secret (mode anonyme) : rien d'autre à vérifier
        }
        $claims = json_decode(base64_decode(strtr(explode('.', $state['mercureToken'])[1], '-_', '+/')), true);
        self::assertSame(['subscribe' => [$state['topicBase'] . '/{type}']], $claims['mercure']);
    }

    /**
     * Régression préprod : l'ETag ne dépendait que du tick. Après un déploiement qui ajoutait
     * le jeton à la réponse, le navigateur revalidait, recevait 304 et gardait l'ancienne
     * réponse sans jeton → abonnement refusé (401). L'ETag porte donc aussi l'empreinte du
     * jeton : une réponse en cache sans jeton (ou au jeton périmé) n'est plus validée.
     */
    public function testStateEtagChangesWithTheSubscriberToken(): void
    {
        $this->beginWrites();
        static::getContainer()->get(ScoringLiveService::class)->ensureState(9404);

        $state = $this->requestAs(self::ADMIN, 'GET', '/admin/scoring/state/9404');
        $etag = (string) $this->client->getResponse()->headers->get('ETag');
        $tickOnlyEtag = 'W/"scoring-9404-' . $state['tick'] . '"';

        $this->client->request('GET', '/admin/scoring/state/9404', server: [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $this->tokenFor(self::ADMIN),
            'HTTP_IF_NONE_MATCH' => $tickOnlyEtag,
        ]);
        self::assertSame(200, $this->client->getResponse()->getStatusCode(), 'un ETag sans empreinte du jeton ne doit plus valider le cache');

        $this->client->request('GET', '/admin/scoring/state/9404', server: [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $this->tokenFor(self::ADMIN),
            'HTTP_IF_NONE_MATCH' => $etag,
        ]);
        self::assertSame(304, $this->client->getResponse()->getStatusCode(), 'même tick et même jeton : 304');
    }
}
