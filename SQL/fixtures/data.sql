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

-- Événement 77 (publié, Id < 3000 = tournoi kp_evenement) : J2 (+ Pause) de RCH et toutes les journées de RCP.
-- Événement 78 (NON publié) : J1 de RCH.
INSERT INTO `kp_evenement` (`Id`, `Libelle`, `Lieu`, `Date_debut`, `Date_fin`, `Publication`, `logo`, `app`) VALUES
  (77, 'Tournoi Résultats', 'Rivecity', '2999-04-01', '2999-04-02', 'O', 'logo/resultats.png', 'O'),
  (78, 'Tournoi Résultats Masqué', 'Lacville', '2999-03-01', '2999-03-02', 'N', NULL, 'O');
INSERT INTO `kp_evenement_journee` (`Id_evenement`, `Id_journee`) VALUES
  (77, 9202), (77, 9204), (77, 9211), (77, 9212), (77, 9213),
  (78, 9201);

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
  (9303, 9504, 'CHARLIE', 'Dan', 'M', 'SEN', 10, '-');

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
