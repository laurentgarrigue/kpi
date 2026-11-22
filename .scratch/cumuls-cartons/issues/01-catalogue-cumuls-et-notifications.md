# Catalogue des cumuls de cartons, suspensions et notifications

Status: needs-triage
Blocked by: —

## Besoin

Aucun catalogue n'existe aujourd'hui (ADR-0004). Détecter les cumuls de cartons, en déduire la suspension prévue par
le règlement et **notifier** les personnes concernées.

## Règles (art. 50, « Cartons et suspensions »)

| Situation | Suspension |
|---|---|
| 8 verts dans la saison (avant : 12) | Match suivant |
| 2 jaunes/rouges dans des matchs différents d'une journée, puis chaque carton en plus | Match suivant |
| 4 jaunes/rouges dans la saison (avant : 6 jaunes) | Match suivant |
| 1 carton noir dans un match | 2 matchs + jury d'appel |
| 2 noirs dans une journée | 3 matchs + jury d'appel |
| 3 noirs dans la saison ou comportement grave | 4 matchs + jury d'appel |

Les cartons noirs entrent aussi dans les critères de départage des égalités, avant les rouges. Pour un départage à
3 équipes ou plus, les critères sont réappliqués en cascade (à vérifier dans `AdminRankingsController` / classements).

## Décisions déjà connues

- **Critères** : dans le règlement ou ses annexes, donc au plus une évolution par an. Ils vont dans un catalogue en
  code, versionné par saison (ADR-0004).
- **Destinataires** : les membres du **bureau de la CNA** et les **responsables de compétition** concernés. Ils
  peuvent changer en cours de saison : on les tire des Désignations (`kp_designation`, rôles `BUREAU_CNA` et `RC`,
  FEATURE_DESIGNATIONS.md), sans modifier le code.

## Questions à trancher

- Périmètre du décompte « saison » : toutes compétitions, ou par niveau, ou par compétition ?
- Source des cartons : timeline (`kp_match_detail`) des matchs validés seulement ?
- Une suspension « jury d'appel » ouvre-t-elle un rapport de jury d'appel pré-rempli (FEATURE_DESIGNATIONS.md § 5) ?
- Affichage de la suspension sur la feuille de présence ou dans la console de scoring (joueur suspendu) ?

## Comments
