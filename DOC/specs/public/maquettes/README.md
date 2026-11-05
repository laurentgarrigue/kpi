# Maquettes de modernisation d'app3

Maquettes HTML validées le 2026-10-22 pour moderniser l'interface du site public (app3). Elles servent de
référence visuelle pour l'implémentation. Elles **ne remplacent pas les specs** : chaque page s'implémente en
mettant d'abord à jour sa spec (`PAGE_*.md` ci-dessous), puis en suivant le cycle TDD habituel
([CLEAN_CODE.md](../../../developer/guides/CLEAN_CODE.md)).

Les maquettes s'appuient sur de vraies réponses d'api2 (base de dev), capturées dans [data/](data/).

## Voir les maquettes

```bash
make dev                                         # app3 sur https://beta.kpi.localhost
python3 DOC/specs/public/maquettes/src/build.py  # génère sources/app3/public/maquettes/ (ignoré par git)
```

Puis ouvrir https://beta.kpi.localhost/maquettes/index.html. Les polices sont servies par app3 et les logos
par `kpi.localhost` : un fichier ouvert directement depuis le disque s'affiche sans elles.

Chaque page a une barre de démonstration en bas à gauche : retour à l'index, thème sombre, et des simulations
le cas échéant (match en direct, tableau à 4 tours, mois de septembre…). Cette barre ne fait pas partie du
design.

| Dossier | Contenu |
|---|---|
| `src/*.tpl.html` | une page par fichier ; les marqueurs `__NOM__` sont remplacés par les données |
| `src/maquette.css` | jetons de couleur (clair et sombre), en-têtes, blocs partagés |
| `src/maquette.js` | en-tête du site, en-têtes de compétition et d'événement, bloc match, formatage des noms |
| `src/build.py` | association marqueur → fichier de `data/`, génération |
| `data/` | réponses api2 capturées |

## Principes communs

- **Charte FFCK conservée** : navy `#20265b`, bleu KPI `#357b9c`, Agency FB pour les titres et les scores,
  Raleway pour le texte. C'est la mise en page qui change, pas l'identité.
- **La colonne des scores** est l'élément signature. Les scores sont en Agency FB, blanc sur navy, et forment
  une colonne continue (page Matchs) ou, ailleurs, un bloc étroit avec les deux scores empilés (Terrains,
  Déroulement). Le score perdant est atténué, la colonne est rouge pendant un match en direct.
- **Couleurs à sens unique** : l'or n'est utilisé que pour la finale et les médailles d'or, le rouge que pour
  le direct. Une couleur n'est jamais le seul porteur d'une information : le niveau, le statut ou le rang sont
  toujours écrits.
- **Statut affiché seulement s'il informe** : un score validé suffit à dire qu'un match est terminé ; seuls
  « en cours » et « provisoire » sont signalés.
- **Pas d'eyebrow en capitales** (« COMPÉTITION »), pas de libellé répété sur chaque carte (« Événement »).
- **Mobile d'abord** : aucun défilement horizontal de page à 390 px ; les tableaux larges défilent dans leur
  propre conteneur, les filtres nombreux deviennent des listes déroulantes.

## Pages

| Maquette | Route app3 | Spec | Décisions validées |
|---|---|---|---|
| `accueil` | `/` | [PAGE_HOME](../PAGE_HOME.md) | Le prochain rendez-vous ouvre la page (dates en très grand, « Ce week-end » / « En ce moment », compétitions du même événement listées dessous). Actualités à gauche (une à la une + deux), agenda à droite (« Ensuite », « Derniers résultats »). La phrase de présentation reste le `h1`, en petit. |
| `actualites` | `/news` | [PAGE_NEWS](../PAGE_NEWS.md) | Une actualité à la une, puis une liste éditoriale (vignette, titre, chapeau, date et catégorie). Filtres par catégorie, lien RSS. |
| `article` | `/news/{slug}` | [PAGE_NEWS](../PAGE_NEWS.md) | Colonne de lecture de 720 px, photo plus large que le texte, chapeau en gras, partage, précédente / suivante. |
| `calendrier` | `/calendar` | [PAGE_CALENDAR](../PAGE_CALENDAR.md) | Deux vues. **Par semaine** (lundi → dimanche) : les dates réelles du bloc, « Week-end » ou « Semaine », une compétition de 3 jours ou plus affiche ses dates et sa durée. **Mois** : grille complète, une barre par compétition sur ses jours (pointillé quand elle continue la semaine suivante), libellé complet avec lieu et département. Filtre par niveau, abonnement. |
| `competitions` | `/competitions` | [PAGE_COMPETITIONS](../PAGE_COMPETITIONS.md) | Index des groupes par section à gauche (liste déroulante sur mobile), classements résumés (5 premiers) de chaque compétition du groupe, événements où le groupe a joué. |
| `infos` | `/competitions/{s}/{c}/info` | [PAGE_COMPETITION](../PAGE_COMPETITION.md) | Dates et lieu en tête, officiels en grille, équipes par poule avec logos, podium si terminé, bloc « Dans votre agenda » (bouton d'abonnement, copier le lien, .ics, aide repliée). |
| (tous les onglets) | `/competitions/{s}/{c}/*` | [PAGE_COMPETITION](../PAGE_COMPETITION.md) | En-tête compact : titre, saison, statut, « Suivre en direct ». Le sous-titre identique au titre disparaît. **Encart événement** cliquable : vignette des dates, nom, « Les N compétitions, tous les matchs par terrain et le direct », bouton « Voir l'événement ». |
| `matchs` | `/competitions/{s}/{c}/games` | [PAGE_COMPETITION](../PAGE_COMPETITION.md) | Colonne des scores continue par journée, logos des clubs, terrain toujours visible. Choix du jour par liens (sans JavaScript), « Matchs à venir seulement ». Codes de tirage traduits pour les matchs à venir (« 1er poule B », « Vainqueur n° 222 »), masqués une fois joués. La finale est marquée en or. Mobile : équipes et scores empilés. |
| `deroulement` | `/competitions/{s}/{c}/progress` | [PAGE_COMPETITION](../PAGE_COMPETITION.md) | Une colonne par tour défini par l'organisateur (endpoint `charts`, 2 à 5 colonnes), **sans titre ni jour en tête de colonne** (titre conservé pour les lecteurs d'écran), contenu **centré verticalement** dans chaque colonne. Poules en tableaux (Pts, J, +/−), phases éliminatoires en matchs. Toucher une équipe surligne tout son parcours. Pas de classement final dans cet onglet. |
| `classement` | `/competitions/{s}/{c}/ranking` | [PAGE_COMPETITION](../PAGE_COMPETITION.md) | Podium en marches (2–1–3) avec logos, puis tableau complet ; colonnes secondaires masquées sur mobile. |
| `stats` | `/competitions/{s}/{c}/stats` | [PAGE_COMPETITION](../PAGE_COMPETITION.md) | Meilleur buteur mis en avant, une barre par joueur sur une échelle commune, filtre par équipe, « Voir les N suivants ». |
| `palmares` | `/history/{group}` | [PAGE_HISTORY](../PAGE_HISTORY.md) | **Pas de classement historique des titres** (il met en avant des équipes disparues). En tête : le tenant du titre, son logo et sa série en cours ; puis la frise des dix dernières saisons ; puis toutes les saisons avec raccourcis par décennie. Toucher une équipe surligne ses podiums. |
| `evenements` | `/events` | [PAGE_EVENT_GROUP](../PAGE_EVENT_GROUP.md) | Texte : « Un événement réunit plusieurs compétitions ou catégories sur un même lieu, le même week-end ou la même semaine. » Liste par mois, à venir d'abord (étiquette « Prochain »), puis « Déjà joués » ; recherche par nom ou lieu. |
| `evenement` | `/events/{id}` | [PAGE_EVENT_GROUP](../PAGE_EVENT_GROUP.md) | Bandeau navy (nom, lieu, dates, nombre de compétitions, direct). Une ligne par compétition : nom, podium avec logos, liens Matchs / Déroulement / Classement. |
| `terrains` | `/events/{id}/pitches` et onglet compétition | [PAGE_EVENT_GROUP](../PAGE_EVENT_GROUP.md) | Une ligne par horaire, **une carte par match** dans la colonne de son terrain (pas de quadrillage). Boutons de jour à gauche, de terrain alignés à droite (ils passent à la ligne sans se chevaucher). Mobile (< 600 px) : deux listes déroulantes Jour / Terrain, cartes empilées portant leur terrain. |
| `clubs` | `/clubs` (remplace aussi `/teams`) | [PAGE_CLUBS](../PAGE_CLUBS.md) | Menu « Clubs et équipes ». **Carte affichée directement**, recherche sur la même page (clubs par nom, ville, code ; équipes par nom), fiche du club à côté de la carte au clic (logo, adresse, e-mail, département, région, équipes). Boutons « Site web » et « Itinéraire » avec flèche de lien sortant. Plus de liste complète des clubs. Adresse partageable `?club=5006`. « Clubs près de moi ». |
| `equipe` | `/teams/{number}` | [PAGE_TEAM](../PAGE_TEAM.md) | Logo, nom, club, tenue ; décompte des médailles (titres, 2es, 3es places) ; palmarès par saison, phases de qualification atténuées. |
| `recherche` | `/search` | [FEATURE_SEARCH](../FEATURE_SEARCH.md) | Meilleur résultat en tête (club : logo, « Voir sur la carte »), filtres par type avec compteurs, résultats groupés. |
| `contenu` | `/{slug}` | [PAGE_CONTENT](../PAGE_CONTENT.md) | Colonne de lecture, sommaire « Sur cette page » qui suit la lecture (au-dessus du texte sur mobile). |
| `formulaire` | `/forms/{slug}` | [FEATURE_FORMS](../FEATURE_FORMS.md) | Champs groupés en sections, quantités avec boutons − / +, récapitulatif et total en direct (barre collante en bas sur mobile). |

## Implémentation (branche `app3_design`, 23/10/2026)

Toutes les pages du tableau sont implémentées dans app3, spec d'abord (critères ajoutés ou révisés dans chaque
`PAGE_*.md`, repérés « maquettes du 22/10/2026 »), tests nommés d'après ces critères. Écarts assumés :

- **Codes de tirage** : la maquette lit `[1E-1F/2B-2C]` en coupant sur `/` ; l'implémentation suit
  `MatchLabelParser` d'api2, la référence (un code par côté, A d'abord : `[1E-1F]` → « 1er poule E », « 1er poule F »).
- **Carte des clubs** : la décision Q-P3-5 (tuiles chargées après un clic) est conservée tant que le point RGPD
  ci-dessous n'est pas tranché.
- **Formulaire** : sections et récapitulatif avec total non implémentés (données absentes, cf. ci-dessous) ; champs
  sur deux colonnes, « facultatif », choix en cartes et quantités − / + le sont.
- **Calendrier** : pas de bouton « S'abonner au calendrier » global (api2 n'a pas d'ICS de tout le calendrier).
- **Palmarès** : les noms d'équipes du tableau surlignent les podiums ; les liens vers les fiches d'équipe sont dans
  le bandeau du tenant du titre et la frise.

## Points à traiter côté données et API

Relevés en construisant les maquettes ; les maquettes les contournent, l'implémentation doit les régler à la source.

- ✅ **Logos absents** de `ranking`, `info` et `history` : api2 prend désormais le logo du club quand l'équipe n'en a
  pas, comme pour les matchs (`TeamLogos`).
- **Équipes fictives dans les clubs** : les tournois créent des équipes « (1er Poule A) », « Vainqueur match 21 »…
  que `/club/{code}` renvoie avec les vraies. app3 les masque (`isPlaceholderTeam`) ; à régler dans api2.
- ✅ **Noms en capitales** (clubs, comités) : reformatés pour l'affichage par app3 (`titleCase`, `committeeName`).
- **Entités HTML** dans certains libellés (« Coupe d&#039;Europe ») : décodées par app3 pour les noms de personnes
  (`personName`) ; les autres libellés restent à corriger à la source.
- **Officiels répétés** dans chaque journée de `info`, casse des noms variable.
- **Lien événement ↔ compétitions** : l'accueil rattache les compétitions à l'événement par leurs dates ; un
  vrai lien côté API serait plus sûr.
- **Formulaires sans sections** : le regroupement des champs est fait par la maquette ; un champ « section »
  permettrait aux rédacteurs de le choisir.
- **Fixtures** : la Coupe de France Hommes 2025 garde des équipes non attribuées (« (Winner game #236) »).

## À décider avant d'implémenter

- **Carte des clubs et RGPD** : la page actuelle ne charge les tuiles OpenStreetMap qu'après un clic de
  consentement. La maquette les charge directement, comme demandé : à valider au regard du consentement.
- **Thème sombre** : les jetons sombres existent dans `maquette.css`, mais le rendu sombre n'a pas été vérifié
  page par page.
