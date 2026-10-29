# Réalignement des structures fédérales et des codes régionaux sur le PCE

> Statut : spec prête à implémenter. Vocabulaire : [GLOSSARY.md](../../../GLOSSARY.md).
> Décision structurante : [ADR 0001](../reference/adr/0001-codes-regionaux-alignes-sur-les-cr-actuels.md).
> Tickets : [.scratch/realignement-structures-ffck/](../../../.scratch/realignement-structures-ffck/README.md).

## Problem Statement

Les CR, les CD et les Clubs de KPI ne correspondent plus au PCE, et l'import quotidien ne les corrige jamais.

- L'import ajoute les structures inconnues mais **ne met jamais à jour** une structure existante. Un libellé ou un rattachement modifié dans le PCE reste donc faux dans KPI.
- L'import **n'ajoute au plus qu'un Club par exécution**. Au 2026-10-28, 17 Clubs du PCE manquent en dev.
- Les CD d'outre-mer de **Saint-Pierre-et-Miquelon, Mayotte et Wallis-et-Futuna** sont mal convertis. Ils sont rangés sous Paris, la Seine-Maritime et la Vienne.
- La table des CD mélange plusieurs générations de codes : format interne, codes bruts `CDxxx` sans aucun Club, et pseudo-CD des Anciennes ligues.
- Les équipes de sélection appartiennent à des pseudo-clubs d'**Anciennes ligues** (avant 2016) dont la numérotation chevauche celle des CR actuels : l'ancien `CR07` désigne la Bretagne, l'actuel `CR07` l'Île-de-France.
- Les codes des **Compétitions régionales** (`REGxx*`, `T-Rxx*`) portent toujours le numéro de l'Ancienne ligue. Pour un responsable régional comme pour un visiteur, le code ne dit rien de la région actuelle, et il peut même en désigner une autre.

## Solution

Le PCE devient la seule source de vérité des structures fédérales, et KPI s'y aligne en deux temps.

1. **Import PCE corrigé** (livré par le déploiement normal d'api2). Chaque import met à jour les CR, CD et Clubs existants (libellé et rattachement), insère tous les nouveaux Clubs et convertit correctement les CD d'outre-mer. L'opération d'administration « changer un code compétition » met à jour toutes les tables qui référencent un code compétition.
2. **Remise à plat ponctuelle** par un script SQL exécuté une fois par environnement (dev, puis préprod, puis prod), après un import PCE fait avec l'import corrigé. Le script nettoie les structures obsolètes non référencées, crée une **Sélection régionale** par CR en y fusionnant les Anciennes ligues, et renomme les codes des Compétitions régionales sur toutes les saisons.

## User Stories

1. En tant qu'administrateur fédéral, je veux que les CR, CD et Clubs de KPI reflètent le PCE du jour, pour ne plus corriger les libellés et rattachements à la main.
2. En tant qu'administrateur fédéral, je veux qu'un Club qui change de libellé dans le PCE change aussi de libellé dans KPI dès l'import suivant, pour que les feuilles de match et les classements affichent le bon nom.
3. En tant qu'administrateur fédéral, je veux qu'un Club qui change de CD dans le PCE change de rattachement dans KPI, pour que les filtres par CD et par CR le retrouvent au bon endroit.
4. En tant qu'administrateur fédéral, je veux qu'un CD qui change de CR dans le PCE change de rattachement dans KPI, pour que la hiérarchie CR → CD → Club reste exacte.
5. En tant qu'administrateur fédéral, je veux que tous les nouveaux Clubs du PCE soient créés au même import, pour que leurs équipes puissent être engagées sans attendre plusieurs jours.
6. En tant que responsable d'un club de Mayotte, je veux que mon Club soit rattaché au CD de Mayotte et pas à la Seine-Maritime, pour apparaître sous la bonne région.
7. En tant que responsable d'un club de Wallis-et-Futuna, je veux que mon Club soit rattaché au CD de Wallis-et-Futuna et pas à la Vienne, pour la même raison.
8. En tant que responsable d'un club de Saint-Pierre-et-Miquelon, je veux que mon Club soit rattaché à un CD de Saint-Pierre-et-Miquelon et pas à Paris, pour la même raison.
9. En tant qu'administrateur, je veux que l'import n'écrase ni les coordonnées, ni l'adresse, ni le site web, ni l'email saisis dans KPI pour un Club, parce que le PCE ne les fournit pas.
10. En tant qu'administrateur, je veux qu'un Club ou un CD absent du PCE mais encore référencé par l'historique soit conservé, pour que les anciennes équipes et compétitions restent consultables.
11. En tant qu'administrateur, je veux que les structures étrangères (sous `98`) soient conservées, parce que le PCE ne les contient pas et que les compétitions internationales en dépendent.
12. En tant qu'administrateur, je veux que les CD et Clubs obsolètes et non référencés disparaissent, pour que les listes de choix ne proposent que des structures réelles.
13. En tant que responsable régional, je veux une seule Sélection régionale par CR, avec le code et le libellé de la CR, pour engager les équipes de ma région sans hésiter entre plusieurs anciennes ligues.
14. En tant que responsable régional du Grand Est, je veux que les équipes des Anciennes ligues Alsace, Champagne-Ardenne et Lorraine appartiennent à la Sélection régionale Grand Est, pour retrouver tout l'historique de ma région au même endroit.
15. En tant que responsable régional des Hauts-de-France, je veux que les équipes des Anciennes ligues Nord-Pas-de-Calais et Picardie appartiennent à la Sélection régionale Hauts-de-France.
16. En tant qu'utilisateur limité aux équipes d'une Ancienne ligue, je veux que ma limitation pointe vers la Sélection régionale de ma CR actuelle, pour garder l'accès à mes équipes.
17. En tant que responsable régional, je veux que le code de mes Compétitions régionales porte le numéro de ma CR actuelle (ex. `REG03` pour la Bretagne), pour qu'il soit cohérent avec la structure fédérale.
18. En tant que visiteur, je veux que l'historique d'une Compétition régionale reste regroupé sous un seul code sur toutes les saisons, pour comparer les éditions.
19. En tant qu'utilisateur filtré sur des Compétitions régionales, je veux que mon filtre de compétitions suive le renommage, pour continuer à voir les mêmes compétitions.
20. En tant qu'utilisateur disposant d'un mandat filtré sur des Compétitions régionales, je veux que le filtre du mandat suive le renommage.
21. En tant qu'organisateur d'une compétition multi-compétitions, je veux que la liste des compétitions agrégées suive le renommage, pour que l'agrégation continue de fonctionner.
22. En tant qu'administrateur, je veux que les responsables de compétition (RC) suivent le renommage, pour que les responsables restent attachés à leur compétition.
23. En tant qu'administrateur, je veux que le code de référence (regroupement) des compétitions suive le renommage, pour que les regroupements restent cohérents.
24. En tant qu'administrateur, je veux que l'opération « changer un code compétition » de l'administration mette à jour toutes ces références, pour pouvoir renommer une compétition sans rien casser à l'avenir.
25. En tant qu'exploitant, je veux que le script de remise à plat s'exécute en une seule transaction, pour qu'un échec laisse la base intacte.
26. En tant qu'exploitant, je veux que le script s'arrête sans rien modifier s'il rencontre un code d'Ancienne ligue ou de Compétition régionale absent de la table de correspondance, parce que la préprod et la prod peuvent contenir des codes inconnus en dev.
27. En tant qu'exploitant, je veux que le script soit rejouable sans effet supplémentaire, pour pouvoir le relancer sans risque après une exécution partielle ou un doute.
28. En tant qu'exploitant, je veux une requête de contrôle à lancer avant et après le script, qui compare CR, CD et Clubs au PCE et liste les codes régionaux restants, pour vérifier le résultat sur chaque environnement.
29. En tant qu'exploitant, je veux pouvoir relancer un import PCE à partir d'un fichier local, pour rejouer un import sans dépendre du serveur fédéral.
30. En tant que responsable régional, je veux être prévenu avant le renommage en prod que les anciens liens et favoris vers mes compétitions ne seront pas redirigés.

## Implementation Decisions

### Import PCE

- Le PCE est la seule source de vérité pour le code, le libellé et le rattachement des CR, CD et Clubs. L'import met à jour ces trois champs pour les lignes existantes et ne touche à aucun autre champ (coordonnées, adresse, site web, email, indicateurs internes).
- La sélection des Clubs à insérer est groupée par Club. Chaque Club du PCE produit une ligne, rattachée à un seul CD (le plus petit code de CD s'il en a plusieurs).
- Le format interne des codes CD est conservé (département sur deux caractères suivi de `00`). Codes d'outre-mer : 971 → `9A00`, 972 → `9B00`, 973 → `9C00`, 974 → `9D00`, **975 → `9E00`**, **976 → `9F00`**, 988 → `9G00`, **986 → `9I00`**. La conversion des codes Club suit la même table d'outre-mer.
- La commande d'import accepte une **option de fichier local**. Quand elle est fournie, le fichier remplace le téléchargement fédéral, et le reste du traitement est identique.
- Les filtres existants de l'import restent inchangés : Clubs `0`/`0000` exclus, structures internationales `98` exclues, saison courante uniquement.

### Opération « changer un code compétition »

- Le renommage (toutes saisons ou saison active) met aussi à jour : les responsables de compétition, le code de référence des compétitions dont il vaut l'ancien code, le groupe (`kp_groupe`) que ce code de référence désigne, la liste des compétitions agrégées des compétitions multi-compétitions, les formulaires d'inscription liés à la compétition (`kp_form`) et le filtre de compétitions des mandats utilisateur. Ces mises à jour s'ajoutent à celles qui existent déjà (compétitions, engagements, journées, filtres utilisateur — liste `|CODE|` ou liste SQL `'CODE'`).
- **Groupe** : `kp_competition.Code_ref` est une clé étrangère vers `kp_groupe.Groupe`, qui n'a pas de saison. En toutes saisons, le groupe de l'ancien code prend le nouveau code (sauf si un groupe du nouveau code existe déjà). En saison active, les saisons passées pointent toujours vers l'ancien groupe : il est conservé, et un groupe du nouveau code est créé à son image s'il n'existe pas.
- Le renommage est atomique (une transaction) ; le contrôle des clés étrangères, coupé pendant le renommage, est rétabli dans tous les cas, succès ou échec (connexions persistantes du worker FrankenPHP).
- Le contrat HTTP de l'endpoint ne change pas. Le SQL métier vit dans un service (`CompetitionCodeRenameService`), le contrôleur ne fait que lire la requête et répondre.

### Script de remise à plat (exécution unique par environnement)

- Script SQL daté, rangé avec les autres scripts SQL du dépôt. Il est autonome, contient une transaction et le rejouer ne produit aucun effet supplémentaire.
- **Préconditions** : l'import corrigé est déployé et un import PCE a tourné après ce déploiement. Les données des licenciés de la saison courante sont donc propres.
- **Garde-fou** : avant toute écriture, le script vérifie que chaque pseudo-club d'Ancienne ligue et chaque code `REGxx*` / `T-Rxx*` est couvert par la table de correspondance, qu'aucun renommage ne crée de collision, et que la forme temporaire `~<nouveau code>` de chaque code tient dans sa colonne. Sinon il s'arrête sans rien modifier et liste les codes en cause.
- **CR, CD, Clubs** : les **libellés viennent de l'import corrigé** (précondition) : l'import vide les colonnes de libellé de `kp_licence` (club, CD, CR) à la fin de chaque exécution, le script ne peut donc pas les recalculer. Le script ne recalcule que les **rattachements** (CD → CR, Club → CD), depuis les codes des licenciés de la saison du dernier import. Les Clubs et CD absents du PCE sont conservés s'ils sont référencés (équipes, engagements, limitations utilisateur, Clubs rattachés). Les CD non référencés sont supprimés, dont les CD au format brut `CDxxx`. Les structures sous `98` sont conservées.
- **Sélections régionales** : pour chaque CR métropolitaine ou d'outre-mer qui a une Ancienne ligue à fusionner, le script crée un pseudo-CD et un pseudo-club portant le code de la CR et son libellé, avec la chaîne Sélection → pseudo-CD → CR. Il réaffecte ensuite à la Sélection les équipes, les engagements et les limitations utilisateur et de mandat des pseudo-clubs des Anciennes ligues, puis supprime ces pseudo-clubs et pseudo-CD. Le logo d'un engagement ou d'une équipe qui pointait vers le fichier de l'Ancienne ligue (`KIP/logo/CRnn-logo.png`) est réécrit vers celui de la Sélection : les numéros se recoupent (ADR 0001), sans cette réécriture une équipe bretonne (ancien `CR07`) afficherait `CR07-logo.png`, qui devient le logo de l'Île-de-France. Les fichiers d'images, hors base, sont réaffectés à la main (rollout checklist). Le chevauchement des numéros (l'ancien `CR07` contre l'actuel `CR07`) impose de faire la réaffectation en plusieurs passes, avec des codes temporaires.
- **Table de correspondance** (Ancienne ligue → CR actuelle, cf. ADR 0001) : 02 Alsace, 09 Champagne-Ardenne et 17 Lorraine → 05 ; 03 Aquitaine, 16 Limousin et 22 Poitou-Charentes → 09 ; 04 Pays de la Loire → 11 ; 07 Bretagne → 03 ; 08 Centre → 04 ; 12 Nord-Pas-de-Calais et 21 Picardie → 06 ; 13 Franche-Comté → 02 ; 14 Île-de-France → 07 ; 15 Languedoc-Roussillon et 19 Midi-Pyrénées → 10 ; 18 Rhône-Alpes → 01 ; 20 Normandie → 08 ; 23 PACA → 12 ; 25 Réunion → 24 ; 29 Guyane → 21.
- **Codes des Compétitions régionales** : renommage sur **toutes les saisons** des préfixes `REGxx` et `T-Rxx` selon la même table, en gardant le suffixe (`REG07U15` → `REG03U15`). Cas particuliers : `REG09`/`REG09B` (2018, déjà au code actuel) restent tels quels ; `REG23` et la compétition `CR23` (2018, Réunion) passent en 24 ; `REG19J` (Sud) passe en 10. Le renommage met à jour les mêmes références que l'opération corrigée : compétitions, engagements, journées, responsables de compétition, code de référence et groupes (`kp_groupe`, cible de sa clé étrangère), compétitions agrégées, formulaires d'inscription (`kp_form`), filtres utilisateur et filtres de mandat. **Repère d'exécution** : le renommage ne s'applique qu'une fois, signalé par une ligne `Remise à plat structures FFCK` dans `kp_journal` ; un code déjà renommé (`REG03` = Bretagne) ne se distingue pas d'un ancien code (`REG03` = Aquitaine), seul ce repère rend le script rejouable. Les collisions (`REG14` → `REG07` alors que l'ancien `REG07` existe) se résolvent par un ordonnancement en plusieurs passes. Les autres codes régionaux (`T-SBEA`, `T-CACH`, `T-IDF`, `T-POIDF`…) gardent leur code, mais leur code de référence suit la table.
- **Requête de contrôle** livrée avec le script. Elle compte les écarts entre CR, CD et Clubs et le PCE de la saison courante, les Clubs du PCE absents de KPI, les pseudo-clubs d'Anciennes ligues restants et les codes `REGxx` / `T-Rxx` hors des CR actuelles. Elle se lance avant et après le script. Tant que le script n'est pas passé, elle liste aussi, par préfixe renuméroté, les codes compétition à renommer avec leurs saisons et leur libellé : un code créé après 2016 avec le numéro d'une CR actuelle serait renuméroté à tort, l'exploitant le repère avant de lancer le script.

### Déroulé par environnement

1. Déployer api2 avec l'import et l'opération corrigés.
2. Lancer un import PCE.
3. Faire un dump complet de la base.
4. Lancer la requête de contrôle (avant).
5. Exécuter le script.
6. Lancer la requête de contrôle (après).

En prod : un jour sans compétition, après avoir prévenu les responsables régionaux concernés.

## Testing Decisions

- Un bon test vérifie le comportement observable (l'état des tables CR, CD et Clubs après un import, ou les références après un renommage), jamais la forme des requêtes SQL ni les méthodes privées.
- **Seam 1 : la commande d'import PCE**, testée en intégration via le testeur de commandes Symfony sur la base de fixtures avec un petit fichier PCE de fixture (option de fichier local). Critères couverts : mise à jour d'un libellé de Club et d'un rattachement de CD, insertion de tous les nouveaux Clubs au même import, conversion 975/976/986 en `9E00`/`9F00`/`9I00`, champs non fournis par le PCE préservés.
- **Seam 2 : l'endpoint « changer un code compétition »**, testé en intégration avec une requête authentifiée profil 1. Critères couverts : responsables de compétition, code de référence et son groupe, compétitions agrégées, formulaires d'inscription, filtres utilisateur et filtres de mandat suivent le renommage, en toutes saisons comme en saison active ; un échec ne laisse rien à moitié renommé et rétablit le contrôle des clés étrangères.
- Précédents dans le dépôt : le test d'intégration de la reprise WordPress (commande + état de la base dans une transaction annulée), et les tests d'intégration des endpoints d'administration (requêtes authentifiées sur les comptes des fixtures).
- **Le script de remise à plat n'a pas de test automatisé** : il ne s'exécute qu'une fois. Il est vérifié par la requête de contrôle avant/après, d'abord en dev, puis sur chaque environnement.

## Out of Scope

- Redirection des anciens codes de Compétitions régionales (liens externes, favoris, pages WordPress). Ils ne sont pas redirigés, voir l'ADR 0001.
- Les autres codes de compétition régionaux sans numéro de CR (`T-SBEA`, `T-CACH`…), hormis leur code de référence.
- Les compétitions départementales (`DEP06`…) et les compétitions nationales ou internationales.
- Le journal des actions, qui reste un historique et n'est pas réécrit.
- Les scripts d'extraction des structures en CSV/HTML, qui produisent des codes CD bruts et restent des outils de consultation.
- Une interface d'administration des CR, CD et Clubs.

## Further Notes

- Mesures en dev au 2026-10-28 : `kp_cr` déjà aligné sur le PCE ; 269 CD dont environ 100 au format brut sans Club ; 1 410 Clubs dont 691 sans licencié en 2026 ; 17 Clubs du PCE absents ; 248 équipes et 34 utilisateurs sur des pseudo-clubs d'Anciennes ligues ; 173 filtres utilisateur et 9 filtres de mandat contenant un code régional.
- Les chiffres de préprod et de prod peuvent différer : le garde-fou du script est là pour ça.
- À la fin du chantier, déplacer ce document dans `DOC/developer/archive/<thème>/` et mettre à jour les références (cf. CLAUDE.md).
