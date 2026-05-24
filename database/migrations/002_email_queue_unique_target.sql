-- Aynı hedef için iki kuyruk satırını engeller (çift gönderim önleme)
-- Mevcut veritabanında yinelenen campaign_target_id varsa önce temizleyin.

ALTER TABLE email_queue
    ADD UNIQUE KEY uq_eq_campaign_target (campaign_target_id);
