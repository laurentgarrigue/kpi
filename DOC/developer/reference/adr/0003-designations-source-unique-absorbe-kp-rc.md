# Les désignations sont la source unique des officiels et responsables, et absorbent `kp_rc`

Qui est RC d'une compétition, responsable d'une équipe, R1, délégué CNA ou membre du jury d'appel d'un événement ou d'une journée est enregistré dans une seule table, `kp_designation` : `(saison, rôle, type et id de périmètre, licence, ordre, qualité)`. Chaque Désignation porte la licence de la personne, jamais un nom en texte libre. Les rôles, les types de périmètre autorisés et le profil du mandat généré sont définis à un seul endroit, le catalogue des rôles côté api2. La table `kp_rc` est migrée en Désignations de rôle `RC` sur le périmètre compétition, puis supprimée.

## Considered Options

- **Garder les noms en texte libre de `kp_journee` (`Delegue`, `Responsable_R1`…) comme source** et les rapprocher des comptes : rejeté. Les homonymes et les fautes de frappe rendent le rapprochement non fiable, alors que le calcul des rétributions en dépend.
- **Garder `kp_rc` à côté de `kp_designation`** : rejeté. On aurait deux modèles et deux écrans pour la même opération, et les RC n'auraient pas eu de mandat automatique.

## Consequences

- Les pages legacy qui lisent `kp_rc` (`GestionRc`, `GestionCompetition`, `GestionParamJournee`, `GestionInstances`, `GestionOperations`, `MyBdd.php`) cessent de fonctionner. Aucune vue de transition n'est prévue ([ADR-0002](0002-plus-d-evolution-du-legacy.md)).
- Les lecteurs de `kp_rc` dans api2 (`AdminRcController`, `AdminCompetitionsController`, `CompetitionCodeRenameService`, `SeasonOperationsService`, entité `Rc`) passent sur `kp_designation`.
