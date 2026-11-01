# La catégorie d'âge d'une composition est une valeur dérivée, mise en cache dans `Categ`

La catégorie d'âge d'un joueur dans une composition se déduit toujours de son année de naissance et de la saison de la compétition ; ce n'est pas une déclaration historique. La colonne `kp_competition_equipe_joueur.Categ` est conservée, mais seulement comme cache : elle est recalculée quand la date de naissance d'un joueur change, quand il est ajouté ou copié dans une composition, et par une migration qui corrige l'existant, **y compris dans les compétitions terminées et verrouillées**. Le verrou d'une compétition protège ce qui a été saisi (équipes, journées, présents), pas cette valeur dérivée.

## Considered Options

- **Instantané figé à l'engagement** : rejeté, il conserve les erreurs de saisie (joueurs internationaux classés V9 à cause d'une date conventionnelle 1901-01-01, puis V4 une fois la vraie date connue).
- **Supprimer la colonne et calculer à la lecture** : rejeté, `Categ` est lue par de nombreux écrans, PDF et exports (legacy compris) ; trois points d'écriture coûtent moins que réécrire toutes les lectures.

## Consequences

- Un PDF ou une statistique d'une saison close peut changer quand une date de naissance est corrigée après coup : c'est voulu.
- Une date de naissance inconnue est `NULL` (jamais une date conventionnelle) et ne donne aucune catégorie d'âge.
- Les surclassements (`kp_surclassement`) sont des décisions déclarées et ne sont pas concernés.
