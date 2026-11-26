# Spécification - Page Suspensions (app4)

## Statut : 🚧 EN COURS (2026-11-26)

**Fonctionnalité** : [FEATURE_CARD_SUSPENSIONS.md](FEATURE_CARD_SUSPENSIONS.md) (S14, S16 à S19)
**Route** : `/card-suspensions` · **Profils** : 1 et 2 · **API** : `GET /admin/card-suspensions?season=`,
`POST /admin/card-suspensions/{id}/lift`

---

## 1. Objectif

Voir les suspensions que KPI a détectées sur la saison de travail, retrouver le joueur, son équipe et le rapport de
jury d'appel, et **lever** une suspension une fois ses matchs purgés.

## 2. Accès

Menu **Référentiels › Suspensions**, profils 1 et 2 seulement (comme l'API, `ROLE_ADMIN`). Saison : celle du contexte
de travail.

## 3. Contenu

1. **En-tête** : titre, saison.
2. **Filtres** : état (toutes, en vigueur, levées, annulées ; « en vigueur » par défaut), recherche sur le nom ou la
   licence.
3. **Tableau**, une ligne par suspension, les plus récentes d'abord :

   | Colonne | Contenu |
   |---|---|
   | Joueur | NOM Prénom, licence, club |
   | Équipe | Libellé, lien vers sa feuille de présence (`/presence/team/{id}`) ; « — » si aucun statut n'a été posé |
   | Match | Compétition, n° et date du match déclencheur |
   | Règle | Libellé de la règle retenue (catalogue reçu de l'API) et décompte (« 4e ») ; autres règles atteintes en dessous |
   | Suspension | « 1 match », « 2 matchs + jury d'appel », « 1 de plus, 3 au total pour la journée » |
   | État | En vigueur · Levée le … par … · Annulée le … ; « non notifiée » pour une suspension enregistrée en `--silent` |
   | Actions | **Lever** (suspension en vigueur), lien **Rapport** vers le brouillon de jury d'appel |

4. **Règles de la saison** (repliable) : le catalogue en vigueur — libellé, seuil, suspension, jury d'appel — ou
   « Aucune règle pour cette saison » avant 2027.

**Lever** demande une confirmation (« Lever la suspension de NOM Prénom ? Il retrouvera son statut d'avant dans la
feuille de présence de son équipe »), puis recharge la ligne.

## 4. Critères d'acceptation

- **PSU-01** — Étant donné un profil 3 ou plus, alors l'entrée de menu n'apparaît pas (et l'API répond `403`).
- **PSU-02** — Étant donné des suspensions de la saison, alors le filtre « en vigueur » (par défaut) ne montre que les
  suspensions en vigueur ; « toutes » les montre toutes.
- **PSU-03** — Étant donné une suspension « 1 de plus, 3 au total pour la journée », alors la colonne Suspension
  affiche l'ajout et le total ; une suspension avec jury d'appel affiche « + jury d'appel » et le lien Rapport.
- **PSU-04** — Étant donné une suspension en vigueur, quand on clique Lever et qu'on confirme, alors l'API de levée est
  appelée et la ligne passe à « Levée le … ».
- **PSU-05** — La règle est affichée avec le libellé du catalogue reçu de l'API (aucun libellé de règle codé dans app4,
  ADR-0004).
- **PSU-06** — Étant donné une saison sans catalogue, alors le bloc des règles affiche « Aucune règle pour cette
  saison ».

Tests : `tests/unit/cardSuspensions.spec.ts` (fonctions pures : libellés, filtres), noms de test portant le code.
