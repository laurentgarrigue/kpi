# Environnement de développement : une seule commande

**Date** : 2026-07-16
**Public** : développeurs du projet KPI

---

## En bref

```bash
make dev          # lance TOUT en détaché (legacy + api2 + app2 + app4) + affiche l'état
make dev_status   # état + URLs, à tout moment
make dev_down     # tout arrêter
```

**Plus besoin d'un terminal par application.** Les serveurs Nuxt démarrent avec leur conteneur.

Les logs se consultent **à la demande** :

```bash
make api2_logs           # api2 (FrankenPHP) : erreurs PHP + Symfony
make api2_logs_errors    # api2 : erreurs uniquement
make app2_logs           # serveur Nuxt app2
make app4_logs           # serveur Nuxt app4
make dev_logs            # tous les containers
```

Toutes acceptent `lines=200` pour remonter plus loin (défaut : 50).

---

## Ce qui a changé

### Avant

`make docker_dev_up`, puis **un terminal par app** (`make app2_dev`, `make app4_dev`…), chacun
bloqué sur un serveur Nuxt au premier plan.

Le conteneur Node tournait en `tail -f /dev/null` : il attendait qu'on vienne y lancer le serveur
via `docker exec`. C'est la commande `npm run dev` qui occupait le terminal, pas le conteneur.

### Après

Les conteneurs `node_app2` et `node_app4` déclarent `command: sh -c "npm run dev"` dans
[compose.dev.yaml](../../../../docker/compose.dev.yaml) : le serveur démarre **avec** le conteneur,
en détaché, et redémarre tout seul (`restart: unless-stopped`).

`make app2_dev` et `make app4_dev` existent toujours : ils affichent maintenant les logs du serveur
déjà lancé. **Ctrl-C quitte les logs, le serveur continue de tourner.**

> **Nuxt met ~15 s à démarrer.** Un 404 juste après `make dev` est normal : relancer
> `make dev_status`.

### Cas particulier : app3

app3 **ne démarre pas** automatiquement : son `npm run dev` échoue sur `dotenv: not found`
(`dotenv-cli` absent de ses `node_modules`). **Problème préexistant**, sans lien avec ce changement.

Pour l'utiliser : `make app3_npm_install`, puis `make app3_dev` (mode terminal classique).

---

## Certificats : fin des exceptions Firefox

### Le problème

Chaque domaine (`kpi.localhost`, `kpi-node4.localhost`…) exigeait d'accepter une exception de
sécurité dans Firefox.

### La cause (deux bugs cumulés)

1. **Le provider `file` n'était pas déclaré** dans `traefik/config/traefik.dev.yaml`. Le fichier
   `config.dev.yaml` — qui déclare les certificats mkcert — était bien monté dans le conteneur,
   mais **Traefik ne le lisait jamais**. Il servait donc son certificat auto-signé
   (`TRAEFIK DEFAULT CERT`).
2. **Le certificat mkcert ne couvrait que les domaines `.local`** (`kpi.local`, `kpi-node.local`…),
   alors que le projet utilise `.localhost`.

La CA mkcert était, elle, **déjà installée** dans le profil Firefox : seule la chaîne côté serveur
était en cause.

### Le correctif

Provider `file` ajouté à `traefik.dev.yaml` (dépôt `traefik`, hors KPI) :

```yaml
providers:
  docker:
    ...
  file:
    filename: /config.yaml
    watch: true
```

Puis régénération du certificat avec les bons domaines :

```bash
make dev_certs
```

Cette cible sauvegarde l'ancien certificat (`.bak`), régénère avec les domaines lus dans
`docker/.env`, **conserve les domaines `.local` existants** et redémarre Traefik.

> ⚠️ `make dev_certs` écrit dans `$(TRAEFIK_CERTS_PATH)` (défaut :
> `/home/laurent/Documents/dev/traefik/certs`) et redémarre Traefik — donc **coupure brève de tous
> les projets** servis par Traefik. Surcharger la variable si le chemin diffère.

**Après un `make dev_certs`, fermer complètement Firefox et le rouvrir.** Si une alerte persiste :
`mkcert -install` puis redémarrer Firefox.

Validé : `kpi.localhost`, `kpi-node.localhost`, `kpi-node4.localhost`, `app3.localhost` et
`kpi-myadmin.localhost` valident tous contre la CA mkcert.

---

## Où sont les logs d'api2 ?

**`docker/apachelogs_8/error.log` ne concerne plus api2** — seulement le legacy Apache.

Depuis le passage à FrankenPHP, api2 loggue sur stdout/stderr (driver `json-file` de Docker),
**identiquement en dev, préprod et production**. Voir
[FRANKENPHP_MIGRATION_ANALYSIS.md §7.10](../../audits/FRANKENPHP_MIGRATION_ANALYSIS.md).

| Besoin | Commande |
|---|---|
| Logs api2 en direct | `make api2_logs` |
| Erreurs api2 uniquement | `make api2_logs_errors` |
| Legacy / WordPress | `docker/apachelogs_8/error.log` (inchangé) |

Les requêtes HTTP réussies **ne sont pas loggées** (`CADDY_ACCESS_LOG_LEVEL=ERROR`) : une seule
requête produit ~20 lignes de JSON. Pour déboguer un routage, passer la variable à `INFO` dans
[compose.dev.yaml](../../../../docker/compose.dev.yaml).

> ⚠️ **Mode worker** : le kernel Symfony reste en mémoire. En dev, la directive `watch` du Caddyfile
> recycle le worker à chaque modification de `src/`. En cas de doute : `make api2_restart`.
