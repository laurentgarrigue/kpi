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

## 3. Questions ouvertes

- Profil du mandat généré par rôle.
- Règles de cohérence entre les Désignations d'un événement et celles de ses journées.
- Composition du jury d'appel (finales, représentants fédéraux).
- Contenu détaillé du bilan, son workflow (brouillon, soumission, verrouillage), ses lecteurs.
- Barème et calcul des rétributions ; infractions.
