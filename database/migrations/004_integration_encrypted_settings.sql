-- Entegrasyon ayarları (LDAP / SMTP) mevcut system_settings tablosunda tutulur.
-- Anahtarlar: integration_ldap_v1, integration_smtp_v1 (value = şifreli JSON, is_secret = 1)
-- Bu dosya şema değişikliği gerektirmez; yalnızca dokümantasyon amaçlıdır.

-- İsteğe bağlı: eski ortamda tablo yoksa 001_initial_schema.sql çalıştırılmış olmalıdır.
