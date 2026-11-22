# Plus d'évolution du legacy

À partir du 2026-11-22, les nouvelles fonctions et les changements de modèle de données ne sont développés que dans app2, app3, app4 et api2. Le legacy (`sources/admin/`, `sources/api/`, `sources/commun/`, `kp*.php`, applications Vue `app_*_dev`) ne reçoit plus d'évolution, sauf instruction contraire explicite. Il doit cependant **continuer à fonctionner** (sans erreur) jusqu'à quelques mois après la bascule de `www` vers app3, car il sert alors de secours. Il peut afficher de façon incomplète les données nouvelles qu'il ne connaît pas.

## Considered Options

- **Maintenir la compatibilité du legacy à chaque évolution** (vues SQL de transition, double écriture) : rejeté. Cela double le coût de chaque changement de modèle pour des pages qu'app4 et app3 remplacent.

## Consequences

- Une évolution du schéma ne doit pas casser une page legacy : quand une table lue par le legacy est remplacée, on garde une vue SQL en lecture seule à son ancien nom, jusqu'à la fin de la période de secours. La première concernée est l'absorption de `kp_rc` par `kp_designation` ([ADR-0003](0003-designations-source-unique-absorbe-kp-rc.md)).
- Une écriture faite depuis le legacy sur une donnée qu'il ne possède plus peut échouer. On l'accepte, parce que la saisie se fait dans app4.
- Les correctifs de sécurité et les corrections de bugs bloquants sur le legacy restent possibles, sur instruction.
