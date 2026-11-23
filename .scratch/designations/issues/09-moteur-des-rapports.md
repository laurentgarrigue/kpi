# Moteur des rapports officiels

Status: ready-for-agent
Blocked by: 02

Spec : [FEATURE_DESIGNATIONS.md](../../../DOC/specs/FEATURE_DESIGNATIONS.md) (D12, D13, D15, D17, D18, D29 ; § 5, § 8)

## À faire

`App\Report\ReportTypeCatalog` versionné ; tables `kp_report`, `kp_report_pdf` ; endpoints rapports (CRUD, submit, reopen, prefill) ; PDF Gotenberg d'un rapport et PDF vierge ; notifications par e-mail des destinataires résolus depuis les Désignations ; écrans app4 génériques (liste, formulaire rendu depuis le catalogue, aperçu).

## Critères

RAP-01 à RAP-07, RAP-10.

## Comments

- 2026-11-23 — Implémenté. api2 : `src/Report/` (catalogue `ReportTypeCatalog` versionné avec INCIDENT v1 pour
  éprouver le moteur, `ReportAnswers` validation et sommes calculées, `ReportPerimeters`, `ReportPeople` officiels D15
  et destinataires D28, `ReportService`, `ReportPdf` sur `sections.html.twig`), `AdminReportsController`, e-mail
  `NotificationService::sendReportSubmitted` avec le PDF joint. **Écart à la spec** : pas de table `kp_report_pdf` ;
  chaque soumission est figée dans `kp_report_submission` (réponses, données KPI, destinataires) et son PDF est
  rendu à la demande, ce qui garde tous les PDF soumis (spec § 8.1 mise à jour). app4 : `pages/reports` (liste,
  création depuis un périmètre avec auteur arbitre facultatif, PDF vierge), `pages/reports/[id].vue` (formulaire
  rendu depuis la définition de la version, brouillon, soumission, réouverture avec motif, PDF par soumission), lien
  « Rédiger un rapport officiel » dans le bloc Désignations, menu Référentiels › Rapports (profils ≤ 8).
  Rapports sur un match : pas encore de point d'entrée depuis la page des matchs (ticket 11).
- 2026-11-23 — Décision : un rapport se crée **seulement depuis la page Rapports** (bouton « Nouveau rapport »), avec
  une recherche de l'élément lié : événement, compétition, journée ou match (compétition → journée → match). Les
  boutons « Rapport » de la page des matchs et le lien du bloc Désignations sont retirés (interfaces existantes
  allégées).
