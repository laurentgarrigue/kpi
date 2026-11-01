# Rubrique Éditorial — gérer le contenu du nouveau site public

**Statut** : ✅ En production (17/10/2026) — site public servi sur `beta.kayak-polo.info` jusqu'à la bascule de `www`
**Accès** : Administration (app4) › **Admin › Éditorial**
**Specs** : [FEATURE_CMS.md](../specs/public/FEATURE_CMS.md), [FEATURE_FORMS.md](../specs/public/FEATURE_FORMS.md),
[PAGE_CMS.md](../specs/PAGE_CMS.md)

La rubrique Éditorial remplace l'administration WordPress : actualités, pages, médias, menu « Le kayak-polo », page
d'accueil et formulaires d'inscription du nouveau site public sont gérés depuis l'Administration KPI.

---

## Qui peut faire quoi ?

| Action | Rédacteur | Profils 1–2 |
|---|:---:|:---:|
| Articles, pages, médias, formulaires et inscriptions | ✅ | ✅ |
| Menu « Le kayak-polo », blocs d'accueil, redirections | ❌ | ✅ |
| Supprimer un article, une page, un média | ❌ | ✅ |

Le droit **Rédacteur** s'attribue dans la fiche d'un utilisateur (Admin › Utilisateurs) : case **« Rédacteur du site
public »** (profils 1–2 seulement). Le menu Admin affiche alors la rubrique Éditorial.

---

## Articles (actualités)

1. **Éditorial › Articles › Nouvel article**.
2. Onglets **FR / EN** : titre, chapô et corps (barre d'outils : titres, gras, listes, liens, citation, image de la
   médiathèque, tableau, vidéo YouTube / Vimeo par son adresse). La version anglaise est facultative : à défaut, le
   texte français est affiché.
3. Panneau de droite :
   - **catégories** (au moins une pour publier) : Équipes de France ; Compétitions nationales ; Compétitions
     régionales, open et internationales ; Règlements et vie fédérale ;
   - **signature** (texte libre, par défaut « La CNA Kayak-Polo ») ;
   - **image de une** ;
   - **liens** vers des compétitions, événements ou clubs : l'article apparaît aussi sur leurs pages ;
   - **date de publication** : une date future programme l'article ;
   - **SEO** (titre et description pour les moteurs de recherche).
4. **Prévisualiser** ouvre l'article tel qu'il sera publié (lien valable 15 minutes), puis **Publier**.

Le site public est mis à jour **immédiatement** à chaque publication. Un brouillon est sauvegardé automatiquement dans
le navigateur toutes les 30 secondes et proposé à la réouverture.

## Pages

Mêmes outils que les articles, sans catégories ni signature, avec une **page parente** : l'adresse suit
l'arborescence (`/le-kayak-polo/arbitrage`). Certaines adresses sont réservées aux pages du site (`calendar`,
`news`, `teams`…) et refusées.

## Médiathèque

- Envoi par glisser-déposer : JPEG, PNG, WebP, PDF (20 Mo maximum). Les photos sont redimensionnées
  automatiquement et leurs métadonnées (position GPS…) supprimées.
- **Texte alternatif** obligatoire pour une image (accessibilité).
- **Remplacer le fichier** garde la même adresse : les articles qui l'utilisent sont mis à jour.
- **Albums** : les médias utilisés par les articles et les pages sont rangés automatiquement dans les albums
  « Articles » et « Pages ».

## Menu, accueil, redirections (profils 1–2)

- **Menu « Le kayak-polo »** : jusqu'à **trois niveaux** (entrée, sous-entrée, sous-sous-entrée), chaque entrée
  menant à une page, un article ou une adresse ; ordre avec les flèches haut / bas.
- **Accueil** : bandeau d'alerte (information ou important, avec dates d'affichage), articles à la une, dernières
  actualités, partenaires ; chaque bloc s'active et s'ordonne.
- **Redirections** : anciennes adresses (notamment celles de WordPress) renvoyées vers les nouvelles pages.

## Formulaires d'inscription

1. **Éditorial › Formulaires › Nouveau** : titre, introduction, événement ou compétition rattachés (le formulaire
   est alors proposé sur leur page), champs (texte, e-mail, téléphone, nombre, date, listes, club, compétition…).
2. **Ouverture** : dates, nombre de places, liste d'attente, confirmation **automatique** ou **manuelle** (ex. après
   réception du paiement), lien de paiement (HelloAsso…), e-mails des organisateurs.
3. **Liste publique** (facultative) : seules les colonnes non personnelles choisies sont publiées, jamais e-mail ni
   téléphone.
4. **Inscriptions** : confirmer (un e-mail part au déclarant), mettre en attente, annuler ; **export** CSV / Excel ;
   journal des e-mails avec renvoi d'un envoi en échec.

Les inscriptions sont supprimées automatiquement à la fin de leur durée de conservation (RGPD) ; le nombre
d'inscrits est conservé.

---

## Questions fréquentes

- **Je ne vois pas la rubrique Éditorial** : demandez le droit Rédacteur à un administrateur (profil 1–2).
- **Mon article n'apparaît pas** : vérifiez son statut (publié), sa date de publication (pas dans le futur) et
  qu'il a au moins une catégorie.
- **Une entrée de menu n'apparaît pas** : sa cible (page ou article) n'est pas publiée.
