# Plan de communication Facebook / Instagram — nouveautés KPI avant la saison 2027

**Créé le** : 21 octobre 2026 — **Statut** : 📝 Proposition à valider
**Fenêtre** : du lundi 11 janvier au dimanche 14 mars 2027 (9 semaines), plus une semaine de suivi
**Date-cible** : première journée de championnat le **week-end du 13-14 mars 2027**
**Hypothèse structurante** : la **bascule d'app3** (phase 5, `www.kayak-polo.info` servi par le nouveau site) a
lieu **avant** la saison ; la campagne la place au **lundi 15 février 2027** (S6)
**Sources** : [NOUVEAUTES.md](../../user/NOUVEAUTES.md), [APP2_APPLICATION_WEB.md](../../user/APP2_APPLICATION_WEB.md),
[PUBLIC_SITE_REDESIGN_STRATEGY.md](PUBLIC_SITE_REDESIGN_STRATEGY.md), [specs publiques](../../specs/public/README.md),
[PAGE_SCRUTINEERING.md](../../specs/PAGE_SCRUTINEERING.md), [DARK_MODE.md](../../specs/DARK_MODE.md),
[TUTORIEL_ADMIN2.md](../../specs/TUTORIEL_ADMIN2.md)

---

## 1. Objectifs

| # | Objectif | Indicateur (Matomo, déjà intégré dans app2/app4) | Cible au 14/03/2027 |
|---|---|---|---|
| O1 | Les joueurs et supporters **installent app2** et l'utilisent au bord du bassin | Visiteurs uniques app2 le week-end du 13-14/03 ; visites avec `mtm_campaign=saison2027` | ×1,5 par rapport à la 1re journée 2026 |
| O2 | Le public **adopte le nouveau site** dès la bascule | Visites `www` après le 15/02 ; taux de 404 (suivi prévu en phase 5) | 404 < 1 % des pages vues |
| O3 | Les organisateurs, officiels et responsables **travaillent dans app4** dès la 1re journée | Connexions app4 des profils 3 à 10 ; tutoriel guidé lancé | 100 % des organisateurs de la J1 connectés au moins une fois avant le 13/03 |
| O4 | Plus aucun contrôleur ne cherche le **scrutineering dans app2** | Questions reçues sur le sujet | 0 la semaine de la J1 |

Les chiffres-cibles sont à recaler sur les valeurs 2026 relevées dans Matomo pendant la préparation (§ 7).

## 2. Publics, réseaux, messages

| Public | Réseau principal | Ce qu'il doit retenir | Appel à l'action |
|---|---|---|---|
| **Joueurs et supporters** | Instagram (Reels, stories, carrousels), Facebook en relais | « Tous les matchs, scores et classements dans ta poche, même sans réseau au bord du bassin » | Installer app2 sur l'écran d'accueil ; s'abonner au calendrier |
| **Organisateurs, officiels** (table de marque, contrôle équipement) | Facebook (publications longues, groupes de clubs, Messenger) | « Préparez votre journée dans app4 : contexte de travail, présences, contrôle équipement au même endroit » | Se connecter à app4 et lancer le tutoriel guidé avant la J1 |
| **Clubs, responsables de compétition** | Facebook | « Inscriptions, compositions, éligibilité et nouvelle saison : moins de saisie, moins d'erreurs » | Vérifier ses équipes et ses mandats 2027 dans app4 |

**Règle de répartition** : Instagram parle aux joueurs et supporters ; Facebook porte en plus tous les contenus
« organisateurs » et « clubs », trop détaillés pour Instagram.

**Ton** : tutoiement sur Instagram, vouvoiement sur Facebook pour les contenus organisateurs ; phrases courtes,
un bénéfice par publication, une capture d'écran réelle à chaque fois (les données affichées doivent être
publiques : résultats, classements ; jamais une fiche licencié ni un écran d'administration avec des données
personnelles).

**Liens** : Instagram n'accepte pas de lien cliquable dans une publication. On utilise le **lien en bio** (une
page ou un lien unique mis à jour chaque semaine), l'**autocollant lien** des stories et le **QR code** d'app2
sur les visuels. Chaque lien porte `?mtm_campaign=saison2027&mtm_source=instagram|facebook&mtm_content=<sujet>`
pour mesurer les retombées.

**Hashtags** (3 à 5 par publication) : `#kayakpolo` `#canoepolo` `#kayakpoloinfo` + un hashtag de semaine
(`#KPIsaison2027`) + éventuellement la compétition (`#N1H`).

## 3. Les nouveautés mises en avant

Seules les fonctionnalités **en production à la date de publication** sont annoncées (voir § 7, contrôle avant
chaque semaine).

### app2 — l'application de résultats (`app.kayak-polo.info`)

| Nouveauté | Message | Format adapté |
|---|---|---|
| Mode **Compétitions** et lien direct par catégorie (`/group/2027/N1H`) | « Suis tout le championnat N1H sur la saison, pas seulement une journée » | Carrousel |
| Accueil **« À venir »** (frise des prochains rendez-vous, badge « En cours ») | « Le prochain tournoi en un coup d'œil » | Story, Reel |
| **Feuille de match** (composition, buts, cartons) en cliquant sur un score ou un numéro de match | « Qui a marqué ? Touche le score » | Reel enregistrement d'écran |
| **Fiche équipe** et **Progression** (classements, tableaux de phases) | « Le parcours de ton équipe, match après match » | Carrousel |
| **QR code** et partage d'un événement | « Un scan et tout le gymnase suit les scores » | Visuel, affiche à imprimer pour les organisateurs |
| **Installation sur l'écran d'accueil** et **hors ligne** | « Pas de réseau au bord du bassin ? Elle marche quand même » | Reel tutoriel iPhone + Android |
| **Thème sombre** (Clair / Sombre / Système) | « Mode sombre : enfin » | Story avant/après |
| **Français / anglais**, catégories traduites | « Your friends abroad can follow too » | Story |

### app3 — le nouveau site public (`www.kayak-polo.info` après la bascule)

| Nouveauté | Message | Format adapté |
|---|---|---|
| Nouveau site, charte FFCK, mobile d'abord | « Le site fait peau neuve » | Reel de lancement, carrousel avant/après |
| **Pages compétition** (matchs, terrains, classement, progression, statistiques) | « Toute la compétition sur une page » | Carrousel |
| **Calendrier** et **abonnement ICS** | « Les dates de ta compétition directement dans ton agenda » | Reel tutoriel |
| **Historique et palmarès** | « Qui a gagné la N1 en 2012 ? » | Carrousel quiz |
| **Clubs** : liste, carte, fiche | « Trouve un club près de chez toi » | Visuel carte |
| **Recherche globale** | « Une équipe, un club, une compétition : tape, trouve » | Story |
| **Actualités** et **formulaires d'inscription** (paiement via HelloAsso) | « Inscriptions aux stages et tournois en ligne » | Publication Facebook |
| Passerelle « Suivre dans l'app » vers app2 | « Le site pour tout savoir, l'app pour le jour J » | Visuel |

### app4 — l'administration (`/admin2`)

| Nouveauté | Pour qui | Format adapté |
|---|---|---|
| **Contexte de travail** (événement / compétition) mémorisé sur toutes les pages | Organisateurs | Reel enregistrement d'écran, publication FB |
| **Contrôle équipement (scrutineering)** désormais dans app4, **profil 10 « Contrôle équipement »** ; il n'est **plus dans app2** | Officiels, contrôleurs | Publication FB épinglée + story |
| **Feuilles de présence** des équipes | Organisateurs, contrôleurs | Publication FB |
| **Règles d'éligibilité des joueurs**, vérification des compositions (licence, certificat, pagaie couleur, surclassement) | Clubs, resp. compétition | Carrousel FB |
| **Ajout des équipes depuis le classement précédent**, copie de compétition, copie de composition | Resp. compétition | Publication FB |
| **Passage des mandats à la nouvelle saison**, ajout de saison en masse | Resp. division / compétition | Publication FB |
| **Réinitialisation du mot de passe par e-mail** | Tous les utilisateurs | Story « mot de passe oublié ? » |
| **Tutoriel guidé** et page **Aide**, **thème sombre** | Tous les utilisateurs | Reel court |

## 4. Rythme

| Réseau | Publications par semaine | Contenu |
|---|---|---|
| Instagram | 3 publications (dont **1 Reel**) + 4 à 6 stories | Joueurs, supporters ; relais des annonces majeures |
| Facebook | 3 publications | Mêmes sujets + 1 publication organisateurs/clubs par semaine ; partage dans les groupes de clubs |

Jours de publication : **mardi, jeudi, samedi** (le samedi, on cible les gens au bassin le week-end). Les
stories sont quotidiennes les semaines de lancement (S6) et de la J1 (S9).

## 5. Calendrier semaine par semaine

Légende : 🅸 Instagram · 🅵 Facebook · 🎬 Reel · 🖼 carrousel · ⭕ story

### Phase 1 — Éveil (S1 à S3) : « Une nouvelle saison, de nouveaux outils »

| Semaine | Thème | Mardi | Jeudi | Samedi |
|---|---|---|---|---|
| **S1** 11-17 jan. | Teasing | 🅸🅵 🎬 « 2027 arrive. KPI aussi. » : montage rapide des trois applis, sans tout dévoiler | 🅸🅵 🖼 « Ce qui change cette saison » : 1 carte par appli (résultats, site, admin) | 🅸 ⭕ Sondage « Tu suis les scores comment ? » · 🅵 Appel aux clubs : « Qui organise une journée en mars ? » |
| **S2** 18-24 jan. | app2 : l'essentiel | 🅸🅵 🎬 Installer app2 sur l'écran d'accueil (iPhone puis Android, 20 s) | 🅸🅵 🖼 Mode Compétitions et lien direct `/group/2027/N1H` | 🅸 ⭕ Quiz « Il marche sans réseau ? » → oui · 🅵 Relais du Reel |
| **S3** 25-31 jan. | app4 : préparer la saison | 🅵 Publication longue : passage des mandats à la saison 2027, ajout de saison en masse | 🅵 🖼 Ajouter les équipes depuis le classement précédent et vérifier les compositions | 🅸🅵 🎬 « Le tutoriel guidé d'app4 en 30 s » + rappel : mot de passe oublié → lien par e-mail |

### Phase 2 — Démonstration (S4 et S5) : « Chaque appli, son usage »

| Semaine | Thème | Mardi | Jeudi | Samedi |
|---|---|---|---|---|
| **S4** 1-7 fév. | app2 : le jour du match | 🅸🅵 🎬 « Qui a marqué ? Touche le score » : feuille de match | 🅸🅵 🖼 Fiche équipe et Progression | 🅸 ⭕ Mode sombre avant/après · 🅵 Relais |
| **S5** 8-14 fév. | Teasing du site | 🅸🅵 ⭕ + 🖼 « Le site fait peau neuve le 15 février » : 3 extraits flous | 🅵 app4 : contexte de travail + **annonce : le contrôle équipement passe dans app4** (publication épinglée) | 🅸🅵 🎬 Compte à rebours, aperçu de la page d'accueil |

### Phase 3 — Lancement du site (S6 et S7) : « Le nouveau kayak-polo.info »

| Semaine | Thème | Mardi | Jeudi | Samedi |
|---|---|---|---|---|
| **S6** 15-21 fév. **Bascule** | Lancement | **Lundi 15** 🅸🅵 🎬 Reel de lancement + publication épinglée « Le nouveau site est en ligne » ; ⭕ stories toute la semaine | 🅸🅵 🖼 Visite guidée : compétitions, calendrier, clubs, historique, recherche | 🅸🅵 🖼 Quiz palmarès « Qui a gagné… ? » (réponses sur la page Historique) |
| **S7** 22-28 fév. | Le site au quotidien | 🅸🅵 🎬 S'abonner au calendrier de sa compétition (ICS) | 🅸🅵 Visuel carte des clubs « Trouve un club près de chez toi » | 🅵 Actualités et formulaires d'inscription (HelloAsso) · 🅸 ⭕ « Le site pour tout savoir, l'app pour le jour J » |

### Phase 4 — Préparation de la J1 (S8 et S9) : « Prêts pour le 13 mars »

| Semaine | Thème | Mardi | Jeudi | Samedi |
|---|---|---|---|---|
| **S8** 1-7 mars | Organisateurs et officiels | 🅵 Check-list organisateur : contexte de travail, présences, contrôle équipement, QR code à imprimer (PDF joint) | 🅵 🖼 Contrôle équipement dans app4 pas à pas (profil 10, équipes, kayak, gilet, casque, pagaies) | 🅸🅵 🎬 Coulisses : une table de marque qui prépare sa journée |
| **S9** 8-14 mars **J1** | Jour J | 🅸🅵 🖼 « Tout ce qu'il faut pour suivre la J1 » : app2, liens directs par catégorie | 🅸🅵 ⭕ Compte à rebours, rappel « installe l'app avant d'arriver au bassin » | **Sam. 13 / dim. 14** 🅸 ⭕ stories en direct (scores, QR code au bassin) · 🅵 publication des liens de suivi |

### Phase 5 — Suivi (S10) : 15-21 mars

| Mardi | Jeudi |
|---|---|
| 🅸🅵 🖼 Bilan de la J1 en chiffres (visiteurs app2, matchs suivis), merci aux organisateurs | 🅵 Appel aux retours : formulaire (sur le nouveau site) « Qu'est-ce qui vous a manqué ? » ; 🅸 ⭕ sondage |

## 6. Production

### 6.1 Liste des contenus à produire

| Type | Nombre | Contenu |
|---|---|---|
| 🎬 Reels (15 à 30 s, enregistrement d'écran + sous-titres, sans voix obligatoire) | 9 | Teasing, installation app2, feuille de match, tutoriel app4, compte à rebours, lancement du site, abonnement ICS, coulisses, (bilan en option) |
| 🖼 Carrousels (5 à 8 visuels) | 10 | Voir le calendrier |
| Visuels simples / publications longues FB | 10 | Dont 2 publications épinglées (contrôle équipement, lancement du site) |
| ⭕ Stories | ~50 | Sondages, quiz, comptes à rebours, relais |
| Imprimables | 2 | Affiche QR code de l'événement (A4), check-list organisateur (PDF) |

### 6.2 Charte visuelle

- Charte FFCK **univers Compétition**, la même que le nouveau site : bleus `#69b9e6` / `#357b9c`, rouges
  `#c94a4c` / `#882831`, noir `#1e1e1c`, gris `#c6c7c7` ; titres en Agency FB, textes en Raleway
  ([PUBLIC_SITE_REDESIGN_STRATEGY.md § 10](PUBLIC_SITE_REDESIGN_STRATEGY.md)).
- Un code couleur par appli, constant sur toute la campagne (ex. app2 bleu clair, site bleu foncé, admin rouge).
- Gabarits 1080×1350 (carrousel), 1080×1920 (Reel, story), 1200×630 (lien Facebook).
- Captures prises sur une **compétition 2026 terminée** (données publiques), en mode clair **et** sombre.

### 6.3 Production des Reels

Enregistrement d'écran sur téléphone (fonction native iOS / Android) et sur PC, montage avec gabarit commun
(intro 1 s, sous-titres incrustés, fin avec URL + QR code). Prévoir les 9 Reels **avant le 11 janvier** pour
les phases 1 et 2, et ceux des phases 3 et 4 **dès que la préprod de la bascule est figée**.

## 7. Préparation et contrôles (novembre 2026 à début janvier 2027)

| Quand | Action | Responsable |
|---|---|---|
| Novembre | Valider ce plan, fixer la date de bascule d'app3 (S6 par défaut) | Porteur du projet |
| Novembre | Relever les chiffres 2026 dans Matomo (J1 2026, app2, site) pour fixer les cibles du § 1 | Porteur du projet |
| Décembre | Produire gabarits, carrousels et Reels des phases 1 et 2 | Communication |
| Décembre | Rédiger la check-list organisateur et l'affiche QR code | Communication + un organisateur |
| Début janvier | Programmer les publications S1 à S5 (Meta Business Suite) | Communication |
| **Chaque jeudi** | Vérifier que les fonctionnalités de la semaine suivante sont **en production**, sinon permuter les semaines | Porteur du projet |
| Avant la S5 | Confirmer la bascule ; sinon appliquer le plan B (§ 8) | Porteur du projet |

## 8. Risques et plans de repli

| Risque | Effet | Parade |
|---|---|---|
| **Bascule d'app3 retardée** | Les phases 3 et 4 annoncent un site absent | Plan B : S6-S7 deviennent « Testez le nouveau site sur `beta.kayak-polo.info` et donnez votre avis » ; le lancement est reporté après la J1 |
| Fonctionnalités app3 des phases 2 à 4 encore « à livrer » | Annonce d'une page non disponible | Contrôle du jeudi (§ 7) ; ne jamais montrer une capture de préprod comme si elle était en ligne |
| Contrôleurs qui cherchent encore le scrutineering dans app2 | Blocage le matin de la J1 | Publication épinglée S5, rappel S8, message direct aux organisateurs de la J1, profil 10 attribué par les profils 1-2 **avant** le 13 mars |
| Version d'app2 périmée sur les téléphones (PWA installée) | Écran blanc, ancienne version | La spec [PWA_AUTO_UPDATE.md](../../specs/PWA_AUTO_UPDATE.md) est « à implémenter » : la livrer avant la S2 ou ajouter le geste « fermer et rouvrir l'app » dans le Reel d'installation |
| Pic de fréquentation le 13 mars | Lenteurs | Prévenir l'équipe technique du calendrier (S6 et S9) ; pas de déploiement le week-end de la J1 |
| Vacances scolaires de février (selon les zones) | Moins d'audience en S6-S7 | Republier le Reel de lancement en S8 |

## 9. Après la campagne

- Bilan chiffré au 21 mars 2027 (objectifs § 1) et note des retours reçus.
- Les meilleurs contenus (installation d'app2, ICS, contrôle équipement) sont réutilisés à chaque grand
  événement de la saison.
- Une fois ce plan exécuté, ce document est déplacé dans `archive/` avec son bilan (règle de `CLAUDE.md`).
