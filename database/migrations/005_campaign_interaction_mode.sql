-- Kampanya bazında tıklama sonrası akış seçimi
-- NULL: landing şablonundaki varsayılan davranış
-- awareness_form: bilgilendirme + geri bildirim formu
-- awareness_noform: bilgilendirme (form kapalı)
-- credential_capture: sahte oturum açma formu

ALTER TABLE campaigns
    ADD COLUMN interaction_mode VARCHAR(32) NULL DEFAULT NULL
    COMMENT 'awareness_form|awareness_noform|credential_capture|NULL(default)'
    AFTER landing_page_id;
