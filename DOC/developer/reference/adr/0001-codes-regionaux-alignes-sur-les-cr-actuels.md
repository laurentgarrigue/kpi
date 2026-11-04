# Codes régionaux alignés sur les CR actuels, sur toutes les saisons

Les codes des Compétitions régionales (`REGxx*`, `T-Rxx*` et leur `Code_ref`) et les pseudo-clubs régionaux utilisaient encore les numéros des Anciennes ligues d'avant 2016. Ces numéros chevauchent ceux des CR actuels (ancien `CR07` = Bretagne, actuel `CR07` = Île-de-France). Nous avons renommé ces codes vers les CR actuels **sur toutes les saisons** et fusionné les pseudo-clubs des Anciennes ligues en une Sélection régionale par CR (ex. Alsace, Champagne et Lorraine → `CR05`). Le PCE reste ainsi la seule source de vérité, et l'historique d'une compétition reste groupé sous un seul code.

## Considered Options

- **Renommer seulement à partir de la saison courante** : rejeté, car l'historique d'une même compétition aurait été coupé en deux codes.
- **Garder les anciens codes** : rejeté, car un même code `CRxx` aurait désigné deux régions différentes selon la table.

## Consequences

- Les liens et favoris externes vers un ancien code ne sont pas redirigés. Comme les numéros se chevauchent, un ancien lien `REG07` (Bretagne) affiche désormais l'Île-de-France au lieu d'échouer.
- Les équipes des Anciennes ligues appartiennent désormais à la Sélection régionale de leur CR actuel, y compris dans l'historique.
- Correspondance appliquée (Ancienne ligue → CR actuelle) : 02, 09 et 17→05 ; 03, 16 et 22→09 ; 04→11 ; 07→03 ; 08→04 ; 12 et 21→06 ; 13→02 ; 14→07 ; 15 et 19→10 ; 18→01 ; 20→08 ; 23→12 ; 25→24 ; 29→21.
- Exceptions pour les codes compétition, nommés à tort en 2018 : `REG23` et `CR23` (Réunion, alors que l'ancien 23 désignait PACA) passent en 24 ; `REG19J` (Sud) passe en 10 ; `REG09`/`REG09B` (Nouvelle-Aquitaine) utilisaient déjà le code actuel 09 et restent tels quels.
