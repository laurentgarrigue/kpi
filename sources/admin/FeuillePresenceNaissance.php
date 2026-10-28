<?php

include_once('../commun/MyPage.php');
include_once('../commun/MyBdd.php');
include_once('../commun/MyTools.php');

require_once('../commun/MyPDF.php');

// Liste des présents par année de naissance (saut de page par année, tri par mois)
class FeuillePresenceNaissance extends MyPage
{

    function __construct()
    {
        parent::__construct();

        $myBdd = new MyBdd();

        $codeCompet = utyGetSession('codeCompet');
        $codeCompet = utyGetGet('Compet', $codeCompet);
        $codeCompet = utyGetGet('compet', $codeCompet); // App4 uses 'compet'
        $codeSaison = $myBdd->GetActiveSaison();
        $codeSaison = utyGetGet('S', $codeSaison);
        $codeSaison = utyGetGet('season', $codeSaison); // App4 uses 'season'

        // Chargement des équipes ...
        $arrayEquipe = array();
        $arrayJoueur = array();
        $arrayCompetition = array();

        if (strlen($codeCompet) > 0) {
            $sql = "SELECT Id, Libelle, Code_club, Numero
                FROM kp_competition_equipe
                WHERE Code_compet = ?
                AND Code_saison = ?
                ORDER BY Libelle, Id ";
            $result = $myBdd->pdo->prepare($sql);
            $result->execute(array($codeCompet, $codeSaison));
            $num_results = $result->rowCount();
            if ($num_results == 0) {
                die('Aucune équipe dans cette compétition');
            }
            $resultarray = $result->fetchAll(PDO::FETCH_ASSOC);

            foreach ($resultarray as $key => $row) {
                $arrayEquipe[] = $row['Id'];
            }

            // Chargement des Coureurs ...
            if (count($arrayEquipe) > 0) {
                $in = str_repeat('?,', count($arrayEquipe) - 1) . '?';
                // Tri : année de naissance (saut de page), puis mois de naissance
                $sql2 = "SELECT a.Matric, a.Nom, a.Prenom, a.Sexe, a.Categ, a.Numero, a.Capitaine,
                    ce.Libelle NomEquipe, b.Origine, b.Numero_club, b.Naissance,
                    LEFT(b.Naissance, 4) AS AnneeNaissance, SUBSTRING(b.Naissance, 6, 2) AS MoisNaissance
                    FROM kp_competition_equipe ce, kp_competition_equipe_joueur a
                    LEFT OUTER JOIN kp_licence b ON (a.Matric = b.Matric)
                    WHERE a.Id_Equipe IN ($in)
                    AND ce.Id = a.Id_Equipe
                    ORDER BY AnneeNaissance, MoisNaissance, a.Nom, a.Prenom ";
                $result2 = $myBdd->pdo->prepare($sql2);
                $result2->execute($arrayEquipe);
                $num_results2 = $result2->rowCount();
                $arrayJoueur = array();

                while ($row2 = $result2->fetch()) {
                    $numero = $row2['Numero'];
                    if (strlen($numero) == 0) {
                        $numero = 0;
                    }

                    $capitaine = $row2['Capitaine'];
                    if (strlen($capitaine) == 0) {
                        $capitaine = '-';
                    }

                    if ($row2['Origine'] != $codeSaison) {
                        $row2['Origine'] = ' (' . $row2['Origine'] . ')';
                    } else {
                        $row2['Origine'] = '';
                    }

                    array_push($arrayJoueur, array(
                        'Matric' => $row2['Matric'], 'Nom' => mb_strtoupper($row2['Nom']), 'Prenom' => mb_convert_case(strtolower($row2['Prenom']), MB_CASE_TITLE, "UTF-8"),
                        'Sexe' => $row2['Sexe'], 'Categ' => $row2['Categ'], 'Numero' => $numero, 'Capitaine' => $capitaine,
                        'Saison' => $row2['Origine'], 'Numero_club' => $row2['Numero_club'], 'NomEquipe' => $row2['NomEquipe'],
                        'Naissance' => $row2['Naissance'], 'AnneeNaissance' => $row2['AnneeNaissance'], 'MoisNaissance' => $row2['MoisNaissance'],
                        'nbJoueurs' => $num_results2
                    ));
                }
            } else {
                die('Aucune équipe');
            }
        } else {
            die('Aucune compétition sélectionnée');
        }

        // Chargement des infos de la compétition
        $arrayCompetition = $myBdd->GetCompetition($codeCompet, $codeSaison);
        if (($arrayCompetition['Titre_actif'] ?? '') == 'O') {
            $titreCompet = $arrayCompetition['Libelle'];
        } else {
            $titreCompet = $arrayCompetition['Soustitre'] ?? '';
        }
        if (($arrayCompetition['Soustitre2'] ?? '') != '') {
            $titreCompet .= ' - ' . $arrayCompetition['Soustitre2'];
        }

        $visuels = utyGetVisuels($arrayCompetition, TRUE);

        // Noms des mois (index 1..12)
        $moisNoms = array(
            1 => 'Janvier', 2 => 'Février', 3 => 'Mars', 4 => 'Avril', 5 => 'Mai', 6 => 'Juin',
            7 => 'Juillet', 8 => 'Août', 9 => 'Septembre', 10 => 'Octobre', 11 => 'Novembre', 12 => 'Décembre'
        );

        // Entête PDF avec MyPDF (mPDF wrapper)
        $pdf = new MyPDF('L');
        $pdf->SetTitle("Feuilles de presence par annee de naissance");
        $pdf->SetAuthor("Kayak-polo.info");
        $pdf->SetCreator("Kayak-polo.info avec mPDF");

        $yStart = 10;

        $lastAnnee = '';
        for ($i = 0; $i < $num_results2; $i++) {
            if ($lastAnnee != $arrayJoueur[$i]['AnneeNaissance']) {

                $pdf->SetTopMargin($yStart);
                $pdf->AddPage();
                $pdf->SetAutoPageBreak(false);

                // Affichage - Bandeau/Logo/Sponsor
                if (($arrayCompetition['Bandeau_actif'] ?? '') == 'O' && isset($visuels['bandeau'])) {
                    $img = redimImage($visuels['bandeau'], 297, 10, 20, 'C');
                    $pdf->Image($img['image'], $img['positionX'], 8, 0, $img['newHauteur']);
                    // KPI + Logo
                } elseif (($arrayCompetition['Kpi_ffck_actif'] ?? '') == 'O' && ($arrayCompetition['Logo_actif'] ?? '') == 'O' && isset($visuels['logo'])) {
                    $pdf->Image('../img/CNAKPI_small.jpg', 10, 10, 0, 20, 'jpg', "https://www.kayak-polo.info");
                    $img = redimImage($visuels['logo'], 297, 10, 20, 'R');
                    $pdf->Image($img['image'], $img['positionX'], 8, 0, $img['newHauteur']);
                    // KPI
                } elseif (($arrayCompetition['Kpi_ffck_actif'] ?? '') == 'O') {
                    $pdf->Image('../img/CNAKPI_small.jpg', 125, 10, 0, 20, 'jpg', "https://www.kayak-polo.info");
                    // Logo
                } elseif (($arrayCompetition['Logo_actif'] ?? '') == 'O' && isset($visuels['logo'])) {
                    $img = redimImage($visuels['logo'], 297, 10, 20, 'C');
                    $pdf->Image($img['image'], $img['positionX'], 8, 0, $img['newHauteur']);
                }
                // Sponsor
                if (($arrayCompetition['Sponsor_actif'] ?? '') == 'O' && isset($visuels['sponsor'])) {
                    $img = redimImage($visuels['sponsor'], 297, 10, 16, 'C');
                    $pdf->Image($img['image'], $img['positionX'], 184, 0, $img['newHauteur']);
                }

                // Réactiver AutoPageBreak avec marge basse adaptée
                if (($arrayCompetition['Sponsor_actif'] ?? '') == 'O' && isset($visuels['sponsor'])) {
                    $pdf->SetAutoPageBreak(true, 30);
                } else {
                    $pdf->SetAutoPageBreak(true, 15);
                }
                $pdf->SetLeftMargin(10);
                $pdf->SetRightMargin(10);

                $pdf->SetY($yStart);
                $pdf->SetX(10);

                // titre
                $pdf->Ln(20);
                $pdf->SetFont('Arial', 'BI', 12);
                $pdf->Cell(137, 8, $titreCompet, 0, 0, 'L');
                $pdf->Cell(136, 8, 'Saison ' . $codeSaison, 0, 1, 'R');
                $pdf->SetFont('Arial', 'B', 14);
                $annee = $arrayJoueur[$i]['AnneeNaissance'] != '' ? $arrayJoueur[$i]['AnneeNaissance'] : 'Inconnue';
                $pdf->Cell(273, 8, "Feuille de présence - Né(e)s en " . $annee, 0, 1, 'C');
                $pdf->Ln(10);

                $lastAnnee = $arrayJoueur[$i]['AnneeNaissance'];

                $pdf->SetFont('Arial', 'BI', 10);
                $pdf->Cell(13, 7, 'Num', 'B', 0, 'C');
                $pdf->Cell(9, 7, 'Cap', 'B', 0, 'C');
                $pdf->Cell(25, 7, 'Licence', 'B', 0, 'C');
                $pdf->Cell(55, 7, 'Nom', 'B', 0, 'C');
                $pdf->Cell(55, 7, 'Prenom', 'B', 0, 'C');
                $pdf->Cell(62, 7, 'Equipe', 'B', 0, 'C');
                $pdf->Cell(20, 7, 'Club', 'B', 0, 'C');
                $pdf->Cell(34, 7, 'Mois', 'B', 1, 'C');
                $pdf->SetFont('Arial', '', 10);
            }

            $mois = (int) $arrayJoueur[$i]['MoisNaissance'];
            $moisLibelle = ($mois >= 1 && $mois <= 12) ? $moisNoms[$mois] : '';

            $pdf->Cell(13, 7, $arrayJoueur[$i]['Numero'], 'B', 0, 'C');
            $pdf->Cell(9, 7, $arrayJoueur[$i]['Capitaine'], 'B', 0, 'C');
            $pdf->Cell(25, 7, $arrayJoueur[$i]['Matric'] . $arrayJoueur[$i]['Saison'], 'B', 0, 'C');
            $pdf->Cell(55, 7, $arrayJoueur[$i]['Nom'], 'B', 0, 'C');
            $pdf->Cell(55, 7, $arrayJoueur[$i]['Prenom'], 'B', 0, 'C');
            $pdf->Cell(62, 7, $arrayJoueur[$i]['NomEquipe'], 'B', 0, 'C');
            $pdf->Cell(20, 7, $arrayJoueur[$i]['Numero_club'], 'B', 0, 'C');
            $pdf->Cell(34, 7, $moisLibelle, 'B', 1, 'C');
        }
        $pdf->Output('Présences par année de naissance.pdf', \Mpdf\Output\Destination::INLINE);
    }
}

$page = new FeuillePresenceNaissance();
