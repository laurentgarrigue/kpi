<?php

/**
 * AdminChoice.php
 *
 * Page de choix entre l'administration "Admin1" (legacy) et la nouvelle
 * administration "Admin2" (Beta, app4 / Nuxt).
 *
 * Objectif : éviter les confusions entre les deux versions. Lorsqu'elle sera
 * activée, le lien "Administration" du site public legacy pointera vers cette
 * page (au lieu de pointer directement vers admin/GestionCompetition.php),
 * laissant l'utilisateur choisir l'interface à utiliser.
 *
 * Pour activer : remplacer dans commun/MyPage.php le menu public
 *   'href' => 'admin/GestionCompetition.php'
 * par
 *   'href' => 'AdminChoice.php'
 *
 * Page volontairement autonome (HTML + Bootstrap 5, sans Smarty) pour rester
 * simple et indépendante.
 */

if (!isset($_SESSION)) {
    session_start();
}

// Langue : fr (défaut) ou en, via ?lang= ou la session
$lang = isset($_GET['lang']) ? $_GET['lang'] : (isset($_SESSION['lang']) ? $_SESSION['lang'] : 'fr');
if ($lang !== 'en') {
    $lang = 'fr';
}
$_SESSION['lang'] = $lang;

// Cibles
$URL_ADMIN1 = 'admin/GestionCompetition.php'; // Admin1 - legacy (PHP)
$URL_ADMIN2 = '/admin2/';                      // Admin2 - nouvelle interface (Beta)

// Libellés
$t = [
    'fr' => [
        'page_title'   => 'Administration — Choix de la version',
        'heading'      => 'Quelle administration souhaitez-vous utiliser ?',
        'intro'        => 'Deux interfaces d\'administration coexistent actuellement. Choisissez celle que vous voulez ouvrir. En cas de doute, utilisez l\'administration classique (Admin1).',
        'admin1_badge' => 'Version actuelle',
        'admin1_title' => 'Admin1 — Administration classique',
        'admin1_desc'  => 'L\'interface historique, complète et stable. Toutes les fonctionnalités habituelles y sont disponibles (compétitions, équipes, athlètes, journées, matchs, classements, statistiques…).',
        'admin1_btn'   => 'Ouvrir Admin1',
        'admin2_badge' => 'Nouveau — Beta',
        'admin2_title' => 'Admin2 — Nouvelle administration',
        'admin2_desc'  => 'La nouvelle interface modernisée. Plus rapide et plus ergonomique, elle propose de nouvelles fonctionnalités. Elle sera généralisée pour la saison 2027.',
        'admin2_btn'   => 'Ouvrir Admin2 (Beta)',
        'note'         => 'Vos identifiants de connexion sont identiques sur les deux versions.',
        'back'         => 'Retour au site public',
    ],
    'en' => [
        'page_title'   => 'Administration — Choose a version',
        'heading'      => 'Which administration do you want to use?',
        'intro'        => 'Two administration interfaces currently coexist. Choose the one you want to open. If in doubt, use the classic administration (Admin1).',
        'admin1_badge' => 'Current version',
        'admin1_title' => 'Admin1 — Classic administration',
        'admin1_desc'  => 'The original, complete and stable interface. All historical features are available (competitions, teams, athletes, game days, games, rankings, statistics…).',
        'admin1_btn'   => 'Open Admin1',
        'admin2_badge' => 'New — Beta',
        'admin2_title' => 'Admin2 — New administration',
        'admin2_desc'  => 'The new modernised interface. Faster and more user-friendly, it does cover new features. It will be the only one in 2027.',
        'admin2_btn'   => 'Open Admin2 (Beta)',
        'note'         => 'Your login credentials are the same on both versions.',
        'back'         => 'Back to the public site',
    ],
];
$L = $t[$lang];

// Petites aides
function e($s)
{
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}
$qsOther = ($lang === 'fr') ? 'en' : 'fr';
?>
<!DOCTYPE html>
<html lang="<?= e($lang) ?>">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <meta name="author" content="LG" />
    <meta name="robots" content="noindex" />
    <link rel="shortcut icon" type="image/x-icon" href="favicon.ico" />
    <link href="vendor/twbs/bootstrap/dist/css/bootstrap.min.css" rel="stylesheet" type="text/css" />
    <title><?= e($L['page_title']) ?></title>
    <style>
        body {
            background: #eef1fb;
        }

        .admin-choice-wrap {
            max-width: 1040px;
        }

        .admin-card {
            transition: transform .15s ease, box-shadow .15s ease;
            border: 1px solid #e2e6f0;
        }

        .admin-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 .75rem 1.5rem rgba(30, 64, 175, .15);
        }

        .admin-card .shot {
            border-bottom: 1px solid #e9ecef;
            background: #f8f9fb;
            aspect-ratio: 1280 / 860;
            overflow: hidden;
        }

        .admin-card .shot img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            object-position: top center;
        }

        .lang-switch a {
            text-decoration: none;
        }
    </style>
</head>

<body>
    <div class="container admin-choice-wrap py-4 py-md-5">

        <div class="d-flex justify-content-end mb-2 lang-switch">
            <a href="?lang=<?= e($qsOther) ?>" class="btn btn-sm btn-outline-secondary">
                <?= $qsOther === 'en' ? 'English' : 'Français' ?>
            </a>
        </div>

        <div class="text-center mb-4">
            <img src="img/CNAKPI_small.png" alt="KPI" height="64" class="mb-3">
            <h1 class="h3 fw-bold"><?= e($L['heading']) ?></h1>
            <p class="text-muted mx-auto" style="max-width: 720px;"><?= e($L['intro']) ?></p>
        </div>

        <div class="row g-4 align-items-stretch">

            <!-- Admin1 - legacy -->
            <div class="col-12 col-md-6">
                <div class="card admin-card h-100 shadow-sm">
                    <div class="shot">
                        <img src="img/admin-choice/admin1-legacy.png" alt="<?= e($L['admin1_title']) ?>">
                    </div>
                    <div class="card-body d-flex flex-column">
                        <span class="badge bg-primary align-self-start mb-2"><?= e($L['admin1_badge']) ?></span>
                        <h2 class="h5 card-title"><?= e($L['admin1_title']) ?></h2>
                        <p class="card-text text-muted flex-grow-1"><?= e($L['admin1_desc']) ?></p>
                        <a href="<?= e($URL_ADMIN1) ?>" class="btn btn-lg btn-primary w-100 mt-2">
                            <?= e($L['admin1_btn']) ?>
                        </a>
                    </div>
                </div>
            </div>

            <!-- Admin2 - new (Beta) -->
            <div class="col-12 col-md-6">
                <div class="card admin-card h-100 shadow-sm">
                    <div class="shot">
                        <img src="img/admin-choice/admin2-new.png" alt="<?= e($L['admin2_title']) ?>">
                    </div>
                    <div class="card-body d-flex flex-column">
                        <span class="badge bg-warning text-dark align-self-start mb-2"><?= e($L['admin2_badge']) ?></span>
                        <h2 class="h5 card-title"><?= e($L['admin2_title']) ?></h2>
                        <p class="card-text text-muted flex-grow-1"><?= e($L['admin2_desc']) ?></p>
                        <a href="<?= e($URL_ADMIN2) ?>" class="btn btn-lg btn-outline-primary w-100 mt-2">
                            <?= e($L['admin2_btn']) ?>
                        </a>
                    </div>
                </div>
            </div>

        </div>

        <p class="text-center text-muted mt-4 mb-1"><small><?= e($L['note']) ?></small></p>
        <p class="text-center"><a href="./"><?= e($L['back']) ?></a></p>

    </div>
</body>

</html>
