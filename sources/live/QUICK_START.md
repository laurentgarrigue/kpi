# 🚀 Quick Start - Event Worker

Ce guide vous permet de démarrer rapidement avec le nouveau système de worker pour la génération automatique des caches d'événements.

---

## ⚡ Démarrage rapide (3 étapes)

### 1️⃣ Installer la base de données

```bash
# Démarrer les conteneurs Docker
make docker_dev_up

# Attendre que les conteneurs démarrent (30 secondes)

# Exécuter la migration SQL
docker exec -i kpi_db mysql -u root -p"${MYSQL_ROOT_PASSWORD}" kpi_db < SQL/20251111_create_event_worker_config.sql
```

Ou manuellement via phpMyAdmin :
- Ouvrir phpMyAdmin
- Sélectionner la base `kpi_db`
- Onglet "Import" > Choisir `SQL/20251111_create_event_worker_config.sql`
- Cliquer "Exécuter"

### 2️⃣ Créer le dossier de logs

```bash
mkdir -p sources/live/logs
chmod 755 sources/live/logs
```

### 3️⃣ Le daemon worker : rien à lancer

Le daemon génère les fichiers JSON tant qu'une config est `running` dans
`kp_event_worker_config`. Il tourne en permanence dans son **propre conteneur**
(`${APPLICATION_NAME}_event_cache_worker`), défini dans les trois compose avec
`restart: unless-stopped` : il démarre donc avec les autres conteneurs et
redémarre tout seul.

```bash
make docker_dev_up            # (ou docker_preprod_up / docker_prod_up)
make backend_worker_status    # vérifier qu'il tourne
```

> **Il n'y a plus de lancement manuel.** Les anciennes cibles
> `backend_worker_start` / `_start_prod` / `_stop` lançaient un second daemon
> dans le conteneur Apache, concurrent du conteneur dédié sur le même cache.
> Elles ont été supprimées (2026-07-17). Voir « Service Docker persistant »
> ci-dessous.

### 4️⃣ Utiliser l'interface web (Event Cache Manager)

1. Ouvrir l'**Event Cache Manager** dans app4 (console d'administration)
2. Sélectionner un événement
3. Configurer la date, l'heure et les paramètres
4. Cliquer sur **"▶ Start Worker"**
5. ✅ **Vous pouvez fermer le navigateur !** Le daemon continue de générer les caches

> La page ne fait qu'écrire la configuration en base. C'est le **daemon**
> (étape 3) qui lit cette config et génère réellement les fichiers
> `live/cache/event<id>_pitch<pitch>.json`.

---

## 🐳 Service Docker persistant (recommandé en preprod/prod)

Un service dédié `event-cache-worker` est défini dans les fichiers
`compose.dev.yaml`, `compose.preprod.yaml` et `compose.prod.yaml`. Il lance
`php api2/bin/console app:event-cache-worker` avec `restart: unless-stopped`,
donc le daemon redémarre automatiquement avec les containers.

```bash
make docker_prod_up        # (ou docker_preprod_up / docker_dev_up)
```

Une fois ce service en place, **le lancement manuel via `make` n'est plus
nécessaire** : le worker est toujours présent et consomme la config écrite par
la page.

### Isolation multi-environnement (preprod + prod sur le même VPS)

Le service worker s'isole exactement comme les autres containers, via
`APPLICATION_NAME` (défini dans chaque `docker/.env`) :

| Ressource          | Valeur                                   |
|--------------------|------------------------------------------|
| Nom du container   | `${APPLICATION_NAME}_event_cache_worker` |
| Réseau             | `network_${APPLICATION_NAME}`            |
| Dossier de cache   | `live/cache/` du dossier de déploiement  |

Tant que preprod et prod ont un **`APPLICATION_NAME` distinct** et des
**dossiers de déploiement séparés**, les deux workers tournent en parallèle
sans aucun conflit (containers, réseaux et fichiers de cache distincts).

---

## 🎯 Différences avec l'ancien système

| Ancien système | Nouveau système (Worker) |
|----------------|--------------------------|
| ❌ Navigateur obligatoire | ✅ Indépendant du navigateur |
| ❌ JavaScript setInterval() | ✅ Processus PHP serveur |
| ❌ Perd la session si l'onglet se ferme | ✅ Continue même si vous fermez tout |
| ⚠️ Difficile à monitorer | ✅ Interface de monitoring intégrée |
| ⚠️ Pas de logs centralisés | ✅ Logs accessibles via `make backend_worker_logs` |

---

## 🔧 Commandes utiles

```bash
# Vérifier que le worker tourne
make backend_worker_status

# Voir les logs en temps réel
make backend_worker_logs

# Redémarrer le worker (redémarre son conteneur)
make backend_worker_restart
```

---

## 📖 Documentation complète

Pour plus de détails, consultez : [`EVENT_WORKER_README.md`](EVENT_WORKER_README.md)

---

## ✅ Checklist de vérification

Avant d'utiliser le worker en production :

- [ ] Table `kp_event_worker_config` créée
- [ ] Dossier `sources/live/logs/` existant avec permissions 755
- [ ] Conteneurs Docker en cours d'exécution
- [ ] **Daemon worker actif** (`make backend_worker_status` → conteneur `Up`)
- [ ] Accès à l'Event Cache Manager (app4) fonctionnel
- [ ] Test de démarrage/arrêt du worker
- [ ] Vérification des fichiers JSON générés dans `sources/live/cache/`

---

## 🚨 Dépannage rapide

### "Worker Status: Not configured"
→ Normal au premier démarrage. Configurez et cliquez sur "Start Worker"

### "Worker may not be running properly"
→ Vérifier : `make backend_worker_status`
→ Redémarrer : `make backend_worker_restart`

### Fichiers JSON non générés
→ Vérifier les logs : `make backend_worker_logs`
→ Vérifier les permissions sur `sources/live/cache/`

### API renvoie une erreur
→ Vérifier que la table existe dans la base de données
→ Vérifier les logs PHP du container

---

## 💡 Cas d'usage

**Compétition sur une journée**
1. Configurer le worker 1h avant le début
2. Démarrer le worker
3. Les pages d'incrustation changent automatiquement de match
4. Monitorer via l'interface web si besoin
5. Arrêter le worker en fin de journée

**Compétition sur plusieurs jours**
1. Configurer le worker pour le premier jour
2. À la fin de la journée, cliquer sur "Pause"
3. Le lendemain, ajuster la date/heure et cliquer sur "Start Worker"
4. Répéter pour chaque jour

---

## 🎉 Prêt !

Vous êtes maintenant prêt à utiliser le système de worker pour vos événements !

N'hésitez pas à consulter la [documentation complète](EVENT_WORKER_README.md) pour en savoir plus.
