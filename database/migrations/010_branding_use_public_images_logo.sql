-- Eski uploads/branding yolu geçersizse public/images logosuna düşsün diye ayarı temizleyin.
-- Logo dosyanız: public/images/sakalli-logo.png (veya logo.png)

UPDATE system_settings
SET value = ''
WHERE `key` = 'branding_logo_path'
  AND value LIKE 'uploads/branding/%';

-- İsterseniz doğrudan sabitleyin:
-- UPDATE system_settings SET value = 'images/sakalli-logo.png' WHERE `key` = 'branding_logo_path';
