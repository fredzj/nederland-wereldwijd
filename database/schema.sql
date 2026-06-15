-- ---------------------------------------------------------------------------
-- Nederland Wereldwijd - databaseschema (MariaDB 10.6.20)
--
-- Slaat landen, reisadviezen en nood-informatie op die zijn opgehaald bij de
-- open data API van Nederland Wereldwijd.
--
-- Gebruik:
--   mysql -u root -p < database/schema.sql
-- ---------------------------------------------------------------------------

CREATE DATABASE IF NOT EXISTS `nederlandwereldwijd`
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_520_ci;

USE `nederlandwereldwijd`;

-- ---------------------------------------------------------------------------
-- Landen
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `countries` (
    `iso_code`              VARCHAR(8)   NOT NULL COMMENT 'ISO 3166-1 alpha-3, bijv. VIR',
    `type`                  VARCHAR(32)  NULL     COMMENT 'bijv. land',
    `location`              VARCHAR(255) NOT NULL COMMENT 'Weergavenaam, bijv. Amerikaanse Maagdeneilanden',
    `location_key`          VARCHAR(255) NOT NULL COMMENT 'Sleutel in kleine letters',
    `data_url`              VARCHAR(512) NULL,
    `travel_advice_url`     VARCHAR(512) NULL,
    `nl_representation_url` VARCHAR(512) NULL,
    `created_at`            DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`            DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`iso_code`),
    KEY `idx_countries_location_key` (`location_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

-- ---------------------------------------------------------------------------
-- Reisadviezen
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `travel_advice` (
    `id`                     VARCHAR(128) NOT NULL COMMENT 'API-id van het reisadvies',
    `country_iso_code`       VARCHAR(8)   NULL     COMMENT 'Verwijzing naar countries.iso_code',
    `type`                   VARCHAR(64)  NULL,
    `canonical`              VARCHAR(512) NULL,
    `data_url`               VARCHAR(512) NULL,
    `title`                  VARCHAR(512) NULL,
    `introduction`           TEXT         NULL,
    `location`               VARCHAR(255) NULL,
    `location_key`           VARCHAR(255) NULL,
    `classification`         VARCHAR(64)  NULL     COMMENT 'Kleurcodering reisadvies (groen/geel/oranje/rood)',
    `modification_date`      VARCHAR(64)  NULL,
    `modifications`          TEXT         NULL,
    `content`                LONGTEXT     NULL     COMMENT 'JSON met de inhoudsblokken',
    `files`                  LONGTEXT     NULL     COMMENT 'JSON met bijbehorende bestanden/kaarten',
    `additional_information` TEXT         NULL,
    `language`               VARCHAR(16)  NULL,
    `license`                VARCHAR(255) NULL,
    `issued_at`              DATETIME     NULL,
    `available_at`           DATETIME     NULL,
    `last_modified_at`       DATETIME     NULL,
    `created_at`             DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`             DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_travel_advice_country` (`country_iso_code`),
    KEY `idx_travel_advice_location_key` (`location_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

-- ---------------------------------------------------------------------------
-- Nood-informatie (hulp bij nood / noodhulp)
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `emergency_infos` (
    `id`               VARCHAR(64)  NOT NULL COMMENT 'UUID van het noodhulp-item',
    `type`             VARCHAR(64)  NULL,
    `canonical`        VARCHAR(512) NULL,
    `data_url`         VARCHAR(512) NULL,
    `title`            VARCHAR(512) NULL,
    `introduction`     TEXT         NULL,
    `content`          LONGTEXT     NULL     COMMENT 'JSON met de inhoudsblokken',
    `parent`           VARCHAR(64)  NULL     COMMENT 'Bovenliggend item',
    `order_no`         VARCHAR(32)  NULL,
    `locations`        LONGTEXT     NULL     COMMENT 'JSON met gekoppelde locaties',
    `languages`        LONGTEXT     NULL     COMMENT 'JSON met talen',
    `license`          VARCHAR(255) NULL,
    `issued_at`        DATETIME     NULL,
    `available_at`     DATETIME     NULL,
    `last_modified_at` DATETIME     NULL,
    `created_at`       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_emergency_parent` (`parent`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;
