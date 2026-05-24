-- Tüm kullanıcıları siler.
-- Etki (şemadaki FK kurallarına göre):
--   CASCADE ile silinir: user_roles, campaign_targets (kampanya hedefleri),
--   email_logs (kullanıcıya bağlı satırlar), form_submissions, credential_captures, risk_scores
--   Eğitim tabloları hâlâ varsa: training_assignments vb. kullanıcı FK CASCADE
--   SET NULL: campaigns.created_by / approved_by, templates.created_by,
--   tracking_events.user_id, audit_logs.actor_user_id
-- Giriş yapamazsınız; LDAP senkronu veya seeder ile kullanıcı yeniden oluşturun.

SET NAMES utf8mb4;

-- Yöneticilik self-FK (manager_user_id) için önce kopar (tüm silmede InnoDB genelde idare eder; yine de güvenli)
UPDATE users SET manager_user_id = NULL;

DELETE FROM users;
