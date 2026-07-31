<?php

namespace App\Tests\Integration;

/**
 * Chemin de lecture PUBLIC du scoring live — celui que consomment les incrustations
 * vidéo : `GET /scoring/program/{event}/{pitch}` et `GET /scoring/state/{matchId}`.
 *
 * Ce qui est testé ici, c'est le **contrôle d'accès**, pas le contenu :
 *
 *  - ces routes ne portent aucun JWT utilisateur (une incrustation tourne sans opérateur
 *    dans un mélangeur vidéo) : le seul verrou est le **jeton d'affichage**, et un test
 *    qui l'oublierait laisserait passer une route ouverte à tous ;
 *  - un jeton **expiré** ou **révoqué** doit être refusé — la révocation est la garantie
 *    qu'on peut couper une régie dont l'URL a fuité (plan §4.4) ;
 *  - un jeton d'un **autre événement**, ou restreint à **un autre terrain**, ne doit rien
 *    ouvrir : c'est ce qui empêche une incrustation d'espionner un autre tournoi.
 *
 * Voir DOC/specs/PAGE_INCRUSTATION.md §11bis pour l'analyse (et pourquoi un contrôle
 * same-origin ne peut pas tenir ce rôle).
 *
 * Données : SQL/fixtures/ — jetons `tsttok-*`, événement 9001, match 99001 sur le terrain 2.
 */
final class ScoringPublicEndpointsTest extends ApiTestCase
{
    private const EVENT = 9001;
    private const PITCH = '2';
    private const MATCH = 99001;

    private const TOKEN_VALID = 'tsttok-valid-event-9001';
    private const TOKEN_PITCH2 = 'tsttok-valid-pitch2';
    private const TOKEN_EXPIRED = 'tsttok-expired';
    private const TOKEN_REVOKED = 'tsttok-revoked';
    private const TOKEN_OTHER_EVENT = 'tsttok-other-event';

    private function programUri(?string $token, string $pitch = self::PITCH): string
    {
        $uri = '/scoring/program/' . self::EVENT . '/' . $pitch;

        return $token === null ? $uri : $uri . '?token=' . urlencode($token);
    }

    /** Statut HTTP d'un GET, sans passer par les assertions JSON du socle. */
    private function statusOf(string $uri): int
    {
        $this->client->request('GET', $uri);

        return $this->client->getResponse()->getStatusCode();
    }

    // ------------------------------------------------------------- refus d'accès

    /**
     * Le cas qui compte le plus : **sans jeton, rien**. Si ce test passe au vert alors
     * qu'il devrait être rouge, c'est que le chemin public est ouvert à tous.
     */
    public function testProgramRequiresADisplayToken(): void
    {
        self::assertSame(401, $this->statusOf($this->programUri(null)));
    }

    public function testProgramRejectsAnUnknownToken(): void
    {
        self::assertSame(401, $this->statusOf($this->programUri('ce-jeton-nexiste-pas')));
    }

    public function testProgramRejectsAnExpiredToken(): void
    {
        self::assertSame(401, $this->statusOf($this->programUri(self::TOKEN_EXPIRED)));
    }

    /** La révocation prime sur une expiration encore lointaine. */
    public function testProgramRejectsARevokedToken(): void
    {
        self::assertSame(401, $this->statusOf($this->programUri(self::TOKEN_REVOKED)));
    }

    /** Isolation entre événements : un jeton d'un autre tournoi n'ouvre rien ici. */
    public function testProgramRejectsATokenOfAnotherEvent(): void
    {
        self::assertSame(401, $this->statusOf($this->programUri(self::TOKEN_OTHER_EVENT)));
    }

    /** Un jeton restreint à un terrain ne doit pas ouvrir les autres terrains. */
    public function testPitchScopedTokenIsRefusedOnAnotherPitch(): void
    {
        self::assertSame(200, $this->statusOf($this->programUri(self::TOKEN_PITCH2, '2')));
        self::assertSame(401, $this->statusOf($this->programUri(self::TOKEN_PITCH2, '3')));
    }

    /**
     * Défense en profondeur : un `fetch` manifestement cross-site est refusé (403).
     * Ce n'est **pas** la serrure — les en-têtes sont falsifiables — mais ça écarte
     * l'exploitation depuis un autre site dans le navigateur d'un visiteur.
     */
    public function testObviousCrossSiteRequestIsRefused(): void
    {
        $this->client->request(
            'GET',
            $this->programUri(self::TOKEN_VALID),
            server: ['HTTP_ORIGIN' => 'https://un-autre-site.example', 'HTTP_SEC_FETCH_SITE' => 'cross-site']
        );

        self::assertSame(403, $this->client->getResponse()->getStatusCode());
    }

    // ------------------------------------------------------------- accès autorisé

    /**
     * Avec un jeton valide, la réponse porte ce dont l'incrustation a besoin pour
     * démarrer seule : l'adressage Mercure (jamais fabriqué côté client) et les réglages
     * d'enchaînement résolus.
     */
    public function testProgramReturnsAddressingAndSettings(): void
    {
        $program = $this->getJson($this->programUri(self::TOKEN_VALID));

        self::assertSame(self::EVENT, $program['event']);
        self::assertSame(self::PITCH, $program['pitch']);
        self::assertSame(
            '/scoring/event/' . self::EVENT . '/pitch/' . self::PITCH,
            $program['topicBase'],
            'le topic est calculé par le serveur : le client ne doit jamais le fabriquer'
        );
        self::assertArrayHasKey('settings', $program);
        self::assertArrayHasKey('halftimeScoreDelay', $program['settings'], 'réglages résolus (défauts inclus)');
    }

    /**
     * La réponse embarque un JWT d'abonné Mercure : elle ne doit donc JAMAIS être mise
     * en cache partagé. (Le JWT lui-même n'est présent que si le hub est configuré ;
     * l'en-tête de cache, lui, doit être correct dans tous les cas.)
     */
    public function testProgramResponseIsNotPubliclyCacheable(): void
    {
        $this->client->request('GET', $this->programUri(self::TOKEN_VALID));
        $cacheControl = (string) $this->client->getResponse()->headers->get('Cache-Control');

        self::assertStringContainsString('private', $cacheControl);
        self::assertStringNotContainsString('public', $cacheControl);
    }

    /** L'ETag permet à l'incrustation de revalider sans retélécharger l'état. */
    public function testProgramSendsAnEtagAndHonoursIt(): void
    {
        $this->client->request('GET', $this->programUri(self::TOKEN_VALID));
        $etag = (string) $this->client->getResponse()->headers->get('ETag');
        self::assertNotSame('', $etag, 'un ETag doit être émis');

        $this->client->request(
            'GET',
            $this->programUri(self::TOKEN_VALID),
            server: ['HTTP_IF_NONE_MATCH' => $etag]
        );
        self::assertSame(304, $this->client->getResponse()->getStatusCode());
    }

    // ------------------------------------------------------------- état d'un match

    /**
     * `/state` est adressé par match, alors que le jeton est adressé par événement/terrain :
     * le contrôleur doit résoudre l'un vers l'autre. Un jeton d'un autre événement ne doit
     * donc pas ouvrir l'état d'un match de celui-ci.
     */
    public function testStateIsScopedByTheTokenOfItsOwnEvent(): void
    {
        self::assertSame(401, $this->statusOf('/scoring/state/' . self::MATCH));
        self::assertSame(401, $this->statusOf('/scoring/state/' . self::MATCH . '?token=' . self::TOKEN_OTHER_EVENT));
    }

    /**
     * Un match jamais touché par la console n'a pas d'état live : 404 explicite plutôt
     * qu'un objet vide, pour que l'incrustation distingue « pas encore commencé » d'une
     * panne de données.
     */
    public function testStateOfAMatchNeverScoredLiveIsNotFound(): void
    {
        self::assertSame(
            404,
            $this->statusOf('/scoring/state/' . self::MATCH . '?token=' . self::TOKEN_VALID)
        );
    }
}
