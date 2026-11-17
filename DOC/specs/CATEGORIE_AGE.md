# Spécification - Catégorie d'âge des joueurs

## Statut : ✅ LIVRÉ (en production ; checklist close le 14/11/2026)

Vocabulaire : [GLOSSARY.md](../../GLOSSARY.md) (joueur international, date de naissance inconnue, catégorie d'âge).
Décision d'architecture : [ADR 0001 - catégorie d'âge dérivée](../../docs/adr/0001-categorie-age-derivee.md).
Déploiement et recette : [CATEGORIE_AGE_ROLLOUT_CHECKLIST.md](../developer/archive/features/CATEGORIE_AGE_ROLLOUT_CHECKLIST.md).

## Problem Statement

Les joueurs internationaux ont des catégories d'âge incohérentes d'une saison à l'autre : un même joueur né en 1972 apparaît V5, puis V9, puis V4 et V9 dans la même saison. Leur date de naissance a souvent été connue en cours de route, et en attendant elle a été saisie avec une date conventionnelle (`01/01/1901`, `00/00/0000`) ou laissée vide. La catégorie d'âge est figée au moment où le joueur entre dans une composition et recopiée de saison en saison, si bien qu'une date corrigée plus tard ne corrige rien, et la date conventionnelle 1901 fabrique des Vétéran 9 (75-120 ans). Certains écrans calculent en plus l'âge sur l'année civile courante ou sur la saison d'origine de la licence au lieu de la saison de la compétition.

En base de développement : 3 986 joueurs internationaux, dont 695 nés « le 01/01/1901 », 961 nés « le 00/00/0000 » et 81 sans date ; les licenciés FFCK n'ont aucune date absente.

## Solution

La catégorie d'âge devient une valeur toujours déduite de l'année de naissance du joueur et de la saison de la compétition, calculée par une règle unique. Une date de naissance inconnue est une absence de valeur, jamais une date conventionnelle, et ne donne aucune catégorie d'âge, affichée « ? » (« Date de naissance inconnue »). La catégorie stockée dans les compositions n'est qu'un cache, recalculé quand la date de naissance d'un joueur change, quand il est ajouté ou copié dans une composition, et par une migration qui corrige tout l'historique, compétitions verrouillées comprises. La saisie et les imports n'acceptent plus de date invraisemblable ni n'effacent une date connue, et la page Athlètes permet de retrouver les joueurs dont la date reste à compléter.

## User Stories

### Calcul de la catégorie d'âge

1. En tant que gestionnaire de compétition, je veux que la catégorie d'âge d'un joueur soit calculée à partir de son année de naissance et de la saison de la compétition, afin qu'elle soit juste quelle que soit la façon dont le joueur a été engagé.
2. En tant que gestionnaire, je veux qu'un même joueur puisse être V3 en 2020 et V4 en 2025, afin que sa catégorie suive son âge au fil des saisons.
3. En tant que gestionnaire, je veux que tous les écrans, PDF et exports donnent la même catégorie d'âge pour un joueur et une saison, afin de ne plus voir deux catégories différentes pour la même personne.
4. En tant que gestionnaire, je veux que la catégorie d'âge ne dépende jamais de l'année civile en cours ni de la saison d'origine de la licence, afin que consulter une saison passée donne la catégorie de cette saison-là.
5. En tant que gestionnaire, je veux qu'un joueur dont la date de naissance est inconnue n'ait aucune catégorie d'âge, afin de ne jamais voir une catégorie inventée (SEN, V9).
6. En tant que gestionnaire, je veux que les bornes de chaque catégorie restent celles de la table des catégories (POU 9-10 … V9 75-120), afin de ne pas changer le règlement en corrigeant les données.

### Affichage d'une date inconnue

7. En tant que gestionnaire, je veux voir « ? » à la place de la catégorie d'âge d'un joueur dont la date de naissance est inconnue, afin de distinguer « inconnue » de « pas encore calculée ».
8. En tant que gestionnaire, je veux que le « ? » soit accompagné du libellé « Date de naissance inconnue » quand la place le permet (infobulle, fiche), afin de comprendre immédiatement pourquoi.
9. En tant que gestionnaire, je veux que les feuilles de présence PDF affichent « ? » pour un joueur sans date de naissance, afin que le document imprimé reste lisible et honnête.
10. En tant qu'utilisateur d'un export CSV, je veux une cellule vide pour la catégorie d'âge d'un joueur sans date, afin de pouvoir trier et filtrer dans un tableur.

### Saisie et correction de la date de naissance

11. En tant que gestionnaire autorisé à modifier un athlète, je veux corriger la date de naissance d'un joueur et voir ses catégories d'âge recalculées dans toutes ses compositions, toutes saisons confondues, afin qu'une seule correction suffise.
12. En tant que gestionnaire, je veux que ce recalcul touche aussi les compétitions terminées et verrouillées, afin que l'historique ne conserve pas de catégorie fausse.
13. En tant que gestionnaire, je veux pouvoir indiquer explicitement « date inconnue » sur la fiche d'un athlète, afin de ne plus avoir à inventer une date pour valider le formulaire.
14. En tant que gestionnaire, je veux que la fiche refuse une date de naissance qui donnerait plus de 100 ans ou moins de 5 ans pour la saison en cours, avec un message explicite, afin d'empêcher le retour des dates conventionnelles.
15. En tant que gestionnaire, je veux pouvoir effacer une date de naissance erronée (repasser à « inconnue »), afin de corriger une date fausse que je ne connais pas.
16. En tant que gestionnaire, je veux qu'effacer la date d'un joueur efface aussi ses catégories d'âge dans ses compositions, afin que l'effet soit cohérent dans les deux sens.

### Compositions d'équipe

17. En tant que gestionnaire, je veux qu'un joueur ajouté à une composition reçoive la catégorie d'âge de la saison de cette compétition, afin qu'elle soit juste dès l'engagement.
18. En tant que gestionnaire, je veux que la copie d'une composition vers une autre saison recalcule la catégorie d'âge de chaque joueur pour la saison de destination, afin de ne pas propager la catégorie de la saison source.
19. En tant que gestionnaire, je veux que l'ajout d'un joueur depuis la recherche de licence de l'ancienne interface applique la même règle, afin que les deux interfaces donnent le même résultat.

### Éligibilité

20. En tant que gestionnaire d'une compétition à limite d'âge, je veux un avertissement non bloquant « date de naissance inconnue, catégorie d'âge non vérifiable » quand j'engage un joueur sans date, afin de pouvoir engager un international et compléter sa date plus tard.
21. En tant que contrôleur, je veux que cet avertissement apparaisse dans les contrôles d'éligibilité de la feuille de présence, afin de savoir quels joueurs n'ont pas pu être vérifiés.
22. En tant que gestionnaire, je veux que les surclassements existants restent inchangés, afin que les décisions déclarées ne soient pas touchées par le recalcul.

### Imports

23. En tant que gestionnaire important des internationaux (fichiers ICF CSV/XML), je veux qu'un joueur importé sans date de naissance soit enregistré avec une date inconnue, afin de ne plus créer de date conventionnelle.
24. En tant que gestionnaire, je veux qu'une réimportation ne remplace jamais une date de naissance connue par une date absente, afin de ne pas perdre les dates complétées à la main.
25. En tant que gestionnaire, je veux qu'un import qui apporte une vraie date pour un joueur à date inconnue la prenne en compte et recalcule ses catégories d'âge, afin que l'import complète les données.
26. En tant que gestionnaire, je veux qu'une date invraisemblable dans un fichier importé soit traitée comme une date inconnue, afin que l'import ne réintroduise pas de 1901.
27. En tant que gestionnaire, je veux que l'import d'un événement exporté recalcule les catégories d'âge au lieu de reprendre celles du fichier, afin que la règle unique s'applique aussi là.

### Retrouver les joueurs à compléter

28. En tant que gestionnaire, je veux filtrer la liste des athlètes sur « date de naissance inconnue », afin de retrouver les joueurs à compléter.
29. En tant que gestionnaire, je veux combiner ce filtre avec « joueur international » et « engagé cette saison », afin de traiter en priorité les joueurs qui jouent actuellement.
30. En tant que gestionnaire, je veux voir le nombre de joueurs correspondant au filtre, afin de mesurer le travail restant.

### Migration des données existantes

31. En tant qu'administrateur, je veux un rapport à blanc qui indique combien de dates conventionnelles deviennent « inconnue » et combien de catégories d'âge changent, par saison et par catégorie (avant → après), afin de mesurer l'impact avant d'agir en production.
32. En tant qu'administrateur, je veux exécuter la migration seulement après avoir relu ce rapport, afin de garder la maîtrise d'un changement qui touche les saisons verrouillées.
33. En tant qu'administrateur, je veux que la migration convertisse `01/01/1901` et `00/00/0000` en date inconnue, puis recalcule toutes les catégories d'âge des compositions, afin de corriger tout l'historique en une fois.
34. En tant qu'administrateur, je veux pouvoir relancer la migration sans effet de bord (idempotente), afin de m'en servir comme filet de sécurité après un correctif ou une saisie par l'ancienne interface.
35. En tant qu'administrateur, je veux que la fusion de doublons de joueurs considère une date conventionnelle comme absente, afin qu'elle choisisse bien la fiche qui porte la vraie date.

## Implementation Decisions

- **Module unique « catégorie d'âge »** dans api2, profond et sans état : à partir d'une date de naissance (ou de son absence) et d'une saison, il rend une catégorie ou « inconnue » ; il expose aussi la règle de plausibilité d'une date (âge > 100 ans ou < 5 ans pour la saison donnée = invraisemblable). La table des catégories lui est fournie, ce qui le rend testable sans base. Il remplace la méthode de calcul actuelle du contrôle d'éligibilité et les calculs dupliqués (fiche athlète, classements, statistiques, pool d'arbitres, feuilles de présence).
- **Saison de référence** : toujours la saison de la compétition (`Code_saison` de l'équipe). Les calculs basés sur l'année civile ou sur la saison `Origine` de la licence sont supprimés.
- **Date inconnue = `NULL`** dans `kp_licence.Naissance`. Aucune date conventionnelle n'est écrite par le code ; `1901-01-01` et `0000-00-00` sont convertis par la migration.
- **Cache `kp_competition_equipe_joueur.Categ`** conservé (ADR 0001), `NULL` quand la catégorie est inconnue. Points d'écriture :
  1. modification (ou effacement) de la date de naissance d'un athlète : recalcul de toutes ses compositions, toutes saisons, verrou compris ;
  2. ajout d'un joueur et copie d'une composition : calcul pour la saison de l'équipe de destination ;
  3. commande console de migration.
- **Le verrou de compétition (END) ne bloque pas ce recalcul** : il protège équipes, journées et présents, pas la valeur dérivée.
- **Commande console de migration** avec option `--dry-run` : le dry-run produit le rapport (dates converties, changements de catégorie par saison et par couple avant → après) sans rien écrire ; l'exécution réelle fait la même chose en écrivant. Idempotente.
- **API de la fiche athlète** : la date de naissance accepte une valeur vide (= inconnue) ; une date invraisemblable est refusée avec une erreur de validation explicite. Le formulaire app4 propose une case « date inconnue ».
- **API de la liste des athlètes** : nouveaux filtres « date de naissance inconnue », « joueur international », « engagé dans la saison », combinables, avec le total des résultats.
- **Contrat d'affichage** : l'API renvoie une catégorie d'âge absente (`null`) ; c'est le client (app4, PDF) qui affiche « ? » et le libellé « Date de naissance inconnue » ; les exports CSV laissent la cellule vide.
- **Éligibilité** : une date inconnue dans une compétition à bornes d'âge produit un avertissement non bloquant, quel que soit le mode d'application (bloquant ou non) des autres règles.
  - *Hypothèse retenue (#372)* : « compétition à bornes d'âge » = compétition dont `Age_min` ou `Age_max` est renseigné et non nul. L'avertissement est un niveau distinct des erreurs d'éligibilité : il ne devient jamais une erreur, ne bloque pas l'ajout (même en compétition nationale), ne passe pas le joueur en Inactif à la copie et n'affiche pas le badge rouge ([PLAYER_ELIGIBILITY_RULES.md](../developer/reference/PLAYER_ELIGIBILITY_RULES.md) § 4.3). Le contrôle du surclassement, lui, reste inchangé : dans une compétition de `SURCLASSEMENT_COMPETITIONS`, un joueur sans catégorie (date inconnue) reste soumis au surclassement.
- **Imports** (ICF CSV/XML, import d'événement) : date absente ou invraisemblable → `NULL` ; une date connue en base n'est jamais remplacée par `NULL` ; l'import d'événement recalcule `Categ` au lieu de reprendre la valeur du fichier.
- **Fusion de joueurs** : une date conventionnelle est traitée comme absente lors du choix de la fiche cible et de la recopie de date.
- **Legacy** (ajout depuis la recherche de licence, imports ICF CSV/XML) : corrigé sur place, sans test (calcul sur la saison de la compétition, `NULL` si date absente, pas d'écrasement d'une date connue). La commande de migration, relançable, rattrape tout écart éventuel.

## Testing Decisions

- **Un bon test** vérifie un comportement observable (catégorie rendue, réponse HTTP, contenu de la base après l'appel), jamais la structure interne. Les tests sont nommés d'après les user stories ci-dessus.
- **Seam 1 - module catégorie d'âge (tests unitaires)** : bornes de chaque catégorie, date inconnue, changement de catégorie d'une saison à l'autre, plausibilité (100 ans / 5 ans, dates conventionnelles). Prior art : les tests unitaires du contrôle d'éligibilité dans `tests/Unit/Eligibility/`.
- **Seam 2 - endpoints HTTP d'api2 (tests d'intégration)** : modification et effacement de la date d'un athlète avec recalcul des compositions (y compris d'une compétition verrouillée), refus d'une date invraisemblable, ajout et copie d'un joueur, filtres de la liste des athlètes, avertissement d'éligibilité, import d'événement. Prior art : `ApiTestCase` et les tests de `tests/Integration/` (par exemple contrôle d'équipement et feuilles de présence).
- **Seam 3 - commande de migration (test d'intégration)** : le dry-run n'écrit rien et rapporte les bons comptes ; l'exécution convertit les dates conventionnelles et recalcule `Categ` ; une seconde exécution ne change plus rien.
- **Non testé automatiquement** : le code legacy (pas de seam) ; il est couvert par la commande de migration relançable et par la recette manuelle.

## Out of Scope

- Les surclassements (`kp_surclassement`) : décisions déclarées, inchangées.
- Les bornes de la table des catégories et le règlement sportif.
- Les règles d'âge propres aux compétitions (`Age_min`/`Age_max` des compétitions) au-delà de l'avertissement pour date inconnue.
- La collecte des dates de naissance manquantes elle-même (travail de saisie des gestionnaires).
- La suppression de la colonne `Categ` (rejetée par l'ADR 0001).
- L'ancienne interface au-delà des trois écritures legacy citées.

## Décisions de revue et écarts acceptés

- **Décision utilisateur** : date inconnue dans une compétition à surclassement (`SURCLASSEMENT_COMPETITIONS`) : l'erreur Surclassement reste bloquante (seul l'avertissement « date de naissance inconnue » est non bloquant).
- **Pool arbitres** (`Code_compet = 'POOL'`, pseudo saison 1000) : son `Categ` est celui de la saison active, comme à l'ajout d'un arbitre ; `CompositionAgeCategories` (recalcul d'un joueur, d'une équipe, migration) et `MyBdd::RefreshCategoriesJoueurs` le recalculent donc aussi, sur la saison active.
- **Saison active** : `kp_saison.Etat = 'A'` (service api2 `ActiveSeason`) ; entre deux saisons, aucune n'est active : la saison la plus récente est alors utilisée, pour que la plausibilité ne refuse pas toutes les dates.
- **Suivi legacy** : `MyBdd::RefreshCategoriesJoueurs` est un ajout legacy délibéré (les imports ICF et l'ajout depuis la recherche de licence doivent recalculer les catégories, stories 19 et 25), à supprimer quand ces écritures passeront par api2.
- **Écarts acceptés** (hors stories, livrés avec la fonctionnalité) :
  - correction de la saison active `Etat = 'A'` (et non `'O'`) dans la fiche athlète et le pool arbitres ;
  - « ? » dans les feuilles de présence PDF legacy (`FeuillePresence*.php`) pour un joueur sans catégorie ;
  - `refreshPlayer` après une fusion de joueurs, pour recalculer les compositions reprises par la fiche cible.

## Further Notes

- Ordre de déploiement en production : livrer le code, lancer la migration en `--dry-run`, relire le rapport, puis exécuter.
- Effet assumé : un PDF ou une statistique d'une saison close peut changer après la correction d'une date de naissance.
