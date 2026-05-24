-- Kampanya bazlı e-posta görünen gönderen adı (From display name)
-- Boş/null ise Ayarlar → SMTP içindeki SMTP_FROM_NAME kullanılır.

ALTER TABLE campaigns
    ADD COLUMN smtp_from_name VARCHAR(191) NULL COMMENT 'Kampanya e-postasında görünen gönderen adı' AFTER tracking_base_url;
