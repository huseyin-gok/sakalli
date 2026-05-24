-- Şablon kategorileri: ENUM yeni değerleri kabul etmiyordu (finans_kredi, banka_guvenlik vb.)
-- Kayıt sırasında SQL hatası oluşuyordu. VARCHAR ile serbest bırakıldı.

ALTER TABLE templates
    MODIFY category VARCHAR(48) NOT NULL DEFAULT 'other';
