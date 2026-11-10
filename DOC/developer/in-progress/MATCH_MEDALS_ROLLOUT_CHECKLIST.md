# Médailles de match et statistiques de timeline — checklist de test et de déploiement

**Créée le** : 10 novembre 2026 — fonction transverse : api2, app3 (beta), app2 et app4 (hors des phases du site public).
Convention des pictogrammes (⌨️ 🖥 🌐 📦) et règle de non-régression : voir [PUBLIC_SITE_ROLLOUT_CHECKLIST.md](PUBLIC_SITE_ROLLOUT_CHECKLIST.md).

---


Spec : [FEATURE_MATCH_MEDALS](../../specs/public/FEATURE_MATCH_MEDALS.md) (`MED-*`), maquettes validées le 09/11/2026.
Branche : `claude/optimistic-cray-9nl0r6`. Statut : 🛠 implémentée, **à déployer**. Touche api2, app3 (beta), app2 et
app4 : l'impact hors beta est réel (feuille de match d'app2, page d'admin, recalcul à la validation).

### M.0 Décisions (08 et 09/11/2026)

| # | Question | Retenu | ☑ |
|---|---|---|---|
| 1 | Matchs éligibles | validés et publiés, matchs de placement compris | ☑ |
| 2 | Durée des périodes | 10 min / 5 min, approximation acceptée pour les formats courts | ☑ |
| 3 | Exclusions | vert, jaune, rouge : 2 min levées au but adverse ; rouge définitif (`D`) : jusqu'à la fin du match | ☑ |
| 4 | Seuils | un seul fichier (`sources/api2/config/packages/match_medals.yaml`), figés par saison | ☑ |
| 5 | Publication | par surface (app2, app3), premier lot réduit, le reste dans l'admin (profils 1–2) | ☑ |
| 6 | Arbitres | désignés nommément seulement ; faits du match comptés pour les deux ; médailles d'arbitre publiées dès le premier lot | ☑ |

### M.1 Contenu (commits relisibles séparément)

| Commit | Contenu | Impact hors beta |
|---|---|---|
| feat(api2) moteur | `src/MatchMedals` : analyse de la timeline, une règle par élément, seuils | aucun |
| feat(api2) persistance | migration `SQL/migrations/2026-11-09_public_site_match_medals.sql` (6 tables `kp_medal_*` / `kp_match_*`, catalogue initial) ; commandes `app:match-medals:compute` / `:sync` ; recalcul à la (dé)validation dans app4 | **schéma** : nouvelles tables seulement ; la (dé)validation d'un match dans app4 recalcule ses médailles |
| feat(api2) endpoints | `/medals/catalog`, `/team/{n}/medals`, `/competition/…/labels`, `medals` dans `/game-sheet/{id}`, statistiques de timeline dans `/competition/…/stats` (paramètre `surface`, `app3` par défaut), `/admin/medals/*` (profils 1–2), pictogrammes `public/medals/icons/` | `/game-sheet/{id}` : champ **ajouté** ; `/competition/…/stats` : kinds **ajoutés** quand la compétition a des matchs calculés |
| feat(app3) | onglet Stats, `/medals`, fiche équipe, étiquettes des matchs, entrée de menu | aucun (beta) |
| feat(app2) | bloc Médailles de la feuille de match, ballon pour les buts (liste et frise) | **app2** : feuille de match ; variable `PUBLIC_SITE_URL` (lien vers `/medals`) |
| feat(app4) | page **Médailles** (groupe Opérations, profils 1–2) | **app4** : nouvelle page |
| docs | spec, checklist, cron, guide utilisateur, jetons PDF resynchronisés (couleurs de famille) | aucun |

### M.2 ⌨️ Tester en local

```bash
git fetch origin && git checkout claude/optimistic-cray-9nl0r6 && git pull
make db_migrate file=SQL/migrations/2026-11-09_public_site_match_medals.sql
make api2_test                          # recharge les fixtures (migration comprise)
make api2_match_medals_compute season=2026
make app3_test && make app3_lint
make app4_bash                          # puis `npm test`
```

| # | Commande / action | Résultat attendu | ☑ |
|---|---|---|---|
| 1 | `make api2_test` | unit 316 + integration 336 (tests `MED-*` : `MatchMedals*Test`) | ☑ |
| 2 | `make app3_test` | unit 185, nuxt 188 (dont `medals.spec.ts`), e2e verts | ☐ |
| 3 | `make api2_match_medals_compute season=2026` | `Season 2026: N validated games computed` ; relancé : mêmes nombres, aucune erreur | ☐ |
| 4 | `make api2_match_medals_sync` | `0 computed, 0 removed` juste après le calcul complet | ☐ |
| 5 | `https://beta.kpi.localhost/competitions/2026/<code>/stats` | statistiques « Médailles des équipes · Team Medals »… avec explication et nombre de matchs | ☐ |
| 6 | `https://beta.kpi.localhost/medals` et `/en/medals` | éléments publiés par famille, double nom en français, nom anglais seul en anglais | ☐ |
| 7 | Fiche d'une équipe de la saison | section « Médailles de match », compteurs menant à la feuille de match d'app2 | ☐ |
| 8 | app2 : feuille d'un match validé à feuille complète | bloc Médailles, médailles d'arbitre, ballon aux couleurs des équipes ; match non validé : pas de bloc | ☐ |
| 9 | app4 › Opérations › Médailles (profil 1) | catalogue, saisons (v1, seuils), statistiques et arbitrage du contexte de travail ; profil 3 : page refusée | ☐ |
| 10 | app4 : publier `goal_timing` sur app3, puis le retirer | la statistique apparaît puis disparaît de l'onglet Stats sans recalcul ; entrées dans le journal | ☐ |
| 11 | app4 : dévalider puis revalider un match calculé | médailles supprimées puis recalculées aussitôt | ☐ |

**Non-régression locale** : app2 (feuille de match d'un match sans médaille, frise), app4 (validation des matchs),
app3 (onglets Stats existants : meilleurs buteurs), legacy, PDF (jetons resynchronisés). ☐

### M.3 ⌨️ 🌐 PR et CI

Jobs attendus : `tests-api2` (la migration est chargée par la boucle `*_public_site_*.sql` entre `schema.sql` et
`data.sql`), `phpstan-api2`, `lint-api2`, `lint-nuxt` (app2, app3, app4), `build-nuxt`, `tests-app3`, tests unitaires
app4, `ci-summary` vert. ☐

### M.4 📦 Prérequis serveur (préprod puis prod)

| # | Action | ☑ |
|---|---|---|
| 1 | `sources/app2/.env.preprod` / `.env.production` : `PUBLIC_SITE_URL` (`https://beta.preprod.kayak-polo.info`, `https://beta.kayak-polo.info` ; `www` après la bascule) | ☐ |
| 2 | Cron toutes les 10 min : `*/10 * * * * docker exec <APPLICATION_NAME>_api2 php bin/console app:match-medals:sync >> /var/log/kpi/cron_match_medals.log 2>&1` ([CRON_DOCUMENTATION](../infrastructure/CRON_DOCUMENTATION.md) § 4) | ☐ |

### M.5 🖥 Préprod (`/data/kpi_preprod`)

| # | Commande / action | Résultat attendu | ☑ |
|---|---|---|---|
| 1 | 🌐 « Deploy preprod » | vert ; `make api2_restart` si le wrapper ne l'a pas fait (worker FrankenPHP) | ☐ |
| 2 | `make db_migrate file=SQL/migrations/2026-11-09_public_site_match_medals.sql` | 6 tables créées, 50 éléments au catalogue ; relançable sans erreur | ☐ |
| 3 | `make api2_match_medals_compute season=AAAA` pour **chaque saison**, de la plus récente à la plus ancienne | une ligne par saison dans app4 › Médailles › Saisons ; part de feuilles complètes plausible | ☐ |
| 4 | Pose du cron (§ M.4) puis, 10 min plus tard, `tail /var/log/kpi/cron_match_medals.log` | une ligne `… computed, … removed` par passage | ☐ |
| 5 | `make app2_generate_preprod`, `make app3_generate_preprod`, `make app4_generate_preprod` | applications reconstruites | ☐ |
| 6 | Tests 5 à 11 du § M.2 sur la préprod | idem | ☐ |
| 7 | `curl -sI https://preprod.kayak-polo.info/api2/medals/icons/goal.svg` | `200`, `image/svg+xml` | ☐ |

**Non-régression préprod** : app2 (feuilles de match), app4 (validation de masse), legacy, PDF. ☐

### M.6 Production

Comme le § 1.5 (`make release_tag`, « Deploy production »), puis § M.4, `make db_migrate file=…`, calcul de chaque
saison (§ M.5 n° 3), cron, `make app2_generate_production`, `make app3_generate_production`, `make app4_generate_prod`.
Vérifier une feuille de match d'app2, l'onglet Stats d'une compétition terminée et `beta.kayak-polo.info/medals`. ☐

### M.7 Retour arrière

| Situation | Action |
|---|---|
| Une médaille ou une statistique pose problème | app4 › Médailles : décocher app2 / app3 ou passer l'élément à « Retiré » (effet immédiat, sans déploiement) |
| Un seuil est mal réglé | Corriger `config/packages/match_medals.yaml`, déployer, `make api2_match_medals_compute season=<saison courante>` |
| Calcul en erreur ou trop lent | Retirer le cron ; les pages publiques n'affichent rien sans données (aucune erreur) |
| Régression d'app2 / app4 | Procédure standard du [runbook § 4](../infrastructure/DEPLOYMENT_RUNBOOK.md) ; les nouvelles tables peuvent rester en place |
