# Développer plusieurs features en parallèle (git worktrees)

**But** : bosser sur plusieurs branches feature simultanément sur le même poste,
dans des fenêtres VS Code / terminaux distincts, sans `git stash` ni `checkout`
qui écrase le travail en cours — puis ouvrir les PR facilement.

Outil : [`scripts/git-wt.sh`](../../../scripts/git-wt.sh).

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
   **symlinkés** vers le repo principal (gain de temps/espace), pas réinstallés.

### Spécificité Docker — UN SEUL stack à la fois

Le stack monte `../sources` **relatif au dossier courant** et expose des **ports
fixes** (3002/3003/3004/8080) + un `APPLICATION_NAME` fixe. Faire tourner deux
stacks dev en même temps = conflit de ports/noms.

➡️ **Règle** : un seul `make docker_dev_up` actif. Pour tester une autre feature,
arrête le stack courant (`make docker_dev_down`) puis démarre-le depuis l'autre
worktree. L'édition/commit de plusieurs branches en parallèle, elle, n'a aucune
limite.

---

## Workflow type (via cibles Make)

```bash
# 1. Créer un worktree pour une nouvelle feature (branche feature/scoring depuis develop)
make wt_new name=scoring

# 2. L'ouvrir dans VS Code (fenêtre séparée)
code ~/Documents/dev/kpi-worktrees/scoring

# 3. Y bosser, committer normalement — c'est une branche à part entière
cd ~/Documents/dev/kpi-worktrees/scoring
# ... edits ...
git add -A && git commit -m "..."

# 4. Tester dans Docker (si besoin) — arrête d'abord tout autre stack
make docker_dev_down     # depuis le worktree/repo qui tournait
make docker_dev_up       # depuis CE worktree

# 5. Pousser + ouvrir la PR vers develop en une commande
make pr_create           # = git push -u origin <branche> && gh pr create --base develop --fill
make pr_checks           # suivre l'état de la CI sur la PR

# 6. Une fois la PR mergée, nettoyer
make wt_rm name=scoring
git branch -d feature/scoring           # depuis le repo principal
```

### Cibles Make (raccourcis)

| Cible | Effet | Commande sous-jacente |
|---|---|---|
| `make wt_new name=<n> [base=<b>]` | crée `feature/<n>` + worktree + fichiers env | `scripts/git-wt.sh new <n> <b>` |
| `make wt_list` | liste les worktrees | `scripts/git-wt.sh list` |
| `make wt_sync name=<n>` | re-copie les fichiers non-versionnés | `scripts/git-wt.sh sync <n>` |
| `make wt_rm name=<n>` | supprime le worktree (garde la branche) | `scripts/git-wt.sh rm <n>` |
| `make pr_push` | push la branche courante en suivi | `git push -u origin <branche>` |
| `make pr_create [base=<b>]` | push + ouvre la PR (base `develop`) | `git push -u …` + `gh pr create --fill` |
| `make pr_web [base=<b>]` | push + ouvre le formulaire PR dans le navigateur | `… + gh pr create --web` |
| `make pr_status` | état de tes PR | `gh pr status` |
| `make pr_checks` | état de la CI sur la PR courante | `gh pr checks` |

Le script `scripts/git-wt.sh` reste utilisable directement (mêmes sous-commandes
`new/list/rm/sync`) ; les cibles Make ne sont que des raccourcis.

---

## Faciliter les PR : GitHub CLI (`gh`)

`gh` n'est pas encore installé sur le poste. C'est lui qui transforme « pousser une
branche » en « PR ouverte » sans quitter le terminal.

```bash
# Installation (Debian/Ubuntu)
sudo apt install gh          # ou : https://cli.github.com

# Auth (une fois)
gh auth login                # choisir GitHub.com + SSH

# Ouvrir une PR depuis un worktree
gh pr create --base develop --fill          # titre/desc depuis les commits
gh pr create --base develop --web           # ouvre le formulaire pré-rempli dans le navigateur
gh pr status                                # état de tes PR
gh pr checks                                # état de la CI (Phase 1) sur la PR courante
```

Rappel workflow du projet : les PR ciblent **`develop`** (intégration). Le passage
`develop → main` se fait par une PR de release séparée. `main` est protégée
(Require PR + linear history) — voir le plan CI/CD.

### Sans `gh`

`git push` affiche un lien « Create a pull request » cliquable, ou va sur
`https://github.com/laurentgarrigue/kpi/pulls` → New pull request.

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
- **Symlinks node_modules** : si deux features ont des `package.json` divergents,
  `sync` puis remplace le symlink par un vrai `npm ci` dans le worktree concerné
  (supprime le lien, `npm ci` local). Tant que les deps sont identiques, le lien
  suffit.
- **`docker/.env`** est copié, pas lié : si tu changes `APPLICATION_NAME` dans un
  worktree pour tenter deux stacks en parallèle, tu sors du cas « un stack à la
  fois » — non couvert ici (ports en dur dans compose.dev.yaml).
