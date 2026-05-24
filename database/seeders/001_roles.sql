SET NAMES utf8mb4;

INSERT INTO roles (slug, name, description) VALUES
('super_admin', 'Super Admin', 'Tam yetki'),
('security_manager', 'Güvenlik Yöneticisi', 'Kampanya ve şablon yönetimi'),
('report_viewer', 'Rapor Görüntüleyici', 'Salt okunur raporlar')
ON DUPLICATE KEY UPDATE name = VALUES(name);
