# Spécification — Écrans de score et de chronomètre de tir autonomes, reliés par Internet

> **Statut : évaluation + spec (2026-11-25), aucune ligne de code.** Issue GitHub
> [laurentgarrigue/kpi#425](https://github.com/laurentgarrigue/kpi/issues/425).
>
> ⛔ **Implémentation bloquée** par la checklist
> [SCORING_ROLLOUT_CHECKLIST.md](../developer/in-progress/SCORING_ROLLOUT_CHECKLIST.md) : aucun nouveau
> lot scoring avant **R1 et R2** cochés. Cette page **lit** exactement ce que lit l'incrustation
> ([PAGE_INCRUSTATION.md](PAGE_INCRUSTATION.md)), qui n'a encore **jamais** été alimentée par un hub
> Mercure réel (V3) : tant que V3 n'est pas passée, rien ici n'est démontrable.
>
> Documents liés : [PAGE_SCORING.md §6.5 et §6.5bis](PAGE_SCORING.md) (écrans plein écran actuels,
> canaux d'affichage) ; [étude des afficheurs de chronomètre de tir déportés](../developer/in-progress/SCORING_SHOTCLOCK_REMOTE_DISPLAYS_STUDY.md)
> (afficheurs d'angle radio, option « navigateur dans chaque angle » écartée).

---

## 1. Besoin

Aujourd'hui le **tableau de score** (`games/[id]/scoreboard`) et le **chronomètre de tir**
(`games/[id]/shotclock`) sont des **fenêtres secondaires du PC de scoring**, alimentées par
`BroadcastChannel` (même poste, même origine, session authentifiée). Chaque écran impose donc un
**câble vidéo** depuis la table de marque.

On veut des écrans **autonomes** — un navigateur, un boîtier ou une TV connectée, en filaire ou en
Wi-Fi — qui suivent le match **par Internet**, comme l'incrustation : état canonique
`scoring_live_*`, diffusion `scoring_outbox` → Mercure.

## 2. Évaluation des enjeux

### 2.1 Synthèse

| Écran | Par Internet ? | Pourquoi |
|---|---|---|
| **Tableau de score** (face à la table, public) | ✅ **Oui**, c'est le cas d'usage idéal | le score tolère une seconde de retard ; le chrono de jeu est interpolé localement ; la page est l'incrustation avec une autre mise en page |
| **Chronomètre de tir — écran secondaire** (public, 2ᵉ table, salle d'échauffement, secours) | ✅ **Oui, comme affichage non officiel** | mêmes mécanismes, sous réserve des corrections §2.2 |
| **Chronomètre de tir — afficheurs réglementaires d'angle** | ❌ **Non** | l'étude impose E3 (latence < 200 ms) et **E6 (aucune dépendance à Internet)** ; elle a déjà écarté cette option (§3, option B) au profit d'afficheurs radio ESP-NOW. Rien dans cette évaluation ne remet ce choix en cause. |

### 2.2 Latence et précision du chrono — le point dur

**Ce qui est déjà bien conçu** : l'état transmis est le **modèle 4 valeurs** (`initMs`,
`elapsedMs`, `startedAtMs`, `running`) — on transmet l'**instant de départ**, pas les secondes
restantes (`useInterpolatedClock`). Un écran qui reçoit un départ en retard affiche **tout de
suite la bonne valeur** : le retard ne s'accumule pas, il ne coûte qu'une marche de rattrapage.

**Ce qui ne va pas, relevé dans le code (2026-11-25)** :

| # | Constat | Effet | Gravité |
|---|---|---|---|
| L1 | L'outbox est drainée **toutes les 1 s** par l'event-cache-worker (`EventCacheWorkerCommand`, `sleep(1)`) | retard ajouté **0 à 1 s** (≈ 0,5 s en moyenne) entre l'appui et la publication | 🔴 pour le chronomètre de tir |
| L2 | `startedAt` est l'heure **du PC de la console** ; l'écran l'interpole avec **sa propre** horloge (`Date.now()`). Aucune correction de décalage n'est appliquée, alors que PAGE_INCRUSTATION §5 l'annonce (« offset heure serveur ») | l'erreur affichée = **écart d'horloge console ↔ écran**. Une clé HDMI ou une TV dont l'heure dérive de 3 s affiche le chronomètre de tir **faux de 3 s, en permanence** | 🔴 |
| L3 | `GET /scoring/state` expose `nowServer = time() % 86400` : **à la seconde**, inutilisable pour un recalage sub-seconde | pas de base pour corriger L2 | 🟠 |
| L4 | Un **arrêt** (`--`) ou une remise à 60/40 arrive avec le même retard que L1 + réseau | l'écran **continue de décompter** pendant ce retard, puis saute ; près de 0, il peut afficher **0 alors que le jeu a été arrêté** | 🔴 pour un affichage qui ferait foi ; acceptable s'il est non officiel |
| L5 | Pas de *heartbeat* applicatif sur les topics ; le hub n'a pas de `heartbeat` configuré (défaut Mercure : 40 s) | une coupure silencieuse peut passer inaperçue plusieurs dizaines de secondes | 🟠 |

**Budget de latence visé** (appui à la console → chiffre à l'écran, réseau du site sain) :

| Étape | Aujourd'hui | Cible |
|---|---|---|
| Console → api2 (POST, 4G/Wi-Fi) | 50–300 ms | inchangé |
| Transaction + dépôt outbox | < 20 ms | inchangé |
| Attente du drainage (L1) | 0–1 000 ms | **≤ 100 ms** |
| Publication Mercure → SSE écran | 50–300 ms | inchangé |
| **Total p95** | **≈ 1,5 s** | **≤ 500 ms** |

Une demi-seconde convient au tableau de score et à un chronomètre de tir **non officiel**. Elle
ne respecte pas l'E3 de l'étude (< 200 ms) : c'est la raison pour laquelle les afficheurs
d'angle restent en radio locale.

### 2.3 Réseau du site

| Situation | Verdict |
|---|---|
| **Wi-Fi public du site, saturé** (spectateurs) | ❌ à proscrire : c'est la cause de panne la plus probable. Le SSE tient, mais les rattrapages se multiplient. |
| **Routeur 4G/5G dédié** à la table + écrans (SSID privé) ou **Ethernet** | ✅ configuration recommandée ; l'écran ne consomme presque rien (un `GET` au démarrage + quelques octets par action) |
| **Pas d'Internet du tout** (réseau local seul) | ❌ hors périmètre. Un hub Mercure local suppose un serveur sur le site, un certificat TLS valide en local (le *mixed content* interdit à une page HTTPS d'écouter `http://` local) et une synchronisation avec api2 : c'est le **niveau N2** de l'étude (§6, application autonome, 3–5 mois), à arbitrer avec le **lot 7 offline**. En attendant, le câble vidéo reste la solution sans Internet. |
| Coupure courte | l'écran continue d'interpoler ; rejeu `Last-Event-ID` à la reconnexion (mécanique déjà en place pour l'incrustation) |

### 2.4 Matériel

| Option | Prix indicatif | Verdict |
|---|---|---|
| **Raspberry Pi 4/5 + Chromium en mode kiosque**, Ethernet ou Wi-Fi, NTP actif | 80–130 € | ✅ **recommandé** : moteur Chromium connu (même rendu que les tests), horloge NTP fiable, démarrage automatique sur l'URL, pilotable à distance |
| Mini-PC (Windows/Linux) en kiosque | 150–300 € | ✅ si déjà disponible |
| Clé HDMI Android TV / Fire TV + navigateur kiosque | 40–80 € | ⚠️ acceptable pour le tableau de score ; moteurs WebView inégaux, mise en veille, horloge à surveiller (L2) |
| Navigateur intégré d'une **TV connectée** | 0 € | ❌ moteurs anciens, veille agressive, pas de plein écran fiable |
| Plein soleil | — | ⚠️ un téléviseur grand public (300–600 cd/m²) n'est **pas** lisible en extérieur (étude, E1) : le boîtier doit alors piloter un **panneau LED** par HDMI |

**Appairage d'un écran à un terrain** : l'écran n'a ni clavier ni souris. Le lien porte tout
(événement, terrain, type d'écran, jeton). Il est fabriqué dans app4 et transmis par **QR code**
ou copié une fois dans la configuration du kiosque. Un appairage par **code court affiché sur
l'écran** (type « device code ») est plus confortable mais demande un flux supplémentaire :
renvoyé à plus tard (§9).

### 2.5 Sécurité

Le **jeton d'affichage** existe déjà (`scoring_display_token`, `ScoringDisplayAccessService`,
PAGE_INCRUSTATION §11bis) : portée événement ou **terrain**, expiration, révocation, JWT d'abonné
Mercure limité aux topics du jeton. **Il couvre le besoin tel quel** : lecture seule, aucun
secret affiché (les données sont publiques), un jeton fuité ne donne accès qu'à ce que le public
voit déjà, et se révoque. À ajouter : un **libellé** par jeton (« écran tribune terrain 2 ») pour
savoir quoi révoquer.

### 2.6 Réutilisation

| Brique | Réutilisée telle quelle | À adapter |
|---|---|---|
| `useOverlayProgram` (GET programme + état, SSE, rejeu, re-GET au réveil, bascule de match) | ✅ | — |
| `useInterpolatedClocks` (modèle 4 valeurs) | ✅ | **correction du décalage d'horloge** (L2), partagée avec l'incrustation |
| Jeton d'affichage, routes publiques `/scoring/program` et `/scoring/state` | ✅ | libellé du jeton |
| Mise en page plein écran (`useFitText`, contraste, disposition colonne, `?theme=light`, `?clock=0`) de `scoreboard.vue` / `shotclock.vue` | ✅ le rendu | ces pages sont **authentifiées** et lisent **`BroadcastChannel`** : on extrait le rendu en composants alimentés par props, branchés soit sur `BroadcastChannel` (PC de la console, inchangé), soit sur Mercure (écran autonome) |
| Interface d'émission des jetons (onglet incrustations de la supervision TV) | ✅ | générateur de lien « écran » + QR |

**Conclusion** : c'est **l'incrustation avec une mise en page plein écran** plus **deux
corrections de fond** (L1, L2) dont l'incrustation a de toute façon besoin. Le coût est faible ;
le risque est concentré sur le réseau du site et sur l'horloge des boîtiers.

### 2.7 Piste : écran sur le même réseau local que le PC de la console (2026-11-25)

**Question** : si l'écran et le PC de la console partagent le même réseau local (même routeur 4G,
même switch), la latence est-elle réglée ?

**Non, pas à lui seul.** Partager le réseau ne change pas le trajet des données : la console
écrit dans api2 et l'écran lit Mercure, donc tout passe **par le serveur KPI sur Internet**,
deux fois par le même accès 4G. Les vrais coûts restent entiers :

| Cause | Réglée par le simple réseau local ? |
|---|---|
| L1 — attente du drainage de l'outbox (0–1 s), **la plus grosse part** | ❌ elle est sur le serveur |
| Aller-retour Internet console → serveur → écran | ❌ inchangé (on gagne seulement d'éviter le Wi-Fi public saturé, déjà exclu au §2.3) |
| L2 — décalage d'horloge entre la console et l'écran | ❌ inchangé |
| Coupure d'Internet | ❌ l'écran perd le flux comme avant |

Le réseau local ne rapporte que si les données vont **directement** du PC à l'écran. Pour un
navigateur, il n'existe que trois façons de le faire :

| Chemin direct | Faisable ? | Pourquoi |
|---|---|---|
| **Serveur local** (WebSocket/SSE servi par le PC ou un boîtier) | ❌ sans application installée | une page HTTPS ne peut pas ouvrir `ws://192.168.x.x` (*mixed content*), Chrome restreint en plus l'accès au réseau privé, et un navigateur ne sait pas écouter un port. Il faut un certificat TLS local et un programme installé : c'est le **niveau N2** de l'étude (§6) |
| `BroadcastChannel` | ❌ | limité aux fenêtres d'un **même** navigateur sur un **même** poste (c'est le câble vidéo actuel) |
| **WebRTC DataChannel** pair à pair | ✅ **seule voie sans rien installer** | natif dans tous les navigateurs récents, autorisé en HTTPS, chiffré. Sur un même réseau local, la liaison s'établit directement entre les deux appareils (candidats « host ») sans passer par Internet |

**WebRTC en réseau local — ce que ça donnerait**

```
 Console (PC)  ──① offre/réponse via api2 + Mercure (Internet, quelques secondes, une fois)──▶ Écran
      │                                                                                       ▲
      └──────────② DataChannel direct sur le réseau local : chaque action, ≈ 5–30 ms ─────────┘
      └──③ POST api2 → outbox → Mercure (inchangé) : la référence, et le secours si ② tombe ──┘
```

- **Latence** : quelques dizaines de ms appui → écran, **sous les 200 ms** de l'E3 de l'étude ;
  le drainage de l'outbox ne joue plus sur le chemin rapide.
- **Horloges** : l'écran se recale sur la console par aller-retour sur le DataChannel (RTT de
  quelques ms → précision de l'ordre de la milliseconde). Combiné à T4 (console en heure serveur),
  les deux chemins restent cohérents.
- **Coupure d'Internet** : une liaison **déjà établie** survit ; l'écran continue de recevoir la
  console. En revanche, un écran qui redémarre pendant la coupure ne peut pas se réappairer
  (l'appairage ① passe par api2).
- **Mercure reste la vérité** : la console envoie l'action aux écrans **avant** la confirmation
  serveur. Si le serveur la refuse (409, source non active) ou la corrige, le message Mercure
  suivant l'emporte (`tick`). Un écran prend donc toujours le plus récent des deux chemins.

**Limites**

| Limite | Effet |
|---|---|
| **Isolation des clients Wi-Fi** (fréquente sur les Wi-Fi publics et certains routeurs 4G en mode invité) | pas de liaison directe → retour au chemin Mercure, sans gain. À vérifier sur le routeur retenu (réglage « AP isolation » désactivé) |
| Chrome masque les IP locales derrière des noms `.local` (mDNS) | si le réseau bloque le multicast, pas de liaison directe → même repli |
| L'écran dépend du **PC de la console allumé, onglet ouvert** | perte du chemin rapide si la console plante ou passe sur un autre terminal (reprise §6.4 de PAGE_SCORING) : repli Mercure, puis nouvel appairage |
| L'appairage demande que l'**écran écrive** (sa réponse SDP) | entorse à « lecture seule » : une route d'écriture **limitée à la signalisation**, autorisée par le jeton d'affichage du terrain |
| La console gère **N liaisons** (2–4 écrans par terrain) | code et tests supplémentaires dans la console, déjà dense |
| **Afficheurs d'angle réglementaires** | la latence serait tenue, mais le blocage reste **matériel** : écran lisible au soleil à 1 500–3 000 € par angle (étude §3), Wi-Fi extérieur à 20 m dans un spectre 2,4 GHz encombré, dépendance à Internet pour l'appairage (E6). **L'étude n'est pas remise en cause.** |

**Coût estimé** : **2–3 semaines** (signalisation api2 + topic privé, gestion des liaisons dans
la console, client écran, fusion des deux chemins, tests de coupure), **après** le lot de base
(T1–T8), dont il réutilise tout. Sans commune mesure avec le niveau N2 (3–5 mois).

**Verdict**

- Réseau local **seul** : ❌ ne résout ni L1 ni L2 ; seul bénéfice, un réseau dédié (déjà
  recommandé au §2.3).
- Réseau local **+ WebRTC** : ✅ résout la latence (≈ 5–30 ms) et la précision, tient une coupure
  d'Internet en cours de match. C'est la bonne réponse **si** les mesures §2.8-1 montrent qu'un
  chronomètre de tir à ~500 ms (lot de base) ne suffit pas aux écrans secondaires. À n'engager
  qu'en second temps, une fois le chemin Mercure validé.

### 2.8 Recommandation

1. **Maintenant, sans code** (compatible avec le gel) : profiter de R1/R4 pour **mesurer** sur
   l'incrustation (`?debug`) la latence réelle appui → affichage et l'écart d'horloge sur un vrai
   site, en 4G dédiée et en Wi-Fi.
2. **Après R2** : un lot court « écrans autonomes » = L1 + L2/L3 (qui servent aussi l'incrustation)
   + la page `/live/display` (§3).
3. **Afficheurs d'angle réglementaires** : inchangé, filière radio de l'étude.
4. **Sans Internet** : pas de hub local dans ce lot ; câble vidéo, puis arbitrage N2 / lot 7.
5. **Réseau local + WebRTC** (§2.7) : extension possible du lot de base si la latence mesurée ne
   suffit pas ; prérequis à documenter dès maintenant : routeur dédié **sans isolation des clients**.

## 3. Périmètre fonctionnel

Une **page publique unique** d'app4, sœur de l'incrustation :

```
/live/display?event=236&pitch=2&screen=scoreboard&token=…
/live/display?event=236&pitch=2&screen=shotclock&theme=light&clock=0&token=…
```

- elle affiche **le match courant du terrain** et **passe seule au suivant** (programme du terrain,
  PAGE_INCRUSTATION §6) — un écran installé le matin tient toute la journée ;
- **zéro interaction**, plein écran, pas de son (le buzzer reste à la console) ;
- même rendu que les fenêtres plein écran de la console (PAGE_SCORING §6.5bis).

### 3.1 Contrat d'URL

| Paramètre | Obligatoire | Valeurs / défaut | Rôle |
|---|---|---|---|
| `event` | ✅ | id d'événement | abonnement niveau 1 |
| `pitch` | ✅ | n° de terrain | abonnement niveau 2 |
| `token` | ✅ | jeton d'affichage | autorise les lectures et l'abonnement Mercure |
| `screen` | ✅ | `scoreboard` \| `shotclock` | mise en page |
| `theme` | — | `dark` (défaut) \| `light` | blanc sur noir / noir sur blanc |
| `clock` | — | `1` (défaut) \| `0` | `shotclock` : chrono de jeu sous le chronomètre de tir |
| `shotclock` | — | `1` (défaut) \| `0` | `scoreboard` : afficher le chronomètre de tir |
| `skin` | — | `inter` (défaut) \| `club` | noms d'équipes (même règle que l'incrustation) |
| `debug` | — | absent | latence mesurée, écart d'horloge, état SSE, `tick` |

### 3.2 États affichés hors match

| Situation | Écran `scoreboard` | Écran `shotclock` |
|---|---|---|
| Aucun match courant sur le terrain | prochain match (n°, heure, équipes) | `--` |
| Mi-temps / pause | score + décompte de pause | `--` |
| Match terminé | score final, puis prochain match (délais du programme) | `--` |
| Jeton refusé / expiré | message explicite à l'écran (comme l'incrustation) | idem |

### 3.3 Indicateur de liaison

Un **point discret** dans un coin, invisible quand tout va bien :

- **orange** : aucun message ni *heartbeat* depuis plus de **5 s** — l'écran continue
  d'interpoler ;
- **rouge** : déconnecté depuis plus de **30 s** — le chronomètre de tir affiche `--` (on préfère
  « pas d'information » à un décompte peut-être faux) ; le tableau de score garde le score et
  fige le chrono.

## 4. Exigences techniques

| # | Exigence | Mise en œuvre attendue |
|---|---|---|
| T1 | **Drainage de l'outbox ≤ 100 ms** (L1) | boucle de drainage resserrée (sans toucher à la cadence de génération du cache legacy) ; l'ordre strict par `id` et le *at-least-once* sont conservés |
| T2 | **Heure serveur précise** (L3) | `GET /scoring/time` public, sans cache, renvoyant l'heure serveur en epoch ms (ou champ `serverNowMs` dans `/program` et `/state`) |
| T3 | **Correction du décalage d'horloge** (L2) côté **écran** | au démarrage puis toutes les 5 min : 5 échanges, on garde celui de plus faible aller-retour, `offset = serverNow − (t0 + t1) / 2` ; l'interpolation utilise `Date.now() + offset` |
| T4 | **`startedAt` exprimé en heure serveur** (L2) côté **console** | la console applique la même correction T3 avant d'envoyer `startedAt` — sinon l'erreur d'horloge du PC de table se répercute sur tous les écrans |
| T5 | ***Heartbeat*** (L5) | `heartbeat` du hub réglé à 5 s dans `Caddyfile.api2`, ou message `…/heartbeat` publié par le worker ; l'écran mesure le silence (§3.3) |
| T6 | **Rendu partagé** | composants plein écran extraits de `scoreboard.vue` / `shotclock.vue`, alimentés par props ; les fenêtres de la console gardent `BroadcastChannel` |
| T7 | **Jetons libellés** | colonne `label` sur `scoring_display_token`, saisie à la création, affichée dans la liste |
| T8 | **Lien d'écran + QR** | dans l'onglet des incrustations de la supervision TV : choix du terrain et du type d'écran → URL + QR code |

## 5. Critères d'acceptation

Les tests sont nommés d'après ces critères.

**Fonctionnel**

- **CA1** — Ouvrir `/live/display?event=E&pitch=P&screen=scoreboard&token=T` dans un navigateur
  nu affiche le match courant du terrain P, sans aucune action ; recharger donne le même état.
- **CA2** — Un but saisi à la console apparaît sur l'écran `scoreboard` en **moins de 1 s (p95)**
  sur le réseau de test.
- **CA3** — Statut `END` saisi à la console : l'écran affiche le score final puis le prochain match
  selon les délais du programme, puis bascule seul au coup d'envoi suivant.
- **CA4** — Un jeton absent, expiré, révoqué ou d'un autre terrain affiche un message d'erreur à
  l'écran et n'obtient ni état ni abonnement (401/403).
- **CA5** — Un jeton de portée terrain 2 ne reçoit aucun message du terrain 1 (isolation des topics).
- **CA6** — `screen=shotclock&theme=light&clock=0` donne un chronomètre de tir noir sur blanc,
  sans chrono de jeu, à la taille maximale (parité PAGE_SCORING §6.5bis).

**Précision**

- **CA7** — Écran dont l'horloge système est **décalée de +5 s** : après la synchronisation T3, le
  chronomètre de tir affiché diffère de celui de la console de **moins de 150 ms**.
- **CA8** — PC de console décalé de **−3 s** : même résultat que CA7 sur tous les écrans (T4).
- **CA9** — Délai appui console → publication Mercure **≤ 150 ms (p95)** mesuré sur 100 actions
  (T1).
- **CA10** — Arrêt du chronomètre de tir (`--`) : l'écran l'affiche en **moins de 500 ms (p95)**
  sur le réseau de test.

**Résilience**

- **CA11** — Câble réseau débranché 20 s pendant que le chrono tourne : l'écran continue de
  décompter, le point passe orange ; au rebranchement, les actions manquées sont rattrapées
  (rejeu) et le point disparaît.
- **CA12** — Coupure de plus de 30 s : point rouge, chronomètre de tir à `--` ; au retour, état
  exact en moins de 2 s.
- **CA13** — Écran en veille ou onglet masqué puis réveillé : re-`GET` complet, état exact.

**Exploitation**

- **CA14** — Depuis la supervision TV, générer le lien d'un écran (terrain, type, libellé) affiche
  l'URL et un QR code ; révoquer le jeton coupe l'écran au plus tard à sa prochaine
  resynchronisation (re-`GET` refusé) — le hub ne ferme pas de lui-même une connexion SSE déjà
  ouverte : l'écran refait donc un `GET /program` au moins toutes les **5 min**.
- **CA15** — Un Raspberry Pi en kiosque Chromium démarré sur le lien affiche le bon écran sans
  clavier ni souris (vérification manuelle, procédure dans `DOC/user/`).

## 6. Plan de vérification sur le terrain (avant toute mise en service)

1. Sur un match réel (R4), deux écrans : un en Ethernet ou 4G dédiée, un sur le Wi-Fi du site.
2. Filmer côte à côte la console et chaque écran à 60 i/s pendant 20 départs/arrêts du
   chronomètre de tir ; relever l'écart image par image.
3. Relever l'écart d'horloge brut de chaque boîtier (mode `debug`) en début et en fin de journée.
4. Seuil de mise en service : CA2, CA7 et CA10 tenus sur la configuration dédiée.

## 7. Ce que la page ne fait pas

- Aucune écriture, aucun bouton, aucun son.
- Elle ne remplace **pas** les afficheurs réglementaires d'angle du chronomètre de tir (étude).
- Elle ne fonctionne **pas** sans Internet (pas de hub local).
- Elle ne décide pas quel match afficher : le programme du terrain le fait (PAGE_INCRUSTATION P4).

## 8. Fichiers concernés (indicatif)

| Couche | Fichiers |
|---|---|
| api2 | `Command/EventCacheWorkerCommand.php` (T1), `Controller/ScoringLiveController.php` (T2), `Service/ScoringDisplayAccessService.php` + migration (T7), `docker/config/Caddyfile.api2` (T5) |
| app4 | `pages/live/display.vue` (nouvelle), `composables/useInterpolatedClock.ts` + nouveau `useServerClock.ts` (T3), console : envoi de `startedAt` (T4), `components/scoring/` (T6), `components/tv/OverlaysTab.vue` (T8) |
| Doc | PAGE_INCRUSTATION §5 (corriger l'annonce du recalage, ou la rendre vraie via T3), `DOC/user/` (installation d'un écran) |

## 9. Hors périmètre / plus tard

- **Hub Mercure local / fonctionnement sans Internet** — niveau N2 de l'étude, lot 7.
- **Chemin direct WebRTC en réseau local** (§2.7) — second temps, selon les mesures.
- **Appairage par code court** affiché sur l'écran (flux « device code ») — si le QR s'avère
  pénible en exploitation.
- **Supervision des écrans** (liste des écrans connectés, dernière activité) — utile à partir de
  plusieurs terrains équipés.
- **Afficheurs d'angle radio** — étude dédiée.

## 10. Questions ouvertes

1. Un chronomètre de tir **non officiel** par Internet a-t-il une utilité réelle (public, 2ᵉ
   table), ou seul le tableau de score compte-t-il ?
2. Matériel de référence : **Raspberry Pi en kiosque** (recommandé) — acceptable pour les
   organisateurs, ou faut-il viser d'abord les clés HDMI grand public ?
3. Réseau : peut-on imposer un **routeur 4G dédié** par site comme prérequis documenté ?
4. Les mesures §2.8-1 peuvent-elles être faites pendant le pilote R4 ?
