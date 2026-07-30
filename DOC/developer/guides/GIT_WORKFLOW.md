# Workflow Git & CI/CD — vue d'ensemble

Ce guide décrit **deux choses qui s'imbriquent** :

1. **Ton workflow local** — comment une feature va du premier commit jusqu'en
   production (mode solo ou worktrees).
2. **Les workflows GitHub Actions automatiques** — ce qu'ils déclenchent tout
   seuls à chaque étape, et comment ils interagissent avec tes actions.

> **Règle d'or du projet** : tu ne pousses jamais en direct sur `develop` ni
> `main`. **Les deux exigent une PR** (ruleset). Tout ce qui arrive sur ces
> branches passe par une pull request — la tienne, ou celle qu'un workflow ouvre
> pour toi.

---

## 1. Carte mentale : qui fait quoi, et quand

```
        TOI (local)                         GITHUB ACTIONS (auto)
   ┌───────────────────┐
   │ branche feature   │
   │ code + commit     │
   │ make dev (test)   │
   └─────────┬─────────┘
             │ make pr_create
             ▼
   ┌───────────────────┐   PR ouverte    ┌────────────────────────────┐
   │  PR → develop     │────────────────►│ CI (ci.yml)                │
   └─────────┬─────────┘                 │  lint · phpstan · audits · │
             │ make pr_checks (vert)     │  build-nuxt · smoke-api2 · │
             │                           │  secrets-scan → ci-summary │
             │ make pr_merge (squash)    └────────────────────────────┘
             ▼
   ═══════════════════════ develop ═══════════════════════
             │ (push develop)
             │                    ┌───────────────────────────────────┐
             ├───────────────────►│ Deploy preprod (deploy-preprod.yml)│
             │                    │  SSH → deploy-wrapper.sh sur le VPS │
             │                    │  rebuild sélectif + smoke test      │
             │                    │  ❌ smoke KO → ROLLBACK auto        │
             │                    └───────────────────────────────────┘
             │                    ┌───────────────────────────────────┐
             └───────────────────►│ Version bump (version-bump.yml)    │
                                  │  ouvre PR chore/version-bump        │
                                  │  (auto-merge squash)                │
                                  └───────────────────────────────────┘
             │ make pr_web base=main  (release, manuelle)
             ▼
   ┌───────────────────┐
   │  PR → main        │──► CI (ci.yml) ──► ci-summary REQUIS pour merger
   └─────────┬─────────┘
             │ merge (squash, linéaire)
             ▼
   ═══════════════════════ main ═════════════════════════
             │ (push main)
             │                    ┌───────────────────────────────────┐
             └───────────────────►│ Back-merge (backmerge-…-develop.yml)│
                                  │  ouvre PR chore/backmerge → develop │
                                  │  (réaligne develop sur main)        │
                                  └───────────────────────────────────┘
```

### Quand chaque workflow s'active

| Workflow | Déclencheur | Ce qu'il fait | Bloquant ? |
|---|---|---|---|
| **CI** (`ci.yml`) | PR vers `develop` ou `main` | lint · PHPStan · audits · build Nuxt · smoke api2 · secrets-scan → `ci-summary` | **Oui sur `main`** (`ci-summary` = required check) |
| **Deploy preprod** (`deploy-preprod.yml`) | **push** sur `develop` (= un merge de PR) | SSH vers le VPS → `deploy-wrapper.sh` : rebuild sélectif + smoke + **rollback auto** | Non (déploie, ne garde rien) |
| **Deploy production** (`deploy-prod.yml`) | **manuel** (`workflow_dispatch`, ref sur `main`) + **approbation** | SSH → `deploy-wrapper.sh production` : backup DB + rebuild + smoke + **rollback auto** | Non (déploie ; approbation requise) |
| **Version bump** (`version-bump.yml`) | **push** sur `develop` | ouvre une PR `chore/version-bump` (auto-merge squash) si une app a changé | Non |
| **Back-merge** (`backmerge-main-to-develop.yml`) | **push** sur `main` + manuel | ouvre une PR `chore/backmerge-main-to-develop → develop` | Non |
| **CodeQL** (`codeql.yml`) | cron lundi 6 h + manuel | SAST JS/TS → onglet Security | Non |
| **Trivy image** (`trivy-image.yml`) | cron mardi 6 h + `docker/.env.dist` + manuel | CVE des images de base → onglet Security | Non |
| **Test env isolation** (`test-env-isolation.yml`) | manuel uniquement | vérifie qu'un job preprod ne lit pas les secrets production | Non (test ponctuel) |

**Point clé à retenir** : la CI tourne sur **PR** ; le déploiement et les bumps
tournent sur **push** (donc après un merge). C'est pourquoi `develop` exige une PR
à CI verte — ainsi tout push sur `develop` est déjà validé, et le déploiement
préprod qui suit part sur du code testé.

---

## 2. Ton workflow local

Deux modes, **même cycle PR** (`pr_create` → `pr_checks` → `pr_merge`) :

| Mode | Quand | Où |
|---|---|---|
| **Solo sur `develop`** (courant) | une feature à la fois | `~/Documents/dev/kpi` |
| **Worktrees** | plusieurs branches en parallèle | `~/Documents/dev/kpi-worktrees/<nom>` |

➡️ Si tu développes une feature à la fois, suis le **[mode solo](#21-mode-solo)**
et ignore les worktrees.

### 2.1 Mode solo

Un seul stack Docker, tout dans `~/Documents/dev/kpi`.

```
branche ─► code/commit ─► make dev ─► pr_create ─► pr_checks ─► pr_merge ─► (release)
```

**1. Partir d'un `develop` à jour**

```bash
cd ~/Documents/dev/kpi
git checkout develop && git pull
git checkout -b feature/scoring
```

Working tree, `node_modules`, `api2/vendor`, `.env`, base MariaDB : **déjà en
place**, rien à copier.

> **Si `git checkout develop` refuse** (« local changes would be overwritten ») :
> committe, ou `git stash`, ou passe en [worktree](#22-mode-worktrees).

**2. Développer et tester**

```bash
# ... edits ...
git add -A && git commit -m "feat: ..."   # ⚠️ les cibles pr_* ne committent JAMAIS

make dev            # démarre tout le stack (détaché)
make dev_status     # 200/401 = OK ; les Nuxt mettent ~15 s
make dev_logs       # ou api2_logs / app2_logs / app4_logs
```

Après un changement PHP dans api2 : `make api2_restart` (le worker garde le kernel
en mémoire). Si `package.json`/`composer.json` a bougé : `make app4_npm_install` /
`make api2_composer_install`.

**3. Ouvrir la PR vers `develop`**

```bash
make pr_create      # push + gh pr create --base develop --fill
make pr_checks      # suit la CI jusqu'au vert
```

Titre/description viennent des commits (`--fill`) — soigne tes messages.

**4. Merger**

```bash
make pr_merge       # squash + delete-branch + checkout develop + pull + branch -D
```

Refusé sur `develop`/`main`. Tu te retrouves sur un `develop` à jour.

> **Ce qui part tout seul juste après ce merge** (push sur develop) :
> - **Deploy preprod** déploie develop sur le VPS ;
> - **Version bump** ouvre une PR de bump si une app a changé.
> Tu n'as rien à faire — sauf si le déploiement rollback (voir §3).

**5. Enchaîner** — `git checkout -b feature/suivante` (develop est déjà à jour).

### 2.2 Mode worktrees

Pour bosser sur plusieurs branches simultanément sans `git stash`. Outil :
[`scripts/git-wt.sh`](../../../scripts/git-wt.sh), cibles `make wt_*`.

```bash
make wt_new name=scoring              # branche feature/scoring + worktree + env copiés
code ~/Documents/dev/kpi-worktrees/scoring
# ... cycle identique : commit → make dev → pr_create → pr_checks → pr_merge ...
```

`make pr_merge` depuis un worktree fait le merge **et** supprime le worktree.

<details>
<summary><b>Spécificités KPI des worktrees (à lire une fois)</b></summary>

**Fichiers gitignorés recopiés** : `docker/.env`, `sources/api2/.env`,
`docker/MyParams.php`, `docker/MyConfig.php`, les `.env.development`, **les clés
JWT** (`sources/api2/config/jwt/*.pem` — sans elles, login 500). `git-wt.sh` les
copie du repo principal.

**Deps recopiées, pas symlinkées** (~1,8 Go/worktree) : `node_modules` (app2/3/4)
et `api2/vendor`. Les symlinks **cassent le stack Docker** (Docker ne suit pas un
lien hors montage ; Vite/Symfony écrivent dans ces dossiers). Coût assumé.

**Base MariaDB partagée** : `HOST_DB_PATH`/`HOST_DBWP_PATH` réécrits en absolu vers
le repo principal → tous les worktrees partagent la base de dev. ⚠️ Une **migration
Doctrine** depuis un worktree affecte **toutes** les branches ; dumpe avant une
migration destructive.

**UN SEUL stack Docker à la fois** : ports fixes (3002/3/4, 8080) + `APPLICATION_NAME`
fixe. Pour changer de worktree : `make dev_down` **là où il tourne**, puis `make dev`
ailleurs.

Diagnostic « quel dossier est servi » :
```bash
docker inspect kpi_php --format '{{index .Config.Labels "com.docker.compose.project.working_dir"}}'
```
</details>

Pour les pannes worktree (500 login, `.env` manquant, worktree fantôme, nettoyage
incomplet après merge) : voir [Situations particulières](#6-situations-particulières-worktree).

---

## 3. Rollback : quand un déploiement préprod casse

Le workflow **Deploy preprod** exécute `deploy-wrapper.sh` sur le VPS. Si une étape
de rebuild **ou** le smoke test final échoue, le wrapper **rollback automatiquement**
la préprod vers le commit précédent (`.last-deploy-sha`). Tu le vois dans les logs
du run :

```
❌ une étape de rebuild a échoué → rollback
🔙 ROLLBACK vers dcb75aaf…
❌ Déploiement ANNULÉ — preprod restaurée sur dcb75aaf…
```

> **Ce que le rollback restaure et ce qu'il NE restaure PAS.**
> Le rollback remet le **working tree du VPS** sur le commit précédent — la préprod
> refonctionne. Mais **`develop` sur GitHub garde le commit fautif** : le rollback
> est côté serveur, pas côté dépôt. **Sans revert, le prochain déploiement
> re-déploiera le commit cassé** et re-rollbackera.

### La marche à suivre après un rollback

Il faut **reverter le commit fautif dans `develop`** — via une PR (push direct
interdit). Deux cibles Make automatisent ça :

```bash
# 1. Retrouver le SHA à reverter (le dernier merge sur develop est le suspect n°1)
make last_merge_sha
#    → affiche le dernier commit de origin/develop + comment cibler une PR précise

# 2. Préparer le revert (crée une branche revert/<sha> avec le commit inversé)
make preprod_rollback sha=<sha>

# 3. Ouvrir la PR de revert et la merger
make pr_create
make pr_checks        # CI verte
make pr_merge         # squash → push develop → redéploie une préprod SAINE
```

`make preprod_rollback` refuse un working tree sale et un SHA introuvable, crée la
branche depuis `origin/develop` à jour, applique `git revert`, et te laisse juste la
PR à ouvrir. En cas de conflit de revert, il s'arrête avec la marche à suivre.

**Retrouver le commit de merge d'une PR précise** (si ce n'est pas le tout dernier) :

```bash
gh pr view <num> --json mergeCommit --jq .mergeCommit.oid
make preprod_rollback sha=<ce_sha>
```

> **Rollback manuel côté VPS** (si le rollback auto lui-même a échoué) : voir le
> `deploy-wrapper.sh` dans le repo `vps-manager` et le fichier `.last-deploy-sha`
> du checkout préprod.

---

## 4. Publier en production (develop → main)

**Pas de cible Make** — une release se décide, elle ne s'automatise pas au fil de
l'eau.

```bash
cd ~/Documents/dev/kpi
git checkout develop && git pull
make pr_web base=main          # ou : gh pr create --base main --head develop --web
```

`main` est **protégée** : Require PR + **linear history** + **`ci-summary` requis**.
Donc :

- **pas de push direct** ;
- **pas de merge commit** — le merge doit être **squash** (ou rebase). Un
  `gh pr merge --merge` est refusé (`Merge commits are not allowed`) ;
- la CI (`ci.yml`) doit être **verte** avant de pouvoir merger.

Une fois mergée (push sur `main`), le workflow **Back-merge** ouvre
automatiquement une PR `chore/backmerge-main-to-develop → develop` pour réaligner
develop. Merge-la (squash) pour refermer la boucle.

### Déployer en production (manuel + approbation)

**Le push sur `main` ne déploie RIEN tout seul** (contrairement à `develop` → préprod).
La prod se déploie **à la main, avec approbation** via le workflow **Deploy production**
(`deploy-prod.yml`) :

1. Actions → **« Deploy production »** → **Run workflow**.
2. Sélecteur de branche sur **`main`** (l'environment `production` n'autorise que `main`) ;
   input `ref` = `main` (ou un SHA/tag précis, qui **doit être sur `main`** — vérifié par
   `git merge-base --is-ancestor`).
3. GitHub met le run **en pause** et demande une **approbation** (required reviewer de
   l'environment `production`) → approuve pour lancer.
4. Le run fait SSH → `deploy-wrapper.sh production <sha>` sur le VPS (`/data/kpi`) :
   **backup DB avant migration**, rebuild sélectif, smoke test, **rollback auto** si KO —
   même mécanique que la préprod.

> **Rollback prod** : identique à la préprod (le wrapper restaure `.last-deploy-sha`).
> Pour re-déployer un état sain après un rollback, relancer « Deploy production » sur un
> `ref` antérieur connu bon, ou reverter le commit fautif sur `main` via PR.

> **Note Dependabot** : ses *security updates* ouvrent leurs PR **sur `main`**
> directement (elles ignorent `target-branch: develop`, limitation GitHub) — d'où
> ce back-merge automatique après chaque push sur `main`.

---

## 5. Rapatrier main → develop (back-merge)

Après un merge sur `main` (release ou Dependabot security), develop doit récupérer
ces commits. Le workflow **Back-merge** le fait tout seul (une PR
`chore/backmerge-main-to-develop`). Pour le **déclencher à la main** (s'il n'a pas
tourné, ou pour forcer) :

```bash
make backmerge_main_to_develop     # lance le workflow → ouvre/actualise la PR
gh pr list --base develop --head chore/backmerge-main-to-develop   # la retrouver
```

Puis merge la PR (squash) comme d'habitude.

> **Pourquoi un workflow et plus un merge local ?** L'ancienne cible
> `sync_develop_from_main` faisait `git push origin develop` **en direct** — ce que
> le ruleset develop **rejette** depuis 2026-07-24 (« Changes must be made through a
> pull request »). Elle a été **remplacée** par `backmerge_main_to_develop`, qui
> passe par le même workflow que l'automatisme. **N'essaie plus de pousser develop
> ou main directement** : toujours une PR.

---

## 6. Situations particulières (worktree)

<details>
<summary><b><code>pr_merge</code> s'est arrêté après le merge (nettoyage incomplet)</b></summary>

La PR est **mergée sur GitHub**, mais le nettoyage local a échoué (`develop is
already used by worktree`, ou `worktree remove` sur dossier non vide). Rien n'est
perdu :

```bash
gh pr view <n> --json state,mergeCommit --jq '"\(.state) \(.mergeCommit.oid[0:8])"'
cd ~/Documents/dev/kpi && git pull --ff-only
cd ~/Documents/dev/kpi-worktrees/<nom> && make dev_down   # si le stack y tourne
cd ~/Documents/dev/kpi && make dev
git worktree remove ~/Documents/dev/kpi-worktrees/<nom>
git branch -D feature/<nom>
```
</details>

<details>
<summary><b>Changer de worktree (aligner l'environnement)</b></summary>

Un seul stack à la fois ; il sert le dossier d'où il a été lancé.

```bash
cd ~/Documents/dev/kpi-worktrees/scoring && make dev_down   # arrêter LÀ où il tourne
cd ~/Documents/dev/kpi-worktrees/dark-mode && make dev
make dev_status
```

Premiers démarrages plus lents (caches Vite/Symfony reconstruits par worktree :
~30 s au lieu de ~15 s).
</details>

<details>
<summary><b>500 au login (<code>kp_user doesn't exist</code> ou erreur JWT)</b></summary>

```bash
# 1. Base vide → HOST_DB_PATH resté relatif (worktree d'avant juillet 2026)
grep HOST_DB_PATH docker/.env        # doit être ABSOLU
make wt_sync name=<n>

# 2. « Unable to encode the JWT token » → clés Lexik absentes
ls sources/api2/config/jwt/          # private.pem + public.pem attendus
make wt_sync name=<n>
make api2_restart
```

Ne **pas** régénérer les clés (`api2_jwt_generate_keys`) dans un worktree : la
nouvelle paire ne correspondrait plus à `JWT_PASSPHRASE`.
</details>

<details>
<summary><b>Autres cas worktree</b></summary>

- **`.env` manquant** : `make wt_sync name=<n>` (écrase les configs, préserve tes
  ajustements → sauvegarde-les avant).
- **`worktree remove` refuse « dossier non vide »** : `git worktree remove --force`
  puis `git branch -D`.
- **Shell dans un dossier supprimé** après `pr_merge` : `cd ~/Documents/dev/kpi`.
- **Worktree fantôme** dans `git worktree list` : `git worktree prune`.
- **Deps divergentes** : `make app4_npm_install` / `make api2_composer_install`
  dans le worktree concerné (n'affecte pas les autres).
</details>

---

## 7. J'ai committé sur `develop` au lieu d'une branche feature

Tant que ce n'est **pas poussé** (et de toute façon le push serait refusé), les
commits se déplacent sur une branche feature :

```bash
git status -sb                              # "## develop...origin/develop [devant N]"
git log --oneline origin/develop..develop   # les N commits à déplacer

# working tree propre requis (sinon committe/stash d'abord)
git branch feature/xxx
git rev-parse --verify feature/xxx          # ⚠️ doit afficher un SHA avant de continuer
git reset --hard origin/develop             # develop redevient identique au distant
git checkout feature/xxx                    # les commits sont ici
make pr_create
```

> Ne saute **pas** le `rev-parse` : c'est lui qui rend le `reset --hard` sûr (la
> branche pointe déjà sur les commits). Si `git branch` a échoué, les commits
> deviendraient non référencés (récupérables via `git reflog`, mais autant éviter).

---

## 8. Cibles Make (référence)

Les `wt_*` ne servent qu'en mode worktree ; les `pr_*` dans les deux modes.

| Cible | Effet |
|---|---|
| `make wt_new name=<n> [base=<b>]` | *(worktree)* crée `feature/<n>` + worktree + env |
| `make wt_list` / `wt_sync name=<n>` / `wt_rm name=<n>` | *(worktree)* liste / re-copie env / supprime |
| `make pr_push` | push la branche courante en suivi |
| `make pr_create [base=<b>]` | push + ouvre la PR (base `develop` par défaut) |
| `make pr_web [base=<b>]` | push + formulaire PR dans le navigateur |
| `make pr_status` / `pr_checks` | état des PR / suit la CI jusqu'au bout |
| `make pr_merge` | merge (squash) + remet develop à jour + nettoie branche/worktree |
| `make pr_close` | ferme la PR **sans** merger + supprime la branche (PR jetable) |
| `make backmerge_main_to_develop` | déclenche le workflow de back-merge main → develop (ouvre une PR) |
| `make last_merge_sha` | affiche le SHA du dernier merge sur develop (pour un revert) |
| `make preprod_rollback sha=<sha>` | prépare le revert local d'un commit fusionné → PR |

> **Les cibles `pr_*` ne committent jamais** : l'ordre est toujours `git add` →
> `git commit` → `make pr_create`. `main` et `develop` n'acceptent **aucun** push
> direct — tout passe par PR.
>
> `--squash` garde `develop` propre (un commit par PR). `main` impose en plus
> l'**historique linéaire** (squash/rebase only, jamais de merge commit).

---

## 9. GitHub CLI (`gh`)

✅ **`gh` est installé et configuré** (SSH, scope `repo`). Il transforme « pousser
une branche » en « PR ouverte » via les cibles `make pr_*`.

> **⚠️ Deux remotes** : `origin` = `laurentgarrigue/kpi`, `remote_ffck_kpi` =
> `FFCK/kpi`. `gh` doit savoir lequel viser, sinon `gh pr create` risque d'ouvrir
> une PR **vers FFCK**. Réglé une fois pour toutes (à refaire si tu re-clones) :
>
> ```bash
> gh repo set-default laurentgarrigue/kpi
> ```

Commandes sous-jacentes :

```bash
gh pr create --base develop --fill         # = make pr_create
gh pr create --base main --head develop --web   # = make pr_web base=main (release)
gh pr checks --watch                       # = make pr_checks
gh pr merge <n> --squash --delete-branch   # merge une PR
gh pr view <n> --json mergeCommit --jq .mergeCommit.oid   # SHA de merge (pour revert)
gh workflow run "Back-merge main → develop" --ref main    # = make backmerge_main_to_develop
```

---

## 10. Astuces VS Code (worktrees)

- **Une fenêtre par worktree** : `File → New Window` puis ouvre le dossier. Chaque
  fenêtre a sa branche, son terminal, son état Git.
- Le panneau Source Control montre la bonne branche par fenêtre automatiquement.
