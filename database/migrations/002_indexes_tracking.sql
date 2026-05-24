-- Ek performans indeksleri (örnek ikinci migration)
SET NAMES utf8mb4;

CREATE INDEX idx_te_created ON tracking_events (created_at);
CREATE INDEX idx_audit_action ON audit_logs (action);
