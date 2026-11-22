# Plus d'évolution du legacy

À partir du 2026-11-22, les nouvelles fonctions et les changements de modèle de données ne sont développés que dans app2, app3, app4 et api2. Le legacy (`sources/admin/`, `sources/api/`, `sources/commun/`, `kp*.php`, applications Vue `app_*_dev`) ne reçoit plus d'évolution, sauf instruction contraire explicite. Il n'est pas non plus adapté quand une évolution du modèle de données le casse.

## Considered Options

- **Maintenir la compatibilité du legacy à chaque évolution** (vues SQL de transition, double écriture) : rejeté. Cela double le coût de chaque changement de modèle pour des pages qu'app4 et app3 remplacent.

## Consequences

- Une évolution du schéma peut casser les pages legacy qui lisent les tables modifiées. On l'accepte et on le signale dans la spec concernée. La première concernée est l'absorption de `kp_rc` par `kp_designation` ([ADR-0003](0003-designations-source-unique-absorbe-kp-rc.md)).
- Les correctifs de sécurité et les corrections de bugs bloquants sur le legacy restent possibles, sur instruction.
