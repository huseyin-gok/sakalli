-- Mevcut veritabanından eğitim modülünü kaldırır (sıra: FK bağımlılıkları).
-- Yeni kurulumlar için 001_initial_schema.sql güncellendi; bu betik yalnızca eski DB'ler içindir.

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS training_results;
DROP TABLE IF EXISTS training_assignments;
DROP TABLE IF EXISTS trainings;

DELETE ur FROM user_roles ur
INNER JOIN roles r ON r.id = ur.role_id
WHERE r.slug = 'training_manager';

DELETE FROM roles WHERE slug = 'training_manager';

SET FOREIGN_KEY_CHECKS = 1;
