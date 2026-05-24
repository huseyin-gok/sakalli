-- Manuel kullanıcı örneği (LDAP_AUTO_PROVISION kapalıysa veya rolü elle vermek için)
-- Önce rolleri yükleyin: 001_roles.sql
-- E-posta, AD'deki mail veya userPrincipalName ile birebir aynı olmalı (LDAP kullanıyorsanız).

SET NAMES utf8mb4;

INSERT INTO users (username, email, first_name, last_name, display_name, is_active, password_hash)
VALUES (
    'demo_viewer',
    'demo.viewer@example.com',
    'Demo',
    'Kullanici',
    'Demo Kullanici',
    1,
    NULL
)
ON DUPLICATE KEY UPDATE email = VALUES(email), is_active = 1;

INSERT INTO user_roles (user_id, role_id)
SELECT u.id, r.id FROM users u, roles r WHERE u.username = 'demo_viewer' AND r.slug = 'report_viewer'
ON DUPLICATE KEY UPDATE user_id = user_id;
