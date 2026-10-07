# Runbook de déploiement

Procédures opérationnelles du CI/CD KPI : **comment déployer, comment constater
un problème, comment revenir en arrière**. Phase 8.4 du plan
[CI_CD_STRATEGY.md](../archive/ci-cd/CI_CD_STRATEGY.md).

Ce document est fait pour être lu **en situation**, y compris un dimanche soir.
Il ne réexplique pas la conception : pour le « pourquoi », voir le
[journal d'exécution](../archive/ci-cd/CI_CD_EXECUTION_NOTES.md).

> **Convention** : ⌨️ = à taper sur le **poste de dev**, 🖥 = **sur le VPS** (en
> SSH), 🌐 = dans l'**interface GitHub**.

---

## 0. Carte express

| Je veux… | Où | Comment |
|---|---|---|
| **Amener mon code** sur `main` (→ préprod) | ⌨️ | `make pr_create` → `make pr_checks` → `make pr_merge` ([§1.0](#10-amener-le-code-jusquà-main)) |
| **Préparer une release** (→ prod) | ⌨️ | `make release_tag version=X.Y.Z` sur `main` à jour ([§1.0.3](#103-la-release--un-tag-sur-main)) |
| **Tester avant de pousser** | ⌨️ | `make api2_test` (unit + integration, ce que fait la CI) |
| Déployer en **préprod** | — | Rien à faire : tout merge sur `main` déploie automatiquement (sauf bumps de version et docs) |
| Déployer en **production** | 🌐 | Actions → « Deploy production » → Run workflow depuis `main`, `ref` = tag `vX.Y.Z` → approuver |
| Tester une **branche feature** (ou un tag) en préprod | 🌐 | Actions → « Deploy preprod (experimental) » → branche/tag + TTL |
| Voir **pourquoi ça a échoué** | 🌐 | Actions → le run → étape « Déployer via SSH » |
| **Annuler** un déploiement préprod cassé | ⌨️ | `make last_merge_sha` puis `make preprod_rollback sha=…` |
| **Restaurer la base** de prod | 🖥 | [§5](#5-rollback-de-la-base-de-données-prod) |
| **Médias** (logos, photos) : état, sauvegarde, restauration | 🖥 | `make media_status` / `media_backup` / `media_restore` — [MEDIA_STORAGE.md](MEDIA_STORAGE.md) |
| Savoir **quelle version** tourne | 🖥 | `cat /data/kpi/.last-deploy-sha` (et `git -C /data/kpi rev-parse HEAD`) |

**Accès VPS** : `ssh -i ~/.ssh/kpi-deploy/kpi_deploy_ed25519 -p 22 deploy@<host>`
(l'utilisateur `deploy` est dans le groupe `docker`, **sans `sudo` — volontairement**,
cf. [§3.1](#31-deploy-na-volontairement-pas-de-sudo) : les commandes d'admin système se
jouent en tant que `laurent` ; ⚠️ fail2ban est actif, ne pas multiplier les tentatives
ratées).

---

## 1. Déployer

### 1.0 Amener le code jusqu'à `main`

`main` est la **seule branche permanente** (`develop` n'existe plus depuis le
2026-09-13). Un merge sur `main` déploie la préprod ; la prod se déploie à la main
à partir d'un **tag** posé sur `main`. **On ne pousse jamais en direct sur `main`** :
son ruleset exige une PR à CI verte. Voici les commandes `make` du poste de dev qui
y mènent — la conception et les cas particuliers (worktrees) sont dans
[GIT_WORKFLOW.md](../guides/GIT_WORKFLOW.md).

#### 1.0.1 Vérifier avant de pousser (optionnel mais rentable)

⌨️ La CI rejouera tout, mais en local c'est ~1 s au lieu d'un aller-retour GitHub :

```bash
make api2_test          # les DEUX suites PHPUnit = équivalent local du job CI `tests-api2`
make api2_test_unit     # suite `unit` seule : logique pure, sans base, < 1 s
```

`api2_test_integration` recharge d'abord les fixtures de `SQL/fixtures/` dans une
base **dédiée** `kpi_fixtures_test` (jamais la base de dev) via
`make api2_test_fixtures`. Ces cibles ont besoin du stack dev démarré
(`make dev`) : elles s'exécutent dans les conteneurs `kpi_api2` et `kpi_db`.

#### 1.0.2 De la branche feature vers `main` (→ préprod)

⌨️ Depuis la branche de travail :

```bash
make pr_create      # push la branche + ouvre la PR vers main (base=main par défaut)
make pr_checks      # suit la CI jusqu'au bout, sans clic (approuve le run du bump, relance un run bloqué)
make pr_merge       # squash-merge, revient sur main à jour, supprime la branche
```

Le merge pousse sur `main` → **« Deploy preprod » part tout seul** ([§1.1](#11-préprod-—-automatique-aucun-clic)).

| Cible | À savoir |
|---|---|
| `make pr_create` | vise `main` par défaut ; `make pr_web` ouvre le formulaire pré-rempli dans le navigateur |
| `make pr_merge` | **Refuse** de tourner depuis `main`, et refuse si le stack Docker tourne depuis le worktree à supprimer |
| `make pr_close` | Ferme **SANS merger** + supprime la branche — pour une PR jetable, jamais pour une vraie |
| `make pr_status` | État de toutes tes PR |

#### 1.0.3 La release : un tag sur `main`

⌨️ Il n'y a plus de PR de release : le bump de version par brique est fait
automatiquement par la CI dans chaque PR. Une fois la dernière PR mergée :

```bash
git checkout main && git pull
make release_tag version=X.Y.Z    # pose et pousse le tag global vX.Y.Z (sans PR)
```

`make release_tag` refuse un tag déjà existant et vérifie que `HEAD` est bien sur
`origin/main`. Détail (bump manuel, `make release`) :
[GIT_WORKFLOW.md §4](../guides/GIT_WORKFLOW.md).

> ⚠️ Poser un tag **ne déploie rien**. La prod reste sur l'ancienne version
> jusqu'au déclenchement manuel de [§1.2](#12-production-—-manuelle-avec-approbation).

### 1.1 Préprod — automatique, aucun clic

Tout commit qui atterrit sur `main` est déployé en préprod, **sauf** les bumps de
version et les modifications de doc (`paths-ignore` : `DOC/**`, `*.md`, fichiers de
version). La garantie « code testé » est **structurelle** : le ruleset de `main`
exige une PR à CI verte, donc un commit ne peut y arriver que validé.

```
PR → CI verte → merge sur main → « Deploy preprod » → smoke tests → OK
                                                        ↘ KO → rollback auto
```

Durée : **~48 s** si aucune brique n'est à rebuilder, **~7 min** si les apps Nuxt
le sont (`npm ci` + `nuxt generate` sans cache npm).

> ⚠️ **Ne pas confondre un build long avec un blocage.** Un run resté
> `in_progress` 5 min n'est pas figé : c'est le rebuild des apps. Pour en être
> sûr : 🖥 `ps -u deploy` doit montrer un `nuxt generate` en cours.

Redéploiement manuel de la préprod sur le dernier `main` :
🌐 Actions → « Deploy preprod » → Run workflow (**depuis `main`**).

### 1.2 Production — manuelle, avec approbation

1. La release doit être taguée sur `main` ([§1.0.3](#103-la-release--un-tag-sur-main)).
2. 🌐 Actions → **« Deploy production »** → Run workflow, **branche `main`**, input
   `ref` = **le tag** (`vX.Y.Z`). Un SHA ou `main` fonctionne aussi, mais le tag est
   immuable : il désigne sans ambiguïté ce qui part en prod.
3. Approuver (l'environment `production` a un *required reviewer*).
4. Le workflow refuse tout `ref` qui n'est pas un ancêtre de `origin/main` — donc
   pas passé par la CI et la revue.

En prod, un **dump de la base est pris avant toute migration** Doctrine, dans
`/data/backups/kpi/pre-migration/kpi_<date-heure>_<sha7>.sql.gz`.

> ⚠️ La politique de branche de l'environment `production` est **stricte** : il
> n'accepte que `main`. Un « Run workflow » depuis une autre branche est rejeté avec
> `Branch "x" is not allowed to deploy to y`. Le **sélecteur de branche du bouton
> Run** est ce qui compte.

### 1.3 Préprod expérimentale — une branche feature en conditions réelles

🌐 Actions → **« Deploy preprod (experimental) »** → Run workflow :

| Champ | Valeur |
|---|---|
| `branch` | la branche **ou le tag** à déployer (ex. `feature/scoring`, `v1.26.0`) |
| `ttl_hours` | durée avant retour auto à `main` (1 à 168, défaut 24) |

Ce que ça implique, à savoir **avant** de cliquer :

- la préprod **ne reflète plus `main`** jusqu'à expiration ;
- un **bandeau fuchsia** s'affiche dans app2 et app4 (nom de branche, SHA court,
  heures restantes) — impossible de croire à une préprod normale ;
- passé le TTL, le **cron du VPS redéploie `main`** tout seul (à HH:15) ;
- un merge sur `main` pendant ce temps **reprend la main** (déploiement normal)
  et retire le bandeau.

Revenir à `main` **immédiatement**, sans attendre le TTL :
🌐 Actions → « Deploy preprod » → Run workflow depuis `main`.

---

## 2. Constater : où regarder

| Quoi | Où |
|---|---|
| Résultat du déploiement | 🌐 Actions → le run → étape « Déployer via SSH » |
| Log **détaillé** du wrapper | 🖥 `/tmp/kpi-deploy-<date>.log` (chemin affiché en tête de run) |
| Logs **api2** (FrankenPHP) | ⌨️/🖥 `make api2_logs` / `make api2_logs_errors` (`lines=200`) |
| Logs **legacy** (Apache) | 🖥 `docker/apachelogs_8/` — **ne contient RIEN sur api2** |
| Logs du cron d'expiration | 🖥 `<LOGS_BASE_DIR>/cron/preprod-experimental-expiry.log` |
| Dumps pré-migration | 🖥 `/data/backups/kpi/pre-migration/` |
| SHA déployé précédent | 🖥 `<checkout>/.last-deploy-sha` |
| Déploiement expérimental actif | 🖥 `<checkout>/.experimental-deploy.json` (absent = aucun) |

Le wrapper n'affiche **qu'une ligne de statut par étape** ; le log complet n'est
déversé (40 dernières lignes) **qu'en cas d'échec**. `VERBOSE=1` force l'affichage
intégral.

### Les smoke tests

Après chaque déploiement, une URL **par brique** doit répondre 200 (api2 `/doc`,
un endpoint public api2, app2, app4, legacy `index.php`). Chacune est réessayée
**10 × 6 s ≈ 60 s** : FrankenPHP met plusieurs secondes à router après un
`docker compose restart`, et un curl unique provoquerait un rollback à tort.

**Une seule URL en échec ⇒ rollback automatique.**

---

## 3. Échecs courants et quoi faire

| Symptôme | Cause probable | Action |
|---|---|---|
| `dial tcp: i/o timeout`, run KO en 25-40 s | Aléa réseau runner↔VPS sur l'**établissement** de la connexion (connu, intermittent) | Rien à réparer. Le workflow **retente déjà une fois**. Si les 2 tentatives tombent dans la même fenêtre : 🌐 `gh run rerun <id>` |
| `Host key verification failed`, exit 128 | Remote git du checkout en **SSH** (`deploy` n'a pas de clé GitHub) | 🖥 `git remote set-url origin https://github.com/laurentgarrigue/kpi` |
| `fatal: detected dubious ownership` | Checkout possédé par un autre utilisateur | 🖥 `sudo -u deploy git config --global --add safe.directory <chemin>` |
| Écriture refusée dans le checkout | ACL manquante pour `deploy` | 🖥 `sudo setfacl -R -m u:deploy:rwX <chemin>` puis idem avec `-d` (héritage) |
| `backup DB pré-migration EN ÉCHEC … Permission non accordée` | ACL manquante sur `/data/backups/kpi` | 🖥 `sudo setfacl -R -m u:deploy:rwX /data/backups/kpi` puis idem `-d`. **Non bloquant** : le déploiement continue |
| `Branch "x" is not allowed to deploy to y` | Mauvaise branche dans le sélecteur du bouton Run | Relancer depuis la bonne branche (§1.2) |
| Le bouton « Run workflow » **n'apparaît pas** | Le fichier du workflow n'est pas sur la **branche par défaut** (`main`) | Faire remonter `.github/workflows/` jusqu'à `main` |
| Bandeau expérimental affiché alors que la préprod est normale | Marqueur resté en place | 🖥 supprimer `<checkout>/.experimental-deploy.json` + les `experimental-flag.json` des `.output/public/`, ou relancer un déploiement préprod normal |
| Mail `*** SECURITY information *** … deploy : user NOT in sudoers` | Une commande lancée **sous `deploy`** a appelé `sudo` (cf. §3.1) | Ne rien « réparer » côté déploiement : c'est une commande d'admin jouée sous le mauvais compte. La rejouer en tant que `laurent` |

### 3.1 `deploy` n'a **volontairement** pas de `sudo`

Le compte `deploy` sert **uniquement** à déployer : `git`, `make`, et Docker (via le
groupe `docker`). Il n'est **pas sudoer**, et ce n'est pas un oubli — c'est ce qui
limite ce qu'un attaquant obtiendrait en compromettant la clé SSH exposée à GitHub
Actions. **Ne pas l'ajouter aux sudoers** pour faire passer une commande.

Conséquence pratique : toute commande d'administration système (`fail2ban-client`,
`journalctl`, `iptables`, `systemctl status`…) doit être jouée **en tant que
`laurent`**, pas via la session `deploy`.

Si on l'oublie, `sudo` envoie une alerte `*** SECURITY information ***` par mail à
`root` — c'est le comportement normal de sudo, pas une intrusion. Le mail
mentionne la commande fautive et `PWD=/home/deploy`, ce qui suffit à l'identifier.

> **Piège rencontré (2026-07-30)** : le mail est arrivé pendant une période de
> déploiements, ce qui a fait croire à un problème du pipeline. Il n'en était rien —
> `deploy-wrapper.sh` ne contient **aucun** `sudo`, et le user `deploy` n'a
> **aucune** crontab. La cause était une cible du `Makefile` de **vps-manager**
> (`server-status`) appelant `sudo fail2ban-client`, jouée manuellement sous
> `deploy`. Les cibles concernées utilisent désormais `sudo -n` derrière une sonde
> de droit et **dégradent proprement** (`⏭️ non interrogeable`) au lieu d'alerter.

---

## 4. Rollback du code

### 4.1 Le rollback automatique (rien à faire)

Le wrapper rollback **tout seul** si une étape de rebuild échoue **ou** si un
smoke test échoue : `git reset --hard` sur le SHA précédent + restart, puis sortie
en erreur. Prouvé en conditions réelles (2026-07-27).

```
⏪ ROLLBACK vers dcb75aaf…
❌ Déploiement ANNULÉ — preprod restaurée sur dcb75aaf…
```

> ⚠️ **Le rollback restaure le VPS, PAS `main` sur GitHub.** Le commit fautif
> est toujours sur `main` : sans réparation, le **prochain déploiement
> recassera**. Le revert est obligatoire (§4.2).

### 4.2 Réparer `main` après un rollback

⌨️ Sur le poste de dev (détail dans
[GIT_WORKFLOW.md §3](../guides/GIT_WORKFLOW.md#3-rollback--quand-un-déploiement-préprod-casse)) :

```bash
make last_merge_sha              # SHA du commit fautif sur main
make preprod_rollback sha=<sha>  # crée revert/<sha> avec le commit inversé
make pr_create && make pr_merge  # PR de revert → redéploie une préprod saine
```

### 4.3 Rollback manuel (le rollback auto a échoué)

🖥 Sur le VPS, si le wrapper lui-même n'a pas pu restaurer :

```bash
cd /data/kpi_preprod                  # ou /data/kpi en prod
cat .last-deploy-sha                  # le SHA d'avant le déploiement
git reset --hard "$(cat .last-deploy-sha)"

# Rebuilder ce qui doit l'être, selon la brique touchée :
make api2_composer_install && make api2_cache_clear && make api2_restart
make app2_generate_preprod && make app4_generate_preprod   # si les apps sont concernées
make docker_preprod_restart

# Vérifier
curl -fsS https://preprod.kayak-polo.info/api2/doc > /dev/null && echo OK
```

> En **prod**, remplacer les cibles par `*_production` / `docker_prod_restart`.
> ⚠️ Après un changement de code api2 en prod, `make api2_restart` est
> **obligatoire** : le worker FrankenPHP garde le kernel en mémoire.

---

## 5. Rollback de la base de données (prod)

Phase 8 / §6.4 du plan. À utiliser **seulement** si une migration Doctrine a
abîmé les données — pas pour un simple bug applicatif (là, c'est §4).

> ⚠️ **Aujourd'hui api2 n'a AUCUNE migration versionnée** (le schéma vient de la
> base legacy partagée ; `migrate` tourne avec `--allow-no-migration`, donc
> no-op). Cette procédure est un filet pour le jour où une vraie migration
> arrivera — et elle doit être écrite **avant** ce jour-là, pas pendant.

### 5.1 Choisir le dump

```bash
ls -lt /data/backups/kpi/pre-migration/     # dumps pris avant chaque migration
ls -lt /data/backups/kpi/daily/             # backups cron nocturnes
```

Le nom porte l'horodatage **et le SHA déployé** :
`kpi_2026-07-30_143002_a1b2c3d.sql.gz` — on sait donc exactement à quel
déploiement il correspond.

### 5.2 Restaurer

**Toujours** prendre un dump de l'état courant d'abord : si la restauration se
révèle être une erreur d'analyse, on doit pouvoir revenir à l'état « cassé » pour
l'étudier.

```bash
# 0) Filet : dump de l'état ACTUEL, avant d'y toucher
docker exec kpi_db sh -c 'mariadb-dump -u<user> -p<pass> kpi' \
  | gzip > /data/backups/kpi/pre-migration/kpi_avant-restauration_$(date +%F_%H%M%S).sql.gz

# 1) Couper le trafic écrivain : on ne restaure pas sous les écritures
cd /data/kpi && docker compose -f docker/compose.prod.yaml stop kpi api2

# 2) Restaurer
gunzip -c /data/backups/kpi/pre-migration/<fichier>.sql.gz \
  | docker exec -i kpi_db mariadb -u<user> -p<pass> kpi

# 3) Relancer
docker compose -f docker/compose.prod.yaml start kpi api2
make api2_restart

# 4) Vérifier
curl -fsS https://kayak-polo.info/api2/doc > /dev/null && echo "api2 OK"
curl -fsS https://kayak-polo.info/api2/events/all > /dev/null && echo "données OK"
```

Alternative outillée, depuis le dépôt `vps-manager` : `make restore-backup
service=<n> backup=<chemin>` (`make list-services` / `make list-backups` pour les
identifiants).

### 5.3 Après la restauration

- Le **code** est peut-être encore la version qui a cassé la base → faire aussi
  §4.3, sinon la prochaine requête reproduira le dégât.
- Noter dans le journal d'exécution ce qui s'est passé (cause, dump utilisé,
  durée d'indisponibilité).
- Les données saisies **entre** le dump et la restauration sont **perdues** :
  l'estimer et le dire, plutôt que de le découvrir plus tard.

---

## 6. Qui contacter

Projet mono-mainteneur : **Laurent Garrigue** (lgarrigue@gmail.com).

Ce qui n'appartient pas au dépôt `kpi` et où le chercher :

| Sujet | Emplacement |
|---|---|
| `deploy-wrapper.sh`, `backup.sh`, `health-check.sh`, crons | dépôt **privé `vps-manager`**, checkouté en `/data/vps-manager` |
| Secrets de déploiement (`SSH_*`, `DEPLOY_PATH`) | 🌐 GitHub → Settings → Environments (`preprod`, `production`) |
| Certificats / routage | Traefik sur le VPS |

> Le checkout `/data/vps-manager` est **en lecture seule pour `deploy`** : un
> changement de wrapper se commite dans `vps-manager`, se pousse, puis se
> `git pull` sur le VPS **en tant que `laurent`**.

---

## 7. Ce qui n'est PAS encore en place

Pour ne pas chercher une fonctionnalité qui n'existe pas :

- **Pas de notification** (Discord/Slack/mail) sur succès ou échec de
  déploiement — §8.2 du plan, volontairement reporté. On regarde l'onglet
  Actions.
- **Pas de monitoring d'uptime externe** (§8.3). Le `health-check.sh` du VPS
  couvre les URLs, avec alerte mail.
- **Pas de lock `command=`** dans `~deploy/.ssh/authorized_keys` (durcissement
  optionnel). ⚠️ Si un jour il est posé, le wrapper devra lire
  `$SSH_ORIGINAL_COMMAND` au lieu de `$1 $2 …`.
