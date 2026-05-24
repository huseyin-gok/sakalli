-- Bilgilendirme (landing) şablonları için tarayıcı başlığı alanı
-- MySQL 8+ / MariaDB 10.5+

SET NAMES utf8mb4;

ALTER TABLE landing_pages
    ADD COLUMN page_title VARCHAR(255) NULL DEFAULT NULL COMMENT 'HTML title; empty uses app default' AFTER slug;
