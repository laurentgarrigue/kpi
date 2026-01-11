# Calculateur de distances kilométriques des équipes

## Fonctionnalité

Cette fonctionnalité permet de calculer et exporter les distances routières entre les clubs des équipes engagées dans une compétition, ainsi que le kilométrage total prévu pour chaque équipe en fonction des lieux des différentes journées.

## Accès

Depuis la page **Gestion des équipes** (`GestionEquipe.php`), cliquez sur le bouton **Km** (icône ODS) dans la barre d'outils.

![Bouton Km](../img/ods.png) Km

## Export Excel (ODS)

Le fichier généré contient deux feuilles :

### Feuille 1 : Kilométrage par équipe

| Colonne | Description |
|---------|-------------|
| Équipe | Nom de l'équipe |
| Club | Nom du club |
| Code Club | Code du club (4 caractères) |
| Total km (A/R) | Distance totale aller-retour pour toutes les journées |
| Nb déplacements | Nombre de journées avec déplacement |
| Journée 1, 2, ... | Distance aller vers chaque lieu de journée |

### Feuille 2 : Matrice des distances inter-clubs

Tableau croisé montrant la distance entre chaque paire de clubs de la compétition.

## Calcul des distances

### Avec API OpenRouteService (recommandé)

Les distances sont calculées via l'API [OpenRouteService](https://openrouteservice.org/) qui fournit des distances routières réelles.

**Configuration :**
1. Créer un compte gratuit sur https://openrouteservice.org/dev/#/signup
2. Obtenir une clé API (2000 requêtes/jour gratuites)
3. Ajouter la clé dans `sources/commun/MyConfig.php` :
   ```php
   define('ORS_API_KEY', 'votre-cle-api');
   ```

### Sans API (mode dégradé)

Si la clé API n'est pas configurée, les distances sont calculées à vol d'oiseau avec un facteur de correction de 1.3 (approximation des routes françaises).

## Source des coordonnées

### Clubs

Les coordonnées sont recherchées dans l'ordre suivant :
1. Champ `Coord` de la table `kp_club` (format "lat,lon")
2. Matching de l'adresse postale avec la table `villes_france_free`

### Lieux des journées

Le champ `Lieu` de `kp_journee` est matché automatiquement avec la table `villes_france_free` pour obtenir les coordonnées GPS.

## Limitations

- **Compétitions internationales** : L'export n'est pas disponible pour les compétitions dont toutes les équipes appartiennent à des clubs internationaux (code_comite_reg = '98')
- **Coordonnées manquantes** : Les équipes ou journées sans coordonnées valides affichent "N/A"
- **Précision** : Les distances sont arrondies à l'entier

## Exemple de résultat

```
Équipe          | Club      | Total km | J1      | J2      | J3
----------------|-----------|----------|---------|---------|--------
Lyon KP 1       | LYKP      | 850 km   | 0 (local)| 320 km | 105 km
Paris KP        | PAKP      | 1200 km  | 320 km  | 0 (local)| 280 km
Strasbourg KP   | SBKP      | 980 km   | 450 km  | 280 km  | 0 (local)
```

## Améliorer les données

### Ajouter les coordonnées d'un club

1. Aller dans **Gestion des structures** (`GestionStructure.php`)
2. Sélectionner le club
3. Remplir le champ **Coordonnées GPS** au format `latitude,longitude`
   - Exemple : `45.7640,4.8357` pour Lyon

### Trouver les coordonnées d'une ville

Utiliser :
- Google Maps : clic droit sur un lieu → "Afficher les coordonnées"
- https://www.latlong.net/
- https://www.coordonnees-gps.fr/
