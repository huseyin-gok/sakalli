-- AD Sync özelliği kaldırıldı
-- Mevcut kurulumlarda artık kullanılmayan log tablosunu temizler

DROP TABLE IF EXISTS ad_sync_logs;
