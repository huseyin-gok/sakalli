-- Logo yolu (Ayarlar → Görünüm / logo). Boş değer = varsayılan images/sakalli-logo.png
SET NAMES utf8mb4;

INSERT IGNORE INTO system_settings (`key`, value, is_secret)
VALUES ('branding_logo_path', '', 0);
