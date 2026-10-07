# Bug SQL : Paramètre Compet=* avec PHP 8.3

## 🐛 Symptôme

**Fichier affecté** : `PdfListeMatchs.php` (et potentiellement d'autres fichiers PDF similaires)

**Comportement** :
- ✅ **PHP 7.4** : Le PDF affiche tous les matchs de l'événement
- ❌ **PHP 8.3** : Le PDF est vide (seulement titre + QRcode, pas de données)

**URL de test** : `PdfListeMatchs.php?S=2024&idEvenement=197&Group=N1H&Compet=*&Journee=*`

## 🔍 Cause du Bug

### Code Problématique (Ligne 63-66)

```php
$laCompet = utyGetGet('Compet', $laCompet);  // Récupère "*"

if ($laCompet != 0) {  // ⚠️ "*" != 0 évalue à TRUE en PHP!
    $arrayJournees = [];  // Vide le tableau des 77 journées
    $idEvenement = -1;     // Réinitialise l'événement
}
```

### Séquence d'Erreur

1. **Ligne 46-55** : Récupération des journées de l'événement
   ```php
   if (utyGetGet('idEvenement', 0) > 0) {
       $arrayJournees = [];
       $sql = "SELECT Id_journee FROM kp_evenement_journee WHERE Id_evenement = ?";
       $result = $myBdd->pdo->prepare($sql);
       $result->execute(array($idEvenement));
       while ($row = $result->fetch()) {
           $arrayJournees[] = $row['Id_journee'];
       }
   }
   // À ce stade : $arrayJournees contient 77 IDs de journées ✅
   ```

2. **Ligne 62** : Récupération du paramètre `Compet`
   ```php
   $laCompet = utyGetGet('Compet', $laCompet);  // $laCompet = "*"
   ```

3. **Ligne 63** : Test buggué
   ```php
   if ($laCompet != 0) {  // "*" != 0 est TRUE!
       $arrayJournees = [];  // ❌ Vide les 77 journées!
       $idEvenement = -1;
   }
   ```

4. **Ligne 84-92** : Construction de la requête SQL
   ```php
   if (count($arrayJournees) == 0) {  // TRUE car vidé!
       $sql .= "AND d.Code_competition = ? AND d.Code_saison = ?";
       $arrayQuery = array($laCompet, $codeSaison);
       // Cherche Code_competition = "*" → Aucun résultat!
   } else {
       // Cette branche aurait utilisé les 77 journées
       $in = str_repeat('?,', count($arrayJournees) - 1) . '?';
       $sql .= "AND a.Id_journee IN ($in)";
       $arrayQuery = $arrayJournees;
   }
   ```

5. **Résultat** : La requête cherche `Code_competition = '*'` qui n'existe pas → 0 résultats

## ✅ Solution

### Code Corrigé

```php
$laCompet = utyGetGet('Compet', $laCompet);

// Ne vider $arrayJournees que si $laCompet est une VRAIE compétition
// Pas *, pas 0, pas vide
if ($laCompet != 0 && $laCompet != '*' && $laCompet != '') {
    $arrayJournees = [];
    $idEvenement = -1;
}

$codeCompet = $laCompet;
```

### Logique Corrigée

| Paramètre `Compet` | `idEvenement` fourni | Comportement |
|-------------------|---------------------|--------------|
| `*` (astérisque) | OUI (197) | ✅ Utilise les journées de l'événement |
| `0` | OUI (197) | ✅ Utilise les journées de l'événement |
| `""` (vide) | OUI (197) | ✅ Utilise les journées de l'événement |
| `N1H` (code réel) | OUI (197) | ✅ Ignore l'événement, filtre par compétition N1H |
| `*` | NON | ⚠️ Dépend de la logique métier (à vérifier) |

## 🔎 Pourquoi Différent entre PHP 7.4 et 8.3 ?

La comparaison faible `!=` fonctionne de la même manière en PHP 7.4 et 8.3, **MAIS** :

### Hypothèse 1 : Ordre d'Exécution des Paramètres GET
Peut-être qu'en PHP 7.4, l'ordre de traitement des paramètres GET était différent, ou bien une autre variable de session/cookie influençait le résultat.

### Hypothèse 2 : Différence de Session
Si `utyGetSession('codeCompet', 0)` retournait une valeur différente en PHP 7.4 vs 8.3, cela pourrait expliquer la différence.

### Hypothèse 3 : Configuration PHP.ini
Des paramètres comme `register_globals` (obsolète) ou d'autres configurations pourraient avoir influencé.

### Ce qui est SÛR :
Le code **était déjà bugué** en PHP 7.4, mais le bug ne se manifestait peut-être pas avec les mêmes paramètres ou contexte. La correction rend le code plus robuste pour **les deux versions**.

## 📋 Fichiers Potentiellement Affectés

Rechercher dans tous les fichiers PDF ce pattern :

```bash
grep -n "if.*!= 0" sources/Pdf*.php
```

### Fichiers à Vérifier (Liste des 43 fichiers PDF)

Priorité HAUTE (similaires à PdfListeMatchs.php) :
- [ ] PdfListeMatchs.php ✅ **CORRIGÉ**
- [ ] PdfMatchMulti.php (à vérifier si même logique)
- [ ] PdfClassementpoule.php
- [ ] PdfCalendrier.php
- [ ] PdfPresence.php
- [ ] Tous les autres Pdf*.php qui acceptent des paramètres GET

### Pattern de Recherche

Chercher ces lignes dans tous les fichiers PDF :

```php
// Pattern bugué
$laCompet = utyGetGet('Compet', ...);
if ($laCompet != 0) {  // ⚠️ ATTENTION!
    $arrayJournees = [];
}
```

### Comment Détecter

1. **Rechercher** : `if.*Compet.*!= 0`
2. **Vérifier** : Si le test ne vérifie PAS explicitement `*`, `''`, ou autres valeurs spéciales
3. **Corriger** : Ajouter les vérifications explicites

## 🧪 Test de Validation

### Script de Test

```php
<?php
// Test comparaison faible PHP
$values = ['*', '0', 0, '', null, 'N1H'];

foreach ($values as $val) {
    $result = ($val != 0);
    echo var_export($val, true) . " != 0 : " . ($result ? "TRUE" : "FALSE") . "\n";
}
```

### Résultat Attendu (PHP 7.4 et 8.3)

```
'*' != 0 : TRUE     ← Problème!
'0' != 0 : FALSE
0 != 0 : FALSE
'' != 0 : FALSE
NULL != 0 : FALSE
'N1H' != 0 : TRUE   ← OK (vraie compétition)
```

## 📝 Recommandations pour Migration

### Pour Chaque Fichier PDF Migré

1. **Rechercher** le pattern :
   ```php
   if ($variable != 0)
   ```

2. **Analyser** : Est-ce que `$variable` peut contenir `*` ou d'autres valeurs spéciales ?

3. **Corriger** si nécessaire :
   ```php
   if ($variable != 0 && $variable != '*' && $variable != '')
   ```

4. **Tester** avec les paramètres GET typiques :
   - `?Compet=*&idEvenement=X`
   - `?Compet=CODE&idEvenement=X`
   - `?Compet=0&idEvenement=X`

5. **Documenter** dans les notes de migration si correction appliquée

## 🎯 Checklist Migration PDF

Ajouter cette étape à la checklist de migration :

- [ ] Vérifier les comparaisons `!= 0` avec des paramètres GET
- [ ] Tester avec `Compet=*` en PHP 7.4 ET 8.3
- [ ] S'assurer que `$arrayJournees` n'est pas vidé par erreur
- [ ] Valider que la requête SQL retourne des données

## 📚 Références

- **Fichier corrigé** : [PdfListeMatchs.php](../../../../sources/PdfListeMatchs.php) ligne 64
- **Issue GitHub** : (à créer si nécessaire)
- **Date découverte** : 2025-10-20
- **Contexte** : Migration FPDF → mPDF, test avec PHP 8.3

---

**Note** : Ce bug n'est PAS lié à mPDF, mais a été découvert pendant les tests de migration. Il affecte potentiellement plusieurs fichiers PDF et mérite d'être documenté pour éviter de le reproduire.
