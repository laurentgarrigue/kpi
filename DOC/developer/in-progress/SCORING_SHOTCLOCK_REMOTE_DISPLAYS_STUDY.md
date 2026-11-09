# Étude — Afficheurs de chronomètre de tir déportés (console Scoring app4)

> **Statut** : étude / aide à la décision. v1 du 2026-11-09, **v2 du 2026-11-09** intégrant les
> réponses aux questions (§2.1) et la piste « capter le signal du chronomètre de tir » (§4).
> Aucune ligne de code engagée.
> **Contexte fonctionnel** : [PAGE_SCORING.md §6.5](../../specs/PAGE_SCORING.md) (chronomètre de tir,
> 3 commandes, raccourcis clavier, diffusion locale `BroadcastChannel` / distante Mercure) et
> [plan de refonte live §4.7](LIVE_MATCH_SCORING_REFACTORING_PROPOSALS.md) (relais matériel,
> *Hardware Scoring*).
> **Terminologie** : « chronomètre de tir » (code : `shotclock`).

---

## 1. Le problème

À la table de marque (bord de terrain, **en extérieur**), un PC portable ordinaire (Windows, macOS
ou Linux) exécute la console Scoring d'app4.

| Afficheur | Public | Position | Solution |
|---|---|---|---|
| **Tableau de score** | joueurs + public | face à la table | **HDMI + écran étendu** vers l'écran LED (route `games/[id]/scoreboard`) — ✅ réglé |
| **Chronomètres de tir ×2** | joueurs (surtout l'attaque), public si possible | **dans les angles, aux deux extrémités du terrain** | ❓ **objet de cette étude** |

Deux obstacles bloquent l'approche « une sortie vidéo par afficheur » :

1. **La connectique** : un portable a en général **une seule** sortie vidéo libre une fois
   l'écran LED du tableau de score branché ;
2. **La distance** : terrain de ~35 × 23 m, table au milieu d'une longueur. Les angles du **même
   côté** que la table sont à ~18–20 m, ceux du **côté opposé** à ~29–32 m en ligne droite — et
   bien plus en câble, qui doit contourner le terrain et les zones de passage. Le HDMI passif
   s'arrête vers 10–15 m.

## 2. Exigences

### 2.1 Données d'entrée (réponses du 2026-11-09)

| # | Sujet | Réponse | Conséquence |
|---|---|---|---|
| R1 | Solutions propriétaires | Plusieurs existent, certaines à l'étude, pas encore d'information détaillée | Ne pas en dépendre ; **prévoir de pouvoir les brancher** (§4, §7) |
| R2 | Parc | **Jusqu'à 5 terrains** (rares tournois à 5 ; internationaux à 4 → le 5ᵉ jeu sert de rechange) | 5 jeux = 5 bases + 10 afficheurs ; 5 identifiants de terrain sans interférence |
| R3 | Énergie | Secteur **généralement disponible** dans les angles ; batterie = **option** appréciée des petites organisations | Alimentation secteur 12 V de base, **module batterie optionnel** sur le même connecteur |
| R4 | Fabrication | **Fabrication maison à étudier** | Conception documentée, reproductible, pièces standard |
| R5 | Budget | **< 1 000 € par terrain, < 500 € si possible** | Élimine d'emblée les options vidéo (§3) |
| R6 | Contenu | Chronomètre de tir **+ temps de jeu** si la taille le permet. **Pas de buzzer dans les angles**, seulement à la table (mini-enceinte sur le PC) | Afficheur 2 + 4 chiffres ; le buzzer reste dans la console (Web Audio) |
| R7 | Navigateur | **Difficile d'imposer un navigateur**, seulement en dernier recours | Le canal **de base** doit marcher dans **tous** les navigateurs ; Web Serial (Chromium) ne peut être qu'un **bonus** |
| R8 | Règlements | FFCK : « affichage électronique **visible par les deux équipes** et si possible par le public » ; ICF : « **dans les coins du terrain, du même côté que l'arbitre responsable** » | Deux afficheurs d'angle sur la longueur de l'arbitre ; face principale vers le terrain, **face arrière vers le public en option** |

### 2.2 Exigences techniques

| # | Exigence | Pourquoi |
|---|---|---|
| E1 | **Lisible en plein soleil à ~30 m** : chronomètre de tir en chiffres de **≥ 20–25 cm**, temps de jeu en **~10–12 cm** ; luminosité « outdoor » (≥ 3 000–5 000 cd/m², hors de portée des moniteurs/TV grand public, qui plafonnent à 300–600 cd/m²) | afficheur d'arbitrage |
| E2 | **Les deux afficheurs synchrones** entre eux et avec la console (écart < 0,1 s) | un joueur ne doit jamais voir deux valeurs différentes |
| E3 | **Latence de commande < 200 ms** (départ/reset 60 s, 40 s, arrêt) | l'opérateur réagit au sifflet |
| E4 | **Tolérance aux coupures**, signalées à l'opérateur | fiabilité match |
| E5 | **Aucune dépendance à Internet** pour le chronomètre de tir | réseau souvent absent ou saturé en extérieur |
| E6 | **Indépendant du navigateur** (R7) | parc de PC hétérogène, opérateurs bénévoles |
| E7 | **Robustesse extérieure** : IP54 minimum (IP65 conseillé), chaleur, vent (pied lesté) | plusieurs jours, plusieurs saisons |
| E8 | **Mise en place < 10 min par terrain**, sans compétence réseau, appairage stable d'un jour à l'autre | bénévoles, montage chaque matin |
| E9 | **5 terrains côte à côte sans interférence** | un afficheur n'obéit qu'à **sa** table |
| E10 | **Coût < 500 € / terrain visé** (R5), maintenable (pièces standard) | budget associatif |

> **Principe directeur — transmettre un *état*, pas une *image*.** Un chronomètre de tir
> n'affiche que deux chiffres. Il suffit de transmettre son **état** (60 s / 40 s lancé, arrêté,
> suspendu) — exactement ce que décrit déjà `scoring_live_clock` kind `SHOTCLOCK`
> (`init_ms`, `elapsed_ms`, `running`, cf. PAGE_SCORING §0.5) — et de laisser **chaque
> afficheur décompter lui-même** avec sa propre horloge, recalé par des trames d'état
> périodiques. Quelques octets suffisent ; latence et micro-coupures deviennent indolores.

## 3. Options écartées (résumé de la v1)

| Option | Pourquoi elle est écartée | Coût / terrain |
|---|---|---|
| **A. Prolonger la vidéo** (dock DisplayLink/splitter + extendeurs HDMI sur Cat6 ou HDMI sans fil) | Un écran **lisible au soleil** par angle (1 500–3 000 € pièce), ~100 m de câble au sol ou liaison vidéo 2,4/5 GHz saturée, pilote DisplayLink fragile sous macOS/Linux | 3 500–6 000 € |
| **B. Navigateur dans chaque angle** (mini-PC/tablette + page `shotclock` abonnée à Mercure) | Dépend d'Internet (E5) ; mêmes écrans, même coût ; une version 100 % locale bute sur le *mixed content* (page HTTPS ⇏ `ws://` local) | 3 000–5 000 € (tablettes : ~300 € mais illisibles au soleil) |
| **E. LoRa 868 MHz** | *Duty cycle* européen (1 %, 10 % sur 869,4–869,65 MHz) incompatible avec des trames toutes les 250–500 ms ; portée inutile à 30 m | — |

Ces options restent valables **en dépannage** (B en salle ou à l'ombre) et pour les **écrans
d'ambiance distants** (buvette, zone d'attente), déjà couverts par la cible Mercure.

## 4. La vraie question : qui est le maître du chronomètre de tir ?

La famille de solution retenue dès la v1 — **afficheurs LED dédiés pilotés par radio** — reste la
bonne (lisibilité, coût, aucune vidéo). La réponse R7 (pas de navigateur imposé) et la piste
proposée (« récupérer le signal du chronomètre de tir ») font émerger la question structurante :
**dans quel sens circule l'information ?**

### 4.1 Sens 1 — Console maîtresse (console → afficheurs)

L'opérateur pilote tout depuis le PC (raccourcis `Entrée` / `.` / `0`, cf. PAGE_SCORING §6.5) ;
la console pousse l'état vers une base radio, qui le diffuse aux afficheurs.

Le point dur est le **lien navigateur → boîtier local**, qui dépend du navigateur :

| Canal console → base | Navigateurs | Sans Internet | Logiciel à installer | Verdict |
|---|---|:---:|:---:|---|
| **Web Serial** (clé USB) | Chrome, Edge, Opera, Brave — **pas Firefox, pas Safari** | ✅ | non | Excellent techniquement, **mais impose Chromium** (contraire à R7) |
| **Mercure via Internet** (la base s'abonne au topic du terrain par Wi-Fi/4G) | tous | ❌ | non | Latence 0,2–1 s (console → api2 → outbox → Mercure), recalage horaire nécessaire, coupures 4G → **contraire à E5** |
| **Petit logiciel relais sur le PC** (`ws://localhost`) | Chrome/Firefox oui (localhost « de confiance », invite d'accès réseau local chez Chrome) ; **Safari à vérifier** | ✅ | **oui** (Win/Mac/Linux, signé, maintenu) | Lourd à maintenir pour des bénévoles |
| **Signal audio** (tonalités codées sur la sortie son) | tous | ✅ | non | **Fragile** (volume, sons système) et **en conflit avec le buzzer** sur la même enceinte → écarté |

**Conclusion : aucun canal console → boîtier n'est à la fois universel, local et sans
installation.** Le sens 1 seul ne satisfait pas R7.

### 4.2 Sens 2 — Pupitre maître (pupitre → afficheurs, pupitre → console)

C'est la piste proposée, généralisée : **le chronomètre de tir est piloté par un matériel dédié
à la table** (pupitre maison ou télécommande d'un système propriétaire). Ce matériel commande
**directement** les afficheurs, et **informe** la console.

Le lien **boîtier → navigateur**, lui, a une solution universelle : **se présenter comme un
clavier USB** (HID). Tous les systèmes d'exploitation et tous les navigateurs reconnaissent un
clavier, sans pilote, sans autorisation, sans installation. Et la console **sait déjà** piloter
le chronomètre de tir et le chrono principal au clavier (raccourcis paramétrables,
PAGE_SCORING §6.5).

```
                 Table de marque                                   Angle A (côté arbitre)
 ┌──────────────────────────────┐                                  ┌──────────────────────┐
 │ PC — app4 Scoring            │                                  │  ┌──┐┌──┐  tir 25 cm │
 │  (n'importe quel navigateur) │                          radio   │  └──┘└──┘            │
 │                              │                        ┌ ─ ─ ─ ─▶│  12:34   jeu 10 cm   │
 │  ◀── touches F13…F18 ──┐     │                        │ ESP-NOW │  ESP32 · secteur 12 V │
 │  (clavier USB virtuel) │     │                        │         │  (batterie en option) │
 │                        │     │   ┌────────────────┐   │         └──────────────────────┘
 │  ┄┄ canal retour ┄┄┄┄┄┄┼┄┄┄┄▶│   │ PUPITRE        │───┤         ┌──────────────────────┐
 │  (Web Serial si Chromium,    │USB│ ESP32-S3       │   │         │ Angle B (côté arbitre)│
 │   optionnel)           └─────┼───│ [Chrono ▶/■]   │   └ ─ ─ ─ ─▶│  idem                 │
 │                              │   │ [60] [40] [--] │             └──────────────────────┘
 │  HDMI ─▶ écran LED score     │   │ voyants A / B  │
 │  🔊 buzzer (mini-enceinte)   │   └────────────────┘
 └──────────────────────────────┘
```

**Le pupitre maison** : un petit boîtier USB (microcontrôleur **ESP32-S3**, qui gère nativement
l'USB) avec 4 gros boutons — **chrono principal départ/arrêt**, **60 s**, **40 s**, **arrêt
`--`** — et deux voyants de liaison (un par afficheur, alimentés par les accusés de réception
radio). À chaque appui, il :

1. **diffuse par radio (ESP-NOW)** le nouvel état aux deux afficheurs (quelques ms, même trame
   pour les deux → synchrones, E2–E3) ;
2. **envoie une touche** au PC (clavier virtuel) → la console applique la même commande que
   son raccourci.

Pourquoi c'est solide :

- **Universel** (E6) : aucun navigateur imposé, aucune autorisation, aucun logiciel.
- **Indépendant du PC** : si le navigateur se fige, que le PC redémarre ou qu'Internet tombe,
  **les chronomètres de tir continuent de fonctionner**. La console n'est plus sur le chemin
  critique de l'affichage d'arbitrage (E4, E5).
- **Synchronisation avec le chrono principal résolue par construction** : le bouton chrono du
  pupitre démarre/arrête **à la fois** le chrono de la console (touche) et la suspension
  automatique du chronomètre de tir (radio). Un seul appui, une seule origine : l'écart se
  limite à la latence d'une touche USB (~10 ms) ; la dérive des quartz sur une période de
  10 min reste de l'ordre de quelques centièmes.
- **Ergonomie** : de vrais boutons, utilisables sans regarder l'écran, à côté du clavier où
  l'opérateur saisit les faits de jeu.

**Points de vigilance (à transformer en critères d'acceptation dans la spec)** :

| Risque | Parade |
|---|---|
| **Collision avec la saisie** (touches qui partent dans un champ de texte) | Touches **dédiées F13–F18**, inexistantes sur les claviers physiques ; la console les traite **même si le focus est dans un champ**. À tester sur macOS (certaines touches F13+ sont réservées par le système sur certains postes) |
| **Focus perdu** (l'opérateur a cliqué la fenêtre du tableau de score sur l'écran étendu ; les touches n'arrivent pas à la console) | La fenêtre `scoreboard` **relaie** les touches F13–F18 à la console par `BroadcastChannel` (déjà en place) ; l'afficheur d'angle, maître, reste juste de toute façon |
| **Double pilotage** (l'opérateur utilise aussi `Espace`/`Entrée` du PC, que le pupitre ne voit pas) | Paramètre `ScoringConfig` **« horloges pilotées par pupitre »** : quand il est actif, la console **désactive** ses propres commandes chrono/chronomètre de tir et affiche « piloté par pupitre » |
| **Temps de jeu sur les afficheurs d'angle** (R6) et **masquage** du chronomètre de tir quand le temps restant est inférieur (`shotClockShow`) : il faut que le pupitre connaisse le temps de jeu, or les **corrections** se font dans la console | Le pupitre tient son propre temps de jeu (départ/arrêt + durée de période). **Sans canal retour**, une correction faite dans la console n'atteint pas les angles → afficher le temps de jeu aux angles **seulement si un canal retour existe**, ou faire les corrections au pupitre (boutons ±1 s, à arbitrer) |
| **État de liaison** invisible pour la console en mode clavier pur | Voyants **sur le pupitre** ; la console les reçoit en plus si un canal retour existe |

### 4.3 Le meilleur des deux : sens 2 + canal retour optionnel

Le pupitre ESP32-S3 peut se présenter **à la fois** comme clavier **et** comme port série
(périphérique USB composite). On obtient une **amélioration progressive** :

| Navigateur de la table | Ce qui marche |
|---|---|
| **N'importe lequel** | Pupitre maître, afficheurs synchrones, console synchronisée par touches, buzzer dans la console, voyants sur le pupitre. Chronomètre de tir seul aux angles (ou temps de jeu du pupitre, cf. §4.2) |
| **Chromium (Web Serial accepté)** | + la console pousse le **temps de jeu de référence** et ses **corrections** au pupitre (temps de jeu fiable aux angles, masquage `shotClockShow` exact), + voyants de liaison **dans la console**, + **mode test** d'avant-match lancé depuis la console |
| **Internet disponible** (optionnel) | Même enrichissement possible par Mercure (le pupitre s'abonne au topic du terrain en Wi-Fi), utile si le navigateur n'est pas Chromium — **jamais requis** |

### 4.4 Capter un système propriétaire (piste « récupérer le signal »)

Le sens 2 accueille naturellement un système du commerce : **sa télécommande devient le
pupitre**. Il suffit d'un **adaptateur** (même ESP32-S3) qui lit les signaux du système et les
transforme en **les mêmes touches F13–F18** pour la console. Un seul contrat d'entrée côté
console, quel que soit le matériel → aucun développement spécifique par fabricant côté app4.

Ce qu'il faut savoir **par fabricant** (questionnaire à envoyer, R1) :

| # | Question | Pourquoi |
|---|---|---|
| F1 | Le système expose-t-il une **sortie** : contacts secs/relais, RS-232/RS-485, UDP/TCP, Bluetooth ? Le protocole est-il **documenté** et sa réutilisation **autorisée** ? | moyen de capter le signal |
| F2 | Sort-il des **événements** (reset 60 / 40 / `--`, départ/arrêt) ou la **valeur affichée** ? À quelle fréquence ? | événements → la console reconstitue ; valeur → la console recopie à l'identique |
| F3 | Accepte-t-il une **entrée externe départ/arrêt** du chrono principal (contact, trame) ? Gère-t-il lui-même un **temps de jeu** ? | c'est **le** point de synchronisation avec la console (cf. ci-dessous) |
| F4 | Accepte-t-il une **entrée de pilotage** (pour le sens 1) ? | permettrait de le commander depuis notre pupitre ou la console |
| F5 | Radio utilisée, portée, coexistence de **5 jeux** sur un même site | E9 |
| F6 | Lisibilité soleil (cd/m²), taille des chiffres, IP, alimentation (secteur/batterie, autonomie), garantie, pièces détachées, **prix par terrain** | E1, E7, R3, R5 |

**Le problème de synchronisation avec le chrono principal** se règle selon la réponse à F3 :

- **Le système accepte une entrée départ/arrêt** → notre adaptateur la pilote depuis le bouton
  chrono du pupitre : un seul appui pour les deux horloges (comme en §4.2) ;
- **Le système gère aussi le temps de jeu** → c'est un cas de **Hardware Scoring** (plan §4.7) :
  le système devient la source des horloges, la console les recopie ;
- **Ni l'un ni l'autre** → la suspension du chronomètre de tir à l'arrêt du jeu repose sur un
  **double geste** de l'opérateur (chrono console + télécommande). Risque d'oubli → **à
  éviter** ; le système ne serait acceptable qu'avec cette parade matérielle.

> **Note** : la captation **n'exige pas** que le système envoie la valeur affichée. Des
> événements horodatés suffisent : la console recalcule le décompte comme elle le fait déjà
> (horloges en horodatage, PAGE_SCORING §6.4).

## 5. Afficheur d'angle maison (sens 2)

| Élément | Choix proposé |
|---|---|
| Chronomètre de tir | 2 chiffres 7 segments **~25 cm**, ruban LED adressable **12 V** (WS2815, tolérant à une LED défaillante) sous diffuseur |
| Temps de jeu (R6) | 4 chiffres `mm:ss` **~10–12 cm** sous le chronomètre de tir (disposition « basket ») |
| Face | Une face vers le terrain, visible des deux équipes (R8) ; **face arrière public en option** |
| Commande | ESP32, ESP-NOW (portée 100–200 m en vue directe ≫ 30 m), identifiant de terrain 1–5 par sélecteur rotatif, canal radio réglable |
| Comportement | Décompte local, trame d'état complète toutes les 250–500 ms ; au-delà de ~3 s sans trame, point clignotant (alerte) ; luminosité auto (capteur de lumière : moins de chaleur, moins de consommation) |
| Énergie | Secteur : bloc 12 V étanche (R3). **Option** : batterie LiFePO4 12 V sur le même connecteur |
| Mécanique | Caisson IP65, face PMMA teintée anti-reflet, fixation sur pied enceinte lesté ou poteau |
| Évolutions | Firmware versionné dans le dépôt (ex. `hardware/shotclock/`), mise à jour USB/OTA |

Pas de buzzer dans l'afficheur (R6) : le buzzer reste dans la console, sur la mini-enceinte du
PC, déclenché par l'état (fin du chronomètre de tir, fin de période, fin de pause).

### 5.1 Budget indicatif (ordres de grandeur 2026, à confirmer par le prototype)

| Poste | Par unité | Par terrain |
|---|---|---|
| Afficheur d'angle (LED ~6 m, ESP32, caisson + face, bloc 12 V, fixation, connectique) | 180–220 € | 360–440 € (×2) |
| Pupitre (ESP32-S3 + antenne, 4 gros boutons, voyants, boîtier, câble USB) | 40–60 € | 40–60 € |
| **Total de base (secteur)** | | **≈ 400–500 €** ✅ cible < 500 € |
| Option batterie (LiFePO4 12 V 12–20 Ah + chargeur) | +80–120 € / afficheur | +160–240 € |
| Option face arrière public | +50–80 € / afficheur | +100–160 € |

**Parc de 5 terrains** : ~2 000–2 500 € de base (le 5ᵉ jeu sert de rechange lors des
internationaux à 4 terrains, R2). À comparer aux 3 000–6 000 € **par terrain** des options A/B.

## 6. Recommandation

1. **Architecture : pupitre maître (sens 2)**, afficheurs d'angle LED maison pilotés en
   **ESP-NOW**, console synchronisée par **clavier USB virtuel (touches F13–F18)** — universel,
   sans navigateur imposé (R7), sans Internet (E5), et le chronomètre de tir ne dépend plus du PC.
2. **Canal retour optionnel** par Web Serial (Chromium) puis, si utile, Mercure : il apporte le
   temps de jeu de référence aux angles, les voyants dans la console et le mode test. **Jamais
   requis** pour arbitrer.
3. **Un seul contrat d'entrée console** (touches F13–F18 + paramètre « horloges pilotées par
   pupitre ») qui sert **aussi** aux systèmes propriétaires via un adaptateur → envoyer le
   **questionnaire F1–F6** aux fabricants en parallèle du prototype ; on comparera prix et
   fiabilité sur pièces.
4. **Fabrication maison** à valider par prototype (R4) ; budget visé ≈ 400–500 € par terrain
   sur secteur (R5).

### 6.1 Démarche

1. **Valider cette v2** (questions §7).
2. **Spec** dédiée avec critères d'acceptation (principe du projet) : contrat des touches, mode
   « horloges pilotées par pupitre », trame radio, comportements en coupure, voyants, mode test,
   canal retour.
3. **Prototype** : 1 pupitre + 1 afficheur, essai réel (soleil, 30 m, téléphones du public,
   Windows/macOS/Linux × Chrome/Firefox/Safari), mesure de consommation et de latence.
4. **Pré-série** 1 terrain sur une compétition test, en parallèle du dispositif actuel.
5. **Série** 5 terrains ; guide montage/test/démontage dans `DOC/user/`.

## 7. Questions ouvertes (v2)

1. **Pupitre physique** : l'opérateur accepte-t-il de piloter chrono principal et chronomètre de
   tir sur **un boîtier à boutons** à côté du PC (plutôt qu'au clavier du PC) ? C'est ce qui
   rend la solution indépendante du navigateur.
2. **Corrections du temps de jeu** : sans canal retour, vaut-il mieux (a) n'afficher **que** le
   chronomètre de tir aux angles, (b) ajouter des boutons **±1 s** au pupitre, ou (c) réserver le
   temps de jeu aux angles aux tables équipées de Chromium ?
3. **Position de la table** : est-elle habituellement du **même côté** que les angles de
   l'arbitre (≈ 20 m) ou du côté opposé (≈ 30 m) ? Cela fixe la taille des chiffres.
4. **Face arrière public** : souhaitée d'emblée, ou en option ultérieure ?
5. **Fabrication** : existe-t-il un club, un fablab ou des bénévoles identifiés pour un
   prototype, et qui assurera la maintenance du parc ?
6. **Fabricants** : peut-on leur envoyer le questionnaire F1–F6 dès maintenant, et lesquels ?
