-- Sahte oturum formu (kimlik yakalama simülasyonu) — landing_pages + credential_captures
-- MySQL 8+ / MariaDB 10.5+
-- Uyarı: password_entered düz metin saklanır; yalnızca kontrollü simülasyon ortamında kullanın.

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS credential_captures (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    campaign_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    token VARCHAR(128) NULL,
    username_entered VARCHAR(255) NOT NULL,
    password_entered VARCHAR(512) NOT NULL COMMENT 'Simülasyon düz metin — üretimde erişimi kısıtlayın',
    ip_address VARCHAR(45) NULL,
    user_agent VARCHAR(512) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_cc_campaign_time (campaign_id, created_at),
    INDEX idx_cc_user (user_id),
    CONSTRAINT fk_cc_campaign FOREIGN KEY (campaign_id) REFERENCES campaigns (id) ON DELETE CASCADE,
    CONSTRAINT fk_cc_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE landing_pages
    ADD COLUMN credential_capture TINYINT(1) NOT NULL DEFAULT 0
        COMMENT '1: sahte kullanici/sifre formu (POST /track/credentials)'
        AFTER show_feedback_form;
