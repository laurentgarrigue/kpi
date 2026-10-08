# Page « Compétitions et résultats »

**Phase** : 2 — **Statut** : ✅ Validée (08/10/2026, retours intégrés) — **Routes** : `/competitions` → `/competitions/{season}?group={code}`
(+ `/en/…`) — **Remplace** : `kpclassements.php` — **Entrée de menu** : `competitions-list` (passe à `ready: true`)

## 1. Objectif

Point d'entrée des résultats : choisir une saison et un groupe de compétitions (par exemple « Nationale 1
Hommes »), voir d'un coup d'œil les classements de chaque compétition du groupe, et rejoindre la page d'une
compétition.

## 2. Contenu et comportement

1. **`/competitions`** redirige (302) vers `/competitions/{saison active}` (langue conservée).
2. **Titre** `<h1>` : « Compétitions et résultats {saison} ».
3. **Sélecteurs** (formulaire GET, fonctionne sans JavaScript ; avec JavaScript, la page se met à jour au changement) :
   - **Saison** : liste de `GET /seasons` ; changer de saison conserve le groupe s'il existe dans la nouvelle saison ;
   - **Groupe** : groupes de `GET /groups/{season}`, rangés par **section** (`<optgroup>` : Compétitions
     internationales, nationales, régionales, Tournois internationaux, Continents), libellé `libelle_en` en anglais
     s'il est renseigné, sinon `libelle`.
   - **Groupe par défaut** (paramètre `group` absent ou inconnu pour la saison) : le premier groupe de la section
     « Compétitions nationales », sinon le premier groupe de la liste.
4. **Événement du groupe** (`events` de `GET /group/{season}/{group}/competitions`, API_PUBLIC_RESULTS.md § 5.6) :
   - si un événement couvre **toutes** les journées publiées du groupe (`share = 1`), un encart bien visible en tête
     de liste : logo, « {groupe} se déroule lors de l'événement {libellé} ({lieu}, {dates}) » et bouton
     **« Voir l'événement »** → `/events/{id}` ;
   - sinon, s'il existe des événements liés, une ligne discrète « Événements : {libellé 1}, {libellé 2}… »
     (liens vers `/events/{id}`, 5 au plus, par `share` décroissant) ;
   - aucun événement lié : rien.
5. **Liste des compétitions du groupe**, une carte par compétition :
   - visuel (bandeau, sinon logo), titre (`display_title`), badges **type** (Championnat / Coupe / Multi) et
     **statut** (À venir / En cours / Terminé) ;
   - **classement compact** : rang, logo et nom d'équipe, points, matchs joués. Au-delà de 8 équipes, les 8
     premières sont affichées et un bouton « Voir tout » déplie le reste ;
   - **médailles** : si la compétition est terminée et du tour final, les 3 premiers portent une médaille
     or / argent / bronze (`medal` d'api2, couleurs `gold` et dérivées, + texte « Médaille d'or »…) **à la place**
     de la marque « qualifié » ;
   - sinon, les `qualified` premiers rangs sont marqués « qualifié » (vert, + texte accessible), les `eliminated`
     derniers « relégué/éliminé » (rouge, + texte accessible) ;
   - pas encore de classement : « Classement non disponible » ;
   - lien principal vers la page de la compétition (`/competitions/{season}/{code}`).
6. **Lien « Tous les matchs du groupe »** → `/groups/{season}/{group}/games` (PAGE_EVENT_GROUP.md).
7. **Groupe sans compétition publiée** pour la saison : message « Aucune compétition publiée pour ce groupe. »

## 3. Données

| Besoin | Endpoint |
|---|---|
| Saisons, saison active | `GET /seasons` *(nouveau)* |
| Groupes par section | `GET /groups/{season}` *(existant, app2)* |
| Compétitions, classements compacts, événements liés | `GET /group/{season}/{code}/competitions` *(nouveau)* |

Cache : `routeRules` `cache: { maxAge: 300, swr: true }` sur `/competitions/**` (hors pages de compétition, cf.
PAGE_COMPETITION.md) — les classements changent au plus après chaque journée.

## 4. SEO et accessibilité

- Titre : « Compétitions et résultats {saison} — kayak-polo.info » ; description : « Classements des
  compétitions de kayak-polo {groupe}, saison {saison}. »
- URL canonique avec le paramètre `group` (une page indexable par groupe et par saison après la bascule).
- Sélecteurs avec `<label>` visibles ; bouton « Afficher » présent sans JavaScript.
- Les marques qualifié / éliminé ne reposent pas sur la seule couleur.

## 5. Critères d'acceptation

- **CPL-01** — `/competitions` redirige (302) vers la saison active, et `/en/competitions` vers `/en/competitions/{saison}`.
- **CPL-02** — Sans paramètre `group`, le groupe par défaut est le premier de la section nationale (sinon le premier).
- **CPL-03** — Les groupes sont regroupés par section, dans l'ordre d'api2, avec le libellé anglais sous `/en` s'il existe.
- **CPL-04** — Une carte par compétition, dans l'ordre d'api2, avec titre, badges type/statut et lien vers sa page.
- **CPL-05** — Le classement compact affiche au plus 8 équipes, puis « Voir tout » déplie le reste.
- **CPL-06** — Les rangs qualifiés et éliminés sont marqués visuellement **et** textuellement.
- **CPL-07** — Compétition sans classement → « Classement non disponible » ; groupe vide → message dédié.
- **CPL-08** — Le formulaire de sélection fonctionne sans JavaScript (requête GET).
- **CPL-09** — L'entrée de menu « Compétitions et résultats » et le bouton de l'accueil pointent vers `/competitions`.
- **CPL-10** — Compétition terminée du tour final : les rangs 1 à 3 portent une médaille (visuelle **et** textuelle) au lieu de la marque « qualifié ».
- **CPL-11** — Un événement couvrant tout le groupe est mis en avant avec un bouton vers `/events/{id}` ; sinon les événements liés sont listés discrètement ; aucun → rien.

## 6. Hors périmètre

- Historique multi-saisons d'une compétition : `/history` (phase 3).
- Liens PDF : sur la page de chaque compétition (PAGE_COMPETITION.md), pas sur cette liste.
