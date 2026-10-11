-- Migration : module éditorial et formulaires du site public (phase 4)
-- Date : 2026-10-11
-- Specs : DOC/specs/public/FEATURE_CMS.md § 3, FEATURE_FORMS.md § 4
--
-- Idempotente (CREATE TABLE IF NOT EXISTS, INSERT IGNORE) : peut être rejouée sans effet.
-- Application : docker exec -i <db> mariadb -uroot -p<pass> <base> < SQL/migrations/2026-10-11_public_site_cms_forms.sql
-- Aussi chargée par les fixtures de test d'api2 (CI et `make api2_test_fixtures`), entre schema.sql et data.sql.
-- Les écritures sont journalisées dans kp_journal (existant), comme le reste de l'administration.

-- Capacités indépendantes du niveau de profil (Q-P4-1) : 'editor' = Rédacteur du site public.
CREATE TABLE IF NOT EXISTS `kp_user_capability` (
  `user_code` varchar(8) NOT NULL,
  `capability` varchar(20) NOT NULL,
  PRIMARY KEY (`user_code`, `capability`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Médiathèque : fichiers sous HOST_MEDIA_PATH/content/, servis sous /media/content/.
CREATE TABLE IF NOT EXISTS `kp_cms_album` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `slug` varchar(150) NOT NULL,
  `title_fr` varchar(255) NOT NULL,
  `title_en` varchar(255) NOT NULL DEFAULT '',
  `cover_media_id` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_album_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `kp_cms_media` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `path` varchar(255) NOT NULL COMMENT 'relatif à content/ : 2026/10/nom.jpg',
  `mime` varchar(50) NOT NULL,
  `width` int(11) DEFAULT NULL,
  `height` int(11) DEFAULT NULL,
  `size` int(11) NOT NULL DEFAULT 0,
  `alt_fr` varchar(255) NOT NULL DEFAULT '',
  `alt_en` varchar(255) NOT NULL DEFAULT '',
  `credit` varchar(255) NOT NULL DEFAULT '',
  `album_id` int(11) DEFAULT NULL,
  `legacy_url` varchar(500) DEFAULT NULL COMMENT 'URL WordPress d''origine (import)',
  `uploaded_by` varchar(8) NOT NULL DEFAULT '',
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_media_path` (`path`),
  KEY `idx_media_album` (`album_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Catégories : liste fixe (FEATURE_CMS.md § 3.1), modifiable seulement par script.
CREATE TABLE IF NOT EXISTS `kp_cms_category` (
  `id` smallint(6) NOT NULL,
  `slug` varchar(80) NOT NULL,
  `label_fr` varchar(150) NOT NULL,
  `label_en` varchar(150) NOT NULL,
  `position` smallint(6) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_category_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `kp_cms_category` (`id`, `slug`, `label_fr`, `label_en`, `position`) VALUES
  (1, 'equipes-de-france', 'Équipes de France', 'French national teams', 1),
  (2, 'competitions-nationales', 'Compétitions nationales', 'National competitions', 2),
  (3, 'competitions-regionales-open-internationales', 'Compétitions régionales, open et internationales', 'Regional, open and international competitions', 3),
  (4, 'reglements-vie-federale', 'Règlements et vie fédérale', 'Rules and federation life', 4);

CREATE TABLE IF NOT EXISTS `kp_cms_article` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `slug` varchar(150) NOT NULL,
  `title_fr` varchar(255) NOT NULL,
  `title_en` varchar(255) NOT NULL DEFAULT '',
  `lead_fr` text NOT NULL,
  `lead_en` text NOT NULL,
  `body_fr` mediumtext NOT NULL,
  `body_en` mediumtext NOT NULL,
  `cover_media_id` int(11) DEFAULT NULL,
  `status` varchar(10) NOT NULL DEFAULT 'draft' COMMENT 'draft | published | archived',
  `published_at` datetime DEFAULT NULL COMMENT 'programmable : visible quand <= maintenant',
  `signature` varchar(150) NOT NULL DEFAULT '',
  `author_code` varchar(8) NOT NULL DEFAULT '' COMMENT 'interne, jamais exposé',
  `seo_title_fr` varchar(255) NOT NULL DEFAULT '',
  `seo_title_en` varchar(255) NOT NULL DEFAULT '',
  `seo_description_fr` varchar(300) NOT NULL DEFAULT '',
  `seo_description_en` varchar(300) NOT NULL DEFAULT '',
  `legacy_wp_id` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  `updated_by` varchar(8) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_article_slug` (`slug`),
  UNIQUE KEY `uniq_article_wp` (`legacy_wp_id`),
  KEY `idx_article_published` (`status`, `published_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `kp_cms_article_category` (
  `article_id` int(11) NOT NULL,
  `category_id` smallint(6) NOT NULL,
  PRIMARY KEY (`article_id`, `category_id`),
  KEY `idx_article_category` (`category_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Liens vers les pages de résultats : competition = « {saison}/{code} », event = id, club = code.
CREATE TABLE IF NOT EXISTS `kp_cms_article_link` (
  `article_id` int(11) NOT NULL,
  `kind` varchar(12) NOT NULL COMMENT 'competition | event | club',
  `ref` varchar(40) NOT NULL,
  PRIMARY KEY (`article_id`, `kind`, `ref`),
  KEY `idx_article_link_ref` (`kind`, `ref`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `kp_cms_page` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `slug` varchar(150) NOT NULL,
  `parent_id` int(11) DEFAULT NULL,
  `title_fr` varchar(255) NOT NULL,
  `title_en` varchar(255) NOT NULL DEFAULT '',
  `body_fr` mediumtext NOT NULL,
  `body_en` mediumtext NOT NULL,
  `status` varchar(10) NOT NULL DEFAULT 'draft' COMMENT 'draft | published | archived',
  `seo_title_fr` varchar(255) NOT NULL DEFAULT '',
  `seo_title_en` varchar(255) NOT NULL DEFAULT '',
  `seo_description_fr` varchar(300) NOT NULL DEFAULT '',
  `seo_description_en` varchar(300) NOT NULL DEFAULT '',
  `legacy_wp_id` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  `updated_by` varchar(8) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_page_slug` (`slug`),
  UNIQUE KEY `uniq_page_wp` (`legacy_wp_id`),
  KEY `idx_page_parent` (`parent_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Menu « Le kayak-polo » : deux niveaux ; cible = page (id), article (id) ou URL.
CREATE TABLE IF NOT EXISTS `kp_cms_menu_item` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `parent_id` int(11) DEFAULT NULL,
  `position` smallint(6) NOT NULL DEFAULT 0,
  `label_fr` varchar(150) NOT NULL,
  `label_en` varchar(150) NOT NULL DEFAULT '',
  `target_kind` varchar(10) NOT NULL COMMENT 'page | article | url',
  `target_ref` varchar(500) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_menu_parent` (`parent_id`, `position`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Blocs d'accueil : featured | latest_news | alert | partners ; config JSON propre à chaque bloc.
CREATE TABLE IF NOT EXISTS `kp_cms_home_block` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `kind` varchar(20) NOT NULL,
  `position` smallint(6) NOT NULL DEFAULT 0,
  `enabled` tinyint(1) NOT NULL DEFAULT 1,
  `config` longtext NOT NULL DEFAULT '{}',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `kp_cms_home_block` (`id`, `kind`, `position`, `enabled`, `config`) VALUES
  (1, 'alert', 1, 0, '{"level":"info","text_fr":"","text_en":"","from":null,"to":null}'),
  (2, 'featured', 2, 1, '{"articles":[]}'),
  (3, 'latest_news', 3, 1, '{"count":3}'),
  (4, 'partners', 4, 0, '{"partners":[]}');

CREATE TABLE IF NOT EXISTS `kp_cms_redirect` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `from_path` varchar(255) NOT NULL,
  `to_path` varchar(255) NOT NULL,
  `code` smallint(6) NOT NULL DEFAULT 301,
  `hits` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_redirect_from` (`from_path`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Formulaires d'inscription (FEATURE_FORMS.md).
CREATE TABLE IF NOT EXISTS `kp_form` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `slug` varchar(150) NOT NULL,
  `title_fr` varchar(255) NOT NULL,
  `title_en` varchar(255) NOT NULL DEFAULT '',
  `intro_fr` mediumtext NOT NULL,
  `intro_en` mediumtext NOT NULL,
  `status` varchar(10) NOT NULL DEFAULT 'draft' COMMENT 'draft | published',
  `opens_at` datetime DEFAULT NULL,
  `closes_at` datetime DEFAULT NULL,
  `capacity` int(11) DEFAULT NULL COMMENT 'places confirmées ; NULL = illimité',
  `waiting_list` tinyint(1) NOT NULL DEFAULT 1,
  `confirmation_mode` varchar(10) NOT NULL DEFAULT 'auto' COMMENT 'auto | manual',
  `payment_url` varchar(500) NOT NULL DEFAULT '',
  `notify_emails` varchar(500) NOT NULL DEFAULT '' COMMENT 'organisateurs, séparés par des virgules',
  `confirmation_text_fr` text NOT NULL,
  `confirmation_text_en` text NOT NULL,
  `public_list` tinyint(1) NOT NULL DEFAULT 0,
  `public_columns` varchar(1000) NOT NULL DEFAULT '[]' COMMENT 'JSON : clés des champs publiés',
  `purpose_fr` text NOT NULL,
  `purpose_en` text NOT NULL,
  `retention_days` smallint(6) NOT NULL DEFAULT 90,
  `event_id` int(11) DEFAULT NULL,
  `competition_season` char(4) DEFAULT NULL,
  `competition_code` varchar(12) DEFAULT NULL,
  `legacy_nf_id` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  `updated_by` varchar(8) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_form_slug` (`slug`),
  UNIQUE KEY `uniq_form_nf` (`legacy_nf_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `kp_form_field` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `form_id` int(11) NOT NULL,
  `field_key` varchar(40) NOT NULL,
  `type` varchar(20) NOT NULL COMMENT 'text | textarea | email | phone | number | date | select | radio | checkbox | club | competition',
  `label_fr` varchar(255) NOT NULL,
  `label_en` varchar(255) NOT NULL DEFAULT '',
  `required` tinyint(1) NOT NULL DEFAULT 0,
  `help_fr` varchar(500) NOT NULL DEFAULT '',
  `help_en` varchar(500) NOT NULL DEFAULT '',
  `options` longtext NOT NULL DEFAULT '[]' COMMENT 'JSON [{value,label_fr,label_en}]',
  `min_value` decimal(12,2) DEFAULT NULL,
  `max_value` decimal(12,2) DEFAULT NULL,
  `position` smallint(6) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_form_field_key` (`form_id`, `field_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `kp_form_entry` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `form_id` int(11) NOT NULL,
  `status` varchar(10) NOT NULL COMMENT 'confirmed | waiting | cancelled',
  `reason` varchar(10) DEFAULT NULL COMMENT 'waiting : full | manual',
  `answers` longtext NOT NULL COMMENT 'JSON validé {clé: valeur}',
  `email` varchar(255) NOT NULL DEFAULT '',
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_form_entry` (`form_id`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Journal des e-mails envoyés (remplace Email Log) ; le corps est effacé après 30 jours (purge).
CREATE TABLE IF NOT EXISTS `kp_mail_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `form_id` int(11) DEFAULT NULL,
  `entry_id` int(11) DEFAULT NULL,
  `recipient` varchar(255) NOT NULL,
  `subject` varchar(255) NOT NULL,
  `body` text DEFAULT NULL,
  `status` varchar(10) NOT NULL COMMENT 'sent | failed',
  `error` varchar(255) NOT NULL DEFAULT '',
  `sent_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_mail_form` (`form_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
