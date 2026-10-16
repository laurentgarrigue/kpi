# Spécification - Rubrique Éditorial (site public)

## Statut : ✅ Validée (11/10/2026) — phase 4 du site public

## 1. Vue d'ensemble

Écrans d'administration du contenu du site public (app3) : articles, pages, médiathèque, menu éditorial, blocs
d'accueil, redirections et formulaires d'inscription. Ils remplacent l'administration WordPress.
Specs fonctionnelles : [FEATURE_CMS.md](public/FEATURE_CMS.md), [FEATURE_FORMS.md](public/FEATURE_FORMS.md).

**Menu** : groupe **Admin › Éditorial** du menu d'administration (même mécanisme que Référentiels, Opérations,
Live, Droits), visible si l'utilisateur a la **capacité Rédacteur** ou un profil effectif ≤ 2.

**Routes** :

| Route | Écran | Accès |
|---|---|---|
| `/cms/articles`, `/cms/articles/new`, `/cms/articles/{id}` | Liste et édition des articles | Rédacteur, ≤ 2 |
| `/cms/pages`, `/cms/pages/new`, `/cms/pages/{id}` | Liste et édition des pages | Rédacteur, ≤ 2 |
| `/cms/media` | Médiathèque et albums | Rédacteur, ≤ 2 |
| `/cms/menu` | Menu « Le kayak-polo » | ≤ 2 |
| `/cms/home` | Blocs d'accueil | ≤ 2 |
| `/cms/redirects` | Redirections 301 | ≤ 2 |
| `/cms/forms`, `/cms/forms/new`, `/cms/forms/{id}`, `/cms/forms/{id}/entries` | Formulaires et inscriptions | Rédacteur, ≤ 2 |

**Contexte de travail** : non applicable (pages globales). Droits : [DROITS_PAR_PROFIL.md](DROITS_PAR_PROFIL.md)
§ Éditorial. La capacité Rédacteur s'attribue dans la fenêtre d'édition d'un utilisateur (case « Rédacteur du
site public », profils 1–2).

## 2. Articles

- **Liste** : titre, catégories, statut (badge : brouillon, programmé, publié, archivé), date de publication,
  dernière modification ; filtres statut et catégorie, recherche dans le titre ; pagination ; bouton « Nouvel
  article ».
- **Édition** (une page, deux colonnes sur grand écran) :
  - colonne principale : onglets **FR / EN** ; titre, slug (généré depuis le titre FR, modifiable), chapô, corps
    avec l'éditeur **TipTap** (barre : titres H2–H4, gras, italique, listes, lien, citation, image de la
    médiathèque, tableau, vidéo YouTube / Vimeo par URL, annuler / rétablir) ;
  - panneau latéral : statut et date de publication (programmation), **catégories** (cases à cocher, au moins une
    pour publier), **signature** (texte libre, défaut « La CNA Kayak-Polo »), image de une, liens vers
    compétitions / événements / clubs (autocomplétions existantes d'app4), SEO (titre, description, aperçu) ;
  - actions : Enregistrer, Publier / Dépublier, **Prévisualiser** (ouvre `beta.*/news/{slug}?preview=…` dans un
    nouvel onglet), Archiver ; profils 1–2 : Supprimer.
  - brouillon local : sauvegarde automatique dans le navigateur toutes les 30 s, proposée à la réouverture.
- Erreurs de validation affichées sous les champs (slug déjà pris, catégorie manquante à la publication…).

## 3. Pages

Comme les articles, sans catégories, signature, liens ni programmation ; avec **page parente** (liste) et slug
vérifié (réservés : FEATURE_CMS.md § 6). Liste arborescente (indentation par parent).

## 4. Médiathèque

Grille de vignettes (filtre : images / PDF / album), envoi par glisser-déposer ou sélection (JPEG, PNG, WebP, PDF,
20 Mo max), fiche du média : aperçu, texte alternatif FR / EN (obligatoire pour une image), crédit, album, URL à
copier, **Remplacer le fichier** (même URL), suppression (profils 1–2, refusée si le média est utilisé).
Albums : créer, renommer, choisir la couverture. Le sélecteur de médias de l'éditeur réutilise cette grille dans
une fenêtre modale.

## 5. Menu, accueil, redirections (profils ≤ 2)

- **Menu** : arbre à **trois niveaux** (13/10/2026), chaque niveau distingué par un badge « Niveau n », un retrait
  et une couleur de filet ; entrées (libellés FR / EN, type de cible : page, article ou adresse, et destination),
  ordre par boutons monter / descendre (accessibles au clavier) ; « Ajouter une entrée de niveau n+1 » sous chaque
  entrée des niveaux 1 et 2.
- **Accueil** : blocs (à la une, dernières actualités, bandeau d'alerte, partenaires) : activer, ordonner,
  configurer (articles à la une, texte et niveau de l'alerte avec dates, partenaires : nom, logo, lien).
- **Redirections** : liste (de → vers, compteur), ajout, suppression ; celles de l'import WordPress y figurent.

## 6. Formulaires

- **Liste** : titre, état (brouillon, ouvert, clos), inscriptions (confirmées / en attente / capacité).
- **Édition** : identité (titre FR / EN, slug, introduction riche, événement / compétition rattachés), champs
  (type, libellés FR / EN, obligatoire, aide, options, bornes ; ordre monter / descendre), ouverture (dates,
  capacité, liste d'attente, confirmation automatique / manuelle), lien de paiement, e-mails (organisateurs,
  texte de confirmation), liste publique (colonnes publiées parmi les champs non personnels), RGPD (finalité,
  conservation), publication.
- **Inscriptions** : tableau (date, statut, réponses), changement de statut (confirmer → e-mail au déclarant,
  mettre en attente, annuler), **export** CSV / Excel, journal des e-mails du formulaire (avec renvoi d'un
  e-mail en échec).

## 7. Critères d'acceptation

- **ADM-CMS-01** — Le groupe « Éditorial » du menu Admin n'apparaît qu'avec la capacité Rédacteur ou un profil ≤ 2 ;
  Menu, Accueil et Redirections seulement pour les profils ≤ 2.
- **ADM-CMS-02** — Un article ne peut être publié sans titre FR, slug valide et au moins une catégorie.
- **ADM-CMS-03** — L'éditeur produit un HTML accepté tel quel par le filtre d'api2 (aucune perte de mise en forme
  autorisée).
- **ADM-CMS-04** — La prévisualisation ouvre l'article non publié sur `beta.*` ; un article publié s'ouvre sans
  jeton.
- **ADM-CMS-05** — La case « Rédacteur du site public » de la fenêtre utilisateur attribue / retire la capacité
  (profils 1–2 seulement).
- **ADM-CMS-06** — Confirmer une inscription en attente change son statut et envoie l'e-mail au déclarant.
- **ADM-CMS-07** — L'export produit un fichier avec une ligne par inscription et une colonne par champ.
- **ADM-CMS-08** — Le menu se construit sur trois niveaux au plus, chaque niveau visuellement distinct ; tous les
  libellés de l'écran sont traduits (FR / EN).
