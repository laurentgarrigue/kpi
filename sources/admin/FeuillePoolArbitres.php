<?php

include_once('../commun/MyPage.php');
include_once('../commun/MyBdd.php');
include_once('../commun/MyTools.php');
require_once('../commun/MyPDF.php');

/**
 * Liste des arbitres ACTIFS du pool global
 * (kp_competition_equipe Code_compet='POOL' / Code_saison='1000').
 *
 * Triée par groupe (nation) puis par ordre alphabétique (Nom, Prénom).
 * Seuls les arbitres actifs (Capitaine='A') sont listés.
 */
class FeuillePoolArbitres extends MyPage
{
    private const POOL_COMPET = 'POOL';
    private const POOL_SAISON = '1000';

    function __construct()
    {
        parent::__construct();

        $myBdd = new MyBdd();

        // Langue active (transmise par app4 via ?lang=fr|en, fr par défaut).
        $langue = parse_ini_file("../commun/MyLang.ini", true);
        $lang = (utyGetGet('lang') === 'en') ? $langue['en'] : $langue['fr'];

        // Groupes (nations) du pool, triés par libellé.
        $sqlGroups = "SELECT Id, Libelle, Code_club
                      FROM kp_competition_equipe
                      WHERE Code_compet = ? AND Code_saison = ?
                      ORDER BY Libelle ASC";
        $stmt = $myBdd->pdo->prepare($sqlGroups);
        $stmt->execute(array(self::POOL_COMPET, self::POOL_SAISON));
        $groups = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Arbitres actifs par groupe, triés alphabétiquement.
        $sqlRefs = "SELECT j.Nom, j.Prenom, j.Sexe, a.arbitre, a.niveau
                    FROM kp_competition_equipe_joueur j
                    LEFT JOIN kp_arbitre a ON j.Matric = a.Matric
                    WHERE j.Id_equipe = ? AND j.Capitaine = 'A'
                    ORDER BY j.Nom ASC, j.Prenom ASC";
        $stmtRefs = $myBdd->pdo->prepare($sqlRefs);

        // Création PDF (portrait).
        $pdf = new MyPDF('P');
        $pdf->SetTitle($lang['Pool_Arbitres']);
        $pdf->SetAuthor("Kayak-polo.info");
        $pdf->SetCreator("Kayak-polo.info avec mPDF");

        $pdf->SetAutoPageBreak(false);
        $pdf->AddPage();

        // Logo KPI en entête.
        $pdf->Image('../img/CNAKPI_small.jpg', 10, 10, 0, 16, 'jpg', "https://www.kayak-polo.info");

        $pdf->SetLeftMargin(10);
        $pdf->SetRightMargin(10);

        // Footer HTML : pagination à gauche, horodatage à droite.
        $datetime = (utyGetGet('lang') === 'en')
            ? utyGetPrintDatetime()->format('Y-m-d H:i')
            : utyGetPrintDatetime()->format('d/m/Y à H:i');
        $footerHTML = '<table width="100%" style="font-family:Arial;font-size:8pt;font-style:italic;margin-top:2mm;"><tr>'
            . '<td width="50%" align="left">Page {PAGENO}</td>'
            . '<td width="50%" align="right">' . $datetime . '</td>'
            . '</tr></table>';
        $pdf->SetHTMLFooter($footerHTML);

        $pdf->SetAutoPageBreak(true, 15);

        // Titre.
        $yStart = 30;
        $pdf->SetY($yStart);
        $pdf->SetX(10);
        $pdf->SetFont('Arial', 'B', 16);
        $pdf->Cell(190, 8, $lang['Pool_Arbitres'], 0, 1, 'C');
        $pdf->Ln(4);

        $total = 0;

        foreach ($groups as $group) {
            $stmtRefs->execute(array($group['Id']));
            $refs = $stmtRefs->fetchAll(PDO::FETCH_ASSOC);

            // On n'affiche que les groupes ayant au moins un arbitre actif.
            if (count($refs) === 0) {
                continue;
            }

            // Eviter qu'un titre de groupe se retrouve seul en bas de page.
            if ($pdf->y > 250) {
                $pdf->AddPage();
            }

            // Titre du groupe (nation).
            $pdf->Ln(2);
            $pdf->SetFont('Arial', 'BI', 13);
            $pdf->SetFillColor(235, 235, 235);
            $pdf->Cell(190, 7, $group['Libelle'], 0, 1, 'L', true);
            $pdf->Ln(1);

            // Entête de colonnes.
            $pdf->SetFont('Arial', 'B', 9);
            $pdf->Cell(80, 6, $lang['Nom'], 'B', 0, 'L');
            $pdf->Cell(70, 6, $lang['Prenom'], 'B', 0, 'L');
            $pdf->Cell(15, 6, $lang['Sexe'], 'B', 0, 'C');
            $pdf->Cell(25, 6, $lang['Arbitrage'], 'B', 1, 'C');

            // Lignes.
            $pdf->SetFont('Arial', '', 10);
            foreach ($refs as $ref) {
                $nom = mb_strtoupper((string) $ref['Nom']);
                $prenom = mb_convert_case(mb_strtolower((string) $ref['Prenom']), MB_CASE_TITLE, 'UTF-8');

                $arbLabel = '';
                if (!empty($ref['arbitre'])) {
                    $arbLabel = strtoupper((string) $ref['arbitre']);
                    if (!empty($ref['niveau'])) {
                        $arbLabel .= '-' . $ref['niveau'];
                    }
                }

                $pdf->Cell(80, 6, $nom, 0, 0, 'L');
                $pdf->Cell(70, 6, $prenom, 0, 0, 'L');
                $pdf->Cell(15, 6, $ref['Sexe'] ?? '', 0, 0, 'C');
                $pdf->Cell(25, 6, $arbLabel, 0, 1, 'C');
                $total++;
            }

            $pdf->Ln(3);
        }

        if ($total === 0) {
            $pdf->SetFont('Arial', 'I', 11);
            $pdf->Cell(190, 8, $lang['Aucun_arbitre_actif'], 0, 1, 'C');
        }

        $pdf->Output($lang['Pool_Arbitres'] . '.pdf', \Mpdf\Output\Destination::INLINE);
    }
}

$page = new FeuillePoolArbitres();
