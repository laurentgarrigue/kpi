# Plan de communication Facebook / Instagram — nouveautés KPI avant la saison 2027

**Créé le** : 21 octobre 2026 — **Révisé le** : 22 octobre 2026 (v2 : tournois de préparation, scrutineering,
règlement 2027-2030, chantiers) — **Statut** : 📝 Proposition à valider
**Fenêtre de publication** : du lundi 4 janvier au dimanche 14 mars 2027 (10 semaines), précédée d'une
préparation en novembre-décembre 2026
**Sources** : [NOUVEAUTES.md](../../user/NOUVEAUTES.md), [APP2_APPLICATION_WEB.md](../../user/APP2_APPLICATION_WEB.md),
[PUBLIC_SITE_REDESIGN_STRATEGY.md](PUBLIC_SITE_REDESIGN_STRATEGY.md), [specs publiques](../../specs/public/README.md),
[PAGE_SCRUTINEERING.md](../../specs/PAGE_SCRUTINEERING.md), [PAGE_PRESENCE.md](../../specs/PAGE_PRESENCE.md),
[PAGE_SCORING.md](../../specs/PAGE_SCORING.md), [PWA_AUTO_UPDATE.md](../../specs/PWA_AUTO_UPDATE.md)

---

## 0. Dates repères (à confirmer)

| Repère | Date retenue par ce plan | À confirmer |
|---|---|---|
| Modernisation du design d'app3 | novembre 2026 | — |
| **Bascule d'app3** (phase 5, `www.kayak-polo.info`) | **semaine du 7 décembre 2026** recommandée (§ 7.1) | Moment à arrêter |
| Tournoi de mi-janvier | **samedi 16 – dimanche 17 janvier 2027** | Date exacte, nom, lieu |
| Tournoi de fin février | **samedi 27 – dimanche 28 février 2027** | Date exacte, nom, lieu |
| **1re journée de championnat** | **samedi 6 – dimanche 7 mars 2027** | Date exacte |
| Entrée en vigueur du règlement 2027-2030 | **1er janvier 2027** par hypothèse | Les tournois de janvier et février l'appliquent-ils ? |
| 1re compétition internationale utilisant KPI en 2027 | inconnue | Date, pour caler la communication en anglais |

**La date d'entrée en vigueur du règlement est la plus structurante** : si les tournois de janvier l'appliquent,
le carton noir et le chrono de tir 60/40 doivent être en production **avant le 15 janvier** (§ 7.2).

## 1. Objectifs

| # | Objectif | Indicateur (Matomo) | Cible |
|---|---|---|---|
| O1 | Joueurs et supporters **suivent les tournois et la J1 dans app2** | Visiteurs uniques app2 les week-ends du 16-17/01, 27-28/02 et 6-7/03 ; visites `mtm_campaign=saison2027` | En hausse d'un tournoi à l'autre ; ×1,5 par rapport à la J1 2026 |
| O2 | Le public **adopte le nouveau site**, à tous les niveaux de pratique | Visites `www` ; pages clubs, carte et historique vues ; taux de 404 | 404 < 1 % des pages vues |
| O3 | Les **responsables d'équipes et de clubs** remplissent eux-mêmes les feuilles de présence | Feuilles de présence complètes avant la J1 | 100 % des équipes de la J1 au jeudi 4 mars |
| O4 | Les **juges arbitres** (J1) et **arbitres internationaux** utilisent le **scrutineering** | Équipes contrôlées dans app4 | Toutes les équipes de la J1 |
| O5 | Les règles 2027-2030 qui touchent KPI sont **comprises et appliquées dans les outils** | Questions reçues ; feuilles de marque avec carton noir correct | 0 incident à la J1 |

Les cibles sont à recaler sur les chiffres 2026 relevés dans Matomo (chantier C1).

## 2. Publics, réseaux, langue

| Public | Réseau principal | Langue | Message clé | Appel à l'action |
|---|---|---|---|---|
| **Joueurs et supporters**, tous niveaux (N1, N3, féminines, régionaux) | Instagram (Reels, stories, carrousels), Facebook en relais | FR (+ EN sur clubs et carte) | « Tous les matchs, scores et classements, et ton club sur la carte » | Installer app2 ; trouver son club sur le site |
| **Responsables d'équipes et de clubs** (profil 7) | Facebook | FR | « Votre feuille de présence prête avant la journée, en quelques clics » | Remplir et vérifier sa feuille de présence dans app4 |
| **Organisateurs, tables de marque** | Facebook | FR | « Carton noir, chrono de tir 60/40 : la feuille de marque est à jour » | Se connecter à app4, lancer le tutoriel |
| **Juges arbitres** (J1) | Facebook + message direct | FR | « Nouveau : le contrôle équipement sur tablette ou téléphone » | Demander son accès « Contrôle équipement » |
| **Arbitres internationaux** | Facebook + Instagram, relais par les fédérations | **EN** | « New: equipment scrutineering in KPI » | Ask for a scrutineer account |

**Règle de répartition** : Instagram parle aux joueurs et supporters ; Facebook porte aussi tous les contenus
« organisateurs », « clubs » et « arbitres ».

**Ton** : tutoiement sur Instagram, vouvoiement sur Facebook pour les contenus pratiques ; un bénéfice par
publication ; une capture réelle à chaque fois, sur des données publiques (résultats, classements, clubs).
Jamais une fiche licencié ni un écran d'administration avec des données personnelles : les captures d'app4
utilisent une compétition de démonstration ou des noms floutés.

**Liens et mesure** : lien en bio, autocollant lien en story, QR code d'app2 sur les visuels. Chaque lien porte
`?mtm_campaign=saison2027&mtm_source=instagram|facebook&mtm_content=<sujet>`.

**Hashtags** : `#kayakpolo` `#canoepolo` `#kayakpoloinfo` `#KPIsaison2027` ; en anglais `#canoepolo`
`#kayakpolo` + le hashtag de la compétition.

## 3. Les nouveautés mises en avant

Seules les fonctionnalités **en production à la date de publication** sont annoncées (contrôle du jeudi, § 8).

### app2 — l'application de résultats (`app.kayak-polo.info`)

| Nouveauté | Message | Format |
|---|---|---|
| **Frise chronologique du match** dans la feuille de match (buts, cartons, par période, une ligne par équipe) | « Le match minute par minute » | Reel enregistrement d'écran : **contenu phare d'app2** |
| Feuille de match (composition, buts, cartons) au clic sur un score ou un numéro de match | « Qui a marqué ? Touche le score » | Reel, intégré au précédent |
| Accueil **« À venir »** (prochains rendez-vous, badge « En cours ») | « Le prochain tournoi en un coup d'œil » | Story |
| Mode **Compétitions** et lien direct par catégorie (`/group/2027/N1H`) | « Suis tout le championnat, pas seulement une journée » | Carrousel |
| Fiche équipe et **Progression** (classements, tableaux) | « Le parcours de ton équipe » | Carrousel |
| **QR code** de l'événement | « Un scan et tout le gymnase suit les scores » | Affiche A4 pour les organisateurs |
| **Installation** sur l'écran d'accueil, **hors ligne** | « Pas de réseau au bord du bassin ? Elle marche quand même » | Reel tutoriel iPhone + Android |
| **Thème sombre**, **français / anglais** | « Mode sombre » / « Follow in English » | Stories |

### app3 — le nouveau site (`www.kayak-polo.info`)

| Nouveauté | Message | Format |
|---|---|---|
| Nouveau site, design modernisé, mobile d'abord | « Le site fait peau neuve » | Reel de lancement, carrousel avant/après |
| **Clubs : liste, carte, fiche** | « Trouve ton club, et tous les autres » | Série d'exemples **par niveau** (ci-dessous) |
| **Historique et palmarès** | « Ton club a une histoire » | Carrousels par niveau, quiz |
| Pages compétition (matchs, terrains, classement, progression, statistiques) | « Toute la compétition sur une page » | Carrousel |
| **Calendrier** et **abonnement ICS** | « Les dates de ta compétition dans ton agenda » | Reel tutoriel |
| **Recherche globale** | « Une équipe, un club : tape, trouve » | Story |
| Passerelle « Suivre dans l'app » vers app2 | « Le site pour tout savoir, l'app pour le jour J » | Visuel |
| Formulaires d'inscription et HelloAsso | — | **Hors campagne tant que le test de bout en bout n'est pas validé** (chantier C11) ; créneau de réserve en S10 |

**Série « Tous les niveaux »** (une équipe ou un club par publication, toujours la même mise en page :
fiche club → carte → historique → équipe dans app2) :

| Publication | Exemple à choisir | Langue |
|---|---|---|
| 1 | Un club de **N1** hommes (palmarès riche) | FR |
| 2 | Une équipe **féminine** (N1F ou équipe en progression) | FR |
| 3 | Un club de **N3** | FR |
| 4 | Un club **régional** (championnat régional, club récent ou petit) | FR |
| 5 | Une équipe **U21 / jeunes** (facultatif) | FR |
| 6 à 8 | **2 ou 3 clubs norvégiens**, comptes Instagram **tagués** | **EN** |

Les clubs exemples sont prévenus avant publication, et invités à partager.

### app4 — l'administration (`/admin2`)

| Nouveauté | Pour qui | Format |
|---|---|---|
| **Contrôle équipement (scrutineering)** : nouvelle fonctionnalité. Par joueur : kayak, gilet, casque, pagaies valides, commentaire ; accès dédié « Contrôle équipement » | Juges arbitres (J1), arbitres internationaux | Reel FR **et EN**, carrousel pas à pas FR **et EN**, guide PDF bilingue |
| **Feuilles de présence pas à pas**, dont la **copie de composition** | Responsables d'équipes et de clubs | Carrousel FB + guide PDF + Reel |
| **Carton noir** et **chrono de tir 60/40** dans la feuille de marque | Tables de marque, organisateurs | Publication FB + Reel (fil règlement, § 4) |
| Contexte de travail (événement / compétition) | Organisateurs | Publication FB |
| Vérification des compositions (licence, certificat, pagaie couleur, surclassement), règles d'éligibilité | Clubs, resp. compétition | Carrousel FB |
| Passage des mandats à la nouvelle saison, ajout des équipes depuis le classement précédent | Resp. division / compétition | Publication FB |
| Réinitialisation du mot de passe par e-mail, tutoriel guidé, thème sombre | Tous | Story, Reel court |

## 4. Fil parallèle : règlement sportif 2027-2030

Deux communications coordonnées, à des endroits différents :

| | **Page Facebook de la discipline** | **Comptes KPI** (ce plan) |
|---|---|---|
| Objet | **Toutes** les nouveautés du règlement 2027-2030 | Uniquement les nouveautés **qui touchent KPI** |
| Contenu | Communication distincte, hors de ce plan (listing à réaliser, chantier C13) | « Comment KPI applique la règle » : feuille de marque, feuille de match app2, statistiques |
| Ordre | **Publie d'abord** la règle | **Relaie ensuite**, dans les 48 h, avec la partie outil |

Nouveautés connues qui touchent KPI (liste à compléter par le listing, chantier C13) :

| Règle | Ce qui change dans KPI | Publication KPI |
|---|---|---|
| **Carton noir** à la place du carton rouge définitif | Saisie dans la feuille de marque, icône dans app2 (feuille de match, frise), statistiques et classements (critères cartons, Honourable Play), PDF, affichages TV et live | « Le carton noir arrive dans KPI » : avant/après de la feuille de match app2 |
| **Chrono de tir 60 s et 40 s** | Deuxième valeur de remise à zéro dans la feuille de marque (aujourd'hui fixée à 60 s), affichage du chrono de tir (écrans, TV) | Reel « 60 ou 40 ? » côté table de marque |
| Autres (selon le listing) | À évaluer : durées, prolongations, effectifs… | Selon impact |

## 5. Rythme

| Réseau | Publications par semaine | Contenu |
|---|---|---|
| Instagram | 3 publications (dont **1 Reel**) + 4 à 6 stories | Joueurs, supporters ; relais des annonces majeures |
| Facebook | 3 à 4 publications | Mêmes sujets + 1 ou 2 publications pratiques (clubs, organisateurs, arbitres) |

Jours : **mardi, jeudi, samedi**. Stories quotidiennes les semaines de tournoi (S2, S8) et de la J1 (S9) ; stories
en direct pendant les trois week-ends de compétition.

## 6. Calendrier semaine par semaine

Légende : 🅸 Instagram · 🅵 Facebook · 🎬 Reel · 🖼 carrousel · ⭕ story · **EN** en anglais · 📜 fil règlement

### Décembre 2026 — Bascule discrète

| Quand | Publication |
|---|---|
| Semaine de la bascule | Aucune campagne : on surveille les 404, les redirections et Matomo. Au plus une 🅵 brève « Le site change d'habit, dites-nous si quelque chose vous manque » |
| Mi-décembre | 🅵 📜 relais de l'annonce du règlement 2027-2030 par la page de la discipline (si elle a publié) |

### Phase 1 — Lancement (S1 et S2) : « Nouvelle année, nouveau site, premier tournoi »

| Semaine | Mardi | Jeudi | Samedi |
|---|---|---|---|
| **S1** 4-10 jan. | 🅸🅵 🎬 **Lancement officiel du nouveau site** (Reel + publication épinglée) | 🅸🅵 🖼 Visite guidée : compétitions, calendrier, clubs, carte, historique, recherche | 🅸🅵 🖼 « Tous les niveaux » n° 1 (club de N1) · 🅸 ⭕ sondage « Ton club est sur la carte ? » |
| **S2** 11-17 jan. **Tournoi de mi-janvier** | 🅸🅵 🎬 Installer app2 avant le tournoi (iPhone + Android) | 🅸🅵 🎬 **La frise du match** dans la feuille de match · 🅵 affiche QR code envoyée à l'organisateur | **Sam. 16 / dim. 17** 🅸 ⭕ stories en direct (scores, frise, QR code) · 🅵 liens de suivi du tournoi |

### Phase 2 — Approfondissement (S3 à S5) : « Le site et l'app pour tous »

| Semaine | Mardi | Jeudi | Samedi |
|---|---|---|---|
| **S3** 18-24 jan. | 🅸🅵 🖼 Bilan du tournoi en chiffres + merci à l'organisateur | 🅸🅵 🖼 « Tous les niveaux » n° 2 (équipe féminine) | 🅸🅵 🖼 **EN** « All levels » : club norvégien n° 1 (tagué) |
| **S4** 25-31 jan. | 🅵 Préparer la saison dans app4 : mandats 2027, équipes depuis le classement précédent | 🅸🅵 🎬 S'abonner au calendrier de sa compétition (ICS) | 🅸🅵 🖼 « Tous les niveaux » n° 3 (club de N3) |
| **S5** 1-7 fév. | 🅵 📜 **Le carton noir dans KPI** (après la publication de la page de la discipline) | 🅸🅵 🖼 Historique et palmarès : quiz « Qui a gagné… ? » | 🅸🅵 🖼 « Tous les niveaux » n° 4 (club régional) · **EN** club norvégien n° 2 |

### Phase 3 — Outils de la journée (S6 et S7) : « Prêts pour les compétitions »

| Semaine | Mardi | Jeudi | Samedi |
|---|---|---|---|
| **S6** 8-14 fév. | 🅵 🖼 **Feuilles de présence pas à pas** (responsables d'équipes et de clubs), dont la **copie de composition** + guide PDF | 🅸🅵 🎬 **Nouveau : le contrôle équipement** (FR) · 🅵 appel aux juges arbitres pour leur accès | 🅸🅵 🎬 **EN** « New: equipment scrutineering in KPI » + 🖼 **EN** pas à pas |
| **S7** 15-21 fév. | 🅵 📜 🎬 **Chrono de tir 60/40** côté table de marque | 🅵 🖼 Contrôle équipement pas à pas (FR) + guide PDF bilingue | 🅸🅵 🖼 Fiche équipe et Progression dans app2 · 🅸 ⭕ mode sombre |

### Phase 4 — Tournoi de fin février et J1 (S8 et S9)

| Semaine | Mardi | Jeudi | Samedi |
|---|---|---|---|
| **S8** 22-28 fév. **Tournoi de fin février** | 🅵 Rappel aux responsables : feuilles de présence **avant le jeudi 4 mars** | 🅸🅵 Liens de suivi du tournoi + QR code | **Sam. 27 / dim. 28** 🅸 ⭕ stories en direct, dont **premier carton noir / chrono 40 s** si les règles s'appliquent |
| **S9** 1-7 mars **J1** | 🅵 Check-list organisateur et table de marque (présences, contrôle équipement, carton noir, chrono 60/40, QR code) + PDF | 🅸🅵 🖼 « Tout pour suivre la J1 » : liens directs par catégorie · 🅵 dernier rappel feuilles de présence | **Sam. 6 / dim. 7** 🅸 ⭕ stories en direct · 🅵 liens de suivi |

### Phase 5 — Suivi (S10) : 8-14 mars

| Mardi | Jeudi | Samedi |
|---|---|---|
| 🅸🅵 🖼 Bilan de la J1 en chiffres, merci aux organisateurs et aux juges arbitres | 🅵 Appel aux retours (sondage ou formulaire) | **Réserve** : inscriptions en ligne et HelloAsso **si** validées (C11) ; sinon « Tous les niveaux » n° 5 ou **EN** club norvégien n° 3 |

Communication en anglais **au plus tard 3 semaines avant** la première compétition internationale utilisant le
scrutineering : republier alors le Reel et le pas à pas **EN** (S6), et les envoyer aux arbitres concernés.

## 7. Chantiers à réaliser ou à terminer

Échéance **idéale** = confortable pour produire la communication ; échéance **impérative** = au-delà, la
publication concernée doit être déplacée ou supprimée.

### 7.1 Site public (app3) et bascule

| # | Chantier | État au 22/10/2026 | Idéale | Impérative | Bloque |
|---|---|---|---|---|---|
| C2 | **Modernisation du design** d'app3 | À faire | 20 nov. | **30 nov.** | Bascule, toutes les captures du site |
| C3 | **Livrer en production** les phases 2 (résultats), 3 (calendrier, historique, équipes, clubs, recherche) et 4a (actualités, pages) | Implémentées sur la branche, à livrer | 20 nov. | **30 nov.** | Bascule |
| C4 | **Reprise du contenu WordPress** (~50 articles, 14 pages) et relecture | Import implémenté | 30 nov. | 4 déc. | Bascule |
| C5 | **Redirections 301** (spec `SITE_REDIRECTS.md` à rédiger), sitemap, Search Console, suivi des 404 | Spec à rédiger | Spec le 13 nov., recette le 30 nov. | 4 déc. | Bascule |
| C6 | **Bascule** `www` → app3 | — | **Semaine du 7 déc.** (trafic faible, 5 semaines avant le 1er tournoi, correctifs possibles avant les fêtes) | **8 janvier** (avant le lancement S1 et le tournoi) | Phase 1 du calendrier |
| C7 | **Données des clubs exemples** : logos, photos d'équipe, coordonnées sur la carte, palmarès corrects pour les exemples N1, N3, féminines, régionaux et **norvégiens** ; recueil des comptes Instagram des clubs norvégiens | À faire | 15 déc. | 8 janv. | Série « Tous les niveaux » |

### 7.2 Règlement 2027-2030 dans KPI

| # | Chantier | État | Idéale | Impérative | Bloque |
|---|---|---|---|---|---|
| C13 | **Listing des nouveautés** du règlement, en marquant celles qui touchent KPI ; date d'entrée en vigueur | À réaliser | **6 nov.** | 15 nov. (sinon les développements ne tiennent pas) | C14, C15, fil règlement |
| C14 | **Carton noir** : décision de modèle (nouveau code d'événement, le code `D` « rouge définitif » restant pour l'historique), feuille de marque legacy et scoring app4, app2 (icône, frise, statistiques), app3, classements (critères cartons, Honourable Play), PDF, affichages TV / live, cache des événements | À spécifier | Spec le 20 nov., en production le **18 déc.** | **8 janv.** si les tournois appliquent le règlement ; sinon **19 févr.** | Publications S5, S8, S9 |
| C15 | **Chrono de tir 60/40** : deuxième valeur de remise à zéro dans la feuille de marque (aujourd'hui fixée à 60 s, `FeuilleMarque3.php`), affichage chrono de tir (écrans, TV), prise en compte dans le scoring app4 en cours | À spécifier | En production le **18 déc.** | Comme C14 | Publication S7 |
| C16 | Autres règles touchant KPI (selon C13) | Inconnu | Selon C13 | Comme C14 | — |
| C17 | **Calendrier commun** avec la page Facebook de la discipline (dates de publication du règlement) | À faire | 1er déc. | 15 déc. | Ordre « discipline d'abord, KPI ensuite » |

### 7.3 Applications app2 et app4

| # | Chantier | État | Idéale | Impérative | Bloque |
|---|---|---|---|---|---|
| C8 | **Mise à jour automatique** d'app2 (et app4), spec [PWA_AUTO_UPDATE.md](../../specs/PWA_AUTO_UPDATE.md) : sans elle, des téléphones gardent une version périmée | À implémenter | 18 déc. | **8 janv.** (Reel d'installation S2) | Tournoi de janvier |
| C9 | **Copie de composition par les responsables d'équipes et de clubs** : aujourd'hui le profil 7 peut éditer, ajouter et supprimer des joueurs, mais **pas copier** (copie depuis une autre compétition : profils ≤ 4 ; copie vers les matchs : profils ≤ 6). Décider d'ouvrir la copie au profil 7, limitée à ses clubs, ou d'écrire le pas à pas sans elle | À décider | Décision le 15 nov., livré le 15 janv. | **29 janv.** | Pas à pas S6 |
| C10 | **Comptes des responsables d'équipes et de clubs** (profil 7) pour la saison 2027 : existants, mandats passés à 2027, e-mail renseigné (réinitialisation du mot de passe) | À vérifier | 31 janv. | 12 févr. | O3 |
| C12 | **Scrutineering en anglais** : relecture de l'interface app4 en anglais (écrans scrutineering, présence, connexion), guide bilingue | Interface traduite, à relire | 15 janv. | 5 févr. | Publications **EN** S6 |
| C18 | **Accès « Contrôle équipement »** (profil 10, attribué par les profils 1-2) pour les **juges arbitres** de la J1 ; procédure pour les **arbitres internationaux** (demande d'accès en anglais) | À organiser | 12 févr. | **26 févr.** | O4 |
| C19 | **Gel des déploiements** les week-ends des 16-17 janv., 27-28 févr. et 6-7 mars ; pas de déploiement non urgent le vendredi qui précède | À convenir | 15 déc. | 8 janv. | Stabilité des tournois |
| C11 | **Formulaires d'inscription et HelloAsso** : finalisation et test de bout en bout | En cours | 15 févr. | — (non bloquant : publication de réserve en S10 ou après la J1) | Publication de réserve |

### 7.4 Communication

| # | Chantier | Idéale | Impérative |
|---|---|---|---|
| C0 | Valider ce plan, confirmer les dates du § 0 | 6 nov. | 13 nov. |
| C1 | Relever les chiffres 2026 dans Matomo, fixer les cibles du § 1 | 20 nov. | 4 déc. |
| C20 | Gabarits (charte FFCK univers Compétition), captures **après** le nouveau design (C2) | 11 déc. | 18 déc. |
| C21 | Reels et carrousels des phases 1 et 2 (lancement du site, installation app2, frise, « Tous les niveaux » 1 à 4) | 18 déc. | 31 déc. |
| C22 | Reels et carrousels des phases 3 et 4 (présence, scrutineering FR/EN, carton noir, chrono 60/40), **après** C9, C12, C14, C15 | 22 janv. | 5 févr. |
| C23 | Guides PDF : feuilles de présence (FR), contrôle équipement (FR/EN), check-list organisateur et table de marque (FR), affiche QR code | 29 janv. | 12 févr. |
| C24 | Prévenir les clubs exemples, dont les clubs norvégiens (message en anglais) | 18 déc. | 8 janv. |
| C25 | Programmer les publications (Meta Business Suite), par lots de deux semaines | 23 déc. | 31 déc. pour S1-S2 |

### 7.5 Chemin critique

```
C13 listing (6-15 nov.) ─► C14/C15 carton noir + chrono (18 déc. → 8 janv.) ─► Reels règlement (5 févr.)
C2 design + C3 livraisons (30 nov.) ─► C5 redirections ─► C6 bascule (7 déc. → 8 janv.) ─► captures ─► lancement S1
C9 décision copie de compo (15 nov.) ─► livraison (29 janv.) ─► pas à pas S6
```

Les trois chaînes passent par **novembre** : listing du règlement, design, décision sur la copie de composition.

## 8. Production et contrôles

- **Contenus** : 11 Reels (lancement, installation app2, frise, ICS, scrutineering FR, scrutineering EN,
  chrono 60/40, présence, compte à rebours J1, deux de réserve), 14 carrousels, environ 60 stories, 5 guides et
  imprimables.
- **Charte** : FFCK univers Compétition, comme le site (bleus `#69b9e6` / `#357b9c`, rouges `#c94a4c` /
  `#882831`, noir `#1e1e1c`, gris `#c6c7c7`, Agency FB et Raleway). Formats 1080×1350, 1080×1920, 1200×630.
- **Reels** : enregistrement d'écran, sous-titres incrustés (FR, ou EN pour les versions anglaises), fin avec
  URL + QR code.
- **Contrôle du jeudi** : chaque jeudi, vérifier que les fonctionnalités de la semaine suivante sont **en
  production** ; sinon, permuter avec une publication « Tous les niveaux » ou de réserve.

## 9. Risques et plans de repli

| Risque | Parade |
|---|---|
| Bascule repoussée après le 8 janvier | Le lancement du site glisse en S3 ; S1-S2 se concentrent sur app2 et le tournoi |
| Carton noir / chrono 60/40 pas prêts pour les tournois qui appliquent le règlement | Consigne écrite aux tables de marque (saisie provisoire), publications S5 et S7 décalées ; impératif absolu : la J1 |
| Copie de composition non ouverte au profil 7 (C9) | Pas à pas sans la copie ; la copie est faite par le responsable de compétition sur demande |
| Juges arbitres sans accès le matin de la J1 | Liste des accès arrêtée le 26 février (C18), test de connexion pendant le tournoi de fin février |
| Clubs exemples aux données incomplètes | Choisir les exemples après vérification (C7) |
| Formulaires / HelloAsso non validés | Aucune publication (C11) |
| Vacances scolaires de février (selon les zones) | Republier le pas à pas présence et le Reel scrutineering en S8 |

## 10. Après la campagne

- Bilan chiffré au 14 mars 2027 (objectifs § 1) et note des retours.
- Les contenus durables (installation app2, frise, présence, scrutineering FR/EN) sont réutilisés à chaque
  événement, et pour les compétitions internationales.
- Une fois ce plan exécuté, ce document est déplacé dans `archive/` avec son bilan (règle de `CLAUDE.md`).
