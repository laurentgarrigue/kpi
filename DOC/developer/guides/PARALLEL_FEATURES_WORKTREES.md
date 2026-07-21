# Workflow de développement : une feature à la fois, ou plusieurs en parallèle

**But** : dérouler une feature du premier commit au merge dans `develop`, puis
en production. Deux modes :

| Mode | Quand | Où |
|---|---|---|
| **Solo sur `develop`** (cas courant) | une feature à la fois, rien en cours ailleurs | `~/Documents/dev/kpi` |
| **Worktrees** | plusieurs branches simultanées, ou un travail en cours qu'on ne veut pas `stash` | `~/Documents/dev/kpi-worktrees/<nom>` |

Le workflow PR (`make pr_create` → `pr_checks` → `pr_merge`) est **identique**
dans les deux cas ; seules la création de la branche et le nettoyage final
diffèrent.

➡️ **Si tu développes une feature à la fois** (le cas le plus fréquent), va
directement au [Workflow solo](#workflow-solo--une-feature-à-la-fois-sur-develop)
et ignore tout ce qui concerne les worktrees.

Outil worktrees : [`scripts/git-wt.sh`](../../../scripts/git-wt.sh).

---

## Workflow solo — une feature à la fois, sur `develop`

Pas de worktree, pas de copie de deps, un seul stack Docker par construction :
tout se passe dans `~/Documents/dev/kpi`.

```
branche ──► code/commit ──► make dev ──► pr_create ──► pr_checks ──► pr_merge ──► (release)
```

### 1. Partir d'un `develop` à jour

```bash
cd ~/Documents/dev/kpi
git checkout develop && git pull
git checkout -b feature/scoring
```

Le working tree, les `node_modules`, `sources/api2/vendor`, les `.env` et la base
MariaDB sont **déjà en place** — rien à copier ni à réinstaller.

> **Si `git checkout develop` refuse** (« Your local changes would be
> overwritten ») : tu as du travail non committé. Committe-le, ou `git stash`,
> ou passe en [mode worktree](#workflow-worktrees--plusieurs-features-en-parallèle)
> — c'est exactement le cas d'usage qui le justifie.

### 2. Développer et tester

```bash
# ... edits ...
git add -A && git commit -m "feat: ..."   # ⚠️ les cibles pr_* ne committent JAMAIS

make dev            # démarre tout le stack (détaché)
make dev_status     # 200/401 = OK ; les Nuxt mettent ~15 s
make dev_logs       # ou api2_logs / app2_logs / app4_logs
```

Le stack peut rester allumé pendant tout le développement : les montages sont
relatifs à ce dossier et il n'y a pas d'autre candidat. Après un changement de
code PHP dans api2, `make api2_restart` (le worker garde le kernel en mémoire).

Si `package.json` ou `composer.json` a bougé sur la branche :

```bash
make app4_npm_install          # ou app2_/app3_
make api2_composer_install
```

### 3. Ouvrir la PR vers `develop`

```bash
make pr_create      # push -u origin feature/scoring + gh pr create --base develop --fill
make pr_checks      # suit la CI jusqu'au vert (gh pr checks --watch)
```

Titre et description viennent des commits (`--fill`) — d'où l'intérêt de messages
soignés. `pr_create` échoue si tu n'as rien committé.

### 4. Merger

```bash
make pr_merge       # depuis feature/scoring (refusé sur develop/main)
```

Depuis le repo principal, la cible fait : `gh pr merge --squash --delete-branch`,
puis `git checkout develop && git pull`, puis `git branch -D feature/scoring`.
Tu te retrouves sur un `develop` à jour, prêt pour la feature suivante. **Aucun
worktree à supprimer**, et le garde-fou « stack Docker servi depuis ce worktree »
ne s'applique pas : le stack peut continuer à tourner pendant le merge.

### 5. Enchaîner

```bash
git checkout -b feature/suivante    # develop est déjà à jour après pr_merge
```

Pas besoin de re-`pull` : `pr_merge` l'a fait.

### 6. Publier en production

Voir [Publier en production (develop → main)](#5-publier-en-production-develop--main)
— étape commune aux deux modes.

### Pièges du mode solo

- **Merger dans `develop` sans PR** : `develop` accepte les push directs, mais la
  CI ne tourne alors pas. Passe toujours par `make pr_create`.
- **Oublier de repartir de `develop`** : `git checkout -b` depuis une branche
  feature précédente empile les commits de l'autre feature dans la PR. Toujours
  `git checkout develop && git pull` d'abord.
- **`main` est protégée** : pas de push direct, la release passe par une PR
  `develop → main`.
- **Basculer de branche avec le stack allumé** est sans danger (les montages sont
  relatifs au dossier, pas à la branche), mais un `git checkout` qui change
  `composer.json`/`package.json` demande un `make api2_composer_install` /
  `make app4_npm_install` et souvent un `make api2_restart`.

#### J'ai committé sur `develop` au lieu de créer une branche feature

Le piège le plus fréquent : on oublie le `git checkout -b` et on committe
directement sur `develop`. **Rien n'est perdu** — tant que ce n'est pas poussé,
les commits se déplacent sur une branche feature en trois commandes.

**1. Vérifier l'état** — combien de commits en avance, et rien en cours :

```bash
git status -sb                          # "## develop...origin/develop [devant N]"
git log --oneline origin/develop..develop   # les N commits à déplacer
```

Si le working tree n'est **pas** propre, committe ou `git stash` d'abord : le
`reset --hard` de l'étape 3 détruirait les modifications non committées.

**2. Créer la branche, puis VÉRIFIER qu'elle existe** :

```bash
git branch feature/workflow-solo
git rev-parse --verify feature/workflow-solo   # doit afficher le SHA du dernier commit
```

> **⚠️ Ne saute pas le `rev-parse`.** C'est lui qui rend l'étape 3 sûre : le
> `reset --hard` ne détruit rien *parce que* la branche pointe déjà sur les
> commits. Si `git branch` a échoué (nom déjà pris, faute de frappe) et que tu
> resets quand même, les commits deviennent non référencés — récupérables via
> `git reflog`, mais autant ne pas en arriver là. Tant que la commande n'affiche
> pas un SHA, **ne continue pas**.

**3. Rembobiner `develop` et basculer sur la branche** :

```bash
git reset --hard origin/develop     # develop redevient identique au distant
git checkout feature/workflow-solo  # les commits sont ici
make pr_create
```

**Si les commits sont déjà poussés sur `develop`**, ne fais **pas** ce
`reset --hard` : réécrire une branche partagée casse le clone des autres.
Deux cas :

- travail non critique → laisse-le sur `develop`, il partira à la prochaine
  release `develop → main` (au prix de la CI de PR non jouée) ;
- travail à faire relire → `git revert` les commits sur `develop`, puis
  `git cherry-pick` sur une branche feature et ouvre la PR normalement.

**Variante — le commit contient des changements qui n'ont rien à voir.** C'est
courant si des fichiers traînaient déjà modifiés avant le `git add -A`. Regarde
ce qui part réellement avant d'ouvrir la PR :

```bash
git show --stat HEAD
```

---

## Workflow worktrees — plusieurs features en parallèle

Tout ce qui suit ne concerne **que** le mode multi-branches : bosser sur
plusieurs branches feature simultanément sur le même poste, dans des fenêtres
VS Code / terminaux distincts, sans `git stash` ni `checkout` qui écrase le
travail en cours.

---

## Concept

Un **worktree** est un dossier de travail rattaché au *même* `.git`, checkouté sur
une branche différente. Historique, branches et remotes sont partagés ; seul le
checkout diffère.

```
~/Documents/dev/kpi                    ← repo principal (main / develop)
~/Documents/dev/kpi-worktrees/
    ├── scoring                        ← branche feature/scoring
    └── dark-mode                      ← branche feature/dark-mode
```

### Spécificité KPI — pourquoi un script et pas juste `git worktree add`

Deux pièges propres à ce projet :

1. **Fichiers gitignorés requis par le stack.** `docker/.env`, `sources/api2/.env`,
   `docker/MyParams.php`, `docker/MyConfig.php`, les `.env.development`… ne sont pas
   versionnés. Un worktree neuf ne les a donc pas → `make docker_dev_up` échouerait.
   Le script les **recopie** depuis le repo principal.
2. **Deps lourdes.** `node_modules` (app2/3/4) et `sources/api2/vendor` sont
   **recopiés** depuis le repo principal (~1,8 Go, ~10 s) plutôt que réinstallés
   via `npm`/`composer` (plusieurs minutes, et `npm install` app4 est fragile :
   OOM / crash-loop).

> **⚠️ Pourquoi des copies et pas des symlinks.** Le script a utilisé des symlinks
> vers le repo principal — ça cassait tout le stack, pour deux raisons cumulées :
>
> 1. **Docker ne suit pas un symlink dont la cible sort de ses montages.** Le
>    conteneur ne monte que le worktree ; un lien vers `~/Documents/dev/kpi` y est
>    pendant. Symptômes : `autoload_runtime.php not found` (api2, boucle de
>    redémarrage), `nuxt: not found` / `dotenv: not found` (app2/app4). Piège de
>    diagnostic : `ls` sur l'hôte trouve les fichiers, `docker exec ls` ne trouve
>    rien — et `ls api2/` liste `vendor` alors que `ls api2/vendor/` échoue.
> 2. **Monter aussi le repo principal ne suffit pas.** Vite et Symfony *écrivent*
>    dans ces dossiers (`node_modules/.cache`, `var/cache` via le `projectDir`
>    résolu à travers le lien). En lecture seule → `EROFS` / `Unable to write in
>    the "cache" directory` ; en écriture → deux worktrees se disputent les mêmes
>    caches, avec des chemins absolus pointant vers le mauvais dépôt.
>
> Le partage est donc impossible ici, quel que soit le réglage des montages. Coût
> assumé : **~1,8 Go par worktree**.

### Spécificité base de données — partagée avec le repo principal

`HOST_DB_PATH` / `HOST_DBWP_PATH` (dans `docker/.env`) sont **relatifs au dossier
`docker/`**. Tels quels, chaque worktree initialiserait une base MariaDB **vide** —
symptôme : `Table 'my_database.kp_user' doesn't exist`, 500 au login.

`git-wt.sh` les réécrit donc en **absolu vers le repo principal** : tous les
worktrees partagent les données de dev. Contrairement à `node_modules`/`vendor`,
c'est ici sans danger : Docker monte le chemin directement (pas de symlink), et la
règle « un seul stack à la fois » garantit qu'un seul MariaDB ouvre ces fichiers.

> **⚠️ Deux conséquences.**
> 1. Une **migration Doctrine** jouée depuis un worktree affecte la base de
>    **toutes** les branches. Pour tester une migration destructive, dumpe avant
>    (`make db_bash` puis `mariadb-dump`), ou repasse temporairement
>    `HOST_DB_PATH` en relatif pour repartir sur une base isolée.
> 2. **Ne jamais démarrer deux stacks** sur ce datadir : deux MariaDB sur les
>    mêmes fichiers corrompent InnoDB. C'est la règle ci-dessous, désormais
>    critique et plus seulement une question de ports.

### Spécificité Docker — UN SEUL stack à la fois

Le stack monte `../sources` **relatif au dossier courant** et expose des **ports
fixes** (3002/3003/3004/8080) + un `APPLICATION_NAME` fixe. Faire tourner deux
stacks dev en même temps = conflit de ports/noms.

➡️ **Règle** : un seul `make docker_dev_up` actif. Pour tester une autre feature,
arrête le stack courant (`make docker_dev_down`) puis démarre-le depuis l'autre
worktree. L'édition/commit de plusieurs branches en parallèle, elle, n'a aucune
limite.

---

## Workflow complet (mode worktree), de la feature au merge

### Vue d'ensemble

```
wt_new ──► code/commit ──► make dev ──► pr_create ──► pr_checks ──► pr_merge ──► (release)
   │                          │                                        │
   └── worktree + env         └── UN SEUL stack                        └── merge + nettoyage
```

### 1. Démarrer une nouvelle feature

```bash
make wt_new name=scoring              # branche feature/scoring depuis develop
code ~/Documents/dev/kpi-worktrees/scoring
```

<details>
<summary><b>Ce qui se passe en arrière-plan</b></summary>

1. `git fetch origin develop` — met la base à jour ;
2. `git worktree add -b feature/scoring <dest> origin/develop` — crée branche +
   dossier (si la branche existe déjà, worktree dessus sans la recréer) ;
3. copie des fichiers gitignorés nécessaires au stack : `docker/.env`,
   `docker/MyParams.php`, `docker/MyConfig.php`, `sources/api2/.env`, les
   `.env` d'app2/3/4, **les clés JWT** (`sources/api2/config/jwt/*.pem` — sans
   elles le login renvoie 500) ;
4. réécriture de `HOST_DB_PATH`/`HOST_DBWP_PATH` en **absolu vers le repo
   principal** : la base de dev est partagée (voir ci-dessous) ;
5. copie de `node_modules` (app2/3/4) et `sources/api2/vendor` depuis le repo
   principal, **caches purgés** (`node_modules/.cache`, `.vite`,
   `sources/api2/var/cache`) car ils contiennent des chemins absolus vers le
   repo principal.

Durée : ~10 s, dont ~7 s de copie. Aucun accès réseau hors le `fetch`.
</details>

**Sans worktree** — voir le
[Workflow solo](#workflow-solo--une-feature-à-la-fois-sur-develop) : le reste des
étapes (2 à 5) est identique, seul le nettoyage final diffère.

### 2. Développer et tester en local

```bash
# ... edits ...
git add -A && git commit -m "feat: ..."   # ⚠️ les cibles pr_* ne committent JAMAIS

make dev            # démarre tout le stack (détaché)
make dev_status     # 200/401 = OK ; les Nuxt mettent ~15 s
make dev_logs       # ou api2_logs / app2_logs / app4_logs
```

> **⚠️ UN SEUL stack Docker à la fois.** Si un stack tourne déjà ailleurs :
> `make dev_down` **depuis le dossier qui le fait tourner**, puis `make dev` ici.
> Voir [Changer de worktree](#changer-de-worktree-aligner-lenvironnement).

<details>
<summary><b>Ce que fait <code>make dev</code></b></summary>

`docker compose -f docker/compose.dev.yaml up -d` : Apache/PHP 8.4 (legacy +
`sources/api/` + WordPress), FrankenPHP (api2 + hub Mercure), MariaDB ×2, les
serveurs Nuxt app2/3/4, les Nginx statiques, et `event-cache-worker`. Les
montages sont **relatifs** (`../sources`), donc c'est bien *ce* dossier qui est
servi. `make dev_status` teste juste les 4 URLs en curl.
</details>

### 3. Ouvrir la PR vers develop

```bash
make pr_create      # push -u origin <branche> + gh pr create --base develop --fill
make pr_checks      # suit la CI jusqu'au vert (gh pr checks --watch)
```

`pr_create` échoue si tu n'as rien committé : les cibles `pr_*` poussent, elles
ne committent pas. Titre et description viennent des commits (`--fill`) — d'où
l'intérêt de messages soignés.

### 4. Merger dans develop

```bash
make pr_merge       # depuis la branche de la PR (refusé sur develop/main)
```

> **⚠️ Si le stack Docker tourne depuis ce worktree, bascule-le d'abord.**
> `git worktree remove` échoue sur un dossier non vide, et le stack y maintient
> `docker/db/` (le datadir MariaDB) + les `node_modules`. La cible **refuse
> désormais de merger** dans ce cas, avant tout appel à `gh` :
>
> ```bash
> make dev_down                              # depuis le worktree
> cd ~/Documents/dev/kpi && make dev         # relance depuis le repo principal
> ```
>
> Pour savoir quel dossier est servi :
> `docker inspect kpi_php --format '{{index .Config.Labels "com.docker.compose.project.working_dir"}}'`

<details>
<summary><b>Ce qui se passe en arrière-plan</b></summary>

1. Garde-fou : refuse si tu es sur `develop` ou `main` (sinon `gh` ne sait pas
   quelle PR viser) ;
2. Garde-fou : refuse si le **stack Docker est servi depuis ce worktree** (il
   empêcherait le `worktree remove`) — vérifié **avant** le merge, car un merge
   ne se rejoue pas ;
3. `gh pr merge --squash --delete-branch` — squash dans develop + suppression de
   la branche **distante** ;
4. **puis, selon le contexte** :

| | Dans le repo principal | Dans un worktree |
|---|---|---|
| develop | `git checkout develop && git pull` | `git -C <repo-principal> pull` (checkout seulement si besoin) |
| worktree | — | `git worktree remove` (le worktree courant) |
| branche locale | `git branch -D` | `git -C <repo-principal> branch -D` |

Depuis un worktree, `git checkout develop` sur place est **impossible** (develop
est déjà checkouté dans le repo principal — une branche = un worktree). La cible
détecte le cas via `git rev-parse --git-common-dir` et opère à distance.

Elle vérifie aussi la branche **déjà** checkoutée dans le repo principal : si
c'est `develop` (le cas nominal), le `checkout` est sauté et seul le `pull` est
fait. Sans ça, git refuse (`'develop' is already used by worktree at …`) et le
nettoyage s'arrête alors que la PR est **déjà mergée**.

⚠️ **Après un `pr_merge` depuis un worktree, ton shell est dans un dossier
supprimé.** La cible te le rappelle : fais `cd ~/Documents/dev/kpi`.
</details>

Si tu veux garder le worktree pour enchaîner (rare), supprime-le plus tard avec
`make wt_rm name=scoring` — la branche locale, elle, est déjà partie.

### 5. Publier en production (develop → main)

**Il n'existe pas de cible Make pour cette étape** — c'est volontaire : une
release se décide, elle ne s'automatise pas au fil de l'eau.

```bash
cd ~/Documents/dev/kpi
git checkout develop && git pull
gh pr create --base main --head develop --title "Release: <résumé>" --web
```

`main` est **protégée** (Require PR + linear history), donc pas de push direct.
Le `--web` ouvre le formulaire pour rédiger la note de release. Une fois mergée,
le workflow `backmerge-main-to-develop.yml` réaligne develop automatiquement.

> **Note Dependabot** : ses *security updates* ouvrent leurs PR sur `main`
> directement (elles ignorent `target-branch: develop`, limitation GitHub) —
> d'où ce back-merge automatique.

> **Les cibles `make pr_*` ne committent jamais** : l'ordre est toujours
> `git add` → `git commit -m "..."` → `make pr_push`/`pr_create`. Committer reste
> un geste manuel (choix du message et du périmètre) — un `git push` sans commit
> préalable ne pousse rien de nouveau.

### Cibles Make (raccourcis)

Les cibles `wt_*` ne servent **qu'en mode worktree** ; les cibles `pr_*`
s'utilisent dans les deux modes.

| Cible | Effet | Commande sous-jacente |
|---|---|---|
| `make wt_new name=<n> [base=<b>]` | *(worktree)* crée `feature/<n>` + worktree + fichiers env | `scripts/git-wt.sh new <n> <b>` |
| `make wt_list` | *(worktree)* liste les worktrees | `scripts/git-wt.sh list` |
| `make wt_sync name=<n>` | *(worktree)* re-copie les fichiers non-versionnés | `scripts/git-wt.sh sync <n>` |
| `make wt_rm name=<n>` | *(worktree)* supprime le worktree (garde la branche) | `scripts/git-wt.sh rm <n>` |
| `make pr_push` | push la branche courante en suivi | `git push -u origin <branche>` |
| `make pr_create [base=<b>]` | push + ouvre la PR (base `develop`) | `git push -u …` + `gh pr create --fill` |
| `make pr_web [base=<b>]` | push + ouvre le formulaire PR dans le navigateur | `… + gh pr create --web` |
| `make pr_status` | état de tes PR | `gh pr status` |
| `make pr_checks` | suit la CI de la PR courante jusqu'à la fin | `gh pr checks --watch` |
| `make pr_merge` | merge la PR (squash), remet develop à jour, supprime branche **et worktree** — refuse si le stack Docker tourne depuis ce worktree | `gh pr merge --squash --delete-branch` + `pull` (+ `checkout develop` si besoin) + `worktree remove` + `branch -D` |

Le script `scripts/git-wt.sh` reste utilisable directement (mêmes sous-commandes
`new/list/rm/sync`) ; les cibles Make ne sont que des raccourcis.

Le merge reste faisable au bouton « Merge pull request » sur GitHub — dans ce cas
le nettoyage local (develop à jour, branche, worktree) est à ta charge.

`--squash` garde `develop` propre (un commit par PR) ; `develop` n'impose pas
d'historique linéaire, seule `main` le fait.

---

## Situations particulières (mode worktree)

Ces cas ne se présentent qu'en mode worktree — en solo, il n'y a ni worktree à
supprimer, ni fichiers `.env` à recopier, ni ambiguïté sur le dossier servi par
Docker. Pour les pièges du mode solo, voir
[Pièges du mode solo](#pièges-du-mode-solo).

### `pr_merge` s'est arrêté après le merge (nettoyage incomplet)

Symptôme : la PR est **mergée sur GitHub**, mais la cible a échoué ensuite —
typiquement `fatal: 'develop' is already used by worktree at …` (corrigé
depuis), ou un `worktree remove` refusé sur un dossier non vide.

**Rien n'est perdu** : le merge est distant et acquis. Il reste à finir le
nettoyage local, à la main :

```bash
# 1. Vérifier que la PR est bien mergée
gh pr view <n> --json state,mergeCommit --jq '"\(.state) \(.mergeCommit.oid[0:8])"'

# 2. Mettre develop à jour dans le repo principal (il y est déjà checkouté)
cd ~/Documents/dev/kpi && git pull --ff-only

# 3. Si le stack tourne encore depuis le worktree, le basculer
#    (sinon le remove échouera — voir l'encadré de l'étape 4)
cd ~/Documents/dev/kpi-worktrees/<nom> && make dev_down
cd ~/Documents/dev/kpi && make dev

# 4. Supprimer le worktree, puis la branche locale
git worktree remove ~/Documents/dev/kpi-worktrees/<nom>
git branch -D feature/<nom>
```

> **Si `git worktree remove` a déjà tourné et échoué sur « dossier non vide »** :
> git a pu **désenregistrer** le worktree tout en laissant les fichiers. Il
> disparaît de `git worktree list` mais occupe toujours le disque (~1,5 Go :
> `node_modules` + `docker/db/`). Vérifie qu'aucun conteneur ne le sert et
> qu'il ne contient rien d'unique (`docker/.env`, datadir MariaDB) **avant** de
> faire `rm -rf` dessus.

### Changer de worktree (aligner l'environnement)

Le point sensible : **un seul stack Docker à la fois**, et il sert le dossier
depuis lequel il a été lancé.

```bash
# 1. Arrêter le stack LÀ OÙ il tourne (impératif : les noms de conteneurs
#    sont globaux, `make dev_down` depuis un autre dossier vise les mêmes)
cd ~/Documents/dev/kpi-worktrees/scoring && make dev_down

# 2. Démarrer depuis la nouvelle cible
cd ~/Documents/dev/kpi-worktrees/dark-mode && make dev
make dev_status
```

En pratique `make dev_down` fonctionne depuis n'importe quel worktree (même
`APPLICATION_NAME`, donc mêmes conteneurs), mais le faire depuis le bon dossier
évite toute ambiguïté.

**Si tu ne sais plus quel dossier est servi** :

```bash
docker inspect kpi_php --format '{{range .Mounts}}{{.Source}}{{"\n"}}{{end}}' | head -1
```

**Après le changement, les premiers démarrages sont lents** : caches Vite et
Symfony reconstruits par worktree (ils ne sont plus partagés). Compte ~30 s au
lieu de ~15 s pour les Nuxt, et quelques secondes sur le premier appel api2.

### Les branches ont divergé sur les dépendances

Si `package.json` ou `composer.json` a changé depuis la copie initiale, réinstalle
**dans le worktree concerné** :

```bash
make app4_npm_install          # ou app2_/app3_
make api2_composer_install
```

Chaque worktree ayant sa copie, ça n'affecte aucun autre. Attention : la mémoire
projet note que `make app4_npm_install` peut planter (OOM) — voir les notes
dédiées si le cas se présente.

### 500 au login (`kp_user doesn't exist` ou erreur JWT)

Deux causes distinctes, dans cet ordre :

```bash
# 1. Base vide → HOST_DB_PATH est resté relatif (worktree créé avant juillet 2026)
grep HOST_DB_PATH docker/.env        # doit être un chemin ABSOLU
make wt_sync name=<n>                # le corrige automatiquement

# 2. « Unable to encode the JWT token » → clés Lexik absentes
ls sources/api2/config/jwt/          # doit contenir private.pem + public.pem
make wt_sync name=<n>                # les copie depuis le repo principal
make api2_restart                    # le worker garde la config en mémoire
```

Ne **pas** régénérer les clés avec `make api2_jwt_generate_keys` dans un worktree :
la nouvelle paire ne correspondrait plus à `JWT_PASSPHRASE` de `sources/api2/.env`.

### Un fichier .env manque dans le worktree

```bash
make wt_sync name=scoring      # re-copie les fichiers gitignorés depuis le repo principal
```

`wt_sync` ne réécrit **que** ce qui est absent côté worktree pour les dossiers de
deps, mais **écrase** les fichiers de config (`.env`, `MyParams.php`…). Si tu as
ajusté un `.env` localement, sauvegarde-le avant.

### `pr_merge` s'arrête sur « Worktree non supprimé »

Il reste des modifications non committées dans le worktree. La PR **est déjà
mergée** à ce stade — seul le nettoyage a échoué. Soit :

```bash
cd ~/Documents/dev/kpi
git worktree remove --force ~/Documents/dev/kpi-worktrees/scoring
git branch -D feature/scoring
```

### Je suis dans un dossier qui n'existe plus

Après `pr_merge` depuis un worktree, le shell reste dans le dossier supprimé
(`getcwd: cannot access parent directories`). Simplement :

```bash
cd ~/Documents/dev/kpi
```

### `git worktree list` montre un worktree fantôme

Dossier supprimé à la main sans passer par git :

```bash
git worktree prune       # nettoie les références mortes
```

---

## Faciliter les PR : GitHub CLI (`gh`)

✅ **`gh` est installé et configuré sur le poste** (authentifié en SSH, scope `repo`).
C'est lui qui transforme « pousser une branche » en « PR ouverte » sans quitter le
terminal, via les cibles `make pr_*` ci-dessus (ou `gh` directement).

**⚠️ Configuration importante déjà faite** : le repo a **deux remotes** (`origin` =
`laurentgarrigue/kpi`, `remote_ffck_kpi` = `FFCK/kpi`). `gh` a donc besoin de savoir
lequel cibler, sinon `gh pr create` échoue (« No default remote repository ») et
risquerait d'ouvrir une PR **vers le repo FFCK**. C'est réglé une fois pour toutes :

```bash
gh repo set-default laurentgarrigue/kpi     # déjà fait — à refaire si tu re-clones
```

Commandes `gh` sous-jacentes (rappel) :

```bash
gh pr create --base develop --fill    # PR, titre/desc depuis les commits (= make pr_create)
gh pr create --base develop --web     # formulaire pré-rempli navigateur   (= make pr_web)
gh pr status                          # état de tes PR                      (= make pr_status)
gh pr checks --watch                  # suit la CI jusqu'à la fin           (= make pr_checks)
gh pr merge <n> --squash --delete-branch   # merge la PR
```

Rappel workflow du projet : les PR ciblent **`develop`** (intégration). Le passage
`develop → main` se fait par une PR de release séparée. `main` est protégée
(Require PR + linear history) — voir le plan CI/CD.

> **Note Dependabot** : les *security updates* Dependabot créent leurs PR sur `main`
> (elles ignorent `target-branch: develop` — limitation GitHub). Un workflow
> `.github/workflows/backmerge-main-to-develop.yml` ouvre automatiquement une PR
> `main → develop` après chaque push sur `main` pour réaligner develop.

---

## Astuces VS Code

- **Une fenêtre par worktree** : `File → New Window` puis ouvre le dossier du
  worktree. Chaque fenêtre a sa branche, son terminal intégré, son état Git.
- **Workspace multi-root** possible (ajouter chaque worktree comme dossier), mais
  une fenêtre par feature reste plus lisible.
- Le panneau Source Control montre la bonne branche par fenêtre automatiquement.

---

## Pièges à connaître

- **Ne pas checkouter la même branche dans deux worktrees** : git l'interdit (une
  branche = un worktree). C'est voulu.
- **Deps copiées, pas partagées** : chaque worktree a ses propres `node_modules` et
  `api2/vendor` (~1,8 Go). Si un `package.json` diverge, réinstalle dans le worktree
  concerné (`make app4_npm_install`) — aucun impact sur les autres. Ne les remplace
  **pas** par des symlinks : voir l'encadré plus haut, ça casse le stack Docker.
- **Worktrees créés avant juillet 2026** : ils ont encore des symlinks. Corrige-les
  avec `make wt_sync name=<n>` (le script détecte et remplace les liens obsolètes).
- **`docker/.env`** est copié, pas lié : si tu changes `APPLICATION_NAME` dans un
  worktree pour tenter deux stacks en parallèle, tu sors du cas « un stack à la
  fois » — non couvert ici (ports en dur dans compose.dev.yaml).
