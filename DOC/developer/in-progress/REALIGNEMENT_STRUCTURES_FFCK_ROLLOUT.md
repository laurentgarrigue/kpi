# Réalignement des structures FFCK — rollout checklist (dev → préprod → prod)

**Créée le** : 29 octobre 2026 — à cocher au fil de l'eau, **chaque étape cochée avant la suivante**.
**Contexte** : spec [REALIGNEMENT_STRUCTURES_FFCK.md](REALIGNEMENT_STRUCTURES_FFCK.md) (« Script de remise à plat »,
« Déroulé par environnement ») · [ADR 0001](../reference/adr/0001-codes-regionaux-alignes-sur-les-cr-actuels.md) ·
vocabulaire [GLOSSARY.md](../../../GLOSSARY.md) · tickets [.scratch/realignement-structures-ffck/](../../../.scratch/realignement-structures-ffck/README.md)
(le ticket 05 suit cette checklist).

> **Convention** (comme le [runbook](../infrastructure/DEPLOYMENT_RUNBOOK.md)) : ⌨️ poste de dev ·
> 🖥 VPS (SSH `ssh -i ~/.ssh/kpi-deploy/kpi_deploy_ed25519 -p 22 deploy@93.127.163.83`) · 🌐 interface GitHub ·
> ✉️ messagerie.

**Les deux fichiers livrés** (rangés dans `SQL/migrations/`) :

| Fichier | Rôle |
|---|---|
| `2026-10-29_realignement_structures_ffck_controle.sql` | Requête de contrôle, **lecture seule**. Une ligne par indicateur, colonne `gaps` ; tant que le script n'est pas passé, liste des codes compétition à renommer |
| `2026-10-29_realignement_structures_ffck.sql` | Script de remise à plat. **Une transaction**, **garde-fou** avant toute écriture, **rejouable** sans effet |

**Règles** :

- Le script ne se lance **qu'après un import PCE fait avec l'import corrigé** (api2 déployé). Il ne peut pas
  recalculer les libellés : l'import vide les noms de structures de `kp_licence` à la fin de chaque exécution.
- **Toujours un dump juste avant le script.** C'est le seul retour arrière (§ 6).
- Si le garde-fou arrête le script (`ERROR 1644 (45000) … Garde-fou`), **rien n'a été modifié** : noter la liste
  affichée (`blocker | code`), **ne pas forcer**, compléter la table de correspondance dans un correctif, puis reprendre.
- Le renommage des codes compétition ne s'applique qu'une fois par base. Repère : ligne
  `Remise à plat structures FFCK` dans `kp_journal` (indicateur « Remise à plat appliquée »).
- **Codes à renommer** : le script renumérote tout code `REGnn…`/`T-Rnn…`/`CRnn` dont `nn` est un numéro
  d'Ancienne ligue, **quelle que soit sa date de création**. Un code créé après 2016 avec le numéro d'une CR
  actuelle (ex. un `REG03…` breton) serait renuméroté à tort (`REG09…`). Avant chaque (S), relire la dernière liste
  de (C) « avant » (`renamed_prefix | code | new_code | seasons | latest_label`) : chaque libellé doit nommer la
  région de l'**Ancienne ligue** (`REG07…` = Bretagne, `REG14…` = Île-de-France…). Un libellé d'une autre région
  → **ne pas lancer**, ajouter le code aux exceptions de l'ADR 0001 dans un correctif, puis reprendre.
- **Logos** : les engagements des Anciennes ligues pointent désormais vers `KIP/logo/CRnn-logo.png` (code de la
  Sélection régionale). Les fichiers d'images sont hors base (`HOST_MEDIA_PATH`) : il faut les réaffecter (étape
  « logos » de chaque environnement), sinon les équipes bretonnes affichent l'ancien `CR03-logo.png` (Aquitaine).

### Commandes réutilisées

Toutes se lancent **à la racine du checkout** de l'environnement (⌨️ dépôt local en dev, 🖥 `/data/kpi_preprod` en
préprod, `/data/kpi` en prod). Elles lisent conteneur, base et mot de passe dans `docker/.env`.

```bash
# (C) Contrôle — remplacer <etape> par avant / apres
( set -a; . ./docker/.env; set +a
  docker exec -i "$DB_CONTAINER_NAME" mariadb -uroot -p"$DB_ROOT_PASSWORD" -t "$DB_NAME" \
    < SQL/migrations/2026-10-29_realignement_structures_ffck_controle.sql ) | tee /tmp/rsf-controle-<etape>.txt

# (S) Script de remise à plat
make db_migrate file=SQL/migrations/2026-10-29_realignement_structures_ffck.sql

# (D) Dump complet — <dossier> = /data/backups/kpi_preprod/pre-migration (préprod),
#     /data/backups/kpi/pre-migration (prod), un dossier local hors dépôt (dev) ; deploy peut y écrire (vérifié le 29/10/2026)
mkdir -p <dossier> && ( set -a; . ./docker/.env; set +a
  docker exec "$DB_CONTAINER_NAME" mariadb-dump -uroot -p"$DB_ROOT_PASSWORD" --single-transaction --triggers "$DB_NAME" ) \
  | gzip > <dossier>/"$(grep ^APPLICATION_NAME docker/.env | cut -d= -f2)"_avant-remise-a-plat_$(date +%F_%H%M%S).sql.gz

# (L) Logos des Sélections régionales (sauvegarde puis copie depuis les logos des Anciennes ligues)
LOGO="$(grep ^HOST_MEDIA_PATH docker/.env | cut -d= -f2)/img/KIP/logo"
SAUV="$LOGO/../logo-avant-remise-a-plat-$(date +%F)"
mkdir -p "$SAUV" && cp -a "$LOGO"/CR[0-9][0-9]-logo.png "$SAUV"/
for paire in 01:18 02:13 03:07 04:08 06:12 07:14 08:20 09:03 10:19 11:04 12:23 21:29 24:25; do
  cible=${paire%%:*}; source=${paire##*:}
  if [ -f "$SAUV/CR$source-logo.png" ]; then cp "$SAUV/CR$source-logo.png" "$LOGO/CR$cible-logo.png"
  else rm -f "$LOGO/CR$cible-logo.png"; echo "CR$cible : pas de logo (à téléverser dans app4)"; fi
done
```

> `mariadb-dump` sans `--routines` : en dev, `mysql.proc` est corrompue et fait échouer l'option ; la base KPI n'a
> pas de procédure stockée. Un dump valide se termine par `-- Dump completed on …` (`zcat <fichier> | tail -1`).
> (L) : CR05 (Grand Est) a déjà son logo. Le logo d'une Sélection vient de l'Ancienne ligue principale ; si le
> fichier source manque, le logo périmé de même nom est retiré et la commande affiche la Sélection à compléter.

---

## 1. ⌨️ Validation en dev (faite le 29/10/2026)

Base `my_database` de `kpi_db`, code api2 de la branche d'intégration `feature/realignement_structures_ffck`.

| # | Commande / action | Résultat attendu | ☑ |
|---|---|---|---|
| 1 | (D) vers un dossier local, puis `zcat <fichier> \| tail -1` | `-- Dump completed on …` (≈ 24 Mo gz) | ☑ |
| 2 | `docker exec kpi_api2 php bin/console app:import-pce` | `[OK] Import terminé`, `Saison: 2026` ; CD 269 → 270, Clubs 1 410 → 1 437 | ☑ |
| 3 | (C) avant | Repères dev du tableau ci-dessous, colonne « avant » | ☑ |
| 4 | (S) | `Remise à plat terminée`, `pce_season 2026`, `regional_selections 14`, `renamed_codes 57`, `✔ … appliqué` (< 1 s) | ☑ |
| 5 | (C) après | Colonne « après » : tout à 0 sauf la ligne expliquée sous le tableau, « Remise à plat appliquée » = 1 | ☑ |
| 6 | (S) une 2ᵉ fois, avec `CHECKSUM TABLE` des tables touchées avant/après | `renamed_codes 0`, checksums identiques (aucune modification) | ☑ |
| 7 | Garde-fou (sur une copie de la base) : ajouter un pseudo-CD/pseudo-club `CR30` et une compétition `REG31X`, puis (S) | Tableau `blocker` listant `REG31X` et `CR30`, `ERROR 1644 (45000) … Garde-fou`, checksums inchangés | ☑ |
| 8 | Transaction (sur une copie) : trigger qui fait échouer l'insertion finale dans `kp_journal`, puis (S) | `ERROR 1644 … panne simulee`, checksums inchangés, `@@foreign_key_checks = 1` | ☑ |
| 9 | (L) | Copie des logos ; affiche « pas de logo » pour CR01, CR02, CR04, CR10, CR12, CR24 (fichiers source absents en dev) | ☑ |
| 10 | app4 `https://kpi.localhost/admin2` → Compétitions | `REG03` = « Championnat Régional Bretagne », code de référence `REG03` ; plus de `REG14`/`REG17`/`T-R18` | ☑ |
| 11 | app4 → Équipes → `CFD` 2026 | Équipe « CR Ile de France I F », club `CR07`, logo du comité Île-de-France | ☑ |
| 12 | app4 → Équipes → `REG05` 2026 | « CKLuxembourg 1/2 », club `CR05` (Sélection Grand Est) | ☑ |
| 13 | app4 → choix du mandat (compte avec mandats filtrés) | Filtres de mandat en `REG01`, `T-R01`, `T-R011`, `T-R012` (anciennement `REG18`, `T-R18`…) | ☑ |

**Repères dev (contrôle)** — préprod et prod partent d'une copie plus ancienne de la même base : les chiffres
« avant » doivent être du même ordre ; un écart important est à comprendre **avant** de lancer (S).

| Indicateur | avant | après |
|---|---:|---:|
| CR du PCE absentes de KPI | 0 | 0 |
| CD du PCE absents de KPI | 0 | 0 |
| CD rattachés à une autre CR que dans le PCE | 0 | 0 |
| Clubs du PCE absents de KPI | 1 | 1 |
| Clubs rattachés à un autre CD que dans le PCE | 0 | 0 |
| CD hors PCE non référencés (dont format brut CDxxx) | 98 | 0 |
| Clubs hors PCE non référencés | 235 | 0 |
| Pseudo-CD d'Anciennes ligues restants | 20 | 0 |
| Pseudo-clubs d'Anciennes ligues restants | 21 | 0 |
| Équipes sur un pseudo-club d'Ancienne ligue | 238 | 0 |
| Engagements sur un pseudo-club d'Ancienne ligue | 595 | 0 |
| Utilisateurs et mandats limités à un pseudo-club d'Ancienne ligue | 35 | 0 |
| Codes REGxx/T-Rxx/CRxx hors CR actuelles | 13 | 0 |
| Filtres utilisateur/mandat citant un code REGxx/T-Rxx hors CR actuelles | 109 | 0 |
| Remise à plat appliquée (1 = oui) | 0 | 1 |

Totaux : CD 270 → 165, Clubs 1 437 → 1 195, 14 Sélections régionales, 57 codes régionaux renommés.

> **« Clubs du PCE absents de KPI » = 1 en dev, avant comme après** : `CD077`, un licencié de la saison 2026
> rattaché directement au CD 77, venu d'un import PCE antérieur de la saison et absent du fichier du jour. L'import
> ne peut pas le créer (pas de libellé), le script non plus. Ce n'est pas un écart à corriger ; en préprod/prod,
> la même ligne peut valoir 0 ou quelques unités : vérifier que ce sont des codes `CDxxx` de ce type.

---

## 2. ⌨️ 🌐 PR et CI

```bash
make pr_create        # depuis feature/realignement_structures_ffck, PR vers main
make pr_checks
```

| # | Job CI / action | Résultat attendu | ☑ |
|---|---|---|---|
| 1 | `changes` | `api2=true` (import PCE, opération « changer un code ») ; `SQL/` et `DOC/` ne déclenchent aucun job | ☐ |
| 2 | `tests-api2` (unit + integration), `phpstan` | verts, dont `ImportPceTest` et les tests du renommage de code | ☐ |
| 3 | `bump-version` | bump **api2** uniquement | ☐ |
| 4 | `ci-summary` | vert | ☐ |
| 5 | Revue : relire le script et la table de correspondance contre l'ADR 0001 | OK | ☐ |
| 6 | `make pr_merge` | PR mergée, déploiement préprod automatique lancé | ☐ |
| 7 | 🌐 Actions → « Deploy preprod » → le run | vert ; le wrapper a fait `composer install`, `cache_clear`, `api2_restart` | ☐ |

---

## 3. 🖥 Préprod (`/data/kpi_preprod`)

```bash
ssh -i ~/.ssh/kpi-deploy/kpi_deploy_ed25519 -p 22 deploy@93.127.163.83
cd /data/kpi_preprod
```

| # | Commande / action | Résultat attendu | ☑ |
|---|---|---|---|
| 1 | `cat .last-deploy-sha && git log --oneline -1` | SHA du merge de la PR | ☐ |
| 2 | `ls SQL/migrations/2026-10-29_realignement_structures_ffck*.sql` | les deux fichiers | ☐ |
| 3 | `docker exec kpi_preprod_api2 php bin/console app:import-pce` | `[OK] Import terminé`, `Saison: <année>` | ☐ |
| 4 | (D) avec `<dossier>` = `/data/backups/kpi_preprod/pre-migration`, puis `zcat <fichier> \| tail -1` | `-- Dump completed on …` ; noter le nom du fichier : ______ | ☐ |
| 5 | (C) avant | Proche des repères dev (§ 1) ; « Remise à plat appliquée » = 0 | ☐ |
| 6 | Relire la liste « codes à renommer » de (C) avant (voir « Règles ») | Chaque `latest_label` nomme la région de l'Ancienne ligue de son `renamed_prefix` (repère dev : 55 codes, aucun hors région) | ☐ |
| 7 | (S) | `Remise à plat terminée` … `✔ … appliqué`. **Si garde-fou** : rien n'est modifié, voir « Règles » | ☐ |
| 8 | (C) après | Toutes les lignes à 0 (sauf « Clubs du PCE absents », cf. § 1), « Remise à plat appliquée » = 1 ; liste « codes à renommer » vide | ☐ |
| 9 | (S) une 2ᵉ fois | `renamed_codes 0` ; (C) identique à l'étape 8 | ☐ |
| 10 | (L) | Copie faite ; noter les Sélections sans logo : ______ | ☐ |
| 11 | 🌐 `https://preprod.kayak-polo.info/admin2` → Compétitions | `REG03` « Championnat Régional Bretagne », `REG07` « … Île-de-France » ; plus de `REG14`, `REG17`, `REG18` | ☐ |
| 12 | 🌐 app4 → Équipes → une compétition nationale jeunes (`N15`, `CF15`…) | Les équipes de sélection portent un club `CRnn` au libellé de la CR actuelle et le bon logo | ☐ |
| 13 | 🌐 Site public / app2 : une page de compétition `REG03` | Classement et matchs affichés, logos corrects | ☐ |
| 14 | 🌐 app4 → Opérations → « changer un code compétition » sur une compétition de test, puis retour au code d'origine | OK, responsables, groupe et filtres suivent (ticket 03) | ☐ |
| 15 | Le lendemain, après l'import PCE nocturne : (C) | Toujours à 0 écart (l'import corrigé ne recrée ni pseudo-club ni écart) | ☐ |

---

## 4. ✉️ Message aux responsables régionaux (avant la prod)

À envoyer **au moins une semaine avant** la date retenue pour la prod, aux responsables des Compétitions
régionales et des Sélections régionales (liste : utilisateurs dont le filtre de compétitions ou la limitation
d'équipes contient un code `REG`, `T-R` ou `CR`). Remplacer `<date>`.

> **Objet : KPI — nouveaux codes des compétitions et sélections régionales le <date>**
>
> Bonjour,
>
> Le <date>, KPI aligne les structures fédérales (CR, CD, clubs) sur le fichier fédéral (PCE) et renomme les codes
> des compétitions régionales pour qu'ils portent le numéro de votre comité régional actuel, sur toutes les saisons.
> Exemples : `REG07` (Bretagne) devient `REG03`, `REG14` (Île-de-France) devient `REG07`, `REG18` (Auvergne-Rhône-Alpes)
> devient `REG01`, `T-R14` devient `T-R07`. Les suffixes ne changent pas (`REG07U15` → `REG03U15`).
>
> Les équipes des anciennes ligues sont regroupées dans une seule « Sélection régionale » par comité régional,
> au code et au nom du comité (ex. Alsace, Champagne-Ardenne et Lorraine → `CR05` Grand Est).
>
> Ce qui suit automatiquement : vos accès, vos filtres de compétitions, vos mandats, les responsables de
> compétition et l'historique de vos compétitions.
>
> Ce qui ne suit pas : **les liens et favoris enregistrés vers les anciens codes**. Comme les numéros se recoupent,
> un ancien lien peut même afficher une autre région (un ancien lien `REG07` affichera l'Île-de-France). Pensez à
> mettre à jour vos favoris et les liens publiés sur vos sites après le <date>.
>
> Aucune compétition ne doit être saisie ce jour-là. Pour toute question : contact@kayak-polo.info.
>
> Laurent Garrigue

| # | Action | Résultat attendu | ☑ |
|---|---|---|---|
| 1 | Choisir un jour **sans compétition** (calendrier app4 / legacy) | Date retenue : ______ | ☐ |
| 2 | Envoyer le message | Envoyé le ______ | ☐ |

---

## 5. 🖥 Production (`/data/kpi`) — le jour retenu

Même déroulé qu'en préprod. Le code api2 doit déjà être en prod :

| # | Commande / action | Résultat attendu | ☑ |
|---|---|---|---|
| 1 | ⌨️ `make release_tag version=X.Y.Z` sur `main` à jour, puis 🌐 Actions → « Deploy production » depuis `main`, `ref` = `vX.Y.Z` → approuver | Run vert | ☐ |
| 2 | 🖥 `cd /data/kpi && cat .last-deploy-sha && git describe --tags` | `vX.Y.Z` | ☐ |
| 3 | `docker exec kpi_api2 php bin/console app:import-pce` | `[OK] Import terminé` | ☐ |
| 4 | (D) avec `<dossier>` = `/data/backups/kpi/pre-migration`, puis `zcat <fichier> \| tail -1` | `-- Dump completed on …` ; fichier : ______ | ☐ |
| 5 | (C) avant | Proche des chiffres de préprod (§ 3.5) | ☐ |
| 6 | Relire la liste « codes à renommer » de (C) avant (voir « Règles ») ; comparer à celle de la préprod | Chaque `latest_label` nomme la région de l'Ancienne ligue ; tout code absent de la liste préprod (créé depuis) vérifié | ☐ |
| 7 | (S) | `Remise à plat terminée` … `✔ … appliqué` | ☐ |
| 8 | (C) après | Toutes les lignes à 0 (sauf « Clubs du PCE absents », cf. § 1), « Remise à plat appliquée » = 1 ; liste « codes à renommer » vide | ☐ |
| 9 | (S) une 2ᵉ fois | `renamed_codes 0`, (C) inchangé | ☐ |
| 10 | (L) | Copie faite ; Sélections sans logo : ______ ; téléverser leur logo dans app4 | ☐ |
| 11 | 🌐 `https://kayak-polo.info/admin2` : mêmes vérifications que § 3.11 à 3.13 | OK | ☐ |
| 12 | 🌐 `https://kayak-polo.info/api2/doc` et une page publique de compétition régionale | Réponses 200, données affichées | ☐ |
| 13 | Le lendemain : (C) après l'import nocturne | toujours à 0 écart | ☐ |

---

## 6. 🖥 Rollback (restauration du dump)

À utiliser si le contrôle « après » montre un dégât ou si une vérification visuelle échoue **le jour même**.
Les données saisies entre le dump et la restauration sont perdues : décider vite. Exemple en préprod
(prod : `/data/kpi`, `compose.prod.yaml`, dossier `/data/backups/kpi/pre-migration`).

```bash
cd /data/kpi_preprod
# 0) Filet : dump de l'état actuel (commande (D) avec un autre suffixe)
# 1) Couper les écritures
docker compose -f docker/compose.preprod.yaml stop kpi api2
# 2) Restaurer le dump pris juste avant le script
( set -a; . ./docker/.env; set +a
  gunzip -c /data/backups/kpi_preprod/pre-migration/<fichier>.sql.gz \
    | docker exec -i "$DB_CONTAINER_NAME" mariadb -uroot -p"$DB_ROOT_PASSWORD" "$DB_NAME" )
# 3) Relancer
docker compose -f docker/compose.preprod.yaml start kpi api2
make api2_restart
# 4) Logos : remettre les fichiers sauvegardés par (L)
LOGO="$(grep ^HOST_MEDIA_PATH docker/.env | cut -d= -f2)/img/KIP/logo"
cp -a "$LOGO"/../logo-avant-remise-a-plat-<date>/CR[0-9][0-9]-logo.png "$LOGO"/
```

| # | Vérification | Résultat attendu | ☑ |
|---|---|---|---|
| 1 | (C) | Mêmes chiffres que le contrôle « avant » ; « Remise à plat appliquée » = 0 | ☐ |
| 2 | app4 → Compétitions | Les anciens codes (`REG07` Bretagne, `REG14`…) sont revenus | ☐ |
| 3 | Noter la cause et le dump utilisé dans le ticket 05 | Fait | ☐ |

Le code api2 (import corrigé) n'a pas besoin d'être annulé : il est correct sans le script.

---

## 7. Archivage (fin du chantier)

| # | Commande / action | Résultat attendu | ☑ |
|---|---|---|---|
| 1 | `git mv DOC/developer/in-progress/REALIGNEMENT_STRUCTURES_FFCK.md DOC/developer/in-progress/REALIGNEMENT_STRUCTURES_FFCK_ROLLOUT.md DOC/developer/archive/features/` | Fichiers déplacés | ☐ |
| 2 | `grep -rn "REALIGNEMENT_STRUCTURES_FFCK" --exclude-dir=node_modules --exclude-dir=vendor .` puis corriger chaque lien (DOC/README.md, ADR 0001, en-tête des deux scripts SQL, GLOSSARY.md) | Plus aucun lien vers `in-progress/` | ☐ |
| 3 | Ajouter les deux documents à [DOC/developer/archive/README.md](../archive/README.md) | Entrées présentes | ☐ |
| 4 | `git rm -r .scratch/realignement-structures-ffck/` | Tickets supprimés | ☐ |
| 5 | Commit sur une branche, PR vers `main` (`make pr_create`) | PR mergée | ☐ |
