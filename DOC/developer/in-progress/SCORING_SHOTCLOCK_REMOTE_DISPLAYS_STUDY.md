# Étude — Afficheurs de chronomètre de tir déportés (console Scoring app4)

> **Statut** : étude / aide à la décision. Aucune ligne de code engagée.
> - v1 (2026-11-09) : options de transport, afficheurs LED + radio ;
> - v2 (2026-11-09) : réponses R1–R8, « qui est le maître ? », captation d'un système propriétaire ;
> - **v3 (2026-11-09)** : réponses R9–R14 (**deux opérateurs** à la table → révision de la v2),
>   **étude d'une application de scoring autonome (exécutable Windows/macOS/Linux)** (§6).
>
> **Contexte** : [PAGE_SCORING.md §6.5](../../specs/PAGE_SCORING.md) (chronomètre de tir, 3 commandes,
> raccourcis, diffusion `BroadcastChannel` / Mercure, Phase 4 offline) et
> [plan de refonte live §4.7](LIVE_MATCH_SCORING_REFACTORING_PROPOSALS.md) (relais matériel,
> *Hardware Scoring*). **Terminologie** : « chronomètre de tir » (code : `shotclock`).

---

## 1. Le problème

À la table de marque (bord de terrain, **en extérieur**), un PC portable ordinaire (Windows, macOS
ou Linux) exécute la console Scoring d'app4.

| Afficheur | Position | Solution |
|---|---|---|
| **Tableau de score** | face à la table | **HDMI + écran étendu** vers l'écran LED (route `games/[id]/scoreboard`) — ✅ réglé |
| **Chronomètres de tir ×2** | **dans les angles, du côté de l'arbitre et de la table** (ICF), tournés vers le terrain **et** le public | ❓ **objet de cette étude** |

Deux obstacles bloquent l'approche « une sortie vidéo par afficheur » : un portable n'a en
général **qu'une** sortie vidéo libre, et les angles sont à **~20 m** de la table (≈ 17,5 m de
demi-longueur + dégagements), bien plus en longueur de câble — le HDMI passif s'arrête vers
10–15 m.

## 2. Données d'entrée

### 2.1 Réponses (v2 et v3)

| # | Sujet | Réponse | Conséquence |
|---|---|---|---|
| R1 | Solutions propriétaires | Plusieurs existent, à l'étude ; **fabricants pas encore identifiés** | Ne pas en dépendre ; prévoir de pouvoir les brancher (§5.3) |
| R2 | Parc | **Jusqu'à 5 terrains** (internationaux à 4 → 1 jeu de rechange) | 5 bases + 10 afficheurs, 5 identifiants de terrain |
| R3 | Énergie | **Secteur** généralement disponible ; batterie = **option** pour petites organisations | Bloc 12 V de base, module batterie optionnel |
| R4 | Fabrication | **Maison, à étudier** ; partenaires non identifiés, pistes possibles | Conception documentée, pièces standard |
| R5 | Budget | **< 1 000 € / terrain, < 500 € si possible** | Élimine les options vidéo (§3) |
| R6 | Buzzer | **Pas de buzzer dans les angles**, seulement à la table (mini-enceinte sur le PC) | Buzzer = console (Web Audio) |
| R7 | Navigateur | **Difficile d'imposer un navigateur**, dernier recours | Voir §5.2 et §6 |
| R8 | Règlements | FFCK : visible des deux équipes, si possible du public ; ICF : dans les coins, **côté arbitre responsable** | Deux afficheurs d'angle sur la longueur de l'arbitre |
| **R9** | **Opérateurs** | **Chronométreur et chronométreur de tir sont deux personnes.** Les raccourcis du chronomètre de tir (`Entrée` / `.` / `0`) sont pensés pour un **pavé numérique déporté**, sans gêner le chronométreur au clavier du PC. Un boîtier à boutons à côté du PC pour le chronométreur de tir ne pose **aucune difficulté** | Le **chrono principal reste dans la console** (clavier du PC) ; les boutons du chronomètre de tir peuvent être sur un boîtier |
| **R10** | **Contenu aux angles** | **Chronomètre de tir seul** (le plus solide) | Afficheur 2 chiffres ; pas de temps de jeu à transporter |
| **R11** | **Distance** | Table **du même côté** que l'arbitre → **~20 m** | Chiffres ~20–25 cm suffisent, lisibles jusqu'à la diagonale du terrain (~40 m) |
| **R12** | **Face** | **Une seule face**, tournée vers le terrain **et** le public | Pas d'option face arrière |

### 2.2 Exigences techniques

| # | Exigence |
|---|---|
| E1 | **Lisible en plein soleil** du bout du terrain : 2 chiffres de **~20–25 cm**, luminosité *outdoor* (≥ 3 000–5 000 cd/m² ; moniteurs/TV grand public : 300–600 cd/m²) |
| E2 | **Deux afficheurs synchrones** entre eux et avec la console (écart < 0,1 s) |
| E3 | **Latence de commande < 200 ms** (60 s, 40 s, arrêt) |
| E4 | **Suspension automatique** du chronomètre de tir quand le **chrono principal** s'arrête, reprise quand il repart (PAGE_SCORING §6.5) ; **masquage** quand le temps de jeu restant est inférieur (`shotClockShow`) |
| E5 | **Tolérance aux coupures**, signalées aux opérateurs |
| E6 | **Aucune dépendance à Internet** pour le chronomètre de tir |
| E7 | **Robustesse extérieure** : IP54 minimum (IP65 conseillé), chaleur, vent |
| E8 | **Mise en place < 10 min par terrain**, appairage stable d'un jour à l'autre |
| E9 | **5 terrains côte à côte sans interférence** |
| E10 | **Coût < 500 € / terrain visé**, pièces standard |

> **Principe directeur — transmettre un *état*, pas une *image*.** On transmet l'état du
> chronomètre de tir (celui de `scoring_live_clock` kind `SHOTCLOCK` : `init_ms`, `elapsed_ms`,
> `running`) et **chaque afficheur décompte lui-même**, recalé par des trames d'état
> périodiques. Quelques octets ; latence et micro-coupures deviennent indolores.

## 3. Options écartées

| Option | Pourquoi | Coût / terrain |
|---|---|---|
| **A. Prolonger la vidéo** (dock DisplayLink + extendeurs HDMI Cat6 ou HDMI sans fil) | Un écran **lisible au soleil** par angle (1 500–3 000 € pièce), câbles au sol ou liaison vidéo 2,4/5 GHz saturée, pilotes fragiles | 3 500–6 000 € |
| **B. Navigateur dans chaque angle** (mini-PC/tablette abonnés à Mercure) | Dépend d'Internet ; mêmes écrans coûteux ; version locale bloquée par le *mixed content* (page HTTPS ⇏ `ws://` local) | 3 000–5 000 € |
| **E. LoRa 868 MHz** | *Duty cycle* européen incompatible avec des trames toutes les 250–500 ms ; portée inutile | — |

B reste valable **en dépannage** et pour les **écrans d'ambiance distants** (déjà dans la cible
Mercure).

## 4. Solution matérielle retenue : afficheurs LED + radio ESP-NOW

Inchangée depuis la v1, simplifiée par R10–R12 :

| Élément | Choix |
|---|---|
| Affichage | **2 chiffres 7 segments ~20–25 cm**, ruban LED adressable **12 V** (WS2815, tolère une LED défaillante) sous face PMMA teintée anti-reflet, **une face** |
| Commande | **ESP32**, radio **ESP-NOW** (sans routeur, latence de quelques ms, portée 100–200 m en vue directe ≫ 20 m) ; identifiant de terrain 1–5 par sélecteur rotatif ; canal radio réglable |
| Comportement | Décompte **local**, trame d'état complète toutes les 250–500 ms (recalage) ; au-delà de ~3 s sans trame : point clignotant (alerte), le décompte continue ; `--` à l'arrêt, extinction quand masqué |
| Énergie | Bloc **12 V étanche** sur secteur ; **option batterie** LiFePO4 12 V sur le même connecteur |
| Mécanique | Caisson IP65, pied enceinte lesté ou fixation poteau |
| Logiciel | Firmware versionné dans le dépôt (ex. `hardware/shotclock/`), mise à jour USB/OTA |

À la table, une **base** (ESP32-S3, USB natif) diffuse les trames aux deux afficheurs (même
trame → synchrones, E2) et remonte leurs **accusés de réception** (voyants de liaison, E5).

### 4.1 Budget indicatif (ordres de grandeur 2026, à confirmer par prototype)

| Poste | Par unité | Par terrain |
|---|---|---|
| Afficheur d'angle 2 chiffres (LED ~3 m, ESP32, caisson + face, bloc 12 V, fixation) | 140–180 € | 280–360 € (×2) |
| Base / boîtier du chronométreur de tir (ESP32-S3 + antenne, 3 gros boutons, 2 voyants, boîtier, câble USB) | 40–60 € | 40–60 € |
| **Total de base (secteur)** | | **≈ 320–420 €** ✅ |
| Option batterie (LiFePO4 12 V 10–12 Ah + chargeur) | +70–100 € / afficheur | +140–200 € |

**Parc de 5 terrains : ~1 600–2 100 €** sur secteur.

## 5. Qui pilote quoi ? (révision de la v2)

### 5.1 Ce que change R9 (deux opérateurs)

La v2 recommandait un **pupitre maître** portant **aussi** le bouton du chrono principal, la
console étant informée par un **clavier USB virtuel** — précisément pour éviter de devoir
envoyer des données **du navigateur vers le boîtier**.

Avec deux opérateurs, **le chrono principal reste dans la console**, au clavier du PC, entre les
mains du chronométreur. Conséquence : pour respecter **E4** (suspension automatique et
masquage), **la base doit connaître l'état du chrono principal**, qui n'existe que dans la
console. **Un canal console → base redevient indispensable.** Sans lui, la suspension du
chronomètre de tir à chaque arrêt de jeu reposerait sur un double geste des opérateurs — c'est
précisément le risque que la v2 voulait éliminer.

Le « clavier virtuel » de la v2 n'est donc plus suffisant seul ; il reste utile pour **capter un
système propriétaire** (§5.3).

### 5.2 Architecture révisée : base bidirectionnelle sur USB série

```
            Table de marque                                         Angles (côté arbitre)
 ┌──────────────────────────────────┐                             ┌──────────────────────┐
 │ PC — app4 Scoring                │                    radio    │  ┌──┐┌──┐            │
 │  chronométreur : clavier du PC   │                  ┌ ─ ─ ─ ─ ▶│  └──┘└──┘  ~25 cm    │
 │  (Espace = chrono principal)     │                  │ ESP-NOW  │  ESP32 · 12 V        │
 │                                  │  USB série       │          └──────────────────────┘
 │  état chrono + tir ─────────────▶│──┐ ┌──────────────┴─┐        ┌──────────────────────┐
 │  ◀──── appuis 60 / 40 / --  ─────│◀─┘ │ BASE ESP32-S3  │─ ─ ─ ─▶│  idem angle B        │
 │  ◀──── voyants liaison A / B ────│    │ [60] [40] [--] │        └──────────────────────┘
 │                                  │    │ voyants A / B  │◀── chronométreur de tir
 │  HDMI ─▶ écran LED score         │    └────────────────┘
 │  🔊 buzzer (mini-enceinte)       │
 └──────────────────────────────────┘
```

- **Le chronométreur de tir** appuie sur les boutons **de la base** (60 s / 40 s / `--`). La
  base **agit immédiatement** sur les afficheurs (latence minimale) et **transmet l'appui** à la
  console par le lien série.
- **La console reste la référence** : elle applique la commande, l'écrit dans `scoring_live_clock`
  comme aujourd'hui, et **renvoie l'état complet** (chrono principal en marche/arrêt, valeur du
  chronomètre de tir, masquage) à chaque changement et en *heartbeat*. En cas d'écart, **l'état
  de la console prévaut**.
- **Suspension et masquage** (E4) : la base les applique dès que la console signale l'arrêt du
  chrono principal ou un temps restant inférieur.
- **Pas de problème de focus** : les appuis arrivent par le port série, **pas par le clavier** —
  ils ne peuvent ni partir dans un champ de saisie du chronométreur, ni se perdre si la fenêtre
  du tableau de score a le focus. Le **pavé numérique déporté** (`Entrée` / `.` / `0`) reste un
  mode de secours sans matériel.
- **Coupure du lien** (PC figé, câble arraché) : les afficheurs continuent de décompter, les
  boutons de la base restent actifs, la base signale la perte (voyant) ; seules la suspension et
  le masquage automatiques sont perdus jusqu'au retour du lien.

**Le seul point dur restant : le canal série depuis le navigateur.** L'API **Web Serial**
n'existe que dans les navigateurs **Chromium** (Chrome, Edge, Opera, Brave) — pas Firefox, pas
Safari. Deux façons de ne pas l'imposer comme « navigateur de l'utilisateur » :

1. **Installer la console comme application (PWA) depuis Edge ou Chrome.** La console est déjà
   une PWA installable (PAGE_SCORING §0.8) : elle s'ouvre alors dans sa propre fenêtre, sans
   barre de navigation, avec une icône sur le bureau. **Edge est préinstallé sur tout Windows** :
   sur ce système, **rien à installer**. Sur macOS/Linux, il faut Chrome/Edge/Chromium **pour
   cette seule application**, l'utilisateur gardant son navigateur habituel pour le reste.
2. **Une application de scoring autonome** (exécutable) qui embarque son propre moteur — étudiée
   en §6.

Les autres canaux étudiés en v2 (Mercure via Internet, relais `localhost` à installer, signal
audio) restent écartés pour le chemin critique.

### 5.3 Systèmes propriétaires (captation du signal)

Inchangé dans son principe : un **adaptateur** ESP32-S3 lit les signaux du système (contacts,
RS-232/485, UDP…) et les présente à la console — par le **même lien série** que la base maison
(ou, à défaut, comme **clavier virtuel**, universel). Un seul contrat d'entrée côté console.

R9 renforce l'exigence de synchronisation : le chrono principal étant dans la console, le
système doit **accepter une entrée externe départ/arrêt** (question F3) pour que l'adaptateur lui
transmette les arrêts de jeu ; sinon double geste → à éviter.

Questionnaire fabricants (à envoyer quand ils seront identifiés) :

| # | Question |
|---|---|
| F1 | Sortie disponible (contacts/relais, RS-232/485, UDP/TCP, Bluetooth) ? Protocole **documenté** et réutilisation **autorisée** ? |
| F2 | Sort-il des **événements** (60 / 40 / `--`, départ/arrêt) ou la **valeur affichée** ? Fréquence ? |
| F3 | Accepte-t-il une **entrée externe départ/arrêt** (suspension sur le chrono principal) ? Gère-t-il un temps de jeu ? |
| F4 | Accepte-t-il une **entrée de pilotage** complète (commande par la console) ? |
| F5 | Radio, portée, coexistence de **5 jeux** sur un site |
| F6 | Lisibilité soleil (cd/m²), taille des chiffres, IP, alimentation/autonomie, garantie, pièces, **prix par terrain** |

## 6. Piste : application de scoring autonome (exécutable Windows / macOS / Linux)

### 6.1 Trois niveaux d'ambition

| Niveau | Contenu | Réutilisation du code app4 |
|---|---|---|
| **N1 — « Coquille »** | Un exécutable qui ouvre la console Scoring d'app4 (URL déployée, ou build embarqué) dans **son propre moteur Chromium**, avec quelques capacités natives | **~100 %** : même code Nuxt/Vue, mêmes appels api2, même Mercure |
| **N2 — Station de table autonome (*offline-first*)** | N1 + **stockage local durable** de l'état du match, **file d'écritures** synchronisée avec api2 au retour du réseau, **serveur local** pour les écrans du site (tableau de score sur un 2ᵉ PC, incrustations) sans Internet, **relais Hardware Scoring** local | Code app4 + une couche locale (stockage, synchro, serveur) |
| **N3 — Application native réécrite** (Qt, .NET, Flutter…) | Tout refaire | ❌ duplication complète de la console → **écarté** |

### 6.2 Choix technique pour N1/N2

| | **Electron** | **Tauri 2** |
|---|---|---|
| Moteur | **Chromium embarqué**, identique sur les 3 OS | WebView du système : WebView2 (Windows), **WebKit** (macOS), **WebKitGTK** (Linux) |
| Port série | **Web Serial natif** → **le code app4 de la §5.2 fonctionne tel quel** (sélection automatique du port de la base par identifiant USB) ; ou Node `serialport` | Pas de Web Serial dans WebKit → **plugin Rust** et **second chemin de code** |
| Taille | ~100–150 Mo | ~10–20 Mo |
| Rendu | Celui de Chrome, déjà testé | 3 moteurs à tester (WebKitGTK est le plus capricieux) |
| Mises à jour | `electron-updater` (GitHub Releases) | Updater intégré signé |

**Recommandation si on y va : Electron**, parce qu'il garantit le même moteur partout, et donc le
même code pour le navigateur et l'exécutable. La taille du téléchargement est secondaire pour
un poste de table.

### 6.3 Coût estimé

*Hypothèse : un développeur connaissant le code ; ordres de grandeur, hors tests terrain.*

| Poste | N1 Coquille | N2 Autonome offline-first |
|---|---|---|
| Prototype fonctionnel | 3–5 jours | — |
| Industrialisation : builds 3 OS en CI (GitHub Actions, runners macOS), **signature** du code, mises à jour automatiques, sélection auto du port série, ouverture du tableau de score en plein écran sur l'écran étendu, anti-veille | **3–5 semaines** au total | idem N1 |
| Stockage local + file d'écritures + idempotence (UUID déjà prévus) + reprise après crash | — | +4–6 semaines |
| Synchronisation / conflits avec api2 (match modifié ailleurs pendant la coupure), jetons d'authentification hors ligne, préchargement des feuilles de présence | — | +3–6 semaines |
| Serveur local (écrans du site, incrustations) + relais Hardware Scoring local | — | +3–4 semaines |
| **Total** | **~1 mois** | **~3–5 mois** |
| **Frais récurrents** | Apple Developer Program **~99 $/an** (notarisation obligatoire, sinon macOS bloque l'application) ; signature Windows **~100–400 €/an** selon la solution (sans elle : alerte SmartScreen) ; Linux : AppImage/deb sans frais | idem |
| **Maintenance** | Suivre les versions d'Electron (toutes les ~8 semaines, correctifs de sécurité) : **~1–2 jours/mois** ; compatibilité de version entre l'exécutable et api2 | + support de la synchronisation |

> **N2 recoupe la Phase 4 « offline » déjà prévue** (PAGE_SCORING §8, file d'écritures
> IndexedDB). Une PWA peut aussi la réaliser ; l'exécutable apporte surtout un **stockage plus
> durable** (pas d'éviction par le navigateur) et le **serveur local**, qu'une PWA ne peut pas
> offrir.

### 6.4 Ce que l'exécutable résoudrait, ou non

| Point | N1 | N2 | PWA installée via Edge/Chrome |
|---|:---:|:---:|:---:|
| Navigateur non imposé (R7) | ✅ | ✅ | ⚠️ Windows ✅ (Edge présent) ; macOS/Linux : Chrome/Edge à installer |
| Lien série vers la base (§5.2) | ✅ | ✅ | ✅ |
| Tableau de score ouvert automatiquement en plein écran sur l'écran étendu | ✅ | ✅ | ⚠️ possible (API Window Management, Chromium), avec autorisation |
| Choix de la sortie audio du buzzer | ✅ | ✅ | ⚠️ partiel |
| Anti-veille du PC | ✅ | ✅ | ✅ (Wake Lock) |
| Match entier **sans Internet** | ❌ | ✅ | ⚠️ après la Phase 4 |
| Écrans du site sans Internet (2ᵉ PC, incrustations) | ❌ | ✅ | ❌ |
| Relais Hardware Scoring en local | ❌ | ✅ | ❌ |
| **Coûts induits** | installation sur chaque PC (parfois sans droits admin), signature, 3 OS à maintenir, **deux façons** de lancer la console à supporter | idem + synchro | aucun |

### 6.5 Conclusion sur l'exécutable

- **Pour les seuls chronomètres de tir, l'exécutable n'est pas justifié** : la **PWA installée
  depuis Edge/Chrome** apporte le même lien série sans rien développer de plus, et **sans rien
  installer sous Windows**. L'exécutable N1 ne fait gagner que les postes macOS/Linux dont
  l'utilisateur refuse d'installer Chrome/Edge — pour ~1 mois de travail et des frais récurrents.
- **Il devient intéressant au niveau N2**, si les besoins *hors ligne* se confirment : match
  complet sans réseau, écrans du site et incrustations sur réseau local, relais Hardware Scoring
  local. C'est alors un **chantier à part entière (3–5 mois)**, à arbitrer avec la Phase 4.
- **Rien n'est perdu à attendre** : le code de la §5.2 (Web Serial) est **le même** dans la PWA
  et dans un futur exécutable Electron. On peut commencer par la PWA et basculer plus tard sans
  réécrire.

## 7. Recommandation (v3)

1. **Matériel** : afficheurs d'angle **2 chiffres** maison (LED 12 V, ESP32, ESP-NOW), base
   **ESP32-S3** à 3 boutons pour le chronométreur de tir — **≈ 320–420 € par terrain** sur secteur.
2. **Architecture** : **console de référence**, base reliée en **USB série** dans les deux
   sens — appuis du chronométreur de tir vers la console, état complet (dont le chrono principal,
   pour la suspension et le masquage) vers la base. Pavé numérique déporté en secours.
3. **Poste de table** : **console installée comme PWA depuis Edge** (Windows, rien à installer)
   **ou Chrome/Edge** (macOS/Linux). C'est la seule contrainte, limitée à cette application.
4. **Exécutable** : **pas maintenant**. Il sera à réévaluer au moment de la Phase 4 *offline*
   (niveau N2, Electron), ou si le parc macOS/Linux sans Chromium s'avère significatif.
5. **Systèmes propriétaires** : même contrat d'entrée console via un adaptateur ; questionnaire
   F1–F6 dès que des fabricants sont identifiés, en insistant sur **F3**.

### 7.1 Démarche

1. Valider cette v3 (questions §8).
2. **Spec** avec critères d'acceptation : protocole série (trames, versions, *heartbeat*,
   accusés), comportement de la base et des afficheurs en coupure, voyants, mode test
   d'avant-match, sélection automatique du port, mode secours pavé numérique.
3. **Prototype** : 1 base + 1 afficheur ; essai réel (soleil, 20–40 m, téléphones du public) ;
   Windows/Edge, macOS/Chrome, Linux/Chromium ; mesures de latence et de consommation.
4. **Pré-série** 1 terrain sur une compétition test, en parallèle du dispositif actuel.
5. **Série** 5 terrains ; guide montage/test/démontage dans `DOC/user/`.

## 8. Questions ouvertes (v3)

1. **Poste de table** : est-il acceptable d'installer la console Scoring **comme application
   depuis Edge** (déjà présent sous Windows) ou **Chrome** (macOS/Linux) — l'utilisateur gardant
   son navigateur pour le reste ? Sinon l'exécutable N1 (~1 mois + frais annuels) devient
   nécessaire.
2. **Parc de PC** : quelle part de postes **macOS/Linux** aux tables de marque ? Cela pèse sur
   le choix précédent.
3. **Besoin hors ligne** : des matchs entiers sans Internet sont-ils fréquents (sites sans 4G) ?
   Cela décidera du niveau N2 et du calendrier de la Phase 4.
4. **Boutons de la base** : 3 boutons (60 / 40 / `--`) suffisent-ils, ou faut-il aussi les
   ajustements ±1 s (aujourd'hui autorisés seulement chrono arrêté, `+` / `−`) ?
5. **Fabrication / fabricants** : à reprendre quand les pistes évoquées se préciseront.
