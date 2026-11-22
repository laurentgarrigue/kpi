# Spécification - Désignations, jury d'appel, bilan de journée et rétributions

## Statut : 📝 EN CADRAGE (interrogatoire en cours, sections à compléter)

**Spec créée le** : 2026-11-22
**Décisions d'architecture** : [ADR-0002](../developer/reference/adr/0002-plus-d-evolution-du-legacy.md), [ADR-0003](../developer/reference/adr/0003-designations-source-unique-absorbe-kp-rc.md)
**Vocabulaire** : [GLOSSARY.md](../../GLOSSARY.md), section « Officiels et responsables »

---

## 1. Objectif

1. **Désigner**, en amont de la saison ou des journées, les responsables et les officiels : RC (compétition),
   responsables d'équipe (équipe et club), R1, délégués CNA, chef des arbitres et membres du jury d'appel
   (événement ou journée), en leur attribuant **dans le même geste** le compte et le mandat adéquats.
2. Permettre au **délégué CNA** de remplir un **bilan de journée** à la fin de la compétition ou dans les 3 jours
   qui suivent : représentants des clubs et des athlètes, bilan général (dont l'évaluation du site), bilan sportif,
   bilan arbitrage, bilan logistique, rétributions et infractions. Le bilan est complété par les données KPI
   (dates, lieu, officiels, nombre de matchs, cartons verts, jaunes et rouges, etc.).
3. Faciliter, en cours ou en fin de saison, la **synthèse des bilans** et le **calcul des rétributions**.

Périmètre technique : app4 (saisie), api2 (modèle, règles, PDF), app3 si besoin public. Aucune évolution legacy
(ADR-0002), mais le legacy doit continuer à fonctionner pendant la période de secours.

## 2. Décisions prises

| # | Sujet | Décision |
|---|---|---|
| D1 | Rattachement du bilan | Un bilan **par événement**, avec repli sur la **journée** quand elle n'appartient à aucun événement. |
| D2 | Source de vérité | Table `kp_designation` `(saison, rôle, type et id de périmètre, licence, ordre, qualité)`. Les champs texte de `kp_journee` en sont recopiés. |
| D3 | Rôles | Le catalogue des rôles est défini à un seul endroit (api2) : périmètres autorisés et profil du mandat généré. |
| D4 | `kp_rc` | Absorbée dans `kp_designation` (rôle `RC`, périmètre compétition), puis remplacée par une vue SQL en lecture seule pendant la période de secours. |
| D5 | Compte | Si un licencié désigné n'a pas de compte, le compte est créé automatiquement (`Code` = licence, profil de base 8), **seulement si son email est connu**. Le profil de base d'un compte existant n'est jamais modifié. |
| D6 | Mandat | Un **mandat généré par saison et par rôle**, dont le périmètre est l'union des Désignations de ce rôle. Il est recalculé à chaque création ou suppression de Désignation, et supprimé quand il n'en reste plus. |
| D7 | Personnes désignables | Toute personne de la base des joueurs, Joueurs internationaux compris. Sans email, la Désignation est enregistrée sans compte ni mandat. |
| D8 | Profils des mandats | RC → 4 ; responsable d'équipe → 7 (avec `limitation_equipe_club` = club de l'équipe) ; délégué CNA → 5 ; R1 et chef des arbitres → 6 ; représentant des athlètes et autres membres du jury d'appel → aucun mandat. Un profil 3–4 désigné garde ses droits et bascule par le sélecteur de mandat. |
| D9 | Périmètres emboîtés | Une Désignation se pose sur l'**événement**, la **compétition** (saison) ou la **journée**. Pour une journée et un rôle donnés, la Désignation la plus précise l'emporte : journée, puis compétition, puis événement. Cas du tournoi (CP) : on désigne une fois sur la compétition (une catégorie) ou sur l'événement, et toutes les phases en héritent. On ne désigne sur une phase que pour faire une exception. |
| D10 | Périmètre du mandat | Calculé depuis les journées effectivement couvertes : `filtre_competition` pour une Désignation sur une compétition, `filtre_journee` pour un événement ou une journée. Il est recalculé quand une journée est créée, supprimée ou (dés)associée à un événement. |
| D11 | Tableaux de suivi | Vues de couverture : rôles requis non pourvus par compétition, événement, journée et équipe ; club de chaque officiel désigné ; répartition par club et par personne pour l'équilibre des désignations et les rétributions (§ 4). |
| D12 | Rapports officiels | Le bilan de journée est le premier d'une famille de **rapports officiels** au même formalisme (formulaire app4 pré-rempli depuis KPI, PDF Gotenberg, rôle requis, destinataires) : rapport d'incident (arbitre), rapport de jury d'appel, fiche d'évaluation d'arbitrage. Le rôle requis et les destinataires sont définis par type de rapport. |

## 3. Questions ouvertes

- Rôles « requis » par niveau de compétition (ce qui rend une case rouge dans le suivi).
- Composition du jury d'appel (représentant des équipes ou des athlètes, finales, représentants fédéraux).
- Structure des rapports officiels : définie en code ou configurable dans app4.
- Contenu détaillé du bilan, son workflow (brouillon, soumission, verrouillage), ses lecteurs.
- Barème et calcul des rétributions ; infractions.

## 4. Suivi des désignations (app4)

À détailler. Intentions validées (D11) :

- **Matrice de couverture** : en lignes les compétitions, puis les événements et les journées (héritage visible : une
  case pourvue par héritage est distinguée d'une case pourvue en propre) ; en colonnes les rôles ; une case vide sur
  un rôle requis est signalée. Filtres : saison, niveau, compétition, « seulement les manques ».
- **Équipes sans responsable désigné**, par compétition.
- **Club de chaque officiel désigné** affiché dans la matrice, pour repérer les conflits d'intérêts (officiel du même
  club qu'une équipe engagée) et l'équilibre entre clubs.
- **Récapitulatif par personne et par club** : nombre de désignations et de journées couvertes, en cours de saison et
  en fin de saison ; il alimente le calcul des rétributions.

## 5. Rapports officiels

Exemples fournis par le métier (2026-11-22) : rapport de jury d'appel, rapport d'incident disciplinaire, fiche
d'évaluation d'arbitrage (5 blocs notés sur 10, note finale sur 50, aptitudes et niveau recommandé).

| Rapport | Rédacteur | Rattachement | Données KPI pré-remplies |
|---|---|---|---|
| Bilan de journée | Délégué CNA | Événement (ou journée) | Dates, lieu, officiels désignés, nombre de matchs, cartons |
| Rapport d'incident | Arbitre du match | Match | Match, équipes, arbitres, carton concerné |
| Rapport de jury d'appel | Président du jury (délégué CNA) | Événement ou journée, matchs concernés | Composition du jury, journée, dates, lieu, matchs, arbitres et leurs clubs |
| Fiche d'évaluation d'arbitrage | Évaluateur | Match + arbitre évalué | Saison, compétition, journée, match, rencontre, scores, arbitre secondaire |

Destinataires et workflow : à définir par type.
