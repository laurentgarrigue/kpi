<?php

include_once('commun/MyPage.php');
include_once('commun/MyBdd.php');
include_once('commun/MyTools.php');

require_once('commun/MyPDF.php');
// QRcode class is now autoloaded via Composer

// Liste des Matchs d'une Journee ou d'un Evenement 
class PdfQrCodeApp extends MyPage
{

  function __construct()
  {
    parent::__construct();
    // Chargement des titre ...
    $myBdd = new MyBdd();
    $idEvenement = utyGetSession('idEvenement', -1);
    $idEvenement = utyGetGet('Evt', $idEvenement);
    $libelleEvenement = $myBdd->GetEvenementLibelle($idEvenement);
    $titreEvenementCompet = 'Evénement (Event) : ' . $libelleEvenement;
    $codeSaison = $myBdd->GetActiveSaison();

    // Entête PDF ...
    $pdf = new MyPDF('L');
    $pdf->SetTitle("QR Codes");
    $pdf->SetAuthor("Kayak-polo.info");
    $pdf->SetCreator("Kayak-polo.info avec mPDF");
    $pdf->SetTopMargin(30);
    $pdf->AddPage();
    $pdf->SetAutoPageBreak(true, 15);

    // Page A4 paysage : 297mm de large, marges 15mm de chaque côté → largeur utile 267mm
    $pageWidth = 297;
    $marginLeft = 15;
    $contentWidth = $pageWidth - 2 * $marginLeft; // 267mm

    $titreDate = "Saison (Season) " . $codeSaison;
    // titre
    $pdf->SetFont('Arial', 'BI', 12);
    $pdf->Cell($contentWidth / 2, 5, $titreEvenementCompet, 0, 0, 'L');
    $pdf->Cell($contentWidth / 2, 5, $titreDate, 0, 1, 'R');
    $pdf->SetFont('Arial', 'B', 14);
    $pdf->Cell($contentWidth, 25, "", 0, 1, 'C');
    $pdf->Cell($contentWidth, 6, "Application", 0, 1, 'C');
    $pdf->Ln(20);

    // Logo CNAKPI centré en haut
    $logo = 'img/CNAKPI_small.jpg';
    $logoWidth = 40; // largeur fixe du logo
    $logoX = ($pageWidth - $logoWidth) / 2;
    $pdf->Image($logo, $logoX, 10, $logoWidth, 20, 'jpg', URL_SITE);

    // QR code centré (62mm de large)
    $qrSize = 62;
    $qrX = ($pageWidth - $qrSize) / 2;
    $qrY = 85;
    $qrcode = new QRcode(URL_APP . '/event/' . $idEvenement, 'Q'); // error level : L, M, Q, H
    $qrcode->displayFPDF($pdf, $qrX, $qrY, $qrSize);

    // Logo app centré dans le QR code
    $logoApp = 'img/logo.gif';
    $logo1 = imagecreatefromstring(file_get_contents($logoApp));
    $logo_width = imagesx($logo1);
    $logo_height = imagesy($logo1);
    $logoInnerW = 16;
    $logoInnerH = ($logo_height / $logo_width * $logoInnerW);
    $logoInnerX = ($pageWidth - $logoInnerW) / 2;
    $logoInnerY = $qrY + ($qrSize - $logoInnerH) / 2;
    $pdf->Image($logoApp, $logoInnerX, $logoInnerY, $logoInnerW, $logoInnerH, 'gif', URL_APP . '/event/' . $idEvenement);

    // Restaurer la position après les images pour écrire le lien
    $pdf->SetY($qrY + $qrSize + 5);
    $pdf->SetX($marginLeft);

    $pdf->Cell($contentWidth, 6, URL_APP . '/event/' . $idEvenement, 0, 1, 'C');

    $pdf->Output('Links.pdf', \Mpdf\Output\Destination::INLINE);
  }
}

$page = new PdfQrCodeApp();
