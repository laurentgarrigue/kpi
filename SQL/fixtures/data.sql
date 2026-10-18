-- Fixtures de test api2 — DONNÉES (Phase 4 du plan CI/CD).
--
-- 100 % synthétique : saisons 2999/2998, compétitions TST*, événements ≥ 9000.
-- Aucune donnée réelle (cf. SQL/fixtures/README.md).
--
-- Chaque ligne existe pour couvrir UN cas précis, indiqué en commentaire. Ne pas
-- « nettoyer » une ligne qui paraît redondante sans vérifier quel test la lit :
-- les paires publié/non publié sont ce qui prouve que les filtres marchent.
--
-- Idempotent : on vide les tables avant d'insérer.

-- Les libellés contiennent des accents : sans cela, un client en latin1 les chargerait en mojibake.
SET NAMES utf8mb4;

DELETE FROM `kp_match_detail`;
DELETE FROM `kp_match`;
DELETE FROM `kp_competition_equipe_joueur`;
DELETE FROM `kp_competition_equipe_journee`;
DELETE FROM `kp_competition_equipe`;
DELETE FROM `kp_evenement_journee`;
DELETE FROM `kp_licence`;
DELETE FROM `kp_journee`;
DELETE FROM `kp_competition`;
DELETE FROM `kp_groupe`;
DELETE FROM `kp_saison`;
DELETE FROM `kp_evenement`;
DELETE FROM `kp_equipe`;
DELETE FROM `kp_club`;
DELETE FROM `kp_cd`;
DELETE FROM `kp_cr`;

-- ---------------------------------------------------------------- saisons
-- Une seule saison à l'état 'A' (active) : c'est l'invariant sur lequel
-- s'appuient getActiveSeasonCode() et le mode 'champ' (filtre s.Etat = 'A').
INSERT INTO `kp_saison` (`Code`, `Etat`, `Nat_debut`, `Nat_fin`, `Inter_debut`, `Inter_fin`) VALUES
  ('2999', 'A', '2998-09-01', '2999-08-31', '2998-09-01', '2999-08-31'),  -- saison ACTIVE
  ('2998', 'I', '2997-09-01', '2998-08-31', '2997-09-01', '2998-08-31');  -- saison passée (inactive)

-- ---------------------------------------------------------------- groupes
-- Le mode 'champ' JOIN kp_groupe sur c.Code_ref = g.Groupe : sans la ligne
-- correspondante, la compétition DISPARAÎT du résultat (INNER JOIN).
INSERT INTO `kp_groupe` (`section`, `ordre`, `Code_niveau`, `Groupe`, `Libelle`) VALUES
  (1, 1, 'NAT', 'TSTGRP', 'Groupe de test');

-- ---------------------------------------------------------------- événements « app2 » (Id < 3000 dans la vraie base, ici 9xxx)
-- Le contrôleur distingue deux familles d'événements par leur Id (< 3000 =
-- tournoi kp_evenement, >= 3000 = journée de championnat). Nos ids 9xxx sont
-- donc TOUS vus comme « >= 3000 » par GET /event/{id} : c'est intentionnel, et
-- le test qui couvre la branche « < 3000 » utilise l'id 42 ci-dessous.
INSERT INTO `kp_evenement` (`Id`, `Libelle`, `Lieu`, `Date_debut`, `Date_fin`, `Publication`, `logo`, `app`) VALUES
  -- app='O' ET Publication='O' → visible en mode 'std' ET en mode 'all'
  (9001, 'Tournoi Test Alpha', 'Testville', '2999-06-01', '2999-06-02', 'O', 'logo/alpha.png', 'O'),
  -- app='O' mais Publication='N' → visible en 'std' (filtre app), ABSENT de 'all' (filtre Publication)
  (9002, 'Tournoi Test Beta',  'Testville', '2999-05-01', '2999-05-02', 'N', NULL, 'O'),
  -- app='N' mais Publication='O' → ABSENT de 'std', visible en 'all'
  (9003, 'Tournoi Test Gamma', 'Testville', '2999-04-01', '2999-04-02', 'O', NULL, 'N'),
  -- app='N' ET Publication='N' → invisible partout (témoin négatif)
  (9004, 'Tournoi Test Delta', 'Testville', '2999-03-01', '2999-03-02', 'N', NULL, 'N'),
  -- Id < 3000 : seul moyen de couvrir la branche « legacy » de GET /event/{id}
  (42,   'Tournoi Test Retro', 'Oldtown',   '2999-02-01', '2999-02-02', 'O', 'logo/retro.png', 'O');

-- ---------------------------------------------------------------- compétitions
INSERT INTO `kp_competition`
  (`Code`, `Code_saison`, `Libelle`, `Soustitre`, `Soustitre2`, `BandeauLink`, `LogoLink`, `SponsorLink`,
   `Bandeau_actif`, `Logo_actif`, `Sponsor_actif`, `Code_ref`, `Code_typeclt`, `Verrou`, `Statut`, `Publication`) VALUES
  -- CHPT publiée, saison active, BANDEAU actif → logo attendu = 'logo/bandeau-test.png'
  -- (le CASE du contrôleur privilégie le bandeau sur le logo : ce cas le prouve)
  ('TSTCH', '2999', 'Championnat Test', 'Sous-titre', 'SEN', 'bandeau-test.png', 'logo-test.png', '',
   'O', 'O', '', 'TSTGRP', 'CHPT', 'N', 'ENC', 'O'),
  -- CHPT publiée, bandeau INACTIF mais logo actif → logo attendu = 'logo/logo-seul.png'
  ('TSTLG', '2999', 'Championnat Logo', NULL, NULL, 'bandeau-ignore.png', 'logo-seul.png', '',
   'N', 'O', '', 'TSTGRP', 'CHPT', 'N', 'ENC', 'O'),
  -- CHPT publiée, AUCUN visuel actif → logo attendu = NULL (branche ELSE du CASE)
  ('TSTNO', '2999', 'Championnat Sans Logo', NULL, NULL, '', '', '',
   'N', 'N', '', 'TSTGRP', 'CHPT', 'N', 'ENC', 'O'),
  -- CHPT NON publiée → ses journées ne doivent JAMAIS sortir (filtre c.Publication)
  ('TSTNP', '2999', 'Championnat Non Publie', NULL, NULL, '', '', '',
   'N', 'N', '', 'TSTGRP', 'CHPT', 'N', 'ENC', 'N'),
  -- Publiée mais type != CHPT → hors périmètre du mode 'champ' (filtre Code_typeclt)
  ('TSTTO', '2999', 'Tournoi Non Chpt', NULL, NULL, '', '', '',
   'N', 'N', '', 'TSTGRP', 'TOUR', 'N', 'ENC', 'O'),
  -- CHPT publiée mais SAISON INACTIVE → exclue du mode 'champ' (filtre s.Etat='A')
  ('TSTOL', '2998', 'Championnat Saison Passee', NULL, NULL, '', '', '',
   'N', 'N', '', 'TSTGRP', 'CHPT', 'N', 'END', 'O'),
  -- Statut END : sert aux tests de lecture seule (CompetitionLockTrait)
  ('TSTEN', '2999', 'Championnat Termine', NULL, NULL, '', '', '',
   'N', 'N', '', 'TSTGRP', 'CHPT', 'O', 'END', 'O');

-- ---------------------------------------------------------------- journées (= événements « championnat », Id >= 3000)
INSERT INTO `kp_journee`
  (`Id`, `Code_competition`, `Code_saison`, `Date_debut`, `Date_fin`, `Nom`, `Libelle`, `Lieu`, `Etat`, `Type`, `Publication`) VALUES
  -- journée publiée d'une CHPT publiée en saison active → SORT en mode 'champ'
  (9101, 'TSTCH', '2999', '2999-06-10', '2999-06-11', 'Journee Test 1', 'J1', 'Testville', 'O', 'C', 'O'),
  -- 2e journée publiée, date PLUS ANCIENNE → sert à vérifier l'ordre (Date_debut DESC)
  (9102, 'TSTCH', '2999', '2999-01-10', '2999-01-11', 'Journee Test 0', 'J0', 'Testville', 'O', 'C', 'O'),
  -- journée NON publiée d'une compétition publiée → exclue (filtre j.Publication)
  (9103, 'TSTCH', '2999', '2999-07-10', '2999-07-11', 'Journee Masquee', 'JM', 'Testville', 'O', 'C', 'N'),
  -- journée publiée mais COMPÉTITION non publiée → exclue (filtre c.Publication)
  (9104, 'TSTNP', '2999', '2999-06-20', '2999-06-21', 'Journee Compet Masquee', 'JCM', 'Testville', 'O', 'C', 'O'),
  -- journées des compétitions à visuels : vérifient le CASE logo du contrôleur
  (9105, 'TSTLG', '2999', '2999-06-15', '2999-06-16', 'Journee Logo', 'JL', 'Testville', 'O', 'C', 'O'),
  (9106, 'TSTNO', '2999', '2999-06-16', '2999-06-17', 'Journee Sans Logo', 'JSL', 'Testville', 'O', 'C', 'O'),
  -- journée d'une CHPT en saison INACTIVE → exclue du mode 'champ'
  (9107, 'TSTOL', '2998', '2998-06-10', '2998-06-11', 'Journee Saison Passee', 'JSP', 'Testville', 'O', 'C', 'O'),
  -- journées d'une compétition END : consommées par les tests de lecture seule
  (9108, 'TSTEN', '2999', '2999-06-01', '2999-06-02', 'Journee Terminee', 'JT', 'Testville', 'O', 'C', 'O');


-- ================================================================ RÉSULTATS PUBLICS (site public, phase 2)
-- Jeu « groupe TSTRES » consommé par les tests de caractérisation (tests/Integration/PublicResults*)
-- et les nouveaux endpoints (DOC/specs/public/API_PUBLIC_RESULTS.md). Ids 92xx (journées), 93xx (équipes),
-- 94xx (matchs), 95xx (joueurs), 99xx (arbitres). Chaque ligne couvre un cas, indiqué en commentaire.

INSERT INTO `kp_groupe` (`section`, `ordre`, `Code_niveau`, `Groupe`, `Libelle`, `Libelle_en`) VALUES
  (2, 1, 'NAT', 'TSTRES', 'Groupe Résultats', 'Results group');

INSERT INTO `kp_competition`
  (`Code`, `Code_saison`, `Code_niveau`, `Libelle`, `Soustitre`, `Soustitre2`, `Web`, `BandeauLink`, `LogoLink`, `SponsorLink`,
   `Titre_actif`, `Bandeau_actif`, `Logo_actif`, `Code_ref`, `GroupOrder`, `Code_typeclt`, `Code_tour`, `Nb_equipes`,
   `Statut`, `Qualifies`, `Elimines`, `Publication`) VALUES
  -- CHPT en cours (ON), 4 équipes : 1 qualifié, 1 relégué ; titre actif → display_title = Libelle
  ('RCH', '2999', 'NAT', 'Championnat Résultats', 'Saison régulière', 'Poule A', 'https://example.test', 'bandeau-rch.png', '', '',
   'O', 'O', 'N', 'TSTRES', 1, 'CHPT', 1, 4, 'ON', 1, 1, 'O'),
  -- CP terminée (END) du tour FINAL (Code_tour = 10) : médailles ; titre inactif → display_title = Soustitre
  ('RCP', '2999', 'NAT', 'Coupe Résultats', 'Phase finale', 'Finale', NULL, '', 'logo-rcp.png', '',
   'N', 'N', 'O', 'TSTRES', 2, 'CP', 10, 4, 'END', 0, 0, 'O'),
  -- MULTI terminée du tour final, sans match (classement seul)
  ('RMU', '2999', 'NAT', 'Multi Résultats', NULL, 'Général', NULL, '', '', '',
   'O', 'N', 'N', 'TSTRES', 3, 'MULTI', 10, 3, 'END', 0, 0, 'O'),
  -- CHPT publiée mais en attente (ATT) : exclue des tableaux (c.Statut != 'ATT'), classement non affiché
  ('RAT', '2999', 'NAT', 'Championnat En Attente', NULL, 'Poule B', NULL, '', '', '',
   'O', 'N', 'N', 'TSTRES', 4, 'CHPT', 1, 2, 'ATT', 0, 0, 'O'),
  -- CHPT NON publiée du même groupe : ne doit jamais sortir
  ('RNP', '2999', 'NAT', 'Championnat Masqué', NULL, 'Masquée', NULL, '', '', '',
   'O', 'N', 'N', 'TSTRES', 5, 'CHPT', 1, 2, 'ON', 0, 0, 'N');

INSERT INTO `kp_journee`
  (`Id`, `Code_competition`, `Code_saison`, `Date_debut`, `Date_fin`, `Nom`, `Libelle`, `Lieu`, `Departement`,
   `Organisateur`, `Responsable_insc`, `Responsable_R1`, `Delegue`, `ChefArbitre`,
   `Etat`, `Type`, `Phase`, `Niveau`, `Etape`, `Nbequipes`, `Publication`) VALUES
  -- RCH : 2 journées publiées, 1 non publiée, 1 « Pause »
  (9201, 'RCH', '2999', '2999-03-01', '2999-03-02', 'RCH J1', 'J1', 'Lacville', '33',
   'Club Organisateur', 'RESP Insc', 'RESP Rone', 'DELEGUE Del', 'CHEF Arb', 'O', 'C', 'Journée 1', 1, 1, 4, 'O'),
  (9202, 'RCH', '2999', '2999-04-01', '2999-04-02', 'RCH J2', 'J2', 'Rivecity', '64',
   NULL, NULL, NULL, NULL, NULL, 'O', 'C', 'Journée 2', 1, 1, 4, 'O'),
  (9203, 'RCH', '2999', '2999-05-01', '2999-05-02', 'RCH J3', 'J3', 'Cachéville', '33',
   NULL, NULL, NULL, NULL, NULL, 'O', 'C', 'Journée 3', 1, 1, 4, 'N'),
  (9204, 'RCH', '2999', '2999-04-01', '2999-04-01', 'RCH Pause', 'P', 'Rivecity', '64',
   NULL, NULL, NULL, NULL, NULL, 'O', 'C', 'Pause', 1, 1, 0, 'O'),
  -- RCP : poule A classée (cej), poule B SANS classement (équipes déduites des matchs), finale à élimination
  (9211, 'RCP', '2999', '2999-04-01', '2999-04-01', 'RCP Poule A', 'Poule A', 'Rivecity', '64',
   NULL, NULL, NULL, NULL, NULL, 'O', 'C', 'Poule A', 1, 1, 2, 'O'),
  (9213, 'RCP', '2999', '2999-04-01', '2999-04-01', 'RCP Poule B', 'Poule B', 'Rivecity', '64',
   NULL, NULL, NULL, NULL, NULL, 'O', 'C', 'Poule B', 1, 1, 2, 'O'),
  (9212, 'RCP', '2999', '2999-04-02', '2999-04-02', 'RCP Finale', 'Finale', 'Rivecity', '64',
   NULL, NULL, NULL, NULL, NULL, 'O', 'E', 'Finale', 2, 2, 2, 'O'),
  -- RAT et RNP : une journée chacune (filtres statut / publication)
  (9221, 'RAT', '2999', '2999-06-01', '2999-06-01', 'RAT J1', 'J1', 'Lacville', '33',
   NULL, NULL, NULL, NULL, NULL, 'O', 'C', 'Journée 1', 1, 1, 2, 'O'),
  (9231, 'RNP', '2999', '2999-06-01', '2999-06-01', 'RNP J1', 'J1', 'Lacville', '33',
   NULL, NULL, NULL, NULL, NULL, 'O', 'C', 'Journée 1', 1, 1, 2, 'O');

-- Événement 77 (publié, Id < 3000 = tournoi kp_evenement) : J2 (+ Pause) de RCH et toutes les journées de RCP,
-- toutes du groupe TSTRES → événement « d'un seul groupe » (PAGE_EVENT_GROUP.md § 2.0).
-- Événement 78 (NON publié) : J1 de RCH.
-- Événement 79 (publié) : J1 de RAT (TSTRES) et J1 de RDV (TSTDIV) → groupes différents.
INSERT INTO `kp_evenement` (`Id`, `Libelle`, `Lieu`, `Date_debut`, `Date_fin`, `Publication`, `logo`, `app`) VALUES
  (77, 'Tournoi Résultats', 'Rivecity', '2999-04-01', '2999-04-02', 'O', 'logo/resultats.png', 'O'),
  (78, 'Tournoi Résultats Masqué', 'Lacville', '2999-03-01', '2999-03-02', 'N', NULL, 'O'),
  (79, 'Finales Multi-groupes', 'Rivecity', '2999-06-01', '2999-07-10', 'O', NULL, 'O');
INSERT INTO `kp_evenement_journee` (`Id_evenement`, `Id_journee`) VALUES
  (77, 9202), (77, 9204), (77, 9211), (77, 9212), (77, 9213),
  (78, 9201),
  (79, 9221), (79, 9261);

INSERT INTO `kp_competition_equipe`
  (`Id`, `Code_compet`, `Code_saison`, `Libelle`, `Code_club`, `logo`, `Numero`, `Poule`, `Tirage`,
   `Pts_publi`, `Clt_publi`, `J_publi`, `G_publi`, `N_publi`, `P_publi`, `F_publi`, `Plus_publi`, `Moins_publi`, `Diff_publi`,
   `CltNiveau_publi`) VALUES
  -- RCH : rangs 1 à 4 publiés ; 9304 logo NULL → logo par défaut KIP/logo/empty-logo.png
  (9301, 'RCH', '2999', 'Equipe Alpha', 'C001', 'KIP/logo/alpha.png', 101, '', 0, 700, 1, 3, 2, 1, 0, 0, 9, 4, 5, 0),
  (9302, 'RCH', '2999', 'Equipe Bravo', 'C002', 'KIP/logo/bravo.png', 102, '', 0, 500, 2, 3, 1, 1, 1, 0, 6, 6, 0, 0),
  (9303, 'RCH', '2999', 'Equipe Charlie', 'C003', 'KIP/logo/charlie.png', 103, '', 0, 400, 3, 2, 1, 0, 1, 0, 4, 4, 0, 0),
  (9304, 'RCH', '2999', 'Equipe Delta', 'C004', NULL, 104, '', 0, 100, 4, 2, 0, 0, 2, 0, 1, 6, -5, 0),
  -- RCP : classement niveau 1 à 4 (médailles 1, 2, 3)
  (9311, 'RCP', '2999', 'Equipe Echo', 'C005', NULL, 111, 'A', 1, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 1),
  (9312, 'RCP', '2999', 'Equipe Foxtrot', 'C006', NULL, 112, 'A', 2, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 3),
  (9313, 'RCP', '2999', 'Equipe Golf', 'C007', NULL, 113, 'B', 1, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 2),
  (9314, 'RCP', '2999', 'Equipe Hotel', 'C008', NULL, 114, 'B', 2, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 4),
  -- RMU : 3 équipes classées
  (9321, 'RMU', '2999', 'Equipe India', 'C009', NULL, 121, '', 0, 3000, 1, 2, 0, 0, 0, 0, 0, 0, 0, 0),
  (9322, 'RMU', '2999', 'Equipe Juliet', 'C010', NULL, 122, '', 0, 2000, 2, 2, 0, 0, 0, 0, 0, 0, 0, 0),
  (9323, 'RMU', '2999', 'Equipe Kilo', 'C011', NULL, 123, '', 0, 1000, 3, 2, 0, 0, 0, 0, 0, 0, 0, 0),
  -- RAT : équipe non classée (rang 0)
  (9331, 'RAT', '2999', 'Equipe Lima', 'C012', NULL, 131, '', 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0);

INSERT INTO `kp_competition_equipe_journee`
  (`Id`, `Id_journee`, `Pts_publi`, `Clt_publi`, `J_publi`, `G_publi`, `N_publi`, `P_publi`, `F_publi`,
   `Plus_publi`, `Moins_publi`, `Diff_publi`, `PtsNiveau_publi`, `CltNiveau_publi`) VALUES
  (9301, 9201, 400, 1, 1, 1, 0, 0, 0, 3, 1, 2, 0, 0),
  -- rangs tous distincts : un ex aequo sur toutes les clés de tri rendrait l'ordre dépendant du moteur SQL
  (9302, 9201, 100, 4, 1, 0, 0, 1, 0, 1, 3, -2, 0, 0),
  (9303, 9201, 200, 2, 1, 0, 1, 0, 0, 2, 2, 0, 0, 0),
  (9304, 9201, 200, 3, 1, 0, 1, 0, 0, 2, 2, 0, 0, 0),
  (9311, 9211, 400, 1, 1, 1, 0, 0, 0, 4, 2, 2, 0, 0),
  (9312, 9211, 100, 2, 1, 0, 0, 1, 0, 2, 4, -2, 0, 0);

INSERT INTO `kp_match`
  (`Id`, `Id_journee`, `Libelle`, `Statut`, `Date_match`, `Heure_match`, `Terrain`, `Numero_ordre`, `Periode`,
   `Id_equipeA`, `Id_equipeB`, `ScoreA`, `ScoreB`, `CoeffA`, `CoeffB`,
   `Arbitre_principal`, `Arbitre_secondaire`, `Matric_arbitre_principal`, `Matric_arbitre_secondaire`,
   `Timeshoot`, `Publication`, `Validation`) VALUES
  -- RCH J1 : deux matchs terminés et validés (buteurs), un match NON publié
  (9401, 9201, 'M1', 'END', '2999-03-01', '10:00', '1', 1, 'M2', 9301, 9302, '3', '1', 1, 1,
   'ARBITRE Un (C001)', 'ARBITRE Deux (C002)', 9901, 9902, 'CHRONO Un', 'O', 'O'),
  (9402, 9201, 'M2', 'END', '2999-03-01', '10:00', '2', 2, 'M2', 9303, 9304, '2', '2', 1, 1,
   'ARBITRE Deux (C002)', NULL, 9902, NULL, NULL, 'O', 'O'),
  (9403, 9201, 'M3', 'END', '2999-03-01', '11:00', '1', 3, 'M2', 9301, 9303, '5', '0', 1, 1,
   NULL, NULL, NULL, NULL, NULL, 'N', 'O'),
  -- RCH J2 (dans l'événement 77) : un match en cours non validé, un match à venir (coefficient ≠ 1)
  (9404, 9202, 'M4', 'ON', '2999-04-01', '09:00', '1', 4, 'M1', 9301, 9304, '1', '0', 1, 1,
   NULL, NULL, NULL, NULL, NULL, 'O', ''),
  (9405, 9202, 'M5', 'ATT', '2999-04-01', '09:40', '2', 5, NULL, 9302, 9303, NULL, NULL, 2, 1,
   NULL, NULL, NULL, NULL, NULL, 'O', ''),
  -- RCP poule A, poule B (sans classement), finale ; 9414 : équipes non attribuées, libellé à placeholders
  (9411, 9211, 'A1', 'END', '2999-04-01', '14:00', '1', 11, 'M2', 9311, 9312, '4', '2', 1, 1,
   NULL, NULL, NULL, NULL, NULL, 'O', 'O'),
  (9413, 9213, 'B1', 'END', '2999-04-01', '14:00', '2', 12, 'M2', 9313, 9314, '3', '1', 1, 1,
   NULL, NULL, NULL, NULL, NULL, 'O', 'O'),
  (9412, 9212, 'F1', 'END', '2999-04-02', '10:00', '1', 13, 'M2', 9311, 9313, '2', '1', 1, 1,
   NULL, NULL, NULL, NULL, NULL, 'O', 'O'),
  (9414, 9212, '[V11-P12]', 'ATT', '2999-04-02', '11:00', '1', 14, NULL, NULL, NULL, NULL, NULL, 1, 1,
   NULL, NULL, NULL, NULL, NULL, 'O', ''),
  -- RAT et RNP : un match publié chacun
  (9421, 9221, 'X1', 'ATT', '2999-06-01', '10:00', '1', 21, NULL, 9331, NULL, NULL, NULL, 1, 1,
   NULL, NULL, NULL, NULL, NULL, 'O', ''),
  (9431, 9231, 'Y1', 'ATT', '2999-06-01', '10:00', '1', 31, NULL, NULL, NULL, NULL, NULL, 1, 1,
   NULL, NULL, NULL, NULL, NULL, 'O', '');

-- Arbitres : identité seule (les numéros 99xx sont fictifs)
INSERT INTO `kp_licence` (`Matric`, `Nom`, `Prenom`) VALUES
  (9901, 'ARBITRE', 'Un'),
  (9902, 'ARBITRE', 'Deux');

-- Joueurs (buteurs) : 9501 et 9504 à égalité (2 buts) → rang partagé
INSERT INTO `kp_competition_equipe_joueur` (`Id_equipe`, `Matric`, `Nom`, `Prenom`, `Sexe`, `Categ`, `Numero`, `Capitaine`) VALUES
  (9301, 9501, 'ALPHA', 'Ann', 'F', 'SEN', 7, 'C'),
  (9301, 9502, 'ALPHA', 'Bob', 'M', 'SEN', 8, '-'),
  (9302, 9503, 'BRAVO', 'Cid', 'M', 'SEN', 4, '-'),
  (9303, 9504, 'CHARLIE', 'Dan', 'M', 'SEN', 10, '-'),
  -- composition (phase 3) : entraîneur « E » (après les joueurs) et joueur « X » (exclu)
  (9301, 9505, 'ALPHA', 'Coach', 'M', 'SEN', NULL, 'E'),
  (9301, 9506, 'ALPHA', 'Inactif', 'F', 'SEN', 9, 'X');

INSERT INTO `kp_match_detail` (`Id`, `Id_match`, `Periode`, `Id_evt_match`, `Competiteur`, `Numero`, `Equipe_A_B`) VALUES
  ('fixture-goal-01', 9401, 'M1', 'B', 9501, '7', 'A'),
  ('fixture-goal-02', 9401, 'M2', 'B', 9501, '7', 'A'),
  ('fixture-goal-03', 9401, 'M2', 'B', 9503, '4', 'B'),
  ('fixture-goal-04', 9402, 'M1', 'B', 9504, '10', 'A'),
  ('fixture-goal-05', 9402, 'M2', 'B', 9504, '10', 'A'),
  -- carton vert : ne compte pas comme but
  ('fixture-card-01', 9401, 'M1', 'V', 9502, '8', 'A'),
  -- but dans un match NON VALIDÉ (9404) puis dans un match NON PUBLIÉ (9403) : exclus des buteurs
  ('fixture-goal-06', 9404, 'M1', 'B', 9502, '8', 'A'),
  ('fixture-goal-07', 9403, 'M1', 'B', 9502, '8', 'A');


-- ================================================================ SITE PUBLIC, PHASE 3 (transverse)
-- Consommé par tests/Integration/PublicSiteEndpointsTest.php (DOC/specs/public/API_PUBLIC_TRANSVERSE.md).
-- Équipes (kp_equipe) = numéros 1xx des équipes engagées ci-dessus ; clubs C0xx ; comités CDT/CRT.

INSERT INTO `kp_cr` (`Code`, `Libelle`) VALUES
  ('CRT', 'Comité Régional Test');
INSERT INTO `kp_cd` (`Code`, `Libelle`, `Code_comite_reg`) VALUES
  ('CDT33', 'Comité Départemental Test 33', 'CRT'),
  ('CDT64', 'Comité Départemental Test 64', 'CRT');

INSERT INTO `kp_club` (`Code`, `Libelle`, `Code_comite_dep`, `Coord`, `Postal`, `www`, `email`) VALUES
  -- position valide, coordonnées de la structure ; deux équipes (101, 105)
  ('C001', 'Club Alpha Lacville', 'CDT33', '44.84, -0.58', '1 quai du Lac 99001 Lacville', 'https://alpha.example.test', 'contact@alpha.example.test'),
  -- sans position
  ('C002', 'Club Bravo', 'CDT64', NULL, '', '', ''),
  -- nom avec tiret : trouvé par « saint malo » (motif à tirets, comme searchClubs.php)
  ('C003', 'Club Charlie Saint-Malo', 'CDT33', '', NULL, NULL, NULL),
  -- position illisible → null
  ('C004', 'Club Delta', 'CDT64', 'pas une position', NULL, NULL, NULL),
  ('C005', 'Club Echo', 'CDT33', NULL, NULL, NULL, NULL),
  ('C006', 'Club Foxtrot', 'CDT33', NULL, NULL, NULL, NULL),
  ('C007', 'Club Golf', 'CDT33', NULL, NULL, NULL, NULL),
  ('C008', 'Club Hotel', 'CDT33', NULL, NULL, NULL, NULL),
  ('C009', 'Club India', 'CDT64', NULL, NULL, NULL, NULL),
  ('C010', 'Club Juliet', 'CDT64', NULL, NULL, NULL, NULL),
  ('C011', 'Club Kilo', 'CDT64', NULL, NULL, NULL, NULL),
  ('C012', 'Club Lima', 'CDT64', NULL, NULL, NULL, NULL),
  -- club SANS équipe (structure hors kayak-polo) : jamais publié (liste, fiche, recherche)
  ('C099', 'Club Sans Equipe', 'CDT33', '45.00, 0.00', NULL, 'https://sans.example.test', 'sans@example.test');

INSERT INTO `kp_equipe` (`Numero`, `Libelle`, `Code_club`) VALUES
  (101, 'Equipe Alpha', 'C001'), (102, 'Equipe Bravo', 'C002'), (103, 'Equipe Charlie', 'C003'),
  (104, 'Equipe Delta', 'C004'), (105, 'Alpha Deux', 'C001'),
  (111, 'Equipe Echo', 'C005'), (112, 'Equipe Foxtrot', 'C006'), (113, 'Equipe Golf', 'C007'),
  (114, 'Equipe Hotel', 'C008'), (121, 'Equipe India', 'C009'), (122, 'Equipe Juliet', 'C010'),
  (123, 'Equipe Kilo', 'C011'), (131, 'Equipe Lima', 'C012');

-- Saison 2998 du groupe TSTRES : palmarès sur plusieurs saisons et palmarès d'équipe.
INSERT INTO `kp_competition`
  (`Code`, `Code_saison`, `Code_niveau`, `Libelle`, `Soustitre`, `Soustitre2`, `Web`, `BandeauLink`, `LogoLink`, `SponsorLink`,
   `Titre_actif`, `Bandeau_actif`, `Logo_actif`, `Code_ref`, `GroupOrder`, `Code_typeclt`, `Code_tour`, `Nb_equipes`,
   `Statut`, `Qualifies`, `Elimines`, `Publication`) VALUES
  -- CP terminée du tour final : dans l'historique, médailles
  ('RCP', '2998', 'NAT', 'Coupe Résultats', 'Phase finale', 'Finale', NULL, '', '', '',
   'O', 'N', 'N', 'TSTRES', 2, 'CP', 10, 3, 'END', 0, 0, 'O'),
  -- terminée mais PAS du tour final : absente de l'historique, présente au palmarès d'équipe sans médaille
  ('RQL', '2998', 'NAT', 'Qualification Résultats', NULL, 'Qualif', NULL, '', '', '',
   'O', 'N', 'N', 'TSTRES', 1, 'CHPT', 1, 2, 'END', 0, 0, 'O');

INSERT INTO `kp_journee`
  (`Id`, `Code_competition`, `Code_saison`, `Date_debut`, `Date_fin`, `Nom`, `Libelle`, `Lieu`, `Departement`,
   `Etat`, `Type`, `Phase`, `Niveau`, `Etape`, `Nbequipes`, `Publication`) VALUES
  (9241, 'RCP', '2998', '2998-05-10', '2998-05-11', 'RCP 2998', 'Finale', 'Lacville', '33', 'O', 'E', 'Finale', 1, 1, 3, 'O');

INSERT INTO `kp_competition_equipe`
  (`Id`, `Code_compet`, `Code_saison`, `Libelle`, `Code_club`, `logo`, `Numero`, `Poule`, `Tirage`,
   `Pts_publi`, `Clt_publi`, `J_publi`, `G_publi`, `N_publi`, `P_publi`, `F_publi`, `Plus_publi`, `Moins_publi`, `Diff_publi`,
   `CltNiveau_publi`) VALUES
  -- RCP 2998 : classement de niveau 1, 2, 3 (CP → CltNiveau_publi)
  (9341, 'RCP', '2998', 'Equipe Alpha', 'C001', NULL, 101, '', 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 1),
  (9342, 'RCP', '2998', 'Equipe Bravo', 'C002', NULL, 102, '', 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 2),
  (9343, 'RCP', '2998', 'Equipe Charlie', 'C003', NULL, 103, '', 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 3),
  -- RQL 2998 (CHPT) : Equipe Alpha 2e (Clt_publi)
  (9351, 'RQL', '2998', 'Equipe Alpha', 'C001', NULL, 101, '', 0, 300, 2, 2, 1, 0, 1, 0, 3, 3, 0, 0),
  (9352, 'RQL', '2998', 'Equipe Delta', 'C004', NULL, 104, '', 0, 600, 1, 2, 2, 0, 0, 0, 5, 1, 4, 0);

-- Groupe « Divers » (section 100, hors sections publiques de /groups/{season}) : visible dans le calendrier.
INSERT INTO `kp_groupe` (`section`, `ordre`, `Code_niveau`, `Groupe`, `Libelle`, `Libelle_en`) VALUES
  (100, 1, 'NAT', 'TSTDIV', 'Groupe Divers', 'Misc group');
INSERT INTO `kp_competition`
  (`Code`, `Code_saison`, `Code_niveau`, `Libelle`, `Soustitre`, `Soustitre2`, `Web`, `BandeauLink`, `LogoLink`, `SponsorLink`,
   `Titre_actif`, `Bandeau_actif`, `Logo_actif`, `Code_ref`, `GroupOrder`, `Code_typeclt`, `Code_tour`, `Nb_equipes`,
   `Statut`, `Qualifies`, `Elimines`, `Publication`) VALUES
  ('RDV', '2999', 'NAT', 'Rencontre Divers', NULL, NULL, NULL, '', '', '',
   'O', 'N', 'N', 'TSTDIV', 1, 'CHPT', 1, 2, 'ATT', 0, 0, 'O');
INSERT INTO `kp_journee`
  (`Id`, `Code_competition`, `Code_saison`, `Date_debut`, `Date_fin`, `Nom`, `Libelle`, `Lieu`, `Departement`, `Etat`, `Type`, `Phase`, `Niveau`, `Etape`, `Nbequipes`, `Publication`) VALUES
  (9261, 'RDV', '2999', '2999-07-10', '2999-07-11', 'Rencontre amicale', 'RDV J1', 'Lacville', NULL, 'O', 'C', 'Journée 1', 1, 1, 2, 'O');

-- Rendez-vous hors événement (GET /fixtures, PAGE_HOME.md HOME-09) : deux compétitions du groupe TSTDIV jouées aux
-- mêmes dates et lieu, rattachées à aucun événement → UN seul rendez-vous « groupe », logo de RDX (seul logo actif).
INSERT INTO `kp_competition`
  (`Code`, `Code_saison`, `Code_niveau`, `Libelle`, `Soustitre`, `Soustitre2`, `Web`, `BandeauLink`, `LogoLink`, `SponsorLink`,
   `Titre_actif`, `Bandeau_actif`, `Logo_actif`, `Code_ref`, `GroupOrder`, `Code_typeclt`, `Code_tour`, `Nb_equipes`,
   `Statut`, `Qualifies`, `Elimines`, `Publication`) VALUES
  ('RDX', '2999', 'NAT', 'Rencontre Libre', NULL, NULL, NULL, '', 'logo-rdx.png', '',
   'O', 'N', 'O', 'TSTDIV', 2, 'CHPT', 1, 2, 'ATT', 0, 0, 'O');
INSERT INTO `kp_journee`
  (`Id`, `Code_competition`, `Code_saison`, `Date_debut`, `Date_fin`, `Nom`, `Libelle`, `Lieu`, `Departement`, `Etat`, `Type`, `Phase`, `Niveau`, `Etape`, `Nbequipes`, `Publication`) VALUES
  (9262, 'RDV', '2999', '2999-08-01', '2999-08-01', 'Rencontre estivale', 'RDV J2', 'Lacville', '33', 'O', 'C', 'Journée 2', 1, 1, 2, 'O'),
  (9263, 'RDX', '2999', '2999-08-01', '2999-08-01', 'Rencontre libre', 'RDX J1', 'Lacville', '33', 'O', 'C', 'Journée 1', 1, 1, 2, 'O');

-- ---------------------------------------------------------------------------------------------------------------
-- Site public, phase 4 (FEATURE_CMS.md, FEATURE_FORMS.md). Les catégories et les blocs d'accueil sont créés par la
-- migration. Mots de passe : aucun (les tests signent un JWT directement).
DELETE FROM `kp_user`;
DELETE FROM `kp_user_capability`;
DELETE FROM `kp_cms_media`;
DELETE FROM `kp_cms_article`;
DELETE FROM `kp_cms_article_category`;
DELETE FROM `kp_cms_article_link`;
DELETE FROM `kp_cms_page`;
DELETE FROM `kp_cms_menu_item`;
DELETE FROM `kp_cms_redirect`;
DELETE FROM `kp_form`;
DELETE FROM `kp_form_field`;
DELETE FROM `kp_form_entry`;
DELETE FROM `kp_mail_log`;
DELETE FROM `kp_journal`;
INSERT INTO `kp_user` (`Code`, `Pwd`, `Identite`, `Mail`, `Tel`, `Fonction`, `Niveau`, `Filtre_competition`, `Filtre_saison`, `Filtre_competition_sql`, `Filtre_journee`) VALUES
  ('TADMIN', '', 'Admin Test', 'admin@example.test', '', '', 2, '', '', '', ''),   -- profil 2 : tous droits éditoriaux
  ('TEDIT', '', 'Rédacteur Test', 'edit@example.test', '', '', 8, '', '', '', ''),  -- profil 8 + capacité editor
  ('TVIEW', '', 'Lecteur Test', 'view@example.test', '', '', 8, '', '', '', '');   -- profil 8 sans capacité : 403
INSERT INTO `kp_user_capability` (`user_code`, `capability`) VALUES ('TEDIT', 'editor');

INSERT INTO `kp_cms_media` (`id`, `path`, `mime`, `width`, `height`, `size`, `alt_fr`, `alt_en`, `credit`, `created_at`, `updated_at`) VALUES
  (9501, '2999/01/finale.jpg', 'image/jpeg', 1600, 900, 120000, 'La finale', 'The final', 'CNA', '2020-01-01 10:00:00', '2020-01-01 10:00:00'),
  (9502, '2999/01/reglement.pdf', 'application/pdf', NULL, NULL, 250000, '', '', '', '2020-01-01 10:00:00', '2020-01-01 10:00:00');

INSERT INTO `kp_cms_article` (`id`, `slug`, `title_fr`, `title_en`, `lead_fr`, `lead_en`, `body_fr`, `body_en`, `cover_media_id`, `status`, `published_at`, `signature`, `author_code`, `seo_description_fr`, `created_at`, `updated_at`) VALUES
  -- publié, anglais renseigné, lié à une compétition, un événement et un club ; 2 catégories
  (9601, 'finale-resultats', 'Finale des résultats', 'Results final', 'Le chapô.', 'The lead.', '<p>Corps <strong>gras</strong>.</p>', '<p>Body.</p>', 9501, 'published', '2020-06-01 12:00:00', 'La CNA Kayak-Polo', 'TEDIT', 'Description SEO', '2020-05-30 10:00:00', '2020-06-01 12:00:00'),
  -- publié, sans anglais (repli FR), plus ancien, catégorie 4
  (9602, 'nouveau-reglement', 'Nouveau règlement', '', 'Règlement 2999.', '', '<p>Texte.</p>', '', NULL, 'published', '2020-03-01 12:00:00', 'Commission sportive', 'TADMIN', '', '2020-03-01 10:00:00', '2020-03-01 12:00:00'),
  -- brouillon : jamais public
  (9603, 'brouillon', 'Brouillon', '', '', '', '<p>…</p>', '', NULL, 'draft', NULL, '', 'TEDIT', '', '2020-01-01 10:00:00', '2020-01-01 10:00:00'),
  -- programmé dans le futur : pas encore public
  (9604, 'programme', 'Programmé', '', '', '', '<p>…</p>', '', NULL, 'published', '9999-01-01 00:00:00', '', 'TEDIT', '', '2020-01-01 10:00:00', '2020-01-01 10:00:00'),
  -- archivé : plus public
  (9605, 'archive', 'Archivé', '', '', '', '<p>…</p>', '', NULL, 'archived', '2019-01-01 00:00:00', '', 'TEDIT', '', '2019-01-01 10:00:00', '2019-01-01 10:00:00');
INSERT INTO `kp_cms_article_category` (`article_id`, `category_id`) VALUES (9601, 1), (9601, 2), (9602, 4), (9603, 2);
INSERT INTO `kp_cms_article_link` (`article_id`, `kind`, `ref`) VALUES
  (9601, 'competition', '2999/RCH'), (9601, 'event', '77'), (9601, 'club', 'C001'), (9603, 'competition', '2999/RCH');

INSERT INTO `kp_cms_page` (`id`, `slug`, `parent_id`, `title_fr`, `title_en`, `body_fr`, `body_en`, `status`, `created_at`, `updated_at`) VALUES
  (9701, 'le-kayak-polo', NULL, 'Le kayak-polo', 'Canoe polo', '<h2>Histoire</h2><p>a</p><h2>Règles</h2><p>b</p><h2>Pratiquer</h2><p>c</p>', '<p>EN</p>', 'published', '2020-01-01 10:00:00', '2020-01-01 10:00:00'),
  (9702, 'arbitrage', 9701, 'Arbitrage', '', '<p>Arbitrer.</p>', '', 'published', '2020-01-01 10:00:00', '2020-01-01 10:00:00'),
  (9703, 'page-brouillon', NULL, 'Page brouillon', '', '<p>…</p>', '', 'draft', '2020-01-01 10:00:00', '2020-01-01 10:00:00');
INSERT INTO `kp_cms_menu_item` (`id`, `parent_id`, `position`, `label_fr`, `label_en`, `target_kind`, `target_ref`) VALUES
  (9801, NULL, 1, 'Le kayak-polo', 'Canoe polo', 'page', '9701'),
  (9802, NULL, 2, 'Arbitrage', '', 'page', '9702'),
  (9803, NULL, 3, 'Page brouillon', '', 'page', '9703'),   -- cible non publiée : masquée
  (9804, NULL, 4, 'FFCK', 'FFCK', 'url', 'https://www.ffck.org');
UPDATE `kp_cms_home_block` SET `config` = '{"articles":[9601]}' WHERE `kind` = 'featured';
INSERT INTO `kp_cms_redirect` (`from_path`, `to_path`, `code`) VALUES ('/2020/06/finale/', '/news/finale-resultats', 301);

-- Formulaires : ouvert (capacité 2, liste d'attente), confirmation manuelle, clos, brouillon.
INSERT INTO `kp_form` (`id`, `slug`, `title_fr`, `title_en`, `intro_fr`, `intro_en`, `status`, `opens_at`, `closes_at`, `capacity`, `waiting_list`, `confirmation_mode`, `payment_url`, `notify_emails`, `confirmation_text_fr`, `confirmation_text_en`, `public_list`, `public_columns`, `purpose_fr`, `purpose_en`, `retention_days`, `event_id`, `created_at`, `updated_at`) VALUES
  (9901, 'tournoi-test', 'Tournoi test', 'Test tournament', '<p>Inscrivez votre équipe.</p>', '', 'published', '2020-01-01 00:00:00', '9999-01-01 00:00:00', 2, 1, 'auto', '', 'orga@example.test', 'Merci !', '', 1, '["club","team"]', 'Organisation du tournoi.', '', 90, 77, '2020-01-01 00:00:00', '2020-01-01 00:00:00'),
  (9902, 'stage-paiement', 'Stage (paiement)', '', '', '', 'published', '2020-01-01 00:00:00', '9999-01-01 00:00:00', NULL, 1, 'manual', 'https://www.helloasso.com/test', '', '', '', 1, '["team"]', '', '', 90, NULL, '2020-01-01 00:00:00', '2020-01-01 00:00:00'),
  (9903, 'clos', 'Formulaire clos', '', '', '', 'published', '2020-01-01 00:00:00', '2020-02-01 00:00:00', NULL, 0, 'auto', '', '', '', '', 0, '[]', '', '', 30, NULL, '2020-01-01 00:00:00', '2020-01-01 00:00:00'),
  (9904, 'brouillon-form', 'Brouillon', '', '', '', 'draft', NULL, NULL, NULL, 0, 'auto', '', '', '', '', 0, '[]', '', '', 90, NULL, '2020-01-01 00:00:00', '2020-01-01 00:00:00');
INSERT INTO `kp_form_field` (`form_id`, `field_key`, `type`, `label_fr`, `label_en`, `required`, `options`, `min_value`, `max_value`, `position`) VALUES
  (9901, 'email', 'email', 'E-mail', 'E-mail', 1, '[]', NULL, NULL, 1),
  (9901, 'phone', 'phone', 'Téléphone', 'Phone', 0, '[]', NULL, NULL, 2),
  (9901, 'club', 'club', 'Club', 'Club', 1, '[]', NULL, NULL, 3),
  (9901, 'team', 'text', 'Équipe', 'Team', 1, '[]', NULL, NULL, 4),
  (9901, 'players', 'number', 'Joueurs', 'Players', 1, '[]', 5, 10, 5),
  (9901, 'category', 'select', 'Catégorie', 'Category', 1, '[{"value":"U18","label_fr":"U18","label_en":"U18"},{"value":"SEN","label_fr":"Senior","label_en":"Senior"}]', NULL, NULL, 6),
  (9901, 'competition', 'competition', 'Compétition visée', 'Competition', 0, '[]', NULL, NULL, 7),
  (9902, 'email', 'email', 'E-mail', '', 1, '[]', NULL, NULL, 1),
  (9902, 'team', 'text', 'Équipe', '', 1, '[]', NULL, NULL, 2),
  (9903, 'email', 'email', 'E-mail', '', 1, '[]', NULL, NULL, 1);
INSERT INTO `kp_form_entry` (`id`, `form_id`, `status`, `reason`, `answers`, `email`, `created_at`, `updated_at`) VALUES
  (9951, 9901, 'confirmed', NULL, '{"email":"a@example.test","phone":"0600000000","club":"C001","team":"Alpha","players":6,"category":"SEN"}', 'a@example.test', '2020-01-02 00:00:00', '2020-01-02 00:00:00'),
  (9952, 9901, 'cancelled', NULL, '{"email":"c@example.test","club":"C002","team":"Annulée","players":6,"category":"SEN"}', 'c@example.test', '2020-01-03 00:00:00', '2020-01-03 00:00:00'),
  (9953, 9902, 'waiting', 'manual', '{"email":"b@example.test","team":"Bravo"}', 'b@example.test', '2020-01-04 00:00:00', '2020-01-04 00:00:00'),
  -- formulaire clos depuis 2020 (rétention 30 jours) : purgeable
  (9954, 9903, 'confirmed', NULL, '{"email":"old@example.test"}', 'old@example.test', '2020-01-05 00:00:00', '2020-01-05 00:00:00');

-- Reprise WordPress (CMS-09) : articles 101 (repris, image, liens), 102 (non repris → /news), 103 (brouillon WP),
-- pièce jointe 201, pages 301 / 302 (enfant) / 313 (reprise en brouillon), formulaire Ninja Forms 19.
DELETE FROM `wptest_posts`;
DELETE FROM `wptest_postmeta`;
DELETE FROM `wptest_options`;
DELETE FROM `wptest_nf3_forms`;
DELETE FROM `wptest_nf3_fields`;
DELETE FROM `wptest_nf3_field_meta`;
DELETE FROM `wptest_terms`;
DELETE FROM `wptest_term_taxonomy`;
DELETE FROM `wptest_term_relationships`;
-- WordPress installé sous /wordpress (comme kayak-polo.info) : les médias sont sous /wordpress/wp-content/uploads.
INSERT INTO `wptest_options` (`option_name`, `option_value`) VALUES
  ('permalink_structure', '/%year%/%monthnum%/%postname%/'),
  ('siteurl', 'https://www.kayak-polo.info/wordpress');
-- Catégories : 2 et 3 correspondent aux catégories du site, 1 (« Non classé ») est ignorée, 4 n'a pas d'équivalent.
INSERT INTO `wptest_terms` (`term_id`, `name`, `slug`) VALUES
  (1, 'Non classé', 'non-classe'), (2, 'Equipes de France', 'equipes-de-france'),
  (3, 'Compétitions Nationales', 'nationales'), (4, 'Divers', 'divers');
INSERT INTO `wptest_term_taxonomy` (`term_taxonomy_id`, `term_id`, `taxonomy`) VALUES
  (11, 1, 'category'), (12, 2, 'category'), (13, 3, 'category'), (14, 4, 'category'), (15, 2, 'post_tag');
INSERT INTO `wptest_term_relationships` (`object_id`, `term_taxonomy_id`) VALUES
  (101, 11), (101, 12), (101, 13), (101, 14), (101, 15);
INSERT INTO `wptest_posts` (`ID`, `post_date`, `post_content`, `post_title`, `post_excerpt`, `post_status`, `post_name`, `post_modified`, `post_parent`, `post_type`) VALUES
  (101, '2024-06-02 10:00:00', '[:fr]Premier paragraphe\n\n<img src="https://www.kayak-polo.info/wordpress/wp-content/uploads/2024/06/photo-300x200.jpg" alt="Podium"> <a href="https://www.kayak-polo.info/?p=102">ancien</a> [ninja_form id=19][:en]First paragraph\n\n<img src="https://www.kayak-polo.info/wordpress/wp-content/uploads/2024/06/photo-300x200.jpg" alt="Podium">[:]', '[:fr]Finale N1[:en]N1 final[:]', '[:fr]Le résumé[:]', 'publish', 'finale-n1', '2024-06-03 08:00:00', 0, 'post'),
  (102, '2024-05-01 10:00:00', 'Ancien', 'Ancien article', '', 'publish', 'ancien', '2024-05-01 10:00:00', 0, 'post'),
  (103, '2024-07-01 10:00:00', 'Brouillon', 'Brouillon WP', '', 'draft', 'brouillon-wp', '2024-07-01 10:00:00', 0, 'post'),
  (201, '2024-06-02 09:00:00', '', 'photo', '', 'inherit', 'photo', '2024-06-02 09:00:00', 101, 'attachment'),
  (301, '2020-01-01 10:00:00', '<h2>Règlement</h2>\nTexte du règlement <a href="/wordpress/wp-content/uploads/2024/06/reglement.pdf">PDF</a>', 'Règlement', '', 'publish', 'reglement', '2023-01-01 10:00:00', 0, 'page'),
  (302, '2020-01-01 10:00:00', 'Les arbitres', 'Arbitres', '', 'publish', 'arbitres', '2023-01-01 10:00:00', 301, 'page'),
  (313, '2015-01-01 10:00:00', 'Vieux texte', 'Historique', '', 'publish', 'historique', '2015-01-01 10:00:00', 0, 'page');
INSERT INTO `wptest_postmeta` (`post_id`, `meta_key`, `meta_value`) VALUES
  (101, '_thumbnail_id', '201'),
  (102, '_thumbnail_id', '201'),
  (101, 'rank_math_description', '[:fr]Description SEO[:]'),
  (101, 'rank_math_title', '%title% %sep% %sitename%'),
  (201, '_wp_attached_file', '2024/06/photo.jpg');
INSERT INTO `wptest_nf3_forms` (`id`, `title`) VALUES (19, 'Inscription tournoi');
INSERT INTO `wptest_nf3_fields` (`id`, `label`, `key`, `type`, `parent_id`, `required`, `order`) VALUES
  (1, 'Nom de l''équipe', 'nom_equipe', 'textbox', 19, b'1', 1),
  (2, 'Email', 'email', 'email', 19, b'1', 2),
  (3, 'Catégorie', 'categorie', 'listselect', 19, b'0', 3),
  (4, 'Envoyer', 'submit', 'submit', 19, b'0', 4);
INSERT INTO `wptest_nf3_field_meta` (`parent_id`, `key`, `value`) VALUES
  (3, 'options', 'a:2:{i:0;a:2:{s:5:"label";s:3:"U18";s:5:"value";s:3:"u18";}i:1;a:2:{s:5:"label";s:6:"Senior";s:5:"value";s:3:"sen";}}');

-- ================================================================ SCORING LIVE (refonte scoring, lot 4)
-- Jetons d'affichage des incrustations. Chaque ligne = un motif de refus (ou
-- d'acceptation) distinct, consommé par ScoringPublicEndpointsTest : un test qui échoue
-- doit se diagnostiquer en lisant le jeton, pas en déroulant une requête.
--
-- On RÉUTILISE les données existantes du site public plutôt que d'en créer :
--   * événement 77, journée 9202, match 9404 (en cours, terrain « 1 ») → portée du jeton ;
--   * événement 78 → sert de « autre événement » pour l'isolation.
-- Aucune ligne scoring_live_state n'est insérée : les tests d'accès doivent échouer AVANT
-- toute lecture de données de match, et /state doit répondre 404 sur un match sans état live.

INSERT INTO `scoring_display_token`
  (`token`, `id_event`, `pitch`, `label`, `expires_at`, `revoked_at`) VALUES
  -- valide, portée événement (tous les terrains)
  ('tsttok-valid-event-77', 77, NULL, 'Test valide evenement', '2999-12-31 23:59:59', NULL),
  -- valide mais restreint au terrain 1 : doit être refusé sur un autre terrain
  ('tsttok-valid-pitch1', 77, '1', 'Test valide terrain 1', '2999-12-31 23:59:59', NULL),
  -- expiré
  ('tsttok-expired', 77, NULL, 'Test expire', '2000-01-01 00:00:00', NULL),
  -- révoqué (expiration lointaine : c'est bien la révocation qui doit trancher)
  ('tsttok-revoked', 77, NULL, 'Test revoque', '2999-12-31 23:59:59', '2999-01-01 00:00:00'),
  -- valide, mais pour un AUTRE événement
  ('tsttok-other-event', 78, NULL, 'Test autre evenement', '2999-12-31 23:59:59', NULL);
