# Plan de communication Facebook / Instagram — nouveautés KPI, saison 2027

**Créé le** : 21 octobre 2026 — **Révisé le** : 11 novembre 2026 (v4 : médailles de match révélées après la J1,
J1 fixée aux 13-14 mars, tournoi de Corbeil-Essonnes) — **Statut** : 📝 Proposition à valider
**Déclencheur** : les **plénières kayak-polo des 21 et 22 novembre 2026**
**Temps forts** : formation arbitre national (16-17 janvier 2027), **1re journée de championnat de France
(13-14 mars 2027), suivie de la révélation des médailles de match avec les premières médailles réelles**, **championnats d'Europe à Thury-Harcourt du 22 au
26 septembre 2027**, point d'orgue de la saison
**Sources** : [NOUVEAUTES.md](../../user/NOUVEAUTES.md), [APP2_APPLICATION_WEB.md](../../user/APP2_APPLICATION_WEB.md),
[PUBLIC_SITE_REDESIGN_STRATEGY.md](PUBLIC_SITE_REDESIGN_STRATEGY.md), [specs publiques](../../specs/public/README.md),
[PAGE_SCRUTINEERING.md](../../specs/PAGE_SCRUTINEERING.md), [PAGE_PRESENCE.md](../../specs/PAGE_PRESENCE.md),
[PAGE_SCORING.md](../../specs/PAGE_SCORING.md), [PWA_AUTO_UPDATE.md](../../specs/PWA_AUTO_UPDATE.md),
[FEATURE_MATCH_MEDALS.md](../../specs/public/FEATURE_MATCH_MEDALS.md),
[REALIGNEMENT_STRUCTURES_FFCK.md](REALIGNEMENT_STRUCTURES_FFCK.md)

> 🤫 **Les médailles de match sont gardées secrètes jusqu'à leur révélation, la semaine qui suit la J1** :
> absentes des plénières, du kit de relais, du kit formateurs et de toute publication avant (§ 3.4).

---

## 0. Dates repères

| Repère | Date | Statut |
|---|---|---|
| **Plénières kayak-polo** (représentants des régions, entraîneurs, membres de la CNA) | **samedi 21 – dimanche 22 novembre 2026** | ✅ Confirmé |
| Modernisation du design d'app3 | novembre 2026 | En cours |
| **Bascule d'app3** (phase 5, `www.kayak-polo.info`) | **semaine du 7 décembre 2026** recommandée | Moment à arrêter |
| **Formation arbitre national** | **samedi 16 – dimanche 17 janvier 2027** | ✅ Confirmé |
| **Tournoi de Corbeil-Essonnes** | mi-janvier 2027 (peut tomber le week-end de la formation) | Date à confirmer |
| Tournoi de fin février | 27-28 février 2027 par hypothèse | À confirmer |
| **1re journée de championnat de France (J1)** | **samedi 13 – dimanche 14 mars 2027** | ✅ Confirmé |
| **Révélation des médailles de match** (vague 1) | **semaine du 15 mars 2027**, dès que les matchs de la J1 sont validés ; Reel le jeudi 18 mars | Proposé (§ 3.4) |
| Entrée en vigueur du règlement 2027-2030 | 1er janvier 2027 par hypothèse | Les tournois de préparation l'appliquent-ils ? |
| Renommage des codes des compétitions régionales (`REGxx`, `T-Rxx`) en prod | Un jour sans compétition, avant la saison régionale ; régions prévenues aux plénières | Date à arrêter |
| **Championnats d'Europe, Thury-Harcourt** | **mercredi 22 – dimanche 26 septembre 2027** | ✅ Confirmé |

## 1. Objectifs

| # | Objectif | Indicateur (Matomo et suivi interne) | Cible |
|---|---|---|---|
| O1 | Les participants aux plénières **relaient** les nouveautés dans leur région et leur club | Régions ayant relayé le kit (§ 7) ; clics `mtm_source=relais` | Toutes les régions représentées ont relayé au 20 décembre |
| O2 | Joueurs et supporters **suivent les tournois et la J1 dans app2** | Visiteurs uniques app2 les week-ends de compétition ; visites `mtm_campaign=saison2027` | En hausse d'un tournoi à l'autre ; ×1,5 par rapport à la J1 2026 |
| O3 | Le public **adopte le nouveau site**, à tous les niveaux de pratique | Visites `www` ; pages clubs, carte et historique vues ; taux de 404 | 404 < 1 % des pages vues |
| O4 | Les **responsables d'équipes et de clubs** remplissent eux-mêmes les feuilles de présence | Feuilles de présence complètes avant la J1 | 100 % des équipes de la J1, le jeudi précédent |
| O5 | Les **arbitres** connaissent les outils : carton noir, chrono 60/40 dans la feuille de marque, scrutineering | Stagiaires de la formation sensibilisés ; équipes contrôlées à la J1 | Toutes les équipes de la J1 contrôlées dans app4 |
| O6 | Les championnats d'Europe se suivent **dans KPI, en anglais**, partout en Europe | Visiteurs uniques app2 / app3 pendant les Europe ; part des visites en anglais et hors de France ; équipes contrôlées par les arbitres internationaux | Record de fréquentation de KPI ; toutes les équipes contrôlées |
| O7 | Les **médailles** font parler des matchs, et reviennent à chaque journée | Vues de la page `/medals` et des feuilles de match app2 la semaine de la révélation ; partages et mentions des publications médailles ; `mtm_content=medailles*` | Feuilles de match de la J1 vues ×2 par rapport à la J1 2026, sur la semaine qui suit ; chaque nouvelle vague fait mieux que la précédente en interactions |

Les cibles chiffrées sont à recaler sur les chiffres 2026 relevés dans Matomo (chantier C1).

## 2. Publics, réseaux, langue

| Public | Canal principal | Langue | Message clé | Appel à l'action |
|---|---|---|---|---|
| **Relais** : représentants des régions, entraîneurs, CNA | **Plénières** (en présentiel), puis kit de relais | FR | « Voici ce qui arrive cette saison, et comment le faire savoir » | Relayer le kit dans sa région et ses clubs |
| **Joueurs et supporters**, tous niveaux (N1, N3, féminines, régionaux) | Instagram (Reels, stories, carrousels), Facebook en relais | FR (+ EN sur clubs et Europe) | « Tous les matchs, scores et classements, et ton club sur la carte » | Installer app2 ; trouver son club sur le site |
| **Responsables d'équipes et de clubs** (profil 7) | Facebook | FR | « Votre feuille de présence prête avant la journée, en quelques clics » | Remplir sa feuille de présence dans app4 |
| **Organisateurs, tables de marque** | Facebook | FR | « Carton noir, chrono de tir 60/40 : la feuille de marque est à jour » | Se connecter à app4, lancer le tutoriel |
| **Arbitres nationaux et juges arbitres** | **Formation arbitre national**, Facebook, message direct | FR | « Nouveau : le contrôle équipement sur tablette ou téléphone » | Demander son accès « Contrôle équipement » |
| **Arbitres internationaux** | Facebook, Instagram, relais par les fédérations et le comité d'organisation des Europe | **EN** | « New: equipment scrutineering in KPI » | Ask for a scrutineer account |
| **Public international** des Europe (équipes, familles, fédérations, supporters) | Instagram, Facebook, comptes du comité d'organisation | **EN** (+ FR) | « Follow the European Championships live in KPI » | Install app2, scan the QR code |

**Règle de répartition** : Instagram parle aux joueurs, aux supporters et au public international ; Facebook porte
aussi les contenus pratiques (clubs, organisateurs, arbitres).

**Ton** : tutoiement sur Instagram, vouvoiement sur Facebook pour les contenus pratiques ; un bénéfice par
publication ; une capture réelle à chaque fois, sur des données publiques. Jamais une fiche licencié ni un écran
d'administration avec des données personnelles : les captures d'app4 utilisent une compétition de démonstration
ou des noms floutés. Photos de personnes (plénières, formation, Europe) : avec leur accord.

**Liens et mesure** : lien en bio, autocollant lien en story, QR code d'app2 sur les visuels. Chaque lien porte
`?mtm_campaign=<campagne>&mtm_source=instagram|facebook|relais&mtm_content=<sujet>`, avec
`<campagne>` = `saison2027` jusqu'à mars, puis `euro2027`.

**Hashtags** : `#kayakpolo` `#canoepolo` `#kayakpoloinfo` `#KPIsaison2027` ; à partir de la révélation, `#médaillesKPI` ;
pour les Europe, le hashtag officiel de l'événement (à obtenir du comité d'organisation, chantier C40) +
`#canoepolo` + `#KPImedals`.

## 3. Les nouveautés mises en avant

Seules les fonctionnalités **en production à la date de publication** sont annoncées comme disponibles (contrôle
du jeudi, § 9). Aux plénières et à la formation, on distingue clairement **« disponible »** et **« à venir »**.

### app2 — l'application de résultats (`app.kayak-polo.info`)

| Nouveauté | Message | Format |
|---|---|---|
| **Frise chronologique du match** dans la feuille de match (buts, cartons, par période, une ligne par équipe) | « Le match minute par minute » / « Every goal, every card, minute by minute » | Reel enregistrement d'écran : **contenu phare d'app2**, en FR puis en EN pour les Europe |
| Feuille de match (composition, buts, cartons) au clic sur un score ou un numéro de match | « Qui a marqué ? Touche le score » | Intégré au Reel précédent |
| Accueil **« À venir »** (prochains rendez-vous, badge « En cours ») | « Le prochain tournoi en un coup d'œil » | Story |
| Mode **Compétitions** et lien direct par catégorie (`/group/2027/N1H`) | « Suis tout le championnat, pas seulement une journée » | Carrousel |
| Fiche équipe et **Progression** (classements, tableaux) | « Le parcours de ton équipe » | Carrousel |
| **QR code** de l'événement | « Un scan et tout le plan d'eau suit les scores » | Affiche A4 pour les organisateurs ; affiches FR/EN aux Europe |
| **Installation** sur l'écran d'accueil, **hors ligne** | « Pas de réseau au bord du bassin ? Elle marche quand même » | Reel tutoriel iPhone + Android, FR puis EN |
| **Thème sombre**, **français / anglais** | « Mode sombre » / « Follow in English » | Stories |

### app3 — le nouveau site (`www.kayak-polo.info`)

| Nouveauté | Message | Format |
|---|---|---|
| Nouveau site, design modernisé, mobile d'abord | « Le site fait peau neuve » | Avant-première aux plénières (sur `beta.*`), Reel de lancement en janvier |
| **Clubs : liste, carte, fiche** | « Trouve ton club, et tous les autres » | Série « Tous les niveaux » (ci-dessous) |
| **Historique et palmarès** | « Ton club a une histoire » | Carrousels par niveau, quiz ; palmarès européen avant les Europe |
| Pages compétition (matchs, terrains, classement, progression, statistiques) | « Toute la compétition sur une page » | Carrousel ; page des Europe en EN |
| **Calendrier** et **abonnement ICS** | « Les dates de ta compétition dans ton agenda » | Reel tutoriel ; ICS des Europe en EN |
| **Recherche globale** | « Une équipe, un club : tape, trouve » | Story |
| Passerelle « Suivre dans l'app » vers app2 | « Le site pour tout savoir, l'app pour le jour J » | Visuel |
| Formulaires d'inscription et HelloAsso | — | **Hors campagne tant que le test de bout en bout n'est pas validé** (C11) ; créneau de réserve en S10 |

**Série « Tous les niveaux »** (une équipe ou un club par publication, même mise en page : fiche club → carte →
historique → équipe dans app2) :

| Publication | Exemple à choisir | Langue |
|---|---|---|
| 1 | Un club de **N1** hommes (palmarès riche) | FR |
| 2 | Une équipe **féminine** (N1F ou équipe en progression) | FR |
| 3 | Un club de **N3** | FR |
| 4 | Un club **régional** (championnat régional, club récent ou petit) | FR |
| 5 | Une équipe **U21 / jeunes** (facultatif) | FR |
| 6 à 8 | **2 ou 3 clubs norvégiens**, comptes Instagram **tagués** | **EN** |

La série en anglais prépare les Europe : elle se prolonge au printemps par des clubs d'autres nations
qualifiées (§ 6, phase 6). Les clubs exemples sont prévenus avant publication et invités à partager.

### app4 — l'administration (`/admin2`)

| Nouveauté | Pour qui | Format |
|---|---|---|
| **Contrôle équipement (scrutineering)** : nouvelle fonctionnalité. Par joueur : kayak, gilet, casque, pagaies valides, commentaire ; accès dédié « Contrôle équipement » | Juges arbitres (J1), arbitres internationaux (Europe) | Démonstration à la formation, Reel FR **et EN**, pas à pas FR **et EN**, guide PDF bilingue |
| **Feuilles de présence pas à pas**, dont la **copie de composition** | Responsables d'équipes et de clubs | Carrousel FB + guide PDF + Reel |
| **Carton noir** et **chrono de tir 60/40** dans la feuille de marque | Tables de marque, arbitres, organisateurs | Formation arbitre, publication FB + Reel (fil règlement, § 4) |
| Contexte de travail (événement / compétition) | Organisateurs | Publication FB |
| Vérification des compositions (licence, certificat, pagaie couleur, surclassement), règles d'éligibilité | Clubs, resp. compétition | Carrousel FB |
| Passage des mandats à la nouvelle saison, ajout des équipes depuis le classement précédent | Resp. division / compétition | Plénières, publication FB |
| Réinitialisation du mot de passe par e-mail, tutoriel guidé, thème sombre | Tous | Story, Reel court |
| **Codes des compétitions régionales alignés sur les CR actuelles** (`REG07…` Bretagne → `REG03…`), une Sélection régionale par CR, structures synchronisées chaque jour avec le PCE | Responsables régionaux, resp. compétition | **Plénières** (diapositive dédiée), message direct aux régions avant le renommage en prod ; pas de publication grand public |

### 3.4 Médailles de match — la surprise d'après la J1

**Ce que c'est** ([FEATURE_MATCH_MEDALS.md](../../specs/public/FEATURE_MATCH_MEDALS.md)) : à partir de la feuille de
marque déjà saisie (buts et cartons horodatés), KPI attribue des **médailles** aux équipes, aux joueurs et aux
arbitres (but en supériorité, cage inviolée, but en or…), affichées dans un **blason** sur la feuille de match et la
page équipe d'app2, et dans les statistiques, les fiches équipes et la page `/medals` du site. Rien n'est saisi en
plus. Chaque médaille se **publie** (app2, app3) ou se retire **depuis l'admin, sans déploiement ni recalcul**.

**Secret jusqu'à la révélation** : aucune mention aux plénières, dans le kit de relais, le kit formateurs ou les
publications avant la semaine du 15 mars.

**Révélation après la J1, pas pendant** : une médaille n'est calculée que sur un match **validé** (à la validation
dans app4, ou par la tâche planifiée toutes les 10 minutes). Rien ne garantit une saisie et une validation en direct
à la J1 ; on attend donc que ses matchs soient validés pour publier la vague 1, et la révélation montre les
**premières médailles réelles de la saison 2027**, obtenues sur cette journée : des joueurs, des équipes et des
arbitres que le public connaît, plutôt qu'une démonstration. La fonctionnalité est déployée en production **sans aucune médaille publiée**
(C30) ; l'équipe vérifie les calculs dans l'admin sur les tournois de janvier et de février.

**Principe** : peu de médailles au départ (6), puis de **nouvelles médailles « débloquées » au fil de la saison**, en
commençant par les **plus rares (moins de 0,2 médaille par match)** : elles ont plus de valeur, et chaque vague
donne une raison de revenir. Les médailles fréquentes (premier but, triplé, but de la victoire) arrivent en dernier,
juste avant les Europe.

**Fréquences mesurées** sur la saison 2026 (base de dev, 1 866 matchs éligibles dont 1 489 à timeline fiable),
en médailles par match éligible ; pour une journée d'environ 100 matchs, multiplier par 100 :

| Vague | Date proposée | Médailles publiées (app2 + app3) | Médailles / match | Pourquoi |
|---|---|---|---|---|
| **1 — Révélation** | **Semaine du 15 mars**, après validation des matchs de la J1 | Supériorité · Powerplay (É, J) · Cage inviolée · Clean Sheet (É) · Entrée express · Fast Start (J) · Triplé consécutif · Natural Hat-trick (J) · Infériorité · Shorthanded (É, J) · But en or · Golden Goal (É, J) + classements **Médailles des joueurs** et **Médailles des équipes** | 0,18 · 0,12 · 0,10 · 0,06 · 0,05 · 0,02 | Six familles différentes (élan, supériorité, défense, finition, endurance), toutes rares ; ~50 médailles sur une journée de 100 matchs : de quoi illustrer chaque médaille par un exemple réel de la J1, assez peu pour compter |
| **2 — Le suspense** | J2 (avril, date à confirmer) | Réplique · Bounce Back (É, J) · Quadruplé · Four-goal Haul (J) · Dernières secondes · Late Winner (É, J) · Remontée · Comeback (É) · Égalisation salvatrice · Lifeline (É, J) | 0,13 · 0,19 · 0,02 · < 0,01 · < 0,01 | Les médailles du « money time » : des histoires de fin de match à raconter |
| **3 — Les arbitres** | Mai-juin (une journée nationale) | Match couperet · Knockout · Prolongation · Extra Time · But en or · Golden Goal · Suspense · Thriller (arbitres) + étiquette **Suspense · Thriller** + Résistance · Penalty Kill · Prolongation · Marathon · Retour au score (équipes) + statistique **Médailles des arbitres** et **Matchs arbitrés** (app3) | 0,11 · 0,02 · 0,02 · < 0,01 · — · 0,01 · 0,03 · < 0,01 · < 0,01 | Valoriser l'engagement des arbitres ; jamais de classement par cartons (spec § 3.5) |
| **4 — Grand public, en anglais** | Début septembre, avant les Europe | Premier but · Opener · But de la victoire · Game Winner · Triplé · Hat-trick · Série · Scoring Run · De bout en bout · Wire to Wire · Large victoire · Big Win · Match fluide · Smooth Game (arbitres) · Fair-play (app3) + statistiques **Supériorité %** et **Premier but gagnant** (app3) | 0,80 · 0,73 · 0,55 · 0,37 · 0,35 · 0,30 · 0,64 · 1,6 | Les médailles fréquentes, faciles à comprendre pour un public international qui découvre KPI aux Europe |
| Non publiées | — | But de la délivrance · Tiebreaker (se confond avec *But de la victoire*), Calme plat · Dead Calm (aucun cas en 2026), Chassé-croisé, Festival, Partie d'échecs, statistiques d'arbitrage autres que les deux ci-dessus | 0,11 · 0 · … | Restent dans l'admin ; à réévaluer pour la saison 2028 |

Les fréquences sont à **remesurer sur la prod** (C33) : une médaille qui dépasse 0,2 par match passe dans une vague
plus tardive. L'ordre exact des vagues 2 à 4 peut suivre l'actualité (ex. publier *But en or* arbitre le week-end
d'un match décidé en prolongation). Écart avec la spec : le « premier lot public » de la spec (§ 4) est remplacé par
ce calendrier de publication (C31).

**Messages** : « Chaque match a son histoire. Désormais, il a ses médailles. » / « Every game tells a story. Now it
has medals. » ; à chaque vague, « Nouvelle médaille débloquée » avec le blason, l'explication et un exemple réel.

## 4. Fil parallèle : règlement sportif 2027-2030

| | **Page Facebook de la discipline** | **Comptes KPI** (ce plan) |
|---|---|---|
| Objet | **Toutes** les nouveautés du règlement 2027-2030 | Uniquement celles **qui touchent KPI** |
| Contenu | Communication distincte, hors de ce plan (listing à réaliser, C13) | « Comment KPI applique la règle » : feuille de marque, feuille de match app2, statistiques |
| Ordre | **Publie d'abord** la règle | **Relaie ensuite**, dans les 48 h, avec la partie outil |
| Présentiel | Plénières (CNA), formation arbitre national | Même support : une diapositive « dans KPI » par règle concernée |

Nouveautés connues qui touchent KPI (liste à compléter par le listing, C13) :

| Règle | Ce qui change dans KPI | Publication KPI |
|---|---|---|
| **Carton noir** à la place du carton rouge définitif | Saisie dans la feuille de marque, icône dans app2 (feuille de match, frise), statistiques et classements (critères cartons, Honourable Play), PDF, affichages TV et live | « Le carton noir arrive dans KPI » : avant/après de la feuille de match app2 |
| **Chrono de tir 60 s et 40 s** | Deuxième valeur de remise à zéro dans la feuille de marque (aujourd'hui fixée à 60 s), affichage du chrono de tir (écrans, TV) | Reel « 60 ou 40 ? » côté table de marque |
| Autres (selon le listing) | À évaluer : durées, prolongations, effectifs… | Selon impact |

Si les règles européennes appliquées aux Europe diffèrent du règlement français (à vérifier avec le comité
d'organisation, C40), KPI doit gérer les deux, et la communication EN ne montre que les règles des Europe.

## 5. Rythme

| Période | Instagram | Facebook |
|---|---|---|
| Après les plénières, décembre (phase 0) | 1 publication / semaine | 1 à 2 publications / semaine |
| Janvier – fin mars (phases 1 à 5) | 3 publications (dont **1 Reel**) + 4 à 6 stories / semaine | 3 à 4 publications / semaine |
| Avril – juin (phase 6a) | 1 à 2 publications / semaine, + 1 Reel « médaille débloquée » par vague, + « les médailles de la journée » après chaque journée nationale | 1 à 2 publications / semaine |
| Juillet – août (phase 6b) | 2 publications + stories / semaine, moitié en EN | 2 publications / semaine |
| Septembre jusqu'aux Europe (phase 6c) | 3 à 4 publications / semaine, en EN d'abord | 3 publications / semaine |
| Pendant les Europe (22-26 sept.) | Stories en continu, 1 publication / jour (résultats) | 1 à 2 publications / jour |

Jours habituels : **mardi, jeudi, samedi**. Stories en direct pendant chaque week-end de compétition.

## 6. Calendrier

Légende : 🅸 Instagram · 🅵 Facebook · 🎬 Reel · 🖼 carrousel · ⭕ story · **EN** en anglais · 📜 fil règlement ·
🎤 en présentiel

### 🎤 Plénières — samedi 21 et dimanche 22 novembre 2026 : déclencheur

Présentation de 20 à 30 minutes (support C26, une quinzaine de diapositives), en démonstration réelle :

1. **Disponible aujourd'hui** : app2 (frise du match, « À venir », mode Compétitions, QR code, mode sombre), app4
   (contexte de travail, mandats 2027, équipes depuis le classement précédent, vérification des compositions,
   **contrôle équipement**).
2. **En avant-première** : le nouveau site sur `beta.kayak-polo.info` (nouveau design), date de bascule.
3. **Les règles 2027-2030 dans KPI** : carton noir, chrono 60/40, calendrier de mise à disposition ; la nouvelle
   console de marque (scoring) présentée comme **« à venir »**, sans date promise.
4. **Ce qui change pour les régions** : codes des compétitions régionales alignés sur les CR actuelles, Sélection
   régionale unique par CR, structures synchronisées avec le PCE ; anciens liens non redirigés.
5. **Le calendrier de la saison** : tournoi de Corbeil-Essonnes, formation arbitre, **J1 des 13-14 mars**,
   **Europe de Thury-Harcourt**.
6. **Ce qu'on attend des participants** : relayer le kit (C27), remonter les comptes des responsables d'équipes,
   proposer des clubs exemples pour la série « Tous les niveaux », signaler les codes régionaux à corriger.

🤫 **Pas un mot des médailles** (§ 3.4), pas même une allusion sur la diapositive du calendrier.

**Si le plan n'est pas prêt** (C0, C26 ou C27 manquants) : on présente quand même les points 1 à 4, et la
phase 0 démarre le lundi 7 décembre au lieu du 23 novembre.

### Phase 0 — Après les plénières (23 novembre – 20 décembre 2026) : « Ce qui arrive cette saison »

| Semaine | Publications |
|---|---|
| **23-29 nov.** | 🅵 Retour sur les plénières (photos, accord des personnes) : « Ce qui arrive cette saison », lien vers le kit de relais · 🅸 🖼 « 2027 arrive : résultats, site, outils, et les Europe en France » |
| **30 nov. – 6 déc.** | 🅸🅵 🎬 La frise du match dans app2 (disponible) · 🅵 Envoi du kit aux régions qui ne l'ont pas encore reçu |
| **7-13 déc.** — bascule | Pas de campagne sur le site : on surveille les 404, les redirections et Matomo. Au plus une 🅵 brève « Le site change d'habit, dites-nous si quelque chose vous manque » |
| **14-20 déc.** | 🅵 📜 Relais de l'annonce du règlement par la page de la discipline · 🅸🅵 « Save the date » : Europe 2027 à Thury-Harcourt, du 22 au 26 septembre |
| **21 déc. – 3 janv.** | Pause (fêtes) |

### Phase 1 — Lancement (S1 et S2) : « Nouvelle année, nouveau site, nouveaux arbitres »

| Semaine | Mardi | Jeudi | Samedi / dimanche |
|---|---|---|---|
| **S1** 4-10 jan. | 🅸🅵 🎬 **Lancement officiel du nouveau site** (Reel + publication épinglée) | 🅸🅵 🖼 Visite guidée : compétitions, calendrier, clubs, carte, historique, recherche | 🅸🅵 🖼 « Tous les niveaux » n° 1 (club de N1) |
| **S2** 11-17 jan. — **formation arbitre national** | 🅸🅵 🎬 Installer app2 avant le tournoi de Corbeil-Essonnes (iPhone + Android) | 🅵 Affiche QR code envoyée à l'organisateur du tournoi | 🎤 **Formation** : séquence courte des formateurs (C28), dont la consigne « chaque but et chaque carton avec son temps » (elle nourrit la frise, et en secret les médailles) · 🅵 photos de la formation « les futurs arbitres découvrent le carton noir et le contrôle équipement dans KPI » |

**Tournoi de Corbeil-Essonnes** (mi-janvier, date à confirmer) : 🅸 ⭕ stories du tournoi (scores, frise) le
week-end même, 🅸🅵 🖼 bilan le mardi suivant (S3 ci-dessous). S'il tombe le week-end de la formation, une seule
publication Facebook par jour (§ 9) ; s'il tombe en S1 ou en S3, l'installation d'app2 et l'affiche QR code
glissent à la semaine du tournoi. L'équipe vérifie dans l'admin les médailles calculées sur ses matchs (C33).

### Phase 2 — Approfondissement (S3 à S5) : « Le site et l'app pour tous »

| Semaine | Mardi | Jeudi | Samedi |
|---|---|---|---|
| **S3** 18-24 jan. | 🅸🅵 🖼 Bilan du tournoi de Corbeil-Essonnes + merci à l'organisateur et aux formateurs | 🅸🅵 🖼 « Tous les niveaux » n° 2 (équipe féminine) | 🅸🅵 🖼 **EN** « All levels » : club norvégien n° 1 (tagué) |
| **S4** 25-31 jan. | 🅵 Préparer la saison dans app4 : mandats 2027, équipes depuis le classement précédent | 🅸🅵 🎬 S'abonner au calendrier de sa compétition (ICS) | 🅸🅵 🖼 « Tous les niveaux » n° 3 (club de N3) |
| **S5** 1-7 fév. | 🅵 📜 **Le carton noir dans KPI** | 🅸🅵 🖼 Historique et palmarès : quiz « Qui a gagné… ? » | 🅸🅵 🖼 « Tous les niveaux » n° 4 (club régional) · **EN** club norvégien n° 2 |

### Phase 3 — Outils de la journée (S6 et S7) : « Prêts pour les compétitions »

| Semaine | Mardi | Jeudi | Samedi |
|---|---|---|---|
| **S6** 8-14 fév. | 🅵 🖼 **Feuilles de présence pas à pas**, dont la **copie de composition** + guide PDF | 🅸🅵 🎬 **Nouveau : le contrôle équipement** (FR) · 🅵 appel aux juges arbitres pour leur accès | 🅸🅵 🎬 **EN** « New: equipment scrutineering in KPI » + 🖼 **EN** pas à pas |
| **S7** 15-21 fév. | 🅵 📜 🎬 **Chrono de tir 60/40** côté table de marque | 🅵 🖼 Contrôle équipement pas à pas (FR) + guide PDF bilingue | 🅸🅵 🖼 Fiche équipe et Progression dans app2 |

### Phase 4 — Tournoi de fin février, préparation et J1 (S8 à S10)

| Semaine | Mardi | Jeudi | Samedi / dimanche |
|---|---|---|---|
| **S8** 22-28 fév. — tournoi | 🅵 Rappel aux responsables : feuilles de présence avant le jeudi 11 mars | 🅸🅵 Liens de suivi du tournoi + QR code | 🅸 ⭕ stories en direct, dont **premier carton noir / chrono 40 s** si les règles s'appliquent |
| **S9** 1-7 mars | 🅵 Check-list organisateur et table de marque + PDF (rappel : temps de chaque but et carton) | 🅸🅵 🖼 Bilan du tournoi de fin février · 🅵 test de connexion des juges arbitres (C18) | 🅸🅵 🖼 « Tous les niveaux » n° 5 ou **EN** club norvégien n° 3 |
| **S10** 8-14 mars — **J1, 13-14 mars** | 🅸🅵 🖼 « Tout pour suivre la J1 » : liens directs par catégorie · 🅵 dernier rappel présences | Contrôle du jeudi (11 mars) : feuilles de présence complètes, juges arbitres connectés | 🅸 ⭕ stories en direct (scores, frise) · 🅵 liens de suivi — **rien sur les médailles** |

### Phase 5 — Révélation des médailles et suivi de la J1 (S11 et S12) : 15-28 mars

| Semaine | Lundi / mardi | Jeudi | Samedi |
|---|---|---|---|
| **S11** 15-21 mars — **révélation** | **Lundi** : matchs de la J1 validés ? publication de la vague 1 dans l'admin dès qu'ils le sont (C34), contrôle sur app2 et app3, choix des exemples réels · **mardi** 🅸🅵 🖼 Bilan de la J1 en chiffres, merci aux organisateurs et aux juges arbitres · 🅸 ⭕ teaser « Jeudi, la J1 n'aura pas tout dit 👀 » | 🅸🅵 🎬 **Reel de révélation** « Chaque match a son histoire. Désormais, il a ses médailles » : les blasons, puis les **vraies médailles de la J1** (feuille de match app2, joueurs et équipes tagués avec leur accord), lien `/medals` | 🅸🅵 🖼 « Les médailles de la J1 » (une diapositive par médaille, un exemple réel chacune) · 🅸 ⭕ « Trouve tes médailles » : page équipe d'app2 |
| **S12** 22-28 mars | 🅸🅵 🖼 « Les 6 médailles expliquées » (page `/medals`) | 🅵 Appel aux retours (sondage ou formulaire) | **Réserve** : inscriptions en ligne et HelloAsso **si** validées (C11) ; sinon 🅸 ⭕ quiz « Quelle médaille arrive ensuite ? » |

Si les matchs de la J1 ne sont pas validés le mercredi 17 mars, le Reel glisse au jeudi suivant (S12) et S12
reprend le bilan ; on ne publie jamais la vague 1 sur une journée à moitié validée.

### Phase 6 — En route vers Thury-Harcourt (mi-mars – 26 septembre 2027)

Coordonnée avec le comité d'organisation et la fédération (C40) : KPI parle **des outils** (résultats, site,
contrôle équipement), le comité d'organisation parle **de l'événement** ; chacun relaie l'autre.

| Période | Thème | Publications types |
|---|---|---|
| **6a — fin mars à juin** | Le championnat en cours, les médailles qui se débloquent, et l'Europe en ligne de mire | 🅸🅵 résultats des journées (liens app2) ; 🅸🅵 🖼 « Les médailles de la journée » après chaque journée nationale ; 🅸🅵 🎬 **vague 2** (J2, avril) puis **vague 3** (arbitres, mai-juin) : un Reel « Nouvelle médaille débloquée » le samedi de la publication, un carrousel d'explication le mardi suivant ; 🅵 vague 3 relayée auprès des arbitres (message direct) ; 🅸🅵 🖼 **EN** « All levels » : un club par nation qualifiée (tagué) ; 🅸🅵 🖼 palmarès européen dans l'historique du site ; 🅵 **EN** contrôle équipement, à l'attention des fédérations et des arbitres internationaux |
| **6b — juillet-août** | J-80 à J-30 : préparer son voyage numérique | 🅸🅵 🎬 **EN** « Install KPI before Thury-Harcourt » ; 🅸🅵 programme publié : **EN** page de la compétition, abonnement ICS ; 🅸🅵 🖼 présentation des équipes engagées (compositions publiques) ; 🅸🅵 🖼 **EN** « Every game tells a story » : les médailles de la saison française, pour le public international ; 🅵 **EN** guide des arbitres internationaux pour le contrôle équipement |
| **6c — 1er au 21 septembre** | J-21 à J-1 : compte à rebours | 🅸🅵 compte à rebours hebdomadaire puis quotidien ; 🅸🅵 🎬 **EN** **vague 4** : toutes les médailles « grand public » publiées avant les Europe ; 🅸🅵 🎬 **EN** la frise du match ; 🅵 affiches QR code FR/EN pour le site ; 🅸 ⭕ coulisses : préparation de la table de marque et des écrans |
| **Europe — 22 au 26 septembre** | Le direct | 🅸 ⭕ stories en continu (scores, frise, médailles du jour, QR code au bord du bassin) ; 🅸🅵 résultats du jour et liens app2 ; 🅸🅵 **EN** « Medals of the day » (joueurs et arbitres, tagués avec l'accord du comité d'organisation) ; 🅸🅵 coulisses du contrôle équipement par les arbitres internationaux (avec leur accord) ; podiums |
| **Après — 27 sept. au 10 oct.** | Bilan | 🅸🅵 🖼 Les Europe en chiffres dans KPI (visiteurs, pays, matchs, médailles) ; merci aux bénévoles, arbitres, comité d'organisation ; palmarès 2027 dans l'historique |

## 7. Chantiers à réaliser ou à terminer

Échéance **idéale** = confortable pour produire la communication ; échéance **impérative** = au-delà, la
publication ou la présentation concernée doit être déplacée ou supprimée.

### 7.1 Plénières (21-22 novembre 2026)

| # | Chantier | Idéale | Impérative | Bloque |
|---|---|---|---|---|
| C0 | **Valider ce plan**, confirmer les dates du § 0 | 6 nov. | **13 nov.** | Déclenchement aux plénières |
| C13 | **Listing des nouveautés du règlement**, en marquant celles qui touchent KPI ; date d'entrée en vigueur | 6 nov. | **15 nov.** | Diapositives « règles dans KPI », C14, C15 |
| C26 | **Support de présentation des plénières** (FR, ~15 diapositives, **sans les médailles**) : disponible / avant-première / à venir, changements pour les régions (codes régionaux), démonstration réelle sur app2, app4 et `beta.*` | 13 nov. | **19 nov.** | Plénières |
| C27 | **Kit de relais** pour les régions et les entraîneurs : 3 visuels, textes prêts à publier, liens marqués `mtm_source=relais`, QR code d'app2, calendrier de la saison | 18 nov. (remis sur place) | 4 déc. | O1 |
| C2a | **Design modernisé visible sur `beta.*`**, même partiellement, pour l'avant-première | 18 nov. | 20 nov. (sinon : avant-première sur l'ancien design, ou sans le site) | Avant-première |

### 7.2 Site public (app3) et bascule

| # | Chantier | État au 01/11/2026 | Idéale | Impérative | Bloque |
|---|---|---|---|---|---|
| C2 | **Modernisation du design** d'app3 (terminée) | En cours | 20 nov. | **30 nov.** | Bascule, captures du site |
| C3 | **Livrer en production** les phases 2, 3 et 4a | Implémentées, à livrer | 20 nov. | **30 nov.** | Bascule |
| C4 | **Reprise du contenu WordPress** (~50 articles, 14 pages) et relecture | Import implémenté | 30 nov. | 4 déc. | Bascule |
| C5 | **Redirections 301** (spec `SITE_REDIRECTS.md` à rédiger), sitemap, Search Console, suivi des 404 | Spec à rédiger | Spec le 13 nov., recette le 30 nov. | 4 déc. | Bascule |
| C6 | **Bascule** `www` → app3 | — | **Semaine du 7 déc.** | **8 janv.** | Lancement S1 |
| C7 | **Données des clubs exemples** (N1, N3, féminines, régionaux, norvégiens) : logos, photos, carte, palmarès ; comptes Instagram des clubs norvégiens | À faire | 15 déc. | 8 janv. | Série « Tous les niveaux » |
| C11 | **Formulaires d'inscription et HelloAsso** : finalisation et test de bout en bout | En cours | 15 févr. | — (non bloquant) | Publication de réserve |

### 7.3 Règlement 2027-2030 dans KPI

| # | Chantier | État | Idéale | Impérative | Bloque |
|---|---|---|---|---|---|
| C14 | **Carton noir** : modèle (nouveau code d'événement, le code `D` « rouge définitif » restant pour l'historique), feuille de marque legacy et scoring app4, app2 (icône, frise, statistiques), app3, classements (critères cartons, Honourable Play), PDF, affichages TV / live, cache des événements, **médailles** (exclusion jusqu'à la fin du match, comme `D`) | À spécifier | Spec le 20 nov., en production le **18 déc.** | **8 janv.** (démonstration à la formation, tournoi de janvier) ; au plus tard 19 févr. si les tournois n'appliquent pas le règlement | Formation, S5, S8, S9 |
| C15 | **Chrono de tir 60/40** : deuxième valeur de remise à zéro dans la feuille de marque (aujourd'hui fixée à 60 s, `FeuilleMarque3.php`), affichage (écrans, TV), scoring app4 | À spécifier | En production le **18 déc.** | Comme C14 | Formation, S7 |
| C16 | Autres règles touchant KPI (selon C13) | Inconnu | Selon C13 | Comme C14 | — |
| C17 | **Calendrier commun** avec la page Facebook de la discipline | À faire | 1er déc. | 15 déc. | Fil règlement |

### 7.4 Formation arbitre national (16-17 janvier 2027)

| # | Chantier | Idéale | Impérative | Bloque |
|---|---|---|---|---|
| C28 | **Kit formateurs** : 5 diapositives + 1 page + Reel de 30 s — carton noir et chrono 60/40 dans la feuille de marque, contrôle équipement, frise du match dans app2 ; « disponible » / « à venir » ; comment demander un accès | 18 déc. | **8 janv.** | Séquence de la formation |
| C29 | **Compétition de démonstration** dans KPI (équipes et joueurs fictifs) pour manipuler la feuille de marque et le contrôle équipement sans données personnelles ; sert aussi aux captures d'app4 | 18 déc. | 8 janv. | Formation, captures |
| C18a | Recueil des futurs **juges arbitres** de la J1 parmi les stagiaires et formateurs, pour leur accès « Contrôle équipement » | 17 janv. (sur place) | 12 févr. | C18 |

### 7.5 Applications app2 et app4 (saison)

| # | Chantier | État | Idéale | Impérative | Bloque |
|---|---|---|---|---|---|
| C8 | **Mise à jour automatique** d'app2 et app4 ([PWA_AUTO_UPDATE.md](../../specs/PWA_AUTO_UPDATE.md)) | À implémenter | 18 déc. | **8 janv.** | Reel d'installation S2 |
| C9 | **Copie de composition par les responsables d'équipes et de clubs** : aujourd'hui le profil 7 édite, ajoute et supprime, mais **ne copie pas** (copie depuis une autre compétition : profils ≤ 4 ; vers les matchs : profils ≤ 6). Ouvrir la copie au profil 7 sur ses clubs, ou écrire le pas à pas sans elle | À décider | Décision le 15 nov. (peut être débattue aux plénières), livré le 15 janv. | **29 janv.** | Pas à pas S6 |
| C10 | **Comptes des responsables d'équipes et de clubs** (profil 7) pour 2027 : existants, mandats passés à 2027, e-mail renseigné | À vérifier ; liste remontée par les régions après les plénières | 31 janv. | 26 févr. | O4 |
| C12 | **Scrutineering en anglais** : relecture de l'interface app4 en anglais, guide bilingue | Interface traduite, à relire | 15 janv. | 5 févr. | Publications **EN** S6 |
| C18 | **Accès « Contrôle équipement »** (profil 10, attribué par les profils 1-2) pour les juges arbitres de la J1 | À organiser | 26 févr. | **5 mars** | O5 |
| C19 | **Gel des déploiements** les week-ends des plénières, de la formation et des tournois, et le week-end de la J1 (13-14 mars) ; pas de déploiement non urgent le vendredi qui précède | À convenir | 13 nov. | 20 nov. | Stabilité |
| C48 | **Renommage des codes régionaux en prod** (script de remise à plat, [rollout checklist](REALIGNEMENT_STRUCTURES_FFCK_ROLLOUT.md)) : régions prévenues aux plénières que les anciens liens ne sont pas redirigés, date communiquée par message direct | Dev validé le 29/10 | Annonce aux plénières ; renommage avant la reprise des compétitions régionales | Avant la 1re compétition régionale 2027 | Diapositive des plénières |

### 7.5 bis Médailles de match (secret jusqu'à la révélation, semaine du 15 mars)

| # | Chantier | Idéale | Impérative | Bloque |
|---|---|---|---|---|
| C30 | **Déployer les médailles en prod sans rien publier** : vider les surfaces du catalogue initial (aucune médaille, étiquette ni statistique publiée), masquer l'entrée de menu `/medals` d'app3 et le lien d'app2 tant que rien n'est publié ; calculer les saisons passées et 2027 | 18 déc. | **29 janv.** (avant le tournoi de fin février) | Tout le reste |
| C31 | **Aligner la spec** (§ 4 « Premier lot public ») sur le calendrier de vagues du § 3.4 | 4 déc. | 8 janv. | C30 |
| C32 | **Interface sans médaille** : vérifier qu'app2 (feuille de match, page équipe) et app3 (onglet Stats, fiche équipe, `/medals`) n'affichent ni bloc vide ni indice tant que rien n'est publié ; le ballon comme symbole du but peut rester | 18 déc. | 29 janv. | Secret |
| C33 | **Recette sur données réelles** : médailles calculées sur le tournoi de Corbeil-Essonnes et celui de fin février, contrôlées dans l'admin ; fréquences de la vague 1 remesurées sur la prod (seuil < 0,2 / match) | Après chaque tournoi | **4 mars** | C34 |
| C34 | **Publication de la vague 1** dans l'admin (profil 1) dès que tous les matchs de la J1 sont validés, contrôle juste après sur app2 et app3 ; vagues suivantes : même geste, après validation de la journée choisie | Répétée sur la préprod le 4 mars ; publiée le lundi 15 mars | **Mercredi 17 mars** (sinon Reel décalé d'une semaine) | Révélation |
| C35 | **Contenus médailles** : Reel de révélation (FR) monté à l'avance, ses plans « exemples réels » remplacés par les captures de la J1 le mardi 16 mars ; carrousel « Les 6 médailles expliquées », gabarit « Les médailles de la journée », gabarit « Nouvelle médaille débloquée », version **EN** pour la vague 4 et les Europe | 26 févr. | Gabarits le **11 mars**, Reel final le **17 mars** ; 30 juin (EN) | Phase 5, 6 |
| C36 | **Traductions EN** des noms et explications des médailles relues (vocabulaire du kayak-polo international) | 30 avril | 30 juin | Vague 4, Europe |

### 7.6 Championnats d'Europe (22-26 septembre 2027)

| # | Chantier | Idéale | Impérative | Bloque |
|---|---|---|---|---|
| C40 | **Coordination avec le comité d'organisation et la fédération** : qui publie quoi, comptes à taguer, hashtag officiel, droits sur les photos, règles appliquées (européennes ou françaises), calendrier de publication du programme | 31 janv. | 31 mars | Phase 6 |
| C41 | **Compétition des Europe paramétrée dans KPI** : catégories, équipes, terrains, programme ; noms de nations et catégories en anglais | Dès réception des engagements | **1er sept.** | Pages de la compétition, ICS, phase 6b-6c |
| C42 | **Traductions anglaises complètes** d'app2, d'app3 (pages compétition, calendrier, clubs, historique) et des écrans d'app4 utilisés par les arbitres internationaux | 30 avril | **30 juin** | Phase 6b |
| C43 | **Comptes « Contrôle équipement » des arbitres internationaux** et procédure de demande en anglais ; guide EN | 30 juin | **1er sept.** | Contrôle équipement aux Europe |
| C44 | **Direct** : refonte du scoring app4 (Mercure) livrée **ou** décision de rester sur la feuille de marque actuelle pour les Europe ; écrans du site et overlays TV (gestionnaire de cache d'événements, panneau TV) | Décision le 31 mars ; répétition sur une compétition nationale en juin | Répétition le **31 juillet** au plus tard | Direct aux Europe |
| C45 | **Tenue en charge** d'app2, d'app3 et d'api2 pour une audience européenne (test de charge, cache, Mercure) | 30 juin | **31 août** | O6 |
| C46 | **Gel des déploiements** du 15 septembre au 27 septembre, sauf correctif urgent | — | 15 sept. | Stabilité |
| C47 | **Contenus EN de la phase 6** : Reels (installation, frise, contrôle équipement), affiches QR code FR/EN, présentations des nations | 30 juin | 31 août | Phase 6b-6c |

### 7.7 Communication (transverse)

| # | Chantier | Idéale | Impérative |
|---|---|---|---|
| C1 | Relever les chiffres 2026 dans Matomo, fixer les cibles du § 1 | 13 nov. (pour les plénières) | 4 déc. |
| C20 | Gabarits (charte FFCK univers Compétition), captures **après** le nouveau design (C2) | 11 déc. | 18 déc. |
| C21 | Contenus de la phase 0 et des phases 1-2 (retour plénières, frise, lancement du site, installation app2, « Tous les niveaux » 1 à 4) | Phase 0 : 20 nov. ; phases 1-2 : 18 déc. | Phase 0 : 27 nov. ; phases 1-2 : 31 déc. |
| C22 | Contenus des phases 3 et 4 (présence, scrutineering FR/EN, carton noir, chrono 60/40), **après** C9, C12, C14, C15 ; contenus médailles : C35 | 22 janv. | 5 févr. |
| C23 | Guides PDF : feuilles de présence (FR), contrôle équipement (FR/EN), check-list organisateur et table de marque (FR), affiche QR code | 29 janv. | 12 févr. |
| C24 | Prévenir les clubs exemples, dont les clubs norvégiens (message en anglais) | 18 déc. | 8 janv. |
| C25 | Programmer les publications (Meta Business Suite), par lots de deux semaines | 20 nov. pour la phase 0 | 27 nov. pour la phase 0 |

### 7.8 Chemin critique

```
Novembre : C0 plan + C13 listing (13-15 nov.) ─► C26 support + C27 kit (19 nov.) ─► 🎤 plénières (21-22 nov.)
           C13 ─► C14/C15 carton noir + chrono (18 déc. → 8 janv.) ─► C28 kit formateurs ─► 🎤 formation (16-17 janv.)
           C2 design + C3 livraisons (30 nov.) ─► C5 redirections ─► C6 bascule (7 déc. → 8 janv.) ─► lancement S1
           C9 décision copie de compo (15 nov.) ─► livraison (29 janv.) ─► pas à pas S6
Hiver :    C30 médailles en prod, non publiées (29 janv.) ─► C33 recette sur les tournois (4 mars) ─► J1 (13-14 mars) validée ─► C34 vague 1 ─► 🏅 révélation (18 mars)
Printemps : C40 coordination (31 mars) + C44 décision direct (31 mars) ─► C42 traductions (30 juin) ─► phase 6b
Été :      C44 répétition (31 juillet) + C45 charge (31 août) + C41 compétition paramétrée (1er sept.) ─► 🏆 Europe
```

**Les dix jours qui viennent sont les plus chargés** : plan, listing du règlement, support et kit des
plénières, design du site et décision sur la copie de composition doivent tous être prêts entre le 13 et le
20 novembre.

## 8. Production

- **Contenus** (jusqu'à fin mars) : 13 Reels (dont la révélation des médailles, avec les vraies médailles de la J1), 17 carrousels, environ 75 stories,
  6 guides et imprimables, le support des plénières et le kit formateurs. Phase 6 : 3 Reels « médaille
  débloquée », un carrousel « médailles de la journée » par journée nationale, environ 8 Reels EN, des carrousels
  par nation, des affiches FR/EN.
- **Médailles** : toujours le **blason officiel** (rendu d'app2/app3, capture réelle), le nom FR · EN et
  l'explication ; un exemple réel seulement sur une donnée publique (feuille de match validée).
- **Charte** : FFCK univers Compétition, comme le site (bleus `#69b9e6` / `#357b9c`, rouges `#c94a4c` /
  `#882831`, noir `#1e1e1c`, gris `#c6c7c7`, Agency FB et Raleway). Formats 1080×1350, 1080×1920, 1200×630.
  Pour les Europe : cohabitation avec l'identité de l'événement, à convenir avec le comité d'organisation (C40).
- **Reels** : enregistrement d'écran, sous-titres incrustés FR ou EN, fin avec URL + QR code.

## 9. Contrôles et risques

**Contrôle du jeudi** : chaque jeudi, vérifier que les fonctionnalités de la semaine suivante sont **en
production** ; sinon, permuter avec une publication « Tous les niveaux » ou de réserve.

| Risque | Parade |
|---|---|
| Plan ou kit pas prêts pour les plénières | Présenter quand même les nouveautés ; phase 0 décalée au 7 décembre |
| Design pas visible sur `beta.*` aux plénières | Avant-première limitée aux pages prêtes, ou remplacée par la date de bascule |
| Bascule repoussée après le 8 janvier | Le lancement du site glisse en S3 ; S1-S2 se concentrent sur app2, la formation et le tournoi |
| Carton noir / chrono 60/40 pas prêts pour la formation | Les formateurs présentent la règle et une maquette marquée « à venir » ; consigne écrite aux tables de marque ; impératif absolu : la J1 |
| Formation et tournoi le même week-end | Deux équipes distinctes : formateurs (séquence KPI) et communication (stories du tournoi) ; une seule publication Facebook par jour pour ne pas se concurrencer |
| Copie de composition non ouverte au profil 7 (C9) | Pas à pas sans la copie ; la copie est faite par le responsable de compétition sur demande |
| Juges arbitres sans accès le matin de la J1 | Liste arrêtée le 5 mars (C18), test de connexion pendant le tournoi de fin février |
| Scoring app4 / Mercure pas prêt pour les Europe | Décision au 31 mars (C44) : rester sur la feuille de marque actuelle, éprouvée ; la communication ne promet pas de nouveauté non répétée |
| Pic de fréquentation aux Europe | Test de charge (C45), gel des déploiements (C46), équipe technique d'astreinte |
| Formulaires / HelloAsso non validés | Aucune publication (C11) |
| Les médailles fuitent avant la révélation (capture d'admin, bloc vide visible, page `/medals` indexée) | Rien de publié avant la validation de la J1 (C30), interface vérifiée sans médaille (C32), page d'admin réservée aux profils 1-2 ; en cas de fuite, assumer un teaser plutôt que démentir |
| Timelines peu fiables à la J1 (temps non saisis) : peu de médailles « chronologiques » | Consigne « temps de chaque but et carton » à la formation et dans la check-list table de marque (S9) ; vague 1 mêlée à deux médailles sans timeline (*Cage inviolée*, *But en or*) |
| Matchs de la J1 validés tardivement (pas de saisie ni de validation en direct) | Révélation décalée d'une semaine (C34) : le Reel attend les vraies médailles ; jamais de vague publiée sur une journée à moitié validée |
| Une médaille mal attribuée, contestée publiquement | Recette sur les tournois (C33) ; retrait d'une médaille depuis l'admin, sans déploiement ; réponse type « elle se calcule depuis la feuille de marque, signalez-nous l'erreur » |
| Codes régionaux renommés : liens et favoris cassés | Annonce aux plénières, message direct avant le renommage, jour sans compétition (C48) |

## 10. Après la campagne

- Bilan intermédiaire au 28 mars 2027 (O1 à O5, première lecture d'O7), bilan final au 10 octobre 2027 (O6, O7).
- Les contenus durables (installation app2, frise, présence, contrôle équipement FR/EN, médailles expliquées) sont
  réutilisés à chaque événement.
- Saison 2028 : nouvelles médailles (celles restées dans l'admin, ou de nouvelles règles) révélées de la même façon.
- Une fois ce plan exécuté, ce document est déplacé dans `archive/` avec son bilan (règle de `CLAUDE.md`).
