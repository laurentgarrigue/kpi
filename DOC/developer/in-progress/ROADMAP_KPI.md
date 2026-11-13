# Roadmap KPI - Prochains Objectifs et Travaux

**Version** : 2.0
**Date** : 27 décembre 2025 (v1) — **actualisée le 14 novembre 2026** (v2)
**Statut** : Document de planification

> Ce document donne les **priorités**. L'architecture visée et l'état détaillé de chaque chantier sont dans
> [TARGET_ARCHITECTURE.md](TARGET_ARCHITECTURE.md) ; en cas d'écart, c'est le document du chantier qui fait foi.

---

## 🎯 Vue d'Ensemble

**Fait depuis la v1 (décembre 2025 → novembre 2026)** :
- ✅ **app4 (admin2)** en production : gestion sportive courante, éditorial du site public, opérations, mode sombre,
  tutoriels guidés ; le legacy admin ne sert plus qu'aux pages non migrées
- ✅ **api2 sur FrankenPHP** (mode worker) avec **hub Mercure** embarqué, en préprod et en prod
- ✅ **app2 sans authentification** : connexion et contrôle du matériel (scrutineering) déplacés dans app4 (profil 10)
- ✅ **Site public app3** : phases 1 à 4 en production sur `beta.kayak-polo.info` (résultats, calendrier/ICS,
  historique, équipes, clubs, recherche, actualités, pages, formulaires remplaçant WordPress)
- ✅ **PDF Gotenberg** : phases 0 à 5 en production (documents rendus par api2, PDF legacy fermés aux accès anonymes)
- ✅ **Médailles de match** et statistiques de timeline (api2, app2, app3, app4)
- ✅ **Mesure d'audience Matomo sans cookie** commune à app2, app3 et app4
- ✅ **Catégorie d'âge dérivée** de la date de naissance (date inconnue gérée, historique recalculé)
- ✅ **Réalignement des structures FFCK** (CR/CD/clubs et codes régionaux sur le PCE)
- ✅ **Durcissement de l'authentification**, phases 1a, 1b et 2 (rate-limit, JWT en cookie `HttpOnly` + refresh,
  OAuth Google en option)
- ✅ **CI/CD GitHub Actions** : tests, lint, bump de versions, déploiement automatique en préprod, manuel en prod
- ✅ **Médias hors Git** (montages + sauvegardes restic)

**Abandonné ou remplacé** :
- ~~Migration JWT pour App2~~ → app2 n'a plus d'authentification ; le JWT est l'affaire d'app4
- ~~Migration du WebSocket Manager vers Nuxt 4~~ et ~~Refonte du Live Overlay~~ → remplacés par la **refonte du
  scoring live** (console Scoring dans app4, incrustation unique, relais matériel)

---

## 📋 Court Terme (saison 2027)

### 1. Bascule du site public sur `www` (phase 5)

**Objectif** : app3 devient le site `kayak-polo.info` ; WordPress et les pages `kp*.php` ne sont plus servis au public.

- Traefik : app3 routeur par défaut, Apache sur liste explicite de préfixes
- Redirections 301 (anciennes URL, `beta.*` → domaine principal pendant au moins une saison)
- Search Console, suivi des 404, actions Matomo à la bascule

**Suivi** : [PUBLIC_SITE_REDESIGN_STRATEGY.md](PUBLIC_SITE_REDESIGN_STRATEGY.md) ·
[checklist](PUBLIC_SITE_ROLLOUT_CHECKLIST.md) (phase 5)
**Priorité** : Haute

### 2. Scoring live : porte de validation des lots 1 à 4

**Objectif** : tenir de vrais matchs avec la console Scoring d'app4 et diffuser l'incrustation unique dans OBS,
avant d'ouvrir le moindre nouveau lot.

- Remise à niveau de la base de dev, porte de validation, tests fonctionnels par lot, puis préprod et un pilote en prod
- Lots 1–3 essayés en dev mais jamais validés de bout en bout ; lot 4 jamais passé dans OBS

**Suivi** : [LIVE_MATCH_SCORING_REFACTORING_PROPOSALS.md](LIVE_MATCH_SCORING_REFACTORING_PROPOSALS.md) (§0) ·
[SCORING_ROLLOUT_CHECKLIST.md](SCORING_ROLLOUT_CHECKLIST.md) (R0 → R5)
**Priorité** : Haute

### 3. Authentification : sessions et double facteur

- **1b-bis** : lister et révoquer ses appareils, page `/profile/security`
- **1b-ter** : continuité de session — jamais déconnecté pendant une gestion sportive ou un match (lot A en cours)
- **Phase 3** : TOTP obligatoire pour les profils ≤ 3, optionnel pour les autres

**Suivi** : [AUTH_HARDENING.md](AUTH_HARDENING.md) · [checklist](AUTH_HARDENING_ROLLOUT_CHECKLIST.md)
**Priorité** : Haute (sécurité)

### 4. Communication de la saison 2027

Plénières, formation arbitre, première journée, révélation des médailles de match, championnats d'Europe de
Thury-Harcourt (septembre 2027).

**Suivi** : [PLAN_COMMUNICATION_SAISON_2027.md](PLAN_COMMUNICATION_SAISON_2027.md)

### 5. Stabilisation et retours utilisateurs

Tests en compétition, corrections au fil de l'eau, complétion des dates de naissance inconnues par les gestionnaires
(`/admin2/athletes/list`), validations écrites des commissions pour la feuille de présence et la feuille de marque
PDF (PDF-DOC-03).

---

## 🔄 Moyen Terme (2027-2028)

### 1. Décommissionnement du legacy public (phase 6 du site public)

Arrêt de WordPress (`dbwp`) après archive, suppression des `kp*.php`, adoption du socle `kpi-layer` par app2 et app4 ;
puis `frame_*.php` remplacés par les pages `/embed` d'app3 (phase 7).

### 2. Retrait des PDF legacy (phase 6 des PDF)

Suppression de `Feuille*.php`, `Pdf*.php` et `MyPDF`, redirections 301 (une saison) puis 410, retrait de `mpdf/mpdf`.

**Suivi** : [PDF_GOTENBERG.md](../../specs/PDF_GOTENBERG.md) § 12 et § 15

### 3. Scoring live : lots 5 à 9

Relais matériel (remplace `app_wsm` et le broker WebSocket), paramétrage par compétition, mode hors ligne complet,
ménage du legacy live (FeuilleMarque V2/V3, ~20 incrustations PHP, cache JSON) **sur critère chiffré**, zéro papier
(composition validée par le responsable d'équipe, réclamation horodatée).

### 4. Fin de l'admin legacy

Migration des pages `admin/` restantes vers app4 + api2, puis retrait de `sources/api/` et de `Login.php`. Décider
avant l'arrêt d'Apache qui sert `/img/*` et les médias (candidat : `nginx_static`).

---

## 🚀 Long Terme (2028+)

### 1. Application mobile

La cible actuelle reste la **PWA** (app2 hors ligne au bord du bassin, app4 installable). Une application native
(React Native, Flutter) ne se justifierait que pour des besoins hors de portée d'une PWA (notifications push fiables
sur iOS, par exemple).

### 2. Améliorations continues

Nouvelles statistiques, comparaison d'équipes, live public dans app3 et app2 (abonnement aux topics Mercure),
exports personnalisés, intégrations tierces — selon les retours utilisateurs.

---

## 📊 Architecture Cible

Voir [TARGET_ARCHITECTURE.md](TARGET_ARCHITECTURE.md) : une seule API (api2), trois fronts Nuxt 4 (app3 site public,
app2 bord de bassin, app4 administration et opérations) sur un socle commun `kpi-layer`, état du match en base diffusé
par Mercure, PDF rendus par Gotenberg, legacy en cours de retrait.

---

## ⚙️ Méthodologie

**Migration progressive sans interruption de service** (Strangler Fig) : api2 d'abord, migration module par module,
legacy conservé jusqu'à validation, retour arrière toujours possible.

**Règles de travail** ([CLEAN_CODE.md](../guides/CLEAN_CODE.md)) :
- **Spec d'abord**, avec critères d'acceptation testables ([DOC/specs/](../../specs/))
- **TDD** : test qui échoue, code, refactorisation
- **Checklist de déploiement** par chantier (local → préprod → prod), close puis archivée une fois en production
- **Porte de validation** avant d'ouvrir un nouveau lot d'un chantier

---

## 🎯 Objectifs de Qualité

### Performance
- Temps de chargement < 2 secondes ; réactivité interface < 100 ms ; usage mobile en 4G au bord du bassin

### Fiabilité
- Disponibilité > 99,5 % ; sauvegarde quotidienne de la base et des médias ; retour arrière documenté
  ([DEPLOYMENT_RUNBOOK.md](../infrastructure/DEPLOYMENT_RUNBOOK.md))

### Sécurité
- Authentification renforcée (cookie `HttpOnly`, refresh, rate-limit, TOTP à venir) ; données personnelles des PDF
  protégées ; mises à jour de sécurité suivies (Dependabot)

### Maintenabilité
- Tests automatisés (PHPUnit api2, Vitest app3/app4) exécutés en CI ; documentation tenue à jour dans `DOC/`

---

## 📝 Risques Identifiés

- **Double chemin permanent** : legacy et nouveau coexistent tant qu'aucun critère daté ne déclenche le retrait
  (scoring, PDF, site public)
- **Disponibilité du développeur** : un seul mainteneur ; prioriser ce qui sert les compétitions de la saison
- **Adoption** : formation des tables de marque à la console Scoring, des gestionnaires à app4

**Atténuation** : un chantier à la fois jusqu'à sa porte de validation, checklists détaillées, communication avant
chaque changement visible.

---

**Auteur** : Laurent Garrigue
**Mise à jour** : à chaque clôture de chantier
