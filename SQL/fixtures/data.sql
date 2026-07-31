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

-- ---------------------------------------------------------------------------
-- Refonte scoring (lot 4) — contrôle d'accès des incrustations.
--
-- Les jetons ci-dessous couvrent la matrice de ScoringPublicEndpointsTest :
-- chaque ligne = un motif de refus (ou d'acceptation) distinct. Les valeurs
-- sont volontairement lisibles : un test qui échoue doit se diagnostiquer en
-- lisant le jeton, pas en déroulant une requête.
-- ---------------------------------------------------------------------------

-- Rattachement journée → événement : c'est ce qui donne sa portée à un jeton.
INSERT INTO `kp_evenement_journee` (`Id_evenement`, `Id_journee`) VALUES
  (9001, 9101);

-- Les deux équipes du match : le programme d'incrustation les joint pour
-- afficher les libellés.
INSERT INTO `kp_competition_equipe`
  (`Id`, `Code_compet`, `Code_saison`, `Libelle`, `Code_club`, `Numero`, `Poule`)
VALUES
  (9201, 'TESTCOMP', '2999', 'Equipe A Test', 'TSTA', 1, 'A'),
  (9202, 'TESTCOMP', '2999', 'Equipe B Test', 'TSTB', 2, 'A');

-- Un match publié, terrain 2, dans l'événement 9001.
INSERT INTO `kp_match`
  (`Id`, `Id_journee`, `Libelle`, `Type`, `Statut`, `Date_match`, `Heure_match`,
   `Terrain`, `Numero_ordre`, `Periode`, `Id_equipeA`, `Id_equipeB`, `Publication`, `Validation`)
VALUES
  (99001, 9101, 'Match Test', 'C', 'ON', '2999-01-10', '10:00', '2', 1, 'M1', 9201, 9202, 'O', 'N');

-- Jetons d'affichage : un par cas de la matrice d'accès.
INSERT INTO `scoring_display_token`
  (`token`, `id_event`, `pitch`, `label`, `expires_at`, `revoked_at`)
VALUES
  -- valide, toute la portée de l'événement
  ('tsttok-valid-event-9001', 9001, NULL, 'Test valide evenement', '2999-12-31 23:59:59', NULL),
  -- valide mais restreint au terrain 2 : doit être refusé sur un autre terrain
  ('tsttok-valid-pitch2', 9001, '2', 'Test valide terrain 2', '2999-12-31 23:59:59', NULL),
  -- expiré
  ('tsttok-expired', 9001, NULL, 'Test expire', '2000-01-01 00:00:00', NULL),
  -- révoqué (expiration lointaine : c'est bien la révocation qui doit trancher)
  ('tsttok-revoked', 9001, NULL, 'Test revoque', '2999-12-31 23:59:59', '2999-01-01 00:00:00'),
  -- valide, mais pour un AUTRE événement
  ('tsttok-other-event', 9002, NULL, 'Test autre evenement', '2999-12-31 23:59:59', NULL);
