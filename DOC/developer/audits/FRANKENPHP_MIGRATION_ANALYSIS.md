# Analyse : remplacer Apache-PHP par FrankenPHP ?

**Date** : 2026-07-14
**Mise à jour** : 2026-07-16 — corrections §1/§7, ajout §7.8 (Mercure) et §7.9 (préprod)
**Statut** : Validé — implémentation en cours (déclencheur : refonte scoring Mercure)
**Périmètre** : conteneur `kpi` (PHP 8.4 + Apache), API2 (Symfony 7.4 LTS / API Platform 4.3), WordPress, legacy PHP

---

## Résumé exécutif

**Ne pas remplacer Apache globalement. Extraire API2 dans un conteneur FrankenPHP dédié en mode worker.**

Le conteneur `kpi` ne sert pas seulement API2. Il sert aussi :

- ~70 scripts PHP à la racine de `sources/`
- `sources/api/` (API legacy, front controller `.htaccess`)
- `sources/admin/`, `sources/report/`, `sources/live/`
- **WordPress** (monté via `HOST_WORDPRESS_PATH`)

Le tout repose sur des mécanismes **spécifiques à Apache** : `.htaccess`, `auto_prepend_file`,
`php_admin_value` par répertoire, `mod_remoteip`. FrankenPHP s'appuie sur Caddy, qui **ne lit pas
les `.htaccess`**.

Un remplacement global imposerait de réécrire toute la couche de routage et de headers, pour un
gain nul sur le legacy (qui ne peut pas tourner en mode worker de toute façon).

À l'inverse, **API2 est un candidat idéal** au mode worker, et c'est le chemin chaud réel
(app2 et app4 consomment API2). On peut prendre 100 % du gain sans toucher au reste.

---

## 1. État des lieux

### Architecture actuelle du conteneur `kpi`

| Élément | Valeur |
|---|---|
| Image de base | `php:8.4.13-apache-trixie` |
| Serveur | Apache 2 + mod_php |
| Reverse proxy | Traefik (termine le TLS) |
| Modules Apache activés | `headers`, `expires`, `rewrite`, `remoteip` |
| Extensions PHP | `pdo`, `pdo_mysql`, `mbstring`, `mysqli`, `gd`, `zip`, `intl` |

Fichiers de configuration concernés :

- [docker/config/Dockerfile.dev.web](../../../docker/config/Dockerfile.dev.web)
- [docker/config/Dockerfile.prod.web](../../../docker/config/Dockerfile.prod.web)
- [docker/config/000-default.conf](../../../docker/config/000-default.conf) — vhost + CORS sur fichiers statiques
- [docker/config/apache-api2.conf](../../../docker/config/apache-api2.conf) — `Alias /api2`, rewrite Symfony, désactivation de l'auto-prepend
- [docker/config/apache-remoteip.conf](../../../docker/config/apache-remoteip.conf) — vraie IP client derrière Traefik
- [docker/config/php-auto-prepend.ini](../../../docker/config/php-auto-prepend.ini) — CORS global via `auto_prepend_file`

### Fichiers `.htaccess` actifs

| Fichier | Rôle | Portabilité Caddy |
|---|---|---|
| [sources/.htaccess](../../../sources/.htaccess) | Blocklist iThemes Security / HackRepair (user-agents) | Portable, mais fastidieux |
| [sources/api/.htaccess](../../../sources/api/.htaccess) | Front controller + passage du header `Authorization` | Trivial |
| [sources/api2/public/.htaccess](../../../sources/api2/public/.htaccess) | Rewrite Symfony (73 lignes) | Devient inutile sous FrankenPHP |
| `sources/wordpress/.htaccess` | Permaliens WordPress | **Point dur** (voir §4) |

### Pourquoi API2 est un bon candidat au mode worker

Vérifications effectuées sur `sources/api2/` :

- ✅ `symfony/runtime` déjà présent (`7.4.*`) — **nécessaire mais pas suffisant** (voir ci-dessous)
- ✅ `public/index.php` utilise déjà `autoload_runtime.php` et retourne une closure → **aucune
  modification du front controller nécessaire**
- ⚠️ **`runtime/frankenphp-symfony` est requis en plus** pour le mode worker, avec
  `APP_RUNTIME=Runtime\FrankenPhpSymfony\Runtime`. C'est ce package qui fournit la boucle
  `frankenphp_handle_request()` gardant le kernel en mémoire. Sans lui, le bloc `worker` du
  Caddyfile démarre sans erreur mais chaque requête reboote le kernel : **tout le risque, aucun
  gain**. C'est le seul changement `composer.json` de cette migration.
- ✅ **Aucune propriété statique mutable** détectée dans `src/` (`grep` sur `static $`)
- ✅ Session en `storage_factory_id: session.storage.factory.mock_file` (pas d'état de session serveur)
- ✅ Authentification **JWT (Lexik)** → stateless
- ✅ CORS géré par NelmioCorsBundle (indépendant d'Apache)

C'est un profil « propre » : peu de risques de fuite d'état entre requêtes.

---

## 2. Avantages

### Performances sur API2 (le gain réel)

Le mode worker garde le kernel Symfony **en mémoire entre les requêtes**, au lieu de rebooter
l'ensemble (autoload, compilation du container DI, mapping Doctrine, routing API Platform) à chaque
appel HTTP.

Ordre de grandeur attendu sur une API Platform + Doctrine : **×2 à ×4 en débit**, avec une latence
nettement plus stable (disparition du coût de bootstrap, typiquement 20–60 ms par requête).

C'est exactement le chemin chaud : app2 et app4 tapent sur `https://kpi.localhost/api2`.

### HTTP/2, HTTP/3 et TLS automatiques

Natif dans Caddy. **Bénéfice partiel dans notre cas** : Traefik termine déjà le TLS et parle HTTP/2
côté client. Le gain se limite au lien interne Traefik ↔ PHP.

### Image plus simple

Un binaire unique. Plus de cascade de `a2enmod`, plus de `rm -f /var/run/apache2/apache2.pid`.

### Mercure / SSE intégrés

FrankenPHP embarque un **hub Mercure natif** (une directive du Caddyfile, pas un service à déployer).

Ce n'est plus une simple optionnalité : la **refonte du scoring live** en fait un besoin concret.
Son plan ([LIVE_MATCH_SCORING_REFACTORING_PROPOSALS.md](../reference/LIVE_MATCH_SCORING_REFACTORING_PROPOSALS.md),
§4.6) prévoit de diffuser l'état des matchs via Mercure. Sous Apache, il faudrait un conteneur
`dunglas/mercure` séparé ; en extrayant api2 sous FrankenPHP, le hub vit **dans le même conteneur que
l'API**. La migration décrite ici est donc un **prérequis souhaitable** de l'étape 2 de cette refonte
— sans en être un bloquant (voir le §4.6 du plan pour la trajectoire de convergence).

---

## 3. Impacts (le travail réel)

### Le point dur : `.htaccess`

Caddy ne les lit pas. Il faudrait traduire chaque fichier en Caddyfile.

**WordPress est le cas critique** : les plugins WP réécrivent leur propre `.htaccess`
automatiquement (permaliens, sécurité). Sous Caddy, ces réécritures deviennent **muettes** : le
fichier est modifié, mais plus personne ne le lit. Aucune erreur n'est levée — le comportement
change silencieusement.

### `auto_prepend_file` + `php_admin_value`

[apache-api2.conf](../../../docker/config/apache-api2.conf) désactive l'auto-prepend CORS
**spécifiquement pour `/api2`** (`php_admin_value auto_prepend_file none`), pour éviter les doublons
de headers avec NelmioCors.

Caddy **n'a pas d'équivalent de `php_admin_value` par répertoire**. Il faudrait soit deux
pools/conteneurs distincts, soit conditionner le prepend dans le PHP lui-même.

→ **Un conteneur séparé pour API2 règle ce problème nativement.**

### `mod_remoteip`

À remplacer par `trusted_proxies` dans Caddy. Simple, mais critique : sans cela, on perd les vraies
IP client dans les logs et dans toute logique dépendant de `REMOTE_ADDR`.

### Logs — changement d'habitude (voir §7.10)

Le compose monte `${HOST_APACHE2_LOG_PATH}:/var/log/apache2/`. Caddy loggue en **JSON sur stdout**.

**Conséquence concrète** : `docker/apachelogs_8/error.log` ne contiendra **plus rien concernant
api2**. Ce fichier reste celui du legacy Apache uniquement. Les logs d'api2 se consultent via
`make api2_logs`. Voir §7.10 pour le détail.

---

## 4. Risques

### WordPress en mode worker : à proscrire

WordPress s'appuie massivement sur des variables globales et de l'état statique. En mode worker,
cet état **fuit entre les requêtes**. On peut le faire tourner en mode classique sous FrankenPHP,
mais on cumule alors les inconvénients (perte des `.htaccess`) **sans le gain** (pas de worker).

### Le legacy en mode worker : à proscrire aussi

Les ~70 scripts racine et `sources/commun/` sont écrits pour le modèle « une requête = un process
qui meurt » : globales, `$_SESSION`, connexions PDO jamais fermées. En worker, tout cela fuit ou se
corrompt entre requêtes.

Le legacy resterait donc en **mode classique** → **zéro gain de performance**, uniquement du risque
de régression.

### Fuite mémoire côté worker Symfony

Même API2 doit être surveillé : entités accumulées dans l'`EntityManager`, listeners gardant des
références. Cela se gère (`max_requests`, `EntityManager::clear()`, `--watch` désactivé en prod),
mais c'est **une classe de bug qui n'existe pas aujourd'hui**.

### Régression silencieuse

Une règle de rewrite oubliée ne plante pas : elle renvoie un 404 ou sert le mauvais fichier. Avec
4 `.htaccess` actifs + WordPress, la surface de « ça marchait avant » est large.

---

## 5. Inconvénients

- **Deux serveurs web à maîtriser** au lieu d'un. Le Caddyfile est une syntaxe de plus, là où toute
  la doc projet parle Apache.
- **Écosystème plus jeune** : moins de réponses toutes faites quand ça coince en production.
- **Déploiement plus délicat** : FrankenPHP en worker exige un **reload explicite** après déploiement
  de code. Apache/mod_php reprend le nouveau code dès l'opcache invalidé.

---

## 6. Recommandation

**Extraire API2 dans un conteneur FrankenPHP dédié en mode worker. Laisser Apache servir le legacy
et WordPress, inchangé.**

| Critère | Remplacement global | **Extraction API2 (recommandé)** |
|---|---|---|
| Gain de perf | Nul sur legacy/WP | ×2–4 sur le chemin chaud |
| `.htaccess` à réécrire | 4 (dont WordPress) | **0** |
| Risque WordPress | Élevé | **Nul** |
| Risque legacy | Élevé | **Nul** |
| Rollback | Redéploiement complet | **Un label Traefik** |
| Effort | Semaines | **~1 journée** (voir note) |

> **Note effort (rev. 2026-07-16)** : l'estimation initiale « une demi-journée » supposait qu'aucun
> changement `composer.json` n'était nécessaire. C'est faux : le mode worker exige
> `runtime/frankenphp-symfony` (cf. §1), donc une régénération du `composer.lock`. Compter une
> journée, dont une part de validation dev + préprod.

Étapes :

1. Nouveau service `api2` dans le compose (image FrankenPHP, mode worker, `sources/api2/` seul).
2. Label Traefik `PathPrefix('/api2')` vers ce conteneur, **priorité supérieure** à la règle `kpi`.
3. Retirer l'`Alias /api2` de l'Apache existant (`a2disconf apache-api2`).
4. Apache continue de servir le legacy et WordPress, sans aucune modification.

**Les URLs frontend ne changent pas** : app4 pointe sur `https://kpi.localhost/api2`
([sources/app4/nuxt.config.ts:4](../../../sources/app4/nuxt.config.ts#L4)). Le routage est
entièrement absorbé par Traefik.

Le legacy pourra ensuite migrer progressivement vers FrankenPHP — ou jamais, ce qui est un choix
parfaitement défendable.

---

## 7. Préparation : service Compose + Caddyfile

> ⚠️ Fichiers **proposés**, non créés. À valider avant implémentation.

### 7.1 `docker/config/Caddyfile.api2`

```caddyfile
{
	# Traefik termine déjà le TLS : FrankenPHP écoute en HTTP simple sur le réseau interne.
	auto_https off

	# Vraie IP client derrière Traefik (équivalent de mod_remoteip).
	servers {
		trusted_proxies static 172.16.0.0/12 127.0.0.1 ::1
	}

	frankenphp {
		# Mode worker : le kernel Symfony reste en mémoire entre les requêtes.
		worker {
			file /app/public/index.php
			# Nombre de workers. Démarrer bas, ajuster selon la charge.
			num 4
			# Redémarre le worker après N requêtes : garde-fou anti-fuite mémoire.
			max_requests 500
		}
	}
}

:80 {
	root * /app/public

	# Le worker Symfony gère tout ce qui n'est pas un fichier statique.
	php_server

	# Logs JSON sur stdout (récupérés par le driver json-file de Docker).
	log {
		output stdout
		format json
	}
}
```

**Notes**

- Pas de `auto_prepend_file` ici : le conteneur est isolé, donc le problème du
  `php_admin_value auto_prepend_file none` d'Apache **disparaît par construction**.
- CORS reste géré par **NelmioCorsBundle** côté Symfony — rien à faire dans Caddy.
- `max_requests 500` est un garde-fou : à retirer une fois la stabilité mémoire confirmée en
  production (surveiller la RSS du conteneur).

### 7.2 `docker/config/Dockerfile.api2`

```dockerfile
FROM dunglas/frankenphp:php8.4

ARG USER_ID
ARG GROUP_ID

# Extensions requises par API2 (Doctrine + API Platform + intl).
# gd/zip ne sont pas nécessaires ici : mPDF/OpenSpout restent côté legacy Apache.
RUN apt-get update && DEBIAN_FRONTEND=noninteractive apt-get install -y \
        libicu-dev \
        libzip-dev \
        unzip \
    && docker-php-ext-install pdo pdo_mysql intl zip \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Opcache : indispensable en mode worker.
RUN { \
        echo "opcache.enable=1"; \
        echo "opcache.memory_consumption=256"; \
        echo "opcache.max_accelerated_files=20000"; \
        echo "opcache.validate_timestamps=0"; \
    } > /usr/local/etc/php/conf.d/opcache-worker.ini

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

COPY Caddyfile.api2 /etc/caddy/Caddyfile

WORKDIR /app
```

> ⚠️ `opcache.validate_timestamps=0` est correct **en production uniquement**. En développement,
> mettre `=1` (voir la variante dev en §7.3), sinon les modifications de code ne sont jamais prises
> en compte.

### 7.3 Service Compose — développement

À ajouter dans [docker/compose.dev.yaml](../../../docker/compose.dev.yaml) :

```yaml
    api2:
        container_name: ${APPLICATION_NAME}_api2
        build:
            context: ./config
            dockerfile: Dockerfile.api2
            args:
                USER_ID: ${USER_ID}
                GROUP_ID: ${GROUP_ID}
        user: ${USER_ID}:${GROUP_ID}
        environment:
            - TZ=Europe/Paris
            - LANG=fr_FR.UTF-8
            - LC_ALL=fr_FR.UTF-8
            - APP_ENV=dev
            # Active le mode worker (cf. §1 : exige runtime/frankenphp-symfony).
            - APP_RUNTIME=Runtime\FrankenPhpSymfony\Runtime
        volumes:
            - ../sources/api2:/app
            # ⚠️ INDISPENSABLE — voir §7.7.
            # EventCacheService et AdminTvController écrivent/lisent sous
            # /var/www/html/live/cache (live_document_root). Sans ce montage,
            # AdminGamesController et AdminTvController cassent en HTTP.
            #
            # C'est le SEUL montage legacy nécessaire : api2 ne référence ni
            # MyParams.php, ni MyConfig.php, ni DOC/ (vérifié par grep sur src/,
            # config/, public/, bin/). Ne pas recopier les montages du service kpi.
            - ../sources:/var/www/html
        networks:
            - network_kpi
            - traefiknetwork
        depends_on:
            - db
        logging:
            driver: "json-file"
            options:
                max-size: 10m
                max-file: 3
        restart: unless-stopped
        labels:
            - "traefik.enable=true"
            - "traefik.http.routers.api2.rule=Host(`${KPI_DOMAIN_NAME}`) && PathPrefix(`/api2`)"
            - "traefik.http.routers.api2.entrypoints=websecure"
            - "traefik.http.routers.api2.tls=true"
            # Priorité > routeur `kpi` : sans cela, Host(...) seul pourrait capter /api2.
            - "traefik.http.routers.api2.priority=100"
            # Symfony est monté sur /app/public et route depuis la racine :
            # on retire le préfixe /api2 avant de transmettre.
            - "traefik.http.routers.api2.middlewares=api2-stripprefix"
            - "traefik.http.middlewares.api2-stripprefix.stripprefix.prefixes=/api2"
            - "traefik.http.services.api2.loadbalancer.server.port=80"
```

**En développement**, garder `validate_timestamps=1` et **ne pas activer le worker** : le code est
monté en volume et change en permanence. Le gain de perf n'a d'intérêt qu'en préprod/production.

### 7.4 Service Compose — production

À ajouter dans [docker/compose.prod.yaml](../../../docker/compose.prod.yaml) — identique, sauf :

```yaml
    api2:
        container_name: ${APPLICATION_NAME}_api2
        build:
            context: ./config
            dockerfile: Dockerfile.api2
        environment:
            - TZ=Europe/Paris
            - APP_ENV=prod
            - APP_DEBUG=0
        volumes:
            - ../sources/api2:/app
            # ⚠️ INDISPENSABLE — voir §7.7 (cache live partagé avec Apache).
            - ../sources:/var/www/html
        networks:
            - network_kpi
            - traefiknetwork
        depends_on:
            - db
        restart: unless-stopped
        labels:
            - "traefik.enable=true"
            - "traefik.http.routers.api2.rule=(Host(`${KPI_DOMAIN_NAME}`) || Host(`www.${KPI_DOMAIN_NAME}`)) && PathPrefix(`/api2`)"
            - "traefik.http.routers.api2.entrypoints=websecure"
            - "traefik.http.routers.api2.tls=true"
            - "traefik.http.routers.api2.tls.certresolver=myresolver"
            - "traefik.http.routers.api2.priority=100"
            - "traefik.http.routers.api2.middlewares=api2-stripprefix"
            - "traefik.http.middlewares.api2-stripprefix.stripprefix.prefixes=/api2"
            - "traefik.http.services.api2.loadbalancer.server.port=80"
```

### 7.5 Désactiver `/api2` côté Apache

Une fois le routage Traefik validé, retirer l'alias Apache pour éviter que les deux se marchent
dessus.

Dans [Dockerfile.prod.web](../../../docker/config/Dockerfile.prod.web) et
[Dockerfile.dev.web](../../../docker/config/Dockerfile.dev.web), supprimer :

```dockerfile
COPY apache-api2.conf /etc/apache2/conf-available/
RUN chmod 644 /etc/apache2/conf-available/apache-api2.conf && a2enconf apache-api2
```

> Conserver le fichier `apache-api2.conf` sur disque : il permet un rollback immédiat.

### 7.6 Point de vigilance : `DEFAULT_URI` et `API_DOCS_SERVER_URL`

Avec le `stripprefix`, Symfony reçoit les requêtes **sans** le préfixe `/api2`. Les URLs générées
(API Platform, documentation OpenAPI, liens hypermedia) doivent donc être forcées.

**⚠️ Constat au 2026-07-16 : le piège est déjà refermé.** `sources/api2/.env` contient
aujourd'hui :

```dotenv
DEFAULT_URI=http://kpi.localhost      # ❌ pas de /api2, et HTTP au lieu de HTTPS
API_DOCS_SERVER_URL='https://kpi.localhost/api2'   # ✅ correct
```

`.env.dist` est encore plus faux (`DEFAULT_URI=http://localhost`).

Aujourd'hui sous Apache, cette valeur est sans conséquence visible : l'`Alias /api2` fait que
Symfony reçoit les requêtes **avec** leur préfixe, et `DEFAULT_URI` ne sert qu'aux URLs générées
hors contexte HTTP (CLI, mailer). **Avec le `stripprefix`, elle devient structurante.**

Valeur cible en dev :

```dotenv
DEFAULT_URI=https://kpi.localhost/api2
API_DOCS_SERVER_URL='https://kpi.localhost/api2'
```

**C'est le piège le plus probable de cette migration** : la doc OpenAPI et les liens IRI générés
par API Platform pointeront vers la racine (`/games` au lieu de `/api2/games`) si `DEFAULT_URI`
n'est pas correct. À adapter par environnement (préprod/prod : le domaine réel).

**Confirmé en préprod le 2026-07-17** — le piège s'est bien refermé, exactement comme prévu ici.

#### Comment le reconnaître : les headers de réponse

`/api2/doc` répond **200**, mais tous ses liens sont en 404. La cause est lisible dans les headers :

```bash
curl -sI https://<domaine>/api2/doc
# content-location: /api/.well-known/genid/...      ← /api au lieu de /api2 → Apache → 404
# link: <http://<domaine>/api/docs.jsonld>; ...     ← et http au lieu de https
```

Un préfixe `/api` (au lieu de `/api2`) dans `content-location` ou `link` **est** la signature d'un
`DEFAULT_URI` faux. Après correction, ces headers doivent porter `/api2` **et** `https`.

#### ⚠️ `DEFAULT_URI` ne couvre pas les assets

`DEFAULT_URI` ne pilote que le **routeur**. Le composant `asset` a un réglage **distinct** : sans
lui, la page Swagger demande son CSS/JS à la racine (`/bundles/...`) → Apache → 404, et s'affiche
nue alors que l'API fonctionne. D'où, dans
[framework.yaml](../../../sources/api2/config/packages/framework.yaml) :

```yaml
framework:
    assets:
        base_path: '/api2'
```

Vrai dans **tous** les environnements (l'URL publique est toujours `/api2`), donc non conditionné.

> Après avoir modifié ces réglages, **recycler le worker** : `config/` n'est pas surveillé par le
> `watch` (cf. §7.8d), la nouvelle valeur reste sans effet jusqu'à `make api2_cache_clear`.
> Vérifier ce que le worker a réellement chargé :
> `docker exec ${APPLICATION_NAME}_api2 php bin/console debug:config framework assets`

*Alternative* : ne pas utiliser `stripprefix`, et configurer un
`Alias`/`base path` côté Symfony. Le `stripprefix` reste plus simple si `DEFAULT_URI` est bien posé.

---

## 7.7 Le worker FrankenPHP et le `event-cache-worker` : aucun conflit, mais un piège de volume

### Deux « workers » sans rapport

Le mot est le même, la chose est différente :

| | Worker FrankenPHP | `event-cache-worker` |
|---|---|---|
| Nature | Mode de service **HTTP** | Démon **console** (CLI) |
| Rôle | Garde le kernel Symfony en mémoire entre les requêtes web | Boucle `while` + `sleep()` qui régénère les JSON de cache live |
| Conteneur | `kpi_api2` | `${APPLICATION_NAME}_event_cache_worker` (déjà séparé) |
| Passe par Caddy / Apache | Oui | **Non** — jamais de requête HTTP |
| Point d'entrée | `public/index.php` | `bin/console app:event-cache-worker` |

Ils **ne partagent ni processus, ni mémoire, ni port**. Le démon tourne déjà aujourd'hui dans son
propre conteneur, avec son propre process PHP CLI — l'introduction de FrankenPHP ne le touche pas.

### Pas de problème de performance ni de concurrence

- Le démon **dort** entre les passes : `sleep($delay)` avec `$delay` ≥ 1 s, et 10 s dès qu'aucune
  config n'est active ([EventCacheWorkerCommand.php:60-77](../../../sources/api2/src/Command/EventCacheWorkerCommand.php#L60-L77)).
  Il ne sature rien.
- Son état vit **en base** (`kp_event_worker_config`), pas en mémoire partagée : le heartbeat est un
  simple `UPDATE`, et les échecs de heartbeat sont volontairement avalés pour ne pas tuer la boucle.
- Il utilise `pcntl_signal` (SIGTERM/SIGINT) pour l'arrêt gracieux — du CLI pur, sans aucune
  interaction avec le serveur web.

Le seul terrain partagé est la **base de données** et le **système de fichiers** — exactement comme
aujourd'hui sous Apache. **Rien ne change.**

### ⚠️ Le piège réel : `live_document_root`

C'est **le point de vigilance le plus important de cette migration**, avec §7.6.

[sources/api2/config/services.yaml:7](../../../sources/api2/config/services.yaml#L7) définit :

```yaml
live_document_root: '/var/www/html'
```

Et [EventCacheService.php:40](../../../sources/api2/src/Service/EventCacheService.php#L40) en dérive :

```php
$this->cacheDir = rtrim($liveDocumentRoot, '/') . '/live/cache';
```

Les JSON de cache sont donc écrits dans **`/var/www/html/live/cache/`**, c'est-à-dire dans
l'arborescence **legacy servie par Apache** (`sources/live/cache/`) — et non dans `sources/api2/`.
C'est voulu : ces fichiers sont consommés en statique par les overlays TV / scoreboard.

**Or `live_document_root` n'est pas utilisé que par le démon.** Il est injecté dans deux services
HTTP :

- `App\Service\EventCacheService` → consommé par **`AdminGamesController`** (HTTP)
- `App\Controller\AdminTvController` → lit `$this->liveDocumentRoot . '/live/cache'`
  ([AdminTvController.php:387](../../../sources/api2/src/Controller/AdminTvController.php#L387))

**Conséquence** : un conteneur FrankenPHP qui ne monterait que `../sources/api2:/app` n'aurait
**aucun `/var/www/html`**. `AdminGamesController` et `AdminTvController` échoueraient en écriture /
lecture dès qu'ils touchent le cache live.

### La correction (déjà appliquée en §7.3 et §7.4)

Monter **aussi** l'arborescence legacy dans le conteneur FrankenPHP :

```yaml
volumes:
    - ../sources/api2:/app
    - ../sources:/var/www/html   # ⚠️ requis : cache live partagé avec Apache
```

Les trois conteneurs (`kpi` Apache, `api2` FrankenPHP, `event-cache-worker`) voient alors le **même**
`sources/live/cache/` sur l'hôte. C'est exactement la topologie actuelle — on ne fait que la
préserver.

*Alternative plus propre (hors périmètre)* : rendre `live_document_root` configurable par variable
d'environnement, ou déplacer le cache dans un volume Docker nommé partagé. À envisager si l'on veut
à terme découpler complètement API2 du legacy.

### Concurrence d'écriture sur les JSON

Le démon **et** `AdminGamesController` peuvent écrire les mêmes fichiers de cache. Ce risque
**existe déjà aujourd'hui** (Apache + démon) et n'est ni créé ni aggravé par FrankenPHP.

À noter tout de même : le mode worker augmente le **débit** de l'API, donc mécaniquement la
fréquence potentielle d'écritures concurrentes. Si des JSON tronqués apparaissaient, la parade est
une écriture atomique (`file_put_contents` dans un fichier temporaire + `rename()`), à ajouter dans
`EventCacheService::writeCacheFile()`. **Pas un prérequis** — juste à garder en tête.

---

## 7.7bis Hub Mercure natif (le déclencheur)

Le hub est activé dans [Caddyfile.api2](../../../docker/config/Caddyfile.api2) — **aucun conteneur
`dunglas/mercure` à déployer** :

```caddyfile
mercure {
    publisher_jwt {$MERCURE_PUBLISHER_JWT_KEY}
    subscriber_jwt {$MERCURE_SUBSCRIBER_JWT_KEY}
    cors_origins {$MERCURE_CORS_ORIGINS}
    anonymous {$MERCURE_ANONYMOUS:0}
}
```

Le hub est exposé sur **`/.well-known/mercure`**, donc — préfixe Traefik compris —
**`https://kpi.localhost/api2/.well-known/mercure`** côté client. Validé : HTTP 200 (flux SSE).

| Variable (docker/.env) | Dev | Préprod / Prod |
|---|---|---|
| `MERCURE_JWT_SECRET` | valeur de dev | **à régénérer** : `openssl rand -hex 32` |
| `MERCURE_CORS_ORIGINS` | `*` | `https://${KPI_DOMAIN_NAME}` |
| `MERCURE_ANONYMOUS` | `1` | `0` (abonnements authentifiés) |

> ⚠️ `MERCURE_JWT_SECRET` est un **secret** : `docker/.env` n'est pas versionné, mais `.env.dist`
> contient un placeholder à remplacer impérativement en préprod et en production.

**Côté Symfony** : `symfony/mercure-bundle` (v0.4.2) est désormais installé, avec `MERCURE_URL` /
`MERCURE_PUBLIC_URL` / `MERCURE_JWT_SECRET` passés au conteneur par
[compose.dev.yaml](../../../docker/compose.dev.yaml). Un **banc de test** (profil 1, onglet Mercure
de la page Opérations d'app4 → `AdminMercureController`) valide la chaîne publish/subscribe de bout
en bout. La logique métier de diffusion reste le périmètre de la refonte scoring
(cf. [LIVE_MATCH_SCORING_REFACTORING_PROPOSALS.md](../reference/LIVE_MATCH_SCORING_REFACTORING_PROPOSALS.md) §4.6).

Trois points à connaître :

| Variable | Valeur dev | Pourquoi |
|---|---|---|
| `MERCURE_URL` | `http://localhost/.well-known/mercure` | Publication **interne au conteneur** : évite Traefik et son certificat auto-signé. |
| `MERCURE_PUBLIC_URL` | `https://kpi.localhost/api2/.well-known/mercure` | URL d'**abonnement navigateur** : passe par Traefik, donc préfixe `/api2` inclus. |
| `MERCURE_JWT_SECRET` | = `MERCURE_JWT_SECRET` de `docker/.env` | **Doit être identique** au secret du hub, sinon les JWT publisher sont rejetés (403). |

> ⚠️ **La recette Flex de `symfony/mercure-bundle` ajoute un service `dunglas/mercure` dans
> `sources/api2/compose.yaml`** — exactement ce que cette migration évite. Ces fichiers ne sont pas
> utilisés par le projet (qui tourne sur `docker/compose.*.yaml`) : les modifications de la recette
> ont été **annulées**. Ne pas les réintroduire.

> ⚠️ `EventSource` ne peut pas envoyer de header `Authorization`. En dev, l'abonnement navigateur
> repose donc sur `MERCURE_ANONYMOUS=1`. En préprod/prod (`MERCURE_ANONYMOUS=0`), il faudra un JWT
> subscriber passé en cookie ou en query string — **à traiter dans la refonte scoring**.

---

## 7.8 Pièges rencontrés à l'implémentation (2026-07-16)

Trois écarts entre les fichiers proposés au §7 et la réalité. **Les trois échouent silencieusement** :
le conteneur démarre, l'API répond, et seul le gain de perf manque.

### a. Chemin du Caddyfile

L'image `dunglas/frankenphp` lit **`/etc/frankenphp/Caddyfile`**, pas `/etc/caddy/Caddyfile` (le
§7.2 indiquait ce dernier). Copier au mauvais endroit ne lève **aucune erreur** : le Caddyfile par
défaut de l'image prend le relais, `auto_https` se réactive et le worker ne démarre jamais.

**Symptôme** : `docker logs kpi_api2 | grep "using config from file"` pointe sur
`/etc/frankenphp/Caddyfile` alors qu'on croit avoir fourni le sien.

### b. `max_requests` n'existe pas comme sous-directive `worker`

FrankenPHP 1.x refuse `max_requests` dans le bloc `worker` (là, au moins, l'erreur est explicite et
le conteneur redémarre en boucle). Directives valides : `name`, `file`, `num`, `env`, `watch`,
`match`, `max_consecutive_failures`, `max_threads`.

Le garde-fou anti-fuite mémoire passe par la **variable d'env `MAX_REQUESTS`**, lue par
`runtime/frankenphp-symfony` :

```caddyfile
worker {
    file /app/public/index.php
    num {$FRANKENPHP_NUM_WORKERS:4}
    env MAX_REQUESTS {$FRANKENPHP_MAX_REQUESTS:500}
    watch {$FRANKENPHP_WATCH:/app/src}
    max_consecutive_failures 3
}
```

> `max_requests: 0` dans la ligne de log `FrankenPHP started 🐘` est un **compteur global**, sans
> rapport avec le worker. Ne pas s'y fier pour diagnostiquer. La source de vérité est l'API admin
> de Caddy : `curl -s http://localhost:2019/config/ | grep workers`.

### c. `setcap` pour le port 80

Le conteneur tourne en `user: ${USER_ID}` (non-root) mais Caddy doit binder le port 80, privilégié.
Sans `setcap CAP_NET_BIND_SERVICE=+eip /usr/local/bin/frankenphp` dans le Dockerfile, le conteneur
démarre puis meurt sur un « permission denied » au bind.

### d. `watch` remplace le compromis opcache en dev

En mode worker, `opcache.validate_timestamps=1` **ne suffit pas** : le kernel reste en mémoire, donc
une modification de code n'est pas vue. La directive `watch /app/src` recycle le worker à chaque
changement — c'est ce qui rend le mode worker utilisable en dev.

#### ⚠️ Limite : le `watch` ne couvre que `/app/src`

**Une modification dans `config/` ne recycle pas le worker, même en dev.** La valeur par défaut de
`FRANKENPHP_WATCH` est `/app/src` (cf. Caddyfile.api2) : tout ce qui est hors de `src/` est invisible
pour la surveillance. Concrètement, éditer `config/packages/*.yaml` ne produit **aucun effet** tant
que le worker n'est pas recyclé à la main :

```bash
make api2_cache_clear     # config/routes modifiés → vide, préchauffe et recycle
make api2_restart         # code hors src/ → recycle seulement
```

C'est déroutant parce que le symptôme n'est pas une erreur : l'ancienne config continue simplement
de s'appliquer, silencieusement. Cas vécu (2026-07-17) : l'ajout de `framework.assets.base_path`
n'a rien changé jusqu'au `api2_restart`, ce qui a fait croire à tort que le réglage était faux.

> **Vérifier plutôt que deviner** : `docker exec ${APPLICATION_NAME}_api2 php bin/console debug:config framework assets`
> affiche la config **réellement chargée par le worker**. Si elle ne correspond pas au fichier sur le
> disque, c'est un problème de recyclage, pas de configuration.

Le `watch` n'est volontairement pas étendu à `/app/config` : la portée restreinte évite des
recyclages intempestifs, et la règle « config modifiée → `make api2_cache_clear` » est de toute façon
celle qui s'applique en préprod et en production.

En préprod/prod, `FRANKENPHP_WATCH=` (vide) désactive la surveillance : le code est figé, et
**`make api2_restart` devient obligatoire après tout déploiement** (cf. §5, « déploiement plus
délicat »).

---

## 7.9 Découverte connexe : dérive Symfony 8 → retour en 7.4 LTS

**Trouvé en tentant d'installer le worker, sans rapport direct avec FrankenPHP.**

`composer require runtime/frankenphp-symfony` a échoué : le package exige
`symfony/http-kernel ^7.0`, or le lock imposait **v8.0.14**.

Diagnostic : le `composer.json` déclarait `7.4.*` partout, mais **18 paquets Symfony étaient
verrouillés en 8.x** dans le `composer.lock` — dont `symfony/runtime` en v8.0.13 pour une contrainte
`7.4.*`. `composer.json` et `composer.lock` étaient **désynchronisés** : le lock faisant foi à
l'`install`, préprod et production tournaient déjà sur des composants Symfony 8 non voulus.

**Symfony 8.0 n'est pas souhaitée pour ce projet : la cible est 7.4, qui est la LTS.**

Correction appliquée (`composer update` complet dans `kpi_php`) :

- ✅ 17 paquets Symfony redescendus en v7.4.x, **plus aucun paquet Symfony en 8.x**
- ✅ `symfony/runtime` v8.0.13 → **v7.4.14**, `http-kernel` v8.0.14 → **v7.4.14**
- ✅ `runtime/frankenphp-symfony` 1.0.0 installé → **worker débloqué**

Vérifications post-downgrade : `cache:clear` OK, `lint:container` OK, **278 routes**, mapping
Doctrine OK, `app:event-cache-worker` démarre. Conforme à l'attendu — 7.4 et 8.0 partagent le même
code, 8.0 ne faisant que retirer ce que 7.4 déprécie.

> **À vérifier au déploiement** : préprod et prod doivent faire un `composer install` (le lock a
> changé) pour redescendre en 7.4. C'est un changement **indépendant de FrankenPHP**, qui aurait dû
> être fait de toute façon.

---

## 7.10 Logs et secrets : ce qui change au quotidien

### Où lire les logs d'api2 ?

**L'habitude `docker/apachelogs_8/error.log` ne s'applique plus à api2.** Ce fichier reste celui du
legacy Apache. Sous FrankenPHP, tout part sur stdout/stderr, récupéré par le driver `json-file` de
Docker — **identique en dev, préprod et production**, ce qui supprime l'écart entre environnements.

| Besoin | Commande |
|---|---|
| Suivre les logs d'api2 en direct | `make api2_logs` (ou `make api2_logs lines=500`) |
| Ne voir que les erreurs | `make api2_logs_errors` |
| Legacy / WordPress (inchangé) | `docker/apachelogs_8/error.log` |

Ce qui remonte dans `make api2_logs` :

- **Erreurs PHP** (fatales, warnings) — via `php-error-logging-api2.ini` → `/dev/stderr`
- **Logs applicatifs Symfony** — Monolog était **déjà** configuré sur `php://stderr`
- **Erreurs Caddy** (routage, TLS, worker)
- **Requêtes HTTP en échec** uniquement (cf. ci-dessous)

Validé : une exception levée pendant une requête produit bien une ligne `"level":"error"` avec
message et stack trace.

### ⚠️ Piège : `log_errors=Off` par défaut

L'image FrankenPHP a `log_errors=Off` et `error_log` vide. `php-error-logging.ini` n'est copié que
dans les images **Apache** — sans un équivalent dédié, **une fatale PHP dans api2 ne serait loggée
nulle part**. D'où [php-error-logging-api2.ini](../../../docker/config/php-error-logging-api2.ini).

Différence assumée avec Apache : `display_errors=Off` (Apache : `On`). api2 renvoie du JSON — une
erreur PHP imprimée dedans casserait le parsing côté app2/app4 et fuiterait des chemins serveur.
Symfony sérialise déjà les exceptions proprement.

### Volume des logs d'accès

Les logs d'accès Caddy sont en JSON : **une requête réussie = ~20 lignes de JSON**. Illisible au
quotidien.

`CADDY_ACCESS_LOG_LEVEL` (défaut : `ERROR`) ne loggue donc que les requêtes en échec. Les erreurs
PHP et applicatives remontent indépendamment de ce réglage.

Pour déboguer un problème de routage, passer temporairement à `INFO` (toutes les requêtes, avec
headers `X-Forwarded-*` — utile pour vérifier le `stripprefix`) :

```yaml
- CADDY_ACCESS_LOG_LEVEL=INFO
```

### Secret Mercure

```bash
make mercure_generate_secret   # affiche une ligne MERCURE_JWT_SECRET=<32 octets hex>
```

La commande **n'écrit pas** dans `docker/.env` : elle affiche la ligne à copier. Modifier `.env`
automatiquement serait risqué (fichier non versionné, propre à chaque serveur).

**Un secret différent par environnement.** `docker/.env.dist` ne contient qu'un placeholder.

---

## 8. Plan de validation

Avant bascule, vérifier :

1. **Routage** — `GET https://kpi.localhost/api2/doc` sert bien la doc API Platform depuis le
   nouveau conteneur (`docker logs kpi_api2` doit montrer la requête).
2. **Le legacy est intact** — `https://kpi.localhost/` et WordPress répondent toujours via Apache.
3. **CORS** — app2 et app4 (origines `*.localhost`) n'ont pas d'erreur console. Vérifier
   l'**absence de headers `Access-Control-Allow-Origin` en double** (c'était précisément le rôle du
   `php_admin_value auto_prepend_file none`).
4. **JWT** — le header `Authorization` arrive bien jusqu'à Symfony (Caddy le transmet nativement,
   contrairement à Apache qui exigeait la règle `E=HTTP_AUTHORIZATION`).
5. **IRIs / OpenAPI** — les URLs générées contiennent bien le préfixe `/api2` (cf. §7.6).
6. **Vraie IP client** — présente dans les logs JSON (`trusted_proxies`).
7. **Mémoire en mode worker** — surveiller `docker stats kpi_api2` sous charge : la RSS doit se
   stabiliser, pas croître linéairement.
8. **Cache live accessible depuis FrankenPHP** (cf. §7.7) —
   `docker exec kpi_api2 ls /var/www/html/live/cache` doit lister les JSON. Si le dossier n'existe
   pas, le montage `../sources:/var/www/html` manque.
9. **Contrôleurs TV / Games** — appeler les endpoints d'`AdminTvController` et `AdminGamesController`
   qui touchent le cache : ils doivent lire et écrire sans erreur.
10. **`event-cache-worker` intact** — `docker logs ${APPLICATION_NAME}_event_cache_worker` continue
    d'émettre ses passes, et `kp_event_worker_config.last_execution` avance. Le démon ne doit être
    affecté d'aucune manière.

**Rollback** : remettre `a2enconf apache-api2` et supprimer le service `api2` du compose. Les URLs
frontend étant inchangées, le retour arrière est transparent pour app2/app4.

---

## 8bis. Procédure de déploiement (préprod et production)

Même procédure pour les deux environnements : seul le suffixe des cibles `make` change
(`docker_preprod_*` / `docker_prod_*`) et, bien sûr, les valeurs mises dans les `.env`.

> **L'essentiel du travail est dans les deux fichiers `.env`, qui ne sont pas versionnés.** Le code
> arrive par `git pull`, mais `docker/.env` et `sources/api2/.env` sont propres à chaque serveur :
> personne ne les met à jour à ta place, et c'est là que se logent les erreurs (cf. §8ter).

### Étape 1 — Récupérer le code

```bash
git pull    # sur la branche déployée sur ce serveur
```

### Étape 2 — Compléter `docker/.env`

Deux variables **nouvelles** à ajouter (absentes des `.env` existants, présentes dans `.env.dist`) :

```bash
BASE_IMAGE_FRANKENPHP=dunglas/frankenphp:php8.4
MERCURE_JWT_SECRET=<secret propre à CET environnement>
```

```bash
make mercure_generate_secret   # affiche la ligne à copier — n'écrit PAS dans .env
```

**Un secret différent par environnement.** Ne jamais reprendre celui du dev ni le placeholder de
`.env.dist`.

### Étape 3 — Compléter `sources/api2/.env`

⚠️ **C'est l'étape la plus piégeuse.** Le `.env.dist` est calibré pour le **dev** : le copier tel
quel donne un api2 qui ne trouve ni la base, ni les bonnes origines CORS. Chaque valeur ci-dessous
doit être adaptée au serveur.

> **⚠️ Jamais de commentaire en fin de ligne dans un `.env`.** Contrairement au shell, le parseur
> Dotenv de Symfony n'isole pas toujours un `#` placé après une valeur : il peut finir **dans** la
> valeur. Les commentaires vont sur leur **propre ligne**. (Erreur commise par cette doc même, qui a
> coûté un déploiement le 2026-07-17.)

```dotenv
# Hôte = nom du conteneur DB = ${APPLICATION_NAME}_db (kpi_preprod_db en préprod),
# et NON "kpi_db" comme dans .env.dist, calibré pour le dev.
DATABASE_URL="mysql://<DB_USER>:<DB_PASSWORD>@<APPLICATION_NAME>_db:3306/<DB_NAME>?serverVersion=11.5.2-MariaDB&charset=utf8mb4"

# Le conteneur api2 impose déjà APP_ENV=prod par son environnement, mais toute console
# lancée hors de ce conteneur relit CE fichier : laisser "dev" ici regénère un cache dev
# dans le volume partagé (cf. §8ter.b).
APP_ENV=prod

# Doit inclure /api2 : Traefik strippe le préfixe, sans quoi les IRIs sont fausses (cf. §7.6).
DEFAULT_URI=https://<domaine>/api2
API_DOCS_SERVER_URL='https://<domaine>/api2'

# Origines réelles des frontends — la valeur par défaut ne couvre que *.localhost.
CORS_ALLOW_ORIGIN='<regex des origines de cet environnement>'

# MERCURE_URL : publication interne (bypasse Traefik).
MERCURE_URL=http://localhost/.well-known/mercure
# MERCURE_PUBLIC_URL : abonnement navigateur, avec le préfixe /api2.
MERCURE_PUBLIC_URL=https://<domaine>/api2/.well-known/mercure
# Doit être IDENTIQUE au MERCURE_JWT_SECRET de docker/.env, sinon les publish → 403.
MERCURE_JWT_SECRET="<LE MÊME secret qu'à l'étape 2>"
```

`DB_USER`, `DB_PASSWORD`, `DB_NAME` et `APPLICATION_NAME` se lisent dans le `docker/.env` du serveur.

> **Vérifier qu'aucune clé n'est en double** dans le fichier final : en `.env`, **la dernière
> occurrence gagne**. Une valeur corrigée en début de fichier reste sans effet si une ancienne
> traîne plus bas.

### Étape 4 — Réinstaller les dépendances PHP

**Obligatoire** : le `composer.lock` a changé (retour de Symfony 8 vers 7.4 LTS + ajout de
`runtime/frankenphp-symfony`, cf. §7.9).

```bash
make api2_composer_install
```

**Ne jamais faire de `composer update` ici** : cela repartirait vers Symfony 8, non voulue.

### Étape 5 — Rebuild et démarrage

`Dockerfile.api2` est nouveau : un simple `up` ne suffit pas, il faut un rebuild.

```bash
make docker_preprod_rebuild     # ou docker_prod_rebuild
make docker_preprod_status      # ou docker_prod_status
```

> **Le conteneur legacy doit être recréé, pas seulement redémarré.** Ses labels Traefik ont changé
> (exclusion de `/api2` et `/admin2`, cf. §8quater) et Docker ne les relit qu'à la **recréation** :
> `docker_*_rebuild` ou `docker_*_up` le font, `docker_*_restart` **non**.

### Étape 6 — Vérifier

Reprise du plan §8, adaptée au serveur :

```bash
make api2_logs_errors                                   # doit être silencieux
curl -sI https://<domaine>/api2/doc                     # 200 + "server: FrankenPHP Caddy"
curl -sI https://<domaine>/                             # legacy toujours servi par Apache
docker exec ${APPLICATION_NAME}_api2 ls /var/www/html/live/cache   # montage ../sources présent
docker logs ${APPLICATION_NAME}_event_cache_worker      # démon inchangé (cf. §8ter.d)
docker stats ${APPLICATION_NAME}_api2                   # RSS se stabilise, ne croît pas
```

Sur `/api2/doc`, contrôler **aussi les headers** `content-location` et `link` : ils doivent porter le
préfixe `/api2` et `https`. Un `/api` nu = `DEFAULT_URI` faux (cf. §7.6).

Côté navigateur : app2/app4 sans erreur CORS, **un seul** header `Access-Control-Allow-Origin`, JWT
accepté, `servers` de l'OpenAPI préfixé `/api2`, et **la page Swagger avec son CSS** (sinon :
`asset.base_path`, cf. §7.6). Penser au rechargement forcé (Ctrl+Shift+R) : les 404 d'assets restent
en cache navigateur.

**Vérifier l'absence de coupure** — un `make api2_cache_clear` ne doit produire aucun 404 :

```bash
make api2_cache_clear
for i in $(seq 1 20); do
  curl -s -o /dev/null -w "%{http_code} %{time_total}s\n" https://<domaine>/api2/doc
done
# Attendu : 20 × 200. Un seul appel plus lent (~300 ms) = le worker recyclé, c'est normal.
# Des 404 → cf. §8quater.
```

### ⚠️ Après ce déploiement : `make api2_restart` devient obligatoire

`FRANKENPHP_WATCH=` est vide hors dev : le kernel reste en mémoire. **Un `git pull` ne suffit plus à
déployer du code api2** — il faut recycler le worker :

```bash
git pull && make api2_restart          # code (src/)
git pull && make api2_cache_clear      # config/ ou routes modifiées
```

C'est le changement d'habitude le plus durable de cette migration (cf. §5 et §7.8d). Depuis le
2026-07-17, `api2_restart` recycle **à chaud** (API admin de Caddy) : plus de coupure de service,
contrairement au `docker restart` initial (cf. §8quater).

---

## 8ter. Pannes rencontrées au déploiement préprod (2026-07-17)

Journal du premier déploiement préprod réel. Les quatre problèmes ont produit **le même symptôme —
un 404** — pour quatre causes distinctes. D'où la règle générale :

> **Un 404 sur `/api2` ne dit rien de sa cause.** Toujours commencer par `curl -sI` et regarder le
> header **`server:`** : `Apache` → api2 n'est pas dans la boucle (routage/conteneur absent) ;
> `FrankenPHP Caddy` → api2 répond, le problème est applicatif.

### a. `DEFAULT_URI` sans le préfixe `/api2` → tous les liens en 404

**Symptôme** — `/api2/doc` répond 200, mais tout ce qu'elle référence est en 404.

**Diagnostic** — les headers de la réponse trahissent la cause :

```bash
curl -sI https://<domaine>/api2/doc
# content-location: /api/.well-known/genid/...        ← /api au lieu de /api2
# link: <http://<domaine>/api/docs.jsonld>; ...       ← et http au lieu de https
```

Symfony génère des URLs sous `/api` : elles partent chez **Apache**, qui n'a pas ces routes → 404.

**Cause** — `DEFAULT_URI` ne contenait pas le `/api2` (cf. §7.6). Traefik strippe le préfixe, donc
Symfony ne peut pas le deviner.

**Correction** — `DEFAULT_URI=https://<domaine>/api2` dans `sources/api2/.env`, puis
`make api2_cache_clear`.

### b. Cache `dev` écrit dans un conteneur `prod` → 404 après chaque restart

**Symptôme** — api2 fonctionne, un `make api2_restart` le casse, un `cache:clear` manuel le répare.
Déroutant parce qu'apparemment aléatoire.

**Cause** — `make api2_cache_clear` et `api2_cache_warmup` exécutaient la console dans le conteneur
**Apache** (`PHP_CONTAINER_NAME`), qui n'a pas `APP_ENV=prod`. Ils écrivaient donc un cache **dev**
dans le `var/cache/` partagé d'un api2 tournant en **prod**. Le worker chargeait ce cache incohérent
au redémarrage.

**Indice** — `docker exec ${APPLICATION_NAME}_api2 ls /app/var/cache/` montre `dev` **et** `prod`
côte à côte, alors que le conteneur tourne en prod.

**Correction** — ✅ **corrigé dans le Makefile** : les deux cibles exécutent désormais la console
dans le conteneur api2. Vérifier aussi `APP_ENV=prod` dans `sources/api2/.env` : toute console
lancée hors du conteneur api2 relit ce fichier et repartirait en `dev`.

### c. Assets du Swagger en 404 (`asset.base_path`)

**Symptôme** — l'API fonctionne, mais la page Swagger s'affiche nue (CSS/JS en 404 à la racine).

**Cause** — `DEFAULT_URI` ne couvre **que le routeur**. Le composant `asset` a son propre réglage et
générait `/bundles/...` à la racine → Apache → 404.

**Correction** — ✅ `framework.assets.base_path: '/api2'` dans
[framework.yaml](../../../sources/api2/config/packages/framework.yaml).

### d. `SQLSTATE[HY000] [2002] Connection refused` sur le `event-cache-worker`

**Symptôme** — au tout premier démarrage :

```
[ERROR] Worker error: An exception occurred in the driver: SQLSTATE[HY000] [2002] Connection refused
```

**Cause** — **course au démarrage bénigne** : `depends_on: db` n'attend que le *démarrage* du
conteneur MariaDB, pas qu'il accepte les connexions. Le worker se connecte trop tôt, échoue, et
`restart: unless-stopped` le relance. Le message provient du PID 1 initial.

**Comment trancher** — une course **se résorbe seule** en quelques secondes. Une erreur **qui
persiste** est une vraie erreur de configuration : vérifier alors que l'hôte de `DATABASE_URL`
correspond bien au conteneur DB de cet environnement, soit `${APPLICATION_NAME}_db` (`kpi_preprod_db`
en préprod, et non `kpi_db` comme dans le `.env.dist` calibré pour le dev — piège multi-environnement
classique, cf. [MAKEFILE_MULTI_ENVIRONMENT.md](../guides/infrastructure/MAKEFILE_MULTI_ENVIRONMENT.md)).

```bash
docker logs --tail 20 ${APPLICATION_NAME}_event_cache_worker   # encore des erreurs ? → config
```

---

## 8quater. `docker restart` d'api2 → ~25 s de 404 : le piège Traefik + healthcheck

**Le piège le plus coûteux de cette migration**, et celui qui explique la majorité des 404 ci-dessus.

### Le symptôme

Après un `docker restart` du conteneur api2, `/api2` renvoie des 404 pendant **~25 secondes**, puis
tout repart seul. Mesuré en préprod (sondage 1/s) :

```
01..29  404  ~35 ms        ← Apache : api2 n'est pas dans la boucle
30      200  303 ms        ← le worker boote enfin
31..40  200  ~40 ms
```

### La cause : deux mécanismes cumulés

1. **Les labels Traefik vivent sur le conteneur api2.** `docker restart` le fait disparaître du
   réseau : Traefik retire le routeur `api2`, et le routeur legacy (`Host` seul, sans contrainte de
   chemin) **récupère `/api2` par défaut**. Apache reçoit une URL qu'il ne connaît pas → 404.
2. **Le healthcheck hérité de l'image FrankenPHP** (`curl -f http://localhost:2019/metrics`) n'a ni
   `start_period` ni `interval` : Docker applique ses défauts (~30 s). Traefik n'enregistre pas un
   conteneur qui n'est pas `healthy` — d'où l'essentiel de l'attente.

> Ce n'est **pas** le cache qui se reconstruit. Deux indices le prouvent : les 404 sont **rapides**
> (~35 ms — un Symfony qui bootstrap serait *lent*, pas absent), et le header dit **`server: Apache`**.

### Correction 1 — recycler à chaud, sans `docker restart`

✅ **`make api2_restart` recycle désormais les workers via l'API admin de Caddy** (`localhost:2019`),
sans arrêter le conteneur : Traefik ne décroche jamais, le healthcheck n'est pas réinitialisé.
**Zéro coupure**, mesurée en sondage continu.

```bash
docker exec <api2> curl -X POST http://localhost:2019/load \
  -H "Content-Type: application/json" \
  -H "Cache-Control: must-revalidate" \
  -d @/config/caddy/autosave.json
```

> ⚠️ **`Cache-Control: must-revalidate` est obligatoire.** Sans ce header, Caddy répond
> `"config is unchanged"` et **ne recycle rien** — le reload paraît réussir (HTTP 200) alors qu'il
> n'a rien fait. Vérifier dans les logs la présence de `FrankenPHP started 🐘` / `stopped 🐘`.

Repli automatique sur `docker restart` si l'API admin est injoignable (conteneur arrêté), avec
avertissement explicite sur la coupure attendue.

### Correction 2 — le legacy n'usurpe plus `/api2`

✅ **Le routeur legacy exclut désormais `/api2` et `/admin2`** dans les trois composes :

```yaml
- "traefik.http.routers.kpi.rule=Host(`${KPI_DOMAIN_NAME}`) && !PathPrefix(`/api2`) && !PathPrefix(`/admin2`)"
```

En production, les parenthèses autour du `||` des deux `Host` sont **obligatoires** (précédence des
opérateurs).

Effet quand api2 est absent :

| | Avant | Après |
|---|---|---|
| `/api2` | `404` + **page Apache** (trompeur) | `404 page not found` de **Traefik**, aucun header `server:` |
| `/` legacy | 200 | 200 (inchangé) |
| `/admin2` | nginx | nginx (inchangé) |

L'erreur ne ment plus : Traefik dit « aucune route ne matche » au lieu de laisser Apache se faire
passer pour api2. `/admin2` (nginx_app4) bénéficie de la même protection — il avait le même défaut.

> **Ce n'est pas un 503.** Un vrai 503 exigerait un routeur de repli déclaré dans la configuration
> **statique** de Traefik (priorité intermédiaire, service sans backend) — impossible depuis ce
> dépôt, le Traefik étant partagé avec d'autres projets. Le 404 franc de Traefik a été jugé
> suffisant : il ne trompe plus le diagnostic.

### Ordre correct des opérations

`cache:clear` **supprime** le cache : si les workers redémarrent aussitôt, ils rebootent sur un cache
vide et les requêtes qui arrivent pendant la reconstruction échouent. D'où l'enchaînement retenu dans
`api2_cache_clear` :

```
cache:clear  →  cache:warmup  →  recyclage à chaud
```

C'est aussi pourquoi **`cache:clear` n'est PAS inclus dans `api2_restart`** : ce dernier doit rester
l'opération rapide et sûre du quotidien, et supprimer le cache à chaque redémarrage infligerait un
bootstrap complet aux premières requêtes en production.

| Situation | Commande |
|---|---|
| Changement de code (`src/`) | `make api2_restart` |
| Changement de config / routes | `make api2_cache_clear` (préchauffe et recycle) |

---

## 9. Décision

| Option | Verdict |
|---|---|
| Remplacer Apache par FrankenPHP partout | ❌ Effort élevé, risque élevé, gain nul sur legacy/WP |
| **Extraire API2 en FrankenPHP worker** | ✅ **Décidé et implémenté en dev (2026-07-16)** |
| Statu quo | ❌ Caduc — voir déclencheur ci-dessous |

### Déclencheur (2026-07-16)

La version initiale concluait que le statu quo restait légitime faute de problème de latence
mesuré. **Ce n'est plus le critère.** La décision est prise sur deux motifs :

1. **Refonte du scoring live avec Mercure** — le hub Mercure natif de FrankenPHP évite de déployer
   et maintenir un conteneur `dunglas/mercure` dédié. C'est un gain de **simplicité
   d'infrastructure**, indépendant de toute mesure de performance.
2. **Performance d'API2** — bénéfice saisi à l'occasion, pas moteur de la décision.

### Résultat constaté en dev

| Vérification (§8) | Résultat |
|---|---|
| `https://kpi.localhost/api2/doc` | ✅ HTTP 200, `server: FrankenPHP Caddy` |
| Latence `/api` (worker chaud) | ✅ ~1,8 ms (vs 20–60 ms de bootstrap) |
| Legacy + WordPress via Apache | ✅ Intacts (`/` → `Apache/2.4.65`) |
| API legacy `/api/events` | ✅ 401 (front controller opérationnel) |
| CORS | ✅ Un seul `Access-Control-Allow-Origin` |
| JWT | ✅ `/api2/admin/events` → 401 (route trouvée, header transmis) |
| OpenAPI `servers` | ✅ `/api2` |
| Vraie IP client | ✅ `client_ip` ≠ `remote_ip` (trusted_proxies) |
| Cache live (§7.7) | ✅ lisible **et** inscriptible depuis `kpi_api2` |
| Hub Mercure | ✅ `/.well-known/mercure` → 200 (SSE) |
| `event-cache-worker` | ✅ inchangé |

### Résultat constaté en préprod (2026-07-17)

Premier déploiement réel sur `preprod.kayak-polo.info`. **api2 est fonctionnel**, après quatre
correctifs — chacun documenté en §8ter :

| Vérification | Résultat |
|---|---|
| `https://preprod.kayak-polo.info/api2/doc` | ✅ HTTP 200, `server: FrankenPHP Caddy` |
| Legacy via Apache | ✅ Intact |
| Hub Mercure | ✅ abonnements SSE constatés dans les logs |
| `DEFAULT_URI` | ⚠️ → ✅ après correction (§8ter.a) |
| Cache dev/prod mélangé | ⚠️ → ✅ Makefile corrigé (§8ter.b) |
| Assets Swagger | ⚠️ → ✅ `asset.base_path` (§8ter.c) |
| Coupure au redémarrage | ⚠️ ~25 s → ✅ 0 s, recyclage à chaud (§8quater) |

**Reste à faire** :

- **Vérifications §8 non encore effectuées en préprod** : mémoire du worker sous charge
  (`docker stats`), contrôleurs TV/Games touchant le cache live, vraie IP client dans les logs.
- **Déploiement en production** — procédure §8bis, en tenant compte des correctifs ci-dessus (le
  chemin est désormais balisé : les quatre pièges sont connus et corrigés dans le dépôt).
