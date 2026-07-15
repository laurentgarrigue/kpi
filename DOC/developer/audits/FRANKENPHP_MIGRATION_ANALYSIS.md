# Analyse : remplacer Apache-PHP par FrankenPHP ?

**Date** : 2026-07-14
**Statut** : Analyse / proposition — non implémenté
**Périmètre** : conteneur `kpi` (PHP 8.4 + Apache), API2 (Symfony 7.3 / API Platform 4.2), WordPress, legacy PHP

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

- ✅ `symfony/runtime` déjà présent (`7.4.*`) — prérequis du mode worker FrankenPHP
- ✅ `public/index.php` utilise déjà `autoload_runtime.php` et retourne une closure → **aucune
  modification du front controller nécessaire**
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

Utile si `event-cache-worker` ou app3/app4 devaient pousser du temps réel sans WebSocket maison.
Pas un besoin actuel — c'est de l'optionnalité gratuite.

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

### Logs

Le compose monte `${HOST_APACHE2_LOG_PATH}:/var/log/apache2/`. Caddy loggue en **JSON sur stdout**.
Tout outil ou habitude lisant `docker/apachelogs_8/access.log` change de format.

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
| Effort | Semaines | **Une demi-journée** |

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
            # En dev : pas de worker (rechargement du code à chaque requête).
            # Retirer cette ligne pour tester le mode worker.
            - FRANKENPHP_CONFIG=
        volumes:
            - ../sources/api2:/app
            # ⚠️ INDISPENSABLE — voir §7.7.
            # EventCacheService et AdminTvController écrivent/lisent sous
            # /var/www/html/live/cache (live_document_root). Sans ce montage,
            # AdminGamesController et AdminTvController cassent en HTTP.
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

Vérifier dans `sources/api2/.env` :

```dotenv
DEFAULT_URI=https://kpi.localhost/api2
API_DOCS_SERVER_URL='https://kpi.localhost/api2'
```

**C'est le piège le plus probable de cette migration** : la doc OpenAPI et les liens IRI générés
par API Platform pointeront vers la racine (`/games` au lieu de `/api2/games`) si `DEFAULT_URI`
n'est pas correct.

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

## 8. Plan de validation

Avant bascule, vérifier :

1. **Routage** — `GET https://kpi.localhost/api2/api` sert bien la doc API Platform depuis le
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

## 9. Décision

| Option | Verdict |
|---|---|
| Remplacer Apache par FrankenPHP partout | ❌ Effort élevé, risque élevé, gain nul sur legacy/WP |
| **Extraire API2 en FrankenPHP worker** | ✅ **Recommandé** |
| Statu quo | 🟡 Acceptable — aucun problème de perf avéré à ce jour |

L'extraction d'API2 n'a d'intérêt que si la latence de l'API devient un problème mesuré. En
l'absence de métrique montrant que le bootstrap Symfony coûte cher en production, **le statu quo
reste une option légitime** : le document ci-dessus permettra de basculer rapidement le jour où le
besoin se manifeste.
