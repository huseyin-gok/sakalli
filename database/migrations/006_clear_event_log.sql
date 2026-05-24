-- Olay günlüğü (tracking_events) — tüm takip olaylarını siler, AUTO_INCREMENT sıfırlanır.
-- Dashboard / raporlardaki geçmiş olay sayıları düşer.
-- risk_scores tablosu eski kırılımları tutabilir; tutarlılık için aşağıdaki ikinci satırı da açabilirsiniz.

SET NAMES utf8mb4;

TRUNCATE TABLE tracking_events;

-- İsteğe bağlı: risk skorlarını da sıfırlamak (kullanıcı başına yeniden hesap, yeni olay gelince oluşur)
-- TRUNCATE TABLE risk_scores;
