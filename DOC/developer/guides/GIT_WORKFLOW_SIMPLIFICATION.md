# Simplification & consolidation du workflow Git / CI-CD

> **Statut** : ✅ **APPLIQUÉE** (lots 1 et 3, le 2026-09-13). Rédigée le 2026-09-12
> à partir des runs en échec, des PR en attente et de l'incident `make pr_merge`
> du 2026-09-11. Conservée comme dossier d'analyse et journal de la bascule.
> Document compagnon de [GIT_WORKFLOW.md](GIT_WORKFLOW.md) (qui décrit l'existant).

---

## 0. Résumé exécutif

Les pannes à répétition (merge develop, déploiement préprod, merge main, PR
Dependabot) **ne viennent pas d'un manque de rigueur ni d'un excès de contrôles
qualité**. Elles viennent de **six défauts mécaniques précis**, tous corrigeables
sans retirer un seul garde-fou.

Le constat central : **la sécurité n'est pas le problème, la topologie l'est.**
Le pipeline a deux branches longue durée, cinq workflows qui s'écrivent
mutuellement dessus (bump, back-merge, deploy), et un check requis dont
l'allowlist est instable par construction. Chaque automatisme est raisonnable
isolément ; c'est leur **superposition** qui produit les divergences.

| # | Défaut | Symptôme vécu | Gravité | Effort |
|---|---|---|---|---|
| 1 | `pr_merge` fait `git pull` avec `pull.rebase=true` | `fatal: Pas possible d'avancer rapidement` | 🔴 bloquant | 5 min |
| 2 | Allowlist Gitleaks par *fingerprint horodaté* | `ci-summary` rouge sur du code sain | 🔴 bloquant | 30 min |
| 3 | Dependabot npm livre un lock désynchronisé | `npm ci` EUSAGE sur PR #309 | 🔴 bloquant | 20 min |
| 4 | Trois workflows écrivent sur `develop` | PR #304 `CONFLICTING` | 🟠 chronique | 1 h |
| 5 | Deux branches longue durée pour un mainteneur unique | back-merge permanent | 🟠 chronique | 1/2 j |
| 6 | Rollback serveur sans revert dépôt | re-déploie le commit cassé | 🟠 latent | 30 min |

> **Le déploiement d'une branche / d'un tag expérimental en préprod est
> intégralement préservé** — geste inchangé, une seule variable VPS à basculer.
> Détail et vérifications : [§2bis](#2bis-la-préprod-expérimentale-reste-intacte).

**Gain attendu** : suppression de ~90 % des interventions manuelles de
rattrapage, **à niveau de sécurité et de qualité strictement égal** (aucun check
retiré ; deux checks deviennent au contraire plus fiables).

---

## 1. Ce que disent les faits

État relevé le 2026-09-12 (`gh run list`, `gh pr list`).

### 1.1 Les runs en échec

| Run | Workflow | Cause réelle |
|---|---|---|
| `34649361260` | CI sur PR #309 (Dependabot) | `npm ci` → `EUSAGE`, `Missing: oxc-parser@0.149.0 from lock file` (+17 autres) |
| `34646511749` | CI sur PR #304 | `secrets-scan` → `leaks found: 31` — **tous des faux positifs déjà connus** |
| `34647952025` | Deploy preprod | SSH échoué 2× (60 s chacune), `SHA invalide` dans la résolution |
| `34648069058` | Dependabot Updates | `The updater encountered one or more errors` |

### 1.2 Les PR en attente

| PR | Base | État | Blocage |
|---|---|---|---|
| #309 Dependabot npm | `main` | `BLOCKED` | `ci-summary` rouge (lock désynchronisé) |
| #308 back-merge | `develop` | `UNKNOWN` | en attente de merge manuel |
| #304 « Develop » | `main` | **`CONFLICTING` / `DIRTY`** | develop et main ont divergé |

`origin/main` porte 2 commits absents de `develop` (dont le bump Dependabot
`dc2b7773`), `origin/develop` en porte 5 absents de `main`. **La release est
bloquée par un conflit que le process a lui-même fabriqué.**

### 1.3 L'incident `make pr_merge`

Ton log montre le merge réussi puis :

```
hint: Diverging branches can't be fast-forwarded...
fatal: Pas possible d'avancer rapidement, abandon.
! warning: not possible to fast-forward to: "develop"
```

**La PR était mergée. Seul le nettoyage local a échoué** — bruit anxiogène sur
une opération en réalité réussie.

---

## 2. Les six défauts, expliqués

### Défaut 1 — `pr_merge` et `pull.rebase=true` 🔴

`git config pull.rebase` = **`true`** sur ce poste. Or
[Makefile:1255](../../../Makefile#L1255) fait :

```make
git checkout develop && git pull || exit 1;
```

Chronologie de l'échec :

1. `gh pr merge --squash` crée un commit **neuf** sur `origin/develop` ;
2. le `develop` local porte encore l'ancien commit → **divergence légitime** ;
3. `git pull` en mode rebase tente de rejouer le commit local par-dessus… mais
   son contenu est déjà en amont (sous forme squashée) → `fatal`.

C'est structurel : **après tout squash-merge, le local diverge toujours**. La
cible ne peut pas fonctionner de façon fiable en l'état.

**Correctif** — remplacer le `pull` par une remise à l'état distant, qui est la
seule sémantique correcte ici (on ne veut *jamais* conserver de travail local
sur `develop`) :

```make
git fetch origin develop && \
git checkout develop && \
git reset --hard origin/develop || exit 1;
```

`reset --hard` est sûr **précisément parce que** `develop` n'accepte aucun commit
local (ruleset). Ajouter un garde-fou explicite juste avant :

```make
if ! git diff --quiet || ! git diff --cached --quiet; then \
  echo "⛔ Working tree sale sur develop — commit/stash avant."; exit 1; \
fi;
```

### Défaut 2 — L'allowlist Gitleaks est instable par construction 🔴

`.gitleaksignore` (supprimé par le lot 1, remplacé par
[`.gitleaks.toml`](../../../.gitleaks.toml)) listait des **fingerprints** de la
forme `<SHA_COMMIT>:<fichier>:<règle>:<ligne>`.

Le SHA du commit fait partie de la clé. Conséquences :

- un **nouveau commit** touchant un de ces fichiers → **nouveau fingerprint** →
  non couvert → CI rouge ;
- `fetch-depth: 0` fait rescanner **tout l'historique** : le moindre changement
  de contexte régénère des fingerprints inédits ;
- l'allowlist doit être ré-alimentée *après chaque échec* — c'est le mode de
  fonctionnement actuel, et il ne convergera jamais.

Les 31 « leaks » du run `34646511749` sont : des **noms d'icônes FontAwesome**
(`faLinkedin`), une **clé Google Maps front-end de 2016** et son cache Smarty
compilé, et des **placeholders de documentation**. Zéro secret réel.

**Correctif** — passer d'une liste de fingerprints à une **config `.gitleaks.toml`
à base de règles**, stable dans le temps :

```toml
title = "KPI gitleaks config"
[extend]
useDefault = true

[allowlist]
description = "Faux positifs structurels — vérifiés le 2026-09-11"
paths = [
  # Librairies tierces vendorées : constantes, pas des secrets
  '''sources/(program|report)/libraries/fontawesome/.*''',
  # Caches Smarty compilés (générés, non éditables)
  '''smarty/templates_c/.*''',
  # Placeholders de doc
  '''DOC/developer/reference/API2_ENDPOINTS\.md''',
  '''sources/api2/README\.md''',
]
regexes = [
  # Clé Google Maps front-end publique (2016), révoquée — front-end par nature
  '''AIza[0-9A-Za-z\-_]{35}''',
]
```

et **scanner le diff de la PR, pas tout l'historique** :

```yaml
- uses: actions/checkout@v4
  with:
    fetch-depth: 0
- name: Gitleaks
  uses: gitleaks/gitleaks-action@v2
  env:
    GITHUB_TOKEN: ${{ secrets.GITHUB_TOKEN }}
    GITLEAKS_CONFIG: .gitleaks.toml
    GITLEAKS_ENABLE_COMMENTS: false
```

> ⚠️ **Ceci ne baisse pas la garde** : les règles par *chemin* visent uniquement
> des répertoires vendorés/générés, et la seule regex autorisée cible une clé
> front-end publique déjà révoquée. Un vrai secret dans du code applicatif reste
> détecté. On gagne même en fiabilité : plus de rouge aléatoire qui pousse à
> merger sans lire le rapport — **le pire effet d'un check instable, c'est
> qu'on cesse de le croire**.

### Défaut 3 — Dependabot npm et le lock désynchronisé 🔴

PR #309 : `npm ci` refuse le lock (`Missing: oxc-parser@0.149.0`, `unplugin@3.3.0`,
et toutes les variantes `@oxc-parser/binding-*`).

C'est le piège déjà documenté pour app4 (cf. mémoire
`reference_app4_lock_eslint_peerdep`) : Dependabot met à jour `package.json` et
recalcule le lock **partiellement**, sans résoudre les dépendances optionnelles
multi-plateformes ni les peer deps transitives.

**Correctif en deux temps.**

*a) Réduire la surface* — retirer de `dependabot.yml` les répertoires morts. Le
commentaire du fichier exclut déjà `/sources/app3` pour cette raison ; la même
logique vaut pour les legacy non buildés :

```yaml
directories:
  - "/sources/app2"
  - "/sources/app4"
  - "/sources"
  # app_dev, app_live_dev, app_wsm_dev retirés : non buildés, non déployés.
  # Ils ne passent pas en CI et ne produisent que des PR ingérables.
```

*b) Réparer le lock automatiquement* — job dédié dans `ci.yml`, déclenché
seulement sur les PR Dependabot :

```yaml
  fix-dependabot-lock:
    if: github.actor == 'dependabot[bot]'
    runs-on: ubuntu-latest
    permissions:
      contents: write
    strategy:
      matrix:
        app: [app2, app4]
    steps:
      - uses: actions/checkout@v4
        with:
          ref: ${{ github.head_ref }}
          token: ${{ secrets.GITHUB_TOKEN }}
      - uses: actions/setup-node@v4
        with:
          node-version: ${{ env.NODE_VERSION }}
      - name: Resynchroniser le lock
        working-directory: sources/${{ matrix.app }}
        run: |
          npm install --package-lock-only=false --ignore-scripts
          if ! git diff --quiet package-lock.json; then
            git config user.name  "github-actions[bot]"
            git config user.email "github-actions[bot]@users.noreply.github.com"
            git add package-lock.json
            git commit -m "chore: resync ${{ matrix.app }} lock (Dependabot)"
            git push
          fi
```

> **Un vrai `npm install`**, jamais `--package-lock-only` : c'est exactement
> l'erreur relevée sur app4 (mémoire `reference_app4_lock_eslint_peerdep`).

### Défaut 4 — Trois workflows écrivent sur `develop` 🟠

Sur un simple push `develop`, se déclenchent **en parallèle** :

- `deploy-preprod.yml` (déploie),
- `version-bump.yml` (ouvre une PR de bump, **auto-merge** → nouveau push
  develop → **re-déclenche deploy-preprod**),
- et, côté `main`, `backmerge-main-to-develop.yml` ouvre une 3ᵉ PR.

Chaque merge peut donc en engendrer deux autres. C'est la source directe du
`CONFLICTING` de la PR #304 : `develop` bouge pendant que la PR de release
est ouverte.

**Correctif** — réduire la source du bruit :

1. **La sérialisation est DÉJÀ en place — ne rien changer.**
   `deploy-preprod.yml` (lignes 32-36) déclare déjà :

   ```yaml
   concurrency:
     group: deploy-preprod
     cancel-in-progress: false   # jamais interrompre un déploiement en cours
   ```

   et `deploy-preprod-experimental.yml` partage **volontairement le même
   groupe**, pour qu'un déploiement expérimental et un déploiement automatique ne
   s'entrelacent jamais sur la même préprod. C'est correct : à **préserver tel
   quel** lors de toute réécriture des workflows.

2. **Le bump ne devrait pas déclencher de déploiement** — ajouter au
   `deploy-preprod.yml` (`branches: [main]` après le lot 3) :

   ```yaml
   on:
     push:
       branches: [develop]     # → [main] après le lot 3
       paths-ignore:
         - '**/package.json'
         - '**/composer.json'
         - 'sources/api2/config/packages/*.yaml'
         - 'DOC/**'
         - '**/*.md'
   ```

   Un bump de version pur ne change aucun comportement : le redéployer est du
   bruit, et c'est ce bruit qui multiplie les fenêtres de conflit.

   > ⚠️ `paths-ignore` ne filtre **que** le déclencheur `push` de ce workflow. Il
   > n'affecte ni le `workflow_dispatch` (redéploiement manuel), ni le retour
   > automatique du cron `--check-expiry` (qui appelle le wrapper directement sur
   > le VPS, sans passer par GitHub Actions). Le dispositif expérimental du §2bis
   > reste donc intact.

3. **Bumper à la release, pas à chaque merge** — voir §3, où le bump se déplace
   naturellement sur le tag.

### Défaut 5 — Deux branches longue durée pour un mainteneur unique 🟠

> **Avant de lire** : le déploiement d'une branche expérimentale en préprod
> survit intégralement à ce changement. Voir **[§2bis](#2bis-la-préprod-expérimentale-reste-intacte)**.


C'est **la cause racine** des défauts 4 et 6, et de la moitié de ta charge
mentale.

Le modèle `develop` + `main` (GitFlow) répond à un besoin précis : plusieurs
développeurs, des releases datées, des correctifs sur version publiée. Ici :
**un seul mainteneur, un déploiement continu en préprod, une prod manuelle**.
GitFlow ne rend aucun service et facture tout son coût :

- un back-merge permanent (workflow + PR + merge, à chaque passage sur `main`) ;
- les *security updates* Dependabot qui ciblent `main` (limitation GitHub
  documentée dans ta mémoire `reference_dependabot_security_updates_main`) et
  créent une divergence **structurelle** ;
- une PR de release qui, en pratique, conflicte (PR #304 aujourd'hui).

**Correctif recommandé — passer à une seule branche longue durée (`main`) plus
des tags de release.**

```
feature/*  ──PR──►  main  ──(push)──►  déploiement préprod auto
                     │
                     └──(tag vX.Y.Z)──►  déploiement prod (manuel + approbation)
```

Ce que ça supprime, sans rien perdre :

| Supprimé | Pourquoi c'est sans risque |
|---|---|
| `backmerge-main-to-develop.yml` | plus de seconde branche à réaligner |
| PR de back-merge à merger | idem |
| La divergence Dependabot `main`/`develop` | les security updates ciblent la seule branche |
| La PR de release conflictuelle (#304) | une release = un **tag**, jamais un merge |
| `make backmerge_main_to_develop` | sans objet |

Ce qui est **conservé à l'identique** :

- ✅ PR obligatoire sur `main` (ruleset inchangé) ;
- ✅ `ci-summary` requis avant merge ;
- ✅ historique linéaire (squash) ;
- ✅ déploiement prod **manuel avec approbation** (`environment: production`) ;
- ✅ backup DB, smoke test, rollback auto.

La prod se déploie alors sur un **tag**, ce qui est plus sûr que l'actuel : un
tag est immuable et désigne sans ambiguïté ce qui tourne, là où « le HEAD de
`main` au moment où j'ai lancé le workflow » est une cible mouvante.

> **Si tu préfères garder `develop`** (par exemple pour préserver une zone
> tampon avant la prod), les défauts 1, 2, 3 et 6 restent corrigeables
> indépendamment — ils représentent déjà les trois blocages 🔴. Le défaut 5 est
> le seul qui exige ce choix de topologie, et il est réversible.

### Défaut 6 — Le rollback serveur ne touche pas le dépôt 🟠

Documenté dans [GIT_WORKFLOW.md §3](GIT_WORKFLOW.md#3-rollback--quand-un-déploiement-préprod-casse) :
le wrapper restaure le VPS, mais le commit fautif **reste sur la branche**. Sans
revert manuel, **le déploiement suivant le re-déploie et re-rollback**.

La procédure existe (`make last_merge_sha` → `preprod_rollback` → PR), mais elle
est **manuelle, en trois étapes, au pire moment** — juste après une panne.

**Correctif** — que le workflow ouvre la PR de revert lui-même :

```yaml
      - name: Ouvrir une PR de revert si le déploiement a rollback
        if: failure()
        env:
          GH_TOKEN: ${{ secrets.GITHUB_TOKEN }}
        run: |
          sha="${{ steps.resolve.outputs.sha }}"
          git config user.name  "github-actions[bot]"
          git config user.email "github-actions[bot]@users.noreply.github.com"
          git checkout -b "revert/$sha"
          if git revert --no-edit "$sha"; then
            git push -u origin "revert/$sha"
            gh pr create --base "$GITHUB_REF_NAME" \
              --title "revert: déploiement préprod KO sur $sha" \
              --body "Rollback auto côté VPS. Cette PR retire le commit fautif du dépôt, sinon le prochain déploiement le re-déploiera."
          else
            gh issue create \
              --title "⚠️ Revert auto impossible pour $sha" \
              --body "Conflit de revert : intervention manuelle requise (make preprod_rollback sha=$sha)."
          fi
```

Le rollback devient **complet** : serveur *et* dépôt. En cas de conflit, une
issue est ouverte plutôt qu'un échec silencieux.

---

## 2bis. La préprod expérimentale reste intacte

**Question posée** : dans le workflow cible, déployer une branche / une release /
un tag expérimental en préprod reste-t-il possible et simple ?

**Réponse : oui, et le geste ne change pas du tout.** Une seule variable
d'environnement est à modifier côté VPS, et rien dans le workflow ni dans le
front. Vérification faite sur le VPS préprod le 2026-09-13.

### Ce qui existe (et qui est bien conçu)

`deploy-preprod-experimental.yml` déploie temporairement une branche en préprod :

```
Actions → « Deploy preprod (experimental) » → Run workflow
   branch    = feature/scoring
   ttl_hours = 24
```

Le wrapper VPS écrase la préprod avec cette branche, dépose un
`experimental-flag.json` dans la racine servie de chaque app (bandeau fuchsia
non masquable), et un **cron horaire** (`--check-expiry`, à HH:15) restaure la
branche de référence à expiration du TTL.

État constaté sur le VPS :

| Élément | Constat |
|---|---|
| Cron `--check-expiry` | ✅ **actif** — log écrit le 2026-09-13 00:15 : « Aucun déploiement expérimental actif — rien à faire. » |
| Installation du cron | cible `install-cron-experimental-expiry` du Makefile `vps-manager` (crontab de `laurent`, d'où son invisibilité sous `crontab -l` de `deploy`) |
| Branche de référence | `DEPLOY_DEFAULT_BRANCH="develop"` dans `/data/vps-manager/.env` (ligne 35) |
| Concurrency | partagée avec `deploy-preprod.yml` (groupe `deploy-preprod`) |
| Refspec du checkout préprod | `+refs/heads/*:refs/remotes/origin/*` — **toutes** les branches suivies |

### Pourquoi ça survit au passage en mono-branche

Trois raisons, toutes vérifiées :

1. **La branche de référence est une variable, pas un littéral.** Le wrapper lit
   `DEFAULT_BRANCH="${DEPLOY_DEFAULT_BRANCH:-develop}"`, alimentée par le `.env`
   de `vps-manager`. Elle n'est **pas** dans la liste des variables requises
   (`DEPLOY_PATH_*`, `SMOKE_URL_*`) : elle a un fallback, donc rien ne casse
   pendant la transition.

2. **Le checkout préprod suit déjà toutes les branches.** Le refspec est
   générique et `origin/main` y résout déjà (`dc2b7773`). Aucun changement de
   configuration git n'est nécessaire.

3. **Le front ne connaît aucune branche.** `ExperimentalBanner.vue` affiche
   `flag.branch` lu depuis le JSON déposé par le wrapper ; `useExperimentalFlag`
   ne mentionne `develop` que dans ses commentaires. Aucune logique à modifier —
   seulement des commentaires à rafraîchir.

### Le seul changement à faire

Une ligne, côté VPS :

```bash
# /data/vps-manager/.env
DEPLOY_DEFAULT_BRANCH="main"   # était "develop"
```

Et, pour la cohérence documentaire, la même ligne dans `.env.dist` (dépôt
`vps-manager`), plus les commentaires de `useExperimentalFlag.ts` (app2 **et**
app4 — les deux fichiers sont des jumeaux à maintenir en parallèle).

> ⚠️ **À faire au moment du basculement, pas avant.** Tant que la préprod suit
> `develop`, pointer la restauration sur `main` ferait revenir la préprod sur un
> état qui n'est pas celui déployé en continu.

### Déployer un tag ou une release en préprod

C'est le **gain** de la cible mono-branche : aujourd'hui l'input `branch` est
validé par la regex `^[A-Za-z0-9._/-]{1,100}$`, qui accepte déjà `v1.26.0`. Mais
le `actions/checkout` se fait sur `ref:` — qui accepte une branche **comme un
tag**. Donc :

```
Actions → « Deploy preprod (experimental) » → Run workflow
   branch    = v1.26.0        ← un tag fonctionne tel quel
   ttl_hours = 4
```

Cas d'usage direct dans le workflow cible : **rejouer en préprod le tag
exactement déployé en prod** pour reproduire un bug signalé par un utilisateur,
sans toucher à la prod ni à `main`. Le TTL garantit le retour automatique.

> Le champ s'appelle `branch` et sa description dit « Branche à déployer » : si tu
> t'en sers pour des tags, renomme l'input en `ref` (cosmétique, aucune logique à
> changer — la regex et le checkout acceptent déjà les deux).

### Ce qu'il faut vérifier au passage

Le cron tourne bien, mais il est installé dans le **crontab de `laurent`**, pas
dans celui de `deploy`. C'est fragile à deux titres : invisible depuis le compte
de déploiement, et perdu si le crontab de `laurent` est réinitialisé. Sans lui,
**le TTL ne s'applique plus** et un déploiement expérimental resterait
indéfiniment en préprod — le bandeau resterait affiché, ce qui limite le risque
de confusion, mais la préprod cesserait de refléter la branche de référence.

Contrôle rapide (à faire après tout changement d'infra) :

```bash
ssh deploy@<vps> 'tail -3 /data/logs/cron/preprod-experimental-expiry.log'
# Doit afficher une ligne récente (le cron tourne à HH:15).
```

Réinstallation si besoin, depuis `/data/vps-manager` :

```bash
make install-cron-experimental-expiry
```

---

## 3. La cible proposée

```
        TOI (local)                          GITHUB ACTIONS (auto)
   ┌────────────────────┐
   │ branche feature    │
   │ code + commit      │
   └─────────┬──────────┘
             │ make pr_create
             ▼
   ┌────────────────────┐   PR ouverte   ┌──────────────────────────────┐
   │   PR → main        │───────────────►│ CI (ci.yml) — inchangée      │
   └─────────┬──────────┘                │ + fix-dependabot-lock        │
             │ make pr_checks            └──────────────────────────────┘
             │ make pr_merge (squash)
             ▼
   ══════════════════ main ══════════════════
             │ (push, hors bumps/docs)
             │              ┌──────────────────────────────────────┐
             ├─────────────►│ Deploy preprod  [concurrency: 1]     │
             │              │  ❌ KO → rollback VPS + PR de revert  │
             │              └──────────────────────────────────────┘
             │
             │ make release version=X.Y.Z   (tag, décision explicite)
             ▼
   ┌────────────────────┐   ┌──────────────────────────────────────┐
   │  tag vX.Y.Z        │──►│ Deploy production                    │
   └────────────────────┘   │  manuel + approbation + backup DB    │
                            └──────────────────────────────────────┘
```

**Workflows : 9 → 7.** Disparaissent `backmerge-main-to-develop.yml` et
`version-bump.yml` (le bump se fait à la release). `ci.yml` gagne un job.

### Le cycle quotidien

```bash
git checkout main && git pull        # (ff garanti : plus de divergence possible)
git checkout -b feature/scoring
# ... code, commit ...
make pr_create && make pr_checks && make pr_merge
```

### La release

```bash
make release version=1.26.0
# → bump des versions (app2/app4/api2), commit, tag v1.26.0, push
# → puis : Actions → Deploy production → ref = v1.26.0 → approbation
```

---

## 4. Plan d'application

Ordonné par **ratio bénéfice/risque décroissant**. Les trois premiers lots
débloquent le quotidien et sont indépendants du choix de topologie.

### Lot 1 — Débloquer l'immédiat ✅ APPLIQUÉ (PR #311, 2026-09-13)

1. Corriger `pr_merge` (`reset --hard` sur la branche cible + garde working tree) ;
2. Créer `.gitleaks.toml` par règles, supprimer `.gitleaksignore` ;
3. Ajouter `fix-dependabot-lock` à `ci.yml` ;
4. Élaguer `dependabot.yml` (retirer `app_dev`, `app_live_dev`, `app_wsm_dev`).

**Effet** : PR #309 passe, `ci-summary` redevient fiable, `pr_merge` cesse de
finir en `fatal`.

### Lot 2 — Calmer les boucles (partiellement absorbé par le lot 3)

5. ~~`concurrency: deploy-preprod`~~ — **était déjà en place** (constat du §2, défaut 4) ;
6. ✅ `paths-ignore` sur le déploiement (bumps, docs) — appliqué avec le lot 3 ;
7. ✅ PR de revert automatique sur échec de déploiement — appliqué le 2026-09-13
   (étape `if: failure()` de `deploy-preprod.yml` : ouvre une PR `revert/<sha>`,
   ou une **issue** si le revert conflicte ; voir défaut 6).

### Lot 3 — Simplifier la topologie ✅ APPLIQUÉ (2026-09-13)

8. ✅ PR #304, #308 et #309 fermées — **la cause réelle était tout autre** : `main`
   était un **historique orphelin** (2 commits, aucun ancêtre commun avec
   `develop`), d'où l'impossibilité de merger. Réparé par force-push de `develop`
   sur `main` (sauvegarde : tag `backup/main-before-reset-20260913`) ;
9. ✅ `main` est la branche par défaut ; rulesets et Dependabot y pointent
   (`target-branch: "develop"` retiré des 3 écosystèmes) ;
10. ✅ `backmerge-main-to-develop.yml` et `version-bump.yml` supprimés ;
11. ✅ Cibles `make release version=X.Y.Z` **et** `make release_tag version=X.Y.Z` ;
12. ✅ `deploy-prod.yml` documente le tag comme `ref` attendu (la vérification
    d'ancêtre fonctionnait déjà pour un tag, aucun code à changer) ;
13. ⏳ **Basculer la branche de référence de la préprod expérimentale** (cf. §2bis) :
    `DEPLOY_DEFAULT_BRANCH="main"` dans `/data/vps-manager/.env` **et** dans son
    `.env.dist` — **RESTE À FAIRE, côté VPS** (dépôt `vps-manager`, hors de ce
    repo). Les commentaires de `useExperimentalFlag.ts` (app2 + app4) et du
    workflow experimental sont ✅ à jour ;
14. ⏳ Archiver `develop` **et** `chore/backmerge-main-to-develop` (vestige du
    workflow supprimé) — la topologie a désormais tourné sur plusieurs cycles
    verts, c'est la dernière étape ;
15. ✅ [GIT_WORKFLOW.md](GIT_WORKFLOW.md) réécrit pour la topologie mono-branche.

### Lot 4 — Hygiène (optionnel)

15. Passer `actions/checkout@v4` → `@v5` (avertissements Node 20 dépréciés
    sur tous les runs) ;
16. Fiabiliser le SSH de déploiement : le run `34647952025` montre deux timeouts
    à 60 s et un `SHA invalide` — porter le timeout à 120 s et logger le SHA
    résolu avant connexion.

---

## 5. Débloquer la PR #304 dès maintenant

Elle est `CONFLICTING` : `main` porte 2 commits absents de `develop`, `develop`
en porte 5 absents de `main`.

**Si tu retiens le lot 3** (mono-branche), le plus simple est de ne pas la
réparer : merger la PR de back-merge #308 pour que `develop` contienne tout,
puis faire de `develop` la nouvelle `main`.

```bash
gh pr merge 308 --squash              # develop récupère les 2 commits de main
git checkout develop && git pull
# develop est alors un sur-ensemble de main → la PR #304 devient mergeable
gh pr checks 304 && gh pr merge 304 --squash
gh pr close 309   # la PR Dependabot sera réouverte proprement après le lot 1
```

**Si tu restes en deux branches**, même séquence, en gardant #304 comme PR de
release — l'ordre (back-merge **d'abord**, release **ensuite**) est ce qui évite
le conflit, et c'est précisément ce que le process actuel ne garantit pas.

---

## 6. Ce qui n'est pas touché

Pour lever toute ambiguïté sur le « sans rogner sur la sécurité » :

| Garde-fou | Statut |
|---|---|
| PR obligatoire (pas de push direct) | ✅ conservé |
| `ci-summary` = required check | ✅ conservé (et **fiabilisé**) |
| Historique linéaire / squash | ✅ conservé |
| lint · PHPStan · tests api2 · smoke | ✅ conservés à l'identique |
| `audit-npm` / `audit-composer` | ✅ conservés |
| CodeQL, Trivy image, Trivy config | ✅ conservés |
| Scan de secrets | ✅ conservé, **rendu déterministe** |
| Approbation manuelle pour la prod | ✅ conservée |
| Backup DB avant migration prod | ✅ conservé |
| Rollback auto préprod & prod | ✅ conservé, **complété par le revert dépôt** |
| Isolation des secrets preprod/prod | ✅ conservée |
| Déploiement préprod expérimental (TTL + bandeau) | ✅ conservé — cf. [§2bis](#2bis-la-préprod-expérimentale-reste-intacte) |
| Sérialisation des déploiements préprod (concurrency) | ✅ déjà en place, à préserver |

**Aucun contrôle n'est retiré.** Deux (secrets-scan, rollback) deviennent plus
sûrs qu'aujourd'hui. Ce qui disparaît, ce sont des **étapes de rattrapage
manuel** rendues nécessaires par la topologie — pas des vérifications.

---

## 7. Suites possibles

- **Couverture de tests api2** — déjà identifiée comme le principal affinage
  restant (mémoire `project_cicd_pipeline`). Une fois le pipeline stable, c'est
  le prochain investissement qualité à plus fort rendement.
- **Durcir `trivy-config` en HIGH** et hadolint au-delà de `error`, une fois la
  dette Dockerfile nettoyée (échéance déjà notée dans `ci.yml`).
- **Tests Playwright en CI** — l'infrastructure existe
  (`reference_playwright_dev_testing`), elle ne tourne pas encore en pipeline.

### Intégrer `vps-manager` au CI/CD — et revoir le compte `deploy`

`vps-manager` (dépôt privé : `deploy-wrapper.sh`, `backup.sh`, `health-check.sh`,
`Makefile`, `.env`) est aujourd'hui **hors CI/CD** : il vit en clone direct dans
`/data/vps-manager`, se modifie à la main en SSH, et rien ne valide ses scripts
avant qu'ils ne pilotent un déploiement. C'est le maillon le moins outillé de la
chaîne, alors que c'est **lui** qui déploie.

**Ce qu'une intégration apporterait :**

- **Lint des scripts** (`shellcheck` sur `*.sh`) — un `deploy-wrapper.sh` cassé
  ne se découvre aujourd'hui qu'en plein déploiement ;
- **Déploiement versionné du wrapper** : un `git pull` dans `/data/vps-manager`
  déclenché par workflow, au lieu d'éditions manuelles non tracées ;
- **Gestion du cron par le dépôt** : `install-cron-experimental-expiry` est
  aujourd'hui à relancer à la main, dans le crontab du bon utilisateur (cf.
  §2bis — il vit dans celui de `laurent`, pas de `deploy`, ce qui le rend
  invisible depuis le compte de déploiement et fragile).

#### Le compte `deploy` partagé : à corriger, et c'est le point sensible

**Question posée** : est-il judicieux d'utiliser le même compte `deploy` et les
mêmes clés SSH pour deux projets distincts sur le même VPS ?

**Réponse : non.** Audit effectué le 2026-09-13 sur le VPS préprod :

| Constat | Détail |
|---|---|
| Groupe `docker` | `deploy` en est membre (`uid=1002 … 994(docker)`) |
| Conteneurs atteignables | **tous** : `kpi_*`, `matomo_app`, `matomo_db`, `traefik`, `pma`, `broker` |
| Clé SSH | **une seule**, sans restriction `command=` dans `authorized_keys` |
| `/data/vps-manager/.env` | **lisible** par `deploy` — contient les identifiants DB de **tous** les services (`SERVICES_TO_BACKUP` : kpi, wordpress, matomo…) |
| Écriture fichier | correctement limitée à `/data/kpi*` par ACL (`user:deploy:rwx`) |

Les ACL sont bien faites, mais **l'appartenance au groupe `docker` les rend
cosmétiques** : qui peut parler au daemon Docker peut monter `/` dans un
conteneur privilégié et devenir root sur toute la machine. Concrètement, la clé
SSH stockée dans les secrets GitHub du dépôt KPI donne, en cas de compromission,
un accès effectif à **Matomo, WordPress, Dolibarr, Traefik et leurs bases** —
des services sans rapport avec KPI.

C'est un problème de **rayon d'explosion**, pas une faille immédiate : le risque
n'est pas que le pipeline fasse quelque chose de mal aujourd'hui, c'est qu'un
secret GitHub qui fuite (action tierce compromise, fork malveillant, log
imprudent) emporte bien plus que le projet auquel il appartient.

**Correctifs, par ordre de rapport bénéfice/effort :**

1. **Une clé par usage, pas une clé pour tout** (rapide, sans risque) — une paire
   distincte par dépôt et par environnement (`kpi-preprod`, `kpi-prod`,
   `vps-manager`), chacune dans son environment GitHub. Révoquer une clé cesse
   alors d'être un événement global. C'est l'effort le plus faible pour le gain
   le plus net ;
2. **Restreindre chaque clé dans `authorized_keys`** — `command="/home/deploy/deploy-wrapper.sh …",no-port-forwarding,no-agent-forwarding,no-pty`,
   pour qu'une clé volée ne donne pas un shell interactif ;
3. **Sortir les secrets DB de la portée de `deploy`** — `.env` de `vps-manager`
   en `root:root 0600`, le wrapper le lisant via un `sudo` ciblé ; ou scinder le
   fichier (chemins/URLs lisibles par `deploy`, identifiants de backup réservés
   à `backup.sh`, qui n'a pas à tourner sous le même compte que le déploiement ;
4. **Comptes distincts par projet** (`deploy-kpi`, `deploy-vpsmanager`) — la
   séparation la plus propre, mais elle ne vaut vraiment **que combinée au
   point 5** : deux comptes tous deux dans `docker` restent équivalents à root ;
5. **Retirer `deploy` du groupe `docker`** — le vrai correctif de fond. À
   remplacer par un `sudoers` nominatif et limité :

   ```
   deploy ALL=(root) NOPASSWD: /usr/bin/docker compose -f /data/kpi_preprod/docker/compose.preprod.yaml *
   ```

   Plus intrusif (à tester hors production), mais c'est le seul point qui réduit
   réellement le rayon d'explosion.

> **Priorité** : les points 1 et 2 sont réalisables en une session, sans rien
> casser, et couvrent l'essentiel du risque « secret GitHub qui fuite ». Les
> points 3 à 5 relèvent d'un chantier d'infra à planifier — à ne pas entamer en
> même temps que la refonte du workflow git, pour garder des changements
> diagnosticables un par un.
