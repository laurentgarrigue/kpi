# Étude — Afficheurs de chronomètre de tir déportés (console Scoring app4)

> **Statut** : étude / aide à la décision (2026-11-09). Aucune ligne de code engagée.
> **Contexte fonctionnel** : [PAGE_SCORING.md §6.5](../../specs/PAGE_SCORING.md) (chronomètre de tir,
> 3 commandes, diffusion locale `BroadcastChannel` / distante Mercure) et
> [plan de refonte live §4.7](LIVE_MATCH_SCORING_REFACTORING_PROPOSALS.md) (relais matériel).
> **Terminologie** : « chronomètre de tir » (code : `shotclock`).

---

## 1. Le problème

À la table de marque (bord de terrain, **en extérieur**), un PC portable ordinaire (Windows, macOS
ou Linux) exécute la console Scoring d'app4.

| Afficheur | Public | Position | Solution |
|---|---|---|---|
| **Tableau de score** | joueurs + public en face | face à la table, de l'autre côté du terrain | **HDMI + écran étendu** vers l'écran LED (route `games/[id]/scoreboard`) — ✅ réglé |
| **Chronomètres de tir ×2** | joueurs (surtout l'attaque) | **aux deux extrémités du terrain, dans les angles** | ❓ **objet de cette étude** |

Deux obstacles bloquent l'approche « une sortie vidéo par afficheur » :

1. **La connectique** : un portable a en général **une seule** sortie vidéo libre une fois
   l'écran LED du tableau de score branché ;
2. **La distance** : un terrain de kayak-polo mesure ~35 × 23 m. Depuis la table (milieu d'une
   longueur), chaque angle opposé est à **~25–40 m** en ligne droite, et **plus de 40 m de câble**
   si on contourne le terrain et les zones de passage. Le HDMI passif s'arrête vers 10–15 m.

## 2. Exigences

Ce qu'un chronomètre de tir doit garantir, quelle que soit la solution :

| # | Exigence | Pourquoi |
|---|---|---|
| E1 | **Lisible en plein soleil à ~40 m** : chiffres de **≥ 20 cm** de haut, luminosité « outdoor » (≥ 3 000–5 000 cd/m², soit au-delà de tout moniteur ou TV grand public, qui plafonnent à 300–600 cd/m²) | c'est un afficheur d'arbitrage, pas un écran d'ambiance |
| E2 | **Les deux afficheurs synchrones** entre eux et avec la console (écart < 0,1 s, aucun saut visible) | un joueur ne doit jamais voir deux valeurs différentes |
| E3 | **Latence de commande < 200 ms** (départ/reset 60 s, 40 s, arrêt) | l'opérateur réagit au sifflet ; au-delà le décalage se voit |
| E4 | **Tolérance aux coupures** : une perte de liaison de quelques secondes ne doit ni figer ni faire sauter le décompte, et doit être **signalée** à l'opérateur | fiabilité match |
| E5 | **Aucune dépendance à Internet** | réseau souvent absent ou saturé sur les sites extérieurs |
| E6 | **Autonomie d'une journée** (8–12 h) sans secteur, ou alimentation secteur simple | pas toujours de prise dans les angles |
| E7 | **Robustesse extérieure** : pluie (IP54 minimum, IP65 conseillé), chaleur au soleil, vent (pied stable, lesté) | compétitions de plusieurs jours, plusieurs saisons |
| E8 | **Mise en place en < 10 min par terrain**, sans compétence réseau, avec appairage stable d'un jour à l'autre | bénévoles, montage chaque matin |
| E9 | **Multi-terrains sans interférence** (4 à 8 terrains côte à côte) | un afficheur ne doit obéir qu'à **sa** table |
| E10 | **Coût maîtrisé** et **maintenable** (pièces standard, remplaçables) | budget associatif, parc à faire durer |

> **Principe directeur — envoyer un *état*, pas une *image*.** Un chronomètre de tir n'affiche
> que deux chiffres. Transporter de la vidéo (HDMI, cast d'écran) jusqu'aux angles revient à
> payer cher pour transporter beaucoup de pixels sur une longue distance. Il suffit de
> transmettre l'**état** du chronomètre — exactement ce que le nouveau modèle sait déjà
> décrire (`scoring_live_clock` kind `SHOTCLOCK` : `init_ms`, `elapsed_ms`, `running`, cf.
> PAGE_SCORING §0.5) — et de laisser **l'afficheur décompter lui-même** avec sa propre horloge.
> Quelques octets suffisent, la latence et les micro-coupures deviennent indolores (E2–E4).

## 3. Solutions envisagées

### Option A — Prolonger la vidéo (HDMI déporté)

Ajouter des sorties (dock USB-C **DisplayLink** ou **splitter HDMI**) puis transporter le signal :

- **A1. Extendeurs HDMI sur câble réseau** (HDBaseT, Cat6 jusqu'à 70–100 m) ;
- **A2. Émetteurs HDMI sans fil** (gamme vidéo « prosumer », 50–150 m en vue directe).

| | |
|---|---|
| ✅ | Aucun développement ; réutilise la route `games/[id]/shotclock` existante |
| ❌ | Il faut **un écran par angle**, et un écran **lisible au soleil** coûte 1 500–3 000 € (moniteur haute luminosité) ; un téléviseur ordinaire est illisible dehors (E1) |
| ❌ | A1 : ~100 m de câble au sol, à protéger des passages, de l'eau, à dérouler/enrouler chaque jour ; un connecteur abîmé = afficheur noir |
| ❌ | A2 : 2,4/5 GHz saturé sur un site plein de téléphones ; liaison sensible aux obstacles (public, bateaux) ; alimentation des récepteurs dans les angles |
| ❌ | Le dock DisplayLink exige un pilote (fragile sous macOS/Linux) ; trois écrans étendus à gérer à la main à chaque match |
| 💶 | **~3 500–6 000 € par terrain** (2 écrans haute luminosité + transport + dock) |

**Verdict : déconseillé.** Cher, lourd à installer, fragile dans la durée, et la lisibilité
soleil reste le vrai coût.

### Option B — Navigateur dans chaque angle (écrans « intelligents » en réseau)

Un petit appareil autonome par angle (Raspberry Pi, clé Android TV, vieille tablette) ouvre en
mode kiosque une page `shotclock` qui **s'abonne à Mercure**.

| | |
|---|---|
| ✅ | Réutilise la diffusion distante prévue (Mercure, PAGE_SCORING §0.8) ; aucun câble vers la table |
| ❌ | **Dépend d'Internet** (E5) : la console publie vers le serveur KPI, les afficheurs y relisent. Latence 0,2–1 s, coupures 4G fréquentes en bord de plan d'eau |
| ❌ | Mêmes écrans que l'option A → même problème de luminosité et de coût (E1) |
| ❌ | Une version 100 % locale (routeur Wi-Fi à la table) se heurte à une limite du navigateur : la console est servie en **HTTPS** et **ne peut pas ouvrir de connexion `ws://` vers un appareil local** (*mixed content*). Il faudrait un serveur local avec certificat, donc un logiciel à installer et maintenir |
| 💶 | Écrans compris : **~3 000–5 000 € par terrain** ; sans écran adapté (tablettes) : ~300 €, mais **illisible au soleil** |

**Verdict : acceptable seulement en dépannage** (tablettes à l'ombre, compétition en salle).
Utile en revanche pour les **écrans d'ambiance distants** (buvette, zone d'attente), qui sont
déjà dans la cible Mercure.

### Option C — Afficheurs LED dédiés pilotés par radio, depuis le navigateur ⭐

Chaque chronomètre de tir est un **afficheur LED 2 chiffres** (segments géants, lisibles au
soleil) piloté par un **microcontrôleur ESP32**. À la table, une **« base » ESP32** branchée en
**USB** sur le PC reçoit l'état depuis la console via l'API **Web Serial** du navigateur, et le
**diffuse par radio** aux deux afficheurs.

```
 PC table de marque                                         Angle A
 ┌───────────────────────┐   USB    ┌──────────┐  radio   ┌──────────────────┐
 │ app4 Scoring (Chrome) │─────────▶│ Base     │─ ─ ─ ─ ─▶│ ESP32 + LED 2 ch.│
 │  store shotclock      │  Web     │ ESP32    │  2,4 GHz │ batterie 12 V    │
 │  └ useShotclockDevice │  Serial  │ (clé USB)│ ESP-NOW  └──────────────────┘
 │                       │◀─────────│          │─ ─ ─ ─ ─▶┌──────────────────┐
 │  HDMI ─▶ écran LED    │  accusés │          │◀─ accusés│ Angle B          │
 │        tableau score  │          └──────────┘          └──────────────────┘
 └───────────────────────┘
```

**Pourquoi c'est la bonne famille de solution**

- **Aucun écran, aucune vidéo** : deux chiffres LED haute luminosité, conçus pour l'extérieur
  (E1), pour une fraction du prix d'un moniteur.
- **Aucun réseau, aucun Internet, aucun logiciel à installer** (E5, E8) : le navigateur parle
  directement à la clé USB. Le reste de la console (Mercure, api2) peut tomber, les chronomètres
  de tir continuent.
- **ESP-NOW** (protocole radio d'Espressif, sans routeur ni appairage Wi-Fi) : trame de quelques
  dizaines d'octets, **latence de quelques millisecondes**, portée **100–200 m en vue directe**
  (davantage en mode *long range* et antenne externe) — largement assez pour 40 m (E3).
  Une **même trame diffusée** atteint les deux afficheurs simultanément (E2).
- **Accusés de réception** : la base remonte à la console, afficheur par afficheur, « reçu / perdu
  depuis X s » → la console affiche un **voyant de liaison** pour chaque angle (E4).
- **Le décompte est local** : l'afficheur reçoit « 60 s, en marche » et décompte avec son quartz ;
  une **trame d'état complète est renvoyée périodiquement** (heartbeat ~250–500 ms) qui recale
  la valeur. Une coupure de 2 s ne se voit pas ; au-delà d'un seuil (ex. 3 s sans trame), le
  point décimal clignote pour alerter, et à l'expiration l'afficheur continue de décompter jusqu'à
  0 (comportement sûr : il ne fige pas une valeur fausse).
- **Multi-terrains** (E9) : chaque base et ses afficheurs portent un **identifiant de terrain**
  (sélecteur rotatif/DIP sur le boîtier ou réglage mémorisé) ; les trames d'un autre terrain sont
  ignorées. Canal radio choisissable si un site est saturé.

#### Variantes de réalisation de l'afficheur

| Variante | Description | Coût indicatif / afficheur¹ | Remarques |
|---|---|---|---|
| **C1. Fabrication maison (« DIY »)** | Chiffres 7 segments de ~25–30 cm en **ruban LED adressable 12 V** (WS2815/SK6812) sous diffuseur PMMA, ESP32, boîtier étanche IP65, batterie **LiFePO4 12 V 20 Ah**, pied enceinte lesté | **200–350 €** | Coût minimal, pièces standard réparables, **firmware ouvert maîtrisé par KPI** ; demande un bricoleur (soudure simple) et un gabarit reproductible |
| **C2. Afficheur du commerce + carte de pilotage** | Afficheur « chronomètre de tir / compteur » LED extérieur générique (2 chiffres, entrée RS-485/RS-232 ou contacts), auquel on ajoute un ESP32 qui traduit la trame radio vers son entrée | **350–800 €** | Mécanique et étanchéité « finies » ; vérifier **avant achat** que le protocole d'entrée est documenté |
| **C3. Système de chronomètre de tir professionnel sans fil** | Gamme équipementier sportif (basket/water-polo), avec sa propre télécommande | **2 000–6 000 € la paire** | Robuste, garanti ; mais **double saisie** (télécommande ≠ console KPI) sauf si le fabricant expose une entrée de pilotage, ce qui est rare/payant |

¹ *Ordres de grandeur 2026, hors main-d'œuvre, à vérifier par devis.* La base USB coûte
**~15–30 €** (ESP32 avec antenne externe dans un petit boîtier).

**Budget par terrain (C1)** : 2 afficheurs + 1 base + 1 base de rechange ≈ **450–750 €**, à
comparer aux 3 500–6 000 € des options A/B.

#### Point d'attention : navigateur

**Web Serial** n'est disponible que dans les navigateurs **Chromium** (Chrome, Edge, Opera,
Brave) sur ordinateur — **pas Firefox, pas Safari**. Sur Mac, il faut donc utiliser **Chrome ou
Edge** pour la console Scoring. L'autorisation d'accès au port est donnée **une fois** (clic
« Connecter les chronomètres de tir ») puis retrouvée automatiquement aux ouvertures suivantes.
Alternative sans câble USB : **Web Bluetooth** vers la base (même restriction navigateur, mais
moins robuste qu'une liaison USB à la table) — non recommandé en premier choix.

### Option D — Liaison filaire RS-485 vers les afficheurs

Mêmes afficheurs que l'option C, mais reliés à la base par **un câble 2 paires** (RS-485 :
plusieurs centaines de mètres, insensible aux perturbations).

| | |
|---|---|
| ✅ | Le plus déterministe ; peut aussi transporter l'alimentation (12–24 V) et supprimer les batteries |
| ❌ | ~100 m de câble à poser autour du terrain chaque jour, à protéger des passages et de l'eau ; connecteurs à entretenir |
| 💶 | Câble + connecteurs étanches : 100–200 € par terrain en plus des afficheurs |

**Verdict : excellente roue de secours de l'option C.** Prévoir dans le firmware **les deux
liaisons** (radio et RS-485) ne coûte presque rien : si un site est saturé en radio, on tire un
câble, sans rien changer d'autre.

### Option E — LoRa 868 MHz

Portée kilométrique et bonne traversée des obstacles, mais **la réglementation européenne limite
le temps d'émission** (*duty cycle* 1 %, 10 % sur la sous-bande 869,4–869,65 MHz). Un heartbeat
toutes les 500 ms la dépasse vite. Sur 40 m, la portée de LoRa n'apporte rien. **Écartée**, sauf
site très particulier.

## 4. Comparatif

| Critère | A. HDMI déporté | B. Navigateur + Mercure | **C. LED + ESP-NOW + Web Serial** | D. LED + RS-485 |
|---|:---:|:---:|:---:|:---:|
| Lisible au soleil (E1) | 💶💶💶 | 💶💶💶 / ❌ | ✅ | ✅ |
| Synchro & latence (E2–E3) | ✅ | ⚠️ (Internet) | ✅ | ✅ |
| Tolérance coupure (E4) | ❌ écran noir | ⚠️ | ✅ décompte local + voyant | ✅ |
| Sans Internet (E5) | ✅ | ❌ | ✅ | ✅ |
| Mise en place (E8) | ❌ câbles + config écrans | ⚠️ Wi-Fi | ✅ allumer + brancher la clé | ⚠️ câbles |
| Multi-terrains (E9) | ✅ | ✅ | ✅ (ID terrain) | ✅ |
| Coût / terrain | 3 500–6 000 € | 3 000–5 000 € | **450–750 €** (C1) | C + 150 € |
| Développement KPI | aucun | faible | **moyen** (composable + firmware) | idem C |
| Contrainte poste | sorties vidéo | aucune | Chrome/Edge | Chrome/Edge |

## 5. Recommandation

**Option C1 (afficheurs LED maison, ESP32, ESP-NOW, base USB pilotée en Web Serial), avec la
liaison RS-485 de l'option D prévue dans le firmware comme secours.**

Elle répond à toutes les exigences, divise le coût par ~6, n'ajoute aucune dépendance réseau et
s'insère proprement dans l'architecture déjà décidée :

- la console reste la **source unique** de l'état du chronomètre de tir (store Pinia), comme
  pour `BroadcastChannel` (fenêtres locales) et Mercure (écrans distants) : on ajoute un
  **troisième canal de sortie**, « matériel local » ;
- le **contrat de données** est celui de `scoring_live_clock` (kind `SHOTCLOCK`) : pas de
  nouvelle logique métier, seulement une sérialisation ;
- les règles déjà spécifiées s'appliquent telles quelles : `--` à l'arrêt, masquage quand le
  temps de jeu restant est inférieur au chronomètre de tir (`shotClockShow`), suspension
  automatique quand le chrono principal s'arrête, buzzer à 0.

### 5.1 Esquisse d'intégration logicielle (pour mémoire, à spécifier avant tout code)

- **app4** : composable `useShotclockDevice` (Web Serial) abonné au store, à côté de
  `useScoringBroadcast` ; bouton « Connecter les chronomètres de tir » + **voyants de liaison
  A/B** dans la console ; mode **test** (affiche 88, fait clignoter, déclenche le buzzer) pour
  le contrôle d'avant-match.
- **Trame** (binaire compacte, versionnée) : `version, terrain, séquence, état (arrêté / en
  décompte / suspendu / masqué), valeur restante en dixièmes, buzzer, luminosité` + CRC.
  Renvoyée à chaque commande **et** en heartbeat.
- **Firmware** (base + afficheur) : versionné dans le dépôt (ex. `hardware/shotclock/`),
  mise à jour par USB ou OTA, réglages (ID terrain, luminosité, seuil d'alerte) persistés.
- **Option** : afficher aussi le **temps de jeu** sur une 2ᵉ ligne (afficheurs 4 chiffres),
  comme en basket — à décider (cf. questions).

### 5.2 Fiabilité sur plusieurs jours et plusieurs saisons

- **Énergie** : estimer la consommation réelle au prototype (ordre de grandeur 10–30 W selon
  luminosité) → batterie dimensionnée pour **1 journée + 30 %**, recharge la nuit ; prise
  secteur 12 V optionnelle sur le même connecteur.
- **Chaleur** : boîtier clair, aéré par chicanes, LED **pilotées en luminosité** (capteur de
  lumière ambiante) — moins de chaleur, plus d'autonomie.
- **Démarrage sans intervention** : l'afficheur s'allume, rejoint son terrain, affiche `--` ;
  aucun appairage à refaire chaque matin.
- **Parc** : un **afficheur de rechange** pour 3–4 terrains, une **base de rechange** par
  terrain (pièce à 20 €), une fiche « montage / test / démontage » d'une page.
- **Maintenance** : pièces standard (ruban LED, ESP32, batterie), firmware ouvert ; aucune
  dépendance à un fournisseur unique.

### 5.3 Démarche proposée

1. **Réponses aux questions ci-dessous** (§6) → figer les exigences.
2. **Spec** dédiée avec critères d'acceptation (conformément aux principes du projet) :
   contrat de trame, comportements en coupure, voyants, mode test.
3. **Prototype** : 1 base + 1 afficheur, test terrain réel (soleil, 40 m, téléphones du
   public), mesure de consommation et de latence.
4. **Pré-série** pour 1 terrain (2 afficheurs) sur une compétition test, en parallèle du
   dispositif actuel.
5. **Série** pour le parc de terrains ; documentation utilisateur dans `DOC/user/`.

## 6. Questions ouvertes

1. **Matériel existant** : le panneau de score propriétaire utilisé en *Hardware Scoring*
   comporte-t-il déjà des chronomètres de tir ? Si oui, peut-on les piloter (protocole) ?
2. **Parc** : combien de terrains simultanés au maximum sur une compétition (donc combien de
   jeux à produire) ?
3. **Énergie** : y a-t-il habituellement du secteur dans les angles, ou faut-il viser 100 %
   batterie ?
4. **Fabrication** : une réalisation « maison » (C1) est-elle envisageable (bénévoles bricoleurs,
   club partenaire, fablab), ou faut-il privilégier l'achat (C2/C3) ?
5. **Budget** cible par terrain ?
6. **Contenu** : les afficheurs d'angle doivent-ils montrer **uniquement** le chronomètre de tir,
   ou aussi le **temps de jeu** ? Un **buzzer** doit-il sonner dans les angles, ou seulement à la
   table ?
7. **Poste** : peut-on imposer **Chrome ou Edge** sur le PC de table (nécessaire pour Web
   Serial) ?
8. **Règlement** : existe-t-il une exigence ICF/FFCK sur la taille, la couleur ou la position des
   afficheurs de chronomètre de tir à respecter (homologation de compétition) ?
