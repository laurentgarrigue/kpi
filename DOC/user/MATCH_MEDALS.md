# Médailles de match

**Pour qui** : profils 1 et 2 (page **Opérations › Médailles** de l'admin). Spec :
[FEATURE_MATCH_MEDALS.md](../specs/public/FEATURE_MATCH_MEDALS.md).

## Ce que c'est

À partir des buts et des cartons notés sur la feuille de match, KPI attribue des **médailles** aux équipes, aux
joueurs et aux arbitres (Premier but · Opener, Supériorité · Powerplay, Triplé · Hat-trick, Match couperet…), des
**étiquettes** aux matchs (Suspense · Thriller…) et calcule des **statistiques** par compétition. Rien n'est saisi en
plus.

- Seuls les matchs **validés et publiés** comptent ; les médailles apparaissent une fois le match validé.
- Les médailles liées au déroulé (premier but, supériorité…) demandent que chaque but ait son temps. Une feuille sans
  temps (ou avec des temps à `00:00`) n'attribue que les médailles liées au score.
- Les faits d'un match comptent pour ses **deux arbitres**, et seuls les arbitres **désignés nommément** comptent.

Où on les voit : la feuille de match et la **page équipe** d'**app2**, l'onglet **Stats** des compétitions, la fiche
des équipes et la page **/medals** (explications) du site public.

Une médaille se montre dans un **blason** à la couleur de sa famille, signé d'un petit « KPI » en haut à gauche ; un
chiffre en pastille (« ×3 ») dit combien de fois elle a été gagnée. Sur la page équipe d'app2, le nombre de buts d'un
joueur est le **ballon** avec la même pastille (« ×13 »), et les médailles de l'équipe et de ses joueurs portent sur
l'événement (ou le groupe) choisi.

## La page Médailles

| Onglet | Usage |
|---|---|
| **Catalogue** | Tous les éléments ; survoler une médaille affiche son explication (dans la langue de l'interface), la colonne **Seuils actuels** donne les seuils qu'elle utilise, et la colonne **Attributions** donne le nombre de médailles de la saison du contexte avec leur proportion **par match** et le nombre de matchs sur lesquels elles se mesurent (feuilles complètes pour les médailles de timeline, matchs validés sinon) : au-dessus de 1 par match pour une médaille d'équipe, ou très au-dessus pour une médaille de joueur, le seuil est probablement trop bas. Cocher **app2** ou **app3** publie un élément sur cette application ; l'historique s'affiche aussitôt, sans recalcul. **Retiré** le masque partout et arrête son calcul. **Modifier** : noms français et anglais, explications. Les variables entre accolades (`{bounceBackSeconds}`) sont remplacées par les seuils de la saison. Chaque modification est inscrite au journal. |
| **Saisons** | Les seuils utilisés pour calculer chaque saison et la version des règles. Une alerte signale une saison calculée avec une version antérieure. **Profil 1 seulement** : modifier les **seuils par défaut** (formulaire replié, à afficher à la demande), **recalculer** une saison avec ces seuils et **supprimer** les calculs d'une saison, chaque fois après une confirmation. Une saison supprimée n'est pas recalculée automatiquement : un calcul explicite (bouton *Calculer*) la remet en service. |
| **Statistiques** | Toutes les statistiques actives, publiées ou non, sur le **contexte de travail** (saison et compétitions). |
| **Arbitrage** | Statistiques d'arbitrage (cartons par match, matchs fluides, moment et motifs des cartons, binômes…), à côté de la moyenne. Un indicateur ne s'affiche qu'à partir de 5 matchs arbitrés (« — » en dessous). Tri par nombre de matchs, jamais par cartons. |

Chaque onglet a un champ de **recherche** (sans accents ni majuscules) qui filtre ce qui est affiché : médailles, saisons et seuils, lignes d'une statistique.

## Calcul et recalcul

- Valider ou dévalider un match dans l'admin calcule ou supprime ses médailles **aussitôt**.
- Les validations faites ailleurs (legacy) sont reprises toutes les 10 minutes.
- **Changer un seuil** (onglet Saisons, profil 1 ; le fichier `sources/api2/config/packages/match_medals.yaml` donne les valeurs d'origine) ne change rien tant qu'on ne recalcule pas
  une saison : bouton *Recalculer*, ou `make api2_match_medals_compute season=AAAA`. Les autres saisons gardent leurs résultats et leurs
  seuils.
